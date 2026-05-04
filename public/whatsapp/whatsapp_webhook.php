<?php
declare(strict_types=1);

/**
 * public/whatsapp/whatsapp_webhook.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Webhook receptor de WhatsApp Business Cloud API (Meta).
 * URL pública a registrar en Meta: https://seguroslux.mx/whatsapp/webhook
 * Eventos a suscribir en Meta Developer Console: messages
 *
 * Variables de entorno necesarias (.env.php):
 *   WHATSAPP_TOKEN        → Bearer token permanente (no de prueba)
 *   WHATSAPP_PHONE_ID     → Phone Number ID del número de negocios
 *   WHATSAPP_VERIFY_TOKEN → Token que tú eliges para verificar el webhook
 * ─────────────────────────────────────────────────────────────────────────────
 */

// ── 1. Raíz del proyecto ──────────────────────────────────────────────────────
define('WA_PROJECT_ROOT', dirname(__DIR__, 2));

$waEnvFile = WA_PROJECT_ROOT . '/.env.php';
if (is_file($waEnvFile)) {
    require_once $waEnvFile;
}

$logFile = __DIR__ . '/wa.log';
$rawBody = file_get_contents('php://input');

$waDebugLog = filter_var(getenv('WEBHOOK_DEBUG_LOG') ?: '0', FILTER_VALIDATE_BOOLEAN);

$waWebhookLog = static function (string $message) use ($logFile, $waDebugLog): void {
    if (!$waDebugLog) {
        return;
    }
    file_put_contents($logFile, date('Y-m-d H:i:s') . ' ' . $message . "\n", FILE_APPEND);
};

$waWebhookLog($_SERVER['REQUEST_METHOD'] . "\n" . $rawBody . "\n\n");

// ── 3. GET: Verificación del webhook (Meta lo llama una sola vez al registrar) ─
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $verifyToken = getenv('WHATSAPP_VERIFY_TOKEN') ?: ($_ENV['WHATSAPP_VERIFY_TOKEN'] ?? '');

    // PHP convierte hub.mode → hub_mode, hub.verify_token → hub_verify_token, etc.
    $mode      = $_GET['hub_mode']         ?? '';
    $token     = $_GET['hub_verify_token'] ?? '';
    $challenge = $_GET['hub_challenge']    ?? '';

    $waWebhookLog(
        "GET verification\n"
        . "  mode=[{$mode}] token=[{$token}] expected=[" . ($verifyToken !== '' ? '(set)' : '(empty)') . "]\n"
        . '  params: ' . json_encode($_GET) . "\n\n"
    );

    if ($verifyToken && $mode === 'subscribe' && $token === $verifyToken) {
        http_response_code(200);
        echo $challenge;
        exit;
    }

    http_response_code(403);
    echo 'Forbidden';
    exit;
}

// ── 4. Solo aceptar POST ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

// ── 5. Decodificar payload ────────────────────────────────────────────────────
$payload  = json_decode($rawBody, true);
$entry    = $payload['entry'][0]          ?? null;
$changes  = $entry['changes'][0]          ?? null;
$value    = $changes['value']             ?? null;
$messages = $value['messages']            ?? null;

// No es un mensaje (puede ser status update: delivered/read) — responder 200 y salir
if (!$messages || empty($messages)) {
    http_response_code(200);
    echo json_encode(['ok' => true]);
    exit;
}

// ── 6. Cargar dependencias ────────────────────────────────────────────────────
if (!is_file($waEnvFile)) {
    error_log('whatsapp_webhook: .env.php no encontrado en ' . WA_PROJECT_ROOT);
    $waWebhookLog("ERROR: .env.php no encontrado\n\n");
    http_response_code(200);
    echo json_encode(['ok' => true]);
    exit;
}

$autoloader = WA_PROJECT_ROOT . '/vendor/autoload.php';
if (file_exists($autoloader)) require_once $autoloader;
require_once WA_PROJECT_ROOT . '/Core/Autoloader.php';

// ── 7. Procesar mensaje con el bot ────────────────────────────────────────────
// $payload y $value quedan en scope para WhatsAppBot.php
require_once WA_PROJECT_ROOT . '/App/Controllers/WhatsAppBot.php';

// ── 8. Respuesta 200 obligatoria (Meta requiere respuesta en < 20 s) ──────────
http_response_code(200);
echo json_encode(['ok' => true]);
exit;