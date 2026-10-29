/* ============================================
   SEGUROS LUX · landing.js
   - Scroll reveal
   - Mobile menu toggle
   - Plan tabs (subpáginas)
   - Quote tabs (cotizador inline)
   - Blog WordPress API (últimas 3 entradas)
   ============================================ */

document.addEventListener('DOMContentLoaded', function() {
    initBlogPreview();
});

// ---------- Scroll reveal ----------
const io = new IntersectionObserver((entries) => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      e.target.classList.add('in');
      io.unobserve(e.target);
    }
  });
}, { threshold: 0.12 });
document.querySelectorAll('.reveal').forEach(el => io.observe(el));

// ---------- Mobile menu ----------
const navToggle = document.querySelector('.nav-toggle');
const navMenu = document.querySelector('.nav-menu');
navToggle?.addEventListener('click', () => navMenu.classList.toggle('open'));

// ---------- Plan tabs (subpáginas autos.html, gmm.html) ----------
document.querySelectorAll('.plan-tabs').forEach(group => {
  const tabs = group.querySelectorAll('.plan-tab');
  const panels = group.parentElement.querySelectorAll('.plan-panel');
  tabs.forEach(tab => {
    tab.addEventListener('click', () => {
      tabs.forEach(t => t.classList.remove('active'));
      panels.forEach(p => p.classList.remove('active'));
      tab.classList.add('active');
      const target = tab.dataset.target;
      const panel = group.parentElement.querySelector(`[data-panel="${target}"]`);
      panel?.classList.add('active');
    });
  });
});

// ---------- Quote tabs (cotizador inline en autos.html) ----------
document.querySelectorAll('.quote-tab').forEach(tab => {
  tab.addEventListener('click', () => {
    document.querySelectorAll('.quote-tab').forEach(t => t.classList.remove('active'));
    tab.classList.add('active');
  });
});

// ---------- BLOG: últimas 3 entradas WordPress API ----------
function initBlogPreview() {
  const grid     = document.getElementById('blogGrid');
  const loading  = document.getElementById('blogLoading');
  const errorBox = document.getElementById('blogError');
  const cta      = document.getElementById('blogCta');

  if (!grid) return; // no estamos en una página con sección de blog

  const WP_ENDPOINT = 'https://blog.seguroslux.mx/wp-json/wp/v2/posts?_embed=true&per_page=3';

  const showError = () => {
    if (loading)  loading.hidden  = true;
    if (grid)     grid.hidden     = true;
    if (cta)      cta.hidden      = true;
    if (errorBox) errorBox.hidden = false;
  };

  const stripHtml = (html) => {
    const div = document.createElement('div');
    div.innerHTML = html;
    return (div.textContent || div.innerText || '').trim();
  };

  const truncate = (text, max = 130) => {
    if (text.length <= max) return text;
    return text.substring(0, max).replace(/\s+\S*$/, '') + '…';
  };

  const formatDate = (iso) => {
    try {
      const d = new Date(iso);
      return d.toLocaleDateString('es-MX', {
        day: 'numeric', month: 'long', year: 'numeric'
      });
    } catch { return ''; }
  };

  const placeholderEmoji = () => {
    const iconos = ['newspaper', 'pencil-square', 'lightbulb', 'shield-check', 'car-front', 'heart-pulse'];
    return iconoSvg(iconos[Math.floor(Math.random() * iconos.length)]);
  };

  fetch(WP_ENDPOINT, { headers: { 'Accept': 'application/json' } })
    .then(res => {
      if (!res.ok) throw new Error('HTTP ' + res.status);
      return res.json();
    })
    .then(posts => {
      if (!Array.isArray(posts) || posts.length === 0) {
        showError();
        return;
      }

      grid.innerHTML = posts.map(post => {
        const title   = stripHtml(post.title?.rendered   || 'Artículo');
        const excerpt = truncate(stripHtml(post.excerpt?.rendered || ''));
        const link    = post.link || '/blog';
        const date    = formatDate(post.date);

        // Imagen destacada (vía _embedded)
        const media  = post._embedded?.['wp:featuredmedia']?.[0];
        const imgUrl = media?.source_url
                    || media?.media_details?.sizes?.medium_large?.source_url;
        const imgAlt = media?.alt_text || title;

        // Categoría primaria
        const term     = post._embedded?.['wp:term']?.[0]?.[0];
        const category = term?.name || 'Blog';

        // FIX img-src CSP: si la imagen viene de blog.seguroslux.mx
        // la cargamos via proxy o la omitimos si el CSP no la permite.
        // Por ahora la incluimos normalmente; el fix real es en SessionManager.php
        // (img-src ya incluye https://blog.seguroslux.mx en el archivo generado).
        const imageHtml = imgUrl
          ? `<img src="${imgUrl}" alt="${imgAlt}" loading="lazy" />`
          : `<div class="placeholder-emoji">${placeholderEmoji()}</div>`;

        return `
          <article class="blog-card">
            <a href="${link}" class="blog-card-image" aria-label="${title}">
              ${imageHtml}
            </a>
            <div class="blog-card-content">
              <div class="blog-card-meta">
                <span class="blog-category">${category}</span>
                <span>${date}</span>
              </div>
              <h3><a href="${link}">${title}</a></h3>
              <p class="blog-card-excerpt">${excerpt}</p>
              <a href="${link}" class="blog-card-link">
                Leer más
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                  <path d="M5 12h14M12 5l7 7-7 7"/>
                </svg>
              </a>
            </div>
          </article>
        `;
      }).join('');

      if (loading)  loading.hidden  = true;
      if (grid)     grid.hidden     = false;
      if (cta)      cta.hidden      = false;
    })
    .catch(err => {
      console.warn('[Blog] No se pudo cargar:', err);
      showError();
    });
}