<?php
declare(strict_types=1);

namespace App\Security;

/**
 * Logger de eventos de seguridad.
 *
 * Registra en JSON-lines: login_ok, login_failed, login_locked,
 * logout, session_expired, session_hijack, forbidden_role,
 * csrf_rejected, login_exception.
 */
class SecurityLogger
{
    private const LOG_FILE = __DIR__ . '/../../logs/security.log';

    public static function log(string $event, array $extra = []): void
    {
        $entry = array_merge([
            'ts'         => date('c'),
            'event'      => $event,
            'ip'         => self::clientIp(),
            'ua'         => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200),
            'session_id' => session_id() ?: null,
        ], $extra);

        $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;

        $dir = dirname(self::LOG_FILE);
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        file_put_contents(self::LOG_FILE, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Devuelve la IP real respetando proxies de confianza definidos
     * en la variable de entorno TRUSTED_PROXIES (IPs separadas por coma).
     */
    private static function clientIp(): string
    {
        $remote  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $trusted = array_filter(array_map('trim', explode(',', (string)getenv('TRUSTED_PROXIES'))));

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']) && in_array($remote, $trusted, true)) {
            return trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        }

        return $remote;
    }
}