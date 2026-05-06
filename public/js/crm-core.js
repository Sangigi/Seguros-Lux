/**
 * crm-core.js
 * Núcleo del SPA: router, fetch helpers, modales, toasts.
 */

const CRM = (() => {
    // ── Estado ─────────────────────────────────────────────────────────────
    let _currentSection = null;
    const _charts = {};

    // ── API base ────────────────────────────────────────────────────────────
    const API = {
        base: '/src/Controller/Api/',

        async get(endpoint, params = {}) {
            const qs = new URLSearchParams(params).toString();
            const url = this.base + endpoint + (qs ? '?' + qs : '');
            const res = await fetch(url);
            if (res.status === 401) { window.location.href = '/templates/Login.php'; return null; }
            return res.json();
        },

        async post(endpoint, body = {}) {
            const res = await fetch(this.base + endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body),
            });
            if (res.status === 401) { window.location.href = '/templates/Login.php'; return null; }
            return res.json();
        },

        async postForm(endpoint, formData) {
            const res = await fetch(this.base + endpoint, {
                method: 'POST',
                body: formData,
            });
            if (res.status === 401) { window.location.href = '/templates/Login.php'; return null; }
            return res.json();
        },
    };

    // ── Router ──────────────────────────────────────────────────────────────
    function navigate(section) {
        if (_currentSection === section) return;

        // Destruir gráficos activos de la sección anterior
        _destroyCharts();

        // Limpiar main
        const main = document.getElementById('app-main');
        const tpl  = document.getElementById('tpl-' + section);

        if (!tpl) {
            showToast('Sección no encontrada', 'error');
            return;
        }

        main.innerHTML = '';
        // Clonar el template para poder reutilizarlo si el usuario regresa
        main.appendChild(tpl.firstElementChild.cloneNode(true));

        // Actualizar nav activo
        document.querySelectorAll('.nav-spa').forEach(a => {
            a.classList.toggle('active', a.dataset.section === section);
        });

        // Actualizar URL sin recargar (pushState)
        history.pushState({ section }, '', '?s=' + section);

        _currentSection = section;

        // Llamar al módulo de la sección
        const moduleMap = {
            dashboard:    () => DashboardSection.load(),
            clientes:     () => ClientesSection.load(),
            citas:        () => CitasSection.load(),
            cotizaciones: () => CotizacionesSection.load(),
            reportes:     () => ReportesSection.load(),
        };

        moduleMap[section]?.();
    }

    function _destroyCharts() {
        Object.values(_charts).forEach(c => { try { c.destroy(); } catch (_) {} });
        Object.keys(_charts).forEach(k => delete _charts[k]);
    }

    // ── Charts helper ───────────────────────────────────────────────────────
    function createChart(id, config) {
        const canvas = document.getElementById(id);
        if (!canvas) return null;
        if (_charts[id]) _charts[id].destroy();
        _charts[id] = new Chart(canvas.getContext('2d'), config);
        return _charts[id];
    }

    // ── Modales ─────────────────────────────────────────────────────────────
    function openModal(id) {
        const el = document.getElementById(id);
        if (el) el.classList.add('active');
    }

    function closeModal(id) {
        const el = document.getElementById(id);
        if (el) el.classList.remove('active');
    }

    // ── Toasts ──────────────────────────────────────────────────────────────
    function showToast(msg, type = 'info', duration = 3500) {
        const container = document.getElementById('toastContainer');
        if (!container) return;

        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.textContent = msg;

        container.appendChild(toast);
        requestAnimationFrame(() => toast.classList.add('show'));

        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, duration);
    }

    // ── Loading state ───────────────────────────────────────────────────────
    function setLoading(containerId, msg = 'Cargando...') {
        const el = document.getElementById(containerId);
        if (el) el.innerHTML = `<div class="empty-state"><div class="empty-state-icon">⏳</div><p>${msg}</p></div>`;
    }

    function setError(containerId, msg = 'Error al cargar datos') {
        const el = document.getElementById(containerId);
        if (el) el.innerHTML = `<div class="empty-state"><div class="empty-state-icon">⚠️</div><p>${msg}</p></div>`;
    }

    // ── Formatters ──────────────────────────────────────────────────────────
    const fmt = {
        currency: v => '$' + Number(v).toLocaleString('es-MX', { minimumFractionDigits: 2 }),
        date: s => s ? new Date(s + 'T00:00').toLocaleDateString('es-MX', { day:'2-digit', month:'2-digit', year:'numeric' }) : '–',
        datetime: s => s ? new Date(s).toLocaleString('es-MX', { day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit' }) : '–',
        ucfirst: s => s ? s.charAt(0).toUpperCase() + s.slice(1) : '',
    };

    const estadoClienteConfig = {
        nuevo:          { label: 'Nuevo',          cls: 'badge-info' },
        contactado:     { label: 'Contactado',     cls: 'badge-warning' },
        con_cotizacion: { label: 'Con Cotización', cls: 'badge-success' },
        sin_contactar:  { label: 'Sin Contactar',  cls: 'badge-danger' },
        cerrado:        { label: 'Cerrado',        cls: 'badge-success' },
        perdido:        { label: 'Perdido',        cls: 'badge-neutral' },
    };

    const estadoCotConfig = {
        pendiente: { label: 'Pendiente',  cls: 'badge-neutral' },
        enviada:   { label: 'Enviada',    cls: 'badge-info' },
        aceptada:  { label: 'Aceptada',   cls: 'badge-success' },
        rechazada: { label: 'Rechazada',  cls: 'badge-danger' },
    };

    const estadoCitaConfig = {
        programada: { label: 'Programada', cls: 'badge-info' },
        completada: { label: 'Completada', cls: 'badge-success' },
        cancelada:  { label: 'Cancelada',  cls: 'badge-neutral' },
        no_asistio: { label: 'No asistió', cls: 'badge-danger' },
    };

    // ── Public API ───────────────────────────────────────────────────────────
    return {
        API,
        navigate,
        createChart,
        showToast,
        setLoading,
        setError,
        fmt,
        estadoClienteConfig,
        estadoCotConfig,
        estadoCitaConfig,
    };
})();

// Exponer helpers globales (compatibilidad con onclick en HTML)
function openModal(id)  { CRM.navigate; document.getElementById(id)?.classList.add('active'); }
function closeModal(id) { document.getElementById(id)?.classList.remove('active'); }
