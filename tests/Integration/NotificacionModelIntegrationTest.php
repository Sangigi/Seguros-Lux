<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Models\NotificacionModel;
use Database\Connection;
use PHPUnit\Framework\TestCase;

/**
 * Tests de integración de NotificacionModel.
 *
 * Cubre:
 *   - crear
 *   - getNoLeidas / getRecientes
 *   - contarNoLeidas
 *   - marcarLeida / marcarTodasLeidas
 *   - eliminar / eliminarTodas
 */
final class NotificacionModelIntegrationTest extends TestCase
{
    private NotificacionModel $model;
    private \PDO              $db;

    protected function setUp(): void
    {
        $this->model = new NotificacionModel();
        $this->db    = Connection::getConnection();
        $this->db->beginTransaction();

        $this->db->exec(
            "INSERT INTO agentes (id, nombre, apellido, correo, contrasena, rol)
             VALUES (50, 'Agt', 'Notif', 'notif50@lux.mx', 'hash', 'asesor')"
        );
    }

    protected function tearDown(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    // ── Fixtures ──────────────────────────────────────────────────────────────

    private function crearNotif(string $titulo = 'Aviso', int $agenteId = 50): int
    {
        return $this->model->crear($agenteId, 'info', $titulo, 'Mensaje de prueba');
    }

    // ── crear ────────────────────────────────────────────────────────────────

    public function testCrearDevuelveIdPositivo(): void
    {
        $id = $this->crearNotif();
        $this->assertGreaterThan(0, $id);
    }

    // ── contarNoLeidas ────────────────────────────────────────────────────────

    public function testContarNoLeidasEsCero(): void
    {
        $this->assertSame(0, $this->model->contarNoLeidas(50));
    }

    public function testContarNoLeidasAumentaAlCrear(): void
    {
        $this->crearNotif();
        $this->crearNotif('Otro aviso');

        $this->assertSame(2, $this->model->contarNoLeidas(50));
    }

    // ── getNoLeidas ───────────────────────────────────────────────────────────

    public function testGetNoLeidasDevuelveNotificacionesNoLeidas(): void
    {
        $this->crearNotif('Primera');
        $this->crearNotif('Segunda');

        $noLeidas = $this->model->getNoLeidas(50);
        $this->assertCount(2, $noLeidas);
    }

    // ── marcarLeida ───────────────────────────────────────────────────────────

    public function testMarcarLeidaReduceContador(): void
    {
        $id = $this->crearNotif();
        $this->model->marcarLeida($id, 50);

        $this->assertSame(0, $this->model->contarNoLeidas(50));
    }

    public function testMarcarLeidaNoAfectaOtroAgente(): void
    {
        $id = $this->crearNotif(agenteId: 50);
        $this->model->marcarLeida($id, 99); // agente incorrecto

        // La notificación sigue sin leer para el agente 50
        $this->assertSame(1, $this->model->contarNoLeidas(50));
    }

    // ── marcarTodasLeidas ─────────────────────────────────────────────────────

    public function testMarcarTodasLeidasDejaCeroNoLeidas(): void
    {
        $this->crearNotif('A');
        $this->crearNotif('B');
        $this->crearNotif('C');

        $this->model->marcarTodasLeidas(50);

        $this->assertSame(0, $this->model->contarNoLeidas(50));
    }

    // ── eliminar ──────────────────────────────────────────────────────────────

    public function testEliminarBorraUnaNotificacion(): void
    {
        $id = $this->crearNotif();
        $this->model->eliminar($id, 50);

        $this->assertSame(0, $this->model->contarNoLeidas(50));
        $this->assertCount(0, $this->model->getRecientes(50));
    }

    // ── eliminarTodas ─────────────────────────────────────────────────────────

    public function testEliminarTodasBorraTodas(): void
    {
        $this->crearNotif('X');
        $this->crearNotif('Y');

        $this->model->eliminarTodas(50);

        $this->assertCount(0, $this->model->getRecientes(50));
    }

    // ── getRecientes ──────────────────────────────────────────────────────────

    public function testGetRecientesDevuelveTodas(): void
    {
        $this->crearNotif('Reciente 1');
        $this->crearNotif('Reciente 2');

        $recientes = $this->model->getRecientes(50);
        $this->assertCount(2, $recientes);
    }
}
