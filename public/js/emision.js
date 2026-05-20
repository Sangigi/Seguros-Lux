/**
 * emision.js — Módulo de Emisión de Pólizas
 * CRM EIRA — Panel de Coordinación
 *
 * Depende de: toast(), openModal(), closeModal() (definidas en admin/index.php)
 */

(function () {
    'use strict';

    // ── Estado global del módulo ──────────────────────────────────────────────
    let _cotActual = null;   // id de cotización en foco
    let _folioActual = null;

    // ── Sub-tabs ──────────────────────────────────────────────────────────────
    document.querySelectorAll('.emi-sub').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.emi-sub').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.emi-panel').forEach(p => p.style.display = 'none');
            btn.classList.add('active');
            const panel = document.getElementById('emiSub-' + btn.dataset.sub);
            if (panel) panel.style.display = '';
        });
    });

    // ── Filtro búsqueda cola de emisión ───────────────────────────────────────
    document.getElementById('srchEmi')?.addEventListener('input', filtrarCola);
    document.getElementById('flEmiRamo')?.addEventListener('change', filtrarCola);

    function filtrarCola() {
        const tx   = (document.getElementById('srchEmi')?.value || '').toLowerCase();
        const ramo = document.getElementById('flEmiRamo')?.value || '';
        document.querySelectorAll('#eListaColas .emi-card').forEach(card => {
            const ok = (!tx   || card.dataset.folio.includes(tx) ||
                                  card.dataset.cliente.includes(tx) ||
                                  card.dataset.compania.includes(tx))
                    && (!ramo || card.dataset.ramo === ramo);
            card.style.display = ok ? '' : 'none';
        });
    }

    // ── Delegación de acciones ────────────────────────────────────────────────
    document.addEventListener('click', function (e) {
        const el = e.target.closest('[data-action^="emi-"]');
        if (!el) return;

        const action = el.dataset.action;
        const id     = el.dataset.id     ? parseInt(el.dataset.id)  : null;
        const folio  = el.dataset.folio  || '';
        const ramo   = el.dataset.ramo   || 'autos';

        switch (action) {

            // ── Ver checklist de documentos ───────────────────────────────────
            case 'emi-verChecklist':
                _cotActual   = id;
                _folioActual = folio;
                document.getElementById('eckFolio').textContent = folio;
                document.getElementById('eckBody').innerHTML    =
                    '<div style="text-align:center;padding:40px;color:var(--text-muted);">⏳ Cargando…</div>';
                document.getElementById('eckBtnDescargar').style.display = '';
                openModal('modalEmiChecklist');
                cargarChecklist(id, ramo);
                break;

            // ── Tomar caso ────────────────────────────────────────────────────
            case 'emi-tomarCaso':
                if (!confirm(`¿Tomar el caso ${folio}? Se marcará como "En proceso" y quedará asignado a ti.`)) break;
                postAdmin({ accion: 'emi_tomar', cotizacion_id: id })
                    .then(d => {
                        if (d.ok) { toast('Caso tomado — ahora está en proceso', 'success'); setTimeout(() => location.reload(), 1000); }
                        else toast(d.msg, 'error');
                    });
                break;

            // ── Abrir modal subir póliza ──────────────────────────────────────
            case 'emi-subirPoliza':
                _cotActual   = id;
                _folioActual = folio;
                document.getElementById('spCotId').value           = id;
                document.getElementById('spFolio').textContent     = folio;
                document.getElementById('spNumero').value          = '';
                document.getElementById('spNotas').value           = '';
                document.getElementById('spFechaInicio').value     = hoy();
                document.getElementById('spFechaFin').value        = anioPlusFecha(1);
                document.getElementById('spFile').value            = '';
                document.getElementById('spFileName').style.display = 'none';
                document.getElementById('spDropZone').style.borderColor = '';
                openModal('modalEmiSubirPoliza');
                break;

            // ── Dropzone click ────────────────────────────────────────────────
            case 'emi-clickDropzone':
                document.getElementById('spFile')?.click();
                break;

            // ── Confirmar emisión ─────────────────────────────────────────────
            case 'emi-confirmarSubirPoliza':
                confirmarSubirPoliza();
                break;

            // ── Abrir modal rechazar ──────────────────────────────────────────
            case 'emi-rechazarDocs':
                _cotActual   = id;
                document.getElementById('rdCotId').value       = id;
                document.getElementById('rdFolio').textContent = folio;
                document.getElementById('rdMotivo').value      = '';
                openModal('modalEmiRechazar');
                break;

            // ── Confirmar rechazo ─────────────────────────────────────────────
            case 'emi-confirmarRechazo':
                confirmarRechazo();
                break;

            // ── Notificar renovación ──────────────────────────────────────────
            case 'emi-alertarRenovacion':
                const agenteId = el.dataset.agente;
                if (!confirm(`¿Enviar notificación de renovación al asesor para la póliza ${folio}?`)) break;
                postAdmin({ accion: 'emi_notificar_renovacion', cotizacion_id: id, agente_id: agenteId })
                    .then(d => {
                        if (d.ok) toast('Notificación enviada al asesor', 'success');
                        else toast(d.msg || 'Error', 'error');
                    });
                break;

            // ── Marcar doc del checklist ──────────────────────────────────────
            case 'emi-marcarDoc':
                marcarDoc(id, el.dataset.tipo, el.dataset.estado, el);
                break;

            // ── Abrir modal registrar comprobante de pago (coordinador) ───────
            case 'emi-abrirComprobante':
                if (typeof window.emiAbrirComprobante === 'function') {
                    window.emiAbrirComprobante(
                        el.dataset.cotId,
                        el.dataset.folio,
                        el.dataset.asesorId
                    );
                }
                break;

            // ── Ver comprobante ya registrado ─────────────────────────────────
            case 'emi-verComprobante':
                if (typeof window.emiVerComprobante === 'function') {
                    window.emiVerComprobante(parseInt(el.dataset.comprobanteId || '0'));
                }
                break;

            // ── Abrir modal registrar pago desde el modal de detalle ──────────
            case 'emi-abrirPagoDesdeDetalle':
                if (typeof window.emiAbrirComprobante === 'function') {
                    window.emiAbrirComprobante(
                        el.dataset.cotId,
                        el.dataset.folio,
                        el.dataset.asesorId
                    );
                    if (typeof closeModal === 'function') closeModal('modalEmiDetalle');
                }
                break;

            // ── Descargar checklist PDF ───────────────────────────────────────
            case 'emi-descargarChecklist':
                if (_cotActual) window.open('/admin?accion=emi_pdf_checklist&cotizacion_id=' + _cotActual, '_blank');
                break;
        }
    });

    // ── File input ────────────────────────────────────────────────────────────
    document.getElementById('spFile')?.addEventListener('change', function () {
        const f = this.files[0];
        if (!f) return;
        const fn = document.getElementById('spFileName');
        fn.textContent = '📎 ' + f.name + ' (' + (f.size / 1024).toFixed(0) + ' KB)';
        fn.style.display = '';
        document.getElementById('spDropZone').style.borderColor = 'var(--accent-color)';
    });

    // Drag & drop
    const dz = document.getElementById('spDropZone');
    if (dz) {
        dz.addEventListener('dragover', e => { e.preventDefault(); dz.style.borderColor = 'var(--accent-color)'; });
        dz.addEventListener('dragleave', () => { dz.style.borderColor = ''; });
        dz.addEventListener('drop', e => {
            e.preventDefault();
            const file = e.dataTransfer.files[0];
            if (!file) return;
            const input = document.getElementById('spFile');
            const dt = new DataTransfer();
            dt.items.add(file);
            input.files = dt.files;
            input.dispatchEvent(new Event('change'));
        });
    }

    // ── Funciones principales ─────────────────────────────────────────────────

    function cargarChecklist(cotId, ramo) {
        postAdmin({ accion: 'emi_get_checklist', cotizacion_id: cotId, ramo })
            .then(d => {
                if (!d.ok) {
                    document.getElementById('eckBody').innerHTML =
                        '<div style="color:var(--danger-color);text-align:center;padding:32px;">Error al cargar</div>';
                    return;
                }
                renderChecklist(d.docs, d.checklist, d.ramo, cotId);
            });
    }

    function renderChecklist(docs, checklist, ramo, cotId) {
        // docs: archivos subidos por el asesor + comprobante virtual del coordinador
        // checklist: estado marcado por el coordinador (puede estar vacío)
        const tipoLabel = {
            ine: 'INE', comprobante_dom: 'Comp. domicilio',
            tarjeta_circulacion: 'T. circulación', factura_auto: 'Factura auto',
            foto_coche_1: 'Foto 1', foto_coche_2: 'Foto 2', foto_coche_3: 'Foto 3',
            foto_coche_4: 'Foto 4', foto_coche_5: 'Foto 5',
            comprobante_pago: 'Comp. pago (Coordinador)', constancia_fiscal: 'Const. fiscal',
            poliza: 'Póliza actual', doc_cotizacion: 'Doc. cotización',
        };

        const REQS = {
            autos:          ['ine', 'comprobante_dom', 'tarjeta_circulacion', 'foto_coche_1', 'foto_coche_2', 'comprobante_pago'],
            vida:           ['ine', 'comprobante_dom', 'comprobante_pago'],
            gastos_medicos: ['ine', 'comprobante_dom', 'comprobante_pago'],
        };
        const requeridos = REQS[ramo] || REQS['autos'];

        // Docs que el ASESOR sube (no el coordinador)
        const DOCS_ASESOR = requeridos.filter(t => t !== 'comprobante_pago');

        // Mapas rápidos
        const docsMap     = {};
        docs.forEach(d => { docsMap[d.tipo_documento] = d; });
        const checkMap    = {};
        checklist.forEach(c => { checkMap[c.doc_tipo] = c; });

        // Unión de tipos (requeridos + extra subidos)
        const allTipos = [...new Set([...requeridos, ...Object.keys(docsMap)])];

        let completados = 0;
        let html = `
            <div style="margin-bottom:16px;padding:12px 16px;background:var(--bg-secondary);border-radius:8px;font-size:13px;">
                <strong>Ramo:</strong> ${ramo} &nbsp;|&nbsp;
                <strong>Docs subidos:</strong> ${docs.length} &nbsp;|&nbsp;
                <strong>Progreso:</strong> <span id="eckProgress">calculando…</span>
            </div>
            <div style="display:flex;flex-direction:column;gap:2px;">`;

        allTipos.forEach(tipo => {
            const doc      = docsMap[tipo];
            const ck       = checkMap[tipo];
            const requerido= requeridos.includes(tipo);
            const tieneDoc = !!doc;
            const ckEstado = ck?.estado || 'pendiente';
            const label    = tipoLabel[tipo] || tipo;
            const ext      = tieneDoc && doc.nombre_archivo ? String(doc.nombre_archivo).split('.').pop().toLowerCase() : '';
            const esImg    = ['jpg', 'jpeg', 'png', 'webp'].includes(ext);

            // El comprobante_pago lo sube el COORDINADOR, no el asesor
            const esDeCoordinador = tipo === 'comprobante_pago';

            let estIcon, estColor, estBg;
            if (!tieneDoc) {
                if (esDeCoordinador) {
                    // Pendiente de coordinador — no es error del asesor
                    estIcon  = '🕐';
                    estColor = '#2563eb';
                    estBg    = '#eff6ff';
                } else if (requerido) {
                    estIcon  = '❌'; estColor = '#dc2626'; estBg = '#fee2e2';
                } else {
                    estIcon  = '—'; estColor = 'var(--text-muted)'; estBg = 'var(--bg-secondary)';
                }
            } else if (ckEstado === 'ok') {
                estIcon  = '✅'; estColor = '#16a34a'; estBg = '#f0fdf4';
                completados++;
            } else if (ckEstado === 'rechazado') {
                estIcon  = '⚠️'; estColor = '#d97706'; estBg = '#fffbeb';
            } else {
                estIcon  = '⏳'; estColor = 'var(--text-muted)'; estBg = 'var(--bg-secondary)';
            }

            let urlLocal = null;
            if (tieneDoc) {
                let relRaw = '';
                if (doc.archivo_relative && String(doc.archivo_relative).trim()) {
                    relRaw = String(doc.archivo_relative).trim().replace(/^\/+/, '');
                } else if (doc.nombre_archivo) {
                    relRaw = `cotizaciones/${doc.cotizacion_id}/${doc.nombre_archivo}`;
                }
                relRaw = relRaw.replace(/^uploads\//, '');
                if (relRaw) {
                    urlLocal = `/archivo?file=${encodeURI(relRaw)}`;
                }
            }

            const notaTexto = esDeCoordinador && !tieneDoc
                ? 'Pendiente — el coordinador lo subirá al registrar el pago'
                : (requerido && !tieneDoc ? 'Requerido — no subido por asesor' : (tieneDoc ? '' : 'No subido'));

            html += `
            <div style="display:flex;align-items:center;gap:12px;padding:10px 14px;
                        border-radius:8px;background:${estBg};margin-bottom:4px;">
                <div style="font-size:20px;width:28px;text-align:center;">${estIcon}</div>
                <div style="flex:1;">
                    <div style="font-size:13px;font-weight:600;color:${estColor};">
                        ${label}
                        ${requerido ? '<span style="font-size:10px;color:#dc2626;margin-left:4px;">*</span>' : ''}
                        ${esDeCoordinador ? '<span style="font-size:10px;background:#dbeafe;color:#1d4ed8;padding:1px 6px;border-radius:99px;margin-left:6px;">Coord.</span>' : ''}
                    </div>
                    ${tieneDoc
                        ? `<div style="font-size:11px;color:var(--text-muted);">${doc.nombre_original || doc.nombre_orig || ''}</div>`
                        : `<div style="font-size:11px;color:${estColor};">${notaTexto}</div>`}
                    ${ck?.nota ? `<div style="font-size:11px;color:#d97706;margin-top:2px;">Nota: ${ck.nota}</div>` : ''}
                </div>
                <div style="display:flex;gap:6px;align-items:center;flex-shrink:0;">
                    ${urlLocal ? `<a href="${urlLocal}" target="_blank" class="btn btn-sm btn-ghost" style="font-size:11px;padding:3px 8px;">👁 Ver</a>` : ''}
                    ${urlLocal ? `<a href="${urlLocal}" download="${doc.nombre_original || doc.nombre_orig || ''}" class="btn btn-sm btn-outline" style="font-size:11px;padding:3px 8px;">⬇</a>` : ''}
                    ${tieneDoc && !esDeCoordinador ? `
                    <button class="btn btn-sm btn-ghost"
                            style="font-size:11px;color:#16a34a;padding:3px 8px;"
                            data-action="emi-marcarDoc"
                            data-id="${cotId}"
                            data-tipo="${tipo}"
                            data-estado="ok">✓ OK</button>
                    <button class="btn btn-sm btn-ghost"
                            style="font-size:11px;color:#d97706;padding:3px 8px;"
                            data-action="emi-marcarDoc"
                            data-id="${cotId}"
                            data-tipo="${tipo}"
                            data-estado="rechazado">⚠ Obs.</button>
                    ` : ''}
                </div>
            </div>`;
        });

        html += '</div>';

        document.getElementById('eckBody').innerHTML = html;

        // Actualizar contador progreso (excluir comprobante_pago del conteo de asesor)
        const totalReq = DOCS_ASESOR.filter(t => !!docsMap[t]).length;
        document.getElementById('eckProgress').textContent =
            `${totalReq}/${DOCS_ASESOR.length} docs asesor presentes — ${completados} marcados OK`;
    }

    function marcarDoc(cotId, tipo, estado, btn) {
        // Opcional: pedir nota si es "rechazado"
        let nota = '';
        if (estado === 'rechazado') {
            nota = prompt('Observación sobre este documento (opcional):') || '';
        }
        postAdmin({ accion: 'emi_marcar_checklist', cotizacion_id: cotId, doc_tipo: tipo, estado, nota })
            .then(d => {
                if (d.ok) {
                    toast('Checklist actualizado', 'success');
                    // Recargar checklist en el modal
                    const ramo = document.querySelector(`[data-action="emi-verChecklist"][data-id="${cotId}"]`)?.dataset.ramo || 'autos';
                    cargarChecklist(cotId, ramo);
                } else {
                    toast(d.msg || 'Error', 'error');
                }
            });
    }

    function confirmarSubirPoliza() {
        const cotId      = document.getElementById('spCotId').value;
        const numero     = document.getElementById('spNumero').value.trim();
        const fechaInicio= document.getElementById('spFechaInicio').value;
        const fechaFin   = document.getElementById('spFechaFin').value;
        const periodicidad=document.getElementById('spPeriodicidad').value;
        const notas      = document.getElementById('spNotas').value.trim();
        const fileInput  = document.getElementById('spFile');
        const file       = fileInput?.files[0] || null;

        if (!numero)      { toast('Ingresa el número de póliza', 'error'); return; }
        if (!fechaInicio) { toast('Ingresa la fecha de inicio',  'error'); return; }
        if (!fechaFin)    { toast('Ingresa la fecha de vencimiento', 'error'); return; }

        const btn = document.getElementById('spBtnSubir');
        btn.disabled    = true;
        btn.textContent = '⏳ Subiendo…';

        const fd = new FormData();
        fd.append('accion',         'emi_subir_poliza');
        fd.append('cotizacion_id',  cotId);
        fd.append('numero_poliza',  numero);
        fd.append('fecha_inicio',   fechaInicio);
        fd.append('fecha_fin',      fechaFin);
        fd.append('periodicidad',   periodicidad);
        fd.append('notas',          notas);
        if (file) fd.append('poliza_file', file);

        fetch('/admin', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                btn.disabled    = false;
                btn.textContent = '✅ Confirmar emisión';
                if (d.ok) {
                    toast('¡Póliza emitida correctamente! El asesor fue notificado.', 'success');
                    closeModal('modalEmiSubirPoliza');
                    setTimeout(() => location.reload(), 1200);
                } else {
                    toast(d.msg || 'Error al subir póliza', 'error');
                }
            })
            .catch(() => {
                btn.disabled    = false;
                btn.textContent = '✅ Confirmar emisión';
                toast('Error de conexión', 'error');
            });
    }

    function confirmarRechazo() {
        const cotId  = document.getElementById('rdCotId').value;
        const motivo = document.getElementById('rdMotivo').value.trim();
        if (!motivo) { toast('Escribe el motivo del rechazo', 'error'); return; }

        postAdmin({ accion: 'emi_rechazar_docs', cotizacion_id: cotId, nota: motivo })
            .then(d => {
                if (d.ok) {
                    toast('Documentación rechazada — asesor notificado', 'success');
                    closeModal('modalEmiRechazar');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    toast(d.msg || 'Error', 'error');
                }
            });
    }

    // ── Helpers ───────────────────────────────────────────────────────────────
    function postAdmin(data) {
        const fd = new FormData();
        Object.entries(data).forEach(([k, v]) => fd.append(k, v));
        return fetch('/admin', { method: 'POST', body: fd }).then(r => r.json());
    }

    function hoy() {
        return new Date().toISOString().split('T')[0];
    }

    function anioPlusFecha(years) {
        const d = new Date();
        d.setFullYear(d.getFullYear() + years);
        return d.toISOString().split('T')[0];
    }

})();