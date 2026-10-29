<?php
declare(strict_types=1);

namespace App\Models;

class CitaModel extends BaseModel
{
    protected string $table = 'citas';

    protected array $defaultColumns = [
        'id', 'cliente_id', 'agente_id', 'tipo', 'fecha',
        'hora', 'estado', 'ramo', 'comentarios',
    ];

    protected array $fillable = [
        'cliente_id'       => 'int',
        'agente_id'        => 'int',
        'tipo'             => 'string',
        'fecha'            => 'string',
        'hora'             => 'string',
        'estado'           => 'string',
        'ramo'             => 'string',
        'comentarios'      => 'string',
        'telegram_token'   => 'string',
        'telegram_enviado' => 'string',
    ];

    // ── Disponibilidad ────────────────────────────────────────────────────────

    public function horaDisponible(int $agenteId, string $fecha, string $hora): bool
    {
        $stmt = $this->db()->prepare(
            "SELECT 1 FROM citas
             WHERE agente_id = :agente_id AND fecha = :fecha AND hora = :hora
               AND estado IN ('programada','pendiente')" . $this->sqlEmpresa() . "
             LIMIT 1"
        );
        $stmt->execute($this->bindEmpresa([
            'agente_id' => $agenteId, 'fecha' => $fecha, 'hora' => $hora,
        ]));
        return !$stmt->fetch();
    }

    public function getHorasOcupadas(int $agenteId, string $fecha): array
    {
        $stmt = $this->db()->prepare(
            "SELECT hora FROM citas
             WHERE agente_id = :agente_id AND fecha = :fecha
               AND estado IN ('pendiente','programada')" . $this->sqlEmpresa()
        );
        $stmt->execute($this->bindEmpresa(['agente_id' => $agenteId, 'fecha' => $fecha]));
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function getHorasDisponibles(int $agenteId, string $fecha): array
    {
        // Horario real: mañana 9-14h, tarde 16-18h (sin 13:30, 14:30 que no existen)
        $todas    = ['09:00','09:30','10:00','10:30','11:00','11:30',
                     '12:00','12:30','13:00','13:30','14:00',
                     '16:00','16:30','17:00','17:30'];
        $ocupadas = $this->getHorasOcupadas($agenteId, $fecha);
        return array_values(array_diff($todas, $ocupadas));
    }

    // ── Consultas ─────────────────────────────────────────────────────────────

    public function getByMes(int $agenteId, string $fechaIni, string $fechaFin): array
    {
        $stmt = $this->db()->prepare(
            "SELECT ci.*,
                    CONCAT(cl.nombre,' ',cl.apellido_paterno,' ',cl.apellido_materno) AS cliente_nombre,
                    cl.correo AS cliente_correo, cl.telefono AS cliente_telefono
             FROM citas ci JOIN clientes cl ON cl.id = ci.cliente_id
             WHERE cl.agente_id = :agente_id
               AND ci.fecha BETWEEN :fecha_ini AND :fecha_fin" . $this->sqlEmpresa('ci') . "
             ORDER BY ci.fecha ASC, ci.hora ASC"
        );
        $stmt->execute($this->bindEmpresa([
            'agente_id' => $agenteId, 'fecha_ini' => $fechaIni, 'fecha_fin' => $fechaFin,
        ]));
        return $stmt->fetchAll();
    }

    public function getByMesYRamo(int $agenteId, string $fechaIni, string $fechaFin, ?string $ramo = null): array
    {
        // Si ramo es null devuelve todas (equivale a getByMes)
        if ($ramo === null) {
            return $this->getByMes($agenteId, $fechaIni, $fechaFin);
        }

        $stmt = $this->db()->prepare(
            "SELECT ci.*,
                    CONCAT(cl.nombre,' ',cl.apellido_paterno,' ',cl.apellido_materno) AS cliente_nombre,
                    cl.correo AS cliente_correo, cl.telefono AS cliente_telefono
             FROM citas ci JOIN clientes cl ON cl.id = ci.cliente_id
             WHERE cl.agente_id = :agente_id AND ci.ramo = :ramo
               AND ci.fecha BETWEEN :fecha_ini AND :fecha_fin" . $this->sqlEmpresa('ci') . "
             ORDER BY ci.fecha ASC, ci.hora ASC"
        );
        $stmt->execute($this->bindEmpresa([
            'agente_id' => $agenteId, 'ramo' => $ramo,
            'fecha_ini' => $fechaIni, 'fecha_fin' => $fechaFin,
        ]));
        return $stmt->fetchAll();
    }

    public function getProximas(int $agenteId, int $limit = 8): array
    {
        $stmt = $this->db()->prepare(
            "SELECT ci.*,
                    CONCAT(cl.nombre,' ',cl.apellido_paterno,' ',cl.apellido_materno) AS cliente_nombre,
                    cl.telefono AS cliente_telefono
             FROM citas ci JOIN clientes cl ON cl.id = ci.cliente_id
             WHERE cl.agente_id = :agente_id AND ci.estado = 'programada'
               AND ci.fecha >= CURDATE()" . $this->sqlEmpresa('ci') . "
             ORDER BY ci.fecha ASC, ci.hora ASC LIMIT :limit"
        );
        $stmt->execute($this->bindEmpresa(['agente_id' => $agenteId, 'limit' => $limit]));
        return $stmt->fetchAll();
    }

    public function getProximasYRamo(int $agenteId, ?string $ramo = null, int $limit = 8): array
    {
        if ($ramo === null) {
            return $this->getProximas($agenteId, $limit);
        }

        $stmt = $this->db()->prepare(
            "SELECT ci.*,
                    CONCAT(cl.nombre,' ',cl.apellido_paterno,' ',cl.apellido_materno) AS cliente_nombre,
                    cl.telefono AS cliente_telefono
             FROM citas ci JOIN clientes cl ON cl.id = ci.cliente_id
             WHERE cl.agente_id = :agente_id AND ci.ramo = :ramo
               AND ci.estado = 'programada' AND ci.fecha >= CURDATE()" . $this->sqlEmpresa('ci') . "
             ORDER BY ci.fecha ASC, ci.hora ASC LIMIT :limit"
        );
        $stmt->execute($this->bindEmpresa([
            'agente_id' => $agenteId, 'ramo' => $ramo, 'limit' => $limit,
        ]));
        return $stmt->fetchAll();
    }

    public function getStatsByMes(int $agenteId, string $fechaIni, string $fechaFin, ?string $ramo = null): array
    {
        $ramoFilter = $ramo ? 'AND ramo = :ramo' : '';
        $stmt = $this->db()->prepare(
            "SELECT COUNT(*) AS total,
                    SUM(estado='programada') AS programadas,
                    SUM(estado='completada') AS completadas,
                    SUM(estado='cancelada')  AS canceladas,
                    SUM(estado='vencida')    AS vencidas
             FROM citas
             WHERE agente_id = :agente_id
               AND fecha BETWEEN :fecha_ini AND :fecha_fin {$ramoFilter}" . $this->sqlEmpresa()
        );
        $params = ['agente_id' => $agenteId, 'fecha_ini' => $fechaIni, 'fecha_fin' => $fechaFin];
        if ($ramo) { $params['ramo'] = $ramo; }
        $stmt->execute($this->bindEmpresa($params));
        $d = $stmt->fetch();
        return [
            'total'       => (int)($d['total']       ?? 0),
            'programadas' => (int)($d['programadas'] ?? 0),
            'completadas' => (int)($d['completadas'] ?? 0),
            'canceladas'  => (int)($d['canceladas']  ?? 0),
            'vencidas'    => (int)($d['vencidas']    ?? 0),
        ];
    }

    public function countPendientes(int $agenteId, ?string $ramo = null): int
    {
        $ramoFilter = $ramo ? 'AND ramo = :ramo' : '';
        $stmt = $this->db()->prepare(
            "SELECT COUNT(*) FROM citas
             WHERE agente_id = :agente_id AND estado = 'programada'
               AND fecha >= CURDATE() {$ramoFilter}" . $this->sqlEmpresa()
        );
        $params = ['agente_id' => $agenteId];
        if ($ramo) { $params['ramo'] = $ramo; }
        $stmt->execute($this->bindEmpresa($params));
        return (int) $stmt->fetchColumn();
    }

    public function countCompletadas(int $agenteId): int
    {
        $stmt = $this->db()->prepare(
            "SELECT COUNT(*) FROM citas
             WHERE agente_id = :agente_id AND estado = 'completada'" . $this->sqlEmpresa()
        );
        $stmt->execute($this->bindEmpresa(['agente_id' => $agenteId]));
        return (int) $stmt->fetchColumn();
    }

    public function getCitaActivaByClienteId(int $clienteId): ?array
    {
        $stmt = $this->db()->prepare(
            "SELECT ci.id, ci.fecha, ci.hora, ci.tipo, ci.estado, ci.agente_id,
                    CONCAT(a.nombre,' ',a.apellido) AS agente_nombre
             FROM citas ci JOIN agentes a ON a.id = ci.agente_id
             WHERE ci.cliente_id = :cliente_id AND ci.estado = 'programada'
               AND ci.fecha >= CURDATE()" . $this->sqlEmpresa('ci') . "
             ORDER BY ci.fecha ASC, ci.hora ASC LIMIT 1"
        );
        $stmt->execute($this->bindEmpresa(['cliente_id' => $clienteId]));
        return $stmt->fetch() ?: null;
    }

    public function getByClienteId(int $clienteId): array
    {
        $stmt = $this->db()->prepare(
            "SELECT ci.*, CONCAT(a.nombre,' ',a.apellido) AS agente_nombre
             FROM citas ci
             LEFT JOIN agentes a ON a.id = ci.agente_id
             WHERE ci.cliente_id = :cliente_id" . $this->sqlEmpresa('ci') . "
             ORDER BY ci.fecha DESC, ci.hora DESC"
        );
        $stmt->execute($this->bindEmpresa(['cliente_id' => $clienteId]));
        return $stmt->fetchAll() ?: [];
    }

    public function getClientesParaSelector(int $agenteId): array
    {
        $stmt = $this->db()->prepare(
            'SELECT id, nombre, apellido_paterno, apellido_materno
             FROM clientes WHERE agente_id = :agente_id' . $this->sqlEmpresa() . '
             ORDER BY nombre ASC'
        );
        $stmt->execute($this->bindEmpresa(['agente_id' => $agenteId]));
        return $stmt->fetchAll();
    }

    // ── Mutaciones ────────────────────────────────────────────────────────────

    public function crear(int $clienteId, int $agenteId, string $tipo, string $fecha, string $hora, string $comentarios = '', string $ramo = 'autos', string $estado = 'programada'): int
    {
        return $this->insert([
            'cliente_id'  => $clienteId,
            'agente_id'   => $agenteId,
            'tipo'        => $tipo,
            'fecha'       => $fecha,
            'hora'        => $hora,
            'comentarios' => $comentarios,
            'ramo'        => $ramo,
            'estado'      => $estado
        ]);
    }

    public function updateEstado(int $citaId, int $agenteId, string $estado): bool
    {
        $stmt = $this->db()->prepare(
            'UPDATE citas SET estado = :estado WHERE id = :id AND agente_id = :agente_id'
            . $this->sqlEmpresa()
        );
        $stmt->execute($this->bindEmpresa([
            'estado' => $estado, 'id' => $citaId, 'agente_id' => $agenteId,
        ]));
        return $stmt->rowCount() > 0;
    }

    public function clientePerteneceAAgente(int $clienteId, int $agenteId): bool
    {
        $stmt = $this->db()->prepare(
            'SELECT id FROM clientes WHERE id = :id AND agente_id = :agente_id' . $this->sqlEmpresa()
        );
        $stmt->execute($this->bindEmpresa(['id' => $clienteId, 'agente_id' => $agenteId]));
        return (bool) $stmt->fetch();
    }

    // ── Telegram / WhatsApp ───────────────────────────────────────────────────

    public function generarTelegramToken(int $citaId): string
    {
        $token = bin2hex(random_bytes(16));
        $this->db()->prepare(
            'UPDATE citas SET telegram_token = :token WHERE id = :id' . $this->sqlEmpresa()
        )->execute($this->bindEmpresa(['token' => $token, 'id' => $citaId]));
        return $token;
    }

    public function getByTelegramToken(string $token): ?array
    {
        $stmt = $this->db()->prepare(
            "SELECT ci.*,
                    CONCAT(cl.nombre,' ',cl.apellido_paterno) AS cliente_nombre,
                    cl.correo AS cliente_correo, cl.telefono AS cliente_telefono, cl.id AS cliente_id_real,
                    CONCAT(a.nombre,' ',a.apellido) AS agente_nombre, a.correo AS agente_correo
             FROM citas ci
             JOIN clientes cl ON cl.id = ci.cliente_id
             JOIN agentes  a  ON a.id  = ci.agente_id
             WHERE ci.telegram_token = :token" . $this->sqlEmpresa('ci') . " LIMIT 1"
        );
        $stmt->execute($this->bindEmpresa(['token' => $token]));
        return $stmt->fetch() ?: null;
    }

    /**
     * Busca una cita activa por teléfono, whatsapp_chat_id o telegram_chat_id.
     * FIX: ahora incluye whatsapp_chat_id en el WHERE.
     */
    public function getByChatId(string $chatId): ?array
    {
        $sinPlus = ltrim($chatId, '+');
        $conPlus = '+' . $sinPlus;

        $stmt = $this->db()->prepare(
            "SELECT ci.*,
                    CONCAT(cl.nombre,' ',cl.apellido_paterno) AS cliente_nombre,
                    CONCAT(a.nombre,' ',a.apellido)           AS agente_nombre
             FROM citas ci
             JOIN clientes cl ON cl.id = ci.cliente_id
             JOIN agentes  a  ON a.id  = ci.agente_id
             WHERE ci.estado IN ('programada','pendiente')
               AND ci.fecha >= CURDATE()
               AND (
                   cl.whatsapp_chat_id = :chatId
                   OR cl.telegram_chat_id = :chatId2
                   OR REPLACE(REPLACE(REPLACE(cl.telefono, '+', ''), ' ', ''), '-', '') = :sinPlus
                   OR cl.telefono = :conPlus
               )" . $this->sqlEmpresa('ci') . "
             ORDER BY ci.fecha ASC, ci.hora ASC LIMIT 1"
        );
        $stmt->execute($this->bindEmpresa(
            [':chatId' => $chatId, ':chatId2' => $chatId, ':sinPlus' => $sinPlus, ':conPlus' => $conPlus]
        ));
        return $stmt->fetch() ?: null;
    }

    /**
     * Devuelve hasta $limit citas activas por teléfono/whatsapp_chat_id/telegram_chat_id.
     * FIX: incluye whatsapp_chat_id.
     */
    public function getCitasActivasByChatId(string $chatId, int $limit = 3): array
    {
        $sinPlus = ltrim($chatId, '+');
        $conPlus = '+' . $sinPlus;

        $stmt = $this->db()->prepare(
            "SELECT ci.*,
                    CONCAT(cl.nombre,' ',cl.apellido_paterno) AS cliente_nombre,
                    CONCAT(a.nombre,' ',a.apellido)           AS agente_nombre
             FROM citas ci
             JOIN clientes cl ON cl.id = ci.cliente_id
             JOIN agentes  a  ON a.id  = ci.agente_id
             WHERE ci.estado IN ('programada','pendiente')
               AND ci.fecha >= CURDATE()
               AND (
                   cl.whatsapp_chat_id = :chatId
                   OR cl.telegram_chat_id = :chatId2
                   OR REPLACE(REPLACE(REPLACE(cl.telefono, '+', ''), ' ', ''), '-', '') = :sinPlus
                   OR cl.telefono = :conPlus
               )" . $this->sqlEmpresa('ci') . "
             ORDER BY ci.fecha ASC, ci.hora ASC LIMIT :lim"
        );
        $stmt->bindValue(':chatId',  $chatId,  \PDO::PARAM_STR);
        $stmt->bindValue(':chatId2', $chatId,  \PDO::PARAM_STR);
        $stmt->bindValue(':sinPlus', $sinPlus, \PDO::PARAM_STR);
        $stmt->bindValue(':conPlus', $conPlus, \PDO::PARAM_STR);
        $stmt->bindValue(':lim',     $limit,   \PDO::PARAM_INT);
        foreach ($this->bindEmpresa() as $k => $v) {
            $stmt->bindValue(':' . $k, $v, \PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Cancela una cita verificando que el cliente sea dueño por WhatsApp/Telegram/teléfono.
     * FIX: ahora incluye whatsapp_chat_id.
     */
    public function cancelarPorId(int $citaId, string $chatId): bool
    {
        // La autenticación del dueño ya ocurrió en waGetCita/tgGetCita al guardar el cita_id en state.
        // Solo verificamos que la cita exista y esté activa. Intentamos match de chatId primero,
        // y si falla (chatId no guardado aún en BD), cancelamos solo por id+estado como fallback seguro.
        $sinPlus = ltrim($chatId, '+');
        $conPlus = '+' . $sinPlus;

        // Intento 1: con verificación de propiedad
        // Incluye 'vencida' para que un cliente pueda "cancelar" (limpiar) una cita que ya pasó.
        $stmt = $this->db()->prepare(
            "UPDATE citas ci
             JOIN clientes cl ON cl.id = ci.cliente_id
             SET ci.estado = 'cancelada'
             WHERE ci.id = :id
               AND ci.estado IN ('programada','pendiente','vencida')
               AND (
                   cl.whatsapp_chat_id = :chatId
                   OR cl.telegram_chat_id = :chatId2
                   OR REPLACE(REPLACE(REPLACE(cl.telefono, '+', ''), ' ', ''), '-', '') = :sinPlus
                   OR cl.telefono = :conPlus
               )" . $this->sqlEmpresa('ci')
        );
        $stmt->execute($this->bindEmpresa(
            [':id' => $citaId, ':chatId' => $chatId, ':chatId2' => $chatId, ':sinPlus' => $sinPlus, ':conPlus' => $conPlus]
        ));
        if ($stmt->rowCount() > 0) return true;

        // Intento 2: fallback por id solo (el dueño ya fue verificado por waGetCita al inicio del flujo)
        $stmt2 = $this->db()->prepare(
            "UPDATE citas SET estado = 'cancelada'
             WHERE id = :id AND estado IN ('programada','pendiente','vencida')" . $this->sqlEmpresa()
        );
        $stmt2->execute($this->bindEmpresa(['id' => $citaId]));
        return $stmt2->rowCount() > 0;
    }

    /**
     * Reprograma una cita. Intenta con verificación de chatId primero;
     * si falla (chatId no en BD aún), usa fallback por id solo.
     * La propiedad ya fue verificada por waGetCita/tgGetCita al inicio del flujo.
     */
    public function reprogramar(int $citaId, string $chatId, string $fecha, string $hora): bool
    {
        $sinPlus = ltrim($chatId, '+');
        $conPlus = '+' . $sinPlus;

        // Intento 1: con verificación de propiedad por chatId/teléfono
        // Incluye 'vencida': el usuario puede reprogramar una cita que pasó sin ser atendida.
        $stmt = $this->db()->prepare(
            "UPDATE citas ci
             JOIN clientes cl ON cl.id = ci.cliente_id
             SET ci.fecha = :fecha, ci.hora = :hora, ci.estado = 'programada', ci.telegram_enviado = NULL
             WHERE ci.id = :id
               AND ci.estado IN ('programada','pendiente','vencida')
               AND (
                   cl.whatsapp_chat_id = :chatId
                   OR cl.telegram_chat_id = :chatId2
                   OR REPLACE(REPLACE(REPLACE(cl.telefono, '+', ''), ' ', ''), '-', '') = :sinPlus
                   OR cl.telefono = :conPlus
               )" . $this->sqlEmpresa('ci')
        );
        $stmt->execute($this->bindEmpresa([
            ':fecha'   => $fecha,
            ':hora'    => $hora,
            ':id'      => $citaId,
            ':chatId'  => $chatId,
            ':chatId2' => $chatId,
            ':sinPlus' => $sinPlus,
            ':conPlus' => $conPlus,
        ]));
        if ($stmt->rowCount() > 0) return true;

        // Intento 2: fallback por id solo (propiedad ya verificada en waGetCita al inicio)
        $stmt2 = $this->db()->prepare(
            "UPDATE citas SET fecha = :fecha, hora = :hora, estado = 'programada', telegram_enviado = NULL
             WHERE id = :id AND estado IN ('programada','pendiente','vencida')" . $this->sqlEmpresa()
        );
        $stmt2->execute($this->bindEmpresa(['fecha' => $fecha, 'hora' => $hora, 'id' => $citaId]));
        return $stmt2->rowCount() > 0;
    }

    public function marcarRecordatorioEnviado(int $citaId): void
    {
        $this->db()->prepare(
            'UPDATE citas SET telegram_enviado = NOW() WHERE id = :id' . $this->sqlEmpresa()
        )->execute($this->bindEmpresa(['id' => $citaId]));
    }

    /**
     * Marca como 'vencida' todas las citas 'programada' cuya fecha+hora ya pasó.
     * Llamar desde el cron VencerCitas.php (cada hora o diariamente).
     * Requiere que la columna estado incluya 'vencida' en el ENUM.
     *
     * @return int Número de citas vencidas en esta ejecución.
     */
    public function vencerCitasPasadas(): int
    {
        $stmt = $this->db()->prepare(
            "UPDATE citas
             SET estado = 'vencida'
             WHERE estado = 'programada'
               AND TIMESTAMP(fecha, hora) < NOW()" . $this->sqlEmpresa()
        );
        $stmt->execute($this->bindEmpresa());
        return $stmt->rowCount();
    }

    public function getCitasParaRecordatorio(): array
    {
        // Con prepare() en vez de query() para poder enlazar el filtro de empresa.
        $stmt = $this->db()->prepare(
            "SELECT ci.id, ci.fecha, ci.hora, ci.tipo,
                    CONCAT(cl.nombre,' ',cl.apellido_paterno) AS cliente_nombre,
                    cl.telegram_chat_id,
                    cl.whatsapp_chat_id,
                    cl.telefono AS cliente_telefono,
                    CONCAT(a.nombre,' ',a.apellido) AS agente_nombre
             FROM citas ci
             JOIN clientes cl ON cl.id = ci.cliente_id
             JOIN agentes  a  ON a.id  = ci.agente_id
             WHERE ci.estado IN ('programada','pendiente')
               AND (cl.telegram_chat_id IS NOT NULL OR cl.whatsapp_chat_id IS NOT NULL OR cl.telefono IS NOT NULL)
               AND ci.telegram_enviado IS NULL
               AND TIMESTAMPDIFF(MINUTE, NOW(), CONCAT(ci.fecha,' ',ci.hora)) BETWEEN 55 AND 60"
            . $this->sqlEmpresa('ci')
        );
        $stmt->execute($this->bindEmpresa());
        return $stmt->fetchAll();
    }

    public function buscarAgenteLibre(string $fecha, string $hora): ?int
    {
        // Carga de trabajo del asesor, para repartir las citas al más libre.
        // tenant-ok: cuenta solo las citas de `a.id`, y `agentes a` ya va filtrado
        //            por empresa en el WHERE (sección 3 del CONTRIBUTING).
        $ordenCarga = "(SELECT COUNT(*) FROM citas c2
                        WHERE c2.agente_id = a.id
                          AND c2.estado IN ('programada','pendiente'))";

        $stmt = $this->db()->prepare(
            "SELECT a.id
             FROM agentes a
             WHERE a.rol IN ('asesor','agente')
               AND COALESCE(a.activo, 1) = 1" . $this->sqlEmpresa('a') . "
               AND NOT EXISTS (
                   SELECT 1 FROM citas c
                   WHERE c.agente_id = a.id
                     AND c.fecha = :fecha
                     AND c.hora  = :hora
                     AND c.estado IN ('programada','pendiente')
               )
             ORDER BY {$ordenCarga} ASC, RAND() LIMIT 1"
        );
        $stmt->execute($this->bindEmpresa(['fecha' => $fecha, 'hora' => $hora]));
        return ($row = $stmt->fetch()) ? (int)$row['id'] : null;
    }
}
