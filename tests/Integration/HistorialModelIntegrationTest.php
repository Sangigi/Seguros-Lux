<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Models\HistorialModel;
use Database\Connection;
use PHPUnit\Framework\TestCase;

/**
 * Tests de integración de HistorialModel.
 *
 * Cubre:
 *   - registrar
 *   - getByClienteId
 */
final class HistorialModelIntegrationTest extends TestCase
{
    private HistorialModel $model;
    private \PDO           $db;

    protected function setUp(): void
    {
        $this->model = new HistorialModel();
        $this->db    = Connection::getConnection();
        $this->db->beginTransaction();

        $this->db->exec(
            "INSERT INTO agentes (id, nombre, apellido, correo, contrasena, rol)
             VALUES (70, 'Hist', 'Agente', 'hist70@lux.mx', 'hash', 'asesor')"
        );
        $this->db->exec(
            "INSERT INTO clientes (id, nombre, apellido_paterno, agente_id)
             VALUES (200, 'Cliente', 'Historial', 70)"
        );
    }

    protected function tearDown(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    // ── registrar ─────────────────────────────────────────────────────────────

    public function testRegistrarDevuelveIdPositivo(): void
    {
        $id = $this->model->registrar(200, 70, 'nota', 'Primera nota', 'Descripción');
        $this->assertGreaterThan(0, $id);
    }

    public function testRegistrarGuardaLosDatosCorrectamente(): void
    {
        $this->model->registrar(200, 70, 'llamada', 'Llamada realizada', 'Se contactó al cliente');

        $historial = $this->model->getByClienteId(200);

        $this->assertCount(1, $historial);
        $this->assertSame('llamada',            $historial[0]['tipo']);
        $this->assertSame('Llamada realizada',  $historial[0]['titulo']);
    }

    // ── getByClienteId ────────────────────────────────────────────────────────

    public function testGetByClienteIdDevuelveArrayVacioSinHistorial(): void
    {
        $historial = $this->model->getByClienteId(200);
        $this->assertSame([], $historial);
    }

    public function testGetByClienteIdDevuelveTodosLosEventos(): void
    {
        $this->model->registrar(200, 70, 'nota',    'Nota 1', '');
        $this->model->registrar(200, 70, 'llamada', 'Nota 2', '');
        $this->model->registrar(200, 70, 'email',   'Nota 3', '');

        $historial = $this->model->getByClienteId(200);
        $this->assertCount(3, $historial);
    }

    public function testGetByClienteIdNoDevuelveEventosDeOtroCliente(): void
    {
        $this->db->exec(
            "INSERT INTO clientes (id, nombre, apellido_paterno, agente_id)
             VALUES (201, 'Otro', 'Cliente', 70)"
        );

        $this->model->registrar(200, 70, 'nota', 'Para cliente 200', '');
        $this->model->registrar(201, 70, 'nota', 'Para cliente 201', '');

        $historial200 = $this->model->getByClienteId(200);
        $historial201 = $this->model->getByClienteId(201);

        $this->assertCount(1, $historial200);
        $this->assertCount(1, $historial201);
        $this->assertSame('Para cliente 200', $historial200[0]['titulo']);
    }
}
