<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Términos y Condiciones | Seguros Lux</title>
<meta name="description" content="Términos y condiciones de uso del sitio y los servicios de Seguros Lux, intermediario autorizado por la CNSF." />
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Google+Sans:ital,opsz,wght@0,17..18,400..700;1,17..18,400..700&family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/landing.css">
<style>
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
  .legal-hero p { font-size: 16px; color: rgba(255,255,255,0.80); max-width: 640px; }
  .legal-meta { display: flex; gap: 24px; flex-wrap: wrap; margin-top: 22px; font-size: 13px; color: rgba(255,255,255,0.60); }
  .legal-meta span { display: flex; align-items: center; gap: 6px; }

  .legal-body { padding: 70px 0 90px; background: var(--white); }
  .legal-layout { display: grid; grid-template-columns: 240px 1fr; gap: 60px; align-items: start; }

  .legal-index {
    position: sticky; top: 100px;
    background: var(--gray-50); border: 1px solid var(--gray-200);
    border-radius: var(--radius-lg); padding: 24px 20px;
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
  .legal-index li a::before { content: counter(idx); font-size: 10px; font-weight: 800; color: var(--accent-dark); min-width: 16px; }
  .legal-index li a:hover { color: var(--accent-dark); }

  .legal-content h2 {
    font-family: var(--font-display);
    font-size: 22px; font-weight: 800; color: var(--primary);
    letter-spacing: -0.3px; margin: 42px 0 14px;
    padding-top: 16px; border-top: 2px solid var(--gray-100);
    display: flex; align-items: center; gap: 10px;
  }
  .legal-content h2:first-child { margin-top: 0; border-top: 0; padding-top: 0; }
  .legal-content h2 .num {
    display: inline-flex; align-items: center; justify-content: center;
    width: 32px; height: 32px; border-radius: 8px;
    background: var(--accent); color: var(--primary);
    font-size: 14px; font-weight: 900; flex-shrink: 0;
  }
  .legal-content h3 { font-size: 16px; font-weight: 800; color: #fff; margin: 22px 0 8px; }
  .legal-content p { font-size: 15px; color: var(--gray-600); line-height: 1.75; margin-bottom: 14px; }
  .legal-content ul, .legal-content ol { margin: 10px 0 16px 20px; }
  .legal-content li { font-size: 15px; color: var(--gray-600); line-height: 1.65; margin-bottom: 6px; }
  .legal-content a { color: var(--accent-dark); font-weight: 600; border-bottom: 1px solid var(--accent); }
  .legal-content a:hover { color: var(--primary); }
  .legal-highlight {
    background: var(--gray-50); border-left: 4px solid var(--accent);
    border-radius: 0 var(--radius-md) var(--radius-md) 0;
    padding: 18px 22px; margin: 20px 0;
  }
  .legal-highlight p { margin: 0; font-size: 14px; }
  .legal-highlight strong { color: var(--primary); }
  .legal-warning {
    background: #fff7ed; border-left: 4px solid #f59e0b;
    border-radius: 0 var(--radius-md) var(--radius-md) 0;
    padding: 18px 22px; margin: 20px 0;
  }
  .legal-warning p { margin: 0; font-size: 14px; color: #92400e; }
  .legal-contact-box {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    color: var(--white); border-radius: var(--radius-xl);
    padding: 32px 30px; margin-top: 48px;
  }
  .legal-contact-box h3 { font-size: 18px; font-weight: 800; margin-bottom: 10px; }
  .legal-contact-box p { font-size: 14px; color: rgba(255,255,255,0.80); margin-bottom: 18px; }
  .legal-contact-box .contact-items { display: flex; flex-direction: column; gap: 10px; }
  .legal-contact-box .contact-item { display: flex; align-items: center; gap: 10px; font-size: 14px; color: rgba(255,255,255,0.90); }
  .legal-contact-box .contact-item svg { color: var(--accent); flex-shrink: 0; }

  @media (max-width: 900px) {
    .legal-layout { grid-template-columns: 1fr; }
    .legal-index { position: static; }
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
    <span class="current">Términos y Condiciones</span>
  </div>
</div>

<!-- HERO -->
<section class="legal-hero">
  <div class="container">
    <div class="legal-hero-inner">
      <div class="legal-badge">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        Legal
      </div>
      <h1>Términos y Condiciones</h1>
      <p>Al usar nuestro sitio web o contratar nuestros servicios de asesoría en seguros, aceptas los presentes términos. Léelos con atención.</p>
      <div class="legal-meta">
        <span>
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          Última actualización: enero 2026
        </span>
        <span>
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          Tiempo de lectura: ~6 min
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
          <li><a href="#objeto">Objeto</a></li>
          <li><a href="#usuario">Usuario</a></li>
          <li><a href="#acceso">Acceso y navegación</a></li>
          <li><a href="#privacidad">Privacidad y datos</a></li>
          <li><a href="#enlaces">Política de enlaces</a></li>
          <li><a href="#propiedad">Propiedad intelectual</a></li>
          <li><a href="#legislacion">Legislación aplicable</a></li>
          <li><a href="#contacto-tc">Contacto</a></li>
        </ol>
      </aside>

      <article class="legal-content">

        <div class="legal-highlight">
          <p><strong>Titular:</strong> OPERACIONES Y MULTISERVICIOS IDEAS, SA. DE C.V. (GRUPO IDEAS), con domicilio en Calle Río Mississippi #52-301-B, Colonia Cuauhtémoc, C.P. 06500, Alcaldía Cuauhtémoc, Ciudad de México. El presente contrato regula el uso del contenido, productos y/o servicios de <strong>SEGUROS LUX by GRUPO IDEAS</strong> disponibles en <a href="https://seguroslux.mx">seguroslux.mx</a>.</p>
        </div>

        <h2 id="objeto"><span class="num">I</span> Objeto</h2>
        <p>El objeto es regular el acceso y utilización del contenido, productos y/o servicios a disposición del público en general en el dominio <a href="https://seguroslux.mx">https://seguroslux.mx</a>.</p>
        <p>El titular se reserva el derecho de realizar cualquier tipo de modificación en el sitio web en cualquier momento y sin previo aviso; el usuario acepta dichas modificaciones.</p>
        <p>El acceso al sitio web por parte del usuario es libre y gratuito, en el cual podrán consultarse los productos y servicios en cuanto a Pólizas de Seguros (gastos médicos mayores, vida, auto/moto y patrimonio). La utilización del contenido, cotización de producto y/o servicio no implicará un costo y no compromete al usuario a la contratación de producto alguno.</p>
        <p>El sitio web solo admite el acceso a personas mayores de edad y no se hace responsable por el incumplimiento de esto. El sitio está dirigido a usuarios residentes en México y cumple con la legislación establecida en dicho país.</p>
        <p>La administración del sitio web puede ejercerse por terceros, es decir, personas distintas al titular, sin afectar los presentes términos y condiciones.</p>

        <h2 id="usuario"><span class="num">II</span> Usuario</h2>
        <p>La actividad del usuario en el sitio web —como publicaciones o comentarios— estará sujeta a los presentes términos y condiciones. El usuario se compromete a utilizar el contenido, productos y/o servicios de forma lícita, sin faltar a la moral o al orden público, absteniéndose de realizar cualquier acto que afecte los derechos de terceros o el funcionamiento del sitio web.</p>
        <p>El usuario se compromete a proporcionar información verídica en los formularios del sitio web.</p>
        <p>El acceso al sitio web no supone una relación entre el usuario y el titular. SEGUROS LUX se deslinda de toda responsabilidad del mal uso que el usuario pudiera dar al sitio. Está totalmente prohibido el uso del sitio para actos ilícitos, de forma enunciativa y no limitativa: venta de estupefacientes, armas de fuego, trata de personas, venta o reproducción de contenido de pornografía infantil o de cualquier tipo, apuestas y casinos, entre otros. SEGUROS LUX by GRUPO IDEAS queda exento de cualquier responsabilidad derivada del mal uso del sitio.</p>
        <p>El usuario manifiesta ser mayor de edad y contar con la capacidad jurídica para acatar los presentes términos y condiciones.</p>
        <p>Una vez contratada una Póliza de Seguro (gastos médicos mayores, vida, auto/moto o patrimonio), el usuario deberá enviar la documentación requerida y sus datos personales. Una vez recabados y aceptados, el contratante deberá realizar el pago de la póliza contratada. El pago podrá efectuarse en efectivo, transferencia interbancaria (SPI), tarjeta de débito y crédito (MSI).</p>
        <p>SEGUROS LUX by GRUPO IDEAS se pondrá en contacto con el cliente en caso de que falten datos o documentos para la integración de su expediente, así como para notificar pagos pendientes, vencimientos próximos o la cancelación de la póliza.</p>

        <div class="legal-warning">
          <p><strong>Vigencia de la póliza:</strong> Una vez realizado el pago, la póliza contratada entrará en vigor dentro de las doce horas siguientes a la contratación.</p>
        </div>

        <p>SEGUROS LUX by GRUPO IDEAS resguardará el perfil e información del cliente durante la vigencia de la póliza. Una vez terminada o cancelada, conservará dicha información por un período de un año, tras el cual el perfil e información del cliente serán eliminados.</p>

        <h2 id="acceso"><span class="num">III</span> Acceso y navegación en el sitio web</h2>
        <p>El titular no garantiza la continuidad y disponibilidad del contenido, productos y/o servicios en el sitio web por causas ajenas o de fuerza mayor (sismos, huracanes, inundaciones, entre otros eventos de fuerza mayor).</p>
        <p>El titular no se responsabiliza de que el software esté libre de errores que puedan causar daño al software y/o hardware del equipo del usuario, ni por los daños causados por el acceso y/o utilización del sitio web.</p>

        <h2 id="privacidad"><span class="num">IV</span> Política de privacidad y protección de datos</h2>
        <p>Conforme a lo establecido en la Ley de Protección de Datos Personales en Posesión de Particulares, el titular se compromete a tomar las medidas necesarias que garanticen la seguridad del usuario, evitando el uso indebido de los datos personales proporcionados en el sitio web.</p>
        <p>El titular verificará que los datos personales contenidos en las bases de datos sean correctos, verídicos y actuales, y que se utilicen únicamente con el fin para el que fueron recabados.</p>
        <p>El uso de datos personales se limitará a lo previsto en el Aviso de Privacidad disponible en <a href="https://www.seguroslux.mx/aviso-privacidad">https://www.seguroslux.mx/aviso-privacidad</a>.</p>
        <p>SEGUROS LUX by GRUPO IDEAS se reserva el derecho de modificar el Aviso de Privacidad en cualquier momento y sin previo aviso, de acuerdo con sus necesidades o cambios en la legislación aplicable; el usuario acepta dichas modificaciones.</p>
        <p>El sitio web utiliza cookies: pequeñas cantidades de información almacenadas en el navegador del usuario (datos de ingreso, preferencias, fecha y hora de acceso, sitios visitados y dirección IP). Esta información es anónima y se utilizará únicamente para mejorar el sitio web. El usuario puede desactivar las cookies en cualquier momento desde su navegador, con el entendido de que esto puede afectar algunas funciones del sitio.</p>

        <h2 id="enlaces"><span class="num">V</span> Política de enlaces</h2>
        <p>El sitio web puede contener enlaces a otros sitios de internet pertenecientes a terceros, de los cuales SEGUROS LUX by GRUPO IDEAS no se hace responsable.</p>

        <h2 id="propiedad"><span class="num">VI</span> Política de propiedad intelectual e industrial</h2>
        <p>El titular manifiesta tener los derechos de propiedad intelectual e industrial del sitio web, incluyendo imágenes, archivos de audio o video, logotipos, marcas, colores, estructuras, tipografías, diseños y demás elementos que lo distinguen, protegidos por la legislación mexicana e internacional en materia de propiedad intelectual e industrial.</p>
        <p>El usuario se compromete a respetar los derechos de propiedad industrial e intelectual del titular, pudiendo visualizar los elementos del sitio web, almacenarlos, copiarlos e imprimirlos exclusivamente para uso personal.</p>

        <h2 id="legislacion"><span class="num">VII</span> Legislación y jurisdicción aplicable</h2>
        <p>La relación entre el usuario y el titular se regirá por las legislaciones aplicables en México.</p>
        <p>Por medio del acceso y uso del sitio web, el usuario manifiesta su conformidad con los presentes Términos y Condiciones.</p>

        <h2 id="contacto-tc"><span class="num">VIII</span> Contacto</h2>
        <p>Para cualquier duda, aclaración o reclamación relacionada con estos Términos, contáctanos:</p>

        <div class="legal-contact-box">
          <h3>Seguros Lux — Área Legal</h3>
          <p>Respondemos en un plazo máximo de 5 días hábiles.</p>
          <div class="contact-items">
            <div class="contact-item">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
              <a href="mailto:legal@seguroslux.mx" style="color:rgba(255,255,255,0.9)">legal@seguroslux.mx</a>
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
      <div>© 2026 Seguros Lux. Intermediario autorizado por la CNSF. Todos los derechos reservados.</div>
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