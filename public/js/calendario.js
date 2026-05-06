/**
 * Calendario JavaScript
 * CRM para Agentes de Seguros de Autos
 *
 * Funcionalidades:
 * - Ver detalle de cita
 * - Ver citas de un día
 * - Crear nueva cita
 * - Completar / cancelar citas
 * - Toast notifications
 */

// =============================================
// ESTADO GLOBAL
// =============================================
let citaActualId = null;

const TIPO_LABEL = {
    llamada     : '📞 Llamada telefónica',
    videollamada: '💻 Videollamada',
    presencial  : '🤝 Presencial'
};

const ESTADO_BADGE = {
    programada : { label: 'Programada',  clase: 'badge-info'    },
    completada : { label: 'Completada',  clase: 'badge-success' },
    cancelada  : { label: 'Cancelada',   clase: 'badge-danger'  }
};

// =============================================
// MODALES
// =============================================
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

document.addEventListener('click', function (e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('active');
        document.body.style.overflow = '';
    }
});

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        const active = document.querySelector('.modal-overlay.active');
        if (active) {
            active.classList.remove('active');
            document.body.style.overflow = '';
        }
    }
});

// =============================================
// VER DETALLE DE CITA
// =============================================
function verCita(citaId) {
    const cita = citasData.find(c => c.id === citaId);
    if (!cita) return;

    citaActualId = citaId;

    const estadoInfo = ESTADO_BADGE[cita.estado] || { label: cita.estado, clase: 'badge-neutral' };
    const fecha = new Date(cita.fecha + 'T12:00:00').toLocaleDateString('es-MX', {
        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
    });

    document.getElementById('modalVerCitaContent').innerHTML = `
        <div style="display:flex;flex-direction:column;gap:16px;">
            <!-- Cliente -->
            <div style="display:flex;align-items:center;gap:14px;padding:16px;
                        background:linear-gradient(135deg,var(--primary-color),var(--primary-light));
                        border-radius:var(--border-radius);color:white;">
                <div style="width:52px;height:52px;background:rgba(255,255,255,0.2);border-radius:50%;
                            display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:700;">
                    ${cita.cliente.split(' ').slice(0,2).map(p=>p[0]).join('').toUpperCase()}
                </div>
                <div style="flex:1;">
                    <div style="font-size:17px;font-weight:700;">${escHtml(cita.cliente)}</div>
                    <div style="opacity:.85;font-size:13px;">${TIPO_LABEL[cita.tipo] || cita.tipo}</div>
                </div>
                <span class="badge ${estadoInfo.clase}"
                      style="background:rgba(255,255,255,0.2);color:white;">${estadoInfo.label}</span>
            </div>

            <!-- Info -->
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Fecha</span>
                    <span class="info-value" style="text-transform:capitalize;">${fecha}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Hora</span>
                    <span class="info-value" style="font-size:20px;font-weight:700;color:var(--accent-color);">
                        ${escHtml(cita.hora)}
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">Tipo</span>
                    <span class="info-value">${TIPO_LABEL[cita.tipo] || cita.tipo}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Estado</span>
                    <span class="info-value">
                        <span class="badge ${estadoInfo.clase}">${estadoInfo.label}</span>
                    </span>
                </div>
            </div>

            ${cita.comentarios ? `
            <div style="background:#f8fafc;border-left:3px solid var(--accent-color);
                        border-radius:var(--border-radius-sm);padding:14px;">
                <div style="font-size:11px;color:var(--text-secondary);text-transform:uppercase;
                            letter-spacing:.5px;font-weight:600;margin-bottom:6px;">Agenda / Comentarios</div>
                <div style="color:var(--text-primary);line-height:1.6;">${escHtml(cita.comentarios)}</div>
            </div>` : ''}
        </div>
    `;

    // Mostrar/ocultar botones según estado
    const btnCompletar = document.getElementById('btnCompletarCita');
    const btnCancelar  = document.getElementById('btnCancelarCita');
    const esProgramada = cita.estado === 'programada';
    btnCompletar.style.display = esProgramada ? '' : 'none';
    btnCancelar.style.display  = esProgramada ? '' : 'none';

    openModal('modalVerCita');
}

// =============================================
// ABRIR DÍA (citas de un día específico)
// =============================================
function abrirDia(dia, mes, anio) {
    const fechaStr = `${anio}-${String(mes).padStart(2,'0')}-${String(dia).padStart(2,'0')}`;
    const citasDia = citasData.filter(c => c.fecha === fechaStr);

    const meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio',
                   'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

    document.getElementById('modalDiaTitulo').textContent =
        `${dia} de ${meses[mes - 1]} ${anio}`;

    if (citasDia.length === 0) {
        document.getElementById('modalDiaContent').innerHTML = `
            <div class="empty-state" style="padding:32px 0;">
                <div class="empty-state-icon" style="width:56px;height:56px;font-size:22px;">📅</div>
                <div class="empty-state-title" style="font-size:15px;">Sin citas este día</div>
                <p class="empty-state-description">Puedes crear una nueva cita desde el botón inferior.</p>
            </div>`;
    } else {
        const items = citasDia.map(cita => {
            const estadoInfo = ESTADO_BADGE[cita.estado] || { label: cita.estado, clase: 'badge-neutral' };
            return `
            <div style="display:flex;align-items:center;gap:12px;padding:12px 0;
                        border-bottom:1px solid var(--border-color);">
                <div style="min-width:52px;text-align:center;font-size:17px;font-weight:700;
                            color:var(--accent-color);">${escHtml(cita.hora)}</div>
                <div style="flex:1;">
                    <div style="font-weight:600;margin-bottom:2px;">${escHtml(cita.cliente)}</div>
                    <div style="font-size:12px;color:var(--text-secondary);">
                        ${TIPO_LABEL[cita.tipo] || cita.tipo}
                    </div>
                </div>
                <span class="badge ${estadoInfo.clase}" style="font-size:11px;">${estadoInfo.label}</span>
                <button class="btn btn-ghost btn-icon btn-sm" onclick="closeModal('modalDia'); verCita(${cita.id})">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>
                </button>
            </div>`;
        }).join('');

        document.getElementById('modalDiaContent').innerHTML = `<div style="padding:0 4px;">${items}</div>`;
    }

    openModal('modalDia');
}

// =============================================
// COMPLETAR CITA
// =============================================
function completarCitaActual() {
    if (!citaActualId) return;
    const cita = citasData.find(c => c.id === citaActualId);
    if (!cita) return;

    cita.estado = 'completada';
    console.log('Cita completada:', citaActualId);

    actualizarEventoCalendario(citaActualId, 'completada');
    showToast('Cita marcada como completada', 'success');
    closeModal('modalVerCita');
}

// =============================================
// CANCELAR CITA
// =============================================
function cancelarCitaActual() {
    if (!citaActualId) return;
    if (!confirm('¿Deseas cancelar esta cita?')) return;

    const cita = citasData.find(c => c.id === citaActualId);
    if (!cita) return;

    cita.estado = 'cancelada';
    console.log('Cita cancelada:', citaActualId);

    actualizarEventoCalendario(citaActualId, 'cancelada');
    showToast('Cita cancelada', 'warning');
    closeModal('modalVerCita');
}

/**
 * Actualiza visualmente el evento en el calendario (clase CSS)
 */
function actualizarEventoCalendario(citaId, nuevoEstado) {
    const eventos = document.querySelectorAll('.cal-event');
    eventos.forEach(el => {
        if (el.getAttribute('onclick') && el.getAttribute('onclick').includes(`verCita(${citaId})`)) {
            el.className = `cal-event ${nuevoEstado === 'cancelada' ? 'cancelada' : 'completada'}`;
        }
    });
}

// =============================================
// GUARDAR NUEVA CITA
// =============================================
function guardarNuevaCita() {
    const clienteEl     = document.getElementById('nuevaCitaCliente');
    const tipo          = document.getElementById('nuevaCitaTipo').value;
    const fecha         = document.getElementById('nuevaCitaFecha').value;
    const hora          = document.getElementById('nuevaCitaHora').value;
    const comentarios   = document.getElementById('nuevaCitaComentarios').value.trim();

    if (!clienteEl.value) {
        showToast('Selecciona un cliente', 'error');
        return;
    }
    if (!fecha || !hora) {
        showToast('Indica la fecha y hora de la cita', 'error');
        return;
    }

    const fechaCita = new Date(fecha + 'T' + hora);
    if (fechaCita < new Date()) {
        showToast('La fecha y hora no pueden ser en el pasado', 'error');
        return;
    }

    const nuevaCita = {
        id         : Date.now(),
        cliente_id : parseInt(clienteEl.value),
        cliente    : clienteEl.options[clienteEl.selectedIndex].text,
        tipo,
        fecha,
        hora,
        comentarios,
        estado     : 'programada'
    };

    // En producción: POST /api/citas
    console.log('Guardando cita:', nuevaCita);
    citasData.push(nuevaCita);

    showToast('Cita programada correctamente', 'success');
    closeModal('modalNuevaCita');

    // Reset form
    clienteEl.value = '';
    document.getElementById('nuevaCitaComentarios').value = '';
}

// =============================================
// TOAST NOTIFICATIONS
// =============================================
function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    const icon = type === 'error' ? '&#10007;' : type === 'warning' ? '&#9888;' : '&#10003;';

    toast.innerHTML = `
        <div class="toast-icon">${icon}</div>
        <div class="toast-message">${message}</div>
        <button class="toast-close" onclick="this.parentElement.remove()">&times;</button>
    `;
    container.appendChild(toast);

    setTimeout(() => {
        toast.classList.add('hiding');
        setTimeout(() => toast.remove(), 300);
    }, 5000);
}

// =============================================
// UTILIDADES
// =============================================
function escHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

const fechaInput = document.getElementById('fecha');
const horaSelect = document.getElementById('hora');

fechaInput.addEventListener('change', async () => {
    const fecha = fechaInput.value;
    if (!fecha) return;

    // habilitar todo primero
    [...horaSelect.options].forEach(o => {
        o.disabled = false;
        o.textContent = o.value;
    });

    const res = await fetch(`/Controller/CitaController.php?fecha=${fecha}`);
    const data = await res.json();

    if (!data.success) return;

    data.horas.forEach(horaOcupada => {
        const option = [...horaSelect.options]
            .find(o => o.value === horaOcupada);

        if (option) {
            option.disabled = true;
            option.textContent += ' (Ocupado)';
        }
    });
});