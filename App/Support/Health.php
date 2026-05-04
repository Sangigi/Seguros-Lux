<?php

declare(strict_types=1);

namespace App\Support;

use Database\Connection;

/**
 * Estado de salud para observabilidad (liveness / readiness ligera).
 */
final class Health
{
    /**
     * @return array{ok: true, service: string, time: string}
     */
    public static function liveness(): array
    {
        return [
            'ok'      => true,
            'service' => 'crm',
            'time'    => gmdate('c'),
        ];
    }

    /**
     * Comprueba conexión a la base de datos (SELECT 1).
     */
    public static function databasePing(): bool
    {
        try {
            $pdo = Connection::getConnection();
            $pdo->query('SELECT 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
