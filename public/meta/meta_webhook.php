<?php
declare(strict_types=1);

/**
 * public/meta/meta_webhook.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Webhook receptor de Meta Lead Ads (Facebook/Instagram).
 * URL pública a registrar en Meta: https://seguroslux.mx/meta/webhook
 * Objeto a suscribir en Meta Developer Console: page → leadgen
 *
 * Variables de entorno necesarias (.env.php) — opcional, también se puede
 * guardar en la tabla `configuracion` desde el panel de admin existente:
 *   META_VERIFY_TOKEN → Token que tú eliges para verificar el webhook (GET).
 *   META_APP_SECRET   → App Secret de la app en Meta Developer Console
 *                        (Dashboard → App → Settings → Basic → App Secret).
 *                        Necesario para verificar la firma HMAC-SHA256.
 *
 * El token de acceso de la página (META_PAGE_ACCESS_TOKEN) y el mapeo de
 * formularios a ramo se gestionan desde la tabla `configuracion` —
 * mismo patrón que wa_token/wa_pid — para poder rotarlos sin tocar código.
 *
 * FLUJO META:
 *   1. GET  → Meta verifica la URL del webhook (hub.mode=subscribe).
 *   2. POST → Meta avisa "llegó un lead nuevo", pero NO manda los datos del
 *             formulario, solo un leadgen_id. Hay que ir a buscarlos a
 *             Graph API (lo hace MetaLeadsService::procesarLead).
 *   3. Hay que responder 200 en menos de 20s o Meta reintenta/desactiva.
 * ─────────────────────────────────────────────────────────────────────────────
 */

// ── 1. Raíz del proyecto ──────────────────────────────────────────────────────
define('META_PROJECT_ROOT', dirname(__DIR__, 2));

$metaEnvFile = META_PROJECT_ROOT . '/.env.php';
if (is_file($metaEnvFile)) {
    require_once $metaEnvFile;
}

$_metaAutoloaderEarly = META_PROJECT_ROOT . '/vendor/autoload.php';
if (file_exists($_metaAutoloaderEarly)) require_once $_metaAutoloaderEarly;
require_once META_PROJECT_ROOT . '/Core/Autoloader.php';

use App\Security\SecurityLogger;
use App\Services\MetaLeadsService;
use Database\Connection;

$logFile = __DIR__ . '/meta.log';
$rawBody = file_get_contents('php://input');

$metaDebugLog = filter_var(getenv('WEBHOOK_DEBUG_LOG') ?: '0', FILTER_VALIDATE_BOOLEAN);

$metaWebhookLog = static function (string $message) use ($logFile, $metaDebugLog): void {
    if (!$metaDebugLog) {
        return;
    }
    file_put_contents($logFile, date('Y-m-d H:i:s') . ' ' . $message . "\n", FILE_APPEND);
};

$metaWebhookLog($_SERVER['REQUEST_METHOD'] . "\n" . $rawBody . "\n\n");

// ── 2. Verificación GET (registro del webhook en Meta) ───────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $verifyToken = getenv('META_VERIFY_TOKEN') ?: ($_ENV['META_VERIFY_TOKEN'] ?? '');
    if ($verifyToken === '') {
        try {
            $verifyToken = MetaLeadsService::config('meta_verify_token');
        } catch (\Throwable) {
            $verifyToken = '';
        }
    }

    $mode      = $_GET['hub_mode']         ?? '';
    $token     = $_GET['hub_verify_token'] ?? '';
    $challenge = $_GET['hub_challenge']    ?? '';

    $metaWebhookLog(
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

// ── 3. Solo aceptar POST a partir de aquí ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

// ── 4. Verificación de firma HMAC-SHA256 ──────────────────────────────────────
// Meta firma cada notificación con HMAC-SHA256 del cuerpo usando el App Secret.
// Referencia: https://developers.facebook.com/docs/graph-api/webhooks/getting-started#validate-payloads
$appSecret = getenv('META_APP_SECRET') ?: ($_ENV['META_APP_SECRET'] ?? '');
if ($appSecret === '') {
    try {
        $appSecret = MetaLeadsService::config('meta_app_secret');
    } catch (\Throwable) {
        $appSecret = '';
    }
}

if ($appSecret === '') {
    error_log('meta_webhook: META_APP_SECRET no está configurado — rechazando POST.');
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Configuración incompleta.']);
    exit;
}

$sigHeader = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
if (!str_starts_with($sigHeader, 'sha256=')) {
    $metaWebhookLog("HMAC: header X-Hub-Signature-256 ausente o malformado.\n\n");
    SecurityLogger::log('meta_hmac_missing', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$receivedHash = substr($sigHeader, 7); // quitar "sha256="
$expectedHash = hash_hmac('sha256', $rawBody, $appSecret);

if (!hash_equals($expectedHash, $receivedHash)) {
    $metaWebhookLog("HMAC: firma inválida. Recibida={$receivedHash}\n\n");
    SecurityLogger::log('meta_hmac_invalid', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$metaWebhookLog("HMAC: firma válida.\n\n");

// ── 5. Decodificar payload y extraer leads ────────────────────────────────────
// Formato típico:
// { "object": "page", "entry": [{ "id": "<page_id>",
//     "changes": [{ "field": "leadgen",
//       "value": { "leadgen_id": "...", "form_id": "...", "page_id": "..." }
//     }] }] }
$payload = json_decode($rawBody, true);
$entries = $payload['entry'] ?? [];

$leadsAEncolar = [];
foreach ($entries as $entry) {
    $pageId = (string) ($entry['id'] ?? '');
    foreach (($entry['changes'] ?? []) as $change) {
        if (($change['field'] ?? '') !== 'leadgen') {
            continue;
        }
        $value     = $change['value'] ?? [];
        $leadgenId = (string) ($value['leadgen_id'] ?? '');
        $formId    = (string) ($value['form_id']    ?? '');
        if ($leadgenId !== '') {
            $leadsAEncolar[] = [$leadgenId, $formId, $pageId];
        }
    }
}

// Nada que procesar (puede ser un ping de verificación de Meta) — responder 200.
if (empty($leadsAEncolar)) {
    http_response_code(200);
    echo json_encode(['ok' => true]);
    exit;
}

// ── 6. Responder 200 INMEDIATAMENTE ───────────────────────────────────────────
// Meta exige respuesta rápida (<20s). Procesamos el/los lead(s) después de
// cerrar la conexión HTTP para no hacer esperar a Meta mientras llamamos a
// Graph API, escribimos en BD y enviamos email/WhatsApp.
http_response_code(200);
echo json_encode(['ok' => true]);

if (function_exists('fastcgi_finish_request')) {
    // Envía la respuesta ya mismo y sigue ejecutando en background (PHP-FPM).
    fastcgi_finish_request();
} else {
    // Sin FPM no podemos cerrar la conexión antes; igualmente seguimos,
    // el navegador/Meta ya recibió el código 200 en el buffer de salida.
    if (function_exists('flush')) {
        flush();
    }
}

// ── 7. Procesar cada lead recibido ────────────────────────────────────────────
foreach ($leadsAEncolar as [$leadgenId, $formId, $pageId]) {
    MetaLeadsService::procesarLead($leadgenId, $formId, $pageId);
}

$metaWebhookLog('Procesados ' . count($leadsAEncolar) . " lead(s).\n\n");
exit;
