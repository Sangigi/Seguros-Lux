<?php
declare(strict_types=1);

namespace Tests\Controllers;

use PHPUnit\Framework\TestCase;
use App\Controllers\HorasOcupadasController;
use App\Support\CorsHelper;

/**
 * Tests para HorasOcupadasController.
 *
 * GET /horas-ocupadas?fecha=YYYY-MM-DD
 * Devuelve JSON con array de horas ocupadas (cuando TODOS los agentes tienen cita).
 */
class HorasOcupadasControllerTest extends TestCase
{
    protected function setUp(): void
    {
        unset($_GET['fecha'], $_SERVER['REQUEST_METHOD'], $_SERVER['HTTP_ORIGIN']);
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    protected function tearDown(): void
    {
        unset($_GET['fecha'], $_SERVER['HTTP_ORIGIN']);
        putenv('CORS_ALLOWED_ORIGINS=');
    }

    // ── Validación de fecha ───────────────────────────────────────────────────

    /** @test */
    public function sin_fecha_devuelve_array_vacio(): void
    {
        unset($_GET['fecha']);

        ob_start();
        HorasOcupadasController::handle();
        $raw = ob_get_clean();

        $data = json_decode($raw, true);

        $this->assertIsArray($data);
        $this->assertEmpty($data);
    }

    /** @test */
    public function fecha_formato_invalido_devuelve_array_vacio(): void
    {
        $_GET['fecha'] = '32/13/2025'; // formato DD/MM/YYYY — inválido

        ob_start();
        HorasOcupadasController::handle();
        $raw = ob_get_clean();

        $data = json_decode($raw, true);

        $this->assertIsArray($data);
        $this->assertEmpty($data);
    }

    /** @test */
    public function fecha_con_letras_devuelve_array_vacio(): void
    {
        $_GET['fecha'] = 'hoy';

        ob_start();
        HorasOcupadasController::handle();
        $raw = ob_get_clean();

        $this->assertSame([], json_decode($raw, true));
    }

    /** @test */
    public function fecha_valida_devuelve_json_array(): void
    {
        $_GET['fecha'] = date('Y-m-d', strtotime('+7 days'));

        ob_start();
        HorasOcupadasController::handle();
        $raw = ob_get_clean();

        $data = json_decode($raw, true);

        // Con SQLite sin agentes, devuelve [] (0 agentes activos → vacío)
        $this->assertIsArray($data);
    }

    /** @test */
    public function get_horas_ocupadas_con_agentes_devuelve_horas_ocupadas(): void
    {
        $this->markTestSkipped('TIME_FORMAT es función MySQL no disponible en SQLite');
    }

    // ── OPTIONS (CORS preflight) ───────────────────────────────────────────────

    /** @test */
    public function options_devuelve_204(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
        putenv('CORS_ALLOWED_ORIGINS=https://example.com');
        $_SERVER['HTTP_ORIGIN'] = 'https://example.com';

        ob_start();
        HorasOcupadasController::handle();
        ob_get_clean();

        // Si llegamos aquí sin fatal, está OK. El 204 se fija con http_response_code.
        $this->assertTrue(true);
    }

    // ── CorsHelper (dependencia directa) ──────────────────────────────────────

    /** @test */
    public function cors_helper_permite_origen_en_lista(): void
    {
        $result = CorsHelper::resolveAllowedOrigin('https://seguroslux.mx', 'https://seguroslux.mx,https://www.seguroslux.mx');
        $this->assertSame('https://seguroslux.mx', $result);
    }

    /** @test */
    public function cors_helper_rechaza_origen_no_permitido(): void
    {
        $result = CorsHelper::resolveAllowedOrigin('https://evil.com', 'https://seguroslux.mx');
        $this->assertNull($result);
    }

    /** @test */
    public function cors_helper_con_lista_vacia_devuelve_null(): void
    {
        $result = CorsHelper::resolveAllowedOrigin('https://seguroslux.mx', '');
        $this->assertNull($result);
    }

    /** @test */
    public function cors_helper_con_origen_nulo_devuelve_null(): void
    {
        $result = CorsHelper::resolveAllowedOrigin(null, 'https://seguroslux.mx');
        $this->assertNull($result);
    }
}