<?php
declare(strict_types=1);

/**
 * App/Cron/CronMail.php
 * Procesa la cola de emails pendientes en la tabla mail_queue.
 *
 * HOSTGATOR CRON (cada minuto):
 *   * * * * * /usr/local/bin/php /home2/grupoide/seguroslux.mx/App/Cron/CronMail.php >> /dev/null 2>&1
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
    fwrite(STDERR, "[CronMail] vendor/autoload.php no encontrado. Ejecuta composer install.\n");
    exit(1);
}
require_once $autoload;

use App\Services\MailQueue;

$start = microtime(true);

try {
    MailQueue::flush(batchSize: 20);
} catch (\Throwable $e) {
    fwrite(STDERR, '[CronMail] ' . $e->getMessage() . "\n");
    exit(1);
}

$elapsed = round((microtime(true) - $start) * 1000);
echo '[CronMail] OK en ' . $elapsed . " ms\n";
