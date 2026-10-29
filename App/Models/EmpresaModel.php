<?php
declare(strict_types=1);

namespace App\Models;

use App\Security\Tenant;
use PDO;

/**
 * EmpresaModel — las empresas (tenants) de la plataforma.
 *
 * Esta tabla NO se filtra por empresa: es la tabla que define las empresas.
 * Es una de las pocas excepciones al aislamiento automático, y por eso lo
 * declara explícitamente en vez de heredarlo.
 *
 * Quién puede tocarla lo decide Roles::EMPRESAS_*, no este modelo.
 */
class EmpresaModel extends BaseModel
{
    protected string $table = 'empresas';
    protected bool   $tenantScoped = false;
    protected int    $cacheTtl = 120;

    protected array $defaultColumns = [
        'id', 'nombre', 'slug', 'razon_social', 'rfc', 'correo_contacto',
        'telefono', 'direccion', 'logo', 'color_primario', 'plan',
        'max_usuarios', 'activo', 'notas', 'creado_en',
    ];

    protected array $fillable = [
        'nombre'          => 'string',
        'slug'            => 'string',
        'razon_social'    => 'string',
        'rfc'             => 'string',
        'correo_contacto' => 'string',
        'telefono'        => 'string',
        'direccion'       => 'string',
        'logo'            => 'string',
        'color_primario'  => 'string',
        'plan'            => 'string',
        'max_usuarios'    => 'int',
        'activo'          => 'int',
        'notas'           => 'string',
        'creada_por'      => 'int',
    ];

    public const PLANES = [
        'basico'    => ['label' => 'Básico',    'max_usuarios' => 10],
        'profesional' => ['label' => 'Profesional', 'max_usuarios' => 50],
        'ilimitado' => ['label' => 'Ilimitado', 'max_usuarios' => 999],
    ];

    // ── Lectura ───────────────────────────────────────────────────────────────

    /**
     * Listado para el panel del propietario, con el conteo de usuarios y
     * clientes de cada empresa.
     *
     * @return array<int,array<string,mixed>>
     */
    public function listar(bool $incluirInactivas = true): array
    {
        $sql = 'SELECT e.id, e.nombre, e.slug, e.rfc, e.correo_contacto, e.telefono,
                       e.plan, e.max_usuarios, e.activo, e.creado_en,
                       (SELECT COUNT(*) FROM agentes a
                         WHERE a.empresa_id = e.id AND COALESCE(a.activo,1) = 1) AS usuarios,
                       (SELECT COUNT(*) FROM clientes c
                         WHERE c.empresa_id = e.id)                              AS clientes
                FROM empresas e
                WHERE e.eliminado_en IS NULL';

        if (!$incluirInactivas) {
            $sql .= ' AND e.activo = 1';
        }

        $sql .= ' ORDER BY e.activo DESC, e.nombre ASC';

        return $this->db()->query($sql)->fetchAll();
    }

    public function porSlug(string $slug): ?array
    {
        $stmt = $this->db()->prepare(
            'SELECT * FROM empresas WHERE slug = ? AND eliminado_en IS NULL LIMIT 1'
        );
        $stmt->execute([$slug]);

        return $stmt->fetch() ?: null;
    }

    public function activa(int $id): bool
    {
        $stmt = $this->db()->prepare(
            'SELECT activo FROM empresas WHERE id = ? AND eliminado_en IS NULL LIMIT 1'
        );
        $stmt->execute([$id]);
        $fila = $stmt->fetch();

        return $fila !== false && (int) $fila['activo'] === 1;
    }

    /** Usuarios activos de una empresa, para comparar contra su tope de plan. */
    public function contarUsuarios(int $empresaId): int
    {
        $stmt = $this->db()->prepare(
            'SELECT COUNT(*) FROM agentes WHERE empresa_id = ? AND COALESCE(activo,1) = 1'
        );
        $stmt->execute([$empresaId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * True si la empresa todavía puede dar de alta un usuario más.
     * Cuenta también las invitaciones pendientes: si no, se podrían emitir
     * 50 invitaciones contra un tope de 10 y reventarlo al aceptarlas.
     */
    public function admiteOtroUsuario(int $empresaId): bool
    {
        $empresa = $this->find($empresaId, ['id', 'max_usuarios']);
        if ($empresa === null) {
            return false;
        }

        $tope = (int) $empresa['max_usuarios'];
        if ($tope <= 0) {
            return true;
        }

        $stmt = $this->db()->prepare(
            "SELECT COUNT(*) FROM invitaciones
             WHERE empresa_id = ? AND estado = 'pendiente' AND expira_en > NOW()"
        );
        $stmt->execute([$empresaId]);
        $pendientes = (int) $stmt->fetchColumn();

        return ($this->contarUsuarios($empresaId) + $pendientes) < $tope;
    }

    // ── Escritura ─────────────────────────────────────────────────────────────

    /**
     * Genera un slug único a partir del nombre.
     * El slug identifica a la empresa en URLs y correos, así que no puede
     * repetirse ni contener caracteres problemáticos.
     */
    public function generarSlug(string $nombre): string
    {
        $base = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nombre) ?: $nombre;
        $base = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $base) ?? '');
        $base = trim($base, '-');

        if ($base === '') {
            $base = 'empresa';
        }
        $base = substr($base, 0, 60);

        $slug = $base;
        $n    = 1;

        $stmt = $this->db()->prepare('SELECT 1 FROM empresas WHERE slug = ? LIMIT 1');

        while (true) {
            $stmt->execute([$slug]);
            if (!$stmt->fetch()) {
                return $slug;
            }
            $slug = $base . '-' . (++$n);
        }
    }

    /**
     * Da de alta una empresa. Devuelve su id, o 0 si el nombre está vacío.
     *
     * @param array<string,mixed> $datos
     */
    public function crear(array $datos, ?int $creadaPor = null): int
    {
        $nombre = trim((string) ($datos['nombre'] ?? ''));
        if ($nombre === '') {
            return 0;
        }

        $plan = (string) ($datos['plan'] ?? 'basico');
        if (!isset(self::PLANES[$plan])) {
            $plan = 'basico';
        }

        $maxUsuarios = isset($datos['max_usuarios']) && (int) $datos['max_usuarios'] > 0
            ? (int) $datos['max_usuarios']
            : self::PLANES[$plan]['max_usuarios'];

        // La tabla de empresas no está aislada, pero el insert de BaseModel
        // exige contexto. Aquí lo decimos explícitamente: es una operación
        // de plataforma.
        return Tenant::sinRestriccion(fn() => $this->insert([
            'nombre'          => $nombre,
            'slug'            => $this->generarSlug($nombre),
            'razon_social'    => (string) ($datos['razon_social']    ?? ''),
            'rfc'             => strtoupper(trim((string) ($datos['rfc'] ?? ''))),
            'correo_contacto' => trim((string) ($datos['correo_contacto'] ?? '')),
            'telefono'        => (string) ($datos['telefono']        ?? ''),
            'direccion'       => (string) ($datos['direccion']       ?? ''),
            'color_primario'  => (string) ($datos['color_primario']  ?? ''),
            'plan'            => $plan,
            'max_usuarios'    => $maxUsuarios,
            'activo'          => 1,
            'notas'           => (string) ($datos['notas'] ?? ''),
            'creada_por'      => $creadaPor ?? 0,
        ]));
    }

    public function actualizar(int $id, array $datos): bool
    {
        $permitidos = array_intersect_key($datos, array_flip([
            'nombre', 'razon_social', 'rfc', 'correo_contacto', 'telefono',
            'direccion', 'logo', 'color_primario', 'plan', 'max_usuarios', 'notas',
        ]));

        if ($permitidos === []) {
            return false;
        }

        $permitidos['actualizado_en'] = date('Y-m-d H:i:s');

        $ok = Tenant::sinRestriccion(fn() => $this->update($id, $permitidos));

        if ($ok) {
            $this->db()
                ->prepare('UPDATE empresas SET actualizado_en = NOW() WHERE id = ?')
                ->execute([$id]);
        }

        return $ok;
    }

    /**
     * Suspende o reactiva una empresa.
     * Suspender no borra nada: los usuarios dejan de poder entrar y sus
     * sesiones abiertas se cortan en la siguiente petición.
     */
    public function cambiarEstado(int $id, bool $activa): bool
    {
        return $this->db()
            ->prepare('UPDATE empresas SET activo = ?, actualizado_en = NOW() WHERE id = ?')
            ->execute([$activa ? 1 : 0, $id]);
    }

    /**
     * Baja lógica. No se borra físicamente: una empresa arrastra clientes,
     * pólizas y expedientes, y eso no debe desaparecer por un clic.
     */
    public function eliminar(int $id): bool
    {
        return $this->db()
            ->prepare('UPDATE empresas SET activo = 0, eliminado_en = NOW() WHERE id = ?')
            ->execute([$id]);
    }

    // ── Canales entrantes ─────────────────────────────────────────────────────

    /**
     * Empresa dueña de un canal entrante (WhatsApp, Telegram, Meta).
     *
     * Es el primer paso de todo webhook: llega un identificador de canal y hay
     * que saber en qué empresa se está trabajando antes de tocar nada. Se
     * consulta en modo plataforma a propósito, porque todavía no hay empresa
     * en contexto: precisamente esta consulta es la que la determina.
     *
     * Devuelve null si el canal no está registrado, está desactivado o la
     * empresa está dada de baja. El que llama debe parar ahí: procesar un
     * mensaje sin saber de quién es acaba creando datos huérfanos o, peor,
     * datos en la empresa equivocada.
     */
    public function porCanal(string $canal, string $identificador): ?int
    {
        if ($canal === '' || $identificador === '') {
            return null;
        }

        return Tenant::sinRestriccion(function () use ($canal, $identificador): ?int {
            // tenant-ok: resolución de canal a empresa, previa a entrar en ella
            $stmt = $this->db()->prepare(
                'SELECT c.empresa_id
                   FROM empresa_canales c
                   JOIN empresas e ON e.id = c.empresa_id
                  WHERE c.canal = ? AND c.identificador = ? AND c.activo = 1
                    AND e.activo = 1 AND e.eliminado_en IS NULL
                  LIMIT 1'
            );
            $stmt->execute([$canal, $identificador]);
            $id = $stmt->fetchColumn();

            return $id === false ? null : (int) $id;
        });
    }

    /** Métricas de la plataforma para el panel del propietario. */
    public function resumenPlataforma(): array
    {
        $db = $this->db();

        // Cada conteo va suelto y marcado: así la exención queda pegada a la
        // consulta que exime, y no hay una marca lejana cubriendo de paso algo
        // que nadie revisó.
        $cuenta = static function (string $sql) use ($db): int {
            return (int) $db->query($sql)->fetchColumn();
        };

        return [
            // tenant-ok: panel del propietario, conteo de toda la plataforma
            'empresas' => $cuenta('SELECT COUNT(*) FROM empresas WHERE eliminado_en IS NULL'),
            // tenant-ok: panel del propietario, conteo de toda la plataforma
            'activas'  => $cuenta('SELECT COUNT(*) FROM empresas WHERE activo = 1 AND eliminado_en IS NULL'),
            // tenant-ok: panel del propietario, conteo de toda la plataforma
            'usuarios' => $cuenta('SELECT COUNT(*) FROM agentes WHERE COALESCE(activo,1) = 1'),
            // tenant-ok: panel del propietario, conteo de toda la plataforma
            'clientes' => $cuenta('SELECT COUNT(*) FROM clientes'),
        ];
    }
}
