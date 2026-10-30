<?php
declare(strict_types=1);

use App\Security\FiltroRamo;
use App\Security\SessionManager;

SessionManager::requireAuth('/login');

/*
 * /set-ramo — cambia el filtro de ramo único del CRM (el selector del menú
 * lateral) y vuelve a la página desde donde se eligió.
 */

$ramoPedido = $_POST['ramo'] ?? $_GET['ramo'] ?? '';
FiltroRamo::fijar(is_string($ramoPedido) ? $ramoPedido : null);

$redirect = $_POST['redirect'] ?? $_GET['redirect'] ?? '/dashboard';

// Validación mínima de la URL de redirect (solo rutas relativas propias).
// "//algo" sería una URL relativa al protocolo, es decir, otro dominio.
if (!is_string($redirect)
    || !preg_match('#^/[a-zA-Z0-9/_?=&%.+-]*$#', $redirect)
    || str_starts_with($redirect, '//')) {
    $redirect = '/dashboard';
}

// Se quita un ?ramo= viejo de la ruta: si no, la página volvería a fijarlo.
[$ruta, $query] = array_pad(explode('?', $redirect, 2), 2, '');
parse_str($query, $params);
unset($params['ramo']);

header('Location: ' . $ruta . ($params !== [] ? '?' . http_build_query($params) : ''));
exit;
