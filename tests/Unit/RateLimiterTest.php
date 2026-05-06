<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Security\RateLimiter;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitarios de RateLimiter.
 *
 * El rate limiter tiene dos backends: Redis y ficheros.
 * En CI (GitHub Actions) Redis no está disponible, así que los tests
 * ejercen el fallback de ficheros en un directorio temporal.
 *
 * Usamos reflection para:
 *   1. Apuntar CACHE_DIR a un directorio temporal de tests.
 *   2. Acceder a métodos privados (erase/read) para setup y teardown.
 *
 * NOTA: Si en el futuro Redis se añade a CI, los tests seguirán pasando
 * porque el RateLimiter detecta Redis automáticamente y los assertivos
 * son independientes del backend.
 */
final class RateLimiterTest extends TestCase
{
    private const TEST_IP    = '192.0.2.1';           // RFC 5737 — IP de documentación
    private const TEST_EMAIL = 'test@prueba.lux';

    private string $tempDir;

    protected function setUp(): void
    {
        // Crear directorio temporal para los ficheros del rate limiter
        $this->tempDir = sys_get_temp_dir() . '/slux_ratelimit_test_' . uniqid();
        mkdir($this->tempDir, 0750, true);

        // Apuntar la constante CACHE_DIR al directorio temporal
        $this->overrideCacheDir($this->tempDir);

        // Limpiar estado inicial para este correo+IP
        $this->eraseKey(self::TEST_EMAIL, self::TEST_IP);
    }

    protected function tearDown(): void
    {
        // Limpiar ficheros temporales
        $this->eraseKey(self::TEST_EMAIL, self::TEST_IP);
        @rmdir($this->tempDir);
    }

    // ── tooManyAttempts (estado limpio) ───────────────────────────────────────

    public function testNoBloqueSinIntentos(): void
    {
        $this->assertFalse(
            RateLimiter::tooManyAttempts(self::TEST_EMAIL, self::TEST_IP)
        );
    }

    // ── hit y bloqueo progresivo ──────────────────────────────────────────────

    public function testBloqueaDespuesDe5Intentos(): void
    {
        // 5 intentos fallidos = exactamente el límite
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit(self::TEST_EMAIL, self::TEST_IP);
        }

        $this->assertTrue(
            RateLimiter::tooManyAttempts(self::TEST_EMAIL, self::TEST_IP)
        );
    }

    public function testNoBloqueCon4Intentos(): void
    {
        for ($i = 0; $i < 4; $i++) {
            RateLimiter::hit(self::TEST_EMAIL, self::TEST_IP);
        }

        $this->assertFalse(
            RateLimiter::tooManyAttempts(self::TEST_EMAIL, self::TEST_IP)
        );
    }

    // ── clear ─────────────────────────────────────────────────────────────────

    public function testClearReiniciaContador(): void
    {
        // Llenar hasta el límite
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit(self::TEST_EMAIL, self::TEST_IP);
        }
        $this->assertTrue(RateLimiter::tooManyAttempts(self::TEST_EMAIL, self::TEST_IP));

        // Limpiar (login exitoso)
        RateLimiter::clear(self::TEST_EMAIL, self::TEST_IP);

        $this->assertFalse(
            RateLimiter::tooManyAttempts(self::TEST_EMAIL, self::TEST_IP)
        );
    }

    // ── Correos distintos no se bloquean mutuamente ───────────────────────────

    public function testCorreosDiferentesNoInterfirenEntreEllos(): void
    {
        $otroCorreo = 'otro@prueba.lux';

        // Bloquear el correo principal
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit(self::TEST_EMAIL, self::TEST_IP);
        }

        // El OTRO correo con la misma IP no debe estar bloqueado
        $this->assertFalse(
            RateLimiter::tooManyAttempts($otroCorreo, self::TEST_IP)
        );

        // Limpieza
        $this->eraseKey($otroCorreo, self::TEST_IP);
    }

    // ── La misma IP con distinto correo genera clave distinta ─────────────────

    public function testMismaIpDistintoCorreoGeneraClaveDistinta(): void
    {
        // Solo 3 intentos para correo1
        for ($i = 0; $i < 3; $i++) {
            RateLimiter::hit(self::TEST_EMAIL, self::TEST_IP);
        }

        // correo2 con misma IP no debe estar bloqueado
        $correo2 = 'correo2@prueba.lux';
        $this->assertFalse(RateLimiter::tooManyAttempts($correo2, self::TEST_IP));

        $this->eraseKey($correo2, self::TEST_IP);
    }

    // ── Helpers via Reflection ────────────────────────────────────────────────

    /**
     * Construye la clave interna del rate limiter usando reflexión.
     * Replica la lógica de RateLimiter::key() sin exponerla.
     */
    private function buildKey(string $correo, string $ip): string
    {
        $ref    = new \ReflectionClass(RateLimiter::class);
        $method = $ref->getMethod('key');
        $method->setAccessible(true);
        return $method->invoke(null, $correo, $ip);
    }

    /**
     * Borra el fichero de estado del rate limiter para una combinación correo+IP.
     */
    private function eraseKey(string $correo, string $ip): void
    {
        $ref    = new \ReflectionClass(RateLimiter::class);
        $method = $ref->getMethod('erase');
        $method->setAccessible(true);
        $method->invoke(null, $this->buildKey($correo, $ip));
    }

    /**
     * Apunta CACHE_DIR a un directorio temporal para no contaminar storage/ real.
     */
    private function overrideCacheDir(string $dir): void
    {
        $ref  = new \ReflectionClass(RateLimiter::class);

        try {
            $prop = $ref->getProperty('cacheDir');
            $prop->setAccessible(true);
            $prop->setValue(null, $dir);
        } catch (\ReflectionException) {
            // Si la constante no es sobreescribible (es const no prop),
            // los tests aún funcionan con el directorio real storage/ratelimit/
            // siempre que los archivos se limpien en tearDown.
        }
    }
}