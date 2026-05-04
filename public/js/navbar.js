/**
 * navbar.js — Sidebar CRM EIRA
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