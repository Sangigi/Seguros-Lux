/**
 * Reportes JavaScript
 * CRM para Agentes de Seguros de Autos
 *
 * Gráficos de reportes usando Canvas puro,
 * reutilizando el mismo patrón de clases de dashboard.js
 */

// =============================================
// COLORES (igual que dashboard.js)
// =============================================
const COLORS = {
    primary    : '#0ea5e9',
    primaryDark: '#0284c7',
    success    : '#10b981',
    warning    : '#f59e0b',
    danger     : '#ef4444',
    info       : '#3b82f6',
    purple     : '#8b5cf6',
    neutral    : '#64748b',
    background : '#f1f5f9',
    text       : '#1e293b',
    textLight  : '#64748b',
    gridLine   : '#e2e8f0'
};

// =============================================
// CLASE BASE
// =============================================
class BaseChart {
    constructor(canvasId, options = {}) {
        this.canvas = document.getElementById(canvasId);
        if (!this.canvas) return;
        this.ctx = this.canvas.getContext('2d');
        this.options = { padding: 50, animationDuration: 800, ...options };
        this.setupCanvas();
    }

    setupCanvas() {
        const dpr  = window.devicePixelRatio || 1;
        const rect = this.canvas.parentElement.getBoundingClientRect();
        const h    = this.options.height || 300;

        this.canvas.width  = rect.width * dpr;
        this.canvas.height = h * dpr;
        this.canvas.style.width  = rect.width + 'px';
        this.canvas.style.height = h + 'px';

        this.ctx.scale(dpr, dpr);
        this.width  = rect.width;
        this.height = h;
    }

    clear() {
        this.ctx.clearRect(0, 0, this.width, this.height);
    }

    drawText(text, x, y, opts = {}) {
        const { color = COLORS.text, font = '12px Segoe UI', align = 'center', baseline = 'middle' } = opts;
        this.ctx.fillStyle  = color;
        this.ctx.font       = font;
        this.ctx.textAlign  = align;
        this.ctx.textBaseline = baseline;
        this.ctx.fillText(text, x, y);
    }

    animate(callback, duration = this.options.animationDuration) {
        const start = performance.now();
        const step  = (now) => {
            const progress = Math.min((now - start) / duration, 1);
            const ease     = 1 - Math.pow(1 - progress, 3);
            callback(ease);
            if (progress < 1) requestAnimationFrame(step);
        };
        requestAnimationFrame(step);
    }

    roundRect(x, y, w, h, r) {
        this.ctx.moveTo(x + r, y);
        this.ctx.lineTo(x + w - r, y);
        this.ctx.quadraticCurveTo(x + w, y, x + w, y + r);
        this.ctx.lineTo(x + w, y + h - r);
        this.ctx.quadraticCurveTo(x + w, y + h, x + w - r, y + h);
        this.ctx.lineTo(x + r, y + h);
        this.ctx.quadraticCurveTo(x, y + h, x, y + h - r);
        this.ctx.lineTo(x, y + r);
        this.ctx.quadraticCurveTo(x, y, x + r, y);
    }
}

// =============================================
// GRÁFICO DE LÍNEAS MÚLTIPLE — Evolución mensual
// =============================================
class MultiLineChart extends BaseChart {
    constructor(canvasId, data, lineConfigs, options = {}) {
        super(canvasId, options);
        this.data        = data;
        this.lineConfigs = lineConfigs; // [{key, color, label}]
    }

    draw() {
        if (!this.canvas) return;
        this.animate(p => { this.clear(); this.render(p); });
    }

    render(progress = 1) {
        const { padding } = this.options;
        const chartW = this.width  - padding * 2;
        const chartH = this.height - padding * 2 - 30;

        // Guard: necesitamos al menos 2 puntos
        if (!this.data || this.data.length < 2) {
            this.drawText('Sin datos suficientes', this.width / 2, this.height / 2, {
                color: COLORS.textLight, font: '13px Segoe UI'
            });
            return;
        }

        let maxVal = 0;
        this.lineConfigs.forEach(lc => {
            const m = Math.max(...this.data.map(d => d[lc.key] || 0));
            if (m > maxVal) maxVal = m;
        });

        // Guard: si todos los valores son 0
        if (maxVal === 0) {
            this.drawText('Sin datos para este período', this.width / 2, this.height / 2, {
                color: COLORS.textLight, font: '13px Segoe UI'
            });
            return;
        }

        maxVal *= 1.2;

        // Líneas de guía
        for (let i = 0; i <= 5; i++) {
            const y   = padding + (chartH / 5) * i;
            const val = Math.round(maxVal - (maxVal / 5) * i);
            this.ctx.strokeStyle = COLORS.gridLine;
            this.ctx.lineWidth   = 1;
            this.ctx.setLineDash([4, 4]);
            this.ctx.beginPath();
            this.ctx.moveTo(padding, y);
            this.ctx.lineTo(this.width - padding, y);
            this.ctx.stroke();
            this.ctx.setLineDash([]);
            this.drawText(val.toString(), padding - 12, y, { color: COLORS.textLight, font: '10px Segoe UI', align: 'right' });
        }

        // Etiquetas eje X
        const spacing = chartW / (this.data.length - 1);
        this.data.forEach((item, i) => {
            const x = padding + i * spacing;
            this.drawText(item.mes, x, this.height - padding + 18, { color: COLORS.textLight, font: '11px Segoe UI' });
        });

        // Cada línea
        this.lineConfigs.forEach(lc => {
            const points = this.data.map((item, i) => ({
                x: padding + i * spacing,
                y: this.height - padding - (item[lc.key] / maxVal) * chartH,
                v: item[lc.key]
            }));

            const drawn = Math.max(1, Math.ceil(points.length * progress));

            // Área
            this.ctx.fillStyle = lc.color + '18';
            this.ctx.beginPath();
            this.ctx.moveTo(points[0].x, this.height - padding);
            for (let i = 0; i < drawn; i++) this.ctx.lineTo(points[i].x, points[i].y);
            this.ctx.lineTo(points[drawn - 1].x, this.height - padding);
            this.ctx.closePath();
            this.ctx.fill();

            // Línea
            this.ctx.strokeStyle = lc.color;
            this.ctx.lineWidth   = 2.5;
            this.ctx.lineCap     = 'round';
            this.ctx.lineJoin    = 'round';
            this.ctx.beginPath();
            for (let i = 0; i < drawn; i++) {
                i === 0 ? this.ctx.moveTo(points[i].x, points[i].y)
                        : this.ctx.lineTo(points[i].x, points[i].y);
            }
            this.ctx.stroke();

            // Puntos
            for (let i = 0; i < drawn; i++) {
                this.ctx.fillStyle = '#fff';
                this.ctx.beginPath();
                this.ctx.arc(points[i].x, points[i].y, 5, 0, Math.PI * 2);
                this.ctx.fill();
                this.ctx.fillStyle = lc.color;
                this.ctx.beginPath();
                this.ctx.arc(points[i].x, points[i].y, 3.5, 0, Math.PI * 2);
                this.ctx.fill();
            }
        });

        // Leyenda
        if (progress === 1) {
            let lx = this.width - this.options.padding;
            this.lineConfigs.slice().reverse().forEach(lc => {
                const tw = this.ctx.measureText(lc.label).width;
                lx -= tw + 30;
                this.ctx.fillStyle = lc.color;
                this.ctx.beginPath();
                this.ctx.arc(lx, 18, 5, 0, Math.PI * 2);
                this.ctx.fill();
                this.drawText(lc.label, lx + 12, 18, { color: COLORS.text, font: '11px Segoe UI', align: 'left' });
            });
        }
    }
}

// =============================================
// GRÁFICO DE BARRAS HORIZONTALES — Vehículos
// =============================================
class HBarChart extends BaseChart {
    constructor(canvasId, data, colors, options = {}) {
        super(canvasId, options);
        this.data   = data;
        this.colors = colors;
    }

    draw() {
        if (!this.canvas) return;
        this.animate(p => { this.clear(); this.render(p); });
    }

    render(progress = 1) {
        const { padding } = this.options;
        const labelW = 90;
        const chartW = this.width  - padding - labelW - 40;
        const chartH = this.height - padding * 2;
        const barH   = (chartH / this.data.length) * 0.62;
        const gap    = (chartH / this.data.length) * 0.38;
        const maxVal = Math.max(...this.data.map(d => d.total));

        this.data.forEach((item, i) => {
            const y = padding + i * (barH + gap) + gap / 2;
            const x = padding + labelW;

            // Etiqueta
            this.drawText(item.tipo || item.tipo, padding + labelW - 8, y + barH / 2, {
                color: COLORS.text, font: '12px Segoe UI', align: 'right'
            });

            // Fondo
            this.ctx.fillStyle = COLORS.gridLine;
            this.ctx.beginPath();
            this.roundRect(x, y, chartW, barH, 3);
            this.ctx.fill();

            // Barra
            const bw    = (item.total / maxVal) * chartW * progress;
            const color = this.colors[i % this.colors.length];
            const grad  = this.ctx.createLinearGradient(x, y, x + bw, y);
            grad.addColorStop(0, color);
            grad.addColorStop(1, color + 'bb');
            this.ctx.fillStyle = grad;
            this.ctx.beginPath();
            this.roundRect(x, y, bw, barH, 3);
            this.ctx.fill();

            // Valor
            if (progress === 1) {
                this.drawText(item.total.toString(), x + bw + 10, y + barH / 2, {
                    color: COLORS.text, font: 'bold 12px Segoe UI', align: 'left'
                });
            }
        });
    }
}

// =============================================
// GRÁFICO DE DONA — Coberturas
// =============================================
class DonutChartR extends BaseChart {
    constructor(canvasId, data, colors, options = {}) {
        super(canvasId, options);
        this.data   = data;   // [{tipo, total}]
        this.colors = colors;
    }

    draw() {
        if (!this.canvas) return;
        this.animate(p => { this.clear(); this.render(p); });
    }

    render(progress = 1) {
        const cx    = this.width  / 2;
        const cy    = this.height / 2;
        const outer = Math.min(this.width, this.height) / 2 - 30;
        const inner = outer * 0.58;

        const total = this.data.reduce((s, d) => s + d.total, 0);
        if (total === 0) {
            this.drawText('Sin datos', this.width / 2, this.height / 2, {
                color: COLORS.textLight, font: '14px Segoe UI'
            });
            return;
        }
        let angle = -Math.PI / 2;

        this.data.forEach((item, i) => {
            const slice = (item.total / total) * Math.PI * 2 * progress;
            this.ctx.fillStyle = this.colors[i % this.colors.length];
            this.ctx.beginPath();
            this.ctx.arc(cx, cy, outer, angle, angle + slice);
            this.ctx.arc(cx, cy, inner, angle + slice, angle, true);
            this.ctx.closePath();
            this.ctx.fill();

            // Porcentaje en el segmento
            if (progress === 1) {
                const mid  = angle + slice / 2;
                const lr   = outer + 22;
                const lx   = cx + Math.cos(mid) * lr;
                const ly   = cy + Math.sin(mid) * lr;
                const pct  = Math.round(item.total / total * 100);
                this.drawText(`${pct}%`, lx, ly, { color: COLORS.text, font: 'bold 12px Segoe UI' });
            }

            angle += slice;
        });

        // Centro
        if (progress === 1) {
            this.drawText(total.toString(), cx, cy - 8, { color: COLORS.text, font: 'bold 26px Segoe UI' });
            this.drawText('ventas', cx, cy + 14, { color: COLORS.textLight, font: '11px Segoe UI' });
        }
    }
}

// =============================================
// INICIALIZAR TODOS LOS GRÁFICOS
// =============================================
function initReportesCharts() {

    // 1 — Evolución mensual (líneas: clientes, ventas)
    if (typeof datosEvolucion !== 'undefined') {
        new MultiLineChart(
            'chartEvolucion',
            datosEvolucion,
            [
                { key: 'clientes',     color: COLORS.primary, label: 'Clientes' },
                { key: 'ventas',       color: COLORS.success, label: 'Ventas'   },
                { key: 'cotizaciones', color: COLORS.warning, label: 'Cotizaciones' }
            ],
            { padding: 48 }
        ).draw();
    }

    // 2 — Vehículos (barras horizontales)
    if (typeof datosVehiculos !== 'undefined') {
        new HBarChart(
            'chartVehiculos',
            datosVehiculos,
            [COLORS.primary, COLORS.success, COLORS.warning, COLORS.info, COLORS.danger],
            { padding: 36 }
        ).draw();
    }

    // 3 — Coberturas (dona)
    if (typeof datosCoberturas !== 'undefined') {
        new DonutChartR(
            'chartCoberturas',
            datosCoberturas,
            [COLORS.primary, COLORS.success, COLORS.warning, '#8b5cf6'],
            { height: 220, padding: 30 }
        ).draw();
    }
}

// Redibujar al redimensionar
let _resizeTimer;
window.addEventListener('resize', () => {
    clearTimeout(_resizeTimer);
    _resizeTimer = setTimeout(initReportesCharts, 250);
});

// =============================================
// TOAST (por si se necesita en esta página)
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
    setTimeout(() => { toast.classList.add('hiding'); setTimeout(() => toast.remove(), 300); }, 5000);
}