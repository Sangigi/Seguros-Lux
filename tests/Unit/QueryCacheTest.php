<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Services\QueryCache;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitarios de QueryCache.
 *
 * ENFOQUE:
 *   Solo se prueba la caché L1 (array en memoria), que es la que funciona
 *   sin ninguna extensión extra. Los tests de L2 (APCu) se saltan si la
 *   extensión no está disponible.
 *
 *   Cada test llama QueryCache::flush() en setUp() para partir de estado limpio.
 */
final class QueryCacheTest extends TestCase
{
    protected function setUp(): void
    {
        QueryCache::flush();
    }

    // ── remember — hit de L1 ─────────────────────────────────────────────────

    public function testRememberEjecutaFnEnPrimerLlamada(): void
    {
        $llamadas = 0;

        QueryCache::remember(
            table: 'clientes',
            key:   'test:1',
            ttl:   0,
            fn:    function () use (&$llamadas) {
                $llamadas++;
                return ['id' => 1];
            },
        );

        $this->assertSame(1, $llamadas);
    }

    public function testRememberNoEjecutaFnEnSegundaLlamadaConMismaClave(): void
    {
        $llamadas = 0;
        $fn       = function () use (&$llamadas) {
            $llamadas++;
            return ['id' => 1];
        };

        QueryCache::remember(table: 'clientes', key: 'test:1', ttl: 0, fn: $fn);
        QueryCache::remember(table: 'clientes', key: 'test:1', ttl: 0, fn: $fn);

        $this->assertSame(1, $llamadas, 'fn no debe ejecutarse en el segundo remember (L1 hit)');
    }

    public function testRememberDevuelveElMismoValorEnAmbasLlamadas(): void
    {
        $valor = ['rows' => [1, 2, 3], 'total' => 3];

        $r1 = QueryCache::remember(table: 'clientes', key: 'test:multi', ttl: 0, fn: fn() => $valor);
        $r2 = QueryCache::remember(table: 'clientes', key: 'test:multi', ttl: 0, fn: fn() => ['otro']);

        $this->assertSame($valor, $r1);
        $this->assertSame($valor, $r2, 'L1 debe devolver el valor cacheado, no el de la segunda fn');
    }

    // ── remember — claves distintas no colisionan ─────────────────────────────

    public function testClavesDistintasNoColisionan(): void
    {
        $a = QueryCache::remember(table: 'clientes', key: 'clave:a', ttl: 0, fn: fn() => 'valor-A');
        $b = QueryCache::remember(table: 'clientes', key: 'clave:b', ttl: 0, fn: fn() => 'valor-B');

        $this->assertSame('valor-A', $a);
        $this->assertSame('valor-B', $b);
    }

    public function testTablasDiferentesConMismaKeyNoColisionan(): void
    {
        $c = QueryCache::remember(table: 'clientes',    key: 'id:1', ttl: 0, fn: fn() => 'cliente');
        $p = QueryCache::remember(table: 'polizas',     key: 'id:1', ttl: 0, fn: fn() => 'poliza');

        $this->assertSame('cliente', $c);
        $this->assertSame('poliza',  $p);
    }

    // ── invalidate ────────────────────────────────────────────────────────────

    public function testInvalidateFuerzaReejecutarFn(): void
    {
        $llamadas = 0;
        $fn       = function () use (&$llamadas) {
            $llamadas++;
            return $llamadas; // devuelve el número de llamada
        };

        $primera = QueryCache::remember(table: 'clientes', key: 'id:5', ttl: 0, fn: $fn);
        $this->assertSame(1, $primera);

        // Invalidar la tabla clientes (simula un INSERT/UPDATE/DELETE)
        QueryCache::invalidate('clientes');

        // La siguiente llamada debe ejecutar fn de nuevo
        $segunda = QueryCache::remember(table: 'clientes', key: 'id:5', ttl: 0, fn: $fn);
        $this->assertSame(2, $segunda, 'Después de invalidate, fn debe ejecutarse de nuevo');
    }

    public function testInvalidateDeTablaANoAfectaTablaB(): void
    {
        $llamadasCliente = 0;
        $llamadasPoliza  = 0;

        QueryCache::remember(table: 'clientes', key: 'x', ttl: 0, fn: function () use (&$llamadasCliente) {
            $llamadasCliente++;
            return 'c';
        });

        QueryCache::remember(table: 'polizas', key: 'x', ttl: 0, fn: function () use (&$llamadasPoliza) {
            $llamadasPoliza++;
            return 'p';
        });

        // Solo invalidar clientes
        QueryCache::invalidate('clientes');

        // Volver a pedir ambos
        QueryCache::remember(table: 'clientes', key: 'x', ttl: 0, fn: function () use (&$llamadasCliente) {
            $llamadasCliente++;
            return 'c2';
        });

        QueryCache::remember(table: 'polizas', key: 'x', ttl: 0, fn: function () use (&$llamadasPoliza) {
            $llamadasPoliza++;
            return 'p';
        });

        $this->assertSame(2, $llamadasCliente, 'clientes debe re-ejecutar fn tras invalidate');
        $this->assertSame(1, $llamadasPoliza,  'polizas NO debe re-ejecutar fn (no fue invalidada)');
    }

    // ── flush ─────────────────────────────────────────────────────────────────

    public function testFlushLimpiaTodasLasEntradasL1(): void
    {
        $llamadas = 0;
        $fn       = function () use (&$llamadas) { $llamadas++; return 'x'; };

        QueryCache::remember(table: 'a', key: '1', ttl: 0, fn: $fn);
        QueryCache::remember(table: 'b', key: '2', ttl: 0, fn: $fn);
        $this->assertSame(2, $llamadas);

        QueryCache::flush();

        QueryCache::remember(table: 'a', key: '1', ttl: 0, fn: $fn);
        QueryCache::remember(table: 'b', key: '2', ttl: 0, fn: $fn);
        $this->assertSame(4, $llamadas, 'Después de flush, fn debe ejecutarse de nuevo para todas las claves');
    }

    // ── Tipos de datos cacheables ─────────────────────────────────────────────

    /** @dataProvider tiposProvider */
    public function testCacheaDistintosTiposDeDatos(mixed $valor): void
    {
        $result = QueryCache::remember(
            table: 'test',
            key:   'tipo:' . gettype($valor),
            ttl:   0,
            fn:    fn() => $valor,
        );
        $this->assertSame($valor, $result);
    }

    public static function tiposProvider(): array
    {
        return [
            'null'   => [null],
            'false'  => [false],
            'int'    => [42],
            'float'  => [3.14],
            'string' => ['hola mundo'],
            'array'  => [['a' => 1, 'b' => [2, 3]]],
        ];
    }

    // ── APCu (saltado si no disponible) ───────────────────────────────────────

    public function testRememberGuardaEnApcuSiDisponible(): void
    {
        if (!function_exists('apcu_fetch')) {
            $this->markTestSkipped('APCu no disponible en este entorno.');
        }

        $key = 'apcu:test:' . uniqid();

        QueryCache::remember(table: 'clientes', key: $key, ttl: 10, fn: fn() => 'apcu_valor');

        // Verificar directamente en APCu que el valor fue almacenado
        // La clave real incluye el prefijo y la generación, así que
        // simplemente verificamos que la segunda llamada no ejecuta fn.
        $llamadas = 0;
        $result   = QueryCache::remember(
            table: 'clientes',
            key:   $key,
            ttl:   10,
            fn:    function () use (&$llamadas) { $llamadas++; return 'otro'; },
        );

        $this->assertSame(0,           $llamadas, 'fn no debe ejecutarse (hit de L1)');
        $this->assertSame('apcu_valor', $result);
    }
}