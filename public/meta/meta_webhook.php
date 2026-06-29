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

// ── 1. Bootstrap: sólo si se llama directamente (sin pasar por index.php) ────
// Cuando index.php ya arrancó, ROOT y el autoloader ya están disponibles.
if (!defined('ROOT')) {
    // Llamada directa al archivo (raro, pero posible en debug).
    $metaProjectRoot = dirname(__DIR__, 2);
    if (is_file($metaProjectRoot . '/.env.php')) {
        require_once $metaProjectRoot . '/.env.php';
    }
    $metaAutoloader = $metaProjectRoot . '/vendor/autoload.php';
    if (file_exists($metaAutoloader)) {
        require_once $metaAutoloader;
    }
    if (file_exists($metaProjectRoot . '/Core/Autoloader.php')) {
        require_once $metaProjectRoot . '/Core/Autoloader.php';
    }
}

use App\Security\SecurityLogger;
use App\Services\MetaLeadsService;

// ── 2. Log de debug ───────────────────────────────────────────────────────────
// Actívalo temporalmente con WEBHOOK_DEBUG_LOG=1 en .env.php para diagnosticar.
$logFile   = __DIR__ . '/meta.log';
$debugLog  = filter_var(getenv('WEBHOOK_DEBUG_LOG') ?: '0', FILTER_VALIDATE_BOOLEAN);
$rawBody   = file_get_contents('php://input');

$metaLog = static function (string $message) use ($logFile, $debugLog): void {
    // Siempre loguear errores críticos; el flag solo controla el modo verbose.
    file_put_contents($logFile, date('Y-m-d H:i:s') . ' ' . $message . "\n", FILE_APPEND | LOCK_EX);
};

// Log verbose solo si está activado
$metaVerbose = static function (string $message) use ($metaLog, $debugLog): void {
    if ($debugLog) {
        $metaLog($message);
    }
};

$metaVerbose($_SERVER['REQUEST_METHOD'] . "\n" . $rawBody . "\n\n");

// ── 3. Verificación GET (registro del webhook en Meta) ────────────────────────
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

    $metaVerbose(
        "GET verification\n"
        . "  mode=[{$mode}] token=[{$token}] expected=[" . ($verifyToken !== '' ? '(set)' : '(empty)') . "]\n"
        . '  params: ' . json_encode($_GET) . "\n\n"
    );

    if ($verifyToken && $mode === 'subscribe' && $token === $verifyToken) {
        http_response_code(200);
        echo $challenge;
        exit;
    }

    $metaLog("GET RECHAZADO — verify_token vacío o no coincide. mode=[{$mode}]\n");
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

// ── 4. Solo aceptar POST a partir de aquí ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

// ── 5. Verificación de firma HMAC-SHA256 ─────────────────────────────────────
$appSecret = getenv('META_APP_SECRET') ?: ($_ENV['META_APP_SECRET'] ?? '');
if ($appSecret === '') {
    try {
        $appSecret = MetaLeadsService::config('meta_app_secret');
    } catch (\Throwable) {
        $appSecret = '';
    }
}

if ($appSecret === '') {
    $metaLog("ERROR CRÍTICO: meta_app_secret no está configurado — rechazando POST.\n");
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Configuración incompleta.']);
    exit;
}

$sigHeader = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
if (!str_starts_with($sigHeader, 'sha256=')) {
    $metaLog("HMAC: header X-Hub-Signature-256 ausente o malformado. IP=" . ($_SERVER['REMOTE_ADDR'] ?? '') . "\n");
    SecurityLogger::log('meta_hmac_missing', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$receivedHash = substr($sigHeader, 7);
$expectedHash = hash_hmac('sha256', $rawBody, $appSecret);

if (!hash_equals($expectedHash, $receivedHash)) {
    $metaLog("HMAC: firma inválida. IP=" . ($_SERVER['REMOTE_ADDR'] ?? '') . "\n");
    SecurityLogger::log('meta_hmac_invalid', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$metaVerbose("HMAC: firma válida.\n\n");

// ── 6. Decodificar payload y extraer leads ────────────────────────────────────
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

// Nada que procesar (ping de verificación de Meta) — responder 200.
if (empty($leadsAEncolar)) {
    $metaVerbose("POST recibido sin leads leadgen (posible ping). Payload: " . $rawBody . "\n");
    http_response_code(200);
    echo json_encode(['ok' => true]);
    exit;
}

$metaLog("POST recibido: " . count($leadsAEncolar) . " lead(s) a procesar.\n");

// ── 7. Responder 200 INMEDIATAMENTE ──────────────────────────────────────────
// Meta exige respuesta rápida (<20s). Procesamos los leads después de cerrar
// la conexión HTTP para no hacer esperar a Meta.
http_response_code(200);
echo json_encode(['ok' => true]);

if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    if (function_exists('flush')) {
        flush();
    }
}

// ── 8. Procesar cada lead recibido ───────────────────────────────────────────
foreach ($leadsAEncolar as [$leadgenId, $formId, $pageId]) {
    try {
        MetaLeadsService::procesarLead($leadgenId, $formId, $pageId);
        $metaLog("Lead procesado: leadgen_id={$leadgenId} form_id={$formId}\n");
    } catch (\Throwable $e) {
        $metaLog("ERROR procesando lead {$leadgenId}: " . $e->getMessage() . "\n");
    }
}

$metaLog('Fin. Procesados ' . count($leadsAEncolar) . " lead(s).\n\n");
exit;
