/**
 * empresas.js — Landing Seguros Empresariales
 * Conecta el formulario #formEmpresas con /api/vida-solicitud (ramo: empresas)
 */
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('formEmpresas');
    if (!form) return;

    // Fecha mínima = mañana
    const fechaInput = form.querySelector('[name="fecha_preferida"]');
    if (fechaInput) {
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        const iso = tomorrow.toISOString().split('T')[0];
        fechaInput.min = iso;
        if (!fechaInput.value) fechaInput.value = iso;
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const nombre      = form.querySelector('[name="nombre"]')?.value.trim()           || '';
        const correo      = form.querySelector('[name="correo"]')?.value.trim()            || '';
        const telefono    = form.querySelector('[name="telefono"]')?.value.trim()          || '';
        const empresa     = form.querySelector('[name="empresa"]')?.value.trim()           || '';
        const girEmp      = form.querySelector('[name="giro_empresa"]')?.value.trim()      || '';
        const empleados   = form.querySelector('[name="empleados"]')?.value                || '';
        const servicios   = form.querySelector('[name="servicios"]')?.value                || '';
        const fechaPref   = form.querySelector('[name="fecha_preferida"]')?.value          || '';
        const comentarios = form.querySelector('[name="comentarios"]')?.value.trim()       || '';

        // Validación
        if (!nombre || !telefono) {
            showMsg('error', 'Por favor completa nombre y teléfono.');
            return;
        }
        if (correo && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo)) {
            showMsg('error', 'Ingresa un correo electrónico válido.');
            return;
        }
        if (!empresa) {
            showMsg('error', 'Indica el nombre de la empresa.');
            return;
        }

        const btn = form.querySelector('[type=submit]');
        if (btn) { btn.disabled = true; btn.textContent = 'Enviando…'; }

        try {
            const res = await fetch('/api/vida-solicitud', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    ramo:            'empresas',
                    nombre,
                    correo,
                    telefono:        telefono.replace(/\D/g, ''),
                    empresa,
                    giro_empresa:    girEmp,
                    empleados,
                    servicios,
                    fecha_preferida: fechaPref,
                    comentarios,
                })
            });

            const data = await res.json();

            if (data.ok) {
                const extra = data.advertencia
                    ? `<p style="color:#b45309;background:#fef3c7;border-radius:8px;padding:10px 14px;margin:12px auto 0;max-width:420px;font-size:14px;">${iconoSvg('exclamation-triangle-fill')} ${data.advertencia}</p>`
                    : '';
                form.innerHTML = `
                    <div style="text-align:center;padding:32px 0;">
                        <div style="font-size:52px;margin-bottom:12px;color:#16a34a;">${iconoSvg('check-circle-fill')}</div>
                        <h3 style="color:#7c3aed;margin-bottom:8px;">¡Solicitud recibida!</h3>
                        <p style="color:#555;max-width:420px;margin:0 auto;">
                            Un asesor empresarial de Seguros Lux se pondrá en contacto contigo
                            antes de <strong>${fechaPref ? formatFecha(fechaPref) : '24 horas'}</strong>.
                        </p>${extra}
                    </div>`;
            } else if (data.citas_activas) {
                showMsg('error', data.msg);
                if (btn) { btn.disabled = false; btn.textContent = 'Solicitar Asesoría Empresarial →'; }
            } else {
                showMsg('error', data.msg || 'Ocurrió un error. Intenta de nuevo.');
                if (btn) { btn.disabled = false; btn.textContent = 'Solicitar Asesoría Empresarial →'; }
            }
        } catch {
            showMsg('error', 'Error de conexión. Intenta de nuevo o llámanos al 55 4123 2311.');
            if (btn) { btn.disabled = false; btn.textContent = 'Solicitar Asesoría Empresarial →'; }
        }
    });

    function showMsg(type, msg) {
        let box = form.querySelector('.form-msg');
        if (!box) {
            box = document.createElement('div');
            box.className = 'form-msg';
            box.style.cssText = 'margin-bottom:14px;padding:12px 16px;border-radius:8px;font-size:14px;font-weight:500;';
            form.prepend(box);
        }
        box.style.background = type === 'error' ? '#fee2e2' : '#d1fae5';
        box.style.color      = type === 'error' ? '#991b1b' : '#065f46';
        box.textContent = msg;
    }

    function formatFecha(iso) {
        const meses = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
        const [y, m, d] = iso.split('-');
        return `${parseInt(d)} de ${meses[parseInt(m)-1]} de ${y}`;
    }
});