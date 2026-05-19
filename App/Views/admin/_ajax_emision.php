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

        // Actualizar estado de pago en cotizacion
        $db->prepare(
            "UPDATE cotizaciones SET estado_pago = 'confirmado', comprobante_pago_id = ? WHERE id = ?"
        )->execute([$comprobanteId, $cotId]);

        // Obtener datos de cotización para la notificación
        $stCot = $db->prepare(
            "SELECT c.folio, c.compania,
                    CONCAT(cl.nombre,' ',cl.apellido_paterno) AS cliente_nombre
             FROM cotizaciones c
             JOIN clientes cl ON cl.id = c.cliente_id
             WHERE c.id = ?"
        );
        $stCot->execute([$cotId]);
        $datosCot = $stCot->fetch(\PDO::FETCH_ASSOC);

        // Registrar en historial del coordinador
        $db->prepare(
            "INSERT INTO historial_coordinador (coordinador_id, tipo, titulo, descripcion, cotizacion_id)
             VALUES (?,?,?,?,?)"
        )->execute([
            $agenteId,
            'cobro',
            '💳 Comprobante de pago registrado',
            "Folio: {$datosCot['folio']} — Cliente: {$datosCot['cliente_nombre']} — Método: {$metodo}",
            $cotId,
        ]);

        // Notificación importante (roja) al asesor
        if ($asesorId) {
            _emi_notificar(
                db: $db,
                asesorId: $asesorId,
                titulo: '✅ Pago confirmado',
                cuerpo: "El coordinador confirmó el pago de la cotización {$datosCot['folio']} "
                       . "({$datosCot['cliente_nombre']} / {$datosCot['compania']}). "
                       . "Método: " . _emi_metodoLabel($metodo) . ".",
                urgente: true,
                color: 'green',
                url: "/cotizacion/{$cotId}",
            );
            // Notificación adicional: recordar al asesor que debe contactar al cliente para renovación
            _emi_notificar(
                db: $db,
                asesorId: $asesorId,
                titulo: '🔔 Contacta al cliente para renovación',
                cuerpo: "Pago de {$datosCot['cliente_nombre']} confirmado. Contáctalo para ofrecerle renovación o actualización de su póliza ({$datosCot['compania']} — Folio {$datosCot['folio']}).",
                urgente: true,
                color: 'red',
                url: "/cotizacion/{$cotId}",
            );
        }

        echo json_encode(['ok'=>true,'comprobante_id'=>$comprobanteId]);
        exit;
    }

    // ── Obtener datos de un comprobante ───────────────────────────────────────
    case 'emi_get_comprobante': {
        $compId = (int)($_POST['comprobante_id'] ?? 0);
        if (!$compId) { echo json_encode(['ok'=>false,'msg'=>'ID inválido.']); exit; }

        $st = $db->prepare(
            "SELECT cp.*, c.id AS cotizacion_id
             FROM cotizacion_comprobantes_pago cp
             JOIN cotizaciones c ON c.id = cp.cotizacion_id
             WHERE cp.id = ? AND cp.eliminado_en IS NULL"
        );
        $st->execute([$compId]);
        $comp = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$comp) { echo json_encode(['ok'=>false,'msg'=>'Comprobante no encontrado.']); exit; }

        // URL archivo
        if ($comp['nombre_archivo']) {
            $rutaFis = dirname(__DIR__, 3) . '/uploads/comprobantes/' . $comp['cotizacion_id'] . '/' . $comp['nombre_archivo'];
            $comp['url_local'] = file_exists($rutaFis)
                ? '/archivo?file=comprobantes/' . $comp['cotizacion_id'] . '/' . $comp['nombre_archivo']
                : null;
        } else {
            $comp['url_local'] = null;
        }

        $comp['creada_en'] = date('d/m/Y H:i', strtotime($comp['creada_en']));
        echo json_encode(['ok'=>true,'comprobante'=>$comp]);
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