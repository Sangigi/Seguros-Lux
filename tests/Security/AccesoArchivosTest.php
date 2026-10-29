<?php
declare(strict_types=1);

namespace Tests\Security;

use App\Controllers\FileController;
use App\Security\Tenant;
use PHPUnit\Framework\TestCase;

/**
 * Quién puede descargar qué expediente.
 *
 * FileController ya impedía salirse de `uploads/`, pero eso solo evita leer
 * el resto del disco: no dice de quién es el archivo. Con una sesión
 * cualquiera se podía pedir cualquier expediente conociendo su ruta, y las
 * rutas son predecibles (`cotizaciones/{id}/...`).
 *
 * Aquí se prueba la comprobación de propiedad. Es un método privado, pero lo
 * que decide es quién lee los documentos de quién, así que merece prueba
 * propia en vez de dejarlo a lo que haga la vista.
 */
class AccesoArchivosTest extends TestCase
{
    private \PDO $pdo;
    private int $empresaB;
    private int $cotizacionDeA;
    private int $cotizacionDeB;
    private int $clienteDeB;

    private const EMPRESA_A = 1;

    protected function setUp(): void
    {
        $this->pdo = \Database\Connection::getConnection();

        $this->pdo->exec(
            "INSERT INTO empresas (nombre, slug, plan, max_usuarios, activo)
             VALUES ('Rival Archivos', 'rival-arch-" . uniqid() . "', 'basico', 10, 1)"
        );
        $this->empresaB = (int) $this->pdo->lastInsertId();

        $this->cotizacionDeA = $this->insertarCotizacion(self::EMPRESA_A);
        $this->cotizacionDeB = $this->insertarCotizacion($this->empresaB);
        $this->clienteDeB    = $this->insertarCliente($this->empresaB);

        Tenant::reset();
        Tenant::set(self::EMPRESA_A);
    }

    protected function tearDown(): void
    {
        $this->pdo->prepare('DELETE FROM cotizaciones WHERE id IN (?,?)')
                  ->execute([$this->cotizacionDeA, $this->cotizacionDeB]);
        $this->pdo->prepare('DELETE FROM clientes WHERE id = ?')->execute([$this->clienteDeB]);
        $this->pdo->prepare('DELETE FROM empresas WHERE id = ?')->execute([$this->empresaB]);

        Tenant::reset();
        Tenant::set(self::EMPRESA_A);
    }

    private function insertarCotizacion(int $empresaId): int
    {
        $this->pdo->prepare(
            'INSERT INTO cotizaciones (cliente_id, agente_id, ramo, estado, precio, empresa_id)
             VALUES (1, 1, \'autos\', \'pendiente\', 0, ?)'
        )->execute([$empresaId]);

        return (int) $this->pdo->lastInsertId();
    }

    private function insertarCliente(int $empresaId): int
    {
        $this->pdo->prepare(
            'INSERT INTO clientes (nombre, apellido_paterno, ramo, empresa_id)
             VALUES (\'Archivo\', \'Ajeno\', \'autos\', ?)'
        )->execute([$empresaId]);

        return (int) $this->pdo->lastInsertId();
    }

    /** Invoca la comprobación privada de propiedad. */
    private function permitido(string $rutaRelativa): bool
    {
        $metodo = new \ReflectionMethod(FileController::class, 'esDeLaEmpresaActiva');
        $metodo->setAccessible(true);

        return (bool) $metodo->invoke(null, $rutaRelativa);
    }

    public function testSePuedeLeerUnArchivoDeLaPropiaEmpresa(): void
    {
        $this->assertTrue(
            $this->permitido('cotizaciones/' . $this->cotizacionDeA . '/poliza.pdf'),
            'La empresa A no puede leer sus propios expedientes.'
        );
    }

    public function testNoSePuedeLeerUnArchivoDeOtraEmpresa(): void
    {
        $this->assertFalse(
            $this->permitido('cotizaciones/' . $this->cotizacionDeB . '/poliza.pdf'),
            'Desde la empresa A se descargó un expediente de la empresa B.'
        );
    }

    public function testNoSePuedeLeerLaPolizaDeUnClienteDeOtraEmpresa(): void
    {
        $this->assertFalse(
            $this->permitido('polizas/' . $this->clienteDeB . '/poliza.pdf'),
            'Desde la empresa A se descargó la póliza de un cliente de la empresa B.'
        );
    }

    /**
     * Fail-closed: una ruta cuya forma no se reconoce se rechaza. Si mañana
     * se guarda un tipo de archivo nuevo, el fallo será «no se ve» y no
     * «lo ve todo el mundo».
     */
    public function testUnaRutaDeFormaDesconocidaSeRechaza(): void
    {
        $this->assertFalse($this->permitido('otracosa/1/archivo.pdf'));
        $this->assertFalse($this->permitido('suelto.pdf'));
        $this->assertFalse($this->permitido('cotizaciones/no-es-un-id/archivo.pdf'));
    }

    public function testSinEmpresaEnContextoNoSeSirveNada(): void
    {
        Tenant::reset();

        $this->assertFalse(
            $this->permitido('cotizaciones/' . $this->cotizacionDeA . '/poliza.pdf'),
            'Sin empresa en contexto se sirvió un archivo.'
        );
    }
}
