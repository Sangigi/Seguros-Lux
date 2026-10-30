<?php
declare(strict_types=1);

namespace App\Services;

use Aws\S3\S3Client;

/**
 * Contenido de un archivo de cotización (`cotizacion_archivos`), esté todavía
 * en el disco o ya solo en iDrive.
 *
 * Los archivos se guardan en `uploads/cotizaciones/{id}/`. El cron
 * SubirIdriveYBorrar los copia a iDrive E2 y, 24 h después, borra la copia
 * local. FileController (`/archivo`) solo sabe servir lo que sigue en disco,
 * y la pantalla de documentos resuelve lo demás con un enlace firmado para el
 * navegador. Quien necesita los bytes —por ejemplo, para adjuntarlos a un
 * correo— tiene que saber buscar en los dos sitios. Eso es lo que hace esta
 * clase.
 *
 * La clave en iDrive se calcula exactamente como en el cron:
 *   clientes/{slug-nombre-cliente}/{folio}/{carpeta}/{nombre_archivo}
 * Si el cron cambia la forma de la clave, hay que cambiarla aquí también.
 *
 * Esta clase no consulta la base de datos: quien la llama ya comprobó que la
 * cotización es de la empresa activa. Aun así, antes de descargar de iDrive
 * se compara la metadata `cotizacion-id` que pone el cron, porque la clave no
 * incluye la empresa y dos clientes homónimos con el mismo folio en empresas
 * distintas caerían en el mismo objeto.
 */
final class ArchivosCotizacion
{
    /** El archivo pasa del cupo que queda. */
    public const MOTIVO_TAMANO = 'tamano';

    /** No está en disco y no se pudo traer de iDrive. */
    public const MOTIVO_NO_DISPONIBLE = 'no_disponible';

    private string $raiz;
    private ?S3Client $s3;
    private bool $s3Resuelto;
    private string $bucket;

    /**
     * @param string|null   $raizProyecto Carpeta que contiene `uploads/`. Por defecto, la del proyecto.
     * @param S3Client|null $s3           Cliente de iDrive ya hecho. Por defecto se arma con las
     *                                    variables IDRIVE_* la primera vez que hace falta.
     */
    public function __construct(?string $raizProyecto = null, ?S3Client $s3 = null, ?string $bucket = null)
    {
        $this->raiz       = rtrim($raizProyecto ?? dirname(__DIR__, 2), '/\\');
        $this->s3         = $s3;
        $this->s3Resuelto = $s3 !== null;
        $this->bucket     = $bucket ?? (string) (getenv('IDRIVE_BUCKET') ?: 'crmseguros');
    }

    /**
     * Lee un archivo de cotización.
     *
     * @param array  $archivo       Fila de cotizacion_archivos.
     * @param string $folio         Folio de la cotización (forma parte de la clave en iDrive).
     * @param string $clienteNombre Nombre del cliente tal como lo arma el cron:
     *                              CONCAT(nombre,' ',apellido_paterno).
     * @param int    $maxBytes      Lo que queda de cupo. Un archivo más grande no se lee.
     * @return array{contenido: ?string, motivo: ?string}
     */
    public function leer(array $archivo, string $folio, string $clienteNombre, int $maxBytes): array
    {
        $local = $this->rutaLocal($archivo);

        if ($local !== null) {
            $tamano = filesize($local);

            if ($tamano === false || $tamano > $maxBytes) {
                return ['contenido' => null, 'motivo' => self::MOTIVO_TAMANO];
            }

            $contenido = file_get_contents($local);

            return $contenido === false
                ? ['contenido' => null, 'motivo' => self::MOTIVO_NO_DISPONIBLE]
                : ['contenido' => $contenido, 'motivo' => null];
        }

        // Sin copia local solo queda iDrive, y solo si el cron llegó a subirlo.
        if (empty($archivo['idrive_subido'])) {
            return ['contenido' => null, 'motivo' => self::MOTIVO_NO_DISPONIBLE];
        }

        return $this->leerDeIdrive($archivo, $folio, $clienteNombre, $maxBytes);
    }

    // ── Clave en iDrive (misma lógica que App/Cron/SubirIdriveYBorrar.php) ───

    public static function slugCliente(string $nombre): string
    {
        $slug = mb_strtolower($nombre, 'UTF-8');
        $slug = str_replace(
            ['á','é','í','ó','ú','ü','ñ','Á','É','Í','Ó','Ú','Ü','Ñ'],
            ['a','e','i','o','u','u','n','a','e','i','o','u','u','n'],
            $slug
        );
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);

        return trim((string) $slug, '-') ?: 'cliente';
    }

    public static function carpetaIdrive(string $tipo): string
    {
        return match ($tipo) {
            'poliza'           => 'polizas',
            'comprobante_pago' => 'pagos',
            default            => 'documentos',
        };
    }

    public static function claveIdrive(string $clienteNombre, string $folio, string $tipo, string $nombreArchivo): string
    {
        return 'clientes/' . self::slugCliente($clienteNombre !== '' ? $clienteNombre : 'cliente')
             . '/' . ($folio !== '' ? $folio : 'sin_folio')
             . '/' . self::carpetaIdrive($tipo)
             . '/' . $nombreArchivo;
    }

    // ── Internos ──────────────────────────────────────────────────────────────

    /**
     * Ruta en disco del archivo, solo si existe y queda dentro de `uploads/`.
     * Misma comprobación que FileController: la ruta viene de la base de
     * datos, pero no por eso se le permite salir de la carpeta de cargas.
     */
    private function rutaLocal(array $archivo): ?string
    {
        $uploads = realpath($this->raiz . DIRECTORY_SEPARATOR . 'uploads');

        if ($uploads === false) {
            return null;
        }

        $candidatos = [];

        $ruta = ltrim(str_replace('\\', '/', (string) ($archivo['ruta'] ?? '')), '/');
        if ($ruta !== '') {
            $candidatos[] = $this->raiz . '/' . $ruta;
        }

        // Respaldo: la carpeta donde se suben siempre, por si `ruta` vino vacía.
        $nombre = basename(str_replace('\\', '/', (string) ($archivo['nombre_archivo'] ?? '')));
        if ($nombre !== '') {
            $candidatos[] = $uploads . '/cotizaciones/' . (int) ($archivo['cotizacion_id'] ?? 0) . '/' . $nombre;
        }

        foreach ($candidatos as $candidato) {
            if (str_contains($candidato, '..')) {
                continue;
            }

            $real = realpath(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $candidato));

            if ($real !== false && str_starts_with($real, $uploads . DIRECTORY_SEPARATOR) && is_file($real)) {
                return $real;
            }
        }

        return null;
    }

    /** @return array{contenido: ?string, motivo: ?string} */
    private function leerDeIdrive(array $archivo, string $folio, string $clienteNombre, int $maxBytes): array
    {
        $noDisponible = ['contenido' => null, 'motivo' => self::MOTIVO_NO_DISPONIBLE];
        $nombre       = basename(str_replace('\\', '/', (string) ($archivo['nombre_archivo'] ?? '')));
        $s3           = $this->clienteS3();

        if ($s3 === null || $nombre === '') {
            return $noDisponible;
        }

        $clave = self::claveIdrive($clienteNombre, $folio, (string) ($archivo['tipo_documento'] ?? ''), $nombre);
        $id    = (int) ($archivo['id'] ?? 0);

        try {
            // Primero la cabecera: tamaño y dueño sin bajar el archivo.
            $cabecera = $s3->headObject(['Bucket' => $this->bucket, 'Key' => $clave]);

            $deCotizacion = $cabecera['Metadata']['cotizacion-id'] ?? null;
            if ($deCotizacion !== null && (int) $deCotizacion !== (int) ($archivo['cotizacion_id'] ?? 0)) {
                error_log("[ArchivosCotizacion] El objeto de iDrive del archivo #{$id} pertenece a otra cotización; no se usa.");

                return $noDisponible;
            }

            if ((int) ($cabecera['ContentLength'] ?? 0) > $maxBytes) {
                return ['contenido' => null, 'motivo' => self::MOTIVO_TAMANO];
            }

            $objeto    = $s3->getObject(['Bucket' => $this->bucket, 'Key' => $clave]);
            $contenido = (string) $objeto['Body'];
        } catch (\Throwable $e) {
            // Sin la clave en el log: lleva el nombre del cliente.
            error_log("[ArchivosCotizacion] No se pudo traer de iDrive el archivo #{$id}: " . $e->getMessage());

            return $noDisponible;
        }

        if (strlen($contenido) > $maxBytes) {
            return ['contenido' => null, 'motivo' => self::MOTIVO_TAMANO];
        }

        return ['contenido' => $contenido, 'motivo' => null];
    }

    /** Cliente de iDrive con la misma configuración que el cron, o null si no está configurado. */
    private function clienteS3(): ?S3Client
    {
        if ($this->s3Resuelto) {
            return $this->s3;
        }

        $this->s3Resuelto = true;

        $keyId     = (string) (getenv('IDRIVE_KEY_ID')     ?: '');
        $keySecret = (string) (getenv('IDRIVE_KEY_SECRET') ?: '');

        if ($keyId === '' || $keySecret === '' || !class_exists(S3Client::class)) {
            return null;
        }

        try {
            $this->s3 = new S3Client([
                'version'                 => 'latest',
                'region'                  => getenv('IDRIVE_REGION') ?: 'us-west-2',
                'endpoint'                => 'https://' . (getenv('IDRIVE_ENDPOINT') ?: 's3.us-west-2.idrivee2.com'),
                'use_path_style_endpoint' => true,
                'credentials'             => ['key' => $keyId, 'secret' => $keySecret],
                // Quien espera es una persona frente a la pantalla, no el cron.
                'http'                    => ['connect_timeout' => 5, 'timeout' => 20],
            ]);
        } catch (\Throwable $e) {
            error_log('[ArchivosCotizacion] No se pudo inicializar iDrive: ' . $e->getMessage());
            $this->s3 = null;
        }

        return $this->s3;
    }
}
