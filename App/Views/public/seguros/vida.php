<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Seguro de Vida | Seguros Lux</title>
<meta name="description" content="Seguro de Vida: protege el futuro de tu familia con coberturas flexibles y la mejor asesoría personalizada." />
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Google+Sans:ital,opsz,wght@0,17..18,400..700;1,17..18,400..700&family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../css/landing.css">
<link rel="icon" type="image/png" sizes="32x32" href="/images/logo.png">
<link rel="stylesheet" href="../css/nav-submenus.css">
<style>
  /* Override del acento amarillo por el rojo Vida en esta página */
  .product-vida {
    --accent: var(--vida-primary);
    --accent-light: var(--vida-light);
    --accent-dark: var(--vida-dark);
  }
  .product-vida .nav-cta { background: var(--vida-primary); color: var(--white); }
  .product-vida .nav-cta:hover { background: var(--vida-dark); color: var(--white); }
  .product-vida .btn-primary {
    background: var(--vida-primary); color: var(--white);
    box-shadow: 0 10px 24px rgba(225, 29, 72, 0.3);
  }
  .product-vida .btn-primary:hover {
    background: var(--vida-dark);
    box-shadow: 0 14px 30px rgba(225, 29, 72, 0.4);
  }
  .plans-section { padding: 80px 0 30px; background: var(--white); }
  .benefits-section { padding: 80px 0; background: var(--gray-50); }
  .benefits-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; }
  .benefit-card {
    background: var(--white); border-radius: var(--radius-md);
    padding: 28px 22px; text-align: center;
    border: 1px solid var(--gray-200);
    transition: transform .3s, box-shadow .3s;
  }
  .benefit-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-md); }
  .benefit-card .icon {
    width: 52px; height: 52px; margin: 0 auto 16px;
    background: var(--primary); color: var(--accent);
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
  }
  .benefit-card h4 { font-family: var(--font-display); font-size: 17px; color: var(--primary); margin-bottom: 6px; }
  .benefit-card p { font-size: 13px; color: var(--gray-600); }

  .form-section { padding: 80px 0; background: var(--white); }
  .form-wrap {
    max-width: 640px; margin: 0 auto;
    background: var(--white); border-radius: var(--radius-xl);
    padding: 40px; box-shadow: var(--shadow-md);
    border: 1px solid var(--gray-200);
  }
  .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
  .form-row.full { grid-template-columns: 1fr; }
  .form-group label {
    display: block; font-size: 13px; font-weight: 700;
    color: var(--primary); margin-bottom: 6px;
  }
  .form-group input, .form-group select, .form-group textarea {
    width: 100%; padding: 12px 14px;
    border: 1.5px solid var(--gray-200);
    border-radius: 10px;
    font-family: inherit; font-size: 14px;
    color: var(--primary); background: var(--white);
    transition: border-color .2s;
  }
  .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
    outline: 0; border-color: var(--accent);
  }
  .form-group textarea { resize: vertical; min-height: 80px; }
  .form-submit { width: 100%; margin-top: 8px; }

  /* ========== HERO CON IMAGEN ========== */
  .sub-hero {
    position: relative;
    color: var(--white);
    padding: 0;
    overflow: hidden;
    min-height: 420px;
    display: flex;
    flex-direction: column;
    justify-content: center;
  }
  .sub-hero-img-wrap {
    position: absolute;
    inset: 0;
    z-index: 0;
  }
  .sub-hero-img-wrap img {
    width: 100%; height: 100%;
    object-fit: cover;
    object-position: center;
    display: block;
  }
  .sub-hero-img-wrap::after {
    content: '';
    position: absolute; inset: 0;
    background: linear-gradient(
      135deg,
      rgba(15,23,42,0.82) 0%,
      rgba(190,18,60,0.55) 60%,
      rgba(15,23,42,0.70) 100%
    );
  }
  .sub-hero .container {
    position: relative;
    z-index: 1;
    padding-top: 80px;
    padding-bottom: 90px;
  }
  .sub-hero-eyebrow {
    display: inline-block;
    font-size: 12px; font-weight: 700; letter-spacing: 2px;
    text-transform: uppercase; color: var(--vida-light);
    margin-bottom: 16px;
  }
  .sub-hero h1 {
    font-family: var(--font-display);
    font-size: clamp(2.2rem, 5vw, 3.4rem);
    font-weight: 900; line-height: 1.05; letter-spacing: -1.5px;
    margin-bottom: 18px; max-width: 680px;
  }
  .sub-hero h1 em { font-style: italic; color: var(--vida-light); font-weight: 500; }
  .sub-hero .lead {
    font-size: 18px; color: rgba(255,255,255,0.85);
    max-width: 560px; margin-bottom: 32px;
  }
  .sub-hero-ctas { display: flex; flex-wrap: wrap; gap: 14px; }

  /* ========== SECTION-HEAD (subtítulos de sección) ========== */
  .section-head {
    margin-bottom: 48px;
    text-align: center;
  }
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
    letter-spacing: -1px; line-height: 1.1;
    margin-bottom: 12px;
  }
  .section-head h2 em { font-style: italic; color: var(--accent-dark); font-weight: 500; }
  .section-head p { font-size: 16px; color: var(--gray-600); max-width: 640px; margin: 0 auto; }

  /* ========== FAQ ========== */
  .faq-section { padding: 80px 0; background: var(--gray-50); }
  .faq-wrap { max-width: 760px; margin: 0 auto; }
  .faq-item {
    background: var(--white);
    border: 1px solid var(--gray-200);
    border-radius: var(--radius-md);
    margin-bottom: 10px;
    overflow: hidden;
    transition: box-shadow .2s;
  }
  .faq-item:hover { box-shadow: var(--shadow-sm); }
  .faq-item[open] {
    border-color: var(--vida-primary);
    box-shadow: 0 0 0 1px var(--vida-primary);
  }
  .faq-item summary {
    padding: 18px 22px;
    font-weight: 700; font-size: 15px; color: var(--primary);
    cursor: pointer; list-style: none;
    display: flex; align-items: center; justify-content: space-between; gap: 12px;
    transition: color .2s;
  }
  .faq-item summary::-webkit-details-marker { display: none; }
  .faq-item summary::after {
    content: '+';
    font-size: 22px; font-weight: 300; color: var(--accent-dark);
    flex-shrink: 0; transition: transform .2s;
  }
  .faq-item[open] summary::after { transform: rotate(45deg); }
  .faq-item[open] summary { color: var(--vida-primary); }
  .faq-answer {
    padding: 0 22px 18px;
    font-size: 14px; color: var(--gray-600); line-height: 1.65;
  }

  /* ========== CTA FINAL ========== */
  .cta-final {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    color: var(--white);
    padding: 80px 0;
    text-align: center;
    position: relative; overflow: hidden;
  }
  .cta-final::before {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse at 50% 50%, rgba(225,29,72,0.18), transparent 65%);
    pointer-events: none;
  }
  .cta-final h2 {
    font-family: var(--font-display);
    font-size: clamp(2rem, 4vw, 2.8rem);
    font-weight: 900; letter-spacing: -1px;
    margin-bottom: 14px; position: relative;
  }
  .cta-final h2 em { font-style: italic; color: var(--vida-light); font-weight: 500; }
  .cta-final p { font-size: 17px; color: rgba(255,255,255,0.82); margin-bottom: 30px; position: relative; }
  .cta-final-actions { display: inline-flex; gap: 14px; flex-wrap: wrap; justify-content: center; position: relative; }

  /* ========== FOOTER ========== */
  footer {
    background: var(--primary-dark);
    color: rgba(255,255,255,0.75);
    padding: 60px 0 0;
  }
  .footer-grid {
    display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 40px;
    padding-bottom: 50px;
    border-bottom: 1px solid rgba(255,255,255,0.08);
  }
  .footer-logo {
    font-family: var(--font-display); font-size: 24px; font-weight: 900;
    color: var(--white); margin-bottom: 12px;
  }
  .footer-logo span { color: var(--accent); }
  .footer-about p { font-size: 13px; line-height: 1.65; margin-bottom: 16px; }
  .footer-contact-item {
    display: flex; align-items: center; gap: 8px;
    font-size: 13px; margin-bottom: 8px;
  }
  .footer-contact-item svg { color: var(--accent); flex-shrink: 0; }
  .footer-contact-item a { color: rgba(255,255,255,0.85); }
  footer h5 { color: var(--white); font-size: 13px; font-weight: 800; letter-spacing: 1px; text-transform: uppercase; margin-bottom: 14px; }
  footer ul { list-style: none; }
  footer ul li { margin-bottom: 8px; }
  footer ul li a { font-size: 13px; color: rgba(255,255,255,0.65); transition: color .2s; }
  footer ul li a:hover { color: var(--accent); }
  .footer-bottom {
    display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;
    padding: 20px 0;
    font-size: 12px; color: rgba(255,255,255,0.4);
  }

  /* ========== WHATSAPP FLOAT ========== */
  .whatsapp-float {
    position: fixed; bottom: 24px; right: 24px; z-index: 999;
    width: 58px; height: 58px; border-radius: 50%;
    background: #25D366; color: var(--white);
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 6px 24px rgba(37,211,102,0.45);
    transition: transform .2s, box-shadow .2s;
  }
  .whatsapp-float:hover { transform: translateY(-3px); box-shadow: 0 10px 30px rgba(37,211,102,0.55); }

  @media (max-width: 900px) {
    .benefits-grid { grid-template-columns: repeat(2, 1fr); }
    .form-row { grid-template-columns: 1fr; }
    .form-wrap { padding: 28px 22px; }
    .footer-grid { grid-template-columns: 1fr 1fr; }
    .sub-hero .container { padding-top: 60px; padding-bottom: 70px; }
  }
  @media (max-width: 520px) {
    .footer-grid { grid-template-columns: 1fr; }
    .footer-bottom { flex-direction: column; text-align: center; }
    .sub-hero-ctas { flex-direction: column; align-items: stretch; }
    .cta-final-actions { flex-direction: column; align-items: stretch; }
  }
</style>
</head>
<body class="product-vida">

<!-- NAV -->
<nav class="nav">
  <div class="nav-inner">
    <a href="/" class="nav-logo">Seguros<span>Lux</span></a>
    <ul class="nav-menu">
      <li><a href="#inicio">Inicio</a></li>
      <li><a href="#beneficios">Beneficios</a></li>
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
      <a href="#agendar" class="nav-cta">
        <span class="hide-sm">Agendar</span>
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
    <span class="current">Seguro de Vida</span>
  </div>
</div>

<!-- HERO -->
<section class="sub-hero" id="inicio">
  <div class="sub-hero-img-wrap">
    <img
      src="/images/banner_vida.webp"
      alt="Familia protegida con seguro de vida"
      width="1440" height="600"
      fetchpriority="high"
      decoding="async"
    >
  </div>
  <div class="container">
    <div class="sub-hero-eyebrow">Seguro de Vida</div>
    <h1>Protegemos lo que <em>más quieres</em></h1>
    <p class="lead">Tu familia merece seguridad sin importar lo que pase. Coberturas flexibles, asesoría real y un equipo que está contigo cuando más lo necesitas.</p>
    <div class="sub-hero-ctas">
      <a href="#agendar" class="btn btn-primary">
        Agendar Asesoría Gratis
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
      </a>
      <a href="tel:+525541232311" class="btn btn-ghost">55 4123 2311</a>
    </div>
  </div>
</section>

<!-- ¿POR QUÉ UN SEGURO DE VIDA? -->
<section style="padding:70px 0;background:var(--white);border-bottom:1px solid var(--gray-100);">
  <div class="container">
    <div class="section-head">
      <div class="section-eyebrow">La razón más importante</div>
      <h2>¿Por qué tener un <em>seguro de vida?</em></h2>
      <p>No se trata de pensar en lo peor. Se trata de ser responsable con quienes dependen de ti.</p>
    </div>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:24px;max-width:960px;margin:0 auto;">
      <div style="background:var(--gray-50);border-radius:var(--radius-xl);padding:32px 26px;border-left:4px solid var(--vida-primary);">
        <div style="font-size:36px;margin-bottom:14px;">🏠</div>
        <h4 style="font-family:var(--font-display);font-size:18px;font-weight:800;color:var(--primary);margin-bottom:8px;">Protege el patrimonio</h4>
        <p style="font-size:14px;color:var(--gray-600);line-height:1.6;">Si algo te llegara a pasar, tu familia podría mantener su estilo de vida, cubrir la hipoteca y los gastos del hogar sin depender de nadie.</p>
      </div>
      <div style="background:var(--gray-50);border-radius:var(--radius-xl);padding:32px 26px;border-left:4px solid var(--vida-primary);">
        <div style="font-size:36px;margin-bottom:14px;">🎓</div>
        <h4 style="font-family:var(--font-display);font-size:18px;font-weight:800;color:var(--primary);margin-bottom:8px;">Garantiza la educación</h4>
        <p style="font-size:14px;color:var(--gray-600);line-height:1.6;">El futuro de tus hijos no depende de lo que pase contigo. Una póliza bien elegida garantiza que sus estudios sigan adelante pase lo que pase.</p>
      </div>
      <div style="background:var(--gray-50);border-radius:var(--radius-xl);padding:32px 26px;border-left:4px solid var(--vida-primary);">
        <div style="font-size:36px;margin-bottom:14px;">💰</div>
        <h4 style="font-family:var(--font-display);font-size:18px;font-weight:800;color:var(--primary);margin-bottom:8px;">Construye ahorro</h4>
        <p style="font-size:14px;color:var(--gray-600);line-height:1.6;">Muchos planes de vida incluyen un componente de ahorro o inversión a mediano y largo plazo. Proteges a tu familia y generas capital al mismo tiempo.</p>
      </div>
    </div>
  </div>
</section>

<!-- BENEFICIOS -->
<section class="benefits-section">
  <div class="container">
    <div class="section-head">
      <div class="section-eyebrow">Por qué elegirnos</div>
      <h2>Beneficios del <em>seguro de vida</em></h2>
      <p>Tranquilidad para ti y protección para tu familia.</p>
    </div>
    <div class="benefits-grid">
      <div class="benefit-card reveal">
        <div class="icon">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        </div>
        <h4>Protección familiar</h4>
        <p>Tu familia queda protegida económicamente.</p>
      </div>
      <div class="benefit-card reveal">
        <div class="icon">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <h4>Suma flexible</h4>
        <p>Tú eliges el monto de protección que necesitas.</p>
      </div>
      <div class="benefit-card reveal">
        <div class="icon">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        </div>
        <h4>Beneficios fiscales</h4>
        <p>Deduce tus primas del impuesto sobre la renta.</p>
      </div>
      <div class="benefit-card reveal">
        <div class="icon">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        </div>
        <h4>Proceso simple</h4>
        <p>Contratación sencilla con asesoría personalizada.</p>
      </div>
    </div>
  </div>
</section>

<!-- COMPARA PLANES -->
<section class="plans-section">
  <div class="container">
    <div class="section-head">
      <div class="section-eyebrow">Objetivos de vida</div>
      <h2>Planes para cada <em>etapa de tu vida</em></h2>
      <p>Elige el objetivo que mejor describe lo que buscas. Tu asesor te orientará en el plan ideal según tu perfil.</p>
    </div>
    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:22px;max-width:960px;margin:0 auto 32px;">
      <!-- Retiro -->
      <div style="background:var(--white);border:2px solid var(--gray-200);border-radius:var(--radius-xl);padding:32px 28px;transition:transform .3s,box-shadow .3s;" onmouseover="this.style.transform='translateY(-4px)';this.style.boxShadow='0 20px 50px rgba(0,0,0,0.1)';" onmouseout="this.style.transform='';this.style.boxShadow='';">
        <div style="font-size:40px;margin-bottom:14px;">🏖️</div>
        <h3 style="font-family:var(--font-display);font-size:20px;font-weight:800;color:var(--primary);margin-bottom:8px;">Plan de Retiro</h3>
        <p style="font-size:14px;color:var(--gray-600);line-height:1.6;margin-bottom:18px;">Asegura tu tranquilidad financiera para cuando dejes de trabajar. Combina protección de vida con un fondo de retiro que crece con el tiempo.</p>
        <ul style="list-style:none;margin-bottom:22px;">
          <li style="padding:5px 0;font-size:14px;color:var(--primary);display:flex;gap:10px;align-items:flex-start;"><span style="color:var(--vida-primary);font-weight:700;">✓</span> Suma asegurada en caso de fallecimiento</li>
          <li style="padding:5px 0;font-size:14px;color:var(--primary);display:flex;gap:10px;align-items:flex-start;"><span style="color:var(--vida-primary);font-weight:700;">✓</span> Ahorro acumulado a largo plazo</li>
          <li style="padding:5px 0;font-size:14px;color:var(--primary);display:flex;gap:10px;align-items:flex-start;"><span style="color:var(--vida-primary);font-weight:700;">✓</span> Deducible fiscal en ISR</li>
        </ul>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
          <a href="#agendar" class="btn btn-primary" style="flex:1;text-align:center;justify-content:center;">Cotizar ahora</a>
          <a href="tel:+525541232311" class="btn" style="flex:1;text-align:center;padding:12px 18px;border:2px solid var(--vida-primary);color:var(--vida-primary);border-radius:999px;font-weight:700;font-size:14px;display:flex;align-items:center;justify-content:center;gap:6px;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
            Llamar
          </a>
        </div>
      </div>

      <!-- Ahorro -->
      <div style="background:var(--white);border:2px solid var(--vida-primary);border-radius:var(--radius-xl);padding:32px 28px;position:relative;transition:transform .3s,box-shadow .3s;" onmouseover="this.style.transform='translateY(-4px)';this.style.boxShadow='0 20px 50px rgba(225,29,72,0.15)';" onmouseout="this.style.transform='';this.style.boxShadow='';">
        <span style="position:absolute;top:-12px;left:28px;background:var(--vida-primary);color:var(--white);font-size:11px;font-weight:800;padding:4px 14px;border-radius:999px;letter-spacing:1px;text-transform:uppercase;">★ Más elegido</span>
        <div style="font-size:40px;margin-bottom:14px;">💎</div>
        <h3 style="font-family:var(--font-display);font-size:20px;font-weight:800;color:var(--primary);margin-bottom:8px;">Plan de Ahorro</h3>
        <p style="font-size:14px;color:var(--gray-600);line-height:1.6;margin-bottom:18px;">El equilibrio perfecto entre proteger a tu familia hoy y construir un patrimonio para el futuro. Ideal para mediano y largo plazo.</p>
        <ul style="list-style:none;margin-bottom:22px;">
          <li style="padding:5px 0;font-size:14px;color:var(--primary);display:flex;gap:10px;align-items:flex-start;"><span style="color:var(--vida-primary);font-weight:700;">✓</span> Cobertura por fallecimiento e invalidez</li>
          <li style="padding:5px 0;font-size:14px;color:var(--primary);display:flex;gap:10px;align-items:flex-start;"><span style="color:var(--vida-primary);font-weight:700;">✓</span> Fondo de ahorro con rendimientos</li>
          <li style="padding:5px 0;font-size:14px;color:var(--primary);display:flex;gap:10px;align-items:flex-start;"><span style="color:var(--vida-primary);font-weight:700;">✓</span> Opción de rescate parcial</li>
        </ul>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
          <a href="#agendar" class="btn btn-primary" style="flex:1;text-align:center;justify-content:center;">Cotizar ahora</a>
          <a href="tel:+525541232311" class="btn" style="flex:1;text-align:center;padding:12px 18px;border:2px solid var(--vida-primary);color:var(--vida-primary);border-radius:999px;font-weight:700;font-size:14px;display:flex;align-items:center;justify-content:center;gap:6px;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
            Llamar
          </a>
        </div>
      </div>

      <!-- Educativo -->
      <div style="background:var(--white);border:2px solid var(--gray-200);border-radius:var(--radius-xl);padding:32px 28px;transition:transform .3s,box-shadow .3s;" onmouseover="this.style.transform='translateY(-4px)';this.style.boxShadow='0 20px 50px rgba(0,0,0,0.1)';" onmouseout="this.style.transform='';this.style.boxShadow='';">
        <div style="font-size:40px;margin-bottom:14px;">🎓</div>
        <h3 style="font-family:var(--font-display);font-size:20px;font-weight:800;color:var(--primary);margin-bottom:8px;">Plan Educativo</h3>
        <p style="font-size:14px;color:var(--gray-600);line-height:1.6;margin-bottom:18px;">Garantiza que la educación de tus hijos no se detenga pase lo que pase. El plan crece junto con ellos.</p>
        <ul style="list-style:none;margin-bottom:22px;">
          <li style="padding:5px 0;font-size:14px;color:var(--primary);display:flex;gap:10px;align-items:flex-start;"><span style="color:var(--vida-primary);font-weight:700;">✓</span> Capital garantizado a la fecha de ingreso a universidad</li>
          <li style="padding:5px 0;font-size:14px;color:var(--primary);display:flex;gap:10px;align-items:flex-start;"><span style="color:var(--vida-primary);font-weight:700;">✓</span> El plan no se cancela si el padre fallece</li>
          <li style="padding:5px 0;font-size:14px;color:var(--primary);display:flex;gap:10px;align-items:flex-start;"><span style="color:var(--vida-primary);font-weight:700;">✓</span> Exención de primas por invalidez</li>
        </ul>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
          <a href="#agendar" class="btn btn-primary" style="flex:1;text-align:center;justify-content:center;">Cotizar ahora</a>
          <a href="tel:+525541232311" class="btn" style="flex:1;text-align:center;padding:12px 18px;border:2px solid var(--vida-primary);color:var(--vida-primary);border-radius:999px;font-weight:700;font-size:14px;display:flex;align-items:center;justify-content:center;gap:6px;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
            Llamar
          </a>
        </div>
      </div>

      <!-- Mediano y largo plazo -->
      <div style="background:var(--white);border:2px solid var(--gray-200);border-radius:var(--radius-xl);padding:32px 28px;transition:transform .3s,box-shadow .3s;" onmouseover="this.style.transform='translateY(-4px)';this.style.boxShadow='0 20px 50px rgba(0,0,0,0.1)';" onmouseout="this.style.transform='';this.style.boxShadow='';">
        <div style="font-size:40px;margin-bottom:14px;">📈</div>
        <h3 style="font-family:var(--font-display);font-size:20px;font-weight:800;color:var(--primary);margin-bottom:8px;">Mediano y Largo Plazo</h3>
        <p style="font-size:14px;color:var(--gray-600);line-height:1.6;margin-bottom:18px;">Para quienes buscan protección combinada con una estrategia de inversión que genere resultados en horizontes de 10, 15 o 20 años.</p>
        <ul style="list-style:none;margin-bottom:22px;">
          <li style="padding:5px 0;font-size:14px;color:var(--primary);display:flex;gap:10px;align-items:flex-start;"><span style="color:var(--vida-primary);font-weight:700;">✓</span> Flexibilidad en plazos y aportaciones</li>
          <li style="padding:5px 0;font-size:14px;color:var(--primary);display:flex;gap:10px;align-items:flex-start;"><span style="color:var(--vida-primary);font-weight:700;">✓</span> Cobertura por fallecimiento todo el tiempo</li>
          <li style="padding:5px 0;font-size:14px;color:var(--primary);display:flex;gap:10px;align-items:flex-start;"><span style="color:var(--vida-primary);font-weight:700;">✓</span> Aportaciones deducibles de ISR</li>
        </ul>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
          <a href="#agendar" class="btn btn-primary" style="flex:1;text-align:center;justify-content:center;">Cotizar ahora</a>
          <a href="tel:+525541232311" class="btn" style="flex:1;text-align:center;padding:12px 18px;border:2px solid var(--vida-primary);color:var(--vida-primary);border-radius:999px;font-weight:700;font-size:14px;display:flex;align-items:center;justify-content:center;gap:6px;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
            Llamar
          </a>
        </div>
      </div>
    </div>

    <p style="text-align:center;font-size:13px;color:var(--gray-400);max-width:680px;margin:0 auto;">La aseguradora determina el tipo de seguro y condiciones específicas según el perfil médico y financiero de cada solicitante. Aplican restricciones y condiciones según aseguradora. Tu asesor Lux te guiará en el proceso sin costo adicional.</p>
  </div>
</section>

<!-- FORMULARIO ASESORÍA -->
<section class="form-section" id="agendar">
  <div class="container">

    <div class="section-head">
      <div class="section-eyebrow">Agenda tu cita</div>
      <h2>Solicita tu <em>Asesoría Gratuita</em></h2>
      <p>
        Un especialista en seguros de vida te contactará para orientarte sobre la mejor opción para ti y tu familia.
      </p>
    </div>

    <div class="form-wrap">
      <div style="background:linear-gradient(135deg,var(--vida-primary),var(--vida-dark));color:var(--white);border-radius:var(--radius-md);padding:18px 22px;margin-bottom:24px;font-size:14px;line-height:1.55;">
        <strong style="display:block;font-size:15px;margin-bottom:4px;">💡 ¿Cómo funciona la elección del plan?</strong>
        La aseguradora analiza tu perfil —edad, salud, objetivos y situación financiera— para recomendarte el tipo de seguro que mejor se adapta a ti. Tu asesor Lux te acompaña en todo el proceso y pelea por las mejores condiciones. <strong>Sin costo adicional.</strong>
      </div>
      <form id="formVida">

        <div class="form-row full">
          <div class="form-group">
            <label for="nombre">Nombre completo *</label>
            <input type="text" id="nombre" name="nombre" placeholder="Ej. Juan García López" required>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="correo">Correo electrónico *</label>
            <input type="email" id="correo" name="correo" placeholder="tu@correo.com" required>
          </div>

          <div class="form-group">
            <label for="telefono">Teléfono / WhatsApp *</label>
            <input type="tel" id="telefono" name="telefono" placeholder="+52 55 1234 5678" maxlength="10" required>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="fecha">Fecha preferida de contacto *</label>
            <input type="date" id="fecha" name="fecha_preferida" required>
          </div>
        </div>

        <div class="form-row full">
          <div class="form-group">
            <label for="comentarios">Comentarios adicionales</label>
            <textarea id="comentarios" name="comentarios" placeholder="Cuéntanos más sobre lo que buscas..."></textarea>
          </div>
        </div>

        <button type="submit" class="btn btn-primary form-submit">
          Cotizar ahora
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round">
            <path d="M5 12h14M13 5l7 7-7 7"/>
          </svg>
        </button>
      </form>
    </div>

  </div>
</section>

<!-- FAQ -->
<section class="faq-section">
  <div class="container">
    <div class="section-head">
      <div class="section-eyebrow">Preguntas frecuentes</div>
      <h2>Dudas sobre <em>Seguro de Vida</em></h2>
    </div>
    <div class="faq-wrap">
      <details class="faq-item">
        <summary>¿A qué edad puedo contratar un seguro de vida?</summary>
        <div class="faq-answer">Desde los 18 hasta los 65 años, dependiendo del plan y la aseguradora. Mientras más joven contrates, menor será tu prima.</div>
      </details>
      <details class="faq-item">
        <summary>¿Necesito examen médico para contratar?</summary>
        <div class="faq-answer">Depende del monto de la suma asegurada. Para montos menores generalmente solo se requiere un cuestionario médico. Para sumas mayores podría requerirse un examen.</div>
      </details>
      <details class="faq-item">
        <summary>¿Quién puede ser mi beneficiario?</summary>
        <div class="faq-answer">Cualquier persona: familiares, amigos o instituciones. Puedes cambiar a tus beneficiarios en cualquier momento.</div>
      </details>
      <details class="faq-item">
        <summary>¿Puedo cancelar mi seguro?</summary>
        <div class="faq-answer">Sí, en cualquier momento. Algunos planes con componente de ahorro permiten recuperar parte del valor acumulado.</div>
      </details>
    </div>
  </div>
</section>

<!-- CTA FINAL -->
<section class="cta-final" id="contacto">
  <div class="container">
    <h2>Protege a tu <em>familia hoy</em></h2>
    <p>Agenda una asesoría gratuita y conoce las opciones de seguro de vida que tenemos para ti.</p>
    <div class="cta-final-actions">
      <a href="#agendar" class="btn btn-primary">Agendar Asesoría</a>
      <a href="tel:+525541232311" class="btn btn-ghost">Llamar Ahora</a>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer>
  <div class="container">
    <div class="footer-grid">
      <div class="footer-about">
        <div class="footer-logo">Seguros<span>Lux</span></div>
        <p>Protegiendo lo que más importa desde 2010.</p>
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
<script src="../js/vida.js" defer></script>
</body>
</html>