<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\NotificacionModel;
use Database\Connection;
use PDO;

/**
 * App/Services/MetaLeadsService.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Procesa leads recibidos vía webhook de Meta Lead Ads (Facebook/Instagram).
 *
 * FLUJO:
 *   1. Meta envía al webhook solo un `leadgen_id` (no los datos del formulario).
 *   2. Este servicio llama a Graph API con ese id para obtener los campos
 *      que la persona llenó dentro del formulario instantáneo.
 *   3. Si el correo/teléfono ya existe en `clientes`, no duplica.
 *   4. Si es nuevo, asigna agente por menor carga (mismo criterio que
 *      VidaSolicitudController) e inserta en `clientes`.
 *   5. Crea una notificación in-app (tabla `notificaciones`) para el asesor
 *      asignado — el navbar la recoge por polling en tiempo casi real.
 *   6. Encola email y dispara WhatsApp al asesor, igual que los leads del
 *      formulario propio.
 *
 * CONFIGURACIÓN (tabla `configuracion`, igual patrón que `wa_token`/`wa_pid`):
 *   meta_page_access_token  → Token de acceso de la página de Facebook
 *   meta_app_secret         → App Secret de la app en Meta Developer Console
 *   meta_verify_token       → Token que tú eliges para verificar el webhook
 *   meta_form_ramo_<formId> → (opcional) mapea un form_id de Meta a un ramo
 *                              fijo, ej. meta_form_ramo_123456789 = autos
 * ─────────────────────────────────────────────────────────────────────────────
 */
class MetaLeadsService
{
    private const GRAPH_VERSION = 'v21.0';

    /**
     * Procesa un leadgen_id recibido en el webhook: lo descarga de Graph API,
     * lo guarda en el CRM, asigna asesor y dispara las notificaciones.
     *
     * Idempotente: si el leadgen_id ya fue procesado, no hace nada.
     */
    public static function procesarLead(string $leadgenId, string $formId = '', string $pageId = ''): void
    {
        try {
            if (self::yaProcesado($leadgenId)) {
                return;
            }

            $token = self::config('meta_page_access_token');
            if ($token === '') {
                error_log('[MetaLeadsService] meta_page_access_token no configurado.');
                return;
            }

            $datosLead = self::descargarLead($leadgenId, $token);
            if ($datosLead === null) {
                error_log("[MetaLeadsService] No se pudo descargar el lead {$leadgenId}.");
                return;
            }

            $campos = self::normalizarCampos($datosLead);
            $ramo   = self::resolverRamo($formId, $campos);

            if ($campos['nombre'] === '' || ($campos['telefono'] === '' && $campos['correo'] === '')) {
                error_log("[MetaLeadsService] Lead {$leadgenId} sin nombre/contacto suficiente. Datos: " . json_encode($campos));
                self::marcarProcesado($leadgenId, $formId, $pageId, 0);
                return;
            }

            [$clienteId, $agenteId, $esNuevo] = self::guardarEnCRM($campos, $ramo, $leadgenId, $formId);

            self::marcarProcesado($leadgenId, $formId, $pageId, $agenteId);

            if (!$esNuevo || !$agenteId) {
                // Cliente repetido o sin agentes activos: no se duplican avisos.
                return;
            }

            self::notificarAsesor($agenteId, $campos, $ramo, $clienteId);
            self::encolarEmailAsesor($agenteId, $campos, $ramo);
            self::notificarWhatsApp($campos, $ramo, $agenteId);

        } catch (\Throwable $e) {
            error_log('[MetaLeadsService] Error procesando lead: ' . $e->getMessage());
        }
    }

    // ── Descarga del lead vía Graph API ───────────────────────────────────────

    private static function descargarLead(string $leadgenId, string $token): ?array
    {
        $url = 'https://graph.facebook.com/' . self::GRAPH_VERSION . "/{$leadgenId}"
             . '?fields=field_data,form_id,created_time,ad_id,campaign_id'
             . '&access_token=' . urlencode($token);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode >= 400) {
            error_log("[MetaLeadsService] Graph API error (HTTP {$httpCode}): " . substr((string)$response, 0, 500));
            return null;
        }

        $data = json_decode($response, true);
        return is_array($data) ? $data : null;
    }

    /**
     * Meta devuelve field_data como lista de pares {name, values[]}.
     * Mapeamos los nombres más comunes de los formularios de Lead Ads
     * a las columnas que usa el CRM. Los nombres exactos dependen de cómo
     * se llamó cada pregunta al crear el formulario en Meta.
     */
    private static function normalizarCampos(array $datosLead): array
    {
        $valores = [];
        foreach ($datosLead['field_data'] ?? [] as $campo) {
            $clave = strtolower(trim($campo['name'] ?? ''));
            $valor = trim($campo['values'][0] ?? '');
            if ($clave !== '') {
                $valores[$clave] = $valor;
            }
        }

        $buscar = static function (array $candidatos) use ($valores): string {
            foreach ($candidatos as $c) {
                if (isset($valores[$c]) && $valores[$c] !== '') {
                    return $valores[$c];
                }
            }
            return '';
        };

        $nombreCompleto = $buscar(['full_name', 'nombre_completo', 'nombre']);
        if ($nombreCompleto === '') {
            $nombreCompleto = trim(
                $buscar(['first_name', 'nombre_de_pila']) . ' ' .
                $buscar(['last_name', 'apellido', 'apellidos'])
            );
        }

        return [
            'nombre'    => $nombreCompleto,
            'correo'    => $buscar(['email', 'correo', 'correo_electronico']),
            'telefono'  => self::normalizarTelefono($buscar(['phone_number', 'telefono', 'celular', 'whatsapp'])),
            'ciudad'    => $buscar(['city', 'ciudad']),
            'cp'        => $buscar(['post_code', 'zip_code', 'codigo_postal', 'cp']),
            'empresa'   => $buscar(['company_name', 'empresa', 'nombre_de_la_empresa']),
            'comentarios' => $buscar(['comments', 'comentarios', 'mensaje']),
        ];
    }

    private static function normalizarTelefono(string $telefono): string
    {
        $limpio = preg_replace('/[^0-9+]/', '', $telefono) ?? '';
        if ($limpio !== '' && !str_starts_with($limpio, '+')) {
            // Lead Ads en México normalmente ya manda con código de país;
            // si llega a 10 dígitos sin '+', se asume +52.
            if (strlen($limpio) === 10) {
                $limpio = '+52' . $limpio;
            }
        }
        return $limpio;
    }

    /**
     * El ramo del lead se define por mapeo manual form_id → ramo guardado en
     * `configuracion` (clave meta_form_ramo_<formId>). Si no hay mapeo,
     * cae a 'autos' por defecto (el enfoque principal de campaña actual).
     */
    private static function resolverRamo(string $formId, array $campos): string
    {
        if ($formId !== '') {
            $mapeo = self::config("meta_form_ramo_{$formId}");
            if (in_array($mapeo, ['vida', 'gmm', 'empresas', 'autos'], true)) {
                return $mapeo;
            }
        }
        return $campos['empresa'] !== '' ? 'empresas' : 'autos';
    }

    // ── Guardar en CRM con agente por menor carga ─────────────────────────────

    /**
     * @return array{0:int,1:int,2:bool} [clienteId, agenteId, esNuevo]
     */
    private static function guardarEnCRM(array $campos, string $ramo, string $leadgenId, string $formId): array
    {
        $db = Connection::getConnection();

        // No duplicar si ya existe el correo o teléfono
        if ($campos['correo'] !== '' || $campos['telefono'] !== '') {
            $st = $db->prepare(
                'SELECT id, agente_id FROM clientes
                 WHERE (correo = ? AND correo <> "")
                    OR (telefono = ? AND telefono <> "")
                 LIMIT 1'
            );
            $st->execute([$campos['correo'], $campos['telefono']]);
            $existente = $st->fetch(PDO::FETCH_ASSOC);
            if ($existente) {
                return [(int) $existente['id'], (int) ($existente['agente_id'] ?? 0), false];
            }
        }

        // Agente con menor número de clientes asignados (mismo criterio que
        // el formulario público propio en VidaSolicitudController).
        $stA = $db->query(
            "SELECT a.id
             FROM agentes a
             LEFT JOIN clientes c ON c.agente_id = a.id
             WHERE a.rol = 'asesor' AND COALESCE(a.activo,1) = 1
             GROUP BY a.id
             ORDER BY COUNT(c.id) ASC
             LIMIT 1"
        );
        $agRow    = $stA->fetch(PDO::FETCH_ASSOC);
        $agenteId = $agRow ? (int) $agRow['id'] : 0;

        if (!$agenteId) {
            error_log('[MetaLeadsService] No hay agentes activos para asignar.');
            return [0, 0, true];
        }

        $partes = explode(' ', trim($campos['nombre']), 3);
        $primerNombre    = $partes[0] ?? $campos['nombre'];
        $apellidoPaterno = $partes[1] ?? '';
        $apellidoMaterno = $partes[2] ?? '';

        $datos = [
            'nombre'           => $primerNombre,
            'apellido_paterno' => $apellidoPaterno,
            'apellido_materno' => $apellidoMaterno,
            'correo'           => $campos['correo'] ?: null,
            'telefono'         => $campos['telefono'] ?: null,
            'ciudad'           => $campos['ciudad'] ?: null,
            'codigo_postal'    => $campos['cp'] ?: null,
            'ramo'             => $ramo,
            'agente_id'        => $agenteId,
            'estado_cliente'   => 'nuevo',
            'asegurado'        => 'no',
            'origen'           => 'meta_lead_ads',
        ];

        if ($ramo === 'empresas' && $campos['empresa'] !== '') {
            $datos['giro_empresa'] = $campos['empresa'];
        }

        // 'origen' puede no existir en instalaciones antiguas de la tabla.
        try {
            $cols   = implode(', ', array_keys($datos));
            $places = implode(', ', array_fill(0, count($datos), '?'));
            $db->prepare("INSERT INTO clientes ({$cols}) VALUES ({$places})")->execute(array_values($datos));
        } catch (\Throwable $e) {
            unset($datos['origen']);
            $cols   = implode(', ', array_keys($datos));
            $places = implode(', ', array_fill(0, count($datos), '?'));
            $db->prepare("INSERT INTO clientes ({$cols}) VALUES ({$places})")->execute(array_values($datos));
        }

        $clienteId = (int) $db->lastInsertId();

        self::guardarLogLead($leadgenId, $formId, $campos, $ramo, $clienteId, $agenteId);

        return [$clienteId, $agenteId, true];
    }

    // ── Tabla de control de idempotencia + log histórico de leads de Meta ─────

    private static function asegurarTablaLeads(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS meta_leads (
                id              INT AUTO_INCREMENT PRIMARY KEY,
                leadgen_id      VARCHAR(64)  NOT NULL,
                form_id         VARCHAR(64)  DEFAULT NULL,
                page_id         VARCHAR(64)  DEFAULT NULL,
                nombre          VARCHAR(200) DEFAULT NULL,
                correo          VARCHAR(200) DEFAULT NULL,
                telefono        VARCHAR(30)  DEFAULT NULL,
                ramo            VARCHAR(20)  DEFAULT NULL,
                cliente_id      INT          DEFAULT NULL,
                agente_id       INT          DEFAULT NULL,
                procesado_en    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_leadgen_id (leadgen_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }

    private static function yaProcesado(string $leadgenId): bool
    {
        $db = Connection::getConnection();
        self::asegurarTablaLeads($db);

        $st = $db->prepare('SELECT id FROM meta_leads WHERE leadgen_id = ? LIMIT 1');
        $st->execute([$leadgenId]);
        return (bool) $st->fetchColumn();
    }

    private static function marcarProcesado(string $leadgenId, string $formId, string $pageId, int $agenteId): void
    {
        $db = Connection::getConnection();
        self::asegurarTablaLeads($db);

        $db->prepare(
            'INSERT IGNORE INTO meta_leads (leadgen_id, form_id, page_id, agente_id)
             VALUES (?, ?, ?, ?)'
        )->execute([$leadgenId, $formId ?: null, $pageId ?: null, $agenteId ?: null]);
    }

    private static function guardarLogLead(
        string $leadgenId, string $formId, array $campos, string $ramo, int $clienteId, int $agenteId
    ): void {
        try {
            $db = Connection::getConnection();
            self::asegurarTablaLeads($db);
            $db->prepare(
                'UPDATE meta_leads
                 SET nombre = ?, correo = ?, telefono = ?, ramo = ?, cliente_id = ?, agente_id = ?
                 WHERE leadgen_id = ?'
            )->execute([
                $campos['nombre'] ?: null, $campos['correo'] ?: null, $campos['telefono'] ?: null,
                $ramo, $clienteId ?: null, $agenteId ?: null, $leadgenId,
            ]);
        } catch (\Throwable $e) {
            error_log('[MetaLeadsService] Error guardando log de lead: ' . $e->getMessage());
        }
    }

    // ── Notificación in-app al asesor asignado ────────────────────────────────

    private static function notificarAsesor(int $agenteId, array $campos, string $ramo, int $clienteId): void
    {
        try {
            $ramoLabel = match ($ramo) {
                'gmm'      => 'Gastos Médicos Mayores',
                'empresas' => 'Seguros Empresariales',
                'autos'    => 'Seguro Vehicular',
                default    => 'Seguro de Vida',
            };

            $titulo  = '📣 Nuevo lead de Meta — ' . $ramoLabel;
            $mensaje = trim($campos['nombre']) . ($campos['telefono'] ? ' · ' . $campos['telefono'] : '')
                     . '. Te fue asignado automáticamente, contáctalo cuanto antes.';

            (new NotificacionModel())->crear(
                $agenteId,
                'sistema',
                $titulo,
                $mensaje,
                (string) $clienteId
            );
        } catch (\Throwable $e) {
            error_log('[MetaLeadsService] Error creando notificación in-app: ' . $e->getMessage());
        }
    }

    // ── Email al asesor asignado ──────────────────────────────────────────────

    private static function encolarEmailAsesor(int $agenteId, array $campos, string $ramo): void
    {
        try {
            $db = Connection::getConnection();
            $st = $db->prepare('SELECT nombre, correo FROM agentes WHERE id = ? LIMIT 1');
            $st->execute([$agenteId]);
            $agente = $st->fetch(PDO::FETCH_ASSOC);
            if (!$agente || empty($agente['correo'])) {
                return;
            }

            $ramoLabel = match ($ramo) {
                'gmm'      => 'Gastos Médicos Mayores',
                'empresas' => 'Seguros Empresariales',
                'autos'    => 'Seguro Vehicular',
                default    => 'Seguro de Vida',
            };

            $body = "
            <html><body style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>
                <div style='background:linear-gradient(135deg,#1877f2,#0d65d9);padding:28px;border-radius:12px 12px 0 0;'>
                    <h2 style='color:#fff;margin:0;'>📣 Nuevo lead de Facebook/Instagram — {$ramoLabel}</h2>
                </div>
                <div style='background:#f8fafc;padding:28px;border-radius:0 0 12px 12px;border:1px solid #e5e7eb;'>
                    <table style='width:100%;border-collapse:collapse;'>
                        <tr><td style='padding:10px 0;border-bottom:1px solid #eee;'><strong>👤 Nombre:</strong></td><td style='padding:10px 0;border-bottom:1px solid #eee;'>" . htmlspecialchars($campos['nombre']) . "</td></tr>
                        <tr><td style='padding:10px 0;border-bottom:1px solid #eee;'><strong>📞 Teléfono:</strong></td><td style='padding:10px 0;border-bottom:1px solid #eee;'>" . htmlspecialchars($campos['telefono'] ?: '(no proporcionado)') . "</td></tr>
                        <tr><td style='padding:10px 0;border-bottom:1px solid #eee;'><strong>✉️ Correo:</strong></td><td style='padding:10px 0;border-bottom:1px solid #eee;'>" . htmlspecialchars($campos['correo'] ?: '(no proporcionado)') . "</td></tr>
                    </table>
                    <p style='margin-top:20px;color:#1877f2;font-weight:bold;'>Este lead te fue asignado automáticamente. Contáctalo lo antes posible.</p>
                </div>
            </body></html>";

            MailQueue::push(
                to:      $agente['correo'],
                toName:  $agente['nombre'] ?? '',
                subject: "[Seguros LUX] Nuevo lead de Meta — {$campos['nombre']}",
                body:    $body,
            );
        } catch (\Throwable $e) {
            error_log('[MetaLeadsService] Error encolando email: ' . $e->getMessage());
        }
    }

    // ── WhatsApp al asesor (reutiliza configuración existente wa_token/wa_pid) ─

    private static function notificarWhatsApp(array $campos, string $ramo, int $agenteId): void
    {
        try {
            $waToken = self::config('wa_token');
            $waPid   = self::config('wa_pid');
            $db      = Connection::getConnection();

            $st = $db->prepare('SELECT telefono FROM agentes WHERE id = ? LIMIT 1');
            $st->execute([$agenteId]);
            $telAsesor = $st->fetchColumn();

            if (!$waToken || !$waPid || !$telAsesor) {
                return;
            }

            $ramoLabel = strtoupper($ramo);
            $msg = "📣 *Nuevo lead de Meta — {$ramoLabel}*\n"
                 . "👤 " . $campos['nombre'] . "\n"
                 . ($campos['telefono'] ? "📞 {$campos['telefono']}\n" : '')
                 . ($campos['correo']   ? "📧 {$campos['correo']}\n"   : '')
                 . "👔 Te fue asignado automáticamente.";

            $to = preg_replace('/[^0-9]/', '', (string) $telAsesor);
            $ch = curl_init("https://graph.facebook.com/" . self::GRAPH_VERSION . "/{$waPid}/messages");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$waToken}", 'Content-Type: application/json'],
                CURLOPT_POSTFIELDS     => json_encode([
                    'messaging_product' => 'whatsapp',
                    'to'                => $to,
                    'type'              => 'text',
                    'text'              => ['body' => $msg],
                ]),
                CURLOPT_TIMEOUT        => 8,
            ]);
            curl_exec($ch);
            curl_close($ch);
        } catch (\Throwable $e) {
            error_log('[MetaLeadsService] Error notificando WhatsApp: ' . $e->getMessage());
        }
    }

    // ── Config helper (tabla `configuracion`, clave/valor) ────────────────────

    public static function config(string $clave): string
    {
        try {
            $st = Connection::getConnection()->prepare('SELECT valor FROM configuracion WHERE clave = ? LIMIT 1');
            $st->execute([$clave]);
            $valor = $st->fetchColumn();
            return $valor !== false ? (string) $valor : '';
        } catch (\Throwable $e) {
            return '';
        }
    }
}
