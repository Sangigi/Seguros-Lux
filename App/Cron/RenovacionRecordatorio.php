<?php
/**
 * RenovacionRecordatorio.php
 * Avisa al asesor (Telegram o email) cuando un cliente tiene renovación próxima.
 * Lógica: busca clientes cuya primera venta fue hace 3, 6 o 12 meses,
 * con una ventana de 7 días de anticipación.
 *
 * HOSTGATOR CRON (una vez al día a las 9AM):
 *   0 9 * * * /usr/local/bin/php /home2/grupoide/public_html/src/Cron/RenovacionRecordatorio.php
 *
 * WINDOWS TASK SCHEDULER (desarrollo):
 *   Programa:   C:\xampp\php\php.exe
 *   Argumentos: C:\xampp\htdocs\apirest-dinamica\src\Cron\RenovacionRecordatorio.php
 *   Frecuencia: Una vez al día a las 09:00 AM
 */
declare(strict_types=1);

// ── Bootstrap ─────────────────────────────────────────────────────────────────
define('PROJECT_ROOT', dirname(__DIR__, 2));

if (file_exists(PROJECT_ROOT . '/.env.php')) {
    require_once PROJECT_ROOT . '/.env.php';
}

require_once PROJECT_ROOT . '/Core/Autoloader.php';

use Database\Connection;

// ── Config ────────────────────────────────────────────────────────────────────
$BOT_TOKEN         = getenv('TELEGRAM_BOT_TOKEN') ?: '';
$DIAS_ANTICIPACION = 7;

if ($BOT_TOKEN === '') {
    fwrite(STDERR, "[ERROR] Falta TELEGRAM_BOT_TOKEN en variables de entorno.\n");
    exit(1);
}

$logsDir = PROJECT_ROOT . '/logs';
if (!is_dir($logsDir)) mkdir($logsDir, 0755, true);
$logFile = $logsDir . '/renovaciones_' . date('Y-m') . '.log';

// ── Helpers ───────────────────────────────────────────────────────────────────
function log_msg_ren(string $msg, string $file): void
{
    $line = date('[Y-m-d H:i:s] ') . $msg . "\n";
    file_put_contents($file, $line, FILE_APPEND);
    // Sin echo: el cron no genera output y no dispara emails del sistema
}

function sendTelegramRen(string $chatId, string $text, string $token): bool
{
    $ch = curl_init("https://api.telegram.org/bot{$token}/sendMessage");
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode([
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => 'HTML',
        ]),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $res = json_decode(curl_exec($ch) ?: '{}', true);
    curl_close($ch);
    return $res['ok'] ?? false;
}

function sendEmailRen(string $to, string $subject, string $body): bool
{
    $headers = implode("\r\n", [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: CRM EIRA <no-reply@seguroslux.mx>',
    ]);
    return mail($to, $subject, $body, $headers);
}

// ── Query: clientes con renovación próxima ────────────────────────────────────
// Busca la primera cotización aceptada de cada cliente y calcula cuándo
// caen los hitos de 3, 6 y 12 meses. Si ese hito está en los próximos
// $DIAS_ANTICIPACION días, incluye el registro.
$db = Connection::getConnection();

$sql = "
    SELECT
        cl.id            AS cliente_id,
        cl.nombre,
        cl.apellido_paterno,
        cl.telefono,
        cl.correo,
        cl.agente_id,
        a.nombre         AS agente_nombre,
        a.apellido       AS agente_apellido,
        a.correo         AS agente_correo,
        a.telegram_chat_id AS agente_telegram,
        primera.fecha_primera_venta,
        hito.meses,
        DATE_ADD(primera.fecha_primera_venta, INTERVAL hito.meses MONTH) AS fecha_renovacion
    FROM clientes cl
    JOIN agentes a ON a.id = cl.agente_id
    JOIN (
        SELECT cliente_id, MIN(fecha_creacion) AS fecha_primera_venta
        FROM cotizaciones
        WHERE estado IN ('aceptada', 'pagado')
        GROUP BY cliente_id
    ) primera ON primera.cliente_id = cl.id
    -- Generar filas para los hitos 3, 6 y 12 meses
    JOIN (
        SELECT 3 AS meses UNION SELECT 6 UNION SELECT 12
    ) hito ON 1=1
    WHERE
        -- El hito cae exactamente dentro de la ventana de anticipación
        DATE_ADD(primera.fecha_primera_venta, INTERVAL hito.meses MONTH)
            BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :dias DAY)
        AND COALESCE(a.activo, 1) = 1
    ORDER BY fecha_renovacion ASC, cl.id ASC
";

$stmt = $db->prepare($sql);
$stmt->execute([':dias' => $DIAS_ANTICIPACION]);
$clientes = $stmt->fetchAll();

if (empty($clientes)) {
    log_msg_ren("Sin renovaciones próximas en los próximos {$DIAS_ANTICIPACION} días.", $logFile);
    exit(0);
}

log_msg_ren('=== RENOVACIONES PRÓXIMAS: ' . count($clientes) . ' clientes ===', $logFile);

$enviados = 0;
$errores  = 0;

foreach ($clientes as $cl) {
    $nombre        = htmlspecialchars($cl['nombre'] . ' ' . $cl['apellido_paterno']);
    $asesor        = htmlspecialchars($cl['agente_nombre'] . ' ' . $cl['agente_apellido']);
    $meses         = (int)$cl['meses'];
    $fechaRenov    = date('d/m/Y', strtotime($cl['fecha_renovacion']));
    $diasRestantes = (int)ceil((strtotime($cl['fecha_renovacion']) - time()) / 86400);

    $etiquetaMeses = match($meses) {
        3       => '3 meses',
        6       => '6 meses',
        12      => '1 año',
        default => "{$meses} meses",
    };

    // ── Mensaje Telegram para el asesor ───────────────────────────────────────
    $msgAsesor  = "🔔 <b>Recordatorio de Renovación</b>\n\n";
    $msgAsesor .= "Hola <b>{$asesor}</b>, el cliente <b>{$nombre}</b> ";
    $msgAsesor .= "tiene su renovación de <b>{$etiquetaMeses}</b> en <b>{$diasRestantes} días</b>.\n\n";
    $msgAsesor .= "📅 <b>Fecha:</b> {$fechaRenov}\n";
    if (!empty($cl['telefono'])) {
        $msgAsesor .= "📱 <b>Teléfono:</b> " . htmlspecialchars($cl['telefono']) . "\n";
    }
    if (!empty($cl['correo'])) {
        $msgAsesor .= "📧 <b>Correo:</b> " . htmlspecialchars($cl['correo']) . "\n";
    }
    $msgAsesor .= "\n¡Buen momento para contactarlo! 💼";

    $enviado = false;

    // Canal 1: Telegram al asesor
    if (!empty($cl['agente_telegram'])) {
        if (sendTelegramRen($cl['agente_telegram'], $msgAsesor, $BOT_TOKEN)) {
            $enviado = true;
            log_msg_ren(
                "✅ Telegram → {$asesor} | Cliente: {$nombre} | Renovación {$etiquetaMeses} el {$fechaRenov}",
                $logFile
            );
        }
    }

    // Canal 2: Email al asesor (si no hay Telegram o falló)
    if (!$enviado && !empty($cl['agente_correo'])) {
        $asunto = "Recordatorio: Renovación de {$nombre} en {$diasRestantes} días";
        $html   = "
        <div style='font-family:sans-serif;max-width:520px;'>
            <h2 style='color:#0ea5e9;'>🔔 Recordatorio de Renovación</h2>
            <p>Hola <strong>{$asesor}</strong>,</p>
            <p>El cliente <strong>{$nombre}</strong> tiene su renovación de
               <strong>{$etiquetaMeses}</strong> en <strong>{$diasRestantes} días</strong>.</p>
            <table style='width:100%;border-collapse:collapse;margin-top:16px;'>
                <tr><td style='padding:8px;background:#f8fafc;font-weight:bold;'>Fecha renovación:</td>
                    <td style='padding:8px;'>{$fechaRenov}</td></tr>
                <tr><td style='padding:8px;background:#f8fafc;font-weight:bold;'>Teléfono:</td>
                    <td style='padding:8px;'>" . htmlspecialchars($cl['telefono'] ?? '—') . "</td></tr>
                <tr><td style='padding:8px;background:#f8fafc;font-weight:bold;'>Correo cliente:</td>
                    <td style='padding:8px;'>" . htmlspecialchars($cl['correo'] ?? '—') . "</td></tr>
            </table>
            <p style='margin-top:20px;color:#666;font-size:12px;'>— CRM EIRA Seguros</p>
        </div>";

        if (sendEmailRen($cl['agente_correo'], $asunto, $html)) {
            $enviado = true;
            log_msg_ren(
                "✅ Email → {$cl['agente_correo']} | Cliente: {$nombre} | Renovación {$etiquetaMeses}",
                $logFile
            );
        }
    }

    if ($enviado) {
        $enviados++;
    } else {
        $errores++;
        log_msg_ren(
            "⚠️  Sin canal disponible para agente_id={$cl['agente_id']} cliente={$nombre}",
            $logFile
        );
    }
}

log_msg_ren("=== FIN: {$enviados} enviados, {$errores} sin canal ===", $logFile);