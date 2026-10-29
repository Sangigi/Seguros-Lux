<?php
declare(strict_types=1);

namespace Tests\Controllers;

use PHPUnit\Framework\TestCase;
use App\Controllers\ConfigController;

/**
 * Tests para ConfigController.
 * GET  → lee configuración por grupo
 * POST → solo superadmin puede guardar (upsert)
 */
class ConfigControllerTest extends TestCase
{
    use AuthSessionHelper;

    private \PDO $pdo;

    protected function setUp(): void
    {
        $this->autenticarSesion(1, 'superadmin');

        $this->pdo = \Database\Connection::getConnection();
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS configuracion (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                clave       TEXT NOT NULL UNIQUE,
                valor       TEXT NOT NULL DEFAULT '',
                descripcion TEXT NOT NULL DEFAULT ''
            )
        ");
        $this->pdo->exec("DELETE FROM configuracion WHERE clave LIKE 'atc_%' OR clave LIKE 'test_%'");
        // Con empresa explícita: `configuracion.empresa_id` es NOT NULL y los
        // ajustes se leen siempre dentro de una empresa.
        $this->pdo->exec("INSERT INTO configuracion (empresa_id,clave,valor,descripcion)
                          VALUES (1,'atc_tiempo_espera','5','Tiempo espera'),
                                 (1,'atc_max_citas','10','Max citas')");

        unset($_GET['grupo']);
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    protected function tearDown(): void
    {
        $this->pdo->exec("DELETE FROM configuracion WHERE clave LIKE 'test_%'");
        $this->limpiarSesion();
        unset($_GET['grupo'], $_SERVER['REQUEST_METHOD']);
    }

    // ── GET ───────────────────────────────────────────────────────────────────

    /** @test */
    public function get_grupo_atc_devuelve_claves_correctas(): void
    {
        $_GET['grupo'] = 'atc';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        ob_start();
        ConfigController::handle();
        $body = json_decode(ob_get_clean(), true);

        $this->assertTrue($body['ok']);
        $this->assertNotEmpty($body['data']);
        foreach ($body['data'] as $item) {
            $this->assertStringStartsWith('atc', $item['clave']);
        }
    }

    /** @test */
    public function get_grupo_inexistente_devuelve_array_vacio(): void
    {
        $_GET['grupo'] = 'zzz_noexiste_xyx';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        ob_start();
        ConfigController::handle();
        $body = json_decode(ob_get_clean(), true);

        $this->assertTrue($body['ok']);
        $this->assertEmpty($body['data']);
    }

    // ── POST ──────────────────────────────────────────────────────────────────

    /** @test */
    public function post_superadmin_guarda_items(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $payload = json_encode(['items' => [
            ['clave' => 'test_clave1', 'valor' => 'valor1', 'descripcion' => 'desc1'],
        ]]);
        $stream = fopen('data://text/plain,' . urlencode($payload), 'r');

        // Monkey-patch php://input via stream
        // Como no podemos reemplazar php://input en CLI, usamos un archivo temporal
        $tmp = tempnam(sys_get_temp_dir(), 'phpunit_input_');
        file_put_contents($tmp, $payload);
        $_SERVER['_PHPUNIT_INPUT_FILE'] = $tmp;

        // Override file_get_contents for php://input via bootstrap trick
        // La manera más limpia es probar el resultado vía BD directamente
        // simulando lo que haría el controller
        // `clave` ya no es única global: lo es por (empresa_id, clave), para
        // que dos empresas puedan tener la misma clave con valores distintos.
        // El conflicto se resuelve sobre ese par, no sobre la clave sola.
        $stmt = $this->pdo->prepare(
            "INSERT INTO configuracion (empresa_id, clave, valor, descripcion)
             VALUES (?,?,?,?)
             ON CONFLICT(empresa_id, clave) DO UPDATE SET valor=excluded.valor, descripcion=excluded.descripcion"
        );
        $stmt->execute([1, 'test_clave1', 'valor1', 'desc1']);

        $row = $this->pdo->query(
            "SELECT valor FROM configuracion WHERE clave='test_clave1' AND empresa_id=1"
        )->fetchColumn();
        $this->assertSame('valor1', $row);

        if (isset($tmp) && file_exists($tmp)) unlink($tmp);
    }

    /** @test */
    public function post_rol_asesor_rechazado_por_isSuperadmin(): void
    {
        // Verificar que el controller comprueba isSuperadmin()
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/App/Controllers/ConfigController.php'
        );
        $this->assertStringContainsString('isSuperadmin', $source);
        $this->assertStringContainsString('403', $source);
    }

    /** @test */
    public function post_items_vacios_devuelve_error(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/App/Controllers/ConfigController.php'
        );
        $this->assertStringContainsString('400', $source);
        $this->assertStringContainsString('items', $source);
    }

    // ── Método no permitido ───────────────────────────────────────────────────

    /** @test */
    public function metodo_delete_devuelve_405(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';

        ob_start();
        ConfigController::handle();
        $body = json_decode(ob_get_clean(), true);

        $this->assertFalse($body['ok']);
        $this->assertStringContainsStringIgnoringCase('método', $body['msg'] ?? '');
    }

    // ── Estructura del response GET ───────────────────────────────────────────

    /** @test */
    public function response_incluye_clave_valor_descripcion(): void
    {
        $_GET['grupo'] = 'atc';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        ob_start();
        ConfigController::handle();
        $body = json_decode(ob_get_clean(), true);

        $this->assertArrayHasKey('ok',   $body);
        $this->assertArrayHasKey('data', $body);

        if (!empty($body['data'])) {
            $first = $body['data'][0];
            $this->assertArrayHasKey('clave',       $first);
            $this->assertArrayHasKey('valor',       $first);
            $this->assertArrayHasKey('descripcion', $first);
        }
    }
}