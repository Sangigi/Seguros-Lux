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
             WHERE agente_id = ? AND fecha = ? AND hora = ?
               AND estado IN ('programada','pendiente')
             LIMIT 1"
        );
        $stmt->execute([$agenteId, $fecha, $hora]);
        return !$stmt->fetch();
    }

    public function getHorasOcupadas(int $agenteId, string $fecha): array
    {
        $stmt = $this->db()->prepare(
            "SELECT hora FROM citas
             WHERE agente_id = ? AND fecha = ?
               AND estado IN ('pendiente','programada')"
        );
        $stmt->execute([$agenteId, $fecha]);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function getHorasDisponibles(int $agenteId, string $fecha): array
    {
        $todas    = ['09:00','09:30','10:00','10:30','11:00','11:30',
                     '12:00','12:30','13:00','13:30','14:00','14:30',
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
             WHERE cl.agente_id = ? AND ci.fecha BETWEEN ? AND ?
             ORDER BY ci.fecha ASC, ci.hora ASC"
        );
        $stmt->execute([$agenteId, $fechaIni, $fechaFin]);
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
             WHERE cl.agente_id = ? AND ci.ramo = ? AND ci.fecha BETWEEN ? AND ?
             ORDER BY ci.fecha ASC, ci.hora ASC"
        );
        $stmt->execute([$agenteId, $ramo, $fechaIni, $fechaFin]);
        return $stmt->fetchAll();
    }

    public function getProximas(int $agenteId, int $limit = 8): array
    {
        $stmt = $this->db()->prepare(
            "SELECT ci.*,
                    CONCAT(cl.nombre,' ',cl.apellido_paterno,' ',cl.apellido_materno) AS cliente_nombre,
                    cl.telefono AS cliente_telefono
             FROM citas ci JOIN clientes cl ON cl.id = ci.cliente_id
             WHERE cl.agente_id = ? AND ci.estado = 'programada' AND ci.fecha >= CURDATE()
             ORDER BY ci.fecha ASC, ci.hora ASC LIMIT ?"
        );
        $stmt->execute([$agenteId, $limit]);
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
             WHERE cl.agente_id = ? AND ci.ramo = ? AND ci.estado = 'programada' AND ci.fecha >= CURDATE()
             ORDER BY ci.fecha ASC, ci.hora ASC LIMIT ?"
        );
        $stmt->execute([$agenteId, $ramo, $limit]);
        return $stmt->fetchAll();
    }

    public function getStatsByMes(int $agenteId, string $fechaIni, string $fechaFin, ?string $ramo = null): array
    {
        $ramoFilter = $ramo ? 'AND ramo = ?' : '';
        $stmt = $this->db()->prepare(
            "SELECT COUNT(*) AS total,
                    SUM(estado='programada') AS programadas,
                    SUM(estado='completada') AS completadas,
                    SUM(estado='cancelada')  AS canceladas
             FROM citas
             WHERE agente_id = ? AND fecha BETWEEN ? AND ? {$ramoFilter}"
        );
        $params = $ramo ? [$agenteId, $fechaIni, $fechaFin, $ramo] : [$agenteId, $fechaIni, $fechaFin];
        $stmt->execute($params);
        $d = $stmt->fetch();
        return [
            'total'       => (int)($d['total']       ?? 0),
            'programadas' => (int)($d['programadas'] ?? 0),
            'completadas' => (int)($d['completadas'] ?? 0),
            'canceladas'  => (int)($d['canceladas']  ?? 0),
        ];
    }

    public function countPendientes(int $agenteId, ?string $ramo = null): int
    {
        $ramoFilter = $ramo ? 'AND ramo = ?' : '';
        $stmt = $this->db()->prepare(
            "SELECT COUNT(*) FROM citas
             WHERE agente_id = ? AND estado = 'programada' AND fecha >= CURDATE() {$ramoFilter}"
        );
        $params = $ramo ? [$agenteId, $ramo] : [$agenteId];
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function countCompletadas(int $agenteId): int
    {
        $stmt = $this->db()->prepare("SELECT COUNT(*) FROM citas WHERE agente_id = ? AND estado = 'completada'");
        $stmt->execute([$agenteId]);
        return (int) $stmt->fetchColumn();
    }

    public function getCitaActivaByClienteId(int $clienteId): ?array
    {
        $stmt = $this->db()->prepare(
            "SELECT ci.id, ci.fecha, ci.hora, ci.tipo, ci.estado, ci.agente_id,
                    CONCAT(a.nombre,' ',a.apellido) AS agente_nombre
             FROM citas ci JOIN agentes a ON a.id = ci.agente_id
             WHERE ci.cliente_id = ? AND ci.estado = 'programada' AND ci.fecha >= CURDATE()
             ORDER BY ci.fecha ASC, ci.hora ASC LIMIT 1"
        );
        $stmt->execute([$clienteId]);
        return $stmt->fetch() ?: null;
    }

    public function getByClienteId(int $clienteId): array
    {
        $stmt = $this->db()->prepare(
            "SELECT ci.*, CONCAT(a.nombre,' ',a.apellido) AS agente_nombre
             FROM citas ci
             LEFT JOIN agentes a ON a.id = ci.agente_id
             WHERE ci.cliente_id = ?
             ORDER BY ci.fecha DESC, ci.hora DESC"
        );
        $stmt->execute([$clienteId]);
        return $stmt->fetchAll() ?: [];
    }

    public function getClientesParaSelector(int $agenteId): array
    {
        $stmt = $this->db()->prepare(
            'SELECT id, nombre, apellido_paterno, apellido_materno
             FROM clientes WHERE agente_id = ? ORDER BY nombre ASC'
        );
        $stmt->execute([$agenteId]);
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
        $stmt = $this->db()->prepare('UPDATE citas SET estado = ? WHERE id = ? AND agente_id = ?');
        $stmt->execute([$estado, $citaId, $agenteId]);
        return $stmt->rowCount() > 0;
    }

    public function clientePerteneceAAgente(int $clienteId, int $agenteId): bool
    {
        $stmt = $this->db()->prepare('SELECT id FROM clientes WHERE id = ? AND agente_id = ?');
        $stmt->execute([$clienteId, $agenteId]);
        return (bool) $stmt->fetch();
    }

    // ── Telegram / WhatsApp ───────────────────────────────────────────────────

    public function generarTelegramToken(int $citaId): string
    {
        $token = bin2hex(random_bytes(16));
        $this->db()->prepare('UPDATE citas SET telegram_token = ? WHERE id = ?')->execute([$token, $citaId]);
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
             WHERE ci.telegram_token = ? LIMIT 1"
        );
        $stmt->execute([$token]);
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
                   OR cl.telegram_chat_id = :chatId
                   OR REPLACE(REPLACE(REPLACE(cl.telefono, '+', ''), ' ', ''), '-', '') = :sinPlus
                   OR cl.telefono = :conPlus
               )
             ORDER BY ci.fecha ASC, ci.hora ASC LIMIT 1"
        );
        $stmt->execute([':chatId' => $chatId, ':sinPlus' => $sinPlus, ':conPlus' => $conPlus]);
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
                   OR cl.telegram_chat_id = :chatId
                   OR REPLACE(REPLACE(REPLACE(cl.telefono, '+', ''), ' ', ''), '-', '') = :sinPlus
                   OR cl.telefono = :conPlus
               )
             ORDER BY ci.fecha ASC, ci.hora ASC LIMIT :lim"
        );
        $stmt->bindValue(':chatId',  $chatId,  \PDO::PARAM_STR);
        $stmt->bindValue(':sinPlus', $sinPlus, \PDO::PARAM_STR);
        $stmt->bindValue(':conPlus', $conPlus, \PDO::PARAM_STR);
        $stmt->bindValue(':lim',     $limit,   \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Cancela una cita verificando que el cliente sea dueño por WhatsApp/Telegram/teléfono.
     * FIX: ahora incluye whatsapp_chat_id.
     */
    public function cancelarPorId(int $citaId, string $chatId): bool
    {
        $sinPlus = ltrim($chatId, '+');
        $conPlus = '+' . $sinPlus;

        $stmt = $this->db()->prepare(
            "UPDATE citas ci
             JOIN clientes cl ON cl.id = ci.cliente_id
             SET ci.estado = 'cancelada'
             WHERE ci.id = :id
               AND ci.estado IN ('programada','pendiente')
               AND (
                   cl.whatsapp_chat_id = :chatId
                   OR cl.telegram_chat_id = :chatId
                   OR REPLACE(REPLACE(REPLACE(cl.telefono, '+', ''), ' ', ''), '-', '') = :sinPlus
                   OR cl.telefono = :conPlus
               )"
        );
        $stmt->execute([':id' => $citaId, ':chatId' => $chatId, ':sinPlus' => $sinPlus, ':conPlus' => $conPlus]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Reprograma una cita verificando propiedad por WhatsApp/Telegram/teléfono.
     * FIX: incluye whatsapp_chat_id.
     */
    public function reprogramar(int $citaId, string $chatId, string $fecha, string $hora): bool
    {
        $sinPlus = ltrim($chatId, '+');
        $conPlus = '+' . $sinPlus;

        $stmt = $this->db()->prepare(
            "UPDATE citas ci
             JOIN clientes cl ON cl.id = ci.cliente_id
             SET ci.fecha = :fecha, ci.hora = :hora, ci.telegram_enviado = NULL
             WHERE ci.id = :id
               AND ci.estado IN ('programada','pendiente')
               AND (
                   cl.whatsapp_chat_id = :chatId
                   OR cl.telegram_chat_id = :chatId
                   OR REPLACE(REPLACE(REPLACE(cl.telefono, '+', ''), ' ', ''), '-', '') = :sinPlus
                   OR cl.telefono = :conPlus
               )"
        );
        $stmt->execute([
            ':fecha'   => $fecha,
            ':hora'    => $hora,
            ':id'      => $citaId,
            ':chatId'  => $chatId,
            ':sinPlus' => $sinPlus,
            ':conPlus' => $conPlus,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function marcarRecordatorioEnviado(int $citaId): void
    {
        $this->db()->prepare('UPDATE citas SET telegram_enviado = NOW() WHERE id = ?')->execute([$citaId]);
    }

    public function getCitasParaRecordatorio(): array
    {
        return $this->db()->query(
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
        )->fetchAll();
    }

    public function buscarAgenteLibre(string $fecha, string $hora): ?int
    {
        $stmt = $this->db()->prepare(
            "SELECT a.id
             FROM agentes a
             WHERE a.rol IN ('asesor','agente')
               AND COALESCE(a.activo, 1) = 1
               AND NOT EXISTS (
                   SELECT 1 FROM citas c
                   WHERE c.agente_id = a.id
                     AND c.fecha = ?
                     AND c.hora  = ?
                     AND c.estado IN ('programada','pendiente')
               )
             ORDER BY (
                 SELECT COUNT(*) FROM citas c2
                 WHERE c2.agente_id = a.id
                   AND c2.estado IN ('programada','pendiente')
             ) ASC LIMIT 1"
        );
        $stmt->execute([$fecha, $hora]);
        return ($row = $stmt->fetch()) ? (int)$row['id'] : null;
    }
}