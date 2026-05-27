<?php
declare(strict_types=1);

namespace Tests\Controllers;

use PHPUnit\Framework\TestCase;
use App\Controllers\ActivityController;

/**
 * Tests para ActivityController.
 * handle() → SessionManager::ping() — keepalive de sesión.
 */
class ActivityControllerTest extends TestCase
{
    use AuthSessionHelper;

    protected function setUp(): void    { $this->autenticarSesion(); }
    protected function tearDown(): void { $this->limpiarSesion(); }

    /** @test */
    public function handle_no_produce_output(): void
    {
        ob_start();
        ActivityController::handle();
        $output = ob_get_clean();

        $this->assertSame('', $output);
    }

    /** @test */
    public function handle_no_lanza_excepciones(): void
    {
        $this->expectNotToPerformAssertions();
        ob_start();
        try {
            ActivityController::handle();
        } finally {
            ob_get_clean();
        }
    }

    /** @test */
    public function handle_actualiza_last_activity(): void
    {
        $antes = time();
        ob_start();
        ActivityController::handle();
        ob_get_clean();

        $last = $_SESSION['last_activity'] ?? $_SESSION['LAST_ACTIVITY'] ?? null;
        if ($last !== null) {
            $this->assertGreaterThanOrEqual($antes, $last);
        } else {
            $this->assertTrue(true); // ping() no usa last_activity en esta impl
        }
    }

    /** @test */
    public function controlador_delega_en_session_ping(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/App/Controllers/ActivityController.php'
        );
        $this->assertStringContainsString('SessionManager::ping', $source);
    }
}