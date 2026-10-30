<?php
/**
 * Partial: _navbar.php — Sidebar del CRM
 * Requiere que $cspNonce esté definido (lo define _head.php).
 */

use App\Security\SessionManager;
use App\Models\NotificacionModel;
use App\Models\AgentModel;

$_agente      = SessionManager::getAgentData();
$_agenteNombre= trim(($_agente['nombre'] ?? '') . ' ' . ($_agente['apellido'] ?? ''));
$_agenteRol   = $_agente['rol'] ?? 'asesor';
$_iniciales   = strtoupper(
    substr($_agente['nombre'] ?? 'U', 0, 1) .
    substr($_agente['apellido'] ?? '', 0, 1)
);

$_uri = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
$_queryString = $_SERVER['QUERY_STRING'] ?? '';
parse_str($_queryString, $_queryParams);
$_currentTab = $_queryParams['tab'] ?? '';

function _navActive(string $path, string $uri): string {
    return $uri === $path ? 'active' : '';
}
function _navTabActive(string $tab, string $currentTab): string {
    return $currentTab === $tab ? 'active' : '';
}

$_sidebarNotifCount = 0;
// Notificaciones solo para asesores (y superadmin en modo asesor)
if ($_agenteRol === 'asesor' || $_agenteRol === 'superadmin') {
    try {
        $_sidebarNotifCount = (new NotificacionModel())->contarNoLeidas((int) SessionManager::getAgentId());
    } catch (\Throwable $e) {
        $_sidebarNotifCount = 0;
    }
}

$_isCoord     = in_array($_agenteRol, ['coordinador', 'administrador'], true);
$_isSuperadmin= $_agenteRol === 'superadmin';
$_isAsesor    = $_agenteRol === 'asesor';

// Tabs de admin disponibles por rol
$_adminTabs = [
    'resumen'       => ['bi-grid-1x2-fill',  'Resumen'],
    'rendimiento'   => ['bi-bar-chart-fill',  'Rendimiento'],
    'pipeline'      => ['bi-arrow-repeat',    'Pipeline'],
    'actividad'     => ['bi-lightning-fill',  'Actividad'],
    'asesores'      => ['bi-people-fill',     'Asesores'],
    'polizas'       => ['bi bi-archive','Pólizas'],
    'emision'       => ['bi-file-earmark-check-fill', 'Emisión'],
    'renovaciones'  => ['bi-arrow-clockwise', 'Renovaciones'],
];
$_adminTabsSA = array_merge($_adminTabs, [
    'cotizaciones'  => ['bi-file-earmark-text-fill', 'Cotizaciones'],
    'configuracion' => ['bi-gear-fill',              'Configuración'],
]);
?>
<style nonce="<?= $cspNonce ?? '' ?>">
/* ── Sidebar CRM ─────────────────────────────────────────────────────────── */
.crm-sidebar {
    width: 240px;
    height: 100vh;
    background: #0f172a;
    display: flex;
    flex-direction: column;
    position: fixed;
    top: 0;
    left: 0;
    z-index: 100;
    transition: transform .25s ease;
    overflow: hidden;
}
.crm-sidebar + .main-content,
.crm-sidebar ~ .main-content {
    margin-left: 240px;
}
.sidebar-logo-wrap {
    padding: 22px 20px 18px;
    border-bottom: 1px solid rgba(255,255,255,.08);
    display: flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
    flex-shrink: 0;
}
.sidebar-logo-icon {
    width: 36px;
    height: 36px;
    background: linear-gradient(135deg, #3b82f6, #0ea5e9);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.sidebar-logo-text {
    font-size: 17px;
    font-weight: 800;
    color: #fff;
    letter-spacing: -.3px;
}
.sidebar-logo-sub {
    font-size: 10px;
    color: rgba(255,255,255,.45);
    letter-spacing: .5px;
    text-transform: uppercase;
    font-weight: 600;
    margin-top: 1px;
}

/* Nav links */
.sidebar-nav {
    flex: 1;
    min-height: 0;
    padding: 14px 10px;
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: rgba(255,255,255,.15) transparent;
}
.sidebar-nav::-webkit-scrollbar { width: 4px; }
.sidebar-nav::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,.15);
    border-radius: 4px;
}
.sidebar-section-label {
    font-size: 10px;
    font-weight: 700;
    color: rgba(255,255,255,.3);
    text-transform: uppercase;
    letter-spacing: .8px;
    padding: 10px 10px 6px;
    margin-top: 4px;
}
.sidebar-link {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px 12px;
    border-radius: 8px;
    color: rgba(255,255,255,.65);
    font-size: 13.5px;
    font-weight: 500;
    text-decoration: none;
    transition: background .15s, color .15s;
    margin-bottom: 2px;
    position: relative;
}
.sidebar-link:hover {
    background: rgba(255,255,255,.07);
    color: #fff;
}
.sidebar-link.active {
    background: rgba(59,130,246,.25);
    color: #93c5fd;
    font-weight: 600;
}
.sidebar-link.active::before {
    content: '';
    position: absolute;
    left: 0;
    top: 6px;
    bottom: 6px;
    width: 3px;
    background: #3b82f6;
    border-radius: 0 3px 3px 0;
}
.sidebar-link i {
    font-size: 17px;
    width: 20px;
    text-align: center;
    flex-shrink: 0;
}
.sidebar-link .nav-badge {
    margin-left: auto;
    background: #3b82f6;
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    padding: 1px 6px;
    border-radius: 99px;
    line-height: 16px;
}

/* Footer / Usuario */
.sidebar-footer {
    padding: 14px 10px;
    border-top: 1px solid rgba(255,255,255,.08);
    flex-shrink: 0;
}
.sidebar-user {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 8px;
    background: rgba(255,255,255,.05);
    margin-bottom: 6px;
    cursor: default;
}
.sidebar-user-avatar {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: linear-gradient(135deg, #3b82f6, #0ea5e9);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 700;
    color: #fff;
    flex-shrink: 0;
}
.sidebar-user-name {
    font-size: 12.5px;
    font-weight: 600;
    color: #fff;
    line-height: 1.2;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.sidebar-user-role {
    font-size: 10px;
    color: rgba(255,255,255,.4);
    text-transform: capitalize;
}
.sidebar-logout {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 8px 12px;
    border-radius: 8px;
    color: rgba(255,255,255,.45);
    font-size: 13px;
    text-decoration: none;
    transition: background .15s, color .15s;
}
.sidebar-logout:hover {
    background: rgba(239,68,68,.12);
    color: #fca5a5;
}

/* Botón hamburguesa (mobile) */
.sidebar-toggle {
    display: none;
    position: fixed;
    top: 14px;
    right: 14px;
    z-index: 200;
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: #0f172a;
    border: none;
    cursor: pointer;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,.3);
}
.sidebar-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.4);
    z-index: 99;
}

.crm-wrapper {
    display: flex;
    min-height: 100vh;
}
.crm-wrapper .main-content {
    flex: 1;
    margin-left: 240px;
    min-width: 0;
    padding: 24px 28px;
    background: var(--bg-subtle, #f8fafc);
}

button.sidebar-link {
    width: 100%;
    border: none;
    background: transparent;
    font: inherit;
    font-family: inherit;
    cursor: pointer;
    text-align: left;
}

/* Notif panel */
.crm-notif-panel {
    margin: 0 10px 10px;
    border-radius: 10px;
    background: rgba(10, 15, 28, 0.85);
    border: 1px solid rgba(243, 195, 50, 0.2);
    max-height: 340px;
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: rgba(243,195,50,.25) transparent;
}
.crm-notif-panel::-webkit-scrollbar { width: 4px; }
.crm-notif-panel::-webkit-scrollbar-thumb { background: rgba(243,195,50,.25); border-radius: 4px; }
.crm-notif-panel .notif-item { font-size: 12px; color: rgba(255,255,255,.88); padding: 8px 6px; border-bottom: 1px solid rgba(255,255,255,.06); cursor: pointer; text-align: left; width: 100%; background: none; border-left: none; border-right: none; border-top: none; }
.crm-notif-panel .notif-item:last-child { border-bottom: none !important; }
.crm-notif-panel .notif-item:hover { background: rgba(255,255,255,.05) !important; }
.crm-notif-panel .notif-item.is-unread { font-weight: 600; }
.crm-notif-panel .notif-item.is-unread:hover { background: rgba(243,195,50,.09) !important; }
.crm-notif-panel .notif-meta { font-size: 10px; color: rgba(255, 255, 255, 0.45); margin-top: 4px; }
.crm-notif-actions { display: flex; gap: 6px; padding: 10px 10px 6px; flex-wrap: wrap; border-bottom: 1px solid rgba(255,255,255,.06); }
.crm-notif-actions button { font-size: 10px; padding: 4px 9px; border-radius: 6px; border: 1px solid rgba(255,255,255,.15); background: rgba(255,255,255,.07); color: rgba(255,255,255,.75); cursor: pointer; font-weight: 600; transition: background .15s, color .15s; }
.crm-notif-actions button:hover { background: rgba(255,255,255,.13); color: #fff; }

/* Rol badge en sidebar */
.sidebar-rol-badge {
    display: inline-block;
    font-size: 9px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .5px;
    padding: 2px 7px;
    border-radius: 99px;
    margin-left: 6px;
    vertical-align: middle;
}
.sidebar-rol-badge.coordinador { background: rgba(59,130,246,.25); color: #93c5fd; }
.sidebar-rol-badge.superadmin  { background: rgba(243,195,50,.2);  color: #fbbf24; }

@media (max-width: 1024px) {
    .crm-sidebar { transform: translateX(-100%); }
    .crm-sidebar.open { transform: translateX(0); }
    .sidebar-toggle { display: flex; }
    .sidebar-overlay.open { display: block; }
    .crm-wrapper .main-content { margin-left: 0; padding: 60px 16px 20px; }
}
</style>

<!-- Overlay mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Botón hamburguesa mobile -->
<button class="sidebar-toggle" id="sidebarToggle" aria-label="Abrir menú">
    <i class="bi bi-list"></i>
</button>

<aside class="crm-sidebar" id="crmSidebar" role="navigation" aria-label="Menú del CRM">

    <!-- Logo -->
    <a href="<?= ($_isCoord || $_isSuperadmin) ? '/admin?tab=resumen' : '/dashboard' ?>" class="sidebar-logo-wrap">
        <div class="sidebar-logo-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
            </svg>
        </div>
        <div>
            <div class="sidebar-logo-text">Seguros Lux</div>
            <div class="sidebar-logo-sub">CRM</div>
        </div>
    </a>

    <nav class="sidebar-nav">

        <?php if ($_isAsesor): ?>
        <!-- ══════════════════════════════════════════════
             MENÚ ASESOR
        ══════════════════════════════════════════════ -->
        <div class="sidebar-section-label">Principal</div>

        <a href="/dashboard" class="sidebar-link <?php echo _navActive('/dashboard', $_uri); ?>">
            <i class="bi bi-grid-1x2-fill"></i>
            Dashboard
        </a>
        <a href="/clientes" class="sidebar-link <?php echo _navActive('/clientes', $_uri); ?>">
            <i class="bi bi-people-fill"></i>
            Clientes
        </a>
        <a href="/cotizaciones" class="sidebar-link <?php echo _navActive('/cotizaciones', $_uri); ?>">
            <i class="bi bi-file-earmark-text-fill"></i>
            Cotizaciones
        </a>
        <a href="/citas" class="sidebar-link <?php echo _navActive('/citas', $_uri); ?>">
            <i class="bi bi-calendar2-check-fill"></i>
            Citas
        </a>
        <a href="/reportes" class="sidebar-link <?php echo _navActive('/reportes', $_uri); ?>">
            <i class="bi bi-bar-chart-fill"></i>
            Reportes
        </a>

        <!-- Notificaciones (solo asesores) -->
        <button type="button"
                class="sidebar-link <?php echo $_sidebarNotifCount > 0 ? 'has-alert' : ''; ?>"
                id="sidebarNotifToggle"
                aria-expanded="false"
                aria-controls="sidebarNotifPanel">
            <i class="bi bi-bell-fill"></i>
            Avisos
            <?php if ($_sidebarNotifCount > 0): ?>
                <span class="nav-badge" id="sidebarNotifBadge"><?php echo $_sidebarNotifCount > 99 ? '99+' : $_sidebarNotifCount; ?></span>
            <?php endif; ?>
        </button>
        <div id="sidebarNotifPanel" class="crm-notif-panel" hidden data-initial-count="<?php echo (int) $_sidebarNotifCount; ?>">
            <div class="crm-notif-actions">
                <button type="button" id="sidebarNotifMarkAll">✓ Marcar leídas</button>
                <button type="button" id="sidebarNotifReload">↺ Actualizar</button>
                <button type="button" id="sidebarNotifClearAll" style="color:#fca5a5;border-color:rgba(239,68,68,.3);"><?= icono('trash') ?> Limpiar todo</button>
            </div>
            <div id="sidebarNotifList" style="font-size:12px;color:rgba(255,255,255,.5);padding:10px;text-align:center;">Cargando…</div>
        </div>

        <div class="sidebar-section-label" style="margin-top:8px;">Acciones</div>
        <a href="/crear-cotizacion" class="sidebar-link <?php echo _navActive('/crear-cotizacion', $_uri); ?>">
            <i class="bi bi-plus-circle-fill"></i>
            Nueva Cotización
        </a>

        <?php elseif ($_isCoord): ?>
        <!-- ══════════════════════════════════════════════
             MENÚ COORDINADOR
        ══════════════════════════════════════════════ -->
        <div class="sidebar-section-label">Panel de Coordinación</div>

        <?php foreach ($_adminTabs as $tabKey => [$icon, $label]): ?>
        <a href="/admin?tab=<?= $tabKey ?>"
           class="sidebar-link <?= ($_uri === '/admin' && $_currentTab === $tabKey) ? 'active' : '' ?>">
            <i class="bi <?= $icon ?>"></i>
            <?= $label ?>
        </a>
        <?php endforeach; ?>

        <div class="sidebar-section-label" style="margin-top:8px;">Clientes</div>
        <a href="/clientes" class="sidebar-link <?php echo _navActive('/clientes', $_uri); ?>">
            <i class="bi bi-people-fill"></i>
            Todos los Clientes
        </a>

        <?php elseif ($_isSuperadmin): ?>
        <!-- ══════════════════════════════════════════════
             MENÚ SUPERADMIN
             (Panel admin completo + flujo de asesor)
        ══════════════════════════════════════════════ -->
        <div class="sidebar-section-label">
            Panel Admin
            <span class="sidebar-rol-badge superadmin">SA</span>
        </div>

        <?php foreach ($_adminTabsSA as $tabKey => [$icon, $label]): ?>
        <a href="/admin?tab=<?= $tabKey ?>"
           class="sidebar-link <?= ($_uri === '/admin' && $_currentTab === $tabKey) ? 'active' : '' ?>">
            <i class="bi <?= $icon ?>"></i>
            <?= $label ?>
        </a>
        <?php endforeach; ?>

        <div class="sidebar-section-label" style="margin-top:8px;">Clientes</div>
        <a href="/clientes" class="sidebar-link <?php echo _navActive('/clientes', $_uri); ?>">
            <i class="bi bi-people-fill"></i>
            Todos los Clientes
        </a>

        <?php endif; ?>

        <?php
        /*
         * Administración de personas y de la plataforma.
         *
         * Va fuera del if/elseif de arriba, que reparte el menú por rol: estos
         * dos enlaces no dependen del rol sino del permiso concreto, y así un
         * cambio en la matriz de Roles se refleja solo aquí.
         *
         * Ocultar el enlace es comodidad, no seguridad: la vista y la API
         * vuelven a comprobar el permiso por su cuenta.
         */
        $_puedeInvitar  = \App\Security\SessionManager::puede(\App\Security\Roles::USUARIOS_INVITAR);
        $_puedeEmpresas = \App\Security\SessionManager::puede(\App\Security\Roles::EMPRESAS_VER);
        ?>

        <?php if ($_puedeInvitar || $_puedeEmpresas): ?>
        <div class="sidebar-section-label" style="margin-top:8px;">Administración</div>

            <?php if ($_puedeInvitar): ?>
            <a href="/equipo" class="sidebar-link <?php echo _navActive('/equipo', $_uri); ?>">
                <i class="bi bi-person-plus-fill"></i>
                Equipo
            </a>
            <?php endif; ?>

            <?php if ($_puedeEmpresas): ?>
            <a href="/plataforma/empresas" class="sidebar-link <?php echo _navActive('/plataforma/empresas', $_uri); ?>">
                <i class="bi bi-building"></i>
                Empresas
            </a>
            <?php endif; ?>
        <?php endif; ?>

    </nav>

    <!-- Usuario y cierre de sesión -->
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="sidebar-user-avatar"><?php echo htmlspecialchars($_iniciales); ?></div>
            <div style="min-width:0;">
                <div class="sidebar-user-name"><?php echo htmlspecialchars($_agenteNombre); ?></div>
                <div class="sidebar-user-role"><?php echo htmlspecialchars(\App\Models\AgentModel::labelPuesto($_agente['puesto'] ?? null, $_agenteRol)); ?></div>
            </div>
        </div>
        <form method="POST" action="/logout" id="formLogout" style="margin:0;">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(SessionManager::getCsrfToken(), ENT_QUOTES); ?>">
            <button type="submit" class="sidebar-logout" style="width:100%;background:none;border:none;cursor:pointer;text-align:left;">
                <i class="bi bi-box-arrow-left" style="font-size:16px;"></i>
                Cerrar sesión
            </button>
        </form>
    </div>
</aside>

<script src="<?= asset_url('js/navbar.js') ?>" nonce="<?= $cspNonce ?? '' ?>"></script>
<?php if ($_isAsesor || $_isSuperadmin): ?>
<script src="<?= asset_url('js/notificaciones-navbar.js') ?>" nonce="<?= $cspNonce ?? '' ?>"></script>
<?php endif; ?>
