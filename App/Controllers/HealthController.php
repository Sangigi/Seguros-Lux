<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\Health;

/**
 * Salud del servicio para balanceadores y monitoreo.
 *
 * GET /api/health — solo liveness (sin tocar BD).
 * GET /api/health?deep=1&token=... — readiness con ping a BD (requiere HEALTH_CHECK_TOKEN).
 */
class HealthController
{
    public static function handle(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $deep = isset($_GET['deep']) && $_GET['deep'] === '1';
        if (!$deep) {
            echo json_encode(Health::liveness());

            return;
        }

        $expected = getenv('HEALTH_CHECK_TOKEN') ?: '';
        $token    = (string) ($_GET['token'] ?? '');
        if ($expected === '' || !hash_equals($expected, $token)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'msg' => 'Token inválido o HEALTH_CHECK_TOKEN no configurado.']);

            return;
        }

        $dbUp = Health::databasePing();
        $payload = [
            'ok'     => $dbUp,
            'time'   => gmdate('c'),
            'checks' => [
                'database' => $dbUp ? 'up' : 'down',
            ],
        ];

        if (!$dbUp) {
            http_response_code(503);
        }

        echo json_encode($payload);
    }
}
