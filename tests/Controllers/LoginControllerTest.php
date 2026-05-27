<?php
declare(strict_types=1);

namespace Tests\Controllers;

use PHPUnit\Framework\TestCase;
use App\Security\SessionManager;
use App\Security\RateLimiter;

/**
 * Tests para LoginController.
 *
 * LoginController::handle() usa exit() en todas las rutas, por lo que
 * no se puede invocar directamente. Probamos las capas que el controlador
 * delega: CSRF, RateLimiter y LoginService, más validaciones estructurales
 * vía análisis del código fuente.
 */
class LoginControllerTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        // Limpiar rate limiter de tests anteriores
        $this->limpiarRateLimit('test@example.com', '127.0.0.1');
    }

    protected function tearDown(): void
    {
        $this->limpiarRateLimit('test@example.com', '127.0.0.1');
        unset($_POST['correo'], $_POST['contrasena'], $_POST['csrf_token'],
              $_SERVER['HTTP_X_REQUESTED_WITH']);
    }

    private function limpiarRateLimit(string $correo, string $ip): void
    {
        try {
            RateLimiter::clear($correo, $ip);
        } catch (\Throwable) {}
    }

    // ── CSRF ──────────────────────────────────────────────────────────────────

    /** @test */
    public function csrf_invalido_falla_validacion(): void
    {
        $this->assertFalse(
            SessionManager::validateCsrf('token_falso'),
            'Token CSRF inválido debe fallar'
        );
    }

    /** @test */
    public function csrf_valido_pasa_validacion(): void
    {
        $token = SessionManager::getCsrfToken();
        $this->assertTrue(SessionManager::validateCsrf($token));
    }

    // ── RateLimiter ───────────────────────────────────────────────────────────

    /** @test */
    public function rate_limiter_bloquea_tras_5_intentos(): void
    {
        $correo = 'rl_test_login@example.com';
        $ip     = '10.0.0.1';

        RateLimiter::clear($correo, $ip);

        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit($correo, $ip);
        }

        $this->assertTrue(
            RateLimiter::tooManyAttempts($correo, $ip),
            'Después de 5 hits debe reportar tooManyAttempts=true'
        );

        RateLimiter::clear($correo, $ip);
    }

    /** @test */
    public function rate_limiter_clear_desbloquea(): void
    {
        $correo = 'rl_clear_test@example.com';
        $ip     = '10.0.0.2';

        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit($correo, $ip);
        }

        RateLimiter::clear($correo, $ip);

        $this->assertFalse(
            RateLimiter::tooManyAttempts($correo, $ip),
            'Después de clear(), tooManyAttempts debe ser false'
        );
    }

    /** @test */
    public function rate_limiter_sin_intentos_no_bloquea(): void
    {
        $correo = 'nuevo_login@example.com';
        $ip     = '10.0.0.3';

        RateLimiter::clear($correo, $ip);

        $this->assertFalse(RateLimiter::tooManyAttempts($correo, $ip));
    }

    // ── Redirección segura (lista blanca) ─────────────────────────────────────

    /** @test */
    public function controlador_usa_lista_blanca_para_redirect(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/App/Controllers/LoginController.php'
        );

        $this->assertStringContainsString('ALLOWED_REDIRECTS', $source);
        $this->assertStringContainsString('/dashboard', $source);
        $this->assertStringContainsString('/admin', $source);
    }

    /** @test */
    public function controlador_no_usa_redirect_de_usuario(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/App/Controllers/LoginController.php'
        );

        // Nunca debe leer ?redirect= o similar del input del usuario
        $this->assertStringNotContainsString("\$_GET['redirect']",  $source);
        $this->assertStringNotContainsString("\$_POST['redirect']", $source);
    }

    // ── LoginService ──────────────────────────────────────────────────────────

    /** @test */
    public function login_service_devuelve_null_para_credenciales_invalidas(): void
    {
        $pdo = \Database\Connection::getConnection();
        $pdo->exec("INSERT OR IGNORE INTO agentes (nombre,apellido,correo,contrasena,rol)
                    VALUES ('Luis','Lopez','luis@test.com','" . password_hash('correcto', PASSWORD_DEFAULT) . "','asesor')");

        $service = new \App\Services\LoginService(new \App\Models\AgentModel());
        $result  = $service->login('luis@test.com', 'incorrecto');

        $this->assertNull($result, 'Contraseña incorrecta debe devolver null');
    }

    /** @test */
    public function login_service_devuelve_agente_para_credenciales_validas(): void
    {
        $pass = password_hash('mi_clave', PASSWORD_DEFAULT);
        $pdo  = \Database\Connection::getConnection();
        $pdo->exec("INSERT OR IGNORE INTO agentes (nombre,apellido,correo,contrasena,rol)
                    VALUES ('Ana','Garcia','ana@test.com','{$pass}','asesor')");

        $service = new \App\Services\LoginService(new \App\Models\AgentModel());
        $agente  = $service->login('ana@test.com', 'mi_clave');

        $this->assertIsArray($agente);
        $this->assertSame('ana@test.com', $agente['correo']);
    }

    /** @test */
    public function login_service_devuelve_null_para_correo_inexistente(): void
    {
        $service = new \App\Services\LoginService(new \App\Models\AgentModel());
        $result  = $service->login('nadie@noexiste.com', 'cualquier');

        $this->assertNull($result);
    }

    // ── Respuesta AJAX ────────────────────────────────────────────────────────

    /** @test */
    public function controlador_soporta_respuesta_json_para_ajax(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/App/Controllers/LoginController.php'
        );

        $this->assertStringContainsString('XMLHttpRequest', $source);
        $this->assertStringContainsString('application/json', $source);
    }

    // ── Seguridad: no filtrar detalles de excepción ───────────────────────────

    /** @test */
    public function controlador_captura_excepciones_de_bd(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/App/Controllers/LoginController.php'
        );

        $this->assertStringContainsString('catch', $source);
        $this->assertStringContainsString('Throwable', $source);
    }
}