<?php
declare(strict_types=1);

/**
 * App/Cron/WebhookKeepAlive.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Mantiene vivos los webhooks de WhatsApp y Telegram.
 * Corre cada 5 minutos vía cron en Hostgator:
 *
 *   HOSTGATOR CRON (cada 5 minutos):
 *   *\/5 * * * * /usr/local/bin/php /home2/grupoide/seguroslux.mx/App/Cron/WebhookKeepAlive.php >> /dev/null 2>&1
 *
 * Qué hace:
 *   1. Hace ping al propio servidor para evitar que PHP-FPM duerma.
 *   2. Verifica que el webhook de Telegram siga registrado (y lo re-registra si no).
 *   3. Verifica el estado del webhook de WhatsApp en Meta y loguea si hay errores.
 *   4. Limpia sesiones de bot antiguas (> 2 horas de inactividad).
 * ─────────────────────────────────────────────────────────────────────────────
 */

define('PROJECT_ROOT', dirname(__DIR__, 2));

if (file_exists(PROJECT_ROOT . '/.env.php')) {
    require_once PROJECT_ROOT . '/.env.php';
}

$logFile = PROJECT_ROOT . '/logs/webhook_keepalive.log';

function kaLog(string $msg, string $logFile): void
{
    $line = date('Y-m-d H:i:s') . ' [KeepAlive] ' . $msg . PHP_EOL;
    file_put_contents($logFile, $line, FILE_APPEND);
    echo $line;
}

function kaCurl(string $url, array $opts = []): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, array_replace([
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT      => 'SeguroLux-KeepAlive/1.0',
    ], $opts));
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    return ['body' => $body ?: '', 'code' => $code, 'error' => $err];
}

// ── 1. Ping al propio servidor ────────────────────────────────────────────────
// Hace una petición GET simple al dominio para que PHP-FPM no quede dormido.
$siteUrl = getenv('APP_URL') ?: 'https://seguroslux.mx';
$ping = kaCurl($siteUrl . '/health', [CURLOPT_TIMEOUT => 8]);
if ($ping['code'] === 200 || $ping['code'] === 404) {
    kaLog("Ping OK → {$siteUrl} (HTTP {$ping['code']})", $logFile);
} else {
    kaLog("Ping WARN → {$siteUrl} HTTP {$ping['code']} err={$ping['error']}", $logFile);
}

// ── 2. Verificar y re-registrar webhook de Telegram ──────────────────────────
$tgToken      = getenv('TELEGRAM_BOT_TOKEN') ?: '';
$tgWebhookUrl = getenv('TELEGRAM_WEBHOOK_URL') ?: 'https://seguroslux.mx/telegram/webhook';

if ($tgToken) {
    // Consultar estado actual del webhook
    $info = kaCurl("https://api.telegram.org/bot{$tgToken}/getWebhookInfo");
    $tgInfo = json_decode($info['body'], true);

    $currentUrl   = $tgInfo['result']['url']             ?? '';
    $pendingCount = $tgInfo['result']['pending_update_count'] ?? 0;
    $lastError    = $tgInfo['result']['last_error_message']   ?? '';
    $hasCustomCert = $tgInfo['result']['has_custom_certificate'] ?? false;

    kaLog(
        "Telegram webhook → url=[{$currentUrl}] pending={$pendingCount} lastError=[{$lastError}]",
        $logFile
    );

    // Re-registrar si la URL no coincide o si hay error reciente
    $needsReregister = ($currentUrl !== $tgWebhookUrl) || ($lastError !== '');

    if ($needsReregister) {
        kaLog("Telegram: re-registrando webhook → {$tgWebhookUrl}", $logFile);
        $reg = kaCurl("https://api.telegram.org/bot{$tgToken}/setWebhook", [
            CURLOPT_POST        => true,
            CURLOPT_POSTFIELDS  => json_encode(['url' => $tgWebhookUrl]),
            CURLOPT_HTTPHEADER  => ['Content-Type: application/json'],
        ]);
        $regResult = json_decode($reg['body'], true);
        $ok = $regResult['ok'] ?? false;
        kaLog('Telegram: setWebhook → ' . ($ok ? 'OK ✓' : 'FALLÓ: ' . ($regResult['description'] ?? $reg['body'])), $logFile);
    } else {
        kaLog('Telegram: webhook OK, no necesita re-registro', $logFile);
    }
} else {
    kaLog('Telegram: TELEGRAM_BOT_TOKEN no configurado, saltando.', $logFile);
}

// ── 3. Verificar estado del webhook de WhatsApp (Meta) ───────────────────────
// Meta no permite re-registrar vía API con token de sistema — solo desde el
// Developer Console. Pero sí podemos detectar si hay errores y alertar.
$waToken   = getenv('WHATSAPP_TOKEN')    ?: '';
$waPhoneId = getenv('WHATSAPP_PHONE_ID') ?: '';

if ($waToken && $waPhoneId) {
    $waInfo = kaCurl(
        "https://graph.facebook.com/v19.0/{$waPhoneId}/webhook_subscribed_apps",
        [
            CURLOPT_HTTPHEADER => ["Authorization: Bearer {$waToken}"],
        ]
    );
    kaLog("WhatsApp webhook check → HTTP {$waInfo['code']}: " . substr($waInfo['body'], 0, 300), $logFile);

    // Hacer también un ping al propio webhook endpoint de WA para que PHP no duerma
    $waPing = kaCurl('https://seguroslux.mx/whatsapp/webhook', [
        CURLOPT_TIMEOUT => 8,
    ]);
    kaLog("WhatsApp self-ping → HTTP {$waPing['code']}", $logFile);
} else {
    kaLog('WhatsApp: tokens no configurados, saltando.', $logFile);
}

// ── 4. Limpiar sesiones de bot antiguas (> 2 horas) ─────────────────────────
// Las sesiones se guardan en archivos JSON en App/Controllers/bot_sessions/
// Si un usuario abandona a la mitad de un flujo, la sesión queda "zombi".
$sessionsDir = PROJECT_ROOT . '/App/Controllers/bot_sessions';
$maxAgeSeconds = 2 * 3600; // 2 horas
$cleaned = 0;

if (is_dir($sessionsDir)) {
    foreach (glob($sessionsDir . '/*.json') as $file) {
        if (filemtime($file) < (time() - $maxAgeSeconds)) {
            unlink($file);
            $cleaned++;
        }
    }
    kaLog("Limpieza de sesiones: {$cleaned} sesión(es) expirada(s) eliminada(s).", $logFile);
} else {
    kaLog("Directorio de sesiones no existe aún: {$sessionsDir}", $logFile);
}

// ── 5. Rotar log si pesa más de 500 KB ───────────────────────────────────────
if (file_exists($logFile) && filesize($logFile) > 500 * 1024) {
    rename($logFile, $logFile . '.bak');
    kaLog('Log rotado (superó 500 KB).', $logFile);
}

kaLog('KeepAlive completado.', $logFile);