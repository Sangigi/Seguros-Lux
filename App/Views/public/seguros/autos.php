<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Seguro Vehicular | Seguros Lux</title>
<meta name="description" content="Seguro vehicular para autos, motos, camiones y flotillas. Cobertura amplia, asistencia vial 24/7 y atención personalizada." />
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Google+Sans:ital,opsz,wght@0,17..18,400..700;1,17..18,400..700&family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../css/landing.css">
<link rel="icon" type="image/png" sizes="32x32" href="/images/logo.png">
<link rel="stylesheet" href="../css/nav-submenus.css">
<style>
  /* ===== HERO con cotizador inline ===== */
  .hero-vehiculo {
    position: relative;
    color: var(--white); overflow: hidden;
    padding: 0;
    min-height: 500px;
    display: flex;
    flex-direction: column;
    justify-content: center;
  }
  .hero-vehiculo-img-wrap {
    position: absolute;
    inset: 0;
    z-index: 0;
  }
  .hero-vehiculo-img-wrap img {
    width: 100%; height: 100%;
    object-fit: cover;
    object-position: center;
    display: block;
  }
  .hero-vehiculo-img-wrap::after {
    content: '';
    position: absolute; inset: 0;
    /* background: linear-gradient(
      135deg,
      rgba(15,23,42,0.88) 0%,
      rgba(15,23,42,0.65) 55%,
      rgba(27,50,80,0.75) 100%
    ); */
  }
  .hero-vehiculo > .container {
    position: relative;
    z-index: 1;
    padding-top: 70px;
    padding-bottom: 80px;
  }
  .hero-vehiculo-grid {
    display: grid; grid-template-columns: 1fr 0.85fr; gap: 50px;
    align-items: center;
  }
  .hero-vehiculo-text .eyebrow {
    display: inline-block;
    font-size: 13px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase;
    color: var(--gray-900); margin-bottom: 16px;
    border-radius: 10px;
    background: #fff;
    padding: 5px 10px;
  }
  .hero-vehiculo-text h1 {
    font-family: var(--font-display);
    font-size: clamp(2.2rem, 5vw, 3.4rem);
    font-weight: 900; line-height: 1.05; letter-spacing: -1.5px;
    margin-bottom: 18px;
    text-shadow: 3px 3px 0px var(--gray-900);
  }
  .hero-vehiculo-text h1 em { font-style: italic; color: var(--accent); font-weight: 500; }
  .hero-vehiculo-text p.lead { font-size: 17px; color: rgba(255,255,255,0.85); margin-bottom: 22px; text-shadow: 3px 3px 4px var(--gray-900); }
  .hero-bullets { list-style: none; }
  .hero-bullets li {
    padding: 8px 0; font-size: 15px; color: rgba(255,255,255,0.92);
    display: flex; align-items: center; gap: 10px; text-shadow: 3px 3px 4px var(--gray-900);
  }
  .hero-bullets svg { color: var(--accent); flex-shrink: 0; filter: drop-shadow(0px 3px #fff);}

  /* Cotizador inline */
  .quote-card {
    background: var(--white); color: var(--primary);
    border-radius: var(--radius-xl);
    padding: 32px 28px;
    box-shadow: 0 30px 80px rgba(0,0,0,0.3);
    position: relative;
  }
  .quote-card::before {
    content: ''; position: absolute; top: -1px; left: 24px; right: 24px; height: 4px;
    background: var(--accent); border-radius: 0 0 4px 4px;
  }
  .quote-card-head { margin-bottom: 22px; }
  .quote-card h3 {
    font-family: var(--font-display); font-size: 22px; font-weight: 800;
    margin-bottom: 6px; letter-spacing: -0.3px;
  }
  .quote-card p.sub { font-size: 14px; color: var(--gray-600); }
  .quote-tabs { display: flex; gap: 8px; margin-bottom: 18px; flex-wrap: wrap; }
  .quote-tab {
    padding: 8px 16px; border-radius: 999px;
    background: var(--gray-50); color: var(--gray-600);
    font-weight: 700; font-size: 13px; cursor: pointer; border: 1.5px solid transparent;
    transition: all .2s;
    display: inline-flex; align-items: center; gap: 6px;
  }
  .quote-tab:hover { border-color: var(--accent); }
  .quote-tab.active { background: var(--primary); color: var(--accent); border-color: var(--primary); }
  .quote-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px; }
  .quote-form-row.full { grid-template-columns: 1fr; }
  .quote-form-group label {
    display: block; font-size: 12px; font-weight: 700;
    color: var(--primary); margin-bottom: 5px; text-transform: uppercase; letter-spacing: 0.5px;
  }
  .quote-form-group input, .quote-form-group select {
    width: 100%; padding: 12px 14px;
    border: 1.5px solid var(--gray-200); border-radius: 10px;
    font-family: inherit; font-size: 14px;
    color: var(--primary); background: var(--white);
    transition: border-color .2s;
  }
  .quote-form-group input:focus, .quote-form-group select:focus {
    outline: 0; border-color: var(--accent);
  }
  .quote-submit { width: 100%; margin-top: 8px; }
  .quote-trust {
    margin-top: 14px; padding-top: 14px;
    border-top: 1px solid var(--gray-200);
    display: flex; justify-content: space-between; gap: 10px; flex-wrap: wrap;
    font-size: 11px; color: var(--gray-400);
  }
  .quote-trust span { display: inline-flex; align-items: center; gap: 4px; }
  .quote-trust svg { color: var(--success); }

  /* ===== BLOQUE MOTOS DESTACADO ===== */
  .moto-feature {
    background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%);
    color: var(--primary);
    padding: 70px 0;
    position: relative;
    overflow: hidden;
  }
  .moto-feature::before {
    content: '';
    position: absolute; top: 0; right: 0;
    width: 50%; height: 100%;
    background-image: radial-gradient(circle at 70% 50%, rgba(11, 31, 58, 0.08) 1px, transparent 1px);
    background-size: 24px 24px;
    pointer-events: none;
    z-index: 0;
  }
  .moto-feature-inner {
    display: grid; grid-template-columns: 1fr 1fr; gap: 50px;
    align-items: center; position: relative;
  }
  .moto-eyebrow {
    display: inline-flex; align-items: center; gap: 8px;
    background: var(--primary); color: var(--accent);
    padding: 6px 14px; border-radius: 999px;
    font-size: 12px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase;
    margin-bottom: 18px;
  }
  .moto-feature h2 {
    font-family: var(--font-display);
    font-size: clamp(2rem, 4.5vw, 2.8rem);
    font-weight: 900; line-height: 1.05; letter-spacing: -1px;
    margin-bottom: 16px;
  }
  .moto-feature h2 em { font-style: italic; font-weight: 500; }
  .moto-feature p.lead { font-size: 17px; color: var(--primary-light); margin-bottom: 24px; line-height: 1.6; }
  .moto-features { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 28px; }
  .moto-feat {
    background: var(--primary); color: var(--white);
    padding: 16px 18px; border-radius: var(--radius-md);
    display: flex; align-items: flex-start; gap: 12px;
  }
  .moto-feat-icon {
    width: 36px; height: 36px; flex-shrink: 0;
    background: var(--accent); color: var(--primary);
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
  }
  .moto-feat strong { display: block; font-size: 14px; margin-bottom: 2px; }
  .moto-feat span { font-size: 12px; color: rgba(255,255,255,0.75); line-height: 1.4; }

  .moto-illustration {
    position: relative;
    width: 100%;
    max-width: 420px;
    height: 420px;
    margin: 0 auto;
  }
  .moto-circle {
    position: absolute; inset: 10%;
    background: var(--primary);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    overflow: hidden;
  }
  .moto-circle img {
    width: 85%;
    height: 85%;
    object-fit: contain;
    object-position: center;
    display: block;
    /* Invierte a blanco para que contraste con el fondo oscuro */
    /* filter: brightness(0) invert(1); */
  }
  .moto-badge {
    position: absolute;
    background: var(--white); color: var(--primary);
    padding: 14px 18px; border-radius: 14px;
    box-shadow: 0 12px 30px rgba(0,0,0,0.15);
    font-weight: 700; font-size: 13px;
    display: flex; align-items: center; gap: 10px;
  }
  .moto-badge.b1 { top: 8%; right: -8%; transform: rotate(-3deg); }
  .moto-badge.b2 { bottom: 12%; left: -8%; transform: rotate(2deg); }
  .moto-badge .dot { width: 8px; height: 8px; background: var(--success); border-radius: 50%; }

  /* ===== COMPARADOR DE PLANES (cards) ===== */
  .compare-section { padding: 80px 0; background: var(--white); }
  .vehicle-tabs {
    display: flex; flex-wrap: wrap; gap: 10px; justify-content: center;
    margin: 0 auto 40px; max-width: 900px;
  }
  .plan-tab {
    padding: 12px 22px; border-radius: 999px;
    background: var(--gray-50); color: var(--gray-600);
    font-weight: 700; font-size: 14px; cursor: pointer; border: 1.5px solid transparent;
    transition: all .2s;
    display: inline-flex; align-items: center; gap: 8px;
  }
  .plan-tab:hover { border-color: var(--accent); color: var(--primary); }
  .plan-tab.active { background: var(--primary); color: var(--accent); border-color: var(--primary); }

  .plan-panel { display: none; }
  .plan-panel.active { display: block; animation: fadeIn .35s; }
  @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

  .plan-cards {
    display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;
    margin-bottom: 30px;
  }
  .plan-card {
    background: var(--white);
    border: 2px solid var(--gray-200);
    border-radius: var(--radius-xl);
    padding: 32px 26px;
    position: relative;
    transition: transform .3s, box-shadow .3s, border-color .3s;
    display: flex; flex-direction: column;
  }
  .plan-card:hover { transform: translateY(-6px); box-shadow: var(--shadow-lg); }
  .plan-card.popular {
    border-color: var(--accent);
    box-shadow: 0 16px 40px rgba(243, 195, 50, 0.18);
    transform: scale(1.03);
  }
  .plan-card.popular:hover { transform: scale(1.03) translateY(-6px); }
  .plan-popular-tag {
    position: absolute; top: -12px; left: 50%; transform: translateX(-50%);
    background: var(--accent); color: var(--primary);
    padding: 5px 14px; border-radius: 999px;
    font-size: 11px; font-weight: 800; letter-spacing: 1px; text-transform: uppercase;
    white-space: nowrap;
  }
  .plan-name {
    font-family: var(--font-display);
    font-size: 22px; font-weight: 800; color: var(--primary);
    margin-bottom: 4px;
  }
  .plan-tagline { font-size: 13px; color: var(--gray-600); margin-bottom: 18px; }
  .plan-price {
    padding: 16px 0;
    border-top: 1px solid var(--gray-200);
    border-bottom: 1px solid var(--gray-200);
    margin-bottom: 18px;
  }
  .plan-price-label { font-size: 11px; font-weight: 700; color: var(--gray-400); text-transform: uppercase; letter-spacing: 1px; }
  .plan-price-amount {
    font-family: var(--font-display);
    font-size: 32px; font-weight: 900; color: var(--primary);
    letter-spacing: -1px; line-height: 1; margin: 4px 0 2px;
  }
  .plan-price-amount em { font-style: italic; color: var(--accent-dark); font-weight: 500; font-size: 22px; }
  .plan-price-note { font-size: 12px; color: var(--gray-400); }
  .plan-features { list-style: none; flex: 1; margin-bottom: 22px; }
  .plan-features li {
    padding: 7px 0; font-size: 14px;
    display: flex; align-items: flex-start; gap: 10px;
  }
  .plan-features li.included { color: var(--primary); }
  .plan-features li.included svg { color: var(--success); }
  .plan-features li.excluded { color: var(--gray-400); text-decoration: line-through; }
  .plan-features li.excluded svg { color: var(--gray-400); opacity: 0.5; }
  .plan-cta {
    display: block; text-align: center; padding: 13px;
    border-radius: 999px; font-weight: 700; font-size: 14px;
    background: var(--gray-50); color: var(--primary);
    transition: all .2s;
  }
  .plan-cta:hover { background: var(--primary); color: var(--white); }
  .plan-card.popular .plan-cta { background: var(--accent); color: var(--primary); }
  .plan-card.popular .plan-cta:hover { background: var(--accent-dark); }
  .plan-call-btn {
    display: flex; align-items: center; justify-content: center; gap: 6px;
    margin-top: 8px; padding: 10px;
    border-radius: 999px; font-weight: 700; font-size: 13px;
    border: 1.5px solid var(--gray-200); color: var(--gray-600);
    background: transparent; transition: all .2s;
  }
  .plan-call-btn:hover { border-color: var(--primary); color: var(--primary); background: var(--gray-50); }
  .plan-card.popular .plan-call-btn { border-color: rgba(243,195,50,0.4); color: var(--primary); }
  .plan-card.popular .plan-call-btn:hover { background: rgba(243,195,50,0.1); }

  .compare-note {
    text-align: center; font-size: 13px; color: var(--gray-400);
    margin-top: 16px;
  }

  /* ===== TESTIMONIOS ESPECÍFICOS ===== */
  .testi-vehiculo { padding: 80px 0; background: var(--gray-50); }
  .testi-vehi-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 24px; }
  .testi-vehi-card {
    background: var(--white); border-radius: var(--radius-xl);
    padding: 30px 28px;
    border: 1px solid var(--gray-200);
    transition: transform .3s, box-shadow .3s;
    position: relative;
  }
  .testi-vehi-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-md); }
  .testi-vehi-quote {
    font-family: var(--font-display);
    font-size: 80px; font-weight: 900; font-style: italic;
    color: var(rgba(243, 195, 50, 0.10)); line-height: 0.5;
    height: 30px; margin-bottom: 8px;
  }
  .testi-vehi-stars { color: var(--accent); margin-bottom: 12px; letter-spacing: 2px; }
  .testi-vehi-card p.story { font-size: 16px; color: var(--primary); margin-bottom: 18px; line-height: 1.55; }
  .testi-vehi-card p.story strong { color: var(--primary); }
  .testi-vehi-meta { display: flex; align-items: center; gap: 12px; padding-top: 16px; border-top: 1px solid var(--gray-200); }
  .testi-vehi-avatar {
    width: 44px; height: 44px; border-radius: 50%;
    background: var(--accent); color: var(--primary);
    display: flex; align-items: center; justify-content: center;
    font-weight: 800; font-size: 15px;
  }
  .testi-vehi-meta strong { display: block; font-size: 14px; color: var(--primary); }
  .testi-vehi-meta span { font-size: 12px; color: var(--gray-400); }
  .testi-vehi-meta .vehicle-tag {
    margin-left: auto; padding: 4px 10px;
    background: var(--gray-50); color: var(--primary);
    border-radius: 999px; font-size: 11px; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.5px;
  }

  /* ===== PROCESO ===== */
  .process-section { padding: 80px 0; background: var(--white); }
  .process-steps { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; position: relative; }
  .process-steps::before {
    content: ''; position: absolute;
    top: 36px; left: 16%; right: 16%; height: 2px;
    background: repeating-linear-gradient(90deg, var(--gray-200) 0 8px, transparent 8px 16px);
    z-index: 0;
  }
  .process-step { text-align: center; position: relative; z-index: 1; }
  .process-num {
    width: 72px; height: 72px; margin: 0 auto 18px;
    background: var(--white); border: 2px solid var(--accent);
    color: var(--accent-dark); border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-family: var(--font-display); font-weight: 900; font-size: 32px;
    transition: all .3s;
  }
  .process-step:hover .process-num { background: var(--accent); color: var(--primary); transform: scale(1.08); }
  .process-step h4 { font-family: var(--font-display); font-size: 19px; color: var(--primary); margin-bottom: 8px; }
  .process-step p { font-size: 14px; color: var(--gray-600); }

  @media (max-width: 900px) {
    .hero-vehiculo-grid, .moto-feature-inner { grid-template-columns: 1fr; gap: 36px; }
    .hero-vehiculo > .container { padding-top: 50px; padding-bottom: 60px; }
    .moto-features { grid-template-columns: 1fr; }
    .moto-illustration { max-width: 280px; }
    .plan-cards { grid-template-columns: 1fr; }
    .plan-card.popular { transform: none; }
    .plan-card.popular:hover { transform: translateY(-6px); }
    .testi-vehi-grid { grid-template-columns: 1fr; }
    .process-steps { grid-template-columns: 1fr; gap: 30px; }
    .process-steps::before { display: none; }
    .quote-form-row { grid-template-columns: 1fr; }
  }
</style>
</head>
<body>

<!-- NAV -->
<nav class="nav">
  <div class="nav-inner">
    <a href="/" class="nav-logo">Seguros<span>Lux</span></a>
    <ul class="nav-menu">
      <li><a href="#inicio">Inicio</a></li>
      <li><a href="#planes">Planes</a></li>
      <li class="has-submenu">
        <a href="#">Seguros</a>
        <ul class="submenu">
          <li><a href="/autos">Seguro Vehicular</a></li>
          <li><a href="/vida">Seguro de Vida</a></li>
          <li><a href="/gmm">Gastos Médicos Mayores</a></li>
        </ul>
      </li>
      <li class="has-submenu">
        <a href="#">Recursos</a>
        <ul class="submenu">
          <li><a href="#casos_reales">Casos Reales</a></li>
          <li><a href="#proceso_simple">Proceso Simple</a></li>
          <li><a href="#faq">Preguntas Frecuentes</a></li>
        </ul>
      </li>
      <li><a href="#contacto">Contacto</a></li>
    </ul>
    <div class="nav-right">
      <a href="tel:+525541232311" class="nav-phone">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
        55 4123 2311
      </a>
      <a href="#cotizar" class="nav-cta">
        <span class="hide-sm">Cotizar</span>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
      </a>
      <button class="nav-toggle" aria-label="Menú">☰</button>
    </div>
  </div>
</nav>

<!-- Breadcrumb -->
<div class="breadcrumb" id="inicio">
  <div class="container">
    <a href="/">Inicio</a>
    <span>›</span>
    <a href="/#seguros">Seguros</a>
    <span>›</span>
    <span class="current">Seguro Vehicular</span>
  </div>
</div>

<!-- HERO con cotizador inline -->
<section class="hero-vehiculo" id="cotizar">
  <div class="hero-vehiculo-img-wrap">
    <img
      src="/images/banner_vehiculos.webp"
      alt="Seguro vehicular para autos y motos — Seguros Lux"
      width="1440" height="600"
      fetchpriority="high"
      decoding="async"
    >
  </div>
  <div class="container">
    <div class="hero-vehiculo-grid">
      <div class="hero-vehiculo-text">
        <div class="eyebrow">Seguro Vehicular</div>
        <h1>Protege <em>tu vehículo</em> en cada camino</h1>
        <p class="lead">Cobertura amplia, asistencia vial 24/7 y atención personal. Autos, motos, camiones y flotillas.</p>
        <ul class="hero-bullets">
          <li><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Grúa 24/7 en todo momento</li>
          <li><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Paso de corriente incluido</li>
          <li><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Cobertura nacional sin restricciones</li>
          <li><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Cambio de llanta en carretera</li>
          <li><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Asesor personal de siniestros</li>
        </ul>
      </div>

      <!-- COTIZADOR INLINE -->
      <div class="quote-card">
        <div class="quote-card-head">
          <h3>Cotiza ahora</h3>
          <p class="sub">Recibe tu cotización.</p>
        </div>
        <div class="quote-tabs">
          <button class="quote-tab active" data-quote="auto">🚗 Auto</button>
          <button class="quote-tab" data-quote="moto">🏍️ Moto</button>
          <button class="quote-tab" data-quote="camion">🚚 Camión</button>
          <button class="quote-tab" data-quote="flotilla">🚐 Flotilla</button>
        </div>
        <form id="formCotizadorAutos">
          <input type="hidden" name="tipo_vehiculo" value="auto">
          <div class="quote-form-row">
            <div class="quote-form-group">
              <label>Nombre Completo</label>
              <input type="text" name="nombre" placeholder="Ej. Juan" required>
            </div>
            <div class="quote-form-group">
              <label>Marca</label>
              <input type="text" name="marca" placeholder="Ej. Honda" required>
            </div>
            <div class="quote-form-group">
              <label>Modelo</label>
              <input type="text" name="modelo" placeholder="Ej. CB500" required>
            </div>
          </div>
          <div class="quote-form-row">
            <div class="quote-form-group">
              <label>Año</label>
              <select name="anio" required>
                <option value="">Selecciona</option>
                <option>2026</option><option>2025</option><option>2024</option>
                <option>2023</option><option>2022</option><option>2021</option>
                <option>2020 o anterior</option>
              </select>
            </div>
            <div class="quote-form-group">
              <label>Telefono</label>
              <input type="tel" name="telefono" placeholder="55 1234 5678" required>
            </div>
          </div>
          <div class="quote-form-row full">
            <div class="quote-form-group">
              <label>Correo electrónico</label>
              <input type="email" name="correo" placeholder="tu@correo.com" required>
            </div>
          </div>
          <button type="submit" class="btn btn-primary quote-submit">
            Cotiza ahora
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
          </button>
        </form>
        <div class="quote-trust">
          <span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Sin compromiso</span>
          <span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Datos protegidos</span>
          <span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Seguimiento personalizado</span>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- BLOQUE MOTOS DESTACADO -->
<section class="moto-feature">
  <div class="container">
    <div class="moto-feature-inner">
      <div>
        <div class="moto-eyebrow">★ Nuestra cobertura</div>
        <h2>¿Tienes un vehículo? Somos <em>tu mejor opción</em>.</h2>
        <p class="lead">Nuestras coberturas están diseñadas para proteger tu vehículo y a quienes viajan en él, con la seguridad que realmente importa en cada trayecto.</p>
        <div class="moto-features">
          <div class="moto-feat">
            <div class="moto-feat-icon">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
            </div>
            <div><strong>Cobertura integral del vehículo</strong><span>Protección para tu unidad ante los riesgos más comunes y los imprevistos del camino.</span></div>
          </div>
          <div class="moto-feat">
            <div class="moto-feat-icon">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M21 12c0 4.97-4.03 9-9 9s-9-4.03-9-9 4.03-9 9-9 9 4.03 9 9z"/><line x1="9" y1="9" x2="15" y2="15"/><line x1="15" y1="9" x2="9" y2="15"/></svg>
            </div>
            <div><strong>Gastos médicos a ocupantes</strong><span>Atención inmediata si tienes un accidente.</span></div>
          </div>
          <div class="moto-feat">
            <div class="moto-feat-icon">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 2L2 7v10l10 5 10-5V7L12 2z"/></svg>
            </div>
            <div><strong>Accesorios y equipamiento protegidos</strong><span>Rines, estéreos, sistemas adicionales y más.</span></div>
          </div>
          <div class="moto-feat">
            <div class="moto-feat-icon">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
            </div>
            <div><strong>Asistencia vial 24/7</strong><span>Grúa, paso de corriente, cambio de llanta y apoyo en carretera cuando lo necesites.</span></div>
          </div>
        </div>
        <a href="#planes" class="btn btn-outline" style="background: var(--primary); color: var(--accent); border-color: var(--primary);">Ver planes para vehículos</a>
      </div>
      <div class="moto-illustration" aria-hidden="true">
        <div class="moto-circle">
          <img
            src="/images/moto.webp"
            alt="Motocicleta"
            width="320" height="320"
            loading="lazy"
            decoding="async"
          >
        </div>
        <div class="moto-badge b1">
          <span class="dot"></span>
          Asistencia 24/7
        </div>
        <div class="moto-badge b2">
          ⭐ Plan Premium Vehicular
        </div>
      </div>
    </div>
  </div>
</section>

<!-- COMPARADOR DE PLANES -->
<section class="compare-section" id="planes">
  <div class="container">
    <div class="section-head">
      <div class="section-eyebrow">Compara y elige</div>
      <h2>Planes que <em>se adaptan a ti</em></h2>
      <p>Tres niveles de protección para cada tipo de vehículo.</p>
    </div>

    <!-- <div class="vehicle-tabs">
      <button class="plan-tab active" data-target="autos">🚗 Autos</button>
      <button class="plan-tab" data-target="motos">🏍️ Motos</button>
      <button class="plan-tab" data-target="camiones">🚚 Camiones</button>
      <button class="plan-tab" data-target="flotillas">🚐 Flotillas</button>
    </div> -->

    <!-- Panel Vehiculos -->
    <div class="plan-panel active" data-panel="autos">
      <div class="plan-cards">
        <div class="plan-card">
          <div class="plan-name">Básico</div>
          <p class="plan-tagline">Lo esencial por la ley.</p>
          <div class="plan-price">
            <div class="plan-price-label">Desde</div>
            <div class="plan-price-amount"><em>$</em>3,200<em style="font-size:14px;">/año</em></div>
            <div class="plan-price-note">*precio referencial sedán 2020</div>
          </div>
          <ul class="plan-features">
            <li class="included"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Daños a terceros</li>
            <li class="included"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Asistencia vial 24/7</li>
            <li class="excluded"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg> Robo total</li>
            <li class="excluded"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg> Daños materiales</li>
            <li class="excluded"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg> Auto sustituto</li>
          </ul>
          <a href="#cotizar" class="plan-cta">Cotizar Básico</a>
          <a href="tel:+525541232311" class="plan-call-btn">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
            Llamar ahora
          </a>
        </div>
        <div class="plan-card popular">
          <span class="plan-popular-tag">★ Lo más elegido</span>
          <div class="plan-name">Amplio</div>
          <p class="plan-tagline">Equilibrio ideal entre cobertura y precio.</p>
          <div class="plan-price">
            <div class="plan-price-label">Desde</div>
            <div class="plan-price-amount"><em>$</em>6,800<em style="font-size:14px;">/año</em></div>
            <div class="plan-price-note">*precio referencial sedán 2020</div>
          </div>
          <ul class="plan-features">
            <li class="included"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Robo total</li>
            <li class="included"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Daños a terceros y materiales</li>
            <li class="included"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Asistencia vial 24/7</li>
            <li class="included"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Gastos médicos ocupantes</li>
            <li class="excluded"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg> Auto sustituto y cero deducible</li>
          </ul>
          <a href="#cotizar" class="plan-cta">Cotizar Amplio</a>
          <a href="tel:+525541232311" class="plan-call-btn">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
            Llamar ahora
          </a>
        </div>
        <div class="plan-card">
          <div class="plan-name">Premium</div>
          <p class="plan-tagline">Tranquilidad total, sin sorpresas.</p>
          <div class="plan-price">
            <div class="plan-price-label">Desde</div>
            <div class="plan-price-amount"><em>$</em>11,500<em style="font-size:14px;">/año</em></div>
            <div class="plan-price-note">*precio referencial sedán 2020</div>
          </div>
          <ul class="plan-features">
            <li class="included"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Todo lo del plan Amplio</li>
            <li class="included"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Auto sustituto durante reparación</li>
            <li class="included"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Cero deducible en cristales</li>
            <li class="included"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Cobertura en EUA y Canadá</li>
          </ul>
          <a href="#cotizar" class="plan-cta">Cotizar Premium</a>
          <a href="tel:+525541232311" class="plan-call-btn">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
            Llamar ahora
          </a>
        </div>
      </div>
      <p class="compare-note">*Los precios mostrados son referenciales para sedán 2020 con cobertura mínima legal. Tu cotización personalizada depende de marca, modelo, año, uso y código postal.</p>
    </div>

    <!-- Panel Motos -->
    <div class="plan-panel" data-panel="motos">
      <div class="plan-cards">
        <div class="plan-card">
          <div class="plan-name">Básico</div>
          <p class="plan-tagline">RC obligatoria + asistencia vial.</p>
          <div class="plan-price">
            <div class="plan-price-label">Desde</div>
            <div class="plan-price-amount"><em>$</em>1,800<em style="font-size:14px;">/año</em></div>
            <div class="plan-price-note">*moto 250cc, modelo 2022</div>
          </div>
          <ul class="plan-features">
            <li class="included"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Responsabilidad civil (RC)</li>
            <li class="included"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Asistencia vial 24/7</li>
            <li class="excluded"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg> Robo total</li>
            <li class="excluded"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg> Casco y equipo</li>
          </ul>
          <a href="#cotizar" class="plan-cta">Cotizar Básico</a>
        </div>
        <div class="plan-card popular">
          <span class="plan-popular-tag">★ Recomendado</span>
          <div class="plan-name">Estándar</div>
          <p class="plan-tagline">Protección balanceada para motociclistas.</p>
          <div class="plan-price">
            <div class="plan-price-label">Desde</div>
            <div class="plan-price-amount"><em>$</em>3,900<em style="font-size:14px;">/año</em></div>
            <div class="plan-price-note">*moto 250cc, modelo 2022</div>
          </div>
          <ul class="plan-features">
            <li class="included"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Robo total + RC + Daños propios</li>
            <li class="included"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Cobertura de casco y equipo</li>
            <li class="included"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Gastos médicos al piloto</li>
            <li class="included"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Asistencia vial 24/7</li>
          </ul>
          <a href="#cotizar" class="plan-cta">Cotizar Estándar</a>
        </div>
        <div class="plan-card">
          <div class="plan-name">Premium</div>
          <p class="plan-tagline">Para motociclistas que viven sobre dos ruedas.</p>
          <div class="plan-price">
            <div class="plan-price-label">Desde</div>
            <div class="plan-price-amount"><em>$</em>6,400<em style="font-size:14px;">/año</em></div>
            <div class="plan-price-note">*moto 250cc, modelo 2022</div>
          </div>
          <ul class="plan-features">
            <li class="included"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Todo lo del plan Estándar</li>
            <li class="included"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Cobertura de accesorios</li>
            <li class="included"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> RC ampliada</li>
            <li class="included"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Sin deducible en cristales y luces</li>
          </ul>
          <a href="#cotizar" class="plan-cta">Cotizar Premium</a>
        </div>
      </div>
      <p class="compare-note">*Precios referenciales para moto de 250cc, modelo 2022. Tu cotización depende del cilindraje, antigüedad y uso.</p>
    </div>

    <!-- Panel Camiones -->
    <div class="plan-panel" data-panel="camiones">
      <div class="plan-cards">
        <div class="plan-card">
          <div class="plan-name">Básico</div>
          <p class="plan-tagline">Para camiones y vans con uso ocasional.</p>
          <div class="plan-price">
            <div class="plan-price-label">Desde</div>
            <div class="plan-price-amount"><em>$</em>8,500<em style="font-size:14px;">/año</em></div>
            <div class="plan-price-note">*van de carga ligera</div>
          </div>
          <ul class="plan-features">
            <li class="included"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> RC daños a terceros</li>
            <li class="included"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Asistencia vial pesada 24/7</li>
            <li class="excluded"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg> Robo total y cobertura de mercancía</li>
          </ul>
          <a href="#cotizar" class="plan-cta">Cotizar Básico</a>
        </div>
        <div class="plan-card popular">
          <span class="plan-popular-tag">★ Recomendado</span>
          <div class="plan-name">Comercial</div>
          <p class="plan-tagline">Para reparto diario y carga regular.</p>
          <div class="plan-price">
            <div class="plan-price-label">Desde</div>
            <div class="plan-price-amount"><em>$</em>15,200<em style="font-size:14px;">/año</em></div>
            <div class="plan-price-note">*van de carga ligera</div>
          </div>
          <ul class="plan-features">
            <li class="included"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Robo total + RC + Daños propios</li>
            <li class="included"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Cobertura de mercancía</li>
            <li class="included"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Gastos médicos al operador</li>
            <li class="included"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Asistencia vial pesada 24/7</li>
          </ul>
          <a href="#cotizar" class="plan-cta">Cotizar Comercial</a>
        </div>
        <div class="plan-card">
          <div class="plan-name">Premium</div>
          <p class="plan-tagline">Cobertura total para carga de alto valor.</p>
          <div class="plan-price">
            <div class="plan-price-label">Desde</div>
            <div class="plan-price-amount"><em>$</em>24,800<em style="font-size:14px;">/año</em></div>
            <div class="plan-price-note">*van de carga ligera</div>
          </div>
          <ul class="plan-features">
            <li class="included"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Todo lo del plan Comercial</li>
            <li class="included"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> RC ampliada por carga</li>
            <li class="included"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Robo parcial de mercancía</li>
          </ul>
          <a href="#cotizar" class="plan-cta">Cotizar Premium</a>
        </div>
      </div>
      <p class="compare-note">*Precios referenciales para van de carga ligera. Cotización personalizada según peso, ruta y carga.</p>
    </div>

    <!-- Panel Flotillas -->
    <div class="plan-panel" data-panel="flotillas">
      <div style="max-width:900px;margin:0 auto;">

        <!-- Intro flotillas -->
        <div style="background:var(--primary);color:var(--white);border-radius:var(--radius-xl);padding:32px 36px;margin-bottom:30px;display:flex;align-items:center;gap:24px;flex-wrap:wrap;">
          <div style="flex:1;min-width:220px;">
            <div style="font-size:12px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:var(--accent);margin-bottom:8px;">Atención Personalizada</div>
            <h3 style="font-family:var(--font-display);font-size:22px;font-weight:800;margin-bottom:8px;">Seguros para flotillas y empresas</h3>
            <p style="font-size:15px;color:rgba(255,255,255,0.82);line-height:1.5;">Cada empresa es diferente. Tu asesor José Luis revisa tu caso directamente para ofrecerte la propuesta más competitiva.</p>
          </div>
          <div style="display:flex;flex-direction:column;gap:10px;">
            <a href="tel:+525541232311" class="btn btn-primary" style="background:var(--accent);color:var(--primary);display:inline-flex;align-items:center;gap:8px;">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
              Solicita tu atención personalizada
            </a>
            <a href="#formFlotilla" class="btn btn-ghost" style="border-color:rgba(255,255,255,0.3);text-align:center;">Llenar formulario</a>
          </div>
        </div>

        <!-- Botón PyMEs -->
        <div style="text-align:center;margin-bottom:30px;">
          <a href="tel:+525541232311" style="display:inline-flex;align-items:center;gap:10px;background:var(--gray-50);border:1.5px solid var(--accent);color:var(--primary);padding:14px 28px;border-radius:999px;font-weight:700;font-size:15px;transition:all .2s;" onmouseover="this.style.background='var(--accent)'" onmouseout="this.style.background='var(--gray-50)'">
            🏢 Atención especializada para PyMEs y empresas
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
          </a>
        </div>

        <!-- Formulario flotilla -->
        <div id="formFlotilla" style="background:var(--white);border:1px solid var(--gray-200);border-radius:var(--radius-xl);padding:36px 32px;box-shadow:var(--shadow-md);">
          <div style="margin-bottom:22px;">
            <div style="font-size:12px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:var(--accent-dark);margin-bottom:8px;">Datos de la empresa</div>
            <h3 style="font-family:var(--font-display);font-size:20px;font-weight:800;color:var(--primary);">Cuéntanos sobre tu flotilla</h3>
            <p style="font-size:14px;color:var(--gray-600);">José Luis revisará tu solicitud personalmente y te contactará con la mejor propuesta.</p>
          </div>
          <form id="formCotizadorFlotilla">
            <input type="hidden" name="tipo_vehiculo" value="flotilla">
            <div class="quote-form-row">
              <div class="quote-form-group">
                <label>Nombre de la empresa *</label>
                <input type="text" name="empresa" placeholder="Ej. Transportes García S.A." required>
              </div>
              <div class="quote-form-group">
                <label>Giro / Sector</label>
                <input type="text" name="giro" placeholder="Ej. Logística, Reparto, Construcción">
              </div>
            </div>
            <div class="quote-form-row">
              <div class="quote-form-group">
                <label>Número de vehículos *</label>
                <input type="number" name="num_unidades" min="3" placeholder="Mín. 3 unidades" required>
              </div>
              <div class="quote-form-group">
                <label>Tipo principal de vehículos</label>
                <select name="tipo_vehiculos_flotilla">
                  <option value="">Selecciona</option>
                  <option>Autos / SUVs</option>
                  <option>Camionetas pickup</option>
                  <option>Vans de carga</option>
                  <option>Camiones</option>
                  <option>Mixto</option>
                </select>
              </div>
            </div>
            <div class="quote-form-row">
              <div class="quote-form-group">
                <label>Nombre del contacto *</label>
                <input type="text" name="nombre" placeholder="Tu nombre completo" required>
              </div>
              <div class="quote-form-group">
                <label>Teléfono / WhatsApp *</label>
                <input type="tel" name="telefono" placeholder="55 1234 5678" required>
              </div>
            </div>
            <div class="quote-form-row full">
              <div class="quote-form-group">
                <label>Correo electrónico *</label>
                <input type="email" name="correo" placeholder="contacto@empresa.com" required>
              </div>
            </div>
            <div class="quote-form-row full">
              <div class="quote-form-group">
                <label>¿Tienen seguro actual? Comentarios adicionales</label>
                <textarea name="comentarios" style="width:100%;padding:12px 14px;border:1.5px solid var(--gray-200);border-radius:10px;font-family:inherit;font-size:14px;resize:vertical;min-height:80px;" placeholder="Cuéntanos más sobre sus necesidades o el seguro que tienen actualmente..."></textarea>
              </div>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;margin-top:8px;">
              Enviar solicitud de flotilla
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
            </button>
            <p style="text-align:center;font-size:12px;color:var(--gray-400);margin-top:12px;">Tu solicitud va directo a José Luis para seguimiento personalizado.</p>
          </form>
        </div>

      </div>
      <p class="compare-note" style="margin-top:24px;">*Descuentos sobre tarifa individual. Aplican condiciones según composición y antigüedad de flotilla. Mínimo 3 vehículos.</p>
    </div>
  </div>
</section>

<!-- TESTIMONIOS específicos vehiculares -->
<section class="testi-vehiculo" id="casos_reales">
  <div class="container">
    <div class="section-head">
      <div class="section-eyebrow">Casos reales</div>
      <h2>Lo que pasa cuando <em>de verdad lo necesitas</em></h2>
      <p>Historias reales de clientes que confiaron en nosotros en el peor momento.</p>
    </div>
    <div class="testi-vehi-grid">
      <div class="testi-vehi-card">
        <div class="testi-vehi-quote">"</div>
        <div class="testi-vehi-stars">★★★★★</div>
        <p class="story">Choqué mi moto un domingo a las 11 de la noche en Periférico. Llamé a Lux pensando que me iban a contestar hasta el lunes. <strong>En 25 minutos ya tenía la grúa en camino y un asesor en línea acompañándome.</strong> Nunca me sentí solo.</p>
        <div class="testi-vehi-meta">
          <div class="testi-vehi-avatar">JR</div>
          <div>
            <strong>Jorge Ramírez</strong>
            <span>Cliente desde 2022</span>
          </div>
          <span class="vehicle-tag">🏍️ Moto</span>
        </div>
      </div>
      <div class="testi-vehi-card">
        <div class="testi-vehi-quote">"</div>
        <div class="testi-vehi-stars">★★★★★</div>
        <p class="story">Me robaron la camioneta en plena Ciudad de México. <strong>Lux me reembolsó el valor completo en 18 días.</strong> Conocía gente con la misma situación que tardó 6 meses con otra aseguradora. Aquí mi asesor estuvo encima del trámite todo el tiempo.</p>
        <div class="testi-vehi-meta">
          <div class="testi-vehi-avatar">SM</div>
          <div>
            <strong>Sofía Méndez</strong>
            <span>Cliente desde 2021</span>
          </div>
          <span class="vehicle-tag">🚗 Auto</span>
        </div>
      </div>
      <div class="testi-vehi-card">
        <div class="testi-vehi-quote">"</div>
        <div class="testi-vehi-stars">★★★★★</div>
        <p class="story">Tengo flotilla de 8 vans para mi negocio de reparto. Cambié a Lux y <strong>ahorré $42,000 al año en primas, con mejor cobertura.</strong> Mi gestor revisa todo conmigo cada renovación. Es otra liga.</p>
        <div class="testi-vehi-meta">
          <div class="testi-vehi-avatar">DL</div>
          <div>
            <strong>Daniel López</strong>
            <span>Cliente desde 2019</span>
          </div>
          <span class="vehicle-tag">🚐 Flotilla</span>
        </div>
      </div>
      <div class="testi-vehi-card">
        <div class="testi-vehi-quote">"</div>
        <div class="testi-vehi-stars">★★★★★</div>
        <p class="story">Soy ciclista y conductor de Didi. Pensé que ninguna aseguradora iba a entender mi caso. <strong>Lux me armó una cobertura específica para uso de plataforma</strong> y el deducible me salió más barato que el seguro estándar que tenía antes.</p>
        <div class="testi-vehi-meta">
          <div class="testi-vehi-avatar">PG</div>
          <div>
            <strong>Patricia Guzmán</strong>
            <span>Cliente desde 2024</span>
          </div>
          <span class="vehicle-tag">🚗 Auto Didi</span>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- PROCESO -->
<section class="process-section" id="proceso_simple">
  <div class="container">
    <div class="section-head">
      <div class="section-eyebrow">Proceso simple</div>
      <h2>Tu póliza en <em>3 pasos</em></h2>
      <p>Cotiza, contrata y protege tu vehículo el mismo día.</p>
    </div>
    <div class="process-steps">
      <div class="process-step">
        <div class="process-num">1</div>
        <h4>Cotiza ahora</h4>
        <p>Llena el formulario con marca, modelo y año.</p>
      </div>
      <div class="process-step">
        <div class="process-num">2</div>
        <h4>Te contactamos</h4>
        <p>Un asesor te envía opciones por WhatsApp o correo.</p>
      </div>
      <div class="process-step">
        <div class="process-num">3</div>
        <h4>Recibe tu póliza</h4>
        <p>Póliza digital activa el mismo día. Sin papelería.</p>
      </div>
    </div>
  </div>
</section>

<!-- FAQ -->
<section class="faq-section" id="faq">
  <div class="container">
    <div class="section-head">
      <div class="section-eyebrow">Preguntas frecuentes</div>
      <h2>Dudas sobre <em>seguro vehicular</em></h2>
    </div>
    <div class="faq-wrap">
      <details class="faq-item">
        <summary>¿Qué pasa si tengo un accidente?</summary>
        <div class="faq-answer">Llama a nuestra línea 24/7 o escribe por WhatsApp. Un ajustador acude al lugar y te acompañamos durante todo el proceso de reclamación.</div>
      </details>
      <details class="faq-item">
        <summary>¿Aseguran todo tipo de vehículos?</summary>
        <div class="faq-answer">Sí. Aseguramos desde vehículos de uso cotidiano hasta modelos de mayor valor o características especiales. Solicita una cotización con los datos exactos de tu vehículo.</div>
      </details>
      <details class="faq-item">
        <summary>¿Cubre Uber, Didi y plataformas?</summary>
        <div class="faq-answer">Sí. Tenemos coberturas específicas para conductores de plataformas con deducibles ajustados al uso comercial. Coméntalo a tu asesor al cotizar.</div>
      </details>
      <details class="faq-item">
        <summary>¿Aseguran autos usados?</summary>
        <div class="faq-answer">Aseguramos vehículos hasta de 20 años de antigüedad. Las coberturas pueden variar para autos antiguos según su valor comercial.</div>
      </details>
      <details class="faq-item">
        <summary>¿Cuándo se activa mi póliza?</summary>
        <div class="faq-answer">Se activa inmediatamente después del pago. Recibes tu póliza digital por correo y WhatsApp en cuestión de minutos.</div>
      </details>
    </div>
  </div>
</section>

<!-- CTA FINAL -->
<section class="cta-final" id="contacto">
  <div class="container">
    <h2>Protege <em>tu vehículo</em> hoy</h2>
    <p>Cotiza ahora. Recibe tu póliza el mismo día.</p>
    <div class="cta-final-actions">
      <a href="#cotizar" class="btn btn-primary">Cotizar ahora</a>
      <a href="tel:+525541232311" class="btn btn-ghost">Llamar 55 4123 2311</a>
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
      </div>
      <div>
        <h5>Seguros</h5>
        <ul>
          <li><a href="/autos">Seguro Vehicular</a></li>
          <li><a href="/vida">Seguro de Vida</a></li>
          <li><a href="/gmm">Gastos Médicos</a></li>
        </ul>
      </div>
      <div>
        <h5>Clientes</h5>
        <ul>
          <li><a href="/#contacto">Atención a clientes</a></li>
          <li><a href="/#contacto">Pago de pólizas</a></li>
          <li><a href="/#faq">Preguntas frecuentes</a></li>
        </ul>
      </div>
      <div>
        <h5>Legal</h5>
        <ul>
          <li><a href="/aviso-privacidad">Aviso de privacidad</a></li>
          <li><a href="/terminos">Términos y condiciones</a></li>
          <li><a href="/condiciones">Condiciones generales</a></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <div>© 2026 Seguros Lux. Todos los derechos reservados.</div>
      <div>Powered by <strong style="color: #fff">GrupoIdeas</strong></div>
    </div>
  </div>
</footer>

<a href="https://wa.me/525541232311" class="whatsapp-float" aria-label="WhatsApp">
  <svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347M12 2C6.48 2 2 6.48 2 12c0 1.77.46 3.45 1.27 4.9L2 22l5.25-1.38C8.66 21.5 10.27 22 12 22c5.52 0 10-4.48 10-10S17.52 2 12 2z"/></svg>
</a>

<script src="../js/landing.js" defer></script>
<script src="../js/autos.js" defer></script>
</body>
</html>