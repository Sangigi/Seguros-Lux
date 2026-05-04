<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Security\SessionManager;
use Database\Connection;
use PDO;

class ConfigController
{
    public static function handle(): void
    {
        header('Content-Type: application/json');
        SessionManager::requireAuthApi();

        $db = Connection::getConnection();

        match ($_SERVER['REQUEST_METHOD']) {
            'GET'  => self::handleGet($db),
            'POST' => self::handlePost($db),
            default => (function() {
                http_response_code(405);
                echo json_encode(['ok' => false, 'msg' => 'Método no permitido.']);
            })(),
        };
    }

    private static function handleGet(PDO $db): void
    {
        $grupo = $_GET['grupo'] ?? 'atc';
        $stmt  = $db->prepare('SELECT clave, valor, descripcion FROM configuracion WHERE clave LIKE ?');
        $stmt->execute(["{$grupo}%"]);
        
        echo json_encode(['ok' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    private static function handlePost(PDO $db): void
    {
        if (!SessionManager::isSuperadmin()) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'msg' => 'Sin permisos.']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $items = $input['items'] ?? [];

        if (empty($items)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'msg' => 'Sin datos.']);
            return;
        }

        $stmt = $db->prepare(
            'INSERT INTO configuracion (clave, valor, descripcion)
             VALUES (?,?,?)
             ON DUPLICATE KEY UPDATE valor = VALUES(valor), descripcion = VALUES(descripcion)'
        );

        foreach ($items as $item) {
            $clave = trim($item['clave'] ?? '');
            if (!$clave) continue;
            
            $stmt->execute([
                $clave,
                trim($item['valor'] ?? ''),
                trim($item['descripcion'] ?? ''),
            ]);
        }

        echo json_encode(['ok' => true, 'msg' => 'Configuración guardada.']);
    }
}