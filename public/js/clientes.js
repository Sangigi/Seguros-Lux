/**
 * clientes.js — CRM Seguros Lux
 * Búsqueda, filtros, modales y campos dinámicos por ramo.
 */

// ── Configuración de campos dinámicos por ramo ────────────────────────────────
// Estos campos se muestran/ocultan en el formulario de nueva cita/cliente
// dependiendo del ramo activo en sesión.
/**
 * Obtiene el token CSRF del meta tag inyectado por PHP.
 * El meta tag debe estar en _head.php:
 *   <meta name="csrf-token" content="<?= SessionManager::getCsrfToken() ?>">
 */
function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

const CAMPOS_RAMO = {
    autos: [
        { id: 'citaVehiculo', label: 'Vehículo (marca · modelo · año)', placeholder: 'Ej: Toyota Corolla 2022' },
        {
            id: 'citaTipoVehiculo', label: 'Tipo de vehículo', tipo: 'select',
            options: [
                { value: 'auto',          label: 'Auto' },
                { value: 'suv_camioneta', label: 'SUV / Camioneta' },
                { value: 'pickup',        label: 'Pickup' },
                { value: 'moto',          label: 'Moto' },
                { value: 'deportivo',     label: 'Deportivo' },
                { value: 'electrico',     label: 'Eléctrico' },
                { value: 'van',           label: 'Van / Minivan' },
            ]
        },
        {
            id: 'citaAsegurado', label: '¿Ya cuenta con seguro?', tipo: 'select',
            options: [{ value: 'no', label: 'No' }, { value: 'si', label: 'Sí' }]
        },
    ],
    vida: [
        { id: 'citaEdad',      label: 'Edad del titular',       placeholder: 'Ej: 35', inputType: 'number' },
        {
            id: 'citaSexo', label: 'Sexo', tipo: 'select',
            options: [{ value: 'masculino', label: 'Masculino' }, { value: 'femenino', label: 'Femenino' }]
        },
        {
            id: 'citaAsegurado', label: '¿Tiene seguro de vida?', tipo: 'select',
            options: [{ value: 'no', label: 'No' }, { value: 'si', label: 'Sí' }]
        },
    ],
    gastos_medicos: [
        { id: 'citaEdad',      label: 'Edad del titular',       placeholder: 'Ej: 40', inputType: 'number' },
        {
            id: 'citaSexo', label: 'Sexo', tipo: 'select',
            options: [{ value: 'masculino', label: 'Masculino' }, { value: 'femenino', label: 'Femenino' }]
        },
        {
            id: 'citaAsegurado', label: '¿Cuenta con GMM?', tipo: 'select',
            options: [{ value: 'no', label: 'No' }, { value: 'si', label: 'Sí' }]
        },
    ],
    empresas: [
        { id: 'citaGiro',        label: 'Giro / Industria',        placeholder: 'Ej: Manufactura' },
        { id: 'citaEmpleados',   label: 'Número de empleados',     placeholder: 'Ej: 25', inputType: 'number' },
        {
            id: 'citaAsegurado', label: '¿Tiene seguro empresarial?', tipo: 'select',
            options: [{ value: 'no', label: 'No' }, { value: 'si', label: 'Sí' }]
        },
    ],
};

const RAMO_LABEL = {
    autos:          ['car-front',  'Autos'],
    vida:           ['heart-fill', 'Vida'],
    gastos_medicos: ['hospital',   'Gastos Médicos Mayores'],
    empresas:       ['building',   'Empresas'],
};

// ── Init ──────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    initSearch();
    initFilters();
});

// ── Búsqueda ──────────────────────────────────────────────────────────────────
function initSearch() {
    const input = document.getElementById('searchInput');
    if (!input) return;
    let timer;
    input.addEventListener('input',   () => { clearTimeout(timer); timer = setTimeout(filtrarTabla, 280); });
    input.addEventListener('keydown', e  => { if (e.key === 'Enter') { clearTimeout(timer); filtrarTabla(); } });
}

// ── Filtros ───────────────────────────────────────────────────────────────────
function initFilters() {
    ['filterEstado', 'filterCiudad', 'filterFecha'].forEach(id =>
        document.getElementById(id)?.addEventListener('change', filtrarTabla)
    );
}

function filtrarTabla() {
    const q      = (document.getElementById('searchInput')?.value || '').toLowerCase();
    const estado = document.getElementById('filterEstado')?.value  || 'todos';
    const ciudad = (document.getElementById('filterCiudad')?.value || 'todos').toLowerCase();
    const fecha  = document.getElementById('filterFecha')?.value   || '';
    let visible  = 0;

    document.querySelectorAll('#clientesTableBody tr').forEach(tr => {
        const show = (!q      || (tr.dataset.nombre || '').includes(q))
                  && (estado === 'todos' || tr.dataset.estado  === estado)
                  && (ciudad === 'todos' || (tr.dataset.ciudad || '') === ciudad)
                  && (!fecha  || tr.dataset.fecha === fecha);
        tr.style.display = show ? '' : 'none';
        if (show) visible++;
    });

    const cnt = document.getElementById('clientesCount');
    if (cnt) cnt.textContent = visible + ' cliente' + (visible !== 1 ? 's' : '');

    const btnF = document.getElementById('btnLimpiarFecha');
    if (btnF) btnF.style.display = fecha ? '' : 'none';
}

function limpiarFecha() {
    const el = document.getElementById('filterFecha');
    if (el) el.value = '';
    filtrarTabla();
}

// ── Modales ───────────────────────────────────────────────────────────────────
function openModal(id)  { const m = document.getElementById(id); if (m) { m.classList.add('active'); document.body.style.overflow = 'hidden'; } }
function closeModal(id) { const m = document.getElementById(id); if (m) { m.classList.remove('active'); document.body.style.overflow = ''; } }

document.addEventListener('click', e => {
    if (e.target.classList.contains('modal-overlay')) { e.target.classList.remove('active'); document.body.style.overflow = ''; }
});
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { const m = document.querySelector('.modal-overlay.active'); if (m) { m.classList.remove('active'); document.body.style.overflow = ''; } }
});

// ── Modal Nota ────────────────────────────────────────────────────────────────
function openModalNota(id) {
    if (document.getElementById('notaClienteId')) document.getElementById('notaClienteId').value = id;
    if (document.getElementById('notaTexto'))     document.getElementById('notaTexto').value = '';
    openModal('modalNota');
}

// ── Modal Cita — con campos dinámicos por ramo ────────────────────────────────
function openModalCita(clienteId) {
    if (document.getElementById('citaClienteId')) document.getElementById('citaClienteId').value = clienteId;

    const hoy = new Date().toISOString().split('T')[0];
    const fEl = document.getElementById('citaFecha');
    if (fEl) { fEl.min = hoy; fEl.value = hoy; }
    if (document.getElementById('citaHora'))        document.getElementById('citaHora').value = '10:00';
    if (document.getElementById('citaComentarios')) document.getElementById('citaComentarios').value = '';

    // Renderizar campos dinámicos del ramo activo
    _renderCamposRamo();

    openModal('modalCita');
}

/**
 * Renderiza los campos extra del ramo dentro del contenedor #citaCamposRamo.
 * El ramo activo lo lee de window.ramoActivo (inyectado por PHP en la vista).
 */
function _renderCamposRamo() {
    const contenedor = document.getElementById('citaCamposRamo');
    if (!contenedor) return;

    const ramo   = window.ramoActivo || 'autos';
    const campos = CAMPOS_RAMO[ramo] || [];

    if (campos.length === 0) {
        contenedor.innerHTML = '';
        return;
    }

    const ramoInfo  = RAMO_LABEL[ramo];
    const ramoLabel = ramoInfo ? iconoSvg(ramoInfo[0]) + ' ' + ramoInfo[1] : ramo;
    let html = `<div style="font-size:11px;font-weight:700;color:var(--accent-color);text-transform:uppercase;
                            letter-spacing:.5px;margin-bottom:10px;padding-top:4px;">
                    Datos de ${ramoLabel}
                </div>`;

    campos.forEach(campo => {
        html += `<div class="form-group">
                    <label class="form-label">${campo.label}</label>`;

        if (campo.tipo === 'select') {
            html += `<select class="form-select" id="${campo.id}" name="${campo.id}">`;
            campo.options.forEach(opt => {
                html += `<option value="${opt.value}">${opt.label}</option>`;
            });
            html += `</select>`;
        } else {
            const type = campo.inputType || 'text';
            const ph   = campo.placeholder || '';
            html += `<input type="${type}" class="form-input" id="${campo.id}" name="${campo.id}" placeholder="${ph}">`;
        }

        html += `</div>`;
    });

    contenedor.innerHTML = html;
}

// ── Guardar Nota ──────────────────────────────────────────────────────────────
function guardarNota() {
    const id    = document.getElementById('notaClienteId')?.value;
    const texto = document.getElementById('notaTexto')?.value?.trim();
    if (!texto) { showToast('Escribe una nota', 'error'); return; }

    const fd = new FormData();
    fd.append('accion', 'nota');
    fd.append('cliente_id', id);
    fd.append('texto', texto);
    fd.append('csrf_token', getCsrfToken());

    fetch('/clientes', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.ok) {
                showToast('Nota guardada', 'success');
                closeModal('modalNota');
                document.getElementById('notaTexto').value = '';
            } else {
                showToast(d.msg || 'Error', 'error');
            }
        })
        .catch(() => showToast('Error de conexión', 'error'));
}

// ── Guardar Cita ──────────────────────────────────────────────────────────────
function guardarCita() {
    const id    = document.getElementById('citaClienteId')?.value;
    const fecha = document.getElementById('citaFecha')?.value;
    const hora  = document.getElementById('citaHora')?.value;
    const tipo  = document.getElementById('citaTipo')?.value || 'llamada';
    const com   = document.getElementById('citaComentarios')?.value || '';
    const ramo  = window.ramoActivo || 'autos';

    if (!fecha || !hora) { showToast('Selecciona fecha y hora', 'error'); return; }
    if (new Date(fecha + 'T' + hora) < new Date()) { showToast('La fecha no puede ser pasada', 'error'); return; }

    const fd = new FormData();
    fd.append('accion',     'cita');
    fd.append('cliente_id', id);
    fd.append('tipo',       tipo);
    fd.append('fecha',      fecha);
    fd.append('hora',       hora);
    fd.append('comentarios',com);
    fd.append('ramo',       ramo); // ← siempre enviar el ramo activo

    // Agregar campos dinámicos del ramo si existen
    const campos = CAMPOS_RAMO[ramo] || [];
    campos.forEach(campo => {
        const el = document.getElementById(campo.id);
        if (el && el.value) fd.append(campo.id, el.value);
    });

    fd.append('csrf_token', getCsrfToken());
    fetch('/clientes', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.ok) {
                showToast('Cita programada', 'success');
                closeModal('modalCita');
            } else {
                showToast(d.msg || 'Error', 'error');
            }
        })
        .catch(() => showToast('Error de conexión', 'error'));
}

// ── Eliminar cliente ──────────────────────────────────────────────────────────
function eliminarCliente(id) {
    if (!confirm('¿Eliminar este cliente? Esta acción no se puede deshacer.')) return;
    const fd = new FormData();
    fd.append('accion', 'eliminar');
    fd.append('id', id);
    fd.append('csrf_token', getCsrfToken());
    fetch('/clientes', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.ok) {
                showToast('Cliente eliminado', 'success');
                document.querySelector(`#clientesTableBody tr[data-id="${id}"]`)?.remove();
                filtrarTabla();
            } else {
                showToast(d.msg || 'Error', 'error');
            }
        });
}

// ── Toast ─────────────────────────────────────────────────────────────────────
function showToast(msg, type = 'success') {
    const c = document.getElementById('toastContainer');
    if (!c) return;
    const t = document.createElement('div');
    t.className = `toast ${type}`;
    t.innerHTML = `<div class="toast-icon">${type === 'error' ? '✗' : type === 'warning' ? '⚠' : '✓'}</div>
                   <div class="toast-message">${msg}</div>
                   <button class="toast-close" onclick="this.parentElement.remove()">&times;</button>`;
    c.appendChild(t);
    setTimeout(() => { t.classList.add('hiding'); setTimeout(() => t.remove(), 300); }, 5000);
}