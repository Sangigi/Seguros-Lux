<?php
declare(strict_types=1);

namespace Database;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Migrator — runner de migraciones versionadas para Seguros Lux.
 *
 * Antes de esto las migraciones eran archivos .sql sueltos que había que
 * aplicar a mano, sin registro de cuáles ya corrieron. Eso hacía imposible
 * saber el estado real de una base de datos en producción.
 *
 * Este runner:
 *   - Lleva una tabla `migraciones` con lo ya aplicado (versión, hash, fecha).
 *   - Ejecuta en orden por número de versión.
 *   - Detecta si un archivo cambió después de aplicarse (hash distinto).
 *   - Soporta migraciones .php (con up()/down()) y .sql (solo up).
 *   - Permite rollback de la última migración aplicada.
 *
 * Uso: php bin/migrate.php [status|up|down|fresh-check]
 */
final class Migrator
{
    private PDO $db;
    private string $path;

    /** Migraciones .sql heredadas que pudieron aplicarse a mano antes de existir el runner. */
    private const LEGACY_SQL = [
        '001_mail_queue.sql',
        '002_renovacion_notificaciones_programadas.sql',
        '003_storage_locks_dir.sql',
        '004_add_vencida_to_citas.sql',
    ];

    public function __construct(?PDO $db = null, ?string $path = null)
    {
        $this->db   = $db   ?? Connection::getConnection();
        $this->path = $path ?? __DIR__ . '/migrations';
        $this->ensureRegistryTable();
    }

    // ── Registro ──────────────────────────────────────────────────────────────

    private function ensureRegistryTable(): void
    {
        $this->db->exec(
            'CREATE TABLE IF NOT EXISTS migraciones (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                version     VARCHAR(20)  NOT NULL,
                archivo     VARCHAR(255) NOT NULL,
                hash        CHAR(64)     NOT NULL,
                aplicada_en DATETIME     NOT NULL,
                UNIQUE KEY uq_version (version)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    /** @return array<string,array{archivo:string,hash:string,aplicada_en:string}> */
    public function aplicadas(): array
    {
        $out = [];
        foreach ($this->db->query('SELECT version, archivo, hash, aplicada_en FROM migraciones')->fetchAll() as $r) {
            $out[(string) $r['version']] = [
                'archivo'     => (string) $r['archivo'],
                'hash'        => (string) $r['hash'],
                'aplicada_en' => (string) $r['aplicada_en'],
            ];
        }
        return $out;
    }

    /**
     * Migraciones disponibles en disco, ordenadas por versión.
     *
     * @return array<string,string> version => ruta absoluta
     */
    public function disponibles(): array
    {
        $files = glob($this->path . '/*.{php,sql}', GLOB_BRACE) ?: [];
        $out   = [];

        foreach ($files as $file) {
            $base = basename($file);
            if (!preg_match('/^(\d{3,})_/', $base, $m)) {
                continue;
            }
            $out[$m[1]] = $file;
        }

        ksort($out, SORT_STRING);
        return $out;
    }

    /** @return array<int,array{version:string,archivo:string,estado:string}> */
    public function status(): array
    {
        $aplicadas = $this->aplicadas();
        $rows      = [];

        foreach ($this->disponibles() as $version => $file) {
            $base = basename($file);

            if (!isset($aplicadas[$version])) {
                $estado = 'pendiente';
            } elseif ($aplicadas[$version]['hash'] !== $this->hash($file)) {
                $estado = 'MODIFICADA tras aplicarse';
            } else {
                $estado = 'aplicada ' . $aplicadas[$version]['aplicada_en'];
            }

            $rows[] = ['version' => $version, 'archivo' => $base, 'estado' => $estado];
        }

        return $rows;
    }

    // ── Ejecución ─────────────────────────────────────────────────────────────

    /**
     * Aplica todas las migraciones pendientes.
     *
     * @param  callable(string):void|null $log
     * @return array<int,string> versiones aplicadas en esta corrida
     */
    public function up(?callable $log = null): array
    {
        $log ??= static fn(string $m) => null;

        $aplicadas = $this->aplicadas();
        $corridas  = [];

        // Las .sql heredadas pudieron aplicarse a mano. Si sus objetos ya existen,
        // se registran sin re-ejecutarlas para no chocar con la base real.
        $this->adoptarLegacy($aplicadas, $log);
        $aplicadas = $this->aplicadas();

        foreach ($this->disponibles() as $version => $file) {
            if (isset($aplicadas[$version])) {
                continue;
            }

            $base = basename($file);
            $log("→ Aplicando {$base}");

            try {
                $this->ejecutarUp($file);
            } catch (Throwable $e) {
                throw new RuntimeException(
                    "Falló la migración {$base}: " . $e->getMessage(),
                    0,
                    $e
                );
            }

            $this->registrar($version, $base, $this->hash($file));
            $corridas[] = $version;
            $log("  ✓ {$base} aplicada");
        }

        if ($corridas === []) {
            $log('Sin migraciones pendientes.');
        }

        return $corridas;
    }

    /** Revierte la última migración aplicada (solo las .php con down()). */
    public function down(?callable $log = null): ?string
    {
        $log ??= static fn(string $m) => null;

        $row = $this->db
            ->query('SELECT version, archivo FROM migraciones ORDER BY version DESC LIMIT 1')
            ->fetch();

        if (!$row) {
            $log('No hay migraciones que revertir.');
            return null;
        }

        $version = (string) $row['version'];
        $file    = $this->path . '/' . $row['archivo'];

        if (!is_file($file) || !str_ends_with($file, '.php')) {
            throw new RuntimeException(
                "La migración {$row['archivo']} no se puede revertir automáticamente (solo .php con down())."
            );
        }

        $mig = require $file;
        if (!is_array($mig) || !isset($mig['down']) || !is_callable($mig['down'])) {
            throw new RuntimeException("La migración {$row['archivo']} no define down().");
        }

        $log("→ Revirtiendo {$row['archivo']}");
        ($mig['down'])($this->db, new SchemaHelper($this->db));

        $this->db->prepare('DELETE FROM migraciones WHERE version = ?')->execute([$version]);
        $log("  ✓ {$row['archivo']} revertida");

        return $version;
    }

    // ── Internos ──────────────────────────────────────────────────────────────

    private function ejecutarUp(string $file): void
    {
        if (str_ends_with($file, '.php')) {
            $mig = require $file;
            if (!is_array($mig) || !isset($mig['up']) || !is_callable($mig['up'])) {
                throw new RuntimeException('La migración debe devolver un array con la clave "up" invocable.');
            }
            ($mig['up'])($this->db, new SchemaHelper($this->db));
            return;
        }

        $sql = file_get_contents($file);
        if ($sql === false || trim($sql) === '') {
            return;
        }

        foreach ($this->partirSql($sql) as $stmt) {
            $this->db->exec($stmt);
        }
    }

    /**
     * Parte un archivo .sql en sentencias. Respeta comillas y comentarios
     * para no cortar un punto y coma que viva dentro de una cadena.
     *
     * @return array<int,string>
     */
    private function partirSql(string $sql): array
    {
        $out     = [];
        $buf     = '';
        $quote   = null;
        $len     = strlen($sql);

        for ($i = 0; $i < $len; $i++) {
            $ch   = $sql[$i];
            $next = $sql[$i + 1] ?? '';

            if ($quote === null && $ch === '-' && $next === '-') {
                while ($i < $len && $sql[$i] !== "\n") { $i++; }
                continue;
            }

            if ($quote === null && $ch === '/' && $next === '*') {
                $i += 2;
                while ($i < $len && !($sql[$i] === '*' && ($sql[$i + 1] ?? '') === '/')) { $i++; }
                $i++;
                continue;
            }

            if ($quote !== null && $ch === '\\') {
                $buf .= $ch . $next;
                $i++;
                continue;
            }

            if ($ch === "'" || $ch === '"' || $ch === '`') {
                if ($quote === null)      { $quote = $ch; }
                elseif ($quote === $ch)   { $quote = null; }
            }

            if ($ch === ';' && $quote === null) {
                if (trim($buf) !== '') { $out[] = trim($buf); }
                $buf = '';
                continue;
            }

            $buf .= $ch;
        }

        if (trim($buf) !== '') {
            $out[] = trim($buf);
        }

        return $out;
    }

    /**
     * Registra como aplicadas las migraciones .sql heredadas cuyos objetos
     * ya existen en la base, para no intentar recrearlos.
     */
    private function adoptarLegacy(array $aplicadas, callable $log): void
    {
        $marcadores = [
            '001_mail_queue.sql'                            => 'mail_queue',
            '002_renovacion_notificaciones_programadas.sql' => 'renovacion_notificaciones_programadas',
            '004_add_vencida_to_citas.sql'                  => 'citas',
        ];

        $helper = new SchemaHelper($this->db);

        foreach (self::LEGACY_SQL as $base) {
            if (!preg_match('/^(\d{3,})_/', $base, $m)) {
                continue;
            }
            $version = $m[1];

            if (isset($aplicadas[$version]) || !is_file($this->path . '/' . $base)) {
                continue;
            }

            $tabla = $marcadores[$base] ?? null;
            if ($tabla !== null && $helper->tablaExiste($tabla)) {
                $this->registrar($version, $base, $this->hash($this->path . '/' . $base));
                $log("  · {$base} ya estaba aplicada en la base; registrada sin re-ejecutar");
            }
        }
    }

    private function registrar(string $version, string $archivo, string $hash): void
    {
        $this->db
            ->prepare('INSERT INTO migraciones (version, archivo, hash, aplicada_en) VALUES (?, ?, ?, NOW())')
            ->execute([$version, $archivo, $hash]);
    }

    private function hash(string $file): string
    {
        return hash_file('sha256', $file) ?: '';
    }
}
