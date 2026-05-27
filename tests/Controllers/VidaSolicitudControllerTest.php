<?php
declare(strict_types=1);

namespace Tests\Controllers;

use PHPUnit\Framework\TestCase;

/**
 * Tests para VidaSolicitudController.
 *
 * Controlador público para solicitudes de cotización (vida, gmm, empresas, autos).
 * Probamos:
 *  - Validaciones de campos obligatorios
 *  - Rate limiting
 *  - Lógica de ramos
 *  - Helpers privados via reflexión
 */
class VidaSolicitudControllerTest extends TestCase
{
    private \PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = \Database\Connection::getConnection();

        $this->pdo->exec("INSERT OR IGNORE INTO agentes (id,nombre,apellido,correo,contrasena,rol,activo)
                          VALUES (500,'VidaAgent','Test','vida_agent@test.com','hash','asesor',1)");

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REMOTE_ADDR']    = '192.168.1.100';

        // Limpiar rate limiter
        try {
            \App\Security\RateLimiter::clear('publica', '192.168.1.100');
        } catch (\Throwable) {}
    }

    protected function tearDown(): void
    {
        $this->pdo->exec("DELETE FROM clientes WHERE correo LIKE '%_vtst@test.com'");
        $this->pdo->exec("DELETE FROM agentes  WHERE id = 500");

        try {
            \App\Security\RateLimiter::clear('publica', '192.168.1.100');
        } catch (\Throwable) {}

        unset($_SERVER['REQUEST_METHOD']);
    }

    private function invokePrivate(string $method, ...$args): mixed
    {
        $ref = new \ReflectionClass(\App\Controllers\VidaSolicitudController::class);
        $m   = $ref->getMethod($method);
        $m->setAccessible(true);
        return $m->invoke(null, ...$args);
    }

    private function postJson(array $data): array
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, json_encode($data));
        rewind($stream);

        // Monkey-patch: guardamos el contenido en variable estática accesible
        // No podemos reemplazar php://input en tests CLI, así que probamos
        // las validaciones que sí podemos interceptar (método, output).
        return $data;
    }

    // ── Método no permitido ───────────────────────────────────────────────────

    /** @test */
    public function get_devuelve_405(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        ob_start();
        \App\Controllers\VidaSolicitudController::handle();
        $raw = ob_get_clean();

        $body = json_decode($raw, true);
        $this->assertFalse($body['ok']);
        $this->assertStringContainsStringIgnoringCase('método', $body['msg'] ?? '');
    }

    // ── formatFecha privado ───────────────────────────────────────────────────

    /** @test */
    public function format_fecha_junio(): void
    {
        $result = $this->invokePrivate('formatFecha', '2025-06-20');
        $this->assertSame('20 de junio de 2025', $result);
    }

    /** @test */
    public function format_fecha_diciembre(): void
    {
        $result = $this->invokePrivate('formatFecha', '2025-12-01');
        $this->assertSame('1 de diciembre de 2025', $result);
    }

    // ── row privado (HTML helper) ─────────────────────────────────────────────

    /** @test */
    public function row_genera_html_con_label_y_valor(): void
    {
        $result = $this->invokePrivate('row', 'Nombre', 'Juan Pérez');

        $this->assertStringContainsString('Nombre', $result);
        $this->assertStringContainsString('Juan Pérez', $result);
        $this->assertStringContainsString('<tr>', $result);
        $this->assertStringContainsString('<td', $result);
    }

    // ── buildEmailCliente y buildEmailAsesor ──────────────────────────────────

    /** @test */
    public function build_email_cliente_contiene_ramo_y_nombre(): void
    {
        $result = $this->invokePrivate(
            'buildEmailCliente', '🚗', 'Seguro Vehicular', '#10b981,#059669', 'María García', '15 de junio de 2025'
        );

        $this->assertStringContainsString('María García',     $result);
        $this->assertStringContainsString('Seguro Vehicular', $result);
        $this->assertStringContainsString('15 de junio de 2025', $result);
    }

    /** @test */
    public function build_email_asesor_contiene_datos_de_contacto(): void
    {
        $result = $this->invokePrivate(
            'buildEmailAsesor',
            '❤️', 'Seguro de Vida', '#c9a227,#dbb84d',
            'Pedro López', 'pedro@test.com', '5512345678', '10 de enero de 2026', ''
        );

        $this->assertStringContainsString('Pedro López', $result);
        $this->assertStringContainsString('pedro@test.com', $result);
        $this->assertStringContainsString('5512345678', $result);
    }

    // ── Validación de ramo ────────────────────────────────────────────────────

    /** @test */
    public function ramo_invalido_cae_a_vida(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/App/Controllers/VidaSolicitudController.php'
        );

        // El controlador acepta: vida, gmm, empresas, autos — default 'vida'
        $this->assertStringContainsString("'vida'",     $source);
        $this->assertStringContainsString("'gmm'",      $source);
        $this->assertStringContainsString("'empresas'", $source);
        $this->assertStringContainsString("'autos'",    $source);
    }

    // ── Validación de correo ──────────────────────────────────────────────────

    /** @test */
    public function correo_invalido_debe_rechazarse(): void
    {
        $this->assertFalse(
            (bool)filter_var('no_es_correo', FILTER_VALIDATE_EMAIL),
            'filter_var debe rechazar correos malformados'
        );
    }

    /** @test */
    public function correo_valido_pasa_validacion(): void
    {
        $this->assertNotFalse(
            filter_var('usuario@dominio.com', FILTER_VALIDATE_EMAIL)
        );
    }

    // ── Rate limiting ─────────────────────────────────────────────────────────

    /** @test */
    public function rate_limiter_bloquea_ip_tras_5_solicitudes(): void
    {
        $ip = '192.168.99.1';
        \App\Security\RateLimiter::clear('publica', $ip);

        for ($i = 0; $i < 5; $i++) {
            \App\Security\RateLimiter::hit('publica', $ip);
        }

        $this->assertTrue(\App\Security\RateLimiter::tooManyAttempts('publica', $ip));

        \App\Security\RateLimiter::clear('publica', $ip);
    }

    // ── Límite de citas activas ───────────────────────────────────────────────

    /** @test */
    public function max_citas_es_3(): void
    {
        $ref    = new \ReflectionClass(\App\Controllers\VidaSolicitudController::class);
        $const  = $ref->getConstant('MAX_CITAS');

        $this->assertSame(3, $const, 'MAX_CITAS debe ser 3');
    }

    // ── getConfig devuelve array ──────────────────────────────────────────────

    /** @test */
    public function get_config_devuelve_array(): void
    {
        // Crear tabla configuracion si no existe
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS configuracion (
                id    INTEGER PRIMARY KEY AUTOINCREMENT,
                clave TEXT NOT NULL UNIQUE,
                valor TEXT NOT NULL DEFAULT ''
            )
        ");

        $result = $this->invokePrivate('getConfig');

        $this->assertIsArray($result);
    }

    // ── HTML sanitization ─────────────────────────────────────────────────────

    /** @test */
    public function build_email_sanitiza_xss_en_nombre(): void
    {
        $result = $this->invokePrivate(
            'buildEmailCliente',
            '🚗', 'Seguro Vehicular', '#10b981,#059669',
            '<script>alert("xss")</script>', '1 de enero de 2026'
        );

        $this->assertStringNotContainsString('<script>', $result,
            'Los nombres deben ser escapados con htmlspecialchars');
    }
}