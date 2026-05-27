<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests unitarios del Router.
 *
 * CORRECCIÓN respecto a la versión anterior:
 *   - Namespace corregido a Tests\Unit (antes era Tests\ — namespace raíz).
 *     El phpunit.xml busca los tests de Unit en tests/Unit/, así que el
 *     namespace debe coincidir.
 *   - Se mantiene el require_once manual porque Router.php no tiene namespace
 *     y el autoloader PSR-4 no lo carga automáticamente.
 *
 * COBERTURA:
 *   - normalizePath()  → separadores, rutas absolutas
 *   - add() + dispatch() → rutas estáticas, rutas con parámetros, 404
 *   - dispatch()       → trim de trailing slash
 */

require_once dirname(__DIR__, 2) . '/Core/Router.php';

final class RouterTest extends TestCase
{
    private \Router $router;

    protected function setUp(): void
    {
        $this->router = new \Router();
    }

    // ── normalizePath ─────────────────────────────────────────────────────────

    public function testNormalizePathUnificaSeparadoresMixtos(): void
    {
        $this->assertSame('foo/bar/baz', \Router::normalizePath('foo/bar\baz'));
    }

    public function testNormalizePathNoModificaRutaYaNormalizada(): void
    {
        $this->assertSame('foo/bar/baz', \Router::normalizePath('foo/bar/baz'));
    }

    public function testNormalizePathManejaStringVacio(): void
    {
        $this->assertSame('', \Router::normalizePath(''));
    }

    public function testNormalizePathManejaRutaAbsoluta(): void
    {
        // Solo verificamos que devuelve string — el separador depende del SO
        $result = \Router::normalizePath('/var/www/html/index.php');
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    // ── add + dispatch: rutas estáticas ───────────────────────────────────────

    public function testDispatchEjecutaHandlerCallable(): void
    {
        $ejecutado = false;

        $this->router->add('GET', '/test', function () use (&$ejecutado): void {
            $ejecutado = true;
        });

        // Capturar output y suprimir http_response_code en CLI
        ob_start();
        $this->router->dispatch('GET', '/test');
        ob_end_clean();

        $this->assertTrue($ejecutado, 'El handler debe ejecutarse al hacer dispatch en la ruta correcta');
    }

    public function testDispatchNoEjecutaHandlerEnRutaDistinta(): void
    {
        $ejecutado = false;

        $this->router->add('GET', '/ruta-a', function () use (&$ejecutado): void {
            $ejecutado = true;
        });

        // Ruta /ruta-b no existe → no debe ejecutar el handler de /ruta-a
        ob_start();
        try {
            $this->router->dispatch('GET', '/ruta-b');
        } catch (\Throwable) {
            // 404 puede hacer require de un archivo que no existe en CLI — ignorar
        }
        ob_end_clean();

        $this->assertFalse($ejecutado);
    }

    public function testDispatchDistingueMetodoHTTP(): void
    {
        $ejecutadoGet  = false;
        $ejecutadoPost = false;

        $this->router->add('GET',  '/metodo', function () use (&$ejecutadoGet):  void { $ejecutadoGet  = true; });
        $this->router->add('POST', '/metodo', function () use (&$ejecutadoPost): void { $ejecutadoPost = true; });

        ob_start();
        $this->router->dispatch('GET', '/metodo');
        ob_end_clean();

        $this->assertTrue($ejecutadoGet,   'GET /metodo debe ejecutar el handler GET');
        $this->assertFalse($ejecutadoPost, 'GET /metodo NO debe ejecutar el handler POST');
    }

    // ── add + dispatch: rutas con parámetros ─────────────────────────────────

    public function testDispatchExtraeParametroDeRuta(): void
    {
        $idCapturado = null;

        $this->router->add('GET', '/clientes/:id', function () use (&$idCapturado): void {
            $idCapturado = $_GET['id'] ?? null;
        });

        ob_start();
        $this->router->dispatch('GET', '/clientes/42');
        ob_end_clean();

        $this->assertSame('42', $idCapturado, 'El parámetro :id debe extraerse de la URL y guardarse en $_GET');
    }

    public function testDispatchExtraeMultiplesParametros(): void
    {
        $capturado = [];

        $this->router->add('GET', '/agentes/:agenteId/clientes/:clienteId', function () use (&$capturado): void {
            $capturado = ['agente' => $_GET['agenteId'] ?? null, 'cliente' => $_GET['clienteId'] ?? null];
        });

        ob_start();
        $this->router->dispatch('GET', '/agentes/5/clientes/99');
        ob_end_clean();

        $this->assertSame('5',  $capturado['agente']);
        $this->assertSame('99', $capturado['cliente']);
    }

    // ── trailing slash ────────────────────────────────────────────────────────

    public function testDispatchTrimTrailingSlash(): void
    {
        $ejecutado = false;

        $this->router->add('GET', '/dashboard', function () use (&$ejecutado): void {
            $ejecutado = true;
        });

        ob_start();
        $this->router->dispatch('GET', '/dashboard/');
        ob_end_clean();

        $this->assertTrue($ejecutado, 'La ruta /dashboard/ con trailing slash debe coincidir con /dashboard');
    }

    public function testDispatchNoTrimSlashEnRaiz(): void
    {
        $ejecutado = false;

        $this->router->add('GET', '/', function () use (&$ejecutado): void {
            $ejecutado = true;
        });

        ob_start();
        $this->router->dispatch('GET', '/');
        ob_end_clean();

        $this->assertTrue($ejecutado, 'La ruta raíz / debe seguir funcionando');
    }

    // ── handler como string (clase estática con ::handle()) ───────────────────

    public function testDispatchEjecutaHandlerDeClaseConMetodoHandle(): void
    {
        // Definir una clase stub inline para este test
        if (!class_exists('StubHandlerController')) {
            eval('class StubHandlerController {
                public static bool $called = false;
                public static function handle(): void { self::$called = true; }
            }');
        }

        \StubHandlerController::$called = false;

        $this->router->add('GET', '/stub', 'StubHandlerController');

        ob_start();
        $this->router->dispatch('GET', '/stub');
        ob_end_clean();

        $this->assertTrue(\StubHandlerController::$called, 'dispatch() debe llamar a StubHandlerController::handle()');
    }
}
