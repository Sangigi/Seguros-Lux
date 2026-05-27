<?php
declare(strict_types=1);

namespace App\Models;
use App\Services\QueryCache;
class HistorialModel extends BaseModel
{
    protected string $table = 'historial_cliente';

    protected array $defaultColumns = [
        'id', 'cliente_id', 'agente_id', 'tipo', 'titulo', 'descripcion', 'fecha',
    ];

    protected array $fillable = [
        'cliente_id'  => 'int',
        'agente_id'   => 'int',
        'tipo'        => 'string',
        'titulo'      => 'string',
        'descripcion' => 'string',
    ];

    /**
     * Registra un evento en el historial del cliente.
     */
    public function registrar(int $clienteId, int $agenteId, string $tipo, string $titulo, string $descripcion = ''): int 
    {
        return $this->insert([
            'cliente_id'  => $clienteId,
            'agente_id'   => $agenteId,
            'tipo'        => $tipo,
            'titulo'      => $titulo,
            'descripcion' => $descripcion
        ]);
    }

    public function getByClienteId(int $clienteId): array
    {
        $stmt = $this->db()->prepare(
            'SELECT * FROM historial_cliente WHERE cliente_id = ? ORDER BY fecha DESC'
        );
        $stmt->execute([$clienteId]);
        return $stmt->fetchAll();
    }

    public function getByClienteIdConAgente(int $clienteId, int $limit = 200): array
    {
        $stmt = $this->db()->prepare(
            "SELECT h.tipo, h.titulo, h.descripcion, h.fecha,
                    CONCAT(a.nombre,' ',a.apellido) AS agente_nombre
             FROM historial_cliente h
             LEFT JOIN agentes a ON a.id = h.agente_id
             WHERE h.cliente_id = ? ORDER BY h.fecha DESC LIMIT ?"
        );
        $stmt->execute([$clienteId, $limit]);
        return $stmt->fetchAll();
    }

    public function getRecentByAgenteId(int $agenteId, int $limit = 8): array
    {
        return QueryCache::remember(
            table: 'historial_cliente',
            key:   "recentActivity:{$agenteId}:{$limit}",
            ttl:   60, // TTL más corto (60s) porque el historial es muy dinámico
            fn:    function () use ($agenteId, $limit): array {
                $stmt = $this->db()->prepare(
                    "SELECT h.tipo, h.titulo AS actividad_desc, h.fecha,
                            CONCAT(cl.nombre,' ',cl.apellido_paterno) AS cliente_nombre
                     FROM historial_cliente h
                     JOIN clientes cl ON cl.id = h.cliente_id
                     WHERE h.agente_id = ? ORDER BY h.fecha DESC LIMIT ?"
                );
                $stmt->execute([$agenteId, $limit]);
                return $stmt->fetchAll();
            }
        );
    }
}