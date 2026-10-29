<?php
declare(strict_types=1);

/**
 * App/Cron/CronMail.php
 * Procesa la cola de emails pendientes en la tabla mail_queue.
 *
 * ESTRATEGIA DE EMPRESA: GLOBAL (Tenant::sinRestriccion()).
 *   Es un proceso de infraestructura: vacía la cola de toda la plataforma y
 *   no crea datos de negocio. Cada correo ya trae su destinatario y su
 *   empresa_id desde que se encoló; aquí solo se envía y se marca el
 *   resultado sobre la fila que ya existe. Recorrer empresa por empresa
 *   abriría una conexión SMTP por empresa sin ganar aislamiento alguno.
 *
 * HOSTGATOR CRON (cada minuto):
 *   * * * * * /usr/local/bin/php /home2/grupoide/seguroslux.mx/App/Cron/CronMail.php >> /home2/grupoide/seguroslux.mx/logs/cron_mail.log 2>&1
 *
 * DESARROLLO LOCAL (ejecutar manualmente o con Task Scheduler):
 *   php App/Cron/CronMail.php
 *
 * El script procesa hasta 20 emails por ejecución con backoff exponencial
 *   en los reintentos (intento 2 → 5 min, intento 3 → 30 min).
 *
 * Envía un máximo de 20 correos por minuto — suficiente para el volumen
 * actual de Seguros Lux sin saturar el límite de Hostgator (500/hora).
 */

define('PROJECT_ROOT', dirname(__DIR__, 2));

if (file_exists(PROJECT_ROOT . '/.env.php')) {
    require_once PROJECT_ROOT . '/.env.php';
}

require_once PROJECT_ROOT . '/Core/Autoloader.php';

// Verificar que PHPMailer esté disponible
$autoload = PROJECT_ROOT . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    $logsDir = PROJECT_ROOT . '/logs';
    if (!is_dir($logsDir)) mkdir($logsDir, 0755, true);
    file_put_contents($logsDir . '/cron_mail.log', date('[Y-m-d H:i:s] ') . "[CronMail] vendor/autoload.php no encontrado. Ejecuta composer install.\n", FILE_APPEND);
    exit(1);
}
require_once $autoload;

use App\Security\Tenant;
use App\Services\MailQueue;

// ── Log ───────────────────────────────────────────────────────────────────────
$logsDir = PROJECT_ROOT . '/logs';
if (!is_dir($logsDir)) mkdir($logsDir, 0755, true);
$logFile = $logsDir . '/cron_mail.log';

function log_cron_mail(string $msg, string $file): void
{
    $line = date('[Y-m-d H:i:s] ') . $msg . "\n";
    file_put_contents($file, $line, FILE_APPEND);
}

// Rotar si supera 5 MB
if (file_exists($logFile) && filesize($logFile) > 5 * 1024 * 1024) {
    rename($logFile, $logFile . '.' . date('Y-m-d') . '.bak');
}

$start = microtime(true);

try {
    // La cola es de toda la plataforma: se declara explícitamente en vez de
    // depender de que no haya empresa en contexto.
    Tenant::sinRestriccion(static fn() => MailQueue::flush(batchSize: 20));
} catch (\Throwable $e) {
    log_cron_mail('[CronMail] ERROR: ' . $e->getMessage(), $logFile);
    exit(1);
}

$elapsed = round((microtime(true) - $start) * 1000);
log_cron_mail('[CronMail] OK en ' . $elapsed . ' ms', $logFile);