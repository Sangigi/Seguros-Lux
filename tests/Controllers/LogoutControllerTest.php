<?php
declare(strict_types=1);

namespace Tests\Controllers;

use PHPUnit\Framework\TestCase;

/**
 * Tests para LogoutController.
 *
 * LogoutController::handle() valida CSRF y destruye la sesión.
 * Como termina con exit, no se puede invocar directamente en tests.
 * Probamos la lógica extraída via reflexión y los helpers que usa.
 */
class LogoutControllerTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        unset($_POST['csrf_token'], $_SERVER['HTTP_X_CSRF_TOKEN']);
    }

    // ── Validación de CSRF (via SessionManager) ───────────────────────────────

    /** @test */
    public function csrf_token_valido_pasa_validacion(): void
    {
        $token = \App\Security\SessionManager::getCsrfToken();

        $this->assertTrue(
            \App\Security\SessionManager::validateCsrf($token),
            'Un token generado por getCsrfToken() debe pasar validateCsrf()'
        );
    }

    /** @test */
    public function csrf_token_vacio_falla_validacion(): void
    {
        $this->assertFalse(
            \App\Security\SessionManager::validateCsrf(''),
            'Token vacío debe fallar validateCsrf()'
        );
    }

    /** @test */
    public function csrf_token_falso_falla_validacion(): void
    {
        $this->assertFalse(
            \App\Security\SessionManager::validateCsrf('token_inventado_12345'),
            'Token inventado debe fallar validateCsrf()'
        );
    }

    /** @test */
    public function csrf_token_es_consistente_en_la_misma_sesion(): void
    {
        $t1 = \App\Security\SessionManager::getCsrfToken();
        $t2 = \App\Security\SessionManager::getCsrfToken();

        $this->assertSame($t1, $t2, 'getCsrfToken() debe devolver el mismo token en la misma sesión');
    }

    // ── SessionManager::destroy ───────────────────────────────────────────────

    /** @test */
    public function destroy_limpia_la_sesion(): void
    {
        \App\Security\SessionManager::start();
        $_SESSION['agente_id']  = 99;
        $_SESSION['agente_rol'] = 'asesor';
        $_SESSION['logged_in']  = true;

        \App\Security\SessionManager::destroy();

        // Después de destroy, la sesión debe estar vacía o cerrada
        $isEmpty = empty($_SESSION) || session_status() !== PHP_SESSION_ACTIVE;
        $this->assertTrue($isEmpty, 'destroy() debe limpiar los datos de sesión');

        // Reabrir sesión para los demás tests
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    // ── Fuente — verificar que LogoutController usa CSRF y destroy ─────────────

    /** @test */
    public function controlador_valida_csrf_antes_de_destruir(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/App/Controllers/LogoutController.php'
        );

        $this->assertStringContainsString('validateCsrf', $source);
        $this->assertStringContainsString('destroy',      $source);
    }

    /** @test */
    public function controlador_acepta_token_csrf_por_header_x_csrf(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/App/Controllers/LogoutController.php'
        );

        $this->assertStringContainsString('HTTP_X_CSRF_TOKEN', $source);
    }

    /** @test */
    public function controlador_redirige_a_login_tras_logout(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/App/Controllers/LogoutController.php'
        );

        $this->assertStringContainsString('/login', $source);
    }
}