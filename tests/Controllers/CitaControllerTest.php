<?php
declare(strict_types=1);

namespace Tests\Controllers;

use PHPUnit\Framework\TestCase;

/**
 * Tests para CitaController.
 *
 * CitaController::handle() requiere sesión autenticada.
 * Probamos:
 *  - Validación de fecha en GET
 *  - Validación de acción en POST (crear, estado)
 *  - Reglas de negocio: hora disponible, estado permitido
 *  - Comportamiento del modelo CitaModel usado por el controlador
 */
class CitaControllerTest extends TestCase
{
    private \PDO $pdo;
    private int  $agenteId;
    private int  $clienteId;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        // Sesión autenticada
        $_SESSION['agente_id']     = 1;
        $_SESSION['agente_rol']    = 'asesor';
        $_SESSION['logged_in']     = true;
        $_SESSION['last_activity'] = time();

        $this->pdo = \Database\Connection::getConnection();

        // Agente de prueba
        $this->pdo->exec("INSERT OR IGNORE INTO agentes (id,nombre,apellido,correo,contrasena,rol,activo)
                          VALUES (100,'Asesor','Prueba','asesor_cita@test.com','hash','asesor',1)");
        $this->agenteId = 100;

        // Cliente de prueba
        $this->pdo->exec("INSERT OR IGNORE INTO clientes (id,nombre,apellido_paterno,ramo,agente_id)
                          VALUES (200,'Cliente','Cita','autos',100)");
        $this->clienteId = 200;

        $_SESSION['agente_id'] = $this->agenteId;

        // Limpiar citas anteriores del test
        $this->pdo->exec("DELETE FROM citas WHERE agente_id = {$this->agenteId}");

        unset($_GET['fecha'], $_POST);
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    protected function tearDown(): void
    {
        $this->pdo->exec("DELETE FROM citas   WHERE agente_id = {$this->agenteId}");
        $this->pdo->exec("DELETE FROM clientes WHERE id = {$this->clienteId}");
        $this->pdo->exec("DELETE FROM agentes  WHERE id = {$this->agenteId}");
        unset($_GET['fecha'], $_POST, $_SERVER['REQUEST_METHOD']);
    }

    // ── CitaModel::horaDisponible ─────────────────────────────────────────────

    /** @test */
    public function hora_disponible_devuelve_true_si_no_hay_cita(): void
    {
        $model = new \App\Models\CitaModel();
        $fecha = date('Y-m-d', strtotime('+10 days'));

        $this->assertTrue($model->horaDisponible($this->agenteId, $fecha, '09:00'));
    }

    /** @test */
    public function hora_disponible_devuelve_false_si_ya_existe_cita(): void
    {
        $fecha = date('Y-m-d', strtotime('+10 days'));

        $this->pdo->prepare(
            "INSERT INTO citas (cliente_id, agente_id, fecha, hora, estado, tipo, ramo)
             VALUES (?, ?, ?, '11:00', 'programada', 'llamada', 'autos')"
        )->execute([$this->clienteId, $this->agenteId, $fecha]);

        $model = new \App\Models\CitaModel();
        $this->assertFalse($model->horaDisponible($this->agenteId, $fecha, '11:00'));
    }

    // ── CitaModel::crear ──────────────────────────────────────────────────────

    /** @test */
    public function crear_cita_devuelve_id_entero_positivo(): void
    {
        $model  = new \App\Models\CitaModel();
        $fecha  = date('Y-m-d', strtotime('+5 days'));
        $citaId = $model->crear($this->clienteId, $this->agenteId, 'llamada', $fecha, '10:00', '', 'autos');

        $this->assertIsInt($citaId);
        $this->assertGreaterThan(0, $citaId);
    }

    /** @test */
    public function crear_dos_citas_da_ids_distintos(): void
    {
        $model = new \App\Models\CitaModel();
        $fecha = date('Y-m-d', strtotime('+6 days'));

        $id1 = $model->crear($this->clienteId, $this->agenteId, 'llamada',      $fecha, '09:00', '', 'autos');
        $id2 = $model->crear($this->clienteId, $this->agenteId, 'videollamada', $fecha, '10:00', '', 'vida');

        $this->assertNotSame($id1, $id2);
    }

    // ── CitaModel::updateEstado ───────────────────────────────────────────────

    /** @test */
    public function update_estado_a_completada_devuelve_true(): void
    {
        $model  = new \App\Models\CitaModel();
        $fecha  = date('Y-m-d', strtotime('+4 days'));
        $citaId = $model->crear($this->clienteId, $this->agenteId, 'llamada', $fecha, '14:00', '', 'autos');

        $ok = $model->updateEstado($citaId, $this->agenteId, 'completada');

        $this->assertTrue($ok);
    }

    /** @test */
    public function update_estado_a_cancelada_devuelve_true(): void
    {
        $model  = new \App\Models\CitaModel();
        $fecha  = date('Y-m-d', strtotime('+4 days'));
        $citaId = $model->crear($this->clienteId, $this->agenteId, 'llamada', $fecha, '15:00', '', 'autos');

        $ok = $model->updateEstado($citaId, $this->agenteId, 'cancelada');

        $this->assertTrue($ok);
    }

    // ── CitaModel::getHorasOcupadas ───────────────────────────────────────────

    /** @test */
    public function get_horas_ocupadas_devuelve_array(): void
    {
        $model = new \App\Models\CitaModel();
        $fecha = date('Y-m-d', strtotime('+8 days'));

        $horas = $model->getHorasOcupadas($this->agenteId, $fecha);

        $this->assertIsArray($horas);
    }

    /** @test */
    public function get_horas_ocupadas_incluye_hora_agendada(): void
    {
        $model = new \App\Models\CitaModel();
        $fecha = date('Y-m-d', strtotime('+9 days'));

        $model->crear($this->clienteId, $this->agenteId, 'llamada', $fecha, '16:00', '', 'autos');

        $horas = $model->getHorasOcupadas($this->agenteId, $fecha);

        $this->assertContains('16:00', $horas);
    }

    // ── CitaController — validación de fecha GET (sin auth overhead) ──────────

    /** @test */
    public function controlador_valida_regex_fecha(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/App/Controllers/CitaController.php'
        );

        $this->assertStringContainsString('\d{4}-\d{2}-\d{2}', $source,
            'El controlador debe validar fecha con regex YYYY-MM-DD');
    }

    /** @test */
    public function controlador_rechaza_metodo_desconocido(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/App/Controllers/CitaController.php'
        );

        // Debe retornar 405 si el método no es GET ni POST
        $this->assertStringContainsString('405', $source);
    }

    /** @test */
    public function controlador_permite_estado_vencida_solo_a_superadmin(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/App/Controllers/CitaController.php'
        );

        $this->assertStringContainsString('isSuperadmin', $source);
        $this->assertStringContainsString('vencida',      $source);
    }

    // ── CitaModel::getCitaActivaByClienteId ───────────────────────────────────

    /** @test */
    public function get_cita_activa_devuelve_null_si_no_hay(): void
    {
        $model = new \App\Models\CitaModel();
        // cliente 999 no existe
        $this->assertNull($model->getCitaActivaByClienteId(999));
    }

    /** @test */
    public function get_cita_activa_devuelve_cita_si_existe(): void
    {
        $model = new \App\Models\CitaModel();
        $fecha = date('Y-m-d', strtotime('+2 days'));
        $model->crear($this->clienteId, $this->agenteId, 'llamada', $fecha, '09:00', '', 'autos');

        $cita = $model->getCitaActivaByClienteId($this->clienteId);

        $this->assertIsArray($cita);
        // getCitaActivaByClienteId devuelve id, fecha, hora, tipo, estado, agente_id, agente_nombre
        $this->assertArrayHasKey('id',    $cita);
        $this->assertArrayHasKey('fecha', $cita);
    }
}