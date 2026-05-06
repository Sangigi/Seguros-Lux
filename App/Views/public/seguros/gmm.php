<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Gastos Médicos Mayores | Seguros Lux</title>
<meta name="description" content="Gastos Medicos Mayores con +460 hospitales, atención 24/7 y un asesor que pelea por ti. Encuentra el plan ideal en ahora." />
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Google+Sans:ital,opsz,wght@0,17..18,400..700;1,17..18,400..700&family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../css/landing.css">
<link rel="icon" type="image/png" sizes="32x32" href="/images/logo.png">
<link rel="stylesheet" href="../css/nav-submenus.css">
<style>
  /* Override del acento amarillo por el azul GMM en esta página */
  .product-gmm {
    --accent: var(--gmm-primary);
    --accent-light: var(--gmm-light);
    --accent-dark: var(--gmm-dark);
  }
  .product-gmm .nav-cta { background: var(--gmm-primary); color: var(--white); }
  .product-gmm .nav-cta:hover { background: var(--gmm-dark); color: var(--white); }
  .product-gmm .btn-primary {
    background: var(--gmm-primary); color: var(--white);
    box-shadow: 0 10px 24px rgba(2, 132, 199, 0.3);
  }
  .product-gmm .btn-primary:hover {
    background: var(--gmm-dark);
    box-shadow: 0 14px 30px rgba(2, 132, 199, 0.4);
  }
  /* ========== HERO CON IMAGEN ========== */
  .hero-gmm {
    position: relative;
    color: var(--white);
    overflow: hidden;
    min-height: 480px;
    display: flex;
    flex-direction: column;
    justify-content: center;
  }
  .hero-gmm-img-wrap {
    position: absolute;
    inset: 0;
    z-index: 0;
  }
  .hero-gmm-img-wrap img {
    width: 100%; height: 100%;
    object-fit: cover;
    object-position: center;
    display: block;
  }
  .hero-gmm-img-wrap::after {
    content: '';
    position: absolute; inset: 0;
    background: linear-gradient(
      135deg,
      rgba(2,56,100,0.88) 0%,
      rgba(2,132,199,0.60) 60%,
      rgba(15,23,42,0.80) 100%
    );
  }
  .hero-gmm .container { position: relative; z-index: 1; padding-top: 80px; padding-bottom: 90px; }
  .hero-gmm-inner { text-align: center; }
  .hero-gmm .eyebrow {
    display: inline-block; font-size: 13px; font-weight: 700; letter-spacing: 2px;
    text-transform: uppercase; color: #0a374f; margin-bottom: 18px;
  }
  .hero-gmm h1 {
    font-family: var(--font-display);
    font-size: clamp(2.2rem, 5vw, 3.4rem);
    font-weight: 900; line-height: 1.05; letter-spacing: -1.5px;
    margin: 0 auto 18px; max-width: 880px;
  }
  .hero-gmm h1 em { font-style: italic; color: #0a374f; font-weight: 500; }
  .hero-gmm p.lead {
    font-size: 18px; color: rgba(255,255,255,0.85);
    max-width: 720px; margin: 0 auto 28px;
  }
  .hero-gmm-stats {
    display: inline-flex; gap: 32px; flex-wrap: wrap; justify-content: center;
    padding: 18px 32px; background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.10);
    border-radius: 999px;
    margin-bottom: 28px;
  }
  .hero-stat { display: flex; align-items: center; gap: 8px; font-size: 14px; color: rgba(255, 255, 255, 0.92); }
  .hero-stat strong { color: #fff; font-family: var(--font-display); font-size: 20px; font-weight: 900; }
  .hero-gmm-ctas { display: inline-flex; gap: 14px; flex-wrap: wrap; justify-content: center; }

  /* ========== SECTION-HEAD ========== */
  .section-head { margin-bottom: 48px; text-align: center; }
  .section-eyebrow {
    display: inline-block;
    font-size: 12px; font-weight: 700; letter-spacing: 2px;
    text-transform: uppercase; color: var(--accent-dark);
    margin-bottom: 12px;
  }
  .section-head h2 {
    font-family: var(--font-display);
    font-size: clamp(1.8rem, 3.5vw, 2.6rem);
    font-weight: 900; color: var(--primary);
    letter-spacing: -1px; line-height: 1.1; margin-bottom: 12px;
  }
  .section-head h2 em { font-style: italic; color: var(--accent-dark); font-weight: 500; }
  .section-head p { font-size: 16px; color: var(--gray-600); max-width: 640px; margin: 0 auto; }

  /* ========== 3 PERFILES (núcleo de la página) ========== */
  .perfiles-section { padding: 90px 0 70px; background: var(--white); }
  .perfiles-grid {
    display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px;
  }
  .perfil-card {
    background: var(--white);
    border: 2px solid var(--gray-200);
    border-radius: var(--radius-xl);
    padding: 40px 32px;
    transition: transform .3s, box-shadow .3s, border-color .3s;
    position: relative; overflow: hidden;
    display: flex; flex-direction: column;
  }
  .perfil-card:hover { transform: translateY(-6px); box-shadow: var(--shadow-lg); border-color: var(--accent); }
  .perfil-emoji {
    font-size: 56px;
    line-height: 1;
    margin-bottom: 22px;
  }
  .perfil-card h3 {
    font-family: var(--font-display);
    font-size: 26px; font-weight: 800; color: var(--primary);
    line-height: 1.15; margin-bottom: 8px; letter-spacing: -0.5px;
  }
  .perfil-card .desc { font-size: 15px; color: var(--gray-600); margin-bottom: 22px; line-height: 1.55; }
  .perfil-recomienda {
    background: var(rgba(243, 195, 50, 0.10)); color: var(--primary);
    padding: 14px 16px; border-radius: var(--radius-md);
    margin-bottom: 18px; font-size: 13px;
  }
  .perfil-recomienda strong {
    display: block; color: var(--accent-dark); font-size: 11px;
    text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px;
  }
  .perfil-features { list-style: none; flex: 1; margin-bottom: 22px; }
  .perfil-features li {
    padding: 6px 0; font-size: 14px; color: var(--primary);
    display: flex; align-items: flex-start; gap: 10px;
  }
  .perfil-features svg { color: var(--success); flex-shrink: 0; margin-top: 3px; }
  .perfil-cta {
    display: block; text-align: center; padding: 14px;
    border-radius: 999px; font-weight: 700; font-size: 14px;
    background: var(--primary); color: var(--white);
    transition: all .2s;
  }
  .perfil-cta:hover { background: var(--accent); color: var(--primary); }

  .perfiles-note {
    text-align: center; font-size: 14px; color: var(--gray-400);
    margin-top: 30px;
  }
  .perfiles-note a { color: var(--accent-dark); font-weight: 700; border-bottom: 1px solid var(--accent); }

  /* ========== ¿POR QUÉ UN INTERMEDIARIO? ========== */
  .intermediario-section { padding: 80px 0; background: var(--gray-50); }
  .intermediario-grid {
    display: grid; grid-template-columns: 1fr 1fr; gap: 50px; align-items: center;
  }
  .intermediario-text .eyebrow {
    display: inline-block; font-size: 13px; font-weight: 700; letter-spacing: 2px;
    text-transform: uppercase; color: var(--accent-dark); margin-bottom: 16px;
  }
  .intermediario-text h2 {
    font-family: var(--font-display);
    font-size: clamp(1.8rem, 3.5vw, 2.4rem);
    font-weight: 900; color: var(--primary);
    letter-spacing: -1px; line-height: 1.15;
    margin-bottom: 18px;
  }
  .intermediario-text h2 em { font-style: italic; color: var(--accent-dark); font-weight: 500; }
  .intermediario-text p.intro {
    font-size: 16px; color: var(--gray-600); line-height: 1.6;
    margin-bottom: 24px;
  }
  .intermediario-text p.highlight {
    background: var(--white); padding: 20px 22px;
    border-left: 4px solid var(--accent);
    border-radius: 0 var(--radius-md) var(--radius-md) 0;
    font-size: 16px; color: var(--primary); line-height: 1.55;
    margin-bottom: 24px;
    box-shadow: var(--shadow-sm);
  }
  .intermediario-text p.highlight strong { display: block; color: var(--accent-dark); font-size: 13px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px; }
  .compare-vs {
    background: var(--white); border-radius: var(--radius-xl);
    padding: 32px; box-shadow: var(--shadow-md);
    border: 1px solid var(--gray-200);
  }
  .compare-vs-head {
    display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 18px;
    text-align: center;
  }
  .compare-vs-head > div {
    padding: 12px;
    border-radius: var(--radius-sm);
    font-weight: 800; font-size: 14px;
  }
  .compare-vs-head .col-direct { background: var(--gray-100); color: var(--gray-600); }
  .compare-vs-head .col-lux { background: var(--primary); color: var(--accent); }
  .compare-row {
    display: grid; grid-template-columns: 1fr 1fr; gap: 12px;
    padding: 14px 0; border-top: 1px solid var(--gray-200);
    font-size: 14px;
  }
  .compare-row > div { display: flex; align-items: center; gap: 8px; }
  .compare-direct { color: var(--gray-600); }
  .compare-lux { color: var(--primary); font-weight: 600; }
  .compare-lux svg { color: var(--success); flex-shrink: 0; }
  .compare-direct svg { color: var(--gray-400); flex-shrink: 0; }

  /* ========== CASO REAL (Ricardo) ========== */
  .caso-section { padding: 80px 0; background: var(--white); }
  .caso-card {
    max-width: 980px; margin: 0 auto;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    color: var(--white);
    border-radius: var(--radius-xl);
    padding: 50px 44px;
    position: relative; overflow: hidden;
  }
  .caso-card::before {
    content: '';
    position: absolute; top: -50px; right: -50px;
    width: 200px; height: 200px; border-radius: 50%;
    background: rgba(243, 195, 50, 0.10);
  }
  .caso-card::after {
    content: '📖';
    position: absolute; bottom: -20px; right: 30px;
    font-size: 140px;
    opacity: 0.06;
  }
  .caso-eyebrow {
    display: inline-block; font-size: 12px; font-weight: 700; letter-spacing: 2px;
    text-transform: uppercase; color: var(--accent); margin-bottom: 14px;
    position: relative;
  }
  .caso-card h3 {
    font-family: var(--font-display);
    font-size: clamp(1.6rem, 3vw, 2.2rem);
    font-weight: 800; line-height: 1.15; letter-spacing: -0.5px;
    margin-bottom: 14px;
    position: relative;
  }
  .caso-card h3 em { font-style: italic; color: var(--accent); font-weight: 500; }
  .caso-card p.intro {
    font-size: 16px; color: rgba(255,255,255,0.85);
    margin-bottom: 32px; max-width: 700px;
    position: relative;
  }
  .caso-events {
    display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;
    position: relative;
  }
  .caso-event {
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.10);
    border-radius: var(--radius-md);
    padding: 22px 20px;
    transition: background .3s;
  }
  .caso-event:hover { background: rgba(255,255,255,0.10); }
  .caso-event-emoji { font-size: 32px; margin-bottom: 10px; }
  .caso-event h5 { font-family: var(--font-display); font-size: 16px; font-weight: 700; color: var(--white); margin-bottom: 6px; }
  .caso-event p { font-size: 13px; color: rgba(255,255,255,0.75); line-height: 1.5; margin-bottom: 12px; }
  .caso-savings {
    display: inline-flex; align-items: center; gap: 6px;
    background: var(--accent); color: var(--primary);
    padding: 5px 11px; border-radius: 999px;
    font-size: 12px; font-weight: 800;
  }

  /* ========== ASISTENCIAS (jerarquizadas) ========== */
  .asistencias-section { padding: 80px 0; background: var(--gray-50); }
  .asistencias-main {
    display: grid; grid-template-columns: 1fr 1fr; gap: 22px;
    margin-bottom: 22px;
  }
  .asistencia-big {
    background: var(--white);
    border-radius: var(--radius-xl);
    padding: 34px 30px;
    border: 1px solid var(--gray-200);
    transition: transform .3s, box-shadow .3s;
    position: relative;
  }
  .asistencia-big:hover { transform: translateY(-4px); box-shadow: var(--shadow-md); }
  .asistencia-big.featured {
    background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%);
    color: var(--primary); border-color: var(--accent);
  }
  .asistencia-tag {
    position: absolute; top: 16px; right: 16px;
    background: var(--primary); color: var(--accent);
    font-size: 10px; font-weight: 800; padding: 4px 10px;
    border-radius: 999px; letter-spacing: 1px; text-transform: uppercase;
  }
  .asistencia-big .emoji { font-size: 38px; margin-bottom: 14px; }
  .asistencia-big h4 { font-family: var(--font-display); font-size: 22px; font-weight: 800; margin-bottom: 12px; letter-spacing: -0.3px; }
  .asistencia-big.featured h4 { color: var(--primary); }
  .asistencia-big ul { list-style: none; }
  .asistencia-big ul li {
    padding: 6px 0; font-size: 14px;
    display: flex; align-items: flex-start; gap: 10px;
  }
  .asistencia-big ul li::before { content: '✓'; font-weight: 700; flex-shrink: 0; }
  .asistencia-big ul li { color: var(--gray-600); }
  .asistencia-big ul li::before { color: var(--success); }
  .asistencia-big.featured ul li { color: var(--primary); }
  .asistencia-big.featured ul li::before { color: var(--primary); }

  .asistencias-extras {
    display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px;
  }
  .asistencia-mini {
    background: var(--white);
    border-radius: var(--radius-md);
    padding: 18px 22px;
    border: 1px solid var(--gray-200);
    display: flex; align-items: center; gap: 14px;
    transition: transform .2s, box-shadow .2s;
  }
  .asistencia-mini:hover { transform: translateY(-2px); box-shadow: var(--shadow-sm); }
  .asistencia-mini .emoji { font-size: 26px; flex-shrink: 0; }
  .asistencia-mini h5 { font-family: var(--font-display); font-size: 15px; color: var(--primary); margin-bottom: 2px; }
  .asistencia-mini p { font-size: 12px; color: var(--gray-600); }

  /* ========== RED HOSPITALARIA (compacta) ========== */
  .red-section { padding: 70px 0; background: var(--white); }
  .red-stats {
    display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;
    max-width: 800px; margin: 0 auto 24px;
  }
  .red-stat {
    background: var(--gray-50); padding: 24px 20px;
    border-radius: var(--radius-md); text-align: center;
    border: 1px solid var(--gray-200);
  }
  .red-stat-num {
    font-family: var(--font-display); font-size: 36px; font-weight: 900;
    color: var(--accent-dark); line-height: 1; margin-bottom: 6px;
  }
  .red-stat p { font-size: 13px; color: var(--gray-600); }
  .hospitales-tags {
    display: flex; justify-content: center; flex-wrap: wrap; gap: 10px;
    max-width: 700px; margin: 0 auto 14px;
  }
  .hospital-tag {
    background: var(--white); border: 1px solid var(--gray-200);
    padding: 10px 18px; border-radius: 999px;
    font-weight: 700; font-size: 14px; color: var(--primary);
  }
  .red-note {
    text-align: center; font-size: 13px; color: var(--gray-400);
    max-width: 660px; margin: 0 auto;
  }

  /* ========== FORMULARIO CORTO ========== */
  .form-section { padding: 80px 0; background: var(--gray-50); }
  .form-wrap {
    max-width: 540px; margin: 0 auto;
    background: var(--white); border-radius: var(--radius-xl);
    padding: 38px 36px; box-shadow: var(--shadow-md);
    border: 1px solid var(--gray-200);
    position: relative;
  }
  .form-wrap::before {
    content: ''; position: absolute; top: -1px; left: 24px; right: 24px; height: 4px;
    background: var(--accent); border-radius: 0 0 4px 4px;
  }
  .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px; }
  .form-row.full { grid-template-columns: 1fr; }
  .form-group label {
    display: block; font-size: 12px; font-weight: 700;
    color: var(--primary); margin-bottom: 6px;
    text-transform: uppercase; letter-spacing: 0.5px;
  }
  .form-group input {
    width: 100%; padding: 14px 16px;
    border: 1.5px solid var(--gray-200); border-radius: 10px;
    font-family: inherit; font-size: 15px;
    color: var(--primary); background: var(--white);
    transition: border-color .2s;
  }
  .form-group input:focus { outline: 0; border-color: var(--accent); }
  .form-submit { width: 100%; margin-top: 10px; }
  .form-extra-note {
    font-size: 12px; color: var(--gray-400); text-align: center;
    margin-top: 14px; line-height: 1.5;
  }

  @media (max-width: 900px) {
    .perfiles-grid { grid-template-columns: 1fr; }
    .intermediario-grid { grid-template-columns: 1fr; gap: 32px; }
    .caso-events { grid-template-columns: 1fr; }
    .asistencias-main { grid-template-columns: 1fr; }
    .asistencias-extras { grid-template-columns: 1fr; }
    .red-stats { grid-template-columns: 1fr; }
    .form-row { grid-template-columns: 1fr; }
    .form-wrap { padding: 28px 22px; }
    .caso-card { padding: 36px 26px; }
    .compare-vs { padding: 22px; }
  }
</style>
</head>
<body class="product-gmm">

<!-- NAV -->
<nav class="nav">
  <div class="nav-inner">
    <a href="/" class="nav-logo">Seguros<span>Lux</span></a>
    <ul class="nav-menu">
      <li><a href="#inicio">Inicio</a></li>
      <li><a href="/#beneficios">Beneficios</a></li>
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
          <li><a href="/#aseguradoras">Aseguradoras</a></li>
          <li><a href="/#faq">Preguntas Frecuentes</a></li>
          <li><a href="/#avisos-legales">Avisos Legales</a></li>
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
    <span class="current">Gastos Médicos Mayores</span>
  </div>
</div>

<!-- HERO -->
<section class="hero-gmm" id="inicio">
  <div class="hero-gmm-img-wrap">
    <img
      src="/images/banner_gmm.webp"
      alt="Atención médica de calidad con Seguros Lux"
      width="1440" height="600"
      fetchpriority="high"
      decoding="async"
    >
  </div>
  <div class="container">
    <div class="hero-gmm-inner">
      <div class="eyebrow">Gastos Médicos Mayores</div>
      <h1>El mejor, <em>asesorado por alguien que pelea por ti</em></h1>
      <p class="lead">Te ayudamos a elegir entre las mejores coberturas del mercado y te acompañamos cuando más lo necesitas. Como intermediario autorizado por la CNSF, somos tu voz frente a la aseguradora.</p>
      <div class="hero-gmm-stats">
        <div class="hero-stat"><strong>+460</strong> hospitales certificados</div>
        <div class="hero-stat"><strong>+15</strong> años asesorando</div>
        <div class="hero-stat"><strong>24/7</strong> atención médica</div>
      </div>
      <div class="hero-gmm-ctas">
        <a href="#cotizar" class="btn btn-primary">
          Cotizar en ahora
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
        </a>
        <a href="tel:+525541232311" class="btn btn-ghost">Hablar con asesor</a>
      </div>
    </div>
  </div>
</section>

<!-- 3 PERFILES (núcleo) -->
<section class="perfiles-section" id="perfiles">
  <div class="container">
    <div class="section-head">
      <div class="section-eyebrow">Encuentra tu plan</div>
      <h2>¿Cuál se parece <em>más a ti?</em></h2>
      <p>Olvídate de comparar 11 productos confusos. Te ayudamos a elegir según tu estilo de vida.</p>
    </div>
    <div class="perfiles-grid">
      <!-- Perfil 1: Familia en México -->
      <article class="perfil-card">
        <div class="perfil-emoji">🏠</div>
        <h3>Para mí y mi familia</h3>
        <p class="desc">Quieres protección hospitalaria sólida en el país, con red de hospitales privados y atención médica 24/7.</p>
        <div class="perfil-recomienda">
          <strong>Recomendamos</strong>
          Plan Esencial · Versátil · Premium / Platino
        </div>
        <ul class="perfil-features">
          <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Red de +460 hospitales en México</li>
          <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Pago directo al hospital</li>
          <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Cobertura para cónyuge e hijos</li>
          <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Asistencia dental, visión y viajes</li>
        </ul>
        <a href="#cotizar" class="perfil-cta">Cotizar este perfil</a>
      </article>

      <!-- Perfil 2: Internacional -->
      <article class="perfil-card">
        <div class="perfil-emoji">✈️</div>
        <h3>Seguros para viajeros</h3>
        <p class="desc">Necesitas atención médica de primer nivel sin importar dónde estés. Ejecutivos, expatriados y viajeros frecuentes.</p>
        <div class="perfil-recomienda">
          <strong>Recomendamos</strong>
          Línea Azul Internacional · VIP · Enlace Internacional
        </div>
        <ul class="perfil-features">
          <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Cobertura nacional e internacional</li>
          <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Hospitales top en EUA y Europa</li>
          <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Repatriación incluida</li>
          <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Asistencia médica global 24/7</li>
        </ul>
        <a href="#cotizar" class="perfil-cta">Cotizar este perfil</a>
      </article>

      <!-- Perfil 3: Padecimiento complejo -->
      <article class="perfil-card">
        <div class="perfil-emoji">⚕️</div>
        <h3>Tengo un padecimiento complejo</h3>
        <p class="desc">Buscas atención de máximo nivel para condiciones que requieren especialistas, segunda opinión y centros élite.</p>
        <div class="perfil-recomienda">
          <strong>Recomendamos</strong>
          Alta Especialidad · Indemniza · Plenitud
        </div>
        <ul class="perfil-features">
          <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Centros médicos de alta especialidad</li>
          <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Segunda opinión médica certificada</li>
          <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Acompañamiento personalizado</li>
          <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Cobertura indemnizatoria opcional</li>
        </ul>
        <a href="#cotizar" class="perfil-cta">Cotizar este perfil</a>
      </article>
    </div>
    <p class="perfiles-note">¿No te identificas con ninguno? <a href="#cotizar">Habla con un asesor</a> y te orientamos sin compromiso.</p>
  </div>
</section>

<!-- ¿POR QUÉ UN INTERMEDIARIO? -->
<section class="intermediario-section">
  <div class="container">
    <div class="intermediario-grid">
      <div class="intermediario-text">
        <div class="eyebrow">¿Por qué Lux?</div>
        <h2>Contigo o sin nosotros, <em>pagas lo mismo</em></h2>
        <p class="intro">Tu póliza la emite la aseguradora. Como intermediario autorizado por la CNSF, nuestro servicio no te cuesta nada extra. Pero contigo o sin nosotros, hay una diferencia enorme.</p>
        <p class="highlight">
          <strong>La diferencia clave</strong>
          Con la aseguradora directo, eres uno más. Con Seguros Lux, tienes a alguien que pelea por ti cuando la aseguradora dice "no", "espera" o "necesito más papeles".
        </p>
        <a href="#cotizar" class="btn btn-primary">Cotizar con asesor Lux</a>
      </div>
      <div class="compare-vs">
        <div class="compare-vs-head">
          <div class="col-direct">Sin intermediario</div>
          <div class="col-lux">Con Seguros Lux</div>
        </div>
        <div class="compare-row">
          <div class="compare-direct"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg> Llamas a un call center</div>
          <div class="compare-lux"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Tu asesor te conoce</div>
        </div>
        <div class="compare-row">
          <div class="compare-direct"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg> Eliges tú solo entre muchos productos</div>
          <div class="compare-lux"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Te orientamos al ideal</div>
        </div>
        <div class="compare-row">
          <div class="compare-direct"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg> Te dan un "no" y se acabó</div>
          <div class="compare-lux"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Buscamos las mejores opciones.</div>
        </div>
        <div class="compare-row">
          <div class="compare-direct"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg> Renovación automática silenciosa</div>
          <div class="compare-lux"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> Revisamos cada año</div>
        </div>
        <div class="compare-row">
          <div class="compare-direct"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg> Mismo precio</div>
          <div class="compare-lux"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg> <strong>Mismo precio</strong></div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- 3 EJEMPLOS MEMBRESÍA MÉDICA MÓVIL -->
<section class="caso-section">
  <div class="container">
    <div class="caso-card">
      <div class="caso-eyebrow">📱 Membresía Médica Móvil</div>
      <h3>3 ejemplos de cómo usar tu <em>membresía móvil</em></h3>
      <p class="intro">Tu membresía incluye tres tipos de atención disponibles 24/7, sin afectar tu póliza y sin costo adicional.</p>
      <div class="caso-events">
        <div class="caso-event">
          <div class="caso-event-emoji">💻</div>
          <h5>Atención médica por videollamada</h5>
          <p>Consulta con un médico certificado desde tu teléfono o computadora. Ideal para síntomas leves, seguimientos y recetas digitales sin salir de casa.</p>
          <span class="caso-savings">Disponible 24/7</span>
        </div>
        <div class="caso-event">
          <div class="caso-event-emoji">🏠</div>
          <h5>Atención médica a domicilio</h5>
          <p>Un médico va hasta donde estás. Para casos que requieren revisión presencial pero no ameritan urgencias. Sin filas, sin salas de espera.</p>
          <span class="caso-savings">A tu puerta</span>
        </div>
        <div class="caso-event">
          <div class="caso-event-emoji">🚑</div>
          <h5>Servicio de ambulancia</h5>
          <p>En una emergencia real, el servicio de traslado médico está disponible para llevarte al hospital de la red más cercano con atención especializada.</p>
          <span class="caso-savings">Emergencias reales</span>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ASISTENCIAS jerarquizadas -->
<section class="asistencias-section">
  <div class="container">
    <div class="section-head">
      <div class="section-eyebrow">Lo que también incluye</div>
      <h2>Más allá de <em>la hospitalización</em></h2>
      <p>Incluye asistencias sin costo adicional que cubren toda la familia.</p>
    </div>
    <div class="asistencias-main">
      <div class="asistencia-big featured">
        <span class="asistencia-tag">★ Más usada</span>
        <div class="emoji">🦷</div>
        <h4>Asistencia Dental</h4>
        <ul>
          <li>2 limpiezas dentales al año sin costo</li>
          <li>Revisión y diagnóstico sin costo</li>
          <li>Hasta 70% de descuento en resinas y extracciones</li>
          <li>50% en endodoncias y procedimientos especializados</li>
        </ul>
      </div>
      <div class="asistencia-big">
        <span class="asistencia-tag">Para viajeros</span>
        <div class="emoji">✈️</div>
        <h4>Asistencia en Viajes</h4>
        <ul>
          <li>Traslado médico durante emergencia en viaje</li>
          <li>Repatriación o traslado de restos</li>
          <li>Boleto redondo para un familiar (>5 días hospitalizado)</li>
          <li>Hasta 5 noches de hospedaje por convalecencia</li>
        </ul>
      </div>
    </div>
    <div class="asistencias-extras">
      <div class="asistencia-mini">
        <div class="emoji">👁️</div>
        <div>
          <h5>Asistencia Visión</h5>
          <p>Lentes anuales sin costo y descuentos en ópticas participantes (la red varía según aseguradora y región).</p>
        </div>
      </div>
      <div class="asistencia-mini">
        <div class="emoji">🌹</div>
        <div>
          <h5>Asistencia Funeraria</h5>
          <p>Servicio funerario completo con asesoría jurídica y tanatología.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- RED HOSPITALARIA (compacta) -->
<section class="red-section">
  <div class="container">
    <div class="section-head">
      <div class="section-eyebrow">Red hospitalaria</div>
      <h2>Atención en <em>todo México</em></h2>
    </div>
    <div class="red-stats">
      <div class="red-stat">
        <div class="red-stat-num">+460</div>
        <p>Hospitales certificados</p>
      </div>
      <div class="red-stat">
        <div class="red-stat-num">+7,000</div>
        <p>Médicos en convenio</p>
      </div>
      <div class="red-stat">
        <div class="red-stat-num">+1,040</div>
        <p>Laboratorios certificados</p>
      </div>
    </div>
    <div class="hospitales-tags">
      <span class="hospital-tag">Hospital ABC</span>
      <span class="hospital-tag">Médica Sur</span>
      <span class="hospital-tag">Ángeles</span>
      <span class="hospital-tag">Christus Muguerza</span>
      <span class="hospital-tag">San Javier</span>
      <span class="hospital-tag">Star Médica</span>
    </div>
    <p class="red-note">En hospitales de la red, la aseguradora paga directo. Solo cubres tu deducible y coaseguro.</p>
  </div>
</section>

<!-- FORMULARIO CORTO (3 campos) -->
<section class="form-section" id="cotizar">
  <div class="container">
    <div class="section-head">
      <div class="section-eyebrow">Cotización gratuita</div>
      <h2>Cotiza <em>ahora.</em></h2>
      <p>Solo necesitamos lo esencial. Tu asesor te contacta por WhatsApp para los detalles.</p>
    </div>
    <div class="form-wrap">
      <form id="formGmm" novalidate>
        <div class="form-row full">
          <div class="form-group">
            <label for="gmm-nombre">Nombre completo *</label>
            <input type="text" id="gmm-nombre" name="nombre" required autocomplete="name" placeholder="Tu nombre">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label for="gmm-telefono">WhatsApp *</label>
            <input type="tel" id="gmm-telefono" name="telefono" required inputmode="numeric" maxlength="10" autocomplete="tel-national" placeholder="55 1234 5678">
          </div>
          <div class="form-group">
            <label for="gmm-nacimiento">Fecha de nacimiento *</label>
            <input type="date" id="gmm-nacimiento" name="fecha_nacimiento" required>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label for="gmm-correo">Correo electrónico</label>
            <input type="email" id="gmm-correo" name="correo" placeholder="tu@correo.com" autocomplete="email">
          </div>
          <div class="form-group">
            <label for="gmm-edad">Edad *</label>
            <input type="number" id="gmm-edad" name="edad" required min="18" max="80" placeholder="35">
          </div>
        </div>
        <div class="form-row full">
          <div class="form-group">
            <label for="gmm-hospitales">¿En qué hospitales te has atendido? *</label>
            <input type="text" id="gmm-hospitales" name="hospitales" required placeholder="Ej. Hospital ABC, Ángeles, IMSS, Médica Sur...">
          </div>
        </div>
        <div class="form-row full">
          <div class="form-group">
            <label for="gmm-comentarios">Comentarios adicionales</label>
            <textarea id="gmm-comentarios" name="comentarios" style="width:100%;padding:14px 16px;border:1.5px solid var(--gray-200);border-radius:10px;font-family:inherit;font-size:15px;resize:vertical;min-height:80px;" placeholder="Cuéntanos más: antecedentes médicos, personas a asegurar, dudas específicas..."></textarea>
          </div>
        </div>

        <!-- Fecha preferida + semáforo de urgencia -->
        <div class="form-row" style="align-items:flex-end;">
          <div class="form-group">
            <label for="gmm-fecha">Fecha preferida de contacto</label>
            <input type="date" id="gmm-fecha" name="fecha_preferida">
          </div>
          <div class="form-group">
            <label>Nivel de urgencia</label>
            <div style="display:flex;gap:8px;margin-top:4px;">
              <label style="flex:1;cursor:pointer;">
                <input type="radio" name="urgencia" value="urgente" style="display:none;" onchange="document.querySelectorAll('.urgencia-btn').forEach(b=>b.style.background='');this.closest('label').querySelector('.urgencia-btn').style.background='#fef2f2';this.closest('label').querySelector('.urgencia-btn').style.borderColor='#ef4444';">
                <span class="urgencia-btn" style="display:flex;align-items:center;justify-content:center;gap:6px;padding:12px 10px;border:1.5px solid var(--gray-200);border-radius:10px;font-size:13px;font-weight:700;transition:all .2s;text-align:center;">🔴 Urgente</span>
              </label>
              <label style="flex:1;cursor:pointer;">
                <input type="radio" name="urgencia" value="despues" style="display:none;" checked onchange="document.querySelectorAll('.urgencia-btn').forEach(b=>b.style.background='');this.closest('label').querySelector('.urgencia-btn').style.background='#f0fdf4';this.closest('label').querySelector('.urgencia-btn').style.borderColor='#22c55e';">
                <span class="urgencia-btn" style="display:flex;align-items:center;justify-content:center;gap:6px;padding:12px 10px;border:1.5px solid var(--gray-200);border-radius:10px;font-size:13px;font-weight:700;transition:all .2s;text-align:center;">🟢 Sin prisa</span>
              </label>
            </div>
          </div>
        </div>

        <button type="submit" class="btn btn-primary form-submit">
          Cotizar ahora
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
        </button>
        <p class="form-extra-note">Al enviar aceptas que un asesor te contacte por WhatsApp. Sin compromiso, sin spam.</p>
      </form>
    </div>
  </div>
</section>

<!-- FAQ -->
<section class="faq-section">
  <div class="container">
    <div class="section-head">
      <div class="section-eyebrow">Preguntas frecuentes</div>
      <h2>Dudas</h2>
    </div>
    <div class="faq-wrap">
      <details class="faq-item">
        <summary>¿Cuánto cuesta contratar con Lux vs directo con la aseguradora?</summary>
        <div class="faq-answer">Cuesta exactamente lo mismo. Como intermediario autorizado por la CNSF, nuestro servicio no se suma al precio de tu póliza. La aseguradora paga nuestra comisión, no tú.</div>
      </details>
      <details class="faq-item">
        <summary>¿Qué es el deducible y coaseguro?</summary>
        <div class="faq-answer">El deducible es una cantidad fija que pagas al inicio de cada padecimiento. El coaseguro es un porcentaje (10–20%) de los gastos después del deducible, hasta un tope. Tu asesor Lux te explica cuál combinación conviene a tu situación.</div>
      </details>
      <details class="faq-item">
        <summary>¿Cubren enfermedades preexistentes?</summary>
        <div class="faq-answer">Depende. Algunas condiciones tienen periodos de espera, exclusiones o extraprimas. Es esencial declararlas al contratar. Te ayudamos a presentar tu solicitud de la forma real.</div>
      </details>
      <details class="faq-item">
        <summary>¿Cómo funciona el pago directo al hospital?</summary>
        <div class="faq-answer">En los +460 hospitales de la red, la aseguradora paga directamente al hospital. Solo cubres tu deducible y coaseguro. En urgencias puedes ir a cualquier hospital y solicitar reembolso después.</div>
      </details>
      <details class="faq-item">
        <summary>¿Puedo agregar a mi familia?</summary>
        <div class="faq-answer">Sí, cónyuge, padres e hijos. Los planes familiares suelen tener descuentos especiales sobre tarifa individual.</div>
      </details>
      <details class="faq-item">
        <summary>¿Qué incluye la Membresía Médica Móvil?</summary>
        <div class="faq-answer">Orientación médica telefónica ilimitada y sin costo, videoconsultas, acceso a psicólogos y nutriólogos a costo preferente. Disponible 24/7 sin afectar tu póliza.</div>
      </details>
    </div>
  </div>
</section>

<!-- CTA FINAL -->
<section class="cta-final" id="contacto">
  <div class="container">
    <h2>Buscamos la <em>solución para ti.</em></h2>
    <p>Mismo precio que ir directo. Asesoría real cuando más importa.</p>
    <div class="cta-final-actions">
      <a href="#cotizar" class="btn btn-primary">Cotizar ahora</a>
      <a href="tel:+525541232311" class="btn btn-ghost">Hablar con asesor</a>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer>
  <div class="container">
    <div class="footer-grid">
      <div class="footer-about">
        <div class="footer-logo">Seguros<span>Lux</span></div>
        <p>Intermediario autorizado por la CNSF. Protegiendo lo que más importa desde 2010.</p>
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
      <div>© 2026 Seguros Lux. Intermediario autorizado por la CNSF. Todos los derechos reservados.</div>
      <div>Powered by <strong style="color: #fff">GrupoIdeas</strong></div>
    </div>
  </div>
</footer>

<a href="https://wa.me/525541232311" class="whatsapp-float" aria-label="WhatsApp">
  <svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347M12 2C6.48 2 2 6.48 2 12c0 1.77.46 3.45 1.27 4.9L2 22l5.25-1.38C8.66 21.5 10.27 22 12 22c5.52 0 10-4.48 10-10S17.52 2 12 2z"/></svg>
</a>

<script src="../js/landing.js" defer></script>
<script src="../js/gmm.js" defer></script>
</body>
</html>