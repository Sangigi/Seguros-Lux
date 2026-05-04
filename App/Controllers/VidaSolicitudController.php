<?php
declare(strict_types=1);
namespace App\Controllers;

use Database\Connection;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$__crmAutoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
if (is_file($__crmAutoload)) {
    require_once $__crmAutoload;
}

/**
 * Controlador unificado de solicitudes de cotización.
 * Maneja: vida, gmm, empresas, autos
 *
 * Recibe JSON via POST /api/vida-solicitud
 */
class VidaSolicitudController
{
    private const ASESOR_EMAIL   = 'contacto@seguroslux.mx';
    private const EMPRESA_NOMBRE = 'Seguros LUX';
    private const SMTP_DEFAULT_PORT = 465;

    public static function handle(): void
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['ok' => false, 'msg' => 'Método no permitido.']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        // ── Campos comunes ────────────────────────────────────────────────────
        $nombre    = trim($input['nombre']          ?? '');
        $correo    = trim($input['correo']           ?? '');
        $telefono  = trim($input['telefono']         ?? '');
        $fechaPref = trim($input['fecha_preferida']  ?? date('Y-m-d', strtotime('+2 days')));
        $comentarios = trim($input['comentarios']    ?? '');

        $ramo = in_array($input['ramo'] ?? '', ['vida', 'gmm', 'empresas', 'autos'], true)
            ? $input['ramo']
            : 'vida';

        // ── Campos por ramo ───────────────────────────────────────────────────
        // GMM / Vida
        $edadGmm = trim($input['edad']        ?? '');
        $depGmm  = trim($input['dependientes'] ?? '');

        // Empresas
        $empresa   = trim($input['empresa']   ?? '');
        $empleados = trim($input['empleados'] ?? '');
        $servicios = trim($input['servicios'] ?? '');

        // Autos
        $marca    = trim($input['marca']          ?? '');
        $modelo   = trim($input['modelo']         ?? '');
        $anio     = trim($input['anio']           ?? '');
        $tipoVeh  = trim($input['tipo_vehiculo']  ?? '');

        // ── Validación básica ─────────────────────────────────────────────────
        if (!$nombre || !$telefono) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'msg' => 'Nombre y teléfono son obligatorios.']);
            return;
        }

        // Correo obligatorio solo para vida y autos (GMM puede no tenerlo)
        if (in_array($ramo, ['vida', 'autos'], true) && !$correo) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'msg' => 'El correo electrónico es obligatorio.']);
            return;
        }

        if ($correo && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'msg' => 'Correo electrónico inválido.']);
            return;
        }

        // ── Guardar en BD ─────────────────────────────────────────────────────
        self::guardarEnDB(
            $nombre, $correo, $telefono, $fechaPref,
            $ramo, $edadGmm, $depGmm, $comentarios,
            $empresa, $empleados, $servicios,
            $marca, $modelo, $anio, $tipoVeh
        );

        // ── Enviar correos ────────────────────────────────────────────────────
        self::enviarCorreos(
            $nombre, $correo ?: self::ASESOR_EMAIL, $telefono, $fechaPref,
            $ramo, $edadGmm, $depGmm, $comentarios,
            $empresa, $empleados, $servicios,
            $marca, $modelo, $anio, $tipoVeh
        );

        // ── Notificación WhatsApp (opcional) ──────────────────────────────────
        try {
            $config  = self::getConfig();
            $waToken = $config['wa_token'] ?? '';
            $waPid   = $config['wa_pid']   ?? '';

            if ($waToken && $waPid) {
                $ramoLabel = strtoupper($ramo);
                $msg = "🔔 *Nueva solicitud — {$ramoLabel}*\n"
                     . "👤 {$nombre}\n"
                     . ($correo    ? "📧 {$correo}\n"              : '')
                     . "📞 {$telefono}\n"
                     . ($marca     ? "🚗 {$marca} {$modelo} {$anio}\n" : '')
                     . ($empresa   ? "🏢 {$empresa}\n"              : '')
                     . ($empleados ? "👥 Empleados: {$empleados}\n"  : '')
                     . ($edadGmm   ? "🎂 Edad: {$edadGmm} años\n"   : '')
                     . ($comentarios ? "💬 {$comentarios}\n"         : '')
                     . "📅 Contactar antes: " . self::formatFecha($fechaPref);

                $asesorPhone = $config['asesor_whatsapp'] ?? '';
                if ($asesorPhone) {
                    self::waSendText($asesorPhone, $msg, $waPid, $waToken);
                }
            }
        } catch (\Throwable $e) {
            error_log('WA notify error: ' . $e->getMessage());
        }

        echo json_encode(['ok' => true, 'msg' => 'Solicitud recibida correctamente.']);
    }

    // ── Base de datos ─────────────────────────────────────────────────────────

    private static function guardarEnDB(
        string $nombre, string $correo, string $telefono, string $fechaPref,
        string $ramo, string $edadGmm, string $depGmm, string $comentarios,
        string $empresa, string $empleados, string $servicios,
        string $marca = '', string $modelo = '', string $anio = '', string $tipoVeh = ''
    ): void {
        try {
            $db = Connection::getConnection();

            // Crear tabla si no existe (con campos de autos)
            $db->exec("
                CREATE TABLE IF NOT EXISTS vida_solicitudes (
                    id              INT AUTO_INCREMENT PRIMARY KEY,
                    nombre          VARCHAR(200)  NOT NULL,
                    correo          VARCHAR(200)  DEFAULT NULL,
                    telefono        VARCHAR(30)   NOT NULL,
                    fecha_preferida DATE,
                    ramo            VARCHAR(20)   DEFAULT 'vida',
                    edad            TINYINT       DEFAULT NULL,
                    dependientes    TINYINT       DEFAULT 0,
                    comentarios     TEXT,
                    marca           VARCHAR(100)  DEFAULT NULL,
                    modelo          VARCHAR(100)  DEFAULT NULL,
                    anio            VARCHAR(10)   DEFAULT NULL,
                    tipo_vehiculo   VARCHAR(50)   DEFAULT NULL,
                    estado          ENUM('pendiente','contactado','cerrado') DEFAULT 'pendiente',
                    created_at      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");

            // Agregar columnas de autos si no existen (para BDs antiguas)
            foreach (['marca VARCHAR(100)', 'modelo VARCHAR(100)', 'anio VARCHAR(10)', 'tipo_vehiculo VARCHAR(50)'] as $col) {
                try {
                    $colName = explode(' ', $col)[0];
                    $db->exec("ALTER TABLE vida_solicitudes ADD COLUMN IF NOT EXISTS {$col} DEFAULT NULL");
                } catch (\Throwable) { /* columna ya existe */ }
            }

            $db->prepare(
                "INSERT INTO vida_solicitudes
                    (nombre, correo, telefono, fecha_preferida, ramo, edad, dependientes,
                     comentarios, marca, modelo, anio, tipo_vehiculo)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            )->execute([
                $nombre,
                $correo ?: null,
                $telefono,
                $fechaPref,
                $ramo,
                $edadGmm ?: null,
                $depGmm  ?: 0,
                $comentarios ?: null,
                $marca   ?: null,
                $modelo  ?: null,
                $anio    ?: null,
                $tipoVeh ?: null,
            ]);
        } catch (\Throwable $e) {
            error_log('VidaSolicitudController DB error: ' . $e->getMessage());
        }
    }

    // ── Correos ───────────────────────────────────────────────────────────────

    private static function enviarCorreos(
        string $nombre, string $correo, string $telefono, string $fechaPref, string $ramo,
        string $edadGmm, string $depGmm, string $comentarios,
        string $empresa = '', string $empleados = '', string $servicios = '',
        string $marca = '', string $modelo = '', string $anio = '', string $tipoVeh = ''
    ): void {
        try {
            $fechaFmt = self::formatFecha($fechaPref);

            $ramoLabel = match ($ramo) {
                'gmm'      => 'Gastos Médicos Mayores',
                'empresas' => 'Seguros Empresariales',
                'autos'    => 'Seguro Vehicular',
                default    => 'Seguro de Vida',
            };
            $ramoIcon = match ($ramo) {
                'gmm'      => '🏥',
                'empresas' => '🏢',
                'autos'    => '🚗',
                default    => '❤️',
            };
            $gradient = match ($ramo) {
                'gmm'      => '#0ea5e9,#0284c7',
                'empresas' => '#7c3aed,#6d28d9',
                'autos'    => '#10b981,#059669',
                default    => '#c9a227,#dbb84d',
            };

            // ── Filas extra por ramo ──────────────────────────────────────────
            $extraRows = '';

            if ($ramo === 'autos') {
                if ($tipoVeh) $extraRows .= self::row('🚗 Tipo', ucfirst($tipoVeh));
                if ($marca || $modelo) $extraRows .= self::row('🏷️ Vehículo', htmlspecialchars("{$marca} {$modelo}"));
                if ($anio) $extraRows .= self::row('📅 Año', htmlspecialchars($anio));
            }

            if ($ramo === 'gmm') {
                if ($edadGmm) $extraRows .= self::row('🎂 Edad', htmlspecialchars($edadGmm) . ' años');
                if ($depGmm)  $extraRows .= self::row('👨‍👩‍👧 Dependientes', htmlspecialchars($depGmm));
            }

            if ($ramo === 'vida') {
                if ($depGmm) $extraRows .= self::row('👨‍👩‍👧 Dependientes', htmlspecialchars($depGmm));
            }

            if ($ramo === 'empresas') {
                if ($empresa)   $extraRows .= self::row('🏢 Empresa',   htmlspecialchars($empresa));
                if ($empleados) $extraRows .= self::row('👥 Empleados', htmlspecialchars($empleados));
                if ($servicios) $extraRows .= self::row('🧩 Servicios', htmlspecialchars($servicios));
            }

            if ($comentarios) $extraRows .= self::row('💬 Comentarios', htmlspecialchars($comentarios));

            // ── Email al asesor ───────────────────────────────────────────────
            $asuntoAsesor = "[" . self::EMPRESA_NOMBRE . "] Nueva solicitud de {$ramoLabel} — {$nombre}";
            $cuerpoAsesor = self::buildEmailAsesor(
                $ramoIcon, $ramoLabel, $gradient,
                $nombre, $correo === self::ASESOR_EMAIL ? '(no proporcionado)' : $correo,
                $telefono, $fechaFmt, $extraRows
            );

            // ── Email al cliente (solo si tiene correo real) ───────────────────
            $clienteCorreo = (filter_var($correo, FILTER_VALIDATE_EMAIL) && $correo !== self::ASESOR_EMAIL)
                ? $correo
                : null;

            $asuntoCliente = '✅ Tu solicitud fue recibida — ' . self::EMPRESA_NOMBRE;
            $cuerpoCliente = self::buildEmailCliente($ramoIcon, $ramoLabel, $gradient, $nombre, $fechaFmt);

            // ── Envíos ────────────────────────────────────────────────────────
            $smtp = self::buildMailer();

            // Al asesor
            $smtp->clearAddresses();
            $smtp->addAddress(self::ASESOR_EMAIL);
            if ($clienteCorreo) $smtp->addReplyTo($clienteCorreo, $nombre);
            $smtp->Subject = $asuntoAsesor;
            $smtp->Body    = $cuerpoAsesor;
            $smtp->send();

            // Al cliente (si tiene correo)
            if ($clienteCorreo) {
                $smtp->clearAddresses();
                $smtp->addAddress($clienteCorreo, $nombre);
                $smtp->Subject = $asuntoCliente;
                $smtp->Body    = $cuerpoCliente;
                $smtp->send();
            }

        } catch (Exception $e) {
            error_log('VidaSolicitudController PHPMailer: ' . $e->getMessage());
        } catch (\Throwable $e) {
            error_log('VidaSolicitudController mail: ' . $e->getMessage());
        }
    }

    // ── Helpers de HTML ───────────────────────────────────────────────────────

    private static function row(string $label, string $value): string
    {
        return "<tr>
            <td style='padding:10px 0;border-bottom:1px solid #eee;white-space:nowrap;padding-right:16px;'>
                <strong>{$label}:</strong>
            </td>
            <td style='padding:10px 0;border-bottom:1px solid #eee;'>{$value}</td>
        </tr>";
    }

    private static function buildEmailAsesor(
        string $icon, string $label, string $gradient,
        string $nombre, string $correo, string $telefono,
        string $fecha, string $extraRows
    ): string {
        return "
        <html><body style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>
            <div style='background:linear-gradient(135deg,{$gradient});padding:28px;border-radius:12px 12px 0 0;'>
                <h2 style='color:#fff;margin:0;'>{$icon} Nueva Solicitud — {$label}</h2>
            </div>
            <div style='background:#f8fafc;padding:28px;border-radius:0 0 12px 12px;border:1px solid #e5e7eb;'>
                <table style='width:100%;border-collapse:collapse;'>
                    " . self::row('👤 Nombre', htmlspecialchars($nombre))
                      . self::row('✉️ Correo', htmlspecialchars($correo))
                      . self::row('📞 Teléfono', htmlspecialchars($telefono))
                      . self::row('📅 Contactar antes de', $fecha)
                      . $extraRows . "
                </table>
            </div>
        </body></html>";
    }

    private static function buildEmailCliente(
        string $icon, string $label, string $gradient,
        string $nombre, string $fecha
    ): string {
        return "
        <html><body style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>
            <div style='background:linear-gradient(135deg,{$gradient});padding:28px;border-radius:12px 12px 0 0;text-align:center;'>
                <div style='font-size:48px;'>{$icon}</div>
                <h2 style='color:#fff;margin:8px 0 0;'>¡Solicitud recibida!</h2>
            </div>
            <div style='padding:28px;'>
                <p>Hola <strong>" . htmlspecialchars($nombre) . "</strong>,</p>
                <p>Recibimos tu solicitud para <strong>{$label}</strong>.</p>
                <p>Nuestro especialista te contactará a más tardar el <strong>{$fecha}</strong>.</p>
                <p style='color:#6b7280;font-size:14px;'>Si tienes alguna duda puedes responder este correo o llamarnos al <strong>55 4123 2311</strong>.</p>
            </div>
        </body></html>";
    }

    // ── SMTP ──────────────────────────────────────────────────────────────────

    private static function buildMailer(): PHPMailer
    {
        if (!class_exists(PHPMailer::class)) {
            throw new \RuntimeException('Ejecuta composer install (PHPMailer no disponible).');
        }

        $smtpHost = getenv('SMTP_HOST') ?: '';
        $smtpUser = getenv('SMTP_USER') ?: '';
        $smtpPass = getenv('SMTP_PASS') ?: '';
        $smtpPort = (int)(getenv('SMTP_PORT') ?: (string) self::SMTP_DEFAULT_PORT);

        if ($smtpHost === '' || $smtpUser === '' || $smtpPass === '') {
            throw new \RuntimeException('Faltan SMTP_HOST, SMTP_USER o SMTP_PASS en variables de entorno.');
        }

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $smtpHost;
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtpUser;
        $mail->Password   = $smtpPass;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = $smtpPort;
        $mail->CharSet    = 'UTF-8';
        $mail->isHTML(true);
        $mail->setFrom($smtpUser, self::EMPRESA_NOMBRE);
        return $mail;
    }

    // ── Utilidades ────────────────────────────────────────────────────────────

    private static function formatFecha(string $fecha): string
    {
        $meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
                  'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
        [$y, $m, $d] = array_pad(explode('-', $fecha), 3, '1');
        return (int)$d . ' de ' . ($meses[(int)$m] ?? '') . ' de ' . $y;
    }

    private static function getConfig(): array
    {
        try {
            $db = Connection::getConnection();
            $rows = $db->query("SELECT clave, valor FROM configuracion")->fetchAll(\PDO::FETCH_KEY_PAIR);
            return $rows ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    private static function waSendText(string $to, string $msg, string $pid, string $token): void
    {
        $to = preg_replace('/[^0-9]/', '', $to);
        $payload = ['messaging_product' => 'whatsapp', 'to' => $to,
                    'type' => 'text', 'text' => ['body' => $msg]];
        $ch = curl_init("https://graph.facebook.com/v18.0/{$pid}/messages");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$token}", 'Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_TIMEOUT        => 8,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
}