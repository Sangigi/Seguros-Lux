<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Seguros Lux | Protege lo que más importa</title>
<meta name="description" content="Seguros Lux: atención personal, especialistas en vehículos y respuesta rápida. Cotiza tu seguro vehicular, de vida o gastos médicos mayores." />
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Google+Sans:ital,opsz,wght@0,17..18,400..700;1,17..18,400..700&family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/landing.css">
<link rel="icon" type="image/png" sizes="32x32" href="/images/logo.png">
<link rel="stylesheet" href="css/nav-submenus.css">
<style>
  /* ========== HERO CON IMAGEN ========== */
  .hero {
    position: relative;
    color: var(--white);
    overflow: hidden;
    min-height: 580px;
    display: flex;
    flex-direction: column;
    justify-content: center;
  }
  .hero-img-wrap {
    position: absolute;
    inset: 0;
    z-index: 0;
  }
  .hero-img-wrap img {
    width: 100%; height: 100%;
    object-fit: cover;
    object-position: center;
    display: block;
  }
  .hero-img-wrap::after {
    content: '';
    position: absolute; inset: 0;
    background: linear-gradient(
      135deg,
      rgba(15,23,42,0.85) 0%,
      rgba(15,23,42,0.60) 50%,
      rgba(15,36,60,0.70) 100%
    );
  }
  .hero-inner {
    max-width: 1200px; margin: 0 auto;
    padding: 80px 24px 90px;
    display: grid; grid-template-columns: 1.1fr 0.9fr; gap: 60px; align-items: center;
    position: relative; z-index: 1;
  }
  .hero-eyebrow {
    display: inline-flex; align-items: center; gap: 8px;
    background: rgba(243, 195, 50, 0.15);
    border: 1px solid rgba(243, 195, 50, 0.35);
    color: var(--accent);
    padding: 6px 14px; border-radius: var(--radius-full);
    font-size: 13px; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase;
    margin-bottom: 22px;
  }
  .hero-eyebrow::before { content: '●'; font-size: 8px; animation: pulse 2s infinite; }
  @keyframes pulse { 0%,100% { opacity: 1 } 50% { opacity: 0.3 } }
  .hero h1 {
    font-family: var(--font-display);
    font-size: clamp(2.3rem, 5vw, 3.6rem);
    font-weight: 900; line-height: 1.05; letter-spacing: -1.5px;
    margin-bottom: 20px;
  }
  .hero h1 em { font-style: italic; color: var(--accent); font-weight: 500; }
  .hero p.lead { font-size: 18px; color: rgba(255,255,255,0.82); max-width: 520px; margin-bottom: 34px; }
  .hero-ctas { display: flex; flex-wrap: wrap; gap: 14px; margin-bottom: 36px; }
  .hero-trust { display: flex; gap: 26px; flex-wrap: wrap; font-size: 13px; color: rgba(255,255,255,0.7); }
  .hero-trust div { display: flex; align-items: center; gap: 8px; }
  .hero-trust svg { color: var(--accent); }
  .hero-visual { position: relative; height: 440px; }
  .glass-card {
    position: absolute; background: rgba(255,255,255,0.08);
    backdrop-filter: blur(14px); border: 1px solid rgba(255,255,255,0.15);
    border-radius: var(--radius-xl); padding: 22px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
  }
  .gc-1 { top: 0; right: 20px; width: 280px; transform: rotate(-4deg); }
  .gc-2 { top: 140px; left: 0; width: 260px; transform: rotate(3deg); background: var(--accent); border-color: var(--accent-dark); color: var(--primary); }
  .gc-3 { bottom: 0; right: 40px; width: 250px; transform: rotate(-2deg); }
  .glass-card .icon-box { width: 44px; height: 44px; border-radius: 12px; background: rgba(255,255,255,0.15); display: flex; align-items: center; justify-content: center; margin-bottom: 14px; }
  .gc-2 .icon-box { background: var(--primary); color: var(--accent); }
  .glass-card h4 { font-size: 16px; font-weight: 800; margin-bottom: 4px; }
  .glass-card p { font-size: 13px; opacity: 0.85; line-height: 1.4; }

  /* ========== BENEFICIOS ========== */
  .beneficios {
    background: var(--white);
    padding: 100px 0 80px;
    position: relative;
    margin-top: -40px;
    border-radius: 40px 40px 0 0;
    z-index: 2;
  }
  .reasons-grid {
    display: grid; grid-template-columns: repeat(3, 1fr); gap: 28px;
    max-width: 1100px; margin: 0 auto 60px;
  }
  .reason-card {
    background: var(--white);
    border: 1px solid var(--gray-200);
    border-radius: var(--radius-xl);
    padding: 36px 30px;
    position: relative;
    transition: transform var(--transition-normal), box-shadow var(--transition-normal), border-color var(--transition-normal);
    overflow: hidden;
  }
  .reason-card::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: var(--accent);
    transform: scaleX(0); transform-origin: left;
    transition: transform .4s;
  }
  .reason-card:hover { transform: translateY(-6px); box-shadow: var(--shadow-xl); border-color: transparent; }
  .reason-card:hover::before { transform: scaleX(1); }
  .reason-number {
    font-family: var(--font-display);
    font-size: 56px; font-weight: 900; font-style: italic;
    color: rgba(243, 195, 50, 0.15);
    line-height: 1; margin-bottom: -20px;
  }
  .reason-icon {
    width: 56px; height: 56px; border-radius: 14px;
    background: var(--primary); color: var(--accent);
    display: flex; align-items: center; justify-content: center;
    margin-bottom: 20px; position: relative; z-index: 1;
  }
  .reason-card h3 {
    font-family: var(--font-display);
    font-size: 22px; font-weight: 700; color: var(--primary);
    margin-bottom: 10px; letter-spacing: -0.3px;
  }
  .reason-card p { color: var(--gray-600); font-size: 15px; }

  .benefits-mini {
    display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px;
    max-width: 1100px; margin: 0 auto;
  }
  .benefit-mini {
    padding: 24px 20px; background: var(--gray-50);
    border-radius: var(--radius-md); text-align: center;
    border: 1px solid var(--gray-200);
  }
  .benefit-mini-icon {
    width: 44px; height: 44px; margin: 0 auto 12px;
    background: var(--white); border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    color: var(--accent-dark); box-shadow: var(--shadow-sm);
  }
  .benefit-mini h4 { font-size: 15px; font-weight: 800; color: var(--primary); margin-bottom: 6px; }
  .benefit-mini p { font-size: 13px; color: var(--gray-600); line-height: 1.5; }

  /* ========== SEGUROS ========== */
  .seguros { background: var(--gray-50); padding: 100px 0; }
  .seguros-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; }
  .seguro-card {
    background: var(--white); border-radius: var(--radius-xl);
    overflow: hidden; box-shadow: var(--shadow-sm);
    transition: transform var(--transition-normal), box-shadow var(--transition-normal);
    display: flex; flex-direction: column;
  }
  .seguro-card:hover { transform: translateY(-6px); box-shadow: var(--shadow-xl); }
  .seguro-header {
    padding: 32px 28px 24px;
    flex: 0 0 150px;
    color: var(--white); position: relative; overflow: hidden;
  }
  /* Cada producto con su color */
  .seguro-header.auto {
    background: linear-gradient(135deg, var(--accent) 0%, var(--accent) 100%);
    color: var(--primary);
  }
  .seguro-header.vida {
    background: var(--vida-gradient);
  }
  .seguro-header.gmm {
    background: var(--gmm-gradient);
  }
  .seguro-header::after {
    content: ''; position: absolute; top: -60px; right: -60px;
    width: 180px; height: 180px; border-radius: 50%;
    background: rgba(255,255,255,0.10);
  }
  .seguro-badge {
    display: inline-block;
    font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: var(--radius-full);
    letter-spacing: 1px; text-transform: uppercase; margin-bottom: 12px;
  }
  .seguro-header.auto .seguro-badge { background: var(--primary); color: var(--accent); }
  .seguro-header.vida .seguro-badge { background: var(--vida-dark); color: var(--white); }
  .seguro-header.gmm .seguro-badge { background: var(--gmm-dark); color: var(--white); }
  .seguro-header h3 {
    font-family: var(--font-display);
    font-size: 26px; font-weight: 800; letter-spacing: -0.5px;
    margin-bottom: 6px; position: relative;
  }
  .seguro-header p { font-size: 14px; opacity: 0.92; position: relative; }
  .seguro-body { padding: 26px 28px; flex: 1; display: flex; flex-direction: column; }
  .seguro-features { list-style: none; margin-bottom: 24px; flex: 1; }
  .seguro-features li {
    display: flex; align-items: flex-start; gap: 10px;
    font-size: 14px; color: var(--gray-600); padding: 7px 0;
  }
  .seguro-features svg { color: var(--success); flex-shrink: 0; margin-top: 2px; }
  .seguro-cta {
    display: inline-flex; align-items: center; justify-content: space-between;
    padding: 13px 20px; background: var(--gray-50); color: var(--primary);
    border-radius: var(--radius-full); font-weight: 700; font-size: 14px;
    transition: all var(--transition-fast);
  }
  .seguro-cta:hover { background: var(--primary); color: var(--accent); }
  .seguro-cta svg { transition: transform var(--transition-fast); }
  .seguro-cta:hover svg { transform: translateX(4px); }

  /* ========== TESTIMONIOS ========== */
  .testimonios { padding: 100px 0; background: var(--primary); color: var(--white); position: relative; overflow: hidden; }
  .testimonios::before {
    content: ''; position: absolute; inset: 0;
    background-image: radial-gradient(circle at 20% 30%, rgba(243, 195, 50, 0.10), transparent 40%);
  }
  .testimonios .section-title { color: var(--white); }
  .testimonios .section-description { color: rgba(255,255,255,0.7); }
  .testimonios .section-badge { color: var(--accent); }
  .testi-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; position: relative; }
  .testi-card {
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: var(--radius-xl);
    padding: 32px 26px; transition: background var(--transition-normal);
  }
  .testi-card:hover { background: rgba(255,255,255,0.07); }
  .testi-stars { color: var(--accent); margin-bottom: 14px; letter-spacing: 2px; font-size: 14px; }
  .testi-card p { font-size: 15px; color: rgba(255,255,255,0.88); margin-bottom: 22px; line-height: 1.55; }
  .testi-author { display: flex; align-items: center; gap: 12px; }
  .testi-avatar {
    width: 44px; height: 44px; border-radius: 50%;
    background: var(--accent); color: var(--primary);
    display: flex; align-items: center; justify-content: center;
    font-weight: 800; font-size: 15px;
  }
  .testi-author strong { display: block; color: var(--white); font-size: 14px; }
  .testi-author span { font-size: 12px; color: rgba(255,255,255,0.6); }

  /* ========== CONTACTO ========== */
  .contacto-section { padding: 100px 0; background: var(--gray-50); }
  .contacto-grid {
    display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px;
    max-width: 1000px; margin: 0 auto;
  }
  .contacto-card {
    background: var(--white); border-radius: var(--radius-xl);
    padding: 36px 28px; text-align: center;
    border: 1px solid var(--gray-200);
    transition: transform var(--transition-normal), box-shadow var(--transition-normal);
  }
  .contacto-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-md); }
  .contacto-icon {
    width: 60px; height: 60px; margin: 0 auto 18px;
    background: var(--primary); color: var(--accent);
    border-radius: 16px;
    display: flex; align-items: center; justify-content: center;
  }
  .contacto-card h3 { font-family: var(--font-display); font-size: 20px; color: var(--primary); margin-bottom: 8px; }
  .contacto-card p { color: var(--gray-600); font-size: 14px; margin-bottom: 16px; }
  .contacto-card a.link {
    font-weight: 700; color: var(--accent-dark); font-size: 16px;
    border-bottom: 2px solid var(--accent); padding-bottom: 2px;
  }
  .contacto-card a.link:hover { color: var(--primary); }

  /* ========== AVISOS LEGALES ========== */
  .avisos { padding: 70px 0; background: var(--white); }
  .avisos-wrap { max-width: 860px; margin: 0 auto; }
  .avisos-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
  .aviso-link {
    padding: 18px 20px; background: var(--gray-50);
    border-radius: var(--radius-md); border: 1px solid var(--gray-200);
    display: flex; align-items: center; gap: 12px;
    font-weight: 600; color: var(--primary); font-size: 14px;
    transition: all var(--transition-fast);
  }
  .aviso-link:hover { background: var(--primary); color: var(--white); border-color: var(--primary); }
  .aviso-link svg { color: var(--accent); flex-shrink: 0; }

  @media (max-width: 900px) {
    .hero-inner { grid-template-columns: 1fr; padding: 60px 24px 70px; gap: 40px; }
    .hero-visual { height: 360px; }
    .reasons-grid, .seguros-grid, .testi-grid, .contacto-grid { grid-template-columns: 1fr; }
    .benefits-mini { grid-template-columns: repeat(2, 1fr); }
    .beneficios { border-radius: 28px 28px 0 0; padding: 70px 0 60px; }
    .seguros, .testimonios, .contacto-section { padding: 70px 0; }
    .avisos-grid { grid-template-columns: 1fr; }
  }
  @media (max-width: 520px) {
    .hero-ctas { flex-direction: column; align-items: stretch; }
    .btn { justify-content: center; }
  }
</style>
</head>
<body>

<!-- NAV -->
<nav class="nav">
  <div class="nav-inner">
    <a href="/" class="nav-logo">Seguros<span>Lux</span></a>
    <ul class="nav-menu">
      <li><a href="/">Inicio</a></li>
      <li><a href="#beneficios">Beneficios</a></li>
      <li class="has-submenu">
        <a href="#seguros">Seguros</a>
        <ul class="submenu">
          <li><a href="/seguros/autos">Seguro Vehicular</a></li>
          <li><a href="seguros/vida">Seguro de Vida</a></li>
          <li><a href="seguros/gmm">Gastos Médicos Mayores</a></li>
          <li><a href="seguros/empresas">Seguros Empresariales</a></li>
        </ul>
      </li>
      <li class="has-submenu">
        <a href="#recursos">Recursos</a>
        <ul class="submenu">
          <li><a href="#aseguradoras">Aseguradoras</a></li>
          <li><a href="#blog">Blog</a></li>
          <li><a href="#faq">Preguntas Frecuentes</a></li>
          <li><a href="#avisos-legales">Avisos Legales</a></li>
        </ul>
      </li>
      <li><a href="#contacto">Contacto</a></li>
    </ul>
    <div class="nav-right">
      <a href="tel:+525541232311" class="nav-phone">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
        55 4123 2311
      </a>
      <a href="/formulario" class="nav-cta">
        <span class="hide-sm">Cotizar</span>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
      </a>
      <button class="nav-toggle" aria-label="Menú">☰</button>
    </div>
  </div>
</nav>

<!-- HERO -->
<section class="hero" id="inicio">
  <div class="hero-img-wrap">
    <img
      src="/images/banner_principal.webp"
      alt="Seguros Lux — Protege lo que más importa"
      width="1440" height="680"
      fetchpriority="high"
      decoding="async"
    >
  </div>
  <div class="hero-inner">
    <div class="hero-content">
      <div class="hero-eyebrow">Aseguradora #1 en México</div>
      <h1>Protege <em>lo que más</em> importa.</h1>
      <p class="lead">
        Atención personal, especialistas en vehículos y respuesta rápida.
        Así protegemos tu patrimonio, tu familia y tu salud.
      </p>
      <div class="hero-ctas">
        <a href="/formulario" class="btn btn-primary">Cotiza tu seguro gratis</a>
        <a href="tel:+525541232311" class="btn btn-ghost">55 4123 2311</a>
      </div>
    </div>
    <div class="hero-visual" aria-hidden="true">
      <div class="glass-card gc-1">
        <div class="icon-box">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none"
            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z"/>
          </svg>
        </div>
        <h4>Seguro de Vida</h4>
        <p>Estamos contigo cuando la vida lo exige.</p>
      </div>
      <div class="glass-card gc-2">
        <div class="icon-box">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none"
            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 13l2-5h14l2 5"/>
            <path d="M5 16h14"/>
            <circle cx="7.5" cy="16.5" r="1.5"/>
            <circle cx="16.5" cy="16.5" r="1.5"/>
            <path d="M12 3l4 4-4 4-4-4 4-4z"/>
          </svg>
        </div>
        <h4>Especialistas en vehículos</h4>
        <p>Coberturas pensadas para todo tipo de vehículos.</p>
      </div>
      <div class="glass-card gc-3">
        <div class="icon-box">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none"
            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="3" width="18" height="18" rx="2"/>
            <path d="M12 8v8M8 12h8"/>
          </svg>
        </div>
        <h4>Gastos Médicos Mayores</h4>
        <p>Atención médica oportuna cuando más lo necesitas.</p>
      </div>
    </div>
  </div>
</section>

<!-- BENEFICIOS -->
<section class="beneficios" id="beneficios">
  <div class="container">
    <div class="section-header center reveal">
      <span class="section-badge">Por qué elegirnos</span>
      <h2 class="section-title">Tres razones que hacen <em>la diferencia</em></h2>
      <p class="section-description">No vendemos pólizas. Resolvemos lo que importa, cuando importa.</p>
    </div>

    <div class="reasons-grid">
      <div class="reason-card reveal">
        <div class="reason-number">01</div>
        <div class="reason-icon">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        </div>
        <h3>Atención personal</h3>
        <p>Un asesor real que te conoce por tu nombre desde el primer día. Sin bots: un solo contacto que te acompaña siempre.</p>
      </div>
      <div class="reason-card reveal">
        <div class="reason-number">02</div>
        <div class="reason-icon">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none"
            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 13l2-5h14l2 5"/>
            <path d="M5 16h14"/>
            <circle cx="7.5" cy="16.5" r="1.5"/>
            <circle cx="16.5" cy="16.5" r="1.5"/>
            <path d="M12 3l4 4-4 4-4-4 4-4z"/>
          </svg>
        </div>
        <h3>Especialistas en vehículos</h3>
        <p>Conocemos la vida sobre el camino. Coberturas diseñadas para todo tipo de vehículos, con protección integral y asistencia vial inmediata.</p>
      </div>
      <div class="reason-card reveal">
        <div class="reason-number">03</div>
        <div class="reason-icon">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
        </div>
        <h3>Respuesta rápida</h3>
        <p>Los imprevistos no esperan. Por eso te atendemos por teléfono o WhatsApp, cualquier día y a cualquier hora.</p>
      </div>
    </div>

    <div class="benefits-mini">
      <div class="benefit-mini reveal">
        <div class="benefit-mini-icon">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <h4>Respuesta inmediata</h4>
        <p>Atención disponible 24/7 cuando más lo necesitas.</p>
      </div>
      <div class="benefit-mini reveal">
        <div class="benefit-mini-icon">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <h4>Precios competitivos</h4>
        <p>Comparamos entre las principales aseguradoras sin letra pequeña.</p>
      </div>
      <div class="benefit-mini reveal">
        <div class="benefit-mini-icon">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        </div>
        <h4>Asesor dedicado</h4>
        <p>Un solo contacto que conoce tu perfil desde el primer día.</p>
      </div>
      <div class="benefit-mini reveal">
        <div class="benefit-mini-icon">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        </div>
        <h4>Proceso digital</h4>
        <p>Cotiza de forma sencilla y recibe acompañamiento personalizado por WhatsApp.</p>
      </div>
    </div>
  </div>
</section>

<!-- SEGUROS -->
<section class="seguros" id="seguros">
  <div class="container">
    <div class="section-header center reveal">
      <span class="section-badge">Tu tranquilidad</span>
      <h2 class="section-title">Vive <em>sin preocupaciones</em></h2>
      <p class="section-description">Tu familia, tu patrimonio, tu salud. Tres protecciones esenciales, las mejores coberturas del mercado.</p>
    </div>
    <div class="seguros-grid">
      <article class="seguro-card reveal">
        <div class="seguro-header auto">
          <h3>Tu Vehículo</h3>
          <p>Vehículos con asistencia vial 24/7.</p>
          <span class="seguro-badge" style="margin: 10px 0;">★ Nuestro fuerte</span>
        </div>
        <div class="seguro-body">
          <ul class="seguro-features">
            <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Cobertura amplia, amplia total o premium</li>
            <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Plan especial para vehículos</li>
            <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Grúa, cambio de llanta, combustible, cerrajero y paso de corriente</li>
            <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Camiones y flotillas</li>
          </ul>
          <a href="seguros/autos" class="seguro-cta">
            Conocer más
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
          </a>
        </div>
      </article>

      <article class="seguro-card reveal">
        <div class="seguro-header vida">
          <h3>Tu Familia</h3>
          <p>Seguro de vida para los que más amas.</p>
        </div>
        <div class="seguro-body">
          <ul class="seguro-features">
            <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Fallecimiento por cualquier causa</li>
            <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Invalidez total y permanente</li>
            <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Retiro con deducibilidad fiscal</li>
            <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Suma asegurada flexible, cubrimos tus necesidades y nos ajustamos a tu presupuesto</li>
          </ul>
          <a href="seguros/vida" class="seguro-cta">
            Conocer más
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
          </a>
        </div>
      </article>

      <article class="seguro-card reveal">
        <div class="seguro-header gmm">
          <h3>Tu Salud</h3>
          <p>Gastos médicos en los mejores hospitales.</p>
        </div>
        <div class="seguro-body">
          <ul class="seguro-features">
            <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Hospitales en convenio, médicos certificados</li>
            <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Pago directo al hospital</li>
            <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Orientación médica 24/7</li>
            <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Cobertura nacional</li>
            <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Asistencias medicas en convenio</li>
          </ul>
          <a href="seguros/gmm" class="seguro-cta">
            Conocer más
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
          </a>
        </div>
      </article>
    </div>
  </div>
</section>

<!-- TESTIMONIOS -->
<section class="testimonios">
  <div class="container">
    <div class="section-header center reveal">
      <span class="section-badge">Testimonios</span>
      <h2 class="section-title">Lo que dicen <em>nuestros clientes</em></h2>
      <p class="section-description">Miles de personas confían en nosotros para proteger lo que más les importa.</p>
    </div>
    <div class="testi-grid">
      <div class="testi-card reveal">
        <div class="testi-stars">★★★★★</div>
        <p>"Tuve un percance y recibí atención pronta que me ayudó a resolver mi problema. Totalmente recomendados."</p>
        <div class="testi-author">
          <div class="testi-avatar">MG</div>
          <div>
            <strong>María García</strong>
            <span>Cliente desde 2023</span>
          </div>
        </div>
      </div>
      <div class="testi-card reveal">
        <div class="testi-stars">★★★★★</div>
        <p>"El proceso de cotización fue muy rápido y el precio justo. Mi asesor siempre está disponible para resolver mis dudas."</p>
        <div class="testi-author">
          <div class="testi-avatar">CR</div>
          <div>
            <strong>Carlos Rodríguez</strong>
            <span>Cliente desde 2018</span>
          </div>
        </div>
      </div>
      <div class="testi-card reveal">
        <div class="testi-stars">★★★★★</div>
        <p>"Llevo 3 años con ellos y al momento de mi choque me atendieron y me sentí respaldado. El seguro premium vale cada peso que pagas."</p>
        <div class="testi-author">
          <div class="testi-avatar">AL</div>
          <div>
            <strong>Ana López</strong>
            <span>Cliente desde 2022</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Partners Section -->
<section class="partners" id="aseguradoras">
  <div class="container">
    <div class="section-header center">
      <span class="section-badge">Respaldo Total</span>
      <h2 class="section-title">Aseguradoras que nos respaldan</h2>
      <p class="section-description">
        Trabajamos con las principales aseguradoras de México para ofrecerte protección real y confiable.
      </p>
    </div>

    <div class="slider-container">
      <div class="partners-track">
        <div class="partner-card"><img src="images/qualtas.webp" alt="Qualitas"><span class="partner-name">Qualitas</span></div>
        <div class="partner-card"><img src="images/gnp.webp" alt="GNP"><span class="partner-name">GNP</span></div>
        <div class="partner-card"><img src="images/chubb.webp" alt="Chubb"><span class="partner-name">Chubb</span></div>
        <div class="partner-card"><img src="images/inbursa.webp" alt="Inbursa"><span class="partner-name">Inbursa</span></div>

        <div class="partner-card"><img src="images/qualtas.webp" alt="Qualitas"><span class="partner-name">Qualitas</span></div>
        <div class="partner-card"><img src="images/gnp.webp" alt="GNP"><span class="partner-name">GNP</span></div>
        <div class="partner-card"><img src="images/chubb.webp" alt="Chubb"><span class="partner-name">Chubb</span></div>
        <div class="partner-card"><img src="images/inbursa.webp" alt="Inbursa"><span class="partner-name">Inbursa</span></div>
      </div>
    </div>

    <p class="partners-note">Las coberturas, deducibles y condiciones están sujetas a cada aseguradora.</p>
  </div>
</section>

<!-- ================================================================
    SECCIÓN BLOG — últimas 3 entradas desde WordPress API
================================================================ -->
<section class="blog-preview" id="blog">
  <div class="container">
    <div class="section-header center">
      <span class="section-badge">Consejos y Noticias</span>
      <h2 class="section-title">Artículos del Blog</h2>
      <p class="section-description">
        Mantente informado sobre seguros, protección financiera y consejos
        para cuidar lo que más importa.
      </p>
    </div>
    <!-- Estado de carga -->
    <div class="blog-loading" id="blogLoading" aria-live="polite">
      <div class="blog-spinner"></div>
      <p>Cargando artículos…</p>
    </div>
    <!-- Mensaje de error -->
    <div class="blog-error" id="blogError" hidden>
      <svg width="40" height="40" viewBox="0 0 24 24" fill="none"
           stroke="currentColor" stroke-width="1.5">
        <circle cx="12" cy="12" r="10"/>
        <line x1="12" y1="8" x2="12" y2="12"/>
        <line x1="12" y1="16" x2="12.01" y2="16"/>
      </svg>
      <p>No pudimos cargar los artículos en este momento.</p>
      <a href="/blog" class="btn btn-outline-sm">Ver Blog</a>
    </div>
    <!-- Grid de tarjetas (se rellena con JS) -->
    <div class="blog-grid" id="blogGrid" hidden>
      <!-- Las tarjetas se inyectan dinámicamente -->
    </div>
    <div class="blog-cta" id="blogCta" hidden>
      <a href="https://blog.seguroslux.mx/blog/" class="btn btn-ghost-accent">
        Ver todos los artículos
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2">
          <path d="M5 12h14M12 5l7 7-7 7"/>
        </svg>
      </a>
    </div>
  </div>
</section>
<!-- FIN SECCIÓN BLOG -->

<!-- FAQ -->
<section class="faq-section" id="faq">
  <div class="container">
    <div class="section-header center reveal">
      <span class="section-badge">Preguntas frecuentes</span>
      <h2 class="section-title">Resolvemos <em>tus dudas</em></h2>
      <p class="section-description">Encuentra respuestas a las preguntas más comunes sobre nuestros seguros.</p>
    </div>
    <div class="faq-wrap">
      <details class="faq-item">
        <summary>¿Qué tipos de seguros ofrecen?</summary>
        <div class="faq-answer">Ofrecemos tres tipos principales: Seguro Vehicular con cobertura amplia y asistencia vial 24/7, Seguro de Vida para proteger a tu familia, y Gastos Médicos Mayores para cobertura hospitalaria integral.</div>
      </details>
      <details class="faq-item">
        <summary>¿Cuánto tarda en activarse mi póliza?</summary>
        <div class="faq-answer">Se activa inmediatamente después de realizar el pago. Recibirás tu póliza digital por correo electrónico. Se aplican términos y condiciones.</div>
      </details>
      <details class="faq-item">
        <summary>¿Puedo cancelar mi seguro?</summary>
        <div class="faq-answer">Sí, puedes cancelar tu seguro en cualquier momento. Nosotros te guiamos en el proceso y te devolvemos la parte proporcional no utilizada de tu prima.</div>
      </details>
      <details class="faq-item">
        <summary>¿Cómo puedo contactar a un asesor?</summary>
        <div class="faq-answer">Por teléfono al 55 4123 2311, por WhatsApp, o llenando nuestro formulario de contacto. Estamos disponibles para atenderte.</div>
      </details>
      <details class="faq-item">
        <summary>¿Son especialistas en vehículos?</summary>
        <div class="faq-answer">Sí. Llevamos años asesorando a vehículos y conocemos a detalle las coberturas, deducibles y asistencias que realmente marcan la diferencia sobre dos ruedas.</div>
      </details>
      <details class="faq-item">
        <summary>¿Tienes alguna opción para mi retiro?</summary>
        <div class="faq-answer">Sí. Contamos con planes para tu retiro, donde también podemos ayudarte con la deducibilidad de impuestos.</div>
      </details>
      <details class="faq-item">
        <summary>¿Cuentan con planes para la educación de mis hijos?</summary>
        <div class="faq-answer">Sí. Contamos con las mejores estrategias para cubrir varias etapas de su educación.</div>
      </details>
      <details class="faq-item">
        <summary>¿Cuentan con planes para la salud?</summary>
        <div class="faq-answer">Sí. Contamos con Seguros de Gastos Médicos Mayores con diferentes empresas, cubriendo tus necesidades y ajustándonos a tu presupuesto.</div>
      </details>
    </div>
  </div>
</section>

<!-- CONTACTO -->
<section class="contacto-section" id="contacto">
  <div class="container">
    <div class="section-header center reveal">
      <span class="section-badge">Siempre contigo</span>
      <h2 class="section-title">Atención a <em>clientes</em></h2>
      <p class="section-description">Siempre hay un asesor real listo para ayudarte. Contáctanos por el medio que prefieras.</p>
    </div>
    <div class="contacto-grid">
      <div class="contacto-card reveal">
        <div class="contacto-icon">
          <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
        </div>
        <h3>Teléfono</h3>
        <p>Línea directa para emergencias y atención general.</p>
        <a href="tel:+525541232311" class="link">55 4123 2311</a>
      </div>
      <div class="contacto-card reveal">
        <div class="contacto-icon">
          <svg width="26" height="26" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347M12 2C6.48 2 2 6.48 2 12c0 1.77.46 3.45 1.27 4.9L2 22l5.25-1.38C8.66 21.5 10.27 22 12 22c5.52 0 10-4.48 10-10S17.52 2 12 2z"/></svg>
        </div>
        <h3>WhatsApp</h3>
        <p>Escríbenos y recibe respuesta inmediata.</p>
        <a href="https://wa.me/525541232311" class="link">Enviar mensaje</a>
      </div>
      <div class="contacto-card reveal">
        <div class="contacto-icon">
          <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
        </div>
        <h3>Correo electrónico</h3>
        <p>Para consultas detalladas y documentación.</p>
        <a href="mailto:info@seguroslux.mx" class="link">info@seguroslux.mx</a>
      </div>
    </div>
  </div>
</section>

<!-- AVISOS LEGALES -->
<section class="avisos" id="avisos-legales">
  <div class="container">
    <div class="avisos-wrap">
      <div class="section-header center" style="margin-bottom: 30px;">
        <span class="section-badge">Recursos</span>
        <h2 class="section-title" style="font-size: 1.8rem;">Avisos <em>Legales</em></h2>
      </div>
      <div class="avisos-grid">
        <a href="/aviso-privacidad" class="aviso-link">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          Aviso de Privacidad
        </a>
        <a href="/terminos" class="aviso-link">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
          Términos y Condiciones
        </a>
        <a href="/condiciones" class="aviso-link">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M9 12l2 2 4-4"/><path d="M21 12c0 4.97-4.03 9-9 9s-9-4.03-9-9 4.03-9 9-9c2.49 0 4.74 1.01 6.36 2.64"/></svg>
          Condiciones Generales
        </a>
      </div>
      <p style="text-align: center; margin-top: 30px; font-size: 13px; color: var(--gray-400); line-height: 1.7;">
        <strong>Aviso importante:</strong> La información publicada en este sitio es de carácter informativo. Las coberturas, precios y condiciones están sujetos a los términos de cada aseguradora y al perfil del solicitante. Consulta las condiciones generales de tu póliza antes de contratar.
      </p>
    </div>
  </div>
</section>

<!-- CTA FINAL -->
<section class="cta-final">
  <div class="container">
    <h2>Protege lo que más <em>importa hoy</em></h2>
    <p>Completa nuestro formulario rápido y recibe una cotización personalizada rápida. Sin compromiso, sin letra pequeña.</p>
    <div class="cta-final-actions">
      <a href="/formulario" class="btn btn-primary">
        Llenar Formulario
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
      </a>
      <a href="tel:+525541232311" class="btn btn-ghost">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
        Llamar Ahora
      </a>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer>
  <div class="container">
    <div class="footer-grid">
      <div class="footer-about">
        <div class="footer-logo">Seguros<span>Lux</span></div>
        <p>Protegiendo lo que más importa desde 2010. Tu tranquilidad es nuestra prioridad.</p>
        <div class="footer-contact-item">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
          <a href="tel:+525541232311">55 4123 2311</a>
        </div>
        <div class="footer-contact-item">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
          <a href="mailto:info@seguroslux.mx">info@seguroslux.mx</a>
        </div>
        <div class="footer-contact-item">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
          CDMX, México
        </div>
      </div>
      <div>
        <h5>Seguros</h5>
        <ul>
          <li><a href="seguros/autos">Seguro Vehicular</a></li>
          <li><a href="seguros/vida">Seguro de Vida</a></li>
          <li><a href="seguros/gmm">Gastos Médicos</a></li>
          <li><a href="/formulario">Cotiza en línea</a></li>
        </ul>
      </div>
      <div>
        <h5>Recursos</h5>
        <ul>
          <li><a href="#aseguradoras">Aseguradoras</a></li>
          <li><a href="#blog">Blog</a></li>
          <li><a href="#faq">Preguntas frecuentes</a></li>
          <li><a href="#contacto">Atención a clientes</a></li>
        </ul>
      </div>
      <div>
        <h5>Legal</h5>
        <ul>
          <li><a href="/aviso-privacidad">Aviso de privacidad</a></li>
          <li><a href="/terminos">Términos y condiciones</a></li>
          <li><a href="/condiciones">Condiciones generales</a></li>
          <li><a href="/login">Corporativo</a></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <div>©Seguros Lux. Todos los derechos reservados.</div>
      <div>Secure Sockets Layer (SSL)</div>
      <div>Powered by <strong style="color: #fff">GrupoIdeas</strong></div>
    </div>
  </div>
</footer>

<a href="https://wa.me/5215514605959" class="whatsapp-float" aria-label="WhatsApp" target="_blank">
  <svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347M12 2C6.48 2 2 6.48 2 12c0 1.77.46 3.45 1.27 4.9L2 22l5.25-1.38C8.66 21.5 10.27 22 12 22c5.52 0 10-4.48 10-10S17.52 2 12 2z"/></svg>
</a>

<script src="js/landing.js" defer></script>
</body>
</html>