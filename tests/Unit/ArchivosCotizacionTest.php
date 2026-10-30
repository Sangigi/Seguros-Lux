<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ArchivosCotizacion;
use Aws\CommandInterface;
use Aws\MockHandler;
use Aws\Result;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\TestCase;

/**
 * De dónde salen los bytes de un documento de cotización.
 *
 * Un archivo recién subido está en disco; a partir del día siguiente el cron
 * lo deja solo en iDrive. Aquí se prueba que se encuentre en los dos casos,
 * que la clave de iDrive sea la misma que usa el cron, que no se lea nada
 * fuera de `uploads/` y que el límite de tamaño se respete antes de bajar
 * el archivo. iDrive se simula con el MockHandler del SDK: nada sale a la red.
 */
class ArchivosCotizacionTest extends TestCase
{
    private string $raiz;

    protected function setUp(): void
    {
        $this->raiz = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'slux_archivos_' . uniqid();
        mkdir($this->raiz . '/uploads/cotizaciones/7', 0755, true);
    }

    protected function tearDown(): void
    {
        $this->borrar($this->raiz);
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

    private function fila(array $extra = []): array
    {
        return $extra + [
            'id'             => 70,
            'cotizacion_id'  => 7,
            'tipo_documento' => 'doc_cotizacion',
            'nombre_archivo' => 'doc_cotizacion_1700000000.pdf',
            'ruta'           => 'uploads/cotizaciones/7/doc_cotizacion_1700000000.pdf',
            'idrive_subido'  => null,
        ];
    }

    /**
     * Cliente S3 que responde con lo que se le encola y apunta qué se le pidió.
     *
     * @param list<callable|Result|\Throwable> $respuestas
     * @param list<array{0:string,1:string}>   $pedidos
     */
    private function s3(array $respuestas, ?array &$pedidos): S3Client
    {
        $pedidos = [];
        $mock    = new MockHandler();

        foreach ($respuestas as $respuesta) {
            $mock->append(function (CommandInterface $cmd) use ($respuesta, &$pedidos) {
                $pedidos[] = [$cmd->getName(), (string) $cmd['Key']];

                return is_callable($respuesta) ? $respuesta($cmd) : $respuesta;
            });
        }

        return new S3Client([
            'version'     => 'latest',
            'region'      => 'us-west-2',
            'credentials' => ['key' => 'prueba', 'secret' => 'prueba'],
            'handler'     => $mock,
        ]);
    }

    // ── Clave de iDrive ───────────────────────────────────────────────────────

    public function testLaClaveDeIdriveEsLaMismaQueArmaElCron(): void
    {
        $this->assertSame(
            'clientes/jose-nunez-perez/AUT-AMP-26-001-0001-N/documentos/doc_cotizacion_1.pdf',
            ArchivosCotizacion::claveIdrive('José Ñúñez Pérez', 'AUT-AMP-26-001-0001-N', 'doc_cotizacion', 'doc_cotizacion_1.pdf')
        );
    }

    public function testSinNombreNiFolioLaClaveUsaLosMismosValoresPorDefectoQueElCron(): void
    {
        $this->assertSame(
            'clientes/cliente/sin_folio/documentos/x.pdf',
            ArchivosCotizacion::claveIdrive('', '', 'doc_cotizacion', 'x.pdf')
        );
    }

    // ── Disco ─────────────────────────────────────────────────────────────────

    public function testLeeElArchivoQueSigueEnDisco(): void
    {
        file_put_contents($this->raiz . '/uploads/cotizaciones/7/doc_cotizacion_1700000000.pdf', '%PDF-local');

        $leido = (new ArchivosCotizacion($this->raiz))->leer($this->fila(), 'F-1', 'Ana López', 1024);

        $this->assertSame('%PDF-local', $leido['contenido']);
        $this->assertNull($leido['motivo']);
    }

    public function testSiLaRutaVieneVaciaLoBuscaEnLaCarpetaDeLaCotizacion(): void
    {
        file_put_contents($this->raiz . '/uploads/cotizaciones/7/doc_cotizacion_1700000000.pdf', '%PDF-local');

        $leido = (new ArchivosCotizacion($this->raiz))->leer($this->fila(['ruta' => '']), 'F-1', 'Ana López', 1024);

        $this->assertSame('%PDF-local', $leido['contenido']);
    }

    public function testNoLeeNadaFueraDeUploads(): void
    {
        file_put_contents($this->raiz . '/secreto.txt', 'no debe salir');

        $leido = (new ArchivosCotizacion($this->raiz))->leer(
            $this->fila(['ruta' => 'secreto.txt', 'nombre_archivo' => 'no-existe.pdf']),
            'F-1', 'Ana López', 1024
        );

        $this->assertNull($leido['contenido'], 'Se leyó un archivo fuera de uploads/.');
        $this->assertSame(ArchivosCotizacion::MOTIVO_NO_DISPONIBLE, $leido['motivo']);
    }

    public function testUnArchivoMasGrandeQueElCupoNoSeLee(): void
    {
        file_put_contents($this->raiz . '/uploads/cotizaciones/7/doc_cotizacion_1700000000.pdf', str_repeat('x', 100));

        $leido = (new ArchivosCotizacion($this->raiz))->leer($this->fila(), 'F-1', 'Ana López', 99);

        $this->assertNull($leido['contenido']);
        $this->assertSame(ArchivosCotizacion::MOTIVO_TAMANO, $leido['motivo']);
    }

    // ── iDrive ────────────────────────────────────────────────────────────────

    public function testSinCopiaLocalLoTraeDeIdriveConLaClaveDelCron(): void
    {
        $s3 = $this->s3([
            new Result(['ContentLength' => 11, 'Metadata' => ['cotizacion-id' => '7']]),
            new Result(['Body' => Utils::streamFor('%PDF-idrive')]),
        ], $pedidos);

        $leido = (new ArchivosCotizacion($this->raiz, $s3, 'bucket'))->leer(
            $this->fila(['idrive_subido' => '2026-10-01 02:00:00']),
            'AUT-AMP-26-001-0001-N', 'Ana López', 1024
        );

        $this->assertSame('%PDF-idrive', $leido['contenido']);

        $clave = 'clientes/ana-lopez/AUT-AMP-26-001-0001-N/documentos/doc_cotizacion_1700000000.pdf';
        $this->assertSame([['HeadObject', $clave], ['GetObject', $clave]], $pedidos);
    }

    public function testSiIdriveDiceQueEsDemasiadoGrandeNoLoDescarga(): void
    {
        $s3 = $this->s3([
            new Result(['ContentLength' => 5000, 'Metadata' => ['cotizacion-id' => '7']]),
        ], $pedidos);

        $leido = (new ArchivosCotizacion($this->raiz, $s3, 'bucket'))->leer(
            $this->fila(['idrive_subido' => '2026-10-01 02:00:00']), 'F-1', 'Ana López', 1024
        );

        $this->assertSame(ArchivosCotizacion::MOTIVO_TAMANO, $leido['motivo']);
        $this->assertCount(1, $pedidos, 'Se descargó un archivo que ya se sabía demasiado grande.');
    }

    /** La clave no lleva la empresa: si el objeto es de otra cotización, no se usa. */
    public function testNoUsaUnObjetoDeIdriveQueEsDeOtraCotizacion(): void
    {
        $s3 = $this->s3([
            new Result(['ContentLength' => 11, 'Metadata' => ['cotizacion-id' => '999']]),
        ], $pedidos);

        $leido = (new ArchivosCotizacion($this->raiz, $s3, 'bucket'))->leer(
            $this->fila(['idrive_subido' => '2026-10-01 02:00:00']), 'F-1', 'Ana López', 1024
        );

        $this->assertNull($leido['contenido']);
        $this->assertSame(ArchivosCotizacion::MOTIVO_NO_DISPONIBLE, $leido['motivo']);
        $this->assertCount(1, $pedidos, 'Se descargó el objeto de otra cotización.');
    }

    public function testSiNoEstaEnIdriveNoRompe(): void
    {
        $s3 = $this->s3([
            static fn(CommandInterface $cmd) => new S3Exception('No existe', $cmd, ['code' => 'NotFound']),
        ], $pedidos);

        $leido = (new ArchivosCotizacion($this->raiz, $s3, 'bucket'))->leer(
            $this->fila(['idrive_subido' => '2026-10-01 02:00:00']), 'F-1', 'Ana López', 1024
        );

        $this->assertNull($leido['contenido']);
        $this->assertSame(ArchivosCotizacion::MOTIVO_NO_DISPONIBLE, $leido['motivo']);
    }

    public function testSiElCronNoLoSubioNoLoBuscaEnIdrive(): void
    {
        $s3 = $this->s3([], $pedidos);

        $leido = (new ArchivosCotizacion($this->raiz, $s3, 'bucket'))->leer($this->fila(), 'F-1', 'Ana López', 1024);

        $this->assertSame(ArchivosCotizacion::MOTIVO_NO_DISPONIBLE, $leido['motivo']);
        $this->assertSame([], $pedidos);
    }
}
