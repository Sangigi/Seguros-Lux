<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Security\Roles;
use App\Security\SessionManager;
use App\Security\Tenant;
use Database\Connection;
use PDO;

/**
 * ConfigController — ajustes clave/valor de la tabla `configuracion`.
 *
 * MULTIEMPRESA: tras la migración 005 `configuracion` tiene `empresa_id`, y la
 * separación es la siguiente:
 *
 *   empresa_id = 0   ajustes globales de la plataforma. Son las claves de
 *                    integración (`meta_*`, `wa_*`): un solo número de
 *                    WhatsApp y una sola app de Meta sirven a todas las
 *                    empresas, así que no pertenecen a ninguna. Solo las ve y
 *                    las toca el propietario (Roles::CONFIG_PLATAFORMA).
 *
 *   empresa_id = N   ajustes de la empresa N (números de ATC, etc.). Cada
 *                    empresa ve y escribe los suyos y nunca los de otra.
 *
 * Antes la clave era única global y la consulta no filtraba nada: con dos
 * empresas, la segunda habría leído y sobreescrito los ajustes de la primera.
 */
class ConfigController
{
    /**
     * Prefijos de las claves que son de plataforma, no de empresa.
     * Son los mismos que respetó la migración 005 al repartir las filas.
     */
    private const PREFIJOS_PLATAFORMA = ['meta_', 'wa_'];

    /** empresa_id que identifica a los ajustes globales de plataforma. */
    private const EMPRESA_PLATAFORMA = 0;

    public static function handle(): void
    {
        header('Content-Type: application/json');
        SessionManager::requireAuthApi();

        $db = Connection::getConnection();

        match ($_SERVER['REQUEST_METHOD']) {
            'GET'  => self::handleGet($db),
            'POST' => self::handlePost($db),
            default => (function() {
                http_response_code(405);
                echo json_encode(['ok' => false, 'msg' => 'Método no permitido.']);
            })(),
        };
    }

    /** True si la clave es de integración y por tanto vive en la plataforma. */
    private static function esClavePlataforma(string $clave): bool
    {
        foreach (self::PREFIJOS_PLATAFORMA as $prefijo) {
            if (str_starts_with($clave, $prefijo)) {
                return true;
            }
        }

        return false;
    }

    private static function handleGet(PDO $db): void
    {
        $grupo = $_GET['grupo'] ?? 'atc';

        // El propietario no pertenece a ninguna empresa: mientras no entre a
        // una solo ve los ajustes de plataforma. El resto siempre tiene empresa.
        $empresaId       = Tenant::id();
        $puedePlataforma = SessionManager::puede(Roles::CONFIG_PLATAFORMA);

        if ($empresaId === null && !$puedePlataforma) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'msg' => 'Sin empresa en contexto.']);
            return;
        }

        // Se devuelven los ajustes de la empresa activa y, solo a quien puede
        // administrar la plataforma, las claves de integración globales. Nunca
        // filas de otra empresa: ese era el agujero antes de la migración.
        $ambitos = [];
        $params  = ['grupo' => "{$grupo}%"];

        if ($empresaId !== null) {
            $ambitos[]         = ':empresa';
            $params['empresa'] = $empresaId;
        }
        if ($puedePlataforma) {
            $ambitos[]            = ':plataforma';
            $params['plataforma'] = self::EMPRESA_PLATAFORMA;
        }

        $stmt = $db->prepare(
            'SELECT clave, valor, descripcion FROM configuracion
             WHERE clave LIKE :grupo
               AND empresa_id IN (' . implode(', ', $ambitos) . ')'
        );
        $stmt->execute($params);

        echo json_encode(['ok' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    private static function handlePost(PDO $db): void
    {
        if (!SessionManager::isSuperadmin()) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'msg' => 'Sin permisos.']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $items = $input['items'] ?? [];

        if (empty($items)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'msg' => 'Sin datos.']);
            return;
        }

        $empresaId       = Tenant::id();
        $puedePlataforma = SessionManager::puede(Roles::CONFIG_PLATAFORMA);

        if ($empresaId === null && !$puedePlataforma) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'msg' => 'Sin empresa en contexto.']);
            return;
        }

        // empresa_id entra en el INSERT y la clave única es (empresa_id, clave),
        // así que el ON DUPLICATE KEY actualiza la fila de ESTA empresa y nunca
        // pisa la de otra con el mismo nombre de clave.
        $stmt = $db->prepare(
            'INSERT INTO configuracion (empresa_id, clave, valor, descripcion)
             VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE valor = VALUES(valor), descripcion = VALUES(descripcion)'
        );

        $ignoradas = [];

        foreach ($items as $item) {
            $clave = trim($item['clave'] ?? '');
            if (!$clave) continue;

            $esPlataforma = self::esClavePlataforma($clave);

            // Las claves de integración son de la plataforma: un administrador
            // de empresa no puede cambiar el token de WhatsApp o de Meta que
            // usan todas las demás.
            if ($esPlataforma && !$puedePlataforma) {
                $ignoradas[] = $clave;
                continue;
            }

            // Un ajuste de empresa necesita empresa: el propietario que no ha
            // entrado a ninguna solo puede tocar las claves de plataforma.
            if (!$esPlataforma && $empresaId === null) {
                $ignoradas[] = $clave;
                continue;
            }

            $stmt->execute([
                $esPlataforma ? self::EMPRESA_PLATAFORMA : $empresaId,
                $clave,
                trim($item['valor'] ?? ''),
                trim($item['descripcion'] ?? ''),
            ]);
        }

        $respuesta = ['ok' => true, 'msg' => 'Configuración guardada.'];
        if ($ignoradas !== []) {
            $respuesta['ignoradas'] = $ignoradas;
            $respuesta['msg']       = 'Configuración guardada. Las claves de plataforma requieren permiso del propietario.';
        }

        echo json_encode($respuesta);
    }
}
