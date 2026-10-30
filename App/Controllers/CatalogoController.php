<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\CatalogoModel;
use App\Security\Roles;
use App\Security\SessionManager;
use App\Security\Tenant;
use DomainException;
use InvalidArgumentException;

/**
 * CatalogoController — catálogos de la empresa (API JSON, /api/catalogos).
 *
 * Lo usa la pestaña Configuración del panel de administración para que cada
 * empresa decida sus ramos, aseguradoras, coberturas y documentos requeridos.
 *
 *   GET  → todo el catálogo agrupado, incluido lo inactivo:
 *          { ok, ramos: [fila…], por_ramo: { <ramo>: { aseguradoras, coberturas, documentos } } }
 *   POST → { csrf, accion, … } con accion:
 *          agregar     { tipo: aseguradora|cobertura, ramo, etiqueta } → 201 { ok, msg, item }
 *          actualizar  { id, etiqueta?, activo?, orden? }              → 200 { ok, msg, item }
 *          eliminar    { id }                                          → 200 { ok, msg }
 *
 * Las reglas (qué se puede borrar, que quede un ramo activo, nombres
 * repetidos) viven en CatalogoModel, no aquí: el controlador solo traduce sus
 * negativas a códigos HTTP y a mensajes que se pueden enseñar tal cual.
 *
 * Todo ocurre dentro de la empresa activa. La empresa sale de la sesión,
 * nunca de la petición: un id de otra empresa simplemente no se encuentra.
 */
class CatalogoController
{
    public static function handle(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        SessionManager::requireAuthApi();
        SessionManager::requirePermisoApi(Roles::CONFIG_EMPRESA);

        match ($_SERVER['REQUEST_METHOD']) {
            'GET'  => self::listar(),
            'POST' => self::handlePost(),
            default => self::responder(405, ['ok' => false, 'msg' => 'Método no permitido.']),
        };
    }

    private static function listar(): void
    {
        if (Tenant::id() === null) {
            self::responder(409, [
                'ok'  => false,
                'msg' => 'No hay empresa en contexto. Entra a una empresa para ver sus catálogos.',
            ]);
            return;
        }

        self::responder(200, ['ok' => true] + (new CatalogoModel())->agrupado());
    }

    private static function handlePost(): void
    {
        $input = json_decode((string) file_get_contents('php://input'), true);

        if (!is_array($input)) {
            self::responder(400, ['ok' => false, 'msg' => 'Petición mal formada.']);
            return;
        }

        if (!SessionManager::validateCsrf((string) ($input['csrf'] ?? ''))) {
            self::responder(419, ['ok' => false, 'msg' => 'La sesión caducó. Recarga la página.']);
            return;
        }

        if (Tenant::id() === null) {
            self::responder(409, ['ok' => false, 'msg' => 'No hay empresa en contexto.']);
            return;
        }

        try {
            match ((string) ($input['accion'] ?? '')) {
                'agregar'    => self::agregar($input),
                'actualizar' => self::actualizar($input),
                'eliminar'   => self::eliminar($input),
                default      => self::responder(400, ['ok' => false, 'msg' => 'Acción desconocida.']),
            };
        } catch (InvalidArgumentException | DomainException $e) {
            // Son las negativas del modelo, escritas para quien administra.
            self::responder(422, ['ok' => false, 'msg' => $e->getMessage()]);
        }
    }

    private static function agregar(array $input): void
    {
        $tipo     = (string) ($input['tipo'] ?? '');
        $ramo     = (string) ($input['ramo'] ?? '');
        $etiqueta = (string) ($input['etiqueta'] ?? '');

        $modelo = new CatalogoModel();
        $id     = $modelo->agregar($tipo, $ramo, $etiqueta);
        $item   = $modelo->fila($id);

        self::responder(201, [
            'ok'   => true,
            'msg'  => ($tipo === CatalogoModel::ASEGURADORA ? 'Aseguradora añadida' : 'Cobertura añadida')
                      . ': «' . ($item['etiqueta'] ?? $etiqueta) . '».',
            'item' => $item,
        ]);
    }

    private static function actualizar(array $input): void
    {
        $id = (int) ($input['id'] ?? 0);

        if ($id <= 0) {
            self::responder(422, ['ok' => false, 'msg' => 'Elemento no indicado.']);
            return;
        }

        $datos = array_intersect_key($input, array_flip(['etiqueta', 'activo', 'orden']));

        if ($datos === []) {
            self::responder(422, ['ok' => false, 'msg' => 'No hay nada que cambiar.']);
            return;
        }

        $modelo = new CatalogoModel();

        // El modelo exige que la fila sea de la empresa activa: un id ajeno
        // no se encuentra, igual que uno que no existe.
        if (!$modelo->actualizar($id, $datos)) {
            self::responder(404, ['ok' => false, 'msg' => 'No se encontró ese elemento.']);
            return;
        }

        self::responder(200, [
            'ok'   => true,
            'msg'  => 'Cambios guardados.',
            'item' => $modelo->fila($id),
        ]);
    }

    private static function eliminar(array $input): void
    {
        $id = (int) ($input['id'] ?? 0);

        if ($id <= 0) {
            self::responder(422, ['ok' => false, 'msg' => 'Elemento no indicado.']);
            return;
        }

        $ok = (new CatalogoModel())->eliminar($id);

        self::responder($ok ? 200 : 404, [
            'ok'  => $ok,
            'msg' => $ok
                ? 'Eliminado. Las cotizaciones que ya lo usaban conservan el nombre.'
                : 'No se encontró ese elemento.',
        ]);
    }

    private static function responder(int $codigo, array $cuerpo): void
    {
        http_response_code($codigo);
        echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
    }
}
