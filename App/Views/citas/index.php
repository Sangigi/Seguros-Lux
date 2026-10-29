<?php
declare(strict_types=1);

use App\Security\SessionManager;
use App\Models\{CitaModel, ClientModel, HistorialModel};

SessionManager::requireAuth('/login');

$agenteId     = SessionManager::getAgentId();
$ramo         = SessionManager::getRamo();
$empresaId    = \App\Security\Tenant::requireId();
$citaModel    = new CitaModel();
$clienteModel = new ClientModel();
$historialModel = new HistorialModel();

// ── AJAX ──────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    header('Content-Type: application/json');

    if ($_POST['accion'] === 'crear') {
        $pCliente    = (int)($_POST['cliente_id']  ?? 0);
        $pTipo       = trim($_POST['tipo']         ?? 'llamada');
        $pFecha      = trim($_POST['fecha']        ?? '');
        $pHora       = trim($_POST['hora']         ?? '');
        $pComentarios= trim($_POST['comentarios']  ?? '');

        if (!$pCliente || !$pFecha || !$pHora) {
            echo json_encode(['ok'=>false,'msg'=>'Faltan campos requeridos.']); exit;
        }
        if (!$citaModel->clientePerteneceAAgente($pCliente, $agenteId)) {
            echo json_encode(['ok'=>false,'msg'=>'Cliente no encontrado.']); exit;
        }

        $pMeetLink = trim($_POST['meet_link'] ?? '');
        // FIX ramo: usar el ramo enviado por el formulario; cae al ramo de sesión si no viene
        $pRamo = trim($_POST['ramo'] ?? '') ?: $ramo;
        $nuevaId = $citaModel->crear($pCliente, $agenteId, $pTipo, $pFecha, $pHora, $pComentarios, $pRamo);
        if ($pMeetLink && $nuevaId) {
            \Database\Connection::getConnection()
                ->prepare('UPDATE citas SET comentarios = CONCAT(COALESCE(comentarios,""), "\nMeet: ", ?) WHERE id = ? AND empresa_id = ?')
                ->execute([$pMeetLink, $nuevaId, $empresaId]);
        }
        $historialModel->registrar($pCliente, $agenteId, 'cita', 'Cita programada — '.ucfirst($pTipo), "$pFecha a las $pHora. $pComentarios");
        echo json_encode(['ok'=>true,'id'=>$nuevaId]); exit;
    }

    if ($_POST['accion'] === 'estado') {
        $pCitaId = (int)($_POST['cita_id'] ?? 0);
        $pEstado = trim($_POST['estado']   ?? '');
        if (!$pCitaId || !in_array($pEstado, ['programada','completada','cancelada'], true)) {
            echo json_encode(['ok'=>false,'msg'=>'Datos inválidos.']); exit;
        }
        $ok = $citaModel->updateEstado($pCitaId, $agenteId, $pEstado);
        echo json_encode(['ok'=>$ok]); exit;
    }

    echo json_encode(['ok'=>false,'msg'=>'Acción desconocida.']); exit;
}

// ── Mes activo ────────────────────────────────────────────────────────────
$hoy     = new DateTime();
$anio    = isset($_GET['anio']) ? (int)$_GET['anio'] : (int)$hoy->format('Y');
$mes     = isset($_GET['mes'])  ? (int)$_GET['mes']  : (int)$hoy->format('n');
if ($mes<1)  { $mes=12; $anio--; }
if ($mes>12) { $mes=1;  $anio++; }

$diasEnMes = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);
$fechaIni  = sprintf('%04d-%02d-01',   $anio, $mes);
$fechaFin  = sprintf('%04d-%02d-%02d', $anio, $mes, $diasEnMes);
$primerDia = new DateTime("$anio-$mes-01");
$diaSemana = (int)$primerDia->format('w');

$nombresMes  = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
$mesesCortos = [1=>'Ene',2=>'Feb',3=>'Mar',4=>'Abr',5=>'May',6=>'Jun',7=>'Jul',8=>'Ago',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dic'];

// ── Filtro de ramo opcional (GET ?ramo=) — null = todos ───────────────────
$ramosValidos   = ['autos','vida','gastos_medicos','empresas'];
$ramoFiltro     = in_array($_GET['ramo'] ?? '', $ramosValidos, true) ? $_GET['ramo'] : null;
$ramoLabels = [
    'autos'          => ['icon'=>'car-front',  'label'=>'Autos',       'color'=>'#0ea5e9'],
    'vida'           => ['icon'=>'heart-fill', 'label'=>'Vida',        'color'=>'#c9a227'],
    'gastos_medicos' => ['icon'=>'hospital',   'label'=>'GMM',         'color'=>'#10b981'],
    'empresas'       => ['icon'=>'building',   'label'=>'Empresas',    'color'=>'#8b5cf6'],
];

$citas        = $citaModel->getByMesYRamo($agenteId, $fechaIni, $fechaFin, $ramoFiltro);
$proximas     = $citaModel->getProximasYRamo($agenteId, $ramoFiltro);
$stats        = $citaModel->getStatsByMes($agenteId, $fechaIni, $fechaFin, $ramoFiltro);
$clientesList = $clienteModel->getParaSelector($agenteId);

$citasPorDia = [];
foreach ($citas as $c) {
    $dia = (int)date('j', strtotime($c['fecha']));
    $citasPorDia[$dia][] = $c;
}

$mesPrev = $mes===1?12:$mes-1; $anioPrev = $mes===1?$anio-1:$anio;
$mesSig  = $mes===12?1:$mes+1; $anioSig  = $mes===12?$anio+1:$anio;

$citasJSON = json_encode($citas);
$pageTitle = 'Citas';

// ── Respuesta AJAX para navegación de mes sin recarga ─────────────────────
if (isset($_GET['_ajax'])) {
    header('Content-Type: application/json');
    $hoyParts = explode('-', $hoy->format('Y-m-d'));
    echo json_encode([
        'diasEnMes' => $diasEnMes,
        'primerDia' => $diaSemana,
        'hoy'       => $hoy->format('Y-m-d'),
        'citas'     => $citas,
        'proximas'  => $proximas,
        'stats'     => [
            'total'       => (int)$stats['total'],
            'programadas' => (int)$stats['programadas'],
            'completadas' => (int)$stats['completadas'],
            'canceladas'  => (int)$stats['canceladas'],
        ],
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php require __DIR__ . '/../partials/_head.php'; ?>
    <style>
        .cal-grid{display:grid;grid-template-columns:repeat(7,1fr);border-left:1px solid var(--border-color);border-top:1px solid var(--border-color);}
        .cal-weekday{padding:10px;text-align:center;font-size:11px;font-weight:700;color:var(--text-secondary);background:#f8fafc;text-transform:uppercase;letter-spacing:.5px;border-right:1px solid var(--border-color);border-bottom:1px solid var(--border-color);}
        .cal-day{min-height:110px;padding:8px;border-right:1px solid var(--border-color);border-bottom:1px solid var(--border-color);cursor:pointer;transition:background var(--transition-fast);}
        .cal-day:hover{background:#f8fafc;}
        .cal-day.other-month{background:#fafafa;color:var(--text-muted);}
        .cal-day.today{background:#eff6ff;}
        .cal-day-num{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:50%;font-weight:600;font-size:13px;margin-bottom:6px;}
        .cal-day.today .cal-day-num{background:var(--accent-color);color:#fff;}
        .cal-event{font-size:11px;padding:3px 7px;border-radius:4px;margin-bottom:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;cursor:pointer;}
        .cal-event.llamada{background:var(--info-light);color:#1e40af;}
        .cal-event.videollamada{background:#fef3c7;color:#92400e;}
        .cal-event.presencial{background:#dcfce7;color:#065f46;}
        .cal-event.completada{background:#e2e8f0;color:#475569;}
        .cal-event.cancelada{background:#fee2e2;color:#991b1b;text-decoration:line-through;}
        .cal-more{font-size:11px;color:var(--accent-color);font-weight:600;cursor:pointer;padding:2px 4px;}
        .upcoming-item{display:flex;gap:12px;padding:12px 0;border-bottom:1px solid var(--border-color);align-items:flex-start;}
        .upcoming-item:last-child{border-bottom:none;}
        .upcoming-date{min-width:46px;text-align:center;background:var(--info-light);border-radius:var(--border-radius-sm);padding:6px;}
        .upcoming-date-day{font-size:20px;font-weight:700;color:var(--accent-color);line-height:1;}
        .upcoming-date-mes{font-size:10px;text-transform:uppercase;color:var(--text-secondary);}
        @media(max-width:900px){.layout-cal{grid-template-columns:1fr !important;}.cal-day{min-height:70px;}.cal-event{display:none;}}
    </style>
</head>
<body>
<div class="crm-wrapper">
    <?php require __DIR__ . '/../partials/_navbar.php'; ?>
    <main class="main-content">

        <header class="page-header">
            <div class="page-header-content">
                <div>
                    <h1 class="page-title">
                        <span class="page-title-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/>
                                <line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                            </svg>
                        </span>
                        Calendario de Citas
                    </h1>
                    <p class="page-subtitle" id="pageSubtitle"><?php echo $nombresMes[$mes].' '.$anio; ?></p>
                </div>
                <div class="page-actions">
                    <button class="btn btn-primary" data-open-modal="modalNuevaCita">+ Nueva Cita</button>
                </div>
            </div>
        </header>

        <!-- ── Tabs de ramo ────────────────────────────────────────────────── -->
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px;">
            <?php
            $qBase = http_build_query(array_filter(['anio'=>$anio,'mes'=>$mes]));
            ?>
            <a href="?<?php echo $qBase; ?>"
               class="btn btn-sm <?php echo $ramoFiltro === null ? 'btn-primary' : 'btn-outline'; ?>"
               style="border-radius:99px;"><?= icono('bar-chart') ?> Todos</a>
            <?php foreach ($ramoLabels as $slug => $info): ?>
            <a href="?<?php echo $qBase; ?>&ramo=<?php echo $slug; ?>"
               class="btn btn-sm <?php echo $ramoFiltro === $slug ? 'btn-primary' : 'btn-outline'; ?>"
               style="border-radius:99px;<?php echo $ramoFiltro === $slug ? 'background:'.$info['color'].';border-color:'.$info['color'].';' : ''; ?>">
                <?php echo icono($info['icon']).' '.$info['label']; ?>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Stats -->
        <section class="stats-grid" style="grid-template-columns:repeat(4,1fr);">
            <?php foreach([
                [(int)$stats['total'],      'Total este mes',  ''],
                [(int)$stats['programadas'],'Programadas',     'info'],
                [(int)$stats['completadas'],'Completadas',     'success'],
                [(int)$stats['canceladas'], 'Canceladas',      'danger'],
            ] as [$v,$l,$c]): ?>
            <div class="stat-card <?php echo $c; ?>">
                <div class="stat-card-value"><?php echo $v; ?></div>
                <div class="stat-card-label"><?php echo $l; ?></div>
            </div>
            <?php endforeach; ?>
        </section>

        <!-- Calendario + Sidebar -->
        <div class="layout-cal" style="display:grid;grid-template-columns:1fr 300px;gap:24px;align-items:start;">

            <!-- Calendario -->
            <div class="card">
                <div class="card-header">
                    <div style="display:flex;align-items:center;gap:12px;">
                        <button type="button" id="btnPrevMes" class="btn btn-outline btn-icon btn-sm" id="btnPrevMes">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15,18 9,12 15,6"/></svg>
                        </button>
                        <h3 class="card-title" style="font-size:17px;" id="calTitle"><?php echo $nombresMes[$mes].' '.$anio; ?></h3>
                        <button type="button" id="btnNextMes" class="btn btn-outline btn-icon btn-sm" id="btnNextMes">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9,18 15,12 9,6"/></svg>
                        </button>
                    </div>
                    <div style="display:flex;gap:10px;font-size:11px;align-items:center;flex-wrap:wrap;">
                        <span style="display:inline-flex;align-items:center;gap:4px;"><span style="width:10px;height:10px;border-radius:2px;background:var(--info-light);"></span>Llamada</span>
                        <span style="display:inline-flex;align-items:center;gap:4px;"><span style="width:10px;height:10px;border-radius:2px;background:#fef3c7;"></span>Video</span>
                        <span style="display:inline-flex;align-items:center;gap:4px;"><span style="width:10px;height:10px;border-radius:2px;background:#dcfce7;"></span>Presencial</span>
                    </div>
                </div>

                <div class="cal-grid" id="calGrid">
                    <?php foreach(['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'] as $d): ?>
                    <div class="cal-weekday"><?php echo $d; ?></div>
                    <?php endforeach; ?>

                    <?php for ($i=0;$i<$diaSemana;$i++): ?>
                    <div class="cal-day other-month"></div>
                    <?php endfor; ?>

                    <?php
                    $dHoy=(int)$hoy->format('j');$mHoy=(int)$hoy->format('n');$aHoy=(int)$hoy->format('Y');
                    for ($dia=1;$dia<=$diasEnMes;$dia++):
                        $esHoy=($dia===$dHoy&&$mes===$mHoy&&$anio===$aHoy);
                        $citasDia=$citasPorDia[$dia]??[];
                    ?>
                    <div class="cal-day <?php echo $esHoy?'today':''; ?>" data-action="abrirDia" data-dia="<?php echo $dia; ?>">
                        <div class="cal-day-num"><?php echo $dia; ?></div>
                        <?php foreach(array_slice($citasDia,0,2) as $c): ?>
                        <div class="cal-event <?php echo $c['estado']==='programada'?htmlspecialchars($c['tipo']):htmlspecialchars($c['estado']); ?>"
                             data-action="verCita" data-id="<?php echo $c['id']; ?>"
                             title="<?php echo htmlspecialchars(substr($c['hora'],0,5).' - '.$c['cliente_nombre']); ?>">
                            <?php echo htmlspecialchars(substr($c['hora'],0,5).' '.$c['cliente_nombre']); ?>
                        </div>
                        <?php endforeach; ?>
                        <?php if(count($citasDia)>2): ?>
                        <div class="cal-more" data-action="abrirDia" data-stop="1" data-dia="<?php echo $dia; ?>">+<?php echo count($citasDia)-2; ?> más</div>
                        <?php endif; ?>
                    </div>
                    <?php endfor; ?>

                    <?php $relleno=(7-(($diaSemana+$diasEnMes)%7))%7;
                    for ($i=0;$i<$relleno;$i++): ?>
                    <div class="cal-day other-month"></div>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- Próximas citas -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Próximas Citas</h3>
                    <span class="badge badge-info"><?php echo count($proximas); ?></span>
                </div>
                <div class="card-body" style="padding:0 20px;">
                    <?php if (empty($proximas)): ?>
                    <div class="empty-state" style="padding:28px 0;">
                        <div class="empty-state-icon" style="font-size:20px;"><?= icono('calendar-event') ?></div>
                        <p class="empty-state-description" style="font-size:13px;">Sin citas próximas</p>
                    </div>
                    <?php else:
                    $tipoIcon=['llamada'=>'telephone','videollamada'=>'camera-video','presencial'=>'person-check'];
                    foreach ($proximas as $p):
                        $fd=new DateTime($p['fecha']);$dN=(int)$fd->format('j');$mN=(int)$fd->format('n');
                    ?>
                    <div class="upcoming-item">
                        <div class="upcoming-date">
                            <div class="upcoming-date-day"><?php echo $dN; ?></div>
                            <div class="upcoming-date-mes"><?php echo $mesesCortos[$mN]; ?></div>
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div style="font-weight:600;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                <?php echo htmlspecialchars($p['cliente_nombre']); ?>
                            </div>
                            <div style="font-size:12px;color:var(--text-secondary);">
                                <strong style="color:var(--accent-color);"><?php echo substr($p['hora'],0,5); ?></strong>
                                &nbsp;<?php echo isset($tipoIcon[$p['tipo']]) ? icono($tipoIcon[$p['tipo']]) : ''; ?> <?php echo ucfirst($p['tipo']); ?>
                            </div>
                            <?php if (!empty($p['cliente_telefono'])): ?>
                            <div style="font-size:11px;color:var(--text-muted);"><?= icono('telephone') ?> <?php echo htmlspecialchars($p['cliente_telefono']); ?></div>
                            <?php endif; ?>
                        </div>
                        <button class="btn btn-ghost btn-icon btn-sm" data-action="verCita" data-id="<?php echo $p['id']; ?>">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Modal: Ver Cita -->
<div class="modal-overlay" id="modalVerCita">
    <div class="modal">
        <div class="modal-header"><h3 class="modal-title">Detalle de Cita</h3><button class="modal-close" data-close="modalVerCita">&times;</button></div>
        <div class="modal-body" id="modalVerCitaBody"></div>
        <div class="modal-footer" id="modalVerCitaFooter"></div>
    </div>
</div>

<!-- Modal: Día -->
<div class="modal-overlay" id="modalDia">
    <div class="modal">
        <div class="modal-header"><h3 class="modal-title" id="modalDiaTitulo">Citas del día</h3><button class="modal-close" data-close="modalDia">&times;</button></div>
        <div class="modal-body" id="modalDiaBody"></div>
        <div class="modal-footer">
            <button class="btn btn-outline" data-close="modalDia">Cerrar</button>
            <button class="btn btn-primary" data-action="dia-nueva-cita">Nueva Cita</button>
        </div>
    </div>
</div>

<!-- Modal: Nueva Cita -->
<div class="modal-overlay" id="modalNuevaCita">
    <div class="modal modal-lg">
        <div class="modal-header"><h3 class="modal-title">Nueva Cita</h3><button class="modal-close" data-close="modalNuevaCita">&times;</button></div>
        <div class="modal-body">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label required">Cliente</label>
                    <select class="form-select" id="ncCliente">
                        <option value="">Selecciona...</option>
                        <?php foreach ($clientesList as $cl): ?>
                        <option value="<?php echo $cl['id']; ?>"><?php echo htmlspecialchars($cl['nombre'].' '.$cl['apellido_paterno'].' '.$cl['apellido_materno']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label required">Tipo</label>
                    <select class="form-select" id="ncTipo">
                        <option value="llamada">Llamada</option>
                        <option value="videollamada">Videollamada</option>
                        <option value="presencial">Presencial</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label required">Fecha</label><input type="date" class="form-input" id="ncFecha"></div>
                <div class="form-group"><label class="form-label required">Hora</label><input type="time" class="form-input" id="ncHora" value="10:00"></div>
            </div>
            <div class="form-group" id="meetGroup" style="display:none;">
                <label class="form-label" style="display:flex;align-items:center;gap:8px;">
                    Link de Google Meet
                    <a href="https://meet.google.com/new" target="_blank" class="btn btn-sm btn-ghost" style="margin-left:auto;font-size:11px;"><?= icono('link-45deg') ?> Crear Meet</a>
                </label>
                <input type="text" class="form-input" id="ncMeetLink" placeholder="Pega aquí el link de Google Meet">
            </div>
            <div class="form-group"><label class="form-label">Comentarios</label><textarea class="form-textarea" id="ncComentarios" placeholder="Objetivo de la cita..."></textarea></div>

            <!-- Teléfono de contacto rápido (opcional) -->
            <div class="form-group">
                <label class="form-label">Teléfono de contacto
                    <span style="font-size:10px;color:var(--text-muted);font-weight:400;">(opcional · solo para esta cita)</span>
                </label>
                <input type="tel" class="form-input" id="ncTelefono"
                       maxlength="15" pattern="[\+\d\s\-]{7,15}"
                       placeholder="+52 (123) 456-7890"
                       id="ncTelefono">
                <div style="font-size:10px;color:var(--text-muted);margin-top:3px;">Máximo 15 caracteres</div>
            </div>

            <!-- Campos extra si el ramo es Autos -->
            <?php if (($ramoFiltro ?? $ramo) === 'autos'): ?>
            <div id="grupoAutos" style="border-top:1px solid var(--border-color);padding-top:14px;margin-top:4px;">
                <div style="font-size:11px;font-weight:700;color:var(--accent-color);text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px;"><?= icono('car-front') ?> Datos del vehículo</div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Tipo de auto</label>
                        <select class="form-select" id="ncTipoAuto">
                            <option value="">— Selecciona —</option>
                            <option>Auto</option><option>SUV / Camioneta</option><option>Pickup</option>
                            <option>Moto</option><option>Deportivo</option><option>Eléctrico</option><option>Van / Minivan</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Marca</label>
                        <select class="form-select" id="ncMarcaAuto">
                            <option value="">— Selecciona —</option>
                            <?php
                            $marcas = ['Acura','Audi','BMW','Buick','Cadillac','Chevrolet','Chrysler','Dodge',
                                       'Ferrari','Fiat','Ford','GMC','Honda','Hyundai','Infiniti','Jeep',
                                       'Kia','KTM','Lamborghini','Land Rover','Lexus','Lincoln','Mazda',
                                       'Mercedes-Benz','Mitsubishi','Nissan','Peugeot','Porsche','Ram',
                                       'Renault','SEAT','Subaru','Suzuki','Tesla','Toyota','Volkswagen',
                                       'Volvo','Otra'];
                            foreach ($marcas as $m): ?>
                            <option><?php echo $m; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Estado del auto</label>
                        <select class="form-select" id="ncEstadoAuto">
                            <option value="">— Selecciona —</option>
                            <option value="nuevo">Nuevo</option>
                            <option value="seminuevo">Seminuevo</option>
                            <option value="usado">Usado</option>
                        </select>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Ramo activo en sesión — se envía siempre en el POST -->
            <input type="hidden" id="ncRamo" value="<?php echo htmlspecialchars((string) ($ramoFiltro ?? $ramo)); ?>">
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" data-close="modalNuevaCita">Cancelar</button>
            <button class="btn btn-primary" id="btnGuardarCita">Guardar Cita</button>
        </div>
    </div>
</div>

<div class="toast-container" id="toastContainer"></div>
<script nonce="<?= $cspNonce ?>">
// ── FIX #1: "let" en lugar de "const" para permitir reasignación al navegar meses ──
let citasData  = <?php echo $citasJSON; ?>;
const mesActual  = <?php echo $mes; ?>;
const anioActual = <?php echo $anio; ?>;
const MESES_NOMBRES = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

function openModal(id){const m=document.getElementById(id);if(m){m.classList.add('active');document.body.style.overflow='hidden';}}
function closeModal(id){const m=document.getElementById(id);if(m){m.classList.remove('active');document.body.style.overflow='';}}
document.addEventListener('click',e=>{if(e.target.classList.contains('modal-overlay')){e.target.classList.remove('active');document.body.style.overflow='';}});
document.addEventListener('keydown',e=>{if(e.key==='Escape'){const a=document.querySelector('.modal-overlay.active');if(a){a.classList.remove('active');document.body.style.overflow='';}}});

function verCita(id){
    const c=citasData.find(x=>parseInt(x.id)===id);if(!c)return;
    const badgeClass={programada:'badge-info',completada:'badge-success',cancelada:'badge-danger'};
    const tipoLabel={llamada:iconoSvg('telephone')+' Llamada',videollamada:iconoSvg('camera-video')+' Videollamada',presencial:iconoSvg('person-check')+' Presencial'};
    const fecha=new Date(c.fecha+'T12:00:00').toLocaleDateString('es-MX',{weekday:'long',year:'numeric',month:'long',day:'numeric'});
    const iniciales=esc(c.cliente_nombre).split(' ').slice(0,2).map(p=>p[0]).join('').toUpperCase();
    document.getElementById('modalVerCitaBody').innerHTML=`
        <div style="display:flex;flex-direction:column;gap:14px;">
            <div style="background:linear-gradient(135deg,var(--primary-light),var(--primary-color));border-radius:var(--border-radius);padding:18px;color:white;display:flex;align-items:center;gap:14px;">
                <div style="width:48px;height:48px;background:rgba(255,255,255,.2);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:17px;font-weight:700;">${iniciales}</div>
                <div style="flex:1;"><div style="font-size:16px;font-weight:700;">${esc(c.cliente_nombre)}</div><div style="opacity:.85;font-size:13px;">${tipoLabel[c.tipo]||c.tipo}</div></div>
                <span class="badge ${badgeClass[c.estado]||'badge-neutral'}" style="background:rgba(255,255,255,.2);color:white;">${ucfirst(c.estado)}</span>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div><div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;">Fecha</div><div style="font-weight:500;text-transform:capitalize;">${fecha}</div></div>
                <div><div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;">Hora</div><div style="font-size:20px;font-weight:700;color:var(--accent-color);">${esc(c.hora).substring(0,5)}</div></div>
                ${c.cliente_telefono?`<div><div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;">Teléfono</div><div>${esc(c.cliente_telefono)}</div></div>`:''}
            </div>
            ${c.comentarios?`<div style="background:#f8fafc;border-left:3px solid var(--accent-color);border-radius:var(--border-radius-sm);padding:14px;"><div style="font-size:11px;color:var(--text-secondary);font-weight:600;text-transform:uppercase;margin-bottom:5px;">Agenda</div><div>${esc(c.comentarios)}</div></div>`:''}
            ${c.meet_link?`<a href="${esc(c.meet_link)}" target="_blank" style="display:flex;align-items:center;gap:10px;padding:14px 16px;background:linear-gradient(135deg,#1a73e8,#0d47a1);color:#fff;border-radius:var(--border-radius-sm);text-decoration:none;font-weight:600;">${iconoSvg('camera-video')} <span>Unirse a Google Meet</span></a>`:''}
        </div>`;
    const footer=document.getElementById('modalVerCitaFooter');
    footer.innerHTML=c.estado==='programada'
        ?`<button class="btn btn-danger btn-sm" data-action="cancelar" data-id="${id}">Cancelar cita</button><button class="btn btn-outline" data-close="modalVerCita">Cerrar</button><button class="btn btn-success" data-action="completar" data-id="${id}">✓ Completada</button>`
        :`<button class="btn btn-outline" data-close="modalVerCita">Cerrar</button>`;
    openModal('modalVerCita');
}

// ── FIX #2: abrirDia usa _calMes/_calAnio cuando existen (navegación AJAX),
//            y cae a mesActual/anioActual como fallback en la carga inicial ──
function abrirDia(dia){
    const mes  = (typeof _calMes  !== 'undefined') ? _calMes  : mesActual;
    const anio = (typeof _calAnio !== 'undefined') ? _calAnio : anioActual;
    const fechaStr=`${anio}-${String(mes).padStart(2,'0')}-${String(dia).padStart(2,'0')}`;
    const citas=citasData.filter(c=>c.fecha===fechaStr);
    document.getElementById('modalDiaTitulo').textContent=`${dia} de ${MESES_NOMBRES[mes-1]} ${anio}`;
    const badgeClass={programada:'badge-info',completada:'badge-success',cancelada:'badge-danger'};
    document.getElementById('modalDiaBody').innerHTML=citas.length===0
        ?`<div class="empty-state" style="padding:28px 0;"><div class="empty-state-icon" style="font-size:20px;">${iconoSvg('calendar-event')}</div><p class="empty-state-description">Sin citas este día.</p></div>`
        :citas.map(c=>`<div style="display:flex;align-items:center;gap:10px;padding:11px 0;border-bottom:1px solid var(--border-color);">
            <div style="min-width:46px;font-weight:700;color:var(--accent-color);font-size:15px;">${esc(c.hora).substring(0,5)}</div>
            <div style="flex:1;"><div style="font-weight:600;font-size:13px;">${esc(c.cliente_nombre)}</div><div style="font-size:12px;color:var(--text-secondary);">${ucfirst(c.tipo)}</div></div>
            <span class="badge ${badgeClass[c.estado]||'badge-neutral'}">${ucfirst(c.estado)}</span>
            <button class="btn btn-ghost btn-icon btn-sm" data-action="dia-ver" data-id="${parseInt(c.id)}" title="Ver cita" aria-label="Ver cita">${iconoSvg('eye')}</button>
        </div>`).join('');
    openModal('modalDia');
}

function cambiarEstado(citaId,nuevoEstado){
    const labels={cancelada:'cancelar',completada:'marcar como completada'};
    if(!confirm(`¿Deseas ${labels[nuevoEstado]} esta cita?`))return;
    const fd=new FormData();fd.append('accion','estado');fd.append('cita_id',citaId);fd.append('estado',nuevoEstado);
    fetch('/citas',{method:'POST',body:fd}).then(r=>r.json()).then(data=>{
        if(data.ok){const c=citasData.find(x=>parseInt(x.id)===citaId);if(c)c.estado=nuevoEstado;showToast('Estado actualizado','success');closeModal('modalVerCita');}
        else showToast(data.msg||'Error','error');
    });
}

function guardarCita(){
    const clienteId=document.getElementById('ncCliente').value;
    const tipo=document.getElementById('ncTipo').value;
    const fecha=document.getElementById('ncFecha').value;
    const hora=document.getElementById('ncHora').value;
    const comentarios=document.getElementById('ncComentarios').value;
    const ramo=document.getElementById('ncRamo')?.value||'autos';
    if(!clienteId){showToast('Selecciona un cliente','error');return;}
    if(!fecha||!hora){showToast('Indica la fecha y la hora','error');return;}
    if(new Date(fecha+'T'+hora)<new Date()){showToast('La fecha no puede ser en el pasado','error');return;}
    const btn=document.getElementById('btnGuardarCita');
    btn.disabled=true;btn.textContent='Guardando...';
    const fd=new FormData();
    const meetLink=document.getElementById('ncMeetLink')?.value||'';
    fd.append('accion','crear');
    fd.append('cliente_id',clienteId);
    fd.append('tipo',tipo);
    fd.append('fecha',fecha);
    fd.append('hora',hora);
    fd.append('comentarios',comentarios);
    fd.append('ramo',ramo);
    if(meetLink) fd.append('meet_link',meetLink);
    // Teléfono de contacto rápido
    const tel=document.getElementById('ncTelefono')?.value?.trim();
    if(tel) fd.append('telefono_cliente',tel);
    // Campos extra de autos
    const tipoAuto=document.getElementById('ncTipoAuto')?.value;
    const marcaAuto=document.getElementById('ncMarcaAuto')?.value;
    const estadoAuto=document.getElementById('ncEstadoAuto')?.value;
    if(tipoAuto)  fd.append('tipo_auto',tipoAuto);
    if(marcaAuto) fd.append('marca_auto',marcaAuto);
    if(estadoAuto)fd.append('estado_auto',estadoAuto);
    fetch('/citas',{method:'POST',body:fd}).then(r=>r.json()).then(data=>{
        btn.disabled=false;btn.textContent='Guardar Cita';
        if(data.ok){
            showToast('Cita guardada correctamente','success');
            closeModal('modalNuevaCita');
            setTimeout(()=>{
                const m=(typeof _calMes!=='undefined')?_calMes:mesActual;
                const a=(typeof _calAnio!=='undefined')?_calAnio:anioActual;
                location.href='/citas?mes='+m+'&anio='+a;
            },1200);
        } else {
            showToast(data.msg||'Error al guardar','error');
        }
    }).catch(()=>{
        btn.disabled=false;
        btn.textContent='Guardar Cita';
        showToast('Error de conexión','error');
    });
}

function onTipoChange(tipo){const g=document.getElementById('meetGroup');if(g)g.style.display=tipo==='videollamada'?'':'none';}

function showToast(msg,type='success'){
    const c=document.getElementById('toastContainer');if(!c)return;
    const t=document.createElement('div');t.className=`toast ${type}`;
    t.innerHTML=`<div class="toast-icon">${type==='error'?'✗':'✓'}</div><div class="toast-message">${msg}</div><button class="toast-close" data-action="toast-close">&times;</button>`;
    c.appendChild(t);setTimeout(()=>{t.classList.add('hiding');setTimeout(()=>t.remove(),300);},5000);
}
function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function ucfirst(s){return s?s.charAt(0).toUpperCase()+s.slice(1):'';}

document.addEventListener('DOMContentLoaded',()=>{
    const today=new Date().toISOString().split('T')[0];
    document.getElementById('ncFecha').min=today;document.getElementById('ncFecha').value=today;
});
</script>

<script nonce="<?= $cspNonce ?>">
// ── Navegación de mes sin recarga de página ───────────────────────────────────
var _calMes    = <?php echo $mes; ?>;
var _calAnio   = <?php echo $anio; ?>;
var _calLoading = false;

var _MESES_LARGOS  = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio',
                       'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
var _MESES_CORTOS  = ['','Ene','Feb','Mar','Abr','May','Jun',
                       'Jul','Ago','Sep','Oct','Nov','Dic'];
var _DIAS_SEMANA   = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];

function navMes(dir) {
    if (_calLoading) return;
    _calMes += dir;
    if (_calMes < 1)  { _calMes = 12; _calAnio--; }
    if (_calMes > 12) { _calMes = 1;  _calAnio++; }
    _calLoading = true;

    var titleLabel = _MESES_LARGOS[_calMes] + ' ' + _calAnio;
    document.getElementById('calTitle').textContent    = titleLabel;
    document.getElementById('pageSubtitle').textContent = titleLabel;
    document.getElementById('calGrid').style.opacity   = '0.4';

    fetch('/citas?mes=' + _calMes + '&anio=' + _calAnio + '&_ajax=1')
        .then(function(r){ return r.json(); })
        .then(function(data) {
            // ── FIX #1 aplicado: citasData es "let", esta asignación ya no explota ──
            citasData = data.citas;
            renderCalGrid(data.diasEnMes, data.primerDia, data.hoy);
            renderProximas(data.proximas);
            renderStats(data.stats);
            document.getElementById('calGrid').style.opacity = '1';
            _calLoading = false;
            history.replaceState({}, '', '/citas?mes=' + _calMes + '&anio=' + _calAnio);
        })
        .catch(function(e) {
            console.error('navMes error:', e);
            _calLoading = false;
            document.getElementById('calGrid').style.opacity = '1';
        });
}

function renderCalGrid(diasEnMes, primerDia, hoy) {
    var grid = document.getElementById('calGrid');
    var html = '';
    _DIAS_SEMANA.forEach(function(d) {
        html += '<div class="cal-weekday">' + d + '</div>';
    });

    for (var i = 0; i < primerDia; i++) {
        html += '<div class="cal-day other-month"></div>';
    }

    var hoyParts = hoy.split('-');
    var dHoy = parseInt(hoyParts[2]), mHoy = parseInt(hoyParts[1]), aHoy = parseInt(hoyParts[0]);

    var citasPorDia = {};
    (citasData || []).forEach(function(c) {
        var d = parseInt(c.fecha.split('-')[2]);
        if (!citasPorDia[d]) citasPorDia[d] = [];
        citasPorDia[d].push(c);
    });

    var tipoClase = { llamada:'llamada', videollamada:'videollamada', presencial:'presencial' };

    for (var dia = 1; dia <= diasEnMes; dia++) {
        var esHoy = (dia === dHoy && _calMes === mHoy && _calAnio === aHoy);
        html += '<div class="cal-day' + (esHoy ? ' today' : '') + '" data-action="abrirDia" data-dia="' + dia + '">';
        html += '<div class="cal-day-num">' + dia + '</div>';
        var citasDia = citasPorDia[dia] || [];
        var visible = citasDia.slice(0, 2);
        visible.forEach(function(c) {
            var cls = c.estado === 'programada' ? (tipoClase[c.tipo] || 'llamada') : c.estado;
            var hora = c.hora.substring(0,5);
            html += '<div class="cal-event ' + cls + '" data-action="verCita" data-id="' + c.id + '" title="' + hora + ' - ' + esc(c.cliente_nombre) + '">' + hora + ' ' + esc(c.cliente_nombre) + '</div>';
        });
        if (citasDia.length > 2) {
            html += '<div class="cal-more" data-action="abrirDia" data-stop="1" data-dia="' + dia + '">+' + (citasDia.length-2) + ' más</div>';
        }
        html += '</div>';
    }

    var total = primerDia + diasEnMes;
    var relleno = (7 - (total % 7)) % 7;
    for (var j = 0; j < relleno; j++) {
        html += '<div class="cal-day other-month"></div>';
    }

    grid.innerHTML = html;
}

function renderStats(stats) {
    var cards = document.querySelectorAll('.stat-card');
    if (!stats || !cards.length) return;
    var vals = [stats.total, stats.programadas, stats.completadas, stats.canceladas];
    cards.forEach(function(card, i) {
        var valEl = card.querySelector('.stat-card-value');
        if (valEl && vals[i] !== undefined) valEl.textContent = vals[i];
    });
}

function renderProximas(proximas) {
    var container = document.querySelector('.upcoming-item')?.closest('.card-body');
    if (!container) return;
    if (!proximas || !proximas.length) {
        container.innerHTML = '<div class="empty-state" style="padding:28px 0;"><div class="empty-state-icon" style="font-size:20px;">' + iconoSvg('calendar-event') + '</div><p class="empty-state-description" style="font-size:13px;">Sin citas próximas</p></div>';
        return;
    }
    var TIPO_ICON = { llamada:iconoSvg('telephone'), videollamada:iconoSvg('camera-video'), presencial:iconoSvg('person-check') };
    var html = '';
    proximas.forEach(function(p) {
        var parts = p.fecha.split('-');
        var dN = parseInt(parts[2]), mN = parseInt(parts[1]);
        html += '<div class="upcoming-item">' +
            '<div class="upcoming-date"><div class="upcoming-date-day">' + dN + '</div><div class="upcoming-date-mes">' + _MESES_CORTOS[mN] + '</div></div>' +
            '<div style="flex:1;min-width:0;"><div style="font-weight:600;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + esc(p.cliente_nombre) + '</div>' +
            '<div style="font-size:12px;color:var(--text-secondary);"><strong style="color:var(--accent-color);">' + p.hora.substring(0,5) + '</strong>&nbsp;' + (TIPO_ICON[p.tipo]||'') + ' ' + p.tipo + '</div>' +
            (p.cliente_telefono ? '<div style="font-size:11px;color:var(--text-muted);">' + iconoSvg('telephone') + ' ' + esc(p.cliente_telefono) + '</div>' : '') +
            '</div><button class="btn btn-ghost btn-icon btn-sm" data-action="verCita" data-id="' + p.id + '"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button></div>';
    });
    container.innerHTML = html;
}

// FIX #2: Se eliminó el bloque "window.abrirDia" que hacía el parche incompleto.
// La función abrirDia() definida arriba ya lee _calMes/_calAnio directamente.

function esc(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>


<script src="/js/crm-events.js" nonce="<?= $cspNonce ?>"></script>
<script src="/js/modales.js"    nonce="<?= $cspNonce ?>"></script>
</body>
</html>