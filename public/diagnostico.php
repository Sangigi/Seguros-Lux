<?php
$claveAcceso = 'diag_lux_2025';
if (($_GET['clave'] ?? '') !== $claveAcceso) {
    http_response_code(403);
    echo 'Acceso denegado. Usa: diagnostico.php?clave=diag_lux_2025';
    exit;
}

$projectRoot = dirname(__DIR__);
if (is_file($projectRoot . '/.env.php')) {
    require_once $projectRoot . '/.env.php';
}
if (file_exists($projectRoot . '/vendor/autoload.php')) {
    require_once $projectRoot . '/vendor/autoload.php';
}
if (file_exists($projectRoot . '/Core/Autoloader.php')) {
    require_once $projectRoot . '/Core/Autoloader.php';
}

use App\Services\MetaLeadsService;

header('Content-Type: text/plain; charset=utf-8');
echo "=== Diagnóstico Meta Lead Ads Webhook ===\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";

// ── 1. Variables ──────────────────────────────────────────────────────────────
echo "--- Variables de entorno ---\n";
$verifyToken = getenv('META_VERIFY_TOKEN') ?: MetaLeadsService::config('meta_verify_token');
$appSecret   = getenv('META_APP_SECRET')   ?: MetaLeadsService::config('meta_app_secret');
$pageToken   = getenv('META_PAGE_ACCESS_TOKEN') ?: MetaLeadsService::config('meta_page_access_token');
echo "META_VERIFY_TOKEN     : " . ($verifyToken ? '[SET - ' . strlen($verifyToken) . ' chars]' : '[VACÍO ⚠️]') . "\n";
echo "META_APP_SECRET       : " . ($appSecret   ? '[SET - ' . strlen($appSecret)   . ' chars]' : '[VACÍO ⚠️]') . "\n";
echo "META_PAGE_ACCESS_TOKEN: " . ($pageToken   ? '[SET - ' . strlen($pageToken)   . ' chars]' : '[VACÍO ⚠️]') . "\n";
echo "WEBHOOK_DEBUG_LOG     : " . (getenv('WEBHOOK_DEBUG_LOG') ?: '0') . "\n\n";

// ── 2. Log del webhook (está en public/meta/meta.log) ────────────────────────
echo "--- Log del webhook (public/meta/meta.log) ---\n";
$logFile = __DIR__ . '/meta/meta.log';
echo "Ruta: {$logFile}\n";
echo "Existe   : " . (file_exists($logFile) ? 'Sí' : 'No — nunca ha llegado nada al webhook ⚠️') . "\n";
echo "Escribible: " . (is_writable(dirname($logFile)) ? 'Sí' : 'NO ⚠️') . "\n";
if (file_exists($logFile)) {
    $lines = file($logFile);
    $last  = array_slice($lines, -30);
    echo "Últimas 30 líneas:\n";
    echo implode('', $last) . "\n";
} else {
    echo "(Meta aún no ha enviado ningún POST al webhook)\n";
}
echo "\n";

// ── 3. Test GET de verificación del webhook ───────────────────────────────────
echo "--- Test GET: verificación del webhook ---\n";
$webhookUrl = 'https://seguroslux.mx/meta/webhook';
$challenge  = 'test_challenge_' . time();
$testUrl    = $webhookUrl . '?hub_mode=subscribe&hub_verify_token=' . urlencode($verifyToken) . '&hub_challenge=' . $challenge;

$ch = curl_init($testUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_HEADER         => true,
]);
$resp     = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$body     = substr($resp, $headerSize);
curl_close($ch);

echo "URL probada: {$testUrl}\n";
echo "HTTP code  : {$httpCode}\n";
echo "Body       : " . trim($body) . "\n";
if ($httpCode === 200 && trim($body) === $challenge) {
    echo "Resultado  : ✅ CORRECTO — el webhook responde y el verify_token coincide\n";
} elseif ($httpCode === 403) {
    echo "Resultado  : ❌ 403 Forbidden — verify_token no coincide o el webhook no llega al PHP\n";
} elseif ($httpCode === 404) {
    echo "Resultado  : ❌ 404 — la ruta /meta/webhook no existe o el .htaccess no la está enrutando\n";
} else {
    echo "Resultado  : ⚠️ Respuesta inesperada\n";
}
echo "\n";

// ── 4. Test POST simulado (sin firma válida, para ver si llega) ───────────────
echo "--- Test POST: ¿llega el POST al webhook? ---\n";
$ch2 = curl_init($webhookUrl);
curl_setopt_array($ch2, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => '{"object":"page","entry":[]}',
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_HEADER         => true,
]);
$resp2     = curl_exec($ch2);
$httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
$headerSize2 = curl_getinfo($ch2, CURLINFO_HEADER_SIZE);
$body2     = substr($resp2, $headerSize2);
curl_close($ch2);

echo "HTTP code: {$httpCode2}\n";
echo "Body     : " . trim($body2) . "\n";
if ($httpCode2 === 403) {
    echo "Resultado: ✅ CORRECTO — llega al PHP y rechaza por firma inválida (comportamiento esperado)\n";
} elseif ($httpCode2 === 404) {
    echo "Resultado: ❌ 404 — el POST no llega al PHP. Problema de .htaccess o ruta\n";
} elseif ($httpCode2 === 500) {
    echo "Resultado: ❌ 500 — llega al PHP pero hay un error interno. Revisa error_log del servidor\n";
} else {
    echo "Resultado: ⚠️ HTTP {$httpCode2} — revisar\n";
}
echo "\n";

// ── 5. BD ─────────────────────────────────────────────────────────────────────
echo "--- Base de datos ---\n";
try {
    $db = \Database\Connection::getConnection();
    echo "Conexión: OK (MySQL " . $db->query('SELECT VERSION()')->fetchColumn() . ")\n";

    $claves = $db->query("SELECT clave, LEFT(valor,30) as preview FROM configuracion WHERE clave LIKE 'meta_%'")->fetchAll(\PDO::FETCH_ASSOC);
    echo "Claves meta_* en configuracion:\n";
    foreach ($claves as $row) {
        echo "  {$row['clave']} = {$row['preview']}...\n";
    }
    if (empty($claves)) echo "  (ninguna — tokens solo en .env.php)\n";

    try {
        $count = $db->query('SELECT COUNT(*) FROM meta_leads')->fetchColumn();
        echo "\nmeta_leads: {$count} registro(s)\n";
        if ($count > 0) {
            $recientes = $db->query('SELECT * FROM meta_leads ORDER BY id DESC LIMIT 5')->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($recientes as $r) {
                echo "  [{$r['procesado_en']}] leadgen_id={$r['leadgen_id']} nombre={$r['nombre']}\n";
            }
        }
    } catch (\Throwable $e) {
        echo "Tabla meta_leads: no existe aún\n";
    }
} catch (\Throwable $e) {
    echo "ERROR BD: " . $e->getMessage() . "\n";
}

echo "\n--- Fin del diagnóstico ---\n";
