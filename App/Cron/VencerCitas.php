<?php
declare(strict_types=1);

/**
 * App/Cron/VencerCitas.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Marca como 'vencida' todas las citas con estado 'programada' cuya
 * fecha+hora ya pasó. Se debe ejecutar periódicamente vía cron.
 *
 * ESTRATEGIA DE EMPRESA: ITERA empresa por empresa (Tenant::comoEmpresa()).
 *   Cambia el estado de registros de negocio (citas), así que entra en cada
 *   empresa y la trata por separado: el UPDATE queda acotado a su empresa y
 *   si una falla —ENUM sin 'vencida', tabla bloqueada— las demás se vencen
 *   igual en vez de caerse todas juntas.
 *
 * REQUISITO EN BD: el ENUM de citas.estado debe incluir 'vencida'.
 * Ejecutar UNA VEZ en MySQL antes de activar este cron:
 *
 *   ALTER TABLE citas
 *   MODIFY COLUMN estado
 *     ENUM('programada','completada','cancelada','vencida')
 *     COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'programada';
 *
 * CRON EN CPANEL / HOSTGATOR (cada hora, en punto):
 *   0 * * * * /usr/local/bin/php /home2/grupoide/public_html/App/Cron/VencerCitas.php >> /home2/grupoide/logs/vencer_citas.log 2>&1
 *
 * CRON EN HOSTGATOR (cada 5 minutos, si se quiere más precisión):
 *   * /5 * * * * /usr/local/bin/php /home2/grupoide/public_html/App/Cron/VencerCitas.php >> /home2/grupoide/logs/vencer_citas.log 2>&1
 * ─────────────────────────────────────────────────────────────────────────────
 */

// ── Bootstrap ─────────────────────────────────────────────────────────────────
define('PROJECT_ROOT', dirname(__DIR__, 2));

$envFile = PROJECT_ROOT . '/.env.php';
if (!file_exists($envFile)) {
    fwrite(STDERR, "[ERROR] .env.php no encontrado en {$envFile}\n");
    exit(1);
}
require_once $envFile;
require_once PROJECT_ROOT . '/Core/Autoloader.php';

use App\Models\CitaModel;
use App\Security\Tenant;
use Database\Connection;

// ── Log ───────────────────────────────────────────────────────────────────────
$logsDir = PROJECT_ROOT . '/logs';
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0755, true);
}
$logFile = $logsDir . '/vencer_citas_' . date('Y-m') . '.log';

function log_vencer(string $msg, string $file): void
{
    file_put_contents($file, date('[Y-m-d H:i:s] ') . $msg . "\n", FILE_APPEND);
}

// ── Ejecutar ──────────────────────────────────────────────────────────────────
try {
    $citaModel = new CitaModel();

    // `empresas` es tabla de plataforma y NO está aislada: esta consulta no
    // lleva filtro de empresa a propósito.
    $empresas = Connection::getConnection()->query(
        'SELECT id, nombre FROM empresas
         WHERE activo = 1 AND eliminado_en IS NULL
         ORDER BY id'
    )->fetchAll(\PDO::FETCH_ASSOC);

    $totalGlobal = 0;
    $conError    = 0;

    foreach ($empresas as $empresa) {
        $empresaId     = (int) $empresa['id'];
        $empresaNombre = (string) $empresa['nombre'];

        // Un fallo en una empresa no debe impedir vencer las citas del resto.
        try {
            $total = Tenant::comoEmpresa(
                $empresaId,
                static fn(): int => $citaModel->vencerCitasPasadas()
            );

            $totalGlobal += $total;

            if ($total > 0) {
                log_vencer("✅ Empresa #{$empresaId} ({$empresaNombre}): {$total} cita(s) marcadas como 'vencida'.", $logFile);
            }
        } catch (\Throwable $e) {
            $conError++;
            log_vencer(
                "❌ ERROR empresa #{$empresaId} ({$empresaNombre}): " . $e->getMessage()
                . ' @ ' . $e->getFile() . ':' . $e->getLine(),
                $logFile
            );
        }
    }

    if ($totalGlobal === 0 && $conError === 0) {
        log_vencer('Sin citas que vencer en este ciclo.', $logFile);
    } else {
        log_vencer(
            "=== FIN: {$totalGlobal} cita(s) vencida(s) en " . count($empresas)
            . " empresa(s), {$conError} con error ===",
            $logFile
        );
    }

    if ($conError > 0) {
        exit(1);
    }
} catch (\Throwable $e) {
    log_vencer("❌ ERROR: " . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine(), $logFile);
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

exit(0);
