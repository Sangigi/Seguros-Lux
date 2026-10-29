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
 *   WHATSAPP_APP_SECRET   → App Secret de la app en Meta Developer Console
 *                           (Dashboard → App → Settings → Basic → App Secret)
 *                           Necesario para verificar firma HMAC-SHA256 de Meta.
 * ─────────────────────────────────────────────────────────────────────────────
 */

// ── 1. Raíz del proyecto ──────────────────────────────────────────────────────
define('WA_PROJECT_ROOT', dirname(__DIR__, 2));

$waEnvFile = WA_PROJECT_ROOT . '/.env.php';
if (is_file($waEnvFile)) {
    require_once $waEnvFile;
}

// Cargar autoloader temprano para que SecurityLogger esté disponible
// desde el bloque de verificación HMAC (antes de procesar cualquier mensaje).
$_waAutoloaderEarly = WA_PROJECT_ROOT . '/vendor/autoload.php';
if (file_exists($_waAutoloaderEarly)) require_once $_waAutoloaderEarly;
require_once WA_PROJECT_ROOT . '/Core/Autoloader.php';

use App\Security\SecurityLogger;

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

// ── 2. Verificación de firma HMAC-SHA256 (solo en POST) ───────────────────────
// Meta firma CADA mensaje con HMAC-SHA256 del cuerpo usando APP_SECRET.
// Sin esta verificación, cualquiera puede enviar payloads falsos al bot.
// Referencia: https://developers.facebook.com/docs/messenger-platform/webhooks#validate-payloads
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appSecret = getenv('WHATSAPP_APP_SECRET') ?: ($_ENV['WHATSAPP_APP_SECRET'] ?? '');

    if ($appSecret === '') {
        error_log('whatsapp_webhook: WHATSAPP_APP_SECRET no está configurado — rechazando POST.');
        http_response_code(500);
        echo json_encode(['ok' => false, 'msg' => 'Configuración incompleta.']);
        exit;
    }

    $sigHeader = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
    // El header tiene el formato: "sha256=<hex_hash>"
    if (!str_starts_with($sigHeader, 'sha256=')) {
        $waWebhookLog("HMAC: header X-Hub-Signature-256 ausente o malformado.\n\n");
        SecurityLogger::log('wa_hmac_missing', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }

    $receivedHash  = substr($sigHeader, 7); // quitar "sha256="
    $expectedHash  = hash_hmac('sha256', $rawBody, $appSecret);

    if (!hash_equals($expectedHash, $receivedHash)) {
        $waWebhookLog("HMAC: firma inválida. Recibida={$receivedHash}\n\n");
        SecurityLogger::log('wa_hmac_invalid', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }

    $waWebhookLog("HMAC: firma válida.\n\n");
}
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

// ── 7. Resolver a qué empresa pertenece este número ───────────────────────────
// Meta identifica el número que recibió el mensaje con `phone_number_id`. Es
// lo único del payload que dice de quién es la conversación, así que de ahí
// sale la empresa. Si no se puede resolver, NO se procesa: un mensaje
// atribuido a la empresa equivocada es peor que un mensaje perdido, y crear el
// cliente "sin empresa" lo dejaría invisible para todos.
$waPhoneId = (string) ($value['metadata']['phone_number_id'] ?? '');
$waEmpresa = $waPhoneId !== ''
    ? (new App\Models\EmpresaModel())->porCanal('whatsapp', $waPhoneId)
    : null;

if ($waEmpresa === null) {
    error_log('whatsapp_webhook: phone_number_id sin empresa registrada: ' . $waPhoneId);
    $waWebhookLog("ERROR: phone_number_id [{$waPhoneId}] no está dado de alta en empresa_canales\n\n");

    // 200 igualmente: Meta reintenta ante cualquier otro código y acabaría
    // marcando el webhook como caído por un problema de configuración nuestro.
    http_response_code(200);
    echo json_encode(['ok' => true]);
    exit;
}

// ── 8. Procesar mensaje con el bot, dentro de su empresa ──────────────────────
// $payload y $value quedan en scope para WhatsAppBot.php
App\Security\Tenant::comoEmpresa($waEmpresa, static function () use ($payload, $value, $waPhoneId): void {
    // El bot responde por el número que recibió el mensaje, no por el del
    // .env: cada empresa tiene el suyo.
    $waPhoneIdEntrante = $waPhoneId;

    require_once WA_PROJECT_ROOT . '/App/Controllers/WhatsAppBot.php';
});

// ── 9. Respuesta 200 obligatoria (Meta requiere respuesta en < 20 s) ──────────
http_response_code(200);
echo json_encode(['ok' => true]);
exit;