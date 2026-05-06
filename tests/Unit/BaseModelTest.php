<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Models\BaseModel;
use Database\Connection;
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitarios de BaseModel.
 *
 * Cada test corre dentro de una transacción que se revierte al final,
 * garantizando aislamiento completo entre casos sin truncar tablas.
 *
 * STUB: ConcreteModel implementa BaseModel con la tabla `clientes`
 *       y expone los métodos protegidos que necesitamos testear.
 *
 * NOTA sobre transacciones anidadas (SQLite):
 *   SQLite no soporta transacciones realmente anidadas (SAVEPOINT sí, pero
 *   PDO::beginTransaction() lanza excepción si ya hay una activa).
 *   Los tests de BaseModel::transaction() se ejecutan SIN la transacción
 *   envolvente de setUp/tearDown — gestionan su propio aislamiento.
 */

// ── Stub concreto ──────────────────────────────────────────────────────────────
class ConcreteModel extends BaseModel
{
    protected string $table = 'clientes';

    protected array $defaultColumns = ['id', 'nombre', 'apellido_paterno'];

    protected array $fillable = [
        'nombre'           => 'string',
        'apellido_paterno' => 'string',
        'correo'           => 'string',
        'agente_id'        => 'int',
        'estado_cliente'   => 'string',
    ];

    public function exposedValidateColumns(array $cols): array
    {
        return $this->validateColumns($cols);
    }

    public function exposedFilterData(array $data): array
    {
        return $this->filterData($data);
    }
}

// ── Stub con tabla vacía ───────────────────────────────────────────────────────
class EmptyTableModel extends BaseModel
{
    protected string $table = '';
}

// ── Tests ─────────────────────────────────────────────────────────────────────

final class BaseModelTest extends TestCase
{
    private ConcreteModel $model;
    private \PDO          $db;

    protected function setUp(): void
    {
        $this->model = new ConcreteModel();
        $this->db    = Connection::getConnection();
        $this->db->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    // ── Constructor ───────────────────────────────────────────────────────────

    public function testConstructorLanzaExcepcionSiTableEstaVacia(): void
    {
        $this->expectException(LogicException::class);
        new EmptyTableModel();
    }

    // ── validateColumns ───────────────────────────────────────────────────────

    public function testValidateColumnsDevuelveDefaultCuandoArrayVacio(): void
    {
        $result = $this->model->exposedValidateColumns([]);
        $this->assertSame(['id', 'nombre', 'apellido_paterno'], $result);
    }

    public function testValidateColumnsFiltraColumnasNoPermitidas(): void
    {
        $result = $this->model->exposedValidateColumns(['id', 'nombre', 'contrasena']);
        $this->assertContains('id',     $result);
        $this->assertContains('nombre', $result);
        $this->assertNotContains('contrasena', $result);
    }

    public function testValidateColumnsDevuelveDefaultSiTodasSonInvalidas(): void
    {
        $result = $this->model->exposedValidateColumns(['contrasena', '; DROP TABLE clientes;']);
        $this->assertSame(['id', 'nombre', 'apellido_paterno'], $result);
    }

    public function testValidateColumnsAceptaColumnasEnFillable(): void
    {
        $result = $this->model->exposedValidateColumns(['correo', 'agente_id']);
        $this->assertContains('correo',    $result);
        $this->assertContains('agente_id', $result);
    }

    // ── filterData ────────────────────────────────────────────────────────────

    public function testFilterDataConvierteStringAString(): void
    {
        $result = $this->model->exposedFilterData(['nombre' => 123]);
        $this->assertSame('123', $result['nombre']);
    }

    public function testFilterDataConvierteAgentIdAInt(): void
    {
        $result = $this->model->exposedFilterData(['agente_id' => '42']);
        $this->assertSame(42, $result['agente_id']);
    }

    public function testFilterDataIgnoraCamposNoEnFillable(): void
    {
        $result = $this->model->exposedFilterData([
            'nombre'     => 'Juan',
            'contrasena' => 'hackeado123',
        ]);
        $this->assertArrayHasKey('nombre', $result);
        $this->assertArrayNotHasKey('contrasena', $result);
    }

    public function testFilterDataDevuelveArrayVacioSiNingunCampoConcuerda(): void
    {
        $result = $this->model->exposedFilterData(['campo_falso' => 'x']);
        $this->assertSame([], $result);
    }

    // ── insert ────────────────────────────────────────────────────────────────

    public function testInsertDevuelveCeroSiDataEstaVacia(): void
    {
        $id = $this->model->insert(['campo_invalido' => 'valor']);
        $this->assertSame(0, $id);
    }

    public function testInsertRetornaIdEnteroPositivo(): void
    {
        $id = $this->model->insert([
            'nombre'           => 'TestInsert',
            'apellido_paterno' => 'Prueba',
            'correo'           => 'insert@test.com',
            'agente_id'        => 1,
        ]);
        $this->assertGreaterThan(0, $id);
    }

    // ── find ──────────────────────────────────────────────────────────────────

    public function testFindDevuelveArrayConDatosCorrectos(): void
    {
        $id = $this->model->insert([
            'nombre'           => 'FindMe',
            'apellido_paterno' => 'Test',
            'correo'           => 'find@test.com',
            'agente_id'        => 1,
        ]);

        $result = $this->model->find($id);

        $this->assertIsArray($result);
        $this->assertSame($id, (int) $result['id']);
        $this->assertSame('FindMe', $result['nombre']);
    }

    public function testFindDevuelveNullParaIdInexistente(): void
    {
        $result = $this->model->find(999999);
        $this->assertNull($result);
    }

    // ── update ────────────────────────────────────────────────────────────────

    public function testUpdateModificaRegistroCorrectamente(): void
    {
        $id = $this->model->insert([
            'nombre'           => 'Antes',
            'apellido_paterno' => 'Update',
            'agente_id'        => 1,
        ]);

        $ok = $this->model->update($id, ['nombre' => 'Después']);

        $this->assertTrue($ok);
        $updated = $this->model->find($id);
        $this->assertSame('Después', $updated['nombre']);
    }

    public function testUpdateDevuelveFalseConDataVacia(): void
    {
        $id = $this->model->insert([
            'nombre'    => 'Sinupdate',
            'agente_id' => 1,
        ]);

        $ok = $this->model->update($id, ['campo_invalido' => 'x']);
        $this->assertFalse($ok);
    }

    // ── delete ────────────────────────────────────────────────────────────────

    public function testDeleteEliminaRegistroExistente(): void
    {
        $id = $this->model->insert([
            'nombre'    => 'Borrar',
            'agente_id' => 1,
        ]);

        $ok = $this->model->delete($id);

        $this->assertTrue($ok);
        $this->assertNull($this->model->find($id));
    }

    // ── transaction ───────────────────────────────────────────────────────────
    //
    // IMPORTANTE: estos dos tests NO usan la transacción envolvente de setUp/
    // tearDown, porque SQLite lanza PDOException si se intenta abrir una
    // segunda transacción mientras ya hay una activa.
    //
    // La estrategia es:
    //   1. tearDown revierte la transacción de setUp (si sigue activa).
    //   2. Estos tests la revierten manualmente ANTES de llamar a
    //      BaseModel::transaction(), y limpian con DELETE al final para no
    //      dejar basura en la BD compartida en memoria.

    public function testTransactionHaceRollbackEnExcepcion(): void
    {
        // Cerrar la transacción envolvente antes de probar la interna
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }

        $countAntes = count($this->model->all());

        try {
            $this->model->transaction(function () {
                $this->model->insert([
                    'nombre'    => 'TransactionFail',
                    'agente_id' => 1,
                ]);
                throw new \RuntimeException('fallo intencional');
            });
        } catch (\RuntimeException) {
            // esperado
        }

        $countDespues = count($this->model->all());
        $this->assertSame($countAntes, $countDespues, 'El rollback debe deshacer el insert');
    }

    public function testTransactionCommitaEnExito(): void
    {
        // Cerrar la transacción envolvente antes de probar la interna
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }

        $id = $this->model->transaction(fn() => $this->model->insert([
            'nombre'    => 'TransactionOk',
            'agente_id' => 1,
        ]));

        $this->assertGreaterThan(0, $id);
        $this->assertNotNull($this->model->find($id));

        // Limpiar manualmente ya que no hay transacción envolvente
        $this->model->delete($id);
    }
}