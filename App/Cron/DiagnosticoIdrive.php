<?php

declare(strict_types=1);

use Aws\S3\S3Client;
use Database\Connection;

define('PROJECT_ROOT', dirname(__DIR__, 2));
define('UPLOADS_ROOT', PROJECT_ROOT . '/uploads');

echo "<pre>\n";
echo 'PROJECT_ROOT: ' . PROJECT_ROOT . "\n";
echo 'UPLOADS_ROOT: ' . UPLOADS_ROOT . "\n\n";

$archivos = [
    PROJECT_ROOT . '/.env.php',
    PROJECT_ROOT . '/Core/Autoloader.php',
    PROJECT_ROOT . '/vendor/autoload.php',
];
foreach ($archivos as $f) {
    echo (file_exists($f) ? 'OK ' : 'NO ') . $f . "\n";
}

echo "\n--- Cargando dependencias ---\n";
try {
    if (file_exists(PROJECT_ROOT . '/.env.php')) {
        require_once PROJECT_ROOT . '/.env.php';
        echo "OK .env.php cargado\n";
    }
    require_once PROJECT_ROOT . '/Core/Autoloader.php';
    echo "OK Autoloader cargado\n";
    require_once PROJECT_ROOT . '/vendor/autoload.php';
    echo "OK vendor/autoload.php cargado\n";
} catch (Throwable $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
    exit;
}

echo "\n--- Credenciales iDrive ---\n";
$endpoint  = getenv('IDRIVE_ENDPOINT') ?: 's3.us-west-2.idrivee2.com';
$bucket    = getenv('IDRIVE_BUCKET') ?: 'crmseguros';
$region    = getenv('IDRIVE_REGION') ?: 'us-west-2';
$keyId     = getenv('IDRIVE_KEY_ID') ?: '';
$keySecret = getenv('IDRIVE_KEY_SECRET') ?: '';

if ($keyId === '' || $keySecret === '') {
    echo "ERROR: Definir IDRIVE_KEY_ID e IDRIVE_KEY_SECRET en el entorno (.env.php).\n";
    echo "</pre>";
    exit;
}

echo "Endpoint : {$endpoint}\n";
echo "Bucket   : {$bucket}\n";
echo 'Key ID   : ' . substr($keyId, 0, 4) . "…\n";
echo 'Secret   : ' . substr($keySecret, 0, 4) . "…\n";

echo "\n--- Conexión a iDrive E2 ---\n";
try {
    $s3 = new S3Client([
        'version'                 => 'latest',
        'region'                  => $region,
        'endpoint'                => "https://{$endpoint}",
        'use_path_style_endpoint' => true,
        'credentials'             => ['key' => $keyId, 'secret' => $keySecret],
    ]);
    $result = $s3->listBuckets();
    echo 'OK Conexión. Buckets: ' . count($result['Buckets']) . "\n";
    foreach ($result['Buckets'] as $b) {
        echo '   - ' . $b['Name'] . "\n";
    }
} catch (Throwable $e) {
    echo 'Error S3: ' . $e->getMessage() . "\n";
}

echo "\n--- Carpeta uploads ---\n";
echo (is_dir(UPLOADS_ROOT) ? 'OK ' : 'NO ') . "uploads/ existe\n";
echo (is_dir(UPLOADS_ROOT . '/cotizaciones') ? 'OK ' : 'NO ') . "uploads/cotizaciones/ existe\n";

echo "\n--- Primer archivo pendiente ---\n";
$db = Connection::getConnection();
$row = $db->query(
    "SELECT ca.id, ca.cotizacion_id, ca.nombre_archivo, co.folio
     FROM cotizacion_archivos ca
     JOIN cotizaciones co ON co.id = ca.cotizacion_id
     WHERE ca.idrive_subido IS NULL LIMIT 1"
)->fetch();

if ($row) {
    $ruta = UPLOADS_ROOT . '/cotizaciones/' . $row['cotizacion_id'] . '/' . $row['nombre_archivo'];
    echo 'Archivo BD : ' . $row['nombre_archivo'] . "\n";
    echo "Ruta local : {$ruta}\n";
    echo (file_exists($ruta) ? "OK Existe en disco\n" : "NO existe en disco\n");
} else {
    echo "Sin archivos pendientes en BD\n";
}

echo "\n</pre>";
