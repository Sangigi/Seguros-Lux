<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\NotificacionModel;
use App\Security\EmpresaPublica;
use App\Security\Tenant;
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
 *   meta_form_empresa_<formId> → (opcional) mapea un form_id a la empresa
 *                              dueña del formulario, por slug o por id.
 *   meta_page_empresa_<pageId> → (opcional) lo mismo a nivel de página de
 *                              Facebook, para cuando todos los formularios de
 *                              una página son de la misma empresa.
 *
 * MULTIEMPRESA:
 *   El webhook de Meta no trae empresa: llega un leadgen_id y nada más. Se
 *   resuelve antes de tocar la base, siguiendo el patrón del contrato:
 *     1. identificar el registro por form_id / page_id;
 *     2. leer su empresa en modo plataforma (las claves meta_* viven en
 *        empresa_id = 0, son de la plataforma, no de ninguna empresa);
 *     3. entrar a esa empresa con Tenant::comoEmpresa() y trabajar dentro.
 *   Si no hay mapeo se usa la empresa por defecto de la plataforma. Nunca se
 *   crea un lead sin empresa: quedaría invisible para todos.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class MetaLeadsService
{
    private const GRAPH_VERSION = 'v21.0';

    /** empresa_id de los ajustes globales de plataforma: claves meta_ y wa_. */
    private const EMPRESA_PLATAFORMA = 0;

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

            // ── Empresa del lead ─────────────────────────────────────────────
            // Se resuelve antes de entrar: a partir de aquí todo ocurre dentro
            // de una empresa concreta y lo que se cree queda sellado en ella.
            $empresaId = self::resolverEmpresa($formId, $pageId);

            Tenant::comoEmpresa($empresaId, static function () use (
                $datosLead, $leadgenId, $formId, $pageId
            ): void {
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
            });

        } catch (\Throwable $e) {
            error_log('[MetaLeadsService] Error procesando lead: ' . $e->getMessage());
        }
    }

    // ── Empresa dueña del lead ───────────────────────────────────────────────

    /**
     * A qué empresa pertenece un lead de Meta.
     *
     * El formulario de Meta es lo único que identifica al dueño, así que el
     * mapeo se declara en la configuración de plataforma: primero por form_id
     * (lo más específico) y, si no hay, por page_id. Lo que se declare se
     * valida contra `empresas` —puede estar mal escrito o apuntar a una
     * empresa suspendida— y si nada resuelve cae en la empresa por defecto.
     */
    private static function resolverEmpresa(string $formId, string $pageId): int
    {
        $candidatos = [];
        if ($formId !== '') {
            $candidatos[] = self::config("meta_form_empresa_{$formId}");
        }
        if ($pageId !== '') {
            $candidatos[] = self::config("meta_page_empresa_{$pageId}");
        }

        foreach ($candidatos as $valor) {
            $empresaId = self::empresaDeValor($valor);
            if ($empresaId !== null) {
                return $empresaId;
            }
        }

        return EmpresaPublica::porDefecto();
    }

    /**
     * Convierte el valor de un mapeo (id numérico o slug) en una empresa real
     * y activa. Devuelve null si no resuelve: el valor lo escribe una persona
     * en el panel y no puede darse por bueno sin comprobarlo.
     */
    private static function empresaDeValor(string $valor): ?int
    {
        $valor = trim($valor);
        if ($valor === '') {
            return null;
        }

        if (ctype_digit($valor)) {
            return EmpresaPublica::existeActiva((int) $valor) ? (int) $valor : null;
        }

        return EmpresaPublica::desdeSlug($valor);
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

        // Empresa resuelta en procesarLead(); toda esta alta ocurre dentro.
        $empresaId = Tenant::requireId();

        // No duplicar si ya existe el correo o teléfono EN ESTA EMPRESA. El
        // mismo contacto en otra empresa es otro cliente: devolver su id aquí
        // dejaría el lead apuntando a un registro que esta empresa no ve.
        if ($campos['correo'] !== '' || $campos['telefono'] !== '') {
            $st = $db->prepare(
                'SELECT id, agente_id FROM clientes
                 WHERE ((correo = ? AND correo <> "")
                    OR (telefono = ? AND telefono <> ""))
                   AND empresa_id = ?
                 LIMIT 1'
            );
            $st->execute([$campos['correo'], $campos['telefono'], $empresaId]);
            $existente = $st->fetch(PDO::FETCH_ASSOC);
            if ($existente) {
                return [(int) $existente['id'], (int) ($existente['agente_id'] ?? 0), false];
            }
        }

        // Agente con menor número de clientes asignados dentro de la empresa
        // (mismo criterio que el formulario público propio en
        // VidaSolicitudController). El reparto nunca cruza empresas.
        $stA = $db->prepare(
            "SELECT a.id
             FROM agentes a
             LEFT JOIN clientes c ON c.agente_id = a.id AND c.empresa_id = :empresa_cli
             WHERE a.rol = 'asesor' AND COALESCE(a.activo,1) = 1
               AND a.empresa_id = :empresa
             GROUP BY a.id
             ORDER BY COUNT(c.id) ASC
             LIMIT 1"
        );
        $stA->execute(['empresa' => $empresaId, 'empresa_cli' => $empresaId]);
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
            // La empresa va dentro del INSERT: un lead sin empresa no lo vería
            // nadie, que es exactamente lo que no puede pasar con un lead pagado.
            'empresa_id'       => $empresaId,
        ];

        if ($ramo === 'empresas' && $campos['empresa'] !== '') {
            $datos['giro_empresa'] = $campos['empresa'];
        }

        // 'origen' puede no existir en instalaciones antiguas de la tabla.
        try {
            $cols   = implode(', ', array_keys($datos));
            // $datos lleva empresa_id dentro.
            $places = implode(', ', array_fill(0, count($datos), '?'));
            // tenant-ok: $datos lleva empresa_id
            $db->prepare("INSERT INTO clientes ({$cols}) VALUES ({$places})")->execute(array_values($datos));
        } catch (\Throwable $e) {
            error_log('[MetaLeadsService] INSERT clientes falló con origen incluido: ' . $e->getMessage());
            unset($datos['origen']);
            $cols   = implode(', ', array_keys($datos));
            // El reintento quita 'origen', nunca empresa_id.
            $places = implode(', ', array_fill(0, count($datos), '?'));
            // tenant-ok: $datos lleva empresa_id
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
                empresa_id      INT          DEFAULT NULL,
                procesado_en    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_leadgen_id (leadgen_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // La migración 005 solo añade empresa_id a tablas que ya existían; esta
        // se crea en caliente, así que la columna se asegura aquí también.
        try {
            $chk = $db->prepare(
                "SELECT 1 FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = 'meta_leads' AND column_name = 'empresa_id'"
            );
            $chk->execute();
            if (!$chk->fetch()) {
                $db->exec('ALTER TABLE meta_leads ADD COLUMN empresa_id INT DEFAULT NULL');
            }
        } catch (\Throwable) {}
    }

    /**
     * Idempotencia del webhook.
     *
     * El leadgen_id es único en Meta para toda la plataforma, así que la
     * comprobación tiene que mirar todas las empresas: si se filtrara por la
     * empresa activa, un reintento de Meta mal mapeado crearía el mismo lead
     * otra vez en otra empresa. Además se ejecuta antes de resolver la
     * empresa, cuando todavía no hay contexto.
     */
    private static function yaProcesado(string $leadgenId): bool
    {
        return Tenant::sinRestriccion(static function () use ($leadgenId): bool {
            $db = Connection::getConnection();
            self::asegurarTablaLeads($db);

            // tenant-ok: el leadgen_id es único en toda la plataforma; la idempotencia del webhook no puede ser por empresa
            $st = $db->prepare('SELECT id FROM meta_leads WHERE leadgen_id = ? LIMIT 1');
            $st->execute([$leadgenId]);
            return (bool) $st->fetchColumn();
        });
    }

    private static function marcarProcesado(string $leadgenId, string $formId, string $pageId, int $agenteId): void
    {
        $db = Connection::getConnection();
        self::asegurarTablaLeads($db);

        // empresa_id se graba junto al lead: el log histórico de Meta también
        // es de una empresa concreta, no de la plataforma.
        $db->prepare(
            'INSERT IGNORE INTO meta_leads (leadgen_id, form_id, page_id, agente_id, empresa_id)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([$leadgenId, $formId ?: null, $pageId ?: null, $agenteId ?: null, Tenant::requireId()]);
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
                 WHERE leadgen_id = ? AND empresa_id = ?'
            )->execute([
                $campos['nombre'] ?: null, $campos['correo'] ?: null, $campos['telefono'] ?: null,
                $ramo, $clienteId ?: null, $agenteId ?: null, $leadgenId, Tenant::requireId(),
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
            $st = $db->prepare('SELECT nombre, correo FROM agentes WHERE id = ? AND empresa_id = ? LIMIT 1');
            $st->execute([$agenteId, Tenant::requireId()]);
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

            $st = $db->prepare('SELECT telefono FROM agentes WHERE id = ? AND empresa_id = ? LIMIT 1');
            $st->execute([$agenteId, Tenant::requireId()]);
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

    /**
     * Lee un ajuste de PLATAFORMA (empresa_id = 0).
     *
     * Todas las claves que usa este servicio —meta_app_secret,
     * meta_verify_token, meta_page_access_token, meta_form_*, wa_token,
     * wa_pid— son de la integración, no de una empresa: hay una sola app de
     * Meta y un solo número de WhatsApp para toda la plataforma. Por eso se
     * fija el ámbito explícitamente en vez de depender de la empresa activa:
     * el webhook llega sin ninguna, y además meta_webhook.php llama a este
     * método antes de resolver nada para verificar la firma.
     */
    public static function config(string $clave): string
    {
        try {
            $st = Connection::getConnection()->prepare(
                'SELECT valor FROM configuracion WHERE clave = ? AND empresa_id = ? LIMIT 1'
            );
            $st->execute([$clave, self::EMPRESA_PLATAFORMA]);
            $valor = $st->fetchColumn();
            return $valor !== false ? (string) $valor : '';
        } catch (\Throwable $e) {
            return '';
        }
    }
}
