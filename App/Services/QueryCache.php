<?php
declare(strict_types=1);

namespace App\Services;

/**
 * QueryCache — Caché de resultados de BD en dos niveles:
 *
 *   Nivel 1 (siempre disponible): array PHP en memoria por request.
 *             Elimina consultas duplicadas dentro del mismo ciclo HTTP.
 *             TTL irrelevante — muere con el proceso.
 *
 *   Nivel 2 (opcional): APCu — caché de proceso compartida entre workers.
 *             Se activa solo cuando la extensión está cargada y habilitada.
 *             TTL configurable por llamada (defecto: 60 s).
 *
 * FILOSOFÍA DE INVALIDACIÓN:
 *   No se intenta invalidación granular por tag (es complejo y propenso a bugs).
 *   En su lugar, cada tabla tiene un "generational key" que se incrementa en
 *   cualquier escritura. Todas las claves de caché de esa tabla incluyen la
 *   generación actual; al escribir, la generación cambia y las claves antiguas
 *   quedan huérfanas y expiran por TTL solas.
 *
 *   Ventaja: simple, correcto, sin acoplamiento entre write y read paths.
 *   Costo: un hit extra de APCu por escritura para leer/incrementar la generación.
 *
 * USO TÍPICO (desde BaseModel):
 *   $resultado = QueryCache::remember(
 *       table: 'clientes',
 *       key:   "clientes:agente:{$agenteId}:pag:{$pag}",
 *       ttl:   120,
 *       fn:    fn() => $this->db()->prepare($sql)->fetchAll()
 *   );
 *
 * INVALIDACIÓN tras escritura:
 *   QueryCache::invalidate('clientes');   // en insert/update/delete
 */
final class QueryCache
{
    // ── Caché L1: memoria por request ─────────────────────────────────────────
    /** @var array<string, mixed> */
    private static array $memory = [];

    // ── TTL por defecto para APCu ─────────────────────────────────────────────
    private const DEFAULT_TTL = 60;

    // ── Prefijo de todas las claves APCu de este CRM ─────────────────────────
    private const PREFIX = 'slux:';

    // ── Prefijo de claves de generación ──────────────────────────────────────
    private const GEN_PREFIX = 'slux:gen:';

    // ── API pública ───────────────────────────────────────────────────────────

    /**
     * Recupera un valor del caché o lo genera ejecutando $fn.
     *
     * @template T
     * @param  string   $table  Tabla principal de la query (para invalidación).
     * @param  string   $key    Clave descriptiva y única (sin prefijo).
     * @param  int      $ttl    Segundos de vida en APCu (0 = solo L1).
     * @param  callable $fn     Genera el valor si no está en caché.
     * @return T
     */
    public static function remember(
        string   $table,
        string   $key,
        int      $ttl,
        callable $fn,
    ): mixed {
        $fullKey = self::buildKey($table, $key);

        // ── L1: memoria ───────────────────────────────────────────────────────
        if (array_key_exists($fullKey, self::$memory)) {
            return self::$memory[$fullKey];
        }

        // ── L2: APCu ─────────────────────────────────────────────────────────
        if ($ttl > 0 && self::apcu()) {
            $cached = apcu_fetch($fullKey, $found);
            if ($found) {
                self::$memory[$fullKey] = $cached;
                return $cached;
            }
        }

        // ── Miss: ejecutar y guardar ──────────────────────────────────────────
        $value = $fn();

        self::$memory[$fullKey] = $value;

        if ($ttl > 0 && self::apcu()) {
            apcu_store($fullKey, $value, $ttl);
        }

        return $value;
    }

    /**
     * Invalida todas las entradas asociadas a una tabla incrementando su generación.
     * Llamar después de cualquier INSERT / UPDATE / DELETE sobre $table.
     *
     * Coste: ~1 write de APCu + limpieza del array L1 con ese prefijo.
     */
    public static function invalidate(string $table): void
    {
        // ── Limpiar L1 ────────────────────────────────────────────────────────
        $genPrefix = self::PREFIX . $table . ':';
        foreach (array_keys(self::$memory) as $k) {
            if (str_starts_with($k, $genPrefix)) {
                unset(self::$memory[$k]);
            }
        }

        // ── Avanzar generación en APCu ────────────────────────────────────────
        if (self::apcu()) {
            apcu_inc(self::GEN_PREFIX . $table, 1, $success);
            if (!$success) {
                apcu_store(self::GEN_PREFIX . $table, 1);
            }
        }
    }

    /**
     * Limpia toda la caché L1 del request actual.
     * Útil en tests o al inicio de un proceso CLI de larga vida.
     */
    public static function flush(): void
    {
        self::$memory = [];
    }

    // ── Internals ─────────────────────────────────────────────────────────────

    /**
     * Construye la clave real incluyendo la generación de la tabla.
     * Ejemplo: "slux:clientes:3:agente:25:pag:1"
     */
    private static function buildKey(string $table, string $key): string
    {
        $gen = self::generation($table);
        return self::PREFIX . $table . ':' . $gen . ':' . $key;
    }

    /**
     * Devuelve la generación actual de la tabla (0 si no existe en APCu).
     */
    private static function generation(string $table): int
    {
        if (!self::apcu()) {
            return 0;
        }
        $gen = apcu_fetch(self::GEN_PREFIX . $table, $found);
        return $found ? (int) $gen : 0;
    }

    /**
     * True si APCu está disponible y habilitado en este contexto.
     * Resultado cacheado en una variable estática para no llamar
     * ini_get() / function_exists() en cada request.
     */
    private static function apcu(): bool
    {
        static $available = null;
        if ($available === null) {
            $available = function_exists('apcu_fetch')
                      && filter_var(ini_get('apc.enabled'), FILTER_VALIDATE_BOOLEAN);
        }
        return $available;
    }
}
