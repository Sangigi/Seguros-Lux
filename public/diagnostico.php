<?php
/**
 * public/meta/diagnostico.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Script de diagnóstico para el webhook de Meta Lead Ads.
 * SOLO PARA USO EN DESARROLLO — elimina o protege este archivo en producción.
 *
 * Accede a: https://seguroslux.mx/meta/diagnostico.php
 * (requiere acceso directo, no pasa por el router)
 * ─────────────────────────────────────────────────────────────────────────────
 */

// Protección mínima: solo desde la IP del servidor o con clave en querystring
$claveAcceso = 'diag_lux_2025'; // Cambia esto
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

// 1. Variables de entorno
echo "--- Variables de entorno ---\n";
$verifyToken = getenv('META_VERIFY_TOKEN') ?: MetaLeadsService::config('meta_verify_token');
$appSecret   = getenv('META_APP_SECRET')   ?: MetaLeadsService::config('meta_app_secret');
$pageToken   = getenv('META_PAGE_ACCESS_TOKEN') ?: MetaLeadsService::config('meta_page_access_token');

echo "META_VERIFY_TOKEN    : " . ($verifyToken ? '[SET - ' . strlen($verifyToken) . ' chars]' : '[VACÍO ⚠️]') . "\n";
echo "META_APP_SECRET      : " . ($appSecret   ? '[SET - ' . strlen($appSecret)   . ' chars]' : '[VACÍO ⚠️]') . "\n";
echo "META_PAGE_ACCESS_TOKEN: " . ($pageToken  ? '[SET - ' . strlen($pageToken)   . ' chars]' : '[VACÍO ⚠️]') . "\n";
echo "WEBHOOK_DEBUG_LOG    : " . (getenv('WEBHOOK_DEBUG_LOG') ?: '0') . "\n\n";

// 2. Permisos del archivo de log
$logFile = __DIR__ . '/meta.log';
echo "--- Archivo de log ---\n";
echo "Ruta: {$logFile}\n";
echo "Existe: " . (file_exists($logFile) ? 'Sí' : 'No') . "\n";
echo "Escribible: " . (is_writable(dirname($logFile)) ? 'Sí' : 'NO ⚠️') . "\n";
if (file_exists($logFile)) {
    echo "Últimas 20 líneas:\n";
    $lines = file($logFile);
    $last  = array_slice($lines, -20);
    echo implode('', $last) . "\n";
} else {
    echo "(log vacío o no creado aún)\n";
}
echo "\n";

// 3. Test de conexión a BD
echo "--- Conexión BD ---\n";
try {
    $db = \Database\Connection::getConnection();
    $version = $db->query('SELECT VERSION()')->fetchColumn();
    echo "Conexión: OK (MySQL {$version})\n";

    // Verificar tabla configuracion
    $claves = $db->query("SELECT clave, LEFT(valor,20) as preview FROM configuracion WHERE clave LIKE 'meta_%'")->fetchAll(\PDO::FETCH_ASSOC);
    echo "Claves meta_* en configuracion:\n";
    if (empty($claves)) {
        echo "  (ninguna encontrada — configura meta_verify_token, meta_app_secret, meta_page_access_token)\n";
    }
    foreach ($claves as $row) {
        echo "  {$row['clave']} = {$row['preview']}...\n";
    }

    // Verificar tabla meta_leads
    try {
        $count = $db->query('SELECT COUNT(*) FROM meta_leads')->fetchColumn();
        echo "\nmeta_leads: {$count} registro(s)\n";
        if ($count > 0) {
            $recientes = $db->query('SELECT * FROM meta_leads ORDER BY id DESC LIMIT 5')->fetchAll(\PDO::FETCH_ASSOC);
            echo "Últimos 5:\n";
            foreach ($recientes as $r) {
                echo "  [{$r['procesado_en']}] leadgen_id={$r['leadgen_id']} nombre={$r['nombre']}\n";
            }
        }
    } catch (\Throwable $e) {
        echo "Tabla meta_leads: no existe aún (se creará al recibir el primer lead)\n";
    }
} catch (\Throwable $e) {
    echo "ERROR BD: " . $e->getMessage() . "\n";
}

echo "\n--- Fin del diagnóstico ---\n";
echo "IMPORTANTE: elimina o protege este archivo antes de ir a producción.\n";
