<?php
declare(strict_types=1);

namespace App\Models;

use Database\Connection;
use PDO;
use LogicException;

/**
 * BaseModel — CRUD genérico sobre PDO.
 */
abstract class BaseModel
{
    protected string $table          = '';
    protected array  $fillable       = [];
    protected array  $defaultColumns = ['id'];
    private ?PDO     $pdo            = null;

    public function __construct()
    {
        if ($this->table === '') {
            throw new LogicException('La propiedad $table no está definida en el modelo: ' . static::class);
        }
    }

    // ── Conexión ──────────────────────────────────────────────────────────────

    protected function db(): PDO
    {
        if ($this->pdo === null) {
            $this->pdo = Connection::getConnection();
        }
        return $this->pdo;
    }

    // ── Seguridad ─────────────────────────────────────────────────────────────

    /**
     * Valida las columnas solicitadas contra una whitelist para evitar SQL Injection.
     */
    protected function validateColumns(array $columns): array
    {
        if (empty($columns)) {
            return $this->defaultColumns;
        }

        // Whitelist dinámica = columnas por defecto + llaves permitidas para escritura
        $whitelist = array_unique(array_merge($this->defaultColumns, array_keys($this->fillable)));
        $safeCols  = array_intersect($columns, $whitelist);

        return empty($safeCols) ? $this->defaultColumns : $safeCols;
    }

    // ── Lectura ───────────────────────────────────────────────────────────────

    public function all(array $columns = []): array
    {
        $cols = $this->validateColumns($columns);
        return $this->db()
            ->query('SELECT ' . implode(', ', $cols) . " FROM {$this->table}")
            ->fetchAll();
    }

    public function find(int $id, array $columns = []): ?array
    {
        $cols = $this->validateColumns($columns);
        $stmt = $this->db()->prepare(
            'SELECT ' . implode(', ', $cols) . " FROM {$this->table} WHERE id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    // ── Escritura ─────────────────────────────────────────────────────────────

    protected function filterData(array $data): array
    {
        $filtered = [];
        foreach ($this->fillable as $column => $type) {
            if (array_key_exists($column, $data)) {
                $filtered[$column] = match ($type) {
                    'int'   => (int)  $data[$column],
                    'float' => (float)$data[$column],
                    'bool'  => (bool) $data[$column],
                    default => (string)$data[$column],
                };
            }
        }
        return $filtered;
    }

    public function insert(array $data): int
    {
        $data = $this->filterData($data);
        if (empty($data)) return 0;

        $columns      = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));

        $this->db()
            ->prepare("INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})")
            ->execute($data);

        return (int) $this->db()->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $data = $this->filterData($data);
        if (empty($data)) return false;

        $fields = implode(', ', array_map(fn($k) => "{$k} = :{$k}", array_keys($data)));
        $data['__id'] = $id;

        $stmt = $this->db()->prepare(
            "UPDATE {$this->table} SET {$fields} WHERE id = :__id"
        );
        
        // Retorna true si la query se ejecutó, evitando el falso negativo de rowCount() === 0
        return $stmt->execute($data);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db()->prepare("DELETE FROM {$this->table} WHERE id = ?");
        return $stmt->execute([$id]);
    }

    // ── Transacciones ─────────────────────────────────────────────────────────

    public function transaction(callable $callback): mixed
    {
        $db = $this->db();
        $db->beginTransaction();
        try {
            // Se inyecta $db al callback por si requiere operaciones crudas
            $result = $callback($db);
            $db->commit();
            return $result;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }
}