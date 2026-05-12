<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Models\ClientModel;
use Database\Connection;
use PHPUnit\Framework\TestCase;

/**
 * Tests de integración de ClientModel.
 *
 * Cubre:
 *   - insert / find / update / delete heredados de BaseModel
 *   - findByCorreo
 *   - perteneceAAgente
 *   - updateEstado / updateEstadoSiProcede
 *   - getByAgente / getByAgenteYRamo
 *   - countTotal
 *   - camposPorRamo (método estático puro)
 *
 * Cada test corre dentro de una transacción que se revierte en tearDown.
 */
final class ClientModelIntegrationTest extends TestCase
{
    private ClientModel $model;
    private \PDO        $db;

    protected function setUp(): void
    {
        $this->model = new ClientModel();
        $this->db    = Connection::getConnection();
        $this->db->beginTransaction();

        // Agente de prueba necesario por la FK agente_id
        $this->db->exec(
            "INSERT INTO agentes (id, nombre, apellido, correo, contrasena, rol)
             VALUES (99, 'Test', 'Agente', 'agente99@lux.mx', 'hash', 'asesor')"
        );
    }

    protected function tearDown(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    // ── Fixtures ──────────────────────────────────────────────────────────────

    private function crearCliente(array $extra = []): int
    {
        return $this->model->insert(array_merge([
            'nombre'           => 'Juan',
            'apellido_paterno' => 'Pérez',
            'correo'           => 'juan@example.com',
            'agente_id'        => 99,
            'ramo'             => 'autos',
            'estado_cliente'   => 'nuevo',
        ], $extra));
    }

    // ── insert / find ─────────────────────────────────────────────────────────

    public function testInsertYFindFuncionan(): void
    {
        $id     = $this->crearCliente();
        $result = $this->model->find($id);

        $this->assertIsArray($result);
        $this->assertSame($id, (int)$result['id']);
        $this->assertSame('Juan', $result['nombre']);
    }

    // ── findByCorreo ──────────────────────────────────────────────────────────

    public function testFindByCorreoDevuelveClienteExistente(): void
    {
        $this->crearCliente(['correo' => 'unico@lux.mx']);
        $result = $this->model->findByCorreo('unico@lux.mx');

        $this->assertIsArray($result);
        $this->assertSame('unico@lux.mx', $result['correo']);
    }

    public function testFindByCorreoDevuelveNullSiNoExiste(): void
    {
        $result = $this->model->findByCorreo('noexiste@lux.mx');
        $this->assertNull($result);
    }

    // ── perteneceAAgente ──────────────────────────────────────────────────────

    public function testPerteneceAAgenteDevuelveTrueSiEsCorrecto(): void
    {
        $id = $this->crearCliente();
        $this->assertTrue($this->model->perteneceAAgente($id, 99));
    }

    public function testPerteneceAAgenteDevuelveFalseSiAgenteEsDistinto(): void
    {
        $id = $this->crearCliente();
        $this->assertFalse($this->model->perteneceAAgente($id, 88));
    }

    // ── updateEstado ──────────────────────────────────────────────────────────

    public function testUpdateEstadoCambiaEstadoCorrectamente(): void
    {
        $id = $this->crearCliente();
        $ok = $this->model->updateEstado($id, 99, 'contactado');

        $this->assertTrue($ok);
        $result = $this->model->find($id);
        $this->assertSame('contactado', $result['estado_cliente']);
    }

    public function testUpdateEstadoDevuelveFalseSiAgenteNoCoincide(): void
    {
        $id = $this->crearCliente();
        $ok = $this->model->updateEstado($id, 88, 'contactado'); // agente 88 ≠ 99

        $this->assertFalse($ok);
    }

    // ── updateEstadoSiProcede ────────────────────────────────────────────────

    public function testUpdateEstadoSiProcedeNoModificaEstadosCerrados(): void
    {
        $id = $this->crearCliente(['estado_cliente' => 'cerrado']);
        $this->model->updateEstadoSiProcede($id, 99, 'contactado');

        $result = $this->model->find($id);
        $this->assertSame('cerrado', $result['estado_cliente']);
    }

    public function testUpdateEstadoSiProcedeModificaEstadoNuevo(): void
    {
        $id = $this->crearCliente(['estado_cliente' => 'nuevo']);
        $this->model->updateEstadoSiProcede($id, 99, 'contactado');

        $result = $this->model->find($id);
        $this->assertSame('contactado', $result['estado_cliente']);
    }

    // ── getByAgente ───────────────────────────────────────────────────────────

    public function testGetByAgenteDevuelveSoloClientesDelAgente(): void
    {
        $this->crearCliente(['nombre' => 'ClienteA']);
        $this->crearCliente(['nombre' => 'ClienteB']);

        $resultados = $this->model->getByAgente(99);

        $this->assertCount(2, $resultados);
    }

    public function testGetByAgenteDevuelveArrayVacioSinClientes(): void
    {
        $resultados = $this->model->getByAgente(88); // agente sin clientes
        $this->assertSame([], $resultados);
    }

    // ── getByAgenteYRamo ──────────────────────────────────────────────────────

    public function testGetByAgenteYRamoFiltraPorRamo(): void
    {
        $this->crearCliente(['ramo' => 'autos', 'correo' => 'a1@x.com']);
        $this->crearCliente(['ramo' => 'vida',  'correo' => 'a2@x.com']);

        $autos = $this->model->getByAgenteYRamo(99, 'autos');
        $vida  = $this->model->getByAgenteYRamo(99, 'vida');

        $this->assertCount(1, $autos);
        $this->assertCount(1, $vida);
        $this->assertSame('autos', $autos[0]['ramo']);
    }

    public function testGetByAgenteYRamoSinRamoDevuelveTodos(): void
    {
        $this->crearCliente(['ramo' => 'autos', 'correo' => 'b1@x.com']);
        $this->crearCliente(['ramo' => 'vida',  'correo' => 'b2@x.com']);

        $todos = $this->model->getByAgenteYRamo(99, null);
        $this->assertCount(2, $todos);
    }

    // ── countTotal ───────────────────────────────────────────────────────────

    public function testCountTotalDevuelveCantidadCorrecta(): void
    {
        $this->crearCliente(['correo' => 'c1@x.com']);
        $this->crearCliente(['correo' => 'c2@x.com']);

        $total = $this->model->countTotal(99);
        $this->assertSame(2, $total);
    }

    public function testCountTotalDevuelveCeroSinClientes(): void
    {
        $this->assertSame(0, $this->model->countTotal(99));
    }

    // ── camposPorRamo ─────────────────────────────────────────────────────────

    public function testCamposPorRamoDevuelveLosRamosEsperados(): void
    {
        $campos = ClientModel::camposPorRamo();

        $this->assertArrayHasKey('autos',          $campos);
        $this->assertArrayHasKey('vida',            $campos);
        $this->assertArrayHasKey('gastos_medicos',  $campos);
        $this->assertArrayHasKey('empresas',        $campos);
    }

    public function testCamposPorRamoAutosContieneClavesMarca(): void
    {
        $autos = ClientModel::camposPorRamo()['autos'];
        $keys  = array_column($autos, 'key');

        $this->assertContains('marca',  $keys);
        $this->assertContains('modelo', $keys);
    }
}
