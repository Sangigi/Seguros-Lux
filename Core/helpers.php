<?php

declare(strict_types=1);

/**
 * Prefijo opcional para CSS/JS/imágenes cuando el DocumentRoot no es la carpeta public/.
 * En .env.php: putenv('ASSET_PREFIX=/public');  (ajusta a la ruta real bajo la que se sirven los estáticos)
 */
function asset_url(string $path): string
{
    $prefix = rtrim((string) (getenv('ASSET_PREFIX') ?: ''), '/');
    $path = '/' . ltrim($path, '/');

    return $prefix . $path;
}

/**
 * Ícono SVG del sprite public/svg/iconos.svg (Bootstrap Icons).
 *
 * Toma el tamaño y el color del texto que lo rodea, igual que el emoji al
 * que sustituye. $clase añade clases propias, por ejemplo para colorearlo.
 * En JS, iconoSvg() de public/js/iconos.js genera el mismo marcado.
 */
function icono(string $nombre, string $clase = ''): string
{
    return '<svg class="' . htmlspecialchars(trim('ico ' . $clase), ENT_QUOTES) . '"'
        . ' width="1em" height="1em" fill="currentColor" aria-hidden="true"'
        . ' style="vertical-align:-.125em;flex-shrink:0">'
        . '<use href="' . asset_url('svg/iconos.svg') . '#' . htmlspecialchars($nombre, ENT_QUOTES) . '"/></svg>';
}
