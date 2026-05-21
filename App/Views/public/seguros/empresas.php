<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Seguros Empresariales | Seguros Lux</title>
<meta name="description" content="Protege tu empresa con seguros corporativos: flotillas, GMM colectivo, responsabilidad civil y más. Asesoría personalizada para empresas." />
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Google+Sans:ital,opsz,wght@0,17..18,400..700;1,17..18,400..700&family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../css/landing.css">
<link rel="icon" type="image/png" sizes="32x32" href="/images/logo.png">
<link rel="stylesheet" href="../css/nav-submenus.css">
<style>
  :root {
    --emp-primary: #7c3aed;
    --emp-light:   #ede9fe;
    --emp-dark:    #5b21b6;
  }
  .product-empresas {
    --accent: var(--emp-primary);
    --accent-light: var(--emp-light);
    --accent-dark: var(--emp-dark);
  }
  .hero-emp {
    background: linear-gradient(135deg, #0b1f3a 0%, #1e1b4b 60%, #2d1b69 100%);
    color: #fff;
    padding: 90px 0 80px;
    position: relative;
    overflow: hidden;
  }
  .hero-emp::before {
    content: '';
    position: absolute;
    inset: 0;
    background-image: radial-gradient(circle at 20% 50%, rgba(124,58,237,.18) 0%, transparent 55%),
                      radial-gradient(circle at 80% 30%, rgba(91,33,182,.2) 0%, transparent 50%);
    pointer-events: none;
  }
  .hero-emp-grid {
    display: grid;
    grid-template-columns: 1fr 0.9fr;
    gap: 56px;
    align-items: center;
  }
  .hero-emp h1 {
    font-family: var(--font-display);
    font-size: clamp(2rem, 4.5vw, 3.2rem);
    font-weight: 900;
    line-height: 1.08;
    letter-spacing: -1.5px;
    margin-bottom: 18px;
  }
  .hero-emp h1 em { font-style: italic; color: #a78bfa; }
  .hero-emp p.lead { font-size: 16px; color: rgba(255,255,255,.82); margin-bottom: 24px; }
  .emp-bullets { list-style: none; margin-bottom: 28px; }
  .emp-bullets li {
    display: flex; align-items: center; gap: 10px;
    padding: 7px 0; font-size: 14px; color: rgba(255,255,255,.9);
  }
  .emp-bullets li::before { content: '✓'; color: #a78bfa; font-weight: 800; font-size: 16px; }

  /* Cotizador card */
  .emp-card {
    background: #fff; color: var(--primary);
    border-radius: var(--radius-xl);
    padding: 32px 28px;
    box-shadow: 0 24px 60px rgba(0,0,0,.35);
  }
  .emp-card h3 {
    font-family: var(--font-display);
    font-size: 22px; font-weight: 800; margin-bottom: 6px; letter-spacing: -.3px;
  }
  .emp-card p.sub { font-size: 14px; color: var(--gray-600); margin-bottom: 20px; }
  .emp-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px; }
  .emp-form-row.full { grid-template-columns: 1fr; }
  .emp-form-group label {
    display: block; font-size: 12px; font-weight: 700;
    color: var(--primary); margin-bottom: 5px; text-transform: uppercase; letter-spacing: .5px;
  }
  .emp-form-group input, .emp-form-group select, .emp-form-group textarea {
    width: 100%; padding: 11px 14px;
    border: 1.5px solid var(--gray-200); border-radius: 10px;
    font-family: inherit; font-size: 14px; color: var(--primary); background: #fff;
    transition: border-color .2s;
  }
  .emp-form-group input:focus, .emp-form-group select:focus, .emp-form-group textarea:focus {
    outline: 0; border-color: var(--emp-primary);
  }
  .emp-form-group textarea { resize: vertical; min-height: 76px; }
  .emp-trust {
    margin-top: 14px; padding-top: 14px;
    border-top: 1px solid var(--gray-200);
    display: flex; justify-content: space-between; gap: 10px; flex-wrap: wrap;
    font-size: 11px; color: var(--gray-400);
  }
  .emp-trust span { display: inline-flex; align-items: center; gap: 4px; }

  /* Coberturas */
  .coberturas-section { padding: 80px 0; background: var(--gray-50); }
  .cobertura-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 20px; }
  .cobertura-card {
    background: #fff; border-radius: 14px; padding: 24px 22px;
    border: 1.5px solid var(--gray-200);
    box-shadow: var(--shadow-sm);
    transition: transform .25s, box-shadow .25s, border-color .25s;
  }
  .cobertura-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-md); border-color: var(--emp-primary); }
  .cobertura-icon { font-size: 36px; margin-bottom: 14px; }
  .cobertura-card h4 { font-family: var(--font-display); font-size: 17px; font-weight: 800; margin-bottom: 8px; }
  .cobertura-card p { font-size: 13px; color: var(--gray-600); line-height: 1.6; }

  /* Proceso */
  .proceso-section { padding: 80px 0; }
  .proceso-steps { display: grid; grid-template-columns: repeat(4, 1fr); gap: 28px; }
  .proceso-step { text-align: center; }
  .proceso-num {
    width: 56px; height: 56px; border-radius: 50%;
    background: var(--emp-light); color: var(--emp-primary);
    display: flex; align-items: center; justify-content: center;
    font-weight: 900; font-size: 22px; margin: 0 auto 14px;
    transition: background .2s, color .2s, transform .2s;
  }
  .proceso-step:hover .proceso-num { background: var(--emp-primary); color: #fff; transform: scale(1.08); }
  .proceso-step h4 { font-weight: 700; font-size: 15px; margin-bottom: 6px; }
  .proceso-step p { font-size: 13px; color: var(--gray-600); }

  /* CTA final */
  .cta-final { padding: 80px 0; background: linear-gradient(135deg, var(--emp-primary) 0%, var(--emp-dark) 100%); color: #fff; }
  .cta-final h2 { font-family: var(--font-display); font-size: clamp(1.8rem,3.5vw,2.8rem); font-weight: 900; margin-bottom: 14px; }

  @media (max-width: 900px) {
    .hero-emp-grid, .proceso-steps { grid-template-columns: 1fr; }
    .emp-form-row { grid-template-columns: 1fr; }
  }
</style>
</head>
<body class="product-empresas">

<!-- ── Hero ─────────────────────────────────────────────────────────── -->
<section class="hero-emp" id="inicio">
  <div class="container">
    <div class="hero-emp-grid">
      <div>
        <span style="display:inline-block;font-size:12px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#a78bfa;background:rgba(167,139,250,.12);padding:5px 12px;border-radius:99px;margin-bottom:18px;">🏢 Seguros Empresariales</span>
        <h1>Protege tu empresa.<br><em>Escala sin riesgos.</em></h1>
        <p class="lead">Flotillas, GMM colectivo, responsabilidad civil, daños, interrupción de negocios y más. Un asesor dedicado que entiende tu industria.</p>
        <ul class="emp-bullets">
          <li>Cotización en menos de 24 horas</li>
          <li>Sin intermediarios — directo con las mejores aseguradoras</li>
          <li>Renovación asistida y gestión de siniestros incluida</li>
          <li>Factura electrónica y atención empresarial prioritaria</li>
        </ul>
        <div style="display:flex;gap:14px;flex-wrap:wrap;">
          <a href="#cotizar" class="btn btn-primary">Cotizar ahora</a>
          <a href="tel:5541232311" class="btn btn-ghost" style="border-color:rgba(255,255,255,.35);color:#fff;">📞 55 4123 2311</a>
        </div>
      </div>

      <!-- Cotizador -->
      <div class="emp-card" id="cotizar">
        <h3>Solicitar Cotización</h3>
        <p class="sub">Sin costo · Sin compromiso · Respuesta en 24 h</p>
        <form id="formEmpresas" novalidate>

          <div class="emp-form-row">
            <div class="emp-form-group">
              <label>Nombre de la empresa</label>
              <input type="text" name="empresa" placeholder="Ej. Grupo ABC S.A. de C.V." required>
            </div>
            <div class="emp-form-group">
              <label>Giro / Industria</label>
              <input type="text" name="giro_empresa" placeholder="Ej. Transporte, Manufactura…">
            </div>
          </div>

          <div class="emp-form-row">
            <div class="emp-form-group">
              <label>Número de empleados</label>
              <select name="empleados">
                <option value="">Selecciona…</option>
                <option value="1-10">1 – 10</option>
                <option value="11-50">11 – 50</option>
                <option value="51-200">51 – 200</option>
                <option value="201-500">201 – 500</option>
                <option value="500+">Más de 500</option>
              </select>
            </div>
            <div class="emp-form-group">
              <label>Tipo de seguro principal</label>
              <select name="servicios">
                <option value="">Selecciona…</option>
                <option value="Flotilla vehicular">Flotilla vehicular</option>
                <option value="GMM colectivo">GMM colectivo (empleados)</option>
                <option value="Responsabilidad civil">Responsabilidad civil</option>
                <option value="Daños / incendio">Daños / incendio</option>
                <option value="Interrupción de negocios">Interrupción de negocios</option>
                <option value="Vida grupo">Vida grupo</option>
                <option value="Varios / Paquete">Varios / Paquete</option>
              </select>
            </div>
          </div>

          <div class="emp-form-row">
            <div class="emp-form-group">
              <label>Nombre del contacto</label>
              <input type="text" name="nombre" placeholder="Tu nombre completo" required>
            </div>
            <div class="emp-form-group">
              <label>Teléfono / WhatsApp</label>
              <input type="tel" name="telefono" inputmode="numeric" maxlength="10" placeholder="55 1234 5678" required>
            </div>
          </div>

          <div class="emp-form-row">
            <div class="emp-form-group">
              <label>Correo electrónico</label>
              <input type="email" name="correo" placeholder="contacto@empresa.com">
            </div>
            <div class="emp-form-group">
              <label>Fecha preferida de contacto</label>
              <input type="date" name="fecha_preferida">
            </div>
          </div>

          <div class="emp-form-row full">
            <div class="emp-form-group">
              <label>Comentarios adicionales</label>
              <textarea name="comentarios" placeholder="Cuéntanos más: número de vehículos, ubicaciones, coberturas actuales, dudas…"></textarea>
            </div>
          </div>

          <button type="submit" class="btn btn-primary" style="width:100%;margin-top:4px;">
            Solicitar Asesoría Empresarial →
          </button>

          <div class="emp-trust">
            <span>🔒 Datos protegidos</span>
            <span>📋 Sin spam</span>
            <span>⚡ Respuesta en &lt; 24 h</span>
          </div>
        </form>
      </div>
    </div>
  </div>
</section>

<!-- ── Coberturas ────────────────────────────────────────────────────── -->
<section class="coberturas-section" id="coberturas">
  <div class="container">
    <div style="text-align:center;margin-bottom:48px;">
      <span style="font-size:12px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:var(--emp-primary);">Lo que cubrimos</span>
      <h2 style="font-family:var(--font-display);font-size:clamp(1.8rem,3.5vw,2.6rem);font-weight:900;letter-spacing:-1px;margin-top:8px;">Seguros diseñados para<br>empresas que crecen</h2>
    </div>
    <div class="cobertura-grid">
      <div class="cobertura-card">
        <div class="cobertura-icon">🚗</div>
        <h4>Flotilla Vehicular</h4>
        <p>Cobertura amplia para toda tu flota: daños, robo total, RC ampliada, asistencia vial 24/7. Descuentos por volumen desde 3 unidades.</p>
      </div>
      <div class="cobertura-card">
        <div class="cobertura-icon">🏥</div>
        <h4>GMM Colectivo</h4>
        <p>Gastos Médicos Mayores para empleados y familiares. Mejor prima que pólizas individuales. Fortalece tu paquete de beneficios.</p>
      </div>
      <div class="cobertura-card">
        <div class="cobertura-icon">⚖️</div>
        <h4>Responsabilidad Civil</h4>
        <p>Protege a tu empresa ante reclamaciones de terceros por daños materiales o corporales derivados de tu operación.</p>
      </div>
      <div class="cobertura-card">
        <div class="cobertura-icon">🏭</div>
        <h4>Daños e Incendio</h4>
        <p>Protección para instalaciones, maquinaria, inventario y equipos contra incendio, robo, inundación y otros eventos.</p>
      </div>
      <div class="cobertura-card">
        <div class="cobertura-icon">⏸️</div>
        <h4>Interrupción de Negocios</h4>
        <p>Cubre la pérdida de ingresos cuando tu empresa no puede operar por un siniestro cubierto. Mantén el flujo durante la emergencia.</p>
      </div>
      <div class="cobertura-card">
        <div class="cobertura-icon">❤️</div>
        <h4>Vida Grupo</h4>
        <p>Seguro de vida colectivo para proteger a los empleados y sus familias. Deducible fiscalmente y con administración centralizada.</p>
      </div>
    </div>
  </div>
</section>

<!-- ── Proceso ───────────────────────────────────────────────────────── -->
<section class="proceso-section" id="proceso">
  <div class="container">
    <div style="text-align:center;margin-bottom:48px;">
      <h2 style="font-family:var(--font-display);font-size:clamp(1.6rem,3vw,2.3rem);font-weight:900;letter-spacing:-1px;">Así de fácil funciona</h2>
    </div>
    <div class="proceso-steps">
      <div class="proceso-step">
        <div class="proceso-num">1</div>
        <h4>Llenas el formulario</h4>
        <p>Datos básicos de tu empresa, giro y tipo de cobertura que necesitas.</p>
      </div>
      <div class="proceso-step">
        <div class="proceso-num">2</div>
        <h4>Te contacta un asesor</h4>
        <p>Un especialista empresarial te llama en menos de 24 horas para entender a fondo tu operación.</p>
      </div>
      <div class="proceso-step">
        <div class="proceso-num">3</div>
        <h4>Recibes tu cotización</h4>
        <p>Comparativa de opciones con las mejores aseguradoras del mercado, adaptada a tu presupuesto.</p>
      </div>
      <div class="proceso-step">
        <div class="proceso-num">4</div>
        <h4>Gestión continua</h4>
        <p>Renovaciones, siniestros y cambios: tu asesor te acompaña todo el año.</p>
      </div>
    </div>
  </div>
</section>

<!-- ── CTA Final ─────────────────────────────────────────────────────── -->
<section class="cta-final">
  <div class="container" style="text-align:center;">
    <h2>¿Listo para proteger tu empresa?</h2>
    <p style="font-size:16px;color:rgba(255,255,255,.85);max-width:540px;margin:0 auto 28px;">
      Habla con un asesor empresarial hoy. Sin costo, sin compromiso.
    </p>
    <div style="display:flex;gap:16px;justify-content:center;flex-wrap:wrap;">
      <a href="#cotizar" class="btn" style="background:#fff;color:var(--emp-primary);font-weight:700;">Solicitar cotización</a>
      <a href="https://wa.me/5215514605959" target="_blank" class="btn btn-ghost" style="border-color:rgba(255,255,255,.4);color:#fff;">💬 WhatsApp directo</a>
    </div>
  </div>
</section>

<?php require __DIR__ . '/../../partials/_footer.php'; ?>

<a href="https://wa.me/5215514605959" class="whatsapp-float" aria-label="WhatsApp">
  <svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347M12 2C6.48 2 2 6.48 2 12c0 1.77.46 3.45 1.27 4.9L2 22l5.25-1.38C8.66 21.5 10.27 22 12 22c5.52 0 10-4.48 10-10S17.52 2 12 2z"/></svg>
</a>

<script src="../js/landing.js" defer></script>
<script src="../js/empresas.js" defer></script>
</body>
</html>