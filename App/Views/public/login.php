<?php
declare(strict_types=1);

use App\Security\SessionManager;

// ── 1. Forzar HTTPS en producción ────────────────────────────────────────────
SessionManager::enforceHttps();

// ── 2. Cabeceras HTTP de seguridad ────────────────────────────────────────────
SessionManager::sendSecurityHeaders();

// ── 3. Iniciar sesión y redirigir si ya está autenticado ──────────────────────
SessionManager::start();

if (SessionManager::isAuthenticated()) {
    header('Location: ' . (SessionManager::isAdmin() ? '/admin' : '/dashboard'));
    exit;
}

// ── 4. Token CSRF para el formulario ─────────────────────────────────────────
$csrfToken = SessionManager::getCsrfToken();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?= asset_url('css/LR.css') ?>">
    <title>Iniciar sesión | Seguros Lux</title>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= asset_url('images/logo.png') ?>">
</head>
<body>
<div class="w" id="lrRoot">

    <!-- PANEL IZQ -->
    <div class="left" id="leftPanel">
        <div class="cursor-light" id="cursorLight"></div>
        <canvas id="particles-canvas" style="position:absolute;inset:0;pointer-events:none;"></canvas>

        <svg id="kinetic-svg" viewBox="0 0 400 700" preserveAspectRatio="xMidYMid slice">
            <!-- Líneas generadas por JS -->
        </svg>

        <div class="left-logo">
            <div class="left-logo-mark">
                <div class="logo-square"></div>
                <div class="logo-wordmark">Seguros Lux</div>
            </div>
        </div>

        <div class="left-content">
            <div class="left-tag">Portal de agentes</div>
            <div class="left-headline">
                Protegemos<br>lo que más<br><em>importa.</em>
            </div>
        </div>

        <div class="left-foot">© 2025 Lux Seguros · Todos los derechos reservados</div>
    </div>

    <!-- PANEL DER -->
    <div class="right">
        <div class="bg-num">01</div>

        <div class="form-header">
            <div class="form-eyebrow">Bienvenido de vuelta</div>
            <div class="form-title">Inicia sesión<br>en tu cuenta</div>
        </div>

        <!-- Alerta de error de credenciales -->
        <div class="alert" id="alertBox">
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                <circle cx="7" cy="7" r="6" stroke="#b02020" stroke-width="1.2"/>
                <line x1="7" y1="4" x2="7" y2="8" stroke="#b02020" stroke-width="1.3" stroke-linecap="round"/>
                <circle cx="7" cy="10" r=".8" fill="#b02020"/>
            </svg>
            Correo o contraseña incorrectos
        </div>

        <!-- Alerta de sesión expirada (server-side) -->
        <?php if (isset($_GET['expired'])): ?>
        <div class="alert show" style="background:rgba(245,158,11,.1);color:#92400e;border-color:rgba(245,158,11,.2)">
            Tu sesión expiró por inactividad. Vuelve a iniciar sesión.
        </div>
        <?php endif; ?>

        <!-- Alerta de cuenta bloqueada por intentos fallidos -->
        <?php if (isset($_GET['locked'])): ?>
        <div class="alert show" style="background:rgba(239,68,68,.1);color:#991b1b;border-color:rgba(239,68,68,.2)">
            Cuenta bloqueada temporalmente por múltiples intentos fallidos. Espera unos minutos.
        </div>
        <?php endif; ?>

        <form id="loginForm" method="POST" action="/login" novalidate>

            <!-- Token CSRF oculto — imprescindible, no eliminar -->
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

            <div class="field">
                <div class="field-inner" id="wi-email">
                    <span class="field-label" id="lbl-email">Correo electrónico</span>
                    <input
                        class="field-inp"
                        type="email"
                        name="correo"
                        id="inp-email"
                        required
                        autocomplete="email"
                        maxlength="254"
                    >
                    <div class="field-bar"></div>
                </div>
            </div>

            <div class="field">
                <div class="field-inner" id="wi-pwd">
                    <span class="field-label" id="lbl-pwd">Contraseña</span>
                    <input
                        class="field-inp"
                        type="password"
                        name="contrasena"
                        id="inp-pwd"
                        required
                        autocomplete="current-password"
                        maxlength="128"
                    >
                    <button type="button" class="eye-btn" id="eyeBtn" aria-label="Mostrar/ocultar contraseña">
                        <svg id="eyeIco" width="16" height="16" viewBox="0 0 16 16" fill="none">
                            <path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z" stroke="currentColor" stroke-width="1.2"/>
                            <circle cx="8" cy="8" r="2.2" stroke="currentColor" stroke-width="1.2"/>
                        </svg>
                    </button>
                    <div class="field-bar"></div>
                </div>
            </div>

            <div class="btn-wrap">
                <button type="submit" class="btn-submit" id="btnS">
                    <div class="btn-text-wrap">
                        <span>Acceder al sistema</span>
                    </div>
                    <svg class="btn-arrow" width="16" height="16" viewBox="0 0 16 16" fill="none">
                        <path d="M3 8h10M9 4l4 4-4 4" stroke="#f5f4f1" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <div class="btn-dots" id="btnDots">
                        <div class="dot"></div><div class="dot"></div><div class="dot"></div>
                    </div>
                </button>
            </div>

        </form>

        <div class="back-home">
            <a href="/" class="back-link">
                <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                    <path d="M8 1L3 6l5 5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Volver a la página principal
            </a>
        </div>

        <div class="stats-row">
            <div class="stat">
                <div class="stat-n" id="sn1">0</div>
                <div class="stat-l">Agentes activos</div>
            </div>
            <div class="stat">
                <div class="stat-n" id="sn2">0</div>
                <div class="stat-l">Pólizas vigentes</div>
            </div>
            <div class="stat">
                <div class="stat-n" id="sn3">0</div>
                <div class="stat-l">Años de operación</div>
            </div>
        </div>
    </div><!-- /right -->

</div><!-- /lrRoot -->

<script src="<?= asset_url('js/login.js') ?>"></script>
</body>
</html>