<?php
declare(strict_types=1);

namespace Database;

use PDO;

/**
 * SchemaHelper — operaciones de esquema idempotentes.
 *
 * MySQL 8 no soporta `ADD COLUMN IF NOT EXISTS` (MariaDB sí), así que una
 * migración que se corra dos veces reventaría. Este helper consulta el
 * esquema antes de actuar, de modo que toda migración es segura de repetir.
 *
 * Funciona sobre MySQL/MariaDB y sobre SQLite (usado por los tests), que
 * difieren bastante en DDL e INFORMATION_SCHEMA.
 */
final class SchemaHelper
{
    private PDO $db;
    private string $driver;

    public function __construct(PDO $db)
    {
        $this->db     = $db;
        $this->driver = (string) $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    public function esSqlite(): bool
    {
        return $this->driver === 'sqlite';
    }

    // ── Inspección ────────────────────────────────────────────────────────────

    public function tablaExiste(string $tabla): bool
    {
        if ($this->esSqlite()) {
            $stmt = $this->db->prepare(
                "SELECT 1 FROM sqlite_master WHERE type='table' AND name = ? LIMIT 1"
            );
            $stmt->execute([$tabla]);
            return (bool) $stmt->fetchColumn();
        }

        $stmt = $this->db->prepare(
            'SELECT 1 FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $stmt->execute([$tabla]);
        return (bool) $stmt->fetchColumn();
    }

    public function columnaExiste(string $tabla, string $columna): bool
    {
        if (!$this->tablaExiste($tabla)) {
            return false;
        }

        if ($this->esSqlite()) {
            foreach ($this->db->query("PRAGMA table_info(" . $this->quoteIdent($tabla) . ")")->fetchAll() as $col) {
                if (strcasecmp((string) $col['name'], $columna) === 0) {
                    return true;
                }
            }
            return false;
        }

        $stmt = $this->db->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $stmt->execute([$tabla, $columna]);
        return (bool) $stmt->fetchColumn();
    }

    public function indiceExiste(string $tabla, string $indice): bool
    {
        if (!$this->tablaExiste($tabla)) {
            return false;
        }

        if ($this->esSqlite()) {
            $stmt = $this->db->prepare(
                "SELECT 1 FROM sqlite_master WHERE type='index' AND name = ? LIMIT 1"
            );
            $stmt->execute([$indice]);
            return (bool) $stmt->fetchColumn();
        }

        $stmt = $this->db->prepare(
            'SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1'
        );
        $stmt->execute([$tabla, $indice]);
        return (bool) $stmt->fetchColumn();
    }

    public function fkExiste(string $tabla, string $nombre): bool
    {
        if ($this->esSqlite() || !$this->tablaExiste($tabla)) {
            return false;
        }

        $stmt = $this->db->prepare(
            'SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
               AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = "FOREIGN KEY" LIMIT 1'
        );
        $stmt->execute([$tabla, $nombre]);
        return (bool) $stmt->fetchColumn();
    }

    /** Tipo declarado de una columna, en minúsculas (ej. "int", "varchar(50)", "enum(...)"). */
    public function tipoColumna(string $tabla, string $columna): ?string
    {
        if ($this->esSqlite()) {
            foreach ($this->db->query("PRAGMA table_info(" . $this->quoteIdent($tabla) . ")")->fetchAll() as $col) {
                if (strcasecmp((string) $col['name'], $columna) === 0) {
                    return strtolower((string) $col['type']);
                }
            }
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $stmt->execute([$tabla, $columna]);
        $t = $stmt->fetchColumn();

        return $t === false ? null : strtolower((string) $t);
    }

    // ── Mutación idempotente ──────────────────────────────────────────────────

    /** Añade una columna solo si no existe. Devuelve true si la añadió. */
    public function agregarColumna(string $tabla, string $columna, string $definicion): bool
    {
        if (!$this->tablaExiste($tabla) || $this->columnaExiste($tabla, $columna)) {
            return false;
        }

        $this->db->exec(sprintf(
            'ALTER TABLE %s ADD COLUMN %s %s',
            $this->quoteIdent($tabla),
            $this->quoteIdent($columna),
            $definicion
        ));

        return true;
    }

    /** Elimina una columna solo si existe. SQLite <3.35 no lo soporta: se ignora. */
    public function quitarColumna(string $tabla, string $columna): bool
    {
        if (!$this->columnaExiste($tabla, $columna)) {
            return false;
        }

        try {
            $this->db->exec(sprintf(
                'ALTER TABLE %s DROP COLUMN %s',
                $this->quoteIdent($tabla),
                $this->quoteIdent($columna)
            ));
            return true;
        } catch (\PDOException $e) {
            if ($this->esSqlite()) {
                return false;
            }
            throw $e;
        }
    }

    /**
     * Crea un índice solo si no existe.
     *
     * @param array<int,string> $columnas
     */
    public function crearIndice(string $tabla, string $nombre, array $columnas, bool $unico = false): bool
    {
        if (!$this->tablaExiste($tabla) || $this->indiceExiste($tabla, $nombre)) {
            return false;
        }

        foreach ($columnas as $c) {
            if (!$this->columnaExiste($tabla, $c)) {
                return false;
            }
        }

        $cols = implode(', ', array_map([$this, 'quoteIdent'], $columnas));
        $tipo = $unico ? 'UNIQUE INDEX' : 'INDEX';

        if ($this->esSqlite()) {
            $tipo = $unico ? 'UNIQUE INDEX' : 'INDEX';
            $this->db->exec(sprintf(
                'CREATE %s %s ON %s (%s)',
                $tipo,
                $this->quoteIdent($nombre),
                $this->quoteIdent($tabla),
                $cols
            ));
            return true;
        }

        $this->db->exec(sprintf(
            'ALTER TABLE %s ADD %s %s (%s)',
            $this->quoteIdent($tabla),
            $tipo,
            $this->quoteIdent($nombre),
            $cols
        ));

        return true;
    }

    public function quitarIndice(string $tabla, string $nombre): bool
    {
        if (!$this->indiceExiste($tabla, $nombre)) {
            return false;
        }

        if ($this->esSqlite()) {
            $this->db->exec('DROP INDEX ' . $this->quoteIdent($nombre));
            return true;
        }

        $this->db->exec(sprintf(
            'ALTER TABLE %s DROP INDEX %s',
            $this->quoteIdent($tabla),
            $this->quoteIdent($nombre)
        ));

        return true;
    }

    /**
     * Añade una llave foránea si no existe. En SQLite se omite (no soporta
     * añadir FKs con ALTER). Si quedan filas huérfanas la FK fallaría, así
     * que se reporta el problema en vez de reventar la migración entera.
     */
    public function agregarFk(
        string $tabla,
        string $nombre,
        string $columna,
        string $tablaRef,
        string $columnaRef = 'id',
        string $onDelete = 'CASCADE'
    ): bool {
        if ($this->esSqlite()) {
            return false;
        }

        if (!$this->tablaExiste($tabla) || !$this->tablaExiste($tablaRef)) {
            return false;
        }

        if (!$this->columnaExiste($tabla, $columna) || $this->fkExiste($tabla, $nombre)) {
            return false;
        }

        if ($this->motor($tabla) !== 'innodb') {
            return false;
        }

        try {
            $this->db->exec(sprintf(
                'ALTER TABLE %s ADD CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s (%s) ON DELETE %s ON UPDATE CASCADE',
                $this->quoteIdent($tabla),
                $this->quoteIdent($nombre),
                $this->quoteIdent($columna),
                $this->quoteIdent($tablaRef),
                $this->quoteIdent($columnaRef),
                $onDelete
            ));
            return true;
        } catch (\PDOException $e) {
            return false;
        }
    }

    public function quitarFk(string $tabla, string $nombre): bool
    {
        if ($this->esSqlite() || !$this->fkExiste($tabla, $nombre)) {
            return false;
        }

        $this->db->exec(sprintf(
            'ALTER TABLE %s DROP FOREIGN KEY %s',
            $this->quoteIdent($tabla),
            $this->quoteIdent($nombre)
        ));

        return true;
    }

    private function motor(string $tabla): string
    {
        if ($this->esSqlite()) {
            return 'sqlite';
        }

        $stmt = $this->db->prepare(
            'SELECT ENGINE FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $stmt->execute([$tabla]);

        return strtolower((string) ($stmt->fetchColumn() ?: ''));
    }

    public function quoteIdent(string $ident): string
    {
        $ident = str_replace(['`', '"', "\0"], '', $ident);

        return $this->esSqlite() ? '"' . $ident . '"' : '`' . $ident . '`';
    }

    /** DDL portable para una columna entera sin signo que puede ser nula. */
    public function tipoEntero(): string
    {
        return $this->esSqlite() ? 'INTEGER' : 'INT';
    }
}
