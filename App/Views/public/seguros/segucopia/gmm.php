<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Gastos Médicos Mayores - Seguros Lux. Tu intermediario de confianza para el mejor seguro GMM de México.">
    <title>Gastos Médicos Mayores | Seguros Lux</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/landing.css">
    <link rel="icon" type="image/png" sizes="32x32" href="../images/logo.png">

    <style>
        /* ── Quiénes Somos (Lux como intermediario) ── */
        .about {
            background: var(--gray-50);
            padding: 80px 0;
        }
        .about .section-header { margin-bottom: 48px; }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin-bottom: 48px;
        }
        @media (max-width: 768px) { .stats-grid { grid-template-columns: repeat(2,1fr); } }
        @media (max-width: 480px) { .stats-grid { grid-template-columns: 1fr; } }
        .stat-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 28px 24px;
            text-align: center;
            box-shadow: var(--shadow-md);
            border-top: 4px solid var(--gmm-primary);
            transition: transform var(--transition-normal), box-shadow var(--transition-normal);
        }
        .stat-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-xl); }
        .stat-number {
            font-size: 2.4rem;
            font-weight: 800;
            color: var(--gmm-primary);
            line-height: 1;
            margin-bottom: 8px;
        }
        .stat-label {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--gray-600);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .about-text {
            max-width: 800px;
            margin: 0 auto;
            text-align: center;
            color: var(--gray-600);
            font-size: 1.05rem;
            line-height: 1.8;
        }
        /* Tag de aseguradora en stat-card */
        .stat-card .stat-source {
            font-size: 0.72rem;
            color: var(--gray-400);
            margin-top: 4px;
        }

        /* ── Ventajas Lux como intermediario ── */
        .lux-ventajas {
            padding: 80px 0;
            background: var(--white);
        }
        .ventajas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 24px;
            margin-top: 48px;
        }
        .ventaja-card {
            background: var(--gray-50);
            border-radius: var(--radius-lg);
            padding: 28px 24px;
            border-left: 4px solid var(--gmm-primary);
            transition: all var(--transition-normal);
        }
        .ventaja-card:hover { box-shadow: var(--shadow-lg); transform: translateY(-3px); }
        .ventaja-emoji { font-size: 2rem; margin-bottom: 12px; }
        .ventaja-card h4 { font-size: 1rem; font-weight: 700; color: var(--primary); margin-bottom: 8px; }
        .ventaja-card p { font-size: 0.85rem; color: var(--gray-600); line-height: 1.6; margin: 0; }

        /* ── Productos GMM ── */
        .gmm-products {
            padding: 80px 0;
            background: var(--gray-50);
        }
        .products-tabs {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            justify-content: center;
            margin-bottom: 48px;
        }
        .product-tab {
            padding: 10px 22px;
            border-radius: var(--radius-full);
            border: 2px solid var(--gray-200);
            background: transparent;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--gray-600);
            transition: all var(--transition-normal);
        }
        .product-tab.active, .product-tab:hover {
            background: var(--gmm-primary);
            border-color: var(--gmm-primary);
            color: var(--white);
        }
        .product-panel { display: none; }
        .product-panel.active { display: block; }
        .products-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 24px;
        }
        .product-card {
            border-radius: var(--radius-lg);
            padding: 28px;
            border: 2px solid var(--gray-200);
            background: var(--white);
            transition: all var(--transition-normal);
            position: relative;
            overflow: hidden;
        }
        .product-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: var(--gmm-gradient);
        }
        .product-card:hover { border-color: var(--gmm-light); box-shadow: var(--shadow-xl); transform: translateY(-4px); }
        .product-card.featured {
            border-color: var(--gmm-primary);
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
        }
        .product-card-badge {
            display: inline-block;
            background: var(--accent);
            color: var(--primary-dark);
            font-size: 0.7rem;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: var(--radius-full);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 12px;
        }
        .product-card h3 { font-size: 1.25rem; font-weight: 700; color: var(--primary); margin-bottom: 8px; }
        .product-card p { font-size: 0.9rem; color: var(--gray-600); line-height: 1.6; margin-bottom: 16px; }
        .product-features { list-style: none; padding: 0; }
        .product-features li {
            font-size: 0.85rem;
            color: var(--gray-700);
            padding: 4px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .product-features li::before { content: '✓'; color: var(--gmm-primary); font-weight: 700; }

        /* ── Coberturas detalladas ── */
        .coberturas {
            padding: 80px 0;
            background: var(--white);
        }
        .cobertura-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }
        .cobertura-item {
            background: var(--gray-50);
            border-radius: var(--radius-md);
            padding: 20px;
            display: flex;
            align-items: flex-start;
            gap: 14px;
            box-shadow: var(--shadow-sm);
            border-left: 4px solid var(--gmm-primary);
            transition: all var(--transition-normal);
        }
        .cobertura-item:hover { box-shadow: var(--shadow-md); transform: translateX(4px); }
        .cobertura-icon {
            width: 40px; height: 40px; flex-shrink: 0;
            background: linear-gradient(135deg, #e0f2fe, #bae6fd);
            border-radius: var(--radius-sm);
            display: flex; align-items: center; justify-content: center;
            color: var(--gmm-primary);
        }
        .cobertura-item h4 { font-size: 0.95rem; font-weight: 700; color: var(--primary); margin-bottom: 4px; }
        .cobertura-item p { font-size: 0.82rem; color: var(--gray-600); line-height: 1.5; margin: 0; }

        /* ── Asistencias ── */
        .asistencias {
            padding: 80px 0;
            background: var(--gray-50);
        }
        .asistencias-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 28px;
        }
        .asistencia-card {
            border-radius: var(--radius-lg);
            padding: 32px 28px;
            background: var(--white);
            box-shadow: var(--shadow-md);
            border-top: 5px solid var(--gmm-primary);
            transition: all var(--transition-normal);
        }
        .asistencia-card:hover { transform: translateY(-6px); box-shadow: var(--shadow-2xl); }
        .asistencia-header {
            display: flex; align-items: center; gap: 14px; margin-bottom: 20px;
        }
        .asistencia-emoji { font-size: 2rem; }
        .asistencia-card h3 { font-size: 1.1rem; font-weight: 700; color: var(--primary); }
        .asistencia-card ul { list-style: none; padding: 0; }
        .asistencia-card ul li {
            font-size: 0.88rem;
            color: var(--gray-600);
            padding: 6px 0;
            border-bottom: 1px solid var(--gray-100);
            display: flex; align-items: flex-start; gap: 8px;
        }
        .asistencia-card ul li:last-child { border-bottom: none; }
        .asistencia-card ul li::before { content: '→'; color: var(--gmm-primary); font-weight: 700; flex-shrink: 0; margin-top: 1px; }
        .asistencia-descuento {
            margin-top: 16px;
            background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
            border-radius: var(--radius-sm);
            padding: 12px;
            text-align: center;
        }
        .asistencia-descuento strong { color: var(--gmm-primary); font-size: 1.3rem; }
        .asistencia-descuento span { display: block; font-size: 0.78rem; color: var(--gray-500); }

        /* ── Médica Móvil ── */
        .medica-movil-section {
            padding: 80px 0;
            background: linear-gradient(135deg, #0c4a6e 0%, #0284c7 100%);
            color: var(--white);
        }
        .medica-movil-section .section-badge { background: rgba(255,255,255,0.15); color: var(--white); border: 1px solid rgba(255,255,255,0.3); }
        .medica-movil-section .section-title { color: var(--white); }
        .medica-movil-section .section-description { color: rgba(255,255,255,0.8); }
        .mm-features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 24px;
            margin-top: 48px;
        }
        .mm-feature {
            background: rgba(255,255,255,0.1);
            border-radius: var(--radius-lg);
            padding: 24px;
            border: 1px solid rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
        }
        .mm-feature-icon { font-size: 2rem; margin-bottom: 12px; }
        .mm-feature h4 { font-size: 1rem; font-weight: 700; color: var(--white); margin-bottom: 8px; }
        .mm-feature p { font-size: 0.85rem; color: rgba(255,255,255,0.75); line-height: 1.6; }
        .mm-case-study {
            margin-top: 48px;
            background: rgba(255,255,255,0.1);
            border-radius: var(--radius-lg);
            padding: 32px;
            border: 1px solid rgba(255,255,255,0.15);
        }
        .mm-case-study h4 { color: var(--accent); margin-bottom: 16px; font-size: 1rem; }
        .mm-cases {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 16px;
        }
        .mm-case {
            background: rgba(255,255,255,0.08);
            border-radius: var(--radius-md);
            padding: 16px;
        }
        .mm-case p { font-size: 0.85rem; color: rgba(255,255,255,0.8); margin-bottom: 8px; line-height: 1.6; }
        .mm-case-saving { color: var(--accent); font-weight: 700; font-size: 1rem; }

        /* ── Red + Servicios digitales (fusionados) ── */
        .red-digital {
            padding: 80px 0;
            background: var(--white);
        }
        .red-digital-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            align-items: start;
        }
        @media (max-width: 768px) { .red-digital-grid { grid-template-columns: 1fr; gap: 40px; } }

        /* Red stats inline */
        .red-stats-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 32px;
        }
        .red-stat {
            background: var(--gray-50);
            border-radius: var(--radius-md);
            padding: 18px 12px;
            text-align: center;
            border-top: 3px solid var(--gmm-primary);
        }
        .red-stat-number { font-size: 1.6rem; font-weight: 800; color: var(--gmm-primary); margin-bottom: 4px; }
        .red-stat-label { font-size: 0.75rem; color: var(--gray-500); text-transform: uppercase; letter-spacing: 0.04em; }

        .hospitales-premium {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 16px;
        }
        .hospital-tag {
            background: #e0f2fe;
            color: var(--gmm-primary);
            font-size: 0.78rem;
            font-weight: 600;
            padding: 4px 12px;
            border-radius: var(--radius-full);
        }

        /* Digital lado derecho */
        .digital-col h3 { font-size: 1.2rem; font-weight: 700; color: var(--primary); margin-bottom: 20px; }
        .app-feature-list { list-style: none; padding: 0; }
        .app-feature-list li {
            display: flex; align-items: flex-start; gap: 14px;
            padding: 14px 0; border-bottom: 1px solid var(--gray-100);
        }
        .app-feature-list li:last-child { border-bottom: none; }
        .app-feat-icon {
            width: 38px; height: 38px; flex-shrink: 0;
            background: linear-gradient(135deg, #e0f2fe, #bae6fd);
            border-radius: var(--radius-sm);
            display: flex; align-items: center; justify-content: center;
            color: var(--gmm-primary);
        }
        .app-feat-text h5 { font-size: 0.9rem; font-weight: 700; color: var(--primary); margin-bottom: 3px; }
        .app-feat-text p { font-size: 0.8rem; color: var(--gray-600); line-height: 1.5; margin: 0; }
        .app-badge-row {
            display: flex; gap: 10px; margin-top: 20px; flex-wrap: wrap;
        }
        .app-badge {
            background: var(--primary-dark);
            color: var(--white);
            font-size: 0.8rem;
            font-weight: 600;
            padding: 8px 16px;
            border-radius: var(--radius-sm);
        }

        /* ── Diferenciadores + En Casa (fusionados) ── */
        .diferenciadores {
            padding: 80px 0;
            background: var(--primary-dark);
            color: var(--white);
        }
        .diferenciadores .section-badge { background: rgba(255,255,255,0.15); color: var(--white); }
        .diferenciadores .section-title { color: var(--white); }
        .diferenciadores .section-description { color: rgba(255,255,255,0.7); }
        .dif-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin-top: 48px;
        }
        @media (max-width: 768px) { .dif-grid { grid-template-columns: 1fr; } }
        .dif-card {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: var(--radius-lg);
            padding: 28px;
            transition: all var(--transition-normal);
        }
        .dif-card:hover { background: rgba(255,255,255,0.1); transform: translateY(-4px); }
        .dif-number {
            font-size: 2.4rem;
            font-weight: 900;
            color: var(--accent);
            line-height: 1;
            margin-bottom: 8px;
        }
        .dif-card h4 { font-size: 0.95rem; font-weight: 700; color: var(--white); margin-bottom: 8px; }
        .dif-card p { font-size: 0.82rem; color: rgba(255,255,255,0.6); line-height: 1.6; }
        .dif-source { font-size: 0.7rem; color: rgba(255,255,255,0.35); margin-top: 8px; }

        /* Programa En Casa inline */
        .en-casa-box {
            margin-top: 48px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 16px;
            padding: 36px;
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 24px;
            align-items: start;
        }
        @media (max-width: 640px) { .en-casa-box { grid-template-columns: 1fr; } }
        .en-casa-icon { font-size: 3rem; }
        .en-casa-content h3 { color: var(--accent); font-size: 1.15rem; margin-bottom: 8px; }
        .en-casa-content p { color: rgba(255,255,255,0.75); font-size: 0.92rem; line-height: 1.8; margin-bottom: 16px; }
        .en-casa-tags { display: flex; flex-wrap: wrap; gap: 10px; }
        .en-casa-tag {
            background: rgba(255,255,255,0.08);
            border-radius: 8px;
            padding: 8px 14px;
            font-size: 0.82rem;
            color: rgba(255,255,255,0.8);
        }

        /* ── Acompañamiento hospitalario (cédula) ── */
        .cedula {
            padding: 80px 0;
            background: var(--gray-50);
        }
        .cedula-steps {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 24px;
            margin-top: 48px;
            position: relative;
        }
        .cedula-step {
            text-align: center;
            padding: 24px 16px;
        }
        .cedula-step-number {
            width: 52px; height: 52px;
            background: var(--gmm-gradient);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: var(--white);
            font-weight: 800;
            font-size: 1.2rem;
            margin: 0 auto 16px;
        }
        .cedula-step h4 { font-size: 0.95rem; font-weight: 700; color: var(--primary); margin-bottom: 8px; }
        .cedula-step p { font-size: 0.82rem; color: var(--gray-600); line-height: 1.6; }
        .whatsapp-box {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 28px 32px;
            margin-top: 40px;
            display: flex;
            align-items: center;
            gap: 20px;
            box-shadow: var(--shadow-md);
        }
        @media (max-width: 640px) { .whatsapp-box { flex-direction: column; text-align: center; } }
        .whatsapp-box-icon { font-size: 2.5rem; flex-shrink: 0; }
        .whatsapp-box h4 { font-size: 1rem; font-weight: 700; color: var(--primary); margin-bottom: 6px; }
        .whatsapp-box p { font-size: 0.85rem; color: var(--gray-600); margin: 0; line-height: 1.5; }
    </style>
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
                                        <circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/>
                                    </svg>
                                </span>
                                Seguro Vehicular
                            </a>
                            <a href="/seguros/vida">
                                <span class="dropdown-icon">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                                    </svg>
                                </span>
                                Seguro de Vida
                            </a>
                            <a href="/seguros/gmm" class="active">
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
                <span></span><span></span><span></span>
            </button>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero hero-banner hero-vida" id="inicio" aria-label="Sección principal">
        <div class="hero-video-background">
            <div class="video-foreground">
                <iframe
                    src="https://www.youtube.com/embed/290EUkEaa40?autoplay=1&mute=1&controls=0&loop=1&playlist=290EUkEaa40&modestbranding=1&rel=0&iv_load_policy=3&showinfo=0"
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
                        <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
                    </svg>
                    Gastos Médicos Mayores ·
                </span>
                <h1 class="hero-title">El mejor GMM de México, <span class="text-gradient">asesorado por Lux</span></h1>
                <p class="hero-description">
                    Como intermediario autorizado, te ayudamos a encontrar la cobertura médica ideal
                    respaldada por la aseguradora más sólida de México, con más de 120 años
                    protegiendo familias y una red de +460 hospitales certificados.
                </p>
                <div class="hero-buttons">
                    <a href="/formulario" class="btn btn-primary btn-lg" aria-label="Cotizar seguro">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 12l2 2 4-4"/>
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        </svg>
                        Cotizar Ahora
                    </a>
                    <a href="tel:+525541232311" class="btn btn-primary btn-lg">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                        </svg>
                        55 4123 2311
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- ══════════════════════════════════════════════
         SECCIÓN: POR QUÉ SEGUROS LUX (intermediario)
         ↳ Fusión de "Quiénes Somos" + propuesta de valor Lux
    ══════════════════════════════════════════════ -->
    <section class="about" id="nosotros">
        <div class="container">
            <div class="section-header">
                <span class="section-badge">¿Por qué Seguros Lux?</span>
                <h2 class="section-title">Tu aliado para elegir el mejor seguro médico</h2>
                <p class="section-description">
                    Somos un intermediario de seguros autorizado por la CNSF. No vendemos un solo producto:
                    analizamos tu perfil y te conectamos con la cobertura que realmente necesitas,
                    respaldada por la aseguradora de mayor solidez en México.
                </p>
            </div>

            <!-- Stats de la aseguradora -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number">120+</div>
                    <div class="stat-label">Años de trayectoria de la aseguradora en México</div>
                    <div class="stat-source">Fuente: la aseguradora</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">+10 M</div>
                    <div class="stat-label">Clientes protegidos a nivel nacional</div>
                    <div class="stat-source">Fuente: la aseguradora</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">88.32%</div>
                    <div class="stat-label">Retención de cartera — sinónimo de confianza</div>
                    <div class="stat-source">Fuente: la aseguradora</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">15.1%</div>
                    <div class="stat-label">Participación de mercado con oferta integral</div>
                    <div class="stat-source">Fuente: la aseguradora</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">3.55</div>
                    <div class="stat-label">Calificación de alta solidez financiera</div>
                    <div class="stat-source">Fuente: la aseguradora</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">+7,205</div>
                    <div class="stat-label">Agentes que respaldan la oferta de GMM en México</div>
                    <div class="stat-source">Fuente: la aseguradora</div>
                </div>
            </div>

            <p class="about-text">
                En Seguros Lux llevamos más de 15 años asesorando a familias y empresas. 
                Nuestros asesores certificados te explican cada cobertura con transparencia, 
                te acompañan durante el proceso de contratación y siguen contigo si necesitas hacer válida tu póliza.
                <strong>Sin letra pequeña. Sin sorpresas.</strong>
            </p>
        </div>
    </section>

    <!-- ══════════════════════════════════════════════
         SECCIÓN: VENTAJAS DE CONTRATAR CON LUX
    ══════════════════════════════════════════════ -->
    <section class="lux-ventajas" id="ventajas">
        <div class="container">
            <div class="section-header">
                <span class="section-badge">Ventajas Seguros Lux</span>
                <h2 class="section-title">Lo que obtienes al contratar con nosotros</h2>
                <p class="section-description">
                    Más allá de la póliza, Seguros Lux es tu punto de contacto antes, durante y después de cualquier eventualidad.
                </p>
            </div>
            <div class="ventajas-grid">
                <div class="ventaja-card">
                    <div class="ventaja-emoji">🎯</div>
                    <h4>Asesoría personalizada</h4>
                    <p>Analizamos tu edad, estilo de vida, presupuesto y necesidades específicas para recomendarte el plan GMM más adecuado.</p>
                </div>
                <div class="ventaja-card">
                    <div class="ventaja-emoji">📋</div>
                    <h4>Comparativa sin compromiso</h4>
                    <p>Te presentamos opciones claras de planes y coberturas para que tomes la decisión con información completa.</p>
                </div>
                <div class="ventaja-card">
                    <div class="ventaja-emoji">🤝</div>
                    <h4>Acompañamiento en siniestros</h4>
                    <p>Cuando más nos necesitas, estamos contigo. Te guiamos en la gestión de autorizaciones y reclamaciones con la aseguradora.</p>
                </div>
                <div class="ventaja-card">
                    <div class="ventaja-emoji">🔄</div>
                    <h4>Renovación y seguimiento</h4>
                    <p>Te avisamos antes de cada renovación, revisamos tu cobertura anualmente y ajustamos si tus necesidades cambian.</p>
                </div>
                <div class="ventaja-card">
                    <div class="ventaja-emoji">📞</div>
                    <h4>Contacto directo siempre</h4>
                    <p>Hablas con un asesor real, no con un bot. Atención por teléfono, WhatsApp y correo en horarios extendidos.</p>
                </div>
                <div class="ventaja-card">
                    <div class="ventaja-emoji">🔒</div>
                    <h4>Intermediario autorizado CNSF</h4>
                    <p>Operamos bajo la supervisión de la Comisión Nacional de Seguros y Fianzas. Tu contrato es con la aseguradora, nosotros somos tu voz.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ══════════════════════════════════════════════
         SECCIÓN: PRODUCTOS GMM
    ══════════════════════════════════════════════ -->
    <section class="gmm-products" id="productos">
        <div class="container">
            <div class="section-header">
                <span class="section-badge">Productos GMM</span>
                <h2 class="section-title">Cobertura para cada estilo de vida</h2>
                <p class="section-description">
                    La aseguradora ofrece una amplia gama de seguros de Gastos Médicos Mayores — nacionales, internacionales
                    y especializados. Te asesoramos para que encuentres exactamente lo que necesitas.
                </p>
            </div>

            <!-- Tabs -->
            <div class="products-tabs">
                <button class="product-tab active" data-tab="nacionales">Nacionales</button>
                <button class="product-tab" data-tab="internacionales">Internacionales</button>
                <button class="product-tab" data-tab="especializados">Especializados</button>
                <button class="product-tab" data-tab="indemnizatoria">Indemnizatorios</button>
            </div>

            <!-- Nacionales -->
            <div class="product-panel active" id="tab-nacionales">
                <div class="products-cards-grid">
                    <div class="product-card">
                        <h3>Esencial</h3>
                        <p>Protección básica con cobertura para gastos médicos mayores esenciales.</p>
                        <ul class="product-features">
                            <li>Hospitalización y cirugías</li>
                            <li>Urgencias médicas</li>
                            <li>Membresía Médica Móvil</li>
                            <li>Asistencia en Viajes y Dental</li>
                        </ul>
                    </div>
                    <div class="product-card">
                        <h3>Versátil</h3>
                        <p>Cobertura equilibrada que se adapta a distintas necesidades y presupuestos.</p>
                        <ul class="product-features">
                            <li>Todo lo de Esencial</li>
                            <li>Beneficio Riesgo Selecto</li>
                            <li>Solidez Familiar</li>
                            <li>Coberturas ampliadas</li>
                        </ul>
                    </div>
                    <div class="product-card featured">
                        <div class="product-card-badge">Más Popular</div>
                        <h3>Personaliza</h3>
                        <p>Diseña tu cobertura acorde a tu estilo de vida con beneficios exclusivos.</p>
                        <ul class="product-features">
                            <li>Coberturas completamente personalizables</li>
                            <li>Membresía Médica Móvil ilimitada</li>
                            <li>Asistencia Dental y en Viajes</li>
                            <li>Riesgo Selecto · Solidez Familiar</li>
                        </ul>
                    </div>
                    <div class="product-card">
                        <h3>Flexibles</h3>
                        <p>Planes con suma asegurada y deducible adaptables a tu presupuesto.</p>
                        <ul class="product-features">
                            <li>Suma asegurada flexible</li>
                            <li>Deducible ajustable</li>
                            <li>Membresía Médica Móvil</li>
                            <li>Asistencia en Viajes y Dental</li>
                        </ul>
                    </div>
                    <div class="product-card">
                        <div class="product-card-badge">Premium</div>
                        <h3>Premium / Platino</h3>
                        <p>Cobertura amplia con acceso a los mejores hospitales y médicos del país.</p>
                        <ul class="product-features">
                            <li>Suma asegurada alta</li>
                            <li>Red hospitalaria premium</li>
                            <li>Membresía Médica Móvil ilimitada</li>
                            <li>Todas las asistencias incluidas</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Internacionales -->
            <div class="product-panel" id="tab-internacionales">
                <div class="products-cards-grid">
                    <div class="product-card featured">
                        <div class="product-card-badge">Internacional</div>
                        <h3>Enlace Internacional</h3>
                        <p>Cobertura médica en México con extensión internacional para tus viajes y estancias en el extranjero.</p>
                        <ul class="product-features">
                            <li>Cobertura nacional e internacional</li>
                            <li>Atención en hospitales del mundo</li>
                            <li>Asistencia en viajes incluida</li>
                        </ul>
                    </div>
                    <div class="product-card">
                        <h3>Línea Azul Internacional (LAI)</h3>
                        <p>Acceso a hospitales y médicos de primer nivel tanto en México como en el extranjero.</p>
                        <ul class="product-features">
                            <li>Red internacional de proveedores</li>
                            <li>Asistencia médica 24/7</li>
                            <li>Repatriación cubierta</li>
                        </ul>
                    </div>
                    <div class="product-card">
                        <h3>VIP</h3>
                        <p>La cobertura más completa con acceso a los mejores servicios médicos a nivel global.</p>
                        <ul class="product-features">
                            <li>Cobertura sin límite geográfico</li>
                            <li>Médica Móvil incluida</li>
                            <li>Asistencia Dental premium</li>
                            <li>Asistencia en Viajes completa</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Especializados -->
            <div class="product-panel" id="tab-especializados">
                <div class="products-cards-grid">
                    <div class="product-card">
                        <h3>Conexión</h3>
                        <p>Seguro especializado complementario para momentos y necesidades específicas.</p>
                        <ul class="product-features">
                            <li>Cobertura segmentada</li>
                            <li>Acceso a red hospitalaria nacional</li>
                        </ul>
                    </div>
                    <div class="product-card">
                        <h3>Acceso LA</h3>
                        <p>Plan diseñado para complementar coberturas existentes con acceso a servicios de calidad.</p>
                        <ul class="product-features">
                            <li>Asistencia Visión incluida</li>
                            <li>Asistencia Dental y Funeraria</li>
                            <li>Membresía Médica Móvil</li>
                        </ul>
                    </div>
                    <div class="product-card featured">
                        <h3>Vínculo Mundial</h3>
                        <p>Cobertura internacional de alto nivel para quienes viajan o radican en el extranjero.</p>
                        <ul class="product-features">
                            <li>Cobertura global completa</li>
                            <li>Red de hospitales internacionales</li>
                        </ul>
                    </div>
                    <div class="product-card">
                        <div class="product-card-badge">Élite</div>
                        <h3>Alta Especialidad</h3>
                        <p>Para padecimientos complejos que requieren atención médica de máximo nivel.</p>
                        <ul class="product-features">
                            <li>Acceso a centros de alta especialidad</li>
                            <li>Segunda opinión médica</li>
                            <li>Acompañamiento médico personalizado</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Indemnizatoria -->
            <div class="product-panel" id="tab-indemnizatoria">
                <div class="products-cards-grid">
                    <div class="product-card">
                        <h3>Indemniza</h3>
                        <p>Brinda respaldo económico cubriendo riesgos nombrados en el momento que los necesites.</p>
                        <ul class="product-features">
                            <li>Cobertura indemnizatoria</li>
                            <li>Asistencia Visión incluida</li>
                            <li>Asistencia Funeraria</li>
                            <li>Membresía Médica Móvil</li>
                        </ul>
                    </div>
                    <div class="product-card featured">
                        <h3>Línea Azul Plenitud</h3>
                        <p>Plan con cobertura indemnizatoria de alto nivel y acceso a servicios diferenciados.</p>
                        <ul class="product-features">
                            <li>Indemnización ampliada</li>
                            <li>Beneficios adicionales diferenciados</li>
                            <li>Red hospitalaria preferente</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ══════════════════════════════════════════════
         SECCIÓN: COBERTURAS + TABLA COMPARATIVA
         ↳ Fusión: antes eran dos secciones separadas con info solapada
    ══════════════════════════════════════════════ -->
    <section class="coberturas" id="coberturas">
        <div class="container">
            <div class="section-header">
                <span class="section-badge">Coberturas</span>
                <h2 class="section-title">¿Qué cubre tu seguro GMM?</h2>
                <p class="section-description">
                    Cobertura integral diseñada para protegerte en cada etapa de una atención médica,
                    desde el diagnóstico hasta la recuperación en casa.
                </p>
            </div>

            <div class="cobertura-grid">
                <div class="cobertura-item">
                    <div class="cobertura-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                    </div>
                    <div>
                        <h4>Análisis e Imagenología</h4>
                        <p>Laboratorio, gabinete, imagenología y pruebas moleculares incluidas.</p>
                    </div>
                </div>
                <div class="cobertura-item">
                    <div class="cobertura-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/></svg>
                    </div>
                    <div>
                        <h4>Consultas Médicas</h4>
                        <p>Consultas de especialidad, post-operatorias y segunda opinión médica.</p>
                    </div>
                </div>
                <div class="cobertura-item">
                    <div class="cobertura-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18"/><path d="M5 21V7l8-4 8 4v14"/></svg>
                    </div>
                    <div>
                        <h4>Hospitalización Completa</h4>
                        <p>Cuarto privado estándar, alimentos, paquete de admisión y cama extra para acompañante.</p>
                    </div>
                </div>
                <div class="cobertura-item">
                    <div class="cobertura-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg>
                    </div>
                    <div>
                        <h4>Urgencias y Traslados</h4>
                        <p>Atención y traslado terrestre o aéreo en caso de emergencia o accidente.</p>
                    </div>
                </div>
                <div class="cobertura-item">
                    <div class="cobertura-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 12l2 2 4-4"/><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    </div>
                    <div>
                        <h4>Sala de Operaciones</h4>
                        <p>Procedimientos quirúrgicos, recuperación, terapia intensiva e intermedia.</p>
                    </div>
                </div>
                <div class="cobertura-item">
                    <div class="cobertura-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
                    </div>
                    <div>
                        <h4>Medicamentos</h4>
                        <p>Medicamentos dentro del hospital, material de curación y soluciones intravenosas.</p>
                    </div>
                </div>
                <div class="cobertura-item">
                    <div class="cobertura-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    </div>
                    <div>
                        <h4>Rehabilitación</h4>
                        <p>Sesiones de rehabilitación física, neurorrehab y programas especializados.</p>
                    </div>
                </div>
                <div class="cobertura-item">
                    <div class="cobertura-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M4.93 4.93l14.14 14.14"/></svg>
                    </div>
                    <div>
                        <h4>Quimio y Radioterapia</h4>
                        <p>Radioterapias, quimioterapias, inhaloterapia y rehabilitación cardiaca.</p>
                    </div>
                </div>
                <div class="cobertura-item">
                    <div class="cobertura-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 14.66V20a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h5.34"/><polygon points="18 2 22 6 12 16 8 16 8 12 18 2"/></svg>
                    </div>
                    <div>
                        <h4>Auxiliares y Prótesis</h4>
                        <p>Compra, renta o reposición de auxiliares mecánicos, prótesis y dispositivos médicos.</p>
                    </div>
                </div>
                <div class="cobertura-item">
                    <div class="cobertura-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                    </div>
                    <div>
                        <h4>Medicina Hiperbárica</h4>
                        <p>Bajo supervisión médica para ciertos padecimientos específicos.</p>
                    </div>
                </div>
                <div class="cobertura-item">
                    <div class="cobertura-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                    </div>
                    <div>
                        <h4>Transfusiones</h4>
                        <p>Transfusión de sangre, plasma, sueros y soluciones intravenosas.</p>
                    </div>
                </div>
                <div class="cobertura-item">
                    <div class="cobertura-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                    </div>
                    <div>
                        <h4>Enfermería</h4>
                        <p>Renta de servicio de enfermería para atención domiciliaria o en hospital.</p>
                    </div>
                </div>
            </div>

            <!-- Tabla comparativa integrada aquí (elimina sección duplicada de más abajo) -->
            <div style="margin-top: 56px;">
                <h3 style="text-align:center; color:var(--primary); font-size:1.3rem; font-weight:700; margin-bottom:8px;">Compara los planes por cobertura</h3>
                <p style="text-align:center; color:var(--gray-500); font-size:0.88rem; margin-bottom:28px;">Todos los planes incluyen Membresía Médica Móvil, Asistencia Dental, en Viajes y red de +460 hospitales.</p>
                <div class="comparison-table-wrapper">
                    <table class="comparison-table">
                        <thead>
                            <tr>
                                <th>Cobertura</th>
                                <th>Esencial</th>
                                <th class="recommended">Versátil / Personaliza <span class="recommended-badge">Recomendado</span></th>
                                <th>Premium / Platino</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Hospitalización y cirugías</td>
                                <td><span class="plan-yes">&#10004;</span></td>
                                <td class="recommended"><span class="plan-yes">&#10004;</span></td>
                                <td><span class="plan-yes">&#10004;</span></td>
                            </tr>
                            <tr>
                                <td>Quimio y Radioterapias</td>
                                <td><span class="plan-yes">&#10004;</span></td>
                                <td class="recommended"><span class="plan-yes">&#10004;</span></td>
                                <td><span class="plan-yes">&#10004;</span></td>
                            </tr>
                            <tr>
                                <td>Rehabilitación física</td>
                                <td><span class="plan-yes">&#10004;</span></td>
                                <td class="recommended"><span class="plan-yes">&#10004;</span></td>
                                <td><span class="plan-yes">&#10004;</span></td>
                            </tr>
                            <tr>
                                <td>Membresía Médica Móvil 24/7</td>
                                <td><span class="plan-yes">&#10004;</span></td>
                                <td class="recommended"><span class="plan-yes">&#10004;</span></td>
                                <td><span class="plan-yes">&#10004;</span></td>
                            </tr>
                            <tr>
                                <td>Asistencias (Dental, Viajes, Visión)</td>
                                <td><span class="plan-yes">&#10004;</span></td>
                                <td class="recommended"><span class="plan-yes">&#10004;</span></td>
                                <td><span class="plan-yes">&#10004;</span></td>
                            </tr>
                            <tr>
                                <td>Segunda opinión médica</td>
                                <td><span class="plan-no">&#10006;</span></td>
                                <td class="recommended"><span class="plan-yes">&#10004;</span></td>
                                <td><span class="plan-yes">&#10004;</span></td>
                            </tr>
                            <tr>
                                <td>Riesgo Selecto · Solidez Familiar</td>
                                <td><span class="plan-no">&#10006;</span></td>
                                <td class="recommended"><span class="plan-yes">&#10004;</span></td>
                                <td><span class="plan-yes">&#10004;</span></td>
                            </tr>
                            <tr>
                                <td>Cobertura Internacional</td>
                                <td><span class="plan-no">&#10006;</span></td>
                                <td class="recommended"><span class="plan-no">&#10006;</span></td>
                                <td><span class="plan-yes">&#10004;</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div style="text-align:center; margin-top: 32px;">
                <a href="#cotizar" class="btn btn-primary btn-lg btn-gmm">Cotizar con un Asesor Lux</a>
            </div>
        </div>
    </section>

    <!-- ══════════════════════════════════════════════
         SECCIÓN: MEMBRESÍA MÉDICA MÓVIL
    ══════════════════════════════════════════════ -->
    <section class="medica-movil-section" id="medica-movil">
        <div class="container">
            <div class="section-header">
                <span class="section-badge">Membresía Médica Móvil</span>
                <h2 class="section-title">Atención médica inmediata sin salir de casa</h2>
                <p class="section-description">
                    Con tu póliza gestionada a través de Seguros Lux, cuentas con orientación médica
                    ilimitada y sin costo las 24 horas, los 365 días del año. Una herramienta que cuida
                    tu salud sin afectar tu póliza.
                </p>
            </div>

            <div class="mm-features-grid">
                <div class="mm-feature">
                    <div class="mm-feature-icon">📞</div>
                    <h4>Orientación Telefónica</h4>
                    <p>Ilimitada, sin costo a nivel nacional. Te orientan sobre padecimientos que pueden ser atendidos sin presencia física.</p>
                </div>
                <div class="mm-feature">
                    <div class="mm-feature-icon">📱</div>
                    <h4>Videoconsulta</h4>
                    <p>Servicio donde no se requiera valoración presencial. Incluye Médico Internista, Pediatra y Ginecólogo.</p>
                </div>
                <div class="mm-feature">
                    <div class="mm-feature-icon">🏥</div>
                    <h4>Con o Sin Traslado</h4>
                    <p>Se determina si es necesario acudir a un centro hospitalario según la evaluación del médico inicial.</p>
                </div>
                <div class="mm-feature">
                    <div class="mm-feature-icon">🧠</div>
                    <h4>Psicólogos y Nutriólogos</h4>
                    <p>Consultas presenciales a costo preferente de $300, y diversas especialidades a $400 (copago de $500 por servicio).</p>
                </div>
            </div>

            <div class="mm-case-study">
                <h4>📖 Ejemplo real: Ricardo y su Membresía Médica Móvil</h4>
                <p style="color: rgba(255,255,255,0.7); font-size: 0.9rem; margin-bottom: 20px;">
                    A través de Médica Móvil, Ricardo obtuvo atención médica inmediata en tres ocasiones,
                    ahorrando tiempo y dinero sin afectar su póliza de Gastos Médicos Mayores.
                </p>
                <div class="mm-cases">
                    <div class="mm-case">
                        <p>🤧 Síntomas de resfriado — atendido vía Orientación Médica Telefónica con recomendación de antiinflamatorios.</p>
                        <div class="mm-case-saving">Ahorro: ~$700 en consulta médica</div>
                    </div>
                    <div class="mm-case">
                        <p>🦶 Esguince de tobillo — videollamada lo derivó al hospital en convenio sin cobro adicional de honorarios.</p>
                        <div class="mm-case-saving">Sin cobro adicional de honorarios</div>
                    </div>
                    <div class="mm-case">
                        <p>🌙 Gastroenteritis nocturna — orientación médica inmediata sin traslados ni esperas.</p>
                        <div class="mm-case-saving">Atención inmediata sin salir de casa</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ══════════════════════════════════════════════
         SECCIÓN: ASISTENCIAS
    ══════════════════════════════════════════════ -->
    <section class="asistencias" id="asistencias">
        <div class="container">
            <div class="section-header">
                <span class="section-badge">Asistencias incluidas</span>
                <h2 class="section-title">Protección que va más allá de lo médico</h2>
                <p class="section-description">
                    Tu seguro GMM incluye asistencias sin costo adicional que cubren desde el dentista
                    hasta el servicio funerario de un ser querido.
                </p>
            </div>

            <div class="asistencias-grid">
                <div class="asistencia-card">
                    <div class="asistencia-header">
                        <div class="asistencia-emoji">🦷</div>
                        <h3>Asistencia Dental</h3>
                    </div>
                    <ul>
                        <li>Revisión y diagnóstico sin costo</li>
                        <li>2 limpiezas dentales al año sin costo</li>
                        <li>Evaluación de especialistas incluida</li>
                        <li>Primera radiografía indicada sin costo</li>
                    </ul>
                    <div class="asistencia-descuento">
                        <strong>Hasta 70%</strong>
                        <span>en limpiezas adicionales, resinas, amalgamas y extracciones simples</span>
                    </div>
                    <div class="asistencia-descuento" style="margin-top:8px;">
                        <strong>50%</strong>
                        <span>en endodoncias, apicectomía, pulpotomía</span>
                    </div>
                </div>

                <div class="asistencia-card">
                    <div class="asistencia-header">
                        <div class="asistencia-emoji">✈️</div>
                        <h3>Asistencia en Viajes</h3>
                    </div>
                    <ul>
                        <li>Traslado médico durante emergencia en viaje</li>
                        <li>Traslado post-hospitalario al lugar de residencia</li>
                        <li>Repatriación y/o traslado de restos por fallecimiento</li>
                        <li>Boleto redondo para un familiar si la hospitalización supera 5 días</li>
                        <li>Ayuda para gastos de hospedaje por convalecencia (hasta 5 noches)</li>
                    </ul>
                </div>

                <div class="asistencia-card">
                    <div class="asistencia-header">
                        <div class="asistencia-emoji">👁️</div>
                        <h3>Asistencia Visión</h3>
                    </div>
                    <p style="font-size:0.85rem; color: var(--gray-500); margin-bottom:12px;">A través de ópticas Devlyn</p>
                    <ul>
                        <li>Consulta y valoración con optometrista sin costo</li>
                        <li>Lentes completos (armazón y lentes) o lentes de contacto</li>
                        <li>Descuentos hasta el 30% en diversos productos</li>
                    </ul>
                    <div class="asistencia-descuento">
                        <strong>Anualmente</strong>
                        <span>Lentes completos o de contacto sin costo adicional</span>
                    </div>
                </div>

                <div class="asistencia-card">
                    <div class="asistencia-header">
                        <div class="asistencia-emoji">🌹</div>
                        <h3>Asistencia Funeraria</h3>
                    </div>
                    <ul>
                        <li>Asesoría telefónica durante todo el proceso</li>
                        <li>Traslado y arreglo del cuerpo</li>
                        <li>Ataúd metálico estándar</li>
                        <li>Sala de velación hasta 24 horas</li>
                        <li>Gestión de trámites y asesoría jurídica</li>
                        <li>Servicio de tanatología</li>
                    </ul>
                    <div class="asistencia-descuento">
                        <strong>Hasta 100%</strong>
                        <span>de ahorro en el servicio funerario completo</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ══════════════════════════════════════════════
         SECCIÓN: RED HOSPITALARIA + SERVICIOS DIGITALES
         ↳ Fusión de dos secciones anteriores separadas
    ══════════════════════════════════════════════ -->
    <section class="red-digital" id="red-hospitales">
        <div class="container">
            <div class="section-header">
                <span class="section-badge">Red hospitalaria · Herramientas digitales</span>
                <h2 class="section-title">Atención en todo México y gestión desde tu celular</h2>
                <p class="section-description">
                    La aseguradora pone a tu disposición una de las redes médicas más grandes del país
                    y herramientas digitales para gestionar tu póliza en cualquier momento.
                </p>
            </div>

            <div class="red-digital-grid">
                <!-- Columna izquierda: Red hospitalaria -->
                <div class="red-col">
                    <h3 style="font-size:1.2rem; font-weight:700; color:var(--primary); margin-bottom:20px;">Red de proveedores</h3>
                    <div class="red-stats-row">
                        <div class="red-stat">
                            <div class="red-stat-number">+460</div>
                            <div class="red-stat-label">Hospitales certificados</div>
                        </div>
                        <div class="red-stat">
                            <div class="red-stat-number">+7,000</div>
                            <div class="red-stat-label">Médicos en convenio</div>
                        </div>
                        <div class="red-stat">
                            <div class="red-stat-number">+1,040</div>
                            <div class="red-stat-label">Laboratorios certificados</div>
                        </div>
                    </div>
                    <p style="font-size:0.9rem; color:var(--gray-600); line-height:1.7; margin-bottom:16px;">
                        En hospitales de la red de la aseguradora el pago es directo: la aseguradora paga al hospital.
                        Solo pagas tu deducible y coaseguro, sin anticipos ni papeleo complicado.
                        En urgencias, puedes ir a cualquier hospital y solicitar reembolso después.
                    </p>
                    <p style="font-size:0.85rem; font-weight:600; color:var(--primary); margin-bottom:10px;">Hospitales de primer nivel incluidos:</p>
                    <div class="hospitales-premium">
                        <span class="hospital-tag">Hospital ABC</span>
                        <span class="hospital-tag">Médica Sur</span>
                        <span class="hospital-tag">Ángeles</span>
                        <span class="hospital-tag">Christus Muguerza</span>
                        <span class="hospital-tag">San Javier</span>
                        <span class="hospital-tag">Star Médica</span>
                    </div>
                    <p style="font-size:0.8rem; color:var(--gray-400); margin-top:14px;">
                        Consulta el directorio completo desde la App Soy Cliente o pídele a tu asesor Lux que lo haga contigo.
                    </p>
                </div>

                <!-- Columna derecha: Servicios digitales -->
                <div class="digital-col">
                    <h3>Servicios digitales — disponibles para ti</h3>
                    <ul class="app-feature-list">
                        <li>
                            <div class="app-feat-icon">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            </div>
                            <div class="app-feat-text">
                                <h5>Consulta y descarga tu póliza</h5>
                                <p>Accede a tu póliza GMM y tu tarjeta en cualquier momento desde la app.</p>
                            </div>
                        </li>
                        <li>
                            <div class="app-feat-icon">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                            </div>
                            <div class="app-feat-text">
                                <h5>Reporta siniestros y da seguimiento</h5>
                                <p>Reporta un siniestro y revisa el estatus de tu trámite en tiempo real.</p>
                            </div>
                        </li>
                        <li>
                            <div class="app-feat-icon">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                            </div>
                            <div class="app-feat-text">
                                <h5>Trámites de reembolso en línea</h5>
                                <p>Reembolsos, indemnización, programación de servicios e ingreso hospitalario desde la app.</p>
                            </div>
                        </li>
                        <li>
                            <div class="app-feat-icon">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                            </div>
                            <div class="app-feat-text">
                                <h5>Localiza médicos y hospitales</h5>
                                <p>Directorio completo con búsqueda por especialidad, estado y ciudad.</p>
                            </div>
                        </li>
                        <li>
                            <div class="app-feat-icon">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 12l2 2 4-4"/><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            </div>
                            <div class="app-feat-text">
                                <h5>Consulta todas tus asistencias</h5>
                                <p>Accede a dental, viajes, visión y Médica Móvil directamente desde la app.</p>
                            </div>
                        </li>
                    </ul>
                    <div class="app-badge-row">
                        <div class="app-badge">📱 App Soy Cliente · iOS y Android</div>
                        <div class="app-badge">🌐 Portal web de la aseguradora</div>
                        <div class="app-badge">💬 Módulos hospitalarios + WhatsApp virtual</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ══════════════════════════════════════════════
         SECCIÓN: DIFERENCIADORES + PROGRAMA EN CASA
         ↳ Fusión de dos secciones anteriores
    ══════════════════════════════════════════════ -->
    <section class="diferenciadores" id="diferenciadores">
        <div class="container">
            <div class="section-header">
                <span class="section-badge">La aseguradora en números · 2024</span>
                <h2 class="section-title">La aseguradora que respalda tu póliza</h2>
                <p class="section-description">
                    Al contratar a través de Seguros Lux, te respaldas en la aseguradora
                    de mayor solidez financiera en México, con resultados que lo demuestran.
                </p>
            </div>

            <div class="dif-grid">
                <div class="dif-card">
                    <div class="dif-number">17,000 MDP</div>
                    <h4>Pagados en Servicios de Salud</h4>
                    <p>Se pagaron 17 mil millones de pesos en servicios de salud a sus asegurados durante 2024.</p>
                    <div class="dif-source">Fuente: la aseguradora · 2024</div>
                </div>
                <div class="dif-card">
                    <div class="dif-number">95 mil</div>
                    <h4>Reclamaciones Atendidas</h4>
                    <p>Casos de reclamaciones resueltos con eficiencia y rapidez en 2024.</p>
                    <div class="dif-source">Fuente: la aseguradora · 2024</div>
                </div>
                <div class="dif-card">
                    <div class="dif-number">384 mil</div>
                    <h4>Pólizas Nuevas Emitidas</h4>
                    <p>Nuevas pólizas de Gastos Médicos emitidas en 2024, reflejo de la confianza en la aseguradora.</p>
                    <div class="dif-source">Fuente: la aseguradora · 2024</div>
                </div>
            </div>

            <!-- Programa En Casa integrado -->
            <div class="en-casa-box">
                <div class="en-casa-icon">🏠</div>
                <div class="en-casa-content">
                    <h3>Programa en Casa</h3>
                    <p>
                        Un diferenciador exclusivo: si llegas a estar hospitalizado, su equipo médico
                        analiza tu caso junto con tu médico tratante para agilizar el alta y continuar tu
                        tratamiento con el equipo necesario <strong style="color:white;">en la comodidad de tu hogar</strong>,
                        sin que tengas que gestionar nada solo. Desde Seguros Lux te orientamos para activar
                        este beneficio en el momento que lo necesites.
                    </p>
                    <div class="en-casa-tags">
                        <div class="en-casa-tag">✓ Cobertura a nivel nacional</div>
                        <div class="en-casa-tag">✓ Agiliza el alta hospitalaria</div>
                        <div class="en-casa-tag">✓ Seguimiento médico en casa</div>
                        <div class="en-casa-tag">✓ Sin trámites adicionales para el asegurado</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ══════════════════════════════════════════════
         SECCIÓN: ACOMPAÑAMIENTO HOSPITALARIO (CÉDULA)
    ══════════════════════════════════════════════ -->
    <section class="cedula" id="cedula">
        <div class="container">
            <div class="section-header">
                <span class="section-badge">Acompañamiento Hospitalario</span>
                <h2 class="section-title">La aseguradora está contigo en cada paso</h2>
                <p class="section-description">
                    Módulos de atención dentro de los hospitales con mayor afluencia del país,
                    disponibles antes, durante y después de tu hospitalización.
                    Como tu intermediario, Seguros Lux también te acompaña en este proceso.
                </p>
            </div>

            <div class="cedula-steps">
                <div class="cedula-step">
                    <div class="cedula-step-number">1</div>
                    <h4>Ingreso Hospitalario</h4>
                    <p>Primer contacto para corroborar las condiciones generales de tu póliza y verificar la cobertura del padecimiento.</p>
                </div>
                <div class="cedula-step">
                    <div class="cedula-step-number">2</div>
                    <h4>Seguimiento a la Hospitalización</h4>
                    <p>Entrevista al médico tratante para seguimiento del diagnóstico. Entrega y explicación de la carta de autorización.</p>
                </div>
                <div class="cedula-step">
                    <div class="cedula-step-number">3</div>
                    <h4>Negociación Intermediaria</h4>
                    <p>La aseguradora negocia pagos, honorarios médicos y acuerdos resultantes de la revisión de gastos en tu beneficio.</p>
                </div>
                <div class="cedula-step">
                    <div class="cedula-step-number">4</div>
                    <h4>Alta Hospitalaria</h4>
                    <p>Acompañamiento en el proceso para agilizar tu salida y apoyo con formatos y trámites.</p>
                </div>
            </div>

            <div class="whatsapp-box">
                <div class="whatsapp-box-icon">📲</div>
                <div>
                    <h4>Supervisión Hospitalaria Virtual</h4>
                    <p>
                        Fuera del horario de los módulos hospitalarios, Se habilita supervisión virtual vía
                        <strong>WhatsApp</strong> — escanea el código QR en los módulos para acceder.
                        Y recuerda: tu asesor de Seguros Lux también está disponible para orientarte en cualquier momento.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- ══════════════════════════════════════════════
         SECCIÓN: BENEFICIOS ACCORDION
         ↳ Reenfocado en el valor de contratar con Lux
    ══════════════════════════════════════════════ -->
    <section class="benefits benefits-gmm" id="beneficios">
        <div class="container">
            <div class="benefits-content">
                <div class="benefits-text">
                    <span class="section-badge">Por qué elegirnos</span>
                    <h2 class="section-title">La mejor cobertura, asesorada por Lux</h2>
                    <p class="section-description">
                        La mejor cobertura médica para ti y tu familia, con el respaldo de
                        la aseguradora más sólida y el acompañamiento de Seguros Lux.
                    </p>

                    <div class="benefits-accordion-list">
                        <div class="benefit-accordion">
                            <button class="benefit-q" onclick="toggleBenefit(this)" aria-expanded="false">
                                <div class="benefit-icon benefit-icon-gmm">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M3 21h18"/><path d="M5 21V7l8-4 8 4v14"/>
                                    </svg>
                                </div>
                                <span>+460 Hospitales Certificados</span>
                                <svg class="benefit-arrow" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                            </button>
                            <div class="benefit-a">Acceso a más de 460 hospitales certificados en toda la República — ABC, Médica Sur, Ángeles, Christus Muguerza y más. Tu asesor Lux puede ayudarte a localizar el más cercano y conveniente para ti.</div>
                        </div>

                        <div class="benefit-accordion">
                            <button class="benefit-q" onclick="toggleBenefit(this)" aria-expanded="false">
                                <div class="benefit-icon benefit-icon-gmm">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                    </svg>
                                </div>
                                <span>+7,000 Médicos Especialistas en Convenio</span>
                                <svg class="benefit-arrow" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                            </button>
                            <div class="benefit-a">Red de más de 7,000 médicos certificados en todas las especialidades en todo el país. Consulta el directorio desde la App Soy Cliente o pídenos ayuda en Seguros Lux.</div>
                        </div>

                        <div class="benefit-accordion">
                            <button class="benefit-q" onclick="toggleBenefit(this)" aria-expanded="false">
                                <div class="benefit-icon benefit-icon-gmm">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>
                                    </svg>
                                </div>
                                <span>Orientación Médica 24/7</span>
                                <svg class="benefit-arrow" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                            </button>
                            <div class="benefit-a">Membresía Médica Móvil ilimitada y sin costo. Orientación telefónica y videoconsulta disponibles las 24 horas, los 365 días del año, en toda la República. Sin afectar tu póliza.</div>
                        </div>

                        <div class="benefit-accordion">
                            <button class="benefit-q" onclick="toggleBenefit(this)" aria-expanded="false">
                                <div class="benefit-icon benefit-icon-gmm">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4L12 14.01l-3-3"/>
                                    </svg>
                                </div>
                                <span>Pago Directo al Hospital</span>
                                <svg class="benefit-arrow" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                            </button>
                            <div class="benefit-a">En hospitales de la red de la aseguradora el pago es directo: la aseguradora paga al hospital. Solo pagas tu deducible y coaseguro. En urgencias, puedes ir a cualquier hospital y solicitar reembolso después.</div>
                        </div>

                        <div class="benefit-accordion">
                            <button class="benefit-q" onclick="toggleBenefit(this)" aria-expanded="false">
                                <div class="benefit-icon benefit-icon-gmm">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                                    </svg>
                                </div>
                                <span>Asesoría Seguros Lux en cada siniestro</span>
                                <svg class="benefit-arrow" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                            </button>
                            <div class="benefit-a">No estás solo cuando más lo necesitas. Como tu intermediario, Seguros Lux te acompaña en autorizaciones, reclamaciones y cualquier gestión ante la aseguradora — con más de 15 años de experiencia a tu lado.</div>
                        </div>
                    </div>
                </div>

                <div class="benefits-visual">
                    <div class="benefits-card benefits-card-gmm">
                        <div class="benefits-banner">
                            <span class="benefits-card-badge benefits-badge-gmm">Cotiza Gratis</span>
                            <img src="/images/banner_tc.jpeg" alt="Gastos Médicos Mayores">
                        </div>
                        <div class="benefits-card-body">
                            <a href="#cotizar" class="btn btn-primary btn-block btn-gmm">
                                <span>Obtener Cotización</span>
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                    <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
                                </svg>
                            </a>
                            <div class="conditions-mini">
                                <p>
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>
                                    </svg>
                                    Asesoría personalizada sin compromiso. Póliza emitida por la aseguradora.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Form de Cotización -->
    <section class="form-section" id="cotizar">
        <div class="container">
            <div class="section-header">
                <span class="section-badge">Cotización</span>
                <h2 class="section-title">Cotiza tu Seguro GMM con Seguros Lux</h2>
                <p class="section-description">
                    Completa el formulario y un asesor certificado te contactará con una
                    cotización personalizada, sin compromiso.
                </p>
            </div>

            <div class="form-wrapper">
                <form id="formGMM" class="contact-form">
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
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edad">Edad <span class="required">*</span></label>
                            <input type="number" id="edad" name="edad" placeholder="35" min="0" max="99" required>
                        </div>
                        <div class="form-group">
                            <label for="dependientes">Número de dependientes</label>
                            <select id="dependientes" name="dependientes">
                                <option value="0">Solo yo</option>
                                <option value="1">1 dependiente</option>
                                <option value="2">2 dependientes</option>
                                <option value="3">3 dependientes</option>
                                <option value="4">4+ dependientes</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="plan">Plan de interés</label>
                        <select id="plan" name="plan">
                            <option value="">No lo sé aún — quiero orientación de un asesor</option>
                            <option value="esencial">Esencial</option>
                            <option value="versatil">Versátil</option>
                            <option value="personaliza">Personaliza</option>
                            <option value="premium">Premium / Platino</option>
                            <option value="internacional">Internacional (VIP / LAI / Enlace)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="comentarios">Comentarios adicionales</label>
                        <textarea id="comentarios" name="comentarios" rows="3" placeholder="Condiciones preexistentes, preferencias de hospitales, presupuesto, etc."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg btn-gmm btn-block">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 12l2 2 4-4"/>
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        </svg>
                        Solicitar Cotización
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
                <h2 class="section-title">Dudas sobre GMM y Seguros Lux</h2>
            </div>
            <div class="faq-list">
                <div class="faq-item">
                    <button class="faq-question" onclick="toggleFaq(this)">
                        <span>¿Qué ventaja tengo al contratar con Seguros Lux?</span>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                    </button>
                    <div class="faq-answer">
                        <p>Tu póliza la emite la aseguradora. Seguros Lux es tu intermediario autorizado: te asesoramos para elegir el plan correcto, te acompañamos en siniestros y gestionamos cualquier trámite. Sin costo adicional para ti.</p>
                    </div>
                </div>
                <div class="faq-item">
                    <button class="faq-question" onclick="toggleFaq(this)">
                        <span>¿Qué es el deducible y coaseguro?</span>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                    </button>
                    <div class="faq-answer">
                        <p>El deducible es una cantidad fija que pagas al inicio de cada padecimiento. El coaseguro es un porcentaje (generalmente 10–20%) que pagas de los gastos después del deducible, hasta un tope máximo. Tu asesor Lux te explica cuál conviene más según tu situación.</p>
                    </div>
                </div>
                <div class="faq-item">
                    <button class="faq-question" onclick="toggleFaq(this)">
                        <span>¿Cubren enfermedades preexistentes?</span>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                    </button>
                    <div class="faq-answer">
                        <p>Depende de cada caso. Algunas condiciones pueden tener periodos de espera, exclusiones o extraprimas. Es importante declararlas al contratar. En Seguros Lux te ayudamos a evaluar las opciones disponibles y a presentar tu solicitud de la manera más favorable.</p>
                    </div>
                </div>
                <div class="faq-item">
                    <button class="faq-question" onclick="toggleFaq(this)">
                        <span>¿Puedo agregar a mi familia?</span>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                    </button>
                    <div class="faq-answer">
                        <p>Sí, puedes incluir a tu cónyuge e hijos. Los planes familiares suelen tener descuentos especiales. También existe la opción de Solidez Familiar como beneficio adicional en varios productos.</p>
                    </div>
                </div>
                <div class="faq-item">
                    <button class="faq-question" onclick="toggleFaq(this)">
                        <span>¿Cómo funciona el pago directo al hospital?</span>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                    </button>
                    <div class="faq-answer">
                        <p>En los +460 hospitales de la red, el pago es directo: la aseguradora paga al hospital. Solo pagas tu deducible y coaseguro. En urgencias, puedes ir a cualquier hospital y solicitar reembolso después. Seguros Lux te orienta en este proceso.</p>
                    </div>
                </div>
                <div class="faq-item">
                    <button class="faq-question" onclick="toggleFaq(this)">
                        <span>¿Qué incluye la Membresía Médica Móvil?</span>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                    </button>
                    <div class="faq-answer">
                        <p>Orientación médica telefónica ilimitada y sin costo, videoconsultas, orientación para determinar si necesitas atención presencial, y acceso a psicólogos y nutriólogos a costo preferente. Disponible las 24 horas, los 365 días, sin afectar tu póliza.</p>
                    </div>
                </div>
                <div class="faq-item">
                    <button class="faq-question" onclick="toggleFaq(this)">
                        <span>¿Qué es el Programa en Casa?</span>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                    </button>
                    <div class="faq-answer">
                        <p>Es un diferenciador exclusivo donde, si eres hospitalizado, el equipo médico analiza tu caso junto con tu médico tratante para agilizar el alta y continuar tu tratamiento en casa. Desde Seguros Lux te ayudamos a activar este beneficio.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta cta-gmm" id="contacto">
        <div class="container">
            <div class="cta-content">
                <div class="cta-text">
                    <h2>El mejor GMM de México, con el asesor que necesitas</h2>
                    <p>
                        120 años de solidez, +460 hospitales y atención médica 24/7.
                        Seguros Lux: la asesoría personalizada para que elijas bien y nunca estés solo.
                        Cotiza sin compromiso hoy.
                    </p>
                </div>
                <div class="cta-buttons">
                    <a href="#cotizar" class="btn btn-white btn-lg">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 12l2 2 4-4"/>
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        </svg>
                        Cotizar Ahora
                    </a>
                    <a href="tel:+525541232311" class="btn btn-outline-white btn-lg">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                        </svg>
                        Hablar con un Asesor
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
                    <p>Intermediario de seguros autorizado por la CNSF. Protegiendo lo que más importa desde 2010.</p>
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
                    </ul>
                </div>
            </div>

            <div class="footer-bottom">
                <p>@Seguros Lux. Intermediario de seguros autorizado por la CNSF. Todos los derechos reservados.</p>
                <div class="footer-legal">
                    <a href="/aviso-privacidad.php">Aviso de Privacidad</a>
                    <a href="/terminos.php">Términos y Condiciones</a>
                </div>
            </div>
        </div>
    </footer>

    <script>
    (function() {
        // ── Product Tabs ──────────────────────────────
        document.querySelectorAll('.product-tab').forEach(function(tab) {
            tab.addEventListener('click', function() {
                var target = this.dataset.tab;
                document.querySelectorAll('.product-tab').forEach(function(t) { t.classList.remove('active'); });
                document.querySelectorAll('.product-panel').forEach(function(p) { p.classList.remove('active'); });
                this.classList.add('active');
                var panel = document.getElementById('tab-' + target);
                if (panel) panel.classList.add('active');
            });
        });

        // ── Form ──────────────────────────────────────
        var form = document.getElementById('formGMM');
        if (!form) return;
        var minDate = new Date(); minDate.setDate(minDate.getDate()+1);
        var fechaGroup = document.createElement('div');
        fechaGroup.className = 'form-group';
        fechaGroup.innerHTML = '<label for="fecha_gmm">Fecha preferida para contacto <span class="required">*</span></label>' +
            '<input type="date" id="fecha_gmm" name="fecha_gmm" required min="' + minDate.toISOString().split('T')[0] + '">';
        var submitBtn = form.querySelector('[type=submit]');
        if (submitBtn) form.insertBefore(fechaGroup, submitBtn);

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            var nombre   = (document.getElementById('nombre').value   || '').trim();
            var correo   = (document.getElementById('correo').value   || '').trim();
            var telefono = (document.getElementById('telefono').value || '').trim();
            var fecha    = (document.getElementById('fecha_gmm') ? document.getElementById('fecha_gmm').value : '').trim();
            var edad     = (document.getElementById('edad') ? document.getElementById('edad').value : '').trim();
            var deps     = (document.getElementById('dependientes') ? document.getElementById('dependientes').value : '0');
            var plan     = (document.getElementById('plan') ? document.getElementById('plan').value : '');

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
                    fecha_preferida: fecha, ramo: 'gmm',
                    edad: edad, dependientes: deps, plan: plan,
                    comentarios: (document.getElementById('comentarios') ? document.getElementById('comentarios').value : '')
                })
            })
            .then(function(r){ return r.json(); })
            .then(function(d) {
                if (d.ok) {
                    form.innerHTML = '<div style="text-align:center;padding:32px 20px;">' +
                        '<div style="font-size:56px;margin-bottom:12px;">✅</div>' +
                        '<h3 style="color:#0ea5e9;margin-bottom:8px;">¡Solicitud recibida!</h3>' +
                        '<p style="color:#555;max-width:420px;margin:0 auto;">Hola <strong>' + nombre + '</strong>, ' +
                        'un asesor de Seguros Lux te contactará antes del <strong>' +
                        fecha.split('-').reverse().join('/') + '</strong> con tu cotización personalizada. Revisa tu correo.</p></div>';
                } else {
                    alert('Error: ' + (d.msg || 'Intenta de nuevo.'));
                    if (btn) { btn.disabled = false; btn.textContent = 'Solicitar Cotización'; }
                }
            })
            .catch(function() {
                alert('Error de conexión. Intenta de nuevo.');
                if (btn) { btn.disabled = false; btn.textContent = 'Solicitar Cotización'; }
            });
        });
    })();

    // ── Benefit Accordion ──────────────────────────
    function toggleBenefit(btn) {
        var acc   = btn.closest('.benefit-accordion');
        var ans   = acc.querySelector('.benefit-a');
        var isOpen = btn.getAttribute('aria-expanded') === 'true';
        acc.closest('.benefits-accordion-list')
           ?.querySelectorAll('.benefit-q[aria-expanded="true"]')
           .forEach(function(other) {
               if (other !== btn) {
                   other.setAttribute('aria-expanded', 'false');
                   other.closest('.benefit-accordion').querySelector('.benefit-a').classList.remove('open');
               }
           });
        btn.setAttribute('aria-expanded', String(!isOpen));
        ans.classList.toggle('open', !isOpen);
    }
    </script>

    <?php require view('partials/_chat.php'); ?>
    <script src="/js/landing.js" defer></script>
    <script src="/js/gmm.js" defer></script>
</body>
</html>