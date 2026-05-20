#!/usr/bin/env php
<?php
/**
 * cron_renovaciones.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Ejecutar cada día a las 9:00 AM via cron:
 *   0 9 * * * /usr/bin/php /var/www/html/cron/cron_renovaciones.php >> /var/log/crm_renovaciones.log 2>&1
 *
 * Lógica:
 *  1. Busca pólizas vigentes/por_vencer con fecha_fin en los próximos días objetivo.
 *  2. Crea notificaciones importantes (urgente=1, color=red) para el asesor asignado.
 *  3. Marca la notificación programada como enviada para no duplicar.
 * ─────────────────────────────────────────────────────────────────────────────
 */

define('BASEPATH', dirname(__DIR__));
require BASEPATH . '/vendor/autoload.php';

use Database\Connection;

$db = Connection::getConnection();

$DIAS_OBJETIVO = [30, 15, 10, 5, 4, 3, 2, 1];

echo "[" . date('Y-m-d H:i:s') . "] Iniciando cron de renovaciones...\n";

foreach ($DIAS_OBJETIVO as $dias) {
    $fechaObj = date('Y-m-d', strtotime("+{$dias} days"));

    // Buscar pólizas que vencen en exactamente $dias días y no han sido notificadas para ese intervalo
    $st = $db->prepare(
        "SELECT p.id AS poliza_id, p.numero_poliza, p.compania,
                p.fecha_fin, p.cliente_id, p.agente_id AS asesor_id,
                cl.id AS cliente_id_ref,
                CONCAT(cl.nombre,' ',cl.apellido_paterno) AS cliente_nombre,
                c.id AS cotizacion_id, c.folio,
                CONCAT(a.nombre,' ',a.apellido) AS asesor_nombre
         FROM polizas p
         JOIN clientes cl ON cl.id = p.cliente_id
         JOIN agentes   a ON a.id  = p.agente_id
         LEFT JOIN cotizaciones c ON c.id = p.cotizacion_id
         WHERE p.estado IN ('vigente','por_vencer')
           AND p.eliminado_en IS NULL
           AND DATE(p.fecha_fin) = ?
           AND NOT EXISTS (
               SELECT 1 FROM renovacion_notificaciones_programadas rnp
               WHERE rnp.poliza_id = p.id
                 AND rnp.dias_antes = ?
                 AND rnp.enviada = 1
           )
         GROUP BY p.id, a.id"
    );
    $st->execute([$fechaObj, $dias]);
    $polizas = $st->fetchAll(\PDO::FETCH_ASSOC);

    foreach ($polizas as $pol) {
        $urgente = $dias <= 5;
        $color   = $dias <= 5 ? 'red' : ($dias <= 15 ? 'amber' : 'blue');

        $diaTexto = $dias === 1 ? '1 día' : "{$dias} días";
        $titulo   = $urgente
            ? "🚨 ¡Póliza vence en {$diaTexto}!"
            : "⏰ Póliza vence en {$diaTexto}";
        $cuerpo   = "La póliza de {$pol['cliente_nombre']} ({$pol['compania']}"
                  . (!empty($pol['numero_poliza']) ? " — #{$pol['numero_poliza']}" : '')
                  . ") vence el " . date('d/m/Y', strtotime($pol['fecha_fin']))
                  . ". Contácta al cliente para gestionar la renovación.";
        $urlAccion = $pol['cliente_id'] ? "/cliente/{$pol['cliente_id']}" : '';

        // Insertar notificación
        try {
            $db->prepare(
                "INSERT INTO notificaciones (agente_id, tipo, titulo, mensaje, urgente, color, url_accion, leida, creada_en)
                 VALUES (?,?,?,?,?,?,?,0,NOW())"
            )->execute([$pol['asesor_id'], 'renovacion', $titulo, $cuerpo, $urgente ? 1 : 0, $color, $urlAccion]);
        } catch (\Throwable $e) {
            echo "[ERR] Notificación asesor {$pol['asesor_id']}: {$e->getMessage()}\n";
            continue;
        }

        // Marcar como enviada
        $db->prepare(
            "INSERT INTO renovacion_notificaciones_programadas
             (poliza_id, asesor_id, dias_antes, enviada, enviada_en, fecha_programada)
             VALUES (?,?,?,1,NOW(),?)
             ON DUPLICATE KEY UPDATE enviada=1, enviada_en=NOW()"
        )->execute([$pol['poliza_id'], $pol['asesor_id'], $dias, $fechaObj]);

        // Registrar en historial del cliente (para el coordinador)
        if ($pol['cliente_id']) {
            try {
                $db->prepare(
                    "INSERT INTO historial_cliente (cliente_id, agente_id, tipo, titulo, descripcion, fecha)
                     VALUES (?,?,'nota',?,?,NOW())"
                )->execute([
                    $pol['cliente_id'],
                    $pol['asesor_id'],
                    "🔔 Recordatorio renovación ({$diaTexto})",
                    $cuerpo,
                ]);
            } catch (\Throwable) {}
        }

        echo "[OK] Notificado asesor #{$pol['asesor_id']} — {$diaTexto} — póliza #{$pol['poliza_id']}\n";
    }

    echo "[{$dias}d] Procesadas " . count($polizas) . " pólizas.\n";
}

echo "[" . date('Y-m-d H:i:s') . "] Cron finalizado.\n";