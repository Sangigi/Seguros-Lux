<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Services\MailQueue;
use App\Security\RateLimiter;
use App\Security\SecurityLogger;
use App\Models\NotificacionModel;
use Database\Connection;

$__crmAutoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
if (is_file($__crmAutoload)) {
    require_once $__crmAutoload;
}

/**
 * Controlador unificado de solicitudes de cotización pública.
 * Maneja: vida, gmm, empresas, autos.
 *
 * FIXES aplicados:
 *  1. Guarda el cliente en la tabla `clientes` (CRM) con agente asignado por menor carga.
 *  2. Detecta si el correo ya existe y avisa si tiene citas activas (max 3).
 *  3. Bloquea registro si el cliente ya tiene >= 3 citas activas simultáneas.
 *  4. Emails completos para todos los ramos sin pérdida de datos.
 *  5. Captura campos extendidos de autos:  versión, CP, domicilio, fecha_nacimiento.
 *  6. Captura campo `hospitales` y `urgencia` del formulario GMM.
 */
class VidaSolicitudController
{
    private const ASESOR_EMAIL   = 'contacto@seguroslux.mx';
    private const EMPRESA_NOMBRE = 'Seguros LUX';
    private const MAX_CITAS      = 3;

    public static function handle(): void
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['ok' => false, 'msg' => 'Método no permitido.']);
            return;
        }

        // ── Rate limiting por IP (formulario público, sin sesión) ─────────────
        // Reutiliza el RateLimiter existente: clave fija 'publica' + IP.
        // Límite: 5 solicitudes / 15 min por IP. Mismo comportamiento que el login.
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if (RateLimiter::tooManyAttempts('publica', $ip)) {
            SecurityLogger::log('public_form_rate_limited', ['ip' => $ip]);
            http_response_code(429);
            echo json_encode([
                'ok'  => false,
                'msg' => 'Demasiadas solicitudes. Espera unos minutos e intenta de nuevo.',
            ]);
            return;
        }
        RateLimiter::hit('publica', $ip);

        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        // ── Campos comunes ────────────────────────────────────────────────────
        $nombre      = trim($input['nombre']           ?? '');
        $correo      = trim($input['correo']           ?? '');
        $telefono    = trim($input['telefono']         ?? '');
        $fechaPref   = trim($input['fecha_preferida']  ?? date('Y-m-d', strtotime('+2 days')));
        $comentarios = trim($input['comentarios']      ?? '');
        $nacimiento  = trim($input['fecha_nacimiento'] ?? '');

        $ramo = in_array($input['ramo'] ?? '', ['vida', 'gmm', 'empresas', 'autos'], true)
            ? $input['ramo'] : 'vida';

        // ── Campos GMM ────────────────────────────────────────────────────────
        $edadGmm    = trim($input['edad']          ?? '');
        $depGmm     = trim($input['dependientes']  ?? '');
        $hospitales = trim($input['hospitales']    ?? '');
        $urgencia   = trim($input['urgencia']      ?? '');

        // ── Campos empresas ───────────────────────────────────────────────────
        $empresa    = trim($input['empresa']      ?? '');
        $giroEmp    = trim($input['giro_empresa'] ?? '');
        $empleados  = trim($input['empleados']    ?? '');
        $servicios  = trim($input['servicios']    ?? '');

        // ── Campos autos (extendidos) ─────────────────────────────────────────
        $tipoVeh     = trim($input['tipo_vehiculo']  ?? '');
        $marca       = trim($input['marca']          ?? '');
        $submarca    = trim($input['submarca']       ?? '');
        $modelo      = trim($input['modelo']         ?? '');
        $version     = trim($input['version']        ?? '');
        $anio        = trim($input['anio']           ?? '');
        $domicilio   = trim($input['domicilio']      ?? '');
        $cp          = trim($input['cp']             ?? '');
        $numUnidades = trim($input['num_unidades']   ?? '');
        $tipoFlota   = trim($input['tipo_flota']     ?? '');

        // ── Formulario propio de campaña vehicular (seguroslux.mx/campana-vehicular) ─
        // Usa nombres de campo distintos a los del formulario interno de "autos".
        // Si vienen, se traducen aquí sin afectar al formulario interno existente.
        $vehiculoCampana = trim($input['vehiculo']     ?? ''); // auto | moto | flotilla
        $tieneSeguro     = trim($input['tiene_seguro'] ?? ''); // si | no | cotizando
        $intencion       = trim($input['intencion']    ?? ''); // contratar | renovar | comparar
        $vencimientoSel  = trim($input['vencimiento']  ?? ''); // este-mes | 1-3-meses | ...
        $formaPago       = trim($input['pago']         ?? ''); // contado | meses-3 | ...

        if ($vehiculoCampana !== '') {
            // El formulario de campaña no manda 'ramo' explícito: si llenaron
            // 'vehiculo', asumimos que es una solicitud de autos.
            if (!isset($input['ramo']) || $input['ramo'] === '') {
                $ramo = 'autos';
            }
            if ($cp === '' && isset($input['codigo_postal'])) {
                $cp = trim((string) $input['codigo_postal']);
            }
            // Mapear "flotilla" del select al campo tipo_vehiculo interno.
            if ($tipoVeh === '') {
                $tipoVeh = match ($vehiculoCampana) {
                    'flotilla' => 'van',   // tipo_vehiculo enum no tiene "flotilla"; van es lo más cercano
                    'moto'     => 'moto',
                    default    => 'auto',
                };
            }
            // 'flotilla' además dispara el flujo de ramo empresas si el usuario
            // seleccionó explícitamente esa opción (varios vehículos = empresa).
            if ($vehiculoCampana === 'flotilla' && (!isset($input['ramo']) || $input['ramo'] === '')) {
                $ramo = 'empresas';
            }
            // El detalle de la campaña (qué tan urgido está, cómo quiere pagar,
            // etc.) no tiene columnas propias todavía: se anexa a "comentarios"
            // para que el asesor lo vea íntegro sin perder información.
            $detalleCampana = array_filter([
                $tieneSeguro    !== '' ? 'Seguro actual: ' . match ($tieneSeguro) {
                    'si'        => 'sí tiene',
                    'no'        => 'no tiene',
                    'cotizando' => 'está cotizando con otra aseguradora',
                    default     => $tieneSeguro,
                } : null,
                $intencion      !== '' ? 'Quiere: ' . match ($intencion) {
                    'contratar' => 'contratar nuevo seguro',
                    'renovar'   => 'renovar su seguro actual',
                    'comparar'  => 'comparar opciones',
                    default     => $intencion,
                } : null,
                $vencimientoSel !== '' ? 'Vencimiento de su póliza actual: ' . match ($vencimientoSel) {
                    'este-mes'    => 'este mes',
                    '1-3-meses'   => 'en 1 a 3 meses',
                    'mas-3-meses' => 'en más de 3 meses',
                    'no-se'       => 'no sabe / no aplica',
                    default       => $vencimientoSel,
                } : null,
                $formaPago      !== '' ? 'Forma de pago preferida: ' . match ($formaPago) {
                    'contado'    => 'de contado',
                    'meses-3'    => '3 meses sin intereses',
                    'meses-6'    => '6 meses sin intereses',
                    'meses-12'   => '12 meses sin intereses',
                    'meses-mas'  => 'más de 12 meses',
                    'no-decido'  => 'aún no decide',
                    default      => $formaPago,
                } : null,
            ]);
            if ($detalleCampana) {
                $comentarios = trim($comentarios . ($comentarios ? "\n" : '') . implode(' · ', $detalleCampana));
            }
        }

        // ── Validación básica ─────────────────────────────────────────────────
        if (!$nombre || !$telefono) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'msg' => 'Nombre y teléfono son obligatorios.']);
            return;
        }
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

        // ── Verificar citas activas del cliente por correo ────────────────────
        $advertencia = null;
        if ($correo && filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            try {
                $db = Connection::getConnection();
                $stmtCl = $db->prepare("SELECT id FROM clientes WHERE correo = ? LIMIT 1");
                $stmtCl->execute([$correo]);
                $clienteIdExistente = $stmtCl->fetchColumn();

                if ($clienteIdExistente) {
                    $stmtC = $db->prepare(
                        "SELECT COUNT(*) FROM citas
                         WHERE cliente_id = ?
                           AND estado IN ('programada','pendiente')
                           AND fecha >= CURDATE()"
                    );
                    $stmtC->execute([$clienteIdExistente]);
                    $citasActivas = (int)$stmtC->fetchColumn();

                    if ($citasActivas >= self::MAX_CITAS) {
                        http_response_code(409);
                        echo json_encode([
                            'ok'            => false,
                            'citas_activas' => $citasActivas,
                            'msg'           => "Ya tienes {$citasActivas} citas activas (máximo " . self::MAX_CITAS . "). Por favor cancela alguna antes de agendar una nueva.",
                        ]);
                        return;
                    }

                    if ($citasActivas > 0) {
                        $advertencia = "Tienes {$citasActivas} cita(s) activa(s). Tu solicitud fue registrada y un asesor te contactará pronto.";
                    }
                }
            } catch (\Throwable $e) {
                error_log('[VidaSolicitudController] Error verificando citas: ' . $e->getMessage());
            }
        }

        // ── Guardar en CRM (tabla clientes) con agente por menor carga ─────────
        [$agenteAsignado, $clienteId, $esNuevo] = self::guardarEnCRM(
            nombre: $nombre, correo: $correo, telefono: $telefono, nacimiento: $nacimiento,
            ramo: $ramo, tipoVeh: $tipoVeh, marca: $marca, submarca: $submarca,
            modelo: $modelo, version: $version, anio: $anio, domicilio: $domicilio, cp: $cp,
            edadGmm: $edadGmm, depGmm: $depGmm, hospitales: $hospitales,
            empresa: $empresa, giroEmp: $giroEmp, empleados: $empleados, servicios: $servicios,
            comentarios: $comentarios, numUnidades: $numUnidades, tipoFlota: $tipoFlota
        );

        // ── Avisar en tiempo real al asesor que le cayó el cliente ─────────────
        // Solo para leads nuevos (si ya existía el correo, no se duplica el aviso).
        // El navbar del CRM hace polling cada 8s, así que el asesor lo ve casi
        // de inmediato sin tener que recargar la página.
        if ($esNuevo && $agenteAsignado) {
            self::notificarAsesorAsignado($agenteAsignado, $clienteId, $nombre, $telefono, $ramo, $vehiculoCampana);
        }

        // ── Guardar en vida_solicitudes (log histórico) ────────────────────────
        self::guardarEnSolicitudes(
            nombre: $nombre, correo: $correo, telefono: $telefono, fechaPref: $fechaPref,
            ramo: $ramo, edadGmm: $edadGmm, depGmm: $depGmm, comentarios: $comentarios,
            empresa: $empresa, empleados: $empleados, servicios: $servicios,
            marca: $marca, submarca: $submarca, modelo: $modelo, version: $version,
            anio: $anio, tipoVeh: $tipoVeh, hospitales: $hospitales,
            cp: $cp, domicilio: $domicilio
        );

        // ── Encolar correos ───────────────────────────────────────────────────
        self::encolarCorreos(
            nombre: $nombre, correo: $correo ?: self::ASESOR_EMAIL,
            telefono: $telefono, fechaPref: $fechaPref, ramo: $ramo,
            edadGmm: $edadGmm, depGmm: $depGmm, comentarios: $comentarios,
            empresa: $empresa, giroEmp: $giroEmp, empleados: $empleados, servicios: $servicios,
            marca: $marca, submarca: $submarca, modelo: $modelo, version: $version,
            anio: $anio, tipoVeh: $tipoVeh, domicilio: $domicilio, cp: $cp,
            hospitales: $hospitales, urgencia: $urgencia,
            numUnidades: $numUnidades, tipoFlota: $tipoFlota, nacimiento: $nacimiento
        );

        // ── Notificación WhatsApp ─────────────────────────────────────────────
        try {
            $config  = self::getConfig();
            $waToken = $config['wa_token'] ?? '';
            $waPid   = $config['wa_pid']   ?? '';

            if ($waToken && $waPid) {
                $ramoLabel = strtoupper($ramo);
                $vehiculo  = trim("{$marca} {$submarca} {$modelo} {$version} {$anio}");
                $msg = "🔔 *Nueva solicitud — {$ramoLabel}*\n"
                     . "👤 {$nombre}\n"
                     . ($correo     ? "📧 {$correo}\n"              : '')
                     . "📞 {$telefono}\n"
                     . ($vehiculo   ? "🚗 {$vehiculo}\n"            : '')
                     . ($empresa    ? "🏢 {$empresa}\n"             : '')
                     . ($empleados  ? "👥 Empleados: {$empleados}\n" : '')
                     . ($edadGmm    ? "🎂 Edad: {$edadGmm} años\n"  : '')
                     . ($hospitales ? "🏥 Hospitales: {$hospitales}\n" : '')
                     . ($cp         ? "📍 CP: {$cp}\n"              : '')
                     . ($comentarios ? "💬 {$comentarios}\n"         : '')
                     . ($agenteAsignado ? "👔 Asesor ID asignado: {$agenteAsignado}\n" : '')
                     . "📅 Contactar antes: " . self::formatFecha($fechaPref);

                $asesorPhone = $config['asesor_whatsapp'] ?? '';
                if ($asesorPhone) {
                    self::waSendText($asesorPhone, $msg, $waPid, $waToken);
                }

                // Además del aviso al ATC general (arriba), se manda el mismo
                // mensaje directo al celular del asesor que quedó asignado,
                // si lo tiene registrado en su perfil.
                if ($esNuevo && $agenteAsignado) {
                    try {
                        $dbAsesor = Connection::getConnection();
                        $telAsesor = $dbAsesor->prepare('SELECT telefono FROM agentes WHERE id = ? LIMIT 1');
                        $telAsesor->execute([$agenteAsignado]);
                        $telAsesorValor = $telAsesor->fetchColumn();
                        if ($telAsesorValor) {
                            self::waSendText((string) $telAsesorValor, $msg, $waPid, $waToken);
                        }
                    } catch (\Throwable $e) {
                        error_log('WA notify asesor error: ' . $e->getMessage());
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('WA notify error: ' . $e->getMessage());
        }

        $response = ['ok' => true, 'msg' => 'Solicitud recibida correctamente.'];
        if ($advertencia) {
            $response['advertencia'] = $advertencia;
        }
        echo json_encode($response);
    }

    // ── Guardar en tabla clientes (CRM) con agente por menor carga ────────────

    /**
     * @return array{0:int,1:int,2:bool} [agenteId, clienteId, esNuevo]
     */
    private static function guardarEnCRM(
        string $nombre, string $correo, string $telefono, string $nacimiento,
        string $ramo, string $tipoVeh, string $marca, string $submarca,
        string $modelo, string $version, string $anio, string $domicilio, string $cp,
        string $edadGmm, string $depGmm, string $hospitales,
        string $empresa, string $giroEmp, string $empleados, string $servicios,
        string $comentarios, string $numUnidades, string $tipoFlota
    ): array {
        try {
            $db = Connection::getConnection();

            // No duplicar si ya existe el correo
            if ($correo) {
                $st = $db->prepare("SELECT id, agente_id FROM clientes WHERE correo = ? LIMIT 1");
                $st->execute([$correo]);
                $existente = $st->fetch(\PDO::FETCH_ASSOC);
                if ($existente) {
                    return [(int)($existente['agente_id'] ?? 0), (int)$existente['id'], false];
                }
            }

            // Agente con menor número de clientes asignados
            $stA = $db->query(
                "SELECT a.id
                 FROM agentes a
                 LEFT JOIN clientes c ON c.agente_id = a.id
                 WHERE a.rol = 'asesor' AND COALESCE(a.activo,1) = 1
                 GROUP BY a.id
                 ORDER BY COUNT(c.id) ASC
                 LIMIT 1"
            );
            $agRow    = $stA->fetch(\PDO::FETCH_ASSOC);
            $agenteId = $agRow ? (int)$agRow['id'] : 0;

            if (!$agenteId) {
                error_log('[VidaSolicitudController] No hay agentes activos para asignar.');
                return [0, 0, true];
            }

            // Descomponer nombre completo (los formularios públicos usan un solo campo)
            $partes = explode(' ', trim($nombre), 3);
            $primerNombre    = $partes[0] ?? $nombre;
            $apellidoPaterno = $partes[1] ?? '';
            $apellidoMaterno = $partes[2] ?? '';

            $datos = [
                'nombre'           => $primerNombre,
                'apellido_paterno' => $apellidoPaterno,
                'apellido_materno' => $apellidoMaterno,
                'correo'           => $correo ?: null,
                'telefono'         => $telefono,
                'fecha_nacimiento' => $nacimiento ?: null,
                'codigo_postal'    => $cp ?: null,
                'ramo'             => $ramo,
                'agente_id'        => $agenteId,
                'estado_cliente'   => 'nuevo',
                'asegurado'        => 'no',
            ];

            if ($ramo === 'autos') {
                $modeloCompleto      = trim("{$submarca} {$modelo} {$version}");
                $datos['marca']         = $marca          ?: null;
                $datos['modelo']        = $modeloCompleto ?: null;
                $datos['anio_vehiculo'] = $anio           ? (int)$anio : null;
                $datos['tipo_vehiculo'] = $tipoVeh        ?: null;
                $datos['calle']         = $domicilio      ?: null;
            }

            if ($ramo === 'empresas') {
                $datos['giro_empresa']  = $giroEmp  ?: null;
                $datos['num_empleados'] = $empleados
                    ? (int)filter_var($empleados, FILTER_SANITIZE_NUMBER_INT)
                    : null;
            }

            $cols   = implode(', ', array_keys($datos));
            $places = implode(', ', array_fill(0, count($datos), '?'));
            $db->prepare("INSERT INTO clientes ({$cols}) VALUES ({$places})")->execute(array_values($datos));

            $clienteId = (int) $db->lastInsertId();

            return [$agenteId, $clienteId, true];

        } catch (\Throwable $e) {
            error_log('[VidaSolicitudController] Error guardando en CRM: ' . $e->getMessage());
            return [0, 0, true];
        }
    }

    // ── Notificación in-app al asesor asignado (tiempo casi real) ─────────────
    //
    // El navbar del CRM (public/js/notificaciones-navbar.js) consulta
    // /api/notificaciones cada 8 segundos, así que el asesor ve aparecer
    // este aviso —con sonido y badge— sin tener que recargar la página.

    private static function notificarAsesorAsignado(
        int $agenteId, int $clienteId, string $nombre, string $telefono,
        string $ramo, string $vehiculoCampana
    ): void {
        try {
            $ramoLabel = match ($ramo) {
                'gmm'      => 'Gastos Médicos Mayores',
                'empresas' => 'Seguros Empresariales',
                'autos'    => match ($vehiculoCampana) {
                    'moto'     => 'Seguro de Moto',
                    'flotilla' => 'Seguro de Flotilla',
                    default    => 'Seguro de Auto',
                },
                default    => 'Seguro de Vida',
            };

            $titulo  = '🆕 Nuevo cliente asignado — ' . $ramoLabel;
            $mensaje = trim($nombre) . ($telefono ? ' · ' . $telefono : '')
                     . '. Te fue asignado automáticamente, contáctalo cuanto antes.';

            (new NotificacionModel())->crear(
                $agenteId,
                'sistema',
                $titulo,
                $mensaje,
                $clienteId ? "cliente:{$clienteId}" : null
            );
        } catch (\Throwable $e) {
            error_log('[VidaSolicitudController] Error creando notificación in-app: ' . $e->getMessage());
        }
    }

    // ── Guardar en vida_solicitudes (log histórico) ───────────────────────────

    private static function guardarEnSolicitudes(
        string $nombre, string $correo, string $telefono, string $fechaPref,
        string $ramo, string $edadGmm, string $depGmm, string $comentarios,
        string $empresa, string $empleados, string $servicios,
        string $marca, string $modelo, string $anio, string $tipoVeh,
        string $hospitales = '', string $submarca = '', string $version = '',
        string $cp = '', string $domicilio = ''
    ): void {
        try {
            $db = Connection::getConnection();

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
                    submarca        VARCHAR(100)  DEFAULT NULL,
                    modelo          VARCHAR(100)  DEFAULT NULL,
                    version         VARCHAR(100)  DEFAULT NULL,
                    anio            VARCHAR(10)   DEFAULT NULL,
                    tipo_vehiculo   VARCHAR(50)   DEFAULT NULL,
                    domicilio       VARCHAR(255)  DEFAULT NULL,
                    cp              VARCHAR(10)   DEFAULT NULL,
                    hospitales      TEXT          DEFAULT NULL,
                    estado          ENUM('pendiente','contactado','cerrado') DEFAULT 'pendiente',
                    created_at      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");

            foreach (['submarca VARCHAR(100)', 'version VARCHAR(100)', 'domicilio VARCHAR(255)', 'cp VARCHAR(10)', 'hospitales TEXT'] as $col) {
                try { $db->exec("ALTER TABLE vida_solicitudes ADD COLUMN IF NOT EXISTS {$col} DEFAULT NULL"); }
                catch (\Throwable) {}
            }

            $db->prepare(
                "INSERT INTO vida_solicitudes
                    (nombre, correo, telefono, fecha_preferida, ramo, edad, dependientes,
                     comentarios, marca, modelo, version, anio, tipo_vehiculo,
                     domicilio, cp, hospitales, submarca)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
            )->execute([
                $nombre, $correo ?: null, $telefono, $fechaPref, $ramo,
                $edadGmm ?: null, $depGmm ?: 0, $comentarios ?: null,
                $marca ?: null, $modelo ?: null, $version ?: null,
                $anio ?: null, $tipoVeh ?: null, $domicilio ?: null, $cp ?: null,
                $hospitales ?: null, $submarca ?: null,
            ]);
        } catch (\Throwable $e) {
            error_log('[VidaSolicitudController] DB error vida_solicitudes: ' . $e->getMessage());
        }
    }

    // ── Cola de correos ───────────────────────────────────────────────────────

    private static function encolarCorreos(
        string $nombre, string $correo, string $telefono, string $fechaPref, string $ramo,
        string $edadGmm, string $depGmm, string $comentarios,
        string $empresa = '', string $giroEmp = '', string $empleados = '', string $servicios = '',
        string $marca = '', string $submarca = '', string $modelo = '',
        string $version = '', string $anio = '', string $tipoVeh = '',
        string $domicilio = '', string $cp = '', string $hospitales = '',
        string $urgencia = '', string $numUnidades = '', string $tipoFlota = '',
        string $nacimiento = ''
    ): void {
        try {
            $fechaFmt = self::formatFecha($fechaPref);

            $ramoLabel = match ($ramo) {
                'gmm'      => 'Gastos Médicos Mayores',
                'empresas' => 'Seguros Empresariales',
                'autos'    => 'Seguro Vehicular',
                default    => 'Seguro de Vida',
            };
            $ramoIcon = match ($ramo) { 'gmm' => '🏥', 'empresas' => '🏢', 'autos' => '🚗', default => '❤️' };
            $gradient = match ($ramo) { 'gmm' => '#0ea5e9,#0284c7', 'empresas' => '#7c3aed,#6d28d9', 'autos' => '#10b981,#059669', default => '#c9a227,#dbb84d' };

            $extraRows = '';

            if ($ramo === 'autos') {
                if ($tipoVeh)    $extraRows .= self::row('🚗 Tipo de vehículo', ucfirst($tipoVeh));
                if ($marca)      $extraRows .= self::row('🏷️ Marca',            htmlspecialchars($marca));
                if ($submarca)   $extraRows .= self::row('🏷️ Submarca',         htmlspecialchars($submarca));
                if ($modelo)     $extraRows .= self::row('🔖 Modelo',           htmlspecialchars($modelo));
                if ($version)    $extraRows .= self::row('📋 Versión',          htmlspecialchars($version));
                if ($anio)       $extraRows .= self::row('📅 Año',             htmlspecialchars($anio));
                if ($domicilio)  $extraRows .= self::row('🏠 Domicilio',        htmlspecialchars($domicilio));
                if ($cp)         $extraRows .= self::row('📍 Código Postal',    htmlspecialchars($cp));
                if ($numUnidades) $extraRows .= self::row('🚛 No. Unidades',    htmlspecialchars($numUnidades));
                if ($tipoFlota)  $extraRows .= self::row('🚛 Tipo de flota',    htmlspecialchars($tipoFlota));
                if ($nacimiento) $extraRows .= self::row('🎂 Fecha de nacimiento', htmlspecialchars($nacimiento));
            }

            if ($ramo === 'gmm') {
                if ($edadGmm)    $extraRows .= self::row('🎂 Edad',           htmlspecialchars($edadGmm) . ' años');
                if ($nacimiento) $extraRows .= self::row('📅 Nacimiento',     htmlspecialchars($nacimiento));
                if ($depGmm)     $extraRows .= self::row('👨‍👩‍👧 Dependientes', htmlspecialchars($depGmm));
                if ($hospitales) $extraRows .= self::row('🏥 Hospitales',     htmlspecialchars($hospitales));
                if ($urgencia)   $extraRows .= self::row('⚡ Urgencia',       $urgencia === 'urgente' ? '🔴 Urgente' : '🟢 Sin prisa');
            }

            if ($ramo === 'vida') {
                if ($nacimiento) $extraRows .= self::row('📅 Nacimiento',     htmlspecialchars($nacimiento));
                if ($depGmm)     $extraRows .= self::row('👨‍👩‍👧 Dependientes', htmlspecialchars($depGmm));
            }

            if ($ramo === 'empresas') {
                if ($empresa)   $extraRows .= self::row('🏢 Empresa',         htmlspecialchars($empresa));
                if ($giroEmp)   $extraRows .= self::row('🏭 Giro / Industria', htmlspecialchars($giroEmp));
                if ($empleados) $extraRows .= self::row('👥 Empleados',        htmlspecialchars($empleados));
                if ($servicios) $extraRows .= self::row('🧩 Seguro solicitado', htmlspecialchars($servicios));
            }

            if ($comentarios) $extraRows .= self::row('💬 Comentarios', htmlspecialchars($comentarios));

            $clienteCorreo = (filter_var($correo, FILTER_VALIDATE_EMAIL) && $correo !== self::ASESOR_EMAIL)
                ? $correo : null;

            $correoDisplay = $clienteCorreo ?? '(no proporcionado)';

            MailQueue::push(
                to:      self::ASESOR_EMAIL,
                toName:  self::EMPRESA_NOMBRE,
                subject: '[' . self::EMPRESA_NOMBRE . "] Nueva solicitud de {$ramoLabel} — {$nombre}",
                body:    self::buildEmailAsesor($ramoIcon, $ramoLabel, $gradient, $nombre, $correoDisplay, $telefono, $fechaFmt, $extraRows),
                replyTo: $clienteCorreo,
            );

            if ($clienteCorreo) {
                MailQueue::push(
                    to:      $clienteCorreo,
                    toName:  $nombre,
                    subject: '✅ Tu solicitud fue recibida — ' . self::EMPRESA_NOMBRE,
                    body:    self::buildEmailCliente($ramoIcon, $ramoLabel, $gradient, $nombre, $fechaFmt),
                );
            }

        } catch (\Throwable $e) {
            error_log('[VidaSolicitudController] Error al encolar correos: ' . $e->getMessage());
        }
    }

    // ── Helpers HTML ──────────────────────────────────────────────────────────

    private static function row(string $label, string $value): string
    {
        return "<tr>
            <td style='padding:10px 0;border-bottom:1px solid #eee;white-space:nowrap;padding-right:16px;'><strong>{$label}:</strong></td>
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
                    " . self::row('👤 Nombre',   htmlspecialchars($nombre))
                      . self::row('✉️ Correo',   htmlspecialchars($correo))
                      . self::row('📞 Teléfono', htmlspecialchars($telefono))
                      . self::row('📅 Contactar antes de', $fecha)
                      . $extraRows . "
                </table>
            </div>
        </body></html>";
    }

    private static function buildEmailCliente(
        string $icon, string $label, string $gradient, string $nombre, string $fecha
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

    private static function formatFecha(string $fecha): string
    {
        $meses = ['','enero','febrero','marzo','abril','mayo','junio',
                  'julio','agosto','septiembre','octubre','noviembre','diciembre'];
        [$y, $m, $d] = array_pad(explode('-', $fecha), 3, '1');
        return (int)$d . ' de ' . ($meses[(int)$m] ?? '') . ' de ' . $y;
    }

    private static function getConfig(): array
    {
        try {
            $rows = Connection::getConnection()->query("SELECT clave, valor FROM configuracion")->fetchAll(\PDO::FETCH_KEY_PAIR);
            return $rows ?: [];
        } catch (\Throwable) { return []; }
    }

    private static function waSendText(string $to, string $msg, string $pid, string $token): void
    {
        $to = preg_replace('/[^0-9]/', '', $to);
        $ch = curl_init("https://graph.facebook.com/v21.0/{$pid}/messages");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$token}", 'Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => json_encode(['messaging_product' => 'whatsapp', 'to' => $to, 'type' => 'text', 'text' => ['body' => $msg]]),
            CURLOPT_TIMEOUT        => 8,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
}
