<?php
declare(strict_types=1);

use App\Models\CatalogoModel;
use App\Security\SessionManager;
use App\Security\Tenant;

SessionManager::requireAuth('/login');

/*
 * /set-ramo — redirección de compatibilidad.
 *
 * Antes guardaba el ramo en la sesión y filtraba en silencio las demás
 * pantallas. Ahora el filtro de ramo es de cada página (?ramo=<clave>), así
 * que aquí ya no se escribe nada: solo se reenvía a `redirect` con el ramo en
 * la URL, para que los enlaces y marcadores viejos sigan llevando al mismo
 * sitio filtrado.
 */

// Solo se reenvía un ramo activo del catálogo de la empresa. 'todos', vacío o
// desconocido → sin filtro. Sin empresa en contexto (propietario en modo
// plataforma) no hay catálogo que leer: se aceptan las claves que conoce el
// código y la página de destino vuelve a validar contra el suyo.
$ramoPedido   = $_POST['ramo'] ?? $_GET['ramo'] ?? '';
$ramosValidos = Tenant::id() !== null ? (new CatalogoModel())->ramos() : CatalogoModel::RAMOS;
$ramo         = is_string($ramoPedido) && isset($ramosValidos[$ramoPedido]) ? $ramoPedido : null;

// Soporte para ?redirect=/cotizaciones (GET) o redirigir al dashboard por default
$redirect = $_POST['redirect'] ?? $_GET['redirect'] ?? '/dashboard';

// Validación mínima de la URL de redirect (solo rutas relativas propias).
// "//algo" sería una URL relativa al protocolo, es decir, otro dominio.
if (!is_string($redirect)
    || !preg_match('#^/[a-zA-Z0-9/_?=&-]*$#', $redirect)
    || str_starts_with($redirect, '//')) {
    $redirect = '/dashboard';
}

// El ramo sustituye al que ya trajera la ruta; el resto de parámetros se
// conserva.
[$ruta, $query] = array_pad(explode('?', $redirect, 2), 2, '');
parse_str($query, $params);
unset($params['ramo']);
if ($ramo !== null) {
    $params['ramo'] = $ramo;
}

header('Location: ' . $ruta . ($params !== [] ? '?' . http_build_query($params) : ''));
exit;
