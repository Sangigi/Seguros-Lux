<?php
/**
 * BorrarArchivosBorrados.php
 * Borra del servidor local las carpetas completas de cotizaciones cuyos
 * archivos ya llevan más de 24 horas en iDrive E2.
 *
 * Solo elimina la carpeta cuando TODOS los archivos de esa cotización
 * ya están en iDrive — si queda alguno pendiente, se omite.
 *
 * HOSTGATOR CRON (una vez al día a las 03:00 AM):
 *   0 3 * * * /usr/local/bin/php /home2/grupoide/public_html/src/Cron/BorrarArchivosBorrados.php
 *
 * WINDOWS TASK SCHEDULER (desarrollo):
 *   Programa:   C:\xampp\php\php.exe
 *   Argumentos: C:\xampp\htdocs\apirest-dinamica\src\Cron\BorrarArchivosBorrados.php
 *   Frecuencia: Una vez al día a las 03:00 AM
 *
 * NOTA: SubirIdriveYBorrar.php borra archivos individuales.
 *       Este script limpia las carpetas vacías que quedan.
 */
declare(strict_types=1);

// ── Bootstrap ─────────────────────────────────────────────────────────────────
define('PROJECT_ROOT', dirname(__DIR__, 2));
define('UPLOADS_ROOT', PROJECT_ROOT . '/uploads');

if (file_exists(PROJECT_ROOT . '/.env.php')) {
    require_once PROJECT_ROOT . '/.env.php';
}

require_once PROJECT_ROOT . '/Core/Autoloader.php';

use App\Models\CotizacionModel;
use Database\Connection;

// ── Logs ──────────────────────────────────────────────────────────────────────
$logsDir = PROJECT_ROOT . '/logs';
if (!is_dir($logsDir)) mkdir($logsDir, 0755, true);
$logFile = $logsDir . '/borrado_local_' . date('Y-m') . '.log';

function log_msg_bl(string $msg, string $file): void
{
    $line = date('[Y-m-d H:i:s] ') . $msg . "\n";
    file_put_contents($file, $line, FILE_APPEND);
    echo $line;
}

/**
 * Borra recursivamente el contenido de un directorio y el directorio mismo.
 */
function borrarCarpetaRecursiva(string $dir): bool
{
    if (!is_dir($dir)) return true;

    foreach (scandir($dir) as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $entry;
        is_dir($path) ? borrarCarpetaRecursiva($path) : unlink($path);
    }

    return rmdir($dir);
}

// ── Modelo y BD ───────────────────────────────────────────────────────────────
$cotModel = new CotizacionModel();
$db       = Connection::getConnection();

// Archivos subidos a iDrive hace >24 hrs y aún no marcados como borrados local
$stmt = $db->query(
    "SELECT id, cotizacion_id, nombre_archivo
     FROM cotizacion_archivos
     WHERE idrive_subido IS NOT NULL
       AND idrive_borrado IS NULL
       AND idrive_subido < DATE_SUB(NOW(), INTERVAL 24 HOUR)
     ORDER BY cotizacion_id ASC"
);
$paraBorrar = $stmt->fetchAll();

if (empty($paraBorrar)) {
    log_msg_bl('Sin carpetas pendientes de borrado local.', $logFile);
    exit(0);
}

log_msg_bl('=== INICIO BORRADO LOCAL (' . count($paraBorrar) . ' archivos) ===', $logFile);

// Agrupar por cotizacion_id para borrar carpetas completas de una sola vez
$porCotizacion = [];
foreach ($paraBorrar as $arch) {
    $porCotizacion[(int)$arch['cotizacion_id']][] = $arch;
}

$carpetasBorradas = 0;
$errores          = 0;

foreach ($porCotizacion as $cotId => $archivos) {
    // Verificar que NO queden archivos sin subir a iDrive en esta cotización
    $chk = $db->prepare(
        "SELECT COUNT(*) FROM cotizacion_archivos
         WHERE cotizacion_id = ? AND idrive_subido IS NULL"
    );
    $chk->execute([$cotId]);
    $pendientes = (int)$chk->fetchColumn();

    if ($pendientes > 0) {
        log_msg_bl(
            "⏭️  Cotización #{$cotId}: {$pendientes} archivo(s) aún sin subir a iDrive. Omitida.",
            $logFile
        );
        continue;
    }

    $carpeta = UPLOADS_ROOT . "/cotizaciones/{$cotId}";

    if (is_dir($carpeta)) {
        $archivosEnCarpeta = count(array_diff(scandir($carpeta), ['.', '..']));

        if (borrarCarpetaRecursiva($carpeta)) {
            log_msg_bl(
                "🗑️  Carpeta borrada: uploads/cotizaciones/{$cotId}/ ({$archivosEnCarpeta} archivo(s))",
                $logFile
            );
            $carpetasBorradas++;
        } else {
            log_msg_bl("⚠️  No se pudo borrar: uploads/cotizaciones/{$cotId}/", $logFile);
            $errores++;
            continue; // No marcar como borrado si falló el rmdir
        }
    } else {
        log_msg_bl("ℹ️  Carpeta ya no existe: uploads/cotizaciones/{$cotId}/", $logFile);
    }

    // Marcar todos los archivos de esta cotización como borrados localmente
    foreach ($archivos as $arch) {
        $cotModel->marcarBorradoLocal((int)$arch['id']);
    }
}

log_msg_bl(
    "=== FIN: {$carpetasBorradas} carpeta(s) borrada(s), {$errores} error(es) ===",
    $logFile
);