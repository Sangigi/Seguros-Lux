<?php
declare(strict_types=1);

use App\Security\SessionManager;

SessionManager::requireAuth('/login');

$ramosValidos = ['autos', 'vida', 'gastos_medicos', 'empresas'];
$ramo         = $_POST['ramo'] ?? $_GET['ramo'] ?? 'autos';

// Si recibe 'todos' o un valor vacío, limpia el ramo de sesión (muestra todo)
if ($ramo === 'todos' || $ramo === '' || !in_array($ramo, $ramosValidos, true)) {
    SessionManager::clearRamo();
} else {
    SessionManager::setRamo($ramo);
}

// Soporte para ?redirect=/cotizaciones (GET) o redirigir al dashboard por default
$redirect = $_POST['redirect'] ?? $_GET['redirect'] ?? '/dashboard';

// Validación mínima de la URL de redirect (solo rutas relativas propias)
if (!preg_match('#^/[a-zA-Z0-9/_?=&-]*$#', $redirect)) {
    $redirect = '/dashboard';
}

header('Location: ' . $redirect);
exit;