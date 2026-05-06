<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Models\AgentModel;
use Database\Connection;
use PHPUnit\Framework\TestCase;

/**
 * Tests de integración de AgentModel.
 *
 * Estos tests ejecutan queries reales contra SQLite en memoria.
 * Cada test corre dentro de una transacción que se revierte en tearDown,
 * garantizando aislamiento sin necesidad de truncar tablas entre tests.
 *
 * Cubre:
 *   - crear() — con detección de correo duplicado
 *   - findByEmail() — encontrado y no encontrado
 *   - eliminar() / reactivar() — soft delete
 *   - cambiarRol() — rol validado en la BD
 *   - actualizarHash() — rehash transparente
 */
final class AgentModelIntegrationTest extends TestCase
{
    private AgentModel $model;
    private \PDO       $db;

    protected function setUp(): void
    {
        $this->model = new AgentModel();
        $this->db    = Connection::getConnection();
        $this->db->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    // ── Fixtures ──────────────────────────────────────────────────────────────

    private function crearAgenteBase(
        string $correo = 'agente@int.lux',
        string $rol    = 'asesor',
    ): int {
        return $this->model->crear(
            nombre:    'Agente',
            apellido:  'Integración',
            correo:    $correo,
            telefono:  '5500000001',
            password:  'pass123',
            rol:       $rol,
        );
    }

    // ── crear ─────────────────────────────────────────────────────────────────

    public function testCrearDevuelveIdPositivo(): void
    {
        $id = $this->crearAgenteBase();
        $this->assertGreaterThan(0, $id);
    }

    public function testCrearGuardaHashBcrypt(): void
    {
        $id = $this->crearAgenteBase();

        $stmt = $this->db->prepare('SELECT contrasena FROM agentes WHERE id = ?');
        $stmt->execute([$id]);
        $hash = $stmt->fetchColumn();

        $this->assertNotEmpty($hash);
        $this->assertTrue(password_verify('pass123', $hash), 'El hash debe verificar con la contraseña original');
    }

    public function testCrearDevuelveCeroSiCorreoYaExiste(): void
    {
        $correo = 'duplicado@int.lux';
        $this->crearAgenteBase($correo);

        $id2 = $this->model->crear(
            nombre:   'Duplicado',
            apellido: 'Test',
            correo:   $correo,
            telefono: '5500000002',
            password: 'otraPass',
        );

        $this->assertSame(0, $id2, 'Debe devolver 0 si el correo ya está registrado');
    }

    // ── findByEmail ───────────────────────────────────────────────────────────

    public function testFindByEmailEncuentraAgenteExistente(): void
    {
        $id = $this->crearAgenteBase('buscar@int.lux');

        $agente = $this->model->findByEmail('buscar@int.lux');

        $this->assertIsArray($agente);
        $this->assertSame($id, (int) $agente['id']);
        $this->assertArrayHasKey('contrasena', $agente);
    }

    public function testFindByEmailDevuelveNullParaCorreoInexistente(): void
    {
        $result = $this->model->findByEmail('noexiste@jamas.lux');
        $this->assertNull($result);
    }

    // ── eliminar / reactivar ──────────────────────────────────────────────────

    public function testEliminarMarcaComoInactivo(): void
    {
        $id = $this->crearAgenteBase('eliminar@int.lux');

        $this->model->eliminar($id);

        $agente = $this->model->findByEmail('eliminar@int.lux');
        $this->assertSame(0, (int) $agente['activo'], 'El agente debe tener activo=0 tras eliminar()');
    }

    public function testReactivarMarcaComoActivo(): void
    {
        $id = $this->crearAgenteBase('reactivar@int.lux');
        $this->model->eliminar($id);
        $this->model->reactivar($id);

        $agente = $this->model->findByEmail('reactivar@int.lux');
        $this->assertSame(1, (int) $agente['activo'], 'El agente debe tener activo=1 tras reactivar()');
    }

    // ── cambiarRol ────────────────────────────────────────────────────────────

    public function testCambiarRolActualizaElRol(): void
    {
        $id = $this->crearAgenteBase('rol@int.lux', 'asesor');

        $this->model->cambiarRol($id, 'coordinador');

        $stmt = $this->db->prepare('SELECT rol FROM agentes WHERE id = ?');
        $stmt->execute([$id]);
        $rol = $stmt->fetchColumn();

        $this->assertSame('coordinador', $rol);
    }

    // ── actualizarHash ────────────────────────────────────────────────────────

    public function testActualizarHashCambiaLaContrasena(): void
    {
        $id = $this->crearAgenteBase('rehash@int.lux');

        $nuevoHash = password_hash('nuevaContraseña', PASSWORD_BCRYPT);
        $this->model->actualizarHash($id, $nuevoHash);

        $stmt = $this->db->prepare('SELECT contrasena FROM agentes WHERE id = ?');
        $stmt->execute([$id]);
        $hash = $stmt->fetchColumn();

        $this->assertTrue(
            password_verify('nuevaContraseña', $hash),
            'El hash actualizado debe verificar con la nueva contraseña'
        );
    }
}