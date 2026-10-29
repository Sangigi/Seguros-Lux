/**
 * crm-events.js — CRM Seguros Lux
 *
 * UN SOLO punto de entrada para todos los eventos de click.
 * Usar SIEMPRE data-action en los botones y manejarlos aquí.
 * NUNCA agregar addEventListener directo por ID para acciones
 * que ya están cubiertas por la delegación — causa doble ejecución.
 */

document.addEventListener('DOMContentLoaded', () => {

    // ── 1. Delegación global de clicks ────────────────────────────────────────
    document.addEventListener('click', (e) => {
        const el = e.target.closest('[data-action],[data-close],[data-open-modal]');
        if (!el) return;

        // ── Cerrar modal ──────────────────────────────────────────────────────
        if (el.dataset.close) {
            if (typeof closeModal === 'function') closeModal(el.dataset.close);
            return;
        }

        // ── Abrir modal ───────────────────────────────────────────────────────
        if (el.dataset.openModal) {
            if (typeof openModal === 'function') openModal(el.dataset.openModal);
            return;
        }

        const action = el.dataset.action;
        const id     = el.dataset.id     ? parseInt(el.dataset.id)  : null;
        const key    = el.dataset.key    || null;
        const dia    = el.dataset.dia    ? parseInt(el.dataset.dia) : null;
        const tipo   = el.dataset.tipo   || null;
        const estado = el.dataset.estado || null;
        const url    = el.dataset.url    || null;

        if (!action) return;

        switch (action) {

            // ── CLIENTES — abrir modales ──────────────────────────────────────
            case 'nota':
                if (typeof openModalNota === 'function') openModalNota(id);
                break;
            case 'cita':
                if (typeof openModalCita === 'function') openModalCita(id);
                break;

            // ── CLIENTES — guardar desde modal ────────────────────────────────
            // Estos casos reemplazan los addEventListener directos por ID.
            // El botón en _modales_clientes.php tiene data-action="guardarNota"
            // en lugar de id="btnGuardarNota", así solo hay UN listener.
            case 'guardarNota':
                if (typeof guardarNota === 'function') guardarNota();
                break;
            case 'guardarCita':
                if (typeof guardarCita === 'function') guardarCita();
                break;
            case 'guardarCliente': {
                const form = document.getElementById('formNuevoCliente');
                if (!form) break;
                // Validar campos requeridos manualmente para dar feedback visual
                const requeridos = form.querySelectorAll('[required]');
                let valido = true;
                requeridos.forEach(campo => {
                    if (!campo.value.trim()) {
                        campo.style.borderColor = 'var(--danger-color, #ef4444)';
                        campo.addEventListener('input', () => campo.style.borderColor = '', { once: true });
                        valido = false;
                    }
                });
                if (!valido) {
                    if (typeof showToast === 'function') showToast('Completa los campos obligatorios', 'error');
                    break;
                }
                form.submit();
                break;
            }

            // ── COTIZACIONES — fila de tabla ──────────────────────────────────
            case 'ver':
                e.stopPropagation();
                if (typeof verCotizacion === 'function') verCotizacion(id);
                break;
            case 'estado':
                e.stopPropagation();
                if (typeof closeModal          === 'function') closeModal('modalVerCotizacion');
                if (typeof cambiarEstadoFlujo  === 'function') cambiarEstadoFlujo(id);
                break;
            case 'editar':
                e.stopPropagation();
                if (typeof editarCotizacion === 'function') editarCotizacion(id);
                break;
            case 'enviar':
                e.stopPropagation();
                if (typeof closeModal        === 'function') closeModal('modalVerCotizacion');
                if (typeof enviarCotizacion  === 'function') enviarCotizacion(id);
                break;
            case 'enviarCoordinador':
                e.stopPropagation();
                if (typeof enviarCoordinador === 'function') enviarCoordinador(id);
                break;
            case 'docs':
                if (typeof abrirModalDocumentos === 'function') abrirModalDocumentos(id);
                break;
            case 'flujo':
                if (typeof aplicarEstadoFlujo === 'function') aplicarEstadoFlujo(id, estado);
                break;
            case 'openFile':
                if (url) {
                    window.open(url, '_blank');
                } else if (el.dataset.target) {
                    document.getElementById(el.dataset.target)?.click();
                }
                break;
            case 'eliminarArchivo':
                if (typeof eliminarArchivo === 'function')
                    eliminarArchivo(parseInt(el.dataset.archivoId), el);
                break;

            // ── CITAS ─────────────────────────────────────────────────────────
            case 'abrirDia':
                if (el.dataset.stop) e.stopPropagation();
                if (typeof abrirDia === 'function') {
                    const [aY, aM] = window._calActual || [new Date().getFullYear(), new Date().getMonth()+1];
                    abrirDia(dia, aM, aY);
                }
                break;
            case 'verCita':
                if (el.dataset.stop !== undefined) e.stopPropagation();
                if (typeof verCita === 'function') verCita(id);
                break;
            case 'dia-nueva-cita':
                if (typeof closeModal === 'function') closeModal('modalDia');
                if (typeof openModal  === 'function') openModal('modalNuevaCita');
                break;
            case 'dia-ver':
                if (typeof closeModal === 'function') closeModal('modalDia');
                if (typeof verCita   === 'function')  verCita(id);
                break;
            case 'cancelar':
                if (typeof cambiarEstado === 'function') cambiarEstado(id, 'cancelada');
                break;
            case 'completar':
                if (typeof cambiarEstado === 'function') cambiarEstado(id, 'completada');
                break;

            // ── CREAR COTIZACION ──────────────────────────────────────────────
            case 'selectMetodo':
                if (typeof selectMetodo === 'function') selectMetodo(key);
                break;
            case 'quitarDoc':
                if (typeof quitarDoc === 'function') quitarDoc(tipo);
                break;

            // ── TOAST ─────────────────────────────────────────────────────────
            case 'toast-close':
                el.closest('.toast')?.remove();
                break;
        }
    });

    // ── 2. Botón limpiar fecha (clientes) ─────────────────────────────────────
    document.getElementById('btnLimpiarFecha')
        ?.addEventListener('click', () => {
            if (typeof limpiarFecha === 'function') limpiarFecha();
        });

    // ── 3. Sort cotizaciones ───────────────────────────────────────────────────
    document.getElementById('btnSortVence')
        ?.addEventListener('click', () => {
            if (typeof toggleSortVence === 'function') toggleSortVence();
        });

    // ── 4. Print ───────────────────────────────────────────────────────────────
    document.getElementById('btnPrint')
        ?.addEventListener('click', () => window.print());

    // ── 5. Botones de modales cotizaciones ────────────────────────────────────
    document.getElementById('btnGuardarEstado')
        ?.addEventListener('click', () => {
            if (typeof guardarEstado === 'function') guardarEstado();
        });
    document.getElementById('btnQuitarPago')
        ?.addEventListener('click', () => {
            if (typeof quitarPago === 'function') quitarPago();
        });
    document.getElementById('btnGuardarPago')
        ?.addEventListener('click', () => {
            if (typeof guardarPago === 'function') guardarPago();
        });
    document.getElementById('pagoZone')
        ?.addEventListener('click', () => {
            document.getElementById('pagoFile')?.click();
        });

    // ── 6. Citas: navegación de mes ───────────────────────────────────────────
    document.getElementById('btnPrevMes')
        ?.addEventListener('click', () => {
            if (typeof navMes === 'function') navMes(-1);
        });
    document.getElementById('btnNextMes')
        ?.addEventListener('click', () => {
            if (typeof navMes === 'function') navMes(1);
        });

    // ── 7. Citas: guardar nueva cita (módulo calendario) ──────────────────────
    // NOTA: este btnGuardarCita es del modal del CALENDARIO (/citas),
    // no del modal de clientes. En /clientes el botón usa data-action="guardarCita"
    // y lo maneja la delegación de arriba. No hay conflicto porque en /citas
    // el botón tiene id="btnGuardarCita" sin data-action.
    document.getElementById('btnGuardarCita')
        ?.addEventListener('click', () => {
            if (typeof guardarCita === 'function') guardarCita();
        });

    // ── 8. Citas: select tipo y teléfono ─────────────────────────────────────
    document.getElementById('ncTipo')
        ?.addEventListener('change', function () {
            if (typeof onTipoChange === 'function') onTipoChange(this.value);
        });
    document.getElementById('ncTelefono')
        ?.addEventListener('input', function () {
            this.value = this.value.replace(/[^+\d\s\-]/g, '').slice(0, 15);
        });

    // ── 9. Crear cotizacion: selects y file inputs ────────────────────────────
    document.getElementById('btnCopyFolio')
        ?.addEventListener('click', () => {
            if (typeof copiarFolio === 'function') copiarFolio();
        });
    document.getElementById('btnGuardar')
        ?.addEventListener('click', () => {
            if (typeof guardarCotizacion === 'function') guardarCotizacion();
        });
    document.getElementById('toggleEmitBtn')
        ?.addEventListener('click', function () {
            if (typeof toggleEmitDocs === 'function') toggleEmitDocs(this);
        });
    document.getElementById('clienteSelect')
        ?.addEventListener('change', function () {
            if (typeof onClienteChange === 'function') onClienteChange(this.value);
        });
    document.getElementById('coberturaSelect')
        ?.addEventListener('change', () => {
            if (typeof actualizarFolio === 'function') actualizarFolio();
        });

    document.querySelectorAll('[data-handler="actualizarFolio"]')
        .forEach(el => el.addEventListener('change', () => {
            if (typeof actualizarFolio === 'function') actualizarFolio();
        }));

    document.querySelectorAll('[data-handler="docSelected"]')
        .forEach(input => input.addEventListener('change', function () {
            if (typeof onDocSelected === 'function')
                onDocSelected(this, this.dataset.tipo);
        }));

    document.getElementById('pagoFile')
        ?.addEventListener('change', function () {
            if (typeof onPagoFile === 'function') onPagoFile(this);
        });

    // ── 10. set_ramo ──────────────────────────────────────────────────────────
    document.getElementById('formSetRamo')
        ?.addEventListener('submit', () => { /* CSRF ya va en el form */ });

});