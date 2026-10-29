<?php
declare(strict_types=1);

namespace Tests\Security;

use App\Models\ClientModel;
use App\Models\EmpresaModel;
use App\Security\Tenant;
use PHPUnit\Framework\TestCase;

/**
 * El aislamiento entre empresas, probado atacándolo.
 *
 * El auditor estático (`bin/auditar-tenant.php`) dice que ninguna consulta se
 * quedó sin filtro. Eso es necesario pero no suficiente: comprueba la forma
 * del código, no su efecto. Esta prueba comprueba el efecto. Monta dos
 * empresas con datos reales y, desde una, intenta leer, contar, modificar y
 * borrar los de la otra.
 *
 * Si alguna de estas pruebas falla, hay una fuga de datos entre clientes del
 * CRM. No es un fallo de estilo ni una regresión menor.
 */
class AislamientoEmpresaTest extends TestCase
{
    private \PDO $pdo;

    /** Empresa que ya crea la migración 005. */
    private const EMPRESA_A = 1;

    private int $empresaB;
    private int $clienteDeA;
    private int $clienteDeB;

    protected function setUp(): void
    {
        $this->pdo = \Database\Connection::getConnection();

        // ── Segunda empresa ──────────────────────────────────────────────────
        $this->pdo->exec(
            "INSERT INTO empresas (nombre, slug, plan, max_usuarios, activo)
             VALUES ('Rival Seguros', 'rival-seguros-" . uniqid() . "', 'basico', 10, 1)"
        );
        $this->empresaB = (int) $this->pdo->lastInsertId();

        // ── Un cliente en cada empresa ───────────────────────────────────────
        // Se insertan con SQL directo y empresa explícita para no depender de
        // la misma capa que se está poniendo a prueba.
        $this->clienteDeA = $this->insertarCliente('Ana',  'DeEmpresaA', self::EMPRESA_A);
        $this->clienteDeB = $this->insertarCliente('Beto', 'DeEmpresaB', $this->empresaB);

        Tenant::reset();
        Tenant::set(self::EMPRESA_A);
    }

    protected function tearDown(): void
    {
        $this->pdo->exec('DELETE FROM clientes WHERE apellido_paterno IN (\'DeEmpresaA\',\'DeEmpresaB\')');
        $this->pdo->prepare('DELETE FROM empresas WHERE id = ?')->execute([$this->empresaB]);

        Tenant::reset();
        Tenant::set(self::EMPRESA_A);
    }

    private function insertarCliente(string $nombre, string $apellido, int $empresaId): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO clientes (nombre, apellido_paterno, ramo, agente_id, empresa_id)
             VALUES (?, ?, \'autos\', NULL, ?)'
        );
        $stmt->execute([$nombre, $apellido, $empresaId]);

        return (int) $this->pdo->lastInsertId();
    }

    // ── Lectura ───────────────────────────────────────────────────────────────

    public function testNoSePuedeLeerUnClienteDeOtraEmpresaPorId(): void
    {
        $encontrado = (new ClientModel())->find($this->clienteDeB);

        $this->assertNull(
            $encontrado,
            'Desde la empresa A se leyó un cliente de la empresa B conociendo su id.'
        );
    }

    public function testElClientePropioSiSeLee(): void
    {
        $encontrado = (new ClientModel())->find($this->clienteDeA);

        $this->assertIsArray($encontrado, 'La empresa A no puede leer ni sus propios clientes.');
        $this->assertSame('Ana', $encontrado['nombre']);
    }

    public function testElListadoNoIncluyeClientesDeOtraEmpresa(): void
    {
        $apellidos = array_column((new ClientModel())->all(), 'apellido_paterno');

        $this->assertContains('DeEmpresaA', $apellidos);
        $this->assertNotContains(
            'DeEmpresaB',
            $apellidos,
            'El listado de la empresa A incluye clientes de la empresa B.'
        );
    }

    public function testElPaginadoNoCuentaClientesDeOtraEmpresa(): void
    {
        $pagina = (new ClientModel())->paginate(1, 100);

        foreach ($pagina['data'] as $fila) {
            $this->assertNotSame(
                'DeEmpresaB',
                $fila['apellido_paterno'] ?? null,
                'El paginado de la empresa A devuelve filas de la empresa B.'
            );
        }
    }

    // ── Escritura ─────────────────────────────────────────────────────────────

    public function testNoSePuedeModificarUnClienteDeOtraEmpresa(): void
    {
        (new ClientModel())->update($this->clienteDeB, ['nombre' => 'Secuestrado']);

        $stmt = $this->pdo->prepare('SELECT nombre FROM clientes WHERE id = ?');
        $stmt->execute([$this->clienteDeB]);

        $this->assertSame(
            'Beto',
            $stmt->fetchColumn(),
            'La empresa A modificó un cliente de la empresa B.'
        );
    }

    public function testNoSePuedeBorrarUnClienteDeOtraEmpresa(): void
    {
        (new ClientModel())->delete($this->clienteDeB);

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM clientes WHERE id = ?');
        $stmt->execute([$this->clienteDeB]);

        $this->assertSame(
            1,
            (int) $stmt->fetchColumn(),
            'La empresa A borró un cliente de la empresa B.'
        );
    }

    /**
     * El caso que más fácil se cuela: mandar `empresa_id` en el POST.
     * El dato de entrada nunca puede decidir en qué empresa se escribe.
     */
    public function testAlInsertarSeIgnoraLaEmpresaQueVengaEnLosDatos(): void
    {
        $id = (new ClientModel())->insert([
            'nombre'           => 'Colado',
            'apellido_paterno' => 'DeEmpresaA',
            'ramo'             => 'autos',
            'empresa_id'       => $this->empresaB, // ← intento de escribir en la empresa de al lado
        ]);

        $stmt = $this->pdo->prepare('SELECT empresa_id FROM clientes WHERE id = ?');
        $stmt->execute([$id]);

        $this->assertSame(
            self::EMPRESA_A,
            (int) $stmt->fetchColumn(),
            'Un empresa_id en los datos de entrada escribió en otra empresa.'
        );
    }

    // ── Contexto ──────────────────────────────────────────────────────────────

    /**
     * Fail-closed: sin empresa en contexto no se devuelve todo, se lanza.
     * Devolver todo ante la duda es exactamente como se filtran los datos.
     */
    public function testSinEmpresaEnContextoLaConsultaFalla(): void
    {
        Tenant::reset();

        $this->expectException(\RuntimeException::class);

        (new ClientModel())->all();
    }

    public function testCadaEmpresaVeLoSuyoAlCambiarDeContexto(): void
    {
        $modelo = new ClientModel();

        $desdeA = Tenant::comoEmpresa(self::EMPRESA_A, fn() => $modelo->find($this->clienteDeA));
        $desdeB = Tenant::comoEmpresa($this->empresaB,  fn() => $modelo->find($this->clienteDeB));

        $this->assertSame('Ana',  $desdeA['nombre'] ?? null);
        $this->assertSame('Beto', $desdeB['nombre'] ?? null);

        // Y cruzado, nada.
        $cruzado = Tenant::comoEmpresa($this->empresaB, fn() => $modelo->find($this->clienteDeA));
        $this->assertNull($cruzado, 'Desde la empresa B se vio un cliente de la empresa A.');
    }

    /**
     * La caché es de proceso y la comparten los workers. Si la clave no lleva
     * la empresa dentro, la segunda empresa que ejecute la misma consulta
     * recibe los datos de la primera, sin que falle nada.
     */
    public function testLaCacheNoSeComparteEntreEmpresas(): void
    {
        $modelo = new ClientModel();

        $deA = Tenant::comoEmpresa(self::EMPRESA_A, fn() => $modelo->all());
        $deB = Tenant::comoEmpresa($this->empresaB,  fn() => $modelo->all());

        $apellidosB = array_column($deB, 'apellido_paterno');

        $this->assertNotEmpty($deA);
        $this->assertNotContains(
            'DeEmpresaA',
            $apellidosB,
            'La empresa B recibió datos de la empresa A desde la caché.'
        );
    }

    // ── Canales entrantes ─────────────────────────────────────────────────────

    /**
     * Un webhook sin canal registrado no debe resolver a ninguna empresa:
     * procesarlo «por defecto» metería datos ajenos en la empresa equivocada.
     */
    public function testUnCanalDesconocidoNoResuelveAEmpresa(): void
    {
        $this->assertNull(
            (new EmpresaModel())->porCanal('whatsapp', 'numero-que-no-existe'),
            'Un identificador de canal desconocido resolvió a una empresa.'
        );
    }

    public function testElCanalRegistradoResuelveASuEmpresa(): void
    {
        $this->pdo->prepare(
            'INSERT INTO empresa_canales (empresa_id, canal, identificador, activo)
             VALUES (?, ?, ?, 1)'
        )->execute([$this->empresaB, 'whatsapp', 'phone-id-de-B']);

        $this->assertSame(
            $this->empresaB,
            (new EmpresaModel())->porCanal('whatsapp', 'phone-id-de-B')
        );

        $this->pdo->prepare('DELETE FROM empresa_canales WHERE identificador = ?')
                  ->execute(['phone-id-de-B']);
    }
}
