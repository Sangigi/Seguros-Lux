<?php
declare(strict_types=1);
use App\Security\SessionManager;
$nonce = SessionManager::nonce();
header("Content-Security-Policy: script-src 'self' 'unsafe-hashes' 'nonce-SFJGwYCGbqfOpVAETI36Ug==' 'nonce-bCTKJ8jvaMQSTk+mJqNnSQ==' 'sha256-SxvxBwFKi30Ha/zZWTRb9NJg74REtc1o54/FaxCyQxg=' 'sha256-nMfu92wtOkaru2r7PqzP6B80M8SdzaU6H3INqlYVRqo=' 'sha256-Gwl3zb9M02Q7E/PnJHqlQngViTYyz175pzuwLgN8EcA=' 'sha256-zU92tUfcgNOSw/JQ9H7HZI9KwEgnmnZUfMzwuz+mN+s=' 'sha256-OXiyEbUgkkJphSJ8KbSgayxuH+bsHW21a0LrN6JLpOM=' https://cdn.jsdelivr.net https://blog.seguroslux.mx https://www.googletagmanager.com https://googleads.g.doubleclick.net https://www.googleadservices.com;");
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Seguros LUX — Cotiza tu Seguro</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="/css/formulario_cliente.css">
<link rel="icon" type="image/png" sizes="32x32" href="/images/logo.png">
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
<nav class="navbar" id="navbar">
    <div class="container navbar-content">
        <a href="/" class="logo"><img src="/images/logo.png" alt="Seguros Lux" style="height:38px;"></a>
    </div>
</nav>

<div class="cotizador-layout">

    <!-- ══ SIDEBAR ══ -->
    <aside class="ramo-sidebar" id="ramoSidebar">
        <div class="sidebar-header">
            <span class="sidebar-eyebrow">Seguros LUX</span>
            <h2>¿Qué necesitas proteger?</h2>
            <p>Selecciona el tipo de seguro para iniciar tu cotización.</p>
        </div>
        <div class="ramo-list">
            <button class="ramo-btn" data-ramo="vehicular">
                <span class="ramo-icon-wrap veh"><i class="bi bi-car-front-fill"></i></span>
                <span class="ramo-text"><strong>Seguro Vehicular</strong><small>Autos, SUV, Moto, Pickup</small></span>
                <i class="bi bi-chevron-right ramo-arrow"></i>
            </button>
            <button class="ramo-btn" data-ramo="vida">
                <span class="ramo-icon-wrap vida"><i class="bi bi-heart-fill"></i></span>
                <span class="ramo-text"><strong>Seguro de Vida</strong><small>Protege el futuro de tu familia</small></span>
                <i class="bi bi-chevron-right ramo-arrow"></i>
            </button>
            <button class="ramo-btn" data-ramo="gmm">
                <span class="ramo-icon-wrap gmm"><i class="bi bi-hospital-fill"></i></span>
                <span class="ramo-text"><strong>Gastos Médicos</strong><small>Cobertura médica integral</small></span>
                <i class="bi bi-chevron-right ramo-arrow"></i>
            </button>
            <button class="ramo-btn" data-ramo="empresas">
                <span class="ramo-icon-wrap emp"><i class="bi bi-buildings-fill"></i></span>
                <span class="ramo-text"><strong>Empresas</strong><small>Paquetes corporativos y flotillas</small></span>
                <i class="bi bi-chevron-right ramo-arrow"></i>
            </button>
        </div>
        <div class="sidebar-footer">
            <div class="sidebar-contact"><i class="bi bi-telephone-fill"></i><a href="tel:+525541232311">55 4123 2311</a></div>
            <small>Lun – Vie, 9:00 – 18:00</small>
        </div>
    </aside>

    <!-- ══ MAIN ══ -->
    <main class="form-main" id="formMain">

        <div class="form-empty" id="formEmpty">
            <div class="empty-icon"><i class="bi bi-arrow-left-circle"></i></div>
            <h3>Elige un tipo de seguro</h3>
            <p>Selecciona una opción para comenzar tu cotización personalizada.</p>
        </div>

        <!-- ─── VEHICULAR ─── -->
        <div class="ramo-form" id="ramoVehicular" style="display:none;">
            <div class="form-card">
                <div class="step-counter">Paso <span id="veh-currentStep">1</span> de 6</div>
                <div class="progress-wrapper" id="veh-progressBar">
                    <div class="progress-segment active"></div>
                    <div class="progress-segment"></div>
                    <div class="progress-segment"></div>
                    <div class="progress-segment"></div>
                    <div class="progress-segment"></div>
                    <div class="progress-segment"></div>
                </div>
                <form id="vehForm" novalidate>

                    <!-- V1: Tipo de vehículo -->
                    <div class="form-step active" data-vstep="1">
                        <h2>Tipo de vehículo</h2>
                        <p class="step-subtitle">Selecciona tu vehículo y cuéntanos un poco más.</p>
                        <div class="section-divider"></div>
                        <div class="option-cards" id="tipoVehiculoOptions">
                            <div class="option-card" data-value="auto"><i class="bi bi-car-front" style="color:#0ea5e9;"></i><span class="option-label">Auto</span><small class="option-desc">Sedán, hatchback</small></div>
                            <div class="option-card" data-value="moto"><i class="bi bi-bicycle" style="color:#8b5cf6;"></i><span class="option-label">Moto</span><small class="option-desc">600 cc o más</small></div>
                            <div class="option-card" data-value="camioneta"><i class="bi bi-truck-front" style="color:#10b981;"></i><span class="option-label">SUV</span><small class="option-desc">Familiar, todoterreno</small></div>
                            <div class="option-card" data-value="pickup"><i class="bi bi-truck" style="color:#f59e0b;"></i><span class="option-label">Pickup</span><small class="option-desc">Carga o trabajo</small></div>
                        </div>
                        <div class="error-msg" id="error-tipoVehiculo">Selecciona un tipo.</div>

                        <div id="vehDatosWrapper" style="display:none;margin-top:24px;">
                            <div class="row-fields">
                                <div class="field-group"><label class="form-label-custom">Marca <span class="required">*</span></label><select class="form-input" id="marca"><option value="">— Primero elige tipo —</option></select><div class="error-msg" id="error-marca">Selecciona la marca.</div></div>
                                <div class="field-group"><label class="form-label-custom">Modelo <span class="required">*</span></label><input type="text" class="form-input" id="modelo" placeholder="Versa, CB500…"><div class="error-msg" id="error-modelo">Campo obligatorio.</div></div>
                            </div>
                            <div class="field-group"><label class="form-label-custom">Año <span class="required">*</span></label><select class="form-input" id="anioVehiculo"><option value="">Selecciona</option></select><div class="error-msg" id="error-anioVehiculo">Selecciona el año.</div></div>

                            <div id="miniFormAuto" class="mini-form-ctx" style="display:none;">
                                <div class="mini-form-header"><i class="bi bi-car-front-fill"></i> Cuéntanos más sobre tu auto</div>
                                <div class="mini-q-group"><label class="form-label-custom">¿Quemacocos / techo solar?</label><div class="mini-toggle-opts" data-group="quemacocos"><div class="mini-tog" data-val="si">Sí</div><div class="mini-tog" data-val="no">No</div></div></div>
                                <div class="mini-q-group"><label class="form-label-custom">¿Rines de lujo?</label><div class="mini-toggle-opts" data-group="rines"><div class="mini-tog" data-val="si">Sí</div><div class="mini-tog" data-val="no">No</div></div></div>
                                <div class="mini-q-group"><label class="form-label-custom">¿Uso principal?</label><div class="mini-toggle-opts" data-group="uso_auto"><div class="mini-tog" data-val="particular">Particular</div><div class="mini-tog" data-val="trabajo">Trabajo</div><div class="mini-tog" data-val="reparto">Reparto</div></div></div>
                                <div class="mini-q-group"><label class="form-label-custom">¿GPS instalado?</label><div class="mini-toggle-opts" data-group="gps"><div class="mini-tog" data-val="si">Sí</div><div class="mini-tog" data-val="no_se">No sé</div><div class="mini-tog" data-val="no">No</div></div></div>
                            </div>
                            <div id="miniFormMoto" class="mini-form-ctx" style="display:none;">
                                <div class="mini-form-header"><i class="bi bi-bicycle"></i> Cuéntanos más sobre tu moto</div>
                                <div class="mini-q-group"><label class="form-label-custom">¿Cilindrada?</label><div class="mini-toggle-opts" data-group="cilindrada"><div class="mini-tog" data-val="600_900">600–900</div><div class="mini-tog" data-val="900_1200">900–1200</div><div class="mini-tog" data-val="mas_1200">+1200</div></div></div>
                                <div class="mini-q-group"><label class="form-label-custom">¿Tipo?</label><div class="mini-toggle-opts" data-group="tipo_moto"><div class="mini-tog" data-val="scooter">Scooter</div><div class="mini-tog" data-val="deportiva">Deportiva</div><div class="mini-tog" data-val="naked">Naked</div><div class="mini-tog" data-val="offroad">Off-road</div></div></div>
                                <div class="mini-q-group"><label class="form-label-custom">¿Uso delivery?</label><div class="mini-toggle-opts" data-group="uso_moto"><div class="mini-tog" data-val="si">Sí</div><div class="mini-tog" data-val="no">No</div></div></div>
                            </div>
                            <div id="miniFormCamioneta" class="mini-form-ctx" style="display:none;">
                                <div class="mini-form-header"><i class="bi bi-truck-front-fill"></i> Cuéntanos más sobre tu SUV</div>
                                <div class="mini-q-group"><label class="form-label-custom">¿Uso principal?</label><div class="mini-toggle-opts" data-group="uso_suv"><div class="mini-tog" data-val="familiar">Familiar</div><div class="mini-tog" data-val="offroad">Off-road</div><div class="mini-tog" data-val="ciudad">Ciudad</div></div></div>
                                <div class="mini-q-group"><label class="form-label-custom">¿Tracción?</label><div class="mini-toggle-opts" data-group="trac_suv"><div class="mini-tog" data-val="4x2">4×2</div><div class="mini-tog" data-val="4x4">4×4 / AWD</div></div></div>
                            </div>
                            <div id="miniFormPickup" class="mini-form-ctx" style="display:none;">
                                <div class="mini-form-header"><i class="bi bi-truck"></i> Cuéntanos más sobre tu pickup</div>
                                <div class="mini-q-group"><label class="form-label-custom">¿Uso comercial?</label><div class="mini-toggle-opts" data-group="uso_pickup"><div class="mini-tog" data-val="comercial">Sí, trabajo</div><div class="mini-tog" data-val="familiar">No, familiar</div></div></div>
                                <div class="mini-q-group"><label class="form-label-custom">¿Tracción?</label><div class="mini-toggle-opts" data-group="trac_pickup"><div class="mini-tog" data-val="4x2">4×2</div><div class="mini-tog" data-val="4x4">4×4</div></div></div>
                            </div>

                            <div class="field-group" style="margin-top:20px;"><label class="form-label-custom">¿Recién comprado? <span class="required">*</span></label>
                                <div class="option-cards" id="recienCompradoOptions">
                                    <div class="option-card" data-value="si" data-group="recienComprado"><i class="bi bi-bag-check"></i><span class="option-label">Sí</span></div>
                                    <div class="option-card" data-value="no" data-group="recienComprado"><i class="bi bi-clock-history"></i><span class="option-label">Ya lo tenía</span></div>
                                </div>
                                <div class="error-msg" id="error-recienComprado">Selecciona una opción.</div>
                            </div>
                        </div>
                        <div class="btn-actions"><button type="button" class="btn-next" data-nextstep="1">Continuar <i class="bi bi-arrow-right"></i></button></div>
                    </div>

                    <!-- V2: Seguro actual -->
                    <div class="form-step" data-vstep="2">
                        <h2>Seguro actual</h2>
                        <p class="step-subtitle">Cuéntanos tu situación actual.</p>
                        <div class="section-divider"></div>
                        <div class="field-group"><label class="form-label-custom">¿Está asegurado? <span class="required">*</span></label>
                            <div class="option-cards" id="aseguradoOptions">
                                <div class="option-card" data-value="si" data-group="asegurado"><i class="bi bi-shield-check"></i><span class="option-label">Sí</span></div>
                                <div class="option-card" data-value="no" data-group="asegurado"><i class="bi bi-shield-x"></i><span class="option-label">No</span></div>
                            </div>
                            <div class="error-msg" id="error-asegurado">Selecciona una opción.</div>
                        </div>
                        <div class="field-group"><label class="form-label-custom">¿Cuándo piensas comprar / cambiar? <span class="required">*</span></label>
                            <div class="option-cards" id="tiempoCompraOptions" style="flex-wrap:wrap;">
                                <div class="option-card" data-value="inmediato" data-group="tiempoCompra"><i class="bi bi-lightning"></i><span class="option-label">De inmediato</span></div>
                                <div class="option-card" data-value="esta_semana" data-group="tiempoCompra"><i class="bi bi-calendar-week"></i><span class="option-label">Esta semana</span></div>
                                <div class="option-card" data-value="este_mes" data-group="tiempoCompra"><i class="bi bi-calendar-month"></i><span class="option-label">Este mes</span></div>
                            </div>
                            <div class="error-msg" id="error-tiempoCompra">Selecciona una opción.</div>
                        </div>
                        <div class="btn-actions">
                            <button type="button" class="btn-prev" data-prevstep="2"><i class="bi bi-arrow-left"></i> Atrás</button>
                            <button type="button" class="btn-next" data-nextstep="2">Continuar <i class="bi bi-arrow-right"></i></button>
                        </div>
                    </div>

                    <!-- V3: Datos personales -->
                    <div class="form-step" data-vstep="3">
                        <h2>Datos Personales</h2>
                        <p class="step-subtitle">Para generar tu cotización.</p>
                        <div class="section-divider"></div>
                        <div class="field-group"><label class="form-label-custom">Nombre(s) <span class="required">*</span></label><input type="text" class="form-input" id="nombre" placeholder="Juan Carlos"><div class="error-msg" id="error-nombre">Campo obligatorio.</div></div>
                        <div class="row-fields">
                            <div class="field-group"><label class="form-label-custom">Ap. Paterno <span class="required">*</span></label><input type="text" class="form-input" id="apellidoPaterno" placeholder="García"><div class="error-msg" id="error-apellidoPaterno">Campo obligatorio.</div></div>
                            <div class="field-group"><label class="form-label-custom">Ap. Materno <span class="required">*</span></label><input type="text" class="form-input" id="apellidoMaterno" placeholder="López"><div class="error-msg" id="error-apellidoMaterno">Campo obligatorio.</div></div>
                        </div>
                        <div class="field-group"><label class="form-label-custom">Correo <span class="required">*</span></label><input type="email" class="form-input" id="correo" placeholder="tu@correo.com"><div class="error-msg" id="error-correo">Correo inválido.</div></div>
                        <div class="field-group"><label class="form-label-custom">Teléfono / WhatsApp <span class="required">*</span></label>
                            <div style="display:flex;gap:8px;">
                                <select id="lada" class="form-input" style="flex:0 0 auto;width:auto;padding-right:36px;"><option value="52">MX +52</option><option value="1">US +1</option><option value="34">ES +34</option></select>
                                <input type="tel" class="form-input" id="telefono" placeholder="55 1234 5678" maxlength="10" style="flex:1;">
                            </div>
                            <div class="error-msg" id="error-telefono">Ingresa tu teléfono.</div>
                        </div>
                        <div class="row-fields">
                            <div class="field-group"><label class="form-label-custom">Fecha de Nacimiento <span class="required">*</span></label><input type="date" class="form-input" id="fechaNacimiento"><div class="error-msg" id="error-fechaNacimiento">Campo obligatorio.</div></div>
                            <div class="field-group"><label class="form-label-custom">Edad</label><input type="text" class="form-input" id="edad" readonly placeholder="Auto-calculada"></div>
                        </div>
                        <div class="field-group"><label class="form-label-custom">Sexo <span class="required">*</span></label>
                            <div class="option-cards" id="sexoOptions">
                                <div class="option-card" data-value="masculino" data-group="sexo"><i class="bi bi-gender-male"></i><span class="option-label">Masculino</span></div>
                                <div class="option-card" data-value="femenino" data-group="sexo"><i class="bi bi-gender-female"></i><span class="option-label">Femenino</span></div>
                            </div>
                            <div class="error-msg" id="error-sexo">Selecciona una opción.</div>
                        </div>
                        <div class="field-group"><label class="form-label-custom">Estado Civil <span class="required">*</span></label>
                            <select class="form-input" id="estadoCivil"><option value="">Selecciona</option><option value="soltero">Soltero(a)</option><option value="casado">Casado(a)</option><option value="divorciado">Divorciado(a)</option><option value="viudo">Viudo(a)</option><option value="union_libre">Unión Libre</option></select>
                            <div class="error-msg" id="error-estadoCivil">Campo obligatorio.</div>
                        </div>
                        <div class="btn-actions">
                            <button type="button" class="btn-prev" data-prevstep="3"><i class="bi bi-arrow-left"></i> Atrás</button>
                            <button type="button" class="btn-next" data-nextstep="3">Continuar <i class="bi bi-arrow-right"></i></button>
                        </div>
                    </div>

                    <!-- V4: Dirección -->
                    <div class="form-step" data-vstep="4">
                        <h2>Dirección</h2>
                        <p class="step-subtitle">Para calcular tu cobertura regional.</p>
                        <div class="section-divider"></div>
                        <div class="field-group"><label class="form-label-custom">Calle y Número <span class="required">*</span></label><input type="text" class="form-input" id="calle" placeholder="Av. Reforma 123"><div class="error-msg" id="error-calle">Campo obligatorio.</div></div>
                        <div class="row-fields">
                            <div class="field-group"><label class="form-label-custom">Colonia <span class="required">*</span></label><input type="text" class="form-input" id="colonia" placeholder="Centro"><div class="error-msg" id="error-colonia">Campo obligatorio.</div></div>
                            <div class="field-group"><label class="form-label-custom">C.P. <span class="required">*</span></label><input type="text" class="form-input" id="codigoPostal" placeholder="06600" maxlength="5"><div class="error-msg" id="error-codigoPostal">CP inválido.</div></div>
                        </div>
                        <div class="row-fields">
                            <div class="field-group"><label class="form-label-custom">Ciudad <span class="required">*</span></label><input type="text" class="form-input" id="ciudad" placeholder="Ciudad de México"><div class="error-msg" id="error-ciudad">Campo obligatorio.</div></div>
                            <div class="field-group"><label class="form-label-custom">Estado <span class="required">*</span></label>
                                <select class="form-input" id="estado"><option value="">Selecciona</option><option value="aguascalientes">Aguascalientes</option><option value="baja_california">Baja California</option><option value="cdmx">Ciudad de México</option><option value="jalisco">Jalisco</option><option value="mexico">Estado de México</option><option value="nuevo_leon">Nuevo León</option><option value="puebla">Puebla</option><option value="queretaro">Querétaro</option><option value="veracruz">Veracruz</option><option value="otro">Otro</option></select>
                                <div class="error-msg" id="error-estado">Selecciona tu estado.</div>
                            </div>
                        </div>
                        <div class="btn-actions">
                            <button type="button" class="btn-prev" data-prevstep="4"><i class="bi bi-arrow-left"></i> Atrás</button>
                            <button type="button" class="btn-next" data-nextstep="4">Continuar <i class="bi bi-arrow-right"></i></button>
                        </div>
                    </div>

                    <!-- V5: Cita -->
                    <div class="form-step" data-vstep="5">
                        <h2>Agenda tu Cita</h2>
                        <p class="step-subtitle">¿Cómo y cuándo te contactamos?</p>
                        <div class="section-divider"></div>
                        <div class="field-group"><label class="form-label-custom">Tipo de contacto <span class="required">*</span></label>
                            <div class="option-cards" id="tipoCitaOptions">
                                <div class="option-card" data-value="llamada" data-group="tipoCita"><i class="bi bi-telephone"></i><span class="option-label">Llamada</span></div>
                                <div class="option-card" data-value="videollamada" data-group="tipoCita"><i class="bi bi-camera-video"></i><span class="option-label">Videollamada</span></div>
                            </div>
                            <div class="error-msg" id="error-tipoCita">Selecciona una opción.</div>
                        </div>
                        <div class="field-group"><label class="form-label-custom">Fecha <span class="required">*</span></label>
                            <div class="calendar-header">
                                <button type="button" class="calendar-nav" id="btnCalPrev"><i class="bi bi-chevron-left"></i></button>
                                <h4 id="calendarTitle"></h4>
                                <button type="button" class="calendar-nav" id="btnCalNext"><i class="bi bi-chevron-right"></i></button>
                            </div>
                            <div class="calendar-grid" id="calendarGrid"></div>
                            <div class="error-msg" id="error-fecha">Selecciona una fecha.</div>
                        </div>
                        <div class="field-group"><label class="form-label-custom">Hora <span class="required">*</span></label>
                            <div class="time-slots" id="timeSlots"></div>
                            <div class="error-msg" id="error-hora">Selecciona una hora.</div>
                        </div>
                        <div class="btn-actions">
                            <button type="button" class="btn-prev" data-prevstep="5"><i class="bi bi-arrow-left"></i> Atrás</button>
                            <button type="button" class="btn-next" data-nextstep="5">Continuar <i class="bi bi-arrow-right"></i></button>
                        </div>
                    </div>

                    <!-- V6: Canal de notificación (multi-select) -->
                    <div class="form-step" data-vstep="6">
                        <h2>¿Cómo quieres recibir la confirmación?</h2>
                        <p class="step-subtitle">Elige uno o varios canales. Puedes seleccionar más de uno.</p>
                        <div class="section-divider"></div>

                        <div class="canal-cards" id="canalOptions">
                            <!-- Email: siempre disponible -->
                            <div class="canal-card" data-canal="email">
                                <div class="canal-icon em"><i class="bi bi-envelope-fill"></i></div>
                                <div class="canal-info">
                                    <p class="canal-name">Correo Electrónico</p>
                                    <p class="canal-desc">Recibirás la confirmación en tu bandeja de entrada.</p>
                                </div>
                                <div class="canal-check"><i class="bi bi-check"></i></div>
                            </div>

                            <!-- WhatsApp -->
                            <div class="canal-card" data-canal="whatsapp">
                                <div class="canal-icon wa">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                                </div>
                                <div class="canal-info">
                                    <p class="canal-name">WhatsApp</p>
                                    <p class="canal-desc">Gestiona y cancela tu cita desde WhatsApp.</p>
                                </div>
                                <div class="canal-check"><i class="bi bi-check"></i></div>
                            </div>

                            <!-- Telegram -->
                            <div class="canal-card" data-canal="telegram">
                                <div class="canal-icon tg">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221l-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12l-6.871 4.326-2.962-.924c-.643-.204-.657-.643.136-.953l11.57-4.461c.537-.194 1.006.131.833.94z"/></svg>
                                </div>
                                <div class="canal-info">
                                    <p class="canal-name">Telegram</p>
                                    <p class="canal-desc">El bot te enviará la confirmación y recordatorios.</p>
                                </div>
                                <div class="canal-check"><i class="bi bi-check"></i></div>
                            </div>
                        </div>
                        <div class="error-msg" id="error-canal">Selecciona al menos un canal de notificación.</div>

                        <div class="btn-actions">
                            <button type="button" class="btn-prev" data-prevstep="6"><i class="bi bi-arrow-left"></i> Atrás</button>
                            <button type="button" id="btnSubmitVehicular" class="btn-next">Confirmar Cita <i class="bi bi-check-lg"></i></button>
                        </div>
                    </div>

                </form>
            </div>
        </div>

        <!-- ─── VIDA / GMM ─── -->
        <div class="ramo-form" id="ramoVidaGmm" style="display:none;">
            <div class="form-card">
                <h2 id="vgTitle">Seguro de Vida</h2>
                <p class="step-subtitle" id="vgSubtitle">Un especialista te contactará para orientarte.</p>
                <div class="section-divider"></div>
                <div class="field-group"><label class="form-label-custom">Nombre completo <span class="required">*</span></label><input type="text" class="form-input" id="vg-nombre" placeholder="Juan García López"><div class="error-msg" id="error-vg-nombre">Campo obligatorio.</div></div>
                <div class="row-fields">
                    <div class="field-group"><label class="form-label-custom">Correo <span class="required">*</span></label><input type="email" class="form-input" id="vg-correo" placeholder="tu@correo.com"><div class="error-msg" id="error-vg-correo">Correo inválido.</div></div>
                    <div class="field-group"><label class="form-label-custom">Teléfono <span class="required">*</span></label><input type="tel" class="form-input" id="vg-telefono" placeholder="55 1234 5678" maxlength="10"><div class="error-msg" id="error-vg-telefono">Campo obligatorio.</div></div>
                </div>
                <div class="field-group"><label class="form-label-custom">Fecha preferida de contacto <span class="required">*</span></label><input type="date" class="form-input" id="vg-fecha"><div class="error-msg" id="error-vg-fecha">Selecciona una fecha.</div></div>
                <div id="gmmExtra" style="display:none;">
                    <div class="row-fields">
                        <div class="field-group"><label class="form-label-custom">Dependientes</label>
                            <select class="form-input" id="vg-dependientes"><option value="0">Solo yo</option><option value="1">1</option><option value="2">2</option><option value="3">3</option><option value="4">4+</option></select>
                        </div>
                    </div>
                </div>
                <div class="field-group"><label class="form-label-custom">Comentarios</label><textarea class="form-input" id="vg-comentarios" rows="3" placeholder="Cuéntanos más…" style="resize:vertical;min-height:80px;"></textarea></div>
                <div class="btn-actions">
                    <button type="button" class="btn-next" id="btnEnviarVidaGmm" style="width:100%;justify-content:center;">Solicitar Asesoría <i class="bi bi-check-lg"></i></button>
                </div>
            </div>
        </div>

        <!-- ─── EMPRESAS ─── -->
        <div class="ramo-form" id="ramoEmpresas" style="display:none;">
            <div class="form-card">
                <div class="emp-hero">
                    <div class="emp-hero-icon"><i class="bi bi-buildings-fill"></i></div>
                    <h2>Seguros para Empresas</h2>
                    <p class="step-subtitle">Paquetes corporativos diseñados a la medida de tu negocio.</p>
                </div>
                <div class="emp-beneficios">
                    <div class="emp-ben-item"><i class="bi bi-car-front-fill"></i><span>Flotillas vehiculares</span></div>
                    <div class="emp-ben-item"><i class="bi bi-heart-pulse-fill"></i><span>GMM para empleados</span></div>
                    <div class="emp-ben-item"><i class="bi bi-shield-fill-check"></i><span>Vida colectivo</span></div>
                    <div class="emp-ben-item"><i class="bi bi-briefcase-fill"></i><span>Responsabilidad civil</span></div>
                </div>
                <div class="section-divider" style="margin-top:24px;"></div>
                <div class="field-group"><label class="form-label-custom">Representante <span class="required">*</span></label><input type="text" class="form-input" id="emp-nombre" placeholder="Carlos Ramírez"><div class="error-msg" id="error-emp-nombre">Campo obligatorio.</div></div>
                <div class="field-group"><label class="form-label-custom">Empresa <span class="required">*</span></label><input type="text" class="form-input" id="emp-empresa" placeholder="Nombre de tu empresa"><div class="error-msg" id="error-emp-empresa">Campo obligatorio.</div></div>
                <div class="field-group"><label class="form-label-custom">Giro / Industria</label><input type="text" class="form-input" id="emp-giro" placeholder="Ej. Transporte, Manufactura, Retail…"></div>
                <div class="row-fields">
                    <div class="field-group"><label class="form-label-custom">Correo corporativo <span class="required">*</span></label><input type="email" class="form-input" id="emp-correo" placeholder="correo@empresa.com"><div class="error-msg" id="error-emp-correo">Correo inválido.</div></div>
                    <div class="field-group"><label class="form-label-custom">Teléfono <span class="required">*</span></label><input type="tel" class="form-input" id="emp-telefono" placeholder="55 1234 5678" maxlength="10"><div class="error-msg" id="error-emp-telefono">Campo obligatorio.</div></div>
                </div>
                <div class="field-group"><label class="form-label-custom">Número de empleados</label>
                    <select class="form-input" id="emp-empleados"><option value="">Selecciona</option><option value="1_10">1 – 10</option><option value="11_50">11 – 50</option><option value="51_200">51 – 200</option><option value="200+">Más de 200</option></select>
                </div>
                <div class="field-group"><label class="form-label-custom">¿Qué servicios te interesan?</label>
                    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:4px;">
                        <label class="check-pill"><input type="checkbox" value="flotilla"><span>Flotilla</span></label>
                        <label class="check-pill"><input type="checkbox" value="gmm"><span>GMM empleados</span></label>
                        <label class="check-pill"><input type="checkbox" value="vida"><span>Vida colectivo</span></label>
                        <label class="check-pill"><input type="checkbox" value="rc"><span>Resp. Civil</span></label>
                    </div>
                </div>
                <div class="field-group"><label class="form-label-custom">Mensaje</label><textarea class="form-input" id="emp-mensaje" rows="3" placeholder="Cuéntanos sobre tus necesidades…" style="resize:vertical;min-height:80px;"></textarea></div>
                <div class="btn-actions">
                    <button type="button" id="btnEnviarEmpresa" class="btn-next" style="width:100%;justify-content:center;">Solicitar Asesoría <i class="bi bi-check-lg"></i></button>
                </div>
            </div>
        </div>

        <!-- ─── CONFIRMACIÓN ─── -->
        <div class="ramo-form" id="ramoConfirmacion" style="display:none;">
            <div class="form-card">
                <div style="text-align:center;margin-bottom:18px;">
                    <i class="bi bi-check-circle-fill" id="iconoConfirmacion" style="font-size:3.5rem;color:#22c55e;"></i>
                    <h2 id="tituloConfirmacion" style="margin-top:12px;">¡Cita Registrada!</h2>
                    <p class="step-subtitle">Tu solicitud fue procesada correctamente.</p>
                </div>
                <div id="mensajePersonalizado" style="display:none;"></div>
                <div class="section-divider"></div>

                <!-- Leyenda + canales extra (solo flujo vehicular con cita; se muestra por JS) -->
                <div id="confCanalesExtras" style="display:none;margin-bottom:20px;">
                    <div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:12px;padding:14px 18px;margin-bottom:14px;font-size:0.92rem;line-height:1.55;color:#0f172a;text-align:center;">
                        <strong>Revisa tu correo.</strong> Te enviamos los datos de tu cita por email.
                        Si además quieres recibir la información o gestionar tu cita por <strong>WhatsApp</strong> o <strong>Telegram</strong>, usa los botones de abajo (según lo que hayas elegido al confirmar).
                    </div>
                    <!--
                        Botones de WhatsApp y Telegram:
                        Se muestran cuando el usuario eligió ese canal en el paso 6.
                        Si el servidor devuelve whatsapp_link / telegram_link, se usa ese URL; si no, fallbacks en JS.
                    -->
                    <div id="whatsappBotSection" style="display:none;">
                        <a id="whatsappBotBtn" href="#" target="_blank" rel="noopener" class="bot-btn wa-bot-btn"
                           style="display:flex;align-items:center;gap:10px;justify-content:center;padding:12px 20px;background:#25d366;color:#fff;border-radius:12px;text-decoration:none;font-weight:600;font-size:.95rem;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                            Abrir WhatsApp para gestionar mi cita
                        </a>
                    </div>
                    <div id="telegramSection" style="display:none;margin-top:10px;">
                        <a id="telegramBtn" href="#" target="_blank" rel="noopener" class="bot-btn tg-bot-btn"
                           style="display:flex;align-items:center;gap:10px;justify-content:center;padding:12px 20px;background:#0088cc;color:#fff;border-radius:12px;text-decoration:none;font-weight:600;font-size:.95rem;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221l-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12l-6.871 4.326-2.962-.924c-.643-.204-.657-.643.136-.953l11.57-4.461c.537-.194 1.006.131.833.94z"/></svg>
                            Abrir Telegram para gestionar mi cita
                        </a>
                    </div>
                </div>

                <div class="confirmation-summary" id="confSummary">
                    <div class="conf-section" id="confPersonal">
                        <h4 class="conf-section-title"><i class="bi bi-person"></i> Datos Personales</h4>
                        <div class="conf-row"><span class="conf-label">Nombre:</span><span class="conf-value" id="conf-nombre">-</span></div>
                        <div class="conf-row"><span class="conf-label">Correo:</span><span class="conf-value" id="conf-correo">-</span></div>
                        <div class="conf-row"><span class="conf-label">Teléfono:</span><span class="conf-value" id="conf-telefono">-</span></div>
                        <div class="conf-row" id="conf-row-edad"><span class="conf-label">Edad:</span><span class="conf-value" id="conf-edad">-</span></div>
                        <div class="conf-row" id="conf-row-sexo"><span class="conf-label">Sexo:</span><span class="conf-value" id="conf-sexo">-</span></div>
                        <div class="conf-row" id="conf-row-ecivil"><span class="conf-label">Estado Civil:</span><span class="conf-value" id="conf-ecivil">-</span></div>
                    </div>
                    <div class="conf-section" id="confDireccion">
                        <h4 class="conf-section-title"><i class="bi bi-geo-alt"></i> Dirección</h4>
                        <div class="conf-row"><span class="conf-label">Dirección:</span><span class="conf-value" id="conf-direccion">-</span></div>
                        <div class="conf-row"><span class="conf-label">Ciudad / Estado:</span><span class="conf-value" id="conf-ciudad">-</span></div>
                        <div class="conf-row"><span class="conf-label">CP:</span><span class="conf-value" id="conf-cp">-</span></div>
                    </div>
                    <div class="conf-section" id="confVehiculo">
                        <h4 class="conf-section-title"><i class="bi bi-car-front"></i> Vehículo</h4>
                        <div class="conf-row"><span class="conf-label">Tipo:</span><span class="conf-value" id="conf-tipo">-</span></div>
                        <div class="conf-row"><span class="conf-label">Vehículo:</span><span class="conf-value" id="conf-auto">-</span></div>
                        <div class="conf-row"><span class="conf-label">Asegurado:</span><span class="conf-value" id="conf-asegurado">-</span></div>
                    </div>
                    <div class="conf-section" id="confCita">
                        <h4 class="conf-section-title"><i class="bi bi-calendar-event"></i> Cita Programada</h4>
                        <div class="conf-row"><span class="conf-label">Tipo:</span><span class="conf-value" id="conf-tipo-cita">-</span></div>
                        <div class="conf-row"><span class="conf-label">Fecha:</span><span class="conf-value" id="conf-fecha">-</span></div>
                        <div class="conf-row"><span class="conf-label">Hora:</span><span class="conf-value" id="conf-hora">-</span></div>
                        <div class="conf-row"><span class="conf-label">Canal(es):</span><span class="conf-value" id="conf-canal">-</span></div>
                    </div>
                </div>

                <div style="margin-top:20px;text-align:center;">
                    <a href="/formulario" class="btn-prev" style="display:inline-flex;text-decoration:none;margin:0 auto;">Nueva cotización</a>
                </div>
            </div>
        </div>

    </main>
</div>

<div style="text-align:center;padding:20px;font-size:13px;color:#94a3b8;">©LUX Seguros. Todos los derechos reservados.</div>

<script src="/js/iconos.js"></script>
<script src="/js/formulario_cliente.js"></script>

<script nonce="<?= htmlspecialchars($nonce, ENT_QUOTES) ?>">
/* ══════════════════════════════════════════════════════════
   ESTADO GLOBAL
══════════════════════════════════════════════════════════ */
var selectedRamo        = null;
var selectedVidaGmmRamo = null;
// Números de WhatsApp/Telegram para fallback (si el servidor no devuelve link)
var WA_NUMERO_NEGOCIO   = '5215514605959'; // número del bot WhatsApp
var TG_BOT_USERNAME     = 'SegurosLuxBot'; // username del bot sin @

var _vehRadioSel        = { tipoVehiculo: null, asegurado: null, tiempoCompra: null, recienComprado: null, sexo: null, tipoCita: null };
var _vehMiniData        = {};
var _vehCanales         = [];   // array de strings: 'email', 'whatsapp', 'telegram'
var _vehSelectedDate    = null;
var _vehSelectedTime    = null;

/* ══════════════════════════════════════════════════════════
   INICIALIZACIÓN
══════════════════════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', function () {

    // Años del select vehículo
    var selAnio = document.getElementById('anioVehiculo');
    if (selAnio) {
        var curY = new Date().getFullYear();
        for (var y = curY + 1; y >= curY - 30; y--) {
            var o = document.createElement('option');
            o.value = y; o.textContent = y;
            selAnio.appendChild(o);
        }
    }

    // Edad auto-calculada desde fecha nacimiento
    var fnInput = document.getElementById('fechaNacimiento');
    if (fnInput) {
        fnInput.addEventListener('change', function () {
            var dob   = new Date(this.value);
            var today = new Date();
            var age   = today.getFullYear() - dob.getFullYear();
            var m     = today.getMonth() - dob.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) age--;
            var edadEl = document.getElementById('edad');
            if (edadEl) edadEl.value = age > 0 ? age + ' años' : '';
        });
    }

    // ── Botones de ramo (sidebar) ────────────────────────────────────────────
    document.querySelectorAll('.ramo-btn[data-ramo]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            selectRamo(this.dataset.ramo, this);
        });
    });

    // ── Option cards genéricas (sexo, asegurado, tiempoCompra, tipoCita, recienComprado) ──
    document.querySelectorAll('.option-card[data-group]').forEach(function (card) {
        card.addEventListener('click', function () {
            var group = this.dataset.group;
            var parent = this.closest('[id$="Options"]') || this.parentNode;
            parent.querySelectorAll('.option-card').forEach(function (c) { c.classList.remove('selected'); });
            this.classList.add('selected');
            _vehRadioSel[group] = this.dataset.value;
            var errEl = document.getElementById('error-' + group);
            if (errEl) errEl.classList.remove('show');
        });
    });

    // ── Option cards tipo vehículo (sin data-group, lógica especial) ─────────
    document.querySelectorAll('#tipoVehiculoOptions .option-card').forEach(function (card) {
        card.addEventListener('click', function () {
            selectTipoVehiculo(this.dataset.value, this);
        });
    });

    // ── Mini toggles ─────────────────────────────────────────────────────────
    document.addEventListener('click', function (e) {
        var tog = e.target.closest('.mini-tog');
        if (!tog) return;
        var container = tog.closest('.mini-toggle-opts');
        if (!container) return;
        container.querySelectorAll('.mini-tog').forEach(function (t) { t.classList.remove('selected'); });
        tog.classList.add('selected');
        var group = container.dataset.group;
        if (group) _vehMiniData[group] = tog.dataset.val;
    });

    // ── Canal cards (multi-select) — paso 6 ──────────────────────────────────
    document.querySelectorAll('.canal-card[data-canal]').forEach(function (card) {
        card.addEventListener('click', function () {
            this.classList.toggle('selected');
            var canal = this.dataset.canal;
            var idx = _vehCanales.indexOf(canal);
            if (idx === -1) _vehCanales.push(canal);
            else _vehCanales.splice(idx, 1);
            // Limpiar error al seleccionar algo
            var errEl = document.getElementById('error-canal');
            if (errEl) errEl.classList.remove('show');
        });
    });

    // ── Botones Siguiente ────────────────────────────────────────────────────
    document.querySelectorAll('.btn-next[data-nextstep]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            nextStep(parseInt(this.dataset.nextstep));
        });
    });

    // ── Botones Atrás ────────────────────────────────────────────────────────
    document.querySelectorAll('.btn-prev[data-prevstep]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            vPrev(parseInt(this.dataset.prevstep));
        });
    });

    // ── Botón Confirmar Cita (submit vehicular — paso 6) ──────────────────────
    var btnSubmit = document.getElementById('btnSubmitVehicular');
    if (btnSubmit) {
        btnSubmit.addEventListener('click', submitVehicular);
    }

    // ── Botón Enviar Vida/GMM ─────────────────────────────────────────────────
    var btnVG = document.getElementById('btnEnviarVidaGmm');
    if (btnVG) {
        btnVG.addEventListener('click', submitVidaGmm);
    }

    // ── Botón Enviar Empresa ──────────────────────────────────────────────────
    var btnEmp = document.getElementById('btnEnviarEmpresa');
    if (btnEmp) {
        btnEmp.addEventListener('click', submitEmpresa);
    }

    // ── Calendario ───────────────────────────────────────────────────────────
    var btnCalPrev = document.getElementById('btnCalPrev');
    var btnCalNext = document.getElementById('btnCalNext');
    if (btnCalPrev) btnCalPrev.addEventListener('click', function () { changeMonth(-1); });
    if (btnCalNext) btnCalNext.addEventListener('click', function () { changeMonth(1); });

    renderCalendar();
    renderTimeSlots();
    autoSelectToday();
});

/* ══════════════════════════════════════════════════════════
   NAVEGACIÓN VEHICULAR (6 pasos)
══════════════════════════════════════════════════════════ */
function showVStep(n) {
    document.querySelectorAll('#ramoVehicular .form-step').forEach(function (s) { s.classList.remove('active'); });
    var t = document.querySelector('#ramoVehicular .form-step[data-vstep="' + n + '"]');
    if (t) t.classList.add('active');
    var cEl = document.getElementById('veh-currentStep');
    if (cEl) cEl.textContent = n;
    document.querySelectorAll('#veh-progressBar .progress-segment').forEach(function (seg, i) {
        seg.classList.remove('active', 'done');
        if (i < n - 1) seg.classList.add('done');
        if (i === n - 1) seg.classList.add('active');
    });
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function nextStep(fromStep) {
    if (!validateVStep(fromStep)) return;
    showVStep(fromStep + 1);
}

function vPrev(fromStep) {
    if (fromStep <= 1) volverASeleccionRamo();
    else showVStep(fromStep - 1);
}

/* ── Validación por paso ────────────────────────────────────────────────────── */
function validateVStep(step) {
    var valid = true;
    function showE(id) { var e = document.getElementById('error-' + id); if (e) e.classList.add('show'); }
    function clearE(id) { var e = document.getElementById('error-' + id); if (e) e.classList.remove('show'); }

    if (step === 1) {
        clearE('tipoVehiculo');
        if (!_vehRadioSel.tipoVehiculo) { showE('tipoVehiculo'); valid = false; }
        ['marca', 'modelo', 'anioVehiculo'].forEach(function (f) {
            clearE(f);
            var el = document.getElementById(f);
            if (!el || !el.value.trim()) { showE(f); valid = false; }
        });
        clearE('recienComprado');
        if (!_vehRadioSel.recienComprado) { showE('recienComprado'); valid = false; }
    }
    if (step === 2) {
        clearE('asegurado');
        if (!_vehRadioSel.asegurado) { showE('asegurado'); valid = false; }
        clearE('tiempoCompra');
        if (!_vehRadioSel.tiempoCompra) { showE('tiempoCompra'); valid = false; }
    }
    if (step === 3) {
        ['nombre', 'apellidoPaterno', 'apellidoMaterno', 'fechaNacimiento', 'estadoCivil'].forEach(function (f) {
            clearE(f);
            var el = document.getElementById(f);
            if (!el || !el.value.trim()) { showE(f); valid = false; }
        });
        clearE('correo');
        var correo = (document.getElementById('correo') || {}).value || '';
        if (!correo.trim() || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo)) { showE('correo'); valid = false; }
        clearE('telefono');
        var tel = ((document.getElementById('telefono') || {}).value || '').replace(/\D/g, '');
        if (!tel || tel.length < 8) { showE('telefono'); valid = false; }
        clearE('sexo');
        if (!_vehRadioSel.sexo) { showE('sexo'); valid = false; }
    }
    if (step === 4) {
        ['calle', 'colonia', 'codigoPostal', 'ciudad', 'estado'].forEach(function (f) {
            clearE(f);
            var el = document.getElementById(f);
            if (!el || !el.value.trim()) { showE(f); valid = false; }
        });
    }
    if (step === 5) {
        clearE('tipoCita');
        if (!_vehRadioSel.tipoCita) { showE('tipoCita'); valid = false; }
        clearE('fecha');
        if (!_vehSelectedDate) { showE('fecha'); valid = false; }
        clearE('hora');
        if (!_vehSelectedTime) { showE('hora'); valid = false; }
    }
    // Paso 6: validar que se eligió al menos un canal
    if (step === 6) {
        clearE('canal');
        if (_vehCanales.length === 0) { showE('canal'); valid = false; }
    }
    return valid;
}

/* ══════════════════════════════════════════════════════════
   TIPO DE VEHÍCULO
══════════════════════════════════════════════════════════ */
var MARCAS_POR_TIPO_LOCAL = {
    auto:      ['Audi','BMW','Chevrolet','Ford','Honda','Hyundai','Jeep','KIA','Mazda','Mercedes-Benz','Mitsubishi','Nissan','Peugeot','Porsche','Renault','SEAT','Suzuki','Toyota','Volkswagen','Volvo','Otra'],
    moto:      ['BMW Motorrad','Ducati','Harley-Davidson','Honda','Kawasaki','KTM','Royal Enfield','Suzuki','Triumph','Yamaha','Otra'],
    camioneta: ['Audi','BMW','Chevrolet','Ford','Honda','Hyundai','Jeep','KIA','Land Rover','Mazda','Mercedes-Benz','Mitsubishi','Nissan','Suzuki','Toyota','Volkswagen','Volvo','Otra'],
    pickup:    ['Chevrolet','Ford','GMC','Isuzu','Mazda','Mitsubishi','Nissan','RAM','Toyota','Volkswagen','Otra']
};

function selectTipoVehiculo(tipo, el) {
    var parent = el.parentNode;
    parent.querySelectorAll('.option-card').forEach(function (c) { c.classList.remove('selected'); });
    el.classList.add('selected');
    _vehRadioSel.tipoVehiculo = tipo;
    var errEl = document.getElementById('error-tipoVehiculo');
    if (errEl) errEl.classList.remove('show');

    var sel = document.getElementById('marca');
    if (sel) {
        sel.innerHTML = '<option value="">Selecciona una marca</option>';
        (MARCAS_POR_TIPO_LOCAL[tipo] || []).slice().sort().forEach(function (m) {
            var o = document.createElement('option');
            o.value = m.toLowerCase().replace(/[\s/-]+/g, '_');
            o.textContent = m;
            sel.appendChild(o);
        });
    }

    var miniMap = { auto: 'miniFormAuto', moto: 'miniFormMoto', camioneta: 'miniFormCamioneta', pickup: 'miniFormPickup' };
    Object.values(miniMap).forEach(function (id) { var el2 = document.getElementById(id); if (el2) el2.style.display = 'none'; });
    _vehMiniData = {};
    var wrapper = document.getElementById('vehDatosWrapper');
    if (wrapper) wrapper.style.display = 'block';
    var target = document.getElementById(miniMap[tipo]);
    if (target) {
        target.style.display = 'block';
        setTimeout(function () { target.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }, 50);
    }
}

/* ══════════════════════════════════════════════════════════
   CALENDARIO
══════════════════════════════════════════════════════════ */
var _vehCalMonth = new Date().getMonth();
var _vehCalYear  = new Date().getFullYear();
var _MONTHS_ES   = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

function autoSelectToday() {
    var hoy = new Date(); hoy.setHours(0, 0, 0, 0);
    if (hoy.getDay() === 0) return;
    var yyyy = hoy.getFullYear();
    var mm   = String(hoy.getMonth() + 1).padStart(2, '0');
    var dd   = String(hoy.getDate()).padStart(2, '0');
    var ds   = yyyy + '-' + mm + '-' + dd;
    _vehSelectedDate = ds;
    cargarHorasOcupadas(ds);
    setTimeout(function () {
        var cell = document.querySelector('.day-cell[data-date="' + ds + '"]');
        if (cell) cell.classList.add('selected');
    }, 0);
}

function renderCalendar() {
    var grid = document.getElementById('calendarGrid');
    if (!grid) return;
    grid.innerHTML = '';
    var titleEl = document.getElementById('calendarTitle');
    if (titleEl) titleEl.textContent = _MONTHS_ES[_vehCalMonth] + ' ' + _vehCalYear;
    ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'].forEach(function (d) {
        var c = document.createElement('div'); c.className = 'day-name'; c.textContent = d; grid.appendChild(c);
    });
    var firstDay    = new Date(_vehCalYear, _vehCalMonth, 1).getDay();
    var daysInMonth = new Date(_vehCalYear, _vehCalMonth + 1, 0).getDate();
    var today       = new Date(); today.setHours(0, 0, 0, 0);
    var todayStr    = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0') + '-' + String(today.getDate()).padStart(2, '0');
    for (var i = 0; i < firstDay; i++) { var e = document.createElement('div'); e.className = 'day-cell empty'; grid.appendChild(e); }
    for (var d = 1; d <= daysInMonth; d++) {
        var cell = document.createElement('div'); cell.className = 'day-cell'; cell.textContent = d;
        var dow     = new Date(_vehCalYear, _vehCalMonth, d).getDay();
        var dateStr = _vehCalYear + '-' + String(_vehCalMonth + 1).padStart(2, '0') + '-' + String(d).padStart(2, '0');
        if (dateStr < todayStr || dow === 0) {
            cell.classList.add('disabled');
        } else {
            cell.setAttribute('data-date', dateStr);
            cell.addEventListener('click', (function (ds) {
                return function () {
                    if (this.classList.contains('disabled')) return;
                    document.querySelectorAll('.day-cell.selected').forEach(function (c) { c.classList.remove('selected'); });
                    this.classList.add('selected');
                    _vehSelectedDate = ds;
                    var errEl = document.getElementById('error-fecha'); if (errEl) errEl.classList.remove('show');
                    cargarHorasOcupadas(ds);
                };
            })(dateStr));
        }
        if (dateStr === todayStr) cell.classList.add('today');
        if (_vehSelectedDate === dateStr) cell.classList.add('selected');
        grid.appendChild(cell);
    }
}

function changeMonth(dir) {
    _vehCalMonth += dir;
    if (_vehCalMonth > 11) { _vehCalMonth = 0; _vehCalYear++; }
    if (_vehCalMonth < 0)  { _vehCalMonth = 11; _vehCalYear--; }
    var now = new Date();
    if (_vehCalYear < now.getFullYear() || (_vehCalYear === now.getFullYear() && _vehCalMonth < now.getMonth())) {
        _vehCalMonth = now.getMonth(); _vehCalYear = now.getFullYear();
    }
    renderCalendar();
}

function renderTimeSlots() {
    var container = document.getElementById('timeSlots');
    if (!container) return;
    container.innerHTML = '';
    ['09:00','09:30','10:00','10:30','11:00','11:30','12:00','12:30',
     '13:00','13:30','14:00','14:30','16:00','16:30','17:00','17:30'].forEach(function (t) {
        var slot = document.createElement('div'); slot.className = 'time-slot';
        slot.textContent = t; slot.setAttribute('data-time', t);
        slot.addEventListener('click', function () {
            if (this.classList.contains('disabled')) return;
            document.querySelectorAll('.time-slot.selected').forEach(function (s) { s.classList.remove('selected'); });
            this.classList.add('selected');
            _vehSelectedTime = this.getAttribute('data-time');
            var errEl = document.getElementById('error-hora'); if (errEl) errEl.classList.remove('show');
        });
        container.appendChild(slot);
    });
}

function cargarHorasOcupadas(fecha) {
    _vehSelectedTime = null;
    document.querySelectorAll('.time-slot').forEach(function (s) { s.classList.remove('selected', 'disabled'); });
    var container = document.getElementById('timeSlots');
    if (container) container.style.opacity = '0.5';
    var hoyStr  = new Date().toISOString().split('T')[0];
    var esHoy   = (fecha === hoyStr);
    var ahora   = new Date(); ahora.setMinutes(ahora.getMinutes() + 30);
    var horaLim = String(ahora.getHours()).padStart(2, '0') + ':' + String(ahora.getMinutes()).padStart(2, '0');
    fetch('/horas-ocupadas?fecha=' + fecha)
        .then(function (r) { return r.json(); })
        .then(function (data) {
            var ocupadas = Array.isArray(data) ? data : (data && data.horas ? data.horas : []);
            if (!Array.isArray(ocupadas)) ocupadas = [];
            if (container) container.style.opacity = '1';
            document.querySelectorAll('.time-slot').forEach(function (slot) {
                var hora = slot.getAttribute('data-time');
                if (ocupadas.indexOf(hora) !== -1 || (esHoy && hora <= horaLim)) slot.classList.add('disabled');
            });
        }).catch(function () {
            if (container) container.style.opacity = '1';
            if (esHoy) {
                document.querySelectorAll('.time-slot').forEach(function (slot) {
                    if (slot.getAttribute('data-time') <= horaLim) slot.classList.add('disabled');
                });
            }
        });
}

/* ══════════════════════════════════════════════════════════
   SUBMIT VEHICULAR — paso 6
   El botón "Confirmar Cita" ya NO llama a nextStep sino directo aquí.
   validateVStep(6) valida que haya al menos un canal seleccionado.
══════════════════════════════════════════════════════════ */
function submitVehicular() {
    // Validar paso 6 antes de enviar
    if (!validateVStep(6)) return;

    var btn = document.getElementById('btnSubmitVehicular');
    if (btn) { btn.disabled = true; btn.textContent = 'Enviando…'; }

    var nombre   = (document.getElementById('nombre') || {}).value || '';
    var apPat    = (document.getElementById('apellidoPaterno') || {}).value || '';
    var apMat    = (document.getElementById('apellidoMaterno') || {}).value || '';
    var correo   = (document.getElementById('correo') || {}).value || '';
    var ladaEl   = document.getElementById('lada');
    var telEl    = document.getElementById('telefono');
    var telefono = '+' + (ladaEl ? ladaEl.value : '52') + (telEl ? telEl.value.trim() : '');
    var edadTxt  = ((document.getElementById('edad') || {}).value || '').trim();
    var marcaSel = document.getElementById('marca');
    var marcaTxt = marcaSel ? marcaSel.options[marcaSel.selectedIndex].text : '';
    var modelo   = (document.getElementById('modelo') || {}).value || '';
    var anio     = (document.getElementById('anioVehiculo') || {}).value || '';
    var estadoSel = document.getElementById('estado');
    var estadoTxt = estadoSel && estadoSel.options[estadoSel.selectedIndex]
        ? estadoSel.options[estadoSel.selectedIndex].text : '';
    var ecSel = document.getElementById('estadoCivil');
    var ecTxt = ecSel && ecSel.options[ecSel.selectedIndex]
        ? ecSel.options[ecSel.selectedIndex].text : '';
    var calleTxt = ((document.getElementById('calle') || {}).value || '').trim();
    var colTxt   = ((document.getElementById('colonia') || {}).value || '').trim();
    var ciuTxt   = ((document.getElementById('ciudad') || {}).value || '').trim();
    var cpTxt    = ((document.getElementById('codigoPostal') || {}).value || '').trim();

    var lblTipoVeh = (_vehRadioSel.tipoVehiculo || '').trim();
    if (lblTipoVeh) lblTipoVeh = lblTipoVeh.charAt(0).toUpperCase() + lblTipoVeh.slice(1);

    var parts    = (_vehSelectedDate || '').split('-');
    var fechaFmt = parts[2] + ' de ' + _MONTHS_ES[parseInt(parts[1]) - 1] + ' ' + parts[0];

    var comentariosExtra = '';
    if (Object.keys(_vehMiniData).length) {
        comentariosExtra = '[Datos adicionales] ' + Object.keys(_vehMiniData).map(function (k) {
            return k + ': ' + _vehMiniData[k];
        }).join(' | ');
    }

    var payload = {
        nombre: nombre.trim(), apellidoPaterno: apPat.trim(), apellidoMaterno: apMat.trim(),
        fechaNacimiento: (document.getElementById('fechaNacimiento') || {}).value || '',
        correo: correo.trim(), telefono: telefono,
        sexo: _vehRadioSel.sexo || '', estadoCivil: (document.getElementById('estadoCivil') || {}).value || '',
        calle: (document.getElementById('calle') || {}).value || '',
        colonia: (document.getElementById('colonia') || {}).value || '',
        ciudad: (document.getElementById('ciudad') || {}).value || '',
        estado: (document.getElementById('estado') || {}).value || '',
        codigoPostal: (document.getElementById('codigoPostal') || {}).value || '',
        marca: (document.getElementById('marca') || {}).value || '',
        modelo: modelo, anioVehiculo: anio,
        tipoVehiculo: _vehRadioSel.tipoVehiculo,
        recienComprado: _vehRadioSel.recienComprado,
        asegurado: _vehRadioSel.asegurado,
        tiempoCompra: _vehRadioSel.tiempoCompra,
        tipoCita: _vehRadioSel.tipoCita,
        fechaCita: _vehSelectedDate, horaCita: _vehSelectedTime,
        canalNotificacion: _vehCanales.join(','),
        comentarios: comentariosExtra
    };

    fetch('/api/clientes', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(function (r) { return r.json(); })
    .then(function (d) {
        mostrarConfirmacionVehicular(
            d, nombre, apPat, apMat, correo, telefono,
            edadTxt, marcaTxt, modelo, anio, lblTipoVeh,
            calleTxt, colTxt, ciuTxt, estadoTxt, cpTxt, ecTxt, fechaFmt
        );
    })
    .catch(function () {
        // Aunque falle el fetch, mostrar confirmación con los datos locales
        mostrarConfirmacionVehicular(
            {}, nombre, apPat, apMat, correo, telefono,
            edadTxt, marcaTxt, modelo, anio, lblTipoVeh,
            calleTxt, colTxt, ciuTxt, estadoTxt, cpTxt, ecTxt, fechaFmt
        );
    });
}

function mostrarConfirmacionVehicular(d, nombre, apPat, apMat, correo, telefono, edadTxt,
    marcaTxt, modelo, anio, lblTipoVeh, calleTxt, colTxt, ciuTxt, estadoTxt, cpTxt, ecTxt, fechaFmt)
{
    ['ramoVehicular', 'ramoVidaGmm', 'ramoEmpresas'].forEach(function (id) {
        var el = document.getElementById(id); if (el) el.style.display = 'none';
    });
    document.getElementById('ramoConfirmacion').style.display = 'block';
    var extrasWrap = document.getElementById('confCanalesExtras');
    if (extrasWrap) extrasWrap.style.display = 'block';

    function setConf(id, val) { var el = document.getElementById(id); if (el) el.textContent = val; }

    setConf('conf-nombre',    nombre + ' ' + apPat + ' ' + apMat);
    setConf('conf-correo',    correo);
    setConf('conf-telefono',  telefono);
    setConf('conf-edad',      edadTxt || '—');
    setConf('conf-sexo',      _vehRadioSel.sexo ? (_vehRadioSel.sexo.charAt(0).toUpperCase() + _vehRadioSel.sexo.slice(1)) : '—');
    setConf('conf-ecivil',    ecTxt || '—');
    setConf('conf-direccion', (calleTxt || colTxt) ? (calleTxt + ', ' + colTxt) : '—');
    setConf('conf-ciudad',    (ciuTxt || estadoTxt) ? (ciuTxt + ', ' + estadoTxt) : '—');
    setConf('conf-cp',        cpTxt || '—');
    setConf('conf-tipo',      lblTipoVeh || '—');
    setConf('conf-auto',      marcaTxt + ' ' + modelo + ' ' + anio);
    setConf('conf-asegurado', _vehRadioSel.asegurado === 'si' ? 'Sí' : (_vehRadioSel.asegurado === 'no' ? 'No' : '—'));
    setConf('conf-tipo-cita', _vehRadioSel.tipoCita === 'videollamada' ? 'Videollamada' : 'Llamada telefónica');
    setConf('conf-fecha',     fechaFmt);
    setConf('conf-hora',      (_vehSelectedTime || '') + ' hrs');
    setConf('conf-canal',     _vehCanales.join(', '));

    // ── Mensaje de confirmación ───────────────────────────────────────────────
    var cMsg = document.getElementById('mensajePersonalizado');
    if (cMsg) {
        cMsg.innerHTML = iconoSvg('stars') + ' <strong>¡Cita registrada con éxito!</strong><br>'
            + 'En breve un asesor de Seguros LUX se pondrá en contacto contigo.';
        cMsg.style.cssText = 'display:block;background:#e8f5e9;color:#2e7d32;border:1px solid #a5d6a7;'
            + 'border-radius:12px;padding:14px 18px;margin-bottom:16px;font-size:.9rem;text-align:center;';
    }

    // ── Botones WhatsApp / Telegram ───────────────────────────────────────────
    // Se muestran si el usuario eligió ese canal.
    // URL preferida: la que devuelve el servidor (d.whatsapp_link / d.telegram_link).
    // Fallback: construimos el link nosotros con los números hardcoded.

    var waSec = document.getElementById('whatsappBotSection');
    var tgSec = document.getElementById('telegramSection');
    var waBtn = document.getElementById('whatsappBotBtn');
    var tgBtn = document.getElementById('telegramBtn');

    var eligioWa = (_vehCanales.indexOf('whatsapp') !== -1);
    var eligioTg = (_vehCanales.indexOf('telegram') !== -1);

    if (eligioWa && waSec && waBtn) {
        // Link WA: abre conversación con el número de negocio y envía mensaje de inicio
        var waMsg = encodeURIComponent(
            'Hola, acabo de agendar una cita para el ' + fechaFmt +
            ' a las ' + (_vehSelectedTime || '') + ' hrs.'
        );
        var waLink = d.whatsapp_link || ('https://wa.me/' + WA_NUMERO_NEGOCIO + '?text=' + waMsg);
        waBtn.href = waLink;
        waSec.style.display = 'block';
    } else if (waSec) {
        waSec.style.display = 'none';
    }

    if (eligioTg && tgSec && tgBtn) {
        // Link TG: abre el bot. Si el servidor devolvió un deep link con token, mejor.
        var tgLink = d.telegram_link || ('https://t.me/' + TG_BOT_USERNAME);
        tgBtn.href = tgLink;
        tgSec.style.display = 'block';
    } else if (tgSec) {
        tgSec.style.display = 'none';
    }

    if (window.innerWidth < 900) {
        document.getElementById('formMain').scrollIntoView({ behavior: 'smooth' });
    }
}

/* ══════════════════════════════════════════════════════════
   SELECCIÓN DE RAMO
══════════════════════════════════════════════════════════ */
function selectRamo(ramo, el) {
    document.querySelectorAll('.ramo-btn').forEach(function (b) { b.classList.remove('active'); });
    if (el) el.classList.add('active');
    selectedRamo = ramo;
    ['formEmpty', 'ramoVehicular', 'ramoVidaGmm', 'ramoEmpresas', 'ramoConfirmacion'].forEach(function (id) {
        var domEl = document.getElementById(id); if (domEl) domEl.style.display = 'none';
    });
    if (ramo === 'vehicular') {
        document.getElementById('ramoVehicular').style.display = 'block';
        showVStep(1);
    } else if (ramo === 'vida' || ramo === 'gmm') {
        selectedVidaGmmRamo = ramo;
        var titleEl    = document.getElementById('vgTitle');
        var subtitleEl = document.getElementById('vgSubtitle');
        var gmmExtraEl = document.getElementById('gmmExtra');
        var fechaEl    = document.getElementById('vg-fecha');
        if (titleEl)    titleEl.textContent    = ramo === 'gmm' ? 'Gastos Médicos Mayores' : 'Seguro de Vida';
        if (subtitleEl) subtitleEl.textContent = ramo === 'gmm'
            ? 'Cotización personalizada para ti y tu familia.'
            : 'Un especialista te orientará sobre la mejor opción.';
        if (gmmExtraEl) gmmExtraEl.style.display = ramo === 'gmm' ? 'block' : 'none';
        if (fechaEl) {
            var minD = new Date(); minD.setDate(minD.getDate() + 1);
            fechaEl.min = minD.toISOString().split('T')[0];
        }
        document.getElementById('ramoVidaGmm').style.display = 'block';
    } else if (ramo === 'empresas') {
        document.getElementById('ramoEmpresas').style.display = 'block';
    }
    if (window.innerWidth < 900) document.getElementById('formMain').scrollIntoView({ behavior: 'smooth' });
}

function volverASeleccionRamo() {
    ['ramoVehicular', 'ramoVidaGmm', 'ramoEmpresas', 'ramoConfirmacion'].forEach(function (id) {
        var el = document.getElementById(id); if (el) el.style.display = 'none';
    });
    var empty = document.getElementById('formEmpty');
    if (empty) empty.style.display = 'block';
    document.querySelectorAll('.ramo-btn').forEach(function (b) { b.classList.remove('active'); });
    selectedRamo = null;
}

/* ══════════════════════════════════════════════════════════
   SUBMIT VIDA / GMM
══════════════════════════════════════════════════════════ */
function submitVidaGmm() {
    var nombre   = (document.getElementById('vg-nombre') || {}).value || '';
    var correo   = (document.getElementById('vg-correo') || {}).value || '';
    var telefono = (document.getElementById('vg-telefono') || {}).value || '';
    var fecha    = (document.getElementById('vg-fecha') || {}).value || '';
    var valid    = true;

    [['vg-nombre', 'error-vg-nombre'], ['vg-correo', 'error-vg-correo'],
     ['vg-telefono', 'error-vg-telefono'], ['vg-fecha', 'error-vg-fecha']].forEach(function (p) {
        var el = document.getElementById(p[0]); var er = document.getElementById(p[1]);
        if (!el || !el.value.trim()) { if (el) el.classList.add('error'); if (er) er.classList.add('show'); valid = false; }
        else { if (el) el.classList.remove('error'); if (er) er.classList.remove('show'); }
    });
    if (!valid) return;
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo.trim())) {
        document.getElementById('vg-correo').classList.add('error');
        document.getElementById('error-vg-correo').classList.add('show'); return;
    }
    var btn = document.getElementById('btnEnviarVidaGmm');
    if (btn) { btn.disabled = true; btn.textContent = 'Enviando…'; }
    var ramo = selectedVidaGmmRamo || 'vida';
    var payload = {
        nombre: nombre.trim(), correo: correo.trim(),
        telefono: '+52' + telefono.trim().replace(/\D/g, ''),
        fecha_preferida: fecha, ramo: ramo,
        dependientes: (document.getElementById('vg-dependientes') || { value: '0' }).value,
        comentarios: (document.getElementById('vg-comentarios') || {}).value || ''
    };
    fetch('/api/vida-solicitud', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) })
        .then(function (r) { return r.json(); })
        .then(function () { showConfirmacionVidaGmm(payload); })
        .catch(function () { showConfirmacionVidaGmm(payload); });
}

function showConfirmacionVidaGmm(p) {
    ['ramoVehicular', 'ramoVidaGmm', 'ramoEmpresas'].forEach(function (id) {
        var el = document.getElementById(id); if (el) el.style.display = 'none';
    });
    document.getElementById('ramoConfirmacion').style.display = 'block';
    // Ocultar secciones irrelevantes
    ['confVehiculo', 'confCita', 'confDireccion', 'conf-row-sexo', 'conf-row-ecivil', 'confCanalesExtras'].forEach(function (id) {
        var el = document.getElementById(id); if (el) el.style.display = 'none';
    });

    var tit = document.getElementById('tituloConfirmacion');
    if (tit) tit.textContent = p.ramo === 'gmm' ? '¡Solicitud GMM Recibida!' : '¡Solicitud de Vida Recibida!';

    function setConf(id, val) { var el = document.getElementById(id); if (el) el.textContent = val; }
    setConf('conf-nombre', p.nombre); setConf('conf-correo', p.correo); setConf('conf-telefono', p.telefono);

    var ramoLabel = p.ramo === 'gmm' ? iconoSvg('hospital') + ' Gastos Médicos Mayores' : iconoSvg('heart-fill') + ' Seguro de Vida';
    var MONTHS2   = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    var pts       = p.fecha_preferida.split('-');
    var fechaFmt  = pts[2] + ' de ' + (MONTHS2[parseInt(pts[1]) - 1] || '') + ' ' + pts[0];

    var cMsg = document.getElementById('mensajePersonalizado');
    if (cMsg) {
        cMsg.innerHTML = ramoLabel + '<br>Un asesor te contactará antes del <strong>' + fechaFmt + '</strong>.';
        cMsg.style.cssText = 'display:block;background:linear-gradient(135deg,#e0f2fe,#ddd6fe);color:#1e3a5f;'
            + 'border:1px solid #93c5fd;border-radius:12px;padding:14px 18px;margin-bottom:16px;font-size:.9rem;line-height:1.6;text-align:center;';
    }
    if (window.innerWidth < 900) document.getElementById('formMain').scrollIntoView({ behavior: 'smooth' });
}

/* ══════════════════════════════════════════════════════════
   SUBMIT EMPRESA
══════════════════════════════════════════════════════════ */
function submitEmpresa() {
    var fields = [['emp-nombre','error-emp-nombre'],['emp-empresa','error-emp-empresa'],['emp-correo','error-emp-correo'],['emp-telefono','error-emp-telefono']];
    var valid = true;
    fields.forEach(function (p) {
        var el = document.getElementById(p[0]); var er = document.getElementById(p[1]);
        if (!el.value.trim()) { el.classList.add('error'); er.classList.add('show'); valid = false; }
        else { el.classList.remove('error'); er.classList.remove('show'); }
    });
    if (!valid) return;
    var correo = document.getElementById('emp-correo').value.trim();
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo)) {
        document.getElementById('emp-correo').classList.add('error');
        document.getElementById('error-emp-correo').classList.add('show'); return;
    }
    var btn = document.getElementById('btnEnviarEmpresa');
    if (btn) { btn.disabled = true; btn.textContent = 'Enviando…'; }
    var payload = {
        nombre: document.getElementById('emp-nombre').value.trim(),
        empresa: document.getElementById('emp-empresa').value.trim(),
        giro_empresa: (document.getElementById('emp-giro') || {}).value?.trim() || '',
        correo: correo,
        telefono: '+52' + document.getElementById('emp-telefono').value.trim(),
        empleados: document.getElementById('emp-empleados').value,
        servicios: Array.from(document.querySelectorAll('#ramoEmpresas .check-pill input:checked'))
            .map(function (c) { return c.value; }).join(', '),
        mensaje: document.getElementById('emp-mensaje').value.trim(),
        ramo: 'empresas',
        comentarios: document.getElementById('emp-mensaje').value.trim(),
        fecha_preferida: new Date(Date.now() + 2 * 86400000).toISOString().split('T')[0]
    };
    fetch('/api/vida-solicitud', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) })
        .then(function (r) { return r.json(); })
        .then(function () { showConfirmacionSimple(payload.nombre, payload.correo, payload.telefono); })
        .catch(function () { showConfirmacionSimple(payload.nombre, payload.correo, payload.telefono); });
}

function showConfirmacionSimple(nombre, correo, telefono) {
    ['ramoVehicular', 'ramoVidaGmm', 'ramoEmpresas'].forEach(function (id) {
        var el = document.getElementById(id); if (el) el.style.display = 'none';
    });
    document.getElementById('ramoConfirmacion').style.display = 'block';
    ['confVehiculo', 'confCita', 'confDireccion', 'conf-row-edad', 'conf-row-sexo', 'conf-row-ecivil',
     'confCanalesExtras'].forEach(function (id) {
        var el = document.getElementById(id); if (el) el.style.display = 'none';
    });
    var tit = document.getElementById('tituloConfirmacion');
    if (tit) tit.textContent = '¡Solicitud Empresarial Recibida!';
    function setConf(id, val) { var el = document.getElementById(id); if (el) el.textContent = val; }
    setConf('conf-nombre', nombre); setConf('conf-correo', correo); setConf('conf-telefono', telefono);
    var cMsg = document.getElementById('mensajePersonalizado');
    if (cMsg) {
        cMsg.innerHTML = iconoSvg('stars') + ' <strong>¡Gracias, ' + nombre + '!</strong><br>Nuestro equipo te contactará a la brevedad.';
        cMsg.style.cssText = 'display:block;background:#e8f5e9;color:#2e7d32;border:1px solid #a5d6a7;'
            + 'border-radius:12px;padding:14px 18px;margin-bottom:16px;font-size:.9rem;line-height:1.6;text-align:center;';
    }
    if (window.innerWidth < 900) document.getElementById('formMain').scrollIntoView({ behavior: 'smooth' });
}
</script>
</body>
</html>