<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Security\SessionManager;
use App\Security\RateLimiter;
use App\Security\SecurityLogger;
use App\Services\LoginService;
use App\Models\AgentModel;

/**
 * Controlador de login.
 *
 * Mejoras de seguridad aplicadas:
 *  - Validación de token CSRF antes de procesar credenciales
 *  - Rate limiting por correo + IP (máx. 5 intentos / 15 min)
 *  - Logging de todos los eventos (ok, fallido, bloqueado, excepción)
 *  - Redirección post-login con lista blanca de destinos
 *  - Manejo de excepciones de BD para no filtrar stack traces
 *  - Respuesta JSON para peticiones AJAX (X-Requested-With)
 */
class LoginController
{
    /** Destinos válidos post-login. Nunca redirigir a URLs arbitrarias. */
    private const ALLOWED_REDIRECTS = [
        '/dashboard',
        '/admin',
        '/admin?tab=resumen',
        '/plataforma/empresas',
    ];

    public static function handle(): void
    {
        $esAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
        $ip     = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        // ── 1. CSRF ───────────────────────────────────────────────────────────
        // La sesión debe estar activa para comparar el token almacenado
        SessionManager::start();

        $csrfToken = filter_input(INPUT_POST, 'csrf_token') ?? '';

        if (!SessionManager::validateCsrf($csrfToken)) {
            SecurityLogger::log('csrf_rejected', ['ip' => $ip]);
            self::respond($esAjax, 403, '/login?error=1', 'Token CSRF inválido');
        }

        // ── 2. Inputs ─────────────────────────────────────────────────────────
        $correo     = trim((string)(filter_input(INPUT_POST, 'correo',     FILTER_SANITIZE_EMAIL) ?? ''));
        $contrasena = filter_input(INPUT_POST, 'contrasena') ?? '';

        if (!$correo || !$contrasena) {
            self::respond($esAjax, 400, '/login?error=1', 'Campos requeridos');
        }

        // ── 3. Rate limiting ──────────────────────────────────────────────────
        if (RateLimiter::tooManyAttempts($correo, $ip)) {
            $segsLeft = RateLimiter::secondsLeft($correo, $ip);
            SecurityLogger::log('login_locked', [
                'correo'    => $correo,
                'secs_left' => $segsLeft,
            ]);
            self::respond($esAjax, 429, '/login?locked=1', 'Demasiados intentos. Espera ' . ceil($segsLeft / 60) . ' min.');
        }

        // ── 4. Autenticación ──────────────────────────────────────────────────
        try {
            $service = new LoginService(new AgentModel());
            $agente  = $service->login($correo, $contrasena);
        } catch (\Throwable $e) {
    SecurityLogger::log('login_exception', [
        'msg'   => $e->getMessage(),
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
        'trace' => $e->getTraceAsString(),   // ← agrega esto
    ]);
    self::respond($esAjax, 500, '/login?error=1', 'Error interno');
}

        if ($agente) {
            // Login correcto → limpiar contador y abrir sesión
            RateLimiter::clear($correo, $ip);
            SessionManager::login($agente);

            $destino = self::safeRedirect($agente['rol'] ?? 'asesor');

            if ($esAjax) {
                header('Content-Type: application/json');
                echo json_encode(['redirect' => $destino]);
                exit;
            }
            header('Location: ' . $destino);
            exit;
        }

        // ── 5. Credenciales incorrectas ───────────────────────────────────────
        RateLimiter::hit($correo, $ip);
        self::respond($esAjax, 401, '/login?error=1', 'Correo o contraseña incorrectos');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Determina el destino post-login usando lista blanca.
     * Nunca redirigir a URLs proporcionadas por el usuario.
     */
    private static function safeRedirect(string $rol): string
    {
        // El propietario no pertenece a ninguna empresa: entra eligiendo una.
        $destino = match (true) {
            $rol === 'propietario'                                               => '/plataforma/empresas',
            in_array($rol, ['coordinador', 'administrador', 'superadmin'], true) => '/admin?tab=resumen',
            default                                                              => '/dashboard',
        };

        // Verificar que el destino está en la lista blanca (doble check)
        return in_array($destino, self::ALLOWED_REDIRECTS, true) ? $destino : '/dashboard';
    }

    /**
     * Envía la respuesta adecuada (JSON para AJAX, redirección para form normal)
     * y termina la ejecución.
     */
    private static function respond(bool $esAjax, int $code, string $redirect, string $msg): never
    {
        if ($esAjax) {
            http_response_code($code);
            header('Content-Type: application/json');
            echo json_encode(['error' => $msg]);
        } else {
            header('Location: ' . $redirect);
        }
        exit;
    }
}