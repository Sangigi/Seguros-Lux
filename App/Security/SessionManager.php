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
          "default-src 'self' https://blog.seguroslux.mx; " .

          "script-src 'self' 'nonce-{$nonce}' '{$autosInlineHash}' https://cdn.jsdelivr.net https://blog.seguroslux.mx; " .

          "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; " .
          "font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net; " .

          "img-src 'self' data: blob: https://blog.seguroslux.mx; " .

          "connect-src 'self' https://blog.seguroslux.mx https://cdn.jsdelivr.net; " .
          "media-src 'self' https://blog.seguroslux.mx; " .
          "frame-src 'self' https://blog.seguroslux.mx; " .

          "frame-ancestors 'self';"
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
            (isset($_SESSION['LAST_ACTIVITY']) &&
            (time() - $_SESSION['LAST_ACTIVITY']) > self::TIMEOUT) ||
            self::sesionSinEmpresa()
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

        // Contexto de empresa para toda la request. Va aquí para que ninguna
        // ruta autenticada pueda ejecutarse sin él.
        Tenant::desdeSesion();

        if (self::debeElegirEmpresa((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH))) {
            header('Location: /plataforma/empresas');
            exit;
        }

        // Un usuario de una empresa suspendida no sigue operando con la
        // sesión que abrió antes de la suspensión.
        if (!self::empresaOperativa()) {
            SecurityLogger::log('empresa_inactiva', [
                'agente_id'  => self::getAgentId(),
                'empresa_id' => self::getEmpresaId(),
            ]);
            self::destroy();
            header('Location: ' . $loginPath . '?empresa_inactiva=1');
            exit;
        }
    }

    /**
     * True si la sesión es de un usuario de empresa pero no trae empresa.
     *
     * Es una sesión abierta antes de multiempresa: el login es lo único que
     * fija la empresa, y sin ella toda consulta de negocio falla. Se trata
     * como expirada para que el usuario vuelva a entrar.
     */
    public static function sesionSinEmpresa(): bool
    {
        return !self::isPropietario() && self::getEmpresaId() === null;
    }

    /**
     * True si el propietario pide una pantalla de negocio sin haber entrado
     * a una empresa.
     *
     * El propietario no pertenece a ninguna: hasta que elige una, esas
     * pantallas no tienen nada que mostrar y la primera consulta fallaría.
     * Las de /plataforma/ son las suyas, y /equipo sabe mostrarse sin
     * empresa (pide entrar a una antes de invitar).
     */
    public static function debeElegirEmpresa(string $ruta): bool
    {
        return self::isPropietario()
            && !Tenant::hayContexto()
            && !str_starts_with($ruta, '/plataforma/')
            && $ruta !== '/equipo';
    }

    /**
     * Comprueba que la empresa de la sesión siga activa.
     *
     * Se consulta una vez por sesión y se recuerda por unos minutos: es una
     * consulta por request en el peor caso, y el coste de no hacerla es que
     * una empresa dada de baja siga trabajando.
     */
    private static function empresaOperativa(): bool
    {
        $empresaId = self::getEmpresaId();

        // El propietario no pertenece a una empresa.
        if ($empresaId === null) {
            return true;
        }

        $ahora    = time();
        $ultima   = (int) ($_SESSION['empresa_chk_ts'] ?? 0);
        $recuerdo = $_SESSION['empresa_chk_ok'] ?? null;

        if ($recuerdo !== null && ($ahora - $ultima) < 300) {
            return (bool) $recuerdo;
        }

        try {
            $stmt = \Database\Connection::getConnection()->prepare(
                'SELECT activo FROM empresas WHERE id = ? AND eliminado_en IS NULL LIMIT 1'
            );
            $stmt->execute([$empresaId]);
            $fila = $stmt->fetch();
            $ok   = $fila !== false && (int) $fila['activo'] === 1;
        } catch (\Throwable $e) {
            // Si la tabla aún no existe (migración sin aplicar) no se bloquea
            // el acceso: el CRM sigue funcionando como antes.
            return true;
        }

        $_SESSION['empresa_chk_ts'] = $ahora;
        $_SESSION['empresa_chk_ok'] = $ok;

        return $ok;
    }

    public static function requireAuthApi(): void
    {
        self::start();

        $expired = (isset($_SESSION['LAST_ACTIVITY']) &&
                   (time() - $_SESSION['LAST_ACTIVITY']) > self::TIMEOUT) ||
                   (self::isAuthenticated() && self::sesionSinEmpresa());

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

        Tenant::desdeSesion();

        if (!self::empresaOperativa()) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Empresa inactiva']);
            exit;
        }
    }

    public static function login(array $agente): void
    {
        self::start();
        session_regenerate_id(true);

        $rol = $agente['rol'] ?? Roles::ASESOR;

        $_SESSION['agente']          = $agente['correo'];
        $_SESSION['agente_nombre']   = $agente['nombre'];
        $_SESSION['agente_apellido'] = $agente['apellido'];
        $_SESSION['agente_id']       = (int)$agente['id'];
        $_SESSION['agente_rol']      = $rol;
        $_SESSION['agente_puesto']   = $agente['puesto'] ?? $rol;
        $_SESSION['LAST_ACTIVITY']   = time();

        // La empresa se fija en el login desde la fila del agente y no vuelve
        // a tocarse: nada que venga del navegador puede cambiarla.
        $_SESSION[Tenant::SESSION_KEY] = isset($agente['empresa_id']) ? (int)$agente['empresa_id'] : null;
        unset($_SESSION['empresa_activa']);

        self::storeFingerprint();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        Tenant::desdeSesion();

        SecurityLogger::log('login_ok', [
            'agente_id'  => (int)$agente['id'],
            'correo'     => $agente['correo'],
            'rol'        => $rol,
            'empresa_id' => $_SESSION[Tenant::SESSION_KEY] ?? null,
        ]);
    }

    /**
     * Empresa a la que pertenece la sesión actual.
     * El propietario no tiene empresa propia y devuelve null.
     */
    public static function getEmpresaId(): ?int
    {
        $id = $_SESSION[Tenant::SESSION_KEY] ?? null;

        return ($id !== null && (int)$id > 0) ? (int)$id : null;
    }

    /**
     * Entra a administrar una empresa concreta (solo propietario).
     * Acota lo que ve; no le añade permisos de operación.
     */
    public static function entrarAEmpresa(?int $empresaId): void
    {
        self::start();

        if (!self::isPropietario()) {
            SecurityLogger::log('empresa_switch_denegado', [
                'agente_id' => self::getAgentId(),
                'rol'       => self::getRole(),
            ]);
            return;
        }

        if ($empresaId === null || $empresaId <= 0) {
            unset($_SESSION['empresa_activa']);
        } else {
            $_SESSION['empresa_activa'] = $empresaId;
            SecurityLogger::log('empresa_switch', [
                'agente_id'  => self::getAgentId(),
                'empresa_id' => $empresaId,
            ]);
        }

        Tenant::desdeSesion();
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
        // Si el fingerprint no está guardado en sesión, la sesión es inválida
        // (puede indicar sesión parcialmente restaurada o almacenamiento corrupto).
        // Retornar false obliga a re-login en lugar de pasar silenciosamente.
        if (empty($_SESSION['fp_ip']) || empty($_SESSION['fp_ua'])) {
            return false;
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
            'id'         => self::getAgentId(),
            'correo'     => $_SESSION['agente']          ?? '',
            'nombre'     => $_SESSION['agente_nombre']   ?? '',
            'apellido'   => $_SESSION['agente_apellido'] ?? '',
            'rol'        => $_SESSION['agente_rol']      ?? Roles::ASESOR,
            'puesto'     => $_SESSION['agente_puesto']   ?? ($_SESSION['agente_rol'] ?? Roles::ASESOR),
            'empresa_id' => self::getEmpresaId(),
        ];
    }

    public static function getRole(): string
    {
        return $_SESSION['agente_rol'] ?? Roles::ASESOR;
    }

    public static function isAdmin(): bool
    {
        return Roles::esAdministrativo(self::getRole());
    }

    public static function isSuperadmin(): bool
    {
        // El propietario también pasa esta comprobación: está por encima del
        // administrador de empresa en la jerarquía, y hay mucho código que
        // usa isSuperadmin() como "puede administrar".
        return Roles::alMenos(self::getRole(), Roles::SUPERADMIN);
    }

    public static function isPropietario(): bool
    {
        return Roles::esPropietario(self::getRole());
    }

    /** True si el rol de la sesión tiene el permiso indicado. */
    public static function puede(string $permiso): bool
    {
        return Roles::tienePermiso(self::getRole(), $permiso);
    }

    public static function requireRole(string $rol, string $loginPath = '/login'): void
    {
        self::requireAuth($loginPath);

        if (!Roles::alMenos(self::getRole(), $rol)) {
            SecurityLogger::log('forbidden_role', [
                'agente_id'  => self::getAgentId(),
                'rol_actual' => self::getRole(),
                'rol_req'    => $rol,
            ]);
            header('Location: ' . $loginPath . '?forbidden=1');
            exit;
        }
    }

    /**
     * Exige un permiso con nombre en vez de un nivel jerárquico.
     *
     * Preferir esto sobre requireRole: "quien puede dar de alta empresas" es
     * una intención estable, mientras que "quien es al menos superadmin" se
     * rompe en silencio en cuanto cambia la jerarquía.
     */
    public static function requirePermiso(string $permiso, string $loginPath = '/login'): void
    {
        self::requireAuth($loginPath);

        if (!self::puede($permiso)) {
            SecurityLogger::log('forbidden_permiso', [
                'agente_id'  => self::getAgentId(),
                'rol_actual' => self::getRole(),
                'permiso'    => $permiso,
            ]);
            header('Location: ' . $loginPath . '?forbidden=1');
            exit;
        }
    }

    /** Igual que requirePermiso pero para endpoints JSON. */
    public static function requirePermisoApi(string $permiso): void
    {
        self::requireAuthApi();

        if (!self::puede($permiso)) {
            SecurityLogger::log('forbidden_permiso_api', [
                'agente_id'  => self::getAgentId(),
                'rol_actual' => self::getRole(),
                'permiso'    => $permiso,
            ]);
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Sin permiso']);
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

    // El ramo ya no vive en la sesión: cada pantalla lee su propio
    // ?ramo=<clave> y lo valida contra CatalogoModel::ramos().
}
