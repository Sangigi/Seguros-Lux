<?php
header("Content-Security-Policy: script-src 'self' 'unsafe-hashes' 'nonce-SFJGwYCGbqfOpVAETI36Ug==' 'nonce-bCTKJ8jvaMQSTk+mJqNnSQ==' 'sha256-SxvxBwFKi30Ha/zZWTRb9NJg74REtc1o54/FaxCyQxg=' 'sha256-nMfu92wtOkaru2r7PqzP6B80M8SdzaU6H3INqlYVRqo=' 'sha256-Gwl3zb9M02Q7E/PnJHqlQngViTYyz175pzuwLgN8EcA=' 'sha256-zU92tUfcgNOSw/JQ9H7HZI9KwEgnmnZUfMzwuz+mN+s=' 'sha256-OXiyEbUgkkJphSJ8KbSgayxuH+bsHW21a0LrN6JLpOM=' https://cdn.jsdelivr.net https://blog.seguroslux.mx https://www.googletagmanager.com https://googleads.g.doubleclick.net https://www.googleadservices.com;");
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Aviso de Privacidad | Seguros Lux</title>
<meta name="description" content="Aviso de privacidad de Seguros Lux. Conoce cómo protegemos y tratamos tus datos personales conforme a la LFPDPPP." />
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Google+Sans:ital,opsz,wght@0,17..18,400..700;1,17..18,400..700&family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
<link rel="icon" type="image/png" sizes="32x32" href="/images/logo.png">
<link rel="stylesheet" href="css/landing.css">
<style>
  /* ========== HERO LEGAL ========== */
  .legal-hero {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    color: var(--white);
    padding: 60px 0 70px;
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
  .legal-hero h1 {
    font-family: var(--font-display);
    font-size: clamp(2rem, 4vw, 3rem);
    font-weight: 900; letter-spacing: -1px; line-height: 1.1;
    margin-bottom: 14px;
  }
  .legal-hero p {
    font-size: 16px; color: rgba(255,255,255,0.80);
    max-width: 640px;
  }
  .legal-meta {
    display: flex; gap: 24px; flex-wrap: wrap;
    margin-top: 22px; font-size: 13px; color: rgba(255,255,255,0.60);
  }
  .legal-meta span { display: flex; align-items: center; gap: 6px; }

  /* ========== CONTENIDO LEGAL ========== */
  .legal-body { padding: 70px 0 90px; background: var(--white); }
  .legal-layout {
    display: grid; grid-template-columns: 240px 1fr; gap: 60px;
    align-items: start;
  }
  /* Índice lateral */
  .legal-index {
    position: sticky; top: 100px;
    background: var(--gray-50);
    border: 1px solid var(--gray-200);
    border-radius: var(--radius-lg);
    padding: 24px 20px;
  }
  .legal-index h4 {
    font-size: 11px; font-weight: 800; text-transform: uppercase;
    letter-spacing: 1.5px; color: var(--gray-400);
    margin-bottom: 14px; padding-bottom: 10px;
    border-bottom: 1px solid var(--gray-200);
  }
  .legal-index ol { list-style: none; counter-reset: idx; }
  .legal-index li { counter-increment: idx; margin-bottom: 6px; }
  .legal-index li a {
    font-size: 13px; color: var(--gray-600); font-weight: 600;
    display: flex; gap: 8px; align-items: baseline;
    transition: color .2s; padding: 4px 0;
  }
  .legal-index li a::before {
    content: counter(idx);
    font-size: 10px; font-weight: 800; color: var(--accent-dark);
    min-width: 16px;
  }
  .legal-index li a:hover { color: var(--accent-dark); }

  /* Artículo principal */
  .legal-content h2 {
    font-family: var(--font-display);
    font-size: 22px; font-weight: 800; color: var(--primary);
    letter-spacing: -0.3px; margin: 42px 0 14px;
    padding-top: 16px;
    border-top: 2px solid var(--gray-100);
    display: flex; align-items: center; gap: 10px;
  }
  .legal-content h2:first-child { margin-top: 0; border-top: 0; padding-top: 0; }
  .legal-content h2 .num {
    display: inline-flex; align-items: center; justify-content: center;
    width: 32px; height: 32px; border-radius: 8px;
    background: var(--accent); color: var(--primary);
    font-size: 14px; font-weight: 900; flex-shrink: 0;
  }
  .legal-content h3 {
    font-size: 16px; font-weight: 800; color: #fff;
    margin: 22px 0 8px;
  }
  .legal-content p {
    font-size: 15px; color: var(--gray-600); line-height: 1.75;
    margin-bottom: 14px;
  }
  .legal-content ul, .legal-content ol {
    margin: 10px 0 16px 20px;
  }
  .legal-content li {
    font-size: 15px; color: var(--gray-600); line-height: 1.65;
    margin-bottom: 6px;
  }
  .legal-content a { color: var(--accent-dark); font-weight: 600; border-bottom: 1px solid var(--accent); }
  .legal-content a:hover { color: var(--primary); }
  .legal-highlight {
    background: var(--gray-50); border-left: 4px solid var(--accent);
    border-radius: 0 var(--radius-md) var(--radius-md) 0;
    padding: 18px 22px; margin: 20px 0;
  }
  .legal-highlight p { margin: 0; font-size: 14px; }
  .legal-highlight strong { color: var(--primary); }
  .legal-contact-box {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    color: var(--white); border-radius: var(--radius-xl);
    padding: 32px 30px; margin-top: 48px;
  }
  .legal-contact-box h3 { font-size: 18px; font-weight: 800; margin-bottom: 10px; }
  .legal-contact-box p { font-size: 14px; color: rgba(255,255,255,0.80); margin-bottom: 18px; }
  .legal-contact-box .contact-items { display: flex; flex-direction: column; gap: 10px; }
  .legal-contact-box .contact-item {
    display: flex; align-items: center; gap: 10px;
    font-size: 14px; color: rgba(255,255,255,0.90);
  }
  .legal-contact-box .contact-item svg { color: var(--accent); flex-shrink: 0; }

  @media (max-width: 900px) {
    .legal-layout { grid-template-columns: 1fr; }
    .legal-index { position: static; }
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
    <span class="current">Aviso de Privacidad</span>
  </div>
</div>

<!-- HERO -->
<section class="legal-hero">
  <div class="container">
    <div class="legal-hero-inner">
      <div class="legal-badge">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        Legal
      </div>
      <h1>Aviso de Privacidad</h1>
      <p>En Seguros Lux protegemos tus datos personales con absoluta seriedad, conforme a la Ley Federal de Protección de Datos Personales en Posesión de los Particulares.</p>
      <div class="legal-meta">
        <span>
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          Última actualización: enero 2026
        </span>
        <span>
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          Tiempo de lectura: ~5 min
        </span>
      </div>
    </div>
  </div>
</section>

<!-- CUERPO LEGAL -->
<section class="legal-body">
  <div class="container">
    <div class="legal-layout">

      <!-- Índice -->
      <aside class="legal-index">
        <h4>Contenido</h4>
        <ol>
          <li><a href="#responsable">Responsable del tratamiento</a></li>
          <li><a href="#datos">Datos que recabamos</a></li>
          <li><a href="#finalidades">Finalidades del tratamiento</a></li>
          <li><a href="#transferencias">Transferencias</a></li>
          <li><a href="#derechos">Derechos ARCO</a></li>
          <li><a href="#cookies">Cookies y tecnologías</a></li>
          <li><a href="#cambios">Cambios al aviso</a></li>
          <li><a href="#contacto-legal">Contacto</a></li>
        </ol>
      </aside>

      <!-- Contenido -->
      <article class="legal-content">

        <div class="legal-highlight">
          <p><strong>Resumen:</strong> Recabamos tus datos para brindarte asesoría en seguros y darte seguimiento. No los vendemos. Puedes ejercer tus derechos ARCO en cualquier momento escribiendo a <a href="mailto:privacidad@seguroslux.mx">privacidad@seguroslux.mx</a>.</p>
        </div>

        <h2 id="responsable"><span class="num">1</span> Responsable del tratamiento</h2>
        <p><strong>Seguros Lux, S.A. de C.V.</strong> (en adelante "Seguros Lux"), con domicilio en Ciudad de México, México, es el responsable del tratamiento de los datos personales que nos proporciones.</p>
        <p>Para cualquier consulta sobre este Aviso de Privacidad puedes contactarnos en:</p>
        <ul>
          <li>Correo electrónico: <a href="mailto:privacidad@seguroslux.mx">privacidad@seguroslux.mx</a></li>
          <li>Teléfono: <a href="tel:+525541232311">55 4123 2311</a></li>
          <li>WhatsApp: <a href="https://wa.me/525541232311">55 4123 2311</a></li>
        </ul>

        <h2 id="datos"><span class="num">2</span> Datos que recabamos</h2>
        <p>Para las finalidades señaladas en este Aviso, podemos recabar las siguientes categorías de datos personales:</p>

        <h3>Datos de identificación y contacto</h3>
        <ul>
          <li>Nombre completo</li>
          <li>Correo electrónico</li>
          <li>Número de teléfono / WhatsApp</li>
          <li>Fecha de nacimiento</li>
          <li>Código postal y estado de residencia</li>
        </ul>

        <h3>Datos para cotización de seguros</h3>
        <ul>
          <li>Tipo de vehículo (marca, modelo, año) para seguros vehiculares</li>
          <li>Edad y condición de salud general para GMM</li>
          <li>Datos de beneficiarios para seguros de vida</li>
          <li>Suma asegurada requerida</li>
        </ul>

        <h3>Datos de navegación</h3>
        <ul>
          <li>Dirección IP</li>
          <li>Páginas visitadas y tiempo de sesión</li>
          <li>Tipo de navegador y dispositivo</li>
          <li>Fuente de referencia (campaña de marketing)</li>
        </ul>

        <p><strong>No recabamos datos sensibles</strong> en el sentido del artículo 3, fracción VI de la LFPDPPP, salvo la información de salud estrictamente necesaria para cotizar seguros de Gastos Médicos Mayores, la cual se trata con especial cuidado y solo con tu consentimiento expreso.</p>

        <h2 id="finalidades"><span class="num">3</span> Finalidades del tratamiento</h2>

        <h3>Finalidades primarias (necesarias para la relación contractual)</h3>
        <ul>
          <li>Brindarte asesoría personalizada en seguros vehiculares, de vida y gastos médicos mayores</li>
          <li>Generar cotizaciones y comparativas entre aseguradoras</li>
          <li>Formalizar la contratación de pólizas de seguro</li>
          <li>Darte seguimiento postventa y gestionar renovaciones</li>
          <li>Atender reclamaciones y siniestros</li>
          <li>Cumplir obligaciones legales y regulatorias ante la CNSF</li>
        </ul>

        <h3>Finalidades secundarias (puedes negarte)</h3>
        <ul>
          <li>Enviarte información sobre nuevos productos y servicios de seguros</li>
          <li>Compartirte contenido educativo sobre seguros (blog, tips, guías)</li>
          <li>Invitarte a eventos, webinars o campañas especiales</li>
          <li>Realizar encuestas de satisfacción</li>
        </ul>

        <p>Si no deseas que tus datos sean tratados para estas finalidades secundarias, puedes manifestarlo en cualquier momento escribiendo a <a href="mailto:privacidad@seguroslux.mx">privacidad@seguroslux.mx</a>. La negativa no afectará la prestación de los servicios principales.</p>

        <h2 id="transferencias"><span class="num">4</span> Transferencias de datos</h2>
        <p>Seguros Lux puede transferir tus datos personales a las siguientes categorías de receptores:</p>
        <ul>
          <li><strong>Aseguradoras autorizadas por la CNSF:</strong> para generar cotizaciones y emitir pólizas (GNP, AXA, Qualitas, Mapfre, Insignia, HDI, entre otras). Esta transferencia es necesaria para la relación contractual.</li>
          <li><strong>Proveedores de tecnología:</strong> plataformas de CRM, email marketing y análisis web que actúan como encargados del tratamiento bajo acuerdos de confidencialidad.</li>
          <li><strong>Autoridades competentes:</strong> cuando sea requerido por ley, resolución judicial o por la CNSF.</li>
        </ul>
        <p>En ningún caso vendemos, rentamos ni cedemos tus datos a terceros con fines comerciales propios.</p>

        <h2 id="derechos"><span class="num">5</span> Derechos ARCO</h2>
        <p>Tienes derecho a <strong>Acceder, Rectificar, Cancelar u Oponerte (ARCO)</strong> al tratamiento de tus datos personales, así como a revocar el consentimiento que hayas otorgado.</p>

        <h3>¿Cómo ejercerlos?</h3>
        <p>Envía una solicitud a <a href="mailto:privacidad@seguroslux.mx">privacidad@seguroslux.mx</a> indicando:</p>
        <ol>
          <li>Nombre completo y correo electrónico registrado</li>
          <li>Derecho que deseas ejercer (Acceso, Rectificación, Cancelación u Oposición)</li>
          <li>Descripción clara del dato o tratamiento al que te refieres</li>
          <li>Copia de identificación oficial para acreditar tu identidad</li>
        </ol>
        <p>La respuesta se emitirá <strong>una vez recibida</strong> la solicitud completa, conforme a los plazos aplicables.</p>

        <h2 id="cookies"><span class="num">6</span> Cookies y tecnologías de rastreo</h2>
        <p>Nuestro sitio web utiliza cookies y tecnologías similares para mejorar la experiencia de navegación. Las cookies que utilizamos son:</p>
        <ul>
          <li><strong>Esenciales:</strong> necesarias para el funcionamiento del sitio (sesión, seguridad). No pueden desactivarse.</li>
          <li><strong>Analíticas:</strong> Google Analytics, para entender cómo se usa el sitio (páginas visitadas, tiempos de sesión). Anónimas y agregadas.</li>
          <li><strong>Publicitarias:</strong> Google Ads, Meta Pixel, para medir la efectividad de campañas. Puedes desactivarlas en la configuración de tu navegador.</li>
        </ul>
        <p>Puedes desactivar las cookies no esenciales en cualquier momento desde las preferencias de tu navegador o en nuestra herramienta de gestión de cookies.</p>

        <h2 id="cambios"><span class="num">7</span> Cambios a este Aviso</h2>
        <p>Seguros Lux se reserva el derecho de actualizar este Aviso de Privacidad en cualquier momento. Cualquier modificación será publicada en esta página con la fecha de actualización correspondiente. Te recomendamos revisarla periódicamente.</p>
        <p>Si los cambios son significativos y afectan el tratamiento de tus datos, te notificaremos por correo electrónico al que tenemos registrado.</p>

        <h2 id="contacto-legal"><span class="num">8</span> Contacto</h2>
        <p>Para cualquier duda, solicitud o queja relacionada con la privacidad de tus datos, contáctanos a través de cualquiera de estos medios:</p>

        <div class="legal-contact-box">
          <h3>Seguros Lux — Área de Privacidad</h3>
          <p>Estamos disponibles para atender cualquier duda sobre el tratamiento de tus datos personales.</p>
          <div class="contact-items">
            <div class="contact-item">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
              <a href="mailto:privacidad@seguroslux.mx" style="color:rgba(255,255,255,0.9)">privacidad@seguroslux.mx</a>
            </div>
            <div class="contact-item">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
              <a href="tel:+525541232311" style="color:rgba(255,255,255,0.9)">55 4123 2311</a>
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