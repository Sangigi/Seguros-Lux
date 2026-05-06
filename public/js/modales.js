/**
 * modales.js — CRM EIRA
 */
document.addEventListener('DOMContentLoaded', () => {

    // ── Delegación de eventos para botones data-close ──────────────────────
    // Cualquier botón con data-close="modalId" cerrará ese modal.
    // Esto cubre todos los botones "Cancelar" y "×" de los modales.
    document.addEventListener('click', (e) => {
        const closeTarget = e.target.closest('[data-close]');
        if (closeTarget) {
            const modalId = closeTarget.getAttribute('data-close');
            if (typeof closeModal === 'function') closeModal(modalId);
        }
    });

    // NOTA: Los listeners de guardarNota, guardarCita y guardarCliente
    // se manejan exclusivamente en crm-events.js (delegación por data-action
    // o addEventListener por ID en sección 7). No se registran aquí para
    // evitar doble ejecución → duplicados en base de datos.
});