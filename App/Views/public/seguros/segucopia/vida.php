<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Seguro de Vida - Seguros Lux. Protege el futuro de tu familia con coberturas flexibles y atención personalizada.">
    <title>Seguro de Vida | Seguros Lux</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/landing.css">
    <link rel="icon" type="image/png" sizes="32x32" href="../images/logo.png">
</head>
<body>
    <!-- Navbar Premium -->
    <nav class="navbar" id="navbar">
        <div class="container navbar-content">
            <a href="/" class="logo">
                <img src="../images/logo.png" alt="Seguros Lux" class="logo-img">
            </a>

            <div class="nav-center">
                <ul class="nav-links" id="navLinks">
                    <li><a href="/" class="nav-link">Inicio</a></li>
                    <li><a href="#beneficios" class="nav-link">Beneficios</a></li>
                    <li class="nav-dropdown">
                        <button class="nav-link dropdown-toggle active" aria-haspopup="true" aria-expanded="false">
                            Seguros <span class="arrow"></span>
                        </button>
                        <div class="dropdown-menu">
                            <a href="/seguros/autos">
                                <span class="dropdown-icon">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.5 2.8c0 .1-.1.2-.1.3v5c0 .6.4 1 1 1h2"/>
                                        <circle cx="7" cy="17" r="2"/>
                                        <circle cx="17" cy="17" r="2"/>
                                    </svg>
                                </span>
                                Seguro Vehicular
                            </a>
                            <a href="/seguros/vida" class="active">
                                <span class="dropdown-icon">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                                    </svg>
                                </span>
                                Seguro de Vida
                            </a>
                            <a href="/seguros/gmm">
                                <span class="dropdown-icon">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
                                    </svg>
                                </span>
                                Gastos Médicos Mayores
                            </a>
                        </div>
                    </li>
                    <li><a href="#contacto" class="nav-link">Contacto</a></li>
                    <li><a href="#faq" class="nav-link">FAQ</a></li>
                </ul>
            </div>

            <div class="nav-actions" id="navActions">
                <a href="/login" class="btn btn-ghost">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                    Corporativo
                </a>
                <a href="tel:+525541232311" class="btn btn-primary-nav">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                    </svg>
                    55 4123 2311
                </a>
            </div>

            <button class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Menú">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>
    </nav>

    <!-- Hero Section con Banner -->
    <section class="hero hero-banner hero-vida" id="inicio" aria-label="Sección principal">
    <div class="hero-video-background">
        <div class="video-foreground">
            <iframe 
                src="https://www.youtube.com/embed/1TGOhpbzGnQ?autoplay=1&mute=1&controls=0&loop=1&playlist=1TGOhpbzGnQ&modestbranding=1&rel=0&iv_load_policy=3&showinfo=0" 
                frameborder="0" 
                allow="autoplay; encrypted-media" 
                allowfullscreen>
            </iframe>
        </div>
        <div class="hero-banner-overlay"></div>
    </div>

    <div class="container hero-banner-content">
        <div class="hero-text-col-full">
            <span class="hero-badge">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.5 2.8c0 .1-.1.2-.1.3v5c0 .6.4 1 1 1h2"/>
                    <circle cx="7" cy="17" r="2"/>
                    <circle cx="17" cy="17" r="2"/>
                </svg>
                Seguro de Vida
            </span>
            <h1 class="hero-title">Protege el futuro de <span class="text-gradient"> tu familia</span></h1>
            <p class="hero-description">
                Asegura la tranquilidad de quienes más amas con coberturas flexibles
                y la mejor asesoría personalizada.
            </p>
            <div class="hero-buttons">
                <a href="/formulario" class="btn btn-primary btn-lg" aria-label="Cotizar seguro">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 12l2 2 4-4"/>
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    </svg>
                    Agendar Asesoría Gratis
                </a>
                <a href="tel:+525541232311" class="btn btn-primary btn-lg" aria-label="Cotizar seguro">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 12l2 2 4-4"/>
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    </svg>
                    55 4123 2311
                </a>
            </div>
        </div>
    </div>
</section>

    <!-- Sección Servicios con Animacion - Familia -->
    <!-- <div class="services-scroll-wrapper services-vida" id="servicios">
        <div class="services-sticky">
            <div class="services-inner"> -->
                <!-- Columna izquierda: texto + cards -->
                <!-- <div class="services-text-col">
                    <span class="section-badge srv-item">Protección Familiar</span>
                    <h2 class="section-title srv-item">Seguridad para toda la vida</h2>
                    <p class="section-description srv-item">
                        Un seguro de vida que se adapta a tus necesidades y 
                        protege a quienes más importan.
                    </p>

                    <div class="services-cards">
                        <div class="service-card srv-item">
                            <div class="service-card-icon service-card-icon-vida">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                                </svg>
                            </div>
                            <div class="service-card-text">
                                <h4>Protección Familiar</h4>
                                <p>Tu familia recibe una suma asegurada en caso de fallecimiento.</p>
                            </div>
                        </div>

                        <div class="service-card srv-item">
                            <div class="service-card-icon service-card-icon-vida">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <path d="M12 6v6l4 2"/>
                                </svg>
                            </div>
                            <div class="service-card-text">
                                <h4>Cobertura de Invalidez</h4>
                                <p>Protección ante incapacidad total y permanente.</p>
                            </div>
                        </div>

                        <div class="service-card srv-item">
                            <div class="service-card-icon service-card-icon-vida">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="12" y1="1" x2="12" y2="23"/>
                                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                                </svg>
                            </div>
                            <div class="service-card-text">
                                <h4>Ahorro y Retiro</h4>
                                <p>Acumula un fondo para tu futuro mientras estas protegido.</p>
                            </div>
                        </div>
                    </div>
                </div> -->

                <!-- Columna derecha: canvas de animacion familia -->
                <!-- <div class="services-canvas-col">
                    <canvas id="vidaCanvas"></canvas>
                </div>
            </div> -->

            <!-- Barra de progreso de la animacion -->
            <!-- <div class="anim-progress" aria-hidden="true">
                <span id="animProgressLabel">Cargando...</span>
                <div class="anim-progress-bar">
                    <div class="anim-progress-fill" id="animProgressFill"></div>
                </div>
            </div>
        </div>
    </div> -->

    <!-- Coberturas Section -->
    <section class="plan-comparison plan-vida" id="coberturas">
        <div class="container">
            <div class="section-header">
                <span class="section-badge">Coberturas</span>
                <h2 class="section-title">Planes de Seguro de Vida</h2>
                <p class="section-description">
                    Elige el plan que mejor se adapte a las necesidades de tu familia.
                </p>
            </div>
            <div class="comparison-table-wrapper">
                <!-- Tabla: visible en tablet/desktop -->
                <table class="comparison-table">
                    <thead>
                        <tr>
                            <th>Cobertura</th>
                            <th>Esencial</th>
                            <th class="recommended">Familiar <span class="recommended-badge">Recomendado</span></th>
                            <th>Premium</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Fallecimiento por cualquier causa</td>
                            <td><span class="plan-yes">&#10004;</span></td>
                            <td class="recommended"><span class="plan-yes">&#10004;</span></td>
                            <td><span class="plan-yes">&#10004;</span></td>
                        </tr>
                        <tr>
                            <td>Muerte accidental (doble indemnización)</td>
                            <td><span class="plan-no">&#10006;</span></td>
                            <td class="recommended"><span class="plan-yes">&#10004;</span></td>
                            <td><span class="plan-yes">&#10004;</span></td>
                        </tr>
                        <tr>
                            <td>Invalidez total y permanente</td>
                            <td><span class="plan-no">&#10006;</span></td>
                            <td class="recommended"><span class="plan-yes">&#10004;</span></td>
                            <td><span class="plan-yes">&#10004;</span></td>
                        </tr>
                        <tr>
                            <td>Enfermedades graves</td>
                            <td><span class="plan-no">&#10006;</span></td>
                            <td class="recommended"><span class="plan-no">&#10006;</span></td>
                            <td><span class="plan-yes">&#10004;</span></td>
                        </tr>
                        <tr>
                            <td>Fondo de ahorro</td>
                            <td><span class="plan-no">&#10006;</span></td>
                            <td class="recommended"><span class="plan-yes">&#10004;</span></td>
                            <td><span class="plan-yes">&#10004;</span></td>
                        </tr>
                        <tr>
                            <td>Gastos funerarios</td>
                            <td><span class="plan-yes">&#10004;</span></td>
                            <td class="recommended"><span class="plan-yes">&#10004;</span></td>
                            <td><span class="plan-yes">&#10004;</span></td>
                        </tr>
                        <tr>
                            <td>Asistencia médica telefónica</td>
                            <td><span class="plan-no">&#10006;</span></td>
                            <td class="recommended"><span class="plan-yes">&#10004;</span></td>
                            <td><span class="plan-yes">&#10004;</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="comparison-cta">
                <a href="#agendar" class="btn btn-primary btn-lg btn-vida" aria-label="Agendar asesoría">Agendar Mi Asesoría</a>
            </div>
        </div>
    </section>

    <!-- Benefits Section -->
    <section class="benefits benefits-vida" id="beneficios">
        <div class="container">
            <div class="benefits-content">
                <div class="benefits-text">
                    <span class="section-badge">Por qué elegirnos</span>
                    <h2 class="section-title">Beneficios de nuestro seguro de vida</h2>
                    <p class="section-description">
                        Tranquilidad para ti y protección para tu familia.
                    </p>
                    
                    <div class="benefits-accordion-list">
                                                <div class="benefit-accordion">
                            <button class="benefit-q" onclick="toggleBenefit(this)" aria-expanded="false">
                                <div class="benefit-icon benefit-icon-vida"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                    <circle cx="9" cy="7" r="4"/>
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                </svg></div>
                                <span>Protección Familiar</span>
                                <svg class="benefit-arrow" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                            </button>
                            <div class="benefit-a">Tu familia queda protegida económicamente.</div>
                        </div>
                        
                                                <div class="benefit-accordion">
                            <button class="benefit-q" onclick="toggleBenefit(this)" aria-expanded="false">
                                <div class="benefit-icon benefit-icon-vida"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                                </svg></div>
                                <span>Suma Asegurada Flexible</span>
                                <svg class="benefit-arrow" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                            </button>
                            <div class="benefit-a">Tú eliges el monto de protección que necesitas.</div>
                        </div>
                        
                                                <div class="benefit-accordion">
                            <button class="benefit-q" onclick="toggleBenefit(this)" aria-expanded="false">
                                <div class="benefit-icon benefit-icon-vida"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="12" y1="1" x2="12" y2="23"/>
                                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                                </svg></div>
                                <span>Beneficios Fiscales</span>
                                <svg class="benefit-arrow" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                            </button>
                            <div class="benefit-a">Deduce tus primas del impuesto sobre la renta.</div>
                        </div>
                        
                                                <div class="benefit-accordion">
                            <button class="benefit-q" onclick="toggleBenefit(this)" aria-expanded="false">
                                <div class="benefit-icon benefit-icon-vida"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                    <path d="M22 4L12 14.01l-3-3"/>
                                </svg></div>
                                <span>Proceso Simple</span>
                                <svg class="benefit-arrow" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                            </button>
                            <div class="benefit-a">Contratación sencilla con asesoría personalizada.</div>
                        </div>
                    </div>
                </div>
                
                <div class="benefits-visual">
                    <div class="benefits-card benefits-card-vida">
                        <div class="benefits-banner">
                            <span class="benefits-card-badge benefits-badge-vida">Asesoría Gratis</span>
                            <img src="/images/banner_tc.jpeg" alt="Asesoría de Seguro de Vida">
                        </div>
                        <div class="benefits-card-body">
                            <a href="#agendar" class="btn btn-primary btn-block btn-vida" aria-label="Agendar asesoría">
                                <span>Agendar Asesoría</span>
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                    <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
                                </svg>
                            </a>
                            <div class="conditions-mini">
                                <p>
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>
                                    </svg>
                                    Sin compromiso. Un especialista te contactará.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Form de Asesoría -->
    <section class="form-section" id="agendar">
        <div class="container">
            <div class="section-header">
                <span class="section-badge">Agenda tu Cita</span>
                <h2 class="section-title">Solicita tu Asesoría Gratuita</h2>
                <p class="section-description">
                    Un especialista en seguros de vida te contactará para orientarte sobre la mejor opción para ti y tu familia.
                </p>
            </div>
            
            <div class="form-wrapper">
                <form id="formVida" class="contact-form">
                    <div class="form-group">
                        <label for="nombre">Nombre completo <span class="required">*</span></label>
                        <input type="text" id="nombre" name="nombre" placeholder="Ej. Juan García López" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="correo">Correo electrónico <span class="required">*</span></label>
                            <input type="email" id="correo" name="correo" placeholder="tu@correo.com" required>
                        </div>
                        <div class="form-group">
                            <label for="telefono">Teléfono / WhatsApp <span class="required">*</span></label>
                            <div class="phone-input">
                                <span class="phone-prefix">+52</span>
                                <input type="tel" id="telefono" name="telefono" placeholder="55 1234 5678" maxlength="10" required>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="fecha">Fecha preferida para contacto <span class="required">*</span></label>
                        <input type="date" id="fecha" name="fecha" required>
                    </div>
                    <div class="form-group">
                        <label for="comentarios">Comentarios adicionales</label>
                        <textarea id="comentarios" name="comentarios" rows="3" placeholder="Cuéntanos más sobre lo que buscas..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg btn-vida btn-block">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2"/>
                            <line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="8" y1="2" x2="8" y2="6"/>
                            <line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                        Solicitar Asesoría
                    </button>
                </form>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="faq-section" id="faq">
        <div class="container">
            <div class="section-header">
                <span class="section-badge">Preguntas Frecuentes</span>
                <h2 class="section-title">Dudas sobre Seguro de Vida</h2>
            </div>
            <div class="faq-list">
                <div class="faq-item">
                    <button class="faq-question" onclick="toggleFaq(this)">
                        <span>¿A qué edad puedo contratar un seguro de vida?</span>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M6 9l6 6 6-6"/>
                        </svg>
                    </button>
                    <div class="faq-answer">
                        <p>Puedes contratar un seguro de vida desde los 18 hasta los 65 años, dependiendo del plan y la aseguradora. Mientras más joven contrates, menor será tu prima.</p>
                    </div>
                </div>
                <div class="faq-item">
                    <button class="faq-question" onclick="toggleFaq(this)">
                        <span>¿Necesito examen médico para contratar?</span>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M6 9l6 6 6-6"/>
                        </svg>
                    </button>
                    <div class="faq-answer">
                        <p>Depende del monto de la suma asegurada. Para montos menores generalmente solo se requiere un cuestionario médico. Para sumas mayores podría requerirse un examen.</p>
                    </div>
                </div>
                <div class="faq-item">
                    <button class="faq-question" onclick="toggleFaq(this)">
                        <span>¿Quién puede ser mi beneficiario?</span>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M6 9l6 6 6-6"/>
                        </svg>
                    </button>
                    <div class="faq-answer">
                        <p>Puedes designar como beneficiarios a cualquier persona: familiares, amigos, o incluso una institución. Puedes cambiar tus beneficiarios en cualquier momento.</p>
                    </div>
                </div>
                <div class="faq-item">
                    <button class="faq-question" onclick="toggleFaq(this)">
                        <span>¿Puedo cancelar mi seguro?</span>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M6 9l6 6 6-6"/>
                        </svg>
                    </button>
                    <div class="faq-answer">
                        <p>Sí, puedes cancelar tu seguro de vida en cualquier momento. Algunos planes con componente de ahorro te permiten recuperar parte del valor acumulado.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta cta-vida" id="contacto">
        <div class="container">
            <div class="cta-content">
                <div class="cta-text">
                    <h2>Protege a tu familia hoy</h2>
                    <p>
                        Agenda una asesoría gratuita y conoce las opciones de seguro de vida
                        que tenemos para ti.
                    </p>
                </div>
                <div class="cta-buttons">
                    <a href="#agendar" class="btn btn-white btn-lg">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2"/>
                            <line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="8" y1="2" x2="8" y2="6"/>
                            <line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                        Agendar Asesoría
                    </a>
                    <a href="tel:+525541232311" class="btn btn-outline-white btn-lg">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                        </svg>
                        Llamar Ahora
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer" id="avisos-legales">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-brand">
                    <a href="/" class="logo">
                        <img src="../images/logo_blanco.png" alt="Seguros Lux" class="logo-img footer-logo-img">
                    </a>
                    <p>Protegiendo lo que más importa desde 2010.</p>
                    <div class="social-links">
                        <a href="#" class="social-link" aria-label="Facebook">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>
                            </svg>
                        </a>
                        <a href="#" class="social-link" aria-label="Instagram">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="2" y="2" width="20" height="20" rx="5"/>
                                <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/>
                                <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/>
                            </svg>
                        </a>
                    </div>
                </div>
                
                <div class="footer-links">
                    <h4>Seguros</h4>
                    <ul>
                        <li><a href="/seguros/autos">Seguro Vehicular</a></li>
                        <li><a href="/seguros/vida">Seguro de Vida</a></li>
                        <li><a href="/seguros/gmm">Gastos Médicos Mayores</a></li>
                    </ul>
                </div>
                
                <div class="footer-links">
                    <h4>Contacto</h4>
                    <ul>
                        <li>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                            </svg>
                            55 4123 2311
                        </li>
                        <li>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                <polyline points="22,6 12,13 2,6"/>
                            </svg>
                            <a href="/cdn-cgi/l/email-protection#2e474048416e5d4b495b5c415d425b56004356" class="support-link"><span class="__cf_email__" data-cfemail="0861666e67487b6d6f7d7a677b647d70266570">[email&#160;protected]</span></a>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>@Seguros Lux. Todos los derechos reservados.</p>
                <div class="footer-legal">
                    <a href="/aviso-privacidad.php">Aviso de Privacidad</a>
                    <a href="/terminos.php">Términos y Condiciones</a>
                </div>
            </div>
        </div>
    </footer>


<script>
(function() {
    var form = document.getElementById('formVida');
    if (!form) return;
    // Fecha mínima = mañana
    var minDate = new Date(); minDate.setDate(minDate.getDate()+1);
    var fechaEl = document.getElementById('fecha');
    if (fechaEl) { fechaEl.min = minDate.toISOString().split('T')[0]; }

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        var nombre   = (document.getElementById('nombre').value   || '').trim();
        var correo   = (document.getElementById('correo').value   || '').trim();
        var telefono = (document.getElementById('telefono').value || '').trim();
        var fecha    = (fechaEl ? fechaEl.value : '').trim();

        if (!nombre || !correo || !telefono || !fecha) {
            alert('Por favor completa todos los campos requeridos.'); return;
        }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo)) {
            alert('Ingresa un correo electrónico válido.'); return;
        }

        var btn = form.querySelector('[type=submit]');
        if (btn) { btn.disabled = true; btn.textContent = 'Enviando...'; }

        fetch('/api/vida-solicitud', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                nombre: nombre, correo: correo, telefono: '+52' + telefono,
                fecha_preferida: fecha, ramo: 'vida',
                comentarios: (document.getElementById('comentarios') ? document.getElementById('comentarios').value : '')
            })
        })
        .then(function(r){ return r.json(); })
        .then(function(d) {
            if (d.ok) {
                form.innerHTML = '<div style="text-align:center;padding:32px 20px;">' +
                    '<div style="font-size:56px;margin-bottom:12px;">✅</div>' +
                    '<h3 style="color:#22c55e;margin-bottom:8px;">¡Solicitud recibida!</h3>' +
                    '<p style="color:#555;max-width:420px;margin:0 auto;">Hola <strong>' + nombre + '</strong>, ' +
                    'un especialista en Seguro de Vida te contactará antes del <strong>' +
                    fecha.split('-').reverse().join('/') + '</strong>. Revisa tu correo.</p>' +
                    '<p style="margin-top:16px;font-size:14px;color:#888;">¿Mientras tanto? <a href="/formulario">Cotiza tu Seguro Vehicular</a></p>' +
                    '</div>';
            } else {
                alert('Error: ' + (d.msg || 'Intenta de nuevo.'));
                if (btn) { btn.disabled = false; btn.textContent = 'Solicitar Asesoría'; }
            }
        })
        .catch(function() {
            alert('Error de conexión. Intenta de nuevo.');
            if (btn) { btn.disabled = false; btn.textContent = 'Solicitar Asesoría'; }
        });
    });
})();
</script>

    <?php require view('partials/_chat.php'); ?>
    <script src="/js/landing.js" defer></script>
    <script src="/js/vida.js" defer></script>
<script>
// ── Benefit Accordion ──────────────────────────────────────
function toggleBenefit(btn) {
    const acc   = btn.closest('.benefit-accordion');
    const ans   = acc.querySelector('.benefit-a');
    const isOpen = btn.getAttribute('aria-expanded') === 'true';
    acc.closest('.benefits-accordion-list')
       ?.querySelectorAll('.benefit-q[aria-expanded="true"]')
       .forEach(other => {
           if (other !== btn) {
               other.setAttribute('aria-expanded', 'false');
               other.closest('.benefit-accordion').querySelector('.benefit-a').classList.remove('open');
           }
       });
    btn.setAttribute('aria-expanded', String(!isOpen));
    ans.classList.toggle('open', !isOpen);
}
</script>
</body>
</html>