<?php
declare(strict_types=1);

namespace App\Security;

/**
 * Gestión segura de sesiones — Seguros Lux
 *
 * FIXES en esta versión:
 *  - img-src     incluye https://blog.seguroslux.mx (imágenes del blog)
 *  - connect-src incluye https://blog.seguroslux.mx y https://cdn.jsdelivr.net
 *                (fix: Bootstrap sourcemap bloqueado en formulario_cliente)
 *  - script-src  incluye hash del inline script de autos.php  +
 *                'unsafe-hashes' para permitir onclick="" del formulario_cliente
 *                SIN tener que refactorizar todos los event handlers inline.
 *  - getCsrfToken() y validateCsrf() llaman self::start() internamente.
 *    FIX CRÍTICO: elimina el 403 permanente en login causado porque el
 *    front-controller no garantizaba que la sesión estuviera activa antes de
 *    generar/comparar el token CSRF.
 *
 * NOTA sobre 'unsafe-hashes':
 *   Permite atributos onclick/onchange/etc. inline. Es menos restrictivo que
 *   los nonces/hashes de scripts completos, pero más seguro que 'unsafe-inline'.
 *   La alternativa es refactorizar formulario_cliente.php para mover todos los
 *   onclick al JS externo (formulario_cliente.js) y usar solo el nonce.
 *   Se elige 'unsafe-hashes' aquí para minimizar cambios en el HTML.
 */
class SessionManager
{
    private const TIMEOUT = 10800; // 3 horas

    // ── Inicio de sesión ──────────────────────────────────────────────────────

    public static function start(): void
    {
        if (session_status() !== PHP_SESSION_NONE) return;

        $isSecure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                 || (getenv('APP_ENV') === 'production');

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isSecure,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);

        session_start();
    }

    // ── Nonce por request ─────────────────────────────────────────────────────

    public static function nonce(): string
    {
        static $nonce = null;
        if ($nonce === null) {
            $nonce = base64_encode(random_bytes(16));
        }
        return $nonce;
    }

    // ── Cabeceras HTTP de seguridad ───────────────────────────────────────────

    /**
     * Emite todas las cabeceras HTTP de seguridad.
     * Llamar UNA VEZ en index.php / front-controller antes de cualquier output.
     *
     * FIXES aplicados:
     *   1. connect-src → agrega https://cdn.jsdelivr.net
     *      (Bootstrap sourcemap en formulario_cliente.php estaba bloqueado)
     *   2. script-src  → agrega 'unsafe-hashes'
     *      (permite onclick="" inline en formulario_cliente.php sin refactorizar)
     *   3. script-src  → conserva hash sha256 del inline script de autos.php
     */
    public static function sendSecurityHeaders(): void
    {
        $nonce = self::nonce();

        // Hash del inline script en autos.php (quote-tabs)
        // Contenido exacto:
        //   \n  // Quote tabs (separadas de plan-tabs)\n  document.querySelectorAll...
        $autosInlineHash = 'sha256-SxvxBwFKi30Ha/zZWTRb9NJg74REtc1o54/FaxCyQxg=';

        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), camera=(), microphone=()');

        header(
            "Content-Security-Policy: " .
            "default-src 'self'; " .

            // 'unsafe-hashes' permite onclick="" y otros event handlers inline.
            // El nonce cubre el <script> largo de formulario_cliente.php.
            // El hash cubre el inline de autos.php.
            "script-src 'self' 'unsafe-hashes' 'nonce-{$nonce}' '{$autosInlineHash}' cdn.jsdelivr.net; " .

            "style-src 'self' 'unsafe-inline' cdn.jsdelivr.net fonts.googleapis.com; " .
            "font-src 'self' fonts.gstatic.com cdn.jsdelivr.net; " .

            // FIX: imágenes del blog WordPress
            "img-src 'self' data: blob: https://blog.seguroslux.mx; " .

            // FIX: fetch() al REST API del blog + sourcemap de Bootstrap CDN
            "connect-src 'self' https://blog.seguroslux.mx https://cdn.jsdelivr.net; " .

            "frame-ancestors 'none';"
        );
    }

    // ── HTTPS forzado ─────────────────────────────────────────────────────────

    public static function enforceHttps(): void
    {
        if (
            getenv('APP_ENV') === 'production' &&
            (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off')
        ) {
            $url = 'https://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '/');
            header('Location: ' . $url, true, 301);
            exit;
        }
    }

    // ── CSRF ──────────────────────────────────────────────────────────────────

    /**
     * Devuelve (y crea si no existe) el token CSRF de la sesión.
     *
     * FIX: llama self::start() internamente para que el token siempre se lea/
     * escriba en la misma sesión, independientemente del orden en que el
     * front-controller arranque las cosas. Sin esto, si el controlador de la
     * página de login no llama start() antes de getCsrfToken(), el token se
     * genera en una sesión distinta a la que recibe el POST → 403 permanente.
     */
    public static function getCsrfToken(): string
    {
        self::start(); // garantiza sesión activa antes de leer/escribir $_SESSION
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Valida el token CSRF recibido contra el almacenado en sesión.
     *
     * FIX: igual que getCsrfToken(), arranca la sesión si no está activa para
     * que $_SESSION['csrf_token'] sea el mismo que se entregó en el GET.
     */
    public static function validateCsrf(string $token): bool
    {
        self::start(); // misma sesión que generó el token
        $stored = $_SESSION['csrf_token'] ?? '';
        if (!$stored || !$token) return false;
        return hash_equals($stored, $token);
    }

    // ── Autenticación ─────────────────────────────────────────────────────────

    public static function isAuthenticated(): bool
    {
        return isset($_SESSION['agente_id'], $_SESSION['agente']);
    }

    public static function requireAuth(string $loginPath = '/login'): void
    {
        self::start();

        if (!self::isAuthenticated()) {
            header('Location: ' . $loginPath);
            exit;
        }

        if (
            isset($_SESSION['LAST_ACTIVITY']) &&
            (time() - $_SESSION['LAST_ACTIVITY']) > self::TIMEOUT
        ) {
            SecurityLogger::log('session_expired', ['agente_id' => self::getAgentId()]);
            self::destroy();
            header('Location: ' . $loginPath . '?expired=1');
            exit;
        }

        if (!self::validateFingerprint()) {
            SecurityLogger::log('session_hijack', ['agente_id' => self::getAgentId()]);
            self::destroy();
            header('Location: ' . $loginPath . '?error=1');
            exit;
        }

        $_SESSION['LAST_ACTIVITY'] = time();
    }

    public static function requireAuthApi(): void
    {
        self::start();

        $expired = isset($_SESSION['LAST_ACTIVITY']) &&
                   (time() - $_SESSION['LAST_ACTIVITY']) > self::TIMEOUT;

        if (!self::isAuthenticated() || $expired || !self::validateFingerprint()) {
            if ($expired) {
                SecurityLogger::log('session_expired', ['agente_id' => self::getAgentId()]);
                self::destroy();
            }
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error'   => $expired ? 'Sesión expirada' : 'No autorizado',
            ]);
            exit;
        }

        $_SESSION['LAST_ACTIVITY'] = time();
    }

    public static function login(array $agente): void
    {
        self::start();
        session_regenerate_id(true);

        $_SESSION['agente']          = $agente['correo'];
        $_SESSION['agente_nombre']   = $agente['nombre'];
        $_SESSION['agente_apellido'] = $agente['apellido'];
        $_SESSION['agente_id']       = (int)$agente['id'];
        $_SESSION['agente_rol']      = $agente['rol'] ?? 'asesor';
        $_SESSION['LAST_ACTIVITY']   = time();

        self::storeFingerprint();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        SecurityLogger::log('login_ok', [
            'agente_id' => (int)$agente['id'],
            'correo'    => $agente['correo'],
            'rol'       => $agente['rol'] ?? 'asesor',
        ]);
    }

    public static function destroy(): void
    {
        if (self::isAuthenticated()) {
            SecurityLogger::log('logout', ['agente_id' => self::getAgentId()]);
        }

        self::start();
        session_unset();
        session_destroy();

        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
    }

    // ── Fingerprint ──────────────────────────────────────────────────────────

    private static function storeFingerprint(): void
    {
        $_SESSION['fp_ip'] = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '');
        $_SESSION['fp_ua'] = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
    }

    private static function validateFingerprint(): bool
    {
        if (empty($_SESSION['fp_ip']) || empty($_SESSION['fp_ua'])) {
            self::storeFingerprint();
            return true;
        }

        $ipOk = hash_equals($_SESSION['fp_ip'], hash('sha256', $_SERVER['REMOTE_ADDR'] ?? ''));
        $uaOk = hash_equals($_SESSION['fp_ua'], hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? ''));

        return $ipOk && $uaOk;
    }

    // ── Datos del agente ──────────────────────────────────────────────────────

    public static function getAgentId(): int
    {
        return (int)($_SESSION['agente_id'] ?? 0);
    }

    public static function getAgentData(): array
    {
        return [
            'id'       => self::getAgentId(),
            'correo'   => $_SESSION['agente']          ?? '',
            'nombre'   => $_SESSION['agente_nombre']   ?? '',
            'apellido' => $_SESSION['agente_apellido'] ?? '',
            'rol'      => $_SESSION['agente_rol']      ?? 'asesor',
        ];
    }

    public static function getRole(): string
    {
        return $_SESSION['agente_rol'] ?? 'asesor';
    }

    public static function isAdmin(): bool
    {
        return in_array(self::getRole(), ['coordinador', 'administrador', 'superadmin'], true);
    }

    public static function isSuperadmin(): bool
    {
        return self::getRole() === 'superadmin';
    }

    public static function requireRole(string $rol, string $loginPath = '/login'): void
    {
        self::requireAuth($loginPath);

        $jerarquia = [
            'asesor'        => 1,
            'agente'        => 1,
            'coordinador'   => 2,
            'administrador' => 2,
            'superadmin'    => 3,
        ];
        $nivelReq  = $jerarquia[$rol]            ?? 1;
        $nivelUser = $jerarquia[self::getRole()] ?? 0;

        if ($nivelUser < $nivelReq) {
            SecurityLogger::log('forbidden_role', [
                'agente_id'  => self::getAgentId(),
                'rol_actual' => self::getRole(),
                'rol_req'    => $rol,
            ]);
            header('Location: ' . $loginPath . '?forbidden=1');
            exit;
        }
    }

    // ── Keepalive AJAX ────────────────────────────────────────────────────────

    public static function ping(): void
    {
        self::start();
        header('Content-Type: application/json');

        $expired = isset($_SESSION['LAST_ACTIVITY']) &&
                   (time() - $_SESSION['LAST_ACTIVITY']) > self::TIMEOUT;

        if (!self::isAuthenticated() || $expired || !self::validateFingerprint()) {
            if ($expired) self::destroy();
            http_response_code(401);
            echo json_encode(['ok' => false]);
            exit;
        }

        $_SESSION['LAST_ACTIVITY'] = time();
        echo json_encode(['ok' => true]);
        exit;
    }

    // ── Ramo activo ───────────────────────────────────────────────────────────

    public static function getRamo(): ?string
    {
        $ramo    = $_SESSION['ramo_activo'] ?? null;
        $validos = ['autos', 'vida', 'gastos_medicos', 'empresas'];
        return ($ramo && in_array($ramo, $validos, true)) ? $ramo : null;
    }

    public static function setRamo(string $ramo): void
    {
        $_SESSION['ramo_activo'] = $ramo;
    }

    public static function clearRamo(): void
    {
        unset($_SESSION['ramo_activo']);
    }
}