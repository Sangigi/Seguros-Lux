<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Models\CatalogoModel;
use App\Models\CotizacionModel;
use App\Models\EmpresaModel;
use App\Security\Tenant;
use App\Services\QueryCache;
use DomainException;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * Catálogos por empresa: siembra, reglas de edición y aislamiento.
 *
 * Las pruebas trabajan sobre dos empresas propias (A y B) y no sobre la
 * empresa 1 del bootstrap, para poder cambiar sus catálogos sin afectar a
 * otras pruebas. La empresa 1 solo se lee, para comprobar lo que sembró la
 * migración.
 *
 * Las de aislamiento siguen la idea de AislamientoEmpresaTest: desde una
 * empresa se intenta leer, modificar y borrar lo de la otra, y se comprueba
 * en la base, sin pasar por el modelo, que no ocurrió.
 */
class CatalogosTest extends TestCase
{
    private \PDO $pdo;

    private int $empresaA;
    private int $empresaB;

    /** @var list<int> Empresas creadas por la prueba, para borrarlas al final. */
    private array $creadas = [];

    protected function setUp(): void
    {
        $this->pdo = \Database\Connection::getConnection();

        Tenant::reset();
        QueryCache::flush();

        $this->empresaA = $this->crearEmpresa('Catálogo A');
        $this->empresaB = $this->crearEmpresa('Catálogo B');

        foreach ([$this->empresaA, $this->empresaB] as $id) {
            Tenant::comoEmpresa($id, fn() => (new CatalogoModel())->sembrar($id));
        }

        Tenant::set($this->empresaA);
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
        )->execute([$nombre, 'catalogo-' . uniqid()]);

        $id              = (int) $this->pdo->lastInsertId();
        $this->creadas[] = $id;

        return $id;
    }

    private function modelo(): CatalogoModel
    {
        return new CatalogoModel();
    }

    private function contarFilas(int $empresaId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM catalogos WHERE empresa_id = ?');
        $stmt->execute([$empresaId]);

        return (int) $stmt->fetchColumn();
    }

    /** Id de un elemento de la empresa activa, buscado por su clave. */
    private function idDe(string $tipo, string $ramo, string $clave): int
    {
        foreach ($this->modelo()->lista($tipo, $ramo) as $fila) {
            if ($fila['clave'] === $clave) {
                return $fila['id'];
            }
        }

        $this->fail("No existe {$tipo}/{$ramo}/{$clave} en la empresa activa.");
    }

    // ── Siembra ───────────────────────────────────────────────────────────────

    public function testLaMigracionSembroLasEmpresasExistentes(): void
    {
        $this->assertSame(
            count(CatalogoModel::filasPorDefecto()),
            $this->contarFilas(1),
            'La migración 007 no dejó a la empresa 1 con el catálogo por defecto completo.'
        );
    }

    public function testLaMigracionSePuedeRepetirSinDuplicar(): void
    {
        $antes = [$this->contarFilas(1), $this->contarFilas($this->empresaA)];

        $migracion = require dirname(__DIR__, 2) . '/Database/migrations/007_catalogos.php';
        ($migracion['up'])($this->pdo, new \Database\SchemaHelper($this->pdo));

        $this->assertSame($antes, [$this->contarFilas(1), $this->contarFilas($this->empresaA)]);
    }

    public function testSembrarDosVecesNoDuplicaNada(): void
    {
        $antes = $this->contarFilas($this->empresaA);

        $this->modelo()->sembrar($this->empresaA);

        $this->assertSame($antes, $this->contarFilas($this->empresaA));
        $this->assertSame(0, CatalogoModel::sembrarCon($this->pdo, $this->empresaA));
        $this->assertSame(count(CatalogoModel::filasPorDefecto()), $antes);
    }

    public function testVolverASembrarNoResucitaUnaAseguradoraBorrada(): void
    {
        $modelo = $this->modelo();
        $modelo->eliminar($this->idDe(CatalogoModel::ASEGURADORA, 'autos', 'inbursa'));

        $modelo->sembrar($this->empresaA);

        $this->assertNotContains('Inbursa', $modelo->etiquetas(CatalogoModel::ASEGURADORA, 'autos'));
    }

    public function testUnaEmpresaSinCatalogoSeSiembraEnSuPrimeraLectura(): void
    {
        $this->pdo->prepare('DELETE FROM catalogos WHERE empresa_id = ?')->execute([$this->empresaA]);
        QueryCache::flush();

        $this->assertSame(CatalogoModel::RAMOS, $this->modelo()->ramos());
        $this->assertSame(count(CatalogoModel::filasPorDefecto()), $this->contarFilas($this->empresaA));
    }

    public function testUnaEmpresaNuevaNaceConSusCatalogos(): void
    {
        $id              = (new EmpresaModel())->crear(['nombre' => 'Empresa recién creada']);
        $this->creadas[] = $id;

        $this->assertGreaterThan(0, $id);
        $this->assertSame(count(CatalogoModel::filasPorDefecto()), $this->contarFilas($id));
    }

    public function testNoSePuedeSembrarOtraEmpresaDesdeLaActiva(): void
    {
        $this->expectException(LogicException::class);

        $this->modelo()->sembrar($this->empresaB);
    }

    // ── Valores por defecto ───────────────────────────────────────────────────

    public function testLosRamosPorDefectoSonLosCuatroQueUsaElCrm(): void
    {
        $this->assertSame(
            ['autos' => 'Autos', 'vida' => 'Vida', 'gastos_medicos' => 'Gastos Médicos', 'empresas' => 'Empresas'],
            $this->modelo()->ramos()
        );
    }

    public function testLasAseguradorasPorDefectoSonLasDeCotizacionModel(): void
    {
        // Copia de la lista que CotizacionModel::companiasPorRamo() tenía
        // escrita a mano antes de leer del catálogo (ahora delega en él, así
        // que compararlo consigo mismo no probaría nada).
        $esperadas = [
            'autos'          => ['GNP', 'Qualitas', 'Chubb', 'Inbursa'],
            'vida'           => ['GNP', 'MetLife', 'Seguros Monterrey', 'Banamex Seguros', 'BBVA Seguros', 'Inbursa'],
            'gastos_medicos' => ['GNP', 'AXA', 'Qualitas', 'Mapfre', 'Chubb', 'BUPA', 'Allianz'],
            'empresas'       => ['GNP', 'AXA', 'Mapfre', 'Chubb', 'HDI', 'Zurich', 'Allianz'],
        ];

        foreach ($esperadas as $ramo => $companias) {
            $this->assertSame(
                $companias,
                array_values($this->modelo()->etiquetas(CatalogoModel::ASEGURADORA, $ramo)),
                "Las aseguradoras de {$ramo} no coinciden con las que usaba el CRM."
            );
        }

        $this->assertSame($esperadas, CotizacionModel::companiasPorRamo());
    }

    public function testLasCoberturasPorDefectoSonLasDeNuevaCotizacion(): void
    {
        // Copia de $coberturasPorRamo en App/Views/cotizaciones/crear.php: el
        // texto exacto importa, porque es lo que se guarda en tipo_cobertura.
        $esperadas = [
            'autos'          => ['Básico', 'Amplio', 'Amplio Total', 'Premium'],
            'vida'           => ['Temporal', 'Entera', 'Dotal', 'Entera con Ahorro'],
            'gastos_medicos' => ['Individual', 'Familiar', 'Empresarial', 'PyME'],
            'empresas'       => ['Responsabilidad Civil', 'Daños', 'Flotillas', 'Colectivo de Vida', 'GMM Colectivo', 'Multirriesgo', 'Fianzas'],
        ];

        foreach ($esperadas as $ramo => $coberturas) {
            $this->assertSame(
                $coberturas,
                array_values($this->modelo()->etiquetas(CatalogoModel::COBERTURA, $ramo))
            );
        }
    }

    public function testLosDocumentosRequeridosPorDefectoSonLosQueSePedianParaEmitir(): void
    {
        // Los que el asesor tenía que subir antes de enviar a emisión
        // (App/Views/clientes/detalle.php, $docs_obligatorios_asesor).
        $modelo = $this->modelo();

        $this->assertSame(
            ['doc_cotizacion', 'ine', 'comprobante_dom', 'tarjeta_circulacion', 'foto_coche_1', 'foto_coche_2'],
            $modelo->requeridos('autos')
        );

        foreach (['vida', 'gastos_medicos', 'empresas'] as $ramo) {
            $this->assertSame(['doc_cotizacion', 'ine', 'comprobante_dom'], $modelo->requeridos($ramo));
        }
    }

    public function testAutosConoceTodosLosDocumentosYLosDelFlujoNoSeExigen(): void
    {
        $modelo = $this->modelo();

        $this->assertSame(
            CatalogoModel::DOCUMENTOS,
            $modelo->etiquetas(CatalogoModel::DOCUMENTO, 'autos', false)
        );

        foreach (array_keys(CatalogoModel::RAMOS) as $ramo) {
            foreach (CatalogoModel::DOCUMENTOS_PROCESO as $clave) {
                $this->assertNotContains($clave, $modelo->requeridos($ramo));
            }
        }
    }

    public function testSinRamoSeJuntanLasAseguradorasDeTodosLosRamos(): void
    {
        $todas = $this->modelo()->etiquetas(CatalogoModel::ASEGURADORA);

        $this->assertSame('GNP', array_values($todas)[0], 'Deberían salir primero las de autos.');
        $this->assertSame(1, count(array_keys($todas, 'GNP', true)), 'GNP aparece en todos los ramos, pero una sola vez.');
        $this->assertContains('Zurich', $todas);
        $this->assertContains('MetLife', $todas);
    }

    public function testLaListaDiceQueSePuedeHacerConCadaElemento(): void
    {
        $modelo = $this->modelo();

        $ramo = $modelo->lista(CatalogoModel::RAMO)[0];
        $this->assertFalse($ramo['eliminable']);
        $this->assertTrue($ramo['conmutable']);

        $aseguradora = $modelo->lista(CatalogoModel::ASEGURADORA, 'autos')[0];
        $this->assertTrue($aseguradora['eliminable']);

        $poliza = $modelo->fila($this->idDe(CatalogoModel::DOCUMENTO, 'autos', 'poliza'));
        $this->assertFalse($poliza['eliminable']);
        $this->assertFalse($poliza['conmutable']);
    }

    // ── Agregar ───────────────────────────────────────────────────────────────

    public function testAgregarAnadeAlFinalConUnaClaveDerivadaDelNombre(): void
    {
        $modelo = $this->modelo();
        $id     = $modelo->agregar(CatalogoModel::ASEGURADORA, 'autos', '  Seguros   Atlas ');

        $fila = $modelo->fila($id);
        $this->assertSame('seguros_atlas', $fila['clave']);
        $this->assertSame('Seguros Atlas', $fila['etiqueta']);
        $this->assertTrue($fila['activo']);

        $etiquetas = $modelo->etiquetas(CatalogoModel::ASEGURADORA, 'autos');
        $this->assertSame('Seguros Atlas', end($etiquetas));
    }

    public function testAgregarRechazaUnNombreRepetidoSinImportarMayusculasNiAcentos(): void
    {
        foreach (['gnp', 'QUÁLITAS', ' chubb '] as $repetida) {
            try {
                $this->modelo()->agregar(CatalogoModel::ASEGURADORA, 'autos', $repetida);
                $this->fail("Se aceptó «{$repetida}», que ya estaba en la lista.");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('ya está en la lista', $e->getMessage());
            }
        }

        $this->assertCount(4, $this->modelo()->etiquetas(CatalogoModel::ASEGURADORA, 'autos'));
    }

    public function testElMismoNombreSiCabeEnOtroRamo(): void
    {
        $id = $this->modelo()->agregar(CatalogoModel::ASEGURADORA, 'vida', 'Qualitas');

        $this->assertGreaterThan(0, $id);
    }

    public function testAgregarSoloAdmiteAseguradorasYCoberturas(): void
    {
        foreach ([CatalogoModel::RAMO, CatalogoModel::DOCUMENTO, 'inventado'] as $tipo) {
            try {
                $this->modelo()->agregar($tipo, 'autos', 'Algo nuevo');
                $this->fail("Se pudo añadir un elemento de tipo «{$tipo}».");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testAgregarRechazaRamoInexistenteYNombreVacio(): void
    {
        foreach ([['marítimo', 'Algo'], ['autos', '   '], ['autos', '***'], ['autos', str_repeat('x', 121)]] as [$ramo, $nombre]) {
            try {
                $this->modelo()->agregar(CatalogoModel::COBERTURA, $ramo, $nombre);
                $this->fail("Se aceptó la cobertura «{$nombre}» en «{$ramo}».");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    // ── Actualizar y eliminar ─────────────────────────────────────────────────

    public function testRenombrarRechazaElNombreDeOtroPeroAdmiteElPropio(): void
    {
        $modelo = $this->modelo();
        $chubb  = $this->idDe(CatalogoModel::ASEGURADORA, 'autos', 'chubb');
        $gnp    = $this->idDe(CatalogoModel::ASEGURADORA, 'autos', 'gnp');

        try {
            $modelo->actualizar($chubb, ['etiqueta' => 'GNP']);
            $this->fail('Se renombró Chubb con el nombre de otra aseguradora de la lista.');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        $this->assertTrue($modelo->actualizar($gnp, ['etiqueta' => 'Gnp']));
        $this->assertSame('Gnp', $modelo->fila($gnp)['etiqueta']);
    }

    public function testRamosYDocumentosNoSeEliminan(): void
    {
        $modelo = $this->modelo();

        foreach ([
            [CatalogoModel::RAMO, '', 'vida'],
            [CatalogoModel::DOCUMENTO, 'autos', 'ine'],
        ] as [$tipo, $ramo, $clave]) {
            $id = $this->idDe($tipo, $ramo, $clave);

            try {
                $modelo->eliminar($id);
                $this->fail("Se eliminó {$tipo}/{$clave}.");
            } catch (DomainException) {
                $this->assertNotNull($modelo->fila($id), "{$tipo}/{$clave} desapareció pese al rechazo.");
            }
        }
    }

    public function testEliminarUnaCobertura(): void
    {
        $modelo = $this->modelo();
        $id     = $this->idDe(CatalogoModel::COBERTURA, 'empresas', 'fianzas');

        $this->assertTrue($modelo->eliminar($id));
        $this->assertNull($modelo->fila($id));
        $this->assertNotContains('Fianzas', $modelo->etiquetas(CatalogoModel::COBERTURA, 'empresas', false));
    }

    public function testNoSePuedeDesactivarElUltimoRamoActivo(): void
    {
        $modelo = $this->modelo();

        foreach (['autos', 'vida', 'gastos_medicos'] as $clave) {
            $this->assertTrue($modelo->actualizar($this->idDe(CatalogoModel::RAMO, '', $clave), ['activo' => false]));
        }

        try {
            $modelo->actualizar($this->idDe(CatalogoModel::RAMO, '', 'empresas'), ['activo' => false]);
            $this->fail('Se desactivó el último ramo activo.');
        } catch (DomainException) {
            $this->assertSame(['empresas' => 'Empresas'], $modelo->ramos());
        }

        $this->assertCount(4, $modelo->ramos(false));
    }

    public function testRequeridosReflejaLosDocumentosActivos(): void
    {
        $modelo = $this->modelo();

        $modelo->actualizar($this->idDe(CatalogoModel::DOCUMENTO, 'vida', 'constancia_fiscal'), ['activo' => true]);
        $modelo->actualizar($this->idDe(CatalogoModel::DOCUMENTO, 'vida', 'comprobante_dom'), ['activo' => '0']);

        $this->assertSame(['doc_cotizacion', 'ine', 'constancia_fiscal'], $modelo->requeridos('vida'));
        $this->assertSame([], $modelo->requeridos(''));
        $this->assertSame([], $modelo->requeridos('maritimo'));
    }

    public function testLosDocumentosDelFlujoSoloSeRenombran(): void
    {
        $modelo = $this->modelo();
        $poliza = $this->idDe(CatalogoModel::DOCUMENTO, 'autos', 'poliza');

        try {
            $modelo->actualizar($poliza, ['activo' => true]);
            $this->fail('La póliza se marcó como requisito para enviar a emisión.');
        } catch (DomainException) {
            $this->assertNotContains('poliza', $modelo->requeridos('autos'));
        }

        $this->assertTrue($modelo->actualizar($poliza, ['etiqueta' => 'Póliza emitida']));
        $this->assertSame('Póliza emitida', $modelo->etiquetas(CatalogoModel::DOCUMENTO, 'autos', false)['poliza']);
    }

    // ── Aislamiento entre empresas ────────────────────────────────────────────

    public function testUnaEmpresaNoLeeElCatalogoDeOtra(): void
    {
        $idDeB = Tenant::comoEmpresa(
            $this->empresaB,
            fn() => $this->modelo()->agregar(CatalogoModel::ASEGURADORA, 'autos', 'Exclusiva de B')
        );

        $modelo = $this->modelo();

        $this->assertNull($modelo->fila($idDeB), 'Desde A se leyó un elemento del catálogo de B por su id.');
        $this->assertNotContains('Exclusiva de B', $modelo->etiquetas(CatalogoModel::ASEGURADORA, 'autos', false));
        $this->assertNotContains('Exclusiva de B', $modelo->etiquetas(CatalogoModel::ASEGURADORA));
        $this->assertNotContains($idDeB, array_column($modelo->lista(CatalogoModel::ASEGURADORA, 'autos'), 'id'));
    }

    public function testUnaEmpresaNoModificaNiBorraElCatalogoDeOtra(): void
    {
        [$aseguradoraDeB, $ramoDeB] = Tenant::comoEmpresa($this->empresaB, fn() => [
            $this->idDe(CatalogoModel::ASEGURADORA, 'autos', 'gnp'),
            $this->idDe(CatalogoModel::RAMO, '', 'autos'),
        ]);

        $modelo = $this->modelo();

        $this->assertFalse($modelo->actualizar($aseguradoraDeB, ['etiqueta' => 'Secuestrada', 'activo' => false]));
        $this->assertFalse($modelo->actualizar($ramoDeB, ['activo' => false]));
        $this->assertFalse($modelo->eliminar($aseguradoraDeB));

        $stmt = $this->pdo->prepare('SELECT etiqueta, activo FROM catalogos WHERE id = ?');

        $stmt->execute([$aseguradoraDeB]);
        $this->assertSame(
            ['etiqueta' => 'GNP', 'activo' => 1],
            array_map(static fn($v) => is_numeric($v) ? (int) $v : $v, $stmt->fetch()),
            'La empresa A modificó o borró una aseguradora de la empresa B.'
        );

        $stmt->execute([$ramoDeB]);
        $this->assertSame(1, (int) $stmt->fetch()['activo'], 'La empresa A desactivó un ramo de la empresa B.');
    }

    public function testAgregarSellaLaEmpresaActiva(): void
    {
        $id = $this->modelo()->agregar(CatalogoModel::COBERTURA, 'autos', 'Responsabilidad Civil Plus');

        $stmt = $this->pdo->prepare('SELECT empresa_id FROM catalogos WHERE id = ?');
        $stmt->execute([$id]);

        $this->assertSame($this->empresaA, (int) $stmt->fetchColumn());
    }

    public function testSinEmpresaEnContextoNoSeLeeNingunCatalogo(): void
    {
        Tenant::reset();

        $this->expectException(\RuntimeException::class);

        Tenant::sinRestriccion(fn() => $this->modelo()->ramos());
    }
}
