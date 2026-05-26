<?php
declare(strict_types=1);

namespace App\Models;

class CotizacionModel extends BaseModel
{
    protected string $table = 'cotizaciones';

    protected array $defaultColumns = [
        'id', 'cliente_id', 'agente_id', 'compania', 'tipo_cobertura',
        'precio', 'deducible', 'vigencia', 'estado', 'fecha', 'folio', 'ramo',
    ];

    protected array $fillable = [
        'cliente_id'          => 'int',
        'agente_id'           => 'int',
        'compania'            => 'string',
        'tipo_cobertura'      => 'string',
        'precio'              => 'float',
        'deducible'           => 'float',
        'vigencia'            => 'string',
        'estado'              => 'string',
        'comentarios'         => 'string',
        'fecha'               => 'string',
        'folio'               => 'string',
        'ramo'                => 'string',
        'metodo_pago'         => 'string',
        'fecha_fin_vigencia'  => 'string',
    ];

    /**
     * Definición de "venta concretada" para operación/reportes:
     * - Estado explícito en "pagado", O
     * - Comprobante de pago subido (ya sea como documento en cotizacion_archivos
     *   con tipo_documento='comprobante_pago', o como registro en cotizacion_pagos con ruta).
     *
     * Esto permite que un coordinador vea la cotización aunque el asesor
     * aún no haya cambiado manualmente el estado, siempre que exista evidencia
     * de pago subida.
     */
    public static function ventaConcretadaSql(string $alias = 'co'): string
    {
        $a = $alias;
        return "("
            . "{$a}.estado = 'pagado'"
            . " OR EXISTS ("
            . "   SELECT 1 FROM cotizacion_archivos ca"
            . "   WHERE ca.cotizacion_id = {$a}.id"
            . "     AND ca.tipo_documento = 'comprobante_pago'"
            . "     AND ca.eliminado_en IS NULL"
            . " )"
            . " OR EXISTS ("
            . "   SELECT 1 FROM cotizacion_pagos cp"
            . "   WHERE cp.cotizacion_id = {$a}.id"
            . "     AND COALESCE(NULLIF(cp.ruta,''), NULLIF(cp.nombre_archivo,'')) IS NOT NULL"
            . " )"
            . ")";
    }

    /**
     * Si no hay PDF/imagen como archivo de cotización, devuelve metadatos alineados
     * con cotizacion_archivos usando el último registro en cotizacion_pagos con archivo.
     */
    public static function syntheticComprobantePagoDesdeTablaPagos(\PDO $db, int $cotizacionId): ?array
    {
        $st = $db->prepare(
            "SELECT nombre_original, nombre_archivo, ruta, creado_en
             FROM cotizacion_pagos
             WHERE cotizacion_id = ?
               AND COALESCE(NULLIF(ruta,''), NULLIF(nombre_archivo,'')) IS NOT NULL
             ORDER BY COALESCE(creado_en, id) DESC
             LIMIT 1"
        );
        $st->execute([$cotizacionId]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $orig = trim((string) ($row['nombre_original'] ?: ''))
            ?: (string) ($row['nombre_archivo'] ?: 'Comprobante de pago');

        $rutaRaw = trim(str_replace('\\', '/', (string) ($row['ruta'] ?? '')));
        $archivoRelative = '';
        if ($rutaRaw !== '') {
            $archivoRelative = (string) preg_replace('#^\/?uploads\/?#i', '', $rutaRaw);
        }
        $nomArc = trim((string) ($row['nombre_archivo'] ?? ''));
        if ($archivoRelative === '' && $nomArc !== '') {
            // Compatibilidad: archivos nuevos guardan como cotizaciones/{id}/{archivo}
            $archivoRelative = 'cotizaciones/' . $cotizacionId . '/' . $nomArc;
        }

        return [
            'id'                => 0,
            'cotizacion_id'     => $cotizacionId,
            'tipo_documento'    => 'comprobante_pago',
            'nombre_original'   => $orig,
            'nombre_archivo'    => $nomArc,
            'subido_en'         => $row['creado_en'] ?? null,
            'idrive_subido'     => null,
            'idrive_borrado'    => null,
            'archivo_relative'  => $archivoRelative,
        ];
    }

    public static function docsListaTieneComprobantePago(array $docs): bool
    {
        foreach ($docs as $d) {
            if (($d['tipo_documento'] ?? '') === 'comprobante_pago') {
                return true;
            }
        }
        return false;
    }

    // ── Definición de campos extra por ramo ───────────────────────────────────
    // Devuelve la configuración que el frontend necesita para renderizar
    // el formulario de cotización con campos dinámicos según el ramo activo.

    public static function camposPorRamo(): array
    {
        return [
            'autos' => [
                ['key' => 'tipo_vehiculo', 'label' => 'Tipo de vehículo', 'type' => 'select',
                 'options' => ['auto'=>'Auto','suv_camioneta'=>'SUV / Camioneta',
                               'pickup'=>'Pickup','moto'=>'Moto',
                               'deportivo'=>'Deportivo','electrico'=>'Eléctrico','van'=>'Van']],
                ['key' => 'marca',   'label' => 'Marca',   'type' => 'text'],
                ['key' => 'modelo',  'label' => 'Modelo',  'type' => 'text'],
                ['key' => 'anio',    'label' => 'Año',     'type' => 'number'],
                ['key' => 'uso',     'label' => 'Uso',     'type' => 'select',
                 'options' => ['particular'=>'Particular','comercial'=>'Comercial','uber_didi'=>'Uber / DiDi']],
                ['key' => 'quemacocos', 'label' => 'Quemacocos', 'type' => 'select',
                 'options' => ['si'=>'Sí','no'=>'No']],
                ['key' => 'rines_lujo', 'label' => 'Rines de lujo', 'type' => 'select',
                 'options' => ['si'=>'Sí','no'=>'No']],
                ['key' => 'gps',     'label' => 'GPS instalado', 'type' => 'select',
                 'options' => ['si'=>'Sí','no'=>'No','no_se'=>'No sé']],
            ],
            'vida' => [
                ['key' => 'edad_asegurado',  'label' => 'Edad del asegurado',  'type' => 'number'],
                ['key' => 'sexo',            'label' => 'Sexo',                'type' => 'select',
                 'options' => ['masculino'=>'Masculino','femenino'=>'Femenino']],
                ['key' => 'suma_asegurada',  'label' => 'Suma asegurada (MXN)','type' => 'number'],
                ['key' => 'plazo_anos',      'label' => 'Plazo (años)',         'type' => 'number'],
                ['key' => 'beneficiarios',   'label' => 'Beneficiarios',        'type' => 'text',
                 'placeholder' => 'Ej: Cónyuge 50%, Hijo 50%'],
            ],
            'gastos_medicos' => [
                ['key' => 'edad_titular',      'label' => 'Edad del titular',    'type' => 'number'],
                ['key' => 'cobertura_maternidad','label' => 'Cubre maternidad', 'type' => 'select',
                 'options' => ['si'=>'Sí','no'=>'No']],
                ['key' => 'nivel_hospitalario', 'label' => 'Nivel hospitalario', 'type' => 'select',
                 'options' => ['basico'=>'Básico','intermedio'=>'Intermedio','premium'=>'Premium']],
                ['key' => 'suma_asegurada',    'label' => 'Suma asegurada (MXN)', 'type' => 'number'],
                ['key' => 'deducible_tipo',    'label' => 'Tipo de deducible',   'type' => 'select',
                 'options' => ['fijo'=>'Fijo','porcentaje'=>'Porcentaje']],
                ['key' => 'coaseguro_pct',     'label' => 'Coaseguro (%)',        'type' => 'number'],
            ],
            'empresas' => [
                ['key' => 'giro',                'label' => 'Giro / Industria',       'type' => 'text'],
                ['key' => 'num_empleados',       'label' => 'Número de empleados',    'type' => 'number'],
                ['key' => 'facturacion_anual',   'label' => 'Facturación anual (MXN)','type' => 'number'],
                ['key' => 'tipo_cobertura_emp',  'label' => 'Tipo de cobertura',      'type' => 'select',
                 'options' => ['responsabilidad_civil'=>'Responsabilidad Civil',
                               'daños'=>'Daños a inmueble',
                               'vida_grupo'=>'Vida en grupo',
                               'gmm_colectivo'=>'GMM Colectivo']],
                ['key' => 'num_asegurados',      'label' => 'Personas a asegurar',    'type' => 'number'],
                ['key' => 'incluye_dependientes','label' => 'Incluye dependientes',   'type' => 'select',
                 'options' => ['si'=>'Sí','no'=>'No']],
            ],
        ];
    }

    /**
     * Coberturas disponibles por ramo, para el selector en formularios.
     */
    public static function coberturasPorRamo(): array
    {
        return [
            'autos'          => ['Básico','Amplio','Amplio Total','Premium'],
            'vida'           => ['Temporal','Entera','Dotal'],
            'gastos_medicos' => ['Individual','Familiar','Empresarial'],
            'empresas'       => ['Responsabilidad Civil','Daños','Vida en Grupo','GMM Colectivo'],
        ];
    }

    /**
     * Compañías disponibles por ramo.
     */
    public static function companiasPorRamo(): array
    {
        return [
            'autos'          => ['GNP','Qualitas','Chubb','Inbursa'],
            'vida'           => ['GNP','MetLife','Seguros Monterrey','Banamex Seguros','BBVA Seguros','Inbursa'],
            'gastos_medicos' => ['GNP','AXA','Qualitas','Mapfre','Chubb','BUPA','Allianz'],
            'empresas'       => ['GNP','AXA','Mapfre','Chubb','HDI','Zurich','Allianz'],
        ];
    }

    // ── Lectura ───────────────────────────────────────────────────────────────

    public function getByAgenteId(int $agenteId): array
    {
        $stmt = $this->db()->prepare('SELECT * FROM cotizaciones WHERE agente_id = ? ORDER BY fecha_creacion DESC');
        $stmt->execute([$agenteId]);
        return $stmt->fetchAll();
    }

    public function getByClienteId(int $clienteId): array
    {
        $stmt = $this->db()->prepare('SELECT * FROM cotizaciones WHERE cliente_id = ? ORDER BY fecha_creacion DESC');
        $stmt->execute([$clienteId]);
        return $stmt->fetchAll();
    }

    public function getByIdYAgente(int $id, int $agenteId): ?array
    {
        $stmt = $this->db()->prepare('SELECT * FROM cotizaciones WHERE id = ? AND agente_id = ?');
        $stmt->execute([$id, $agenteId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Lista completa con JOIN a cliente.
     * Filtra por ramo si se proporciona — cuando $ramo es null muestra todas.
     * Incluye detalles extra de cotizacion_detalles si existen.
     */
    public function getAllConCliente(int $agenteId, ?string $ramo = null): array
    {
        $ramoFilter = $ramo ? 'AND co.ramo = ?' : '';
        $stmt = $this->db()->prepare(
            "SELECT co.id, co.cliente_id,
                    CONCAT(cl.nombre,' ',cl.apellido_paterno,' ',cl.apellido_materno) AS cliente_nombre,
                    cl.correo AS cliente_correo,
                    CASE co.ramo
                        WHEN 'autos' THEN CONCAT(
                            UPPER(SUBSTRING(COALESCE(cl.marca,''),1,1)),
                            LOWER(SUBSTRING(COALESCE(cl.marca,''),2)),
                            ' ', COALESCE(cl.modelo,''),
                            ' ', COALESCE(cl.anio_vehiculo,'')
                        )
                        WHEN 'empresas' THEN COALESCE(cl.giro_empresa,'Empresa')
                        ELSE ''
                    END AS vehiculo,
                    co.compania, co.tipo_cobertura, co.precio, co.deducible, co.vigencia,
                    co.estado, co.fecha, co.fecha_creacion,
                    COALESCE(co.comentarios,'')   AS comentarios,
                    COALESCE(co.folio,'')          AS folio,
                    COALESCE(co.ramo,'autos')      AS ramo,
                    COALESCE(co.emision_estado,'pendiente') AS emision_estado,
                    COALESCE(
                        co.fecha_fin_vigencia,
                        CASE co.vigencia
                            WHEN '1 año'      THEN DATE_ADD(co.fecha, INTERVAL 1 YEAR)
                            WHEN '2 años'     THEN DATE_ADD(co.fecha, INTERVAL 2 YEAR)
                            WHEN 'Anual'      THEN DATE_ADD(co.fecha, INTERVAL 1 YEAR)
                            WHEN '1 mes'      THEN DATE_ADD(co.fecha, INTERVAL 1 MONTH)
                            WHEN 'Mensual'    THEN DATE_ADD(co.fecha, INTERVAL 1 MONTH)
                            WHEN '6 meses'    THEN DATE_ADD(co.fecha, INTERVAL 6 MONTH)
                            WHEN 'Semestral'  THEN DATE_ADD(co.fecha, INTERVAL 6 MONTH)
                            WHEN '3 meses'    THEN DATE_ADD(co.fecha, INTERVAL 3 MONTH)
                            WHEN 'Trimestral' THEN DATE_ADD(co.fecha, INTERVAL 3 MONTH)
                            ELSE NULL
                        END
                    ) AS fecha_fin_vigencia,
                    cd.detalles AS detalles_ramo
             FROM cotizaciones co
             JOIN clientes cl ON cl.id = co.cliente_id
             LEFT JOIN cotizacion_detalles cd ON cd.cotizacion_id = co.id
             WHERE co.agente_id = ? {$ramoFilter}
             ORDER BY co.fecha DESC, co.id DESC"
        );
        $params = $ramo ? [$agenteId, $ramo] : [$agenteId];
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        // Decodificar JSON de detalles_ramo
        foreach ($rows as &$row) {
            $row['detalles_ramo'] = !empty($row['detalles_ramo'])
                ? json_decode($row['detalles_ramo'], true)
                : [];
        }
        unset($row);

        return $rows;
    }

    public function getByIdConCliente(int $id, int $agenteId): ?array
    {
        $stmt = $this->db()->prepare(
            "SELECT co.*,
                    CONCAT(cl.nombre,' ',cl.apellido_paterno,' ',cl.apellido_materno) AS cliente_nombre,
                    cl.correo AS cliente_correo, cl.marca, cl.modelo, cl.anio_vehiculo,
                    cl.giro_empresa, cl.num_empleados,
                    cd.detalles AS detalles_ramo
             FROM cotizaciones co
             JOIN clientes cl ON cl.id = co.cliente_id
             LEFT JOIN cotizacion_detalles cd ON cd.cotizacion_id = co.id
             WHERE co.id = ? AND co.agente_id = ?"
        );
        $stmt->execute([$id, $agenteId]);
        $row = $stmt->fetch();
        if (!$row) return null;
        $row['detalles_ramo'] = !empty($row['detalles_ramo'])
            ? json_decode($row['detalles_ramo'], true)
            : [];
        return $row;
    }

    // ── Detalles por ramo ─────────────────────────────────────────────────────

    /**
     * Guarda o actualiza los campos extra de una cotización por ramo.
     * $detalles es un array asociativo libre según el ramo.
     */
    public function guardarDetalles(int $cotizacionId, string $ramo, array $detalles): void
    {
        $json = json_encode($detalles, JSON_UNESCAPED_UNICODE);
        $this->db()->prepare(
            "INSERT INTO cotizacion_detalles (cotizacion_id, ramo, detalles)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE ramo = VALUES(ramo), detalles = VALUES(detalles)"
        )->execute([$cotizacionId, $ramo, $json]);
    }

    /**
     * Obtiene los detalles extra de una cotización.
     */
    public function getDetalles(int $cotizacionId): array
    {
        $stmt = $this->db()->prepare(
            'SELECT ramo, detalles FROM cotizacion_detalles WHERE cotizacion_id = ?'
        );
        $stmt->execute([$cotizacionId]);
        $row = $stmt->fetch();
        if (!$row) return [];
        return json_decode($row['detalles'], true) ?? [];
    }

    // ── Estadísticas ──────────────────────────────────────────────────────────

    public function getKpis(int $agenteId, ?string $ramo = null): array
    {
        $ramoFilter = $ramo ? 'AND ramo = ?' : '';
        $ventaSql   = self::ventaConcretadaSql('cotizaciones');
        $stmt = $this->db()->prepare(
            "SELECT COUNT(*) AS cotizaciones_total,
                    SUM(YEAR(fecha_creacion)=YEAR(NOW()) AND MONTH(fecha_creacion)=MONTH(NOW())) AS cotizaciones_mes,
                    SUM({$ventaSql}) AS ventas_total,
                    SUM({$ventaSql} AND YEAR(fecha_creacion)=YEAR(NOW()) AND MONTH(fecha_creacion)=MONTH(NOW())) AS ventas_mes,
                    SUM(CASE WHEN {$ventaSql} THEN precio ELSE 0 END) AS ingreso_total,
                    SUM(CASE WHEN {$ventaSql} AND YEAR(fecha_creacion)=YEAR(NOW()) AND MONTH(fecha_creacion)=MONTH(NOW()) THEN precio ELSE 0 END) AS ingreso_mes,
                    AVG(CASE WHEN {$ventaSql} THEN precio END) AS ticket_promedio
             FROM cotizaciones WHERE agente_id = ? {$ramoFilter}"
        );
        $params = $ramo ? [$agenteId, $ramo] : [$agenteId];
        $stmt->execute($params);
        $d  = $stmt->fetch();
        $ct = (int)($d['cotizaciones_total'] ?? 0);
        $vt = (int)($d['ventas_total']       ?? 0);

        return [
            'cotizaciones_total' => $ct,
            'cotizaciones_mes'   => (int)($d['cotizaciones_mes']  ?? 0),
            'ventas_total'       => $vt,
            'ventas_mes'         => (int)($d['ventas_mes']        ?? 0),
            'ingreso_total'      => (float)($d['ingreso_total']   ?? 0),
            'ingreso_mes'        => (float)($d['ingreso_mes']     ?? 0),
            'ticket_promedio'    => (float)($d['ticket_promedio'] ?? 0),
            'tasa_conversion'    => $ct > 0 ? round($vt / $ct * 100, 1) : 0,
        ];
    }

    public function countEnviadas(int $agenteId, ?string $ramo = null): int
    {
        $ramoFilter = $ramo ? 'AND ramo = ?' : '';
        $stmt = $this->db()->prepare(
            "SELECT COUNT(*) FROM cotizaciones
             WHERE agente_id = ? AND estado = 'enviada' {$ramoFilter}"
        );
        $stmt->execute($ramo ? [$agenteId, $ramo] : [$agenteId]);
        return (int) $stmt->fetchColumn();
    }

    public function contarPorRamo(int $agenteId): array
    {
        $stmt = $this->db()->prepare(
            "SELECT ramo, COUNT(*) AS cotizaciones, SUM(" . self::ventaConcretadaSql('cotizaciones') . ") AS ventas
             FROM cotizaciones WHERE agente_id = ? GROUP BY ramo"
        );
        $stmt->execute([$agenteId]);
        $ramos = [
            'autos'          => ['cotizaciones'=>0,'ventas'=>0],
            'vida'           => ['cotizaciones'=>0,'ventas'=>0],
            'gastos_medicos' => ['cotizaciones'=>0,'ventas'=>0],
            'empresas'       => ['cotizaciones'=>0,'ventas'=>0],
        ];
        foreach ($stmt->fetchAll() as $row) {
            if (isset($ramos[$row['ramo']])) {
                $ramos[$row['ramo']] = [
                    'cotizaciones' => (int)$row['cotizaciones'],
                    'ventas'       => (int)$row['ventas'],
                ];
            }
        }
        return $ramos;
    }

    public function getRecientsGlobal(int $limit = 20): array
    {
        $stmt = $this->db()->prepare(
            "SELECT co.id, co.folio, co.ramo, co.compania, co.tipo_cobertura,
                    co.precio, co.estado, co.fecha, co.vigencia,
                    COALESCE(
                        co.fecha_fin_vigencia,
                        CASE co.vigencia
                            WHEN '1 año'      THEN DATE_ADD(co.fecha, INTERVAL 1 YEAR)
                            WHEN '2 años'     THEN DATE_ADD(co.fecha, INTERVAL 2 YEAR)
                            WHEN 'Anual'      THEN DATE_ADD(co.fecha, INTERVAL 1 YEAR)
                            WHEN '1 mes'      THEN DATE_ADD(co.fecha, INTERVAL 1 MONTH)
                            WHEN 'Mensual'    THEN DATE_ADD(co.fecha, INTERVAL 1 MONTH)
                            WHEN '6 meses'    THEN DATE_ADD(co.fecha, INTERVAL 6 MONTH)
                            WHEN 'Semestral'  THEN DATE_ADD(co.fecha, INTERVAL 6 MONTH)
                            WHEN '3 meses'    THEN DATE_ADD(co.fecha, INTERVAL 3 MONTH)
                            WHEN 'Trimestral' THEN DATE_ADD(co.fecha, INTERVAL 3 MONTH)
                            ELSE NULL
                        END
                    ) AS fecha_fin_vigencia,
                    CONCAT(cl.nombre,' ',cl.apellido_paterno) AS cliente_nombre,
                    CONCAT(a.nombre,' ',a.apellido)           AS agente_nombre
             FROM cotizaciones co
             JOIN clientes cl ON cl.id = co.cliente_id
             JOIN agentes  a  ON a.id  = co.agente_id
             ORDER BY co.fecha DESC, co.id DESC LIMIT ?"
        );
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    // ── Escritura ─────────────────────────────────────────────────────────────

    /**
     * Crea una nueva cotización, genera su folio y opcionalmente guarda
     * los detalles por ramo en cotizacion_detalles.
     *
     * El campo `precio` es OPCIONAL: puede ser 0 o null cuando aún no se
     * tiene la prima definitiva — la BD admite NULL en esa columna.
     *
     * @param array $data         Campos principales de la cotización.
     * @param array $detallesRamo Campos extra del ramo (marca, modelo, edad…).
     * @return int ID de la cotización creada.
     */
    public function crear(array $data, array $detallesRamo = []): int
    {
        // Precio es opcional: normalizar null / '' / 0 → 0.0
        if (!isset($data['precio']) || $data['precio'] === '' || $data['precio'] === null) {
            $data['precio'] = 0.0;
        }
        $data['precio'] = (float) $data['precio'];

        // Deducible también opcional
        if (!isset($data['deducible']) || $data['deducible'] === '' || $data['deducible'] === null) {
            $data['deducible'] = 0.0;
        }
        $data['deducible'] = (float) $data['deducible'];

        // Valores por defecto
        $data['estado']  = $data['estado']  ?? 'pendiente';
        $data['fecha']   = $data['fecha']   ?? date('Y-m-d');
        $data['ramo']    = $data['ramo']    ?? 'autos';
        $data['vigencia'] = $data['vigencia'] ?? '';

        // Generar folio automáticamente
        $estadoF = $data['estado_folio'] ?? 'N';
        unset($data['estado_folio']); // no es columna de cotizaciones
        $data['folio'] = $this->generarFolio(
            (int)$data['agente_id'],
            (string)$data['ramo'],
            (string)$data['tipo_cobertura'],
            $estadoF
        );

        // Insertar usando el insert() heredado de BaseModel
        $cotizacionId = $this->insert($data);

        // Guardar detalles de ramo si se proporcionaron
        if (!empty($detallesRamo) && $cotizacionId) {
            $this->guardarDetalles($cotizacionId, (string)$data['ramo'], $detallesRamo);
        }

        return $cotizacionId;
    }

    /**
     * Actualiza estado de una cotización.
     */
    public function updateEstado(int $id, string $estado, string $comentarios = ''): bool
    {
        $stmt = $this->db()->prepare(
            "UPDATE cotizaciones SET estado = ?, comentarios = COALESCE(NULLIF(?,''), comentarios)
             WHERE id = ?"
        );
        return $stmt->execute([$estado, $comentarios, $id]);
    }

    public function updateMetodoPago(int $id, int $agenteId, string $metodo): bool
    {
        $stmt = $this->db()->prepare(
            'UPDATE cotizaciones SET metodo_pago = ? WHERE id = ? AND agente_id = ?'
        );
        return $stmt->execute([$metodo, $id, $agenteId]);
    }

    // ── Archivos ──────────────────────────────────────────────────────────────

    public function adjuntarArchivo(int $cotizacionId, int $agenteId, string $tipoDocumento, string $nombreOriginal, string $nombreArchivo, string $ruta): int
    {
        $this->db()->prepare(
            'INSERT INTO cotizacion_archivos
             (cotizacion_id, agente_id, tipo_documento, nombre_original, nombre_archivo, ruta)
             VALUES (?,?,?,?,?,?)'
        )->execute([$cotizacionId, $agenteId, $tipoDocumento, $nombreOriginal, $nombreArchivo, $ruta]);
        return (int) $this->db()->lastInsertId();
    }

    /**
     * Reemplaza el archivo existente de un tipo para una cotización:
     * - Soft-delete del registro anterior en BD.
     * - Inserta el nuevo registro.
     * - Devuelve la ruta física del archivo anterior (para borrarlo del disco).
     */
    public function reemplazarArchivo(int $cotizacionId, int $agenteId, string $tipoDocumento, string $nombreOriginal, string $nombreArchivo, string $ruta): array
    {
        $db = $this->db();

        // Obtener el archivo anterior activo del mismo tipo
        $stmt = $db->prepare(
            'SELECT id, ruta FROM cotizacion_archivos
             WHERE cotizacion_id = ? AND tipo_documento = ? AND eliminado_en IS NULL
             ORDER BY subido_en DESC LIMIT 1'
        );
        $stmt->execute([$cotizacionId, $tipoDocumento]);
        $anterior = $stmt->fetch() ?: null;

        // Soft-delete del registro anterior
        if ($anterior) {
            $db->prepare(
                'UPDATE cotizacion_archivos SET eliminado_en = NOW() WHERE id = ?'
            )->execute([$anterior['id']]);
        }

        // Insertar el nuevo
        $db->prepare(
            'INSERT INTO cotizacion_archivos
             (cotizacion_id, agente_id, tipo_documento, nombre_original, nombre_archivo, ruta)
             VALUES (?,?,?,?,?,?)'
        )->execute([$cotizacionId, $agenteId, $tipoDocumento, $nombreOriginal, $nombreArchivo, $ruta]);

        return [
            'nuevo_id'    => (int) $db->lastInsertId(),
            'ruta_previa' => $anterior['ruta'] ?? null,
        ];
    }

    public function getArchivos(int $cotizacionId): array
    {
        $stmt = $this->db()->prepare(
            "SELECT ca.*, CONCAT(a.nombre,' ',a.apellido) AS agente_nombre
             FROM cotizacion_archivos ca JOIN agentes a ON a.id = ca.agente_id
             WHERE ca.cotizacion_id = ? AND ca.eliminado_en IS NULL
             ORDER BY ca.subido_en DESC"
        );
        $stmt->execute([$cotizacionId]);
        return $stmt->fetchAll();
    }

    public function marcarIdriveSubido(int $archivoId): void
    {
        $this->db()->prepare('UPDATE cotizacion_archivos SET idrive_subido = NOW() WHERE id = ?')->execute([$archivoId]);
    }

    public function marcarBorradoLocal(int $archivoId): void
    {
        $this->db()->prepare('UPDATE cotizacion_archivos SET idrive_borrado = NOW() WHERE id = ?')->execute([$archivoId]);
    }

    // ── Reportes ──────────────────────────────────────────────────────────────

    public function getReporteExportable(?int $agenteId = null, ?string $fechaDesde = null, ?string $fechaHasta = null, ?string $estado = null, ?string $ramo = null): array
    {
        $where  = ['1=1'];
        $params = [];

        if ($agenteId)   { $where[] = 'co.agente_id = ?'; $params[] = $agenteId; }
        if ($fechaDesde) { $where[] = 'co.fecha >= ?';     $params[] = $fechaDesde; }
        if ($fechaHasta) { $where[] = 'co.fecha <= ?';     $params[] = $fechaHasta; }
        if ($estado)     { $where[] = 'co.estado = ?';     $params[] = $estado; }
        if ($ramo)       { $where[] = 'co.ramo = ?';       $params[] = $ramo; }

        $sql = "SELECT co.folio, co.fecha, co.estado, co.precio, co.tipo_cobertura,
                       co.compania, co.vigencia, co.deducible, co.fecha_fin_vigencia,
                       COALESCE(co.metodo_pago,'') AS metodo_pago,
                       CONCAT(cl.nombre,' ',cl.apellido_paterno,' ',COALESCE(cl.apellido_materno,'')) AS cliente,
                       cl.correo AS cliente_correo, cl.telefono AS cliente_telefono,
                       CONCAT(a.nombre,' ',a.apellido) AS asesor,
                       COALESCE(co.ramo,'autos') AS ramo
                FROM cotizaciones co
                JOIN clientes cl ON cl.id = co.cliente_id
                JOIN agentes  a  ON a.id  = co.agente_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY co.fecha DESC";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function contarPorCliente(int $clienteId): int
    {
        $stmt = $this->db()->prepare('SELECT COUNT(*) FROM cotizaciones WHERE cliente_id = ?');
        $stmt->execute([$clienteId]);
        return (int) $stmt->fetchColumn();
    }

    // ── Folio ─────────────────────────────────────────────────────────────────

    private function generarFolio(int $agenteId, string $ramo, string $cobertura, string $estado = 'N'): string
    {
        $ramoPrefix = match($ramo) {
            'autos'          => 'AUT',
            'vida'           => 'VID',
            'gastos_medicos' => 'GMX',
            'empresas'       => 'EMP',
            default          => 'AUT',
        };
        $cobPrefix = match($cobertura) {
            'Básica'                 => 'BAS', 'Limitada'    => 'LIM',
            'Amplia'                 => 'AMP', 'Amplia Plus' => 'APL',
            'Premium'                => 'PRM', 'Temporal'    => 'TEM',
            'Entera'                 => 'ENT', 'Dotal'       => 'DOT',
            'Individual'             => 'IND', 'Familiar'    => 'FAM',
            'Empresarial'            => 'EMP',
            'Responsabilidad Civil'  => 'RCI', 'Daños'       => 'DAN',
            'Vida en Grupo'          => 'VGP', 'GMM Colectivo' => 'GMC',
            default                  => 'GEN',
        };

        $anio      = date('y');
        $agenteCod = str_pad((string)$agenteId, 3, '0', STR_PAD_LEFT);

        // Usar MAX del consecutivo existente para este agente+año en lugar de COUNT
        // Evita colisiones cuando hay registros eliminados o intentos fallidos previos
        $stmtMax = $this->db()->prepare(
            "SELECT MAX(CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(folio, '-', 5), '-', -1) AS UNSIGNED))
             FROM cotizaciones
             WHERE agente_id = ? AND YEAR(fecha_creacion) = YEAR(NOW())
               AND folio REGEXP '^[A-Z]+-[A-Z]+-[0-9]+-[0-9]+-[0-9]+-[A-Z]+$'"
        );
        $stmtMax->execute([$agenteId]);
        $maxConsec   = (int)($stmtMax->fetchColumn() ?? 0);
        $consecutivo = $maxConsec + 1;

        // Generar folio y verificar unicidad, incrementando si ya existe
        $stmtCheck = $this->db()->prepare('SELECT COUNT(*) FROM cotizaciones WHERE folio = ?');
        do {
            $folio = "{$ramoPrefix}-{$cobPrefix}-{$anio}-{$agenteCod}-" .
                     str_pad((string)$consecutivo, 4, '0', STR_PAD_LEFT) . '-' . strtoupper($estado);
            $stmtCheck->execute([$folio]);
            if ((int)$stmtCheck->fetchColumn() === 0) break;
            $consecutivo++;
        } while ($consecutivo < 9999);

        return $folio;
    }

    public function generarFolioPublico(int $agenteId, string $ramo, string $cobertura, string $estado = 'N'): string
    {
        return $this->generarFolio($agenteId, $ramo, $cobertura, $estado);
    }

    public function getEvolucionMensual(int $agenteId, ?string $ramo = null): array
    {
        $ramoFiltroClientes = '';
        $ramoFiltroCot      = '';
        $params             = [$agenteId];

        if ($ramo) {
            $ramoFiltroClientes = " AND ramo = '{$ramo}'";
            $ramoFiltroCot      = " AND ramo = '{$ramo}'";
        }

        $stmt = $this->db()->prepare(
            "SELECT DATE_FORMAT(meses.mes_inicio,'%b') AS mes,
                    COALESCE(cl.total,0)    AS clientes,
                    COALESCE(co.total,0)    AS cotizaciones,
                    COALESCE(co.ventas,0)   AS ventas,
                    COALESCE(co.ingresos,0) AS ingresos
             FROM (
                 SELECT DATE_FORMAT(DATE_SUB(LAST_DAY(NOW()),INTERVAL n MONTH),'%Y-%m-01') AS mes_inicio
                 FROM (SELECT 0 n UNION SELECT 1 UNION SELECT 2
                       UNION SELECT 3 UNION SELECT 4 UNION SELECT 5) nums
             ) meses
             LEFT JOIN (
                 SELECT DATE_FORMAT(fecha_registro,'%Y-%m-01') AS m, COUNT(*) AS total
                 FROM clientes WHERE agente_id = ?{$ramoFiltroClientes} GROUP BY m
             ) cl ON cl.m = meses.mes_inicio
             LEFT JOIN (
                 SELECT DATE_FORMAT(fecha_creacion,'%Y-%m-01') AS m,
                        COUNT(*) AS total,
                        SUM(" . self::ventaConcretadaSql('cotizaciones') . ") AS ventas,
                        SUM(CASE WHEN " . self::ventaConcretadaSql('cotizaciones') . " THEN precio ELSE 0 END) AS ingresos
                 FROM cotizaciones WHERE agente_id = ?{$ramoFiltroCot} GROUP BY m
             ) co ON co.m = meses.mes_inicio
             ORDER BY meses.mes_inicio ASC"
        );
        $stmt->execute([$agenteId, $agenteId]);
        return array_map(fn($e) => [
            'mes'          => $e['mes'],
            'clientes'     => (int)   $e['clientes'],
            'cotizaciones' => (int)   $e['cotizaciones'],
            'ventas'       => (int)   $e['ventas'],
            'ingresos'     => (float) $e['ingresos'],
        ], $stmt->fetchAll());
    }

    // ── Métodos de compatibilidad / alias ─────────────────────────────────────

    /** Conteo de aceptadas+pagadas — usado por dash-index.php */
    public function countAceptadas(int $agenteId, ?string $ramo = null): int
    {
        $ramoFilter = $ramo ? 'AND ramo = ?' : '';
        $stmt = $this->db()->prepare(
            "SELECT COUNT(*) FROM cotizaciones
             WHERE agente_id = ? AND " . self::ventaConcretadaSql('cotizaciones') . " {$ramoFilter}"
        );
        $stmt->execute($ramo ? [$agenteId, $ramo] : [$agenteId]);
        return (int) $stmt->fetchColumn();
    }

    /** Alias de getEvolucionMensual — usado por dash-index.php */
    public function getCotizacionesVsVentasPorMes(int $agenteId, ?string $ramo = null): array
    {
        return $this->getEvolucionMensual($agenteId, $ramo);
    }

    /** Ranking compañías por ventas — usado por reportes/index.php */
    public function getRankingCompanias(int $agenteId, ?string $ramo = null, int $limit = 8): array
    {
        $ramoFilter = $ramo ? 'AND ramo = ?' : '';
        $stmt = $this->db()->prepare(
            "SELECT compania,
                    COUNT(*) AS cotizaciones,
                    SUM(" . self::ventaConcretadaSql('cotizaciones') . ") AS aceptadas,
                    SUM(CASE WHEN " . self::ventaConcretadaSql('cotizaciones') . " THEN precio ELSE 0 END) AS ingreso
             FROM cotizaciones
             WHERE agente_id = ? {$ramoFilter}
             GROUP BY compania
             ORDER BY aceptadas DESC, ingreso DESC
             LIMIT {$limit}"
        );
        $stmt->execute($ramo ? [$agenteId, $ramo] : [$agenteId]);
        return array_map(fn($r) => [
            'compania'     => $r['compania'],
            'cotizaciones' => (int)   $r['cotizaciones'],
            'aceptadas'    => (int)   $r['aceptadas'],
            'ingreso'      => (float) $r['ingreso'],
        ], $stmt->fetchAll());
    }

    /** Coberturas más vendidas — usado por reportes/index.php */
    public function getPorCobertura(int $agenteId, ?string $ramo = null, int $limit = 8): array
    {
        $ramoFilter = $ramo ? 'AND ramo = ?' : '';
        $stmt = $this->db()->prepare(
            "SELECT tipo_cobertura AS tipo,
                    COUNT(*) AS total,
                    SUM(" . self::ventaConcretadaSql('cotizaciones') . ") AS ventas,
                    ramo
             FROM cotizaciones
             WHERE agente_id = ? {$ramoFilter}
             GROUP BY tipo_cobertura, ramo
             ORDER BY ventas DESC, total DESC
             LIMIT {$limit}"
        );
        $stmt->execute($ramo ? [$agenteId, $ramo] : [$agenteId]);
        return array_map(fn($r) => [
            'tipo'   => $r['tipo'],
            'total'  => (int) $r['total'],
            'ventas' => (int) $r['ventas'],
            'ramo'   => $r['ramo'],
        ], $stmt->fetchAll());
    }

    /** Top clientes por valor de ventas cerradas — usado por reportes/index.php */
    public function getTopClientes(int $agenteId, ?string $ramo = null, int $limit = 5): array
    {
        $ramoFilter = $ramo ? 'AND co.ramo = ?' : '';
        $stmt = $this->db()->prepare(
            "SELECT CONCAT(cl.nombre,' ',cl.apellido_paterno) AS nombre,
                    COUNT(*) AS ventas,
                    SUM(co.precio) AS valor,
                    cl.ciudad
             FROM cotizaciones co
             JOIN clientes cl ON cl.id = co.cliente_id
             WHERE co.agente_id = ?
               AND " . self::ventaConcretadaSql('co') . " {$ramoFilter}
             GROUP BY co.cliente_id, cl.nombre, cl.apellido_paterno, cl.ciudad
             ORDER BY valor DESC
             LIMIT {$limit}"
        );
        $stmt->execute($ramo ? [$agenteId, $ramo] : [$agenteId]);
        return array_map(fn($r) => [
            'nombre' => $r['nombre'],
            'ventas' => (int)   $r['ventas'],
            'valor'  => (float) $r['valor'],
            'ciudad' => $r['ciudad'] ?? '',
        ], $stmt->fetchAll());
    }

    /**
     * Elimina una cotización y sus registros vinculados.
     * Hace DELETE físico para que desaparezca de todas las vistas sin
     * necesidad de agregar filtros eliminado_en en cada query existente.
     */
    public function eliminar(int $id, int $agenteId): bool
    {
        $db = $this->db();

        // Verificar existencia (el coordinador puede borrar cualquier cotización, 
        // el asesor solo las suyas; si agente_id = 0 se omite el filtro)
        $checkSql = $agenteId > 0
            ? 'SELECT agente_id FROM cotizaciones WHERE id = ? AND agente_id = ?'
            : 'SELECT agente_id FROM cotizaciones WHERE id = ?';
        $checkParams = $agenteId > 0 ? [$id, $agenteId] : [$id];
        $st = $db->prepare($checkSql);
        $st->execute($checkParams);
        if (!$st->fetch()) {
            return false;
        }

        // Soft-delete de archivos vinculados (para no perder evidencia documental)
        try {
            $db->prepare('UPDATE cotizacion_archivos SET eliminado_en = NOW() WHERE cotizacion_id = ? AND eliminado_en IS NULL')
               ->execute([$id]);
        } catch (\Throwable $e) {}

        // Borrar registros relacionados sin soft-delete
        foreach (['cotizacion_detalles', 'cotizacion_pagos'] as $tabla) {
            try { $db->prepare("DELETE FROM {$tabla} WHERE cotizacion_id = ?")->execute([$id]); } catch (\Throwable $e) {}
        }

        // Borrar la cotización (sin filtro de agente para que coordinador pueda borrar cualquiera)
        $stmt = $db->prepare('DELETE FROM cotizaciones WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    /** Cotizaciones próximas a vencer */
    public function getPorVencer(int $agenteId, int $dias = 30): array
    {
        $stmt = $this->db()->prepare(
            "SELECT co.id, co.folio, co.ramo, co.compania, co.estado,
                    co.fecha_fin_vigencia,
                    DATEDIFF(co.fecha_fin_vigencia, CURDATE()) AS dias_restantes,
                    CONCAT(cl.nombre,' ',cl.apellido_paterno) AS cliente_nombre,
                    cl.telefono AS cliente_telefono
             FROM cotizaciones co
             JOIN clientes cl ON cl.id = co.cliente_id
             WHERE co.agente_id = ?
               AND co.fecha_fin_vigencia IS NOT NULL
               AND " . self::ventaConcretadaSql('co') . "
               AND DATEDIFF(co.fecha_fin_vigencia, CURDATE()) BETWEEN 0 AND ?
             ORDER BY co.fecha_fin_vigencia ASC"
        );
        $stmt->execute([$agenteId, $dias]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ▶ NUEVO PARCHE I4: Forzar estado de cotización sin eliminar
    public function forzarEstado(int $id, string $estado, string $emisionEstado): bool {
        $ok = ['pendiente','en_proceso','enviado','rechazado','aceptada','pagado'];
        if (!in_array($emisionEstado, $ok, true)) return false;
        return $this->db()->prepare(
            'UPDATE cotizaciones SET estado=?,emision_estado=? WHERE id=?'
        )->execute([$estado, $emisionEstado, $id]);
    }
}