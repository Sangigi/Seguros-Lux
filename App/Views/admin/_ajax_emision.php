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
// Forzar visualización de errores temporalmente
ini_set('display_errors', 1);
error_reporting(E_ALL);
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
            "UPDATE cotizaciones SET estado_pago = 'confirmado', comprobante_pago_id = ?, estado = 'pagado' WHERE id = ?"
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

        // Actualizar estado de la PÓLIZA vinculada (si existe) para reflejar pago confirmado
        $stPol = $db->prepare(
            "SELECT id FROM polizas WHERE cotizacion_id = ? AND eliminado_en IS NULL LIMIT 1"
        );
        $stPol->execute([$cotId]);
        $polizaRow = $stPol->fetch(\PDO::FETCH_ASSOC);
        if ($polizaRow) {
            $db->prepare(
                "UPDATE polizas SET notas = CONCAT(IFNULL(notas,''), ?) WHERE id = ?"
            )->execute([
                "\n[" . date('Y-m-d H:i') . "] Pago confirmado por coordinador — Método: " . _emi_metodoLabel($metodo) . ".",
                $polizaRow['id']
            ]);
        }

        // Registrar en historial del coordinador
        $db->prepare(
            "INSERT INTO historial_coordinador (coordinador_id, tipo, titulo, descripcion, cotizacion_id, cliente_id)
             VALUES (?,?,?,?,?,?)"
        )->execute([
            $agenteId,
            'cobro',
            '💳 Comprobante de pago registrado',
            "Folio: {$datosCot['folio']} — Cliente: {$datosCot['cliente_nombre']} — Método: " . _emi_metodoLabel($metodo),
            $cotId,
            $datosCot['cliente_id'] ?? null,
        ]);

        // Registrar en historial del cliente
        try {
            $db->prepare(
                "INSERT INTO historial_cliente (cliente_id, agente_id, tipo, titulo, descripcion)
                 VALUES (?,?,?,?,?)"
            )->execute([
                $datosCot['cliente_id'] ?? 0,
                $agenteId,
                'venta',
                '💰 Pago confirmado por coordinador — ' . ($datosCot['folio'] ?? '#'.$cotId),
                'Método: ' . _emi_metodoLabel($metodo) . ($ref ? " · Ref: {$ref}" : '') . ($monto ? " · $" . number_format((float)$monto, 2) : ''),
            ]);
        } catch (\Throwable $he) { error_log('[historial_cliente cobro] ' . $he->getMessage()); }

        // Notificación al asesor: pago confirmado (verde, urgente)
        if ($asesorId) {
            $urlCliente = '/cliente/' . ($datosCot['cliente_id'] ?? '');
            _emi_notificar(
                db: $db,
                asesorId: $asesorId,
                titulo: '✅ Pago confirmado — ' . ($datosCot['folio'] ?? '#'.$cotId),
                cuerpo: "El coordinador confirmó el pago de la póliza del cliente {$datosCot['cliente_nombre']} "
                       . "({$datosCot['compania']} · Folio: {$datosCot['folio']}). "
                       . "Método: " . _emi_metodoLabel($metodo) . ($monto ? " · $" . number_format((float)$monto, 2) : '') . ".",
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

    // ── NUEVO: Obtener la vista previa del comprobante de pago ────────────────
    case 'emi_get_comprobante': {
        $id = (int)($_POST['comprobante_id'] ?? 0);
        if (!$id) { echo json_encode(['ok'=>false, 'msg'=>'ID de comprobante inválido.']); exit; }

        $st = $db->prepare("SELECT * FROM cotizacion_comprobantes_pago WHERE id = ? AND eliminado_en IS NULL");
        $st->execute([$id]);
        $comp = $st->fetch(\PDO::FETCH_ASSOC);

        if (!$comp) {
            echo json_encode(['ok'=>false, 'msg'=>'El comprobante no existe o fue eliminado.']);
            exit;
        }

        $ruta = '/files?file=' . urlencode('comprobantes/' . $comp['nombre_archivo']);
        $ext = strtolower(pathinfo($comp['nombre_archivo'], PATHINFO_EXTENSION));

        // Generamos el HTML que se inyectará dentro del Modal del coordinador
        $html = '<div style="display:flex; flex-direction:column; gap:16px;">';
        $html .= '<div style="display:flex; justify-content:space-between; align-items:center; background:#1e293b; padding:12px 16px; border-radius:6px; color:#fff;">';
        $html .= '<div><b>Método:</b> ' . htmlspecialchars(_emi_metodoLabel($comp['metodo_pago'])) . ' | <b>Monto:</b> <span style="color:#f3c332; font-weight:700;">$' . number_format((float)$comp['monto'], 2) . '</span></div>';
        $html .= '<a href="' . $ruta . '" target="_blank" class="emi-btn-primary" style="padding:6px 12px; font-size:12px; text-decoration:none; color:#fff; background:#2563eb; border-radius:4px;"><i class="fas fa-external-link-alt"></i> Abrir completo</a>';
        $html .= '</div>';

        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            $html .= '<div style="text-align:center; background:#0f172a; border-radius:6px; padding:10px; overflow:auto; max-height:450px;">';
            $html .= '<img src="' . $ruta . '" style="max-width:100%; height:auto; border-radius:4px;" />';
            $html .= '</div>';
        } elseif ($ext === 'pdf') {
            $html .= '<iframe src="' . $ruta . '" style="width:100%; height:450px; border:none; border-radius:6px;"></iframe>';
        } else {
            $html .= '<div style="padding:30px; text-align:center; background:#f8fafc; border:1px dashed #cbd5e1; border-radius:6px; color:#64748b;">';
            $html .= '<i class="fas fa-file-download fa-3x" style="margin-bottom:10px;"></i><br>';
            $html .= 'Este tipo de archivo (.' . $ext . ') no soporta vista previa dinámica.<br>';
            $html .= '<a href="' . $ruta . '" target="_blank" style="color:#2563eb; font-weight:600; text-decoration:underline;">Haz clic aquí para descargarlo</a>';
            $html .= '</div>';
        }

        if (!empty($comp['comentarios'])) {
            $html .= '<div style="background:#f1f5f9; padding:12px; border-radius:6px; border-left:4px solid #94a3b8; font-size:13px; color:#334155;">';
            $html .= '<b>Notas del Cobro:</b><br>' . nl2br(htmlspecialchars($comp['comentarios']));
            $html .= '</div>';
        }
        $html .= '</div>';

        echo json_encode(['ok'=>true, 'html'=>$html]);
        exit;
    }

    // ── PDF Checklist (ya existía, se mantiene igual) ─────────────────────────
    // El GET accion=emi_pdf_checklist se procesa antes de llegar aquí (en admin/index.php)
    // Por seguridad si alguien lo manda por POST:
    case 'emi_pdf_checklist': {
        echo json_encode(['ok'=>false,'msg'=>'Usa el método GET para el checklist PDF.']); exit;
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
            $cuerpo ?: $titulo,   // mensaje es NOT NULL; si vacío usar título
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