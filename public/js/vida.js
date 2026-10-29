/**
 * vida.js — Landing Seguro de Vida
 * Conecta el formulario #formVida con /api/vida-solicitud
 *
 * FIX: Corregidos los accesos a box.style que aparecían como hipervínculos
 *      corruptos [box.style](http://box.style) en el código original.
 */
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('formVida');
    if (!form) return;

    // Poner fecha mínima = mañana
    const fechaInput = form.querySelector('[name="fecha_preferida"]');
    if (fechaInput) {
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        fechaInput.min = tomorrow.toISOString().split('T')[0];
        if (!fechaInput.value) fechaInput.value = tomorrow.toISOString().split('T')[0];
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const nombre      = form.querySelector('[name="nombre"]')?.value.trim()          || '';
        const correo      = form.querySelector('[name="correo"]')?.value.trim()           || '';
        const telefono    = form.querySelector('[name="telefono"]')?.value.trim()         || '';
        const fechaPref   = form.querySelector('[name="fecha_preferida"]')?.value         || '';
        const comentarios = form.querySelector('[name="comentarios"]')?.value.trim()      || '';

        if (!nombre || !correo || !telefono) {
            showMsg('error', 'Por favor completa nombre, correo y teléfono.');
            return;
        }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo)) {
            showMsg('error', 'Ingresa un correo electrónico válido.');
            return;
        }

        const btn = form.querySelector('[type=submit]');
        if (btn) { btn.disabled = true; btn.textContent = 'Enviando...'; }

        try {
            const res = await fetch('/api/vida-solicitud', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    ramo:            'vida',
                    nombre,
                    correo,
                    telefono:        telefono.replace(/\D/g, ''),
                    fecha_preferida: fechaPref,
                    comentarios,
                })
            });

            const data = await res.json();

            if (data.ok) {
                let extraMsg = data.advertencia
                    ? `<p style="color:#b45309;background:#fef3c7;border-radius:8px;padding:10px 14px;margin:12px auto 0;max-width:420px;font-size:14px;">${iconoSvg('exclamation-triangle-fill')} ${data.advertencia}</p>`
                    : '';
                form.innerHTML = `
                    <div style="text-align:center;padding:32px 0;">
                        <div style="font-size:52px;margin-bottom:12px;color:#16a34a;">${iconoSvg('check-circle-fill')}</div>
                        <h3 style="color:var(--vida-primary,#e11d48);margin-bottom:8px;">¡Solicitud recibida!</h3>
                        <p style="color:#555;max-width:420px;margin:0 auto;">
                            Un asesor de Seguros Lux te contactará pronto por WhatsApp y correo
                            para orientarte sobre el plan de vida ideal para tu familia.
                        </p>${extraMsg}
                    </div>`;
            } else if (data.citas_activas) {
                showMsg('error', data.msg);
                if (btn) { btn.disabled = false; btn.textContent = 'Solicitar Asesoría'; }
            } else {
                showMsg('error', data.msg || 'Ocurrió un error. Intenta de nuevo.');
                if (btn) { btn.disabled = false; btn.textContent = 'Solicitar Asesoría'; }
            }
        } catch {
            showMsg('error', 'Error de conexión. Intenta de nuevo o llámanos.');
            if (btn) { btn.disabled = false; btn.textContent = 'Solicitar Asesoría'; }
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
        box.style.color      = type === 'error' ? '#991b1b'  : '#065f46';
        box.textContent = msg;
    }
});