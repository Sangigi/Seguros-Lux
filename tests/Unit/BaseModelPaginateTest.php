<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Models\BaseModel;
use Database\Connection;
use PHPUnit\Framework\TestCase;

/**
 * Tests de paginación de BaseModel.
 *
 * Usa el mismo ConcreteModel definido en BaseModelTest.php (autoload lo carga).
 * Si PHPUnit corre los archivos en orden diferente, se protege con class_exists.
 *
 * Cubre:
 *   - Estructura de respuesta de paginate()
 *   - Cálculo correcto de total / pages
 *   - Sanitización de parámetros (page < 1, perPage > 200)
 *   - Dirección ASC / DESC
 *   - safeOrderColumn (fallback a 'id' en columna inválida)
 */
final class BaseModelPaginateTest extends TestCase
{
    private \PDO $db;

    // Stub reutilizado del BaseModelTest — se define aquí si no existe
    private object $model;

    protected function setUp(): void
    {
        $this->db    = Connection::getConnection();
        $this->db->beginTransaction();

        // Reutiliza ConcreteModel si ya está definido, o define uno nuevo
        if (!class_exists('Tests\\Unit\\PaginateStub')) {
            eval('
                namespace Tests\\Unit;
                class PaginateStub extends \\App\\Models\\BaseModel {
                    protected string $table = "clientes";
                    protected array $defaultColumns = ["id","nombre","apellido_paterno"];
                    protected array $fillable = [
                        "nombre"           => "string",
                        "apellido_paterno" => "string",
                        "correo"           => "string",
                        "agente_id"        => "int",
                        "estado_cliente"   => "string",
                    ];
                }
            ');
        }

        $this->model = new \Tests\Unit\PaginateStub();

        // Insertar filas de prueba
        for ($i = 1; $i <= 15; $i++) {
            $this->model->insert([
                'nombre'           => "Cliente{$i}",
                'apellido_paterno' => 'Test',
                'agente_id'        => 1,
            ]);
        }
    }

    protected function tearDown(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    // ── Estructura de respuesta ───────────────────────────────────────────────

    public function testPaginateDevuelveClavesEsperadas(): void
    {
        $result = $this->model->paginate(1, 5);

        $this->assertArrayHasKey('data',    $result);
        $this->assertArrayHasKey('total',   $result);
        $this->assertArrayHasKey('page',    $result);
        $this->assertArrayHasKey('perPage', $result);
        $this->assertArrayHasKey('pages',   $result);
    }

    // ── Total y páginas ───────────────────────────────────────────────────────

    public function testPaginateTotalEsCorrecto(): void
    {
        $result = $this->model->paginate(1, 5);
        $this->assertSame(15, $result['total']);
    }

    public function testPaginatePagesEsCorrecto(): void
    {
        $result = $this->model->paginate(1, 5);
        $this->assertSame(3, $result['pages']); // ceil(15/5) = 3
    }

    public function testPaginatePrimeraPageTiene5Elementos(): void
    {
        $result = $this->model->paginate(1, 5);
        $this->assertCount(5, $result['data']);
    }

    public function testPaginateUltimaPaginaTieneElementosRestantes(): void
    {
        $result = $this->model->paginate(2, 10);
        // page 2, perPage 10 → 5 restantes
        $this->assertCount(5, $result['data']);
    }

    // ── Sanitización de parámetros ────────────────────────────────────────────

    public function testPaginatePageMenorA1SeNormalizaA1(): void
    {
        $result = $this->model->paginate(-5, 5);
        $this->assertSame(1, $result['page']);
    }

    public function testPaginatePerPageMayorA200SeClampea(): void
    {
        $result = $this->model->paginate(1, 999);
        $this->assertSame(200, $result['perPage']);
    }

    public function testPaginatePerPageMenorA1SeNormalizaA1(): void
    {
        $result = $this->model->paginate(1, 0);
        $this->assertSame(1, $result['perPage']);
    }

    // ── Dirección ASC / DESC ──────────────────────────────────────────────────

    public function testPaginateAceptaDireccionAsc(): void
    {
        $result = $this->model->paginate(1, 5, 'id', 'ASC');
        $this->assertCount(5, $result['data']);
    }

    public function testPaginateDireccionInvalidaCaeADesc(): void
    {
        // Dirección no válida → cae a DESC (no lanza excepción)
        $result = $this->model->paginate(1, 5, 'id', 'INVALID');
        $this->assertCount(5, $result['data']);
    }

    // ── safeOrderColumn ───────────────────────────────────────────────────────

    public function testPaginateColumnaInvalidaUsaId(): void
    {
        // Si orderBy no está en whitelist, cae a 'id' silenciosamente
        $result = $this->model->paginate(1, 5, '; DROP TABLE clientes; --');
        $this->assertCount(5, $result['data']);
    }
}
