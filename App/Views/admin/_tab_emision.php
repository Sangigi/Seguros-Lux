<?php
/**
 * _tab_emision.php — Tab "Emisión de Pólizas" para admin/index.php
 *
 * Incluir dentro del bloque `elseif ($tab === 'emision')` en admin/index.php.
 * Variables disponibles del scope padre:
 *   $db, $agenteId, $isSuperadmin, $asesores, $cspNonce
 *
 * Acciones AJAX manejadas en el switch del POST de admin/index.php:
 *   get_ventas_emitir, tomar_emision, marcar_checklist, subir_poliza_final,
 *   rechazar_docs, get_checklist
 */

// ── Docs requeridos por ramo ──────────────────────────────────────────────────
$DOCS_REQUERIDOS = [
    'autos' => [
        'ine'                 => 'INE',
        'comprobante_dom'     => 'Comp. domicilio',
        'tarjeta_circulacion' => 'T. circulación',
        'foto_coche_1'        => 'Foto vehículo 1',
        'foto_coche_2'        => 'Foto vehículo 2',
        'foto_coche_3'        => 'Foto vehículo 3',
        'comprobante_pago'    => 'Comp. de pago',
    ],
    'vida' => [
        'ine'             => 'INE',
        'comprobante_dom' => 'Comp. domicilio',
        'comprobante_pago'=> 'Comp. de pago',
    ],
    'gastos_medicos' => [
        'ine'             => 'INE',
        'comprobante_dom' => 'Comp. domicilio',
        'comprobante_pago'=> 'Comp. de pago',
    ],
];

// ── Query: ventas por emitir (estado=pagado, emision_estado != emitida) ───────
$stVentas = $db->query(
    "SELECT c.id, c.folio, c.compania, c.tipo_cobertura, c.precio,
            c.ramo, c.estado, c.emision_estado, c.emision_nota,
            c.fecha, c.fecha_fin_vigencia, c.periodicidad,
            c.emision_fecha,
            CONCAT(cl.nombre,' ',cl.apellido_paterno,' ',cl.apellido_materno) AS cliente_nombre,
            cl.id AS cliente_id,
            CONCAT(a.nombre,' ',a.apellido) AS agente_nombre,
            a.id AS agente_id,
            coord.nombre AS coord_nombre,
            (SELECT COUNT(*) FROM cotizacion_archivos ca
             WHERE ca.cotizacion_id = c.id AND ca.eliminado_en IS NULL) AS total_docs,
            (SELECT COUNT(*) FROM cotizacion_archivos ca
             WHERE ca.cotizacion_id = c.id
               AND ca.tipo_documento = 'comprobante_pago'
               AND ca.eliminado_en IS NULL) AS tiene_comprobante
            ,
            (SELECT COUNT(*) FROM cotizacion_pagos cp
             WHERE cp.cotizacion_id = c.id
               AND COALESCE(NULLIF(cp.ruta,''), NULLIF(cp.nombre_archivo,'')) IS NOT NULL) AS tiene_pago_registrado
     FROM cotizaciones c
     JOIN clientes cl ON cl.id = c.cliente_id
     JOIN agentes a   ON a.id  = c.agente_id
     LEFT JOIN agentes coord ON coord.id = c.emision_coordinador_id
     WHERE (
           c.estado = 'pagado'
           OR (SELECT COUNT(*) FROM cotizacion_archivos ca
               WHERE ca.cotizacion_id = c.id
                 AND ca.tipo_documento = 'comprobante_pago'
                 AND ca.eliminado_en IS NULL) > 0
           OR (SELECT COUNT(*) FROM cotizacion_pagos cp
               WHERE cp.cotizacion_id = c.id
                 AND COALESCE(NULLIF(cp.ruta,''), NULLIF(cp.nombre_archivo,'')) IS NOT NULL) > 0
     )
       AND (c.emision_estado IS NULL OR c.emision_estado != 'emitida')
     ORDER BY
       CASE c.emision_estado WHEN 'en_proceso' THEN 0 ELSE 1 END,
       c.fecha_creacion DESC
     LIMIT 200"
);
$ventasEmitir = $stVentas->fetchAll(\PDO::FETCH_ASSOC);

// ── Query: pólizas ya emitidas (para historial) ────────────────────────────────
$stEmitidas = $db->query(
    "SELECT c.id, c.folio, c.compania, c.precio, c.ramo,
            c.emision_fecha, c.fecha_fin_vigencia, c.periodicidad,
            CONCAT(cl.nombre,' ',cl.apellido_paterno) AS cliente_nombre,
            CONCAT(a.nombre,' ',a.apellido) AS agente_nombre,
            p.numero_poliza, p.fecha_fin AS poliza_fin, p.estado AS poliza_estado,
            DATEDIFF(COALESCE(p.fecha_fin, c.fecha_fin_vigencia), CURDATE()) AS dias_restantes
     FROM cotizaciones c
     JOIN clientes cl ON cl.id = c.cliente_id
     JOIN agentes a   ON a.id  = c.agente_id
     LEFT JOIN polizas p ON p.cotizacion_id = c.id AND p.eliminado_en IS NULL
     WHERE c.emision_estado = 'emitida'
     ORDER BY c.emision_fecha DESC
     LIMIT 300"
);
$polizasEmitidas = $stEmitidas->fetchAll(\PDO::FETCH_ASSOC);

// ── KPIs del tab ─────────────────────────────────────────────────────────────
$kpiPendientes  = count(array_filter($ventasEmitir, fn($v) => ($v['emision_estado'] ?? '') !== 'en_proceso'));
$kpiEnProceso   = count(array_filter($ventasEmitir, fn($v) => ($v['emision_estado'] ?? '') === 'en_proceso'));
$kpiEmitidas    = count($polizasEmitidas);

// Pólizas por vencer en 30 días
$stVencer = $db->query(
    "SELECT COUNT(*) FROM cotizaciones c
     LEFT JOIN polizas p ON p.cotizacion_id = c.id AND p.eliminado_en IS NULL
     WHERE c.emision_estado = 'emitida'
       AND DATEDIFF(COALESCE(p.fecha_fin, c.fecha_fin_vigencia), CURDATE()) BETWEEN 1 AND 30"
);
$kpiPorVencer = (int)$stVencer->fetchColumn();
?>

<!-- ── KPIs ──────────────────────────────────────────────────────────────── -->
<div class="kpi4" style="margin-bottom:20px;">
    <div class="stat-card warning">
        <div class="stat-card-value"><?= $kpiPendientes ?></div>
        <div class="stat-card-label">⏳ Por Emitir</div>
    </div>
    <div class="stat-card info">
        <div class="stat-card-value"><?= $kpiEnProceso ?></div>
        <div class="stat-card-label">🔄 En Proceso</div>
    </div>
    <div class="stat-card success">
        <div class="stat-card-value"><?= $kpiEmitidas ?></div>
        <div class="stat-card-label">✅ Emitidas</div>
    </div>
    <div class="stat-card <?= $kpiPorVencer > 0 ? 'danger' : 'accent' ?>">
        <div class="stat-card-value"><?= $kpiPorVencer ?></div>
        <div class="stat-card-label">🔔 Vencen en 30d</div>
    </div>
</div>

<!-- ── Sub-tabs ───────────────────────────────────────────────────────────── -->
<div style="display:flex;gap:4px;margin-bottom:20px;border-bottom:2px solid var(--border-color);">
    <?php foreach ([
        ['pendientes', '⏳ Cola de Emisión', true],
        ['historial',  '📁 Historial',       false],
        ['vencer',     '🔔 Por Vencer',      false],
    ] as [$st, $label, $def]): ?>
    <button class="admin-tab emi-sub <?= $def ? 'active' : '' ?>"
            data-sub="<?= $st ?>"
            style="border:none;cursor:pointer;">
        <?= $label ?>
    </button>
    <?php endforeach; ?>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════
     SUB-TAB: Cola de Emisión
════════════════════════════════════════════════════════════════════════════ -->
<div id="emiSub-pendientes" class="emi-panel">

    <?php if (empty($ventasEmitir)): ?>
    <div class="card" style="text-align:center;padding:48px;">
        <div style="font-size:48px;margin-bottom:12px;">🎉</div>
        <div style="font-size:16px;font-weight:700;color:var(--text-primary);">Sin ventas pendientes de emitir</div>
        <div style="font-size:13px;color:var(--text-muted);margin-top:6px;">Todas las pólizas pagadas han sido procesadas.</div>
    </div>
    <?php else: ?>

    <div class="toolbar" style="margin-bottom:14px;">
        <div class="search-bar" style="flex:1;min-width:180px;">
            <span class="search-bar-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></span>
            <input type="text" id="srchEmi" class="search-bar-input" placeholder="Folio, cliente, compañía…">
        </div>
        <select id="flEmiRamo" class="filter-select">
            <option value="">Todos los ramos</option>
            <option value="autos">🚗 Autos</option>
            <option value="vida">❤️ Vida</option>
            <option value="gastos_medicos">🏥 GMM</option>
        </select>
    </div>

    <div style="display:flex;flex-direction:column;gap:14px;" id="eListaColas">
    <?php foreach ($ventasEmitir as $v):
        $enProceso = ($v['emision_estado'] ?? '') === 'en_proceso';
        $rechazado = ($v['emision_estado'] ?? '') === 'rechazada_docs';
        $ramoLabel = ['autos'=>'🚗 Autos','vida'=>'❤️ Vida','gastos_medicos'=>'🏥 GMM'][$v['ramo']] ?? $v['ramo'];
        $periLabel = ['mensual'=>'Mensual','trimestral'=>'Trimestral','semestral'=>'Semestral','anual'=>'Anual'][$v['periodicidad']] ?? '—';
    ?>
    <div class="card emi-card"
         data-id="<?= $v['id'] ?>"
         data-folio="<?= strtolower($v['folio'] ?? '') ?>"
         data-cliente="<?= strtolower($v['cliente_nombre']) ?>"
         data-compania="<?= strtolower($v['compania']) ?>"
         data-ramo="<?= $v['ramo'] ?>"
         style="border-left:4px solid <?= $enProceso ? 'var(--accent-color)' : ($rechazado ? 'var(--danger-color)' : 'var(--warning-color)') ?>;">
        <div class="card-body" style="padding:16px 20px;">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">

                <!-- Info principal -->
                <div style="flex:1;min-width:220px;">
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;flex-wrap:wrap;">
                        <?php if (!empty($v['folio'])): ?>
                        <span class="fmono"><?= htmlspecialchars($v['folio']) ?></span>
                        <?php endif; ?>
                        <span class="badge <?= $enProceso ? 'badge-info' : ($rechazado ? 'badge-danger' : 'badge-warning') ?>">
                            <?= $enProceso ? '🔄 En proceso' : ($rechazado ? '⚠️ Docs rechazados' : '⏳ Pendiente') ?>
                        </span>
                        <span style="font-size:11px;background:var(--bg-secondary);padding:2px 8px;border-radius:4px;"><?= $ramoLabel ?></span>
                    </div>
                    <div style="font-size:15px;font-weight:700;margin-bottom:4px;"><?= htmlspecialchars($v['cliente_nombre']) ?></div>
                    <div style="font-size:12px;color:var(--text-muted);display:flex;gap:12px;flex-wrap:wrap;">
                        <span>🏢 <?= htmlspecialchars($v['compania']) ?> — <?= htmlspecialchars($v['tipo_cobertura']) ?></span>
                        <span>👤 Asesor: <?= htmlspecialchars($v['agente_nombre']) ?></span>
                        <span>💰 $<?= number_format((float)$v['precio'], 0) ?></span>
                        <span>📅 <?= $periLabel ?></span>
                    </div>
                    <?php if ($rechazado && !empty($v['emision_nota'])): ?>
                    <div style="margin-top:8px;padding:8px 12px;background:#fee2e2;border-radius:6px;font-size:12px;color:#dc2626;">
                        <strong>Motivo de rechazo:</strong> <?= htmlspecialchars($v['emision_nota']) ?>
                    </div>
                    <?php endif; ?>
                    <!-- Mini checklist rápido -->
                    <div style="margin-top:10px;display:flex;gap:6px;flex-wrap:wrap;" id="minick_<?= $v['id'] ?>">
                        <span style="font-size:11px;color:var(--text-muted);">📎 <?= $v['total_docs'] ?> doc(s) subidos</span>
                        <?php if (!(int)$v['tiene_comprobante'] && !(int)$v['tiene_pago_registrado'] && ($v['estado'] ?? '') !== 'pagado'): ?>
                        <span style="font-size:11px;color:#dc2626;font-weight:600;">⚠ Sin comp. pago</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Acciones -->
                <div style="display:flex;flex-direction:column;gap:8px;min-width:180px;align-items:flex-end;">
                    <button class="btn btn-outline btn-sm"
                            style="width:100%;"
                            data-action="emi-verChecklist"
                            data-id="<?= $v['id'] ?>"
                            data-folio="<?= htmlspecialchars($v['folio'] ?? '#'.$v['id'], ENT_QUOTES) ?>"
                            data-ramo="<?= $v['ramo'] ?>">
                        📋 Ver checklist
                    </button>
                    <?php if (!$enProceso): ?>
                    <button class="btn btn-primary btn-sm"
                            style="width:100%;"
                            data-action="emi-tomarCaso"
                            data-id="<?= $v['id'] ?>"
                            data-folio="<?= htmlspecialchars($v['folio'] ?? '#'.$v['id'], ENT_QUOTES) ?>">
                        🔄 Tomar caso
                    </button>
                    <?php else: ?>
                    <button class="btn btn-success btn-sm"
                            style="width:100%;"
                            data-action="emi-subirPoliza"
                            data-id="<?= $v['id'] ?>"
                            data-folio="<?= htmlspecialchars($v['folio'] ?? '#'.$v['id'], ENT_QUOTES) ?>"
                            data-cliente="<?= htmlspecialchars($v['cliente_nombre'], ENT_QUOTES) ?>">
                        ✅ Subir póliza final
                    </button>
                    <button class="btn btn-ghost btn-sm"
                            style="width:100%;color:var(--danger-color);"
                            data-action="emi-rechazarDocs"
                            data-id="<?= $v['id'] ?>"
                            data-folio="<?= htmlspecialchars($v['folio'] ?? '#'.$v['id'], ENT_QUOTES) ?>">
                        ⊗ Rechazar documentación
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div><!-- /emiSub-pendientes -->

<!-- ═══════════════════════════════════════════════════════════════════════════
     SUB-TAB: Historial de emitidas
════════════════════════════════════════════════════════════════════════════ -->
<div id="emiSub-historial" class="emi-panel" style="display:none;">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Pólizas Emitidas</h3>
            <span style="font-size:11px;color:var(--text-muted);"><?= count($polizasEmitidas) ?> registros</span>
        </div>
        <div class="card-body" style="padding:0;">
            <table class="adt">
                <thead>
                    <tr>
                        <th>Folio</th>
                        <th>Cliente</th>
                        <th>Asesor</th>
                        <th>Compañía</th>
                        <th>No. Póliza</th>
                        <th>Prima</th>
                        <th>Vence</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($polizasEmitidas as $p):
                    $dias = (int)($p['dias_restantes'] ?? 9999);
                    $vCol = $dias <= 7  ? '#dc2626' : ($dias <= 30 ? '#a16207' : 'var(--text-muted)');
                    $estLabel = ['vigente'=>'✅ Vigente','por_vencer'=>'⚠️ Por vencer','vencida'=>'❌ Vencida','cancelada'=>'🚫 Cancelada'][$p['poliza_estado'] ?? 'vigente'] ?? '—';
                    $estBadge = ['vigente'=>'badge-success','por_vencer'=>'badge-warning','vencida'=>'badge-danger','cancelada'=>'badge-neutral'][$p['poliza_estado'] ?? 'vigente'] ?? 'badge-neutral';
                ?>
                <tr>
                    <td><?php if (!empty($p['folio'])): ?><span class="fmono"><?= htmlspecialchars($p['folio']) ?></span><?php else: ?>—<?php endif; ?></td>
                    <td style="font-weight:600;"><?= htmlspecialchars($p['cliente_nombre']) ?></td>
                    <td style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars($p['agente_nombre']) ?></td>
                    <td><?= htmlspecialchars($p['compania']) ?></td>
                    <td><code style="font-size:11px;"><?= htmlspecialchars($p['numero_poliza'] ?? '—') ?></code></td>
                    <td><strong style="color:var(--success-color);">$<?= number_format((float)$p['precio'], 0) ?></strong></td>
                    <td>
                        <?php $fechaVence = $p['poliza_fin'] ?? $p['fecha_fin_vigencia'] ?? null; ?>
                        <?php if ($fechaVence && $fechaVence !== '0000-00-00'): ?>
                        <span style="font-size:12px;color:<?= $vCol ?>;font-weight:<?= $dias <= 30 ? '700' : '400' ?>;">
                            <?= date('d/m/Y', strtotime($fechaVence)) ?>
                        </span>
                        <?php if ($dias <= 30 && $dias > 0): ?>
                        <div style="font-size:10px;color:<?= $vCol ?>;"><?= $dias ?>d restantes</div>
                        <?php elseif ($dias <= 0): ?>
                        <div style="font-size:10px;color:#dc2626;font-weight:700;">VENCIDA</div>
                        <?php endif; ?>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td><span class="badge <?= $estBadge ?>" style="font-size:10px;"><?= $estLabel ?></span></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($polizasEmitidas)): ?>
                <tr><td colspan="8" style="text-align:center;padding:32px;color:var(--text-muted);">Sin pólizas emitidas aún</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div><!-- /emiSub-historial -->

<!-- ═══════════════════════════════════════════════════════════════════════════
     SUB-TAB: Por Vencer
════════════════════════════════════════════════════════════════════════════ -->
<div id="emiSub-vencer" class="emi-panel" style="display:none;">
    <?php
    $stPV = $db->query(
        "SELECT c.id, c.folio, c.compania, c.precio, c.ramo,
                c.fecha_fin_vigencia,
                CONCAT(cl.nombre,' ',cl.apellido_paterno) AS cliente_nombre,
                cl.correo AS cliente_correo, cl.telefono AS cliente_tel,
                CONCAT(a.nombre,' ',a.apellido) AS agente_nombre,
                a.id AS agente_id,
                p.numero_poliza,
                COALESCE(p.fecha_fin, c.fecha_fin_vigencia) AS fecha_vence,
                DATEDIFF(COALESCE(p.fecha_fin, c.fecha_fin_vigencia), CURDATE()) AS dias
         FROM cotizaciones c
         JOIN clientes cl ON cl.id = c.cliente_id
         JOIN agentes a   ON a.id  = c.agente_id
         LEFT JOIN polizas p ON p.cotizacion_id = c.id AND p.eliminado_en IS NULL
         WHERE c.emision_estado = 'emitida'
           AND DATEDIFF(COALESCE(p.fecha_fin, c.fecha_fin_vigencia), CURDATE()) BETWEEN 0 AND 30
         ORDER BY dias ASC
         LIMIT 100"
    );
    $porVencer = $stPV->fetchAll(\PDO::FETCH_ASSOC);
    ?>
    <?php if (empty($porVencer)): ?>
    <div class="card" style="text-align:center;padding:48px;">
        <div style="font-size:48px;margin-bottom:12px;">✅</div>
        <div style="font-size:16px;font-weight:700;">Sin pólizas por vencer en los próximos 30 días</div>
    </div>
    <?php else: ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">🔔 Pólizas que vencen en los próximos 30 días</h3>
            <span style="font-size:11px;background:#fee2e2;color:#dc2626;padding:3px 10px;border-radius:99px;font-weight:700;"><?= count($porVencer) ?> alertas</span>
        </div>
        <div class="card-body" style="padding:0;">
            <table class="adt">
                <thead><tr><th>Días</th><th>Cliente</th><th>Asesor</th><th>Compañía</th><th>No. Póliza</th><th>Vence</th><th>Acción</th></tr></thead>
                <tbody>
                <?php foreach ($porVencer as $pv):
                    $d = (int)$pv['dias'];
                    $urgente = $d <= 7;
                    $dCol = $urgente ? '#dc2626' : '#a16207';
                    $dBg  = $urgente ? '#fee2e2' : '#fef3c7';
                ?>
                <tr style="<?= $urgente ? 'background:rgba(220,38,38,.04);' : '' ?>">
                    <td>
                        <div style="background:<?= $dBg ?>;color:<?= $dCol ?>;font-weight:800;font-size:16px;width:42px;height:42px;border-radius:8px;display:flex;align-items:center;justify-content:center;">
                            <?= $d <= 0 ? '0' : $d ?>
                        </div>
                    </td>
                    <td>
                        <div style="font-weight:600;"><?= htmlspecialchars($pv['cliente_nombre']) ?></div>
                        <div style="font-size:11px;color:var(--text-muted);"><?= htmlspecialchars($pv['cliente_correo'] ?? '') ?></div>
                    </td>
                    <td style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars($pv['agente_nombre']) ?></td>
                    <td><?= htmlspecialchars($pv['compania']) ?></td>
                    <td><code style="font-size:11px;"><?= htmlspecialchars($pv['numero_poliza'] ?? '—') ?></code></td>
                    <td style="font-weight:700;color:<?= $dCol ?>;font-size:13px;"><?= date('d/m/Y', strtotime($pv['fecha_vence'])) ?></td>
                    <td>
                        <button class="btn btn-sm btn-outline"
                                style="white-space:nowrap;"
                                data-action="emi-alertarRenovacion"
                                data-id="<?= $pv['id'] ?>"
                                data-agente="<?= $pv['agente_id'] ?>"
                                data-folio="<?= htmlspecialchars($pv['folio'] ?? '#'.$pv['id'], ENT_QUOTES) ?>">
                            📨 Notificar asesor
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div><!-- /emiSub-vencer -->

<!-- ═══════════════════════════════════════════════════════════════════════════
     MODALES
════════════════════════════════════════════════════════════════════════════ -->

<!-- Modal: Checklist de Documentos -->
<div class="modal-overlay" id="modalEmiChecklist">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3 class="modal-title">📋 Checklist — <span id="eckFolio" style="font-family:'Courier New',monospace;color:var(--accent-color);"></span></h3>
            <button class="modal-close" data-close="modalEmiChecklist">&times;</button>
        </div>
        <div class="modal-body" id="eckBody" style="min-height:200px;">
            <div style="text-align:center;padding:40px;color:var(--text-muted);">⏳ Cargando…</div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" data-close="modalEmiChecklist">Cerrar</button>
            <button class="btn btn-ghost" id="eckBtnDescargar" style="display:none;" data-action="emi-descargarChecklist">📄 Descargar resumen PDF</button>
        </div>
    </div>
</div>

<!-- Modal: Subir Póliza Final -->
<div class="modal-overlay" id="modalEmiSubirPoliza">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">✅ Subir Póliza Final — <span id="spFolio" style="font-family:'Courier New',monospace;color:var(--accent-color);"></span></h3>
            <button class="modal-close" data-close="modalEmiSubirPoliza">&times;</button>
        </div>
        <div class="modal-body">
            <p style="font-size:13px;color:var(--text-secondary);margin-bottom:16px;">
                Al subir la póliza, el caso quedará marcado como <strong>Emitido</strong> y se notificará al asesor.
            </p>
            <input type="hidden" id="spCotId">
            <div class="form-group">
                <label class="form-label required">Número de Póliza (de la aseguradora)</label>
                <input type="text" class="form-input" id="spNumero" placeholder="Ej: GNP-2026-001234">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label required">Fecha de Inicio</label>
                    <input type="date" class="form-input" id="spFechaInicio">
                </div>
                <div class="form-group">
                    <label class="form-label required">Fecha de Fin / Vencimiento</label>
                    <input type="date" class="form-input" id="spFechaFin">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label required">Periodicidad de Pago</label>
                <select class="form-select" id="spPeriodicidad">
                    <option value="mensual">Mensual</option>
                    <option value="trimestral">Trimestral</option>
                    <option value="semestral">Semestral</option>
                    <option value="anual" selected>Anual</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Archivo de Póliza (PDF/imagen)</label>
                <div id="spDropZone"
                     style="border:2px dashed var(--border-color);border-radius:8px;padding:28px;text-align:center;cursor:pointer;transition:border-color .15s;"
                     data-action="emi-clickDropzone">
                    <div style="font-size:28px;margin-bottom:6px;">📄</div>
                    <div style="font-size:13px;font-weight:600;">Haz clic o arrastra el archivo aquí</div>
                    <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">PDF, JPG, PNG — máx 10 MB</div>
                </div>
                <input type="file" id="spFile" accept=".pdf,.jpg,.jpeg,.png" style="display:none;">
                <div id="spFileName" style="margin-top:8px;font-size:12px;color:var(--accent-color);display:none;"></div>
            </div>
            <div class="form-group">
                <label class="form-label">Notas adicionales</label>
                <textarea class="form-textarea" id="spNotas" rows="2" placeholder="Observaciones de la póliza…"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" data-close="modalEmiSubirPoliza">Cancelar</button>
            <button class="btn btn-success" id="spBtnSubir" data-action="emi-confirmarSubirPoliza">✅ Confirmar emisión</button>
        </div>
    </div>
</div>

<!-- Modal: Rechazar documentación -->
<div class="modal-overlay" id="modalEmiRechazar">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">⊗ Rechazar Documentación — <span id="rdFolio" style="font-family:'Courier New',monospace;color:var(--danger-color);"></span></h3>
            <button class="modal-close" data-close="modalEmiRechazar">&times;</button>
        </div>
        <div class="modal-body">
            <p style="font-size:13px;color:var(--text-secondary);margin-bottom:14px;">
                El asesor recibirá una notificación con el motivo del rechazo para que corrija los documentos.
            </p>
            <input type="hidden" id="rdCotId">
            <div class="form-group">
                <label class="form-label required">Motivo del rechazo</label>
                <textarea class="form-textarea" id="rdMotivo" rows="3"
                          placeholder="Ej: INE borrosa, falta comprobante de domicilio reciente…"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" data-close="modalEmiRechazar">Cancelar</button>
            <button class="btn btn-danger" data-action="emi-confirmarRechazo">⊗ Rechazar y notificar</button>
        </div>
    </div>
</div>