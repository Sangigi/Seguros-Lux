#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * bin/migrate.php — CLI de migraciones de Seguros Lux.
 *
 *   php bin/migrate.php status   Lista migraciones y su estado
 *   php bin/migrate.php up       Aplica las pendientes
 *   php bin/migrate.php down     Revierte la última aplicada
 *
 * Lee la conexión del entorno igual que el CRM (.env.php en la raíz).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script solo corre por línea de comandos.\n");
}

$raiz = dirname(__DIR__);

$autoload = $raiz . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}
require_once $raiz . '/Core/Autoloader.php';

$env = $raiz . '/.env.php';
if (is_file($env)) {
    require_once $env;
}

require_once $raiz . '/Database/Connection.php';
require_once $raiz . '/Database/SchemaHelper.php';
require_once $raiz . '/Database/Migrator.php';

$comando = $argv[1] ?? 'status';

$color = static function (string $texto, string $c): string {
    $codigos = ['verde' => '32', 'rojo' => '31', 'amarillo' => '33', 'gris' => '90'];
    return "\033[" . ($codigos[$c] ?? '0') . "m{$texto}\033[0m";
};

$log = static function (string $m): void {
    echo $m . PHP_EOL;
};

try {
    $migrator = new Database\Migrator();

    switch ($comando) {
        case 'status':
            $filas = $migrator->status();
            if ($filas === []) {
                echo "No hay migraciones en Database/migrations.\n";
                break;
            }
            echo PHP_EOL . str_pad('VERSIÓN', 10) . str_pad('ARCHIVO', 45) . 'ESTADO' . PHP_EOL;
            echo str_repeat('─', 95) . PHP_EOL;
            foreach ($filas as $f) {
                $c = str_starts_with($f['estado'], 'pendiente') ? 'amarillo'
                   : (str_starts_with($f['estado'], 'MODIFICADA') ? 'rojo' : 'verde');
                echo str_pad($f['version'], 10)
                   . str_pad($f['archivo'], 45)
                   . $color($f['estado'], $c) . PHP_EOL;
            }
            echo PHP_EOL;
            break;

        case 'up':
            $hechas = $migrator->up($log);
            echo $hechas === []
                ? $color("Base de datos al día.", 'verde') . PHP_EOL
                : $color(count($hechas) . ' migración(es) aplicada(s).', 'verde') . PHP_EOL;
            break;

        case 'down':
            $v = $migrator->down($log);
            echo $v === null
                ? "Nada que revertir.\n"
                : $color("Migración {$v} revertida.", 'verde') . PHP_EOL;
            break;

        default:
            echo "Uso: php bin/migrate.php [status|up|down]\n";
            exit(1);
    }
} catch (Throwable $e) {
    fwrite(STDERR, $color('ERROR: ' . $e->getMessage(), 'rojo') . PHP_EOL);
    exit(1);
}
