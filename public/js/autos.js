/**
 * autos.js — Landing Seguros Autos
 *
 * 1. Quote tabs (cotizador inline del hero)
 * 2. Form cotizador: envío a /api/vida-solicitud con correo
 * 3. Plan tabs + renderizado dinámico de planes por tipo de vehículo
 */

// ── SVG helpers ───────────────────────────────────────────────────────────────
const SVG_CHECK = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none"
    stroke="currentColor" stroke-width="3" stroke-linecap="round">
    <polyline points="20 6 9 17 4 12"/></svg>`;
const SVG_X = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none"
    stroke="currentColor" stroke-width="3" stroke-linecap="round">
    <line x1="18" y1="6" x2="6" y2="18"/>
    <line x1="6" y1="6" x2="18" y2="18"/></svg>`;

// ── Datos de planes por tipo de vehículo ──────────────────────────────────────
const PLANES = {
    autos: {
        nota: '*precio referencial sedán 2020',
        plans: [
            {
                nombre: 'Básico', tagline: 'Lo esencial por la ley.',
                precio: '3,200', popular: false,
                incluidas: ['Daños a terceros', 'Asistencia vial 24/7'],
                excluidas: ['Robo total', 'Daños materiales', 'Auto sustituto'],
            },
            {
                nombre: 'Amplio', tagline: 'Equilibrio ideal entre cobertura y precio.',
                precio: '6,800', popular: true,
                incluidas: ['Robo total', 'Daños a terceros y materiales', 'Asistencia vial 24/7', 'Gastos médicos ocupantes'],
                excluidas: ['Auto sustituto y cero deducible'],
            },
            {
                nombre: 'Premium', tagline: 'Tranquilidad total, sin sorpresas.',
                precio: '11,500', popular: false,
                incluidas: ['Todo lo del plan Amplio', 'Auto sustituto durante reparación', 'Cero deducible en cristales', 'Cobertura en EUA y Canadá'],
                excluidas: [],
            },
        ],
    },
    motos: {
        nota: '*precio referencial moto 250cc 2021',
        plans: [
            {
                nombre: 'Básico', tagline: 'Protección mínima legal.',
                precio: '1,800', popular: false,
                incluidas: ['Responsabilidad Civil', 'Asistencia vial 24/7'],
                excluidas: ['Robo total', 'Daños a la moto', 'Equipo del piloto'],
            },
            {
                nombre: 'Amplio', tagline: 'Cobertura completa para tu moto.',
                precio: '3,500', popular: true,
                incluidas: ['Responsabilidad Civil', 'Robo total', 'Daños materiales', 'Asistencia vial 24/7', 'Gastos médicos piloto'],
                excluidas: ['Equipo premium y accesorios'],
            },
             {
                nombre: 'Amplio', tagline: 'Cobertura completa para tu moto.',
                precio: '3,500', popular: true,
                incluidas: ['Responsabilidad Civil', 'Robo total', 'Daños materiales', 'Asistencia vial 24/7', 'Gastos médicos piloto'],
                excluidas: ['Equipo premium y accesorios'],
            },
            {
                nombre: 'Premium', tagline: 'Máxima protección para motociclistas.',
                precio: '5,800', popular: false,
                incluidas: ['Todo lo del plan Amplio', 'Casco y equipo del piloto', 'Moto sustituta', 'Cobertura fuera de México'],
                excluidas: [],
            },
        ],
    },
    camiones: {
        nota: '*precio referencial camión 3.5 tons 2019',
        plans: [
            {
                nombre: 'Básico', tagline: 'Cobertura legal obligatoria.',
                precio: '8,500', popular: false,
                incluidas: ['Responsabilidad Civil carga', 'Asistencia en carretera'],
                excluidas: ['Robo de unidad', 'Daños a la carga', 'Responsabilidad de carga'],
            },
            {
                nombre: 'Amplio', tagline: 'Protege tu unidad y tu negocio.',
                precio: '16,000', popular: true,
                incluidas: ['RC ampliada', 'Robo total de unidad', 'Daños materiales', 'Asistencia en carretera 24/7', 'Gastos médicos ocupantes'],
                excluidas: ['Seguro de carga / mercancía'],
            },
            {
                nombre: 'Premium', tagline: 'Cobertura total para tu flota.',
                precio: '24,000', popular: false,
                incluidas: ['Todo lo del plan Amplio', 'Seguro de carga incluido', 'Unidad sustituta', 'Rastreo GPS incluido', 'Cobertura internacional'],
                excluidas: [],
            },
        ],
    },
    flotillas: {
        nota: '*precio promedio por unidad, mínimo 3 vehículos',
        plans: [
            {
                nombre: 'Básico', tagline: 'Protección esencial para tu flotilla.',
                precio: '2,900', popular: false,
                incluidas: ['RC por unidad', 'Asistencia vial 24/7'],
                excluidas: ['Robo total', 'Daños materiales', 'Reemplazo de unidad'],
            },
            {
                nombre: 'Flotilla Plus', tagline: 'El favorito de las empresas.',
                precio: '5,400', popular: true,
                incluidas: ['RC ampliada', 'Robo total', 'Daños materiales', 'Asistencia 24/7', 'Gastos médicos ocupantes', 'Descuento por volumen'],
                excluidas: ['Rastreo GPS', 'Reemplazo de unidad'],
            },
            {
                nombre: 'Empresarial', tagline: 'Gestión total de riesgos para tu empresa.',
                precio: '8,200', popular: false,
                incluidas: ['Todo lo de Flotilla Plus', 'Rastreo GPS incluido', 'Unidades sustitutas', 'Gestor de siniestros dedicado', 'Factura electrónica mensual'],
                excluidas: [],
            },
        ],
    },
};

// ── Renderizar planes ─────────────────────────────────────────────────────────
function renderPlanes(tipo) {
    const container = document.querySelector(`.plan-panel[data-panel="${tipo}"] .plan-cards`);
    const notaEl    = document.querySelector(`.plan-panel[data-panel="${tipo}"] .compare-note`);
    if (!container) return;
    const data = PLANES[tipo];
    if (!data) return;
    if (notaEl) notaEl.textContent = `${data.nota}. Los precios son referenciales y dependen de marca, modelo, año, uso y código postal.`;
    container.innerHTML = data.plans.map(plan => `
        <div class="plan-card${plan.popular ? ' popular' : ''}">
            ${plan.popular ? '<span class="plan-popular-tag">★ Lo más elegido</span>' : ''}
            <div class="plan-name">${plan.nombre}</div>
            <p class="plan-tagline">${plan.tagline}</p>
            <div class="plan-price">
                <div class="plan-price-label">Desde</div>
                <div class="plan-price-amount"><em>$</em>${plan.precio}<em style="font-size:14px;">/año</em></div>
                <div class="plan-price-note">${data.nota}</div>
            </div>
            <ul class="plan-features">
                ${plan.incluidas.map(f => `<li class="included">${SVG_CHECK} ${f}</li>`).join('')}
                ${plan.excluidas.map(f => `<li class="excluded">${SVG_X} ${f}</li>`).join('')}
            </ul>
            <a href="#cotizar" class="plan-cta">Cotizar ${plan.nombre}</a>
        </div>
    `).join('');
}

// ── Form cotizador (hero) ─────────────────────────────────────────────────────

// ── Switching de bloques de campos por tipo de vehículo ───────────────────────
const FIELD_GROUPS = {
    auto:     '.quote-fields-auto',
    moto:     '.quote-fields-moto',
    camion:   '.quote-fields-camion',
    flotilla: '.quote-fields-flotilla',
};

function switchFieldGroup(tipo) {
    const form = document.getElementById('formCotizadorAutos');
    if (!form) return;
    Object.values(FIELD_GROUPS).forEach(sel => {
        const el = form.querySelector(sel);
        if (el) el.style.display = 'none';
    });
    const active = form.querySelector(FIELD_GROUPS[tipo] || FIELD_GROUPS['auto']);
    if (active) active.style.display = '';
}

function initFormCotizador() {
    const form = document.getElementById('formCotizadorAutos');
    if (!form) return;

    // Mostrar bloque inicial
    switchFieldGroup('auto');

    form.addEventListener('submit', function(e) {
        e.preventDefault();

        const tipoVeh     = (form.querySelector('[name="tipo_vehiculo"]')?.value || 'auto');
        const nombre      = (form.querySelector('[name="nombre"]')?.value        || '').trim();
        const telefono    = (form.querySelector('[name="telefono"]')?.value      || '').trim();
        const correo      = (form.querySelector('[name="correo"]')?.value        || '').trim();
        const nacimiento  = (form.querySelector('[name="fecha_nacimiento"]')?.value || '');
        const domicilio   = (form.querySelector('[name="domicilio"]')?.value     || '').trim();
        const cp          = (form.querySelector('[name="cp"]')?.value            || '').trim();

        // Campos por tipo
        let marca = '', modelo = '', submarca = '', version = '', anio = '', extra = {};

        if (tipoVeh === 'auto') {
            marca    = (form.querySelector('[name="marca"]')?.value    || '').trim();
            submarca = (form.querySelector('[name="submarca"]')?.value || '').trim();
            modelo   = (form.querySelector('[name="modelo"]')?.value   || '').trim();
            version  = (form.querySelector('[name="version"]')?.value  || '').trim();
            anio     = (form.querySelector('[name="anio"]')?.value     || '').trim();
        } else if (tipoVeh === 'moto') {
            marca    = (form.querySelector('[name="marca_moto"]')?.value    || '').trim();
            submarca = (form.querySelector('[name="submarca_moto"]')?.value || '').trim();
            modelo   = (form.querySelector('[name="modelo_moto"]')?.value   || '').trim();
            version  = (form.querySelector('[name="version_moto"]')?.value  || '').trim();
            anio     = (form.querySelector('[name="anio_moto"]')?.value     || '').trim();
            extra.costo = (form.querySelector('[name="costo_moto"]')?.value || '').trim();
        } else if (tipoVeh === 'camion') {
            marca    = (form.querySelector('[name="marca_camion"]')?.value    || '').trim();
            submarca = (form.querySelector('[name="submarca_camion"]')?.value || '').trim();
            modelo   = (form.querySelector('[name="modelo_camion"]')?.value   || '').trim();
            version  = (form.querySelector('[name="version_camion"]')?.value  || '').trim();
            anio     = (form.querySelector('[name="anio_camion"]')?.value     || '').trim();
            extra.uso = (form.querySelector('[name="uso_camion"]')?.value    || '').trim();
        } else if (tipoVeh === 'flotilla') {
            extra.empresa      = (form.querySelector('[name="empresa"]')?.value              || '').trim();
            extra.num_unidades = (form.querySelector('[name="num_unidades"]')?.value         || '').trim();
            extra.tipo_flota   = (form.querySelector('[name="tipo_vehiculos_flotilla"]')?.value || '').trim();
        }

        if (!nombre || !telefono || !correo || !cp) {
            alert('Por favor completa nombre, teléfono, correo y código postal.'); return;
        }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo)) {
            alert('Ingresa un correo electrónico válido.'); return;
        }
        if (tipoVeh !== 'flotilla' && (!marca || !modelo)) {
            alert('Por favor indica la marca y modelo del vehículo.'); return;
        }

        const btn = form.querySelector('[type=submit]');
        if (btn) { btn.disabled = true; btn.textContent = 'Enviando...'; }

        fetch('/api/vida-solicitud', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                nombre, correo,
                telefono:       '+52' + telefono.replace(/\D/g, ''),
                ramo:           'autos',
                tipo_vehiculo:  tipoVeh,
                fecha_nacimiento: nacimiento,
                domicilio, cp,
                marca, submarca, modelo, version, anio,
                fecha_preferida: '',
                ...extra,
            })
        })
        .then(r => r.json())
        .then(d => {
            if (d.ok) {
                form.innerHTML = `<div style="text-align:center;padding:32px 16px;">
                    <div style="font-size:52px;margin-bottom:10px;">✅</div>
                    <h3 style="color:var(--accent);margin-bottom:8px;">¡Solicitud recibida!</h3>
                    <p style="color:#555;max-width:400px;margin:0 auto;">
                        Un asesor de Seguros Lux te contactará por WhatsApp y correo
                        con tu cotización personalizada.
                    </p>
                </div>`;
            } else {
                alert('Error: ' + (d.msg || 'Intenta de nuevo.'));
                if (btn) { btn.disabled = false; btn.textContent = 'Cotiza ahora'; }
            }
        })
        .catch(() => {
            alert('Error de conexión. Intenta de nuevo.');
            if (btn) { btn.disabled = false; btn.textContent = 'Cotiza ahora'; }
        });
    });
}

// ── Init ──────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {

    // 1. Quote tabs — sincronizan con el campo oculto tipo_vehiculo
    document.querySelectorAll('.quote-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.quote-tab').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            const tipo = tab.dataset.quote || 'auto';
            const hiddenTipo = document.querySelector('[name="tipo_vehiculo"]');
            if (hiddenTipo) hiddenTipo.value = tipo;
            updateFormForTipo(tipo);
        });
    });

    // 2. Plan tabs — renderizado dinámico al cambiar tab
    document.querySelectorAll('.plan-tabs .plan-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            const tipo = tab.dataset.target;
            tab.closest('.plan-tabs').querySelectorAll('.plan-tab').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            document.querySelectorAll('.plan-panel').forEach(p => p.classList.remove('active'));
            const panel = document.querySelector(`.plan-panel[data-panel="${tipo}"]`);
            if (panel) panel.classList.add('active');
            renderPlanes(tipo);
        });
    });

    // 3. Render inicial del panel activo
    renderPlanes('autos');

    // 4. Form cotizador
    initFormCotizador();

    // 5. Estado inicial del formulario
    updateFormForTipo('auto');
});