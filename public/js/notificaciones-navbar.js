(() => {
    const btn   = document.getElementById('sidebarNotifToggle');
    const panel = document.getElementById('sidebarNotifPanel');
    if (!btn || !panel) return;

    const listEl      = document.getElementById('sidebarNotifList');
    const markAllBtn  = document.getElementById('sidebarNotifMarkAll');
    const reloadBtn   = document.getElementById('sidebarNotifReload');
    const clearAllBtn = document.getElementById('sidebarNotifClearAll');

    // ── Tiempo real (polling ligero) ─────────────────────────────────────
    // Cada POLL_INTERVAL_MS se consulta /api/notificaciones SOLO para el
    // contador (sin pedir la lista completa de nuevo si el panel está
    // cerrado). Si llega un lead nuevo, se actualiza el badge, se reproduce
    // un aviso sonoro corto y, si el panel está abierto, se refresca la lista.
    const POLL_INTERVAL_MS = 8000;
    let lastKnownUnread = parseInt(panel.dataset.initialCount || '0', 10) || 0;
    let pollTimer = null;

    function playChime() {
        try {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) return;
            const ctx = new AudioCtx();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.value = 880;
            gain.gain.value = 0.08;
            osc.connect(gain).connect(ctx.destination);
            osc.start();
            gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.35);
            osc.stop(ctx.currentTime + 0.35);
        } catch (_e) { /* navegadores que bloquean audio sin interacción: ignorar */ }
    }

    function flashTitleBriefly() {
        const original = document.title;
        if (document.title.startsWith('🔔 ')) return;
        document.title = '🔔 ' + original;
        setTimeout(() => { document.title = original; }, 4000);
    }

    async function pollUnreadCount() {
        try {
            const r = await fetch('/api/notificaciones', { credentials: 'same-origin' });
            const d = await r.json();
            if (!d.ok) return;

            const unread = typeof d.unread === 'number' ? d.unread : 0;
            setBadge(unread);

            if (unread > lastKnownUnread) {
                playChime();
                if (document.hidden) flashTitleBriefly();
                // Si el panel ya está abierto, refrescar la lista visible
                // para que el lead nuevo aparezca sin que el asesor tenga
                // que cerrar y volver a abrir el panel.
                if (!panel.hidden) renderItems(d.items || []);
            }
            lastKnownUnread = unread;
        } catch (_e) {
            // Silencioso: un fallo de red puntual no debe interrumpir el ciclo.
        }
    }

    function startPolling() {
        if (pollTimer) return;
        pollTimer = setInterval(pollUnreadCount, POLL_INTERVAL_MS);
    }

    function stopPolling() {
        if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
    }

    // Pausar el polling cuando la pestaña no es visible para no gastar
    // ciclos/datos de quien tiene el CRM abierto en segundo plano, y
    // reanudar (con una consulta inmediata) al volver a primer plano.
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            stopPolling();
        } else {
            pollUnreadCount();
            startPolling();
        }
    });

    function csrf() {
        const i = document.querySelector('#formLogout input[name="csrf_token"]');
        return i ? i.value : '';
    }

    function postApi(data) {
        const fd = new FormData();
        fd.append('csrf_token', csrf());
        Object.entries(data).forEach(([k, v]) => fd.append(k, v));
        return fetch('/api/notificaciones', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
        }).then(r => r.json()).catch(() => ({ ok: false }));
    }

    function setBadge(n) {
        let badge = document.getElementById('sidebarNotifBadge');
        const unread = typeof n === 'number' ? n : 0;
        if (unread <= 0) {
            if (badge) badge.remove();
            return;
        }
        if (!badge) {
            badge = document.createElement('span');
            badge.id = 'sidebarNotifBadge';
            badge.className = 'nav-badge';
            btn.appendChild(badge);
        }
        badge.textContent = unread > 99 ? '99+' : String(unread);
    }

    function decBadge() {
        const badge = document.getElementById('sidebarNotifBadge');
        if (badge) setBadge(Math.max(0, (parseInt(badge.textContent, 10) || 0) - 1));
    }

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
    }

    function showPlaceholder() {
        listEl.innerHTML =
            '<div style="padding:12px;text-align:center;opacity:.65;color:rgba(255,255,255,.75);">' +
            'Sin avisos.<br/><span style="font-size:11px;">Aquí llegan mensajes cuando el coordinador rechaza docs, emite tu póliza o avisa renovaciones.</span>' +
            '</div>';
    }

    // ── Render ────────────────────────────────────────────────────────────

    function renderItems(items) {
        listEl.innerHTML = '';

        if (!items || !items.length) {
            showPlaceholder();
            return;
        }

        items.forEach((it) => {
            const leida = Number(it.leida) === 1;
            const id    = Number(it.id);

            const row = document.createElement('div');
            row.className = 'notif-item' + (leida ? '' : ' unread');
            row.style.cssText = 'display:flex;align-items:flex-start;gap:6px;cursor:pointer;position:relative;';
            row.dataset.id    = String(id);
            row.dataset.leida = leida ? '1' : '0';

            const content = document.createElement('div');
            content.style.cssText = 'flex:1;min-width:0;';
            content.innerHTML =
                '<div style="font-size:12px;' + (leida ? '' : 'font-weight:600;') + '">' +
                    (leida ? '' : '<span class="notif-dot" style="display:inline-block;width:7px;height:7px;border-radius:50%;background:#f3c332;margin-right:5px;vertical-align:middle;box-shadow:0 0 4px #f3c33288;flex-shrink:0;"></span>') +
                    escapeHtml(it.titulo) +
                '</div>' +
                '<div style="opacity:.92;font-weight:400;margin-top:3px;font-size:11px;line-height:1.35;">' +
                    escapeHtml(it.mensaje) +
                '</div>' +
                '<div class="notif-meta">' +
                    escapeHtml(it.creada_en || '') +
                '</div>';

            const delBtn = document.createElement('button');
            delBtn.type = 'button';
            delBtn.textContent = '✕';
            delBtn.title = 'Eliminar';
            delBtn.style.cssText =
                'background:none;border:none;color:rgba(255,255,255,.25);font-size:12px;' +
                'cursor:pointer;padding:2px 5px;border-radius:4px;flex-shrink:0;' +
                'transition:color .15s,background .15s;line-height:1;margin-top:1px;';

            delBtn.addEventListener('mouseenter', () => {
                delBtn.style.color      = '#fca5a5';
                delBtn.style.background = 'rgba(239,68,68,.12)';
            });
            delBtn.addEventListener('mouseleave', () => {
                delBtn.style.color      = 'rgba(255,255,255,.25)';
                delBtn.style.background = 'none';
            });
            delBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                eliminarNotif(id, row);
            });

            row.appendChild(content);
            row.appendChild(delBtn);

            row.addEventListener('click', (e) => {
                if (e.target === delBtn || delBtn.contains(e.target)) return;
                if (!leida) marcarLeidaVisual(id, row);
            });

            listEl.appendChild(row);
        });
    }

    // ── Cargar ────────────────────────────────────────────────────────────

    async function cargar() {
        listEl.innerHTML = '<div style="padding:10px;text-align:center;color:rgba(255,255,255,.4);font-size:12px;">Cargando…</div>';
        try {
            const r = await fetch('/api/notificaciones', { credentials: 'same-origin' });
            const d = await r.json();
            if (!d.ok) throw new Error('bad');
            setBadge(typeof d.unread === 'number' ? d.unread : 0);
            renderItems(d.items || []);
        } catch (_e) {
            listEl.innerHTML =
                '<div style="padding:10px;text-align:center;color:#fca5a5;">No se pudieron cargar los avisos.</div>';
        }
    }

    // ── Marcar leída ──────────────────────────────────────────────────────

    function marcarLeidaVisual(id, rowEl) {
        rowEl.classList.remove('unread');
        rowEl.dataset.leida = '1';
        const punto = rowEl.querySelector('.notif-dot');
        if (punto) punto.remove();
        const tituloDiv = rowEl.querySelector('div');
        if (tituloDiv) tituloDiv.style.fontWeight = '';
        decBadge();
        postApi({ accion: 'marcar_leida', id: String(id) });
    }

    // ── Eliminar individual ───────────────────────────────────────────────

    function eliminarNotif(id, rowEl) {
        const eraNoLeida = rowEl.dataset.leida === '0';

        rowEl.style.transition  = 'opacity .2s, max-height .25s, padding .25s, margin .25s';
        rowEl.style.overflow    = 'hidden';
        rowEl.style.maxHeight   = rowEl.offsetHeight + 'px';

        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                rowEl.style.opacity       = '0';
                rowEl.style.maxHeight     = '0';
                rowEl.style.paddingTop    = '0';
                rowEl.style.paddingBottom = '0';
                rowEl.style.marginBottom  = '0';
            });
        });

        setTimeout(() => {
            rowEl.remove();
            if (!listEl.querySelector('.notif-item')) showPlaceholder();
        }, 280);

        if (eraNoLeida) decBadge();

        // ← Ahora sí llama a eliminar, no a marcar_leida
        postApi({ accion: 'eliminar', id: String(id) });
    }

    // ── Marcar todas leídas ───────────────────────────────────────────────

    async function marcarTodas() {
        await postApi({ accion: 'marcar_todas' });
        await cargar();
    }

    // ── Limpiar todo ──────────────────────────────────────────────────────

    async function limpiarTodo() {
        if (!confirm('¿Limpiar todas las notificaciones?')) return;

        const rows = [...listEl.querySelectorAll('.notif-item')];

        // ← Ahora llama a eliminar_todas
        await postApi({ accion: 'eliminar_todas' });

        if (!rows.length) { showPlaceholder(); return; }

        rows.forEach((el, i) => {
            el.style.transition = 'opacity .18s';
            setTimeout(() => {
                el.style.opacity = '0';
                setTimeout(() => el.remove(), 190);
            }, i * 40);
        });

        setBadge(0);

        setTimeout(showPlaceholder, rows.length * 40 + 220);
    }

    // ── Listeners ─────────────────────────────────────────────────────────

    btn.addEventListener('click', () => {
        const open = panel.hidden;
        panel.hidden = !open;
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) cargar();
    });

    markAllBtn?.addEventListener('click',  (e) => { e.stopPropagation(); marcarTodas(); });
    reloadBtn?.addEventListener('click',   (e) => { e.stopPropagation(); cargar(); });
    clearAllBtn?.addEventListener('click', (e) => { e.stopPropagation(); limpiarTodo(); });

    // ── Arranque del tiempo real ──────────────────────────────────────────
    // Primera consulta inmediata (sin esperar el primer intervalo) y luego
    // ciclo cada POLL_INTERVAL_MS mientras la pestaña esté visible.
    if (!document.hidden) {
        pollUnreadCount();
        startPolling();
    }

})();
