<?php
/**
 * _ajax_emision.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Manejador AJAX para el módulo de Emisión.
 * Es requerido (require) desde admin/index.php cuando la acción empieza con "emi_".
 * Variables disponibles del contexto padre: $db, $agenteId, $isSuperadmin
 * ─────────────────────────────────────────────────────────────────────────────
 */

use App\Models\NotificacionModel;

$accion = $_POST['accion'] ?? '';

switch ($accion) {

    // ── Ver documentos de una cotización (solo lectura) ───────────────────────
    case 'emi_get_docs': {
        $cotId = (int)($_POST['cotizacion_id'] ?? 0);
        if (!$cotId) { echo json_encode(['ok'=>false,'msg'=>'ID inválido.']); exit; }

        $st = $db->prepare(
            "SELECT c.ramo, c.compania, c.tipo_cobertura,
                    CONCAT(cl.nombre,' ',cl.apellido_paterno) AS cliente_nombre,
                    CONCAT(a.nombre,' ',a.apellido)           AS asesor_nombre
             FROM cotizaciones c
             JOIN clientes cl ON cl.id = c.cliente_id
             JOIN agentes   a ON a.id  = c.agente_id
             WHERE c.id = ?"
        );
        $st->execute([$cotId]);
        $cot = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$cot) { echo json_encode(['ok'=>false,'msg'=>'Cotización no encontrada.']); exit; }

        $stDocs = $db->prepare(
            "SELECT tipo_documento, nombre_original, nombre_archivo, subido_en, idrive_subido, idrive_borrado
             FROM cotizacion_archivos
             WHERE cotizacion_id = ? AND eliminado_en IS NULL
             ORDER BY subido_en ASC"
        );
        $stDocs->execute([$cotId]);
        $archivos = $stDocs->fetchAll(\PDO::FETCH_ASSOC);

        $docs = array_map(function ($a) use ($cotId) {
            $ext       = strtolower(pathinfo($a['nombre_archivo'], PATHINFO_EXTENSION));
            $esImg     = in_array($ext, ['jpg','jpeg','png','webp'], true);
            $rutaFis   = dirname(__DIR__, 3) . '/uploads/cotizaciones/' . $cotId . '/' . $a['nombre_archivo'];
            $dispLocal = ($a['idrive_borrado'] === null) && file_exists($rutaFis);
            return [
                'tipo'        => $a['tipo_documento'],
                'nombre_orig' => $a['nombre_original'],
                'url_local'   => $dispLocal ? '/archivo?file=cotizaciones/'.$cotId.'/'.$a['nombre_archivo'] : null,
                'en_idrive'   => !empty($a['idrive_subido']),
                'es_imagen'   => $esImg,
                'es_pdf'      => $ext === 'pdf',
            ];
        }, $archivos);

        echo json_encode(array_merge(['ok'=>true, 'docs'=>$docs], $cot));
        exit;
    }

    // ── Obtener checklist de documentos ──────────────────────────────────────
    case 'emi_get_checklist': {
        $cotId = (int)($_POST['cotizacion_id'] ?? 0);
        $ramo  = trim($_POST['ramo'] ?? 'autos');
        if (!$cotId) { echo json_encode(['ok'=>false,'msg'=>'ID inválido.']); exit; }

        // Documentos subidos por el asesor
        $stDocs = $db->prepare(
            "SELECT tipo_documento, nombre_original, nombre_archivo, cotizacion_id,
                    ruta, idrive_subido, idrive_borrado
             FROM cotizacion_archivos
             WHERE cotizacion_id = ? AND eliminado_en IS NULL
             ORDER BY subido_en ASC"
        );
        $stDocs->execute([$cotId]);
        $docs = $stDocs->fetchAll(\PDO::FETCH_ASSOC);

        // Añadir alias archivo_relative para la URL
        $docs = array_map(function($d) {
            $d['archivo_relative'] = $d['ruta'] ?? '';
            return $d;
        }, $docs);

        // Estado de checklist marcado por coordinador
        $stCk = $db->prepare(
            "SELECT doc_tipo, estado, nota
             FROM emi_checklist
             WHERE cotizacion_id = ?
             ORDER BY actualizado_en DESC"
        );
        try {
            $stCk->execute([$cotId]);
            $checklist = $stCk->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            // Si la tabla no existe aún, devolver vacío
            $checklist = [];
        }

        // Comprobante de pago del coordinador (si ya fue subido)
        $stComp = $db->prepare(
            "SELECT id, nombre_archivo, nombre_original, metodo_pago, monto
             FROM cotizacion_comprobantes_pago
             WHERE cotizacion_id = ? AND eliminado_en IS NULL
             ORDER BY creado_en DESC LIMIT 1"
        );
        $stComp->execute([$cotId]);
        $comprobante = $stComp->fetch(\PDO::FETCH_ASSOC);

        // Si hay comprobante del coordinador, incluirlo como doc virtual para el checklist
        if ($comprobante) {
            $docs[] = [
                'tipo_documento'   => 'comprobante_pago',
                'nombre_original'  => $comprobante['nombre_original'] ?? 'comprobante_coordinador',
                'nombre_archivo'   => $comprobante['nombre_archivo'] ?? '',
                'cotizacion_id'    => $cotId,
                'ruta'             => 'comprobantes/' . $cotId . '/' . $comprobante['nombre_archivo'],
                'archivo_relative' => 'comprobantes/' . $cotId . '/' . $comprobante['nombre_archivo'],
                'idrive_subido'    => null,
                'idrive_borrado'   => null,
                'es_de_coordinador'=> true,
            ];
            // Auto-marcar comprobante_pago como ok si existe
            $yaMarcado = !empty(array_filter($checklist, fn($c) => $c['doc_tipo'] === 'comprobante_pago'));
            if (!$yaMarcado) {
                try {
                    $db->prepare(
                        "INSERT INTO emi_checklist (cotizacion_id, doc_tipo, estado, nota, actualizado_en)
                         VALUES (?,?,?,?,NOW())
                         ON DUPLICATE KEY UPDATE estado='ok', nota='Comprobante subido por coordinador', actualizado_en=NOW()"
                    )->execute([$cotId, 'comprobante_pago', 'ok', 'Comprobante subido por coordinador']);
                    // Recargar checklist
                    $stCk->execute([$cotId]);
                    $checklist = $stCk->fetchAll(\PDO::FETCH_ASSOC);
                } catch (\Throwable $e) {
                    error_log('[emi_get_checklist auto-mark] ' . $e->getMessage());
                }
            }
        }

        echo json_encode(['ok'=>true, 'docs'=>$docs, 'checklist'=>$checklist, 'ramo'=>$ramo]);
        exit;
    }

    // ── Marcar documento del checklist ────────────────────────────────────────
    case 'emi_marcar_checklist': {
        $cotId  = (int)($_POST['cotizacion_id'] ?? 0);
        $tipo   = trim($_POST['doc_tipo'] ?? '');
        $estado = trim($_POST['estado'] ?? '');
        $nota   = trim($_POST['nota'] ?? '');
        $estados = ['ok','rechazado','pendiente'];

        if (!$cotId || !$tipo || !in_array($estado, $estados, true)) {
            echo json_encode(['ok'=>false,'msg'=>'Datos inválidos.']); exit;
        }

        try {
            $db->prepare(
                "INSERT INTO emi_checklist (cotizacion_id, doc_tipo, estado, nota, actualizado_en)
                 VALUES (?,?,?,?,NOW())
                 ON DUPLICATE KEY UPDATE estado=VALUES(estado), nota=VALUES(nota), actualizado_en=NOW()"
            )->execute([$cotId, $tipo, $estado, $nota ?: null]);
            echo json_encode(['ok'=>true]);
        } catch (\Throwable $e) {
            // Si la tabla no existe, intentar crearla
            try {
                $db->exec(
                    "CREATE TABLE IF NOT EXISTS emi_checklist (
                        id              INT AUTO_INCREMENT PRIMARY KEY,
                        cotizacion_id   INT NOT NULL,
                        doc_tipo        VARCHAR(60) NOT NULL,
                        estado          ENUM('pendiente','ok','rechazado') DEFAULT 'pendiente',
                        nota            TEXT,
                        actualizado_en  DATETIME DEFAULT CURRENT_TIMESTAMP,
                        UNIQUE KEY uq_cot_tipo (cotizacion_id, doc_tipo)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
                );
                $db->prepare(
                    "INSERT INTO emi_checklist (cotizacion_id, doc_tipo, estado, nota, actualizado_en)
                     VALUES (?,?,?,?,NOW())
                     ON DUPLICATE KEY UPDATE estado=VALUES(estado), nota=VALUES(nota), actualizado_en=NOW()"
                )->execute([$cotId, $tipo, $estado, $nota ?: null]);
                echo json_encode(['ok'=>true]);
            } catch (\Throwable $e2) {
                echo json_encode(['ok'=>false,'msg'=>'Error BD: '.$e2->getMessage()]);
            }
        }
        exit;
    }

    // ── Tomar caso (marcar en proceso) ────────────────────────────────────────
    case 'emi_tomar': {
        $cotId = (int)($_POST['cotizacion_id'] ?? 0);
        if (!$cotId) { echo json_encode(['ok'=>false,'msg'=>'ID inválido.']); exit; }

        $db->prepare("UPDATE cotizaciones SET emision_estado='en_proceso' WHERE id=?")
           ->execute([$cotId]);

        // Registrar en historial
        try {
            $db->prepare(
                "INSERT INTO historial_coordinador (coordinador_id, tipo, titulo, descripcion, cotizacion_id)
                 VALUES (?,?,?,?,?)"
            )->execute([$agenteId, 'emision', '📋 Caso tomado', 'El coordinador tomó el caso para procesarlo.', $cotId]);
        } catch (\Throwable $e) {}

        echo json_encode(['ok'=>true]);
        exit;
    }

    // ── Rechazar documentación ────────────────────────────────────────────────
    case 'emi_rechazar_docs': {
        $cotId  = (int)($_POST['cotizacion_id'] ?? 0);
        $motivo = trim($_POST['nota'] ?? '');
        if (!$cotId || !$motivo) { echo json_encode(['ok'=>false,'msg'=>'Datos inválidos.']); exit; }

        $db->prepare("UPDATE cotizaciones SET emision_estado='rechazado', estado='pendiente' WHERE id=?")
           ->execute([$cotId]);

        // Obtener asesor
        $st = $db->prepare("SELECT agente_id, folio, cliente_id FROM cotizaciones WHERE id=?");
        $st->execute([$cotId]);
        $cot = $st->fetch(\PDO::FETCH_ASSOC);

        if ($cot) {
            // Notificar al asesor
            _emi_notificar(
                db: $db,
                asesorId: (int)$cot['agente_id'],
                titulo: '⚠️ Documentación rechazada — ' . ($cot['folio'] ?? '#'.$cotId),
                cuerpo: 'El coordinador rechazó los documentos de la cotización ' . ($cot['folio'] ?? '#'.$cotId) . '. Motivo: ' . $motivo,
                urgente: true,
                color: 'red',
                url: '/cliente/' . ($cot['cliente_id'] ?? ''),
            );

            // Historial cliente
            try {
                $db->prepare(
                    "INSERT INTO historial_cliente (cliente_id, agente_id, tipo, titulo, descripcion)
                     VALUES (?,?,?,?,?)"
                )->execute([
                    $cot['cliente_id'] ?? 0, $agenteId, 'nota',
                    '⚠️ Documentos rechazados',
                    'Motivo: ' . $motivo,
                ]);
            } catch (\Throwable $e) {}
        }

        echo json_encode(['ok'=>true]);
        exit;
    }

    // ── Subir póliza ──────────────────────────────────────────────────────────
    case 'emi_subir_poliza': {
        $cotId       = (int)($_POST['cotizacion_id'] ?? 0);
        $numero      = trim($_POST['numero_poliza']  ?? '');
        $fechaInicio = trim($_POST['fecha_inicio']   ?? '');
        $fechaFin    = trim($_POST['fecha_fin']      ?? '');
        $periodicidad= trim($_POST['periodicidad']   ?? 'anual');
        $notas       = trim($_POST['notas']          ?? '');

        if (!$cotId || !$numero || !$fechaInicio || !$fechaFin) {
            echo json_encode(['ok'=>false,'msg'=>'Faltan campos obligatorios.']); exit;
        }

        // Obtener datos de la cotización
        $st = $db->prepare(
            "SELECT c.agente_id, c.cliente_id, c.ramo, c.compania, c.folio,
                    CONCAT(cl.nombre,' ',cl.apellido_paterno) AS cliente_nombre
             FROM cotizaciones c
             JOIN clientes cl ON cl.id = c.cliente_id
             WHERE c.id = ?"
        );
        $st->execute([$cotId]);
        $cot = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$cot) { echo json_encode(['ok'=>false,'msg'=>'Cotización no encontrada.']); exit; }

        // Verificar que el asesor ya envió esta cotización al coordinador
        $stEst = $db->prepare("SELECT emision_estado FROM cotizaciones WHERE id=?");
        $stEst->execute([$cotId]);
        $emisEst = $stEst->fetchColumn();
        if ($emisEst === 'pendiente') {
            echo json_encode(['ok'=>false,'msg'=>'El asesor aún no ha enviado esta cotización al coordinador. Pídele que la envíe primero.']); exit;
        }

        // Subir archivo si viene
        $nombreArch = null;
        $nombreOrig = null;
        if (!empty($_FILES['poliza_file']['name']) && $_FILES['poliza_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['poliza_file'];
            $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['pdf','jpg','jpeg','png','webp'], true)) {
                echo json_encode(['ok'=>false,'msg'=>'Tipo de archivo no permitido.']); exit;
            }
            $dir = dirname(__DIR__, 3) . '/uploads/polizas/' . $cotId . '/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $nombreArch = 'poliza_' . time() . '.' . $ext;
            $nombreOrig = basename($file['name']);
            if (!move_uploaded_file($file['tmp_name'], $dir . $nombreArch)) {
                echo json_encode(['ok'=>false,'msg'=>'Error al guardar el archivo.']); exit;
            }
        }

        // Insertar o actualizar en tabla polizas
        $stExist = $db->prepare("SELECT id FROM polizas WHERE cotizacion_id=? AND eliminado_en IS NULL LIMIT 1");
        $stExist->execute([$cotId]);
        $polizaExist = $stExist->fetch(\PDO::FETCH_ASSOC);

        if ($polizaExist) {
            $upd = $db->prepare(
                "UPDATE polizas SET numero_poliza=?, fecha_inicio=?, fecha_fin=?, periodicidad=?,
                 notas=?, nombre_archivo=COALESCE(?,nombre_archivo),
                 nombre_original=COALESCE(?,nombre_original), subido_en=NOW()
                 WHERE id=?"
            );
            $upd->execute([$numero, $fechaInicio, $fechaFin, $periodicidad,
                           $notas ?: null, $nombreArch, $nombreOrig, $polizaExist['id']]);
        } else {
            $ins = $db->prepare(
                "INSERT INTO polizas
                 (cotizacion_id, agente_id, cliente_id, compania, numero_poliza,
                  fecha_inicio, fecha_fin, periodicidad, notas, nombre_archivo, nombre_original, subido_en)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())"
            );
            $ins->execute([
                $cotId, $cot['agente_id'], $cot['cliente_id'],
                $cot['compania'], $numero,
                $fechaInicio, $fechaFin, $periodicidad, $notas ?: null,
                $nombreArch, $nombreOrig,
            ]);
        }

        // Marcar cotización como emitida
        $db->prepare(
            "UPDATE cotizaciones SET emision_estado='emitida', estado=CASE WHEN estado_pago='confirmado' THEN 'pagado' ELSE estado END WHERE id=?"
        )->execute([$cotId]);

        // Notificar al asesor
        _emi_notificar(
            db: $db,
            asesorId: (int)$cot['agente_id'],
            titulo: '📄 Póliza emitida — ' . ($cot['folio'] ?? '#'.$cotId),
            cuerpo: 'La póliza ' . $numero . ' de ' . $cot['compania'] . ' fue emitida para '
                   . $cot['cliente_nombre'] . '. Vigencia: ' . $fechaInicio . ' al ' . $fechaFin . '.',
            urgente: false,
            color: 'green',
            url: '/cliente/' . $cot['cliente_id'],
        );

        // Historial cliente
        try {
            $db->prepare(
                "INSERT INTO historial_cliente (cliente_id, agente_id, tipo, titulo, descripcion)
                 VALUES (?,?,?,?,?)"
            )->execute([
                $cot['cliente_id'], $agenteId, 'venta',
                '📄 Póliza emitida — ' . $numero,
                'Compañía: ' . $cot['compania'] . ' | Vigencia: ' . $fechaInicio . ' — ' . $fechaFin,
            ]);
        } catch (\Throwable $e) {}

        echo json_encode(['ok'=>true]);
        exit;
    }

    // ── Notificar renovación ──────────────────────────────────────────────────
    case 'emi_notificar_renovacion': {
        $cotId    = (int)($_POST['cotizacion_id'] ?? 0);
        $asesorId = (int)($_POST['agente_id']     ?? 0);
        if (!$cotId || !$asesorId) { echo json_encode(['ok'=>false,'msg'=>'Datos inválidos.']); exit; }

        $st = $db->prepare("SELECT folio, compania FROM cotizaciones WHERE id=?");
        $st->execute([$cotId]);
        $cot = $st->fetch(\PDO::FETCH_ASSOC);

        _emi_notificar(
            db: $db,
            asesorId: $asesorId,
            titulo: '🔄 Renovación pendiente — ' . ($cot['folio'] ?? '#'.$cotId),
            cuerpo: 'El coordinador te notifica que la póliza ' . ($cot['folio'] ?? '#'.$cotId)
                   . ' de ' . ($cot['compania'] ?? '') . ' requiere gestión de renovación.',
            urgente: true,
            color: 'orange',
        );

        echo json_encode(['ok'=>true]);
        exit;
    }

    // ── Registrar comprobante de pago (subido por el coordinador) ─────────────
    case 'emi_registrar_comprobante': {
        $cotId    = (int)($_POST['cotizacion_id'] ?? 0);
        $asesorId = (int)($_POST['asesor_id']     ?? 0);
        $metodo   = trim($_POST['metodo_pago']    ?? 'efectivo');
        $banco    = trim($_POST['banco']          ?? '');
        $monto    = trim($_POST['monto']          ?? '');
        $ref      = trim($_POST['referencia']     ?? '');
        $coments  = trim($_POST['comentarios']    ?? '');

        $metodosValidos = ['efectivo','tarjeta_credito','tarjeta_debito','transferencia',
                           'msi_3','msi_6','msi_12','msi_18','deposito_banco'];
        if (!$cotId || !in_array($metodo, $metodosValidos, true)) {
            echo json_encode(['ok'=>false,'msg'=>'Datos inválidos.']); exit;
        }
        // Verificar que el asesor ya envió esta cotización
        $stEst2 = $db->prepare("SELECT emision_estado FROM cotizaciones WHERE id=?");
        $stEst2->execute([$cotId]);
        $emisEst2 = $stEst2->fetchColumn();
        if ($emisEst2 === 'pendiente') {
            echo json_encode(['ok'=>false,'msg'=>'El asesor aún no ha enviado esta cotización al coordinador.']); exit;
        }
        if ($monto === '' || !is_numeric($monto) || (float)$monto <= 0) {
            echo json_encode(['ok'=>false,'msg'=>'El monto es obligatorio y debe ser mayor a $0.']); exit;
        }
        if ($metodo !== 'efectivo' && empty($_FILES['comprobante']['name'])) {
            echo json_encode(['ok'=>false,'msg'=>'Debes adjuntar el PDF o imagen del comprobante.']); exit;
        }

        // Manejar archivo adjunto
        $nombreArch  = null;
        $nombreOrig  = null;
        if (!empty($_FILES['comprobante']['name'])) {
            $file  = $_FILES['comprobante'];
            $ext   = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allow = ['pdf','jpg','jpeg','png','webp'];
            if (!in_array($ext, $allow, true)) {
                echo json_encode(['ok'=>false,'msg'=>'Tipo de archivo no permitido.']); exit;
            }
            $dir = dirname(__DIR__, 3) . '/uploads/comprobantes/' . $cotId . '/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $nombreArch = 'comprobante_' . $agenteId . '_' . time() . '.' . $ext;
            $nombreOrig = basename($file['name']);
            if (!move_uploaded_file($file['tmp_name'], $dir . $nombreArch)) {
                echo json_encode(['ok'=>false,'msg'=>'Error al guardar el archivo.']); exit;
            }
        }

        // Insertar comprobante
        $ins = $db->prepare(
            "INSERT INTO cotizacion_comprobantes_pago
             (cotizacion_id, coordinador_id, metodo_pago, banco, referencia, monto, comentarios, nombre_archivo, nombre_original)
             VALUES (?,?,?,?,?,?,?,?,?)"
        );
        $ins->execute([
            $cotId, $agenteId, $metodo,
            $banco ?: null,
            $ref   ?: null,
            $monto !== '' ? (float)$monto : null,
            $coments ?: null,
            $nombreArch,
            $nombreOrig,
        ]);
        $comprobanteId = $db->lastInsertId();

        // Actualizar estado de pago en cotizacion + marcarla como pagado
        $db->prepare(
            "UPDATE cotizaciones SET estado_pago='confirmado', comprobante_pago_id=?, estado='pagado' WHERE id=?"
        )->execute([$comprobanteId, $cotId]);

        // Obtener datos completos de cotización para notificaciones
        $stCot = $db->prepare(
            "SELECT c.folio, c.compania, c.agente_id AS cot_agente_id, c.cliente_id,
                    CONCAT(cl.nombre,' ',cl.apellido_paterno) AS cliente_nombre
             FROM cotizaciones c
             JOIN clientes cl ON cl.id = c.cliente_id
             WHERE c.id = ?"
        );
        $stCot->execute([$cotId]);
        $datosCot = $stCot->fetch(\PDO::FETCH_ASSOC);

        // Si no se pasó asesorId, obtenerlo de la cotización
        if (!$asesorId && !empty($datosCot['cot_agente_id'])) {
            $asesorId = (int)$datosCot['cot_agente_id'];
        }

        // Actualizar estado de la PÓLIZA vinculada (si existe)
        $stPol = $db->prepare(
            "SELECT id FROM polizas WHERE cotizacion_id=? AND eliminado_en IS NULL LIMIT 1"
        );
        $stPol->execute([$cotId]);
        $polizaRow = $stPol->fetch(\PDO::FETCH_ASSOC);
        if ($polizaRow) {
            $db->prepare(
                "UPDATE polizas SET estado_pago='confirmado', pago_confirmado_en=NOW(),
                 notas=CONCAT(IFNULL(notas,''),?) WHERE id=?"
            )->execute([
                "\n[" . date('Y-m-d H:i') . "] Pago confirmado por coordinador — Método: " . _emi_metodoLabel($metodo) . ".",
                $polizaRow['id']
            ]);
        }

        // Auto-marcar comprobante_pago en checklist como ok
        try {
            $db->prepare(
                "INSERT INTO emi_checklist (cotizacion_id, doc_tipo, estado, nota, actualizado_en)
                 VALUES (?,?,?,?,NOW())
                 ON DUPLICATE KEY UPDATE estado='ok', nota='Comprobante subido por coordinador', actualizado_en=NOW()"
            )->execute([$cotId, 'comprobante_pago', 'ok', 'Comprobante subido por coordinador']);
        } catch (\Throwable $e) {
            // Si la tabla no existe, crearla
            try {
                $db->exec(
                    "CREATE TABLE IF NOT EXISTS emi_checklist (
                        id              INT AUTO_INCREMENT PRIMARY KEY,
                        cotizacion_id   INT NOT NULL,
                        doc_tipo        VARCHAR(60) NOT NULL,
                        estado          ENUM('pendiente','ok','rechazado') DEFAULT 'pendiente',
                        nota            TEXT,
                        actualizado_en  DATETIME DEFAULT CURRENT_TIMESTAMP,
                        UNIQUE KEY uq_cot_tipo (cotizacion_id, doc_tipo)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
                );
                $db->prepare(
                    "INSERT INTO emi_checklist (cotizacion_id, doc_tipo, estado, nota, actualizado_en)
                     VALUES (?,?,?,?,NOW())
                     ON DUPLICATE KEY UPDATE estado='ok', nota='Comprobante subido por coordinador', actualizado_en=NOW()"
                )->execute([$cotId, 'comprobante_pago', 'ok', 'Comprobante subido por coordinador']);
            } catch (\Throwable $e2) {
                error_log('[emi_checklist create] ' . $e2->getMessage());
            }
        }

        // Registrar en historial del coordinador
        try {
            $db->prepare(
                "INSERT INTO historial_coordinador (coordinador_id, tipo, titulo, descripcion, cotizacion_id, cliente_id)
                 VALUES (?,?,?,?,?,?)"
            )->execute([
                $agenteId, 'cobro',
                '💳 Comprobante de pago registrado',
                "Folio: {$datosCot['folio']} — Cliente: {$datosCot['cliente_nombre']} — Método: " . _emi_metodoLabel($metodo),
                $cotId,
                $datosCot['cliente_id'] ?? null,
            ]);
        } catch (\Throwable $e) {}

        // Registrar en historial del cliente
        try {
            $db->prepare(
                "INSERT INTO historial_cliente (cliente_id, agente_id, tipo, titulo, descripcion)
                 VALUES (?,?,?,?,?)"
            )->execute([
                $datosCot['cliente_id'] ?? 0, $agenteId, 'venta',
                '💰 Pago confirmado por coordinador — ' . ($datosCot['folio'] ?? '#'.$cotId),
                'Método: ' . _emi_metodoLabel($metodo) . ($ref ? " · Ref: {$ref}" : '') . ($monto ? " · $" . number_format((float)$monto, 2) : ''),
            ]);
            
            // ACTUALIZACIÓN EXTRA: Cambiar estado del cliente a "Cerrado" automáticamente
            if (!empty($datosCot['cliente_id'])) {
                $db->prepare("UPDATE clientes SET estado_cliente='cerrado' WHERE id=?")->execute([$datosCot['cliente_id']]);
            }
            
        } catch (\Throwable $he) { error_log('[historial_cliente cobro] ' . $he->getMessage()); }
        // Notificación al asesor: estado de póliza cambió a PAGADO
        if ($asesorId) {
            $urlCliente = '/cliente/' . ($datosCot['cliente_id'] ?? '');
            _emi_notificar(
                db: $db,
                asesorId: $asesorId,
                titulo: '✅ Póliza pagada — ' . ($datosCot['folio'] ?? '#'.$cotId),
                cuerpo: "El coordinador confirmó el pago de la póliza de {$datosCot['cliente_nombre']} "
                       . "({$datosCot['compania']} · Folio: {$datosCot['folio']}). "
                       . "Estado actualizado a PAGADO. Método: " . _emi_metodoLabel($metodo)
                       . ($monto ? " · $" . number_format((float)$monto, 2) : '') . ".",
                urgente: true,
                color: 'green',
                url: $urlCliente,
            );
            // Recordatorio de seguimiento post-pago
            _emi_notificar(
                db: $db,
                asesorId: $asesorId,
                titulo: '🔔 Contacta a ' . ($datosCot['cliente_nombre'] ?? 'tu cliente'),
                cuerpo: "La póliza de {$datosCot['cliente_nombre']} está activa y pagada. Recuerda dar seguimiento para renovación y agradecer la confianza.",
                urgente: false,
                color: 'blue',
                url: $urlCliente,
            );
        }

        echo json_encode(['ok'=>true,'comprobante_id'=>$comprobanteId]);
        exit;
    }

    // ── Obtener vista previa del comprobante de pago ──────────────────────────
    case 'emi_get_comprobante': {
        $id = (int)($_POST['comprobante_id'] ?? 0);
        if (!$id) { echo json_encode(['ok'=>false, 'msg'=>'ID de comprobante inválido.']); exit; }

        $st = $db->prepare("SELECT * FROM cotizacion_comprobantes_pago WHERE id=? AND eliminado_en IS NULL");
        $st->execute([$id]);
        $comp = $st->fetch(\PDO::FETCH_ASSOC);

        if (!$comp) {
            echo json_encode(['ok'=>false, 'msg'=>'El comprobante no existe o fue eliminado.']);
            exit;
        }

        // URL usando /archivo (la ruta correcta del FileController)
        $urlLocal = null;
        if (!empty($comp['nombre_archivo'])) {
            $rutaRel  = 'comprobantes/' . $comp['cotizacion_id'] . '/' . $comp['nombre_archivo'];
            $rutaFis  = dirname(__DIR__, 3) . '/uploads/' . $rutaRel;
            if (file_exists($rutaFis)) {
                $urlLocal = '/archivo?file=' . urlencode($rutaRel);
            }
        }

        echo json_encode([
            'ok'         => true,
            'comprobante' => [
                'metodo_pago' => $comp['metodo_pago'],
                'banco'       => $comp['banco'],
                'monto'       => $comp['monto'],
                'referencia'  => $comp['referencia'],
                'comentarios' => $comp['comentarios'],
                'url_local'   => $urlLocal,
                'creado_en'   => $comp['creado_en'] ? date('d/m/Y H:i', strtotime($comp['creado_en'])) : '—',
            ],
        ]);
        exit;
    }

    // ── Configurar ventana de edición (solo SA) ───────────────────────────────
    case 'emi_set_edit_window': {
        if (!$isSuperadmin) { echo json_encode(['ok'=>false,'msg'=>'Solo el superadmin puede hacer esto.']); exit; }
        $horas = max(0, (int)($_POST['horas'] ?? 24));
        try {
            $db->prepare(
                "INSERT INTO configuracion (clave, valor, descripcion)
                 VALUES ('emi_edit_window_horas', ?, 'Ventana de edición de comprobantes del coordinador (en horas)')
                 ON DUPLICATE KEY UPDATE valor=VALUES(valor)"
            )->execute([$horas]);
            // Registrar en historial
            $db->prepare(
                "INSERT INTO historial_coordinador (coordinador_id, tipo, titulo, descripcion)
                 VALUES (?,?,?,?)"
            )->execute([$agenteId, 'nota', '⚙️ Ventana de edición actualizada', "Nueva ventana: {$horas} hora(s). Por superadmin."]);
            echo json_encode(['ok'=>true]);
        } catch (\Throwable $e) {
            echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
        }
        exit;
    }

    // ── PDF Checklist (se procesa por GET en admin/index.php) ─────────────────
    case 'emi_pdf_checklist': {
        echo json_encode(['ok'=>false,'msg'=>'Usa el método GET para el checklist PDF.']); exit;
    }

    // ── Reiniciar temporizador de edición (solo SA) ───────────────────────────
    case 'emi_reset_edit_timer': {
        if (!$isSuperadmin) { echo json_encode(['ok'=>false,'msg'=>'Solo superadmin.']); exit; }
        $cotId = (int)($_POST['cotizacion_id'] ?? 0);
        if (!$cotId) { echo json_encode(['ok'=>false,'msg'=>'ID inválido.']); exit; }
        // Actualizar creado_en del comprobante más reciente a NOW() para reiniciar la ventana
        $db->prepare(
            "UPDATE cotizacion_comprobantes_pago SET creado_en=NOW()
             WHERE cotizacion_id=? AND eliminado_en IS NULL
             ORDER BY id DESC LIMIT 1"
        )->execute([$cotId]);
        // Registrar en historial
        try {
            $db->prepare(
                "INSERT INTO historial_coordinador (coordinador_id, tipo, titulo, descripcion, cotizacion_id)
                 VALUES (?,?,?,?,?)"
            )->execute([
                $agenteId, 'nota',
                '⏱ Temporizador de edición reiniciado',
                'El superadmin reinició la ventana de edición para esta cotización.',
                $cotId
            ]);
        } catch (\Throwable $e) {}
        echo json_encode(['ok'=>true]);
        exit;
    }

    default: {
        echo json_encode(['ok'=>false,'msg'=>'Acción de emisión no reconocida: ' . htmlspecialchars($accion)]);
        exit;
    }
}

// ── Helpers internos ──────────────────────────────────────────────────────────

function _emi_notificar(\PDO $db, int $asesorId, string $titulo, string $cuerpo,
                         bool $urgente = false, string $color = 'blue', string $url = ''): void
{
    try {
        $db->prepare(
            "INSERT INTO notificaciones (agente_id, tipo, titulo, mensaje, urgente, color, url_accion, leida, creada_en)
             VALUES (?,?,?,?,?,?,?,0,NOW())"
        )->execute([
            $asesorId,
            'emision',
            $titulo,
            $cuerpo ?: $titulo,
            $urgente ? 1 : 0,
            $color ?: 'blue',
            $url ?: null,
        ]);
    } catch (\Throwable $e) {
        error_log('[_emi_notificar] ' . $e->getMessage());
    }
}

function _emi_metodoLabel(string $metodo): string {
    return [
        'efectivo'        => 'Efectivo',
        'tarjeta_credito' => 'Tarjeta de crédito',
        'tarjeta_debito'  => 'Tarjeta de débito',
        'transferencia'   => 'Transferencia bancaria',
        'msi_3'           => '3 MSI',
        'msi_6'           => '6 MSI',
        'msi_12'          => '12 MSI',
        'msi_18'          => '18 MSI',
        'deposito_banco'  => 'Depósito en banco',
    ][$metodo] ?? $metodo;
}