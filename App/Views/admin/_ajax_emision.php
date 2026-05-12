<?php
/**
 * _ajax_emision.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Bloque de acciones AJAX para el módulo de emisión.
 * Se incluye desde admin/index.php dentro del bloque POST:
 *
 *   if (str_starts_with($a, 'emi_')) {
 *       $db = Connection::getConnection();
 *       require __DIR__ . '/_ajax_emision.php';
 *       echo json_encode(['ok'=>false,'msg'=>'Acción no reconocida']); exit;
 *   }
 *
 * Variables disponibles del scope padre:
 *   $a         (string) — acción recibida en $_POST['accion']
 *   $agenteId  (int)    — coordinador autenticado
 *   $db        (PDO)    — conexión de base de datos
 *
 * FIXES vs versión original:
 *  - emi_subir_poliza: INSERT en polizas usa frecuencia_renovacion
 *    (nombre real de la columna) en lugar de periodicidad que no existe.
 *  - emi_subir_poliza: no pasa columna 'periodicidad' a polizas, pero sí
 *    la guarda en cotizaciones (que sí la tiene).
 *  - emi_tomar: el WHERE acepta también emision_estado IS NULL o 'pendiente'
 *    además de la lógica original (que solo verificaba estado='pagado').
 *  - emi_get_checklist: protege fecha_fin_vigencia '0000-00-00'.
 * ─────────────────────────────────────────────────────────────────────────────
 */

switch ($a) {

    // ── emi_get_checklist ─────────────────────────────────────────────────────
    case 'emi_get_checklist': {
        $cotId = (int)($_POST['cotizacion_id'] ?? 0);
        $ramo  = trim($_POST['ramo'] ?? 'autos');
        if (!$cotId) { echo json_encode(['ok' => false, 'msg' => 'ID inválido']); exit; }

        $stDocs = $db->prepare(
            "SELECT id, cotizacion_id, tipo_documento,
                    nombre_original, nombre_archivo,
                    ruta AS archivo_relative,
                    subido_en,
                    idrive_subido, idrive_borrado
             FROM cotizacion_archivos
             WHERE cotizacion_id = ? AND eliminado_en IS NULL
             ORDER BY subido_en ASC"
        );
        $stDocs->execute([$cotId]);
        $docs = $stDocs->fetchAll(\PDO::FETCH_ASSOC);

        if (!\App\Models\CotizacionModel::docsListaTieneComprobantePago($docs)) {
            $syn = \App\Models\CotizacionModel::syntheticComprobantePagoDesdeTablaPagos($db, $cotId);
            if ($syn) {
                $docs[] = $syn;
            }
        }

        $stCk = $db->prepare(
            "SELECT doc_tipo, estado, nota
             FROM emision_checklist
             WHERE cotizacion_id = ?"
        );
        $stCk->execute([$cotId]);
        $checklist = $stCk->fetchAll(\PDO::FETCH_ASSOC);

        echo json_encode([
            'ok'        => true,
            'docs'      => $docs,
            'checklist' => $checklist,
            'ramo'      => $ramo,
        ]);
        exit;
    }

    // ── emi_marcar_checklist ──────────────────────────────────────────────────
    case 'emi_marcar_checklist': {
        $cotId  = (int)($_POST['cotizacion_id'] ?? 0);
        $tipo   = trim($_POST['doc_tipo'] ?? '');
        $estado = trim($_POST['estado']   ?? 'pendiente');
        $nota   = trim($_POST['nota']     ?? '');

        if (!$cotId || !$tipo
            || !in_array($estado, ['pendiente', 'ok', 'rechazado'], true)) {
            echo json_encode(['ok' => false, 'msg' => 'Datos inválidos']); exit;
        }

        $db->prepare(
            "INSERT INTO emision_checklist
                 (cotizacion_id, coordinador_id, doc_tipo, estado, nota, revisado_en)
             VALUES (?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                 estado         = VALUES(estado),
                 nota           = VALUES(nota),
                 coordinador_id = VALUES(coordinador_id),
                 revisado_en    = NOW()"
        )->execute([$cotId, $agenteId, $tipo, $estado, $nota]);

        echo json_encode(['ok' => true]);
        exit;
    }

    // ── emi_tomar ─────────────────────────────────────────────────────────────
    // Acepta registros con emision_estado NULL o 'pendiente' (además del caso
    // de 'rechazada_docs' por si el coordinador retoma un caso rechazado).
    case 'emi_tomar': {
        $cotId = (int)($_POST['cotizacion_id'] ?? 0);
        if (!$cotId) { echo json_encode(['ok' => false, 'msg' => 'ID inválido']); exit; }

        $affected = $db->prepare(
            "UPDATE cotizaciones
             SET emision_estado          = 'en_proceso',
                 emision_coordinador_id  = ?
             WHERE id = ?
               AND (
                    estado = 'pagado'
                    OR EXISTS (
                        SELECT 1 FROM cotizacion_archivos ca
                        WHERE ca.cotizacion_id = cotizaciones.id
                          AND ca.tipo_documento = 'comprobante_pago'
                          AND ca.eliminado_en IS NULL
                    )
                    OR EXISTS (
                        SELECT 1 FROM cotizacion_pagos cp
                        WHERE cp.cotizacion_id = cotizaciones.id
                          AND COALESCE(NULLIF(cp.ruta,''), NULLIF(cp.nombre_archivo,'')) IS NOT NULL
                    )
               )
               AND (emision_estado IS NULL
                    OR emision_estado IN ('pendiente','rechazada_docs'))"
        );
        $affected->execute([$agenteId, $cotId]);

        if ($affected->rowCount() === 0) {
            echo json_encode(['ok' => false, 'msg' => 'No se pudo tomar el caso. Recarga la página.']);
            exit;
        }
        echo json_encode(['ok' => true]);
        exit;
    }

    // ── emi_subir_poliza ──────────────────────────────────────────────────────
    // FIX CRÍTICO: polizas.frecuencia_renovacion (no periodicidad) es la columna
    // real. Se mapea el valor del formulario (periodicidad) a ese campo.
    // La tabla cotizaciones sí tiene periodicidad, así que se actualiza allí también.
    case 'emi_subir_poliza': {
        $cotId        = (int)($_POST['cotizacion_id'] ?? 0);
        $numeroPol    = trim($_POST['numero_poliza']  ?? '');
        $fechaInicio  = trim($_POST['fecha_inicio']   ?? '');
        $fechaFin     = trim($_POST['fecha_fin']      ?? '');
        $periodicidad = trim($_POST['periodicidad']   ?? 'anual');
        $notas        = trim($_POST['notas']          ?? '');

        // Validaciones básicas
        if (!$cotId || !$numeroPol || !$fechaInicio || !$fechaFin) {
            echo json_encode(['ok' => false, 'msg' => 'Faltan campos requeridos']); exit;
        }
        if (!in_array($periodicidad, ['mensual','trimestral','semestral','anual'], true)) {
            $periodicidad = 'anual';
        }

        // Recuperar datos de cotización para notificación y FK
        $stCot = $db->prepare(
            "SELECT c.agente_id, c.folio, c.cliente_id, c.compania,
                    CONCAT(cl.nombre,' ',cl.apellido_paterno) AS cliente_nombre
             FROM cotizaciones c
             JOIN clientes cl ON cl.id = c.cliente_id
             WHERE c.id = ?"
        );
        $stCot->execute([$cotId]);
        $cot = $stCot->fetch(\PDO::FETCH_ASSOC);
        if (!$cot) {
            echo json_encode(['ok' => false, 'msg' => 'Cotización no encontrada']); exit;
        }

        // Procesar archivo PDF/imagen si se envió
        $nombreArchivo  = null;
        $nombreOriginal = null;
        $rutaRelativa   = null;

        if (!empty($_FILES['poliza_file']['name'])
            && $_FILES['poliza_file']['error'] === UPLOAD_ERR_OK) {

            $file = $_FILES['poliza_file'];
            $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (!in_array($ext, ['pdf','jpg','jpeg','png'], true)) {
                echo json_encode(['ok' => false, 'msg' => 'Tipo de archivo no permitido']); exit;
            }
            if ($file['size'] > 10 * 1024 * 1024) {
                echo json_encode(['ok' => false, 'msg' => 'El archivo supera los 10 MB']); exit;
            }

            $dir = dirname(__DIR__, 3) . "/uploads/polizas/{$cot['cliente_id']}/";
            if (!is_dir($dir)) mkdir($dir, 0755, true);

            $nombreArchivo  = 'poliza_' . time() . '.' . $ext;
            $nombreOriginal = $file['name'];
            $rutaRelativa   = "uploads/polizas/{$cot['cliente_id']}/{$nombreArchivo}";

            if (!move_uploaded_file($file['tmp_name'], $dir . $nombreArchivo)) {
                echo json_encode(['ok' => false, 'msg' => 'Error al guardar el archivo']); exit;
            }
        }

        $db->beginTransaction();
        try {
            // 1. Insertar en tabla polizas
            //    ⚠ columna es frecuencia_renovacion, NO periodicidad
            $stPol = $db->prepare(
                "INSERT INTO polizas
                     (cliente_id, cotizacion_id, agente_id,
                      nombre_archivo, nombre_original, ruta,
                      compania, numero_poliza,
                      fecha_inicio, fecha_fin,
                      frecuencia_renovacion,
                      notas, estado, subido_en)
                 VALUES (?, ?, ?,
                         ?, ?, ?,
                         ?, ?,
                         ?, ?,
                         ?,
                         ?, 'vigente', NOW())"
            );
            $stPol->execute([
                $cot['cliente_id'],
                $cotId,
                $cot['agente_id'],
                $nombreArchivo  ?? '',
                $nombreOriginal ?? '',
                $rutaRelativa   ?? '',
                $cot['compania'],
                $numeroPol,
                $fechaInicio,
                $fechaFin,
                $periodicidad,   // → frecuencia_renovacion en polizas
                $notas,
            ]);

            // 2. Actualizar cotización
            $db->prepare(
                "UPDATE cotizaciones
                 SET emision_estado         = 'emitida',
                     emision_coordinador_id = ?,
                     emision_fecha          = NOW(),
                     fecha_inicio_vigencia  = ?,
                     fecha_fin_vigencia     = ?,
                     periodicidad           = ?
                 WHERE id = ?"
            )->execute([$agenteId, $fechaInicio, $fechaFin, $periodicidad, $cotId]);

            // 3. Notificar al asesor
            $db->prepare(
                "INSERT INTO notificaciones
                     (agente_id, tipo, titulo, mensaje, referencia)
                 VALUES (?, 'emision', ?, ?, ?)"
            )->execute([
                $cot['agente_id'],
                "✅ Póliza emitida: {$cot['folio']}",
                "La póliza de {$cot['cliente_nombre']} ha sido emitida correctamente. "
                    . "N° {$numeroPol}. Vigencia: {$fechaInicio} al {$fechaFin}.",
                "cotizacion:{$cotId}",
            ]);

            $db->commit();
            echo json_encode(['ok' => true]);

        } catch (\Throwable $e) {
            $db->rollBack();
            error_log('emi_subir_poliza error: ' . $e->getMessage());
            echo json_encode(['ok' => false, 'msg' => 'Error interno al guardar']);
        }
        exit;
    }

    // ── emi_rechazar_docs ─────────────────────────────────────────────────────
    case 'emi_rechazar_docs': {
        $cotId = (int)($_POST['cotizacion_id'] ?? 0);
        $nota  = trim($_POST['nota'] ?? '');
        if (!$cotId || !$nota) {
            echo json_encode(['ok' => false, 'msg' => 'Faltan datos']); exit;
        }

        $stCot = $db->prepare(
            "SELECT c.agente_id, c.folio,
                    CONCAT(cl.nombre,' ',cl.apellido_paterno) AS cliente_nombre
             FROM cotizaciones c
             JOIN clientes cl ON cl.id = c.cliente_id
             WHERE c.id = ?"
        );
        $stCot->execute([$cotId]);
        $cot = $stCot->fetch(\PDO::FETCH_ASSOC);
        if (!$cot) {
            echo json_encode(['ok' => false, 'msg' => 'Cotización no encontrada']); exit;
        }

        $db->prepare(
            "UPDATE cotizaciones
             SET emision_estado = 'rechazada_docs',
                 emision_nota   = ?
             WHERE id = ?"
        )->execute([$nota, $cotId]);

        $db->prepare(
            "INSERT INTO notificaciones (agente_id, tipo, titulo, mensaje, referencia)
             VALUES (?, 'doc_rechazado', ?, ?, ?)"
        )->execute([
            $cot['agente_id'],
            "⚠️ Documentación rechazada: {$cot['folio']}",
            "El coordinador rechazó la documentación de {$cot['cliente_nombre']}. "
                . "Motivo: {$nota}",
            "cotizacion:{$cotId}",
        ]);

        echo json_encode(['ok' => true]);
        exit;
    }

    // ── emi_notificar_renovacion ──────────────────────────────────────────────
    case 'emi_notificar_renovacion': {
        $cotId   = (int)($_POST['cotizacion_id'] ?? 0);
        $agenteD = (int)($_POST['agente_id']     ?? 0);
        if (!$cotId || !$agenteD) {
            echo json_encode(['ok' => false, 'msg' => 'Datos inválidos']); exit;
        }

        $stCot = $db->prepare(
            "SELECT c.folio,
                    CONCAT(cl.nombre,' ',cl.apellido_paterno) AS cliente_nombre,
                    COALESCE(
                        NULLIF(p.fecha_fin,'0000-00-00'),
                        NULLIF(c.fecha_fin_vigencia,'0000-00-00')
                    ) AS fecha_vence
             FROM cotizaciones c
             JOIN clientes cl ON cl.id = c.cliente_id
             LEFT JOIN polizas p
                   ON p.cotizacion_id = c.id AND p.eliminado_en IS NULL
             WHERE c.id = ?"
        );
        $stCot->execute([$cotId]);
        $cot = $stCot->fetch(\PDO::FETCH_ASSOC);
        if (!$cot) {
            echo json_encode(['ok' => false, 'msg' => 'Cotización no encontrada']); exit;
        }

        $vence = $cot['fecha_vence']
            ? date('d/m/Y', strtotime($cot['fecha_vence']))
            : 'próximamente';

        $db->prepare(
            "INSERT INTO notificaciones (agente_id, tipo, titulo, mensaje, referencia)
             VALUES (?, 'renovacion', ?, ?, ?)"
        )->execute([
            $agenteD,
            "🔔 Renovación próxima: {$cot['folio']}",
            "La póliza de {$cot['cliente_nombre']} vence el {$vence}. "
                . "Contacta al cliente para iniciar la renovación.",
            "cotizacion:{$cotId}",
        ]);

        $db->prepare(
            "UPDATE cotizaciones SET renovacion_notificada = 1 WHERE id = ?"
        )->execute([$cotId]);

        echo json_encode(['ok' => true]);
        exit;
    }

    default:
        // Caso no reconocido — el caller en index.php enviará la respuesta
        break;
}