<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title><?php echo htmlspecialchars($titulo); ?></title>
<style>
/* ── Reset ──────────────────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: 'Segoe UI', Arial, sans-serif;
    font-size: 12px;
    color: #1e293b;
    background: #f8fafc;
}

/* ── Toolbar pantalla ───────────────────────────────────────────── */
.no-print {
    background: #0a0f1c;
    padding: 10px 32px;
    display: flex;
    gap: 12px;
    align-items: center;
    border-bottom: 3px solid #f3c332;
}
.no-print button {
    padding: 8px 20px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    border: none;
    transition: opacity .15s;
}
.no-print button:hover { opacity: .85; }
.btn-print { background: #f3c332; color: #0a0f1c; }
.btn-close { background: rgba(255,255,255,.1); color: #fff; border: 1px solid rgba(255,255,255,.25) !important; }
.no-print span { font-size: 11px; color: rgba(255,255,255,.45); margin-left: 6px; }

/* ── Header ─────────────────────────────────────────────────────── */
.header {
    background: linear-gradient(135deg, #0a0f1c 0%, #1a2744 100%);
    padding: 26px 40px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 24px;
    flex-wrap: wrap;
}
.header-brand { display: flex; align-items: center; gap: 16px; }
.header-logo {
    width: 52px;
    height: 52px;
    background: linear-gradient(135deg, #f3c332, #e0a800);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 4px 14px rgba(243,195,50,.35);
}
.header-logo svg { width: 28px; height: 28px; }
.header-title { font-size: 22px; font-weight: 900; color: #fff; letter-spacing: -.5px; }
.header-sub { font-size: 11px; color: rgba(255,255,255,.4); text-transform: uppercase; letter-spacing: .8px; margin-top: 3px; }
.header-meta { text-align: right; font-size: 11px; color: rgba(255,255,255,.55); line-height: 1.7; }
.header-meta strong { color: rgba(255,255,255,.85); }

/* ── Accent bar ─────────────────────────────────────────────────── */
.accent-bar {
    height: 4px;
    background: linear-gradient(90deg, #f3c332, #e0a800, #f3c332);
}

/* ── KPI Strip ──────────────────────────────────────────────────── */
.meta-bar {
    background: #fff;
    border-bottom: 1px solid #e2e8f0;
    padding: 16px 40px;
    display: flex;
    gap: 40px;
    flex-wrap: wrap;
    align-items: center;
}
.meta-item { display: flex; flex-direction: column; gap: 3px; }
.meta-label { font-size: 10px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: .5px; }
.meta-value { font-size: 15px; font-weight: 800; color: #0f172a; }
.meta-value.gold { color: #b8960a; }
.meta-value.green { color: #16a34a; }

/* ── Contenido ──────────────────────────────────────────────────── */
.content { padding: 24px 40px; }

/* ── Tabla ──────────────────────────────────────────────────────── */
table { width: 100%; border-collapse: collapse; margin-top: 0; }
thead tr { background: linear-gradient(90deg, #0a0f1c, #1a2744); }
thead th {
    padding: 11px 14px;
    text-align: left;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .5px;
    color: rgba(255,255,255,.85);
    white-space: nowrap;
}
/* Línea dorada bajo encabezado */
.table-wrap { border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
.table-wrap thead tr { border-bottom: 3px solid #f3c332; }
tbody tr:nth-child(even) { background: #f8fafc; }
tbody tr { transition: background .1s; }
tbody tr:hover { background: #fef9e7; }
tbody td { padding: 10px 14px; border-bottom: 1px solid #e2e8f0; font-size: 12px; vertical-align: middle; }
tbody tr:last-child td { border-bottom: none; }

/* ── Badges ─────────────────────────────────────────────────────── */
.badge {
    display: inline-flex;
    align-items: center;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .4px;
}
.badge-warning  { background: #fef3c7; color: #92400e; }
.badge-info     { background: #dbeafe; color: #1e40af; }
.badge-success  { background: #dcfce7; color: #166534; }
.badge-danger   { background: #fee2e2; color: #991b1b; }
.badge-neutral  { background: #f1f5f9; color: #475569; }

/* ── Folio / price ──────────────────────────────────────────────── */
.folio {
    font-family: 'Courier New', monospace;
    font-size: 10px;
    font-weight: 700;
    color: #0a0f1c;
    background: #fef3c7;
    padding: 2px 7px;
    border-radius: 4px;
    border: 1px solid #f3c332;
}
.price { font-weight: 800; color: #16a34a; }

/* ── Totals bar ─────────────────────────────────────────────────── */
.totals {
    background: linear-gradient(90deg, #0a0f1c, #1a2744);
    padding: 18px 40px;
    display: flex;
    justify-content: flex-end;
    gap: 56px;
    border-top: 3px solid #f3c332;
}
.total-item { text-align: right; }
.total-label { font-size: 10px; color: rgba(255,255,255,.5); text-transform: uppercase; letter-spacing: .5px; }
.total-value { font-size: 22px; font-weight: 900; color: #f3c332; margin-top: 2px; }

/* ── Footer ─────────────────────────────────────────────────────── */
.footer {
    padding: 14px 40px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #fff;
    border-top: 1px solid #e2e8f0;
}
.footer span { font-size: 10px; color: #94a3b8; }
.footer .brand { color: #b8960a; font-weight: 700; }

/* ── Print ──────────────────────────────────────────────────────── */
@media print {
    body { background: #fff; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .no-print { display: none !important; }
    @page { margin: 8mm; size: A4 landscape; }
}
</style>
</head>
<body>

<!-- Toolbar pantalla -->
<div class="no-print">
    <button class="btn-print" id="btnPrintPdf">🖨️ Imprimir / Guardar PDF</button>
    <button class="btn-close" id="btnClosePdf">✕ Cerrar</button>
    <span>Imprimir → Destino: «Guardar como PDF»</span>
</div>
<script src="/js/pdf_export_toolbar.js" defer></script>

<!-- Header con logo LuxSeguros -->
<div class="header">
    <div class="header-brand">
        <div class="header-logo">
            <svg viewBox="0 0 24 24" fill="none" stroke="#0a0f1c" stroke-width="2.5"
                 stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                <polyline points="9 12 11 14 15 10" stroke="#0a0f1c" stroke-width="2.2"/>
            </svg>
        </div>
        <div>
            <div class="header-title">LuxSeguros · EIRA</div>
            <div class="header-sub">Sistema de Gestión de Seguros</div>
        </div>
    </div>
    <div class="header-meta">
        <div><strong><?php echo htmlspecialchars($titulo); ?></strong></div>
        <div>Generado: <?php echo $fechaGen; ?></div>
        <div>Asesor: <?php echo $agenteNombre; ?></div>
        <?php if (isset($rangoFechas)): ?>
        <div>Período: <strong><?php echo htmlspecialchars($rangoFechas); ?></strong></div>
        <?php endif; ?>
    </div>
</div>
<div class="accent-bar"></div>

<!-- KPIs -->
<?php if ($tipo === 'cotizaciones' && !empty($datos)): ?>
<?php
$totalRegistros = count($datos);
$totalPrima     = array_sum(array_column($datos, 'precio'));
$pagadas        = count(array_filter($datos, static fn ($r) => ($r['estado'] ?? '') === 'pagado'));
$conversion     = $totalRegistros > 0 ? round($pagadas / $totalRegistros * 100) : 0;
?>
<div class="meta-bar">
    <div class="meta-item">
        <span class="meta-label">Total registros</span>
        <span class="meta-value"><?php echo $totalRegistros; ?></span>
    </div>
    <div class="meta-item">
        <span class="meta-label">Prima total</span>
        <span class="meta-value gold">$<?php echo number_format($totalPrima, 2); ?></span>
    </div>
    <div class="meta-item">
        <span class="meta-label">Pagadas</span>
        <span class="meta-value green"><?php echo $pagadas; ?></span>
    </div>
    <div class="meta-item">
        <span class="meta-label">Tasa pagadas</span>
        <span class="meta-value"><?php echo $conversion; ?>%</span>
    </div>
</div>
<?php elseif ($tipo === 'ranking' && !empty($datos)): ?>
<div class="meta-bar">
    <div class="meta-item">
        <span class="meta-label">Asesores</span>
        <span class="meta-value"><?php echo count($datos); ?></span>
    </div>
    <div class="meta-item">
        <span class="meta-label">Total ventas</span>
        <span class="meta-value green"><?php echo array_sum(array_column($datos, 'total_ventas')); ?></span>
    </div>
    <div class="meta-item">
        <span class="meta-label">Ingreso total</span>
        <span class="meta-value gold">$<?php echo number_format(array_sum(array_column($datos, 'ingreso_total')), 2); ?></span>
    </div>
</div>
<?php endif; ?>

<!-- Tabla -->
<div class="content">
<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <?php foreach ($cols as $col): ?>
                <th><?php echo htmlspecialchars($col); ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($filas as $fila): ?>
            <tr>
                <?php foreach ($fila as $i => $celda): ?>
                <td>
                    <?php
                    $v = htmlspecialchars((string)$celda);
                    if ($tipo === 'cotizaciones' && $i === 0 && $v) {
                        echo "<span class='folio'>{$v}</span>";
                    } elseif ($tipo === 'cotizaciones' && $i === 7) {
                        $cl = match(strtolower($celda)) {
                            'pendiente'          => 'badge-warning',
                            'enviada'            => 'badge-info',
                            'aceptada','pagado'  => 'badge-success',
                            'rechazada'          => 'badge-danger',
                            default              => 'badge-neutral',
                        };
                        echo "<span class='badge {$cl}'>{$v}</span>";
                    } elseif ($tipo === 'cotizaciones' && $i === 6) {
                        echo "<span class='price'>{$v}</span>";
                    } else {
                        echo $v;
                    }
                    ?>
                </td>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</div>

<!-- Totales -->
<?php if ($tipo === 'cotizaciones' && !empty($datos)): ?>
<div class="totals">
    <div class="total-item">
        <div class="total-label">Registros</div>
        <div class="total-value" style="font-size:18px;"><?php echo $totalRegistros; ?></div>
    </div>
    <div class="total-item">
        <div class="total-label">Prima Total</div>
        <div class="total-value">$<?php echo number_format($totalPrima, 2); ?></div>
    </div>
</div>
<?php endif; ?>

<!-- Footer -->
<div class="footer">
    <span>EIRA · <span class="brand">LuxSeguros</span> — Documento generado el <?php echo $fechaGen; ?></span>
    <span>Confidencial — Uso interno</span>
</div>

</body>
</html>