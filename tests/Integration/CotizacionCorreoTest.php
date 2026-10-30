<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Security\Roles;
use App\Security\Tenant;
use App\Services\ArchivosCotizacion;
use App\Services\CotizacionCorreo;
use PHPMailer\PHPMailer\Exception as MailerException;
use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\TestCase;

/**
 * «Enviar al cliente» manda de verdad la cotización por correo.
 *
 * Antes el botón solo cambiaba el estado a 'enviada' sin que el cliente
 * recibiera nada. Lo que se prueba aquí es que el estado ya no miente: solo
 * cambia si el correo salió, nunca hace retroceder una cotización aceptada,
 * y una cotización de otra empresa —o de otro asesor— no se puede enviar.
 *
 * El correo no sale a la red: se usa un PHPMailer cuyo send() solo cuenta
 * los envíos, y los documentos se leen de una carpeta temporal.
 */
class CotizacionCorreoTest extends TestCase
{
    private const EMPRESA_A = 1;
    private const CORREO_CLIENTE = 'cliente@correo.test';
    private const CORREO_ASESOR  = 'laura.asesora@lux.test';

    private \PDO $pdo;
    private string $raiz;
    private int $empresaB;
    private int $asesor;
    private int $otroAsesor;
    private int $cliente;
    private int $cotizacion;
    private int $cotizacionDeB;

    /** @var list<int> Filas creadas por la prueba, para borrarlas al terminar. */
    private array $agentes  = [];
    private array $clientes = [];

    /** Último mailer de prueba entregado al servicio. */
    private ?PHPMailer $mail = null;

    protected function setUp(): void
    {
        $this->pdo = \Database\Connection::getConnection();

        Tenant::reset();
        Tenant::set(self::EMPRESA_A);

        $this->asesor     = $this->insertarAgente('Laura', 'Méndez', self::CORREO_ASESOR, self::EMPRESA_A);
        $this->otroAsesor = $this->insertarAgente('Otro', 'Asesor', 'otro.asesor@lux.test', self::EMPRESA_A);
        $this->cliente    = $this->insertarCliente('Ana', 'López', self::CORREO_CLIENTE, self::EMPRESA_A);
        $this->cotizacion = $this->insertarCotizacion($this->cliente, $this->asesor, self::EMPRESA_A);

        $this->pdo->exec(
            "INSERT INTO empresas (nombre, slug, plan, max_usuarios, activo)
             VALUES ('Rival Correos', 'rival-correos-" . uniqid() . "', 'basico', 10, 1)"
        );
        $this->empresaB = (int) $this->pdo->lastInsertId();

        $asesorB             = $this->insertarAgente('Beto', 'Rival', 'beto@rival.test', $this->empresaB);
        $clienteB            = $this->insertarCliente('Carla', 'Rival', 'carla@rival.test', $this->empresaB);
        $this->cotizacionDeB = $this->insertarCotizacion($clienteB, $asesorB, $this->empresaB);

        $this->agentes  = [$this->asesor, $this->otroAsesor, $asesorB];
        $this->clientes = [$this->cliente, $clienteB];

        $this->raiz = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'slux_cot_correo_' . uniqid();
        mkdir($this->raiz . '/uploads/cotizaciones/' . $this->cotizacion, 0755, true);
    }

    protected function tearDown(): void
    {
        $cotizaciones = [$this->cotizacion, $this->cotizacionDeB];

        $this->pdo->prepare('DELETE FROM cotizacion_archivos WHERE cotizacion_id IN (?,?)')->execute($cotizaciones);
        $this->pdo->prepare('DELETE FROM cotizaciones WHERE id IN (?,?)')->execute($cotizaciones);
        $this->pdo->prepare('DELETE FROM historial_cliente WHERE cliente_id IN (?,?)')->execute($this->clientes);
        $this->pdo->prepare('DELETE FROM clientes WHERE id IN (?,?)')->execute($this->clientes);
        $this->pdo->prepare('DELETE FROM agentes WHERE id IN (?,?,?)')->execute($this->agentes);
        $this->pdo->prepare('DELETE FROM empresas WHERE id = ?')->execute([$this->empresaB]);

        $this->borrar($this->raiz);

        Tenant::reset();
        Tenant::set(self::EMPRESA_A);
    }

    // ── Fixtures ──────────────────────────────────────────────────────────────

    private function insertarAgente(string $nombre, string $apellido, string $correo, int $empresaId): int
    {
        $this->pdo->prepare(
            "INSERT INTO agentes (nombre, apellido, correo, contrasena, rol, empresa_id)
             VALUES (?, ?, ?, 'x', 'asesor', ?)"
        )->execute([$nombre, $apellido, $correo, $empresaId]);

        return (int) $this->pdo->lastInsertId();
    }

    private function insertarCliente(string $nombre, string $apellido, string $correo, int $empresaId): int
    {
        $this->pdo->prepare(
            "INSERT INTO clientes (nombre, apellido_paterno, correo, ramo, empresa_id)
             VALUES (?, ?, ?, 'autos', ?)"
        )->execute([$nombre, $apellido, $correo, $empresaId]);

        return (int) $this->pdo->lastInsertId();
    }

    private function insertarCotizacion(int $clienteId, int $agenteId, int $empresaId): int
    {
        $this->pdo->prepare(
            "INSERT INTO cotizaciones
                (cliente_id, agente_id, folio, ramo, estado, precio, tipo_cobertura, compania, vigencia, empresa_id)
             VALUES (?, ?, 'AUT-AMP-26-001-0001-N', 'autos', 'pendiente', 12345.6, 'Amplio', 'Qualitas', '12 meses', ?)"
        )->execute([$clienteId, $agenteId, $empresaId]);

        return (int) $this->pdo->lastInsertId();
    }

    private function insertarDocumento(string $tipo, string $nombreArchivo, ?string $contenido, ?string $eliminado = null): void
    {
        if ($contenido !== null) {
            file_put_contents("{$this->raiz}/uploads/cotizaciones/{$this->cotizacion}/{$nombreArchivo}", $contenido);
        }

        $this->pdo->prepare(
            'INSERT INTO cotizacion_archivos (cotizacion_id, tipo_documento, ruta, nombre_archivo, eliminado_en, empresa_id)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([
            $this->cotizacion, $tipo, "uploads/cotizaciones/{$this->cotizacion}/{$nombreArchivo}",
            $nombreArchivo, $eliminado, self::EMPRESA_A,
        ]);
    }

    private function borrar(string $ruta): void
    {
        if (is_dir($ruta)) {
            foreach (scandir($ruta) ?: [] as $f) {
                if ($f !== '.' && $f !== '..') {
                    $this->borrar($ruta . DIRECTORY_SEPARATOR . $f);
                }
            }
            rmdir($ruta);
        } elseif (is_file($ruta)) {
            unlink($ruta);
        }
    }

    /** PHPMailer que no sale a la red: cuenta los envíos, o falla como un SMTP caído. */
    private function mailerDePrueba(bool $falla = false): PHPMailer
    {
        $mail = new class(true) extends PHPMailer {
            public int $envios = 0;
            public bool $falla = false;

            public function send(): bool
            {
                if ($this->falla) {
                    throw new MailerException('SMTP Error: Could not connect to SMTP host.');
                }
                $this->envios++;

                return true;
            }
        };
        $mail->falla   = $falla;
        $mail->CharSet = 'UTF-8';
        $mail->isHTML(true);

        return $mail;
    }

    private function enviar(int $cotizacionId, int $agenteId, string $rol = Roles::ASESOR, bool $falla = false): array
    {
        $this->mail = $this->mailerDePrueba($falla);

        return CotizacionCorreo::enviarAlCliente(
            $cotizacionId, $agenteId, $rol,
            fn(): PHPMailer => $this->mail,
            new ArchivosCotizacion($this->raiz)
        );
    }

    private function estado(int $cotizacionId): string
    {
        $stmt = $this->pdo->prepare('SELECT estado FROM cotizaciones WHERE id = ?');
        $stmt->execute([$cotizacionId]);

        return (string) $stmt->fetchColumn();
    }

    private function entradasDeHistorial(int $clienteId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM historial_cliente WHERE cliente_id = ? AND titulo = 'Cotización enviada por correo al cliente'"
        );
        $stmt->execute([$clienteId]);

        return (int) $stmt->fetchColumn();
    }

    // ── Si el correo no sale, nada cambia ─────────────────────────────────────

    public function testSinSmtpNoSeEnviaNiCambiaElEstado(): void
    {
        $previas = [];
        foreach (['SMTP_HOST', 'SMTP_USER', 'SMTP_PASS'] as $var) {
            $previas[$var] = getenv($var);
            putenv($var);
        }

        try {
            // Sin $crearMailer: el camino de producción, MailService::buildMailer().
            $r = CotizacionCorreo::enviarAlCliente(
                $this->cotizacion, $this->asesor, Roles::ASESOR, null, new ArchivosCotizacion($this->raiz)
            );
        } finally {
            foreach ($previas as $var => $valor) {
                putenv($valor === false ? $var : "{$var}={$valor}");
            }
        }

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('SMTP', $r['msg']);
        $this->assertSame('pendiente', $this->estado($this->cotizacion), 'Se marcó como enviada sin que saliera el correo.');
        $this->assertSame(0, $this->entradasDeHistorial($this->cliente));
    }

    public function testSiElServidorDeCorreoFallaElEstadoNoCambia(): void
    {
        $r = $this->enviar($this->cotizacion, $this->asesor, Roles::ASESOR, falla: true);

        $this->assertFalse($r['ok']);
        $this->assertNotSame('', $r['msg']);
        $this->assertSame('pendiente', $r['estado']);
        $this->assertSame('pendiente', $this->estado($this->cotizacion), 'Se marcó como enviada sin que saliera el correo.');
        $this->assertSame(0, $this->entradasDeHistorial($this->cliente));
    }

    // ── Envío correcto ────────────────────────────────────────────────────────

    public function testElEnvioLlegaAlClienteYMarcaLaCotizacionComoEnviada(): void
    {
        $r = $this->enviar($this->cotizacion, $this->asesor);

        $this->assertTrue($r['ok'], $r['msg']);
        $this->assertStringStartsWith('Cotización enviada por correo a ' . self::CORREO_CLIENTE . '.', $r['msg']);
        $this->assertSame('enviada', $r['estado']);
        $this->assertSame('enviada', $this->estado($this->cotizacion));
        $this->assertSame(1, $this->mail->envios);

        $this->assertSame(self::CORREO_CLIENTE, $this->mail->getToAddresses()[0][0]);
        $this->assertArrayHasKey(self::CORREO_ASESOR, $this->mail->getReplyToAddresses(), 'Las respuestas del cliente no le llegan al asesor.');

        $this->assertStringContainsString('AUT-AMP-26-001-0001-N', $this->mail->Body);
        $this->assertStringContainsString('Qualitas', $this->mail->Body);
        $this->assertStringContainsString('Amplio', $this->mail->Body);
        $this->assertStringContainsString('$12,345.60 MXN', $this->mail->Body);
        $this->assertStringContainsString('12 meses', $this->mail->Body);
        $this->assertStringContainsString('Laura Méndez', $this->mail->Body);
        $this->assertStringContainsString('Qualitas', $this->mail->AltBody);

        $this->assertSame(1, $this->entradasDeHistorial($this->cliente), 'El envío no quedó en el historial del cliente.');
    }

    public function testElCuerpoEscapaLosDatosDeLaCotizacion(): void
    {
        $this->pdo->prepare('UPDATE cotizaciones SET compania = ? WHERE id = ?')
                  ->execute(['<img src=x onerror=alert(1)>', $this->cotizacion]);
        $this->pdo->prepare('UPDATE clientes SET nombre = ? WHERE id = ?')
                  ->execute(['Ana <b>"Negritas"</b>', $this->cliente]);

        $r = $this->enviar($this->cotizacion, $this->asesor);

        $this->assertTrue($r['ok'], $r['msg']);
        $this->assertStringNotContainsString('<img src=x', $this->mail->Body);
        $this->assertStringNotContainsString('<b>', $this->mail->Body);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $this->mail->Body);
        $this->assertStringContainsString('Ana &lt;b&gt;&quot;Negritas&quot;&lt;/b&gt;', $this->mail->Body);
    }

    public function testReenviarUnaCotizacionAceptadaNoLaRegresaAEnviada(): void
    {
        $this->pdo->prepare("UPDATE cotizaciones SET estado = 'aceptada' WHERE id = ?")->execute([$this->cotizacion]);

        $r = $this->enviar($this->cotizacion, $this->asesor);

        $this->assertTrue($r['ok'], $r['msg']);
        $this->assertSame('aceptada', $r['estado']);
        $this->assertSame('aceptada', $this->estado($this->cotizacion), 'Una cotización aceptada retrocedió a enviada.');
    }

    // ── Quién puede enviar qué ────────────────────────────────────────────────

    public function testUnaCotizacionDeOtraEmpresaNoSeEnvia(): void
    {
        // Incluso con un rol que ve todas las cotizaciones: todas las de SU empresa.
        $r = $this->enviar($this->cotizacionDeB, $this->asesor, Roles::COORDINADOR);

        $this->assertFalse($r['ok']);
        $this->assertSame('No encontrada.', $r['msg']);
        $this->assertSame(0, $this->mail->envios, 'Se envió por correo una cotización de otra empresa.');
        $this->assertSame('pendiente', $this->estado($this->cotizacionDeB));
    }

    public function testUnAsesorNoPuedeEnviarLaCotizacionDeOtro(): void
    {
        $r = $this->enviar($this->cotizacion, $this->otroAsesor, Roles::ASESOR);

        $this->assertFalse($r['ok']);
        $this->assertSame('No encontrada.', $r['msg']);
        $this->assertSame(0, $this->mail->envios);
        $this->assertSame('pendiente', $this->estado($this->cotizacion));
    }

    public function testQuienVeTodasLasCotizacionesPuedeEnviarlaYLaRespuestaVaAlAsesorDueno(): void
    {
        $r = $this->enviar($this->cotizacion, $this->otroAsesor, Roles::COORDINADOR);

        $this->assertTrue($r['ok'], $r['msg']);
        $this->assertSame('enviada', $this->estado($this->cotizacion));
        $this->assertArrayHasKey(self::CORREO_ASESOR, $this->mail->getReplyToAddresses());
    }

    public function testSinCorreoValidoDelClienteNoSeEnvia(): void
    {
        $this->pdo->prepare('UPDATE clientes SET correo = ? WHERE id = ?')->execute(['no-es-un-correo', $this->cliente]);

        $r = $this->enviar($this->cotizacion, $this->asesor);

        $this->assertFalse($r['ok']);
        $this->assertStringStartsWith('El cliente no tiene un correo válido', $r['msg']);
        $this->assertSame(0, $this->mail->envios);
        $this->assertSame('pendiente', $this->estado($this->cotizacion));
    }

    // ── Adjuntos ──────────────────────────────────────────────────────────────

    public function testAdjuntaSoloElDocumentoDeCotizacionVigente(): void
    {
        $this->insertarDocumento('doc_cotizacion', 'doc_cotizacion_2.pdf', '%PDF-vigente');
        $this->insertarDocumento('doc_cotizacion', 'doc_cotizacion_1.pdf', '%PDF-reemplazado', date('Y-m-d H:i:s'));
        $this->insertarDocumento('ine', 'ine_1.pdf', '%PDF-ine');

        $r = $this->enviar($this->cotizacion, $this->asesor);

        $this->assertTrue($r['ok'], $r['msg']);
        $this->assertSame('Cotización enviada por correo a ' . self::CORREO_CLIENTE . '.', $r['msg']);
        $this->assertSame(['doc_cotizacion_2.pdf'], $r['adjuntos']);
        $this->assertSame([], $r['omitidos']);

        $adjuntos = $this->mail->getAttachments();
        $this->assertCount(1, $adjuntos, 'Se adjuntó algo más que el documento de cotización vigente.');
        $this->assertSame('%PDF-vigente', $adjuntos[0][0]);
        $this->assertSame('doc_cotizacion_2.pdf', $adjuntos[0][1]);
        $this->assertSame('application/pdf', $adjuntos[0][4]);
        $this->assertStringContainsString('Adjuntamos el documento de la cotización', $this->mail->Body);
    }

    public function testSiElDocumentoNoSeEncuentraElCorreoSaleIgualYSeAvisa(): void
    {
        $this->insertarDocumento('doc_cotizacion', 'doc_cotizacion_perdido.pdf', null);

        $r = $this->enviar($this->cotizacion, $this->asesor);

        $this->assertTrue($r['ok'], $r['msg']);
        $this->assertSame(['doc_cotizacion_perdido.pdf'], $r['omitidos']);
        $this->assertStringContainsString('No se pudo adjuntar: doc_cotizacion_perdido.pdf', $r['msg']);
        $this->assertCount(0, $this->mail->getAttachments());
        $this->assertStringContainsString('por separado', $this->mail->Body);
        $this->assertStringContainsString('por separado', $this->mail->AltBody);
        $this->assertSame('enviada', $this->estado($this->cotizacion));
    }

    public function testUnDocumentoQuePasaDelLimiteNoSeAdjunta(): void
    {
        $nombre = 'doc_cotizacion_grande.pdf';
        $this->insertarDocumento('doc_cotizacion', $nombre, '');

        // Archivo disperso: ocupa el tamaño sin escribir 15 MB en la prueba.
        $f = fopen("{$this->raiz}/uploads/cotizaciones/{$this->cotizacion}/{$nombre}", 'r+b');
        ftruncate($f, CotizacionCorreo::LIMITE_ADJUNTOS + 1);
        fclose($f);

        $r = $this->enviar($this->cotizacion, $this->asesor);

        $this->assertTrue($r['ok'], $r['msg']);
        $this->assertSame([$nombre], $r['omitidos']);
        $this->assertStringContainsString('excede el tamaño máximo del correo', $r['msg']);
        $this->assertCount(0, $this->mail->getAttachments());
        $this->assertStringContainsString('por separado', $this->mail->Body);
    }
}
