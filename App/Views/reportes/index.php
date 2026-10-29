<?php
declare(strict_types=1);

use App\Security\SessionManager;
use App\Models\{ClientModel, CotizacionModel, CitaModel, HistorialModel};

SessionManager::requireAuth('/login');

$agenteId        = SessionManager::getAgentId();
$agente          = SessionManager::getAgentData();
$clienteModel    = new ClientModel();
$cotizacionModel = new CotizacionModel();
$citaModel       = new CitaModel();
$historialModel  = new HistorialModel();

// ── Filtro de ramo (GET param ?ramo=autos|vida|gastos_medicos|empresas|todos) ─
$ramosDisponibles = ['todos','autos','vida','gastos_medicos','empresas'];
$ramoFiltro = in_array($_GET['ramo'] ?? '', $ramosDisponibles, true)
    ? $_GET['ramo']
    : 'todos';
$ramoParam = $ramoFiltro === 'todos' ? null : $ramoFiltro;

$kpis = $cotizacionModel->getKpis($agenteId, $ramoParam);
$kpis['clientes_total']    = $clienteModel->countTotal($agenteId, $ramoParam);
$kpis['clientes_mes']      = $clienteModel->countEsteMes($agenteId, $ramoParam);
$kpis['citas_completadas'] = $citaModel->countCompletadas($agenteId);
$kpis['citas_pendientes']  = $citaModel->countPendientes($agenteId);

$evolucion        = $cotizacionModel->getEvolucionMensual($agenteId, $ramoParam);
$rankingCompanias = $cotizacionModel->getRankingCompanias($agenteId, $ramoParam);
$porCobertura     = $cotizacionModel->getPorCobertura($agenteId, $ramoParam);
$porVehiculo      = $clienteModel->getTopVehiculos($agenteId);
$topClientes      = $cotizacionModel->getTopClientes($agenteId, $ramoParam);
$actividad        = $historialModel->getRecentByAgenteId($agenteId);

$iconosAct = ['registro'=>'person','cotizacion'=>'clipboard','venta'=>'check-circle-fill','cita'=>'calendar-event','llamada'=>'telephone','nota'=>'pencil-square','cambio_estado'=>'arrow-repeat'];
$actividad = array_map(fn($a) => array_merge($a, ['icono' => isset($iconosAct[$a['tipo']]) ? icono($iconosAct[$a['tipo']]) : '•']), $actividad);

$ramoLabels = [
    'todos'          => ['label'=>'Todos los ramos',        'icon'=>'bar-chart',  'color'=>'#64748b'],
    'autos'          => ['label'=>'Autos',                  'icon'=>'car-front',  'color'=>'#0ea5e9'],
    'vida'           => ['label'=>'Vida',                   'icon'=>'heart-fill', 'color'=>'#c9a227'],
    'gastos_medicos' => ['label'=>'Gastos Médicos Mayores', 'icon'=>'hospital',   'color'=>'#10b981'],
    'empresas'       => ['label'=>'Empresas',               'icon'=>'building',   'color'=>'#8b5cf6'],
];
$ramoActualInfo = $ramoLabels[$ramoFiltro];

$pageTitle = 'Reportes';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php require __DIR__ . '/../partials/_head.php'; ?>
    <style>
        .kpi-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:24px;}
        .kpi-card{background:var(--bg-card);border:1px solid var(--border-color);border-radius:var(--border-radius);padding:20px;}
        .kpi-label{font-size:11px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;}
        .kpi-value{font-size:28px;font-weight:700;color:var(--text-primary);line-height:1.1;}
        .kpi-delta{font-size:12px;margin-top:4px;}
        .kpi-delta.up{color:var(--success-color);}
        .kpi-delta.neutral{color:var(--text-secondary);}
        .conversion-bar{margin-top:8px;background:var(--border-color);border-radius:99px;height:8px;overflow:hidden;}
        .conversion-fill{height:100%;background:linear-gradient(90deg,var(--accent-color),var(--success-color));border-radius:99px;}
        .report-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px;}
        @media(max-width:900px){.report-grid-2{grid-template-columns:1fr;}}
        .chart-container{height:280px;position:relative;width:100%;}
        .rank-item{display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid var(--border-color);}
        .rank-item:last-child{border-bottom:none;}
        .rank-pos{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0;}
        .rank-pos.gold{background:#fef3c7;color:#92400e;}.rank-pos.silver{background:#f1f5f9;color:#475569;}.rank-pos.bronze{background:#fef0e7;color:#7c3a00;}.rank-pos.other{background:var(--bg-secondary);color:var(--text-secondary);}
        .rank-info{flex:1;min-width:0;}
        .rank-name{font-weight:600;font-size:14px;}.rank-sub{font-size:11px;color:var(--text-secondary);}
        .rank-bar-bg{background:var(--border-color);border-radius:99px;height:6px;}
        .rank-bar-fill{height:100%;border-radius:99px;background:linear-gradient(90deg,var(--accent-color),var(--success-color));}
        .top-client{display:flex;align-items:center;gap:12px;padding:11px 0;border-bottom:1px solid var(--border-color);}
        .top-client:last-child{border-bottom:none;}
        .top-client-avatar{width:38px;height:38px;background:var(--info-light);border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;color:var(--accent-color);flex-shrink:0;}
        .timeline-item{display:flex;gap:12px;padding:10px 0;border-bottom:1px solid var(--border-color);}
        .timeline-item:last-child{border-bottom:none;}
        .timeline-marker{width:32px;height:32px;border-radius:50%;background:var(--bg-secondary);display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0;}

        /* ── Tabs de ramo ─────────────────────────────────────────────────── */
        .ramo-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px;}
        .ramo-tab{display:inline-flex;align-items:center;gap:6px;padding:8px 18px;border-radius:99px;border:1.5px solid var(--border-color);background:var(--bg-card);color:var(--text-secondary);font-size:13px;font-weight:600;text-decoration:none;transition:all .15s;white-space:nowrap;}
        .ramo-tab:hover{border-color:var(--accent-color);color:var(--accent-color);background:var(--info-light);}
        .ramo-tab.active{color:#fff;border-color:transparent;}
        .ramo-banner{display:flex;align-items:center;gap:10px;padding:10px 16px;border-radius:10px;margin-bottom:20px;font-size:13px;font-weight:600;}
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
                                <line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>
                            </svg>
                        </span>
                        Reportes y Estadísticas
                    </h1>
                    <p class="page-subtitle">Métricas de rendimiento — <?php echo htmlspecialchars(trim($agente['nombre'].' '.$agente['apellido'])); ?></p>
                </div>
            </div>
        </header>

        <!-- ── Selector de ramo ─────────────────────────────────────────────── -->
        <div class="ramo-tabs">
            <?php foreach ($ramoLabels as $slug => $info): ?>
            <a href="?ramo=<?php echo $slug; ?>"
               class="ramo-tab <?php echo $ramoFiltro === $slug ? 'active' : ''; ?>"
               style="<?php echo $ramoFiltro === $slug ? 'background:'.$info['color'].';' : ''; ?>">
                <?php echo icono($info['icon']); ?> <?php echo $info['label']; ?>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Banner ramo activo -->
        <?php if ($ramoFiltro !== 'todos'): ?>
        <div class="ramo-banner"
             style="background:<?php echo $ramoActualInfo['color']; ?>15;border:1.5px solid <?php echo $ramoActualInfo['color']; ?>40;">
            <span style="font-size:20px;"><?php echo icono($ramoActualInfo['icon']); ?></span>
            <span style="color:<?php echo $ramoActualInfo['color']; ?>;">
                Mostrando estadísticas de <strong><?php echo $ramoActualInfo['label']; ?></strong>
            </span>
            <a href="?ramo=todos"
               style="margin-left:auto;font-size:12px;color:var(--text-muted);text-decoration:none;background:var(--bg-secondary);padding:3px 10px;border-radius:99px;">
                ✕ Ver todos
            </a>
        </div>
        <?php endif; ?>

        <!-- KPIs -->
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-label">Clientes Totales</div>
                <div class="kpi-value"><?php echo number_format($kpis['clientes_total']); ?></div>
                <div class="kpi-delta up">▲ <?php echo $kpis['clientes_mes']; ?> este mes</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Ventas Cerradas</div>
                <div class="kpi-value"><?php echo $kpis['ventas_total']; ?></div>
                <div class="kpi-delta up">▲ <?php echo $kpis['ventas_mes']; ?> este mes</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Cotizaciones</div>
                <div class="kpi-value"><?php echo $kpis['cotizaciones_total']; ?></div>
                <div class="kpi-delta neutral">↔ <?php echo $kpis['cotizaciones_mes']; ?> este mes</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Ingreso Total</div>
                <div class="kpi-value">$<?php echo number_format($kpis['ingreso_total']); ?></div>
                <div class="kpi-delta up">▲ $<?php echo number_format($kpis['ingreso_mes']); ?> este mes</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Ticket Promedio</div>
                <div class="kpi-value">$<?php echo number_format($kpis['ticket_promedio']); ?></div>
                <div class="kpi-delta neutral">Por venta cerrada</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Tasa de Conversión</div>
                <div class="kpi-value"><?php echo $kpis['tasa_conversion']; ?>%</div>
                <div class="conversion-bar"><div class="conversion-fill" style="width:<?php echo min(100, $kpis['tasa_conversion']); ?>%"></div></div>
            </div>
        </div>

        <!-- Evolución + Vehículos -->
        <div class="report-grid-2">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Evolución Mensual</h3></div>
                <div class="card-body"><div class="chart-container"><canvas id="chartEvolucion"></canvas></div></div>
            </div>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <?php echo $ramoFiltro === 'autos' ? 'Tipos de Vehículo' : 'Distribución de Clientes'; ?>
                    </h3>
                </div>
                <div class="card-body"><div class="chart-container"><canvas id="chartVehiculos"></canvas></div></div>
            </div>
        </div>

        <!-- Ranking + Coberturas -->
        <div class="report-grid-2">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Ranking de Compañías</h3></div>
                <div class="card-body" style="padding-top:8px;">
                    <?php if (empty($rankingCompanias)): ?>
                    <div class="empty-state" style="padding:24px 0;"><p class="empty-state-description" style="font-size:13px;">Sin cotizaciones registradas.</p></div>
                    <?php else:
                    $maxIngreso = max(array_column($rankingCompanias,'ingreso')) ?: 1;
                    $posClases  = ['gold','silver','bronze','other','other','other'];
                    foreach ($rankingCompanias as $i=>$comp):
                        $tasa=$comp['cotizaciones']>0?round($comp['aceptadas']/$comp['cotizaciones']*100):0;
                        $pct=round($comp['ingreso']/$maxIngreso*100);
                    ?>
                    <div class="rank-item">
                        <div class="rank-pos <?php echo $posClases[$i]??'other'; ?>"><?php echo $i+1; ?></div>
                        <div class="rank-info">
                            <div class="rank-name"><?php echo htmlspecialchars($comp['compania']); ?></div>
                            <div class="rank-sub"><?php echo $comp['aceptadas']; ?>/<?php echo $comp['cotizaciones']; ?> ventas · <?php echo $tasa; ?>% conv.</div>
                        </div>
                        <div style="min-width:100px;">
                            <div style="font-size:12px;font-weight:600;color:var(--success-color);text-align:right;margin-bottom:3px;">$<?php echo number_format($comp['ingreso']); ?></div>
                            <div class="rank-bar-bg"><div class="rank-bar-fill" style="width:<?php echo $pct; ?>%"></div></div>
                        </div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><h3 class="card-title">Coberturas Más Vendidas</h3></div>
                <div class="card-body">
                    <div class="chart-container"><canvas id="chartCoberturas"></canvas></div>
                    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:12px;">
                        <?php $cobColors=['#0ea5e9','#10b981','#f59e0b','#8b5cf6'];
                        foreach ($porCobertura as $i=>$cob): ?>
                        <span style="display:inline-flex;align-items:center;gap:5px;font-size:12px;">
                            <span style="width:10px;height:10px;border-radius:2px;background:<?php echo $cobColors[$i%count($cobColors)]; ?>;"></span>
                            <?php echo htmlspecialchars($cob['tipo']); ?> <strong><?php echo $cob['total']; ?></strong>
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Clientes + Actividad -->
        <div class="report-grid-2">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Top 5 Clientes</h3></div>
                <div class="card-body" style="padding-top:8px;">
                    <?php if (empty($topClientes)): ?>
                    <div class="empty-state" style="padding:24px 0;"><p class="empty-state-description" style="font-size:13px;">Aún no hay ventas cerradas.</p></div>
                    <?php else: ?>
                    <?php foreach ($topClientes as $i=>$cl):
                        $partes=explode(' ',$cl['nombre']);
                        $iniciales=strtoupper(substr($partes[0],0,1).substr($partes[1]??'',0,1));
                    ?>
                    <div class="top-client">
                        <div style="font-size:16px;font-weight:700;color:var(--text-muted);min-width:20px;"><?php echo $i+1; ?></div>
                        <div class="top-client-avatar"><?php echo $iniciales; ?></div>
                        <div style="flex:1;">
                            <div style="font-weight:600;font-size:14px;"><?php echo htmlspecialchars($cl['nombre']); ?></div>
                            <div style="font-size:12px;color:var(--text-secondary);"><?php echo $cl['ventas']; ?> venta<?php echo $cl['ventas']!==1?'s':''; ?><?php if($cl['ciudad']): ?> · <?php echo htmlspecialchars($cl['ciudad']); ?><?php endif; ?></div>
                        </div>
                        <div style="font-weight:700;color:var(--success-color);font-size:14px;">$<?php echo number_format($cl['valor']); ?></div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Actividad Reciente</h3>
                    <a href="/dashboard" class="btn btn-sm btn-ghost">Dashboard</a>
                </div>
                <div class="card-body" style="padding-top:4px;">
                    <?php if (empty($actividad)): ?>
                    <div class="empty-state" style="padding:24px 0;"><p class="empty-state-description" style="font-size:13px;">Sin actividad registrada.</p></div>
                    <?php else: ?>
                    <div>
                        <?php foreach ($actividad as $act): ?>
                        <div class="timeline-item">
                            <div class="timeline-marker"><?php echo $act['icono']; ?></div>
                            <div style="flex:1;">
                                <div style="font-size:13px;font-weight:600;"><?php echo htmlspecialchars($act['actividad_desc']); ?></div>
                                <div style="font-size:11px;color:var(--text-muted);margin-top:2px;"><?php echo date('d/m/Y H:i',strtotime($act['fecha'])); ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </main>
</div>

<div class="toast-container" id="toastContainer"></div>
<script src="/js/reportes.js"></script>
<script nonce="<?= SessionManager::nonce() ?>">
const datosEvolucion  = <?php echo json_encode($evolucion); ?>;
const datosVehiculos  = <?php echo json_encode($porVehiculo); ?>;
const datosCoberturas = <?php echo json_encode($porCobertura); ?>;
const datosRanking    = <?php echo json_encode($rankingCompanias); ?>;
document.addEventListener('DOMContentLoaded', function() { initReportesCharts(); });
</script>
</body>
</html>