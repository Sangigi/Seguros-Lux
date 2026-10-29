<?php
declare(strict_types=1);
define('ROOT', dirname(__DIR__));

// ── Entorno ───────────────────────────────────────────────────────────────────
// NUNCA mostrar errores en producción: filtraría rutas internas, credenciales
// de BD y stack traces. Los errores se escriben solo en el log del servidor.
$appEnv = getenv('APP_ENV') ?: 'production';

if ($appEnv === 'development') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL);                  // seguir registrando, pero no mostrar
    ini_set('log_errors', '1');
}

// ── Cabeceras de seguridad HTTP ───────────────────────────────────────────────
// Se emiten aquí, en el front-controller, una sola vez para TODAS las rutas.
// Así no dependen de que cada vista/controlador las recuerde emitir.
use App\Security\SessionManager;

require ROOT . '/.env.php';
require ROOT . '/Core/Autoloader.php';
require ROOT . '/Core/helpers.php';
require ROOT . '/Core/Router.php';

SessionManager::enforceHttps();         // redirige HTTP → HTTPS en producción
SessionManager::sendSecurityHeaders();  // CSP, HSTS, X-Frame-Options, etc.

// ── Helper de vistas ──────────────────────────────────────────────────────────
function view(string $relative): string {
    return Router::normalizePath(ROOT . '/App/Views/' . $relative);
}

// ── Router ────────────────────────────────────────────────────────────────────
$router = new Router();

use App\Controllers\{
    LoginController,
    LogoutController,
    ClientController,
    CitaController,
    ConfigController,
    ExportController,
    HorasOcupadasController,
    ActivityController,
    HealthController,
    EmpresaController,
    InvitacionController
};
use App\Controllers\FileController;
use App\Controllers\VidaSolicitudController;

$router->add('POST', '/api/vida-solicitud', VidaSolicitudController::class);
$router->add('GET',  '/archivo',            FileController::class);

// ── Rutas públicas ────────────────────────────────────────────────────────────
$router->add('GET',  '/',           fn() => require view('public/landing.php'));
$router->add('GET',  '/login',      fn() => require view('public/login.php'));
$router->add('POST', '/login',      LoginController::class);
$router->add('GET',  '/logout',     LogoutController::class);
$router->add('POST', '/logout',     LogoutController::class);
$router->add('GET',  '/formulario', fn() => require view('public/form_client.php'));

// Activación de cuenta por invitación. Es pública porque quien la usa todavía
// no tiene cuenta: el token del enlace es lo que autoriza, y sin uno válido la
// página no hace nada.
$router->add('GET',  '/registro',   fn() => require view('public/registro.php'));
$router->add('POST', '/registro',   fn() => require view('public/registro.php'));

$router->add('GET', '/blog', function () {
    header('Location: https://blog.seguroslux.mx', true, 301);
    exit;
});

// ── Landings de seguros ───────────────────────────────────────────────────────
$router->add('GET', '/seguros/autos',                   fn() => require view('public/seguros/autos.php'));
$router->add('GET', '/seguros/vida',                    fn() => require view('public/seguros/vida.php'));
$router->add('GET', '/seguros/gmm',                     fn() => require view('public/seguros/gmm.php'));
$router->add('GET', '/seguros/gastos-medicos-mayores',  fn() => require view('public/seguros/gmm.php'));
$router->add('GET', '/seguros/empresas',                fn() => require view('public/seguros/empresas.php'));
$router->add('GET', '/seguros/corporativo',             fn() => require view('public/seguros/empresas.php'));
$router->add('GET', '/autos',           fn() => require view('public/seguros/autos.php'));
$router->add('GET', '/vida',            fn() => require view('public/seguros/vida.php'));
$router->add('GET', '/gmm',             fn() => require view('public/seguros/gmm.php'));
$router->add('GET', '/gastos-medicos',  fn() => require view('public/seguros/gmm.php'));
$router->add('GET', '/empresas',        fn() => require view('public/seguros/empresas.php'));
$router->add('GET', '/corporativo',     fn() => require view('public/seguros/empresas.php'));
$router->add('GET', '/terminos',  fn() => require view('public/legales/terminos.php'));
$router->add('GET', '/condiciones',  fn() => require view('public/legales/condiciones.php'));
$router->add('GET', '/aviso-privacidad',  fn() => require view('public/legales/aviso-privacidad.php'));

// ── Partials ──────────────────────────────────────────────────────────────────
$router->add('GET', '/chat', fn() => require view('partials/_chat.php'));

// ── Webhooks externos (no requieren sesión) ───────────────────────────────────
$router->add('POST', '/telegram/webhook', fn() => require view('public/telegram_bot.php'));
$router->add('GET',  '/whatsapp/webhook', fn() => require ROOT . '/public/whatsapp/whatsapp_webhook.php');
$router->add('POST', '/whatsapp/webhook', fn() => require ROOT . '/public/whatsapp/whatsapp_webhook.php');
$router->add('GET',  '/meta/webhook',     fn() => require ROOT . '/public/meta/meta_webhook.php');
$router->add('POST', '/meta/webhook',     fn() => require ROOT . '/public/meta/meta_webhook.php');

// ── App — asesor ──────────────────────────────────────────────────────────────
$router->add('GET',  '/dashboard',        fn() => require view('dashboard/index.php'));
$router->add('GET',  '/set-ramo',         fn() => require view('dashboard/set_ramo.php'));
$router->add('POST', '/set-ramo',         fn() => require view('dashboard/set_ramo.php'));

$router->add('GET',  '/clientes',         fn() => require view('clientes/index.php'));
$router->add('POST', '/clientes',         fn() => require view('clientes/index.php'));
$router->add('GET',  '/cliente/:id',      fn() => require view('clientes/detalle.php'));
$router->add('POST', '/cliente/:id',      fn() => require view('clientes/detalle.php'));

$router->add('GET',  '/api/clientes',     ClientController::class);
$router->add('POST', '/api/clientes',     ClientController::class);

$router->add('GET',  '/cotizaciones',     fn() => require view('cotizaciones/index.php'));
$router->add('POST', '/cotizaciones',     fn() => require view('cotizaciones/index.php'));
$router->add('GET',  '/crear-cotizacion', fn() => require view('cotizaciones/crear.php'));
$router->add('POST', '/crear-cotizacion', fn() => require view('cotizaciones/crear.php'));

$router->add('GET',  '/citas',            fn() => require view('citas/index.php'));
$router->add('POST', '/citas',            fn() => require view('citas/index.php'));

$router->add('GET',  '/reportes',         fn() => require view('reportes/index.php'));

// ── App — admin ───────────────────────────────────────────────────────────────
$router->add('GET',  '/admin',            fn() => require view('admin/index.php'));
$router->add('POST', '/admin',            fn() => require view('admin/index.php'));

// ── Equipo de la empresa (alta de usuarios por invitación) ────────────────────
$router->add('GET',  '/equipo',           fn() => require view('equipo/index.php'));

// ── Plataforma — solo el propietario ──────────────────────────────────────────
// Las vistas y la API comprueban Roles::EMPRESAS_* por su cuenta; el router
// solo enruta. La autorización nunca depende de que una ruta esté "escondida".
$router->add('GET',  '/plataforma/empresas', fn() => require view('plataforma/empresas.php'));

// ── API / AJAX ────────────────────────────────────────────────────────────────
$router->add('GET',  '/horas-ocupadas',     HorasOcupadasController::class);
$router->add('GET',  '/api/horas-ocupadas', HorasOcupadasController::class);
$router->add('GET',  '/api/config',         ConfigController::class);
$router->add('POST', '/api/config',         ConfigController::class);
$router->add('GET',  '/api/empresas',       EmpresaController::class);
$router->add('POST', '/api/empresas',       EmpresaController::class);
$router->add('GET',  '/api/invitaciones',   InvitacionController::class);
$router->add('POST', '/api/invitaciones',   InvitacionController::class);
$router->add('GET',  '/api/ping',           ActivityController::class);
$router->add('GET',  '/api/health',         HealthController::class);
$router->add('GET',  '/exportar',           ExportController::class);
$router->add('GET',  '/api/notificaciones', fn() => require view('api/notificaciones.php'));
$router->add('POST', '/api/notificaciones', fn() => require view('api/notificaciones.php'));

// ── Dispatch ──────────────────────────────────────────────────────────────────
$uri = $_SERVER['REQUEST_URI'];
$uri = preg_replace('#/+#', '/', $uri);
$uri = '/' . ltrim($uri, '/');
$router->dispatch($_SERVER['REQUEST_METHOD'], $uri);
