/* =========================================================
   SEGUROS LUX — Landing JS
   ========================================================= */

document.addEventListener('DOMContentLoaded', () => {

  /* ---- NAVBAR scroll ---- */
  const navbar = document.querySelector('.navbar');
  window.addEventListener('scroll', () => {
    if (window.scrollY > 40) {
      navbar.style.boxShadow = '0 4px 24px rgba(0,0,0,0.10)';
    } else {
      navbar.style.boxShadow = 'none';
    }
  });

  /* ---- FADE-UP on scroll ---- */
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        e.target.classList.add('visible');
        observer.unobserve(e.target);
      }
    });
  }, { threshold: 0.12 });
  document.querySelectorAll('.fade-up').forEach(el => observer.observe(el));

  /* ---- FAQ accordion ---- */
  document.querySelectorAll('.faq-question').forEach(btn => {
    btn.addEventListener('click', () => {
      const item = btn.closest('.faq-item');
      const isOpen = item.classList.contains('open');
      document.querySelectorAll('.faq-item').forEach(i => i.classList.remove('open'));
      if (!isOpen) item.classList.add('open');
    });
  });

  /* ---- FORMULARIO de leads ---- */
  const form = document.getElementById('lead-form');
  const formWrap = document.getElementById('form-wrap');
  const formSuccess = document.getElementById('form-success');

  if (form) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();

      const btn = form.querySelector('.btn-submit');
      const btnOriginalHtml = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<span>Enviando...</span>';

      /* Recopilar datos del formulario.
         Los nombres de campo (vehiculo, tiene_seguro, intencion, vencimiento,
         pago, codigo_postal) coinciden con los que espera el endpoint del
         CRM en /api/vida-solicitud — ahí se traducen a los campos internos
         y se asigna automáticamente un asesor por menor carga. */
      const data = {
        nombre:         form.nombre.value.trim(),
        telefono:       form.telefono.value.trim(),
        correo:         form.correo.value.trim(),
        vehiculo:       form.vehiculo.value,
        codigo_postal:  form.codigo_postal.value.trim(),
        tiene_seguro:   form.tiene_seguro.value,
        intencion:      form.intencion.value,
        vencimiento:    form.vencimiento.value,
        pago:           form.pago.value,
      };

      try {
        const resp = await fetch('/api/vida-solicitud', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(data),
        });
        const result = await resp.json().catch(() => ({}));

        if (!resp.ok) {
          // El CRM devuelve 409 si el cliente ya tiene 3 citas activas,
          // o 400 si falta algún dato obligatorio.
          alert(result.msg || 'No se pudo enviar tu solicitud. Intenta de nuevo o escríbenos por WhatsApp.');
          btn.disabled = false;
          btn.innerHTML = btnOriginalHtml;
          return;
        }

        /* Éxito: el CRM ya asignó un asesor y le mandó el aviso en tiempo real */
        formWrap.style.display = 'none';
        formSuccess.classList.add('show');

      } catch (err) {
        console.error('[Seguros Lux] Error enviando lead:', err);
        alert('Hubo un problema de conexión. Por favor intenta de nuevo o escríbenos por WhatsApp.');
        btn.disabled = false;
        btn.innerHTML = btnOriginalHtml;
      }
    });
  }

  /* ---- Smooth scroll a #formulario desde botones ---- */
  document.querySelectorAll('[data-scroll]').forEach(el => {
    el.addEventListener('click', (e) => {
      e.preventDefault();
      const target = document.querySelector(el.dataset.scroll);
      if (target) target.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
  });

  /* ---- Contar estadísticas (animación numérica) ---- */
  const counters = document.querySelectorAll('[data-count]');
  const countObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (!entry.isIntersecting) return;
      const el = entry.target;
      const target = parseInt(el.dataset.count, 10);
      const suffix = el.dataset.suffix || '';
      let current = 0;
      const step = Math.ceil(target / 60);
      const timer = setInterval(() => {
        current = Math.min(current + step, target);
        el.textContent = current.toLocaleString('es-MX') + suffix;
        if (current >= target) clearInterval(timer);
      }, 22);
      countObserver.unobserve(el);
    });
  }, { threshold: 0.5 });
  counters.forEach(c => countObserver.observe(c));

});
