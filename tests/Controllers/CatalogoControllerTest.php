<?php
declare(strict_types=1);

namespace Tests\Controllers;

use App\Controllers\CatalogoController;
use App\Models\CatalogoModel;
use App\Services\QueryCache;
use PHPUnit\Framework\TestCase;

/**
 * Contrato de GET /api/catalogos: lo que la pantalla de Configuración y las
 * pantallas que vengan después esperan recibir.
 *
 * Los POST leen php://input y no se pueden simular aquí; sus reglas se
 * prueban en el modelo (tests/Integration/CatalogosTest.php).
 */
class CatalogoControllerTest extends TestCase
{
    use AuthSessionHelper;

    protected function setUp(): void
    {
        $this->autenticarSesion(1, 'superadmin');
        $_SERVER['REQUEST_METHOD'] = 'GET';
        QueryCache::flush();
    }

    protected function tearDown(): void
    {
        $this->limpiarSesion();
        unset($_SERVER['REQUEST_METHOD']);
    }

    public function testGetDevuelveElCatalogoAgrupadoPorRamo(): void
    {
        ob_start();
        CatalogoController::handle();
        $body = json_decode((string) ob_get_clean(), true);

        $this->assertTrue($body['ok']);
        $this->assertSame(array_keys(CatalogoModel::RAMOS), array_column($body['ramos'], 'clave'));
        $this->assertSame(array_keys(CatalogoModel::RAMOS), array_keys($body['por_ramo']));

        foreach ($body['por_ramo'] as $ramo => $listas) {
            $this->assertSame(['aseguradoras', 'coberturas', 'documentos'], array_keys($listas));
            $this->assertNotEmpty($listas['aseguradoras'], "Sin aseguradoras en {$ramo}.");
        }

        $fila = $body['por_ramo']['autos']['aseguradoras'][0];
        foreach (['id', 'tipo', 'ramo', 'clave', 'etiqueta', 'activo', 'orden', 'eliminable', 'conmutable'] as $campo) {
            $this->assertArrayHasKey($campo, $fila);
        }
        $this->assertIsBool($fila['activo']);
    }

    public function testGetIncluyeLoInactivo(): void
    {
        ob_start();
        CatalogoController::handle();
        $body = json_decode((string) ob_get_clean(), true);

        $documentos = array_column($body['por_ramo']['autos']['documentos'], 'activo', 'clave');

        $this->assertFalse($documentos['foto_coche_5'], 'Los documentos no requeridos también deben llegar.');
        $this->assertTrue($documentos['ine']);
    }
}
