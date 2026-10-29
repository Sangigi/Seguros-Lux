<?php
declare(strict_types=1);

namespace App\Models;

use App\Security\Tenant;
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

    /**
     * Si la tabla está aislada por empresa.
     *
     * Por defecto SÍ: una tabla nueva queda protegida sin que nadie tenga que
     * acordarse de activarlo. Las excepciones (tablas de plataforma como
     * `empresas` o `migraciones`) tienen que declararse a mano, que es el
     * lado correcto en el que equivocarse.
     */
    protected bool $tenantScoped = true;

    protected string $tenantColumn = 'empresa_id';

    private ?PDO $pdo = null;

    public function __construct()
    {
        if ($this->table === '') {
            throw new LogicException(
                'La propiedad $table no está definida en el modelo: ' . static::class
            );
        }
    }

    // ── Aislamiento por empresa ───────────────────────────────────────────────

    /**
     * True si esta consulta debe filtrarse por empresa ahora mismo.
     * En modo sin restricción (propietario, crons) no se filtra.
     */
    protected function filtraPorEmpresa(): bool
    {
        return $this->tenantScoped && !Tenant::esGlobal();
    }

    /**
     * Fragmento SQL del filtro de empresa, listo para concatenar.
     * Devuelve '' cuando no aplica, de modo que el SQL se arma igual en
     * ambos casos.
     *
     * @param  string $alias   Alias de la tabla en la consulta, si lo hay.
     * @param  string $conector 'AND' si la consulta ya trae WHERE, 'WHERE' si no.
     * @param  string $sufijo  Distingue el placeholder cuando el filtro aparece
     *                         más de una vez en la misma consulta (subconsultas).
     *                         Con ATTR_EMULATE_PREPARES=false, MySQL no admite
     *                         repetir un parámetro con nombre: cada aparición
     *                         necesita el suyo.
     * @return string Ej.: " AND c.empresa_id = :__empresa"
     */
    protected function sqlEmpresa(string $alias = '', string $conector = 'AND', string $sufijo = ''): string
    {
        if (!$this->filtraPorEmpresa()) {
            return '';
        }

        $col = ($alias !== '' ? $alias . '.' : '') . $this->tenantColumn;

        return " {$conector} {$col} = :__empresa{$sufijo}";
    }

    /**
     * Añade el parámetro de empresa al array de binds si corresponde.
     *
     * @param  array<string,mixed> $params
     * @param  list<string>        $sufijos Uno por cada aparición del filtro en
     *                                      la consulta. Por defecto, una sola.
     * @return array<string,mixed>
     */
    protected function bindEmpresa(array $params = [], array $sufijos = ['']): array
    {
        if (!$this->filtraPorEmpresa()) {
            return $params;
        }

        $id = Tenant::requireId();

        foreach ($sufijos === [] ? [''] : $sufijos as $sufijo) {
            $params['__empresa' . $sufijo] = $id;
        }

        return $params;
    }

    /**
     * Igual que sqlEmpresa(), pero con marcador posicional.
     *
     * PDO no deja mezclar `?` y `:nombre` en la misma consulta, así que las
     * consultas que ya usan posicionales necesitan esta variante. El valor se
     * intercala con empresaPos(), en el mismo orden en que aparece el filtro.
     */
    protected function sqlEmpresaPos(string $alias = '', string $conector = 'AND'): string
    {
        if (!$this->filtraPorEmpresa()) {
            return '';
        }

        $col = ($alias !== '' ? $alias . '.' : '') . $this->tenantColumn;

        return " {$conector} {$col} = ?";
    }

    /**
     * Valor a intercalar por cada sqlEmpresaPos() de la consulta.
     * Devuelve [] cuando no se filtra, de modo que array_merge() deje la
     * lista de parámetros igual que antes.
     *
     * @return list<int>
     */
    protected function empresaPos(): array
    {
        return $this->filtraPorEmpresa() ? [Tenant::requireId()] : [];
    }

    /**
     * Empresa que debe grabarse en un registro nuevo.
     *
     * Cuando hay una empresa en contexto manda esa, siempre, y se ignora
     * cualquier `empresa_id` que venga en los datos. Si se respetara el del
     * array, bastaría con colar ese campo en un POST para escribir en la
     * empresa de al lado; el dato de entrada nunca decide el tenant.
     *
     * Solo en modo plataforma (propietario o cron, sin empresa activa) se
     * toma del array, porque ahí es el código quien dice a qué empresa
     * pertenece lo que está creando.
     */
    protected function empresaParaEscritura(array $data): int
    {
        if (Tenant::hayContexto()) {
            return Tenant::requireId();
        }

        if (Tenant::esGlobal()
            && isset($data[$this->tenantColumn])
            && (int) $data[$this->tenantColumn] > 0
        ) {
            return (int) $data[$this->tenantColumn];
        }

        // Sin contexto y sin empresa explícita: falla, en vez de crear un
        // registro huérfano que nadie podrá ver después.
        return Tenant::requireId();
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
            fn:    function () use ($cols): array {
                $sql = 'SELECT ' . implode(', ', $cols) . " FROM {$this->table}";

                if (!$this->filtraPorEmpresa()) {
                    return $this->db()->query($sql)->fetchAll();
                }

                $sql .= $this->sqlEmpresa('', 'WHERE');
                $stmt = $this->db()->prepare($sql);
                $stmt->execute($this->bindEmpresa());

                return $stmt->fetchAll();
            },
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
                $db    = $this->db();
                $sel   = implode(', ', $cols);
                $tbl   = $this->table;
                $where = $this->sqlEmpresa('', 'WHERE');

                // COUNT y SELECT en paralelo (2 queries ligeras vs 1 query pesada)
                $stmtCount = $db->prepare("SELECT COUNT(*) FROM {$tbl}{$where}");
                $stmtCount->execute($this->bindEmpresa());
                $total = (int) $stmtCount->fetchColumn();

                $stmt = $db->prepare(
                    "SELECT {$sel} FROM {$tbl}{$where}
                     ORDER BY {$orderBy} {$dir}
                     LIMIT :limit OFFSET :offset"
                );
                foreach ($this->bindEmpresa() as $k => $v) {
                    $stmt->bindValue(':' . $k, $v, PDO::PARAM_INT);
                }
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
                // El filtro de empresa va aquí dentro a propósito: sin él,
                // find() con un id adivinado devuelve el registro de otra
                // empresa. Buscar por id es justamente donde se cuelan esas
                // fugas, porque parece una consulta inocente.
                $stmt = $this->db()->prepare(
                    'SELECT ' . implode(', ', $cols) .
                    " FROM {$this->table} WHERE id = :__id" . $this->sqlEmpresa() . ' LIMIT 1'
                );
                $stmt->execute($this->bindEmpresa(['__id' => $id]));

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
        $empresaId = $this->tenantScoped ? $this->empresaParaEscritura($data) : null;

        $data = $this->filterData($data);
        if (empty($data)) return 0;

        // La empresa se sella aquí, después de filterData: así no depende de
        // que cada modelo se acuerde de declararla en $fillable, ni puede
        // falsearse desde un POST.
        if ($empresaId !== null) {
            $data[$this->tenantColumn] = $empresaId;
        }

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

        // La empresa nunca se cambia por un update de datos: mover un registro
        // de empresa es una operación administrativa aparte, no algo que un
        // formulario pueda hacer colando un campo.
        unset($data[$this->tenantColumn]);

        $fields       = implode(', ', array_map(fn($k) => "{$k} = :{$k}", array_keys($data)));
        $data['__id'] = $id;

        $stmt = $this->db()->prepare(
            "UPDATE {$this->table} SET {$fields} WHERE id = :__id" . $this->sqlEmpresa()
        );

        $ok = $stmt->execute($this->bindEmpresa($data));

        // Invalidar caché de la tabla y la entrada individual
        QueryCache::invalidate($this->table);

        return $ok;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db()->prepare(
            "DELETE FROM {$this->table} WHERE id = :__id" . $this->sqlEmpresa()
        );
        $ok = $stmt->execute($this->bindEmpresa(['__id' => $id]));

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
