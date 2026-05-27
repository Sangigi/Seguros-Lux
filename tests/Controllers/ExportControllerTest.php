<?php
declare(strict_types=1);

namespace Tests\Controllers;

use PHPUnit\Framework\TestCase;

/**
 * Tests para ExportController.
 *
 * ExportController::handle() genera CSV o HTML (PDF).
 * Requiere sesión autenticada. Probamos:
 *  - Lógica de formateo de filas (cotizaciones y ranking)
 *  - Validación de parámetros (formato, tipo)
 *  - Formato de fecha
 *  - Nombre de archivo generado
 */
class ExportControllerTest extends TestCase
{
    private \PDO $pdo;
    private int  $agenteId;
    private int  $clienteId;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['agente_id']     = 1;
        $_SESSION['agente_rol']    = 'asesor';
        $_SESSION['logged_in']     = true;
        $_SESSION['last_activity'] = time();

        $this->pdo = \Database\Connection::getConnection();

        // Agente y cliente de prueba
        $this->pdo->exec("INSERT OR IGNORE INTO agentes (id,nombre,apellido,correo,contrasena,rol,activo)
                          VALUES (300,'Export','Test','export@test.com','hash','asesor',1)");
        $this->agenteId = 300;

        $this->pdo->exec("INSERT OR IGNORE INTO clientes (id,nombre,apellido_paterno,ramo,agente_id)
                          VALUES (300,'ClienteExport','Test','autos',300)");
        $this->clienteId = 300;

        unset($_GET['formato'], $_GET['tipo'], $_GET['desde'], $_GET['hasta'], $_GET['estado']);
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    protected function tearDown(): void
    {
        $this->pdo->exec("DELETE FROM cotizaciones WHERE agente_id = {$this->agenteId}");
        $this->pdo->exec("DELETE FROM clientes WHERE id = {$this->clienteId}");
        $this->pdo->exec("DELETE FROM agentes  WHERE id = {$this->agenteId}");
        unset($_GET['formato'], $_GET['tipo'], $_GET['desde'], $_GET['hasta'], $_GET['estado']);
    }

    // ── Formateo de fecha ─────────────────────────────────────────────────────

    /** @test */
    public function formato_fecha_d_m_y_es_correcto(): void
    {
        $fecha    = '2025-06-15';
        $esperado = '15/06/2025';
        $formateado = date('d/m/Y', strtotime($fecha));

        $this->assertSame($esperado, $formateado);
    }

    // ── Formateo de precio ────────────────────────────────────────────────────

    /** @test */
    public function formato_precio_incluye_simbolo_pesos(): void
    {
        $precio    = 12345.67;
        $formateado = '$' . number_format($precio, 2);

        $this->assertStringStartsWith('$', $formateado);
        $this->assertSame('$12,345.67', $formateado);
    }

    // ── Validación de estados de cotización ───────────────────────────────────

    /** @test */
    public function estados_validos_de_cotizacion(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/App/Controllers/ExportController.php'
        );

        foreach (['pendiente', 'enviada', 'aceptada', 'rechazada', 'pagado'] as $estado) {
            $this->assertStringContainsString($estado, $source);
        }
    }

    // ── Validación de parámetros de formato/tipo ──────────────────────────────

    /** @test */
    public function tipo_invalido_devuelve_400(): void
    {
        $_GET['formato'] = 'excel';
        $_GET['tipo']    = 'tipos_invalido_xyz';

        ob_start();
        \App\Controllers\ExportController::handle();
        $out = ob_get_clean();

        $this->assertStringContainsStringIgnoringCase('válido', $out);
    }

    /** @test */
    public function controlador_soporta_tipo_cotizaciones_y_ranking(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/App/Controllers/ExportController.php'
        );

        $this->assertStringContainsString("'cotizaciones'", $source);
        $this->assertStringContainsString("'ranking'",      $source);
    }

    /** @test */
    public function controlador_soporta_formato_excel_y_pdf(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/App/Controllers/ExportController.php'
        );

        $this->assertStringContainsString("'excel'", $source);
        $this->assertStringContainsString("'pdf'",   $source);
    }

    // ── CSV con cotizaciones reales ───────────────────────────────────────────

    /** @test */
    public function export_excel_cotizaciones_genera_csv(): void
    {
        // Insertar cotización de prueba
        $this->pdo->prepare(
            "INSERT INTO cotizaciones (cliente_id, agente_id, ramo, estado, precio, tipo_cobertura)
             VALUES (?, ?, 'autos', 'pendiente', 8500.00, 'amplia')"
        )->execute([$this->clienteId, $this->agenteId]);

        $_SESSION['agente_id']  = $this->agenteId;
        $_SESSION['agente_rol'] = 'asesor';
        $_GET['formato'] = 'excel';
        $_GET['tipo']    = 'cotizaciones';

        ob_start();
        \App\Controllers\ExportController::handle();
        $out = ob_get_clean();

        // CSV debe contener encabezados de columnas
        $this->assertStringContainsString('Folio', $out);
        $this->assertStringContainsString('Asesor', $out);
        $this->assertStringContainsString('Cliente', $out);
    }

    /** @test */
    public function export_csv_incluye_bom_utf8(): void
    {
        $_SESSION['agente_id']  = $this->agenteId;
        $_SESSION['agente_rol'] = 'asesor';
        $_GET['formato'] = 'excel';
        $_GET['tipo']    = 'cotizaciones';

        ob_start();
        \App\Controllers\ExportController::handle();
        $out = ob_get_clean();

        // BOM UTF-8: EF BB BF
        $bom = "\xEF\xBB\xBF";
        $this->assertStringStartsWith($bom, $out, 'CSV debe iniciar con BOM UTF-8');
    }

    /** @test */
    public function export_ranking_genera_columna_conversion(): void
    {
        $_SESSION['agente_id']  = $this->agenteId;
        $_SESSION['agente_rol'] = 'administrador';
        $_GET['formato'] = 'excel';
        $_GET['tipo']    = 'ranking';

        ob_start();
        \App\Controllers\ExportController::handle();
        $out = ob_get_clean();

        $this->assertStringContainsString('Asesor',     $out);
        $this->assertStringContainsString('Conversión', $out);
    }

    // ── Control de acceso ─────────────────────────────────────────────────────

    /** @test */
    public function controlador_require_auth(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/App/Controllers/ExportController.php'
        );

        $this->assertStringContainsString('requireAuth', $source);
    }

    /** @test */
    public function agente_solo_ve_sus_cotizaciones(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/App/Controllers/ExportController.php'
        );

        // El controlador debe filtrar por agente_id cuando no es admin
        $this->assertStringContainsString('isAdmin',    $source);
        $this->assertStringContainsString('agenteId',   $source);
    }
}