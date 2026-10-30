<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Models\CatalogoModel;
use App\Models\CotizacionModel;
use App\Security\Tenant;
use App\Services\QueryCache;
use PHPUnit\Framework\TestCase;

/**
 * Lo que las pantallas de cotización y emisión leen del catálogo de la
 * empresa a través de CotizacionModel: aseguradoras y coberturas por ramo,
 * nombres de ramos y documentos, documentos que se exigen para emitir, la
 * validación del ramo que llega por la URL y las opciones de los <select>.
 *
 * Como CatalogosTest, trabaja sobre empresas propias para poder cambiar sus
 * catálogos sin afectar a otras pruebas.
 */
class CotizacionCatalogoTest extends TestCase
{
    private \PDO $pdo;

    private int $empresa;

    /** @var list<int> Empresas creadas por la prueba, para borrarlas al final. */
    private array $creadas = [];

    protected function setUp(): void
    {
        $this->pdo = \Database\Connection::getConnection();

        Tenant::reset();
        QueryCache::flush();

        $this->empresa = $this->crearEmpresa('Cotizaciones con catálogo');
        Tenant::set($this->empresa);
    }

    protected function tearDown(): void
    {
        foreach ($this->creadas as $id) {
            $this->pdo->prepare('DELETE FROM catalogos WHERE empresa_id = ?')->execute([$id]);
            $this->pdo->prepare('DELETE FROM empresas WHERE id = ?')->execute([$id]);
        }

        QueryCache::flush();
        Tenant::reset();
        Tenant::set(1);
    }

    private function crearEmpresa(string $nombre): int
    {
        $this->pdo->prepare(
            "INSERT INTO empresas (nombre, slug, plan, max_usuarios, activo) VALUES (?, ?, 'basico', 10, 1)"
        )->execute([$nombre, 'cotcat-' . uniqid()]);

        $id              = (int) $this->pdo->lastInsertId();
        $this->creadas[] = $id;

        Tenant::comoEmpresa($id, fn() => (new CatalogoModel())->sembrar($id));

        return $id;
    }

    /** Id de un elemento del catálogo de la empresa activa, buscado por su clave. */
    private function idDe(string $tipo, string $ramo, string $clave): int
    {
        foreach ((new CatalogoModel())->lista($tipo, $ramo) as $fila) {
            if ($fila['clave'] === $clave) {
                return $fila['id'];
            }
        }

        $this->fail("No existe {$tipo}/{$ramo}/{$clave} en la empresa activa.");
    }

    // ── Aseguradoras y coberturas por ramo ────────────────────────────────────

    public function testCompaniasYCoberturasPorRamoSiguenAlCatalogo(): void
    {
        $catalogo = new CatalogoModel();
        $catalogo->agregar(CatalogoModel::ASEGURADORA, 'autos', 'Seguros Atlas');
        $catalogo->actualizar($this->idDe(CatalogoModel::ASEGURADORA, 'autos', 'inbursa'), ['activo' => false]);
        $catalogo->actualizar($this->idDe(CatalogoModel::COBERTURA, 'vida', 'dotal'), ['etiqueta' => 'Dotal Plus']);

        $this->assertSame(['GNP', 'Qualitas', 'Chubb', 'Seguros Atlas'], CotizacionModel::companiasPorRamo()['autos']);
        $this->assertSame(['Temporal', 'Entera', 'Dotal Plus', 'Entera con Ahorro'], CotizacionModel::coberturasPorRamo()['vida']);
    }

    public function testUnRamoApagadoConservaSusListas(): void
    {
        (new CatalogoModel())->actualizar($this->idDe(CatalogoModel::RAMO, '', 'empresas'), ['activo' => false]);

        // Una cotización vieja de un ramo apagado sigue necesitando su lista al editarse.
        $this->assertSame(array_keys(CatalogoModel::RAMOS), array_keys(CotizacionModel::companiasPorRamo()));
        $this->assertContains('Zurich', CotizacionModel::companiasPorRamo()['empresas']);
        $this->assertContains('Fianzas', CotizacionModel::coberturasPorRamo()['empresas']);
    }

    public function testLasListasSonLasDeLaEmpresaActiva(): void
    {
        $otra = $this->crearEmpresa('Otra empresa');
        Tenant::comoEmpresa($otra, fn() => (new CatalogoModel())->agregar(CatalogoModel::ASEGURADORA, 'autos', 'Exclusiva de la otra'));

        $this->assertNotContains('Exclusiva de la otra', CotizacionModel::companiasPorRamo()['autos']);
        $this->assertContains(
            'Exclusiva de la otra',
            Tenant::comoEmpresa($otra, fn() => CotizacionModel::companiasPorRamo()['autos'])
        );
    }

    // ── Ramos ─────────────────────────────────────────────────────────────────

    public function testLosNombresDeRamoIncluyenLosApagados(): void
    {
        $catalogo = new CatalogoModel();
        $catalogo->actualizar($this->idDe(CatalogoModel::RAMO, '', 'gastos_medicos'), ['etiqueta' => 'GMM']);
        $catalogo->actualizar($this->idDe(CatalogoModel::RAMO, '', 'vida'), ['activo' => false]);

        $this->assertSame(
            ['autos' => 'Autos', 'vida' => 'Vida', 'gastos_medicos' => 'GMM', 'empresas' => 'Empresas'],
            CotizacionModel::etiquetasRamo()
        );
        $this->assertSame(['label' => 'Vida', 'icono' => 'heart-fill'], CotizacionModel::infoRamos()['vida']);
        $this->assertSame(['label' => 'GMM', 'icono' => 'hospital'], CotizacionModel::infoRamos()['gastos_medicos']);
    }

    public function testElRamoDeLaUrlSoloValeSiEstaEntreLosActivos(): void
    {
        $activos = ['autos' => 'Autos', 'vida' => 'Vida'];

        $this->assertSame('vida', CotizacionModel::ramoDeParametro('vida', $activos));

        foreach (['empresas', '', 'AUTOS', ' autos', null, ['autos'], 1] as $invalido) {
            $this->assertNull(
                CotizacionModel::ramoDeParametro($invalido, $activos),
                'Se aceptó como ramo: ' . var_export($invalido, true)
            );
        }
    }

    public function testUnRamoApagadoYaNoSeAceptaParaCotizacionesNuevas(): void
    {
        $catalogo = new CatalogoModel();
        $this->assertSame('vida', CotizacionModel::ramoDeParametro('vida', $catalogo->ramos()));

        $catalogo->actualizar($this->idDe(CatalogoModel::RAMO, '', 'vida'), ['activo' => false]);

        $this->assertNull(CotizacionModel::ramoDeParametro('vida', $catalogo->ramos()));
    }

    // ── Documentos ────────────────────────────────────────────────────────────

    public function testParaEmitirSeExigenLosRequeridosDelRamoYElComprobante(): void
    {
        $this->assertSame(
            ['doc_cotizacion', 'ine', 'comprobante_dom', 'tarjeta_circulacion', 'foto_coche_1', 'foto_coche_2', 'comprobante_pago'],
            CotizacionModel::documentosEmision('autos')
        );
        $this->assertSame(
            ['doc_cotizacion', 'ine', 'comprobante_dom', 'comprobante_pago'],
            CotizacionModel::documentosEmision('empresas')
        );
        $this->assertSame(['comprobante_pago'], CotizacionModel::documentosEmision(''));
    }

    public function testLosDocumentosParaEmitirSiguenAlCatalogo(): void
    {
        $catalogo = new CatalogoModel();
        $catalogo->actualizar($this->idDe(CatalogoModel::DOCUMENTO, 'vida', 'constancia_fiscal'), ['activo' => true]);
        $catalogo->actualizar($this->idDe(CatalogoModel::DOCUMENTO, 'vida', 'comprobante_dom'), ['activo' => false]);

        $this->assertSame(
            ['doc_cotizacion', 'ine', 'constancia_fiscal', 'comprobante_pago'],
            CotizacionModel::documentosEmision('vida')
        );
    }

    public function testElNombreDeUnDocumentoEsElQueLeDaSuRamo(): void
    {
        (new CatalogoModel())->actualizar($this->idDe(CatalogoModel::DOCUMENTO, 'vida', 'ine'), ['etiqueta' => 'Identificación']);

        $vida = CotizacionModel::etiquetasDocumento('vida');
        $this->assertSame('Identificación', $vida['ine']);
        // Los que el ramo no tiene salen con el nombre de otro ramo.
        $this->assertSame('Foto del vehículo 1', $vida['foto_coche_1']);

        $this->assertSame('INE / Identificación oficial', CotizacionModel::etiquetasDocumento('autos')['ine']);
        $this->assertSame('Póliza', CotizacionModel::etiquetasDocumento()['poliza']);
    }

    // ── Opciones de los selectores ────────────────────────────────────────────

    public function testLasOpcionesConservanElValorGuardadoQueYaNoEstaEnElCatalogo(): void
    {
        $this->assertSame(['GNP', 'Chubb', 'Qualitas'], CotizacionModel::opcionesConValores(['GNP', 'Chubb'], ['Qualitas']));
        $this->assertSame(
            ['Básico', 'Amplio', 'Renovación - POL-123'],
            CotizacionModel::opcionesConValores(['Básico', 'Amplio'], ['Renovación - POL-123'])
        );
    }

    public function testLasOpcionesNoRepitenNiAnadenVacios(): void
    {
        $this->assertSame(['GNP', 'Chubb'], CotizacionModel::opcionesConValores(['GNP', 'Chubb'], ['GNP']));
        $this->assertSame(['GNP'], CotizacionModel::opcionesConValores(['GNP', 'GNP', ''], ['', null, '   ']));
        $this->assertSame(['GNP', 'AXA'], CotizacionModel::opcionesConValores(['gnp' => 'GNP', 'axa' => 'AXA'], []));
    }

    public function testLosValoresGuardadosVanAlFinalOrdenadosYTalCual(): void
    {
        // El valor se compara tal cual: es el texto que se guarda en la cotización.
        $this->assertSame(
            ['GNP', 'AXA', 'Gnp', 'zurich'],
            CotizacionModel::opcionesConValores(['GNP'], ['zurich', 'AXA', 'zurich', 'Gnp'])
        );
    }

    // ── Las pantallas ─────────────────────────────────────────────────────────
    // No se pueden pintar sobre SQLite (usan SQL de MySQL), así que se revisa
    // su código: que el ramo sea de la página y que las listas salgan del catálogo.

    public function testLasPantallasDeCotizacionYEmisionNoUsanElRamoDeSesion(): void
    {
        $raiz = dirname(__DIR__, 2);

        foreach ([
            'App/Views/cotizaciones/crear.php',
            'App/Views/cotizaciones/index.php',
            'App/Views/admin/index.php',
            'App/Views/admin/_tab_emision.php',
            'App/Views/admin/_ajax_emision.php',
            'public/js/emision.js',
        ] as $ruta) {
            $codigo = (string) file_get_contents($raiz . '/' . $ruta);

            $this->assertDoesNotMatchRegularExpression(
                '/SessionManager::(getRamo|setRamo|clearRamo)\b|ramo_activo|\/set-ramo/',
                $codigo,
                "{$ruta} sigue usando el ramo guardado en la sesión."
            );
        }
    }

    public function testLasPantallasDeCotizacionLeenElCatalogo(): void
    {
        $raiz  = dirname(__DIR__, 2);
        $crear = (string) file_get_contents($raiz . '/App/Views/cotizaciones/crear.php');
        $lista = (string) file_get_contents($raiz . '/App/Views/cotizaciones/index.php');

        foreach (['crear.php' => $crear, 'index.php' => $lista] as $ruta => $codigo) {
            $this->assertStringContainsString('CatalogoModel', $codigo, "{$ruta} no lee el catálogo.");
            $this->assertStringContainsString("\$_GET['ramo']", $codigo, "{$ruta} no toma el ramo de la URL.");
        }

        $this->assertStringNotContainsString("['GNP','Qualitas','Chubb','Inbursa']", $crear);
        $this->assertStringNotContainsString("'Ana Seguros'", $lista);
    }
}
