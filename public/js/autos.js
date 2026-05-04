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

/**
 * Configuración de campos adicionales según tipo de vehículo
 * Permite mostrar/ocultar el campo "Número de unidades" solo para flotillas
 */
const QUOTE_FORM_CONFIG = {
    auto:     { marcaLabel: 'Marca', modeloLabel: 'Modelo', showUnidades: false },
    moto:     { marcaLabel: 'Marca', modeloLabel: 'Modelo', showUnidades: false },
    camion:   { marcaLabel: 'Marca / Tipo', modeloLabel: 'Modelo / Tonelaje', showUnidades: false },
    flotilla: { marcaLabel: 'Marca principal', modeloLabel: 'Tipo de vehículos', showUnidades: true },
};

function updateFormForTipo(tipo) {
    const form = document.getElementById('formCotizadorAutos');
    if (!form) return;
    const cfg = QUOTE_FORM_CONFIG[tipo] || QUOTE_FORM_CONFIG['auto'];

    const marcaLabel  = form.querySelector('[data-label="marca"]');
    const modeloLabel = form.querySelector('[data-label="modelo"]');
    const unidadesRow = form.querySelector('.quote-unidades-row');

    if (marcaLabel)  marcaLabel.textContent  = cfg.marcaLabel;
    if (modeloLabel) modeloLabel.textContent = cfg.modeloLabel;
    if (unidadesRow) unidadesRow.style.display = cfg.showUnidades ? '' : 'none';
}

function initFormCotizador() {
    const form = document.getElementById('formCotizadorAutos');
    if (!form) return;

    // Inyectar fila de "Número de unidades" para flotillas
    const unidadesHTML = `
        <div class="quote-form-row quote-unidades-row" style="display:none;">
            <div class="quote-form-group" style="grid-column:1/-1">
                <label>Número de unidades</label>
                <input type="number" name="num_unidades" min="3" placeholder="Mínimo 3 unidades">
            </div>
        </div>`;
    const submitBtn = form.querySelector('[type=submit]');
    if (submitBtn) submitBtn.closest('[type=submit]') && submitBtn.insertAdjacentHTML('beforebegin', unidadesHTML);

    form.addEventListener('submit', function(e) {
        e.preventDefault();

        const nombre   = (form.querySelector('[name="nombre"]')?.value   || '').trim();
        const marca    = (form.querySelector('[name="marca"]')?.value    || '').trim();
        const modelo   = (form.querySelector('[name="modelo"]')?.value   || '').trim();
        const anio     = (form.querySelector('[name="anio"]')?.value     || '').trim();
        const telefono = (form.querySelector('[name="telefono"]')?.value || '').trim();
        const correo   = (form.querySelector('[name="correo"]')?.value   || '').trim();
        const tipoVeh  = (form.querySelector('[name="tipo_vehiculo"]')?.value || 'auto');

        if (!marca || !modelo || !anio || !telefono || !correo) {
            alert('Por favor completa todos los campos requeridos.'); return;
        }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo)) {
            alert('Ingresa un correo electrónico válido.'); return;
        }

        const btn = form.querySelector('[type=submit]');
        if (btn) { btn.disabled = true; btn.textContent = 'Enviando...'; }

        fetch('/api/vida-solicitud', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                nombre, correo,
                telefono: '+52' + telefono.replace(/\D/g, ''),
                ramo: 'autos',
                marca, modelo, anio,
                tipo_vehiculo: tipoVeh,
                fecha_preferida: ''
            })
        })
        .then(r => r.json())
        .then(d => {
            if (d.ok) {
                form.innerHTML = `<div style="text-align:center;padding:32px 16px;">
                    <div style="font-size:52px;margin-bottom:10px;">✅</div>
                    <h3 style="color:#f3c332;margin-bottom:8px;">¡Solicitud recibida!</h3>
                    <p style="color:#555;max-width:400px;margin:0 auto;">
                        Un asesor de Seguros Lux te contactará por WhatsApp y correo
                        con tu cotización personalizada para tu <strong>${marca} ${modelo} ${anio}</strong>.
                    </p>
                </div>`;
            } else {
                alert('Error: ' + (d.msg || 'Intenta de nuevo.'));
                if (btn) { btn.disabled = false; btn.textContent = 'Cotizar gratis'; }
            }
        })
        .catch(() => {
            alert('Error de conexión. Intenta de nuevo.');
            if (btn) { btn.disabled = false; btn.textContent = 'Cotizar gratis'; }
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