<?php
declare(strict_types=1);

/**
 * Autoloader PSR-4.
 * También instala un manejador de errores global que evita que warnings
 * rompan las respuestas JSON en los endpoints AJAX.
 */
spl_autoload_register(function (string $class): void {
    $map = [
        'App\\'      => __DIR__ . '/../App/',
        'Database\\' => __DIR__ . '/../Database/',
    ];

    foreach ($map as $prefix => $baseDir) {
        if (str_starts_with($class, $prefix)) {
            $relative = substr($class, strlen($prefix));
            $file     = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
    }
});

/**
 * Convierte errores de PHP en excepciones para que no rompan el JSON.
 * En producción, los warnings/notices no deberían llegar al cliente.
 */
set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
    // Solo convertir errores fatales y warnings, no notices en producción
    if ($errno === E_ERROR || $errno === E_WARNING || $errno === E_PARSE) {
        throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
    }
    return false; // Dejar que PHP maneje el resto
});