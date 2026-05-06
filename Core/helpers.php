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
