<?php
declare(strict_types=1);

/**
 * App/Cron/LimpiarSesiones.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Limpia archivos de sesión de los bots (WhatsApp y Telegram) que:
 *   1. Tienen más de 2 horas de antigüedad, O
 *   2. Guardan un cita_id que ya no está en estado activo (programada/pendiente)
 *
 * Ejecutar una vez manualmente AHORA para limpiar sesiones con cita_id=178,
 * y luego configurar como cron diario.
 *
 * CRON (cada día a las 03:00):
 *   0 3 * * * /usr/local/bin/php /home2/grupoide/public_html/App/Cron/LimpiarSesiones.php
 *
 * USO MANUAL (una sola vez para limpiar el problema actual):
 *   php App/Cron/LimpiarSesiones.php
 * ─────────────────────────────────────────────────────────────────────────────
 */
define('PROJECT_ROOT', dirname(__DIR__, 2));

if (file_exists(PROJECT_ROOT . '/.env.php')) {
    require_once PROJECT_ROOT . '/.env.php';
}
require_once PROJECT_ROOT . '/Core/Autoloader.php';

$pdo        = \Database\Connection::getConnection();
$sessionDir = PROJECT_ROOT . '/App/Controllers/bot_sessions';
$borradas   = 0;
$revisadas  = 0;

if (!is_dir($sessionDir)) {
    echo "No existe el directorio de sesiones: {$sessionDir}\n";
    exit(0);
}

$ahora = time();
$maxEdad = 2 * 60 * 60; // 2 horas

foreach (glob($sessionDir . '/*.json') as $file) {
    $revisadas++;
    $data = json_decode(file_get_contents($file), true) ?: [];

    // Criterio 1: sesión antigua
    if (isset($data['_ts']) && ($ahora - $data['_ts']) > $maxEdad) {
        unlink($file);
        $borradas++;
        echo "🗑  Sesión antigua eliminada: " . basename($file) . "\n";
        continue;
    }

    // Criterio 2: cita_id guardado ya no está activo
    $citaId = (int)($data['cita_id'] ?? 0);
    if ($citaId > 0) {
        $st = $pdo->prepare("SELECT estado FROM citas WHERE id = ? LIMIT 1");
        $st->execute([$citaId]);
        $row = $st->fetch();
        // Si la cita ya fue cancelada, completada, o no existe → limpiar sesión
        if (!$row || in_array($row['estado'], ['cancelada', 'completada'], true)) {
            unlink($file);
            $borradas++;
            echo "🗑  Sesión con cita inactiva (id={$citaId}, estado=" . ($row['estado'] ?? 'no existe') . ") eliminada: " . basename($file) . "\n";
            continue;
        }
    }
}

echo "\n✅ Revisadas: {$revisadas} | Eliminadas: {$borradas}\n";
