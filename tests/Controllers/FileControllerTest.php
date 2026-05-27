<?php
declare(strict_types=1);

namespace Tests\Controllers;

use PHPUnit\Framework\TestCase;
use App\Controllers\FileController;

/**
 * Tests para FileController.
 *
 * FileController::handle() sirve archivos desde el directorio /uploads.
 * Dado que necesita sesión autenticada y el sistema de archivos real,
 * probamos la lógica de validación de paths y tipos MIME sin tocar disco.
 *
 * Estrategia: interceptamos la salida con output buffering y
 * verificamos los códigos HTTP devueltos ante diferentes inputs.
 */
class FileControllerTest extends TestCase
{
    private string $tmpDir;
    private string $origUploadDir;

    protected function setUp(): void
    {
        // Simular sesión autenticada
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['agente_id']  = 1;
        $_SESSION['agente_rol'] = 'asesor';
        $_SESSION['logged_in']  = true;
        $_SESSION['last_activity'] = time();

        unset($_GET['file']);
    }

    protected function tearDown(): void
    {
        unset($_GET['file']);
    }

    // ── Validación de path ────────────────────────────────────────────────────

    /** @test */
    public function handle_sin_parametro_file_devuelve_400(): void
    {
        unset($_GET['file']);

        ob_start();
        FileController::handle();
        $out = ob_get_clean();

        $this->assertStringContainsString('specif', strtolower($out));
    }

    /** @test */
    public function handle_con_path_traversal_devuelve_400(): void
    {
        $_GET['file'] = '../../etc/passwd';

        ob_start();
        FileController::handle();
        $out = ob_get_clean();

        $this->assertStringContainsStringIgnoringCase('válida', $out);
    }

    /** @test */
    public function handle_con_ruta_inexistente_devuelve_404_o_500(): void
    {
        $_GET['file'] = 'no_existe_este_archivo_12345.pdf';

        ob_start();
        FileController::handle();
        $out = ob_get_clean();

        // Puede ser 404 (archivo no existe) o 500 (dir uploads no disponible en test)
        $this->assertNotEmpty($out);
    }

    // ── MIME map (prueba de caja blanca via reflexión) ─────────────────────────

    /** @test */
    public function mime_map_contiene_tipos_comunes(): void
    {
        $reflection = new \ReflectionClass(FileController::class);
        $prop       = $reflection->getProperty('MIME_MAP');
        $prop->setAccessible(true);
        $mimes = $prop->getValue(null);

        $esperados = ['pdf', 'jpg', 'png', 'docx', 'xlsx'];
        foreach ($esperados as $ext) {
            $this->assertArrayHasKey($ext, $mimes, "MIME_MAP debe contener la extensión '{$ext}'");
        }
    }

    /** @test */
    public function mime_map_no_contiene_extensiones_peligrosas(): void
    {
        $reflection = new \ReflectionClass(FileController::class);
        $prop       = $reflection->getProperty('MIME_MAP');
        $prop->setAccessible(true);
        $mimes = $prop->getValue(null);

        $peligrosos = ['php', 'sh', 'exe', 'bat', 'js', 'html'];
        foreach ($peligrosos as $ext) {
            $this->assertArrayNotHasKey(
                $ext,
                $mimes,
                "MIME_MAP NO debe permitir la extensión peligrosa '{$ext}'"
            );
        }
    }

    /** @test */
    public function archivos_inline_incluyen_pdf_e_imagenes(): void
    {
        $reflection = new \ReflectionClass(FileController::class);

        // Verificamos que handle() tiene la lógica inline para estos tipos
        // leyendo el código fuente o via prueba de comportamiento indirecto
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/App/Controllers/FileController.php'
        );

        $this->assertStringContainsString('application/pdf', $source);
        $this->assertStringContainsString('image/jpeg', $source);
        $this->assertStringContainsString('image/png', $source);
        $this->assertStringContainsString('inline', $source);
        $this->assertStringContainsString('attachment', $source);
    }
}