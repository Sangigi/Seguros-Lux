<?php
declare(strict_types=1);

namespace App\Models;

use App\Services\QueryCache;

class ClientModel extends BaseModel
{
    protected string $table = 'clientes';

    protected array $defaultColumns = [
        'id', 'nombre', 'apellido_paterno', 'apellido_materno',
        'correo', 'telefono', 'marca', 'modelo', 'anio_vehiculo',
        'tipo_vehiculo', 'ciudad', 'estado_cliente', 'asegurado',
        'fecha_registro', 'agente_id', 'ramo',
    ];

    protected array $fillable = [
        'nombre'            => 'string',
        'apellido_paterno'  => 'string',
        'apellido_materno'  => 'string',
        'fecha_nacimiento'  => 'string',
        'correo'            => 'string',
        'telefono'          => 'string',
        'sexo'              => 'string',
        'estado_civil'      => 'string',
        'calle'             => 'string',
        'colonia'           => 'string',
        'ciudad'            => 'string',
        'estado'            => 'string',
        'codigo_postal'     => 'string',
        // Autos
        'marca'             => 'string',
        'modelo'            => 'string',
        'anio_vehiculo'     => 'int',
        'tipo_vehiculo'     => 'string',
        'recien_comprado'   => 'string',
        'asegurado'         => 'string',
        'tiempo_compra'     => 'string',
        // Vida / GMM
        'fecha_cita'        => 'string',
        'hora_cita'         => 'string',
        // Empresas
        'giro_empresa'      => 'string',
        'num_empleados'     => 'int',
        'facturacion_anual' => 'float',
        // General
        'ramo'              => 'string',
        'agente_id'         => 'int',
        'estado_cliente'    => 'string',
        'telegram_chat_id'  => 'string',
    ];

    // ── Definición de campos extra por ramo ───────────────────────────────────
    // Usados por el frontend para mostrar campos dinámicos en formularios.

    public static function camposPorRamo(): array
    {
        return [
            'autos' => [
                ['key' => 'marca',          'label' => 'Marca',           'type' => 'text'],
                ['key' => 'modelo',         'label' => 'Modelo',          'type' => 'text'],
                ['key' => 'anio_vehiculo',  'label' => 'Año',             'type' => 'number'],
                ['key' => 'tipo_vehiculo',  'label' => 'Tipo de vehículo','type' => 'select',
                 'options' => ['auto','suv_camioneta','pickup','moto','deportivo','electrico','van']],
                ['key' => 'asegurado',      'label' => '¿Ya tiene seguro?','type' => 'select',
                 'options' => ['si','no']],
            ],
            'vida' => [
                ['key' => 'fecha_nacimiento','label' => 'Fecha de nacimiento', 'type' => 'date'],
                ['key' => 'sexo',            'label' => 'Sexo',                'type' => 'select',
                 'options' => ['masculino','femenino']],
                ['key' => 'asegurado',       'label' => '¿Tiene seguro de vida?', 'type' => 'select',
                 'options' => ['si','no']],
            ],
            'gastos_medicos' => [
                ['key' => 'fecha_nacimiento','label' => 'Fecha de nacimiento', 'type' => 'date'],
                ['key' => 'sexo',            'label' => 'Sexo',                'type' => 'select',
                 'options' => ['masculino','femenino']],
                ['key' => 'asegurado',       'label' => '¿Tiene GMM?',         'type' => 'select',
                 'options' => ['si','no']],
            ],
            'empresas' => [
                ['key' => 'giro_empresa',      'label' => 'Giro / Industria',      'type' => 'text'],
                ['key' => 'num_empleados',     'label' => 'Número de empleados',   'type' => 'number'],
                ['key' => 'facturacion_anual', 'label' => 'Facturación anual (MXN)','type' => 'number'],
                ['key' => 'asegurado',         'label' => '¿Tiene seguro empresarial?', 'type' => 'select',
                 'options' => ['si','no']],
            ],
        ];
    }

    // ── Lectura ───────────────────────────────────────────────────────────────

    public function findByCorreo(string $correo): ?array
    {
        $stmt = $this->db()->prepare(
            'SELECT id, nombre, apellido_paterno, apellido_materno,
                    correo, telefono, agente_id, telegram_chat_id, ramo
             FROM clientes WHERE correo = ? ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$correo]);
        return $stmt->fetch() ?: null;
    }

    public function getByAgente(int $agenteId): array
    {
        $stmt = $this->db()->prepare(
            'SELECT * FROM clientes WHERE agente_id = ? ORDER BY fecha_registro DESC'
        );
        $stmt->execute([$agenteId]);
        return $stmt->fetchAll();
    }

    /**
     * Clientes filtrados por ramo activo.
     * Si $ramo es null devuelve todos (igual que getByAgente).
     */
    public function getByAgenteYRamo(int $agenteId, ?string $ramo = null): array
    {
        if ($ramo === null) {
            return $this->getByAgente($agenteId);
        }
        $stmt = $this->db()->prepare(
            'SELECT * FROM clientes WHERE agente_id = ? AND ramo = ? ORDER BY fecha_registro DESC'
        );
        $stmt->execute([$agenteId, $ramo]);
        return $stmt->fetchAll();
    }

    public function getCompleto(int $id, int $agenteId, bool $esCoordinador = false): ?array
    {
        if ($esCoordinador) {
            // El coordinador puede ver cualquier cliente, solo filtramos por ID
            $stmt = $this->db()->prepare('SELECT * FROM clientes WHERE id = ?');
            $stmt->execute([$id]);
        } else {
            // El asesor normal solo ve sus propios clientes
            $stmt = $this->db()->prepare('SELECT * FROM clientes WHERE id = ? AND agente_id = ?');
            $stmt->execute([$id, $agenteId]);
        }
        return $stmt->fetch() ?: null;
    }

    public function getSeleccionado(int $id, int $agenteId): ?array
    {
        $stmt = $this->db()->prepare(
            'SELECT id, nombre, apellido_paterno, apellido_materno,
                    marca, modelo, anio_vehiculo, tipo_vehiculo,
                    correo, telefono, ramo,
                    giro_empresa, num_empleados, facturacion_anual
             FROM clientes WHERE id = ? AND agente_id = ?'
        );
        $stmt->execute([$id, $agenteId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Para selector de clientes en formularios.
     * Filtra por ramo si se proporciona, para que el agente solo vea
     * clientes del ramo activo al crear una cita o cotización.
     */
    public function getParaSelector(int $agenteId, ?string $ramo = null): array
    {
        if ($ramo) {
            $stmt = $this->db()->prepare(
                'SELECT id, nombre, apellido_paterno, apellido_materno,
                        marca, modelo, anio_vehiculo, correo, ramo
                 FROM clientes WHERE agente_id = ? AND ramo = ? ORDER BY nombre ASC'
            );
            $stmt->execute([$agenteId, $ramo]);
        } else {
            $stmt = $this->db()->prepare(
                'SELECT id, nombre, apellido_paterno, apellido_materno,
                        marca, modelo, anio_vehiculo, correo, ramo
                 FROM clientes WHERE agente_id = ? ORDER BY nombre ASC'
            );
            $stmt->execute([$agenteId]);
        }
        return $stmt->fetchAll();
    }

    public function perteneceAAgente(int $clienteId, int $agenteId): bool
    {
        $stmt = $this->db()->prepare('SELECT id FROM clientes WHERE id = ? AND agente_id = ?');
        $stmt->execute([$clienteId, $agenteId]);
        return (bool) $stmt->fetch();
    }

    // ── Contadores ────────────────────────────────────────────────────────────

    public function countTotal(int $agenteId, ?string $ramo = null): int
    {
        $ramoFilter = $ramo ? ' AND ramo = ?' : '';
        $stmt = $this->db()->prepare("SELECT COUNT(*) FROM clientes WHERE agente_id = ?{$ramoFilter}");
        $stmt->execute($ramo ? [$agenteId, $ramo] : [$agenteId]);
        return (int) $stmt->fetchColumn();
    }

    public function countHoy(int $agenteId, ?string $ramo = null): int
    {
        return (int) QueryCache::remember(
            table: 'clientes',
            key:   "countHoy:{$agenteId}:{$ramo}",
            ttl:   90,
            fn:    function () use ($agenteId, $ramo): int {
                $ramoFilter = $ramo ? ' AND ramo = ?' : '';
                $stmt = $this->db()->prepare(
                    "SELECT COUNT(*) FROM clientes WHERE agente_id = ? AND DATE(fecha_registro) = CURDATE(){$ramoFilter}"
                );
                $stmt->execute($ramo ? [$agenteId, $ramo] : [$agenteId]);
                return (int) $stmt->fetchColumn();
            }
        );
    }

    public function countEstaSemana(int $agenteId, ?string $ramo = null): int
    {
        return (int) QueryCache::remember(
            table: 'clientes',
            key:   "countEstaSemana:{$agenteId}:{$ramo}",
            ttl:   90,
            fn:    function () use ($agenteId, $ramo): int {
                $ramoFilter = $ramo ? ' AND ramo = ?' : '';
                $stmt = $this->db()->prepare(
                    "SELECT COUNT(*) FROM clientes
                     WHERE agente_id = ? AND YEARWEEK(fecha_registro,1) = YEARWEEK(CURDATE(),1){$ramoFilter}"
                );
                $stmt->execute($ramo ? [$agenteId, $ramo] : [$agenteId]);
                return (int) $stmt->fetchColumn();
            }
        );
    }

    public function countEsteMes(int $agenteId, ?string $ramo = null): int
    {
        return (int) QueryCache::remember(
            table: 'clientes',
            key:   "countEsteMes:{$agenteId}:{$ramo}",
            ttl:   120,
            fn:    function () use ($agenteId, $ramo): int {
                $ramoFilter = $ramo ? ' AND ramo = ?' : '';
                $stmt = $this->db()->prepare(
                    "SELECT COUNT(*) FROM clientes
                     WHERE agente_id = ? AND YEAR(fecha_registro)=YEAR(NOW()) AND MONTH(fecha_registro)=MONTH(NOW()){$ramoFilter}"
                );
                $stmt->execute($ramo ? [$agenteId, $ramo] : [$agenteId]);
                return (int) $stmt->fetchColumn();
            }
        );
    }

    /**
     * Conteo de clientes por ramo para el agente.
     */
    public function countPorRamo(int $agenteId): array
    {
        $stmt = $this->db()->prepare(
            "SELECT COALESCE(ramo,'autos') AS ramo, COUNT(*) AS total
             FROM clientes WHERE agente_id = ? GROUP BY ramo"
        );
        $stmt->execute([$agenteId]);
        $defaults = ['autos'=>0,'vida'=>0,'gastos_medicos'=>0,'empresas'=>0];
        foreach ($stmt->fetchAll() as $row) {
            $defaults[$row['ramo']] = (int)$row['total'];
        }
        return $defaults;
    }

    // ── Gráficas ──────────────────────────────────────────────────────────────

    public function getClientesPorDia(int $agenteId, ?string $ramo = null): array
    {
        return QueryCache::remember(
            table: 'clientes',
            key:   "clientesPorDia:{$agenteId}:{$ramo}",
            ttl:   90,
            fn:    function () use ($agenteId, $ramo): array {
                $ramoFilter = $ramo ? ' AND ramo = ?' : '';
                $stmt = $this->db()->prepare(
                    "SELECT CASE DAYOFWEEK(fecha_registro)
                                WHEN 1 THEN 'Dom' WHEN 2 THEN 'Lun' WHEN 3 THEN 'Mar'
                                WHEN 4 THEN 'Mié' WHEN 5 THEN 'Jue' WHEN 6 THEN 'Vie'
                                WHEN 7 THEN 'Sáb'
                            END AS dia,
                            COUNT(*) AS total
                     FROM clientes
                     WHERE agente_id = ? AND fecha_registro >= DATE_SUB(CURDATE(), INTERVAL 6 DAY){$ramoFilter}
                     GROUP BY DATE(fecha_registro), dia
                     ORDER BY DATE(fecha_registro) ASC"
                );
                $stmt->execute($ramo ? [$agenteId, $ramo] : [$agenteId]);
                $raw = $stmt->fetchAll();

                $diasSemana = ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'];
                $diasMap    = array_column($raw, 'total', 'dia');

                return array_map(
                    fn($d) => ['dia' => $d, 'total' => (int)($diasMap[$d] ?? 0)],
                    $diasSemana
                );
            }
        );
    }

    public function getTopVehiculos(?int $agenteId = null, int $limit = 8): array
    {
        return QueryCache::remember(
            table: 'clientes',
            key:   "topVehiculos:{$agenteId}:{$limit}",
            ttl:   120,
            fn:    function () use ($agenteId, $limit): array {
                $where = $agenteId !== null ? "WHERE agente_id = ? AND ramo = 'autos'" : "WHERE ramo = 'autos'";
                $sql = "SELECT tipo, SUM(cnt) AS total
                        FROM (
                            SELECT
                                CASE LOWER(TRIM(tipo_vehiculo))
                                    WHEN 'auto'           THEN 'Auto'
                                    WHEN 'autos'          THEN 'Auto'
                                    WHEN 'moto'           THEN 'Moto'
                                    WHEN 'motos'          THEN 'Moto'
                                    WHEN 'camioneta'      THEN 'SUV / Camioneta'
                                    WHEN 'suv'            THEN 'SUV / Camioneta'
                                    WHEN 'suv_camioneta'  THEN 'SUV / Camioneta'
                                    WHEN 'pickup'         THEN 'Pickup'
                                    WHEN 'deportivo'      THEN 'Deportivo'
                                    WHEN 'electrico'      THEN 'Eléctrico'
                                    WHEN 'van'            THEN 'Van / Minivan'
                                    ELSE CONCAT(UPPER(SUBSTRING(tipo_vehiculo,1,1)),LOWER(SUBSTRING(tipo_vehiculo,2)))
                                END AS tipo,
                                COUNT(*) AS cnt
                            FROM clientes {$where}
                            GROUP BY tipo_vehiculo
                        ) t
                        GROUP BY tipo ORDER BY total DESC LIMIT ?";

                $params = $agenteId !== null ? [$agenteId, $limit] : [$limit];
                $stmt = $this->db()->prepare($sql);
                $stmt->execute($params);
                return $stmt->fetchAll() ?: [['tipo' => 'Sin datos', 'total' => 1]];
            }
        );
    }

    public function getEstadoAsegurado(int $agenteId, ?string $ramo = null): array
    {
        return QueryCache::remember(
            table: 'clientes',
            key:   "estadoAsegurado:{$agenteId}:{$ramo}",
            ttl:   90,
            fn:    function () use ($agenteId, $ramo): array {
                $ramoFilter = $ramo ? ' AND ramo = ?' : '';
                $stmt = $this->db()->prepare(
                    "SELECT SUM(asegurado='si') AS asegurados,
                            SUM(asegurado='no') AS no_asegurados
                     FROM clientes WHERE agente_id = ?{$ramoFilter}"
                );
                $stmt->execute($ramo ? [$agenteId, $ramo] : [$agenteId]);
                $d = $stmt->fetch();
                return [
                    'asegurados'    => (int)($d['asegurados']    ?? 0),
                    'no_asegurados' => (int)($d['no_asegurados'] ?? 0),
                ];
            }
        );
    }

    // ── Notas ─────────────────────────────────────────────────────────────────

    public function getNotas(int $clienteId): array
    {
        $stmt = $this->db()->prepare(
            "SELECT n.texto, n.fecha_creacion AS fecha,
                    CONCAT(a.nombre,' ',a.apellido) AS agente_nombre
             FROM notas n
             LEFT JOIN agentes a ON a.id = n.agente_id
             WHERE n.cliente_id = ? ORDER BY n.fecha_creacion DESC"
        );
        $stmt->execute([$clienteId]);
        return $stmt->fetchAll();
    }

    public function addNota(int $clienteId, int $agenteId, string $texto): void
    {
        $this->db()->prepare(
            'INSERT INTO notas (cliente_id, agente_id, texto) VALUES (?,?,?)'
        )->execute([$clienteId, $agenteId, $texto]);
    }

    // ── Estados ───────────────────────────────────────────────────────────────

    public function updateEstado(int $id, int $agenteId, string $estado, bool $esCoordinador = false): bool
    {
        if ($esCoordinador) {
            $stmt = $this->db()->prepare('UPDATE clientes SET estado_cliente = ? WHERE id = ?');
            $stmt->execute([$estado, $id]);
        } else {
            $stmt = $this->db()->prepare('UPDATE clientes SET estado_cliente = ? WHERE id = ? AND agente_id = ?');
            $stmt->execute([$estado, $id, $agenteId]);
        }
        return $stmt->rowCount() > 0;
    }

    public function updateEstadoSiProcede(int $id, int $agenteId, string $estado): void
    {
        $this->db()->prepare(
            "UPDATE clientes SET estado_cliente = ?
             WHERE id = ? AND agente_id = ?
               AND estado_cliente NOT IN ('cerrado','con_cotizacion')"
        )->execute([$estado, $id, $agenteId]);
    }

    // ── Telegram ──────────────────────────────────────────────────────────────

    public function saveTelegramChatId(int $clienteId, string $chatId): void
    {
        $this->db()->prepare('UPDATE clientes SET telegram_chat_id = ? WHERE id = ?')
                   ->execute([$chatId, $clienteId]);
    }

    // ── Registro público con asignación automática de asesor ─────────────────

    public function registrarDesdeFormulario(array $input): array
    {
        $fechaCita = $input['fechaCita'] ?? null;
        $horaCita  = $input['horaCita']  ?? null;
        $db        = $this->db();
        $ramo      = $input['ramo'] ?? 'autos';

        $agenteId = $this->buscarMejorAsesor($fechaCita, $horaCita);

        if (!$agenteId) {
            throw new \RuntimeException(
                'No hay asesores activos disponibles. Verifica que existan agentes con rol asesor/agente y activo=1.'
            );
        }

        // Campos base siempre presentes
        $datosCliente = [
            'nombre'           => $input['nombre'],
            'apellido_paterno' => $input['apellidoPaterno'],
            'apellido_materno' => $input['apellidoMaterno'],
            'fecha_nacimiento' => $input['fechaNacimiento'] ?? '',
            'correo'           => $input['correo'],
            'telefono'         => $input['telefono'] ?? '',
            'sexo'             => $input['sexo'] ?? '',
            'estado_civil'     => $input['estadoCivil'] ?? '',
            'calle'            => $input['calle'] ?? '',
            'colonia'          => $input['colonia'] ?? '',
            'ciudad'           => $input['ciudad'] ?? '',
            'estado'           => $input['estado'] ?? '',
            'codigo_postal'    => $input['codigoPostal'] ?? '',
            'asegurado'        => $input['asegurado'] ?? 'no',
            'agente_id'        => $agenteId,
            'estado_cliente'   => 'nuevo',
            'ramo'             => $ramo,
        ];

        // Campos dinámicos según ramo
        switch ($ramo) {
            case 'autos':
                $datosCliente += [
                    'marca'           => $input['marca'] ?? '',
                    'modelo'          => $input['modelo'] ?? '',
                    'anio_vehiculo'   => (int)($input['anioVehiculo'] ?? 0),
                    'tipo_vehiculo'   => $input['tipoVehiculo'] ?? '',
                    'recien_comprado' => $input['recienComprado'] ?? 'no',
                    'tiempo_compra'   => $input['tiempoCompra'] ?? '',
                ];
                break;
            case 'empresas':
                $datosCliente += [
                    'giro_empresa'     => $input['giroEmpresa'] ?? '',
                    'num_empleados'    => (int)($input['numEmpleados'] ?? 0),
                    'facturacion_anual'=> (float)($input['facturacionAnual'] ?? 0),
                ];
                break;
            // vida y gastos_medicos solo usan los campos base (fecha_nacimiento, sexo ya están)
        }

        $clienteId = $this->insert($datosCliente);

        if ($fechaCita && $horaCita) {
            $tipoCita = in_array($input['tipoCita'] ?? '', ['llamada','videollamada'], true)
                ? $input['tipoCita'] : 'llamada';
            $comentariosCita = trim($input['comentarios'] ?? '');
            $db->prepare(
                "INSERT INTO citas (cliente_id, agente_id, tipo, fecha, hora, comentarios, estado, ramo)
                 VALUES (?, ?, ?, ?, ?, ?, 'programada', ?)"
            )->execute([$clienteId, $agenteId, $tipoCita, $fechaCita, $horaCita, $comentariosCita ?: null, $ramo]);
        }

        return ['cliente_id' => $clienteId, 'agente_id' => $agenteId];
    }

    private function buscarMejorAsesor(?string $fecha, ?string $hora): int
    {
        $db      = $this->db();
        $rolCond = "a.rol IN ('asesor','agente') AND COALESCE(a.activo,1) = 1";

        if ($fecha && $hora) {
            $stmt = $db->prepare(
                "SELECT a.id, COUNT(ci.id) AS citas_dia
                 FROM agentes a
                 LEFT JOIN citas ci ON ci.agente_id = a.id AND ci.fecha = ?
                     AND ci.estado IN ('programada','pendiente')
                 WHERE {$rolCond}
                   AND a.id NOT IN (
                       SELECT DISTINCT agente_id FROM citas
                       WHERE fecha = ? AND hora = ?
                         AND estado IN ('programada','pendiente')
                   )
                 GROUP BY a.id ORDER BY citas_dia ASC, RAND() LIMIT 1"
            );
            $stmt->execute([$fecha, $fecha, $hora]);
            $row = $stmt->fetch();

            if (!$row) {
                $stmt = $db->prepare(
                    "SELECT a.id, COUNT(ci.id) AS citas_dia
                     FROM agentes a
                     LEFT JOIN citas ci ON ci.agente_id = a.id AND ci.fecha = ?
                         AND ci.estado IN ('programada','pendiente')
                     WHERE {$rolCond}
                     GROUP BY a.id ORDER BY citas_dia ASC, RAND() LIMIT 1"
                );
                $stmt->execute([$fecha]);
                $row = $stmt->fetch();
            }
        } else {
            $stmt = $db->prepare(
                "SELECT a.id, COUNT(c.id) AS total_clientes
                 FROM agentes a
                 LEFT JOIN clientes c ON a.id = c.agente_id
                 WHERE {$rolCond}
                 GROUP BY a.id ORDER BY total_clientes ASC, RAND() LIMIT 1"
            );
            $stmt->execute();
            $row = $stmt->fetch();
        }

        return $row ? (int)$row['id'] : 0;
    }
}
