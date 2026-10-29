<?php
declare(strict_types=1);

use App\Security\SessionManager;
use App\Models\{ClientModel, CotizacionModel, HistorialModel};

SessionManager::requireAuth('/login');

$agenteId       = SessionManager::getAgentId();
$agente         = SessionManager::getAgentData();
$ramoActivo     = SessionManager::getRamo();
$empresaId      = \App\Security\Tenant::requireId();
$clienteModel   = new ClientModel();
$cotizacionModel= new CotizacionModel();
$historialModel = new HistorialModel();

// $_autoloader = dirname(__DIR__, 3) . '/vendor/autoload.php';
// if (file_exists($_autoloader)) require_once $_autoloader;

// ── AJAX GET ──────────────────────────────────────────────────────────────
if (isset($_GET['ajax']) && $_GET['ajax'] === 'limite') {
    header('Content-Type: application/json');
    $cid = (int)($_GET['cliente_id'] ?? 0);
    if (!$cid || !$clienteModel->perteneceAAgente($cid, $agenteId)) {
        echo json_encode(['total' => 0]); exit;
    }
    echo json_encode(['total' => $cotizacionModel->contarPorCliente($cid)]);
    exit;
}

// ── AJAX POST ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    header('Content-Type: application/json');

    if ($_POST['accion'] === 'preview_folio') {
        $ramo      = trim($_POST['ramo']           ?? 'autos');
        $cobertura = trim($_POST['tipo_cobertura'] ?? '');
        $estadoF   = trim($_POST['estado_folio']   ?? 'N');
        if (!$cobertura) { echo json_encode(['ok'=>false]); exit; }
        $folio = $cotizacionModel->generarFolioPublico($agenteId, $ramo, $cobertura, $estadoF);
        echo json_encode(['ok'=>true,'folio'=>$folio]);
        exit;
    }

    if ($_POST['accion'] === 'crear') {
        $pCliente    = (int)($_POST['cliente_id']    ?? 0);
        $pRamo       = $ramoActivo;
        $pCompania   = trim($_POST['compania']        ?? '');
        $pCobertura  = trim($_POST['tipo_cobertura']  ?? '');
        $pPrecio     = $_POST['precio'] !== '' ? (float)$_POST['precio'] : 0.0;
        $pEstadoF    = trim($_POST['estado_folio']    ?? 'N');
        $pComentarios= trim($_POST['comentarios']     ?? '');
        $pFecha      = trim($_POST['fecha']           ?? date('Y-m-d'));
        $pMetodo     = trim($_POST['metodo_pago']     ?? '');
        $pEstado     = 'pendiente';

        $errores = [];
        if (!$pCliente)   $errores[] = 'Selecciona un cliente.';
        if (!$pCompania)  $errores[] = 'La compañía es requerida.';
        if (!$pCobertura) $errores[] = 'El tipo de cobertura es requerido.';
        if (!$pFecha)     $errores[] = 'La fecha es requerida.';
        if (!$clienteModel->perteneceAAgente($pCliente, $agenteId)) $errores[] = 'Cliente no válido.';

        if (!empty($errores)) {
            echo json_encode(['ok'=>false,'msg'=>implode(' ', $errores)]); exit;
        }
        if ($cotizacionModel->contarPorCliente($pCliente) >= 10) {
            echo json_encode(['ok'=>false,'msg'=>'Este cliente ya tiene el máximo de 10 cotizaciones.']); exit;
        }

        $tiposDoc = [
            'doc_cotizacion'      => ['Documento de cotización', ['pdf','jpg','jpeg','png']],
            'foto_coche_1'        => ['Foto 1',                  ['jpg','jpeg','png']],
            'foto_coche_2'        => ['Foto 2',                  ['jpg','jpeg','png']],
            'foto_coche_3'        => ['Foto 3',                  ['jpg','jpeg','png']],
            'foto_coche_4'        => ['Foto 4',                  ['jpg','jpeg','png']],
            'foto_coche_5'        => ['Foto 5',                  ['jpg','jpeg','png']],
            'ine'                 => ['INE',                     ['pdf','jpg','jpeg','png']],
            'comprobante_dom'     => ['Comprobante domicilio',   ['pdf','jpg','jpeg','png']],
            'tarjeta_circulacion' => ['Tarjeta circulación',     ['pdf','jpg','jpeg','png']],
            'factura_auto'        => ['Factura del auto',        ['pdf','jpg','jpeg','png']],
            'constancia_fiscal'   => ['Constancia fiscal',       ['pdf','jpg','jpeg','png']],
        ];

        try {
            $cotId = $cotizacionModel->transaction(function () use (
                $cotizacionModel, $clienteModel,
                $pCliente, $pRamo, $pCompania, $pCobertura, $pPrecio,
                $pEstado, $pEstadoF, $pComentarios, $pFecha, $pMetodo, $agenteId
            ) {
                $cotId = $cotizacionModel->crear([
                    'cliente_id'    => $pCliente,
                    'agente_id'     => $agenteId,
                    'ramo'          => $pRamo,
                    'compania'      => $pCompania,
                    'tipo_cobertura'=> $pCobertura,
                    'precio'        => $pPrecio,
                    'estado'        => $pEstado,
                    'estado_folio'  => $pEstadoF,
                    'comentarios'   => $pComentarios,
                    'fecha'         => $pFecha,
                ]);
                if ($pMetodo && in_array($pMetodo, ['spei','contado','meses_sin_intereses'], true)) {
                    $cotizacionModel->updateMetodoPago($cotId, $agenteId, $pMetodo);
                }
                $clienteModel->updateEstadoSiProcede($pCliente, $agenteId, 'con_cotizacion');
                return $cotId;
            });
        } catch (\Throwable $e) {
            error_log('Crear cotización error: ' . $e->getMessage());
            echo json_encode(['ok'=>false,'msg'=>'Error: '.$e->getMessage(),'_file'=>$e->getFile(),'_line'=>$e->getLine()]);
            exit;
        }

        $folio = $cotizacionModel->find($cotId, ['folio'])['folio'] ?? '';
        $uploadDir = dirname(__DIR__, 3) . "/uploads/cotizaciones/{$cotId}/";
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $archivosGuardados = [];

        foreach ($tiposDoc as $tipo => [$label, $extsValidos]) {
            if (empty($_FILES[$tipo]['name'])) continue;
            $file = $_FILES[$tipo];
            if ($file['error'] !== UPLOAD_ERR_OK) continue;
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $extsValidos, true) || $file['size'] > 10*1024*1024) {
                echo json_encode(['ok'=>false,'msg'=>"Formato inválido en {$label}."]); exit;
            }
            $nombreArchivo = $tipo . '_' . time() . '.' . $ext;
            $rutaRelativa  = "uploads/cotizaciones/{$cotId}/{$nombreArchivo}";
            if (!move_uploaded_file($file['tmp_name'], $uploadDir . $nombreArchivo)) {
                echo json_encode(['ok'=>false,'msg'=>"Error al subir {$label}."]); exit;
            }
            $archivosGuardados[] = $cotizacionModel->adjuntarArchivo(
                $cotId, $agenteId, $tipo, $file['name'], $nombreArchivo, $rutaRelativa
            );
        }

        $historialModel->registrar($pCliente, $agenteId, 'cotizacion',
            "Cotización creada — Folio: {$folio}",
            "{$pCompania} · {$pCobertura} · " . ($pPrecio > 0 ? '$'.number_format($pPrecio,2) : 'Sin definir')
        );

        echo json_encode(['ok'=>true,'folio'=>$folio,'id'=>$cotId,'docs'=>count($archivosGuardados)]);
        exit;
    }

    if ($_POST['accion'] === 'actualizar') {
        $pCotId      = (int)($_POST['cotizacion_id'] ?? 0);
        $pCompania   = trim($_POST['compania']        ?? '');
        $pCobertura  = trim($_POST['tipo_cobertura']  ?? '');
        $pPrecio     = $_POST['precio'] !== '' ? (float)$_POST['precio'] : 0.0;
        $pComentarios= trim($_POST['comentarios']     ?? '');
        $pFecha      = trim($_POST['fecha']           ?? date('Y-m-d'));
        $pMetodo     = trim($_POST['metodo_pago']     ?? '');

        if (!$pCotId || !$pCompania || !$pCobertura || !$pFecha) {
            echo json_encode(['ok'=>false,'msg'=>'Completa todos los campos requeridos.']); exit;
        }
        $cotExist = $cotizacionModel->getByIdYAgente($pCotId, $agenteId);
        if (!$cotExist) { echo json_encode(['ok'=>false,'msg'=>'Cotización no encontrada.']); exit; }

        try {
            $cotizacionModel->transaction(function ($db) use (
                $cotizacionModel, $historialModel,
                $pCotId, $pCompania, $pCobertura, $pPrecio,
                $pComentarios, $pFecha, $pMetodo, $agenteId, $empresaId, $cotExist
            ) {
                $db->prepare(
                    "UPDATE cotizaciones SET compania=?,tipo_cobertura=?,precio=?,comentarios=?,fecha=?
                     WHERE id=? AND agente_id=? AND empresa_id=?"
                )->execute([$pCompania,$pCobertura,$pPrecio,$pComentarios,$pFecha,$pCotId,$agenteId,$empresaId]);

                if ($pMetodo && in_array($pMetodo,['spei','contado','meses_sin_intereses'],true)) {
                    $cotizacionModel->updateMetodoPago($pCotId,$agenteId,$pMetodo);
                }
                $historialModel->registrar(
                    (int)$cotExist['cliente_id'], $agenteId, 'cotizacion',
                    'Cotización actualizada — Folio: '.($cotExist['folio']??'#'.$pCotId),
                    "{$pCompania} · {$pCobertura} · ".($pPrecio>0?'$'.number_format($pPrecio,2):'Sin definir')
                );
            });
        } catch (\Throwable $e) {
            error_log('Actualizar cotización: '.$e->getMessage());
            echo json_encode(['ok'=>false,'msg'=>'Error: '.$e->getMessage(),'_line'=>$e->getLine()]); exit;
        }

        $tiposDoc = ['doc_cotizacion'=>['Doc',['pdf','jpg','jpeg','png']],'foto_coche_1'=>['F1',['jpg','jpeg','png']],'foto_coche_2'=>['F2',['jpg','jpeg','png']],'foto_coche_3'=>['F3',['jpg','jpeg','png']],'foto_coche_4'=>['F4',['jpg','jpeg','png']],'foto_coche_5'=>['F5',['jpg','jpeg','png']],'ine'=>['INE',['pdf','jpg','jpeg','png']],'comprobante_dom'=>['CD',['pdf','jpg','jpeg','png']],'tarjeta_circulacion'=>['TC',['pdf','jpg','jpeg','png']],'factura_auto'=>['FA',['pdf','jpg','jpeg','png']],'constancia_fiscal'=>['CF',['pdf','jpg','jpeg','png']]];
        $uploadDir = dirname(__DIR__,3)."/uploads/cotizaciones/{$pCotId}/";
        $baseDir   = dirname(__DIR__,3)."/";
        if (!is_dir($uploadDir)) mkdir($uploadDir,0755,true);
        foreach ($tiposDoc as $tipo=>[$label,$exts]) {
            if (empty($_FILES[$tipo]['name'])) continue;
            $file=$_FILES[$tipo]; if($file['error']!==UPLOAD_ERR_OK) continue;
            $ext=strtolower(pathinfo($file['name'],PATHINFO_EXTENSION));
            if(!in_array($ext,$exts,true)||$file['size']>10*1024*1024) continue;
            $n=$tipo.'_'.time().'.'.$ext; $r="uploads/cotizaciones/{$pCotId}/{$n}";
            if(move_uploaded_file($file['tmp_name'],$uploadDir.$n)) {
                // Reemplaza el registro anterior del mismo tipo (soft-delete) y borra el archivo físico
                $resultado = $cotizacionModel->reemplazarArchivo($pCotId,$agenteId,$tipo,$file['name'],$n,$r);
                if (!empty($resultado['ruta_previa'])) {
                    $rutaFisicaAnterior = $baseDir . $resultado['ruta_previa'];
                    if (file_exists($rutaFisicaAnterior)) @unlink($rutaFisicaAnterior);
                }
            }
        }

        echo json_encode(['ok'=>true,'folio'=>$cotExist['folio']??'','cliente_id'=>$cotExist['cliente_id']]);
        exit;
    }

    echo json_encode(['ok'=>false,'msg'=>'Acción desconocida.']); exit;
}

// ── GET: datos para el formulario ─────────────────────────────────────────
$cotizacionId   = isset($_GET['cotizacion_id']) ? (int)$_GET['cotizacion_id'] : null;
$cotizacionEdit = null;
$modoEdicion    = false;

if ($cotizacionId) {
    $cotizacionEdit = $cotizacionModel->getByIdYAgente($cotizacionId, $agenteId);
    if ($cotizacionEdit) {
        $modoEdicion        = true;
        $_GET['cliente_id'] = $cotizacionEdit['cliente_id'];
    } else {
        $cotizacionId = null;
    }
}

$clientes     = $clienteModel->getParaSelector($agenteId);
$clienteIdPre = isset($_GET['cliente_id']) ? (int)$_GET['cliente_id'] : null;
$clienteSel   = $clienteIdPre ? $clienteModel->getSeleccionado($clienteIdPre, $agenteId) : null;

$ramoLabel = match($ramoActivo) {
    'vida'           => ['icono'=>'heart-fill','nombre'=>'Vida'],
    'gastos_medicos' => ['icono'=>'hospital','nombre'=>'Gastos Médicos'],
    'empresas'       => ['icono'=>'building','nombre'=>'Empresas'],
    default          => ['icono'=>'car-front','nombre'=>'Vehicular'],
};

$coberturasPorRamo = [
    'autos'          => ['Básico','Amplio','Amplio Total','Premium'],
    'vida'           => ['Temporal','Entera','Dotal','Entera con Ahorro'],
    'gastos_medicos' => ['Individual','Familiar','Empresarial','PyME'],
    'empresas'       => ['Responsabilidad Civil','Daños','Flotillas','Colectivo de Vida','GMM Colectivo','Multirriesgo','Fianzas'],
];
$companias = ['GNP','Qualitas','Chubb','Inbursa'];
$estadosF  = ['N'=>'Nueva','R'=>'Renovación'];

$coberturasPorRamoJSON = json_encode($coberturasPorRamo);
$pageTitle = $modoEdicion
    ? 'Editar Cotización — ' . ($cotizacionEdit['folio'] ?? '#'.$cotizacionId)
    : 'Nueva Cotización';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php require __DIR__ . '/../partials/_head.php'; ?>
    <style>
        .cot-wrapper{max-width:820px;margin:0 auto;}
        .section-label{font-size:11px;font-weight:700;color:var(--accent-color);text-transform:uppercase;letter-spacing:.7px;border-bottom:2px solid var(--accent-color);padding-bottom:5px;margin:28px 0 16px;display:inline-block;}
        .folio-card{background:linear-gradient(135deg,#0f2940,#1a3a5c,#0ea5e9);border-radius:var(--border-radius-lg);padding:22px 28px;color:#fff;margin-bottom:20px;display:flex;align-items:center;gap:20px;}
        .folio-icon{width:48px;height:48px;background:rgba(255,255,255,.15);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:22px;}
        .folio-content{flex:1;}
        .folio-label{font-size:11px;opacity:.65;text-transform:uppercase;letter-spacing:.5px;}
        .folio-value{font-size:22px;font-weight:800;letter-spacing:2px;font-family:'Courier New',monospace;margin:4px 0 2px;min-height:30px;}
        .folio-pending{font-size:14px;opacity:.45;font-style:italic;}
        .folio-copy{background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.3);color:#fff;border-radius:8px;padding:7px 14px;font-size:12px;cursor:pointer;}
        .doc-drop-zone{border:2px dashed var(--border-color);border-radius:var(--border-radius-sm);padding:11px;display:flex;align-items:center;gap:8px;cursor:pointer;transition:all .2s;background:var(--bg-secondary);font-size:12px;color:var(--text-muted);min-height:42px;}
        .doc-drop-zone:hover,.doc-drop-zone.done{border-color:var(--accent-color);background:var(--info-light);color:var(--accent-color);}
        .doc-drop-zone.done{border-color:var(--success-color);background:#f0fdf4;color:var(--success-color);}
        .doc-preview{font-size:11px;color:var(--success-color);font-weight:600;display:flex;align-items:center;gap:4px;}
        .doc-preview-remove{background:none;border:none;color:var(--danger-color);cursor:pointer;font-size:13px;}
        .metodo-card{border:2px solid var(--border-color);border-radius:var(--border-radius-sm);padding:12px;cursor:pointer;text-align:center;transition:all .2s;}
        .metodo-card:hover,.metodo-card.selected{border-color:var(--accent-color);background:var(--info-light);}
        .emit-docs-toggle{display:flex;align-items:center;gap:8px;padding:10px 14px;background:var(--bg-secondary);border:1px dashed var(--border-color);border-radius:var(--border-radius-sm);cursor:pointer;font-size:12px;color:var(--text-secondary);font-weight:600;margin-bottom:8px;transition:all .2s;}
        .emit-docs-toggle:hover{border-color:var(--accent-color);color:var(--accent-color);}
        .tag-info{display:inline-flex;align-items:center;gap:4px;font-size:10px;padding:2px 8px;background:var(--info-light);color:var(--accent-color);border-radius:99px;font-weight:600;}
    </style>
</head>
<body>
<div class="crm-wrapper">
    <?php require __DIR__ . '/../partials/_navbar.php'; ?>
    <main class="main-content">

        <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px;">
            <?php if ($modoEdicion && $clienteSel): ?>
            <a href="/cliente/<?php echo $clienteSel['id']; ?>" class="btn btn-ghost btn-sm">← <?php echo htmlspecialchars($clienteSel['nombre'].' '.$clienteSel['apellido_paterno']); ?></a>
            <span style="color:var(--text-muted);">›</span>
            <span style="font-size:13px;color:var(--text-secondary);">Editar Cotización</span>
            <?php else: ?>
            <a href="/cotizaciones" class="btn btn-ghost btn-sm">← Cotizaciones</a>
            <?php if ($clienteSel): ?>
            <span style="color:var(--text-muted);">›</span>
            <a href="/cliente/<?php echo $clienteSel['id']; ?>" class="btn btn-ghost btn-sm">
                <?php echo htmlspecialchars($clienteSel['nombre'].' '.$clienteSel['apellido_paterno']); ?>
            </a>
            <?php endif; ?>
            <?php endif; ?>
        </div>

        <header class="page-header" style="margin-bottom:24px;">
            <div class="page-header-content">
                <div>
                    <h1 class="page-title">
                        <span class="page-title-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14,2 14,8 20,8"/>
                            </svg>
                        </span>
                        <?php echo $modoEdicion ? 'Editar Cotización' : 'Nueva Cotización'; ?>
                    </h1>
                    <p class="page-subtitle">
                        <?php if ($modoEdicion): ?>
                        Modifica los datos de la cotización. El folio, cliente y estado <strong>no cambian</strong>.
                        <?php else: ?>
                        Registra la cotización, copia el folio para usarlo en la aseguradora y adjunta el documento resultado.
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </header>

        <div class="cot-wrapper">
            <div class="card">
                <div class="card-body">

                    <!-- ═══ FOLIO ═══ -->
                    <?php if ($modoEdicion): ?>
                    <div class="folio-card" style="background:linear-gradient(135deg,#1a3a1a,#2d5a2d,#22c55e);">
                        <div class="folio-icon"><?= icono('pencil') ?></div>
                        <div class="folio-content">
                            <div class="folio-label">Modo Edición — Folio existente (no cambia)</div>
                            <div class="folio-value"><?php echo htmlspecialchars($cotizacionEdit['folio'] ?? '#'.$cotizacionId); ?></div>
                            <div style="font-size:12px;opacity:.75;">Estado actual: <strong><?php echo ucfirst($cotizacionEdit['estado'] ?? '—'); ?></strong> · No se puede modificar desde aquí.</div>
                        </div>
                        <input type="hidden" id="cotizacionIdHidden" value="<?php echo $cotizacionId; ?>">
                    </div>
                    <?php else: ?>
                    <div class="folio-card">
                        <div class="folio-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14,2 14,8 20,8"/>
                            </svg>
                        </div>
                        <div class="folio-content">
                            <div class="folio-label">Folio generado automáticamente</div>
                            <div class="folio-value" id="folioDisplay">
                                <span class="folio-pending">Selecciona ramo y cobertura…</span>
                            </div>
                            <div id="folioHint" style="font-size:12px;opacity:.7;display:none;">
                                Usa este folio al cotizar en el portal de la aseguradora.
                            </div>
                        </div>
                        <button class="folio-copy" id="btnCopyFolio" style="display:none;"><?= icono('clipboard') ?> Copiar</button>
                    </div>
                    <?php endif; ?>

                    <!-- ═══ SECCIÓN 1: CLIENTE ═══ -->
                    <span class="section-label">1 · Cliente</span>
                    <?php if ($clienteSel): ?>
                    <div style="background:linear-gradient(135deg,#1d4f6f,var(--primary-light));border-radius:var(--border-radius);padding:14px 18px;display:flex;align-items:center;gap:14px;color:white;margin-bottom:8px;">
                        <div style="width:42px;height:42px;background:rgba(255,255,255,.2);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:700;">
                            <?php echo strtoupper(substr($clienteSel['nombre'],0,1).substr($clienteSel['apellido_paterno'],0,1)); ?>
                        </div>
                        <div style="flex:1;">
                            <div style="font-weight:700;font-size:15px;"><?php echo htmlspecialchars($clienteSel['nombre'].' '.$clienteSel['apellido_paterno']); ?></div>
                            <div style="font-size:12px;opacity:.85;"><?php echo htmlspecialchars($clienteSel['correo']); ?></div>
                        </div>
                        <input type="hidden" id="clienteIdHidden" value="<?php echo $clienteSel['id']; ?>">
                    </div>
                    <?php else: ?>
                    <div class="form-group">
                        <label class="form-label required">Cliente</label>
                        <select class="form-select" id="clienteSelect">
                            <option value="">Selecciona un cliente…</option>
                            <?php foreach ($clientes as $cl): ?>
                            <option value="<?php echo $cl['id']; ?>">
                                <?php echo htmlspecialchars($cl['nombre'].' '.$cl['apellido_paterno'].' '.$cl['apellido_materno']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <div id="limitBar" style="display:none;align-items:center;gap:10px;padding:10px 14px;background:var(--bg-secondary);border-radius:var(--border-radius-sm);border:1px solid var(--border-color);font-size:13px;margin-top:8px;">
                        <span style="font-size:12px;color:var(--text-secondary);">Cotizaciones:</span>
                        <div style="flex:1;height:5px;background:var(--border-color);border-radius:99px;overflow:hidden;">
                            <div id="limitFill" style="height:100%;border-radius:99px;background:var(--success-color);transition:width .4s;width:0%;"></div>
                        </div>
                        <span id="limitText" style="font-size:12px;font-weight:700;">0/10</span>
                    </div>

                    <!-- ═══ SECCIÓN 2: RAMO ═══ -->
                    <span class="section-label">2 · Ramo del Seguro</span>
                    <div style="display:flex;align-items:center;gap:12px;padding:12px 18px;background:var(--info-light);border:1px solid var(--accent-color);border-radius:var(--border-radius);margin-bottom:16px;">
                        <span style="font-size:26px;"><?php echo icono($ramoLabel['icono']); ?></span>
                        <div style="flex:1;">
                            <div style="font-weight:700;color:var(--accent-color);font-size:14px;"><?php echo $ramoLabel['nombre']; ?></div>
                            <div style="font-size:11px;color:var(--text-secondary);">Ramo activo en tu sesión. <a href="/dashboard" style="color:var(--accent-color);">Cambiar ramo</a></div>
                        </div>
                    </div>
                    <input type="hidden" id="ramoHidden" value="<?php echo htmlspecialchars($ramoActivo ?? 'autos'); ?>">

                    <!-- ═══ SECCIÓN 3: DATOS DEL SEGURO ═══ -->
                    <span class="section-label">3 · Datos del Seguro</span>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label required">Tipo de Cobertura</label>
                            <select class="form-select" id="coberturaSelect">
                                <option value="">Selecciona…</option>
                                <?php foreach($coberturasPorRamo[$ramoActivo] ?? [] as $cob): ?>
                                <option value="<?php echo $cob; ?>" <?php echo ($modoEdicion && ($cotizacionEdit['tipo_cobertura']??'')===$cob)?'selected':''; ?>><?php echo $cob; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Estado de la cotización</label>
                            <select class="form-select" id="estadoFolioSelect" data-handler="actualizarFolio" <?php echo $modoEdicion?'disabled':''; ?>>
                                <?php foreach($estadosF as $v=>$l): ?>
                                <option value="<?php echo $v; ?>" <?php echo ($modoEdicion && ($cotizacionEdit['estado_folio']??'N')===$v)?'selected':''; ?>><?php echo $l; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label required">Compañía Aseguradora</label>
                            <select class="form-select" id="companiaSelect">
                                <option value="">Selecciona…</option>
                                <?php foreach($companias as $c): ?>
                                <option value="<?php echo $c; ?>" <?php echo ($modoEdicion && ($cotizacionEdit['compania']??'')===$c)?'selected':''; ?>><?php echo $c; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label required">Fecha de cotización</label>
                            <input type="date" class="form-input" id="fechaInput" value="<?php echo $modoEdicion ? htmlspecialchars($cotizacionEdit['fecha']??date('Y-m-d')) : date('Y-m-d'); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <!-- Prima opcional: se puede dejar en 0 o vacío -->
                            <label class="form-label">
                                Prima anual (MXN)
                                <span style="font-size:10px;font-weight:400;color:var(--text-muted);margin-left:4px;">— opcional</span>
                            </label>
                            <input type="number" class="form-input" id="precioInput"
                                   min="0" step="0.01" placeholder="0.00 — se puede definir después"
                                   value="<?php echo $modoEdicion ? htmlspecialchars((string)($cotizacionEdit['precio'] ?? '')) : ''; ?>">
                        </div>
                        <div class="form-group" style="display:flex;align-items:flex-end;">
                            <div id="pricePreview" style="display:none;background:var(--info-light,#eff6ff);border:1px solid var(--accent-color);border-radius:var(--border-radius-sm);padding:10px 14px;width:100%;">
                                <div id="previewAnual"   style="font-size:16px;font-weight:700;color:var(--accent-color);"></div>
                                <div id="previewMensual" style="font-size:12px;color:var(--text-secondary);margin-top:2px;"></div>
                            </div>
                        </div>
                    </div>

                    <!-- ═══ COMENTARIOS ═══ -->
                    <div class="form-group">
                        <label class="form-label">Notas y comentarios</label>
                        <textarea class="form-textarea" id="comentariosInput" rows="2" placeholder="Condiciones especiales, descuentos acordados, observaciones…"><?php echo $modoEdicion ? htmlspecialchars($cotizacionEdit['comentarios']??'') : ''; ?></textarea>
                    </div>

                    <!-- ═══ SECCIÓN 4: MÉTODO DE PAGO ═══ -->
                    <span class="section-label">4 · Método de Pago <span style="font-size:10px;font-weight:400;text-transform:none;color:var(--text-muted);">estimado — opcional</span></span>
                    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:20px;">
                        <?php foreach(['spei'=>['bank','SPEI','Transferencia'],'contado'=>['cash','Contado','Efectivo'],'meses_sin_intereses'=>['credit-card','MSI','Meses sin intereses']] as $k=>[$ico,$tit,$sub]): ?>
                        <div class="metodo-card" id="metodo_<?php echo $k; ?>" data-action="selectMetodo" data-key="<?php echo $k; ?>">
                            <div style="font-size:20px;margin-bottom:4px;"><?php echo icono($ico); ?></div>
                            <div style="font-size:12px;font-weight:600;"><?php echo $tit; ?></div>
                            <div style="font-size:10px;color:var(--text-muted);"><?php echo $sub; ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" id="metodoPagoInput" value="">

                    <!-- ═══ SECCIÓN 5: DOCUMENTOS ═══ -->
                    <span class="section-label">5 · Documentos</span>

                    <div style="margin-bottom:20px;">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                            <span style="font-size:13px;font-weight:700;"><?= icono('clipboard') ?> Documento de cotización</span>
                            <span class="tag-info">Principal</span>
                        </div>
                        <p style="font-size:12px;color:var(--text-secondary);margin-bottom:10px;">
                            El PDF o imagen que genera la aseguradora con el resumen de la cotización.
                        </p>
                        <div style="display:flex;flex-direction:column;gap:6px;max-width:420px;">
                            <div class="doc-drop-zone" id="zone_doc_cotizacion" data-action="openFile" data-target="file_doc_cotizacion">
                                <input type="file" id="file_doc_cotizacion" name="doc_cotizacion" accept=".pdf,.jpg,.jpeg,.png" data-handler="docSelected" data-tipo="doc_cotizacion" style="display:none;">
                                <span style="font-size:18px;"><?= icono('file-earmark-text') ?></span>
                                <span>Clic para subir (PDF o imagen · máx 10MB)</span>
                            </div>
                            <div class="doc-preview" id="prev_doc_cotizacion" style="display:none;"></div>
                        </div>
                    </div>

                    <?php if ($ramoActivo === 'autos'): ?>
                    <div style="margin-bottom:20px;">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                            <span style="font-size:13px;font-weight:700;"><?= icono('camera') ?> Fotos del vehículo</span>
                            <span style="font-size:10px;color:var(--text-muted);">hasta 5 · JPG/PNG</span>
                        </div>
                        <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:10px;">
                            <?php for ($i=1;$i<=5;$i++): $tipo="foto_coche_{$i}"; ?>
                            <div>
                                <div class="doc-drop-zone" id="zone_<?php echo $tipo; ?>" data-action="openFile" data-target="file_foto_<?php echo $i; ?>"
                                     style="aspect-ratio:1;flex-direction:column;padding:10px;justify-content:center;">
                                    <input type="file" id="file_foto_<?php echo $i; ?>" name="<?php echo $tipo; ?>" accept=".jpg,.jpeg,.png" data-handler="docSelected" data-tipo="<?php echo $tipo; ?>" style="display:none;">
                                    <span style="font-size:20px;"><?= icono('camera') ?></span>
                                    <span style="font-size:10px;margin-top:3px;">Foto <?php echo $i; ?></span>
                                </div>
                                <div class="doc-preview" id="prev_<?php echo $tipo; ?>" style="display:none;margin-top:4px;"></div>
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div>
                        <div class="emit-docs-toggle" id="toggleEmitBtn">
                            <span><?= icono('folder') ?></span>
                            <span style="flex:1;">Documentos para emisión</span>
                            <span style="font-size:11px;color:var(--text-muted);">INE, comprobante, tarjeta de circulación, etc.</span>
                            <span id="emitArrow" style="font-size:12px;transition:transform .2s;">▼</span>
                        </div>
                        <div id="emitDocsPanel" style="display:none;">
                            <p style="font-size:12px;color:var(--text-secondary);margin-bottom:12px;padding:8px 12px;background:var(--bg-secondary);border-radius:var(--border-radius-sm);border-left:3px solid var(--warning-color);">
                                <?= icono('exclamation-triangle-fill') ?> Estos documentos son necesarios para <strong>emitir la póliza</strong>, no para cotizar. Súbelos solo si el cliente ya los compartió.
                            </p>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                                <?php
                                $docEmision = [
                                    'ine'                 => ['person-vcard','INE / Identificación oficial'],
                                    'comprobante_dom'     => ['house','Comprobante de domicilio'],
                                    'tarjeta_circulacion' => ['car-front','Tarjeta de circulación'],
                                    'factura_auto'        => ['receipt','Factura del auto'],
                                    'constancia_fiscal'   => ['files','Constancia de situación fiscal'],
                                ];
                                foreach ($docEmision as $tipo => [$icon, $label]): ?>
                                <div style="display:flex;flex-direction:column;gap:5px;">
                                    <label style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:var(--text-secondary);">
                                        <span><?php echo icono($icon); ?></span>
                                        <span><?php echo $label; ?></span>
                                    </label>
                                    <div class="doc-drop-zone" id="zone_<?php echo $tipo; ?>" data-action="openFile" data-target="file_<?php echo $tipo; ?>">
                                        <input type="file" id="file_<?php echo $tipo; ?>" name="<?php echo $tipo; ?>" accept=".pdf,.jpg,.jpeg,.png" data-handler="docSelected" data-tipo="<?php echo $tipo; ?>" style="display:none;">
                                        <span style="font-size:16px;"><?= icono('file-earmark-text') ?></span>
                                        <span>Clic para subir</span>
                                    </div>
                                    <div class="doc-preview" id="prev_<?php echo $tipo; ?>" style="display:none;"></div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div id="docCounter" style="font-size:12px;color:var(--text-secondary);margin:12px 0 4px;text-align:right;">0 documentos seleccionados</div>

                    <!-- Acciones -->
                    <div style="display:flex;justify-content:flex-end;gap:12px;margin-top:24px;padding-top:20px;border-top:1px solid var(--border-color);">
                        <?php if ($modoEdicion && $clienteSel): ?>
                        <a href="/cliente/<?php echo $clienteSel['id']; ?>" class="btn btn-outline">Cancelar</a>
                        <?php else: ?>
                        <a href="/cotizaciones" class="btn btn-outline">Cancelar</a>
                        <?php endif; ?>
                        <button class="btn btn-primary" id="btnGuardar">
                            <?php echo $modoEdicion ? icono('floppy') . ' Guardar Cambios' : icono('check-circle-fill') . ' Crear Cotización'; ?>
                        </button>
                    </div>

                </div>
            </div>
        </div>
    </main>
</div>

<div class="toast-container" id="toastContainer"></div>
<script nonce="<?= $cspNonce ?>">
const coberturasPorRamo = <?php echo $coberturasPorRamoJSON; ?>;
const clientePreId      = <?php echo $clienteIdPre ?? 'null'; ?>;
const modoEdicion       = <?php echo $modoEdicion ? 'true' : 'false'; ?>;
const cotizacionEditId  = <?php echo $cotizacionId ?? 'null'; ?>;
let folioActual = null;
const docsSeleccionados = {};

document.addEventListener('DOMContentLoaded', function() {
    if (clientePreId) cargarLimite(clientePreId);
    if (!modoEdicion) actualizarFolio();
    if (modoEdicion) {
        const metodoExist = <?php echo json_encode($cotizacionEdit['metodo_pago'] ?? ''); ?>;
        if (metodoExist) selectMetodo(metodoExist);
        actualizarPrecio();
    }
    // Actualizar preview de precio al escribir
    document.getElementById('precioInput')?.addEventListener('input', actualizarPrecio);
});

// ── Folio ──────────────────────────────────────────────────────────────────
function actualizarFolio() {
    const ramo      = document.getElementById('ramoHidden').value;
    const cobertura = document.getElementById('coberturaSelect').value;
    const estadoF   = document.getElementById('estadoFolioSelect').value;
    if (!cobertura) {
        document.getElementById('folioDisplay').innerHTML = '<span class="folio-pending">Selecciona cobertura…</span>';
        document.getElementById('btnCopyFolio').style.display = 'none';
        document.getElementById('folioHint').style.display = 'none';
        folioActual = null; return;
    }
    const fd = new FormData();
    fd.append('accion','preview_folio');
    fd.append('ramo', ramo);
    fd.append('tipo_cobertura', cobertura);
    fd.append('estado_folio', estadoF);
    fetch('/crear-cotizacion', {method:'POST', body:fd}).then(r=>r.json()).then(d=>{
        if (d.ok) {
            folioActual = d.folio;
            document.getElementById('folioDisplay').textContent = d.folio;
            document.getElementById('btnCopyFolio').style.display = '';
            document.getElementById('folioHint').style.display = '';
        }
    });
}
function copiarFolio() {
    if (!folioActual) return;
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(folioActual).then(() => showToast('Folio copiado al portapapeles', 'success'));
    } else {
        const ta = document.createElement('textarea');
        ta.value = folioActual; ta.style.position = 'fixed'; ta.style.opacity = '0';
        document.body.appendChild(ta); ta.focus(); ta.select();
        try { document.execCommand('copy'); showToast('Folio copiado', 'success'); }
        catch(e) { showToast('Folio: ' + folioActual, 'warning'); }
        document.body.removeChild(ta);
    }
}

// ── Cliente / Límite ───────────────────────────────────────────────────────
function onClienteChange(id) { if (id) cargarLimite(parseInt(id)); }
function cargarLimite(id) {
    fetch(`/crear-cotizacion?ajax=limite&cliente_id=${id}`).then(r=>r.json()).then(d=>{
        const pct = (d.total/10)*100;
        document.getElementById('limitBar').style.display = 'flex';
        document.getElementById('limitFill').style.width  = pct+'%';
        document.getElementById('limitText').textContent  = `${d.total}/10`;
        const fill = document.getElementById('limitFill');
        fill.style.background = pct>=100 ? 'var(--danger-color)' : pct>=70 ? 'var(--warning-color)' : 'var(--success-color)';
        if (d.total >= 10) {
            document.getElementById('btnGuardar').disabled = true;
            document.getElementById('btnGuardar').textContent = 'Límite alcanzado';
            showToast('Límite de 10 cotizaciones alcanzado.', 'error');
        }
    });
}

// ── Precio preview ─────────────────────────────────────────────────────────
function actualizarPrecio() {
    const p = parseFloat(document.getElementById('precioInput').value) || 0;
    const prev = document.getElementById('pricePreview');
    if (p > 0) {
        prev.style.display = '';
        document.getElementById('previewAnual').textContent   = '$' + p.toLocaleString('es-MX',{minimumFractionDigits:2});
        document.getElementById('previewMensual').textContent = '≈ $' + (p/12).toLocaleString('es-MX',{minimumFractionDigits:2}) + ' / mes';
    } else {
        prev.style.display = 'none';
    }
}

// ── Método de pago ─────────────────────────────────────────────────────────
function selectMetodo(tipo) {
    document.querySelectorAll('.metodo-card').forEach(c => c.classList.remove('selected'));
    const cur = document.getElementById('metodoPagoInput').value;
    if (cur === tipo) {
        document.getElementById('metodoPagoInput').value = '';
    } else {
        document.getElementById('metodo_' + tipo)?.classList.add('selected');
        document.getElementById('metodoPagoInput').value = tipo;
    }
}

// ── Documentos ─────────────────────────────────────────────────────────────
function onDocSelected(input, tipo) {
    const file = input.files[0]; if (!file) return;
    if (file.size > 10*1024*1024) { showToast('El archivo supera 10MB', 'error'); input.value = ''; return; }
    docsSeleccionados[tipo] = file;
    document.getElementById('zone_' + tipo)?.classList.add('done');
    const prev = document.getElementById('prev_' + tipo);
    if (prev) {
        prev.style.display = 'flex';
        prev.innerHTML = `${iconoSvg('paperclip')} <span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${file.name}</span> <button class="doc-preview-remove" data-action="quitarDoc" data-tipo="${tipo}">✕</button>`;
    }
    actualizarContador();
}
function quitarDoc(tipo) {
    delete docsSeleccionados[tipo];
    const input = document.querySelector(`[name="${tipo}"]`); if (input) input.value = '';
    document.getElementById('zone_' + tipo)?.classList.remove('done');
    const prev = document.getElementById('prev_' + tipo); if (prev) prev.style.display = 'none';
    actualizarContador();
}
function actualizarContador() {
    const n = Object.keys(docsSeleccionados).length;
    const el = document.getElementById('docCounter');
    if (el) el.textContent = n === 0 ? '0 documentos seleccionados' : `${n} documento${n>1?'s':''} seleccionado${n>1?'s':''}`;
}
function toggleEmitDocs(btn) {
    const panel = document.getElementById('emitDocsPanel');
    const arrow = document.getElementById('emitArrow');
    const open  = panel.style.display !== 'none';
    panel.style.display = open ? 'none' : '';
    arrow.style.transform = open ? '' : 'rotate(180deg)';
    btn.style.borderColor = open ? '' : 'var(--accent-color)';
}

// ── Guardar ────────────────────────────────────────────────────────────────
function guardarCotizacion() {
    const clienteId = clientePreId || parseInt(document.getElementById('clienteSelect')?.value || '0');
    const cobertura = document.getElementById('coberturaSelect').value;
    const compania  = document.getElementById('companiaSelect').value;
    const precioRaw = document.getElementById('precioInput').value.trim();
    // Prima es opcional: vacío o 0 son válidos
    const precio    = precioRaw === '' ? '0' : precioRaw;
    const fecha     = document.getElementById('fechaInput').value;

    if (!modoEdicion && !clienteId) { showToast('Selecciona un cliente', 'error'); return; }
    if (!cobertura) { showToast('Selecciona el tipo de cobertura', 'error'); return; }
    if (!compania)  { showToast('Selecciona la compañía aseguradora', 'error'); return; }
    if (!fecha)     { showToast('Selecciona la fecha', 'error'); return; }

    const btn = document.getElementById('btnGuardar');
    btn.disabled = true;
    btn.textContent = 'Guardando…';

    const fd = new FormData();

    if (modoEdicion) {
        fd.append('accion',         'actualizar');
        fd.append('cotizacion_id',  cotizacionEditId);
        fd.append('tipo_cobertura', cobertura);
        fd.append('compania',       compania);
        fd.append('precio',         precio);
        fd.append('fecha',          fecha);
        fd.append('comentarios',    document.getElementById('comentariosInput').value);
        fd.append('metodo_pago',    document.getElementById('metodoPagoInput').value);
        Object.entries(docsSeleccionados).forEach(([tipo, file]) => fd.append(tipo, file));

        fetch('/crear-cotizacion', {method:'POST', body:fd})
            .then(r => r.json())
            .then(d => {
                if (d.ok) {
                    showToast(iconoSvg('floppy') + ' Cotización actualizada · Folio: ' + d.folio, 'success');
                    setTimeout(() => { window.location.href = '/cliente/' + d.cliente_id + '?ok=cotizacion'; }, 1200);
                } else {
                    showToast(d.msg || 'Error al actualizar la cotización', 'error');
                    btn.disabled = false;
                    btn.innerHTML = iconoSvg('floppy') + ' Guardar Cambios';
                }
            })
            .catch(() => {
                showToast('Error de conexión', 'error');
                btn.disabled = false;
                btn.innerHTML = iconoSvg('floppy') + ' Guardar Cambios';
            });
    } else {
        fd.append('accion',         'crear');
        fd.append('cliente_id',     clienteId);
        fd.append('ramo',           document.getElementById('ramoHidden').value);
        fd.append('tipo_cobertura', cobertura);
        fd.append('compania',       compania);
        fd.append('precio',         precio);
        fd.append('estado_folio',   document.getElementById('estadoFolioSelect').value);
        fd.append('fecha',          fecha);
        fd.append('comentarios',    document.getElementById('comentariosInput').value);
        fd.append('metodo_pago',    document.getElementById('metodoPagoInput').value);
        Object.entries(docsSeleccionados).forEach(([tipo, file]) => fd.append(tipo, file));

        fetch('/crear-cotizacion', {method:'POST', body:fd})
            .then(r => r.json())
            .then(d => {
                if (d.ok) {
                    showToast(iconoSvg('check-circle-fill') + ' Cotización creada · Folio: ' + d.folio, 'success');
                    setTimeout(() => {
                        window.location.href = clientePreId
                            ? '/cliente/' + clientePreId + '?ok=cotizacion'
                            : '/cotizaciones?ok=creada';
                    }, 1200);
                } else {
                    showToast(d.msg || 'Error al crear la cotización', 'error');
                    btn.disabled = false;
                    btn.innerHTML = iconoSvg('check-circle-fill') + ' Crear Cotización';
                }
            })
            .catch(() => {
                showToast('Error de conexión', 'error');
                btn.disabled = false;
                btn.innerHTML = iconoSvg('check-circle-fill') + ' Crear Cotización';
            });
    }
}

function showToast(msg, type='success') {
    const c = document.getElementById('toastContainer'); if (!c) return;
    const t = document.createElement('div'); t.className = `toast ${type}`;
    t.innerHTML = `<div class="toast-icon">${type==='error'?'✗':'✓'}</div><div class="toast-message">${msg}</div><button class="toast-close" data-action="toast-close">&times;</button>`;
    c.appendChild(t);
    setTimeout(() => { t.classList.add('hiding'); setTimeout(() => t.remove(), 300); }, 5000);
}
</script>

<script src="/js/crm-events.js" nonce="<?= $cspNonce ?>"></script>
<script src="/js/modales.js"    nonce="<?= $cspNonce ?>"></script>
</body>
</html>
