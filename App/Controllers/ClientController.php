<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\{ClientModel, CitaModel};
use App\Security\SessionManager;
use RuntimeException;
use Throwable;

class ClientController
{
    private const EMPRESA_NOMBRE = 'Seguros LUX';
    private const EMPRESA_EMAIL  = 'noreply@seguroslux.mx';
    private const ASESOR_EMAIL   = 'contacto@seguroslux.mx';

    public static function handle(): void
    {
        header('Content-Type: application/json');

        match ($_SERVER['REQUEST_METHOD']) {
            'GET'  => self::handleGet(),
            'POST' => self::handlePost(),
            default => (function() {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Método no permitido']);
            })(),
        };
    }

    private static function citaActivaToFrontendData(array $row): array
    {
        return [
            'fecha' => (string)($row['fecha'] ?? ''),
            'hora'  => (string)($row['hora'] ?? ''),
            'tipo'  => (string)($row['tipo'] ?? 'llamada'),
            'ramo'  => (string)($row['ramo'] ?? 'autos'),
        ];
    }

    private static function parseCanalesNotificacion(string $raw): array
    {
        $parts = array_map('trim', explode(',', $raw));
        $parts = array_filter($parts, static fn(string $p): bool => $p !== '');
        return array_values(array_unique($parts ?: ['email']));
    }

    private static function canalesToLabelString(array $tokens): string
    {
        $map = [
            'email'    => 'Correo electrónico',
            'whatsapp' => 'WhatsApp',
            'telegram' => 'Telegram',
        ];
        $out = [];
        foreach ($tokens as $t) {
            $out[] = $map[$t] ?? ucfirst((string) $t);
        }
        return implode(', ', $out);
    }

    private static function handleGet(): void
    {
        SessionManager::requireAuthApi();

        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'ID inválido']);
            return;
        }

        $cliente = (new ClientModel())->find($id);
        if (!$cliente) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Cliente no encontrado']);
            return;
        }

        echo json_encode(['success' => true, 'cliente' => $cliente]);
    }

    private static function handlePost(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'No se han recibido datos']);
            return;
        }

        try {
            $clientModel      = new ClientModel();
            $citaModel        = new CitaModel();

            $correo            = trim($input['correo']           ?? '');
            $fechaCita         = $input['fechaCita']             ?? null;
            $horaCita          = $input['horaCita']              ?? null;
            $canalNotificacion = trim((string)($input['canalNotificacion'] ?? 'email'));

            if (($input['ramo'] ?? '') === '' && !empty($input['tipoVehiculo'])) {
                $mapTipoARamo = [
                    'auto' => 'autos', 'moto' => 'moto', 'camioneta' => 'camioneta', 'pickup' => 'pickup',
                ];
                $input['ramo'] = $mapTipoARamo[$input['tipoVehiculo']] ?? 'autos';
            }

            $botUsername = getenv('TELEGRAM_BOT_USERNAME')    ?: ($_ENV['TELEGRAM_BOT_USERNAME'] ?? 'Lux_Prueba_Bot');
            $waNumero    = getenv('WHATSAPP_BUSINESS_NUMBER') ?: ($_ENV['WHATSAPP_BUSINESS_NUMBER'] ?? '');

            $clienteExistente = $correo ? $clientModel->findByCorreo($correo) : null;

            if ($clienteExistente) {
                self::handleExistingClient(
                    $clienteExistente, $citaModel, $fechaCita, $horaCita,
                    $input, $botUsername, $waNumero, $canalNotificacion
                );
                return;
            }

            self::handleNewClient(
                $clientModel, $citaModel, $fechaCita, $horaCita,
                $input, $botUsername, $waNumero, $canalNotificacion
            );

        } catch (Throwable $e) {
            error_log('ClientController error: ' . $e->getMessage());
            $msg = str_contains($e->getMessage(), 'asesores')
                ? 'No hay asesores disponibles en este momento. Intenta más tarde.'
                : 'Ocurrió un error al registrar tu solicitud. Por favor intenta de nuevo.';

            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $msg]);
        }
    }

    private static function handleExistingClient(
        array $clienteExistente,
        CitaModel $citaModel,
        ?string $fechaCita,
        ?string $horaCita,
        array $input,
        string $botUsername,
        string $waNumero,
        string $canalNotificacion
    ): void {
        $clienteId      = (int)$clienteExistente['id'];
        $nombreCompleto = trim(($clienteExistente['nombre'] ?? '') . ' ' . ($clienteExistente['apellido_paterno'] ?? ''));
        $correo         = $clienteExistente['correo'] ?? ($input['correo'] ?? '');
        $agenteId       = self::resolverAgenteId($clienteId, (int)($clienteExistente['agente_id'] ?? 0));

        $citaActiva = $citaModel->getCitaActivaByClienteId($clienteId);

        if ($citaActiva) {
            self::respondSuccess(
                $agenteId, (int)$citaActiva['id'], $botUsername, $waNumero,
                $nombreCompleto, $correo,
                self::citaActivaToFrontendData($citaActiva),
                $canalNotificacion,
                $citaModel, true, true, 'Ya tienes una cita activa.'
            );
            return;
        }

        $citaId   = null;
        $citaData = null;
        if ($fechaCita && $horaCita) {
            if (!$citaModel->horaDisponible($agenteId, $fechaCita, $horaCita)) {
                $agenteId = $citaModel->buscarAgenteLibre($fechaCita, $horaCita)
                    ?: throw new RuntimeException('No hay asesores disponibles para ese horario');
            }

            $citaId = $citaModel->crear(
                $clienteId, $agenteId,
                in_array($input['tipoCita'] ?? '', ['llamada','videollamada'], true) ? $input['tipoCita'] : 'llamada',
                $fechaCita, $horaCita,
                trim($input['comentarios'] ?? ''),
                trim($input['ramo'] ?? 'autos')
            );

            $citaData = [
                'fecha' => $fechaCita,
                'hora'  => $horaCita,
                'tipo'  => $input['tipoCita'] ?? 'llamada',
                'ramo'  => $input['ramo'] ?? 'autos',
            ];
        }

        self::respondSuccess(
            $agenteId, $citaId, $botUsername, $waNumero,
            $nombreCompleto, $correo, $citaData, $canalNotificacion,
            $citaModel, true, false
        );
    }

    private static function handleNewClient(
        ClientModel $clientModel,
        CitaModel $citaModel,
        ?string $fechaCita,
        ?string $horaCita,
        array $input,
        string $botUsername,
        string $waNumero,
        string $canalNotificacion
    ): void {
        $input['tipoCita'] = in_array($input['tipoCita'] ?? '', ['llamada','videollamada'], true)
            ? $input['tipoCita'] : 'llamada';

        $result         = $clientModel->registrarDesdeFormulario($input);
        $nombreCompleto = trim(($input['nombre'] ?? '') . ' ' . ($input['apellidoPaterno'] ?? ''));
        $correo         = $input['correo'] ?? '';

        $citaId   = null;
        $citaData = null;
        if ($fechaCita && $horaCita) {
            $cita   = $citaModel->getCitaActivaByClienteId($result['cliente_id']);
            $citaId = $cita ? (int)$cita['id'] : null;

            $citaData = [
                'fecha' => $fechaCita,
                'hora'  => $horaCita,
                'tipo'  => $input['tipoCita'],
                'ramo'  => $input['ramo'] ?? 'autos',
            ];
        }

        self::respondSuccess(
            $result['agente_id'], $citaId, $botUsername, $waNumero,
            $nombreCompleto, $correo, $citaData, $canalNotificacion,
            $citaModel, false, false
        );
    }

    // ─────────────────────────────────────────────────────────────────────────

    private static function respondSuccess(
        int $agenteId,
        ?int $citaId,
        string $botUsername,
        string $waNumero,
        string $nombreCompleto,
        string $correo,
        ?array $citaData,
        string $canalNotificacion,
        CitaModel $citaModel,
        bool $existente,
        bool $tieneActiva,
        ?string $mensaje = null
    ): void {
        $canales = self::parseCanalesNotificacion($canalNotificacion);
        if (in_array('email', $canales, true) && $correo !== '' && $citaData) {
            self::enviarConfirmacionEmail($nombreCompleto, $correo, $citaData, $canales);
        }

        // Generar el token UNA sola vez — se reutiliza para Telegram y WhatsApp.
        // Si se llama generarTelegramToken() dos veces sobreescribe el token en BD
        // y el primer link generado deja de funcionar.
        $sharedToken = $citaId ? $citaModel->generarTelegramToken($citaId) : null;

        $response = [
            'success'           => true,
            'agente_id'         => $agenteId,
            'cita_id'           => $citaId,
            'canal_notificacion'=> $canalNotificacion,
            'telegram_link'     => ($citaId && $sharedToken)
                ? "https://t.me/{$botUsername}?start={$sharedToken}"
                : null,
            'whatsapp_link'     => ($citaId && $sharedToken && $waNumero)
                ? self::buildWhatsAppLink($waNumero, $sharedToken)
                : null,
            'cliente_existente' => $existente,
            'tiene_cita_activa' => $tieneActiva,
        ];

        if ($mensaje) $response['mensaje'] = $mensaje;

        echo json_encode($response);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // EMAIL AUTOMÁTICO DE CONFIRMACIÓN DE CITA
    // ─────────────────────────────────────────────────────────────────────────

    private static function enviarConfirmacionEmail(
        string $nombre,
        string $correo,
        array $citaData,
        array $canalesElegidos = ['email']
    ): void {
        try {
            $empresaNombre = self::EMPRESA_NOMBRE;
            $fecha      = $citaData['fecha'] ?? '';
            $hora       = $citaData['hora']  ?? '';
            $tipoCita   = $citaData['tipo']  ?? 'llamada';
            $ramo       = $citaData['ramo']  ?? 'autos';

            $fechaFmt  = self::formatFecha($fecha);
            $tipoLabel = $tipoCita === 'videollamada' ? '💻 Videollamada' : '📞 Llamada telefónica';

            $ramoLabels = [
                'autos'    => '🚗 Seguro de Auto',
                'vida'     => '❤️ Seguro de Vida',
                'gmm'      => '🏥 Gastos Médicos Mayores',
                'camioneta'=> '🚙 Seguro de SUV/Camioneta',
                'moto'     => '🏍️ Seguro de Moto',
                'pickup'   => '🛻 Seguro de Pickup',
            ];
            $ramoLabel  = $ramoLabels[$ramo] ?? ucfirst($ramo);
            $nombreHtml = htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');
            $canalesTxt = self::canalesToLabelString($canalesElegidos);
            $asunto     = '✅ Confirmación de cita — ' . self::EMPRESA_NOMBRE;

            $cuerpo = <<<HTML
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f9;padding:32px 0;">
  <tr>
    <td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08);">
        <tr>
          <td style="background:linear-gradient(135deg,#0ea5e9,#0284c7);padding:32px 36px;text-align:center;">
            <div style="font-size:48px;margin-bottom:8px;">📅</div>
            <h1 style="color:#ffffff;margin:0;font-size:26px;font-weight:700;">¡Tu cita está confirmada!</h1>
            <p style="color:rgba(255,255,255,.85);margin:8px 0 0;font-size:15px;">{$ramoLabel}</p>
          </td>
        </tr>
        <tr>
          <td style="padding:32px 36px 16px;">
            <p style="font-size:17px;color:#1a202c;margin:0;">Hola, <strong>{$nombreHtml}</strong> 👋</p>
            <p style="font-size:15px;color:#4a5568;margin:12px 0 0;line-height:1.6;">
              Tu solicitud de cita con un asesor de <strong>{$empresaNombre}</strong> ha sido registrada exitosamente.
            </p>
          </td>
        </tr>
        <tr>
          <td style="padding:0 36px 24px;">
            <table width="100%" cellpadding="0" cellspacing="0"
                   style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:12px;padding:20px;margin-top:8px;">
              <tr><td style="padding:10px 0;border-bottom:1px solid #e0f2fe;">
                <span style="color:#64748b;font-size:13px;display:block;margin-bottom:2px;">📅 Fecha</span>
                <strong style="color:#0f172a;font-size:16px;">{$fechaFmt}</strong>
              </td></tr>
              <tr><td style="padding:10px 0;border-bottom:1px solid #e0f2fe;">
                <span style="color:#64748b;font-size:13px;display:block;margin-bottom:2px;">🕐 Hora</span>
                <strong style="color:#0f172a;font-size:16px;">{$hora} hrs</strong>
              </td></tr>
              <tr><td style="padding:10px 0;border-bottom:1px solid #e0f2fe;">
                <span style="color:#64748b;font-size:13px;display:block;margin-bottom:2px;">📞 Modalidad</span>
                <strong style="color:#0f172a;font-size:16px;">{$tipoLabel}</strong>
              </td></tr>
              <tr><td style="padding:10px 0;">
                <span style="color:#64748b;font-size:13px;display:block;margin-bottom:2px;">🛡️ Tipo de seguro</span>
                <strong style="color:#0f172a;font-size:16px;">{$ramoLabel}</strong>
              </td></tr>
            </table>
          </td>
        </tr>
        <tr>
          <td style="padding:0 36px 32px;">
            <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:16px 18px;">
              <p style="margin:0;font-size:14px;color:#92400e;line-height:1.6;">
                ⏰ <strong>Importante:</strong> Un asesor se comunicará contigo en la fecha y hora indicadas.
              </p>
            </div>
          </td>
        </tr>
        <tr>
          <td style="background:#f8fafc;padding:20px 36px;text-align:center;border-top:1px solid #e2e8f0;">
            <p style="margin:0;font-size:13px;color:#94a3b8;">
              © 2026 {$empresaNombre} · Todos los derechos reservados
            </p>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</body>
</html>
HTML;

            $asuntoAsesor = "[" . self::EMPRESA_NOMBRE . "] Nueva cita agendada — {$nombre}";
            $cuerpoAsesor = <<<HTML
<html>
<body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;">
  <div style="background:linear-gradient(135deg,#0ea5e9,#0284c7);padding:24px;border-radius:12px 12px 0 0;">
    <h2 style="color:#fff;margin:0;">📅 Nueva cita agendada</h2>
  </div>
  <div style="background:#f8fafc;padding:24px;border-radius:0 0 12px 12px;border:1px solid #e5e7eb;">
    <table style="width:100%;border-collapse:collapse;">
      <tr><td style="padding:8px 0;border-bottom:1px solid #eee;"><strong>👤 Cliente:</strong></td><td style="padding:8px 0;border-bottom:1px solid #eee;">{$nombreHtml}</td></tr>
      <tr><td style="padding:8px 0;border-bottom:1px solid #eee;"><strong>✉️ Correo:</strong></td><td style="padding:8px 0;border-bottom:1px solid #eee;">{$correo}</td></tr>
      <tr><td style="padding:8px 0;border-bottom:1px solid #eee;"><strong>📅 Fecha:</strong></td><td style="padding:8px 0;border-bottom:1px solid #eee;">{$fechaFmt}</td></tr>
      <tr><td style="padding:8px 0;border-bottom:1px solid #eee;"><strong>🕐 Hora:</strong></td><td style="padding:8px 0;border-bottom:1px solid #eee;">{$hora} hrs</td></tr>
      <tr><td style="padding:8px 0;border-bottom:1px solid #eee;"><strong>📞 Modalidad:</strong></td><td style="padding:8px 0;border-bottom:1px solid #eee;">{$tipoLabel}</td></tr>
      <tr><td style="padding:8px 0;"><strong>🛡️ Ramo:</strong></td><td style="padding:8px 0;">{$ramoLabel}</td></tr>
    </table>
    <p style="margin:16px 0 0;font-size:13px;color:#888;">Canales: <strong>{$canalesTxt}</strong></p>
  </div>
</body>
</html>
HTML;

            $headers  = "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "From: " . self::EMPRESA_NOMBRE . " <" . self::EMPRESA_EMAIL . ">\r\n";
            $headers .= "Reply-To: " . self::ASESOR_EMAIL . "\r\n";

            mail($correo,            $asunto,       $cuerpo,       $headers);
            mail(self::ASESOR_EMAIL, $asuntoAsesor, $cuerpoAsesor, $headers);

        } catch (\Throwable $e) {
            error_log('ClientController::enviarConfirmacionEmail error: ' . $e->getMessage());
        }
    }

    private static function formatFecha(string $fecha): string
    {
        if (!$fecha) return '';
        $meses = ['','enero','febrero','marzo','abril','mayo','junio',
                  'julio','agosto','septiembre','octubre','noviembre','diciembre'];
        [$y, $m, $d] = explode('-', $fecha);
        return (int)$d . ' de ' . ($meses[(int)$m] ?? '') . ' de ' . $y;
    }

    /**
     * Genera el link de WhatsApp con el token de la cita.
     * El bot detecta el prefijo CITA: y vincula automáticamente el número.
     */
    private static function buildWhatsAppLink(string $waNumero, string $token): ?string
    {
        if (!$waNumero) return null;
        return 'https://wa.me/' . $waNumero . '?text=' . rawurlencode("CITA:{$token}");
    }

    private static function resolverAgenteId(int $clienteId, int $agenteIdActual): int
    {
        if ($agenteIdActual > 0) return $agenteIdActual;

        $db   = \Database\Connection::getConnection();
        $stmt = $db->query(
            "SELECT a.id FROM agentes a
             WHERE a.rol IN ('asesor','agente') AND COALESCE(a.activo,1) = 1
             ORDER BY (SELECT COUNT(*) FROM clientes WHERE agente_id = a.id) ASC, RAND() LIMIT 1"
        );
        $row = $stmt->fetch();
        if (!$row) throw new RuntimeException('No hay asesores activos.');

        $nuevoId = (int)$row['id'];
        $db->prepare('UPDATE clientes SET agente_id = ? WHERE id = ?')->execute([$nuevoId, $clienteId]);

        return $nuevoId;
    }
}
