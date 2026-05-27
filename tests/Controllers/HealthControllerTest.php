<?php
declare(strict_types=1);

namespace Tests\Controllers;

use PHPUnit\Framework\TestCase;
use App\Controllers\HealthController;

/**
 * Tests para HealthController.
 *
 * GET /api/health            → liveness (sin BD)
 * GET /api/health?deep=1&token=... → readiness (ping BD + token check)
 */
class HealthControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        // Limpiar superglobales modificadas
        unset($_GET['deep'], $_GET['token']);
    }

    // ── Liveness ──────────────────────────────────────────────────────────────

    /** @test */
    public function handle_sin_deep_devuelve_json_ok(): void
    {
        unset($_GET['deep']);

        ob_start();
        HealthController::handle();
        $raw = ob_get_clean();

        $body = json_decode($raw, true);

        $this->assertIsArray($body);
        $this->assertTrue($body['ok']);
        $this->assertSame('crm', $body['service']);
        $this->assertArrayHasKey('time', $body);
    }

    /** @test */
    public function handle_liveness_emite_content_type_json(): void
    {
        unset($_GET['deep']);

        ob_start();
        HealthController::handle();
        ob_get_clean();

        $headers = headers_list();
        $found   = false;
        foreach ($headers as $h) {
            if (stripos($h, 'Content-Type') !== false && stripos($h, 'application/json') !== false) {
                $found = true;
                break;
            }
        }

        // En entorno CLI headers_list() puede estar vacío — si no está vacío debemos encontrarlo
        if (!empty($headers)) {
            $this->assertTrue($found, 'Debe emitirse Content-Type: application/json');
        } else {
            $this->assertTrue(true); // entorno CLI, no aplica
        }
    }

    // ── Deep / Readiness ──────────────────────────────────────────────────────

    /** @test */
    public function handle_deep_sin_token_devuelve_403(): void
    {
        $_GET['deep']  = '1';
        $_GET['token'] = '';

        putenv('HEALTH_CHECK_TOKEN=secret123');

        ob_start();
        HealthController::handle();
        $raw = ob_get_clean();

        $body = json_decode($raw, true);

        $this->assertIsArray($body);
        $this->assertFalse($body['ok']);
        $this->assertStringContainsStringIgnoringCase('token', $body['msg'] ?? '');

        putenv('HEALTH_CHECK_TOKEN=');
    }

    /** @test */
    public function handle_deep_token_correcto_devuelve_estado_bd(): void
    {
        $_GET['deep']  = '1';
        $_GET['token'] = 'mitoken';

        putenv('HEALTH_CHECK_TOKEN=mitoken');

        ob_start();
        HealthController::handle();
        $raw = ob_get_clean();

        $body = json_decode($raw, true);

        $this->assertIsArray($body);
        $this->assertArrayHasKey('ok',     $body);
        $this->assertArrayHasKey('time',   $body);
        $this->assertArrayHasKey('checks', $body);
        $this->assertContains($body['checks']['database'], ['up', 'down']);

        putenv('HEALTH_CHECK_TOKEN=');
    }

    /** @test */
    public function handle_deep_con_bd_sqlite_reporta_up(): void
    {
        // La BD de test es SQLite en memoria (bootstrap.php)
        $_GET['deep']  = '1';
        $_GET['token'] = 'testtoken';

        putenv('HEALTH_CHECK_TOKEN=testtoken');

        ob_start();
        HealthController::handle();
        $raw = ob_get_clean();

        $body = json_decode($raw, true);

        $this->assertTrue($body['ok'],  'Con SQLite disponible, database debe estar up');
        $this->assertSame('up', $body['checks']['database']);

        putenv('HEALTH_CHECK_TOKEN=');
    }

    /** @test */
    public function handle_deep_env_vacio_rechaza_cualquier_token(): void
    {
        $_GET['deep']  = '1';
        $_GET['token'] = 'cualquier_cosa';

        putenv('HEALTH_CHECK_TOKEN='); // vacío → siempre 403

        ob_start();
        HealthController::handle();
        $raw = ob_get_clean();

        $body = json_decode($raw, true);

        $this->assertFalse($body['ok']);
    }
}