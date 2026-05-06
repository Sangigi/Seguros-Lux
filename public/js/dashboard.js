/**
 * Dashboard JavaScript
 * CRM para Agentes de Seguros de Autos
 * 
 * Contiene la lógica para:
 * - Gráficos con Canvas (sin librerías externas)
 * - Animaciones
 * - Interactividad del dashboard
 */

// =============================================
// CONFIGURACIÓN DE COLORES
// =============================================
const COLORS = {
    primary: '#0ea5e9',
    primaryDark: '#0284c7',
    success: '#10b981',
    warning: '#f59e0b',
    danger: '#ef4444',
    info: '#3b82f6',
    neutral: '#64748b',
    background: '#f1f5f9',
    text: '#1e293b',
    textLight: '#64748b',
    gridLine: '#e2e8f0'
};

// =============================================
// CLASE PARA GRÁFICOS CON CANVAS
// =============================================
class SimpleChart {
    constructor(canvasId, options = {}) {
        this.canvas = document.getElementById(canvasId);
        if (!this.canvas) return;
        
        this.ctx = this.canvas.getContext('2d');
        this.options = {
            padding: 50,
            animationDuration: 800,
            ...options
        };
        
        // Ajustar resolución para pantallas retina
        this.setupCanvas();
    }

    setupCanvas() {
        const dpr = window.devicePixelRatio || 1;
        const rect = this.canvas.parentElement.getBoundingClientRect();
        
        this.canvas.width = rect.width * dpr;
        this.canvas.height = 300 * dpr;
        this.canvas.style.width = rect.width + 'px';
        this.canvas.style.height = '300px';
        
        this.ctx.scale(dpr, dpr);
        this.width = rect.width;
        this.height = 300;
    }

    // Limpiar canvas
    clear() {
        this.ctx.clearRect(0, 0, this.width, this.height);
    }

    // Dibujar texto
    drawText(text, x, y, options = {}) {
        const {
            color = COLORS.text,
            font = '12px Segoe UI',
            align = 'center',
            baseline = 'middle'
        } = options;

        this.ctx.fillStyle = color;
        this.ctx.font = font;
        this.ctx.textAlign = align;
        this.ctx.textBaseline = baseline;
        this.ctx.fillText(text, x, y);
    }

    // Animar valor
    animate(callback, duration = this.options.animationDuration) {
        const startTime = performance.now();
        
        const step = (currentTime) => {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            const easeProgress = this.easeOutCubic(progress);
            
            callback(easeProgress);
            
            if (progress < 1) {
                requestAnimationFrame(step);
            }
        };
        
        requestAnimationFrame(step);
    }

    // Función de suavizado
    easeOutCubic(x) {
        return 1 - Math.pow(1 - x, 3);
    }
}

// =============================================
// GRÁFICO DE BARRAS
// =============================================
class BarChart extends SimpleChart {
    constructor(canvasId, data, options = {}) {
        super(canvasId, options);
        this.data = data;
        this.options = {
            barColor: COLORS.primary,
            barHoverColor: COLORS.primaryDark,
            showValues: true,
            ...this.options
        };
    }

    draw() {
        if (!this.canvas) return;
        
        this.animate((progress) => {
            this.clear();
            this.drawBars(progress);
        });
    }

    drawBars(progress = 1) {
        const { padding } = this.options;
        const chartWidth = this.width - padding * 2;
        const chartHeight = this.height - padding * 2;

        const barCount = this.data.length;
        if (!barCount) return;

        const barWidth = (chartWidth / barCount) * 0.6;
        const gap      = (chartWidth / barCount) * 0.4;

        // Encontrar valor máximo — si todo es 0 mostrar mensaje
        const rawMax = Math.max(...this.data.map(d => d.total));
        if (rawMax === 0) {
            this.drawText('Sin datos para este período', this.width / 2, this.height / 2, {
                color: COLORS.textLight, font: '13px Segoe UI'
            });
            // Aún dibujamos etiquetas del eje X
            this.data.forEach((item, index) => {
                const x = padding + (index * (barWidth + gap)) + gap / 2;
                this.drawText(item.dia, x + barWidth / 2, this.height - padding + 20, {
                    color: COLORS.textLight, font: '11px Segoe UI'
                });
            });
            return;
        }
        const maxValue = rawMax * 1.2;

        // Dibujar líneas de guía
        this.drawGridLines(maxValue, chartHeight, padding);

        // Dibujar barras
        this.data.forEach((item, index) => {
            const x = padding + (index * (barWidth + gap)) + gap / 2;
            const barHeight = (item.total / maxValue) * chartHeight * progress;
            const y = this.height - padding - barHeight;

            // Etiqueta del eje X siempre
            this.drawText(item.dia, x + barWidth / 2, this.height - padding + 20, {
                color: COLORS.textLight, font: '11px Segoe UI'
            });

            if (barHeight <= 0) return; // sin barra si valor es 0

            // Sombra
            this.ctx.fillStyle = 'rgba(0, 0, 0, 0.05)';
            this.ctx.fillRect(x + 3, y + 3, barWidth, barHeight);

            // Barra con gradiente
            const gradient = this.ctx.createLinearGradient(x, y, x, y + barHeight);
            gradient.addColorStop(0, this.options.barColor);
            gradient.addColorStop(1, this.options.barHoverColor);

            this.ctx.fillStyle = gradient;
            this.ctx.beginPath();
            this.roundRect(x, y, barWidth, barHeight, 4);
            this.ctx.fill();
            
            // Valor encima de la barra
            if (this.options.showValues && progress === 1) {
                this.drawText(item.total.toString(), x + barWidth / 2, y - 10, {
                    color: COLORS.text,
                    font: 'bold 12px Segoe UI'
                });
            }
        });
    }

    drawGridLines(maxValue, chartHeight, padding) {
        const lineCount = 5;
        
        for (let i = 0; i <= lineCount; i++) {
            const y = padding + (chartHeight / lineCount) * i;
            const value = Math.round(maxValue - (maxValue / lineCount) * i);
            
            // Línea
            this.ctx.strokeStyle = COLORS.gridLine;
            this.ctx.lineWidth = 1;
            this.ctx.beginPath();
            this.ctx.moveTo(padding, y);
            this.ctx.lineTo(this.width - padding, y);
            this.ctx.stroke();
            
            // Valor
            this.drawText(value.toString(), padding - 15, y, {
                color: COLORS.textLight,
                font: '10px Segoe UI',
                align: 'right'
            });
        }
    }

    roundRect(x, y, width, height, radius) {
        this.ctx.moveTo(x + radius, y);
        this.ctx.lineTo(x + width - radius, y);
        this.ctx.quadraticCurveTo(x + width, y, x + width, y + radius);
        this.ctx.lineTo(x + width, y + height);
        this.ctx.lineTo(x, y + height);
        this.ctx.lineTo(x, y + radius);
        this.ctx.quadraticCurveTo(x, y, x + radius, y);
    }
}

// =============================================
// GRÁFICO DE LÍNEAS
// =============================================
class LineChart extends SimpleChart {
    constructor(canvasId, data, options = {}) {
        super(canvasId, options);
        this.data = data;
        this.options = {
            lines: [
                { key: 'cotizaciones', color: COLORS.primary, label: 'Cotizaciones' },
                { key: 'ventas', color: COLORS.success, label: 'Ventas' }
            ],
            ...this.options
        };
    }

    draw() {
        if (!this.canvas) return;
        
        this.animate((progress) => {
            this.clear();
            this.drawLines(progress);
        });
    }

    drawLines(progress = 1) {
        const { padding } = this.options;

        // Necesitamos al menos 2 puntos
        if (!this.data || this.data.length < 2) {
            this.drawText('Sin datos suficientes', this.width / 2, this.height / 2, {
                color: COLORS.textLight, font: '13px Segoe UI'
            });
            return;
        }

        const chartWidth  = this.width  - padding * 2;
        const chartHeight = this.height - padding * 2 - 30;

        // Encontrar valor máximo
        let maxValue = 0;
        this.options.lines.forEach(line => {
            const max = Math.max(...this.data.map(d => d[line.key] || 0));
            if (max > maxValue) maxValue = max;
        });

        // Si todo es 0, mostrar mensaje
        if (maxValue === 0) {
            this.drawText('Sin datos para este período', this.width / 2, this.height / 2, {
                color: COLORS.textLight, font: '13px Segoe UI'
            });
            return;
        }

        maxValue *= 1.2;
        
        // Dibujar líneas de guía
        this.drawGridLines(maxValue, chartHeight, padding);
        
        // Dibujar cada línea
        this.options.lines.forEach(lineConfig => {
            this.drawLine(lineConfig, chartWidth, chartHeight, padding, maxValue, progress);
        });
        
        // Dibujar etiquetas del eje X
        const pointSpacing = chartWidth / (this.data.length - 1);
        this.data.forEach((item, index) => {
            const x = padding + index * pointSpacing;
            this.drawText(item.mes, x, this.height - padding + 20, {
                color: COLORS.textLight,
                font: '11px Segoe UI'
            });
        });
        
        // Dibujar leyenda
        this.drawLegend();
    }

    drawLine(lineConfig, chartWidth, chartHeight, padding, maxValue, progress) {
        // Guard: necesitamos al menos 2 puntos para calcular el espaciado
        if (!this.data || this.data.length < 2) return;

        const pointSpacing = chartWidth / (this.data.length - 1);
        const points = [];
        
        // Calcular puntos
        this.data.forEach((item, index) => {
            const x = padding + index * pointSpacing;
            const y = this.height - padding - ((item[lineConfig.key] || 0) / maxValue) * chartHeight;
            points.push({ x, y, value: item[lineConfig.key] || 0 });
        });
        
        const progressPoints = Math.max(1, Math.ceil(points.length * progress));

        // Dibujar área bajo la curva
        this.ctx.fillStyle = lineConfig.color + '20';
        this.ctx.beginPath();
        this.ctx.moveTo(points[0].x, this.height - padding);
        
        for (let i = 0; i < progressPoints; i++) {
            this.ctx.lineTo(points[i].x, points[i].y);
        }
        
        this.ctx.lineTo(points[progressPoints - 1].x, this.height - padding);
        this.ctx.closePath();
        this.ctx.fill();
        
        // Dibujar línea
        this.ctx.strokeStyle = lineConfig.color;
        this.ctx.lineWidth = 3;
        this.ctx.lineCap = 'round';
        this.ctx.lineJoin = 'round';
        this.ctx.beginPath();
        
        for (let i = 0; i < progressPoints; i++) {
            if (i === 0) {
                this.ctx.moveTo(points[i].x, points[i].y);
            } else {
                this.ctx.lineTo(points[i].x, points[i].y);
            }
        }
        this.ctx.stroke();
        
        // Dibujar puntos
        for (let i = 0; i < progressPoints; i++) {
            this.ctx.fillStyle = '#fff';
            this.ctx.beginPath();
            this.ctx.arc(points[i].x, points[i].y, 6, 0, Math.PI * 2);
            this.ctx.fill();
            
            this.ctx.fillStyle = lineConfig.color;
            this.ctx.beginPath();
            this.ctx.arc(points[i].x, points[i].y, 4, 0, Math.PI * 2);
            this.ctx.fill();
        }
    }

    drawGridLines(maxValue, chartHeight, padding) {
        const lineCount = 5;
        
        for (let i = 0; i <= lineCount; i++) {
            const y = padding + (chartHeight / lineCount) * i;
            const value = Math.round(maxValue - (maxValue / lineCount) * i);
            
            this.ctx.strokeStyle = COLORS.gridLine;
            this.ctx.lineWidth = 1;
            this.ctx.setLineDash([5, 5]);
            this.ctx.beginPath();
            this.ctx.moveTo(padding, y);
            this.ctx.lineTo(this.width - padding, y);
            this.ctx.stroke();
            this.ctx.setLineDash([]);
            
            this.drawText(value.toString(), padding - 15, y, {
                color: COLORS.textLight,
                font: '10px Segoe UI',
                align: 'right'
            });
        }
    }

    drawLegend() {
        const legendY = 20;
        let legendX = this.width - this.options.padding;
        
        this.options.lines.slice().reverse().forEach(line => {
            const textWidth = this.ctx.measureText(line.label).width;
            legendX -= textWidth + 30;
            
            // Círculo de color
            this.ctx.fillStyle = line.color;
            this.ctx.beginPath();
            this.ctx.arc(legendX, legendY, 5, 0, Math.PI * 2);
            this.ctx.fill();
            
            // Texto
            this.drawText(line.label, legendX + 15, legendY, {
                color: COLORS.text,
                font: '11px Segoe UI',
                align: 'left'
            });
        });
    }
}

// =============================================
// GRÁFICO DE BARRAS HORIZONTALES
// =============================================
class HorizontalBarChart extends SimpleChart {
    constructor(canvasId, data, options = {}) {
        super(canvasId, options);
        this.data = data;
        this.options = {
            colors: [COLORS.primary, COLORS.success, COLORS.warning, COLORS.info, COLORS.danger],
            ...this.options
        };
    }

    draw() {
        if (!this.canvas) return;
        
        this.animate((progress) => {
            this.clear();
            this.drawBars(progress);
        });
    }

    drawBars(progress = 1) {
        const { padding } = this.options;
        const chartWidth = this.width - padding * 2 - 80; // Espacio para etiquetas
        const chartHeight = this.height - padding * 2;
        
        const barCount = this.data.length;
        const barHeight = (chartHeight / barCount) * 0.7;
        const gap = (chartHeight / barCount) * 0.3;
        
        const maxValue = Math.max(...this.data.map(d => d.total));
        
        this.data.forEach((item, index) => {
            const y = padding + (index * (barHeight + gap));
            const barWidth = (item.total / maxValue) * chartWidth * progress;
            const x = padding + 80;
            
            // Etiqueta
            this.drawText(item.tipo, padding + 75, y + barHeight / 2, {
                color: COLORS.text,
                font: '12px Segoe UI',
                align: 'right'
            });
            
            // Fondo de barra
            this.ctx.fillStyle = COLORS.gridLine;
            this.ctx.beginPath();
            this.roundRect(x, y, chartWidth, barHeight, 4);
            this.ctx.fill();
            
            // Barra
            const color = this.options.colors[index % this.options.colors.length];
            const gradient = this.ctx.createLinearGradient(x, y, x + barWidth, y);
            gradient.addColorStop(0, color);
            gradient.addColorStop(1, color + 'cc');
            
            this.ctx.fillStyle = gradient;
            this.ctx.beginPath();
            this.roundRect(x, y, barWidth, barHeight, 4);
            this.ctx.fill();
            
            // Valor
            if (progress === 1) {
                this.drawText(item.total.toString(), x + barWidth + 10, y + barHeight / 2, {
                    color: COLORS.text,
                    font: 'bold 12px Segoe UI',
                    align: 'left'
                });
            }
        });
    }

    roundRect(x, y, width, height, radius) {
        this.ctx.moveTo(x + radius, y);
        this.ctx.lineTo(x + width - radius, y);
        this.ctx.quadraticCurveTo(x + width, y, x + width, y + radius);
        this.ctx.lineTo(x + width, y + height - radius);
        this.ctx.quadraticCurveTo(x + width, y + height, x + width - radius, y + height);
        this.ctx.lineTo(x + radius, y + height);
        this.ctx.quadraticCurveTo(x, y + height, x, y + height - radius);
        this.ctx.lineTo(x, y + radius);
        this.ctx.quadraticCurveTo(x, y, x + radius, y);
    }
}

// =============================================
// GRÁFICO DE DONA
// =============================================
class DonutChart extends SimpleChart {
    constructor(canvasId, data, options = {}) {
        super(canvasId, options);
        this.data = data;
        this.options = {
            colors: [COLORS.success, COLORS.danger],
            labels: ['Asegurados', 'Sin Seguro'],
            ...this.options
        };
    }

    draw() {
        if (!this.canvas) return;
        
        this.animate((progress) => {
            this.clear();
            this.drawDonut(progress);
        });
    }

    drawDonut(progress = 1) {
        const centerX = this.width / 2;
        const centerY = this.height / 2;
        const outerRadius = Math.min(this.width, this.height) / 2 - 50;
        const innerRadius = outerRadius * 0.6;
        
        const total = this.data.asegurados + this.data.no_asegurados;
        const values = [this.data.asegurados, this.data.no_asegurados];
        
        let currentAngle = -Math.PI / 2;
        
        values.forEach((value, index) => {
            const sliceAngle = (value / total) * Math.PI * 2 * progress;
            
            // Dibujar segmento
            this.ctx.fillStyle = this.options.colors[index];
            this.ctx.beginPath();
            this.ctx.arc(centerX, centerY, outerRadius, currentAngle, currentAngle + sliceAngle);
            this.ctx.arc(centerX, centerY, innerRadius, currentAngle + sliceAngle, currentAngle, true);
            this.ctx.closePath();
            this.ctx.fill();
            
            // Etiqueta del segmento
            if (progress === 1) {
                const labelAngle = currentAngle + sliceAngle / 2;
                const labelRadius = outerRadius + 25;
                const labelX = centerX + Math.cos(labelAngle) * labelRadius;
                const labelY = centerY + Math.sin(labelAngle) * labelRadius;
                
                const percentage = Math.round((value / total) * 100);
                this.drawText(`${percentage}%`, labelX, labelY, {
                    color: COLORS.text,
                    font: 'bold 14px Segoe UI'
                });
            }
            
            currentAngle += sliceAngle;
        });
        
        // Texto central
        if (progress === 1) {
            this.drawText(total.toString(), centerX, centerY - 10, {
                color: COLORS.text,
                font: 'bold 32px Segoe UI'
            });
            this.drawText('Total Clientes', centerX, centerY + 15, {
                color: COLORS.textLight,
                font: '12px Segoe UI'
            });
        }
        
        // Leyenda
        this.drawLegend(values, total);
    }

    drawLegend(values, total) {
        const legendY = this.height - 30;
        const legendStartX = this.width / 2 - 100;
        
        this.options.labels.forEach((label, index) => {
            const x = legendStartX + index * 120;
            
            // Cuadro de color
            this.ctx.fillStyle = this.options.colors[index];
            this.ctx.fillRect(x, legendY - 6, 12, 12);
            
            // Texto
            this.drawText(`${label}: ${values[index]}`, x + 18, legendY, {
                color: COLORS.text,
                font: '11px Segoe UI',
                align: 'left'
            });
        });
    }
}

// =============================================
// INICIALIZACIÓN DE GRÁFICOS
// =============================================
function initDashboardCharts() {
    // Gráfico 1: Clientes por día (barras verticales)
    if (typeof datosClientesPorDia !== 'undefined') {
        const chartClientes = new BarChart('chartClientesDia', datosClientesPorDia, {
            barColor: COLORS.primary,
            barHoverColor: COLORS.primaryDark
        });
        chartClientes.draw();
    }
    
    // Gráfico 2: Cotizaciones vs Ventas (líneas)
    if (typeof datosCotizacionesVentas !== 'undefined') {
        const chartCotizaciones = new LineChart('chartCotizacionesVentas', datosCotizacionesVentas, {
            lines: [
                { key: 'cotizaciones', color: COLORS.primary, label: 'Cotizaciones' },
                { key: 'ventas', color: COLORS.success, label: 'Ventas' }
            ]
        });
        chartCotizaciones.draw();
    }
    
    // Gráfico 3: Vehículos más cotizados (barras horizontales)
    if (typeof datosVehiculosCotizados !== 'undefined') {
        const chartVehiculos = new HorizontalBarChart('chartVehiculos', datosVehiculosCotizados);
        chartVehiculos.draw();
    }
    
    // Gráfico 4: Estado de clientes (dona)
    if (typeof datosEstadoClientes !== 'undefined') {
        const chartEstado = new DonutChart('chartEstadoClientes', datosEstadoClientes, {
            colors: [COLORS.success, COLORS.warning],
            labels: ['Asegurados', 'Sin Seguro']
        });
        chartEstado.draw();
    }
}

// =============================================
// UTILIDADES GLOBALES
// =============================================

/**
 * Mostrar notificación toast
 */
function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `
        <div class="toast-icon">
            ${type === 'success' ? '&#10003;' : type === 'error' ? '&#10007;' : '&#9888;'}
        </div>
        <div class="toast-message">${message}</div>
        <button class="toast-close" onclick="this.parentElement.remove()">&times;</button>
    `;
    
    container.appendChild(toast);
    
    // Auto-eliminar después de 5 segundos
    setTimeout(() => {
        toast.classList.add('hiding');
        setTimeout(() => toast.remove(), 300);
    }, 5000);
}

/**
 * Formatear número con separadores de miles
 */
function formatNumber(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

/**
 * Formatear moneda
 */
function formatCurrency(amount) {
    return '$' + formatNumber(parseFloat(amount).toFixed(2));
}

// Redibujar gráficos al cambiar tamaño de ventana
let resizeTimeout;
window.addEventListener('resize', function() {
    clearTimeout(resizeTimeout);
    resizeTimeout = setTimeout(function() {
        initDashboardCharts();
    }, 250);
});