<?php
declare(strict_types=1);

namespace App\Models;

/**
 * App/Models/PolizaModel.php
 * Gestión de pólizas del cliente (subida al iDrive/S3, caducidad, notificaciones).
 */
class PolizaModel extends BaseModel
{
    protected string $table = 'polizas';
    
    protected array $fillable = [
        'cliente_id'            => 'int',
        'cotizacion_id'         => 'int',
        'agente_id'             => 'int',
        'nombre_archivo'        => 'string',
        'nombre_original'       => 'string',
        'ruta'                  => 'string',
        'compania'              => 'string',
        'numero_poliza'         => 'string',
        'fecha_inicio'          => 'string',
        'fecha_fin'             => 'string',
        'frecuencia_renovacion' => 'string',
        'notas'                 => 'string',
        'estado'                => 'string'
    ];

    // ── Crear póliza ─────────────────────────────────────────────────────────
    public function crear(array $data): int
    {
        $data['frecuencia_renovacion'] = $data['frecuencia_renovacion'] ?? 'anual';
        return $this->insert($data);
    }

    // ── Obtener todas del agente ─────────────────────────────────────────────
    public function getAllConCliente(int $agenteId): array
    {
        $sql = 'SELECT p.*,
                       CONCAT(c.nombre," ",c.apellido_paterno) AS cliente_nombre,
                       c.id AS cliente_id_ref,
                       DATEDIFF(p.fecha_fin, CURDATE()) AS dias_restantes
                FROM polizas p
                JOIN clientes c ON c.id = p.cliente_id
                WHERE p.agente_id = ?
                  AND p.eliminado_en IS NULL
                ORDER BY p.fecha_fin ASC';
                
        $st = $this->db()->prepare($sql);
        $st->execute([$agenteId]);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function listarPorAgente(int $agenteId): array
    {
        $st = $this->db()->prepare(
            'SELECT p.*,
                    CONCAT(c.nombre," ",c.apellido_paterno) AS cliente_nombre,
                    DATEDIFF(p.fecha_fin, CURDATE())        AS dias_restantes
             FROM   polizas p
             JOIN   clientes c ON c.id = p.cliente_id
             WHERE  p.agente_id = ?
               AND  p.eliminado_en IS NULL
             ORDER  BY p.fecha_fin ASC'
        );
        $st->execute([$agenteId]);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function listarPorCliente(int $clienteId, int $agenteId): array
    {
        $st = $this->db()->prepare(
            'SELECT p.*, DATEDIFF(p.fecha_fin, CURDATE()) AS dias_restantes
             FROM   polizas p
             WHERE  p.cliente_id = ? AND p.agente_id = ?
               AND  p.eliminado_en IS NULL
             ORDER  BY p.fecha_fin ASC'
        );
        $st->execute([$clienteId, $agenteId]);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getById(int $id, int $agenteId): ?array
    {
        $st = $this->db()->prepare(
            'SELECT p.*, DATEDIFF(p.fecha_fin, CURDATE()) AS dias_restantes
             FROM polizas p WHERE p.id = ? AND p.agente_id = ? AND p.eliminado_en IS NULL'
        );
        $st->execute([$id, $agenteId]);
        return $st->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function actualizar(int $id, int $agenteId, array $data): bool
    {
        $campos = [];
        $vals   = [];
        $allow  = ['compania','numero_poliza','fecha_inicio','fecha_fin','frecuencia_renovacion','notas','estado'];
        foreach ($allow as $f) {
            if (array_key_exists($f, $data)) {
                $campos[] = "`{$f}` = ?";
                $vals[]   = $data[$f];
            }
        }
        if (empty($campos)) return false;
        $vals[] = $id;
        $vals[] = $agenteId;
        $st = $this->db()->prepare('UPDATE polizas SET ' . implode(', ', $campos) . ' WHERE id = ? AND agente_id = ?');
        return $st->execute($vals);
    }

    public function eliminar(int $id, int $agenteId): bool
    {
        $st = $this->db()->prepare('UPDATE polizas SET eliminado_en = NOW() WHERE id = ? AND agente_id = ?');
        return $st->execute([$id, $agenteId]);
    }

    // ── iDrive ───────────────────────────────────────────────────────────────
    public function marcarIdrive(int $id, string $key): void
    {
        $this->db()->prepare('UPDATE polizas SET idrive_key = ?, idrive_subido = 1 WHERE id = ?')
                   ->execute([$key, $id]);
    }

    // ── Notificaciones de renovación ─────────────────────────────────────────
    public function getPorVencer(): array
    {
        $st = $this->db()->prepare(
            "SELECT p.*, CONCAT(c.nombre,' ',c.apellido_paterno) AS cliente_nombre,
                    DATEDIFF(p.fecha_fin, CURDATE()) AS dias_restantes
             FROM   polizas p
             JOIN   clientes c ON c.id = p.cliente_id
             WHERE  p.fecha_fin IS NOT NULL
               AND  p.eliminado_en IS NULL
               AND  p.estado IN ('vigente','por_vencer')
               AND  DATEDIFF(p.fecha_fin, CURDATE()) BETWEEN 0 AND 30
               AND  p.renovacion_notificada = 0"
        );
        $st->execute();
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function marcarNotificada(int $id): void
    {
        $this->db()->prepare('UPDATE polizas SET renovacion_notificada = 1, estado = "por_vencer" WHERE id = ?')
                   ->execute([$id]);
    }

    public function actualizarEstadosVencidos(): void
    {
        $this->db()->exec(
            "UPDATE polizas SET estado = 'vencida'
             WHERE fecha_fin < CURDATE()
               AND estado NOT IN ('vencida','cancelada')
               AND eliminado_en IS NULL"
        );
    }
}