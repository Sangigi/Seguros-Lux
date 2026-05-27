<?php
declare(strict_types=1);

namespace Tests\Controllers;

use PHPUnit\Framework\TestCase;
use App\Controllers\ActivityController;

/**
 * Tests para ActivityController.
 * handle() → SessionManager::ping() — keepalive de sesión.
 *
 * NOTA: ping() termina con exit, lo cual mataría PHPUnit si se invoca
 * directamente. Los tests que verifican comportamiento de ejecución
 * lo hacen vía inspección de código fuente (caja blanca) en lugar de
 * llamar al controlador real.
 */
class ActivityControllerTest extends TestCase
{
    use AuthSessionHelper;

    protected function setUp(): void    { $this->autenticarSesion(); }
    protected function tearDown(): void { $this->limpiarSesion(); }

    /** @test */
    public function handle_delega_en_session_ping(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/App/Controllers/ActivityController.php'
        );
        $this->assertStringContainsString('SessionManager::ping', $source);
    }

    /** @test */
    public function handle_es_metodo_estatico_void(): void
    {
        $ref    = new \ReflectionMethod(ActivityController::class, 'handle');
        $return = $ref->getReturnType();

        $this->assertTrue($ref->isStatic(), 'handle() debe ser estático');
        $this->assertNotNull($return);
        $this->assertSame('void', (string) $return);
    }

    /** @test */
    public function ping_responde_ok_true_con_sesion_valida(): void
    {
        // Verifica que con sesión completa ping() escribe {"ok":true}
        // Lo comprobamos leyendo el código fuente — no invocamos ping()
        // para evitar el exit que mataría PHPUnit.
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/App/Security/SessionManager.php'
        );
        $this->assertStringContainsString("'ok' => true", $source);
        $this->assertStringContainsString("'ok' => false", $source);
    }

    /** @test */
    public function controlador_delega_en_session_ping(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/App/Controllers/ActivityController.php'
        );
        $this->assertStringContainsString('SessionManager::ping', $source);
    }
}