<?php
declare(strict_types=1);

namespace App\Models;

use App\Security\Roles;
use App\Security\Tenant;

class AgentModel extends BaseModel
{
    protected string $table = 'agentes';
    protected int $cacheTtl = 300;
    protected array $defaultColumns = [
        'id', 'nombre', 'apellido', 'correo', 'telefono', 'rol', 'puesto', 'activo', 'empresa_id',
    ];

    protected array $fillable = [
        'empresa_id' => 'int',
        'nombre'     => 'string',
        'apellido'   => 'string',
        'correo'     => 'string',
        'telefono'   => 'string',
        'contrasena' => 'string',
        'rol'        => 'string',
        'puesto'     => 'string',
        'activo'     => 'int',
    ];

    /**
     * Catálogo de "puestos" (etiquetas de negocio) disponibles, cada uno
     * asociado a UNO de los 3 roles base que controlan permisos en todo
     * el sistema. Agregar un puesto nuevo aquí NO requiere tocar lógica
     * de permisos en ningún controlador/vista: el `rol` real sigue siendo
     * el mismo de siempre, solo cambia cómo se muestra en la UI.
     *
     * Para dar de alta un "puesto" nuevo (ej. "Gerente de Ventas"),
     * solo hay que agregar una línea aquí indicando a qué rol base
     * hereda sus permisos.
     */
    public const PUESTOS = [
        'asesor'            => ['label' => 'Asesor',                    'rol_base' => 'asesor'],
        'coordinador'       => ['label' => 'Coordinador',               'rol_base' => 'coordinador'],
        'gerente_ventas'    => ['label' => 'Gerente de Ventas',         'rol_base' => 'coordinador'],
        'superadmin'        => ['label' => 'Administrador de empresa',  'rol_base' => 'superadmin'],
        'propietario'       => ['label' => 'Propietario de plataforma', 'rol_base' => 'propietario'],
    ];

    public static function labelPuesto(?string $puesto, string $rolFallback): string
    {
        if ($puesto && isset(self::PUESTOS[$puesto])) {
            return self::PUESTOS[$puesto]['label'];
        }
        return self::PUESTOS[$rolFallback]['label'] ?? ucfirst($rolFallback);
    }

    public static function rolBaseDePuesto(?string $puesto, string $rolFallback): string
    {
        if ($puesto && isset(self::PUESTOS[$puesto])) {
            return self::PUESTOS[$puesto]['rol_base'];
        }
        return $rolFallback;
    }

    // ── Lectura ───────────────────────────────────────────────────────────────

    /**
     * Busca un agente por correo.
     *
     * El login ocurre antes de que exista contexto de empresa, así que esta
     * consulta es deliberadamente global. Si se pasa $empresaId se acota.
     */
    public function findByEmail(string $correo, ?int $empresaId = null): ?array
    {
        $sql = 'SELECT id, empresa_id, nombre, apellido, correo, contrasena, rol, puesto, activo
                FROM agentes WHERE correo = ?';
        $params = [$correo];

        if ($empresaId !== null) {
            $sql .= ' AND empresa_id = ?';
            $params[] = $empresaId;
        }

        $stmt = $this->db()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);

        return $stmt->fetch() ?: null;
    }

    /**
     * Todas las cuentas con ese correo, en cualquier empresa.
     *
     * Desde que el correo es único por empresa y no globalmente, una misma
     * persona puede tener cuenta en varias empresas. El login necesita verlas
     * todas para saber si hay que preguntar a cuál entra.
     *
     * @return array<int,array<string,mixed>>
     */
    public function cuentasPorCorreo(string $correo): array
    {
        $stmt = $this->db()->prepare(
            'SELECT a.id, a.empresa_id, a.nombre, a.apellido, a.correo, a.contrasena,
                    a.rol, a.puesto, COALESCE(a.activo,1) AS activo,
                    e.nombre AS empresa_nombre, COALESCE(e.activo,1) AS empresa_activa
             FROM agentes a
             LEFT JOIN empresas e ON e.id = a.empresa_id
             WHERE a.correo = ?
             ORDER BY a.id ASC'
        );
        $stmt->execute([$correo]);

        return $stmt->fetchAll();
    }

    public function getTodos(): array
    {
        $stmt = $this->db()->prepare(
            'SELECT id, empresa_id, nombre, apellido, correo, telefono, rol, puesto,
                    COALESCE(activo,1) AS activo
             FROM agentes
             WHERE 1=1' . $this->sqlEmpresa() . '
             ORDER BY COALESCE(activo,1) DESC, id ASC'
        );
        $stmt->execute($this->bindEmpresa());

        return $stmt->fetchAll();
    }

    public function getSoloAsesores(): array
    {
        $stmt = $this->db()->prepare(
            "SELECT id, empresa_id, nombre, apellido, correo, telefono, rol, puesto,
                    COALESCE(activo,1) AS activo
             FROM agentes
             WHERE rol IN ('asesor','agente') AND COALESCE(activo,1) = 1"
             . $this->sqlEmpresa() . '
             ORDER BY nombre ASC'
        );
        $stmt->execute($this->bindEmpresa());

        return $stmt->fetchAll();
    }

    /** Rol de un agente, acotado a la empresa activa. */
    public function getRol(int $id): ?string
    {
        $stmt = $this->db()->prepare(
            'SELECT rol FROM agentes WHERE id = :__id' . $this->sqlEmpresa() . ' LIMIT 1'
        );
        $stmt->execute($this->bindEmpresa(['__id' => $id]));
        $rol = $stmt->fetchColumn();

        return $rol === false ? null : (string) $rol;
    }

    /** True si el agente pertenece a la empresa indicada. */
    public function perteneceA(int $agenteId, int $empresaId): bool
    {
        $stmt = $this->db()->prepare(
            'SELECT 1 FROM agentes WHERE id = ? AND empresa_id = ? LIMIT 1'
        );
        $stmt->execute([$agenteId, $empresaId]);

        return (bool) $stmt->fetchColumn();
    }

    public function registrarAcceso(int $id): void
    {
        try {
            $this->db()
                ->prepare('UPDATE agentes SET ultimo_acceso = NOW() WHERE id = ?')
                ->execute([$id]);
        } catch (\Throwable $e) {
            // La columna puede no existir si la migración no corrió todavía;
            // no es motivo para impedir el acceso.
        }
    }

    // ── Escritura ─────────────────────────────────────────────────────────────

    /**
     * Da de alta un agente en la empresa activa.
     *
     * El correo se comprueba dentro de la empresa, no globalmente: dos
     * empresas distintas pueden tener a la misma persona como usuario.
     */
    public function crear(string $nombre, string $apellido, string $correo, string $telefono, string $password, string $rol = 'asesor', ?string $puesto = null, ?int $empresaId = null): int
    {
        $empresaId ??= Tenant::id();

        if ($empresaId === null) {
            return 0;
        }

        $chk = $this->db()->prepare('SELECT 1 FROM agentes WHERE correo = ? AND empresa_id = ? LIMIT 1');
        $chk->execute([$correo, $empresaId]);
        if ($chk->fetch()) return 0;

        if (!(new EmpresaModel())->admiteOtroUsuario($empresaId)) {
            return 0;
        }

        return Tenant::comoEmpresa($empresaId, fn() => $this->insert([
            'empresa_id' => $empresaId,
            'nombre'     => $nombre,
            'apellido'   => $apellido,
            'correo'     => $correo,
            'telefono'   => $telefono,
            'contrasena' => password_hash($password, PASSWORD_BCRYPT),
            'rol'        => $rol,
            'puesto'     => $puesto ?? $rol,
            'activo'     => 1,
        ]));
    }

    public function cambiarRol(int $id, string $rol): void
    {
        // El rol `propietario` no se asigna desde la interfaz: es de
        // plataforma y se otorga a mano al instalar.
        if (!Roles::existe($rol) || $rol === Roles::PROPIETARIO) {
            return;
        }

        $stmt = $this->db()->prepare(
            'UPDATE agentes SET rol = :rol WHERE id = :__id' . $this->sqlEmpresa()
        );
        $stmt->execute($this->bindEmpresa(['rol' => $rol, '__id' => $id]));

        \App\Services\QueryCache::invalidate($this->table);
    }

    public function cambiarPuesto(int $id, string $puesto): void
    {
        if (!isset(self::PUESTOS[$puesto]) || $puesto === 'propietario') return;

        $rolBase = self::PUESTOS[$puesto]['rol_base'];
        // El rol base se sincroniza automáticamente: así toda la lógica de
        // permisos existente (basada en `rol`) sigue funcionando sin cambios.
        $stmt = $this->db()->prepare(
            'UPDATE agentes SET rol = :rol, puesto = :puesto WHERE id = :__id' . $this->sqlEmpresa()
        );
        $stmt->execute($this->bindEmpresa([
            'rol'    => $rolBase,
            'puesto' => $puesto,
            '__id'   => $id,
        ]));

        \App\Services\QueryCache::invalidate($this->table);
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
        // tenant-ok: el rehash ocurre durante el login, antes de que exista
        // contexto de empresa; el id viene de la fila ya autenticada, nunca
        // de la petición
        $this->db()
            ->prepare('UPDATE agentes SET contrasena = ? WHERE id = ?')
            ->execute([$hash, $id]);
    }

    // ── Estadísticas ──────────────────────────────────────────────────────────

    public function getStatsGlobales(): array
    {
        $db   = $this->db();
        $bind = $this->bindEmpresa();

        $stmt = $db->prepare('SELECT COUNT(*) FROM agentes' . $this->sqlEmpresa('', 'WHERE'));
        $stmt->execute($bind);
        $totalAgentes = (int) $stmt->fetchColumn();

        $stmt = $db->prepare('SELECT COUNT(*) FROM clientes' . $this->sqlEmpresa('', 'WHERE'));
        $stmt->execute($bind);
        $totalClientes = (int) $stmt->fetchColumn();

        $stmt = $db->prepare(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN estado='aceptada' THEN precio ELSE 0 END) AS ingreso
               FROM cotizaciones" . $this->sqlEmpresa('', 'WHERE')
        );
        $stmt->execute($bind);
        $d = $stmt->fetch();

        $stmt = $db->prepare(
            "SELECT ramo, COUNT(*) AS cotizaciones,
                    SUM(estado='aceptada') AS ventas
               FROM cotizaciones" . $this->sqlEmpresa('', 'WHERE') . ' GROUP BY ramo'
        );
        $stmt->execute($bind);
        $porRamoRaw = $stmt->fetchAll();

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
                    FROM clientes" . $this->sqlEmpresa('', 'WHERE', 'c') . " GROUP BY agente_id
                ) cl ON cl.agente_id = a.id
                LEFT JOIN (
                    SELECT agente_id,
                           COUNT(*) AS total_cot,
                           SUM({$vSql}) AS total_ventas,
                           SUM(CASE WHEN {$vSql} THEN co.precio ELSE 0 END) AS ingreso_total,
                           SUM(YEAR(co.fecha_creacion)=YEAR(NOW()) AND MONTH(co.fecha_creacion)=MONTH(NOW())) AS cot_mes,
                           SUM(CASE WHEN YEAR(co.fecha_creacion)=YEAR(NOW()) AND MONTH(co.fecha_creacion)=MONTH(NOW()) AND ({$vSql}) THEN 1 ELSE 0 END) AS ventas_mes,
                           SUM(CASE WHEN YEAR(co.fecha_creacion)=YEAR(NOW()) AND MONTH(co.fecha_creacion)=MONTH(NOW()) AND ({$vSql}) THEN co.precio ELSE 0 END) AS ingreso_mes
                    FROM cotizaciones co" . $this->sqlEmpresa('co', 'WHERE', 'q') . " GROUP BY agente_id
                ) co ON co.agente_id = a.id
                WHERE a.rol IN ('asesor','agente') AND COALESCE(a.activo,1) = 1"
                . $this->sqlEmpresa('a', 'AND', 'a') . "
                ORDER BY ingreso_total DESC";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($this->bindEmpresa([], ['c', 'q', 'a']));

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
        ], $stmt->fetchAll());
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
                LEFT JOIN (SELECT agente_id, COUNT(*) AS total_clientes FROM clientes"
                    . $this->sqlEmpresa('', 'WHERE', 'c') . " GROUP BY agente_id) cl ON cl.agente_id = a.id
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
                    FROM cotizaciones" . $this->sqlEmpresa('', 'WHERE', 'q') . " GROUP BY agente_id
                ) co ON co.agente_id = a.id"
                . $this->sqlEmpresa('a', 'WHERE', 'a') . "
                ORDER BY ingreso_neto DESC";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($this->bindEmpresa([], ['c', 'q', 'a']));

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
        ], $stmt->fetchAll());
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
                    FROM clientes WHERE 1=1 {$whereCliente}" . $this->sqlEmpresaPos() . " GROUP BY m
                ) cl ON cl.m = meses.mes_inicio
                LEFT JOIN (
                    SELECT DATE_FORMAT(fecha_creacion,'%Y-%m-01') AS m,
                           COUNT(*) AS total,
                           SUM(estado='aceptada') AS ventas,
                           SUM(CASE WHEN estado='aceptada' THEN precio ELSE 0 END) AS ingresos
                    FROM cotizaciones WHERE 1=1 {$whereCot}" . $this->sqlEmpresaPos() . " GROUP BY m
                ) co ON co.m = meses.mes_inicio
                ORDER BY meses.mes_inicio ASC";

        $stmt = $this->db()->prepare($sql);

        // El orden importa: cada subconsulta aporta sus parámetros en el mismo
        // orden en que aparece en el SQL.
        $params = array_merge(
            $agenteId ? [$agenteId] : [],
            $this->empresaPos(),
            $agenteId ? [$agenteId] : [],
            $this->empresaPos(),
        );
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
        $where  = $agenteId ? 'WHERE agente_id = ?' : '';
        $where .= $this->sqlEmpresaPos('', $agenteId ? 'AND' : 'WHERE');

        // LIMIT va interpolado tras castear: con ATTR_EMULATE_PREPARES=false,
        // MySQL rechaza un LIMIT atado como cadena.
        $stmt = $this->db()->prepare(
            "SELECT tipo_vehiculo AS tipo, COUNT(*) AS total
             FROM clientes {$where}
             GROUP BY tipo_vehiculo ORDER BY total DESC LIMIT " . (int) $limit
        );
        $stmt->execute(array_merge($agenteId ? [$agenteId] : [], $this->empresaPos()));
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
        $where .= $this->sqlEmpresaPos();

        $stmt = $this->db()->prepare(
            "SELECT tipo_cobertura AS tipo, COUNT(*) AS total
             FROM cotizaciones {$where}
             GROUP BY tipo_cobertura ORDER BY total DESC LIMIT " . (int) $limit
        );
        $stmt->execute(array_merge($agenteId ? [$agenteId] : [], $this->empresaPos()));
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

    /**
     * Clasifica un valor crudo de `clientes.origen` en uno de 3 buckets
     * de negocio. Único lugar donde vive esta regla — getOrigenClientesMes()
     * y getDetalleOrigenMes() lo reutilizan para que nunca se desincronicen.
     *
     *  - 'campana'    → landing dedicada a la que mandas tráfico pagado
     *                   (ej. /campana-vehicular), detectada por el campo
     *                   `vehiculo` que solo ese formulario envía.
     *  - 'formulario' → formularios de producto del sitio (landing de
     *                   vida, autos, GMM, empresas) y el formulario
     *                   general de agendar cita.
     *  - 'manual'     → alta manual del asesor, o registros sin clasificar
     *                   (histórico previo a que existiera esta columna).
     */
    public static function clasificarOrigen(?string $origen): string
    {
        $campanaTags    = ['campana'];
        $formularioTags = ['formulario', 'formulario_propio', 'formulario_producto', 'formulario_campana'];

        if ($origen !== null && in_array($origen, $campanaTags, true))    return 'campana';
        if ($origen !== null && in_array($origen, $formularioTags, true)) return 'formulario';
        return 'manual';
    }

    public function getOrigenClientesMes(): array
    {
        $resultado = ['campana' => 0, 'formulario' => 0, 'manual' => 0];

        try {
            $stmt = $this->db()->prepare(
                "SELECT origen, COUNT(*) AS total
                 FROM clientes
                 WHERE YEAR(fecha_registro) = YEAR(CURDATE()) AND MONTH(fecha_registro) = MONTH(CURDATE())"
                 . $this->sqlEmpresa() . "
                 GROUP BY origen"
            );
            $stmt->execute($this->bindEmpresa());
            foreach ($stmt->fetchAll() as $row) {
                $bucket = self::clasificarOrigen($row['origen'] ?? null);
                $resultado[$bucket] += (int) $row['total'];
            }
        } catch (\Throwable) {
            // La columna `origen` puede no existir en instalaciones antiguas;
            // en ese caso no se puede distinguir el origen, se reporta todo
            // como alta manual para no inventar datos.
            try {
                $stmt = $this->db()->prepare(
                    "SELECT COUNT(*) AS total FROM clientes
                     WHERE YEAR(fecha_registro) = YEAR(CURDATE()) AND MONTH(fecha_registro) = MONTH(CURDATE())"
                     . $this->sqlEmpresa()
                );
                $stmt->execute($this->bindEmpresa());
                $resultado['manual'] = (int) ($stmt->fetch()['total'] ?? 0);
            } catch (\Throwable) {}
        }

        return $resultado;
    }

    /**
     * Detalle de clientes (para el modal drill-down) por bucket de origen
     * ('campana', 'formulario' o 'manual') del mes en curso.
     */
    public function getDetalleOrigenMes(string $bucket): array
    {
        try {
            $stmt = $this->db()->prepare(
                "SELECT c.id, c.nombre, c.apellido_paterno, c.apellido_materno,
                        c.correo, c.telefono, c.ramo, c.fecha_registro, c.origen,
                        a.nombre AS agente_nombre, a.apellido AS agente_apellido
                 FROM clientes c
                 LEFT JOIN agentes a ON a.id = c.agente_id
                 WHERE YEAR(c.fecha_registro) = YEAR(CURDATE()) AND MONTH(c.fecha_registro) = MONTH(CURDATE())"
                 . $this->sqlEmpresa('c') . "
                 ORDER BY c.fecha_registro DESC
                 LIMIT 300"
            );
            $stmt->execute($this->bindEmpresa());
            $rows = $stmt->fetchAll();
        } catch (\Throwable) {
            return [];
        }

        return array_values(array_filter($rows, fn($r) => self::clasificarOrigen($r['origen'] ?? null) === $bucket));
    }

    /**
     * Detalle de clientes (para el modal drill-down) por periodo, usando
     * las MISMAS ventanas de fecha que getLeadMetrics() para que los
     * números del KPI y del listado siempre coincidan.
     */
    public function getDetalleClientesPeriodo(string $periodo): array
    {
        $where = match ($periodo) {
            'hoy'           => "DATE(c.fecha_registro) = CURDATE()",
            'ayer'          => "DATE(c.fecha_registro) = CURDATE() - INTERVAL 1 DAY",
            'semana'        => "YEARWEEK(c.fecha_registro,1) = YEARWEEK(CURDATE(),1)",
            'semana_pasada' => "YEARWEEK(c.fecha_registro,1) = YEARWEEK(CURDATE(),1) - 1",
            'quincena'      => "(
                                    (DAY(CURDATE()) <= 15 AND DATE(c.fecha_registro) BETWEEN DATE_FORMAT(CURDATE(),'%Y-%m-01') AND DATE_FORMAT(CURDATE(),'%Y-%m-15'))
                                    OR
                                    (DAY(CURDATE()) > 15 AND DATE(c.fecha_registro) BETWEEN DATE_FORMAT(CURDATE(),'%Y-%m-16') AND LAST_DAY(CURDATE()))
                                )",
            'mes'           => "YEAR(c.fecha_registro)=YEAR(CURDATE()) AND MONTH(c.fecha_registro)=MONTH(CURDATE())",
            'mes_pasado'    => "YEAR(c.fecha_registro)=YEAR(CURDATE() - INTERVAL 1 MONTH) AND MONTH(c.fecha_registro)=MONTH(CURDATE() - INTERVAL 1 MONTH)",
            default         => '1=0',
        };

        try {
            $stmt = $this->db()->prepare(
                "SELECT c.id, c.nombre, c.apellido_paterno, c.apellido_materno,
                        c.correo, c.telefono, c.ramo, c.fecha_registro, c.origen,
                        a.nombre AS agente_nombre, a.apellido AS agente_apellido
                 FROM clientes c
                 LEFT JOIN agentes a ON a.id = c.agente_id
                 WHERE {$where}" . $this->sqlEmpresa('c') . "
                 ORDER BY c.fecha_registro DESC
                 LIMIT 300"
            );
            $stmt->execute($this->bindEmpresa());

            return $stmt->fetchAll();
        } catch (\Throwable) {
            return [];
        }
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
             WHERE c.agente_id = ?" . $this->sqlEmpresaPos('c') . "
             GROUP BY c.id
             ORDER BY c.fecha_registro DESC"
        );
        $stmt->execute(array_merge([$agenteId], $this->empresaPos()));
        return $stmt->fetchAll();
    }

    /**
     * Reduce una lista de ids de cliente a los que son de la empresa activa.
     * En modo plataforma la devuelve tal cual, solo saneada.
     *
     * @param  array<int|string> $clienteIds
     * @return list<int>
     */
    private function soloClientesDeLaEmpresa(array $clienteIds): array
    {
        $ids = array_values(array_unique(
            array_filter(array_map('intval', $clienteIds), static fn(int $i): bool => $i > 0)
        ));

        if ($ids === [] || !$this->filtraPorEmpresa()) {
            return $ids;
        }

        $ph   = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db()->prepare(
            "SELECT id FROM clientes WHERE id IN ({$ph})" . $this->sqlEmpresaPos()
        );
        $stmt->execute(array_merge($ids, $this->empresaPos()));

        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    /** True si el agente es de la empresa activa (o si no hay filtro activo). */
    private function perteneceALaEmpresaActiva(int $agenteId): bool
    {
        if ($agenteId <= 0) {
            return false;
        }

        if (!$this->filtraPorEmpresa()) {
            return true;
        }

        $stmt = $this->db()->prepare(
            'SELECT 1 FROM agentes WHERE id = ?' . $this->sqlEmpresaPos() . ' LIMIT 1'
        );
        $stmt->execute(array_merge([$agenteId], $this->empresaPos()));

        return (bool) $stmt->fetchColumn();
    }

    public function reasignarClientes(array $clienteIds, int $nuevoAsesorId): int
    {
        if (empty($clienteIds)) return 0;

        $db = $this->db();

        // Acotar a la empresa activa ANTES de tocar nada. Los ids llegan de un
        // POST: sin este filtro, un coordinador podría reasignar —y por tanto
        // ver— clientes de otra empresa con solo cambiar los números. Al
        // reducir aquí la lista, todas las sentencias de abajo quedan seguras
        // por construcción, sin repetir el filtro en cada una.
        $clienteIds = $this->soloClientesDeLaEmpresa($clienteIds);

        if ($clienteIds === []) return 0;

        // El asesor destino también debe ser de la empresa activa: si no, los
        // clientes saldrían de ella por la puerta de atrás.
        if (!$this->perteneceALaEmpresaActiva($nuevoAsesorId)) return 0;

        $placeholders = implode(',', array_fill(0, count($clienteIds), '?'));

        // 0. Obtener nombres de los clientes ANTES de reasignar (para el mensaje de notificación)
        $clientesInfo = [];
        try {
            // tenant-ok: $clienteIds ya quedó acotado a la empresa activa arriba
            $stmtInfo = $db->prepare(
                "SELECT id, nombre, apellido_paterno FROM clientes WHERE id IN ({$placeholders})"
            );
            $stmtInfo->execute($clienteIds);
            foreach ($stmtInfo->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $clientesInfo[(int)$row['id']] = trim($row['nombre'] . ' ' . $row['apellido_paterno']);
            }
        } catch (\Throwable) {}

        // 1. Reasignar el cliente al nuevo asesor
        // tenant-ok: $clienteIds ya quedó acotado a la empresa activa arriba
        $stmt = $db->prepare(
            "UPDATE clientes SET agente_id = ? WHERE id IN ({$placeholders})"
        );
        $stmt->execute([$nuevoAsesorId, ...$clienteIds]);
        $total = $stmt->rowCount();

        // 2. Reasignar pólizas vinculadas (para que el nuevo asesor las vea)
        try {
            // tenant-ok: las pólizas cuelgan de clientes ya acotados a la empresa
            $db->prepare(
                "UPDATE polizas SET agente_id = ? WHERE cliente_id IN ({$placeholders}) AND eliminado_en IS NULL"
            )->execute([$nuevoAsesorId, ...$clienteIds]);
        } catch (\Throwable) {}

        // 3. Registrar en historial de cada cliente
        try {
            foreach ($clienteIds as $cid) {
                // tenant-ok: el historial cuelga de un cliente ya acotado a la empresa
                $db->prepare(
                    "INSERT INTO historial_cliente (cliente_id, agente_id, tipo, titulo, descripcion, fecha)
                     VALUES (?, ?, 'nota', '↔️ Cliente reasignado', 'Cliente transferido al asesor actual por un coordinador/superadmin.', NOW())"
                )->execute([$cid, $nuevoAsesorId]);
            }
        } catch (\Throwable) {}

        // 4. Notificar in-app al nuevo asesor por cada cliente reasignado
        try {
            $notifModel = new NotificacionModel();
            foreach ($clienteIds as $cid) {
                $nombreCliente = $clientesInfo[$cid] ?? 'Cliente';
                $notifModel->crear(
                    $nuevoAsesorId,
                    'sistema',
                    '↔️ Cliente reasignado',
                    "{$nombreCliente} te fue reasignado. Revísalo y contáctalo cuanto antes.",
                    (string) $cid
                );
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
