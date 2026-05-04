<?php
declare(strict_types=1);

/**
 * GET  /api/notificaciones — lista recientes JSON
 * POST /api/notificaciones — marcar leída(s) / eliminar
 */
use App\Security\SessionManager;
use App\Models\NotificacionModel;

SessionManager::requireAuth('/login');
if (SessionManager::isAdmin()) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'items' => [], 'unread' => 0]);
    exit;
}

$agentId = SessionManager::getAgentId();
$model   = new NotificacionModel();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json');
    $items = $model->getRecientes($agentId, 30);
    echo json_encode([
        'ok'     => true,
        'items'  => array_map(static function (array $n): array {
            return [
                'id'        => (int) $n['id'],
                'tipo'      => $n['tipo'] ?? '',
                'titulo'    => $n['titulo'] ?? '',
                'mensaje'   => $n['mensaje'] ?? '',
                'referencia'=> $n['referencia'] ?? '',
                'leida'     => (int) ($n['leida'] ?? 0),
                'creada_en' => $n['creada_en'] ?? '',
            ];
        }, $items),
        'unread' => $model->contarNoLeidas($agentId),
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (!SessionManager::validateCsrf(trim($_POST['csrf_token'] ?? ''))) {
        echo json_encode(['ok' => false, 'msg' => 'Sesión/Csrf inválido.']);
        exit;
    }
    $acc = $_POST['accion'] ?? '';

    if ($acc === 'marcar_todas') {
        $model->marcarTodasLeidas($agentId);
        echo json_encode(['ok' => true, 'unread' => 0]);
        exit;
    }
    if ($acc === 'marcar_leida') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id) {
            $model->marcarLeida($id, $agentId);
        }
        echo json_encode(['ok' => true, 'unread' => $model->contarNoLeidas($agentId)]);
        exit;
    }
    if ($acc === 'eliminar') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id) {
            $model->eliminar($id, $agentId);
        }
        echo json_encode(['ok' => true, 'unread' => $model->contarNoLeidas($agentId)]);
        exit;
    }
    if ($acc === 'eliminar_todas') {
        $model->eliminarTodas($agentId);
        echo json_encode(['ok' => true, 'unread' => 0]);
        exit;
    }

    echo json_encode(['ok' => false, 'msg' => 'Acción no válida']);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false]);