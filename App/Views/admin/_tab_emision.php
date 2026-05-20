<?php
/**
 * _tab_emision.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Pestaña de Emisión en el Panel Admin.
 *
 * ROLES:
 *  - coordinador / administrador : solo lectura de pólizas + sube comprobante de pago
 *  - superadmin                  : igual que coordinador + puede ver todo
 *
 * El ASESOR tiene su propio flujo en su panel de cliente/cotización.
 * ─────────────────────────────────────────────────────────────────────────────
 */

use Database\Connection;

$db = Connection::getConnection();
// Forzar visualización de errores temporalmente
ini_set('display_errors', 1);
error_reporting(E_ALL);
// ── Paginación y filtros ──────────────────────────────────────────────────────
$emiPage    = max(1, (int)($_GET['emi_pag'] ?? 1));
$emiPP      = 15;
$emiOffset  = ($emiPage - 1) * $emiPP;
$emiSearch  = trim($_GET['emi_q'] ?? '');
$emiEstado  = trim($_GET['emi_estado'] ?? '');
$emiEstatoPago = trim($_GET['emi_pago'] ?? '');

// Ramones disponibles + estados de póliza
$RAMOS_LABEL = ['autos'=>'Autos','vida'=>'Vida','gastos_medicos'=>'Gastos Médicos'];
$REQS = [
    'autos'         => ['ine', 'comprobante_dom', 'tarjeta_circulacion', 'foto_coche_1', 'foto_coche_2'],
    'vida'          => ['ine', 'comprobante_dom'],
    'gastos_medicos'=> ['ine', 'comprobante_dom'],
];
$TIPO_LABEL = [
    'ine'=>'INE','comprobante_dom'=>'Comp. domicilio','tarjeta_circulacion'=>'T. circulación',
    'foto_coche_1'=>'Foto vehículo 1','foto_coche_2'=>'Foto vehículo 2','foto_coche_3'=>'Foto vehículo 3',
    'foto_coche_4'=>'Foto vehículo 4','foto_coche_5'=>'Foto vehículo 5',
    'comprobante_pago'=>'Comprobante de pago','constancia_fiscal'=>'Constancia fiscal',
    'poliza'=>'Póliza (documento)',
];
$METODOS_PAGO = [
    'efectivo'        => 'Efectivo',
    'tarjeta_credito' => 'Tarjeta de crédito',
    'tarjeta_debito'  => 'Tarjeta de débito',
    'transferencia'   => 'Transferencia bancaria',
    'msi_3'           => '3 MSI',
    'msi_6'           => '6 MSI',
    'msi_12'          => '12 MSI',
    'msi_18'          => '18 MSI',
    'deposito_banco'  => 'Depósito en banco',
];

// ── Query de cotizaciones ─────────────────────────────────────────────────────
$where = ["1=1"];
$params = [];

if ($emiSearch !== '') {
    $where[] = "(c.folio LIKE ? OR CONCAT(cl.nombre,' ',cl.apellido_paterno) LIKE ? OR c.compania LIKE ?)";
    $like = "%{$emiSearch}%";
    $params = array_merge($params, [$like, $like, $like]);
}
if ($emiEstado !== '') {
    $where[] = "c.estado = ?";
    $params[] = $emiEstado;
}
if ($emiEstatoPago !== '') {
    $where[] = "c.estado_pago = ?";
    $params[] = $emiEstatoPago;
}

$whereSQL = implode(' AND ', $where);

$stTotal = $db->prepare(
    "SELECT COUNT(*) FROM cotizaciones c
     JOIN clientes cl ON cl.id = c.cliente_id
     WHERE {$whereSQL}"
);
$stTotal->execute($params);
$emiTotal = (int)$stTotal->fetchColumn();
$emiPages = max(1, ceil($emiTotal / $emiPP));

$stCots = $db->prepare(
    "SELECT c.id, c.folio, c.ramo, c.compania, c.tipo_cobertura,
            c.estado, c.estado_pago, c.emision_estado, c.precio, c.periodicidad,
            c.fecha, c.fecha_fin_vigencia,
            CONCAT(cl.nombre,' ',cl.apellido_paterno,' ',COALESCE(cl.apellido_materno,'')) AS cliente_nombre,
            cl.id AS cliente_id,
            CONCAT(a.nombre,' ',a.apellido) AS asesor_nombre,
            a.id AS asesor_id,
            -- Cuenta archivos subidos (incluyendo póliza)
            (SELECT COUNT(*) FROM cotizacion_archivos ca
             WHERE ca.cotizacion_id = c.id AND ca.eliminado_en IS NULL) AS total_archivos,
            -- ¿Tiene póliza subida? Susituir por la tabla polizas corregida antes
            (SELECT COUNT(*) FROM polizas p WHERE p.cotizacion_id = c.id AND p.eliminado_en IS NULL) AS tiene_poliza,
            -- NUEVO: Traer el archivo real de la póliza
            (SELECT p.nombre_archivo FROM polizas p WHERE p.cotizacion_id = c.id AND p.eliminado_en IS NULL LIMIT 1) AS archivo_poliza,
            -- Comprobante de pago del coordinador
            (SELECT id FROM cotizacion_comprobantes_pago cp
             WHERE cp.cotizacion_id = c.id AND cp.eliminado_en IS NULL
             ORDER BY cp.creado_en DESC LIMIT 1) AS comprobante_id
     FROM cotizaciones c
     JOIN clientes cl ON cl.id = c.cliente_id
     JOIN agentes  a  ON a.id  = c.agente_id
     WHERE {$whereSQL}
     ORDER BY c.fecha DESC
     LIMIT {$emiPP} OFFSET {$emiOffset}"
);
$stCots->execute($params);
$emiCots = $stCots->fetchAll(\PDO::FETCH_ASSOC);

// KPIs rápidos
$kpiCots  = $db->query("SELECT COUNT(*) FROM cotizaciones")->fetchColumn();
$kpiConPol= $db->query("SELECT COUNT(DISTINCT cotizacion_id) FROM cotizacion_archivos WHERE tipo_documento='poliza' AND eliminado_en IS NULL")->fetchColumn();
$kpiPagConf = $db->query("SELECT COUNT(*) FROM cotizaciones WHERE estado_pago='confirmado'")->fetchColumn();
$kpiPendPago = $db->query("SELECT COUNT(*) FROM cotizaciones WHERE estado_pago='pendiente'")->fetchColumn();
?>

<!-- ── KPIs ─────────────────────────────────────────────────────────────────── -->
<div class="kpi4" style="margin-bottom:20px;">
    <div class="stat-card info">
        <div class="stat-card-value"><?= $kpiCots ?></div>
        <div class="stat-card-label">📋 Total cotizaciones</div>
    </div>
    <div class="stat-card success">
        <div class="stat-card-value"><?= $kpiConPol ?></div>
        <div class="stat-card-label">📄 Con póliza subida</div>
    </div>
    <div class="stat-card success">
        <div class="stat-card-value"><?= $kpiPagConf ?></div>
        <div class="stat-card-label">✅ Pago confirmado</div>
    </div>
    <div class="stat-card warning">
        <div class="stat-card-value"><?= $kpiPendPago ?></div>
        <div class="stat-card-label">⏳ Pago pendiente</div>
    </div>
</div>

<!-- ── Toolbar filtros ────────────────────────────────────────────────────────── -->
<form method="GET" action="/admin" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:16px;">
    <input type="hidden" name="tab" value="emision">
    <div class="search-bar" style="flex:1;min-width:220px;">
        <span class="search-bar-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></span>
        <input type="text" name="emi_q" class="search-bar-input" placeholder="Folio, cliente, compañía…" value="<?= htmlspecialchars($emiSearch) ?>">
    </div>
    <select name="emi_estado" class="filter-select">
        <option value="">Todos los estados</option>
        <option value="pendiente"  <?= $emiEstado==='pendiente'  ?'selected':'' ?>>Pendiente</option>
        <option value="enviada"    <?= $emiEstado==='enviada'    ?'selected':'' ?>>Enviada</option>
        <option value="aceptada"   <?= $emiEstado==='aceptada'   ?'selected':'' ?>>Aceptada</option>
        <option value="pagado"     <?= $emiEstado==='pagado'     ?'selected':'' ?>>Pagado</option>
    </select>
    <select name="emi_pago" class="filter-select">
        <option value="">Todos los pagos</option>
        <option value="pendiente"  <?= $emiEstatoPago==='pendiente'  ?'selected':'' ?>>⏳ Pago pendiente</option>
        <option value="confirmado" <?= $emiEstatoPago==='confirmado' ?'selected':'' ?>>✅ Pago confirmado</option>
    </select>
    <button type="submit" class="btn btn-outline btn-sm">Filtrar</button>
    <?php if ($emiSearch || $emiEstado || $emiEstatoPago): ?>
    <a href="/admin?tab=emision" class="btn btn-ghost btn-sm">✕ Limpiar</a>
    <?php endif; ?>
</form>

<!-- ── Tabla ──────────────────────────────────────────────────────────────────── -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            Emisión de Pólizas
            <span class="badge badge-neutral"><?= $emiTotal ?></span>
        </h3>
        <span style="font-size:12px;color:var(--text-muted);">Clic en una fila para ver documentos y gestionar pago</span>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="adt" id="tbEmision">
            <thead>
                <tr>
                    <th>Folio</th>
                    <th>Cliente / Asesor</th>
                    <th>Ramo / Compañía</th>
                    <th>Prima</th>
                    <th>Estado</th>
                    <th>Pago</th>
                    <th>Póliza</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $BADGE_ESTADO = [
                'pendiente' =>'badge-warning','enviada'=>'badge-info',
                'aceptada'  =>'badge-success','rechazada'=>'badge-danger',
                'pagado'    =>'badge-success',
            ];
            $BADGE_PAGO = [
                'pendiente'  => ['badge-warning', '⏳ Pendiente'],
                'confirmado' => ['badge-success',  '✅ Confirmado'],
                'rechazado'  => ['badge-danger',   '✗ Rechazado'],
            ];
            foreach ($emiCots as $cot):
                $bEst   = $BADGE_ESTADO[$cot['estado']] ?? 'badge-neutral';
                [$bPago, $lPago] = $BADGE_PAGO[$cot['estado_pago'] ?? 'pendiente'] ?? ['badge-neutral','—'];
                $tienePoliza = (int)$cot['tiene_poliza'] > 0;
                $enProceso   = ($cot['emision_estado'] ?? '') === 'en_proceso';
            ?>
            <tr style="cursor:pointer;"
                data-cot-id="<?= (int)$cot['id'] ?>"
                data-asesor-id="<?= (int)$cot['asesor_id'] ?>"
                data-folio="<?= htmlspecialchars($cot['folio'] ?? '') ?>"
                data-comprobante-id="<?= (int)($cot['comprobante_id'] ?? 0) ?>"
                data-estado-pago="<?= htmlspecialchars($cot['estado_pago'] ?? 'pendiente') ?>"
                data-action="emi-abrirDetalle">
                <td>
                    <?php if (!empty($cot['folio'])): ?>
                    <span class="fmono"><?= htmlspecialchars($cot['folio']) ?></span>
                    <?php else: ?><span style="color:var(--text-muted);">#<?= $cot['id'] ?></span><?php endif; ?>
                </td>
                <td>
                    <div style="font-weight:600;font-size:13px;"><?= htmlspecialchars($cot['cliente_nombre']) ?></div>
                    <div style="font-size:11px;color:var(--text-muted);"><?= htmlspecialchars($cot['asesor_nombre']) ?></div>
                </td>
                <td>
                    <div><?= htmlspecialchars($RAMOS_LABEL[$cot['ramo']] ?? $cot['ramo']) ?></div>
                    <div style="font-size:11px;color:var(--text-muted);"><?= htmlspecialchars($cot['compania']) ?></div>
                </td>
                <td><strong style="color:var(--success-color);">$<?= number_format((float)$cot['precio'], 0) ?></strong></td>
                <td><span class="badge <?= $bEst ?>"><?= ucfirst($cot['estado']) ?></span></td>
                <td><span class="badge <?= $bPago ?>"><?= $lPago ?></span></td>
                <td style="text-align:center;">
    <?php if ($cot['tiene_poliza'] > 0): ?>
        <div style="display:flex; flex-direction:column; align-items:center; gap:4px;">
            <span class="emi-pill pill-ok" title="Póliza cargada y emitida">✓ Subida</span>
            <?php if (!empty($cot['archivo_poliza'])): ?>
                <a href="/archivo?file=<?php echo urlencode('polizas/' . $cot['id'] . '/' . $cot['archivo_poliza']); ?>" 
                   target="_blank" 
                   class="emi-btn-view" 
                   style="padding:2px 6px; font-size:11px; text-decoration:none; display:inline-flex; align-items:center; gap:4px; background:#0f172a; color:#fff; border-radius:4px;"
                   title="Ver archivo de la póliza">
                   👁 Ver Póliza
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <span class="emi-pill pill-pend" title="Falta emitir/subir póliza original">⏳ Pendiente</span>
    <?php endif; ?>
</td>
                <?php
                    $estadoPago    = $cot['estado_pago'] ?? 'pendiente';
                    $pagoCofirmado = ($estadoPago === 'confirmado');
                    $tieneComp     = !empty($cot['comprobante_id']);
                    $esEmitidaPagada = ($cot['estado'] === 'pagado' || $pagoCofirmado);
                    // Mostrar "Registrar pago" si: no está pagado Y hay archivos subidos (el asesor actuó)
                    $puedeRegistrarPago = !$pagoCofirmado && ((int)$cot['total_archivos'] > 0 || $tienePoliza || $enProceso);
                ?>
                <td class="emi-stop-prop">
                    <?php if ($esEmitidaPagada): ?>
                        <div style="color:#10b981; font-size:12px; font-weight:700; margin-bottom:8px; text-align:center;">
                            ✔️ Emitida y Pagada
                        </div>
                        <?php if ($tieneComp): ?>
                            <button class="btn btn-ghost btn-sm"
                                    data-action="emi-verComprobante"
                                    data-comprobante-id="<?= (int)$cot['comprobante_id'] ?>"
                                    style="width:100%; margin-bottom:4px;">
                                👁 Ver comprobante
                            </button>
                            <button class="btn btn-outline btn-sm"
                                    data-action="emi-abrirComprobante"
                                    data-cot-id="<?= $cot['id'] ?>"
                                    data-folio="<?= htmlspecialchars($cot['folio'] ?? '#'.$cot['id'], ENT_QUOTES) ?>"
                                    data-asesor-id="<?= (int)$cot['asesor_id'] ?>"
                                    style="width:100%; margin-bottom:4px;">
                                🔄 Cambiar cobro
                            </button>
                        <?php endif; ?>

                    <?php elseif ($puedeRegistrarPago): ?>
                        <?php if ($enProceso): ?>
                            <div style="font-size:10px;color:var(--success-color);font-weight:700;margin-bottom:6px;">
                                ✅ Docs. recibidos, listo para cobro
                            </div>
                        <?php else: ?>
                            <div style="font-size:10px;color:var(--text-muted);margin-bottom:6px;">
                                📁 <?= (int)$cot['total_archivos'] ?> doc(s) subidos
                            </div>
                        <?php endif; ?>
                        <button class="btn btn-primary btn-sm"
                                data-action="emi-abrirComprobante"
                                data-cot-id="<?= $cot['id'] ?>"
                                data-folio="<?= htmlspecialchars($cot['folio'] ?? '#'.$cot['id'], ENT_QUOTES) ?>"
                                data-asesor-id="<?= (int)$cot['asesor_id'] ?>"
                                style="width:100%; margin-bottom:4px;">
                            💳 Registrar pago
                        </button>

                    <?php else: ?>
                        <span style="font-size:11px;color:var(--text-muted); display:block; text-align:center; margin-bottom:8px;">
                            Esperando asesor
                        </span>
                    <?php endif; ?>

                    <a href="/admin?accion=emi_pdf_checklist&cotizacion_id=<?= $cot['id'] ?>"
                       target="_blank" class="btn btn-outline btn-sm" style="display:block;text-align:center;">
                        📋 Checklist
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($emiCots)): ?>
            <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--text-muted);">Sin cotizaciones que mostrar</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Paginación -->
    <?php if ($emiPages > 1): ?>
    <div style="display:flex;gap:4px;justify-content:center;padding:16px;flex-wrap:wrap;border-top:1px solid var(--border-color);">
        <?php for ($p = 1; $p <= $emiPages; $p++):
            $url = "/admin?tab=emision&emi_pag={$p}" .
                   ($emiSearch  ? "&emi_q=".urlencode($emiSearch)   : '') .
                   ($emiEstado  ? "&emi_estado=".urlencode($emiEstado) : '') .
                   ($emiEstatoPago ? "&emi_pago=".urlencode($emiEstatoPago) : '');
        ?>
        <a href="<?= $url ?>"
           class="page-btn <?= $p === $emiPage ? 'active' : '' ?>"><?= $p ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════
     MODAL: Detalle de cotización (solo lectura para coordinador)
══════════════════════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modalEmiDetalle">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3 class="modal-title">📄 Expediente — <span id="emiDetalleFolio" style="font-family:'Courier New',monospace;color:var(--accent-color);"></span></h3>
            <button class="modal-close" data-close="modalEmiDetalle">&times;</button>
        </div>
        <div class="modal-body" id="emiDetalleBody" style="min-height:200px;">
            <div style="text-align:center;padding:40px;color:var(--text-muted);">Cargando…</div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" data-close="modalEmiDetalle">Cerrar</button>
            <button class="btn btn-primary" id="btnEmiRegistrarPagoDesdeDetalle" style="display:none;">
                💳 Registrar pago
            </button>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════
     MODAL: Registrar comprobante de pago
══════════════════════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modalEmiComprobante">
    <div class="modal" style="max-width:560px;">
        <div class="modal-header">
            <h3 class="modal-title">💳 Registrar pago — <span id="emiCompFolio" style="font-family:'Courier New',monospace;color:var(--accent-color);"></span></h3>
            <button class="modal-close" data-close="modalEmiComprobante">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="emiCompCotizacionId">
            <input type="hidden" id="emiCompAsesorId">

            <!-- Info visual -->
            <div id="emiCompInfoBanner" style="background:var(--info-light);border:1px solid var(--info-color,#3b82f6);border-radius:8px;padding:12px 14px;margin-bottom:18px;font-size:13px;display:none;">
                <strong>Cliente:</strong> <span id="emiCompCliente"></span>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label required">Método de pago</label>
                    <select class="form-select" id="emiCompMetodo">
                        <?php foreach ($METODOS_PAGO as $val => $lbl): ?>
                        <option value="<?= $val ?>"><?= $lbl ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" id="grpBanco" style="display:none;">
                    <label class="form-label">Banco</label>
                    <input type="text" class="form-input" id="emiCompBanco" placeholder="Ej. BBVA, HSBC, Banamex…">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Monto cobrado ($)</label>
                    <input type="number" step="0.01" min="0" class="form-input" id="emiCompMonto" placeholder="0.00">
                </div>
                <div class="form-group">
                    <label class="form-label">Referencia / Folio</label>
                    <input type="text" class="form-input" id="emiCompReferencia" placeholder="Número de autorización…">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Comprobante (archivo)</label>
                <input type="file" class="form-input" id="emiCompArchivo"
                       accept=".pdf,.jpg,.jpeg,.png,.webp"
                       style="padding:6px;">
                <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">PDF o imagen. Opcional si el pago fue en efectivo.</div>
            </div>

            <div class="form-group">
                <label class="form-label">Comentarios</label>
                <textarea class="form-input" id="emiCompComentarios" rows="3"
                          placeholder="Detalles adicionales del cobro, acuerdos, notas…"
                          style="resize:vertical;"></textarea>
            </div>

            <!-- Progress de subida -->
            <div id="emiCompProgress" style="display:none;margin-top:8px;">
                <div style="background:var(--border-color);border-radius:99px;height:6px;overflow:hidden;">
                    <div id="emiCompProgressBar" style="height:100%;background:var(--accent-color);width:0%;transition:width .3s;border-radius:99px;"></div>
                </div>
                <div id="emiCompProgressLabel" style="font-size:11px;color:var(--text-muted);margin-top:4px;text-align:center;">Subiendo…</div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" data-close="modalEmiComprobante">Cancelar</button>
            <button class="btn btn-primary" id="btnEmiGuardarComprobante">💾 Confirmar pago</button>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════
     MODAL: Ver comprobante existente
══════════════════════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modalEmiVerComp">
    <div class="modal" style="max-width:480px;">
        <div class="modal-header">
            <h3 class="modal-title">✅ Comprobante de pago</h3>
            <button class="modal-close" data-close="modalEmiVerComp">&times;</button>
        </div>
        <div class="modal-body" id="emiVerCompBody">
            <div style="text-align:center;padding:32px;color:var(--text-muted);">Cargando…</div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" data-close="modalEmiVerComp">Cerrar</button>
        </div>
    </div>
</div>

<script nonce="<?= $cspNonce ?>">
(function () {
    // ── Método pago → mostrar banco ───────────────────────────────────────────
    document.getElementById('emiCompMetodo')?.addEventListener('change', function () {
        const necesitaBanco = ['tarjeta_credito','tarjeta_debito','transferencia','msi_3','msi_6','msi_12','msi_18','deposito_banco'].includes(this.value);
        document.getElementById('grpBanco').style.display = necesitaBanco ? '' : 'none';
    });

    // ── Abrir detalle de cotización ───────────────────────────────────────────
    window.emiAbrirDetalle = function (tr) {
        const cotId  = tr.dataset.cotId;
        const folio  = tr.dataset.folio || ('#' + cotId);
        const asesorId = tr.dataset.asesorId;
        const pagado = tr.dataset.estadoPago === 'confirmado';
        const compId = parseInt(tr.dataset.comprobanteId || '0');

        document.getElementById('emiDetalleFolio').textContent = folio;
        document.getElementById('emiDetalleBody').innerHTML = '<div style="text-align:center;padding:40px;color:var(--text-muted);">Cargando…</div>';

        // Botón pago desde modal
        const btnPago = document.getElementById('btnEmiRegistrarPagoDesdeDetalle');
        if (pagado) {
            btnPago.style.display = 'none';
        } else {
            btnPago.style.display = '';
            btnPago.dataset.cotId   = cotId;
            btnPago.dataset.folio   = folio;
            btnPago.dataset.asesorId = asesorId;
        }

        openModal('modalEmiDetalle');

        // Cargar docs
        const fd = new FormData();
        fd.append('accion', 'emi_get_docs');
        fd.append('cotizacion_id', cotId);
        fetch('/admin', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if (!d.ok) {
                    document.getElementById('emiDetalleBody').innerHTML = '<p style="color:var(--danger-color);text-align:center;padding:24px;">Error al cargar documentos.</p>';
                    return;
                }
                const tipoLabel = <?= json_encode($TIPO_LABEL) ?>;
                let html = `<div style="margin-bottom:16px;">
                    <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:8px;">
                        <span class="badge badge-info">Ramo: ${d.ramo || '—'}</span>
                        <span class="badge badge-neutral">Compañía: ${d.compania || '—'}</span>
                        <span class="badge badge-neutral">Cobertura: ${d.tipo_cobertura || '—'}</span>
                    </div>
                    <div style="font-size:13px;color:var(--text-secondary);">
                        👤 <strong>${d.cliente_nombre}</strong> &nbsp;·&nbsp; Asesor: ${d.asesor_nombre}
                    </div>
                </div>`;
                if (!d.docs || !d.docs.length) {
                    html += '<p style="color:var(--text-muted);text-align:center;padding:24px;">Sin documentos subidos por el asesor aún.</p>';
                } else {
                    html += '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px;">';
                    d.docs.forEach(doc => {
                        const thumb = doc.es_imagen && doc.url_local
                            ? `<div style="height:90px;border-radius:6px;overflow:hidden;margin-bottom:8px;cursor:pointer;" data-action="emi-openImg"><img src="${doc.url_local}" style="width:100%;height:100%;object-fit:cover;" loading="lazy"></div>`
                            : `<div style="height:90px;background:var(--bg-secondary);border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:36px;margin-bottom:8px;">${doc.es_pdf ? '📄' : '📎'}</div>`;
                        const dl = doc.url_local
                            ? `<a href="${doc.url_local}" download="${doc.nombre_orig}" class="btn btn-sm btn-outline" style="width:100%;text-align:center;font-size:11px;">⬇ Descargar</a>`
                            : `<span style="font-size:11px;color:var(--text-muted);">No disponible</span>`;
                        html += `<div style="background:var(--bg-secondary);border-radius:8px;padding:12px;">
                            ${thumb}
                            <div style="font-size:11px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;margin-bottom:3px;">${tipoLabel[doc.tipo] || doc.tipo}</div>
                            <div style="font-size:11px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-bottom:6px;" title="${doc.nombre_orig}">${doc.nombre_orig}</div>
                            ${dl}
                        </div>`;
                    });
                    html += '</div>';
                }
                document.getElementById('emiDetalleBody').innerHTML = html;
            })
            .catch(() => {
                document.getElementById('emiDetalleBody').innerHTML = '<p style="color:var(--danger-color);text-align:center;padding:24px;">Error de conexión.</p>';
            });
    };

    // ── Abrir modal registrar comprobante ─────────────────────────────────────
    window.emiAbrirComprobante = function (cotId, folio, asesorId) {
        document.getElementById('emiCompCotizacionId').value = cotId;
        document.getElementById('emiCompAsesorId').value     = asesorId;
        document.getElementById('emiCompFolio').textContent  = folio;
        document.getElementById('emiCompMetodo').value       = 'efectivo';
        document.getElementById('emiCompBanco').value        = '';
        document.getElementById('emiCompMonto').value        = '';
        document.getElementById('emiCompReferencia').value   = '';
        document.getElementById('emiCompComentarios').value  = '';
        document.getElementById('emiCompArchivo').value      = '';
        document.getElementById('emiCompProgress').style.display = 'none';
        document.getElementById('grpBanco').style.display   = 'none';
        openModal('modalEmiComprobante');
    };

    // ── Guardar comprobante ───────────────────────────────────────────────────
    document.getElementById('btnEmiGuardarComprobante')?.addEventListener('click', async function () {
        const btn    = this;
        const cotId  = document.getElementById('emiCompCotizacionId').value;
        const asesor = document.getElementById('emiCompAsesorId').value;
        const metodo = document.getElementById('emiCompMetodo').value;
        const monto  = document.getElementById('emiCompMonto').value;

        if (!cotId || !metodo || !monto || parseFloat(monto) <= 0) { 
            if(typeof toast==='function') toast('Debes ingresar un monto válido mayor a 0.', 'error'); 
            return; 
        }
        const necesitaBanco = ['tarjeta_credito','tarjeta_debito','transferencia','msi_3','msi_6','msi_12','msi_18','deposito_banco'].includes(metodo);
        const archivoValido = document.getElementById('emiCompArchivo').files[0];
        if (necesitaBanco && !archivoValido) {
            if(typeof toast==='function') toast('Debes adjuntar el comprobante para este método de pago.', 'error'); 
            return;
        }

        btn.disabled = true; btn.textContent = 'Guardando…';
        document.getElementById('emiCompProgress').style.display = '';

        const fd = new FormData();
        fd.append('accion',        'emi_registrar_comprobante');
        fd.append('cotizacion_id', cotId);
        fd.append('asesor_id',     asesor);
        fd.append('metodo_pago',   metodo);
        fd.append('banco',         document.getElementById('emiCompBanco').value.trim());
        fd.append('monto',         monto);
        fd.append('referencia',    document.getElementById('emiCompReferencia').value.trim());
        fd.append('comentarios',   document.getElementById('emiCompComentarios').value.trim());

        const archivo = document.getElementById('emiCompArchivo').files[0];
        if (archivo) fd.append('comprobante', archivo);

        try {
            const r = await fetch('/admin', { method: 'POST', body: fd });
            const d = await r.json();
            btn.disabled = false; btn.textContent = '💾 Confirmar pago';
            document.getElementById('emiCompProgress').style.display = 'none';
            if (d.ok) {
                if (typeof toast === 'function') toast('Pago confirmado. Notificación enviada al asesor.', 'success');
                closeModal('modalEmiComprobante');
                setTimeout(() => location.reload(), 1400);
            } else {
                if (typeof toast === 'function') toast(d.msg || 'Error al guardar', 'error');
            }
        } catch {
            btn.disabled = false; btn.textContent = '💾 Confirmar pago';
            document.getElementById('emiCompProgress').style.display = 'none';
            if (typeof toast === 'function') toast('Error de conexión', 'error');
        }
    });

    // ── Ver comprobante existente ─────────────────────────────────────────────
    window.emiVerComprobante = function (compId) {
        if (!compId) return;
        document.getElementById('emiVerCompBody').innerHTML = '<div style="text-align:center;padding:32px;color:var(--text-muted);">Cargando…</div>';
        openModal('modalEmiVerComp');
        const fd = new FormData();
        fd.append('accion', 'emi_get_comprobante');
        fd.append('comprobante_id', compId);
        fetch('/admin', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if (!d.ok) { document.getElementById('emiVerCompBody').innerHTML = '<p style="color:var(--danger-color);text-align:center;">Error al cargar.</p>'; return; }
                const c = d.comprobante;
                const metodos = <?= json_encode($METODOS_PAGO) ?>;
                document.getElementById('emiVerCompBody').innerHTML = `
                    <div style="display:flex;flex-direction:column;gap:12px;">
                        <div style="background:var(--bg-secondary);border-radius:8px;padding:14px;">
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                                <div><div style="font-size:10px;font-weight:700;color:var(--text-muted);text-transform:uppercase;">Método</div>
                                     <div style="font-weight:600;">${metodos[c.metodo_pago] || c.metodo_pago}</div></div>
                                <div><div style="font-size:10px;font-weight:700;color:var(--text-muted);text-transform:uppercase;">Monto</div>
                                     <div style="font-weight:700;font-size:16px;color:var(--success-color);">${c.monto ? '$'+parseFloat(c.monto).toLocaleString('es-MX',{minimumFractionDigits:2}) : '—'}</div></div>
                                ${c.banco ? `<div><div style="font-size:10px;font-weight:700;color:var(--text-muted);text-transform:uppercase;">Banco</div><div>${c.banco}</div></div>` : ''}
                                ${c.referencia ? `<div><div style="font-size:10px;font-weight:700;color:var(--text-muted);text-transform:uppercase;">Referencia</div><div style="font-family:monospace;">${c.referencia}</div></div>` : ''}
                            </div>
                        </div>
                        ${c.comentarios ? `<div style="background:var(--bg-secondary);border-radius:8px;padding:12px;font-size:13px;color:var(--text-secondary);">💬 ${c.comentarios}</div>` : ''}
                        ${c.url_local ? `<a href="${c.url_local}" target="_blank" class="btn btn-outline" style="text-align:center;">📄 Ver archivo del comprobante</a>` : ''}
                        <div style="font-size:11px;color:var(--text-muted);text-align:right;">Registrado: ${c.creado_en}</div>
                    </div>`;
            })
            .catch(() => { document.getElementById('emiVerCompBody').innerHTML = '<p style="color:var(--danger-color);text-align:center;">Error de conexión.</p>'; });
    };

    // Cerrar modales al clic overlay (delegado global en admin/index.php)

    // ── Delegación de eventos (sustituye inline onclick, compatible con CSP) ──
    document.addEventListener('click', function(e) {
    // 1. Si hicieron clic dentro de la celda de acciones pero NO en un botón válido, ignorar para que no abra el detalle de la fila
    if (e.target.closest('.emi-stop-prop') && !e.target.closest('[data-action]')) {
        return;
    }

    const el = e.target.closest('[data-action]');
    if (!el) return;
    const action = el.dataset.action;

    if (action === 'emi-abrirDetalle') {
        // Evitar abrir detalle si hicieron clic específicamente en la celda de acciones
        if (e.target.closest('.emi-stop-prop')) return; 
        
        const tr = e.target.closest('tr[data-action="emi-abrirDetalle"]');
        if (tr) { emiAbrirDetalle(tr); }
        return;
    }
    if (action === 'emi-abrirComprobante') {
        e.stopPropagation();
        // CORRECCIÓN: Tenías el.dataset.colId, lo correcto es el.dataset.cotId
        emiAbrirComprobante(el.dataset.cotId, el.dataset.folio, el.dataset.asesorId);
        return;
    }
    if (action === 'emi-verComprobante') {
        e.stopPropagation();
        emiVerComprobante(el.dataset.comprobanteId);
        return;
    }
    if (action === 'emi-openImg') {
        const img = el.querySelector('img');
        if (img) window.open(img.src, '_blank');
        return;
    }
    if (el.id === 'btnEmiRegistrarPagoDesdeDetalle') {
        closeModal('modalEmiDetalle');
        emiAbrirComprobante(el.dataset.cotId, el.dataset.folio, el.dataset.asesorId);
        return;
    }
});
})();
</script>