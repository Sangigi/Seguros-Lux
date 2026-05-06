<?php
declare(strict_types=1);

namespace App\Security;

/**
 * Protección contra fuerza bruta.
 *
 * Límite: 5 intentos fallidos por combinación correo+IP en ventana de 15 min.
 * Cada intento adicional durante el bloqueo extiende la ventana (lockout extensible).
 * Usa Redis si está disponible; si no, cae a ficheros en storage/ratelimit/.
 */
class RateLimiter
{
    private const MAX_ATTEMPTS = 5;
    private const WINDOW_SEC   = 900;          // 15 minutos
    private const CACHE_DIR    = __DIR__ . '/../../storage/ratelimit';

    // ── API pública ──────────────────────────────────────────────────────────

    /** true → rechazar el intento (límite superado). */
    public static function tooManyAttempts(string $correo, string $ip): bool
    {
        $data = self::read(self::key($correo, $ip));
        if ($data === null) return false;

        // Ventana expirada → resetear
        if ((time() - $data['first_at']) > self::WINDOW_SEC) {
            self::erase(self::key($correo, $ip));
            return false;
        }

        return $data['attempts'] >= self::MAX_ATTEMPTS;
    }

    /** Registra un intento fallido. */
    public static function hit(string $correo, string $ip): void
    {
        $k    = self::key($correo, $ip);
        $data = self::read($k);

        if ($data === null || (time() - $data['first_at']) > self::WINDOW_SEC) {
            $data = ['attempts' => 1, 'first_at' => time()];
        } else {
            $data['attempts']++;
            // Lockout extensible: reinicia la ventana en cada intento durante el bloqueo
            if ($data['attempts'] >= self::MAX_ATTEMPTS) {
                $data['first_at'] = time();
            }
        }

        self::store($k, $data);

        SecurityLogger::log('login_failed', [
            'correo'   => self::maskEmail($correo),
            'attempts' => $data['attempts'],
        ]);
    }

    /** Resetea el contador tras login exitoso. */
    public static function clear(string $correo, string $ip): void
    {
        self::erase(self::key($correo, $ip));
    }

    /** Segundos restantes hasta que el bloqueo expire. */
    public static function secondsLeft(string $correo, string $ip): int
    {
        $data = self::read(self::key($correo, $ip));
        if ($data === null) return 0;
        return max(0, self::WINDOW_SEC - (time() - $data['first_at']));
    }

    // ── Internals ────────────────────────────────────────────────────────────

    private static function key(string $correo, string $ip): string
    {
        return 'rl:' . hash('sha256', strtolower(trim($correo)) . '|' . $ip);
    }

    private static function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        return substr($local, 0, 2) . '***@' . $domain;
    }

    // ── Redis ─────────────────────────────────────────────────────────────────

    private static function redis(): ?\Redis
    {
        static $r = false;
        if ($r !== false) return $r ?: null;

        if (!class_exists(\Redis::class)) { $r = null; return null; }
        try {
            $conn = new \Redis();
            $conn->connect(
                (string)(getenv('REDIS_HOST') ?: '127.0.0.1'),
                (int)   (getenv('REDIS_PORT') ?: 6379),
                1.0
            );
            if ($pass = getenv('REDIS_PASSWORD')) $conn->auth($pass);
            $r = $conn;
        } catch (\RedisException) {
            $r = null;
        }
        return $r ?: null;
    }

    private static function read(string $key): ?array
    {
        if ($redis = self::redis()) {
            $raw = $redis->get($key);
            return $raw ? json_decode($raw, true) : null;
        }
        return self::fileRead($key);
    }

    private static function store(string $key, array $data): void
    {
        if ($redis = self::redis()) {
            $redis->setex($key, self::WINDOW_SEC, json_encode($data));
            return;
        }
        self::fileWrite($key, $data);
    }

    private static function erase(string $key): void
    {
        if ($redis = self::redis()) { $redis->del($key); return; }
        self::fileDelete($key);
    }

    // ── Fallback fichero ──────────────────────────────────────────────────────

    private static function filePath(string $key): string
    {
        if (!is_dir(self::CACHE_DIR)) mkdir(self::CACHE_DIR, 0750, true);
        return self::CACHE_DIR . '/' . substr(str_replace(':', '_', $key), -40) . '.json';
    }

    private static function fileRead(string $key): ?array
    {
        $p = self::filePath($key);
        if (!file_exists($p)) return null;
        $raw = file_get_contents($p);
        return $raw ? json_decode($raw, true) : null;
    }

    private static function fileWrite(string $key, array $data): void
    {
        file_put_contents(self::filePath($key), json_encode($data), LOCK_EX);
    }

    private static function fileDelete(string $key): void
    {
        $p = self::filePath($key);
        if (file_exists($p)) unlink($p);
    }
}