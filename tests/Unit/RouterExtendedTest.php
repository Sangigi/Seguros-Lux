<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Router;

/**
 * Tests unitarios del Router.
 *
 * Cubre:
 *   - Despacho de rutas GET y POST
 *   - Rutas con parámetros dinámicos (:id)
 *   - Respuesta 404 para rutas no registradas
 *   - Handler como callable, array [Clase, método] y clase con handle()
 *   - normalizePath
 *   - Trailing slashes
 */
final class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        require_once dirname(__DIR__, 2) . '/Core/Router.php';
        $this->router = new Router();
    }

    // ── normalizePath ─────────────────────────────────────────────────────────

    public function testNormalizePathConvierteBarrasInvertidas(): void
    {
        $result = Router::normalizePath('App\\Views\\errors\\404.php');
        $this->assertStringNotContainsString('\\', $result);
    }

    public function testNormalizePathMantieneRutaNormal(): void
    {
        $result = Router::normalizePath('/home/claude/app/index.php');
        $this->assertStringContainsString('index.php', $result);
    }

    public function testNormalizePathMixedSeparators(): void
    {
        $result = Router::normalizePath('/root/App\\Controllers/Test.php');
        $this->assertStringNotContainsString('\\', $result);
    }

    // ── Dispatch — callable ───────────────────────────────────────────────────

    public function testDespachaRutaGetConCallable(): void
    {
        $executed = false;
        $this->router->add('GET', '/ping', function () use (&$executed) {
            $executed = true;
        });

        $this->router->dispatch('GET', '/ping');
        $this->assertTrue($executed, 'El callable debería haberse ejecutado');
    }

    public function testDespachaRutaPostConCallable(): void
    {
        $executed = false;
        $this->router->add('POST', '/submit', function () use (&$executed) {
            $executed = true;
        });

        $this->router->dispatch('POST', '/submit');
        $this->assertTrue($executed);
    }

    // ── Parámetros dinámicos ──────────────────────────────────────────────────

    public function testDespachaRutaConParametroId(): void
    {
        $capturado = null;
        $this->router->add('GET', '/clientes/:id', function () use (&$capturado) {
            $capturado = $_GET['id'] ?? null;
        });

        $this->router->dispatch('GET', '/clientes/42');
        $this->assertSame('42', $capturado);
    }

    public function testDespachaRutaConMultiplesParametros(): void
    {
        $capturedUser = null;
        $capturedPost = null;
        $this->router->add('GET', '/users/:user/posts/:post', function () use (&$capturedUser, &$capturedPost) {
            $capturedUser = $_GET['user'] ?? null;
            $capturedPost = $_GET['post'] ?? null;
        });

        $this->router->dispatch('GET', '/users/5/posts/12');
        $this->assertSame('5',  $capturedUser);
        $this->assertSame('12', $capturedPost);
    }

    // ── Trailing slash ────────────────────────────────────────────────────────

    public function testTrailingSlashSeNormaliza(): void
    {
        $executed = false;
        $this->router->add('GET', '/dashboard', function () use (&$executed) {
            $executed = true;
        });

        $this->router->dispatch('GET', '/dashboard/');
        $this->assertTrue($executed, 'Trailing slash debe normalizarse');
    }

    // ── 404 ───────────────────────────────────────────────────────────────────

    public function testRutaNoRegistradaDevuelve404(): void
    {
        ob_start();
        $this->router->dispatch('GET', '/ruta-que-no-existe');
        ob_end_clean();

        $this->assertSame(404, http_response_code());
    }

    public function testMetodoIncorrectoNoDespacha(): void
    {
        $executed = false;
        $this->router->add('GET', '/solo-get', function () use (&$executed) {
            $executed = true;
        });

        ob_start();
        $this->router->dispatch('POST', '/solo-get');
        ob_end_clean();

        $this->assertFalse($executed, 'POST no debe despachar una ruta GET');
    }

    // ── Handler array [Clase, método] ─────────────────────────────────────────

    public function testDespachaHandlerComoArray(): void
    {
        // Definir clase de prueba inline
        if (!class_exists('TestHandlerClass')) {
            eval('class TestHandlerClass { public function index(): void { echo "ok"; } }');
        }

        $this->router->add('GET', '/handler-array', ['TestHandlerClass', 'index']);

        ob_start();
        $this->router->dispatch('GET', '/handler-array');
        $output = ob_get_clean();

        $this->assertSame('ok', $output);
    }

    // ── Handler clase con handle() estático ──────────────────────────────────

    public function testDespachaHandlerComoClaseConHandle(): void
    {
        if (!class_exists('TestStaticHandler')) {
            eval('class TestStaticHandler { public static function handle(): void { echo "handled"; } }');
        }

        $this->router->add('GET', '/handler-static', 'TestStaticHandler');

        ob_start();
        $this->router->dispatch('GET', '/handler-static');
        $output = ob_get_clean();

        $this->assertSame('handled', $output);
    }

    // ── Query string ignorada ─────────────────────────────────────────────────

    public function testQueryStringNoInterfiereConElDespacho(): void
    {
        $executed = false;
        $this->router->add('GET', '/api/datos', function () use (&$executed) {
            $executed = true;
        });

        $this->router->dispatch('GET', '/api/datos?page=1&per_page=25');
        $this->assertTrue($executed);
    }
}
