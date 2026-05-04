/**
 * cotizaciones.js — CRM Seguros Lux
 * SOLO utilidades compartidas: escapeHtml, showToast, openModal, closeModal,
 * y manejadores de teclado/overlay.
 *
 * Toda la lógica específica de la página (filtros, ordenación, verCotizacion,
 * cambiarEstadoFlujo, editarCotizacion, etc.) vive en el <script> inline de
 * cotizaciones/index.php para evitar re-declaraciones de `let` que bloquean
 * la ejecución del script inline.
 */

// ── Escape HTML ───────────────────────────────────────────────────────────────
function escapeHtml(str) {
    return String(str || '').replace(/&/g, '&amp;')
                             .replace(/</g, '&lt;')
                             .replace(/>/g, '&gt;')
                             .replace(/"/g, '&quot;');
}

// ── Toast ─────────────────────────────────────────────────────────────────────
function showToast(msg, type = 'success') {
    const c = document.getElementById('toastContainer');
    if (!c) return;
    const t = document.createElement('div');
    t.className = `toast ${type}`;
    t.innerHTML = `
        <div class="toast-icon">${type === 'error' ? '✗' : type === 'warning' ? '⚠' : '✓'}</div>
        <div class="toast-message">${msg}</div>
        <button class="toast-close" onclick="this.parentElement.remove()">&times;</button>`;
    c.appendChild(t);
    setTimeout(() => { t.classList.add('hiding'); setTimeout(() => t.remove(), 300); }, 5000);
}

// ── Modales ───────────────────────────────────────────────────────────────────
function openModal(id) {
    const m = document.getElementById(id);
    if (m) { m.classList.add('active'); document.body.style.overflow = 'hidden'; }
}
function closeModal(id) {
    const m = document.getElementById(id);
    if (m) { m.classList.remove('active'); document.body.style.overflow = ''; }
}

// Cerrar al hacer clic en el fondo del overlay
document.addEventListener('click', e => {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('active');
        document.body.style.overflow = '';
    }
});

// Cerrar con Escape
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        const m = document.querySelector('.modal-overlay.active');
        if (m) { m.classList.remove('active'); document.body.style.overflow = ''; }
    }
});