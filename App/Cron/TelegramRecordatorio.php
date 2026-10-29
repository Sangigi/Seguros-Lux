<?php
/**
 * TelegramRecordatorio.php
 * Envía recordatorios de Telegram a CLIENTES 1 hora antes de su cita.
 *
 * ESTRATEGIA DE EMPRESA: ITERA empresa por empresa (Tenant::comoEmpresa()).
 *   Manda mensajes a clientes y marca la cita como recordada, así que trabaja
 *   dentro de cada empresa: las citas que lee y el UPDATE que hace quedan
 *   acotados a ella, y un fallo en una empresa (API caída, datos sucios) no
 *   deja sin recordatorio a las demás.
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
use App\Security\Tenant;
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

// `empresas` es tabla de plataforma y NO está aislada: esta consulta no lleva
// filtro de empresa a propósito.
$empresas = Connection::getConnection()->query(
    'SELECT id, nombre FROM empresas
     WHERE activo = 1 AND eliminado_en IS NULL
     ORDER BY id'
)->fetchAll(\PDO::FETCH_ASSOC);

$enviados = 0;
$errores  = 0;
$omitidos = 0;

foreach ($empresas as $empresa) {
    $empresaId     = (int) $empresa['id'];
    $empresaNombre = (string) $empresa['nombre'];

    // Cada empresa en su propio try/catch: si una revienta, el resto sigue.
    try {
        Tenant::comoEmpresa($empresaId, function () use (
            $citaModel, $logFile, $BOT_TOKEN, $empresaId, $empresaNombre,
            &$enviados, &$errores, &$omitidos
        ): void {

            // Usa el método ya existente en CitaModel, ya filtrado por empresa.
            $citas = $citaModel->getCitasParaRecordatorio();

            if (empty($citas)) {
                return;
            }

            log_msg_tg(
                "=== Empresa #{$empresaId} ({$empresaNombre}) — RECORDATORIOS A ENVIAR: "
                . count($citas) . ' ===',
                $logFile
            );

            foreach ($citas as $cita) {
                // getCitasParaRecordatorio() también devuelve citas cuyo cliente
                // solo tiene WhatsApp o teléfono. Sin este filtro, el chat_id
                // nulo revienta sendTelegramMsg() y aborta el cron entero.
                $chatId = (string) ($cita['telegram_chat_id'] ?? '');
                if ($chatId === '') {
                    $omitidos++;
                    continue;
                }

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
                    // El UPDATE queda acotado a la empresa activa.
                    $citaModel->marcarRecordatorioEnviado((int)$cita['id']);
                    $enviados++;
                    log_msg_tg("✅ Enviado a {$cliente} (cita #{$cita['id']})", $logFile);
                } else {
                    $errores++;
                    log_msg_tg("❌ Error enviando a chat_id={$chatId} cita #{$cita['id']}", $logFile);
                }
            }
        });
    } catch (\Throwable $e) {
        $errores++;
        log_msg_tg(
            "❌ ERROR empresa #{$empresaId} ({$empresaNombre}): " . $e->getMessage()
            . ' @ ' . $e->getFile() . ':' . $e->getLine(),
            $logFile
        );
    }
}

if ($enviados === 0 && $errores === 0 && $omitidos === 0) {
    log_msg_tg('Sin citas para recordatorio en este ciclo.', $logFile);
    exit(0);
}

log_msg_tg("=== FIN: {$enviados} enviados, {$errores} errores, {$omitidos} sin chat de Telegram ===", $logFile);