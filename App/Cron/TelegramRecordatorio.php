<?php
/**
 * TelegramRecordatorio.php
 * Envía recordatorios de Telegram a CLIENTES 1 hora antes de su cita.
 *
 * HOSTGATOR CRON (cada 5 minutos):
 *   * /5 * * * * /usr/local/bin/php /home2/grupoide/public_html/src/Cron/TelegramRecordatorio.php
 *
 * WINDOWS TASK SCHEDULER (desarrollo):
 *   Programa:   C:\xampp\php\php.exe
 *   Argumentos: C:\xampp\htdocs\apirest-dinamica\src\Cron\TelegramRecordatorio.php
 *   Frecuencia: Cada 5 minutos
 */
declare(strict_types=1);

// ── Bootstrap ─────────────────────────────────────────────────────────────────
define('PROJECT_ROOT', dirname(__DIR__, 2));

if (file_exists(PROJECT_ROOT . '/.env.php')) {
    require_once PROJECT_ROOT . '/.env.php';
}

require_once PROJECT_ROOT . '/Core/Autoloader.php';

use App\Models\CitaModel;
use Database\Connection;

// ── Config ────────────────────────────────────────────────────────────────────
$BOT_TOKEN = getenv('TELEGRAM_BOT_TOKEN') ?: '';

if ($BOT_TOKEN === '') {
    fwrite(STDERR, "[ERROR] Falta TELEGRAM_BOT_TOKEN en variables de entorno.\n");
    exit(1);
}

$logsDir = PROJECT_ROOT . '/logs';
if (!is_dir($logsDir)) mkdir($logsDir, 0755, true);
$logFile = $logsDir . '/telegram_' . date('Y-m') . '.log';

// ── Helpers ───────────────────────────────────────────────────────────────────
function log_msg_tg(string $msg, string $file): void
{
    $line = date('[Y-m-d H:i:s] ') . $msg . "\n";
    file_put_contents($file, $line, FILE_APPEND);
    // Sin echo: el cron no genera output y no dispara emails del sistema
}

function sendTelegramMsg(string $chatId, string $text, string $token): bool
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

function formatFechaTg(string $fecha): string
{
    $meses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
              'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    [$y, $m, $d] = explode('-', $fecha);
    return (int)$d . ' de ' . $meses[(int)$m] . ' ' . $y;
}

// ── Lógica principal ──────────────────────────────────────────────────────────
$citaModel = new CitaModel();

// Usa el método ya existente en CitaModel
$citas = $citaModel->getCitasParaRecordatorio();

if (empty($citas)) {
    log_msg_tg('Sin citas para recordatorio en este ciclo.', $logFile);
    exit(0);
}

log_msg_tg('=== RECORDATORIOS A ENVIAR: ' . count($citas) . ' ===', $logFile);

$enviados = 0;
$errores  = 0;

foreach ($citas as $cita) {
    $chatId  = $cita['telegram_chat_id'];
    $hora    = substr($cita['hora'], 0, 5);
    $fecha   = formatFechaTg($cita['fecha']);
    $cliente = htmlspecialchars($cita['cliente_nombre']);
    $agente  = htmlspecialchars($cita['agente_nombre']);
    $tipo    = ucfirst($cita['tipo'] ?? 'llamada');

    // Minutos restantes
    $ts   = strtotime($cita['fecha'] . ' ' . $cita['hora']);
    $mins = max(1, (int)round(($ts - time()) / 60));

    $msg  = "⏰ <b>Recordatorio de Cita</b>\n\n";
    $msg .= "Hola <b>{$cliente}</b>, tu cita es en <b>{$mins} minutos</b>.\n\n";
    $msg .= "📅 <b>Fecha:</b> {$fecha}\n";
    $msg .= "🕐 <b>Hora:</b> {$hora} hrs\n";
    $msg .= "📋 <b>Tipo:</b> {$tipo}\n";
    $msg .= "👤 <b>Asesor:</b> {$agente}\n\n";
    $msg .= "¡Te esperamos! 🚀";

    if (sendTelegramMsg($chatId, $msg, $BOT_TOKEN)) {
        $citaModel->marcarRecordatorioEnviado((int)$cita['id']);
        $enviados++;
        log_msg_tg("✅ Enviado a {$cliente} (cita #{$cita['id']})", $logFile);
    } else {
        $errores++;
        log_msg_tg("❌ Error enviando a chat_id={$chatId} cita #{$cita['id']}", $logFile);
    }
}

log_msg_tg("=== FIN: {$enviados} enviados, {$errores} errores ===", $logFile);