<?php
declare(strict_types=1);

namespace Tests\Security;

use App\Security\RateLimiter;
use PHPUnit\Framework\TestCase;

/**
 * Tests de seguridad de RateLimiter.
 *
 * CORRECCIÓN respecto a la versión anterior:
 *   - ELIMINADO: overrideCacheDir() via Reflection.
 *     RateLimiter::CACHE_DIR es una constante de clase — las constantes no
 *     son propiedades y no se pueden modificar en runtime con ReflectionProperty.
 *     El intento silencioso hacía que los tests usaran el directorio REAL de
 *     storage/ratelimit/ sin que el desarrollador lo supiera.
 *
 * ESTRATEGIA ACTUAL:
 *   Los tests usan el directorio real (storage/ratelimit/).
 *   Para garantizar aislamiento:
 *     1. setUp()    → elimina el fichero de estado del par correo+IP de prueba.
 *     2. tearDown() → lo elimina de nuevo (limpieza post-test).
 *   Las claves de prueba usan correos y IPs reservados (RFC 5737/RFC 2606)
 *   que nunca coincidirán con actividad real en producción.
 *
 * ALTERNATIVA FUTURA (si se quiere aislamiento total):
 *   Refactorizar RateLimiter para recibir el directorio via inyección de
 *   dependencias o variable de entorno en lugar de una constante, y luego
 *   setear RATELIMIT_DIR=sys_get_temp_dir() en phpunit.xml.
 *   Ver: phpunit.xml → <env name="RATELIMIT_DIR" .../>
 */
final class RateLimiterTest extends TestCase
{
    /** IP del bloque de documentación RFC 5737 — nunca es una IP real de usuario */
    private const TEST_IP = '192.0.2.1';

    /** Dominio de ejemplo RFC 2606 — nunca es un correo real de cliente */
    private const TEST_EMAIL = 'test.ratelimit@example.invalid';

    /** Segundo correo para tests de aislamiento entre correos */
    private const TEST_EMAIL_B = 'other.ratelimit@example.invalid';

    protected function setUp(): void
    {
        // Garantizar estado limpio ANTES de cada test
        $this->limpiarEstado(self::TEST_EMAIL,   self::TEST_IP);
        $this->limpiarEstado(self::TEST_EMAIL_B, self::TEST_IP);
    }

    protected function tearDown(): void
    {
        // Garantizar que no queda basura DESPUÉS de cada test
        $this->limpiarEstado(self::TEST_EMAIL,   self::TEST_IP);
        $this->limpiarEstado(self::TEST_EMAIL_B, self::TEST_IP);
    }

    // ── Sin intentos → no bloqueado ───────────────────────────────────────────

    public function testNoBloqueSinIntentos(): void
    {
        $this->assertFalse(
            RateLimiter::tooManyAttempts(self::TEST_EMAIL, self::TEST_IP),
            'Con 0 intentos no debe estar bloqueado'
        );
    }

    // ── Límite de intentos ────────────────────────────────────────────────────

    public function testNoBloqueCon4Intentos(): void
    {
        for ($i = 0; $i < 4; $i++) {
            RateLimiter::hit(self::TEST_EMAIL, self::TEST_IP);
        }

        $this->assertFalse(
            RateLimiter::tooManyAttempts(self::TEST_EMAIL, self::TEST_IP),
            '4 intentos no deben bloquear (el límite son 5)'
        );
    }

    public function testBloqueaDespuesDe5Intentos(): void
    {
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit(self::TEST_EMAIL, self::TEST_IP);
        }

        $this->assertTrue(
            RateLimiter::tooManyAttempts(self::TEST_EMAIL, self::TEST_IP),
            '5 intentos deben activar el bloqueo'
        );
    }

    public function testSuperarLimiteSigueBloqueado(): void
    {
        for ($i = 0; $i < 8; $i++) {
            RateLimiter::hit(self::TEST_EMAIL, self::TEST_IP);
        }

        $this->assertTrue(
            RateLimiter::tooManyAttempts(self::TEST_EMAIL, self::TEST_IP),
            'Más de 5 intentos también deben estar bloqueados'
        );
    }

    // ── clear: desbloqueo tras login exitoso ──────────────────────────────────

    public function testClearDesbloquea(): void
    {
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit(self::TEST_EMAIL, self::TEST_IP);
        }
        $this->assertTrue(RateLimiter::tooManyAttempts(self::TEST_EMAIL, self::TEST_IP));

        RateLimiter::clear(self::TEST_EMAIL, self::TEST_IP);

        $this->assertFalse(
            RateLimiter::tooManyAttempts(self::TEST_EMAIL, self::TEST_IP),
            'Después de clear() el correo debe poder intentar login de nuevo'
        );
    }

    public function testClearPermiteNuevosIntentos(): void
    {
        // Bloquear → limpiar → volver a intentar sin bloquearse
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit(self::TEST_EMAIL, self::TEST_IP);
        }
        RateLimiter::clear(self::TEST_EMAIL, self::TEST_IP);

        // 3 intentos más no deben bloquear (el contador se reinició)
        for ($i = 0; $i < 3; $i++) {
            RateLimiter::hit(self::TEST_EMAIL, self::TEST_IP);
        }

        $this->assertFalse(
            RateLimiter::tooManyAttempts(self::TEST_EMAIL, self::TEST_IP),
            'Después de clear(), 3 nuevos intentos no deben bloquear'
        );
    }

    // ── Aislamiento entre correos diferentes ──────────────────────────────────

    public function testBloquearCorreoANoAfectaCorreoB(): void
    {
        // Bloquear TEST_EMAIL
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit(self::TEST_EMAIL, self::TEST_IP);
        }

        // TEST_EMAIL_B con la misma IP NO debe estar bloqueado
        $this->assertFalse(
            RateLimiter::tooManyAttempts(self::TEST_EMAIL_B, self::TEST_IP),
            'Bloquear un correo no debe afectar a otro correo distinto con la misma IP'
        );
    }

    public function testContadoresDeCorreosSonIndependientes(): void
    {
        // 3 intentos en correo A, 3 en correo B — ninguno debe bloquearse
        for ($i = 0; $i < 3; $i++) {
            RateLimiter::hit(self::TEST_EMAIL,   self::TEST_IP);
            RateLimiter::hit(self::TEST_EMAIL_B, self::TEST_IP);
        }

        $this->assertFalse(RateLimiter::tooManyAttempts(self::TEST_EMAIL,   self::TEST_IP));
        $this->assertFalse(RateLimiter::tooManyAttempts(self::TEST_EMAIL_B, self::TEST_IP));
    }

    // ── Helper: limpieza de estado del RateLimiter ────────────────────────────

    /**
     * Llama a RateLimiter::erase() via Reflection para borrar el fichero de
     * estado de una combinación correo+IP.
     *
     * Reflection se usa SOLO para acceder al método privado erase() —
     * NO para modificar constantes (que es lo que NO funciona).
     */
    private function limpiarEstado(string $correo, string $ip): void
    {
        $ref = new \ReflectionClass(RateLimiter::class);

        $keyMethod = $ref->getMethod('key');
        $keyMethod->setAccessible(true);
        $key = $keyMethod->invoke(null, $correo, $ip);

        $eraseMethod = $ref->getMethod('erase');
        $eraseMethod->setAccessible(true);
        $eraseMethod->invoke(null, $key);
    }
}
