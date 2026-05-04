<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\CorsHelper;
use Database\Connection;
use PDO;

class HorasOcupadasController
{
    /**
     * Orígenes permitidos para CORS, separados por coma (ej. https://seguroslux.mx,https://www.seguroslux.mx).
     * Si está vacío, no se envían cabeceras CORS (solo mismo origen).
     */
    private static function applyCors(): void
    {
        $raw = getenv('CORS_ALLOWED_ORIGINS');
        if ($raw === false || trim($raw) === '') {
            return;
        }

        $origin = CorsHelper::resolveAllowedOrigin($_SERVER['HTTP_ORIGIN'] ?? null, $raw);
        if ($origin === null) {
            return;
        }

        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
        header('Access-Control-Allow-Methods: GET, OPTIONS');
        header('Access-Control-Max-Age: 86400');
    }

    public static function handle(): void
    {
        self::applyCors();

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);

            return;
        }

        header('Content-Type: application/json');

        $fecha = trim($_GET['fecha'] ?? '');

        if (!$fecha || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            echo json_encode([]);

            return;
        }

        $db = Connection::getConnection();

        $total = (int) $db->query(
            "SELECT COUNT(*) FROM agentes WHERE rol IN ('asesor','agente') AND COALESCE(activo,1) = 1"
        )->fetchColumn();

        if ($total === 0) {
            echo json_encode([]);

            return;
        }

        $stmt = $db->prepare(
            "SELECT TIME_FORMAT(hora,'%H:%i') AS hora_fmt
             FROM citas
             WHERE fecha = ? AND estado IN ('programada','pendiente')
             GROUP BY hora
             HAVING COUNT(DISTINCT agente_id) >= ?"
        );
        $stmt->execute([$fecha, $total]);

        echo json_encode(array_values($stmt->fetchAll(PDO::FETCH_COLUMN)));
    }
}
