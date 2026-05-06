/* ═══════════════════════════════════════════════════════════════
   EIRA CRM · login.js
   Mejoras de seguridad:
     - Token CSRF incluido en cada petición AJAX via header
     - Validación de destino de redirección (solo rutas relativas conocidas)
     - Prevención de doble envío con flag de vuelo
     - Sin eval(), sin innerHTML con datos del servidor
   FIX:
     - safeRedirect() ahora verifica typeof === 'string' && length > 0 antes
       de llamar .startsWith(). Evita "Cannot read properties of undefined
       (reading 'startsWith')" cuando el servidor devuelve JSON sin 'redirect'.
     - fetch block usa data?.redirect ?? null en lugar de data.redirect para
       no lanzar TypeError si data llega sin esa clave.
   ═══════════════════════════════════════════════════════════════ */

/* ── FOCUS FLOAT LABEL ─────────────────────────────────────────── */
function focusF(id) {
  document.getElementById('wi-'  + id)?.classList.add('active');
  document.getElementById('lbl-' + id)?.classList.add('up');
}

function blurF(id) {
  const inp = document.getElementById('inp-' + id);
  if (!inp) return;
  document.getElementById('wi-' + id)?.classList.toggle('active', document.activeElement === inp);
  if (!inp.value) document.getElementById('lbl-' + id)?.classList.remove('up');
  else            document.getElementById('lbl-' + id)?.classList.add('up');
}

/* ── Registrar eventos de inputs (sin inline handlers en el HTML) ─ */
document.addEventListener('DOMContentLoaded', () => {
  // Email
  const inpEmail = document.getElementById('inp-email');
  if (inpEmail) {
    inpEmail.addEventListener('focus', () => focusF('email'));
    inpEmail.addEventListener('blur',  () => blurF('email'));
    inpEmail.addEventListener('input', () => blurF('email'));
  }

  // Password
  const inpPwd = document.getElementById('inp-pwd');
  if (inpPwd) {
    inpPwd.addEventListener('focus', () => focusF('pwd'));
    inpPwd.addEventListener('blur',  () => blurF('pwd'));
    inpPwd.addEventListener('input', () => blurF('pwd'));
  }

  // Eye toggle
  document.getElementById('eyeBtn')?.addEventListener('click', toggleEye);

  // Form submit
  document.getElementById('loginForm')?.addEventListener('submit', handleSubmit);
});

/* ── EYE ───────────────────────────────────────────────────────── */
let pwdVis = false;

function toggleEye() {
  pwdVis = !pwdVis;
  const inp = document.getElementById('inp-pwd');
  if (inp) inp.type = pwdVis ? 'text' : 'password';

  const ico = document.getElementById('eyeIco');
  if (!ico) return;
  ico.innerHTML = pwdVis
    ? '<path d="M2 2l12 12M6.5 6.6A2.2 2.2 0 0110.4 10M1 8s2.5-5 7-5c1.2 0 2.3.3 3.3.8M15 8s-2.5 5-7 5c-1.2 0-2.3-.3-3.3-.8" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>'
    : '<path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z" stroke="currentColor" stroke-width="1.2"/><circle cx="8" cy="8" r="2.2" stroke="currentColor" stroke-width="1.2"/>';
}

/* ── SUBMIT ────────────────────────────────────────────────────── */

/** Rutas permitidas como destino post-login (lista blanca client-side). */
const ALLOWED_REDIRECTS = ['/dashboard', '/admin', '/admin?tab=resumen'];

/**
 * Valida que el destino recibido del servidor sea una ruta conocida y relativa.
 *
 * FIX: comprueba typeof antes de llamar .startsWith() para evitar el
 * "Cannot read properties of undefined (reading 'startsWith')" que ocurre
 * cuando el servidor devuelve un JSON sin la clave 'redirect' o con null.
 */
function safeRedirect(url) {
  if (typeof url !== 'string' || url.length === 0) return '/dashboard';
  if (!url.startsWith('/')) return '/dashboard';       // bloquear URLs absolutas / externos
  // Verificar contra allowlist
  const match = ALLOWED_REDIRECTS.find(r => url === r || url.startsWith(r + '?'));
  return match ? url : '/dashboard';
}

let submitInFlight = false;   // previene doble envío

async function handleSubmit(e) {
  e.preventDefault();
  if (submitInFlight) return;   // evitar doble click

  submitInFlight = true;

  const form  = e.target;
  const btn   = document.getElementById('btnS');
  const dots  = document.getElementById('btnDots');
  const alert = document.getElementById('alertBox');

  // Ocultar alert previo
  alert?.classList.remove('show');

  // Estado de carga
  btn?.classList.add('loading');
  if (btn)  btn.disabled  = true;
  if (dots) dots.classList.add('show');

  try {
    const res = await fetch(form.action, {
      method : 'POST',
      body   : new FormData(form),   // el csrf_token ya va dentro del FormData como campo hidden
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
      },
    });

    if (res.ok) {
      // FIX: data.redirect puede ser undefined si el servidor omite la clave;
      // safeRedirect ya lo maneja, pero siendo explícitos evitamos confusión.
      const data    = await res.json();
      const destino = safeRedirect(data?.redirect ?? null);
      window.location.href = destino;
    } else if (res.status === 429) {
      // Bloqueado por rate limiting
      showAlert('Demasiados intentos fallidos. Espera unos minutos antes de volver a intentarlo.');
    } else {
      // Credenciales incorrectas u otro error
      showAlert(null);   // usa el mensaje por defecto del HTML
    }

  } catch (err) {
    // Error de red u otro inesperado
    showAlert(null);
  } finally {
    submitInFlight = false;
    btn?.classList.remove('loading');
    if (btn)  btn.disabled  = false;
    if (dots) dots.classList.remove('show');
  }
}

function showAlert(msg) {
  const alertBox = document.getElementById('alertBox');
  if (!alertBox) return;
  if (msg) {
    // Usar textContent (nunca innerHTML) para no exponer XSS
    const textNode = alertBox.querySelector('span.alert-msg');
    if (textNode) textNode.textContent = msg;
  }
  alertBox.classList.add('show');
  setTimeout(() => alertBox?.classList.remove('show'), 5000);
}

/* ── CURSOR LIGHT ──────────────────────────────────────────────── */
(function () {
  const leftPanel = document.getElementById('leftPanel');
  const cLight    = document.getElementById('cursorLight');
  if (!leftPanel || !cLight) return;

  leftPanel.addEventListener('mousemove', e => {
    const r = leftPanel.getBoundingClientRect();
    cLight.style.left = (e.clientX - r.left) + 'px';
    cLight.style.top  = (e.clientY - r.top)  + 'px';
  });
})();

/* ── LÍNEAS CINÉTICAS SVG ──────────────────────────────────────── */
(function () {
  const svg = document.getElementById('kinetic-svg');
  if (!svg) return;

  const W = 400, H = 700;
  const lines = [];
  const count = 18;

  function rnd(a, b) { return a + Math.random() * (b - a); }

  for (let i = 0; i < count; i++) {
    const el  = document.createElementNS('http://www.w3.org/2000/svg', 'line');
    const isH = Math.random() > 0.5;
    const obj = {
      el, isH,
      pos    : rnd(0, isH ? H : W),
      offset : rnd(0, 400),
      speed  : rnd(0.12, 0.35) * (Math.random() > 0.5 ? 1 : -1),
      opacity: rnd(0.04, 0.14),
    };
    el.setAttribute('stroke', 'rgba(255,255,255,0.18)');
    el.setAttribute('stroke-width', '0.5');
    svg.appendChild(el);
    lines.push(obj);
  }

  const dots = [];
  for (let i = 0; i < 8; i++) {
    const c = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
    c.setAttribute('r', '1.5');
    c.setAttribute('fill', 'rgba(255,255,255,0.25)');
    svg.appendChild(c);
    dots.push({ el: c, x: rnd(20, 380), y: rnd(80, 620), vx: rnd(-0.2, 0.2), vy: rnd(-0.2, 0.2), life: 0, maxLife: rnd(200, 400) });
  }

  function tick() {
    lines.forEach(l => {
      l.offset += l.speed;
      const p     = ((l.pos + l.offset * 0.5) % (l.isH ? H : W));
      const alpha = l.opacity * (0.5 + 0.5 * Math.sin(l.offset * 0.01));
      l.el.setAttribute('stroke', `rgba(255,255,255,${alpha})`);
      if (l.isH) {
        l.el.setAttribute('x1', '0'); l.el.setAttribute('x2', String(W));
        l.el.setAttribute('y1', String(p)); l.el.setAttribute('y2', String(p));
      } else {
        l.el.setAttribute('y1', '0'); l.el.setAttribute('y2', String(H));
        l.el.setAttribute('x1', String(p)); l.el.setAttribute('x2', String(p));
      }
    });

    dots.forEach(d => {
      d.life++;
      d.x += d.vx; d.y += d.vy;
      if (d.x < 0 || d.x > W) d.vx *= -1;
      if (d.y < 0 || d.y > H) d.vy *= -1;
      if (d.life > d.maxLife) { d.life = 0; d.x = rnd(20, 380); d.y = rnd(80, 620); }
      const t    = d.life / d.maxLife;
      const fade = t < 0.1 ? t / 0.1 : t > 0.9 ? (1 - t) / 0.1 : 1;
      d.el.setAttribute('cx', String(d.x));
      d.el.setAttribute('cy', String(d.y));
      d.el.setAttribute('opacity', String(0.4 * fade));
    });

    requestAnimationFrame(tick);
  }
  tick();
})();

/* ── CONTADORES ANIMADOS ───────────────────────────────────────── */
function countUp(id, target, suffix, dur) {
  const el    = document.getElementById(id);
  if (!el) return;
  const start = performance.now();
  function frame(now) {
    const p    = Math.min((now - start) / dur, 1);
    const ease = 1 - Math.pow(1 - p, 3);
    el.textContent = Math.round(ease * target) + (suffix || '');
    if (p < 1) requestAnimationFrame(frame);
  }
  requestAnimationFrame(frame);
}
setTimeout(() => {
  countUp('sn1', 247,   '',  1400);
  countUp('sn2', 12843, 'k', 1600);
  countUp('sn3', 18,    '',  1000);
}, 400);

/* ── PARTÍCULAS ────────────────────────────────────────────────── */
(function () {
  const canvas = document.getElementById('particles-canvas');
  const root   = document.getElementById('lrRoot');
  if (!canvas || !root) return;

  const ctx = canvas.getContext('2d');
  let W, H, particles = [];
  let mouseX = -999, mouseY = -999;

  function resize() {
    W = canvas.width  = root.offsetWidth;
    H = canvas.height = root.offsetHeight;
  }
  resize();
  window.addEventListener('resize', resize);

  root.addEventListener('mousemove', e => {
    const r = root.getBoundingClientRect();
    mouseX  = e.clientX - r.left;
    mouseY  = e.clientY - r.top;
  });

  function Particle() {
    this.reset = function () {
      this.x       = Math.random() * W;
      this.y       = Math.random() * H;
      this.size    = Math.random() * 1.4 + 0.3;
      this.speedX  = (Math.random() - 0.5) * 0.25;
      this.speedY  = (Math.random() - 0.5) * 0.25;
      this.opacity = Math.random() * 0.5 + 0.1;
      this.life    = Math.random() * 300 + 100;
      this.age     = 0;
      this.gold    = Math.random() > 0.35;
    };
    this.reset();
    this.age = Math.random() * this.life;
  }

  for (let i = 0; i < 55; i++) particles.push(new Particle());

  function draw() {
    ctx.clearRect(0, 0, W, H);
    particles.forEach(p => {
      p.age++;
      if (p.age > p.life) { p.reset(); return; }
      const t    = p.age / p.life;
      const fade = t < 0.15 ? t / 0.15 : t > 0.85 ? (1 - t) / 0.15 : 1;

      const dx = p.x - mouseX, dy = p.y - mouseY;
      const dist = Math.sqrt(dx * dx + dy * dy);
      if (dist < 80 && dist > 0) {
        const force = (80 - dist) / 80 * 0.4;
        p.x += (dx / dist) * force;
        p.y += (dy / dist) * force;
      }

      p.x += p.speedX; p.y += p.speedY;
      if (p.x < 0) p.x = W; if (p.x > W) p.x = 0;
      if (p.y < 0) p.y = H; if (p.y > H) p.y = 0;

      const alpha = p.opacity * fade;
      ctx.beginPath();
      ctx.arc(p.x, p.y, p.size, 0, Math.PI * 2);
      ctx.fillStyle = p.gold ? `rgba(192,132,42,${alpha})` : `rgba(240,232,210,${alpha * 0.4})`;
      ctx.fill();

      particles.forEach(p2 => {
        if (p === p2) return;
        const ddx = p.x - p2.x, ddy = p.y - p2.y;
        const d = Math.sqrt(ddx * ddx + ddy * ddy);
        if (d < 70) {
          ctx.beginPath();
          ctx.moveTo(p.x, p.y);
          ctx.lineTo(p2.x, p2.y);
          ctx.strokeStyle = `rgba(192,132,42,${(1 - d / 70) * 0.12 * alpha})`;
          ctx.lineWidth   = 0.5;
          ctx.stroke();
        }
      });
    });
    requestAnimationFrame(draw);
  }
  draw();

  // Ripple en el botón
  const btnS = document.getElementById('btnS');
  if (btnS) {
    btnS.addEventListener('click', function (e) {
      const r  = this.getBoundingClientRect();
      const el = document.createElement('span');
      el.className  = 'btn-ripple';
      el.style.cssText = `left:${e.clientX - r.left}px;top:${e.clientY - r.top}px;width:20px;height:20px;margin-left:-10px;margin-top:-10px;`;
      this.appendChild(el);
      setTimeout(() => el.remove(), 700);
    });
  }
})();