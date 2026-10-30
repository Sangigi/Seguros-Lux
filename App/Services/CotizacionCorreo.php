<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\HistorialModel;
use App\Security\Roles;
use App\Security\Tenant;
use Database\Connection;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Envía una cotización al cliente por correo.
 *
 * Antes, «Enviar al cliente» solo cambiaba el estado a 'enviada': el cliente
 * no recibía nada y el CRM decía lo contrario. Ahora el estado cambia
 * únicamente si el correo salió.
 *
 * Se envía en el momento y no por la cola, por lo mismo que las invitaciones:
 * el asesor está mirando la pantalla y tiene que saber si el correo salió.
 * (La cola, además, no lleva adjuntos.)
 *
 * El correo lleva un resumen de la cotización y, adjunto, el documento de
 * cotización que subió el asesor (`doc_cotizacion`), esté en disco o ya en
 * iDrive. Si un documento no se puede adjuntar —no se encuentra, o pasa del
 * límite— el correo sale igual, le dice al cliente que se lo harán llegar por
 * separado y la respuesta se lo avisa al asesor. Las respuestas del cliente
 * le llegan al asesor dueño de la cotización (Reply-To).
 *
 * Nunca lanza: devuelve siempre un resultado que la pantalla puede mostrar.
 */
final class CotizacionCorreo
{
    /**
     * Tope de lo que se adjunta. En base64 el correo crece un tercio: 15 MB
     * de archivos quedan por debajo de los 25 MB que aceptan casi todos los
     * servidores.
     */
    public const LIMITE_ADJUNTOS = 15 * 1024 * 1024;

    private const RAMOS = [
        'autos'          => 'Autos',
        'vida'           => 'Vida',
        'gastos_medicos' => 'Gastos médicos mayores',
        'empresas'       => 'Empresas',
    ];

    private const MOTIVOS = [
        ArchivosCotizacion::MOTIVO_TAMANO        => 'excede el tamaño máximo del correo',
        ArchivosCotizacion::MOTIVO_NO_DISPONIBLE => 'no se encontró el archivo',
    ];

    /**
     * Envía la cotización al correo de su cliente y, si sale, la marca como
     * enviada y lo anota en el historial del cliente.
     *
     * @param int                     $cotizacionId
     * @param int                     $agenteId    Quien envía (el de la sesión).
     * @param string                  $rol         Rol de quien envía.
     * @param callable|null           $crearMailer Devuelve el PHPMailer a usar; por defecto
     *                                             MailService::buildMailer(). Las pruebas pasan
     *                                             uno que no sale a la red.
     * @param ArchivosCotizacion|null $archivos    De dónde se leen los documentos.
     * @return array{ok: bool, msg: string, correo: string, estado: ?string, adjuntos: list<string>, omitidos: list<string>}
     */
    public static function enviarAlCliente(
        int $cotizacionId,
        int $agenteId,
        string $rol,
        ?callable $crearMailer = null,
        ?ArchivosCotizacion $archivos = null,
    ): array {
        $resultado = ['ok' => false, 'msg' => '', 'correo' => '', 'estado' => null, 'adjuntos' => [], 'omitidos' => []];

        try {
            $empresaId = Tenant::requireId();
            $db        = Connection::getConnection();
            $cot       = self::cargar($db, $cotizacionId, $empresaId);
        } catch (\Throwable $e) {
            error_log('[CotizacionCorreo] No se pudo cargar la cotización #' . $cotizacionId . ': ' . $e->getMessage());

            return ['msg' => 'No se pudo cargar la cotización.'] + $resultado;
        }

        // El dueño de la cotización, o quien ve todas las de la empresa. Para
        // los demás, la misma respuesta que si no existiera.
        $verTodas = Roles::tienePermiso($rol, Roles::COTIZACIONES_TODAS);

        if ($cot === null || (!$verTodas && (int) $cot['agente_id'] !== $agenteId)) {
            return ['msg' => 'No encontrada.'] + $resultado;
        }

        $resultado['estado'] = (string) $cot['estado'];
        $correo              = trim((string) ($cot['cliente_correo'] ?? ''));

        if (filter_var($correo, FILTER_VALIDATE_EMAIL) === false) {
            return ['msg' => 'El cliente no tiene un correo válido. Agrégalo en su ficha antes de enviar la cotización.'] + $resultado;
        }

        $resultado['correo'] = $correo;

        // El mailer antes que los documentos: sin SMTP no tiene caso bajarlos de iDrive.
        try {
            $mail = $crearMailer !== null ? $crearMailer() : MailService::buildMailer();

            if (!$mail instanceof PHPMailer) {
                throw new \UnexpectedValueException('La fábrica de correo no devolvió un PHPMailer.');
            }
        } catch (\Throwable $e) {
            error_log('[CotizacionCorreo] Correo sin configurar: ' . $e->getMessage());

            return ['msg' => 'El envío de correos no está configurado (SMTP). Avísale al administrador.'] + $resultado;
        }

        $docs  = self::documentos($db, $cot, $empresaId, $archivos ?? new ArchivosCotizacion());
        $envio = self::enviar($mail, $cot, $correo, $docs['adjuntos'], $docs['omitidos'] !== []);

        if (!$envio['ok']) {
            return ['msg' => $envio['error']] + $resultado;
        }

        $resultado['ok']       = true;
        $resultado['adjuntos'] = array_column($docs['adjuntos'], 'nombre');
        $resultado['omitidos'] = array_column($docs['omitidos'], 'nombre');

        // El correo ya salió: lo que falle de aquí en adelante se registra,
        // pero no se informa como fallo del envío.
        try {
            // Solo una cotización pendiente pasa a 'enviada'. Una aceptada,
            // rechazada o pagada no retrocede porque se le reenvíe al cliente.
            $stmt = $db->prepare(
                "UPDATE cotizaciones SET estado = 'enviada'
                 WHERE id = ? AND empresa_id = ? AND estado = 'pendiente'"
            );
            $stmt->execute([(int) $cot['id'], $empresaId]);

            if ($stmt->rowCount() > 0) {
                $resultado['estado'] = 'enviada';
                QueryCache::invalidate('cotizaciones');
            }
        } catch (\Throwable $e) {
            error_log('[CotizacionCorreo] Correo enviado, pero no se pudo marcar la cotización #' . $cot['id'] . ': ' . $e->getMessage());
        }

        try {
            (new HistorialModel())->registrar(
                (int) $cot['cliente_id'], $agenteId, 'cotizacion',
                'Cotización enviada por correo al cliente',
                self::notaHistorial($cot, $correo, $resultado['adjuntos'], $resultado['omitidos'])
            );
        } catch (\Throwable $e) {
            error_log('[CotizacionCorreo] Correo enviado, pero no se pudo anotar en el historial (cotización #' . $cot['id'] . '): ' . $e->getMessage());
        }

        $resultado['msg'] = self::mensaje($correo, $docs['adjuntos'], $docs['omitidos']);

        return $resultado;
    }

    // ── Datos ─────────────────────────────────────────────────────────────────

    /** La cotización con su cliente, su asesor y su empresa; solo si es de la empresa activa. */
    private static function cargar(\PDO $db, int $cotizacionId, int $empresaId): ?array
    {
        // `cliente_clave` es el nombre con el que el cron arma la carpeta del
        // cliente en iDrive; se calcula con la misma expresión para que coincida.
        $stmt = $db->prepare(
            "SELECT co.id, co.folio, co.ramo, co.compania, co.tipo_cobertura, co.precio,
                    co.vigencia, co.fecha_fin_vigencia, co.estado, co.agente_id, co.cliente_id,
                    cl.nombre AS cliente_nombre, cl.apellido_paterno AS cliente_apellido_paterno,
                    cl.apellido_materno AS cliente_apellido_materno, cl.correo AS cliente_correo,
                    CONCAT(cl.nombre,' ',cl.apellido_paterno) AS cliente_clave,
                    a.nombre AS asesor_nombre, a.apellido AS asesor_apellido, a.correo AS asesor_correo,
                    e.nombre AS empresa_nombre
             FROM cotizaciones co
             JOIN clientes cl     ON cl.id = co.cliente_id AND cl.empresa_id = co.empresa_id
             LEFT JOIN agentes a  ON a.id  = co.agente_id  AND a.empresa_id  = co.empresa_id
             LEFT JOIN empresas e ON e.id  = co.empresa_id
             WHERE co.id = ? AND co.empresa_id = ?
             LIMIT 1"
        );
        $stmt->execute([$cotizacionId, $empresaId]);

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Documentos de cotización vigentes, leídos hasta el límite.
     *
     * @return array{adjuntos: list<array{nombre: string, contenido: string}>, omitidos: list<array{nombre: string, motivo: string}>}
     */
    private static function documentos(\PDO $db, array $cot, int $empresaId, ArchivosCotizacion $archivos): array
    {
        $adjuntos = [];
        $omitidos = [];

        try {
            $stmt = $db->prepare(
                "SELECT ca.* FROM cotizacion_archivos ca
                 WHERE ca.cotizacion_id = ? AND ca.empresa_id = ?
                   AND ca.tipo_documento = 'doc_cotizacion' AND ca.eliminado_en IS NULL
                 ORDER BY ca.id ASC"
            );
            $stmt->execute([(int) $cot['id'], $empresaId]);
            $filas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('[CotizacionCorreo] No se pudieron listar los documentos de la cotización #' . $cot['id'] . ': ' . $e->getMessage());

            // Sin saber si hay documento, el correo avisa que llegará aparte.
            return ['adjuntos' => [], 'omitidos' => [['nombre' => 'Documento de cotización', 'motivo' => ArchivosCotizacion::MOTIVO_NO_DISPONIBLE]]];
        }

        $usado = 0;

        foreach ($filas as $fila) {
            $nombre = self::nombreAdjunto($fila);
            $leido  = $archivos->leer(
                $fila,
                (string) ($cot['folio'] ?? ''),
                (string) ($cot['cliente_clave'] ?? ''),
                self::LIMITE_ADJUNTOS - $usado
            );

            if ($leido['contenido'] === null) {
                $omitidos[] = ['nombre' => $nombre, 'motivo' => (string) $leido['motivo']];
                continue;
            }

            $usado     += strlen($leido['contenido']);
            $adjuntos[] = ['nombre' => $nombre, 'contenido' => $leido['contenido']];
        }

        return ['adjuntos' => $adjuntos, 'omitidos' => $omitidos];
    }

    /** Nombre con el que el cliente ve el adjunto: el original, sin rutas ni caracteres de control. */
    private static function nombreAdjunto(array $fila): string
    {
        $original = (string) ($fila['nombre_original'] ?? '');
        $interno  = (string) ($fila['nombre_archivo']  ?? '');

        $nombre = basename(str_replace('\\', '/', $original));
        if ($nombre === '' || pathinfo($nombre, PATHINFO_EXTENSION) === '') {
            $nombre = basename(str_replace('\\', '/', $interno));
        }

        $nombre = trim((string) preg_replace('/[\x00-\x1F\x7F"]+/u', '', $nombre));

        return $nombre !== '' ? $nombre : 'cotizacion.pdf';
    }

    // ── Envío ─────────────────────────────────────────────────────────────────

    /**
     * @param list<array{nombre: string, contenido: string}> $adjuntos
     * @return array{ok: bool, error: string}
     */
    private static function enviar(PHPMailer $mail, array $cot, string $correo, array $adjuntos, bool $faltanDocumentos): array
    {
        try {
            $d = self::datos($cot);

            $mail->addAddress($correo, $d['cliente']);

            if ($d['asesor_correo'] !== '') {
                $mail->addReplyTo($d['asesor_correo'], $d['asesor']);
            }

            $mail->Subject = 'Tu cotización de seguro' . ($d['folio'] !== '' ? ' ' . $d['folio'] : '') . ' — ' . $d['empresa'];
            $mail->Body    = self::cuerpoHtml($d, count($adjuntos), $faltanDocumentos);
            $mail->AltBody = self::cuerpoTexto($d, count($adjuntos), $faltanDocumentos);

            foreach ($adjuntos as $adjunto) {
                $mail->addStringAttachment(
                    $adjunto['contenido'],
                    $adjunto['nombre'],
                    PHPMailer::ENCODING_BASE64,
                    PHPMailer::filenameToType($adjunto['nombre'])
                );
            }

            if (!$mail->send()) {
                throw new \RuntimeException($mail->ErrorInfo ?: 'El servidor de correo no aceptó el mensaje.');
            }

            return ['ok' => true, 'error' => ''];
        } catch (\Throwable $e) {
            error_log('[CotizacionCorreo] No se pudo enviar la cotización #' . $cot['id'] . ': ' . $e->getMessage());

            return ['ok' => false, 'error' => 'No se pudo enviar el correo al cliente. Inténtalo de nuevo en unos minutos.'];
        }
    }

    /** Lo que se le muestra al cliente, ya en texto. Las filas vacías no se muestran. */
    private static function datos(array $cot): array
    {
        $txt = static fn(string $campo): string => trim((string) ($cot[$campo] ?? ''));

        $ramo     = $txt('ramo');
        $precio   = (float) ($cot['precio'] ?? 0);
        $vigencia = $txt('vigencia');
        $fin      = $txt('fecha_fin_vigencia');

        if ($fin !== '' && ($ts = strtotime($fin)) !== false) {
            $vigencia = trim($vigencia . ' (hasta el ' . date('d/m/Y', $ts) . ')');
        }

        $asesorCorreo = $txt('asesor_correo');

        return [
            'saludo'        => $txt('cliente_nombre'),
            'cliente'       => trim(implode(' ', array_filter([
                $txt('cliente_nombre'), $txt('cliente_apellido_paterno'), $txt('cliente_apellido_materno'),
            ]))),
            'asesor'        => trim($txt('asesor_nombre') . ' ' . $txt('asesor_apellido')),
            'asesor_correo' => filter_var($asesorCorreo, FILTER_VALIDATE_EMAIL) !== false ? $asesorCorreo : '',
            'empresa'       => $txt('empresa_nombre') ?: (string) (getenv('SMTP_FROM_NAME') ?: 'Seguros LUX'),
            'folio'         => $txt('folio'),
            'filas'         => array_filter([
                'Folio'       => $txt('folio'),
                'Ramo'        => $ramo !== '' ? (self::RAMOS[$ramo] ?? ucfirst(str_replace('_', ' ', $ramo))) : '',
                'Aseguradora' => $txt('compania'),
                'Cobertura'   => $txt('tipo_cobertura'),
                'Prima'       => $precio > 0 ? '$' . number_format($precio, 2) . ' MXN' : '',
                'Vigencia'    => $vigencia,
            ], static fn(string $v): bool => $v !== ''),
        ];
    }

    private static function avisoDocumento(int $adjuntos, bool $faltan): string
    {
        return match (true) {
            $adjuntos > 0 && $faltan => 'Adjuntamos parte de los documentos de la cotización; el resto te lo haremos llegar por separado.',
            $adjuntos > 1            => 'Adjuntamos los documentos de la cotización con el detalle completo.',
            $adjuntos === 1          => 'Adjuntamos el documento de la cotización con el detalle completo.',
            $faltan                  => 'El documento de la cotización te lo haremos llegar por separado.',
            default                  => '',
        };
    }

    private static function cuerpoHtml(array $d, int $adjuntos, bool $faltan): string
    {
        $h = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

        $saludo = $d['saludo'] !== '' ? 'Hola, ' . $h($d['saludo']) . ':' : 'Hola:';

        $filas = '';
        foreach ($d['filas'] as $etiqueta => $valor) {
            $filas .= '<tr>'
                . '<td style="padding:8px 14px;color:#64748b;font-size:13px;white-space:nowrap">' . $h((string) $etiqueta) . '</td>'
                . '<td style="padding:8px 14px;font-weight:bold">' . $h($valor) . '</td>'
                . '</tr>';
        }

        $aviso     = self::avisoDocumento($adjuntos, $faltan);
        $documento = $aviso !== '' ? '<p>' . $h($aviso) . '</p>' : '';

        $quien     = $d['asesor'] !== '' ? $h($d['asesor']) : 'tu asesor';
        $respuesta = $d['asesor_correo'] !== ''
            ? "Si tienes dudas, responde a este correo y te atenderá directamente {$quien}."
            : 'Si tienes dudas, responde a este correo.';

        $firma = ($d['asesor'] !== '' ? $h($d['asesor']) . '<br>' : '') . '<strong>' . $h($d['empresa']) . '</strong>';

        return <<<HTML
            <div style="font-family:Arial,Helvetica,sans-serif;max-width:560px;margin:0 auto;color:#0f172a">
              <p>{$saludo}</p>
              <p>Te compartimos la cotización de seguro que preparamos para ti.</p>
              <table style="border-collapse:collapse;width:100%;margin:20px 0;background:#f8fafc;border-radius:8px">
                {$filas}
              </table>
              {$documento}
              <p>{$respuesta}</p>
              <p style="margin-top:28px">{$firma}</p>
            </div>
            HTML;
    }

    private static function cuerpoTexto(array $d, int $adjuntos, bool $faltan): string
    {
        $saludo = $d['saludo'] !== '' ? "Hola, {$d['saludo']}:" : 'Hola:';

        $filas = '';
        foreach ($d['filas'] as $etiqueta => $valor) {
            $filas .= "{$etiqueta}: {$valor}\n";
        }

        $aviso     = self::avisoDocumento($adjuntos, $faltan);
        $quien     = $d['asesor'] !== '' ? $d['asesor'] : 'tu asesor';
        $respuesta = $d['asesor_correo'] !== ''
            ? "Si tienes dudas, responde a este correo y te atenderá directamente {$quien}."
            : 'Si tienes dudas, responde a este correo.';

        return "{$saludo}\n\n"
            . "Te compartimos la cotización de seguro que preparamos para ti.\n\n"
            . $filas . "\n"
            . ($aviso !== '' ? "{$aviso}\n\n" : '')
            . "{$respuesta}\n\n"
            . ($d['asesor'] !== '' ? "{$d['asesor']}\n" : '')
            . "{$d['empresa']}\n";
    }

    // ── Resultado ─────────────────────────────────────────────────────────────

    /**
     * @param list<array{nombre: string, contenido: string}> $adjuntos
     * @param list<array{nombre: string, motivo: string}>    $omitidos
     */
    private static function mensaje(string $correo, array $adjuntos, array $omitidos): string
    {
        $msg = "Cotización enviada por correo a {$correo}.";

        if ($omitidos !== []) {
            $detalle = array_map(
                static fn(array $o): string => $o['nombre'] . ' (' . (self::MOTIVOS[$o['motivo']] ?? 'no disponible') . ')',
                $omitidos
            );

            return $msg . ' No se pudo adjuntar: ' . implode(', ', $detalle)
                . '. El correo le avisa al cliente que lo recibirá por separado: compártelo por otro medio.';
        }

        if ($adjuntos === []) {
            return $msg . ' La cotización no tiene documento cargado, así que solo se envió el resumen.';
        }

        return $msg;
    }

    /**
     * @param list<string> $adjuntos
     * @param list<string> $omitidos
     */
    private static function notaHistorial(array $cot, string $correo, array $adjuntos, array $omitidos): string
    {
        $folio = trim((string) ($cot['folio'] ?? '')) ?: '#' . $cot['id'];
        $nota  = "Folio: {$folio} · Para: {$correo}";
        $nota .= $adjuntos !== [] ? ' · Adjuntos: ' . implode(', ', $adjuntos) : ' · Sin adjuntos';

        if ($omitidos !== []) {
            $nota .= ' · Sin adjuntar: ' . implode(', ', $omitidos);
        }

        return $nota;
    }
}
