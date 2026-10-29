/**
 * gmm.js — Landing Gastos Médicos Mayores
 * Conecta el formulario #formGmm con /api/vida-solicitud
 *
 * FIX 1: Corregidos los accesos a box.style que aparecían como hipervínculos
 *         corruptos [box.style](http://box.style) en el código original.
 * FIX 2: Corregido [Date.now](http://Date.now)() → Date.now()
 */
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('formGmm');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const nombre       = form.querySelector('[name="nombre"]')?.value.trim()         || '';
        const telefono     = form.querySelector('[name="telefono"]')?.value.trim()        || '';
        const edad         = form.querySelector('[name="edad"]')?.value.trim()            || '';
        const correo       = form.querySelector('[name="correo"]')?.value.trim()          || '';
        const nacimiento   = form.querySelector('[name="fecha_nacimiento"]')?.value       || '';
        const hospitales   = form.querySelector('[name="hospitales"]')?.value.trim()      || '';
        const comentarios  = form.querySelector('[name="comentarios"]')?.value.trim()     || '';
        const fechaPref    = form.querySelector('[name="fecha_preferida"]')?.value        || '';
        const urgencia     = form.querySelector('[name="urgencia"]:checked')?.value       || 'despues';

        if (!nombre || !telefono || !nacimiento) {
            showMsg('error', 'Por favor completa nombre, WhatsApp y fecha de nacimiento.');
            return;
        }
        if (!hospitales) {
            showMsg('error', 'Por favor indica en qué hospitales te has atendido.');
            return;
        }
        if (edad && (parseInt(edad) < 18 || parseInt(edad) > 80)) {
            showMsg('error', 'La edad debe estar entre 18 y 80 años.');
            return;
        }

        const btn = form.querySelector('[type=submit]');
        if (btn) { btn.disabled = true; btn.textContent = 'Enviando...'; }

        const fechaEnvio = fechaPref || (() => {
            const d = new Date();
            d.setDate(d.getDate() + 2);
            return d.toISOString().split('T')[0];
        })();

        try {
            const res = await fetch('/api/vida-solicitud', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    ramo:            'gmm',
                    nombre,
                    correo:          correo || `sin-correo-${Date.now()}@noreply.seguroslux.mx`,
                    telefono:        telefono.replace(/\D/g, ''),
                    edad,
                    fecha_nacimiento: nacimiento,
                    hospitales,
                    comentarios,
                    fecha_preferida: fechaEnvio,
                    urgencia,
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
                        <h3 style="color:var(--gmm-primary,#0284c7);margin-bottom:8px;">¡Solicitud recibida!</h3>
                        <p style="color:#555;max-width:420px;margin:0 auto;">
                            Un asesor de Seguros Lux te contactará por WhatsApp en las próximas horas
                            con las opciones de GMM que mejor se adaptan a tu perfil.
                        </p>${extraMsg}
                    </div>`;
            } else if (data.citas_activas) {
                showMsg('error', data.msg);
                if (btn) { btn.disabled = false; btn.textContent = 'Cotizar ahora'; }
            } else {
                showMsg('error', data.msg || 'Ocurrió un error. Intenta de nuevo.');
                if (btn) { btn.disabled = false; btn.textContent = 'Cotizar ahora'; }
            }
        } catch {
            showMsg('error', 'Error de conexión. Intenta de nuevo o llámanos al 55 4123 2311.');
            if (btn) { btn.disabled = false; btn.textContent = 'Cotizar ahora'; }
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