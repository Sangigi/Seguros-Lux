<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Resuelve si un origen de petición debe recibir cabecera Access-Control-Allow-Origin.
 */
final class CorsHelper
{
    /**
     * Origen a reflejar en la cabecera, o null si no debe enviarse CORS.
     */
    public static function resolveAllowedOrigin(?string $requestOrigin, string $allowedCsv): ?string
    {
        $allowed = array_values(array_filter(array_map('trim', explode(',', $allowedCsv)), static fn (string $o): bool => $o !== ''));
        if ($allowed === []) {
            return null;
        }

        if ($requestOrigin === null || $requestOrigin === '') {
            return null;
        }

        return in_array($requestOrigin, $allowed, true) ? $requestOrigin : null;
    }
}
