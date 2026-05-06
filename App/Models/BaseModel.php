<?php
declare(strict_types=1);

namespace App\Models;

use App\Services\QueryCache;
use Database\Connection;
use PDO;
use LogicException;

/**
 * BaseModel — CRUD genérico sobre PDO con:
 *   - Paginación integrada en all() → paginate()
 *   - Caché de queries en dos niveles (memoria L1 + APCu L2)
 *   - Invalidación automática de caché en insert/update/delete
 *   - Columnas whitelisted contra SQL Injection (sin cambios)
 *   - Transacciones con rollback automático (sin cambios)
 *
 * CONVENCIÓN DE TTL:
 *   Cada modelo hijo puede sobreescribir $cacheTtl.
 *   0  = sin caché (útil para tablas de alta escritura como notificaciones).
 *   >0 = segundos que el resultado vive en APCu.
 *
 *   La caché L1 (array por request) siempre está activa y elimina
 *   consultas duplicadas dentro del mismo ciclo HTTP sin ningún coste.
 */
abstract class BaseModel
{
    protected string $table          = '';
    protected array  $fillable       = [];
    protected array  $defaultColumns = ['id'];

    /**
     * TTL en segundos para APCu.
     * Sobreescribir en el modelo hijo según la volatilidad de los datos.
     *   - Tablas de configuración (cambian raramente): 300
     *   - Tablas de negocio (clientes, pólizas): 60–120
     *   - Tablas de alta escritura (notificaciones, historial): 0
     */
    protected int $cacheTtl = 60;

    private ?PDO $pdo = null;

    public function __construct()
    {
        if ($this->table === '') {
            throw new LogicException(
                'La propiedad $table no está definida en el modelo: ' . static::class
            );
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

        $whitelist = array_unique(
            array_merge($this->defaultColumns, array_keys($this->fillable))
        );
        $safeCols = array_intersect($columns, $whitelist);

        return empty($safeCols) ? $this->defaultColumns : $safeCols;
    }

    // ── Lectura ───────────────────────────────────────────────────────────────

    /**
     * Devuelve TODOS los registros de la tabla (sin límite).
     *
     * ⚠️  Evita usar all() en tablas que crecen indefinidamente.
     *     Usa paginate() en su lugar para tablas de clientes, pólizas, etc.
     *
     * Incluye caché de dos niveles (L1 memoria + L2 APCu).
     */
    public function all(array $columns = []): array
    {
        $cols    = $this->validateColumns($columns);
        $colsKey = implode(',', $cols);

        return QueryCache::remember(
            table: $this->table,
            key:   "all:{$colsKey}",
            ttl:   $this->cacheTtl,
            fn:    fn() => $this->db()
                ->query('SELECT ' . implode(', ', $cols) . " FROM {$this->table}")
                ->fetchAll(),
        );
    }

    /**
     * Paginación genérica. Devuelve un array con:
     *   - data   : filas de la página actual
     *   - total  : total de registros (para calcular páginas en el frontend)
     *   - page   : página actual
     *   - perPage: registros por página
     *   - pages  : total de páginas
     *
     * @param int    $page    Página deseada (1-indexed).
     * @param int    $perPage Registros por página (máx. 200 para evitar abuso).
     * @param string $orderBy Columna de ordenamiento (whitelisted automáticamente).
     * @param string $dir     'ASC' o 'DESC'.
     * @param array  $columns Columnas a seleccionar (vacío = defaultColumns).
     */
    public function paginate(
        int    $page    = 1,
        int    $perPage = 25,
        string $orderBy = 'id',
        string $dir     = 'DESC',
        array  $columns = [],
    ): array {
        // ── Sanitizar parámetros ──────────────────────────────────────────────
        $page    = max(1, $page);
        $perPage = min(200, max(1, $perPage));
        $dir     = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';
        $orderBy = $this->safeOrderColumn($orderBy);
        $cols    = $this->validateColumns($columns);
        $offset  = ($page - 1) * $perPage;

        $cacheKey = "pag:{$page}:{$perPage}:{$orderBy}:{$dir}:" . implode(',', $cols);

        $perPage2 = $perPage;

        return QueryCache::remember(
            table: $this->table,
            key:   $cacheKey,
            ttl:   $this->cacheTtl,
            fn:    function () use ($cols, $orderBy, $dir, $perPage, $offset, $page, $perPage2): array {
                $db   = $this->db();
                $sel  = implode(', ', $cols);
                $tbl  = $this->table;

                // COUNT y SELECT en paralelo (2 queries ligeras vs 1 query pesada)
                $total = (int) $db->query("SELECT COUNT(*) FROM {$tbl}")->fetchColumn();

                $stmt = $db->prepare(
                    "SELECT {$sel} FROM {$tbl}
                     ORDER BY {$orderBy} {$dir}
                     LIMIT :limit OFFSET :offset"
                );
                $stmt->bindValue(':limit',  $perPage2, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset,   PDO::PARAM_INT);
                $stmt->execute();

                return [
                    'data'    => $stmt->fetchAll(),
                    'total'   => $total,
                    'page'    => $page,
                    'perPage' => $perPage2,
                    'pages'   => (int) ceil($total / $perPage2),
                ];
            },
        );
    }

    /**
     * Busca un registro por ID con caché L1+L2.
     */
    public function find(int $id, array $columns = []): ?array
    {
        $cols    = $this->validateColumns($columns);
        $colsKey = implode(',', $cols);

        return QueryCache::remember(
            table: $this->table,
            key:   "find:{$id}:{$colsKey}",
            ttl:   $this->cacheTtl,
            fn:    function () use ($id, $cols): ?array {
                $stmt = $this->db()->prepare(
                    'SELECT ' . implode(', ', $cols) .
                    " FROM {$this->table} WHERE id = ? LIMIT 1"
                );
                $stmt->execute([$id]);
                return $stmt->fetch() ?: null;
            },
        );
    }

    // ── Escritura ─────────────────────────────────────────────────────────────

    protected function filterData(array $data): array
    {
        $filtered = [];
        foreach ($this->fillable as $column => $type) {
            if (array_key_exists($column, $data)) {
                $filtered[$column] = match ($type) {
                    'int'   => (int)   $data[$column],
                    'float' => (float) $data[$column],
                    'bool'  => (bool)  $data[$column],
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

        $newId = (int) $this->db()->lastInsertId();

        // Invalidar caché de la tabla tras escribir
        QueryCache::invalidate($this->table);

        return $newId;
    }

    public function update(int $id, array $data): bool
    {
        $data = $this->filterData($data);
        if (empty($data)) return false;

        $fields       = implode(', ', array_map(fn($k) => "{$k} = :{$k}", array_keys($data)));
        $data['__id'] = $id;

        $stmt = $this->db()->prepare(
            "UPDATE {$this->table} SET {$fields} WHERE id = :__id"
        );

        $ok = $stmt->execute($data);

        // Invalidar caché de la tabla y la entrada individual
        QueryCache::invalidate($this->table);

        return $ok;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db()->prepare("DELETE FROM {$this->table} WHERE id = ?");
        $ok   = $stmt->execute([$id]);

        // Invalidar caché de la tabla
        QueryCache::invalidate($this->table);

        return $ok;
    }

    // ── Transacciones ─────────────────────────────────────────────────────────

    public function transaction(callable $callback): mixed
    {
        $db = $this->db();
        $db->beginTransaction();
        try {
            $result = $callback($db);
            $db->commit();
            return $result;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    // ── Helpers internos ──────────────────────────────────────────────────────

    /**
     * Valida que la columna de ordenamiento esté en la whitelist.
     * Previene SQL Injection en ORDER BY (no cubierto por prepared statements).
     */
    protected function safeOrderColumn(string $column): string
    {
        $whitelist = array_unique(
            array_merge($this->defaultColumns, array_keys($this->fillable))
        );
        return in_array($column, $whitelist, true) ? $column : 'id';
    }
}
