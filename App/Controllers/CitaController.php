<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\CitaModel;
use App\Security\SessionManager;
use App\Security\Tenant;

class CitaController
{
    public static function handle(): void
    {
        SessionManager::requireAuthApi();

        $agenteId = SessionManager::getAgentId();

        // ── FIX #3: GET → devuelve horas ocupadas para un día (usado por el
        //    listener fechaInput en calendario.js al cambiar la fecha) ─────────
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $fecha = trim($_GET['fecha'] ?? '');

            if (!$fecha || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Fecha inválida']);
                return;
            }

            $model  = new CitaModel();
            $horas  = $model->getHorasOcupadas($agenteId, $fecha);

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'horas' => $horas]);
            return;
        }

        // ── FIX #3: POST → lee $_POST (FormData) en lugar de php://input
        //    (JSON). El frontend usa FormData con fd.append(), no JSON.stringify ─
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            return;
        }

        header('Content-Type: application/json');

        $accion = trim($_POST['accion'] ?? '');

        // ── Crear cita ────────────────────────────────────────────────────────
        if ($accion === 'crear') {
            $clienteId  = (int)($_POST['cliente_id']  ?? 0);
            $tipo       = trim($_POST['tipo']          ?? 'llamada');
            $fecha      = trim($_POST['fecha']         ?? '');
            $hora       = trim($_POST['hora']          ?? '');
            $comentarios= trim($_POST['comentarios']   ?? '');
            $meetLink   = trim($_POST['meet_link']     ?? '');

            if (!$clienteId || !$fecha || !$hora) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'msg' => 'Fecha y hora requeridas']);
                return;
            }

            $model = new CitaModel();

            if (!$model->horaDisponible($agenteId, $fecha, $hora)) {
                http_response_code(409);
                echo json_encode(['ok' => false, 'msg' => 'La hora ya está ocupada']);
                return;
            }

            $citaId = $model->crear(
                clienteId:   $clienteId,
                agenteId:    $agenteId,
                tipo:        $tipo,
                fecha:       $fecha,
                hora:        $hora,
                comentarios: $comentarios,
                ramo:        $_POST['ramo'] ?? 'autos'
            );

            if ($meetLink && $citaId) {
                // El filtro de empresa va aunque la cita se acabe de crear
                // aquí: una consulta por id sin él es exactamente por donde se
                // cuela el acceso a la fila de otra empresa.
                \Database\Connection::getConnection()
                    ->prepare('UPDATE citas SET comentarios = CONCAT(COALESCE(comentarios,""), "\n🎥 Meet: ", ?) WHERE id = ? AND empresa_id = ?')
                    ->execute([$meetLink, $citaId, Tenant::requireId()]);
            }

            self::notificarWhatsApp($_POST, $fecha, $hora);

            echo json_encode(['ok' => true, 'id' => $citaId]);
            return;
        }

        // ── Cambiar estado ────────────────────────────────────────────────────
        if ($accion === 'estado') {
            $citaId      = (int)($_POST['cita_id'] ?? 0);
            $nuevoEstado = trim($_POST['estado'] ?? '');

            // ▶ PARCHE I3: Ampliar permisos de estado para el Superadmin
            $permitidos = ['programada', 'completada', 'cancelada'];
            if (SessionManager::isSuperadmin()) {
                $permitidos[] = 'vencida';
            }

            if (!$citaId || !in_array($nuevoEstado, $permitidos, true)) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'msg' => 'Datos inválidos']);
                return;
            }

            $model = new CitaModel();
            $ok    = $model->updateEstado($citaId, $agenteId, $nuevoEstado);

            echo json_encode(['ok' => $ok]);
            return;
        }

        http_response_code(400);
        echo json_encode(['ok' => false, 'msg' => 'Acción desconocida']);
    }

    private static function notificarWhatsApp(array $data, string $fecha, string $hora): void
    {
        $telefono = $data['telefono_cliente'] ?? '';
        if (!$telefono || !class_exists('\WhatsApp360Service')) return;

        $mensaje = "📋 *Tu cita fue registrada correctamente*\n\n"
            . "📅 Fecha: {$fecha}\n"
            . "🕐 Hora: {$hora}\n"
            . "Un asesor se pondrá en contacto contigo.\n"
            . "Gracias por confiar en LUX Seguros 💙";

        try {
            $whatsapp = new \WhatsApp360Service();
            $whatsapp->enviarMensaje($telefono, $mensaje);
        } catch (\Throwable $e) {
            error_log('WhatsApp error: ' . $e->getMessage());
        }
    }
}