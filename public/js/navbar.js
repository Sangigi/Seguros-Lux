/**
 * navbar.js — Sidebar CRM Seguros Lux
 */

function toggleSidebar(force) {
    const sidebar = document.getElementById('crmSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const toggle  = document.getElementById('sidebarToggle');
    if (!sidebar || !overlay || !toggle) return;

    const isOpen = sidebar.classList.contains('open');
    const open   = force !== undefined ? force : !isOpen;

    sidebar.classList.toggle('open', open);
    overlay.classList.toggle('open', open);
    toggle.innerHTML = open
        ? '<i class="bi bi-x-lg"></i>'
        : '<i class="bi bi-list"></i>';
}

document.addEventListener('DOMContentLoaded', () => {
    // Overlay mobile — cerrar sidebar al hacer clic fuera
    document.getElementById('sidebarOverlay')
        ?.addEventListener('click', () => toggleSidebar(false));

    // Botón hamburguesa
    document.getElementById('sidebarToggle')
        ?.addEventListener('click', () => toggleSidebar());

    // Enlace de logout — confirmación sin onclick inline
    document.getElementById('sidebarLogout')
        ?.addEventListener('click', (e) => {
            if (!confirm('¿Cerrar sesión?')) e.preventDefault();
        });
});
// Conserva el scroll del menú entre secciones (cada sección es una página).
(() => {
    const nav = document.querySelector('.crm-sidebar .sidebar-nav');
    if (!nav) return;
    try {
        nav.scrollTop = Number(sessionStorage.getItem('crmSidebarScroll')) || 0;
    } catch (e) { /* almacenamiento bloqueado: no pasa nada */ }
    window.addEventListener('pagehide', () => {
        try { sessionStorage.setItem('crmSidebarScroll', String(nav.scrollTop)); } catch (e) {}
    });
})();
