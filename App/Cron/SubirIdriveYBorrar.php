<?php
/**
 * SubirIdriveYBorrar.php
 *
 * Estructura en iDrive E2:
 *   clientes/{slug-nombre-cliente}/{folio}/documentos/
 *   clientes/{slug-nombre-cliente}/{folio}/polizas/
 *   clientes/{slug-nombre-cliente}/{folio}/pagos/
 *
 * CRON (una vez al día a las 02:00 AM):
 *   0 2 * * * /usr/local/bin/php /home2/grupoide/seguroslux.mx/App/Cron/SubirIdriveYBorrar.php
 */
declare(strict_types=1);

// ── Capturar CUALQUIER error fatal y loguearlo ────────────────────────────────
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $logsDir = defined('PROJECT_ROOT') ? PROJECT_ROOT . '/logs' : '/tmp';
        $logFile = $logsDir . '/idrive_' . date('Y-m') . '.log';
        $msg = date('[Y-m-d H:i:s] ') . "💀 ERROR FATAL: {$error['message']} en {$error['file']}:{$error['line']}\n";
        file_put_contents($logFile, $msg, FILE_APPEND);
        echo $msg;
    }
});

set_exception_handler(function (\Throwable $e) {
    $logsDir = defined('PROJECT_ROOT') ? PROJECT_ROOT . '/logs' : '/tmp';
    $logFile = $logsDir . '/idrive_' . date('Y-m') . '.log';
    $msg = date('[Y-m-d H:i:s] ') . "💀 EXCEPCIÓN NO CAPTURADA: " . $e->getMessage()
         . " en " . $e->getFile() . ":" . $e->getLine() . "\n";
    file_put_contents($logFile, $msg, FILE_APPEND);
    echo $msg;
    exit(1);
});

define('PROJECT_ROOT', dirname(__DIR__, 2));
define('UPLOADS_ROOT', PROJECT_ROOT . '/uploads');

if (file_exists(PROJECT_ROOT . '/.env.php')) {
    require_once PROJECT_ROOT . '/.env.php';
}

require_once PROJECT_ROOT . '/Core/Autoloader.php';

$autoloader = PROJECT_ROOT . '/vendor/autoload.php';
if (!file_exists($autoloader)) {
    die("[ERROR] AWS SDK no instalado. Ejecuta: composer require aws/aws-sdk-php\n");
}
require_once $autoloader;

use App\Models\CotizacionModel;
use Database\Connection;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;

// ── Config iDrive E2 ──────────────────────────────────────────────────────────
$endpoint  = getenv('IDRIVE_ENDPOINT')   ?: 's3.us-west-2.idrivee2.com';
$bucket    = getenv('IDRIVE_BUCKET')     ?: 'crmseguros';
$region    = getenv('IDRIVE_REGION')     ?: 'us-west-2';
$keyId     = getenv('IDRIVE_KEY_ID')     ?: '';
$keySecret = getenv('IDRIVE_KEY_SECRET') ?: '';

if (!$keyId || !$keySecret) {
    die("[ERROR] Faltan credenciales de iDrive.\n");
}

// ── Logs ──────────────────────────────────────────────────────────────────────
$logsDir = PROJECT_ROOT . '/logs';
if (!is_dir($logsDir)) mkdir($logsDir, 0755, true);
$logFile = $logsDir . '/idrive_' . date('Y-m') . '.log';

function log_msg_id(string $msg, string $file): void
{
    $line = date('[Y-m-d H:i:s] ') . $msg . "\n";
    file_put_contents($file, $line, FILE_APPEND);
    echo $line;
}

function clienteSlug(string $nombre): string
{
    $slug = mb_strtolower($nombre, 'UTF-8');
    $slug = str_replace(
        ['á','é','í','ó','ú','ü','ñ','Á','É','Í','Ó','Ú','Ü','Ñ'],
        ['a','e','i','o','u','u','n','a','e','i','o','u','u','n'],
        $slug
    );
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    return trim($slug, '-') ?: 'cliente';
}

function carpetaIdrive(string $tipo): string
{
    return match ($tipo) {
        'poliza'           => 'polizas',
        'comprobante_pago' => 'pagos',
        default            => 'documentos',
    };
}

// ── Modelo y BD ───────────────────────────────────────────────────────────────
$cotModel = new CotizacionModel();
$db       = Connection::getConnection();

// ── Inicializar S3 ────────────────────────────────────────────────────────────
try {
    $s3 = new S3Client([
        'version'                 => 'latest',
        'region'                  => $region,
        'endpoint'                => "https://{$endpoint}",
        'use_path_style_endpoint' => true,
        'credentials'             => ['key' => $keyId, 'secret' => $keySecret],
    ]);
} catch (\Throwable $e) {
    log_msg_id("💀 No se pudo inicializar S3Client: " . $e->getMessage(), $logFile);
    exit(1);
}

log_msg_id('=== INICIO SubirIdriveYBorrar ===', $logFile);
log_msg_id('PROJECT_ROOT: ' . PROJECT_ROOT, $logFile);
log_msg_id('UPLOADS_ROOT: ' . UPLOADS_ROOT . ' [' . (is_dir(UPLOADS_ROOT) ? 'existe' : 'NO EXISTE') . ']', $logFile);

// ══════════════════════════════════════════════════════════════════════════════
// FASE 1a — Subir cotizacion_archivos pendientes
// ══════════════════════════════════════════════════════════════════════════════
log_msg_id('=== FASE 1a: DOCUMENTOS DE COTIZACIÓN ===', $logFile);

$subidos = 0;
$errores = 0;

try {
    $stmt = $db->query(
        "SELECT ca.id, ca.cotizacion_id, ca.tipo_documento, ca.nombre_original,
                ca.nombre_archivo, ca.ruta, co.folio,
                CONCAT(cl.nombre,' ',cl.apellido_paterno) AS cliente_nombre
         FROM cotizacion_archivos ca
         JOIN cotizaciones co ON co.id = ca.cotizacion_id
         JOIN clientes cl ON cl.id = co.cliente_id
         WHERE ca.idrive_subido IS NULL
           AND ca.eliminado_en IS NULL
         ORDER BY ca.subido_en ASC"
    );
    $pendientes = $stmt->fetchAll();
    log_msg_id("Fase 1a: " . count($pendientes) . " archivos pendientes.", $logFile);
} catch (\Throwable $e) {
    log_msg_id("❌ Fase 1a — Error en query: " . $e->getMessage(), $logFile);
    $pendientes = [];
}

foreach ($pendientes as $arch) {
    $rutaLocal = UPLOADS_ROOT . '/cotizaciones/'
               . $arch['cotizacion_id'] . '/'
               . $arch['nombre_archivo'];

    if (!file_exists($rutaLocal)) {
        log_msg_id("⚠️  Archivo no encontrado: {$rutaLocal}", $logFile);
        $errores++;
        continue;
    }

    $slug    = clienteSlug($arch['cliente_nombre'] ?? 'cliente');
    $folio   = $arch['folio'] ?: 'sin_folio';
    $carpeta = carpetaIdrive($arch['tipo_documento'] ?? 'documentos');
    $s3Key   = "clientes/{$slug}/{$folio}/{$carpeta}/{$arch['nombre_archivo']}";

    try {
        $s3->putObject([
            'Bucket'     => $bucket,
            'Key'        => $s3Key,
            'SourceFile' => $rutaLocal,
            'ACL'        => 'private',
            'Metadata'   => [
                'cotizacion-id' => (string)$arch['cotizacion_id'],
                'tipo'          => $arch['tipo_documento'] ?? '',
                'original'      => $arch['nombre_original'] ?? '',
            ],
        ]);
        $cotModel->marcarIdriveSubido((int)$arch['id']);
        $subidos++;
        log_msg_id("✅ Subido: {$s3Key}", $logFile);
    } catch (AwsException $e) {
        $errores++;
        log_msg_id("❌ Error subiendo {$arch['nombre_archivo']}: " . $e->getAwsErrorMessage(), $logFile);
    } catch (\Throwable $e) {
        $errores++;
        log_msg_id("❌ Error inesperado 1a [{$arch['nombre_archivo']}]: " . $e->getMessage(), $logFile);
    }
}

log_msg_id("Fase 1a completada: {$subidos} subidos, {$errores} errores.", $logFile);

// ══════════════════════════════════════════════════════════════════════════════
// FASE 1b — Subir comprobantes de PAGO pendientes
// ══════════════════════════════════════════════════════════════════════════════
log_msg_id('=== FASE 1b: COMPROBANTES DE PAGO ===', $logFile);

$subidosP = 0;
$erroresP = 0;
$pendientesP = [];

try {
    // Verificar tabla y columnas antes de query principal
    $chkTabla = $db->query("SHOW TABLES LIKE 'cotizacion_pagos'")->fetchAll();
    if (empty($chkTabla)) {
        log_msg_id("⚠️  Tabla 'cotizacion_pagos' no existe. Saltando fase 1b.", $logFile);
    } else {
        $cols = $db->query("SHOW COLUMNS FROM cotizacion_pagos")->fetchAll(\PDO::FETCH_COLUMN);
        log_msg_id("  Columnas en cotizacion_pagos: " . implode(', ', $cols), $logFile);

        $stmtP = $db->query(
            "SELECT cp.id, cp.cotizacion_id, cp.nombre_archivo, cp.ruta,
                    co.folio,
                    CONCAT(cl.nombre,' ',cl.apellido_paterno) AS cliente_nombre
             FROM cotizacion_pagos cp
             JOIN cotizaciones co ON co.id = cp.cotizacion_id
             JOIN clientes cl ON cl.id = co.cliente_id
             WHERE cp.ruta IS NOT NULL
               AND (cp.idrive_subido IS NULL OR cp.idrive_subido = 0)
             ORDER BY cp.id ASC"
        );
        $pendientesP = $stmtP ? $stmtP->fetchAll() : [];
        log_msg_id("Fase 1b: " . count($pendientesP) . " pagos pendientes.", $logFile);
    }
} catch (\Throwable $e) {
    log_msg_id("❌ Fase 1b — Error en query: " . $e->getMessage(), $logFile);
}

foreach ($pendientesP as $pago) {
    if (empty($pago['nombre_archivo'])) continue;

    $cotId = $pago['cotizacion_id'];

    $candidatos = [
        UPLOADS_ROOT . "/pagos/{$cotId}/{$pago['nombre_archivo']}",
        UPLOADS_ROOT . '/' . ltrim((string)$pago['ruta'], '/'),
        UPLOADS_ROOT . "/pagos/{$pago['nombre_archivo']}",
    ];

    $rutaLocal = null;
    foreach ($candidatos as $c) {
        if (file_exists($c)) { $rutaLocal = $c; break; }
    }

    if ($rutaLocal === null) {
        log_msg_id("⚠️ Comprobante no encontrado — id:{$pago['id']} [{$pago['nombre_archivo']}] ruta_bd:[{$pago['ruta']}]", $logFile);
        log_msg_id("   Candidatos: " . implode(' | ', $candidatos), $logFile);
        $erroresP++;
        continue;
    }

    $slug  = clienteSlug($pago['cliente_nombre'] ?? 'cliente');
    $folio = $pago['folio'] ?: 'sin_folio';
    $s3Key = "clientes/{$slug}/{$folio}/pagos/{$pago['nombre_archivo']}";

    try {
        $s3->putObject([
            'Bucket'     => $bucket,
            'Key'        => $s3Key,
            'SourceFile' => $rutaLocal,
            'ACL'        => 'private',
        ]);

        try {
            $db->prepare("UPDATE cotizacion_pagos SET idrive_subido = NOW() WHERE id = ?")
               ->execute([$pago['id']]);
        } catch (\Throwable $eU) {
            log_msg_id("⚠️ No se pudo marcar pago #{$pago['id']}: " . $eU->getMessage(), $logFile);
        }

        $subidosP++;
        log_msg_id("✅ Pago subido: {$s3Key}", $logFile);

    } catch (AwsException $e) {
        $erroresP++;
        log_msg_id("❌ Error pago {$pago['nombre_archivo']}: " . $e->getAwsErrorMessage(), $logFile);
    } catch (\Throwable $e) {
        $erroresP++;
        log_msg_id("❌ Error inesperado 1b [{$pago['nombre_archivo']}]: " . $e->getMessage(), $logFile);
    }
}

log_msg_id("Fase 1b completada: {$subidosP} subidos, {$erroresP} errores.", $logFile);

// ══════════════════════════════════════════════════════════════════════════════
// FASE 1c — Subir pólizas pendientes
// ══════════════════════════════════════════════════════════════════════════════
log_msg_id('=== FASE 1c: PÓLIZAS ===', $logFile);

$subidosPol = 0;
$erroresPol = 0;
$polPend    = [];

try {
    $chkTabla = $db->query("SHOW TABLES LIKE 'polizas'")->fetchAll();
    if (empty($chkTabla)) {
        log_msg_id("⚠️  Tabla 'polizas' no existe. Saltando fase 1c.", $logFile);
    } else {
        $stmtPol = $db->query(
            "SELECT p.id, p.cliente_id, p.nombre_archivo, p.ruta, p.cotizacion_id,
                    CONCAT(cl.nombre,' ',cl.apellido_paterno) AS cliente_nombre,
                    co.folio
             FROM polizas p
             JOIN clientes cl ON cl.id = p.cliente_id
             LEFT JOIN cotizaciones co ON co.id = p.cotizacion_id
             WHERE p.ruta IS NOT NULL
               AND p.eliminado_en IS NULL
               AND (p.idrive_subido IS NULL OR p.idrive_subido = 0)
             ORDER BY p.id ASC"
        );
        $polPend = $stmtPol ? $stmtPol->fetchAll() : [];
        log_msg_id("Fase 1c: " . count($polPend) . " pólizas pendientes.", $logFile);
    }
} catch (\Throwable $e) {
    log_msg_id("❌ Fase 1c — Error en query: " . $e->getMessage(), $logFile);
}

foreach ($polPend as $pol) {
    if (empty($pol['nombre_archivo'])) continue;

    $clienteId = $pol['cliente_id'];

    $candidatos = [
        UPLOADS_ROOT . "/polizas/{$clienteId}/{$pol['nombre_archivo']}",
        UPLOADS_ROOT . '/' . ltrim((string)$pol['ruta'], '/'),
        UPLOADS_ROOT . "/polizas/{$pol['nombre_archivo']}",
    ];

    $rutaLocal = null;
    foreach ($candidatos as $c) {
        if (file_exists($c)) { $rutaLocal = $c; break; }
    }

    if ($rutaLocal === null) {
        log_msg_id("⚠️ Póliza no encontrada — id:{$pol['id']} [{$pol['nombre_archivo']}] ruta_bd:[{$pol['ruta']}]", $logFile);
        log_msg_id("   Candidatos: " . implode(' | ', $candidatos), $logFile);
        $erroresPol++;
        continue;
    }

    $slug  = clienteSlug($pol['cliente_nombre'] ?? 'cliente');
    $folio = $pol['folio'] ?: ('cliente-' . $pol['cliente_id']);
    $s3Key = "clientes/{$slug}/{$folio}/polizas/{$pol['nombre_archivo']}";

    try {
        $s3->putObject([
            'Bucket'     => $bucket,
            'Key'        => $s3Key,
            'SourceFile' => $rutaLocal,
            'ACL'        => 'private',
        ]);

        $db->prepare("UPDATE polizas SET idrive_subido = 1, idrive_key = ? WHERE id = ?")
           ->execute([$s3Key, $pol['id']]);

        $subidosPol++;
        log_msg_id("✅ Póliza subida: {$s3Key}", $logFile);

    } catch (AwsException $e) {
        $erroresPol++;
        log_msg_id("❌ Error póliza #{$pol['id']}: " . $e->getAwsErrorMessage(), $logFile);
    } catch (\Throwable $e) {
        $erroresPol++;
        log_msg_id("❌ Error inesperado 1c [#{$pol['id']}]: " . $e->getMessage(), $logFile);
    }
}

log_msg_id("Fase 1c completada: {$subidosPol} subidas, {$erroresPol} errores.", $logFile);

// ══════════════════════════════════════════════════════════════════════════════
// FASE 2 — Borrar del local los cotizacion_archivos subidos hace >24 hrs
// ══════════════════════════════════════════════════════════════════════════════
log_msg_id('=== FASE 2: BORRADO LOCAL (>24 hrs en iDrive) ===', $logFile);

$borrados   = 0;
$errBorrado = 0;
$paraBorrar = [];

try {
    $stmt2 = $db->query(
        "SELECT id, cotizacion_id, nombre_archivo
         FROM cotizacion_archivos
         WHERE idrive_subido IS NOT NULL
           AND idrive_borrado IS NULL
           AND eliminado_en IS NULL
           AND idrive_subido < DATE_SUB(NOW(), INTERVAL 24 HOUR)
         ORDER BY cotizacion_id ASC"
    );
    $paraBorrar = $stmt2->fetchAll();
    log_msg_id("Fase 2: " . count($paraBorrar) . " archivos para borrar local.", $logFile);
} catch (\Throwable $e) {
    log_msg_id("❌ Fase 2 — Error en query: " . $e->getMessage(), $logFile);
}

foreach ($paraBorrar as $arch) {
    $rutaLocal = UPLOADS_ROOT . '/cotizaciones/'
               . $arch['cotizacion_id'] . '/'
               . $arch['nombre_archivo'];

    if (!file_exists($rutaLocal)) {
        $cotModel->marcarBorradoLocal((int)$arch['id']);
        log_msg_id("ℹ️  Ya no existía localmente: {$arch['nombre_archivo']}", $logFile);
        continue;
    }

    if (unlink($rutaLocal)) {
        $cotModel->marcarBorradoLocal((int)$arch['id']);
        $borrados++;
        log_msg_id("🗑️  Borrado: cotizaciones/{$arch['cotizacion_id']}/{$arch['nombre_archivo']}", $logFile);
    } else {
        $errBorrado++;
        log_msg_id("❌ Error borrando: {$rutaLocal}", $logFile);
    }
}

log_msg_id("Fase 2 completada: {$borrados} borrados, {$errBorrado} errores.", $logFile);

// ── Resumen final ─────────────────────────────────────────────────────────────
log_msg_id(
    sprintf(
        '=== FIN === Docs:%d Pagos:%d Pólizas:%d Borrados:%d Errores:%d',
        $subidos, $subidosP, $subidosPol, $borrados,
        $errores + $erroresP + $erroresPol + $errBorrado
    ),
    $logFile
);