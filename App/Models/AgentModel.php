<?php
declare(strict_types=1);

namespace App\Models;

class AgentModel extends BaseModel
{
    protected string $table = 'agentes';
    protected int $cacheTtl = 300;
    protected array $defaultColumns = [
        'id', 'nombre', 'apellido', 'correo', 'telefono', 'rol', 'activo',
    ];

    protected array $fillable = [
        'nombre'     => 'string',
        'apellido'   => 'string',
        'correo'     => 'string',
        'telefono'   => 'string',
        'contrasena' => 'string',
        'rol'        => 'string',
        'activo'     => 'int',
    ];

    // ── Lectura ───────────────────────────────────────────────────────────────

    public function findByEmail(string $correo): ?array
    {
        $stmt = $this->db()->prepare(
            'SELECT id, nombre, apellido, correo, contrasena, rol, activo
             FROM agentes WHERE correo = ? LIMIT 1'
        );
        $stmt->execute([$correo]);
        return $stmt->fetch() ?: null;
    }

    public function getTodos(): array
    {
        return $this->db()
            ->query('SELECT id, nombre, apellido, correo, telefono, rol,
                            COALESCE(activo,1) AS activo
                     FROM agentes
                     ORDER BY COALESCE(activo,1) DESC, id ASC')
            ->fetchAll();
    }

    public function getSoloAsesores(): array
    {
        return $this->db()
            ->query("SELECT id, nombre, apellido, correo, telefono, rol,
                            COALESCE(activo,1) AS activo
                     FROM agentes
                     WHERE rol IN ('asesor','agente') AND COALESCE(activo,1) = 1
                     ORDER BY nombre ASC")
            ->fetchAll();
    }

    // ── Escritura ─────────────────────────────────────────────────────────────

    public function crear(string $nombre, string $apellido, string $correo, string $telefono, string $password, string $rol = 'asesor'): int 
    {
        $chk = $this->db()->prepare('SELECT 1 FROM agentes WHERE correo = ? LIMIT 1');
        $chk->execute([$correo]);
        if ($chk->fetch()) return 0;

        return $this->insert([
            'nombre'     => $nombre,
            'apellido'   => $apellido,
            'correo'     => $correo,
            'telefono'   => $telefono,
            'contrasena' => password_hash($password, PASSWORD_BCRYPT),
            'rol'        => $rol,
            'activo'     => 1,
        ]);
    }

    public function cambiarRol(int $id, string $rol): void
    {
        $this->db()->prepare('UPDATE agentes SET rol = ? WHERE id = ?')->execute([$rol, $id]);
    }

    public function eliminar(int $id): void
    {
        $this->update($id, ['activo' => 0]);
    }

    public function reactivar(int $id): void
    {
        $this->update($id, ['activo' => 1]);
    }

    /**
     * Reemplaza el hash de contraseña almacenado para un agente.
     * Se usa para rehashing transparente cuando el algoritmo/costo cambia.
     */
    public function actualizarHash(int $id, string $hash): void
    {
        $this->db()
            ->prepare('UPDATE agentes SET contrasena = ? WHERE id = ?')
            ->execute([$hash, $id]);
    }

    // ── Estadísticas ──────────────────────────────────────────────────────────

    public function getStatsGlobales(): array
    {
        $db = $this->db();

        [$totalAgentes]  = $db->query('SELECT COUNT(*) FROM agentes')->fetchAll(\PDO::FETCH_COLUMN);
        [$totalClientes] = $db->query('SELECT COUNT(*) FROM clientes')->fetchAll(\PDO::FETCH_COLUMN);

        $d = $db->query("SELECT COUNT(*) AS total,
                                SUM(CASE WHEN estado='aceptada' THEN precio ELSE 0 END) AS ingreso
                         FROM cotizaciones")->fetch();

        $porRamoRaw = $db->query("SELECT ramo, COUNT(*) AS cotizaciones,
                                         SUM(estado='aceptada') AS ventas
                                  FROM cotizaciones GROUP BY ramo")->fetchAll();

        $porRamo = ['autos' => ['cotizaciones'=>0,'ventas'=>0],
                    'vida'  => ['cotizaciones'=>0,'ventas'=>0],
                    'gastos_medicos' => ['cotizaciones'=>0,'ventas'=>0]];

        foreach ($porRamoRaw as $row) {
            if (isset($porRamo[$row['ramo']])) {
                $porRamo[$row['ramo']] = [
                    'cotizaciones' => (int)$row['cotizaciones'],
                    'ventas'       => (int)$row['ventas'],
                ];
            }
        }

        return [
            'total_agentes'      => (int)$totalAgentes,
            'total_clientes'     => (int)$totalClientes,
            'total_cotizaciones' => (int)$d['total'],
            'ingreso_total'      => (float)$d['ingreso'],
            'por_ramo'           => $porRamo,
        ];
    }

    public function getRankingAsesores(): array
    {
        $vSql = \App\Models\CotizacionModel::ventaConcretadaSql('co');

        $sql = "SELECT a.id, a.nombre, a.apellido, a.correo, a.rol,
                       COALESCE(cl.total_clientes,0)  AS total_clientes,
                       COALESCE(cl.clientes_mes,0)    AS clientes_mes,
                       COALESCE(co.total_cot,0)       AS total_cotizaciones,
                       COALESCE(co.total_ventas,0)    AS total_ventas,
                       COALESCE(co.ingreso_total,0)   AS ingreso_total,
                       COALESCE(co.cot_mes,0)         AS cotizaciones_mes,
                       COALESCE(co.ventas_mes,0)      AS ventas_mes,
                       COALESCE(co.ingreso_mes,0)     AS ingreso_mes
                FROM agentes a
                LEFT JOIN (
                    SELECT agente_id,
                           COUNT(*) AS total_clientes,
                           SUM(YEAR(fecha_registro)=YEAR(NOW()) AND MONTH(fecha_registro)=MONTH(NOW())) AS clientes_mes
                    FROM clientes GROUP BY agente_id
                ) cl ON cl.agente_id = a.id
                LEFT JOIN (
                    SELECT agente_id,
                           COUNT(*) AS total_cot,
                           SUM({$vSql}) AS total_ventas,
                           SUM(CASE WHEN {$vSql} THEN co.precio ELSE 0 END) AS ingreso_total,
                           SUM(YEAR(co.fecha_creacion)=YEAR(NOW()) AND MONTH(co.fecha_creacion)=MONTH(NOW())) AS cot_mes,
                           SUM(CASE WHEN YEAR(co.fecha_creacion)=YEAR(NOW()) AND MONTH(co.fecha_creacion)=MONTH(NOW()) AND ({$vSql}) THEN 1 ELSE 0 END) AS ventas_mes,
                           SUM(CASE WHEN YEAR(co.fecha_creacion)=YEAR(NOW()) AND MONTH(co.fecha_creacion)=MONTH(NOW()) AND ({$vSql}) THEN co.precio ELSE 0 END) AS ingreso_mes
                    FROM cotizaciones co GROUP BY agente_id
                ) co ON co.agente_id = a.id
                WHERE a.rol IN ('asesor','agente') AND COALESCE(a.activo,1) = 1
                ORDER BY ingreso_total DESC";

        return array_map(fn($r) => [
            'id'                 => (int)   $r['id'],
            'nombre'             => $r['nombre'],
            'apellido'           => $r['apellido'],
            'correo'             => $r['correo'],
            'rol'                => $r['rol'],
            'total_clientes'     => (int)   $r['total_clientes'],
            'total_cotizaciones' => (int)   $r['total_cotizaciones'],
            'total_ventas'       => (int)   $r['total_ventas'],
            'ingreso_total'      => (float) $r['ingreso_total'],
            'clientes_mes'       => (int)   $r['clientes_mes'],
            'cotizaciones_mes'   => (int)   $r['cotizaciones_mes'],
            'ventas_mes'         => (int)   $r['ventas_mes'],
            'ingreso_mes'        => (float) $r['ingreso_mes'],
        ], $this->db()->query($sql)->fetchAll());
    }

    public function getReporteFinanciero(): array
    {
        $sql = "SELECT a.id, a.nombre, a.apellido, a.rol,
                       COALESCE(cl.total_clientes,0) AS total_clientes,
                       COALESCE(co.total_cot,0)      AS total_cotizaciones,
                       COALESCE(co.ventas,0)         AS ventas,
                       COALESCE(co.ingreso_bruto,0)  AS ingreso_bruto,
                       COALESCE(co.ingreso_neto,0)   AS ingreso_neto,
                       COALESCE(co.ticket_prom,0)    AS ticket_promedio,
                       COALESCE(co.ventas_mes,0)     AS ventas_mes,
                       COALESCE(co.ingreso_mes,0)    AS ingreso_mes,
                       COALESCE(co.ingreso_ant,0)    AS ingreso_mes_anterior
                FROM agentes a
                LEFT JOIN (SELECT agente_id, COUNT(*) AS total_clientes FROM clientes GROUP BY agente_id) cl ON cl.agente_id = a.id
                LEFT JOIN (
                    SELECT agente_id,
                           COUNT(*) AS total_cot,
                           SUM(estado='aceptada') AS ventas,
                           SUM(precio) AS ingreso_bruto,
                           SUM(CASE WHEN estado='aceptada' THEN precio ELSE 0 END) AS ingreso_neto,
                           AVG(CASE WHEN estado='aceptada' THEN precio END) AS ticket_prom,
                           SUM(estado='aceptada' AND YEAR(fecha_creacion)=YEAR(NOW()) AND MONTH(fecha_creacion)=MONTH(NOW())) AS ventas_mes,
                           SUM(CASE WHEN estado='aceptada' AND YEAR(fecha_creacion)=YEAR(NOW()) AND MONTH(fecha_creacion)=MONTH(NOW()) THEN precio ELSE 0 END) AS ingreso_mes,
                           SUM(CASE WHEN estado='aceptada' AND YEAR(fecha_creacion)=YEAR(DATE_SUB(NOW(),INTERVAL 1 MONTH)) AND MONTH(fecha_creacion)=MONTH(DATE_SUB(NOW(),INTERVAL 1 MONTH)) THEN precio ELSE 0 END) AS ingreso_ant
                    FROM cotizaciones GROUP BY agente_id
                ) co ON co.agente_id = a.id
                ORDER BY ingreso_neto DESC";

        return array_map(fn($r) => [
            'id'                   => (int)   $r['id'],
            'nombre'               => $r['nombre'],
            'apellido'             => $r['apellido'],
            'rol'                  => $r['rol'],
            'total_clientes'       => (int)   $r['total_clientes'],
            'total_cotizaciones'   => (int)   $r['total_cotizaciones'],
            'ventas'               => (int)   $r['ventas'],
            'ingreso_bruto'        => (float) $r['ingreso_bruto'],
            'ingreso_neto'         => (float) $r['ingreso_neto'],
            'ticket_promedio'      => (float) $r['ticket_promedio'],
            'ventas_mes'           => (int)   $r['ventas_mes'],
            'ingreso_mes'          => (float) $r['ingreso_mes'],
            'ingreso_mes_anterior' => (float) $r['ingreso_mes_anterior'],
            'tasa_conversion'      => (int)$r['total_cotizaciones'] > 0
                ? round((int)$r['ventas'] / (int)$r['total_cotizaciones'] * 100, 1)
                : 0,
        ], $this->db()->query($sql)->fetchAll());
    }

    public function getEvolucionGlobal(?int $agenteId = null): array
    {
        $whereCliente = $agenteId ? 'AND agente_id = ?' : '';
        $whereCot     = $agenteId ? 'AND agente_id = ?' : '';

        $sql = "SELECT DATE_FORMAT(meses.mes_inicio,'%b') AS mes,
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
                    FROM clientes WHERE 1=1 {$whereCliente} GROUP BY m
                ) cl ON cl.m = meses.mes_inicio
                LEFT JOIN (
                    SELECT DATE_FORMAT(fecha_creacion,'%Y-%m-01') AS m,
                           COUNT(*) AS total,
                           SUM(estado='aceptada') AS ventas,
                           SUM(CASE WHEN estado='aceptada' THEN precio ELSE 0 END) AS ingresos
                    FROM cotizaciones WHERE 1=1 {$whereCot} GROUP BY m
                ) co ON co.m = meses.mes_inicio
                ORDER BY meses.mes_inicio ASC";

        $stmt = $this->db()->prepare($sql);
        $params = $agenteId ? [$agenteId, $agenteId] : [];
        $stmt->execute($params);

        return array_map(fn($e) => [
            'mes'          => $e['mes'],
            'clientes'     => (int)   $e['clientes'],
            'cotizaciones' => (int)   $e['cotizaciones'],
            'ventas'       => (int)   $e['ventas'],
            'ingresos'     => (float) $e['ingresos'],
        ], $stmt->fetchAll());
    }

    public function getTopVehiculos(?int $agenteId = null, int $limit = 5): array
    {
        $where = $agenteId ? 'WHERE agente_id = ?' : '';
        $stmt  = $this->db()->prepare(
            "SELECT tipo_vehiculo AS tipo, COUNT(*) AS total
             FROM clientes {$where}
             GROUP BY tipo_vehiculo ORDER BY total DESC LIMIT ?"
        );
        $params = $agenteId ? [$agenteId, $limit] : [$limit];
        $stmt->execute($params);
        $rows = $stmt->fetchAll() ?: [['tipo' => 'Sin datos', 'total' => 1]];
        return array_map(fn($r) => [
            'tipo'  => (!empty($r['tipo']) ? $r['tipo'] : 'Otros'),
            'total' => (int)$r['total'],
        ], $rows);
    }

    public function getCoberturaGlobal(?int $agenteId = null, int $limit = 6): array
    {
        $where = $agenteId
            ? "WHERE agente_id = ? AND estado='aceptada'"
            : "WHERE estado='aceptada'";
        $stmt = $this->db()->prepare(
            "SELECT tipo_cobertura AS tipo, COUNT(*) AS total
             FROM cotizaciones {$where}
             GROUP BY tipo_cobertura ORDER BY total DESC LIMIT ?"
        );
        $params = $agenteId ? [$agenteId, $limit] : [$limit];
        $stmt->execute($params);
        $rows = $stmt->fetchAll() ?: [['tipo' => 'Sin ventas', 'total' => 0]];
        return array_map(fn($r) => [
            'tipo'  => (!empty($r['tipo']) ? $r['tipo'] : 'Otros'),
            'total' => (int)$r['total'],
        ], $rows);
    }

    public function getActividadEquipo(int $limit = 60, ?int $agenteId = null): array
    {
        $where  = "WHERE a.rol IN ('asesor','agente') AND COALESCE(a.activo,1) = 1";
        $params = [];
        if ($agenteId) {
            $where  .= ' AND h.agente_id = ?';
            $params[] = $agenteId;
        }
        $params[] = $limit;

        $stmt = $this->db()->prepare(
            "SELECT h.tipo, h.titulo, h.descripcion, h.fecha,
                    CONCAT(a.nombre,' ',a.apellido)  AS agente_nombre,
                    CONCAT(cl.nombre,' ',cl.apellido_paterno) AS cliente_nombre,
                    cl.id AS cliente_id
             FROM historial_cliente h
             JOIN agentes  a  ON a.id  = h.agente_id
             JOIN clientes cl ON cl.id = h.cliente_id
             {$where}
             ORDER BY h.fecha DESC LIMIT ?"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getPipeline(): array
    {
        $sql = "SELECT a.id, a.nombre, a.apellido,
                       SUM(co.estado='pendiente')  AS pendientes,
                       SUM(co.estado='enviada')    AS enviadas,
                       SUM(co.estado='aceptada')   AS cerradas,
                       SUM(co.estado='rechazada')  AS perdidas,
                       SUM(CASE WHEN co.estado IN ('pendiente','enviada') THEN co.precio ELSE 0 END) AS valor_pipeline,
                       SUM(CASE WHEN co.estado='aceptada' THEN co.precio ELSE 0 END) AS valor_cerrado
                FROM agentes a
                LEFT JOIN cotizaciones co ON co.agente_id = a.id
                WHERE a.rol IN ('asesor','agente') AND COALESCE(a.activo,1) = 1
                GROUP BY a.id, a.nombre, a.apellido
                ORDER BY valor_pipeline DESC";

        return $this->db()->query($sql)->fetchAll();
    }

    /**
     * Métricas de leads/clientes nuevos para el panel de coordinación:
     * totales por periodo (hoy, semana, quincena, mes) y un comparativo
     * "antes vs. ahora" por asesor (ayer vs. hoy, semana pasada vs. esta
     * semana) para detectar caídas o subidas de actividad a tiempo.
     *
     * Todo en una sola consulta agregada (nada de N+1 por asesor) usando
     * SUM(CASE ...) por rango de fecha sobre fecha_registro.
     */
    public function getLeadMetrics(): array
    {
        $sql = "SELECT a.id, a.nombre, a.apellido,
                       -- Totales del periodo actual
                       SUM(DATE(c.fecha_registro) = CURDATE())                                              AS hoy,
                       SUM(DATE(c.fecha_registro) = CURDATE() - INTERVAL 1 DAY)                              AS ayer,
                       SUM(YEARWEEK(c.fecha_registro,1) = YEARWEEK(CURDATE(),1))                             AS semana,
                       SUM(YEARWEEK(c.fecha_registro,1) = YEARWEEK(CURDATE(),1) - 1)                         AS semana_pasada,
                       SUM(
                           (DAY(CURDATE()) <= 15 AND DATE(c.fecha_registro) BETWEEN DATE_FORMAT(CURDATE(),'%Y-%m-01') AND DATE_FORMAT(CURDATE(),'%Y-%m-15'))
                           OR
                           (DAY(CURDATE()) > 15 AND DATE(c.fecha_registro) BETWEEN DATE_FORMAT(CURDATE(),'%Y-%m-16') AND LAST_DAY(CURDATE()))
                       )                                                                                     AS quincena,
                       SUM(YEAR(c.fecha_registro)=YEAR(CURDATE()) AND MONTH(c.fecha_registro)=MONTH(CURDATE())) AS mes,
                       SUM(YEAR(c.fecha_registro)=YEAR(CURDATE() - INTERVAL 1 MONTH) AND MONTH(c.fecha_registro)=MONTH(CURDATE() - INTERVAL 1 MONTH)) AS mes_pasado
                FROM agentes a
                LEFT JOIN clientes c ON c.agente_id = a.id
                WHERE a.rol IN ('asesor','agente') AND COALESCE(a.activo,1) = 1
                GROUP BY a.id, a.nombre, a.apellido
                ORDER BY hoy DESC, semana DESC";

        $porAsesor = $this->db()->query($sql)->fetchAll();

        // Totales del equipo completo (suma de la tabla anterior).
        $totales = [
            'hoy' => 0, 'ayer' => 0, 'semana' => 0, 'semana_pasada' => 0,
            'quincena' => 0, 'mes' => 0, 'mes_pasado' => 0,
        ];
        foreach ($porAsesor as $row) {
            foreach ($totales as $k => $v) {
                $totales[$k] += (int) ($row[$k] ?? 0);
            }
        }

        return [
            'totales'    => $totales,
            'por_asesor' => $porAsesor,
        ];
    }

    public function getClientesPorAsesor(int $agenteId): array
    {
        $stmt = $this->db()->prepare(
            "SELECT c.id, c.nombre, c.apellido_paterno, c.apellido_materno,
                    c.correo, c.telefono, c.estado_cliente, c.fecha_registro,
                    c.marca, c.modelo, c.anio_vehiculo,
                    COUNT(co.id) AS total_cotizaciones
             FROM clientes c
             LEFT JOIN cotizaciones co ON co.cliente_id = c.id
             WHERE c.agente_id = ?
             GROUP BY c.id
             ORDER BY c.fecha_registro DESC"
        );
        $stmt->execute([$agenteId]);
        return $stmt->fetchAll();
    }

    public function reasignarClientes(array $clienteIds, int $nuevoAsesorId): int
    {
        if (empty($clienteIds)) return 0;
        $placeholders = implode(',', array_fill(0, count($clienteIds), '?'));
        $db = $this->db();

        // 1. Reasignar el cliente al nuevo asesor
        $stmt = $db->prepare(
            "UPDATE clientes SET agente_id = ? WHERE id IN ({$placeholders})"
        );
        $stmt->execute([$nuevoAsesorId, ...$clienteIds]);
        $total = $stmt->rowCount();

        // 2. Reasignar pólizas vinculadas (para que el nuevo asesor las vea)
        try {
            $db->prepare(
                "UPDATE polizas SET agente_id = ? WHERE cliente_id IN ({$placeholders}) AND eliminado_en IS NULL"
            )->execute([$nuevoAsesorId, ...$clienteIds]);
        } catch (\Throwable) {}

        // 3. Registrar en historial de cada cliente
        try {
            foreach ($clienteIds as $cid) {
                $db->prepare(
                    "INSERT INTO historial_cliente (cliente_id, agente_id, tipo, titulo, descripcion, fecha)
                     VALUES (?, ?, 'nota', '↔️ Cliente reasignado', 'Cliente transferido al asesor actual por un coordinador/superadmin.', NOW())"
                )->execute([$cid, $nuevoAsesorId]);
            }
        } catch (\Throwable) {}

        return $total;
    }

    public function getRecentActivity(int $agenteId, int $limit = 8): array
    {
        $stmt = $this->db()->prepare(
            "SELECT h.tipo, h.titulo AS descripcion, h.fecha,
                    CONCAT(cl.nombre,' ',cl.apellido_paterno) AS cliente_nombre
             FROM historial_cliente h
             JOIN clientes cl ON cl.id = h.cliente_id
             WHERE h.agente_id = ?
             ORDER BY h.fecha DESC LIMIT ?"
        );
        $stmt->execute([$agenteId, $limit]);
        return $stmt->fetchAll();
    }
}
