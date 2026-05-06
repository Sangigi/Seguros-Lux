<?php
declare(strict_types=1);

namespace App\Models;

/**
 * App/Models/NotificacionModel.php
 */
class NotificacionModel extends BaseModel
{
    protected string $table = 'notificaciones';

    protected array $fillable = [
        'agente_id'  => 'int',
        'tipo'       => 'string',
        'titulo'     => 'string',
        'mensaje'    => 'string',
        'referencia' => 'string',
        'leida'      => 'int',
    ];

    public function crear(int $agenteId, string $tipo, string $titulo, string $mensaje, ?string $referencia = null): int
    {
        return $this->insert([
            'agente_id'  => $agenteId,
            'tipo'       => $tipo,
            'titulo'     => $titulo,
            'mensaje'    => $mensaje,
            'referencia' => $referencia,
        ]);
    }

    public function getNoLeidas(int $agenteId, int $limit = 20): array
    {
        $st = $this->db()->prepare(
            'SELECT * FROM notificaciones
             WHERE agente_id = ? AND leida = 0
             ORDER BY creada_en DESC LIMIT ?'
        );
        $st->execute([$agenteId, $limit]);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function contarNoLeidas(int $agenteId): int
    {
        $st = $this->db()->prepare(
            'SELECT COUNT(*) FROM notificaciones WHERE agente_id = ? AND leida = 0'
        );
        $st->execute([$agenteId]);
        return (int) $st->fetchColumn();
    }

    public function marcarLeida(int $id, int $agenteId): void
    {
        $this->db()->prepare(
            'UPDATE notificaciones SET leida = 1 WHERE id = ? AND agente_id = ?'
        )->execute([$id, $agenteId]);
    }

    public function marcarTodasLeidas(int $agenteId): void
    {
        $this->db()->prepare(
            'UPDATE notificaciones SET leida = 1 WHERE agente_id = ?'
        )->execute([$agenteId]);
    }

    public function eliminar(int $id, int $agenteId): void
    {
        $this->db()->prepare(
            'DELETE FROM notificaciones WHERE id = ? AND agente_id = ?'
        )->execute([$id, $agenteId]);
    }

    public function eliminarTodas(int $agenteId): void
    {
        $this->db()->prepare(
            'DELETE FROM notificaciones WHERE agente_id = ?'
        )->execute([$agenteId]);
    }

    public function getRecientes(int $agenteId, int $limit = 50): array
    {
        $st = $this->db()->prepare(
            'SELECT * FROM notificaciones WHERE agente_id = ?
             ORDER BY creada_en DESC LIMIT ?'
        );
        $st->execute([$agenteId, $limit]);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }
}