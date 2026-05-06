<?php
declare(strict_types=1);

/**
 * cron/cron_renovaciones.php
 * Cron de renovaciones y vencimientos de pólizas + cotizaciones.
 *
 * Configurar en crontab (una vez al día, ej. 08:00):
 *   0 8 * * * php /var/www/html/cron/cron_renovaciones.php >> /var/log/cron_renovaciones.log 2>&1
 */

define('PROJECT_ROOT', dirname(__DIR__));

$envFile = PROJECT_ROOT . '/.env.php';
if (file_exists($envFile)) require_once $envFile;
require_once PROJECT_ROOT . '/Core/Autoloader.php';

use App\Models\{PolizaModel, NotificacionModel, CotizacionModel};

$polizaModel     = new PolizaModel();
$notifModel      = new NotificacionModel();
$cotizacionModel = new CotizacionModel();

$log = fn(string $msg) => print('[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL);

// ── 1. Actualizar estados vencidos — PÓLIZAS ─────────────────────────────────
$polizaModel->actualizarEstadosVencidos();
$log('Estados vencidos de pólizas actualizados.');

// ── 2. Actualizar estados vencidos — COTIZACIONES ────────────────────────────
// Marca como 'vencida' las cotizaciones aceptadas/pagadas con fecha_fin_vigencia expirada.
try {
    $cotizacionModel->actualizarEstadosVencidos();
    $log('Estados vencidos de cotizaciones actualizados.');
} catch (\Throwable $e) {
    $log('AVISO actualizarEstadosVencidos cotizaciones: ' . $e->getMessage());
}

// ── 3. Pólizas próximas a vencer (30 días) ───────────────────────────────────
$porVencer = $polizaModel->getPorVencer();
$log(count($porVencer) . ' pólizas por vencer encontradas.');

foreach ($porVencer as $p) {
    $dias    = (int) $p['dias_restantes'];
    $cliente = $p['cliente_nombre'];
    $compania= $p['compania'] ?? 'Aseguradora';
    $num     = $p['numero_poliza'] ?? "Póliza #{$p['id']}";

    if ($dias <= 0) {
        $titulo  = "⚠️ Póliza vencida — {$cliente}";
        $mensaje = "La póliza {$num} de {$compania} para {$cliente} venció. Contacta al cliente para renovación.";
    } elseif ($dias <= 7) {
        $titulo  = "🔴 Póliza vence en {$dias} días — {$cliente}";
        $mensaje = "La póliza {$num} de {$compania} vence el " . date('d/m/Y', strtotime($p['fecha_fin'])) . ". Quedan {$dias} días. Contacta al cliente urgentemente.";
    } else {
        $titulo  = "🟡 Póliza vence en {$dias} días — {$cliente}";
        $mensaje = "La póliza {$num} de {$compania} para {$cliente} vence el " . date('d/m/Y', strtotime($p['fecha_fin'])) . ". Quedan {$dias} días para la renovación.";
    }

    $notifModel->crear(
        (int) $p['agente_id'],
        'renovacion',
        $titulo,
        $mensaje,
        'poliza:' . $p['id']
    );

    $polizaModel->marcarNotificada((int) $p['id']);
    $log("Notificación enviada: agente {$p['agente_id']} — {$titulo}");
}

// ── 4. Cotizaciones con fecha_fin_vigencia próxima (30 días) ──────────────────
try {
    $db = \Database\Connection::getConnection();
    $stCot = $db->prepare(
        "SELECT co.*, CONCAT(c.nombre,' ',c.apellido_paterno) AS cliente_nombre,
                DATEDIFF(co.fecha_fin_vigencia, CURDATE()) AS dias_restantes
         FROM   cotizaciones co
         JOIN   clientes c ON c.id = co.cliente_id
         WHERE  co.fecha_fin_vigencia IS NOT NULL
           AND  co.renovacion_notificada = 0
           AND  co.estado IN ('aceptada','pagado')
           AND  DATEDIFF(co.fecha_fin_vigencia, CURDATE()) BETWEEN 0 AND 30"
    );
    $stCot->execute();
    $cotsPorVencer = $stCot->fetchAll(\PDO::FETCH_ASSOC);

    $log(count($cotsPorVencer) . ' cotizaciones con vigencia próxima.');

    foreach ($cotsPorVencer as $co) {
        $dias    = (int) $co['dias_restantes'];
        $cliente = $co['cliente_nombre'];

        if ($dias <= 0) {
            $titulo  = "📋 Cotización vencida hoy — {$cliente}";
        } elseif ($dias <= 7) {
            $titulo  = "🔴 Cotización vence en {$dias} días — {$cliente}";
        } else {
            $titulo  = "📋 Cotización por vencer ({$dias} días) — {$cliente}";
        }

        $mensaje = "La cotización {$co['folio']} de {$co['compania']} para {$cliente} vence su vigencia el "
                 . date('d/m/Y', strtotime($co['fecha_fin_vigencia'])) . ".";

        $notifModel->crear((int)$co['agente_id'], 'vencimiento', $titulo, $mensaje, 'cotizacion:' . $co['id']);

        $db->prepare('UPDATE cotizaciones SET renovacion_notificada = 1 WHERE id = ?')
           ->execute([$co['id']]);

        $log("Cotización notificada: {$co['folio']} — agente {$co['agente_id']}");
    }
} catch (\Throwable $e) {
    $log('ERROR cotizaciones: ' . $e->getMessage());
}

$log('Cron finalizado.');