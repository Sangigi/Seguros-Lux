<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Security\EmpresaPublica;
use App\Security\Tenant;
use App\Support\CorsHelper;
use Database\Connection;
use PDO;

/**
 * HorasOcupadasController — horas que ya no admiten cita, para el formulario
 * público de agendado.
 *
 * MULTIEMPRESA: es un endpoint sin sesión, así que no hay empresa en contexto
 * y las consultas fallarían. La empresa se resuelve igual que en el resto de
 * formularios públicos: por el slug si el formulario lo manda, y si no, la
 * empresa por defecto de la plataforma (ver App\Security\EmpresaPublica).
 *
 * Importa que esté bien acotado por dos razones: la cuenta de asesores y las
 * citas de un día son datos de la empresa, y mezclarlas haría que una empresa
 * viera bloqueados los horarios que en realidad ocupó otra.
 */
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

        // Sin sesión no hay empresa: se resuelve por el slug del formulario y,
        // si no viene, cae en la empresa por defecto de la plataforma.
        $empresaId = Tenant::id()
            ?? EmpresaPublica::resolver($_GET[EmpresaPublica::CAMPO_SLUG] ?? null);

        $db = Connection::getConnection();

        $stmtTotal = $db->prepare(
            "SELECT COUNT(*) FROM agentes
             WHERE rol IN ('asesor','agente') AND COALESCE(activo,1) = 1
               AND empresa_id = ?"
        );
        $stmtTotal->execute([$empresaId]);
        $total = (int) $stmtTotal->fetchColumn();

        if ($total === 0) {
            echo json_encode([]);

            return;
        }

        $stmt = $db->prepare(
            "SELECT TIME_FORMAT(hora,'%H:%i') AS hora_fmt
             FROM citas
             WHERE fecha = ? AND estado IN ('programada','pendiente')
               AND empresa_id = ?
             GROUP BY hora
             HAVING COUNT(DISTINCT agente_id) >= ?"
        );
        $stmt->execute([$fecha, $empresaId, $total]);

        echo json_encode(array_values($stmt->fetchAll(PDO::FETCH_COLUMN)));
    }
}
