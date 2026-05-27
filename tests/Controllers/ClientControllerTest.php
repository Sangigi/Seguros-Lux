<?php
declare(strict_types=1);

namespace Tests\Controllers;

use PHPUnit\Framework\TestCase;
use App\Controllers\ClientController;

/**
 * Tests para ClientController.
 */
class ClientControllerTest extends TestCase
{
    use AuthSessionHelper;

    private \PDO $pdo;
    private int  $agenteId = 400;
    private int  $clienteId = 400;

    protected function setUp(): void
    {
        $this->autenticarSesion($this->agenteId, 'asesor');

        $this->pdo = \Database\Connection::getConnection();
        $this->pdo->exec("INSERT OR IGNORE INTO agentes (id,nombre,apellido,correo,contrasena,rol,activo)
                          VALUES (400,'Client','Ctrl','client_ctrl@test.com','hash','asesor',1)");
        $this->pdo->exec("INSERT OR IGNORE INTO clientes (id,nombre,apellido_paterno,ramo,agente_id)
                          VALUES (400,'ClientTest','CC','autos',400)");

        unset($_GET['id']);
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    protected function tearDown(): void
    {
        $this->pdo->exec("DELETE FROM clientes WHERE correo LIKE '%_cctst@test.com' OR id = 400");
        $this->pdo->exec("DELETE FROM agentes  WHERE id = 400");
        $this->limpiarSesion();
        unset($_GET['id'], $_SERVER['REQUEST_METHOD']);
    }

    private function invokePrivate(string $method, ...$args): mixed
    {
        $ref = new \ReflectionClass(ClientController::class);
        $m   = $ref->getMethod($method);
        $m->setAccessible(true);
        return $m->invoke(null, ...$args);
    }

    // ── formatFecha ──────────────────────────────────────────────────────────

    /** @test */
    public function format_fecha_formatea_correctamente(): void
    {
        $this->assertSame('15 de junio de 2025', $this->invokePrivate('formatFecha', '2025-06-15'));
    }

    /** @test */
    public function format_fecha_enero(): void
    {
        $this->assertSame('1 de enero de 2026', $this->invokePrivate('formatFecha', '2026-01-01'));
    }

    /** @test */
    public function format_fecha_vacia_devuelve_vacio(): void
    {
        $this->assertSame('', $this->invokePrivate('formatFecha', ''));
    }

    // ── parseCanalesNotificacion ──────────────────────────────────────────────

    /** @test */
    public function parse_canales_email_simple(): void
    {
        $this->assertSame(['email'], $this->invokePrivate('parseCanalesNotificacion', 'email'));
    }

    /** @test */
    public function parse_canales_multiples(): void
    {
        $r = $this->invokePrivate('parseCanalesNotificacion', 'email, whatsapp, telegram');
        $this->assertContains('email',    $r);
        $this->assertContains('whatsapp', $r);
        $this->assertContains('telegram', $r);
    }

    /** @test */
    public function parse_canales_vacio_default_email(): void
    {
        $this->assertSame(['email'], $this->invokePrivate('parseCanalesNotificacion', ''));
    }

    /** @test */
    public function parse_canales_deduplica(): void
    {
        $r = $this->invokePrivate('parseCanalesNotificacion', 'email,email,email');
        $this->assertCount(1, $r);
    }

    // ── canalesToLabelString ──────────────────────────────────────────────────

    /** @test */
    public function canales_to_label_email(): void
    {
        $this->assertSame('Correo electrónico', $this->invokePrivate('canalesToLabelString', ['email']));
    }

    /** @test */
    public function canales_to_label_multiples(): void
    {
        $r = $this->invokePrivate('canalesToLabelString', ['email', 'whatsapp']);
        $this->assertStringContainsString('Correo',   $r);
        $this->assertStringContainsString('WhatsApp', $r);
    }

    // ── buildWhatsAppLink ─────────────────────────────────────────────────────

    /** @test */
    public function whatsapp_link_genera_url_correcta(): void
    {
        $r = $this->invokePrivate('buildWhatsAppLink', '5215512345678', 'abc123');
        $this->assertStringStartsWith('https://wa.me/', $r);
        $this->assertStringContainsString('5215512345678', $r);
        $this->assertStringContainsString('CITA%3Aabc123', $r);
    }

    /** @test */
    public function whatsapp_link_numero_vacio_devuelve_null(): void
    {
        $this->assertNull($this->invokePrivate('buildWhatsAppLink', '', 'abc123'));
    }

    // ── GET con sesión ────────────────────────────────────────────────────────

    /** @test */
    public function get_id_invalido_devuelve_error(): void
    {
        $_GET['id'] = 'no_es_int';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        ob_start();
        ClientController::handle();
        $body = json_decode(ob_get_clean(), true);

        $this->assertFalse($body['success']);
    }

    /** @test */
    public function get_id_inexistente_devuelve_error(): void
    {
        $_GET['id'] = '99999';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        ob_start();
        ClientController::handle();
        $body = json_decode(ob_get_clean(), true);

        $this->assertFalse($body['success']);
    }

    /** @test */
    public function get_id_existente_devuelve_cliente(): void
    {
        $_GET['id'] = (string) $this->clienteId;
        $_SERVER['REQUEST_METHOD'] = 'GET';

        ob_start();
        ClientController::handle();
        $body = json_decode(ob_get_clean(), true);

        $this->assertTrue($body['success']);
        $this->assertArrayHasKey('cliente', $body);
    }

    // ── Método no permitido ───────────────────────────────────────────────────

    /** @test */
    public function metodo_put_devuelve_405(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';

        ob_start();
        ClientController::handle();
        $body = json_decode(ob_get_clean(), true);

        $this->assertFalse($body['success']);
        $this->assertStringContainsStringIgnoringCase('método', $body['error'] ?? '');
    }

    // ── ClientModel findByCorreo ──────────────────────────────────────────────

    /** @test */
    public function find_by_correo_existente(): void
    {
        $this->pdo->exec("INSERT OR IGNORE INTO clientes (nombre,apellido_paterno,correo,ramo,agente_id)
                          VALUES ('Juan','Test','juan_cctst@test.com','autos',400)");

        $model = new \App\Models\ClientModel();
        $c = $model->findByCorreo('juan_cctst@test.com');

        $this->assertIsArray($c);
        $this->assertSame('juan_cctst@test.com', $c['correo']);
    }

    /** @test */
    public function find_by_correo_inexistente_null(): void
    {
        $this->assertNull((new \App\Models\ClientModel())->findByCorreo('noexiste_cctst@test.com'));
    }
}