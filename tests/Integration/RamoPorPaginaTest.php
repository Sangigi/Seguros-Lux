<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Controllers\CitaController;
use App\Controllers\ClientController;
use App\Models\CatalogoModel;
use App\Security\Tenant;
use App\Services\QueryCache;
use PHPUnit\Framework\TestCase;

/**
 * El ramo deja de ser estado de sesión: cada pantalla filtra con su propio
 * ?ramo=<clave> y todo ramo que entra se valida contra el catálogo de la
 * empresa, no contra una lista escrita a mano.
 *
 * Dos partes:
 *  - Controladores: el ramo que llega se resuelve con el catálogo (activos,
 *    alias de formularios viejos, ramos desactivados).
 *  - Fuente de las vistas: ninguna lee ni escribe el ramo de la sesión, y
 *    /set-ramo ya no guarda nada.
 *
 * Como CatalogosTest, las pruebas que cambian el catálogo trabajan sobre una
 * empresa propia para no afectar a la empresa 1 del bootstrap.
 */
class RamoPorPaginaTest extends TestCase
{
    private \PDO $pdo;
    private int $empresaId;

    protected function setUp(): void
    {
        $this->pdo = \Database\Connection::getConnection();

        Tenant::reset();
        QueryCache::flush();

        $this->pdo->prepare(
            "INSERT INTO empresas (nombre, slug, plan, max_usuarios, activo) VALUES (?, ?, 'basico', 10, 1)"
        )->execute(['Ramo por página', 'ramo-pagina-' . uniqid()]);
        $this->empresaId = (int) $this->pdo->lastInsertId();

        Tenant::set($this->empresaId);
        (new CatalogoModel())->sembrar($this->empresaId);
    }

    protected function tearDown(): void
    {
        foreach (['citas', 'clientes', 'catalogos'] as $tabla) {
            $this->pdo->prepare("DELETE FROM {$tabla} WHERE empresa_id = ?")->execute([$this->empresaId]);
        }
        $this->pdo->prepare('DELETE FROM empresas WHERE id = ?')->execute([$this->empresaId]);

        QueryCache::flush();
        Tenant::reset();
        Tenant::set(1);
    }

    private function privado(string $clase, string $metodo, mixed ...$args): mixed
    {
        $m = (new \ReflectionClass($clase))->getMethod($metodo);
        $m->setAccessible(true);

        return $m->invoke(null, ...$args);
    }

    private function desactivarRamo(string $clave): void
    {
        $cat = new CatalogoModel();
        foreach ($cat->lista(CatalogoModel::RAMO) as $fila) {
            if ($fila['clave'] === $clave) {
                $cat->actualizar($fila['id'], ['activo' => false]);
                return;
            }
        }
        $this->fail("No existe el ramo {$clave}.");
    }

    private function crearCliente(string $ramo): int
    {
        $this->pdo->prepare(
            "INSERT INTO clientes (nombre, apellido_paterno, ramo, agente_id, empresa_id) VALUES ('Prueba', 'Ramo', ?, 1, ?)"
        )->execute([$ramo, $this->empresaId]);

        return (int) $this->pdo->lastInsertId();
    }

    // ── Alta pública de clientes (ClientController) ───────────────────────────

    public function testElLeadConservaUnRamoActivo(): void
    {
        $this->assertSame('vida', $this->privado(ClientController::class, 'resolverRamo', ['ramo' => 'vida']));
        $this->assertSame('empresas', $this->privado(ClientController::class, 'resolverRamo', ['ramo' => 'empresas']));
    }

    public function testLosValoresDeFormulariosViejosSeTraducenAlRamoReal(): void
    {
        // Antes un tipo de vehículo acababa guardado como ramo ('moto'…), que
        // no existe en el catálogo y dejaba al cliente fuera de toda pestaña.
        foreach (['moto', 'camioneta', 'pickup', 'auto'] as $viejo) {
            $this->assertSame('autos', $this->privado(ClientController::class, 'resolverRamo', ['ramo' => $viejo]), $viejo);
        }
        $this->assertSame('gastos_medicos', $this->privado(ClientController::class, 'resolverRamo', ['ramo' => 'gmm']));
        $this->assertSame('autos', $this->privado(ClientController::class, 'resolverRamo', ['tipoVehiculo' => 'moto']));
    }

    public function testUnRamoDesconocidoCaeEnAutosOEnElPrimerRamoActivo(): void
    {
        $this->assertSame('autos', $this->privado(ClientController::class, 'resolverRamo', ['ramo' => 'inventado']));
        $this->assertSame('autos', $this->privado(ClientController::class, 'resolverRamo', []));

        $this->desactivarRamo('autos');

        $this->assertSame('vida', $this->privado(ClientController::class, 'resolverRamo', ['ramo' => 'inventado']));
        $this->assertSame('vida', $this->privado(ClientController::class, 'resolverRamo', ['ramo' => ['no', 'texto']]));
    }

    public function testUnLeadDeUnRamoDesactivadoNoSeReetiqueta(): void
    {
        $this->desactivarRamo('autos');

        $this->assertSame('autos', $this->privado(ClientController::class, 'resolverRamo', ['ramo' => 'autos']));
        $this->assertSame('autos', $this->privado(ClientController::class, 'resolverRamo', ['tipoVehiculo' => 'auto']));
    }

    // ── Citas por API (CitaController) ────────────────────────────────────────

    public function testLaCitaAceptaUnRamoActivoDelCatalogo(): void
    {
        $cliente = $this->crearCliente('autos');

        $this->assertSame('empresas', $this->privado(CitaController::class, 'ramoDeCita', $cliente, 'empresas'));
    }

    public function testSinRamoValidoLaCitaTomaElDelCliente(): void
    {
        $cliente = $this->crearCliente('gastos_medicos');

        $this->assertSame('gastos_medicos', $this->privado(CitaController::class, 'ramoDeCita', $cliente, ''));
        $this->assertSame('gastos_medicos', $this->privado(CitaController::class, 'ramoDeCita', $cliente, 'inventado'));

        $this->desactivarRamo('vida');
        $this->assertSame('gastos_medicos', $this->privado(CitaController::class, 'ramoDeCita', $cliente, 'vida'));
    }

    public function testElClienteDeOtraEmpresaNoPrestaSuRamo(): void
    {
        // Un cliente de la empresa 1 no es visible desde esta: la cita cae en
        // el primer ramo activo en vez de leer el ramo ajeno.
        $ajeno = (int) $this->pdo->query(
            "SELECT id FROM clientes WHERE empresa_id = 1 AND ramo <> 'autos' LIMIT 1"
        )->fetchColumn();

        if ($ajeno === 0) {
            $this->pdo->exec(
                "INSERT INTO clientes (nombre, apellido_paterno, ramo, agente_id, empresa_id) VALUES ('Ajeno', 'E1', 'empresas', 1, 1)"
            );
            $ajeno = (int) $this->pdo->lastInsertId();
            $borrar = $ajeno;
        }

        try {
            $this->assertSame('autos', $this->privado(CitaController::class, 'ramoDeCita', $ajeno, ''));
        } finally {
            if (isset($borrar)) {
                $this->pdo->prepare('DELETE FROM clientes WHERE id = ?')->execute([$borrar]);
            }
        }
    }

    // ── Fuente de las vistas ──────────────────────────────────────────────────

    /** @return array<string,string> ruta relativa => contenido */
    private static function fuentes(array $rutas): array
    {
        $raiz = dirname(__DIR__, 2);
        $out  = [];
        foreach ($rutas as $ruta) {
            $out[$ruta] = (string) file_get_contents($raiz . '/' . $ruta);
        }

        return $out;
    }

    public function testNingunaPantallaLeeNiEscribeElRamoDeSesion(): void
    {
        $vistas = self::fuentes([
            'App/Views/clientes/index.php',
            'App/Views/clientes/detalle.php',
            'App/Views/citas/index.php',
            'App/Views/dashboard/index.php',
            'App/Views/dashboard/set_ramo.php',
            'App/Views/reportes/index.php',
            'App/Views/partials/_navbar.php',
            'App/Views/partials/_modales_clientes.php',
            'public/js/clientes.js',
            'public/js/calendario.js',
        ]);

        foreach ($vistas as $ruta => $codigo) {
            $this->assertDoesNotMatchRegularExpression(
                '/SessionManager::(getRamo|setRamo|clearRamo)\b|ramo_activo/',
                $codigo,
                "{$ruta} sigue usando el ramo guardado en la sesión."
            );
        }

        foreach ($vistas as $ruta => $codigo) {
            if ($ruta === 'App/Views/dashboard/set_ramo.php') {
                continue;
            }
            $this->assertStringNotContainsString('/set-ramo', $codigo, "{$ruta} sigue enlazando a /set-ramo.");
        }
    }

    public function testLasListasDeRamosYAseguradorasSalenDelCatalogo(): void
    {
        $vistas = self::fuentes([
            'App/Views/clientes/index.php',
            'App/Views/clientes/detalle.php',
            'App/Views/citas/index.php',
            'App/Views/dashboard/index.php',
            'App/Views/reportes/index.php',
            'App/Controllers/ClientController.php',
        ]);

        foreach ($vistas as $ruta => $codigo) {
            $this->assertStringContainsString('CatalogoModel', $codigo, "{$ruta} no lee el catálogo.");
            $this->assertDoesNotMatchRegularExpression(
                "/\\[\\s*'autos'\\s*,\\s*'vida'\\s*,\\s*'gastos_medicos'\\s*,\\s*'empresas'\\s*\\]/",
                $codigo,
                "{$ruta} valida el ramo con una lista escrita a mano."
            );
        }

        $this->assertStringNotContainsString(
            "['GNP','Qualitas','Chubb','Inbursa']",
            $vistas['App/Views/clientes/detalle.php'],
            'Las aseguradoras de la ficha del cliente deben salir del catálogo.'
        );
    }

    public function testElMenuDelCoordinadorNoRepiteAccesosALasPestanasDeAdmin(): void
    {
        $navbar = self::fuentes(['App/Views/partials/_navbar.php'])['App/Views/partials/_navbar.php'];

        $this->assertStringNotContainsString('Acciones rápidas', $navbar);
        $this->assertSame(0, substr_count($navbar, 'href="/admin?tab=emision"'),
            'Emisión ya está entre las pestañas del menú; no hace falta un acceso aparte.');
    }
}
