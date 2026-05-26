<?php
header("Content-Security-Policy: script-src 'self' 'unsafe-hashes' 'nonce-SFJGwYCGbqfOpVAETI36Ug==' 'nonce-bCTKJ8jvaMQSTk+mJqNnSQ==' 'sha256-SxvxBwFKi30Ha/zZWTRb9NJg74REtc1o54/FaxCyQxg=' 'sha256-nMfu92wtOkaru2r7PqzP6B80M8SdzaU6H3INqlYVRqo=' 'sha256-Gwl3zb9M02Q7E/PnJHqlQngViTYyz175pzuwLgN8EcA=' 'sha256-zU92tUfcgNOSw/JQ9H7HZI9KwEgnmnZUfMzwuz+mN+s=' 'sha256-OXiyEbUgkkJphSJ8KbSgayxuH+bsHW21a0LrN6JLpOM=' https://cdn.jsdelivr.net https://blog.seguroslux.mx https://www.googletagmanager.com https://googleads.g.doubleclick.net https://www.googleadservices.com;");
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Condiciones Generales | Seguros Lux</title>
<meta name="description" content="Condiciones generales del servicio de intermediación de seguros de Seguros Lux. Conoce cómo funciona nuestra asesoría y qué puedes esperar de nosotros." />
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Google+Sans:ital,opsz,wght@0,17..18,400..700;1,17..18,400..700&family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
<link rel="icon" type="image/png" sizes="32x32" href="/images/logo.png">
<link rel="stylesheet" href="css/landing.css">
<style>
  .legal-hero {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    color: var(--white); padding: 60px 0 70px;
    position: relative; overflow: hidden;
  }
  .legal-hero::before {
    content: ''; position: absolute; inset: 0;
    background-image:
      linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
      linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
    background-size: 48px 48px;
    mask-image: radial-gradient(ellipse at center, black 40%, transparent 80%);
    pointer-events: none;
  }
  .legal-hero-inner { position: relative; }
  .legal-badge {
    display: inline-flex; align-items: center; gap: 8px;
    background: rgba(243,195,50,0.15); border: 1px solid rgba(243,195,50,0.3);
    color: var(--accent); padding: 6px 14px; border-radius: var(--radius-full);
    font-size: 12px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase;
    margin-bottom: 18px;
  }
  .legal-hero h1 { font-family: var(--font-display); font-size: clamp(2rem, 4vw, 3rem); font-weight: 900; letter-spacing: -1px; line-height: 1.1; margin-bottom: 14px; }
  .legal-hero p { font-size: 16px; color: rgba(255,255,255,0.80); max-width: 640px; }
  .legal-meta { display: flex; gap: 24px; flex-wrap: wrap; margin-top: 22px; font-size: 13px; color: rgba(255,255,255,0.60); }
  .legal-meta span { display: flex; align-items: center; gap: 6px; }

  .legal-body { padding: 70px 0 90px; background: var(--white); }
  .legal-layout { display: grid; grid-template-columns: 240px 1fr; gap: 60px; align-items: start; }

  .legal-index { position: sticky; top: 100px; background: var(--gray-50); border: 1px solid var(--gray-200); border-radius: var(--radius-lg); padding: 24px 20px; }
  .legal-index h4 { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 1.5px; color: var(--gray-400); margin-bottom: 14px; padding-bottom: 10px; border-bottom: 1px solid var(--gray-200); }
  .legal-index ol { list-style: none; counter-reset: idx; }
  .legal-index li { counter-increment: idx; margin-bottom: 6px; }
  .legal-index li a { font-size: 13px; color: var(--gray-600); font-weight: 600; display: flex; gap: 8px; align-items: baseline; transition: color .2s; padding: 4px 0; }
  .legal-index li a::before { content: counter(idx); font-size: 10px; font-weight: 800; color: var(--accent-dark); min-width: 16px; }
  .legal-index li a:hover { color: var(--accent-dark); }

  .legal-content h2 { font-family: var(--font-display); font-size: 22px; font-weight: 800; color: var(--primary); letter-spacing: -0.3px; margin: 42px 0 14px; padding-top: 16px; border-top: 2px solid var(--gray-100); display: flex; align-items: center; gap: 10px; }
  .legal-content h2:first-child { margin-top: 0; border-top: 0; padding-top: 0; }
  .legal-content h2 .num { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 8px; background: var(--accent); color: var(--primary); font-size: 14px; font-weight: 900; flex-shrink: 0; }
  .legal-content h3 { font-size: 16px; font-weight: 800; color: #fff; margin: 22px 0 8px; }
  .legal-content p { font-size: 15px; color: var(--gray-600); line-height: 1.75; margin-bottom: 14px; }
  .legal-content ul, .legal-content ol { margin: 10px 0 16px 20px; }
  .legal-content li { font-size: 15px; color: var(--gray-600); line-height: 1.65; margin-bottom: 6px; }
  .legal-content a { color: var(--accent-dark); font-weight: 600; border-bottom: 1px solid var(--accent); }
  .legal-content a:hover { color: var(--primary); }
  .legal-highlight { background: var(--gray-50); border-left: 4px solid var(--accent); border-radius: 0 var(--radius-md) var(--radius-md) 0; padding: 18px 22px; margin: 20px 0; }
  .legal-highlight p { margin: 0; font-size: 14px; }
  .legal-highlight strong { color: var(--primary); }

  /* Tabla de obligaciones */
  .obligations-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin: 22px 0; }
  .obligation-card { background: var(--gray-50); border: 1px solid var(--gray-200); border-radius: var(--radius-md); padding: 22px 20px; }
  .obligation-card h4 { font-size: 14px; font-weight: 800; color: var(--primary); margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
  .obligation-card h4 svg { color: var(--accent-dark); }
  .obligation-card ul { list-style: none; margin: 0; }
  .obligation-card li { font-size: 13px; color: var(--gray-600); padding: 5px 0; display: flex; align-items: flex-start; gap: 8px; line-height: 1.5; }
  .obligation-card li::before { content: '✓'; color: var(--success); font-weight: 700; flex-shrink: 0; margin-top: 1px; }

  .legal-contact-box { background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%); color: var(--white); border-radius: var(--radius-xl); padding: 32px 30px; margin-top: 48px; }
  .legal-contact-box h3 { font-size: 18px; font-weight: 800; margin-bottom: 10px; }
  .legal-contact-box p { font-size: 14px; color: rgba(255,255,255,0.80); margin-bottom: 18px; }
  .legal-contact-box .contact-items { display: flex; flex-direction: column; gap: 10px; }
  .legal-contact-box .contact-item { display: flex; align-items: center; gap: 10px; font-size: 14px; color: rgba(255,255,255,0.90); }
  .legal-contact-box .contact-item svg { color: var(--accent); flex-shrink: 0; }

  @media (max-width: 900px) {
    .legal-layout { grid-template-columns: 1fr; }
    .legal-index { position: static; }
    .obligations-grid { grid-template-columns: 1fr; }
  }
</style>
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=AW-18175049842"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'AW-18175049842');
</script>
<!-- Event snippet for Compra conversion page -->
<script>
  gtag('event', 'conversion', {
      'send_to': 'AW-18175049842/PcClCPSkirAcEPKAxdpD',
      'value': 1.0,
      'currency': 'MXN',
      'transaction_id': ''
      // 'new_customer': true /* calculate dynamically, populate with true/false */,
  });
</script>
</head>
<body>

<!-- NAV -->
<nav class="nav">
  <div class="nav-inner">
    <a href="/" class="nav-logo">Seguros<span>Lux</span></a>
    <ul class="nav-menu">
      <li><a href="/">Inicio</a></li>
      <li class="has-submenu">
        <a href="#">Seguros</a>
        <ul class="submenu">
          <li><a href="/autos">Seguro Vehicular</a></li>
          <li><a href="/vida">Seguro de Vida</a></li>
          <li><a href="/gmm">Gastos Médicos Mayores</a></li>
        </ul>
      </li>
      <li class="has-submenu">
        <a href="#">Legal</a>
        <ul class="submenu">
          <li><a href="/aviso-privacidad">Aviso de Privacidad</a></li>
          <li><a href="/terminos">Términos y Condiciones</a></li>
          <li><a href="/condiciones">Condiciones Generales</a></li>
        </ul>
      </li>
      <li><a href="/#contacto">Contacto</a></li>
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

<!-- Breadcrumb -->
<div class="breadcrumb">
  <div class="container">
    <a href="/">Inicio</a>
    <span>›</span>
    <span class="current">Condiciones Generales</span>
  </div>
</div>

<!-- HERO -->
<section class="legal-hero">
  <div class="container">
    <div class="legal-hero-inner">
      <div class="legal-badge">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        Legal
      </div>
      <h1>Condiciones Generales</h1>
      <p>Estas condiciones regulan la relación de servicio entre Seguros Lux y sus clientes. Aquí encontrarás cómo trabajamos, qué puedes esperar de nosotros y cuáles son tus compromisos.</p>
      <div class="legal-meta">
        <span>
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          Última actualización: enero 2026
        </span>
        <span>
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          Tiempo de lectura: ~7 min
        </span>
      </div>
    </div>
  </div>
</section>

<!-- CUERPO -->
<section class="legal-body">
  <div class="container">
    <div class="legal-layout">

      <aside class="legal-index">
        <h4>Contenido</h4>
        <ol>
          <li><a href="#objeto">Objeto del servicio</a></li>
          <li><a href="#intermediacion">Intermediación</a></li>
          <li><a href="#proceso">Proceso de contratación</a></li>
          <li><a href="#obligaciones">Obligaciones</a></li>
          <li><a href="#polizas">Vigencia de pólizas</a></li>
          <li><a href="#siniestros">Gestión de siniestros</a></li>
          <li><a href="#cancelacion">Cancelación</a></li>
          <li><a href="#comisiones">Comisiones</a></li>
          <li><a href="#quejas">Quejas y reclamaciones</a></li>
          <li><a href="#vigencia">Vigencia</a></li>
        </ol>
      </aside>

      <article class="legal-content">

        <div class="legal-highlight">
          <p><strong>Resumen:</strong> Seguros Lux actúa como intermediario autorizado por la CNSF. Su servicio de asesoría es completamente gratuito para el asegurado. El contrato de seguro es siempre entre el asegurado y la aseguradora elegida.</p>
        </div>

        <h2 id="objeto"><span class="num">1</span> Objeto del servicio</h2>
        <p>Las presentes Condiciones Generales regulan la prestación del servicio de intermediación y asesoría en seguros que ofrece <strong>Seguros Lux, S.A. de C.V.</strong> a sus clientes y usuarios.</p>
        <p>Seguros Lux presta los siguientes servicios:</p>
        <ul>
          <li>Asesoría para la selección del seguro más adecuado al perfil y necesidades del cliente</li>
          <li>Comparativa entre productos de distintas aseguradoras autorizadas</li>
          <li>Gestión y seguimiento del proceso de cotización y contratación</li>
          <li>Apoyo durante la vigencia de la póliza: renovaciones, endosos, cancelaciones</li>
          <li>Asistencia y orientación durante el proceso de reclamación de siniestros</li>
        </ul>

        <h2 id="intermediacion"><span class="num">2</span> Naturaleza de la intermediación</h2>
        <p>Seguros Lux opera como <strong>agente intermediario de seguros</strong> debidamente autorizado y registrado ante la Comisión Nacional de Seguros y Fianzas (CNSF), conforme a la Ley sobre el Contrato de Seguro y la Ley de Instituciones de Seguros y de Fianzas.</p>
        <p>En virtud de lo anterior:</p>
        <ul>
          <li>Seguros Lux <strong>no es</strong> una institución de seguros ni emite pólizas a nombre propio</li>
          <li>El contrato de seguro se celebra directamente entre el asegurado y la aseguradora seleccionada</li>
          <li>Las coberturas, exclusiones, deducibles y condiciones de cada póliza son determinadas exclusivamente por la aseguradora</li>
          <li>Seguros Lux no asume responsabilidad por el pago de siniestros, que corresponde íntegramente a la aseguradora</li>
        </ul>

        <h2 id="proceso"><span class="num">3</span> Proceso de contratación</h2>

        <h3>Paso 1 — Solicitud de asesoría</h3>
        <p>El cliente contacta a Seguros Lux a través del sitio web, WhatsApp, teléfono o cualquier otro canal disponible, proporcionando la información necesaria para generar una cotización.</p>

        <h3>Paso 2 — Análisis y cotización</h3>
        <p>Un asesor de Seguros Lux analiza el perfil del cliente y genera comparativas entre las aseguradoras más adecuadas. Las cotizaciones son informativas y no vinculantes.</p>

        <h3>Paso 3 — Selección y solicitud</h3>
        <p>El cliente selecciona el producto de su preferencia. Seguros Lux gestiona la solicitud ante la aseguradora correspondiente, quien realizará su propio proceso de evaluación y aceptación de riesgo.</p>

        <h3>Paso 4 — Emisión de póliza</h3>
        <p>Una vez aprobada la solicitud por la aseguradora y realizado el pago de la prima, la aseguradora emite la póliza. Seguros Lux entrega copia de la póliza al cliente y explica sus condiciones.</p>

        <h3>Paso 5 — Seguimiento postventa</h3>
        <p>Seguros Lux mantiene comunicación con el cliente durante la vigencia de la póliza para gestionar renovaciones, modificaciones o cualquier necesidad que surja.</p>

        <h2 id="obligaciones"><span class="num">4</span> Obligaciones de las partes</h2>

        <div class="obligations-grid">
          <div class="obligation-card">
            <h4>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              Seguros Lux se compromete a:
            </h4>
            <ul>
              <li>Brindarte asesoría objetiva e imparcial</li>
              <li>Presentar opciones de múltiples aseguradoras</li>
              <li>Mantener confidencialidad de tu información</li>
              <li>Gestionar tus trámites con diligencia</li>
              <li>Acompañarte en caso de siniestro</li>
              <li>Notificarte con anticipación las renovaciones</li>
              <li>Responderte en menos de 30 minutos en horario laboral</li>
            </ul>
          </div>
          <div class="obligation-card">
            <h4>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
              El cliente se compromete a:
            </h4>
            <ul>
              <li>Proporcionar información veraz y completa</li>
              <li>Declarar condiciones preexistentes relevantes</li>
              <li>Pagar las primas directamente a la aseguradora</li>
              <li>Notificar cambios en el riesgo asegurado</li>
              <li>Reportar siniestros a la brevedad posible</li>
              <li>Revisar y conservar su póliza</li>
              <li>Actualizar sus datos de contacto si cambian</li>
            </ul>
          </div>
        </div>

        <h2 id="polizas"><span class="num">5</span> Vigencia y renovación de pólizas</h2>
        <p>La vigencia de cada póliza es la establecida en el contrato de seguro emitido por la aseguradora, generalmente de <strong>un año</strong> a partir de la fecha de inicio de cobertura.</p>
        <p>Seguros Lux notificará al cliente con al menos <strong>30 días de anticipación</strong> al vencimiento de su póliza para gestionar su renovación. La no renovación automática dependerá de las condiciones pactadas con la aseguradora.</p>
        <p>Las condiciones y precio de cada renovación pueden variar conforme a las tarifas vigentes de la aseguradora al momento de la renovación.</p>

        <h2 id="siniestros"><span class="num">6</span> Gestión de siniestros</h2>
        <p>En caso de siniestro, Seguros Lux ofrecerá orientación y acompañamiento al cliente durante el proceso de reclamación. Sin embargo, la <strong>resolución y pago del siniestro corresponde exclusivamente a la aseguradora</strong>, conforme a las condiciones de la póliza.</p>

        <h3>¿Cómo reportar un siniestro?</h3>
        <ol>
          <li>Contacta a tu asesor de Seguros Lux por WhatsApp o teléfono inmediatamente</li>
          <li>Tu asesor te orientará sobre los pasos específicos según tu aseguradora y tipo de siniestro</li>
          <li>Reúne la documentación requerida (asesor te indicará cuál)</li>
          <li>Seguros Lux dará seguimiento a tu expediente ante la aseguradora</li>
        </ol>

        <h2 id="cancelacion"><span class="num">7</span> Cancelación de pólizas</h2>
        <p>La cancelación de una póliza deberá solicitarse directamente ante la aseguradora o a través de Seguros Lux. Las condiciones de cancelación, plazos y posibles reembolsos de prima no devengada están regulados por la póliza de cada aseguradora y por la legislación aplicable.</p>
        <p>Seguros Lux gestionará la cancelación a solicitud del cliente sin costo adicional.</p>

        <h2 id="comisiones"><span class="num">8</span> Comisiones y costo del servicio</h2>
        <p>El servicio de asesoría de Seguros Lux es <strong>completamente gratuito para el cliente</strong>. Seguros Lux percibe una comisión de intermediación de las aseguradoras, la cual ya está incorporada en la prima de seguro y no representa un costo adicional para el asegurado.</p>
        <p>Contratar con Seguros Lux tiene el mismo costo que contratar directamente con la aseguradora. La diferencia es que obtienes asesoría personalizada, comparativa de mercado y acompañamiento sin pagar más.</p>

        <h2 id="quejas"><span class="num">9</span> Quejas y reclamaciones</h2>
        <p>Si tienes alguna queja o inconformidad con el servicio de Seguros Lux, puedes dirigirte a:</p>
        <ul>
          <li><strong>Correo:</strong> <a href="mailto:atencion@seguroslux.mx">atencion@seguroslux.mx</a></li>
          <li><strong>WhatsApp / Teléfono:</strong> <a href="tel:+525541232311">55 4123 2311</a></li>
        </ul>
        <p>Atendemos todas las quejas en un plazo máximo de <strong>10 días hábiles</strong>.</p>
        <p>Si la queja es sobre la aseguradora (cobertura, pago de siniestro, etc.), puedes acudir también a la <strong>Comisión Nacional de Seguros y Fianzas (CNSF)</strong> en <a href="https://www.gob.mx/cnsf" target="_blank" rel="noopener">www.gob.mx/cnsf</a>.</p>

        <h2 id="vigencia"><span class="num">10</span> Vigencia de estas condiciones</h2>
        <p>Las presentes Condiciones Generales entran en vigor a partir de su publicación en el sitio web de Seguros Lux y permanecerán vigentes hasta que sean modificadas o sustituidas.</p>
        <p>Seguros Lux podrá modificar estas Condiciones Generales en cualquier momento. Los cambios se notificarán a través del sitio web y, cuando sean significativos, mediante comunicación directa a los clientes registrados.</p>

        <div class="legal-contact-box">
          <h3>¿Tienes dudas sobre nuestro servicio?</h3>
          <p>Estamos aquí para aclarar cualquier punto de estas condiciones. Tu tranquilidad es lo más importante.</p>
          <div class="contact-items">
            <div class="contact-item">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
              <a href="mailto:info@seguroslux.mx" style="color:rgba(255,255,255,0.9)">info@seguroslux.mx</a>
            </div>
            <div class="contact-item">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
              <a href="tel:+525541232311" style="color:rgba(255,255,255,0.9)">55 4123 2311</a>
            </div>
            <div class="contact-item">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347M12 2C6.48 2 2 6.48 2 12c0 1.77.46 3.45 1.27 4.9L2 22l5.25-1.38C8.66 21.5 10.27 22 12 22c5.52 0 10-4.48 10-10S17.52 2 12 2z"/></svg>
              <a href="https://wa.me/525541232311" style="color:rgba(255,255,255,0.9)">WhatsApp: 55 4123 2311</a>
            </div>
          </div>
        </div>

      </article>
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
      <div>©Seguros Lux. Intermediario autorizado por la CNSF. Todos los derechos reservados.</div>
      <div>Powered by <strong style="color:#fff">GrupoIdeas</strong></div>
    </div>
  </div>
</footer>

<a href="https://wa.me/525541232311" class="whatsapp-float" aria-label="WhatsApp">
  <svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347M12 2C6.48 2 2 6.48 2 12c0 1.77.46 3.45 1.27 4.9L2 22l5.25-1.38C8.66 21.5 10.27 22 12 22c5.52 0 10-4.48 10-10S17.52 2 12 2z"/></svg>
</a>

<script src="js/landing.js"></script>
</body>
</html>