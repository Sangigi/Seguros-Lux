/**
 * crm-init.js
 * Inicializa el SPA: router, nav clicks, popstate.
 */

document.addEventListener('DOMContentLoaded', () => {

    // ── Delegar clicks de la navegación SPA ───────────────────────────────
    document.addEventListener('click', e => {
        const link = e.target.closest('.nav-spa');
        if (link) {
            e.preventDefault();
            const section = link.dataset.section;
            if (section) CRM.navigate(section);
        }
    });

    // ── Botón atrás / adelante del navegador ──────────────────────────────
    window.addEventListener('popstate', e => {
        const section = e.state?.section ?? _sectionFromUrl();
        if (section) CRM.navigate(section);
    });

    // ── Sesión keepalive (ping cada 5 min) ────────────────────────────────
    setInterval(async () => {
        const res = await fetch('/SessionPing.php');
        if (res.status === 401) {
            window.location.href = '/templates/Login.php?expired=1';
        }
    }, 5 * 60 * 1000);

    // ── Determinar sección inicial ────────────────────────────────────────
    const initial = _sectionFromUrl() ?? 'dashboard';
    CRM.navigate(initial);
});

function _sectionFromUrl() {
    const params = new URLSearchParams(window.location.search);
    return params.get('s') ?? null;
}
