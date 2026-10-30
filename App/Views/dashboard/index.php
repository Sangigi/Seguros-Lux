<?php
declare(strict_types=1);

use App\Security\SessionManager;
use App\Models\{CatalogoModel, ClientModel, CotizacionModel, CitaModel, HistorialModel};
use Database\Connection;

SessionManager::requireAuth('/login');

// Admins van al panel de administración
if (SessionManager::isAdmin()) {
    header('Location: /admin?tab=estadisticas');
    exit;
}

$agenteId  = SessionManager::getAgentId();
$agente    = SessionManager::getAgentData();
$empresaId = \App\Security\Tenant::requireId();

// ── Ramos (catálogo de la empresa) y filtro de esta página ────────────────
// Las tarjetas de ramo filtran los KPIs y la gráfica de cotizaciones de ESTA
// pantalla con ?ramo=<clave>; sin parámetro se ve todo. No se guarda en la
// sesión, así que no filtra ninguna otra pantalla.
$ramosActivos = (new CatalogoModel())->ramos();
$ramoGet      = $_GET['ramo'] ?? '';
$ramo         = is_string($ramoGet) && isset($ramosActivos[$ramoGet]) ? $ramoGet : null;

$clienteModel    = new ClientModel();
$cotizacionModel = new CotizacionModel();
$citaModel       = new CitaModel();
$historialModel  = new HistorialModel();

// ── KPIs filtrados por ramo ───────────────────────────────────────────────
$estadisticas = [
    'clientes_hoy'          => $clienteModel->countHoy($agenteId),
    'clientes_semana'       => $clienteModel->countEstaSemana($agenteId),
    'cotizaciones_enviadas' => $cotizacionModel->countEnviadas($agenteId, $ramo),
    'ventas_cerradas'       => $cotizacionModel->countAceptadas($agenteId, $ramo),
    'citas_programadas'     => $citaModel->countPendientes($agenteId, $ramo),
];

$clientes_por_dia       = $clienteModel->getClientesPorDia($agenteId);
$cotizaciones_vs_ventas = $cotizacionModel->getCotizacionesVsVentasPorMes($agenteId, $ramo);
$vehiculos_cotizados    = $clienteModel->getTopVehiculos($agenteId);
$estado_clientes        = $clienteModel->getEstadoAsegurado($agenteId);
$actividad_reciente     = $historialModel->getRecentByAgenteId($agenteId);
$conteos_ramo           = $cotizacionModel->contarPorRamo($agenteId);

// ── Números ATC ───────────────────────────────────────────────────────────
$atc_numeros = [];
$db          = Connection::getConnection();
$stmtAtc     = $db->prepare("SELECT clave, valor FROM configuracion WHERE clave LIKE 'atc_%' AND empresa_id = ? ORDER BY clave");
if ($stmtAtc && $stmtAtc->execute([$empresaId])) {
    $atc_raw = array_column($stmtAtc->fetchAll(), 'valor', 'clave');
    for ($i = 1; $i <= 3; $i++) {
        $num = $atc_raw["atc_numero_{$i}"] ?? '';
        $nom = $atc_raw["atc_nombre_{$i}"] ?? '';
        if ($num) $atc_numeros[] = ['numero' => $num, 'nombre' => $nom];
    }
}

// ── Config ramos ──────────────────────────────────────────────────────────
// Qué ramos se muestran y su nombre salen del catálogo; aquí solo queda la
// presentación de cada clave fija (descripción y colores).
$estiloRamo = [
    'autos'          => ['desc' => 'Seguro vehicular',     'color' => '#0ea5e9', 'color_light' => '#e0f2fe'],
    'vida'           => ['desc' => 'Seguro de vida',       'color' => '#10b981', 'color_light' => '#d1fae5'],
    'gastos_medicos' => ['desc' => 'Cobertura médica',     'color' => '#8b5cf6', 'color_light' => '#ede9fe'],
    'empresas'       => ['desc' => 'Seguros corporativos', 'color' => '#f59e0b', 'color_light' => '#fef3c7'],
];
$ramos_info = [];
foreach ($ramosActivos as $clave => $etiqueta) {
    $ramos_info[$clave] = ['icon' => CatalogoModel::ICONOS_RAMO[$clave] ?? 'clipboard', 'nombre' => $etiqueta]
        + ($estiloRamo[$clave] ?? ['desc' => '', 'color' => '#64748b', 'color_light' => '#f1f5f9']);
}
// Sin filtro, el banner habla de todos los ramos (los KPIs no están filtrados).
$ramo_actual = $ramo !== null
    ? $ramos_info[$ramo]
    : ['icon' => 'bar-chart', 'nombre' => 'Todos los ramos', 'desc' => '', 'color' => '#0284c7', 'color_light' => '#e0f2fe'];

// Los atajos del banner llevan el ramo filtrado a la pantalla de destino.
$qRamo = $ramo !== null ? '?' . http_build_query(['ramo' => $ramo]) : '';

// JSON para charts
$clientesPorDiaJSON       = json_encode($clientes_por_dia);
$cotizacionesVsVentasJSON = json_encode($cotizaciones_vs_ventas);
$vehiculosJSON            = json_encode($vehiculos_cotizados);
$estadoClientesJSON       = json_encode($estado_clientes);

$pageTitle = 'Dashboard';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php require __DIR__ . '/../partials/_head.php'; ?>
    <style>
        :root { --success-color:#31e04e; --warning-color:#e0ce31; }
        .ramo-selector{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:28px;}
        .ramo-card{position:relative;display:block;border-radius:var(--border-radius-lg);padding:0;cursor:pointer;overflow:hidden;border:3px solid transparent;transition:all .25s;text-decoration:none;background:none;text-align:center;color:inherit;}
        .ramo-card:hover{transform:translateY(-3px);box-shadow:0 12px 32px rgba(0,0,0,.12);}
        .ramo-card.active{border-color:var(--ramo-color);box-shadow:0 8px 24px rgba(0,0,0,.15);}
        .ramo-card-inner{padding:22px 20px;background:var(--bg-card);}
        .ramo-card.active .ramo-card-inner{background:var(--ramo-light);}
        .ramo-card-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;}
        .ramo-emoji{font-size:32px;line-height:1;color:var(--ramo-color);}
        .ramo-badge{font-size:11px;font-weight:700;padding:3px 8px;border-radius:99px;background:var(--ramo-light);color:var(--ramo-color);}
        .ramo-card.active .ramo-badge{background:var(--ramo-color);color:#fff;}
        .ramo-nombre{font-size:16px;font-weight:700;color:var(--text-primary);margin-bottom:3px;}
        .ramo-desc{font-size:12px;color:var(--text-secondary);}
        .ramo-stats{display:flex;gap:16px;margin-top:14px;padding-top:12px;border-top:1px solid var(--border-color);}
        .ramo-stat-item{text-align:center;flex:1;}
        .ramo-stat-val{font-size:20px;font-weight:700;color:var(--text-primary);}
        .ramo-stat-lbl{font-size:10px;color:var(--text-muted);text-transform:uppercase;}
        .ramo-card.active .ramo-stat-val{color:var(--ramo-color);}
        .ramo-active-indicator{position:absolute;top:0;left:0;right:0;height:4px;background:var(--ramo-color);display:none;}
        .ramo-card.active .ramo-active-indicator{display:block;}
        .ramo-banner{background:linear-gradient(135deg,var(--primary-color),var(--primary-light));border-radius:var(--border-radius-lg);padding:20px 28px;color:#fff;display:flex;align-items:center;gap:16px;margin-bottom:24px;}
        .ramo-banner-emoji{font-size:40px;line-height:1;color:#fff;}
        .ramo-banner-info{flex:1;}
        .ramo-banner-title{font-size:22px;font-weight:700;}
        .ramo-banner-sub{font-size:13px;opacity:.8;margin-top:3px;}
        .ramo-banner-actions{display:flex;gap:10px;}
        .stats-grid{display:grid;gap:16px;margin-bottom:24px;}
        .stat-card{background:var(--bg-card);border:1px solid var(--border-color);border-radius:var(--border-radius);padding:20px;}
        .stat-card.accent{border-left:4px solid var(--accent);}
        .stat-card.success{border-left:4px solid var(--success-color);}
        .stat-card.warning{border-left:4px solid var(--warning-color);}
        .stat-card.info{border-left:4px solid #3b82f6;}
        .stat-card.time{border-left:3px solid var(--text-secondary);}
        .stat-card-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px;}
        .stat-card-icon{width:38px;height:38px;border-radius:8px;background:var(--bg-secondary);display:flex;align-items:center;justify-content:center;}
        .stat-card-value{font-size:28px;font-weight:700;color:var(--text-primary);}
        .stat-card-label{font-size:12px;color:var(--text-secondary);margin-top:2px;}
        .charts-grid{display:grid;grid-template-columns:2fr 1fr;gap:24px;margin-bottom:24px;}
        .chart-container{height:300px;position:relative;}
        .timeline{display:flex;flex-direction:column;gap:0;}
        .timeline-item{display:flex;gap:12px;padding:10px 0;border-bottom:1px solid var(--border-color);}
        .timeline-item:last-child{border-bottom:none;}
        .timeline-marker{width:32px;height:32px;border-radius:50%;background:var(--bg-secondary);display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0;}
        .timeline-title{font-size:13px;font-weight:600;}
        .timeline-meta{font-size:11px;color:var(--text-muted);margin-top:2px;display:flex;gap:8px;}
        @media(max-width:1024px){.ramo-selector{grid-template-columns:repeat(2,1fr);}.charts-grid{grid-template-columns:1fr;}.stats-grid{grid-template-columns:repeat(2,1fr);}}
        @media(max-width:600px){.ramo-selector{grid-template-columns:repeat(2,1fr);}}
    </style>
</head>
<body>
<div class="crm-wrapper">
    <?php require __DIR__ . '/../partials/_navbar.php'; ?>
    <main class="main-content">

        <header class="page-header" style="margin-bottom:24px;">
            <div class="page-header-content">
                <div>
                    <h1 class="page-title">
                        <span class="page-title-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
                                <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
                            </svg>
                        </span>
                        Dashboard
                    </h1>
                    <p class="page-subtitle">
                        Bienvenido, <?php echo htmlspecialchars($agente['nombre'] . ' ' . $agente['apellido']); ?> —
                        <?php echo date('l d \d\e F, Y'); ?>
                    </p>
                </div>
            </div>
        </header>

        <!-- Selector de ramo: filtra esta página (?ramo=<clave>); la tarjeta
             activa vuelve a mostrar todos los ramos. -->
        <div class="ramo-selector">
            <?php foreach ($ramos_info as $ramo_key => $info):
                $es_activo = ($ramo_key === $ramo);
                $total_cot = $conteos_ramo[$ramo_key]['cotizaciones'] ?? 0;
                $total_ven = $conteos_ramo[$ramo_key]['ventas']       ?? 0;
                $href_ramo = $es_activo ? '/dashboard' : '/dashboard?' . http_build_query(['ramo' => $ramo_key]);
            ?>
                <a href="<?php echo htmlspecialchars($href_ramo); ?>"
                    class="ramo-card <?php echo $es_activo ? 'active' : ''; ?>"
                    <?php if ($es_activo): ?>title="Quitar el filtro y ver todos los ramos"<?php endif; ?>
                    style="--ramo-color:<?php echo $info['color']; ?>;--ramo-light:<?php echo $info['color_light']; ?>;">
                    <div class="ramo-active-indicator"></div>
                    <div class="ramo-card-inner">
                        <div class="ramo-card-top">
                            <span class="ramo-emoji"><?= icono($info['icon']) ?></span>
                            <span class="ramo-badge"><?php echo $es_activo ? 'Activo' : 'Cambiar'; ?></span>
                        </div>
                        <div class="ramo-nombre"><?php echo htmlspecialchars($info['nombre']); ?></div>
                        <div class="ramo-desc"><?php echo $info['desc']; ?></div>
                        <div class="ramo-stats">
                            <div class="ramo-stat-item">
                                <div class="ramo-stat-val"><?php echo $total_cot; ?></div>
                                <div class="ramo-stat-lbl">Cotizaciones</div>
                            </div>
                            <div class="ramo-stat-item">
                                <div class="ramo-stat-val"><?php echo $total_ven; ?></div>
                                <div class="ramo-stat-lbl">Ventas</div>
                            </div>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Banner ramo activo -->
        <div class="ramo-banner" style="background:linear-gradient(135deg,<?php echo $ramo_actual['color']; ?>,<?php echo $ramo_actual['color']; ?>cc);">
            <div class="ramo-banner-emoji"><?= icono($ramo_actual['icon']) ?></div>
            <div class="ramo-banner-info">
                <?php if ($ramo !== null): ?>
                <div class="ramo-banner-title">Módulo <?php echo htmlspecialchars($ramo_actual['nombre']); ?></div>
                <div class="ramo-banner-sub">
                    Viendo estadísticas del ramo <?php echo htmlspecialchars(mb_strtolower($ramo_actual['nombre'])); ?> —
                    <?php echo htmlspecialchars($agente['nombre'] . ' ' . $agente['apellido']); ?>
                </div>
                <?php else: ?>
                <div class="ramo-banner-title"><?php echo htmlspecialchars($ramo_actual['nombre']); ?></div>
                <div class="ramo-banner-sub">
                    Viendo estadísticas de todos tus ramos —
                    <?php echo htmlspecialchars($agente['nombre'] . ' ' . $agente['apellido']); ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="ramo-banner-actions">
                <a href="/crear-cotizacion<?php echo htmlspecialchars($qRamo); ?>" class="btn" style="background:rgba(255,255,255,.2);color:#fff;border:1px solid rgba(255,255,255,.4);">+ Cotización</a>
                <a href="/clientes<?php echo htmlspecialchars($qRamo); ?>" class="btn" style="background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.3);">Ver Clientes</a>
            </div>
        </div>

        <!-- KPIs -->
        <div class="stats-grid">
            <?php $kpis = [
                ['val' => $estadisticas['clientes_hoy'],          'lbl' => 'Clientes hoy',          'cls' => 'accent'],
                ['val' => $estadisticas['clientes_semana'],       'lbl' => 'Clientes esta semana',  'cls' => 'info'],
                ['val' => $estadisticas['cotizaciones_enviadas'], 'lbl' => 'Cotizaciones enviadas', 'cls' => 'warning'],
                ['val' => $estadisticas['ventas_cerradas'],       'lbl' => 'Ventas cerradas',       'cls' => 'success'],
                ['val' => $estadisticas['citas_programadas'],     'lbl' => 'Citas programadas',     'cls' => 'time'],
            ];
            foreach ($kpis as $kpi): ?>
            <div class="stat-card <?php echo $kpi['cls']; ?>">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-card-value"><?php echo $kpi['val']; ?></div>
                        <div class="stat-card-label"><?php echo $kpi['lbl']; ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Gráficos -->
        <div class="charts-grid">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Cotizaciones vs Ventas</h3></div>
                <div class="card-body"><div class="chart-container"><canvas id="chartCotizacionesVentas"></canvas></div></div>
            </div>
            <div class="card">
                <div class="card-header"><h3 class="card-title">Estado de Clientes</h3></div>
                <div class="card-body"><div class="chart-container"><canvas id="chartEstadoClientes"></canvas></div></div>
            </div>
        </div>
        <div class="charts-grid">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Clientes por Día</h3></div>
                <div class="card-body"><div class="chart-container"><canvas id="chartClientesDia"></canvas></div></div>
            </div>
            <div class="card">
                <div class="card-header"><h3 class="card-title">Top Vehículos</h3></div>
                <div class="card-body"><div class="chart-container"><canvas id="chartVehiculos"></canvas></div></div>
            </div>
        </div>

        <!-- Actividad reciente -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Actividad Reciente</h3>
                <a href="/reportes" class="btn btn-sm btn-ghost">Ver Reportes</a>
            </div>
            <div class="card-body">
                <?php if (empty($actividad_reciente)): ?>
                <div style="text-align:center;padding:24px 0;color:var(--text-muted);font-size:13px;">Sin actividad registrada aún.</div>
                <?php else:
                $iconos = ['registro'=>'person','cotizacion'=>'clipboard','venta'=>'check-circle-fill','cita'=>'calendar-event','llamada'=>'telephone','nota'=>'pencil-square','cambio_estado'=>'arrow-repeat'];
                ?>
                <div class="timeline">
                    <?php foreach ($actividad_reciente as $act): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker"><?php echo isset($iconos[$act['tipo']]) ? icono($iconos[$act['tipo']]) : '•'; ?></div>
                        <div class="timeline-content">
                            <div class="timeline-title"><?php echo htmlspecialchars($act['actividad_desc']); ?></div>
                            <div class="timeline-meta">
                                <span><?php echo date('d/m/Y H:i', strtotime($act['fecha'])); ?></span>
                                <?php if (!empty($act['cliente_nombre'])): ?>
                                <span>· <?php echo htmlspecialchars($act['cliente_nombre']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Números ATC -->
        <?php if (!empty($atc_numeros)): ?>
        <div class="card" style="margin-top:24px;">
            <div class="card-header">
                <h3 class="card-title">Números de Empresa</h3>
                <span style="font-size:12px;color:var(--text-muted);">Atención al Cliente</span>
            </div>
            <div class="card-body">
                <div style="display:flex;gap:12px;flex-wrap:wrap;">
                    <?php foreach ($atc_numeros as $atc): ?>
                    <a href="tel:<?php echo preg_replace('/\D/','',$atc['numero']); ?>"
                       style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:var(--bg-secondary);border-radius:var(--border-radius-sm);border:1px solid var(--border-color);text-decoration:none;color:var(--text-primary);flex:1;min-width:180px;">
                        <span style="font-size:24px;"><?= icono('telephone') ?></span>
                        <div>
                            <div style="font-weight:700;font-size:14px;"><?php echo htmlspecialchars($atc['numero']); ?></div>
                            <div style="font-size:11px;color:var(--text-muted);"><?php echo htmlspecialchars($atc['nombre']); ?></div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </main>
</div>

<script src="/js/dashboard.js"></script>
<script nonce="<?= SessionManager::nonce() ?>">
const datosClientesPorDia     = <?php echo $clientesPorDiaJSON; ?>;
const datosCotizacionesVentas = <?php echo $cotizacionesVsVentasJSON; ?>;
const datosVehiculosCotizados = <?php echo $vehiculosJSON; ?>;
const datosEstadoClientes     = <?php echo $estadoClientesJSON; ?>;
const ramoActivo              = <?php echo json_encode((string) $ramo); ?>;
const ramoColor               = <?php echo json_encode($ramo_actual['color']); ?>;
document.addEventListener('DOMContentLoaded', function() {
    if (typeof initDashboardCharts === 'function') initDashboardCharts();
});
</script>

<script src="/js/crm-events.js" nonce="<?= SessionManager::nonce() ?>"></script>
<script src="/js/modales.js"    nonce="<?= SessionManager::nonce() ?>"></script>
</body>
</html>