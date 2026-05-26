<?php
declare(strict_types=1);

/**
 * App/Cron/CronRenovaciones.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Cron ÚNICO y canónico de renovaciones. Reemplaza a:
 *   - App/Cron/cron_renovacion.php   (actualiza estados + notificaciones básicas)
 *   - App/Cron/cron_renovaciones.php (notificaciones multi-día con tabla programadas)
 *
 * Ejecutar UNA SOLA VEZ al día (08:00 recomendado):
 *   0 8 * * * /usr/local/bin/php /ruta/al/proyecto/App/Cron/CronRenovaciones.php >> /var/log/crm_renovaciones.log 2>&1
 *
 * PROTECCIÓN CONTRA DOBLE EJECUCIÓN:
 *   Usa flock() sobre un archivo de lock. Si el cron anterior no terminó
 *   (consulta lenta, timeout de red) la nueva instancia sale inmediatamente
 *   sin duplicar notificaciones.
 * ─────────────────────────────────────────────────────────────────────────────
 */

define('PROJECT_ROOT', dirname(__DIR__, 2));

if (file_exists(PROJECT_ROOT . '/.env.php')) {
    require_once PROJECT_ROOT . '/.env.php';
}

$autoloader = PROJECT_ROOT . '/vendor/autoload.php';
if (file_exists($autoloader)) require_once $autoloader;
require_once PROJECT_ROOT . '/Core/Autoloader.php';

use App\Models\{PolizaModel, NotificacionModel, CotizacionModel};
use Database\Connection;

// ── Función de log ────────────────────────────────────────────────────────────
$log = function (string $msg): void {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
};

// ── Lock anti-doble-ejecución ─────────────────────────────────────────────────
$lockDir  = PROJECT_ROOT . '/storage/locks';
if (!is_dir($lockDir)) mkdir($lockDir, 0750, true);
$lockFile = $lockDir . '/cron_renovaciones.lock';
$lockFh   = fopen($lockFile, 'c');

if (!$lockFh || !flock($lockFh, LOCK_EX | LOCK_NB)) {
    $log('SKIP: otra instancia del cron ya está corriendo (lock activo).');
    exit(0);
}

$log('Iniciando cron de renovaciones.');

try {
    $polizaModel     = new PolizaModel();
    $notifModel      = new NotificacionModel();
    $cotizacionModel = new CotizacionModel();
    $db              = Connection::getConnection();

    // ── 1. Actualizar estados vencidos — PÓLIZAS ──────────────────────────────
    $polizaModel->actualizarEstadosVencidos();
    $log('Estados vencidos de pólizas actualizados.');

    // ── 2. Actualizar estados vencidos — COTIZACIONES ─────────────────────────
    try {
        $cotizacionModel->actualizarEstadosVencidos();
        $log('Estados vencidos de cotizaciones actualizados.');
    } catch (\Throwable $e) {
        $log('AVISO actualizarEstadosVencidos cotizaciones: ' . $e->getMessage());
    }

    // ── 3. Notificaciones de pólizas próximas a vencer ────────────────────────
    // Días objetivo: se notifica en cada uno de estos umbrales.
    // La tabla renovacion_notificaciones_programadas evita duplicados.
    $diasObjetivo = [30, 15, 10, 5, 4, 3, 2, 1];

    foreach ($diasObjetivo as $dias) {
        $fechaObj = date('Y-m-d', strtotime("+{$dias} days"));

        $st = $db->prepare(
            "SELECT p.id AS poliza_id, p.numero_poliza, p.compania,
                    p.fecha_fin, p.cliente_id, p.agente_id,
                    CONCAT(cl.nombre,' ',cl.apellido_paterno) AS cliente_nombre,
                    co.folio
             FROM   polizas p
             JOIN   clientes cl ON cl.id = p.cliente_id
             LEFT JOIN cotizaciones co ON co.id = p.cotizacion_id
             WHERE  p.estado IN ('vigente','por_vencer')
               AND  p.eliminado_en IS NULL
               AND  DATE(p.fecha_fin) = ?
               AND  NOT EXISTS (
                   SELECT 1 FROM renovacion_notificaciones_programadas rnp
                   WHERE rnp.poliza_id = p.id
                     AND rnp.dias_antes = ?
                     AND rnp.enviada = 1
               )
             GROUP BY p.id"
        );
        $st->execute([$fechaObj, $dias]);
        $polizas = $st->fetchAll(\PDO::FETCH_ASSOC);

        $log("{$dias}d → " . count($polizas) . ' pólizas por notificar.');

        foreach ($polizas as $pol) {
            $urgente   = $dias <= 5;
            $color     = $dias <= 5 ? 'red' : ($dias <= 15 ? 'amber' : 'blue');
            $diaTexto  = $dias === 1 ? '1 día' : "{$dias} días";
            $clienteNm = $pol['cliente_nombre'];
            $numPol    = $pol['numero_poliza'] ?? "Póliza #{$pol['poliza_id']}";
            $compania  = $pol['compania'] ?? 'Aseguradora';

            $titulo  = $urgente
                ? "🚨 Póliza vence en {$diaTexto} — {$clienteNm}"
                : "⏰ Póliza vence en {$diaTexto} — {$clienteNm}";
            $mensaje = "La póliza {$numPol} de {$compania} para {$clienteNm} vence el "
                     . date('d/m/Y', strtotime($pol['fecha_fin']))
                     . ". Quedan {$diaTexto} para la renovación.";
            $urlAccion = $pol['cliente_id'] ? "/cliente/{$pol['cliente_id']}" : '';

            // Crear notificación interna
            try {
                $db->prepare(
                    "INSERT INTO notificaciones
                     (agente_id, tipo, titulo, mensaje, urgente, color, url_accion, leida, creada_en)
                     VALUES (?,?,?,?,?,?,?,0,NOW())"
                )->execute([
                    $pol['agente_id'], 'renovacion', $titulo, $mensaje,
                    $urgente ? 1 : 0, $color, $urlAccion,
                ]);
            } catch (\Throwable $e) {
                $log("ERR notificación agente {$pol['agente_id']}: {$e->getMessage()}");
                continue;
            }

            // Marcar como enviada para no repetir
            $db->prepare(
                "INSERT INTO renovacion_notificaciones_programadas
                 (poliza_id, agente_id, dias_antes, enviada, enviada_en, fecha_programada)
                 VALUES (?,?,?,1,NOW(),?)
                 ON DUPLICATE KEY UPDATE enviada=1, enviada_en=NOW()"
            )->execute([$pol['poliza_id'], $pol['agente_id'], $dias, $fechaObj]);

            // Registrar en historial del cliente
            if ($pol['cliente_id']) {
                try {
                    $db->prepare(
                        "INSERT INTO historial_cliente
                         (cliente_id, agente_id, tipo, titulo, descripcion, fecha)
                         VALUES (?,'nota',?,?,NOW())"
                    )->execute([
                        $pol['cliente_id'], $pol['agente_id'],
                        "🔔 Recordatorio renovación ({$diaTexto})", $mensaje,
                    ]);
                } catch (\Throwable) {}
            }

            $polizaModel->marcarNotificada((int)$pol['poliza_id']);
            $log("OK póliza #{$pol['poliza_id']} — agente #{$pol['agente_id']} — {$diaTexto}");
        }
    }

    // ── 4. Cotizaciones con vigencia próxima (30 días) ────────────────────────
    try {
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
                $titulo = "📋 Cotización vencida hoy — {$cliente}";
            } elseif ($dias <= 7) {
                $titulo = "🔴 Cotización vence en {$dias} días — {$cliente}";
            } else {
                $titulo = "📋 Cotización por vencer ({$dias} días) — {$cliente}";
            }

            $mensaje = "La cotización {$co['folio']} de {$co['compania']} para {$cliente} vence el "
                     . date('d/m/Y', strtotime($co['fecha_fin_vigencia'])) . ".";

            $notifModel->crear((int)$co['agente_id'], 'vencimiento', $titulo, $mensaje, 'cotizacion:' . $co['id']);

            $db->prepare('UPDATE cotizaciones SET renovacion_notificada = 1 WHERE id = ?')
               ->execute([$co['id']]);

            $log("Cotización notificada: {$co['folio']} — agente {$co['agente_id']}");
        }
    } catch (\Throwable $e) {
        $log('ERROR cotizaciones: ' . $e->getMessage());
    }

    $log('Cron finalizado OK.');

} catch (\Throwable $e) {
    // Registrar error fatal sin detener el proceso limpiamente
    error_log('[CronRenovaciones FATAL] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    $log('ERROR FATAL: ' . $e->getMessage());
} finally {
    // Liberar lock siempre, incluso si hubo excepción
    flock($lockFh, LOCK_UN);
    fclose($lockFh);
}
