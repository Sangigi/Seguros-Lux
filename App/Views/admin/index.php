<?php
declare(strict_types=1);

use App\Security\SessionManager;
use App\Models\{AgentModel, CotizacionModel};
use Database\Connection;

// Capturar cualquier excepción PHP en peticiones AJAX y devolver JSON legible
// SIN filtrar detalles internos (stack trace, nombre de archivo, mensaje de BD).
set_exception_handler(function(\Throwable $e) {
    // Registrar siempre el detalle completo en el log del servidor
    error_log('[admin exception] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

    if (!headers_sent()) header('Content-Type: application/json');
    $isPost = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
    if ($isPost) {
        // JSON: solo un mensaje genérico, nunca detalles de BD o rutas internas
        echo json_encode([
            'ok'  => false,
            'msg' => 'Error interno del servidor. Inténtalo de nuevo.',
        ]);
    } else {
        // Vista HTML (navegación normal): mensaje genérico sin stacktrace
        echo '<div style="background:#fee2e2;color:#7f1d1d;padding:20px;font-family:monospace;border-radius:8px;margin:20px;">
              <b>Error interno.</b> Consulta el log del servidor para más detalles.</div>';
    }
    exit;
});

SessionManager::requireRole('coordinador', '/login');

$agenteId    = SessionManager::getAgentId();
$agente      = SessionManager::getAgentData();
$isSuperadmin= SessionManager::isSuperadmin();
$_agenteRol  = $agente['rol'] ?? 'coordinador';

$agentModel  = new AgentModel();
$cotModel    = new CotizacionModel();

// ── PASO 5 — PDF Checklist Emisión ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['accion'] ?? '') === 'emi_pdf_checklist') {
    $cotId = (int)($_GET['cotizacion_id'] ?? 0);
    if (!$cotId) { header('Location: /admin?tab=emision'); exit; }

    $db = Connection::getConnection();

    $stCot = $db->prepare(
        "SELECT c.id, c.folio, c.ramo, c.compania, c.tipo_cobertura,
                c.periodicidad,
                CONCAT(cl.nombre,' ',cl.apellido_paterno,' ',COALESCE(cl.apellido_materno,'')) AS cliente_nombre,
                CONCAT(a.nombre,' ',a.apellido) AS asesor_nombre,
                -- Detectar si es renovación por folio terminado en -R o tipo_cobertura que empiece con Renovación
                (CASE WHEN c.folio LIKE '%-R' OR c.tipo_cobertura LIKE 'Renovación%' THEN 1 ELSE 0 END) AS es_renovacion,
                -- Número de póliza anterior (extraído del tipo_cobertura \"Renovación - XXXXX\")
                TRIM(REPLACE(c.tipo_cobertura, 'Renovación - ', '')) AS num_poliza_anterior
         FROM cotizaciones c
         JOIN clientes cl ON cl.id = c.cliente_id
         JOIN agentes  a  ON a.id  = c.agente_id
         WHERE c.id = ?"
    );
    $stCot->execute([$cotId]);
    $cot = $stCot->fetch(\PDO::FETCH_ASSOC);
    if (!$cot) { header('Location: /admin?tab=emision'); exit; }

    $stDocs = $db->prepare(
        "SELECT tipo_documento, nombre_original, nombre_archivo, subido_en
         FROM cotizacion_archivos
         WHERE cotizacion_id = ? AND eliminado_en IS NULL
         ORDER BY subido_en ASC"
    );
    $stDocs->execute([$cotId]);
    $docs = $stDocs->fetchAll(\PDO::FETCH_ASSOC);
    $docsMap = [];
    foreach ($docs as $d) {
        $docsMap[$d['tipo_documento']] = $d;
    }
    if (!isset($docsMap['comprobante_pago'])) {
        $syn = CotizacionModel::syntheticComprobantePagoDesdeTablaPagos($db, $cotId);
        if ($syn) {
            $docsMap['comprobante_pago'] = $syn;
        }
    }

    if (!isset($docsMap['comprobante_pago'])) {
        $stComp2 = $db->prepare(
            "SELECT nombre_original, nombre_archivo, creado_en AS subido_en
             FROM cotizacion_comprobantes_pago
             WHERE cotizacion_id = ? AND eliminado_en IS NULL
             ORDER BY creado_en DESC LIMIT 1"
        );
        $stComp2->execute([$cotId]);
        $comp2 = $stComp2->fetch(\PDO::FETCH_ASSOC);
        if ($comp2) {
            $docsMap['comprobante_pago'] = $comp2;
        }
    }
    
    $stCk = $db->prepare(
        "SELECT doc_tipo, estado, nota, actualizado_en AS revisado_en
         FROM emi_checklist
         WHERE cotizacion_id = ?"
    );
    $stCk->execute([$cotId]);
    $ck = $stCk->fetchAll(\PDO::FETCH_ASSOC);
    $ckMap = [];
    foreach ($ck as $c) $ckMap[$c['doc_tipo']] = $c;

    $esRenovacion = !empty($cot['es_renovacion']);
    $numPolizaAnterior = $cot['num_poliza_anterior'] ?? '';
    // Si es renovación, el cliente ya tiene sus documentos personales de la emisión anterior
    // Solo se requiere la póliza nueva y el comprobante de pago
    $REQS_NUEVA = [
        'autos'          => ['ine','comprobante_dom','tarjeta_circulacion','foto_coche_1','foto_coche_2','comprobante_pago'],
        'vida'           => ['ine','comprobante_dom','comprobante_pago'],
        'gastos_medicos' => ['ine','comprobante_dom','comprobante_pago'],
    ];
    $REQS_RENOVACION = [
        'autos'          => ['comprobante_pago'],
        'vida'           => ['comprobante_pago'],
        'gastos_medicos' => ['comprobante_pago'],
    ];
    $REQS = $esRenovacion ? $REQS_RENOVACION : $REQS_NUEVA;
    $tipoLabel = [
        'ine'=>'INE','comprobante_dom'=>'Comprobante de domicilio','tarjeta_circulacion'=>'Tarjeta de circulación',
        'foto_coche_1'=>'Foto vehículo 1','foto_coche_2'=>'Foto vehículo 2','foto_coche_3'=>'Foto vehículo 3',
        'foto_coche_4'=>'Foto vehículo 4','foto_coche_5'=>'Foto vehículo 5',
        'comprobante_pago'=>'Comprobante de pago','constancia_fiscal'=>'Constancia fiscal',
    ];
    $ramo = $cot['ramo'] ?: 'autos';
    $requeridos = $REQS[$ramo] ?? $REQS['autos'];
    $all = array_values(array_unique(array_merge($requeridos, array_keys($docsMap))));

    header('Content-Type: text/html; charset=utf-8');
    ?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="icon" type="image/png" sizes="32x32" href="/images/logo.png">
    <title>Checklist <?= $esRenovacion ? 'Renovación' : 'de Emisión' ?> — <?= htmlspecialchars($cot['folio'] ?? ('#'.$cotId)) ?></title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 12px; color: #1e293b; background: #f8fafc; }
        .toolbar-screen { background: #0a0f1c; padding: 10px 32px; display: flex; gap: 12px; align-items: center; border-bottom: 3px solid #f3c332; }
        .toolbar-screen button { padding: 8px 20px; border-radius: 6px; font-size: 12px; font-weight: 700; cursor: pointer; border: none; transition: opacity .15s; }
        .toolbar-screen button:hover { opacity: .85; }
        .btn-print { background: #f3c332; color: #0a0f1c; }
        .btn-back  { background: rgba(255,255,255,.1); color: #fff; border: 1px solid rgba(255,255,255,.2) !important; }
        .toolbar-screen span { font-size: 11px; color: rgba(255,255,255,.5); margin-left: 8px; }
        .doc-header { background: linear-gradient(135deg, #0a0f1c 0%, #1a2744 100%); padding: 28px 40px; display: flex; align-items: center; justify-content: space-between; gap: 24px; flex-wrap: wrap; }
        .brand { display: flex; align-items: center; gap: 16px; }
        .brand-text h1 { font-size: 22px; font-weight: 900; color: #fff; letter-spacing: -.5px; }
        .brand-text p { font-size: 11px; color: rgba(255,255,255,.45); letter-spacing: .8px; text-transform: uppercase; margin-top: 2px; }
        .doc-meta { text-align: right; }
        .doc-meta .folio-badge { display: inline-block; background: #f3c332; color: #0a0f1c; font-family: 'Courier New', monospace; font-size: 14px; font-weight: 900; padding: 6px 16px; border-radius: 6px; letter-spacing: .5px; margin-bottom: 6px; }
        .doc-meta p { font-size: 11px; color: rgba(255,255,255,.55); line-height: 1.6; }
        .info-strip { background: #fff; border-bottom: 2px solid #f3c332; padding: 16px 40px; display: flex; gap: 40px; flex-wrap: wrap; }
        .info-strip .item { display: flex; flex-direction: column; gap: 2px; }
        .info-strip .label { font-size: 10px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: .6px; }
        .info-strip .value { font-size: 13px; font-weight: 600; color: #0f172a; }
        .content { padding: 28px 40px; }
        .section-title { font-size: 11px; font-weight: 800; color: #f3c332; text-transform: uppercase; letter-spacing: .8px; margin-bottom: 14px; padding-bottom: 6px; border-bottom: 2px solid #f3c332; display: flex; align-items: center; gap: 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        thead tr { background: linear-gradient(90deg, #0a0f1c, #1a2744); }
        thead th { padding: 11px 14px; text-align: left; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .6px; color: rgba(255,255,255,.85); }
        tbody tr:nth-child(even) { background: #f8fafc; }
        tbody tr:hover { background: #fef9e7; }
        tbody td { padding: 10px 14px; border-bottom: 1px solid #e2e8f0; font-size: 12px; vertical-align: middle; }
        tbody tr:last-child td { border-bottom: none; }
        .pill { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 999px; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .4px; }
        .pill-ok   { background: #dcfce7; color: #166534; }
        .pill-pend { background: #f1f5f9; color: #334155; }
        .pill-rej  { background: #fff7ed; color: #9a3412; }
        .pill-miss { background: #fee2e2; color: #991b1b; }
        .req-star  { color: #dc2626; font-weight: 900; margin-left: 3px; }
        .doc-footer { margin: 0 40px 24px; padding: 14px 0; border-top: 2px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
        .doc-footer span { font-size: 10px; color: #94a3b8; }
        .doc-footer .gold { color: #b8960a; font-weight: 700; }
        .progress-bar-wrap { background: #f1f5f9; border-radius: 8px; padding: 12px 16px; margin-bottom: 20px; display: flex; align-items: center; gap: 20px; flex-wrap: wrap; }
        .progress-stat { display: flex; flex-direction: column; align-items: center; }
        .progress-stat .num { font-size: 22px; font-weight: 900; color: #0a0f1c; line-height: 1; }
        .progress-stat .lbl { font-size: 9px; color: #94a3b8; text-transform: uppercase; letter-spacing: .5px; margin-top: 2px; }
        .progress-bar-outer { flex: 1; min-width: 120px; height: 8px; background: #e2e8f0; border-radius: 99px; overflow: hidden; }
        .progress-bar-inner { height: 100%; background: linear-gradient(90deg, #f3c332, #e0a800); border-radius: 99px; }
        @media print { body { background: #fff; } .toolbar-screen { display: none !important; } .doc-header, thead tr, .pill { -webkit-print-color-adjust: exact; print-color-adjust: exact; } @page { margin: 8mm; size: A4 portrait; } }
    </style>
</head>
<body>
<div class="toolbar-screen">
    <button class="btn-back"  id="emiCkBack">← Volver</button>
    <button class="btn-print" id="emiCkPrint">🖨️ Guardar como PDF</button>
    <span>Imprimir → Destino: «Guardar como PDF»</span>
</div>
<script src="/js/emi_checklist_pdf.js" defer></script>
<div class="doc-header">
    <div class="brand">
        <div style="background:none;box-shadow:none;padding:0;width:auto;height:auto;">
            <img src="/images/logo_blanco.png" alt="LuxSeguros" style="height:52px;width:auto;object-fit:contain;">
        </div>
        <div class="brand-text">
            <h1>Seguros Lux · EIRA</h1>
            <p>Sistema de Gestión de Seguros</p>
        </div>
    </div>
    <div class="doc-meta">
        <div class="folio-badge"><?= htmlspecialchars($cot['folio'] ?? ('#'.$cotId)) ?></div>
        <p><?= $esRenovacion ? '🔄 Checklist de Renovación' : 'Checklist de Emisión' ?></p>
        <p>Generado: <?= date('d/m/Y H:i') ?></p>
        <p>Coordinador: <?= htmlspecialchars(($agente['nombre'] ?? '') . ' ' . ($agente['apellido'] ?? '')) ?></p>
    </div>
</div>
<?php if ($esRenovacion): ?>
<div style="background:#fef3c7;border-left:4px solid #f59e0b;padding:12px 40px;display:flex;align-items:center;gap:12px;font-size:13px;color:#92400e;">
    <span style="font-size:18px;">🔄</span>
    <div>
        <strong>Renovación de póliza</strong> — Esta emisión es una renovación.
        <?php if ($numPolizaAnterior && strpos($numPolizaAnterior, 'Renovación') === false): ?>
        Póliza anterior: <span style="font-family:'Courier New',monospace;font-weight:800;background:rgba(0,0,0,.08);padding:2px 8px;border-radius:4px;"><?= htmlspecialchars($numPolizaAnterior) ?></span>.
        <?php endif; ?>
        Los documentos del cliente (INE, domicilio, etc.) ya obran en el expediente de la emisión original.
    </div>
</div>
<?php endif; ?>
<div class="info-strip">
    <div class="item"><span class="label">Cliente</span><span class="value"><?= htmlspecialchars($cot['cliente_nombre']) ?></span></div>
    <div class="item"><span class="label">Asesor</span><span class="value"><?= htmlspecialchars($cot['asesor_nombre']) ?></span></div>
    <div class="item"><span class="label">Ramo</span><span class="value"><?= ucfirst(str_replace('_',' ', htmlspecialchars($ramo))) ?></span></div>
    <div class="item"><span class="label">Compañía</span><span class="value"><?= htmlspecialchars($cot['compania'] ?? '—') ?></span></div>
    <div class="item">
        <span class="label"><?= $esRenovacion ? 'Tipo' : 'Tipo de cobertura' ?></span>
        <span class="value" style="<?= $esRenovacion ? 'color:#d97706;font-weight:800;' : '' ?>">
            <?php if ($esRenovacion): ?>
                🔄 Renovación
                <?php if ($numPolizaAnterior && strpos($numPolizaAnterior, 'Renovación') === false): ?>
                <br><span style="font-size:11px;font-weight:600;font-family:'Courier New',monospace;color:#92400e;">Póliza ant.: <?= htmlspecialchars($numPolizaAnterior) ?></span>
                <?php endif; ?>
            <?php else: ?>
                <?= htmlspecialchars($cot['tipo_cobertura'] ?? '—') ?>
            <?php endif; ?>
        </span>
    </div>
    <div class="item"><span class="label">Periodicidad</span><span class="value"><?= ucfirst(htmlspecialchars($cot['periodicidad'] ?? '—')) ?></span></div>
</div>
<div class="content">
    <?php
    $totalReq  = count($requeridos);
    $presentes = count(array_filter($requeridos, fn($t) => isset($docsMap[$t])));
    $okCount = count(array_filter($all, fn($t) => isset($docsMap[$t]) && ($ckMap[$t]['estado'] ?? 'ok') !== 'rechazado'));
    $pct = $totalReq > 0 ? round($presentes / $totalReq * 100) : 0;
    ?>
    <div class="progress-bar-wrap">
        <div class="progress-stat"><span class="num"><?= $presentes ?>/<?= $totalReq ?></span><span class="lbl">Docs requeridos</span></div>
        <div class="progress-bar-outer"><div class="progress-bar-inner" style="width:<?= $pct ?>%;"></div></div>
        <div class="progress-stat"><span class="num" style="color:#16a34a;"><?= $okCount ?></span><span class="lbl">Marcados OK</span></div>
        <div class="progress-stat"><span class="num" style="color:#dc2626;"><?= count(array_filter($all, fn($t) => ($ckMap[$t]['estado'] ?? '') === 'rechazado')) ?></span><span class="lbl">Con observaciones</span></div>
    </div>
    <div class="section-title">Documentos del Expediente</div>
    <table>
        <thead><tr><th>Documento</th><th>Req.</th><th>Archivo subido</th><th>Fecha subida</th><th>Estado revisión</th><th>Nota del coordinador</th></tr></thead>
        <tbody>
        <?php foreach ($all as $tipo):
            $isReq  = in_array($tipo, $requeridos, true);
            $doc    = $docsMap[$tipo] ?? null;
            $ckr    = $ckMap[$tipo]   ?? null;
            $estado = $ckr['estado']  ?? 'pendiente';
            $hasDoc = (bool)$doc;
            $label  = $tipoLabel[$tipo] ?? $tipo;
            if (!$hasDoc && $isReq)        { $pillClass='pill-miss'; $pillTxt='⚠ Falta'; }
            elseif (!$hasDoc)              { $pillClass='pill-pend'; $pillTxt='—'; }
            elseif ($estado==='rechazado') { $pillClass='pill-rej';  $pillTxt='⚠ Obs.'; }
            else                           { $pillClass='pill-ok';   $pillTxt='✓ Subido'; }
        ?>
            <tr>
                <td><strong><?= htmlspecialchars($label) ?></strong><?php if ($isReq): ?><span class="req-star">*</span><?php endif; ?></td>
                <td><?= $isReq ? '<span style="color:red;font-weight:800;">●</span>' : '<span style="color:#cbd5e1;">○</span>' ?></td>
                <td style="font-size:11px;color:#475569;max-width:160px;word-break:break-word;"><?= $hasDoc ? htmlspecialchars($doc['nombre_original'] ?? $doc['nombre_archivo'] ?? '—') : '<em style="color:#94a3b8;">No subido</em>' ?></td>
                <td style="font-size:11px;white-space:nowrap;color:#64748b;"><?= !empty($doc['subido_en']) ? date('d/m/Y H:i', strtotime($doc['subido_en'])) : '—' ?></td>
                <td><span class="pill <?= $pillClass ?>"><?= $pillTxt ?></span></td>
                <td style="font-size:11px;color:#64748b;"><?= !empty($ckr['nota']) ? htmlspecialchars($ckr['nota']) : '<span style="color:#cbd5e1;">—</span>' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<div class="doc-footer">
    <span>EIRA · <span class="gold">Seguros Lux</span> — Documento generado el <?= date('d/m/Y H:i') ?></span>
    <span style="color:#dc2626;font-size:9px;">● Requerido &nbsp;</span>
    <span>Confidencial — Uso interno</span>
</div>
</body>
</html>
    <?php
    exit;
}
// ─────────────────────────────────────────────────────────────────────────────

// ── AJAX ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    header('Content-Type: application/json');
    $a      = $_POST['accion'];
    $db     = Connection::getConnection();
    $soloSA = ['crear_agente','cambiar_rol','eliminar_agente','reactivar_agente','eliminar_cotizacion'];

    if (in_array($a, $soloSA, true) && !$isSuperadmin) {
        echo json_encode(['ok'=>false,'msg'=>'Solo el superadmin puede realizar esta acción.']); exit;
    }

    // ── MÓDULO EMISIÓN ────────────────────────────────────────────────────────
    if (str_starts_with($a, 'emi_')) {
        require __DIR__ . '/_ajax_emision.php';
        echo json_encode(['ok'=>false,'msg'=>'Acción de emisión no reconocida.']);
        exit;
    }

    // ── Historial de pólizas (coordinador / SA) ────────────────────────────────
    if ($a === 'get_polizas_resumen') {
        $filtroAsesor = (int)($_POST['asesor_id'] ?? 0);
        $sql = 'SELECT p.id, p.cliente_id, p.cotizacion_id, p.agente_id,
                       p.compania, p.numero_poliza, p.fecha_inicio, p.fecha_fin,
                       p.frecuencia_renovacion, p.estado, p.notas,
                       p.nombre_original, p.subido_en,
                       CONCAT(c.nombre," ",c.apellido_paterno) AS cliente_nombre,
                       c.id AS cliente_id_ref,
                       CONCAT(a.nombre," ",a.apellido) AS asesor_nombre,
                       DATEDIFF(p.fecha_fin, CURDATE()) AS dias_restantes,
                       CASE
                           WHEN cot.estado = "pagado"           THEN 1
                           WHEN cot.estado_pago = "confirmado"  THEN 1
                           WHEN ccp.id IS NOT NULL              THEN 1
                           WHEN cp.id IS NOT NULL               THEN 1
                           ELSE 0
                       END AS pago_confirmado,
                       COALESCE(ccp.metodo_pago, cot.metodo_pago) AS metodo_pago,
                       COALESCE(ccp.creado_en, cp.creado_en)      AS fecha_pago
                FROM polizas p
                JOIN clientes c   ON c.id  = p.cliente_id
                JOIN agentes  a   ON a.id  = p.agente_id
                LEFT JOIN cotizaciones                cot ON cot.id  = p.cotizacion_id
                LEFT JOIN cotizacion_comprobantes_pago ccp ON ccp.cotizacion_id = p.cotizacion_id AND ccp.eliminado_en IS NULL
                LEFT JOIN (SELECT cotizacion_id, MAX(id) AS max_id FROM cotizacion_pagos GROUP BY cotizacion_id) cpmax
                          ON cpmax.cotizacion_id = p.cotizacion_id
                LEFT JOIN cotizacion_pagos cp ON cp.id = cpmax.max_id
                WHERE p.eliminado_en IS NULL';
        $params = [];
        if ($filtroAsesor) { $sql .= ' AND p.agente_id = ?'; $params[] = $filtroAsesor; }
        $sql .= ' ORDER BY p.fecha_fin ASC LIMIT 500';
        try {
            $st = $db->prepare($sql);
            $st->execute($params);
            $polizas = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('[admin/polizas PDO] ' . $e->getMessage());
            echo json_encode(['ok'=>false, 'msg'=>'Error al consultar pólizas.']);
            exit;
        } catch (Throwable $e) {
            error_log('[admin/polizas] ' . $e->getMessage());
            echo json_encode(['ok'=>false, 'msg'=>'Error interno.']);
            exit;
        }
        $pagadas    = array_values(array_filter($polizas, fn($p) => !empty($p['pago_confirmado'])));
        $pendientes = array_values(array_filter($polizas, fn($p) => empty($p['pago_confirmado']) && ($p['estado'] ?? '') !== 'vencida'));
        $vencer     = array_values(array_filter($polizas, fn($p) => is_numeric($p['dias_restantes']) && (int)$p['dias_restantes'] >= 0 && (int)$p['dias_restantes'] <= 30));
        echo json_encode([
            'ok'         => true,
            'total'      => count($polizas),
            'pagadas'    => count($pagadas),
            'pendientes' => count($pendientes),
            'por_vencer' => count($vencer),
            'polizas'    => array_values($polizas),
        ]);
        exit;
    }

    // ── Router de acciones POST ──────────────────────────────────────────────────

    if ($a === 'editar_agente_nombre') {
        $tid    = (int)($_POST['agente_id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $apell  = trim($_POST['apellido'] ?? '');
        if (!$tid || !$nombre) { echo json_encode(['ok'=>false,'msg'=>'Datos inválidos.']); exit; }
        $puedeEditar = $isSuperadmin || ($_agenteRol === 'coordinador' && $agentModel->getRol($tid) === 'asesor');
        if (!$puedeEditar) { echo json_encode(['ok'=>false,'msg'=>'Sin permiso.']); exit; }
        $db->prepare('UPDATE agentes SET nombre=?, apellido=? WHERE id=?')->execute([$nombre, $apell, $tid]);
        echo json_encode(['ok'=>true]);

    } elseif ($a === 'cambiar_password_agente') {
        $tid = (int)($_POST['agente_id'] ?? 0);
        $pw  = trim($_POST['password'] ?? '');
        if (!$tid || strlen($pw) < 6) { echo json_encode(['ok'=>false,'msg'=>'Contraseña mínimo 6 caracteres.']); exit; }
        $puedeEditar = $isSuperadmin || ($_agenteRol === 'coordinador' && $agentModel->getRol($tid) === 'asesor');
        if (!$puedeEditar) { echo json_encode(['ok'=>false,'msg'=>'Sin permiso.']); exit; }
        $db->prepare('UPDATE agentes SET contrasena=? WHERE id=?')->execute([password_hash($pw, PASSWORD_BCRYPT), $tid]);
        echo json_encode(['ok'=>true]);

    } elseif ($a === 'get_historial_coordinador') {
        if (!$isSuperadmin) { echo json_encode(['ok'=>false,'msg'=>'Acceso denegado.']); exit; }
        $tid   = (int)($_POST['agente_id'] ?? 0);
        $limit = min((int)($_POST['limit'] ?? 50), 200);
        if (!$tid) { echo json_encode(['ok'=>false,'msg'=>'ID inválido.']); exit; }
        try {
            $st = $db->prepare(
                "SELECT h.tipo, h.titulo, h.descripcion,
                        COALESCE(h.fecha, NOW()) AS fecha_registro,
                        c.folio,
                        CONCAT(cl.nombre,' ',cl.apellido_paterno) AS cliente_nombre
                 FROM historial_coordinador h
                 LEFT JOIN cotizaciones c  ON c.id  = h.cotizacion_id
                 LEFT JOIN clientes    cl ON cl.id = h.cliente_id
                 WHERE h.coordinador_id = ?
                 ORDER BY h.fecha DESC
                 LIMIT ?"
            );
            $st->execute([$tid, $limit]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as &$r) {
                $r['fecha_fmt'] = $r['fecha_registro']
                    ? date('d/m/Y H:i', strtotime($r['fecha_registro']))
                    : '—';
            }
            unset($r);
            echo json_encode(['ok'=>true,'historial'=>$rows]);
        } catch (\Throwable $eH) {
            error_log('[admin/historial] ' . $eH->getMessage());
            echo json_encode(['ok'=>false,'msg'=>'Error al consultar historial.']);
        }

    } elseif ($a === 'cambiar_rol') {
        $tid = (int)($_POST['agente_id']??0);
        $rol = trim($_POST['rol']??'');
        if (!$tid || !in_array($rol,['asesor','coordinador','superadmin'],true)) { echo json_encode(['ok'=>false,'msg'=>'Datos inválidos.']); exit; }
        if ($tid === $agenteId) { echo json_encode(['ok'=>false,'msg'=>'No puedes cambiar tu propio rol.']); exit; }
        $agentModel->cambiarRol($tid, $rol);
        echo json_encode(['ok'=>true]);

    } elseif ($a === 'crear_agente') {
        $n=trim($_POST['nombre']??''); $ap=trim($_POST['apellido']??'');
        $co=trim($_POST['correo']??''); $te=trim($_POST['telefono']??'');
        $pw=trim($_POST['password']??''); $ro=trim($_POST['rol']??'asesor');
        if (!$n||!$ap||!$co||!$pw) { echo json_encode(['ok'=>false,'msg'=>'Faltan campos obligatorios.']); exit; }
        if (!in_array($ro,['asesor','coordinador','superadmin'],true)) $ro='asesor';
        $id = $agentModel->crear($n,$ap,$co,$te,$pw,$ro);
        echo json_encode(['ok'=>true,'id'=>$id]);

    } elseif ($a === 'eliminar_agente') {
        $tid=(int)($_POST['agente_id']??0);
        if (!$tid||$tid===$agenteId) { echo json_encode(['ok'=>false,'msg'=>'No puedes desactivarte a ti mismo.']); exit; }
        // Se reemplaza desactivar() por eliminar() para coincidir con AgentModel
        $agentModel->eliminar($tid);
        echo json_encode(['ok'=>true,'msg'=>'Asesor desactivado.']);

    } elseif ($a === 'reactivar_agente') {
        $tid=(int)($_POST['agente_id']??0);
        if (!$tid) { echo json_encode(['ok'=>false,'msg'=>'ID inválido.']); exit; }
        $agentModel->reactivar($tid);
        echo json_encode(['ok'=>true,'msg'=>'Asesor reactivado.']);

    } elseif ($a === 'eliminar_cotizacion') {
        $cid=(int)($_POST['cotizacion_id']??0);
        if (!$cid) { echo json_encode(['ok'=>false,'msg'=>'ID inválido.']); exit; }
        $cotModel->eliminar($cid, 0); // 0 = coordinador puede borrar cualquier cotización
        echo json_encode(['ok'=>true]);

    } elseif ($a === 'get_documentos_admin') {
        $cotId=(int)($_POST['cotizacion_id']??0);
        if (!$cotId) { echo json_encode(['ok'=>false,'msg'=>'ID inválido.']); exit; }
        $cot=$cotModel->getById($cotId);
        if (!$cot) { echo json_encode(['ok'=>false,'msg'=>'Cotización no encontrada.']); exit; }
        $tipoLabel=['poliza'=>'Póliza','ine'=>'INE','comprobante_dom'=>'Comp. domicilio',
            'tarjeta_circulacion'=>'Tarjeta circulación','constancia_fiscal'=>'Constancia fiscal',
            'foto_coche_1'=>'Foto 1','foto_coche_2'=>'Foto 2','foto_coche_3'=>'Foto 3',
            'foto_coche_4'=>'Foto 4','foto_coche_5'=>'Foto 5','comprobante_pago'=>'Comp. pago',
        ];
        $docs=array_map(function($a) use($tipoLabel,$cotId){
            $ext=strtolower(pathinfo($a['nombre_original']??'',PATHINFO_EXTENSION));
            $esImg=in_array($ext,['jpg','jpeg','png','gif','webp'],true);
            $ruta=dirname(__DIR__,3).'/'.$a['ruta'];
            return [
                'id'          => (int)$a['id'],
                'tipo_label'  => $tipoLabel[$a['tipo_documento']] ?? ucfirst(str_replace('_',' ',$a['tipo_documento'])),
                'nombre_orig' => $a['nombre_original'],
                'url_local'   => file_exists($ruta) ? '/archivo?file='.preg_replace('#^uploads/#','',$a['ruta']) : null,
                'en_idrive'   => !empty($a['idrive_key']),
                'es_imagen'   => $esImg,
                'es_pdf'      => $ext==='pdf',
            ];
        }, $cotModel->getArchivos($cotId));
        echo json_encode(['ok'=>true,'folio'=>$cot['folio']??'','docs'=>$docs]);

    } elseif ($a === 'reasignar_clientes') {
        $ids     = array_filter(array_map('intval', explode(',', $_POST['cliente_ids']??'')));
        $nuevoId = (int)($_POST['nuevo_asesor_id']??0);
        if (empty($ids)||!$nuevoId) { echo json_encode(['ok'=>false,'msg'=>'Datos incompletos.']); exit; }
        $n = $agentModel->reasignarClientes($ids, $nuevoId);
        echo json_encode(['ok'=>true,'reasignados'=>$n]);

    } elseif ($a === 'renov_actualizar_poliza') {
        $polizaId    = (int)($_POST['poliza_id']   ?? 0);
        $clienteId   = (int)($_POST['cliente_id']  ?? 0);
        $fechaInicio = trim($_POST['fecha_inicio']  ?? '');
        $fechaFin    = trim($_POST['fecha_fin']     ?? '');
        $frecuencia  = trim($_POST['frecuencia']    ?? 'anual');
        $nuevoNumero = trim($_POST['nuevo_numero']  ?? '');
        $notas       = trim($_POST['notas']         ?? '');
        
        if (!$polizaId || !$fechaFin) { echo json_encode(['ok'=>false,'msg'=>'Faltan datos obligatorios.']); exit; }
        if (!in_array($frecuencia,['semanal','mensual','trimestral','anual'],true)) $frecuencia='anual';
        
        $db = \Database\Connection::getConnection();
        $st = $db->prepare("SELECT id, cliente_id, notas FROM polizas WHERE id = ?");
        $st->execute([$polizaId]);
        $pol = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$pol) { echo json_encode(['ok' => false, 'msg' => 'Póliza no encontrada.']); exit; }

        $notasOriginales = $pol['notas'] ?? '';
        if ($notas) { $notasOriginales .= ($notasOriginales ? "\n" : "") . "[" . date('Y-m-d H:i') . "] " . $notas; }

        $sets = ['estado = ?', 'fecha_fin = ?', 'frecuencia_renovacion = ?', 'renovacion_notificada = 0', 'actualizado_en = NOW()'];
        $vals = ['vigente', $fechaFin, $frecuencia];
        if ($fechaInicio) { $sets[] = 'fecha_inicio = ?'; $vals[] = $fechaInicio; }
        if ($nuevoNumero) { $sets[] = 'numero_poliza = ?'; $vals[] = $nuevoNumero; }
        
        // Guardar comprobante
        $archivoGuardado = false;
        if (!empty($_FILES['nueva_poliza_archivo']['name'])) {
            $file = $_FILES['nueva_poliza_archivo'];
            $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['pdf','jpg','jpeg','png','webp'], true) && $file['error'] === UPLOAD_ERR_OK) {
                $dir = dirname(__DIR__, 2) . '/uploads/comprobantes/' . $clienteId . '/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                
                $nomArch = 'comprobante_renovacion_' . date('Ymd_His') . '.' . $ext;
                if (move_uploaded_file($file['tmp_name'], $dir . $nomArch)) {
                    // Marcar como pagado
                    $sets[] = 'estado_pago = ?'; 
                    $vals[] = 'confirmado';
                    $sets[] = 'pago_confirmado_en = NOW()';
                    $notasOriginales .= "\n[" . date('Y-m-d H:i') . "] Comprobante de pago subido: " . $file['name'];
                    $archivoGuardado = true;
                }
            }
        }
        
        if ($notasOriginales) { $sets[] = 'notas = ?'; $vals[] = trim($notasOriginales); }
        $vals[] = $polizaId;
        
        $db->prepare('UPDATE polizas SET '.implode(', ', $sets).' WHERE id = ?')->execute($vals);
        
        if ($clienteId) {
            $desc = 'Renovada por coordinador.' . ($nuevoNumero ? " Nueva póliza: {$nuevoNumero}." : '') . ($archivoGuardado ? ' | Comprobante adjunto.' : '');
            $db->prepare("INSERT INTO historial_cliente (cliente_id, agente_id, tipo, titulo, descripcion, fecha) VALUES (?, ?, 'nota', '✅ Póliza renovada', ?, NOW())")->execute([$clienteId, $agenteId, $desc]);
            $db->prepare("UPDATE historial_cliente SET titulo = '✅ Renovación gestionada' WHERE cliente_id = ? AND titulo = '🔄 Solicitud de renovación'")->execute([$clienteId]);
        }
        echo json_encode(['ok'=>true, 'archivo_guardado'=>$archivoGuardado]);
    } elseif ($a === 'renov_marcar_gestionada') {
        $clienteId = (int)($_POST['cliente_id'] ?? 0);
        $fecha     = trim($_POST['fecha'] ?? '');
        if (!$clienteId) { echo json_encode(['ok'=>false,'msg'=>'ID inválido.']); exit; }
        $db->prepare("UPDATE historial_cliente SET titulo = '✅ Renovación gestionada' WHERE cliente_id = ? AND titulo = '🔄 Solicitud de renovación' AND DATE(fecha) = DATE(?)")->execute([$clienteId, $fecha ?: date('Y-m-d')]);
        echo json_encode(['ok'=>true]);

    } else {
        echo json_encode(['ok'=>false,'msg'=>'Acción desconocida: '.$a]);
    }
    exit;
}


// ── Datos ─────────────────────────────────────────────────────────────────────
$tab             = $_GET['tab']      ?? 'resumen';
$filtroAgente    = isset($_GET['agente_id'])  ? (int)$_GET['agente_id']  : null;
$asesorSel       = isset($_GET['asesor_id'])  ? (int)$_GET['asesor_id']  : null;
$filtroActAgente = isset($_GET['act_agente']) ? (int)$_GET['act_agente'] : null;

$stats       = $agentModel->getStatsGlobales();
$ranking     = $agentModel->getRankingAsesores();
$reporte     = $agentModel->getReporteFinanciero();
$agLista     = $agentModel->getTodos();
$asesores    = $agentModel->getSoloAsesores();
$pipeline    = $agentModel->getPipeline();
$actividad   = $agentModel->getActividadEquipo(60, $filtroActAgente);
$evolucion   = $agentModel->getEvolucionGlobal($filtroAgente);
$vehiculos   = $agentModel->getTopVehiculos($filtroAgente);
$coberturas  = $agentModel->getCoberturaGlobal($filtroAgente);

$clientesAsesor = $asesorSel ? $agentModel->getClientesPorAsesor($asesorSel) : [];
$asesorInfo     = $asesorSel ? (array_values(array_filter($agLista, fn($a) => (int)$a['id']===$asesorSel))[0] ?? null) : null;
$cotsGlobal     = $isSuperadmin ? $cotModel->getRecientsGlobal(500) : [];
$agNombres      = array_unique(array_column($cotsGlobal, 'agente_nombre')); sort($agNombres);

if (!empty($cotsGlobal) && empty(array_filter(array_column($cotsGlobal, 'fecha_fin_vigencia')))) {
    $_ids2 = array_column($cotsGlobal, 'id');
    if (!empty($_ids2)) {
        $_ph2   = implode(',', array_fill(0, count($_ids2), '?'));
        $_db2   = \Database\Connection::getConnection();
        $_st2   = $_db2->prepare("SELECT id, fecha_fin_vigencia FROM cotizaciones WHERE id IN ($_ph2)");
        $_st2->execute($_ids2);
        $_vigMap2 = array_column($_st2->fetchAll(\PDO::FETCH_ASSOC), 'fecha_fin_vigencia', 'id');
        foreach ($cotsGlobal as &$_cg) {
            if (empty($_cg['fecha_fin_vigencia'])) { $_cg['fecha_fin_vigencia'] = $_vigMap2[$_cg['id']] ?? null; }
        }
        unset($_cg);
    }
}

$ingNeto   = array_sum(array_column($reporte, 'ingreso_neto'));
$ingMes    = array_sum(array_column($reporte, 'ingreso_mes'));
$ventasT   = array_sum(array_column($reporte, 'ventas'));
$totalPipe = array_sum(array_column($pipeline, 'valor_pipeline'));

$db       = Connection::getConnection();
$citasHoy = $db->query(
    "SELECT ci.hora, ci.tipo,
            CONCAT(cl.nombre,' ',cl.apellido_paterno) AS cliente,
            CONCAT(a.nombre,' ',a.apellido)            AS asesor
     FROM citas ci
     JOIN clientes cl ON cl.id = ci.cliente_id
     JOIN agentes  a  ON a.id  = ci.agente_id
     WHERE ci.fecha = CURDATE() AND ci.estado = 'programada' AND a.rol = 'asesor'
     ORDER BY ci.hora ASC"
)->fetchAll();

$atcData = $db->query(
    "SELECT clave, valor, descripcion FROM configuracion WHERE clave LIKE 'atc_%' ORDER BY clave"
)->fetchAll();
$atcMap  = array_column($atcData, null, 'clave');

$renovacionesPendientes = $db->query(
    "SELECT h.cliente_id, h.descripcion, h.fecha,
            CONCAT(cl.nombre,' ',cl.apellido_paterno,' ',COALESCE(cl.apellido_materno,'')) AS cliente_nombre,
            cl.correo AS cliente_correo, cl.telefono AS cliente_tel,
            CONCAT(a.nombre,' ',a.apellido) AS asesor_nombre,
            a.id AS asesor_id,
            p.id AS poliza_id, p.numero_poliza, p.compania,
            p.fecha_fin, p.estado AS poliza_estado,
            p.frecuencia_renovacion,
            DATEDIFF(p.fecha_fin, CURDATE()) AS dias_vence
     FROM historial_cliente h
     JOIN clientes cl ON cl.id = h.cliente_id
     JOIN agentes  a  ON a.id  = h.agente_id
     LEFT JOIN polizas p ON p.cliente_id = h.cliente_id
                        AND p.estado IN ('por_vencer','vencida')
     WHERE h.titulo = '🔄 Solicitud de renovación'
     ORDER BY h.fecha DESC
     LIMIT 100"
)->fetchAll(\PDO::FETCH_ASSOC);

$pageTitle = 'Panel de Coordinación';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php require __DIR__ . '/../partials/_head.php'; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .admin-tabs{display:flex;gap:2px;margin-bottom:24px;border-bottom:2px solid var(--border-color);overflow-x:auto;}
        .admin-tab{padding:10px 16px;font-size:13px;font-weight:600;border:none;background:none;cursor:pointer;color:var(--text-secondary);border-bottom:3px solid transparent;margin-bottom:-2px;display:flex;align-items:center;gap:6px;text-decoration:none;border-radius:6px 6px 0 0;transition:all .15s;white-space:nowrap;}
        .admin-tab:hover{background:var(--bg-secondary);color:var(--text-primary);}
        .admin-tab.active{color:var(--accent-color);border-bottom-color:var(--accent-color);background:var(--info-light);}
        .kpi4{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px;}
        @media(max-width:900px){.kpi4{grid-template-columns:repeat(2,1fr);}}
        .adt{width:100%;border-collapse:collapse;}
        .adt th{font-size:11px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.5px;padding:10px 14px;border-bottom:2px solid var(--border-color);text-align:left;white-space:nowrap;}
        .adt td{padding:11px 14px;border-bottom:1px solid var(--border-color);font-size:13px;vertical-align:middle;}
        .adt tr:last-child td{border-bottom:none;}.adt tbody tr:hover td{background:var(--bg-secondary);}
        .rol-tag{padding:2px 9px;border-radius:99px;font-size:11px;font-weight:700;text-transform:uppercase;}
        .rol-tag.asesor{background:var(--bg-secondary);color:var(--text-secondary);}
        .rol-tag.coordinador{background:var(--info-light);color:var(--accent-color);}
        .rol-tag.superadmin{background:#fef3c7;color:#92400e;}
        .medal{width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;}
        .medal.gold{background:#fef3c7;color:#92400e;}.medal.silver{background:#f1f5f9;color:#475569;}.medal.bronze{background:#fef0e7;color:#7c3a00;}.medal.other{background:var(--bg-secondary);color:var(--text-muted);}
        .fmono{font-family:'Courier New',monospace;font-size:11px;font-weight:700;color:var(--accent-color);background:var(--info-light);padding:2px 6px;border-radius:4px;white-space:nowrap;}
        .ava{width:34px;height:34px;border-radius:50%;background:var(--info-light);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;color:var(--accent-color);flex-shrink:0;}
        .cbar{height:6px;border-radius:99px;background:var(--border-color);min-width:60px;}.cfill{height:100%;border-radius:99px;background:var(--success-color);}
        .chart-container{height:280px;position:relative;}
        .pipe-bar{height:8px;border-radius:99px;display:flex;overflow:hidden;gap:1px;}
        .pipe-seg{height:100%;transition:width .3s;}
        .act-feed{display:flex;flex-direction:column;}
        .act-item{display:flex;gap:12px;padding:12px 16px;border-bottom:1px solid var(--border-color);}
        .act-item:last-child{border-bottom:none;}
        .act-icon{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0;}
        .cb-row{display:flex;align-items:center;gap:10px;padding:8px 14px;border-bottom:1px solid var(--border-color);cursor:pointer;}
        .cb-row:hover{background:var(--bg-secondary);}
        .cb-row input[type=checkbox]{width:16px;height:16px;cursor:pointer;}
        .pagination{display:flex;gap:4px;justify-content:center;padding:16px 0;flex-wrap:wrap;}
        .page-btn{min-width:32px;height:32px;border-radius:6px;border:1px solid var(--border-color);background:var(--bg-card);color:var(--text-primary);font-size:13px;cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0 8px;}
        .page-btn:hover{border-color:var(--accent-color);color:var(--accent-color);}.page-btn.active{background:var(--accent-color);color:#fff;border-color:var(--accent-color);}
        .ramo-icon{width:40px;height:40px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;}
        .pip-num{font-size:20px;font-weight:700;}
        .toolbar{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:16px;}
        .toolbar .search-bar{flex:1;min-width:200px;}
    </style>
</head>
<body>
<script nonce="<?= $cspNonce ?>">
function openModal(id){document.getElementById(id)?.classList.add('active');document.body.style.overflow='hidden';}
function closeModal(id){document.getElementById(id)?.classList.remove('active');document.body.style.overflow='';}
</script>
<div class="crm-wrapper">
<?php require __DIR__ . '/../partials/_navbar.php'; ?>
<main class="main-content">

<header class="page-header" style="margin-bottom:20px;">
    <div class="page-header-content">
        <div>
            <h1 class="page-title">
                <span class="page-title-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></span>
                Panel de Coordinación
            </h1>
            <p class="page-subtitle">
                <?php echo $isSuperadmin ? '⭐ Superadmin' : '🛡️ Coordinador'; ?>
                — <?php echo htmlspecialchars($agente['nombre'].' '.$agente['apellido']); ?>
            </p>
        </div>
    </div>
</header>

<?php if ($tab === 'resumen'): ?>
<div class="kpi4">
    <?php foreach([
        [count($asesores),              'Asesores Activos','accent'],
        [$stats['total_clientes'],      'Clientes Totales','info'],
        ['$'.number_format($ingNeto,0), 'Ingreso Neto Total','success'],
        ['$'.number_format($totalPipe,0),'Pipeline Activo','warning'],
    ] as [$v,$l,$c]): ?>
    <div class="stat-card <?php echo $c; ?>">
        <div class="stat-card-value"><?php echo $v; ?></div>
        <div class="stat-card-label"><?php echo $l; ?></div>
    </div>
    <?php endforeach; ?>
</div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px;">
    <div class="card">
        <div class="card-header"><h3 class="card-title">Distribución por Ramo</h3></div>
        <div class="card-body">
            <?php $ramosInfo = [
                'autos'          => ['bi-car-front-fill','Autos','#0ea5e9','#e0f2fe'],
                'vida'           => ['bi-heart-fill','Vida','#10b981','#d1fae5'],
                'gastos_medicos' => ['bi-hospital-fill','Gastos Médicos','#8b5cf6','#ede9fe'],
            ];
            foreach ($ramosInfo as $key=>[$icon,$nombre,$color,$light]):
                $d=$stats['por_ramo'][$key]??['cotizaciones'=>0,'ventas'=>0];
                $pct=$d['cotizaciones']>0?min(100,round($d['ventas']/$d['cotizaciones']*100)):0;
            ?>
            <div style="display:flex;align-items:center;gap:14px;padding:12px 0;border-bottom:1px solid var(--border-color);">
                <div class="ramo-icon" style="background:<?php echo $light; ?>;color:<?php echo $color; ?>;"><i class="bi <?php echo $icon; ?>"></i></div>
                <div style="flex:1;">
                    <div style="font-weight:600;font-size:13px;margin-bottom:4px;"><?php echo $nombre; ?></div>
                    <div class="cbar"><div class="cfill" style="width:<?php echo $pct; ?>%;background:<?php echo $color; ?>;"></div></div>
                </div>
                <div style="text-align:right;">
                    <div style="font-weight:700;font-size:15px;"><?php echo $d['ventas']; ?></div>
                    <div style="font-size:11px;color:var(--text-muted);"><?php echo $d['cotizaciones']; ?> cot.</div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h3 class="card-title">Asesores Este Mes</h3><span style="font-size:12px;color:var(--text-muted);"><?php echo date('F Y'); ?></span></div>
        <div class="card-body" style="padding:0;">
            <table class="adt">
                <thead><tr><th>Asesor</th><th>Clientes</th><th>Ventas</th><th>Ingreso</th></tr></thead>
                <tbody>
                <?php foreach ($ranking as $ag): ?>
                <tr>
                    <td><div style="display:flex;align-items:center;gap:8px;">
                        <div class="ava"><?php echo strtoupper(substr($ag['nombre'],0,1).substr($ag['apellido'],0,1)); ?></div>
                        <span style="font-weight:600;"><?php echo htmlspecialchars($ag['nombre']); ?></span>
                    </div></td>
                    <td><?php echo $ag['clientes_mes']; ?></td>
                    <td><strong style="color:var(--success-color);"><?php echo $ag['ventas_mes']; ?></strong></td>
                    <td><strong>$<?php echo number_format($ag['ingreso_mes'],0); ?></strong></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php elseif ($tab === 'rendimiento'): ?>
<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
    <div style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:var(--bg-secondary);border-radius:var(--border-radius);flex-wrap:wrap;flex:1;">
        <label style="font-size:13px;font-weight:600;color:var(--text-secondary);">Ver datos de:</label>
        <select class="form-select" id="selFiltroAgente" style="width:auto;">
            <option value="">Todos los asesores</option>
            <?php foreach ($asesores as $as): ?>
            <option value="<?php echo $as['id']; ?>" <?php echo $filtroAgente===$as['id']?'selected':''; ?>>
                <?php echo htmlspecialchars($as['nombre'].' '.$as['apellido']); ?>
            </option>
            <?php endforeach; ?>
        </select>
        <?php if ($filtroAgente): ?><a href="?tab=rendimiento" class="btn btn-ghost btn-sm">✕ Ver todos</a><?php endif; ?>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
        <select id="expMes" class="form-select" style="width:auto;font-size:12px;">
            <option value="">Todos los meses</option>
            <?php for($_m=1;$_m<=12;$_m++): $_ms = str_pad((string)$_m, 2, '0', STR_PAD_LEFT); ?>
            <option value="<?php echo date('Y').'-'.$_ms; ?>"><?php echo date('F', mktime(0, 0, 0, $_m, 1)).' '.date('Y'); ?></option>
            <?php endfor; ?>
        </select>
        <select id="expEstado" class="form-select" style="width:auto;font-size:12px;">
            <option value="">Todos estados</option>
            <option value="pendiente">Pendiente</option><option value="enviada">Enviada</option>
            <option value="aceptada">Aceptada</option><option value="rechazada">Rechazada</option>
            <option value="pagado">Pagado</option>
        </select>
        <button id="btnExportarExcel" class="btn btn-outline btn-sm">📊 Excel</button>
        <button id="btnExportarPdf"   class="btn btn-outline btn-sm">📄 PDF</button>
        <a href="/exportar?formato=excel&tipo=ranking" class="btn btn-outline btn-sm">🏆 Ranking</a>
    </div>
</div>
<div class="card" style="margin-bottom:24px;">
    <div class="card-header"><h3 class="card-title">Ranking — Todos los Tiempos</h3></div>
    <div class="card-body" style="padding:0;">
        <table class="adt">
            <thead><tr><th>#</th><th>Asesor</th><th>Clientes</th><th>Cotizaciones</th><th>Ventas</th><th>Conversión</th><th>Ingreso Total</th></tr></thead>
            <tbody>
            <?php $mm=['gold','silver','bronze'];
            foreach ($ranking as $i=>$ag):
                if ($filtroAgente && $ag['id']!==$filtroAgente) continue;
                $t=$ag['total_cotizaciones']>0?round($ag['total_ventas']/$ag['total_cotizaciones']*100):0; ?>
            <tr>
                <td><div class="medal <?php echo $mm[$i]??'other'; ?>"><?php echo $i+1; ?></div></td>
                <td><div style="display:flex;align-items:center;gap:10px;">
                    <div class="ava"><?php echo strtoupper(substr($ag['nombre'],0,1).substr($ag['apellido'],0,1)); ?></div>
                    <div>
                        <div style="font-weight:600;"><?php echo htmlspecialchars($ag['nombre'].' '.$ag['apellido']); ?></div>
                        <div style="font-size:11px;color:var(--text-muted);"><?php echo htmlspecialchars($ag['correo']); ?></div>
                    </div>
                </div></td>
                <td><?php echo $ag['total_clientes']; ?></td>
                <td><?php echo $ag['total_cotizaciones']; ?></td>
                <td><strong style="color:var(--success-color);"><?php echo $ag['total_ventas']; ?></strong></td>
                <td><div style="display:flex;align-items:center;gap:8px;">
                    <div class="cbar" style="flex:1;"><div class="cfill" style="width:<?php echo $t; ?>%"></div></div>
                    <span style="font-size:12px;font-weight:600;min-width:32px;"><?php echo $t; ?>%</span>
                </div></td>
                <td><strong>$<?php echo number_format($ag['ingreso_total'],0); ?></strong></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<div style="display:grid;grid-template-columns:2fr 1fr;gap:24px;margin-bottom:24px;">
    <div class="card"><div class="card-header"><h3 class="card-title">Evolución Mensual de Ventas</h3></div><div class="card-body"><div class="chart-container"><canvas id="chartEvolucion"></canvas></div></div></div>
    <div class="card"><div class="card-header"><h3 class="card-title">Coberturas Más Vendidas</h3></div><div class="card-body"><div class="chart-container"><canvas id="chartCoberturas"></canvas></div></div></div>
</div>
<div class="card"><div class="card-header"><h3 class="card-title">Top Vehículos Asegurados</h3></div><div class="card-body"><div class="chart-container"><canvas id="chartVehiculos"></canvas></div></div></div>

<?php elseif ($tab === 'pipeline'): ?>
<?php
$totPend = array_sum(array_column($pipeline,'pendientes'));
$totEnv  = array_sum(array_column($pipeline,'enviadas'));
$totCerr = array_sum(array_column($pipeline,'cerradas'));
$totPerd = array_sum(array_column($pipeline,'perdidas'));
?>
<div class="kpi4">
    <div class="stat-card warning"><div class="stat-card-value"><?php echo $totPend; ?></div><div class="stat-card-label">⏳ Pendientes</div></div>
    <div class="stat-card info">   <div class="stat-card-value"><?php echo $totEnv; ?></div>  <div class="stat-card-label">📤 Enviadas al Cliente</div></div>
    <div class="stat-card success"><div class="stat-card-value"><?php echo $totCerr; ?></div>  <div class="stat-card-label">✅ Cerradas</div></div>
    <div class="stat-card danger"> <div class="stat-card-value"><?php echo $totPerd; ?></div>  <div class="stat-card-label">❌ Perdidas</div></div>
</div>
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Pipeline por Asesor</h3>
        <span style="font-size:12px;color:var(--text-muted);">Valor total: <strong>$<?php echo number_format($totalPipe,0); ?></strong></span>
    </div>
    <div class="card-body" style="padding:0;">
        <table class="adt">
            <thead><tr><th>Asesor</th><th>⏳</th><th>📤</th><th>✅</th><th>❌</th><th>Valor en Proceso</th><th>Ingreso Cerrado</th><th>Barra</th></tr></thead>
            <tbody>
            <?php foreach ($pipeline as $p):
                $tot = $p['pendientes']+$p['enviadas']+$p['cerradas']+$p['perdidas'];
                $pP  = $tot>0?round($p['pendientes']/$tot*100):0;
                $pE  = $tot>0?round($p['enviadas']/$tot*100):0;
                $pC  = $tot>0?round($p['cerradas']/$tot*100):0;
                $pPr = $tot>0?round($p['perdidas']/$tot*100):0;
            ?>
            <tr>
                <td><div style="display:flex;align-items:center;gap:8px;">
                    <div class="ava"><?php echo strtoupper(substr($p['nombre'],0,1).substr($p['apellido'],0,1)); ?></div>
                    <strong><?php echo htmlspecialchars($p['nombre'].' '.$p['apellido']); ?></strong>
                </div></td>
                <td><span class="pip-num" style="color:#f59e0b;"><?php echo $p['pendientes']; ?></span></td>
                <td><span class="pip-num" style="color:#0ea5e9;"><?php echo $p['enviadas']; ?></span></td>
                <td><span class="pip-num" style="color:#10b981;"><?php echo $p['cerradas']; ?></span></td>
                <td><span class="pip-num" style="color:#ef4444;"><?php echo $p['perdidas']; ?></span></td>
                <td><strong>$<?php echo number_format((float)$p['valor_pipeline'],0); ?></strong></td>
                <td style="color:var(--success-color);">$<?php echo number_format((float)$p['valor_cerrado'],0); ?></td>
                <td style="min-width:100px;">
                    <div class="pipe-bar">
                        <div class="pipe-seg" style="width:<?php echo $pP; ?>%;background:#f59e0b;"></div>
                        <div class="pipe-seg" style="width:<?php echo $pE; ?>%;background:#0ea5e9;"></div>
                        <div class="pipe-seg" style="width:<?php echo $pC; ?>%;background:#10b981;"></div>
                        <div class="pipe-seg" style="width:<?php echo $pPr; ?>%;background:#ef4444;"></div>
                    </div>
                    <div style="font-size:10px;color:var(--text-muted);margin-top:2px;"><?php echo $pC; ?>% cierre</div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($tab === 'actividad'): ?>
<div style="display:grid;grid-template-columns:2fr 1fr;gap:24px;">
    <div class="card">
        <div class="card-header" style="flex-wrap:wrap;gap:8px;">
            <h3 class="card-title">Actividad Reciente del Equipo</h3>
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                <select class="form-select" id="selFiltroActividad" style="width:auto;font-size:12px;">
                    <option value="">Todos los asesores</option>
                    <?php foreach ($asesores as $as): ?>
                    <option value="<?php echo $as['id']; ?>" <?php echo (int)$filtroActAgente===$as['id']?'selected':''; ?>>
                        <?php echo htmlspecialchars($as['nombre'].' '.$as['apellido']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($filtroActAgente): ?><a href="?tab=actividad" class="btn btn-ghost btn-sm" style="font-size:11px;">✕ Ver todos</a><?php endif; ?>
                <span style="font-size:11px;color:var(--text-muted);"><?php echo count($actividad); ?> acciones</span>
            </div>
        </div>
        <div class="act-feed" id="actFeed">
        <?php
        $icons = ['registro'=>['🧑','#e0f2fe'],'llamada'=>['📞','#f0fdf4'],'cotizacion'=>['📄','#fef3c7'],
                  'cita'=>['📅','#fdf4ff'],'nota'=>['📝','#f8fafc'],'venta'=>['🎉','#dcfce7'],'cambio_estado'=>['🔄','#f1f5f9']];
        foreach ($actividad as $act):
            $ic = $icons[$act['tipo']] ?? ['•','#f8fafc'];
            $diff = time()-strtotime($act['fecha']);
            $hace = $diff<60?'Hace un momento':($diff<3600?'Hace '.round($diff/60).' min':($diff<86400?'Hace '.round($diff/3600).'h':date('d/m/Y H:i',strtotime($act['fecha']))));
        ?>
        <div class="act-item">
            <div class="act-icon" style="background:<?php echo $ic[1]; ?>;"><?php echo $ic[0]; ?></div>
            <div style="flex:1;">
                <div style="font-size:13px;font-weight:600;"><?php echo htmlspecialchars($act['titulo']); ?></div>
                <div style="font-size:11px;color:var(--text-muted);margin-top:2px;display:flex;gap:8px;flex-wrap:wrap;">
                    <span>👤 <?php echo htmlspecialchars($act['agente_nombre']); ?></span>
                    <span>·</span><span><?php echo htmlspecialchars($act['cliente_nombre']); ?></span>
                    <span>·</span><span><?php echo $hace; ?></span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($actividad)): ?>
        <div style="text-align:center;padding:32px;color:var(--text-muted);font-size:13px;">Sin actividad registrada</div>
        <?php endif; ?>
        </div>
        <div id="actPag" style="display:flex;gap:4px;justify-content:center;padding:10px;flex-wrap:wrap;border-top:1px solid var(--border-color);"></div>
    </div>
    <div>
        <?php if ($filtroActAgente):
            $asFi = array_values(array_filter($agLista, fn($a) => (int)$a['id']===(int)$filtroActAgente))[0] ?? null;
            if ($asFi):
                $sacs = $db->prepare("SELECT COUNT(*) FROM clientes WHERE agente_id=?"); $sacs->execute([$filtroActAgente]); $acs=(int)$sacs->fetchColumn();
                $scos = $db->prepare("SELECT COUNT(*) t,SUM(estado IN('aceptada','pagado')) v FROM cotizaciones WHERE agente_id=?"); $scos->execute([$filtroActAgente]); $aco=$scos->fetch();
                $scis = $db->prepare("SELECT COUNT(*) FROM citas WHERE agente_id=? AND estado='programada' AND fecha>=CURDATE()"); $scis->execute([$filtroActAgente]); $aci=(int)$scis->fetchColumn();
        ?>
        <div class="card" style="margin-bottom:16px;">
            <div class="card-header"><h3 class="card-title">📋 Perfil del Asesor</h3></div>
            <div class="card-body">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
                    <div class="ava" style="width:44px;height:44px;font-size:17px;"><?php echo strtoupper(substr($asFi['nombre'],0,1).substr($asFi['apellido'],0,1)); ?></div>
                    <div>
                        <div style="font-weight:700;"><?php echo htmlspecialchars($asFi['nombre'].' '.$asFi['apellido']); ?></div>
                        <div style="font-size:11px;color:var(--text-muted);"><?php echo htmlspecialchars($asFi['correo']); ?></div>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                    <?php foreach([['Clientes',$acs,'var(--accent-color)'],['Ventas',(int)($aco['v']??0),'var(--success-color)'],['Cotizaciones',(int)($aco['t']??0),'var(--text-primary)'],['Citas prox.',$aci,'var(--warning-color)']] as [$lbl,$val,$col]): ?>
                    <div style="background:var(--bg-secondary);border-radius:6px;padding:10px;text-align:center;">
                        <div style="font-size:22px;font-weight:800;color:<?php echo $col; ?>"><?php echo $val; ?></div>
                        <div style="font-size:10px;color:var(--text-muted);"><?php echo $lbl; ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; endif; ?>
        <div class="card" style="margin-bottom:16px;">
            <div class="card-header"><h3 class="card-title">Citas de Hoy</h3><span style="font-size:12px;color:var(--text-muted);"><?php echo date('d/m/Y'); ?></span></div>
            <div class="card-body" style="padding:0;">
                <?php if (empty($citasHoy)): ?>
                <p style="font-size:13px;color:var(--text-muted);text-align:center;padding:16px;">Sin citas programadas para hoy</p>
                <?php else: foreach ($citasHoy as $ci): ?>
                <div style="display:flex;gap:10px;padding:10px 16px;border-bottom:1px solid var(--border-color);">
                    <span style="font-size:13px;font-weight:700;color:var(--accent-color);min-width:44px;"><?php echo substr($ci['hora'],0,5); ?></span>
                    <div>
                        <div style="font-size:13px;font-weight:600;"><?php echo htmlspecialchars($ci['cliente']); ?></div>
                        <div style="font-size:11px;color:var(--text-muted);"><?php echo htmlspecialchars($ci['asesor'].' · '.ucfirst($ci['tipo'])); ?></div>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</div>

<?php elseif ($tab === 'asesores'): ?>
<div style="display:grid;grid-template-columns:<?php echo $asesorSel?'1fr 2fr':'1fr'; ?>;gap:24px;">
    <div>
        <div class="toolbar">
            <div class="search-bar">
                <span class="search-bar-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></span>
                <input type="text" id="srchA" class="search-bar-input" placeholder="Nombre, correo...">
            </div>
            <?php if ($isSuperadmin): ?>
            <button class="btn btn-primary" data-action="adm-abrirCrearAgente">+ Nuevo</button>
            <?php endif; ?>
        </div>
        <div class="card">
            <div class="card-header"><h3 class="card-title">Asesores <span class="badge badge-neutral" id="cntA"><?php echo count($agLista); ?></span></h3></div>
            <div class="card-body" style="padding:0;">
                <table class="adt"><thead><tr><th>Asesor</th><th>Rol</th><th>Clientes</th><th>Acciones</th></tr></thead>
                <tbody id="tbA">
                <?php foreach ($agLista as $ag):
                    $met    = array_values(array_filter($ranking,fn($r)=>$r['id']===(int)$ag['id']))[0]??[];
                    $activo = (int)($ag['activo']??1);
                    $puedeEditar = ($isSuperadmin && in_array($ag['rol'],['asesor','coordinador'],true))
                                || ($_agenteRol === 'coordinador' && $ag['rol'] === 'asesor');
                ?>
                <tr data-n="<?php echo strtolower($ag['nombre'].' '.$ag['apellido']); ?>" data-c="<?php echo strtolower($ag['correo']); ?>">
                    <td><div style="display:flex;align-items:center;gap:10px;">
                        <div class="ava" style="<?php echo !$activo?'opacity:.4;':''; ?>"><?php echo strtoupper(substr($ag['nombre'],0,1).substr($ag['apellido'],0,1)); ?></div>
                        <div>
                            <div style="font-weight:600;<?php echo !$activo?'color:var(--text-muted);':''; ?>">
                                <?php echo htmlspecialchars($ag['nombre'].' '.$ag['apellido']); ?>
                                <?php if ($ag['id']==$agenteId): ?><span style="font-size:11px;color:var(--text-muted);"> (tú)</span><?php endif; ?>
                                <?php if (!$activo): ?><span style="font-size:10px;background:#fee2e2;color:#dc2626;border-radius:4px;padding:1px 5px;margin-left:4px;">Inactivo</span><?php endif; ?>
                            </div>
                            <div style="font-size:11px;color:var(--text-muted);"><?php echo htmlspecialchars($ag['correo']); ?></div>
                        </div>
                    </div></td>
                    <td><span class="rol-tag <?php echo $ag['rol']; ?>"><?php echo $ag['rol']; ?></span></td>
                    <td><?php $nc=$met['total_clientes']??0; ?>
                        <?php if ($ag['rol']==='asesor'&&$activo&&$nc>0): ?>
                        <a href="?tab=asesores&asesor_id=<?php echo $ag['id']; ?>" class="btn btn-sm btn-ghost" style="font-size:12px;"><?php echo $nc; ?> clientes →</a>
                        <?php else: ?><span style="font-size:12px;color:var(--text-muted);"><?php echo $nc; ?></span><?php endif; ?>
                    </td>
                    <!-- PARCHE: celda de acciones con botón ✏️ editar agente -->
                    <td class="adm-stop-prop">
                        <?php if ($ag['id'] != $agenteId && ($isSuperadmin || $_agenteRol === 'coordinador')): ?>
                        <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                            <?php if ($isSuperadmin): ?>
                            <select class="form-select adm-rol-select" style="font-size:11px;padding:3px 6px;width:auto;"
                                    data-agente-id="<?php echo $ag['id']; ?>">
                                <option value="asesor"      <?php echo $ag['rol']==='asesor'     ?'selected':''; ?>>Asesor</option>
                                <option value="coordinador" <?php echo $ag['rol']==='coordinador'?'selected':''; ?>>Coord.</option>
                                <option value="superadmin"  <?php echo $ag['rol']==='superadmin' ?'selected':''; ?>>SA</option>
                            </select>
                            <?php endif; ?>

                            <?php if ($puedeEditar && $activo): ?>
                            <button class="btn btn-sm btn-ghost"
                                    title="Editar nombre / contraseña"
                                    data-action="adm-editarAgente"
                                    data-id="<?php echo $ag['id']; ?>"
                                    data-nombre="<?php echo htmlspecialchars($ag['nombre'],ENT_QUOTES); ?>"
                                    data-apellido="<?php echo htmlspecialchars($ag['apellido'],ENT_QUOTES); ?>"
                                    data-rol="<?php echo htmlspecialchars($ag['rol'],ENT_QUOTES); ?>">
                                ✏️
                            </button>
                            <?php endif; ?>

                            <?php if ($isSuperadmin): ?>
                            <button class="btn btn-sm btn-ghost"
                                    style="color:<?php echo $activo?'var(--danger-color)':'var(--success-color)'; ?>;"
                                    data-action="adm-toggleAgente"
                                    data-id="<?php echo $ag['id']; ?>"
                                    data-nombre="<?php echo htmlspecialchars($ag['nombre'],ENT_QUOTES); ?>"
                                    data-activo="<?php echo $activo; ?>">
                                <?php echo $activo ? '⊗' : '✓'; ?>
                            </button>
                            <?php endif; ?>

                            <?php if ($_agenteRol === 'coordinador' && $ag['rol'] === 'asesor'): ?>
                            <button class="btn btn-sm btn-ghost"
                                    style="color:<?php echo $activo?'var(--danger-color)':'var(--success-color)'; ?>;"
                                    data-action="adm-toggleAgente"
                                    data-id="<?php echo $ag['id']; ?>"
                                    data-nombre="<?php echo htmlspecialchars($ag['nombre'],ENT_QUOTES); ?>"
                                    data-activo="<?php echo $activo; ?>">
                                <?php echo $activo ? '⊗' : '✓'; ?>
                            </button>
                            <?php endif; ?>

                            <?php if ($isSuperadmin): ?>
                            <button class="btn btn-sm btn-ghost"
                                    title="Ver historial del coordinador"
                                    data-action="adm-verHistorialCoord"
                                    data-id="<?php echo $ag['id']; ?>"
                                    data-nombre="<?php echo htmlspecialchars($ag['nombre'].' '.$ag['apellido'],ENT_QUOTES); ?>"
                                    style="<?php echo $ag['rol']==='coordinador'?'':'display:none;'; ?>">
                                📋
                            </button>
                            <?php endif; ?>
                        </div>
                        <?php else: ?><span style="font-size:12px;color:var(--text-muted);">—</span><?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody></table>
                <div class="pagination" id="pgA"></div>
            </div>
        </div>
    </div>

    <?php if ($asesorSel && $asesorInfo): ?>
    <div><div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">Clientes de <?php echo htmlspecialchars($asesorInfo['nombre'].' '.$asesorInfo['apellido']); ?> <span class="badge badge-neutral"><?php echo count($clientesAsesor); ?></span></h3>
                <p style="font-size:12px;color:var(--text-muted);margin-top:2px;">Selecciona clientes para reasignarlos</p>
            </div>
            <a href="?tab=asesores" class="btn btn-ghost btn-sm">✕ Cerrar</a>
        </div>
        <div class="card-body" style="padding:0;">
            <div id="reasignarBar" style="display:none;padding:12px 16px;background:var(--info-light);border-bottom:1px solid var(--border-color);align-items:center;gap:12px;flex-wrap:wrap;">
                <span id="selCount" style="font-size:13px;font-weight:600;color:var(--accent-color);"></span>
                <span style="font-size:13px;">→ Reasignar a:</span>
                <select class="form-select" id="nuevoAsesor" style="width:auto;">
                    <option value="">Selecciona asesor...</option>
                    <?php foreach ($asesores as $as): if ($as['id']===$asesorSel) continue; ?>
                    <option value="<?php echo $as['id']; ?>"><?php echo htmlspecialchars($as['nombre'].' '.$as['apellido']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-primary btn-sm" data-action="adm-ejecutarReasignacion">Reasignar</button>
                <button class="btn btn-ghost btn-sm"   data-action="adm-deseleccionarTodos">Cancelar selección</button>
            </div>
            <div style="padding:8px 14px;background:var(--bg-secondary);border-bottom:1px solid var(--border-color);display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                <input type="checkbox" id="selectAll" style="width:16px;height:16px;cursor:pointer;">
                <label for="selectAll" style="font-size:12px;font-weight:600;cursor:pointer;">Sel. todos</label>
                <input type="text" id="srchCl" placeholder="Buscar..." style="padding:4px 10px;border:1px solid var(--border-color);border-radius:6px;font-size:12px;width:160px;">
            </div>
            <div id="clientesList">
            <?php foreach ($clientesAsesor as $cl): ?>
            <div class="cb-row" data-action="adm-toggleCliente" data-id="<?php echo $cl['id']; ?>"
                 id="cbrow_<?php echo $cl['id']; ?>"
                 data-n="<?php echo strtolower($cl['nombre'].' '.$cl['apellido_paterno']); ?>">
                <input type="checkbox" id="cb_<?php echo $cl['id']; ?>" value="<?php echo $cl['id']; ?>">
                <div style="flex:1;">
                    <div style="font-size:13px;font-weight:600;"><?php echo htmlspecialchars($cl['nombre'].' '.$cl['apellido_paterno'].' '.$cl['apellido_materno']); ?></div>
                    <div style="font-size:11px;color:var(--text-muted);"><?php echo htmlspecialchars($cl['correo']); ?></div>
                </div>
                <div style="text-align:right;">
                    <span class="badge badge-neutral" style="font-size:10px;"><?php echo $cl['total_cotizaciones']; ?> cot.</span>
                    <div style="font-size:10px;color:var(--text-muted);margin-top:2px;"><?php echo date('d/m/Y',strtotime($cl['fecha_registro'])); ?></div>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
            <div class="pagination" id="pgClientes"></div>
        </div>
    </div></div>
    <?php endif; ?>
</div>

<?php elseif ($tab === 'emision'):
    require __DIR__ . '/_tab_emision.php'; ?>

<?php elseif ($tab === 'renovaciones'): ?>
<?php
$totalRenov = count($renovacionesPendientes);
$urgentes   = count(array_filter($renovacionesPendientes, fn($r) => isset($r['dias_vence']) && (int)$r['dias_vence'] <= 7));
?>
<div class="kpi4" style="grid-template-columns:repeat(3,1fr);margin-bottom:20px;">
    <div class="stat-card warning"><div class="stat-card-value"><?= $totalRenov ?></div><div class="stat-card-label">🔁 Solicitudes</div></div>
    <div class="stat-card <?= $urgentes > 0 ? 'danger' : 'success' ?>"><div class="stat-card-value"><?= $urgentes ?></div><div class="stat-card-label">🚨 Urgentes (≤7 días)</div></div>
    <div class="stat-card info"><div class="stat-card-value"><?= $totalRenov - $urgentes ?></div><div class="stat-card-label">📋 En seguimiento</div></div>
</div>
<?php if (empty($renovacionesPendientes)): ?>
<div class="card" style="text-align:center;padding:56px;">
    <div style="font-size:48px;margin-bottom:12px;">✅</div>
    <div style="font-size:16px;font-weight:700;color:var(--text-primary);">Sin solicitudes de renovación</div>
    <div style="font-size:13px;color:var(--text-muted);margin-top:6px;">Cuando un asesor solicite renovar una póliza, aparecerá aquí.</div>
</div>
<?php else: ?>
<div class="toolbar" style="margin-bottom:16px;">
    <div class="search-bar" style="flex:1;">
        <span class="search-bar-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></span>
        <input type="text" id="srchRenov" class="search-bar-input" placeholder="Buscar por cliente, asesor, compañía…">
    </div>
</div>
<div style="display:flex;flex-direction:column;gap:14px;" id="renovLista">
<?php foreach ($renovacionesPendientes as $r):
    $dias   = isset($r['dias_vence']) ? (int)$r['dias_vence'] : null;
    $urgente= $dias !== null && $dias <= 7;
    $vencida= $dias !== null && $dias < 0;
    $borderColor = $vencida ? 'var(--danger-color)' : ($urgente ? '#f59e0b' : 'var(--accent-color)');
    $fechaSol = $r['fecha'] ? date('d/m/Y H:i', strtotime($r['fecha'])) : '—';
    $fechaFin = !empty($r['fecha_fin']) && $r['fecha_fin'] !== '0000-00-00' ? date('d/m/Y', strtotime($r['fecha_fin'])) : null;
    $polEstLabel = ['vigente'=>'Vigente','por_vencer'=>'Por vencer','vencida'=>'Vencida','cancelada'=>'Cancelada'][$r['poliza_estado'] ?? ''] ?? '—';
    $polEstBadge = ['vigente'=>'badge-success','por_vencer'=>'badge-warning','vencida'=>'badge-danger','cancelada'=>'badge-neutral'][$r['poliza_estado'] ?? ''] ?? 'badge-neutral';
?>
<div class="card renov-card"
     data-cliente="<?= strtolower($r['cliente_nombre']) ?>"
     data-asesor="<?= strtolower($r['asesor_nombre']) ?>"
     data-compania="<?= strtolower($r['compania'] ?? '') ?>"
     style="border-left:4px solid <?= $borderColor ?>;">
    <div class="card-body" style="padding:16px 20px;">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">
            <div style="flex:1;min-width:220px;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;flex-wrap:wrap;">
                    <?php if ($vencida): ?><span class="badge badge-danger">🚨 Póliza vencida</span>
                    <?php elseif ($urgente): ?><span class="badge badge-warning">⚠️ Urgente — <?= $dias ?>d restantes</span>
                    <?php elseif ($dias !== null): ?><span class="badge badge-info"><?= $dias ?>d para vencer</span>
                    <?php endif; ?>
                    <?php if (!empty($r['poliza_estado'])): ?>
                    <span class="badge <?= $polEstBadge ?>" style="font-size:10px;"><?= $polEstLabel ?></span>
                    <?php endif; ?>
                </div>
                <div style="font-size:15px;font-weight:700;margin-bottom:4px;"><?= htmlspecialchars($r['cliente_nombre']) ?></div>
                <div style="font-size:12px;color:var(--text-muted);display:flex;gap:12px;flex-wrap:wrap;margin-bottom:6px;">
                    <?php if ($r['cliente_correo']): ?><span>✉️ <?= htmlspecialchars($r['cliente_correo']) ?></span><?php endif; ?>
                    <?php if ($r['cliente_tel']): ?><span>📞 <?= htmlspecialchars($r['cliente_tel']) ?></span><?php endif; ?>
                    <span>👤 Asesor: <strong><?= htmlspecialchars($r['asesor_nombre']) ?></strong></span>
                </div>
                <?php if (!empty($r['numero_poliza']) || !empty($r['compania'])): ?>
                <div style="font-size:12px;color:var(--text-secondary);display:flex;gap:10px;flex-wrap:wrap;">
                    <?php if ($r['compania']): ?><span>🏢 <?= htmlspecialchars($r['compania']) ?></span><?php endif; ?>
                    <?php if ($r['numero_poliza']): ?><span># <code style="font-size:11px;"><?= htmlspecialchars($r['numero_poliza']) ?></code></span><?php endif; ?>
                    <?php if ($r['frecuencia_renovacion']): ?><span>🔄 <?= ucfirst($r['frecuencia_renovacion']) ?></span><?php endif; ?>
                    <?php if ($fechaFin): ?><span>⏳ Vence: <strong><?= $fechaFin ?></strong></span><?php endif; ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($r['descripcion'])): ?>
                <div style="margin-top:8px;padding:6px 10px;background:var(--bg-secondary);border-radius:6px;font-size:11px;color:var(--text-muted);">
                    💬 <?= htmlspecialchars($r['descripcion']) ?>
                </div>
                <?php endif; ?>
                <div style="margin-top:8px;font-size:11px;color:var(--text-muted);">📅 Solicitado: <?= $fechaSol ?></div>
            </div>
            <div style="display:flex;flex-direction:column;gap:8px;min-width:190px;align-items:flex-end;">
                <?php if ($r['cliente_id']): ?>
                <a href="/cliente/<?= (int)$r['cliente_id'] ?>" class="btn btn-outline btn-sm" style="width:100%;text-align:center;">👁 Ver cliente</a>
                <?php endif; ?>
                <?php if ($r['poliza_id']): ?>
                <button class="btn btn-primary btn-sm" style="width:100%;"
                        data-action="adm-renovGestionarPoliza"
                        data-poliza-id="<?= (int)$r['poliza_id'] ?>"
                        data-cliente-id="<?= (int)$r['cliente_id'] ?>"
                        data-numero="<?= htmlspecialchars($r['numero_poliza'] ?? '', ENT_QUOTES) ?>"
                        data-compania="<?= htmlspecialchars($r['compania'] ?? '', ENT_QUOTES) ?>"
                        data-fecha-fin="<?= htmlspecialchars($r['fecha_fin'] ?? '', ENT_QUOTES) ?>"
                        data-frecuencia="<?= htmlspecialchars($r['frecuencia_renovacion'] ?? 'anual', ENT_QUOTES) ?>">
                    🔁 Gestionar renovación
                </button>
                <?php endif; ?>
                <button class="btn btn-ghost btn-sm" style="width:100%;color:var(--success-color);"
                        data-action="adm-renovMarcarGestionada"
                        data-cliente-id="<?= (int)$r['cliente_id'] ?>"
                        data-fecha="<?= htmlspecialchars($r['fecha'] ?? '', ENT_QUOTES) ?>">
                    ✅ Marcar gestionada
                </button>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Modal: Gestionar Renovación -->
<div class="modal-overlay" id="modalGestionarRenovacion">
    <div class="modal" style="max-width:560px;">
        <div class="modal-header"><h3 class="modal-title">🔁 Gestionar Renovación</h3><button class="modal-close" data-close="modalGestionarRenovacion">&times;</button></div>
        <div class="modal-body">
            <input type="hidden" id="grPolizaId"><input type="hidden" id="grClienteId">
            <div style="background:var(--info-light);border:1px solid var(--info-color,#3b82f6);border-radius:8px;padding:12px 14px;margin-bottom:18px;font-size:13px;">
                <div><strong>Póliza:</strong> <span id="grNumero" style="font-family:monospace;color:var(--accent-color);"></span></div>
                <div style="margin-top:4px;"><strong>Compañía:</strong> <span id="grCompania"></span></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Nueva fecha de inicio</label><input type="date" class="form-input" id="grFechaInicio"></div>
                <div class="form-group"><label class="form-label">Nueva fecha de vencimiento</label><input type="date" class="form-input" id="grFechaFin"></div>
            </div>
            <div class="form-group">
                <label class="form-label">💳 Comprobante de pago (PDF/imagen) <span style="font-size:11px;color:var(--text-muted);">— opcional</span></label>
                <input type="file" class="form-input" id="grArchivoPoliza" accept=".pdf,.jpg,.jpeg,.png,.webp" style="padding:6px;">
                <p style="font-size:11px;color:var(--text-muted);margin-top:4px;">El archivo respaldará el pago y marcará la renovación como confirmada automáticamente.</p>
            </div>
            <div class="form-group"><label class="form-label">Nuevo número de póliza (si cambió)</label><input type="text" class="form-input" id="grNuevoNumero" placeholder="Dejar vacío para mantener el actual"></div>
            <div class="form-group"><label class="form-label">Notas del coordinador</label><textarea class="form-input" id="grNotas" rows="2" style="resize:vertical;"></textarea></div>
            <div class="form-group">
                <label class="form-label">📎 Nueva póliza (PDF/imagen) <span style="font-size:11px;color:var(--text-muted);">— opcional</span></label>
                <input type="file" class="form-input" id="grArchivoPoliza" accept=".pdf,.jpg,.jpeg,.png,.webp" style="padding:6px;">
                <p style="font-size:11px;color:var(--text-muted);margin-top:4px;">El archivo se adjuntará a la póliza y se marcará como renovación.</p>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" data-close="modalGestionarRenovacion">Cancelar</button>
            <button class="btn btn-primary" data-action="adm-confirmarRenovacion">💾 Guardar renovación</button>
        </div>
    </div>
</div>

<script nonce="<?= $cspNonce ?>">
(function () {
    document.getElementById('srchRenov')?.addEventListener('input', function () {
        const q = this.value.toLowerCase().trim();
        document.querySelectorAll('#renovLista .renov-card').forEach(card => {
            const txt = (card.dataset.cliente + ' ' + card.dataset.asesor + ' ' + card.dataset.compania);
            card.closest('.card').style.display = (!q || txt.includes(q)) ? '' : 'none';
        });
    });

    document.addEventListener('click', function (e) {
        const el = e.target.closest('[data-action]');
        if (!el) return;
        switch (el.dataset.action) {
            case 'adm-renovGestionarPoliza': {
                document.getElementById('grPolizaId').value  = el.dataset.polizaId;
                document.getElementById('grClienteId').value = el.dataset.clienteId;
                document.getElementById('grNumero').textContent   = el.dataset.numero || '—';
                document.getElementById('grCompania').textContent = el.dataset.compania || '—';
                document.getElementById('grFrecuencia').value     = el.dataset.frecuencia || 'anual';
                document.getElementById('grNuevoNumero').value    = '';
                document.getElementById('grNotas').value          = '';
                const hoy = new Date(); const fin = new Date(hoy); fin.setFullYear(fin.getFullYear() + 1);
                document.getElementById('grFechaInicio').value = hoy.toISOString().split('T')[0];
                document.getElementById('grFechaFin').value    = fin.toISOString().split('T')[0];
                if (typeof openModal === 'function') openModal('modalGestionarRenovacion');
                break;
            }
            case 'adm-confirmarRenovacion': {
                const btn = el; const polizaId = document.getElementById('grPolizaId').value; const clienteId = document.getElementById('grClienteId').value;
                const fd = new FormData(); fd.append('accion','renov_actualizar_poliza'); fd.append('poliza_id',polizaId); fd.append('cliente_id',clienteId);
                fd.append('fecha_inicio',document.getElementById('grFechaInicio').value); fd.append('fecha_fin',document.getElementById('grFechaFin').value);
                fd.append('frecuencia',document.getElementById('grFrecuencia').value); fd.append('nuevo_numero',document.getElementById('grNuevoNumero').value.trim());
                fd.append('notas',document.getElementById('grNotas').value.trim());
                const grFile = document.getElementById('grArchivoPoliza');
                if (grFile && grFile.files[0]) fd.append('nueva_poliza_archivo', grFile.files[0]);
                btn.disabled = true; btn.textContent = 'Guardando…';
                fetch('/admin', { method: 'POST', body: fd }).then(r => r.json()).then(d => {
                    btn.disabled = false; btn.textContent = '💾 Guardar renovación';
                    if (d.ok) { if (typeof showToast === 'function') showToast('Póliza renovada y marcada como vigente' + (d.archivo_guardado ? ' · 📎 archivo adjunto' : ''), 'success'); if (typeof closeModal === 'function') closeModal('modalGestionarRenovacion'); setTimeout(() => location.reload(), 1200); }
                    else { if (typeof showToast === 'function') showToast(d.msg || 'Error al guardar', 'error'); }
                }).catch(() => { btn.disabled = false; btn.textContent = '💾 Guardar renovación'; if (typeof showToast === 'function') showToast('Error de conexión', 'error'); });
                break;
            }
            case 'adm-renovMarcarGestionada': {
                const clienteId = el.dataset.clienteId; const fecha = el.dataset.fecha;
                if (!confirm('¿Marcar esta solicitud como gestionada?')) break;
                const fd = new FormData(); fd.append('accion','renov_marcar_gestionada'); fd.append('cliente_id',clienteId); fd.append('fecha',fecha);
                fetch('/admin', { method: 'POST', body: fd }).then(r => r.json()).then(d => {
                    if (d.ok) { el.closest('.card').remove(); if (typeof showToast === 'function') showToast('Marcada como gestionada', 'success'); }
                    else { if (typeof showToast === 'function') showToast(d.msg || 'Error', 'error'); }
                }).catch(() => { if (typeof showToast === 'function') showToast('Error de conexión', 'error'); });
                break;
            }
        }
    });
}());
</script>

<?php elseif ($tab === 'polizas'): ?>
<div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap;">
    <h2 style="font-size:17px;font-weight:600;margin:0;">📋 Historial de Pólizas</h2>
    <select id="polFiltroAsesor" class="form-select" style="width:auto;font-size:13px;">
        <option value="">Todos los asesores</option>
        <?php foreach ($agLista as $ag): ?>
        <option value="<?= $ag['id'] ?>"><?= htmlspecialchars($ag['nombre'].' '.$ag['apellido']) ?></option>
        <?php endforeach; ?>
    </select>
    <select id="polFiltroEstado" class="form-select" style="width:auto;font-size:13px;">
        <option value="">Todos los estados</option>
        <option value="pagada">✅ Pagadas</option>
        <option value="pendiente">⏳ Pendientes de pago</option>
        <option value="por_vencer">⚠️ Por vencer (≤30 días)</option>
        <option value="vencida">❌ Vencidas</option>
    </select>
    <button class="btn btn-outline btn-sm" id="btnCargarPolizas">🔄 Cargar / Actualizar</button>
</div>
<div id="polKpis" style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px;"></div>
<div class="card">
    <div class="card-body" style="padding:0;">
        <div id="polTablaWrap" style="overflow-x:auto;">
            <p style="padding:20px;color:var(--text-muted);font-size:13px;">Haz clic en "Cargar / Actualizar" para ver las pólizas.</p>
        </div>
    </div>
</div>
<script nonce="<?= $cspNonce ?>">
(function(){
    function cargarPolizas(){
        var fd = new FormData();
        fd.append('accion','get_polizas_resumen');
        var as = document.getElementById('polFiltroAsesor');
        if(as && as.value) fd.append('asesor_id', as.value);
        fetch('/admin', {method:'POST', body:fd})
            .then(r=>r.text())
            .then(text=>{
                try {
                    var d = JSON.parse(text);
                    if(!d.ok){document.getElementById('polTablaWrap').innerHTML='<p style="padding:20px;color:red;">'+d.msg+'</p>';return;}
                    // KPIs
                    var kd=[['Total',d.total,''],['✅ Pagadas',d.pagadas,'success'],['⏳ Pendientes',d.pendientes,'warning'],['⚠️ Por vencer',d.por_vencer,'danger']];
                    document.getElementById('polKpis').innerHTML=kd.map(([l,v,c])=>`<div class="stat-card ${c}" style="padding:12px 16px;"><div class="stat-card-value">${v}</div><div class="stat-card-label">${l}</div></div>`).join('');
                    // Filtro
                    var filtE = document.getElementById('polFiltroEstado').value;
                    var polizas = d.polizas;
                    if(filtE==='pagada') polizas = polizas.filter(p=>p.pago_confirmado==1||p.pago_confirmado===true);
                    else if(filtE==='pendiente') polizas = polizas.filter(p=>!p.pago_confirmado && p.estado!=='vencida');
                    else if(filtE==='por_vencer') polizas = polizas.filter(p=>p.dias_restantes!==null && parseInt(p.dias_restantes)>=0 && parseInt(p.dias_restantes)<=30);
                    else if(filtE==='vencida') polizas = polizas.filter(p=>p.estado==='vencida');
                    if(!polizas.length){document.getElementById('polTablaWrap').innerHTML='<p style="padding:20px;color:var(--text-muted);">Sin pólizas para el filtro seleccionado.</p>';return;}
                    var estadoBadge={vigente:'<span class="badge badge-success">Vigente</span>',por_vencer:'<span class="badge badge-warning">Por vencer</span>',vencida:'<span class="badge badge-danger">Vencida</span>',cancelada:'<span class="badge badge-neutral">Cancelada</span>'};
                    var rows = polizas.map(p=>{
                        var dias = parseInt(p.dias_restantes);
                        var diasTxt = isNaN(dias) ? '—' : (dias<0 ? `<span style="color:var(--danger-color);">Vencida hace ${Math.abs(dias)}d</span>` : `<span style="color:${dias<=5?'var(--danger-color)':dias<=15?'var(--warning-color)':'inherit'}">${dias} días</span>`);
                        var pagoBadge = (p.pago_confirmado==1||p.pago_confirmado===true) ? `<span class="badge badge-success">✅ Pagada</span>` : `<span class="badge badge-warning">⏳ Pendiente</span>`;
                        return `<tr>
                            <td><a href="/cliente/${p.cliente_id_ref}" style="font-weight:600;color:var(--accent-color);">${p.cliente_nombre??'—'}</a><br><span style="font-size:11px;color:var(--text-muted);">${p.asesor_nombre??''}</span></td>
                            <td>${p.compania??'—'}</td>
                            <td style="font-size:12px;">${p.numero_poliza??'—'}</td>
                            <td>${estadoBadge[p.estado]??p.estado}</td>
                            <td>${pagoBadge}${p.metodo_pago?`<br><span style="font-size:11px;color:var(--text-muted);">${p.metodo_pago}</span>`:''}</td>
                            <td>${p.fecha_fin?new Date(p.fecha_fin).toLocaleDateString('es-MX',{day:'2-digit',month:'2-digit',year:'numeric'}):'—'}</td>
                            <td>${diasTxt}</td>
                        </tr>`;
                    }).join('');
                    document.getElementById('polTablaWrap').innerHTML=`<table class="data-table"><thead><tr><th>Cliente / Asesor</th><th>Compañía</th><th>No. Póliza</th><th>Estado</th><th>Pago</th><th>Vence</th><th>Días restantes</th></tr></thead><tbody>${rows}</tbody></table>`;
                } catch(e) {
                    document.getElementById('polTablaWrap').innerHTML='<div style="padding:20px;color:red;"><b>Error oculto detectado:</b><br><pre style="background:#fee2e2;padding:10px;border-radius:6px;overflow-x:auto;">' + text + '</pre></div>';
                }
            })
            .catch(err => {
                document.getElementById('polTablaWrap').innerHTML='<p style="padding:20px;color:red;">Error de conexión crítico: ' + err.message + '</p>';
            });
    }
    document.getElementById('btnCargarPolizas').addEventListener('click', cargarPolizas);
    document.getElementById('polFiltroEstado').addEventListener('change', cargarPolizas);
    document.getElementById('polFiltroAsesor').addEventListener('change', cargarPolizas);
    cargarPolizas();
})();
</script>

<?php elseif ($tab === 'cotizaciones' && $isSuperadmin): ?>
<div class="toolbar">
    <div class="search-bar">
        <span class="search-bar-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></span>
        <input type="text" id="srchC" class="search-bar-input" placeholder="Folio, cliente, compañía...">
    </div>
    <select class="filter-select" id="flAg"><option value="todos">Todos los asesores</option><?php foreach ($agNombres as $an): ?><option value="<?php echo htmlspecialchars($an); ?>"><?php echo htmlspecialchars($an); ?></option><?php endforeach; ?></select>
    <select class="filter-select" id="flEst"><option value="todos">Todos los estados</option><option value="pendiente">Pendiente</option><option value="enviada">Enviada</option><option value="aceptada">Aceptada</option><option value="rechazada">Rechazada</option></select>
    <button id="btnAdminSortVence" class="btn btn-outline" style="display:inline-flex;align-items:center;gap:6px;white-space:nowrap;"><i class="bi bi-clock-history" style="font-size:14px;"></i> Próximos a vencer</button>
</div>
<div class="card">
    <div class="card-header"><h3 class="card-title">Todas las Cotizaciones <span class="badge badge-neutral" id="cntC"><?php echo count($cotsGlobal); ?></span></h3><span style="font-size:11px;color:var(--text-muted);">Doble clic en una fila para ver documentos</span></div>
    <div class="card-body" style="padding:0;">
        <table class="adt"><thead><tr><th>Folio</th><th>Cliente</th><th>Asesor</th><th>Compañía</th><th>Prima</th><th>Estado</th><th>Fecha</th><th>Vence</th><th></th></tr></thead>
        <tbody id="tbC">
        <?php $eb=['pendiente'=>'badge-warning','enviada'=>'badge-info','aceptada'=>'badge-success','rechazada'=>'badge-danger'];
        foreach ($cotsGlobal as $cot): $bd=$eb[$cot['estado']]??'badge-neutral'; ?>
        <tr style="cursor:pointer;"
            data-id="<?php echo $cot['id']; ?>"
            data-f="<?php echo strtolower($cot['folio']??''); ?>"
            data-cl="<?php echo strtolower($cot['cliente_nombre']); ?>"
            data-co="<?php echo strtolower($cot['compania']); ?>"
            data-ag="<?php echo htmlspecialchars($cot['agente_nombre']); ?>"
            data-st="<?php echo $cot['estado']; ?>"
            data-vence="<?php echo htmlspecialchars($cot['fecha_fin_vigencia'] ?? ''); ?>"
            data-cot='<?php echo htmlspecialchars(json_encode(['id'=>$cot['id'],'folio'=>$cot['folio']??'','cliente_nombre'=>$cot['cliente_nombre'],'agente_nombre'=>$cot['agente_nombre'],'compania'=>$cot['compania'],'tipo_cobertura'=>$cot['tipo_cobertura']??'','precio'=>$cot['precio'],'estado'=>$cot['estado'],'fecha'=>$cot['fecha'],'fecha_fin_vigencia'=>$cot['fecha_fin_vigencia']??'']),ENT_QUOTES); ?>'>
            <td><?php if(!empty($cot['folio'])): ?><span class="fmono"><?php echo htmlspecialchars($cot['folio']); ?></span><?php else: ?><span style="color:var(--text-muted);">—</span><?php endif; ?></td>
            <td style="font-weight:600;"><?php echo htmlspecialchars($cot['cliente_nombre']); ?></td>
            <td><span style="font-size:12px;background:var(--bg-secondary);padding:2px 8px;border-radius:4px;"><?php echo htmlspecialchars($cot['agente_nombre']); ?></span></td>
            <td><?php echo htmlspecialchars($cot['compania']); ?></td>
            <td><strong style="color:var(--success-color);">$<?php echo number_format((float)$cot['precio'],0); ?></strong></td>
            <td><span class="badge <?php echo $bd; ?>"><?php echo ucfirst($cot['estado']); ?></span></td>
            <td style="font-size:12px;color:var(--text-muted);"><?php echo date('d/m/Y',strtotime($cot['fecha'])); ?></td>
            <td><?php
                if (!empty($cot['fecha_fin_vigencia'])):
                    $dR=(int)ceil((strtotime($cot['fecha_fin_vigencia'])-time())/86400);
                    $vC=$dR<=7?'color:#dc2626;font-weight:700;':($dR<=30?'color:#a16207;font-weight:600;':'color:var(--text-muted);');
                ?>
                <span style="font-size:12px;<?php echo $vC; ?>"><?php echo date('d/m/Y',strtotime($cot['fecha_fin_vigencia'])); ?></span>
                <?php if($dR<=30&&$dR>0): ?><div style="font-size:10px;color:<?php echo $dR<=7?'#dc2626':'#a16207'; ?>;"><?php echo $dR; ?>d</div>
                <?php elseif($dR<=0): ?><div style="font-size:10px;color:#dc2626;font-weight:700;">VENCIDA</div><?php endif; ?>
                <?php else: ?><span style="font-size:11px;color:var(--text-muted);">—</span><?php endif; ?>
            </td>
            <td><button class="btn btn-sm btn-ghost" style="color:var(--danger-color);"
                    data-action="adm-eliminarCot"
                    data-id="<?php echo $cot['id']; ?>"
                    data-folio="<?php echo htmlspecialchars($cot['folio']??'#'.$cot['id'],ENT_QUOTES); ?>">🗑</button></td>
        </tr>
        <?php endforeach; ?>
        </tbody></table>
        <div class="pagination" id="pgC"></div>
    </div>
</div>

<?php elseif ($tab === 'configuracion' && $isSuperadmin): ?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
    <div class="card">
        <div class="card-header"><h3 class="card-title">📞 Números de Empresa para ATC</h3></div>
        <div class="card-body">
            <p style="font-size:13px;color:var(--text-secondary);margin-bottom:16px;">Visibles para todos los asesores en su panel.</p>
            <?php for ($i=1;$i<=3;$i++): $num=$atcMap["atc_numero_{$i}"]['valor']??''; $nom=$atcMap["atc_nombre_{$i}"]['valor']??''; ?>
            <div style="background:var(--bg-secondary);border-radius:var(--border-radius-sm);padding:14px;margin-bottom:12px;">
                <div style="font-size:12px;font-weight:700;color:var(--text-secondary);margin-bottom:8px;">Línea <?php echo $i; ?></div>
                <div class="form-row" style="margin-bottom:0;">
                    <div class="form-group"><label class="form-label">Nombre / Tipo</label><input type="text" class="form-input" id="atc_nombre_<?php echo $i; ?>" value="<?php echo htmlspecialchars($nom); ?>" placeholder="Ej. Atención al Cliente"></div>
                    <div class="form-group"><label class="form-label">Número</label><input type="text" class="form-input" id="atc_numero_<?php echo $i; ?>" value="<?php echo htmlspecialchars($num); ?>" placeholder="55 1234 5678"></div>
                </div>
            </div>
            <?php endfor; ?>
            <button id="btnGuardarATC" class="btn btn-primary" style="width:100%;margin-top:8px;">Guardar números ATC</button>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h3 class="card-title">👁️ Vista previa — Asesores</h3></div>
        <div class="card-body">
            <?php foreach ($atcData as $row):
                if (strpos($row['clave'],'atc_numero')!==0||empty($row['valor'])) continue;
                $idx=$row['clave'][-1]; $nombre=$atcMap["atc_nombre_{$idx}"]['valor']??'Línea '.$idx;
            ?>
            <div style="display:flex;align-items:center;gap:14px;padding:14px;background:var(--bg-secondary);border-radius:var(--border-radius-sm);margin-bottom:10px;border-left:3px solid var(--accent-color);">
                <span style="font-size:24px;">📞</span>
                <div><div style="font-weight:700;font-size:14px;"><?php echo htmlspecialchars($row['valor']); ?></div><div style="font-size:12px;color:var(--text-secondary);"><?php echo htmlspecialchars($nombre); ?></div></div>
                <a href="tel:<?php echo preg_replace('/\D/','',$row['valor']); ?>" style="margin-left:auto;background:var(--accent-color);color:#fff;border-radius:99px;padding:6px 14px;font-size:12px;font-weight:600;text-decoration:none;">Llamar</a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>
</main>
</div>

<!-- ═══ MODALES ════════════════════════════════════════════════════════════════ -->

<!-- Modal Crear Agente -->
<div class="modal-overlay" id="modalCrearAgente">
    <div class="modal">
        <div class="modal-header"><h3 class="modal-title">Nuevo Asesor</h3><button class="modal-close" data-close="modalCrearAgente">&times;</button></div>
        <div class="modal-body">
            <div class="form-row">
                <div class="form-group"><label class="form-label required">Nombre</label><input type="text" class="form-input" id="nN"></div>
                <div class="form-group"><label class="form-label required">Apellido</label><input type="text" class="form-input" id="nA"></div>
            </div>
            <div class="form-group"><label class="form-label required">Correo</label><input type="email" class="form-input" id="nC"></div>
            <div class="form-group"><label class="form-label">Teléfono</label><input type="text" class="form-input" id="nT"></div>
            <div class="form-group"><label class="form-label required">Contraseña</label><input type="password" class="form-input" id="nP" placeholder="Mínimo 8 caracteres"></div>
            <div class="form-group"><label class="form-label">Rol</label>
                <select class="form-select" id="nR">
                    <option value="asesor">Asesor de Ventas</option>
                    <option value="coordinador">Coordinador</option>
                    <option value="superadmin">Superadmin</option>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" data-close="modalCrearAgente">Cancelar</button>
            <button class="btn btn-primary" data-action="adm-crearAgente">Crear</button>
        </div>
    </div>
</div>

<!-- Modal Ver Cotización -->
<div class="modal-overlay" id="modalAdminVerCot">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3 class="modal-title">📋 Detalle — <span id="adminCotFolio" style="font-family:'Courier New',monospace;color:var(--accent-color);"></span></h3>
            <button class="modal-close" data-close="modalAdminVerCot">&times;</button>
        </div>
        <div class="modal-body" id="adminCotContent" style="min-height:120px;"></div>
        <div class="modal-footer">
            <button class="btn btn-outline" data-close="modalAdminVerCot">Cerrar</button>
            <button class="btn btn-ghost" data-action="adm-verDocs">📎 Ver documentos</button>
        </div>
    </div>
</div>

<!-- Modal Documentos -->
<div class="modal-overlay" id="modalAdminDocs">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3 class="modal-title">📎 Documentos — <span id="adminDocsFolio" style="font-family:'Courier New',monospace;color:var(--accent-color);"></span></h3>
            <button class="modal-close" data-close="modalAdminDocs">&times;</button>
        </div>
        <div class="modal-body" id="adminDocsContent" style="min-height:160px;"><div style="text-align:center;padding:32px;color:var(--text-muted);">Cargando...</div></div>
        <div class="modal-footer"><button class="btn btn-outline" data-close="modalAdminDocs">Cerrar</button></div>
    </div>
</div>

<!-- PARCHE: Modal Editar Agente (nombre + contraseña) -->
<div class="modal-overlay" id="modalEditarAgente">
    <div class="modal" style="max-width:480px;">
        <div class="modal-header">
            <h3 class="modal-title">✏️ Editar agente</h3>
            <button class="modal-close" data-close="modalEditarAgente">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="editAgenteId">
            <div style="background:var(--bg-secondary);border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:13px;">
                <strong id="editAgenteRolBadge"></strong>
            </div>
            <p style="font-size:13px;color:var(--text-secondary);margin-bottom:16px;">Puedes cambiar el nombre y/o la contraseña. El correo no es editable aquí.</p>
            <div class="form-row">
                <div class="form-group"><label class="form-label required">Nombre</label><input type="text" class="form-input" id="editNombre"></div>
                <div class="form-group"><label class="form-label required">Apellido</label><input type="text" class="form-input" id="editApellido"></div>
            </div>
            <div class="form-group">
                <label class="form-label">Nueva contraseña <span style="font-size:11px;color:var(--text-muted);">(dejar en blanco para no cambiar)</span></label>
                <input type="password" class="form-input" id="editPassword" placeholder="Mínimo 8 caracteres">
            </div>
            <div class="form-group">
                <label class="form-label">Confirmar contraseña</label>
                <input type="password" class="form-input" id="editPasswordConfirm" placeholder="Repite la contraseña">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" data-close="modalEditarAgente">Cancelar</button>
            <button class="btn btn-primary" data-action="adm-guardarEdicionAgente">💾 Guardar</button>
        </div>
    </div>
</div>

<!-- PARCHE: Modal Historial Coordinador (solo superadmin) -->
<div class="modal-overlay" id="modalHistorialCoord">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3 class="modal-title">📋 Historial del coordinador</h3>
            <button class="modal-close" data-close="modalHistorialCoord">&times;</button>
        </div>
        <div class="modal-body" id="historialCoordBody" style="min-height:200px;">
            <div style="text-align:center;padding:40px;color:var(--text-muted);">Cargando…</div>
        </div>
        <div class="modal-footer"><button class="btn btn-outline" data-close="modalHistorialCoord">Cerrar</button></div>
    </div>
</div>

<div class="toast-container" id="toastContainer"></div>

<?php if ($tab === 'rendimiento'): ?>
<script src="/js/reportes.js" nonce="<?= $cspNonce ?>"></script>
<script nonce="<?= $cspNonce ?>">
const datosEvolucion  = <?php echo json_encode($evolucion); ?>;
const datosVehiculos  = <?php echo json_encode($vehiculos); ?>;
const datosCoberturas = <?php echo json_encode($coberturas); ?>;
document.addEventListener('DOMContentLoaded', () => { if(typeof initReportesCharts==='function') initReportesCharts(); });
</script>
<?php endif; ?>

<script nonce="<?= $cspNonce ?>">
// ── Delegación global ─────────────────────────────────────────────────────────
document.addEventListener('click', function(e) {
    const el = e.target.closest('[data-action],[data-close]');
    if (!el) return;
    if (el.dataset.close) { closeModal(el.dataset.close); return; }
    switch(el.dataset.action) {
        case 'adm-abrirCrearAgente':   openModal('modalCrearAgente'); break;
        case 'adm-crearAgente':        crearAgente(); break;
        case 'adm-toggleAgente':       delAg(+el.dataset.id, el.dataset.nombre, +el.dataset.activo); break;
        case 'adm-eliminarCot':        delCot(+el.dataset.id, el.dataset.folio); break;
        case 'adm-ejecutarReasignacion': ejecutarReasignacion(); break;
        case 'adm-deseleccionarTodos': deseleccionarTodos(); break;
        case 'adm-toggleCliente': {
            const id  = +el.dataset.id;
            const cb  = document.getElementById('cb_'+id);
            const row = document.getElementById('cbrow_'+id);
            if (!cb) break;
            if (e.target !== cb) cb.checked = !cb.checked;
            if (cb.checked) { seleccionados.add(id); row.style.background='var(--info-light)'; }
            else            { seleccionados.delete(id); row.style.background=''; }
            actualizarBarraReasignar();
            break;
        }
        case 'adm-verDocs':  abrirDocumentosAdmin(window._adminCotId); break;
        case 'adm-page':     window['_p_'+el.dataset.tb]?.go(+el.dataset.p); break;
        case 'toast-close':  el.closest('.toast')?.remove(); break;
        // PARCHE: editar agente e historial coordinador
        case 'adm-editarAgente': {
            document.getElementById('editAgenteId').value       = el.dataset.id;
            document.getElementById('editNombre').value         = el.dataset.nombre;
            document.getElementById('editApellido').value       = el.dataset.apellido;
            document.getElementById('editPassword').value       = '';
            document.getElementById('editPasswordConfirm').value= '';
            document.getElementById('editAgenteRolBadge').textContent = 'Rol: ' + el.dataset.rol;
            openModal('modalEditarAgente');
            break;
        }
        case 'adm-guardarEdicionAgente': guardarEdicionAgente(); break;
        case 'adm-verHistorialCoord': verHistorialCoord(el.dataset.id, el.dataset.nombre); break;
    }
});

// Cerrar modal al clic en overlay
document.addEventListener('click', e => {
    if (e.target.classList.contains('modal-overlay')) { e.target.classList.remove('active'); document.body.style.overflow=''; }
});

// Stop propagation en celdas de acción: solo si el click NO fue en un botón/link/select interno
// Esto permite que los data-action buttons funcionen, pero evita navegar la fila al clickear la celda
document.addEventListener('click', function(e) {
    const td = e.target.closest('td.adm-stop-prop');
    if (!td) return;
    // Si el click fue en un botón, link, select o su contenido, dejar que el delegador lo maneje
    if (e.target.closest('button,a,select,input')) return;
    // Solo si el click fue en la celda vacía, cancelar propagación al tr
    e.stopPropagation();
});

document.addEventListener('change', function(e) {
    if (e.target.id === 'selFiltroAgente')
        return void (location.href = '?tab=rendimiento' + (e.target.value ? '&agente_id='+e.target.value : ''));
    if (e.target.id === 'selFiltroActividad')
        return void (location.href = '?tab=actividad' + (e.target.value ? '&act_agente='+e.target.value : ''));
    if (e.target.classList.contains('adm-rol-select'))
        return void chgRol(+e.target.dataset.agenteId, e.target.value);
    if (e.target.id === 'flAg' || e.target.id === 'flEst') return void filtC();
    if (e.target.id === 'selectAll') return void toggleTodos(e.target.checked);
});
document.addEventListener('input', function(e) {
    if (e.target.id === 'srchA')  return void filtA();
    if (e.target.id === 'srchC')  return void filtC();
    if (e.target.id === 'srchCl') return void filtCl();
});

// ── Paginación ────────────────────────────────────────────────────────────────
function setupPag(tbId, pgId, pp=15) {
    const tb=document.getElementById(tbId); if (!tb) return;
    const pg=document.getElementById(pgId); let cp=1;
    const rows=()=>[...tb.querySelectorAll('tr')];
    const vis=()=>rows().filter(r=>r.getAttribute('data-filtered')!=='hide');
    function render(){
        const vr=vis(),total=vr.length,pages=Math.max(1,Math.ceil(total/pp));
        if(cp>pages) cp=pages;
        rows().forEach(r=>{
            if(r.getAttribute('data-filtered')==='hide'){r.style.display='none';return;}
            const i=vr.indexOf(r); r.style.display=(i>=(cp-1)*pp&&i<cp*pp)?'':'none';
        });
        if(!pg||pages<=1){if(pg)pg.innerHTML='';return;}
        let h=pagBtn('‹',cp-1,tbId,cp===1);
        for(let i=1;i<=pages;i++){
            if(i===1||i===pages||Math.abs(i-cp)<=1) h+=pagBtn(i,i,tbId,false,i===cp);
            else if(Math.abs(i-cp)===2) h+='<span class="page-btn" style="border:none;cursor:default;">…</span>';
        }
        h+=pagBtn('›',cp+1,tbId,cp===pages);
        pg.innerHTML=h;
    }
    window['_p_'+tbId]={render,go:p=>{cp=p;render();}};
    render();
}
function pagBtn(lbl,p,tb,disabled,active=false){
    return `<button class="page-btn${active?' active':''}" data-action="adm-page" data-p="${p}" data-tb="${tb}" ${disabled?'disabled':''}>${lbl}</button>`;
}
function filtA(){const tx=(document.getElementById('srchA')?.value||'').toLowerCase();let v=0;[...document.querySelectorAll('#tbA tr')].forEach(r=>{const ok=!tx||(r.dataset.n||'').includes(tx)||(r.dataset.c||'').includes(tx);r.setAttribute('data-filtered',ok?'show':'hide');if(ok)v++;});const cnt=document.getElementById('cntA');if(cnt)cnt.textContent=v;window['_p_tbA']?.go(1);}
function filtC(){const tx=(document.getElementById('srchC')?.value||'').toLowerCase();const ag=document.getElementById('flAg')?.value||'todos';const es=document.getElementById('flEst')?.value||'todos';let v=0;[...document.querySelectorAll('#tbC tr')].forEach(r=>{const ok=(!tx||(r.dataset.f||'').includes(tx)||(r.dataset.cl||'').includes(tx)||(r.dataset.co||'').includes(tx))&&(ag==='todos'||r.dataset.ag===ag)&&(es==='todos'||r.dataset.st===es);r.setAttribute('data-filtered',ok?'show':'hide');if(ok)v++;});const cnt=document.getElementById('cntC');if(cnt)cnt.textContent=v;window['_p_tbC']?.go(1);}
function filtCl(){const tx=(document.getElementById('srchCl')?.value||'').toLowerCase();document.querySelectorAll('#clientesList .cb-row').forEach(r=>r.classList.toggle('cl-hidden',!(!tx||(r.dataset.n||'').includes(tx))));if(typeof window._renderCl==='function')window._renderCl();}

// ── CRUD ──────────────────────────────────────────────────────────────────────
function chgRol(id,rol){if(!confirm(`¿Cambiar rol a "${rol}"?`))return;post({accion:'cambiar_rol',agente_id:id,rol}).then(d=>d.ok?toast('Rol actualizado','success'):toast(d.msg,'error'));}
function delAg(id,n,activo){const accion=activo?'eliminar_agente':'reactivar_agente';if(!confirm(activo?`¿Desactivar al asesor ${n}?`:`¿Reactivar al asesor ${n}?`))return;post({accion,agente_id:id}).then(d=>{if(d.ok){toast(d.msg||'OK','success');setTimeout(()=>location.reload(),900);}else toast(d.msg,'error');});}
function crearAgente(){const n=document.getElementById('nN').value.trim(),a=document.getElementById('nA').value.trim(),c=document.getElementById('nC').value.trim(),t=document.getElementById('nT').value.trim(),p=document.getElementById('nP').value.trim(),r=document.getElementById('nR').value;if(!n||!a||!c||!p){toast('Completa los campos requeridos','error');return;}if(p.length<8){toast('Contraseña mínimo 8 caracteres','error');return;}post({accion:'crear_agente',nombre:n,apellido:a,correo:c,telefono:t,password:p,rol:r}).then(d=>{if(d.ok){toast('Asesor creado','success');closeModal('modalCrearAgente');setTimeout(()=>location.reload(),900);}else toast(d.msg,'error');});}
function delCot(id,f){if(!confirm(`¿Eliminar cotización ${f}? Esta acción no se puede deshacer.`))return;post({accion:'eliminar_cotizacion',cotizacion_id:id}).then(d=>{if(d.ok){toast('Eliminada','success');setTimeout(()=>location.reload(),900);}else toast(d.msg,'error');});}

// PARCHE: Guardar edición de agente (nombre + contraseña)
async function guardarEdicionAgente() {
    const id    = document.getElementById('editAgenteId').value;
    const nombre= document.getElementById('editNombre').value.trim();
    const apell = document.getElementById('editApellido').value.trim();
    const pw    = document.getElementById('editPassword').value;
    const pw2   = document.getElementById('editPasswordConfirm').value;
    if (!nombre || !apell) { toast('Nombre y apellido son requeridos', 'error'); return; }
    if (pw && pw !== pw2)  { toast('Las contraseñas no coinciden', 'error'); return; }
    if (pw && pw.length < 8) { toast('Contraseña mínimo 8 caracteres', 'error'); return; }
    const fd1 = new FormData(); fd1.append('accion','editar_agente_nombre'); fd1.append('agente_id',id); fd1.append('nombre',nombre); fd1.append('apellido',apell);
    const r1 = await fetch('/admin',{method:'POST',body:fd1}).then(r=>r.json());
    if (!r1.ok) { toast(r1.msg||'Error al guardar nombre','error'); return; }
    if (pw) {
        const fd2 = new FormData(); fd2.append('accion','cambiar_password_agente'); fd2.append('agente_id',id); fd2.append('password',pw);
        const r2 = await fetch('/admin',{method:'POST',body:fd2}).then(r=>r.json());
        if (!r2.ok) { toast(r2.msg||'Error al cambiar contraseña','error'); return; }
    }
    toast('Agente actualizado correctamente','success');
    closeModal('modalEditarAgente');
    setTimeout(()=>location.reload(),1000);
}

// PARCHE: Ver historial coordinador
function verHistorialCoord(id, nombre) {
    document.querySelector('#modalHistorialCoord .modal-title').textContent = `📋 Historial de ${nombre||'coordinador'}`;
    document.getElementById('historialCoordBody').innerHTML = '<div style="text-align:center;padding:40px;color:var(--text-muted);">Cargando…</div>';
    openModal('modalHistorialCoord');
    const fd=new FormData(); fd.append('accion','get_historial_coordinador'); fd.append('agente_id',id);
    fetch('/admin',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
        if(!d.ok){document.getElementById('historialCoordBody').innerHTML='<p style="color:var(--danger-color);text-align:center;padding:24px;">Error al cargar.</p>';return;}
        if(!d.historial.length){document.getElementById('historialCoordBody').innerHTML='<p style="color:var(--text-muted);text-align:center;padding:40px;">Sin acciones registradas.</p>';return;}
        const typeIcon={cobro:'💳',comprobante:'📄',renovacion:'🔁',nota:'📝'};
        let html='<div style="display:flex;flex-direction:column;">';
        d.historial.forEach(h=>{
            html+=`<div style="display:flex;gap:12px;padding:10px 16px;border-bottom:1px solid var(--border-color);">
                <div style="font-size:20px;flex-shrink:0;">${typeIcon[h.tipo]||'•'}</div>
                <div style="flex:1;">
                    <div style="font-size:13px;font-weight:600;">${h.titulo}</div>
                    <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">${h.cliente_nombre?h.cliente_nombre+' · ':''}${h.fecha_fmt}</div>
                    ${h.descripcion?`<div style="font-size:12px;color:var(--text-secondary);margin-top:4px;">${h.descripcion}</div>`:''}
                </div></div>`;
        });
        html+='</div>';
        document.getElementById('historialCoordBody').innerHTML=html;
    }).catch(()=>{document.getElementById('historialCoordBody').innerHTML='<p style="color:var(--danger-color);text-align:center;padding:24px;">Error de conexión.</p>';});
}

// ── Reasignación ──────────────────────────────────────────────────────────────
const seleccionados=new Set();
function toggleTodos(checked){document.querySelectorAll('#clientesList .cb-row').forEach(row=>{if(row.classList.contains('cl-hidden')||row.style.display==='none')return;const cb=row.querySelector('input[type=checkbox]');if(!cb)return;const id=+cb.value;cb.checked=checked;if(checked){seleccionados.add(id);row.style.background='var(--info-light)';}else{seleccionados.delete(id);row.style.background='';}});actualizarBarraReasignar();}
function deseleccionarTodos(){document.getElementById('selectAll').checked=false;toggleTodos(false);}
function actualizarBarraReasignar(){const bar=document.getElementById('reasignarBar');const cnt=document.getElementById('selCount');if(bar)bar.style.display=seleccionados.size>0?'flex':'none';if(cnt)cnt.textContent=seleccionados.size+' cliente'+(seleccionados.size!==1?'s':'')+' seleccionado'+(seleccionados.size!==1?'s':'');}
function ejecutarReasignacion(){const nuevoId=document.getElementById('nuevoAsesor')?.value;if(!nuevoId){toast('Selecciona el asesor destino','error');return;}if(!seleccionados.size){toast('Selecciona al menos un cliente','error');return;}const nom=document.getElementById('nuevoAsesor').options[document.getElementById('nuevoAsesor').selectedIndex].text;if(!confirm(`¿Reasignar ${seleccionados.size} cliente(s) a ${nom}?`))return;post({accion:'reasignar_clientes',cliente_ids:[...seleccionados].join(','),nuevo_asesor_id:nuevoId}).then(d=>{if(d.ok){toast(`${d.reasignados} cliente(s) reasignados`,'success');setTimeout(()=>location.reload(),1200);}else toast(d.msg,'error');});}

// ── ATC ───────────────────────────────────────────────────────────────────────
document.getElementById('btnGuardarATC')?.addEventListener('click',function(){const items=[];for(let i=1;i<=3;i++){items.push({clave:'atc_numero_'+i,valor:document.getElementById('atc_numero_'+i)?.value.trim()||'',descripcion:'Número ATC '+i});items.push({clave:'atc_nombre_'+i,valor:document.getElementById('atc_nombre_'+i)?.value.trim()||'',descripcion:'Nombre ATC '+i});}fetch('/api/config',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({items})}).then(r=>r.json()).then(d=>{if(d.ok){toast('Números ATC guardados','success');setTimeout(()=>location.reload(),900);}else toast(d.msg||'Error','error');});});

// ── Exportar ──────────────────────────────────────────────────────────────────
document.getElementById('btnExportarExcel')?.addEventListener('click',()=>exportar('excel'));
document.getElementById('btnExportarPdf')  ?.addEventListener('click',()=>exportar('pdf'));
function exportar(fmt){const mes=document.getElementById('expMes')?.value||'';const estado=document.getElementById('expEstado')?.value||'';const ag=<?php echo json_encode($filtroAgente); ?>;let url=`/exportar?formato=${fmt}&tipo=cotizaciones`;if(ag)url+=`&agente_id=${ag}`;if(estado)url+=`&estado=${estado}`;if(mes){const[y,m]=mes.split('-');const ult=new Date(+y,+m,0).getDate();url+=`&desde=${y}-${m}-01&hasta=${y}-${m}-${String(ult).padStart(2,'0')}`;}if(fmt==='pdf')window.open(url,'_blank');else location.href=url;}

// ── Sort por vencimiento ──────────────────────────────────────────────────────
document.getElementById('btnAdminSortVence')?.addEventListener('click',toggleAdminSortVence);
var _admSortActivo=false,_admOrdenOrig=null;
function toggleAdminSortVence(){const tbody=document.getElementById('tbC'),btn=document.getElementById('btnAdminSortVence');if(!tbody||!btn)return;if(!_admSortActivo){_admOrdenOrig=[...tbody.querySelectorAll('tr')].map(tr=>tr.dataset.id);const rows=[...tbody.querySelectorAll('tr')];const hoy=new Date();hoy.setHours(0,0,0,0);const conV=rows.filter(tr=>tr.dataset.vence),sinV=rows.filter(tr=>!tr.dataset.vence);conV.sort((a,b)=>new Date(a.dataset.vence)-new Date(b.dataset.vence));[...conV,...sinV].forEach(tr=>tbody.appendChild(tr));rows.forEach(tr=>{if(!tr.dataset.vence)return;const d=Math.ceil((new Date(tr.dataset.vence+'T00:00:00')-hoy)/86400000);if(d<=0){tr.style.outline='2px solid #ef4444';tr.style.outlineOffset='-2px';}else if(d<=7){tr.style.outline='2px solid #f97316';tr.style.outlineOffset='-2px';}else if(d<=30){tr.style.outline='2px solid #f59e0b';tr.style.outlineOffset='-2px';}});btn.style.cssText='background:#fef3c7;border-color:#f59e0b;color:#92400e;';btn.innerHTML='<i class="bi bi-clock-history"></i> Ordenado <span style="font-size:10px;font-weight:700;background:#f59e0b;color:#fff;padding:1px 5px;border-radius:4px;margin-left:4px;">ACTIVO</span>';_admSortActivo=true;}else{if(_admOrdenOrig)_admOrdenOrig.forEach(id=>{const tr=tbody.querySelector(`tr[data-id="${id}"]`);if(tr)tbody.appendChild(tr);});tbody.querySelectorAll('tr').forEach(tr=>{tr.style.outline='';tr.style.outlineOffset='';});btn.style.cssText='';btn.innerHTML='<i class="bi bi-clock-history" style="font-size:14px;"></i> Próximos a vencer';_admSortActivo=false;}}

// ── Post helper ───────────────────────────────────────────────────────────────
function post(data){const fd=new FormData();Object.entries(data).forEach(([k,v])=>fd.append(k,v));return fetch('/admin',{method:'POST',body:fd}).then(r=>r.json());}

// ── Doble clic en tabla cotizaciones ─────────────────────────────────────────
document.addEventListener('dblclick',e=>{
    const tr=e.target.closest('#tbC tr');if(!tr)return;
    try{const cot=JSON.parse(tr.dataset.cot||'{}');if(!cot.id)return;window._adminCotId=cot.id;
    const eBadge={pendiente:'badge-warning',enviada:'badge-info',aceptada:'badge-success',rechazada:'badge-danger',pagado:'badge-success'};
    const eLabel={pendiente:'Pendiente',enviada:'Enviada',aceptada:'Aceptada',rechazada:'Rechazada',pagado:'Pagado'};
    document.getElementById('adminCotFolio').textContent=cot.folio||'#'+cot.id;
    document.getElementById('adminCotContent').innerHTML=`<div style="display:flex;flex-direction:column;gap:12px;"><div style="background:linear-gradient(135deg,#0f2940,#1a3a5c);border-radius:10px;padding:18px 22px;color:#fff;"><div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;"><div><div style="font-size:10px;opacity:.65;text-transform:uppercase;">Folio</div><div style="font-family:'Courier New',monospace;font-size:17px;font-weight:800;">${cot.folio||'Sin folio'}</div></div><span class="badge ${eBadge[cot.estado]||'badge-neutral'}" style="font-size:12px;padding:5px 12px;">${eLabel[cot.estado]||cot.estado}</span></div><div style="margin-top:10px;font-size:13px;opacity:.85;">📋 ${cot.compania} — ${cot.tipo_cobertura||''}</div></div><div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;"><div style="background:var(--bg-secondary);border-radius:8px;padding:12px;"><div style="font-size:10px;font-weight:700;color:var(--text-muted);text-transform:uppercase;margin-bottom:3px;">Cliente</div><div style="font-weight:600;">${cot.cliente_nombre}</div></div><div style="background:var(--bg-secondary);border-radius:8px;padding:12px;"><div style="font-size:10px;font-weight:700;color:var(--text-muted);text-transform:uppercase;margin-bottom:3px;">Asesor</div><div style="font-weight:600;">${cot.agente_nombre}</div></div></div><div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;"><div style="background:var(--bg-secondary);border-radius:8px;padding:12px;"><div style="font-size:10px;font-weight:700;color:var(--text-muted);text-transform:uppercase;margin-bottom:3px;">Prima</div><div style="font-size:20px;font-weight:800;color:var(--success-color);">$${parseFloat(cot.precio||0).toLocaleString('es-MX',{minimumFractionDigits:2})}</div></div><div style="background:var(--bg-secondary);border-radius:8px;padding:12px;"><div style="font-size:10px;font-weight:700;color:var(--text-muted);text-transform:uppercase;margin-bottom:3px;">Fecha</div><div style="font-weight:600;">${cot.fecha?new Date(cot.fecha+'T12:00').toLocaleDateString('es-MX',{day:'2-digit',month:'short',year:'numeric'}):'—'}</div></div></div></div>`;
    openModal('modalAdminVerCot');}catch(_){}
});
window._adminCotId=null;

function abrirDocumentosAdmin(cotId){if(!cotId)return;document.getElementById('adminDocsFolio').textContent='...';document.getElementById('adminDocsContent').innerHTML='<div style="text-align:center;padding:40px;color:var(--text-muted);">⏳ Cargando...</div>';openModal('modalAdminDocs');const fd=new FormData();fd.append('accion','get_documentos_admin');fd.append('cotizacion_id',cotId);fetch('/admin',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{if(!d.ok){document.getElementById('adminDocsContent').innerHTML='<div style="text-align:center;padding:40px;color:var(--danger-color);">Error al cargar documentos.</div>';return;}document.getElementById('adminDocsFolio').textContent=d.folio||'#'+cotId;if(!d.docs?.length){document.getElementById('adminDocsContent').innerHTML='<div style="text-align:center;padding:48px;color:var(--text-muted);"><div style="font-size:48px;margin-bottom:12px;">📭</div><div style="font-size:15px;font-weight:600;">Sin documentos adjuntos</div></div>';return;}const cards=d.docs.map(doc=>{const bi=doc.en_idrive?'<span style="font-size:10px;background:#dcfce7;color:#16a34a;padding:2px 8px;border-radius:99px;font-weight:600;">☁️ iDrive</span>':'<span style="font-size:10px;background:var(--bg-secondary);color:var(--text-muted);padding:2px 8px;border-radius:99px;">Local</span>';const th=doc.es_imagen&&doc.url_local?`<div style="height:110px;border-radius:7px;overflow:hidden;margin-bottom:10px;cursor:pointer;" data-href="${doc.url_local}"><img src="${doc.url_local}" style="width:100%;height:100%;object-fit:cover;" loading="lazy"></div>`:`<div style="height:110px;background:var(--bg-secondary);border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:40px;margin-bottom:10px;">${doc.es_pdf||doc.url_local?'📄':'📎'}</div>`;const bd=doc.url_local?`<a href="${doc.url_local}" download="${doc.nombre_orig}" class="btn btn-sm btn-outline" style="flex:1;text-align:center;font-size:11px;">⬇ Descargar</a>`:`<span style="font-size:11px;color:var(--text-muted);flex:1;text-align:center;">${doc.en_idrive?'Solo en ☁️ iDrive':'No disponible'}</span>`;return `<div style="background:var(--bg-secondary);border-radius:10px;padding:14px;display:flex;flex-direction:column;">${th}<div style="font-size:11px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.4px;margin-bottom:3px;">${doc.tipo_label}</div><div style="font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:8px;" title="${doc.nombre_orig}">${doc.nombre_orig}</div><div style="display:flex;align-items:center;gap:8px;">${bi}${bd}</div></div>`;}).join('');document.getElementById('adminDocsContent').innerHTML=`<p style="font-size:13px;color:var(--text-secondary);margin-bottom:14px;">${d.docs.length} documento${d.docs.length!==1?'s':''}</p><div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(155px,1fr));gap:14px;">${cards}</div>`;document.querySelectorAll('#adminDocsContent [data-href]').forEach(el=>el.addEventListener('click',()=>window.open(el.dataset.href,'_blank')));}).catch(()=>{document.getElementById('adminDocsContent').innerHTML='<div style="text-align:center;padding:40px;color:var(--danger-color);">Error de conexión.</div>';});}

// ── Paginación clientes del asesor ────────────────────────────────────────────
(function(){const list=document.getElementById('clientesList'),pg=document.getElementById('pgClientes');if(!list||!pg)return;const PP=10;let cp=1;function vis(){return[...list.querySelectorAll('.cb-row')].filter(r=>!r.classList.contains('cl-hidden'));}function renderCl(){const v=vis(),tp=Math.max(1,Math.ceil(v.length/PP));if(cp>tp)cp=tp;[...list.querySelectorAll('.cb-row')].forEach(r=>{if(r.classList.contains('cl-hidden')){r.style.display='none';return;}const i=v.indexOf(r);r.style.display=(i>=(cp-1)*PP&&i<cp*PP)?'':'none';});if(tp<=1){pg.innerHTML='';return;}let h=pagBtn('‹',cp-1,'_cl',cp===1);for(let i=1;i<=tp;i++){if(i===1||i===tp||Math.abs(i-cp)<=1)h+=pagBtn(i,i,'_cl',false,i===cp);else if(Math.abs(i-cp)===2)h+='<span class="page-btn" style="border:none;cursor:default;">…</span>';}h+=pagBtn('›',cp+1,'_cl',cp===tp);pg.innerHTML=h;}window.goClPg=p=>{cp=Math.max(1,p);renderCl();};window._renderCl=renderCl;renderCl();})();

// ── Paginación actividad ──────────────────────────────────────────────────────
(function(){const feed=document.getElementById('actFeed'),pag=document.getElementById('actPag');if(!feed||!pag)return;const items=[...feed.querySelectorAll('.act-item')],pp=10;let cp=1;const pages=()=>Math.max(1,Math.ceil(items.length/pp));function renderFeed(){const p=pages();items.forEach((el,i)=>{el.style.display=(i>=(cp-1)*pp&&i<cp*pp)?'':'none';});if(p<=1){pag.innerHTML='';return;}let h=pagBtn('‹',cp-1,'_act',cp===1);for(let i=1;i<=p;i++)h+=pagBtn(i,i,'_act',false,i===cp);h+=pagBtn('›',cp+1,'_act',cp===p);pag.innerHTML=h;}window.aFeed=p=>{cp=Math.max(1,Math.min(p,pages()));renderFeed();};renderFeed();})();

// ── Inicializar paginación tablas principales ─────────────────────────────────
document.addEventListener('DOMContentLoaded',()=>{setupPag('tbA','pgA',10);setupPag('tbC','pgC',20);});

// ── Toast ─────────────────────────────────────────────────────────────────────
function toast(msg,type='success'){const c=document.getElementById('toastContainer');if(!c)return;const t=document.createElement('div');t.className=`toast ${type}`;t.innerHTML=`<div class="toast-icon">${type==='error'?'✗':'✓'}</div><div class="toast-message">${msg}</div><button class="toast-close" data-action="toast-close">&times;</button>`;c.appendChild(t);setTimeout(()=>{t.classList.add('hiding');setTimeout(()=>t.remove(),300);},5000);}
// Alias para compatibilidad con código externo que use showToast
function showToast(msg,type='success'){toast(msg,type);}
</script>
<?php if ($tab === 'emision'): ?>
<script src="/js/emision.js" nonce="<?= $cspNonce ?>"></script>
<?php endif; ?>

</body>
</html>
