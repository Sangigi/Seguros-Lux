<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\AgentModel;
use App\Models\EmpresaModel;
use App\Models\InvitacionModel;
use App\Security\Roles;
use App\Security\SessionManager;
use App\Security\Tenant;
use App\Services\InvitacionCorreo;

/**
 * EmpresaController — alta y administración de empresas (API JSON).
 *
 * Este es el panel del propietario de la plataforma: el único rol que ve más
 * de una empresa. Todo lo de aquí exige un permiso explícito de
 * `Roles::EMPRESAS_*`; no basta con ser superadmin, porque un superadmin es
 * el administrador *de una empresa*, y dar de alta empresas no es asunto suyo.
 *
 * El alta crea dos cosas de golpe, y en una transacción lógica:
 *   1. la empresa, y
 *   2. una invitación para su primer administrador.
 *
 * Se hace así a propósito. Una empresa sin usuarios no sirve para nada, y
 * crear al administrador con una contraseña elegida por el propietario
 * significaría que alguien de fuera conoce la contraseña del cliente. Con la
 * invitación, el administrador la fija él y nadie más la ve nunca.
 */
class EmpresaController
{
    public static function handle(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        SessionManager::requireAuthApi();

        match ($_SERVER['REQUEST_METHOD']) {
            'GET'  => self::handleGet(),
            'POST' => self::handlePost(),
            default => self::responder(405, ['ok' => false, 'msg' => 'Método no permitido.']),
        };
    }

    // ── Lectura ───────────────────────────────────────────────────────────────

    private static function handleGet(): void
    {
        if (!SessionManager::puede(Roles::EMPRESAS_VER)) {
            self::responder(403, ['ok' => false, 'msg' => 'Sin permisos.']);
            return;
        }

        $modelo = new EmpresaModel();

        self::responder(200, [
            'ok'       => true,
            'empresas' => $modelo->listar(true),
            'resumen'  => $modelo->resumenPlataforma(),
            'planes'   => EmpresaModel::PLANES,
        ]);
    }

    // ── Escritura ─────────────────────────────────────────────────────────────

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

        match ((string) ($input['accion'] ?? '')) {
            'crear'     => self::crear($input),
            'actualizar'=> self::actualizar($input),
            'estado'    => self::cambiarEstado($input),
            'eliminar'  => self::eliminar($input),
            'entrar'    => self::entrar($input),
            default     => self::responder(400, ['ok' => false, 'msg' => 'Acción desconocida.']),
        };
    }

    /**
     * Alta de empresa + invitación a su primer administrador.
     *
     * Si la empresa se crea pero la invitación falla, se deshace el alta: una
     * empresa a la que nadie puede entrar solo estorba en el listado, y deja
     * al propietario sin saber si el alta funcionó o no.
     */
    private static function crear(array $input): void
    {
        if (!SessionManager::puede(Roles::EMPRESAS_CREAR)) {
            self::responder(403, ['ok' => false, 'msg' => 'Sin permisos.']);
            return;
        }

        $correoAdmin = strtolower(trim((string) ($input['admin_correo'] ?? '')));

        if (!filter_var($correoAdmin, FILTER_VALIDATE_EMAIL)) {
            self::responder(422, ['ok' => false, 'msg' => 'El correo del administrador no es válido.']);
            return;
        }

        if (trim((string) ($input['nombre'] ?? '')) === '') {
            self::responder(422, ['ok' => false, 'msg' => 'El nombre de la empresa es obligatorio.']);
            return;
        }

        $empresaModel = new EmpresaModel();
        $empresaId    = $empresaModel->crear($input, SessionManager::getAgentId());

        if ($empresaId === 0) {
            self::responder(500, ['ok' => false, 'msg' => 'No se pudo crear la empresa.']);
            return;
        }

        $invitacion = (new InvitacionModel())->invitar(
            [
                'correo'   => $correoAdmin,
                'nombre'   => (string) ($input['admin_nombre']   ?? ''),
                'apellido' => (string) ($input['admin_apellido'] ?? ''),
                'telefono' => (string) ($input['admin_telefono'] ?? ''),
                'rol'      => Roles::SUPERADMIN,
            ],
            $empresaId,
            SessionManager::getAgentId(),
            Roles::PROPIETARIO
        );

        if (!($invitacion['ok'] ?? false)) {
            $empresaModel->eliminar($empresaId);

            self::responder(422, [
                'ok'  => false,
                'msg' => 'La empresa no se creó: ' . ($invitacion['error'] ?? 'no se pudo invitar al administrador.'),
            ]);
            return;
        }

        $enlace  = self::enlaceRegistro($invitacion['token']);
        $enviado = InvitacionCorreo::enviar(
            $correoAdmin,
            trim((string) ($input['admin_nombre'] ?? '') . ' ' . (string) ($input['admin_apellido'] ?? '')),
            trim((string) $input['nombre']),
            Roles::SUPERADMIN,
            $enlace,
            $invitacion['expira']
        );

        self::responder(201, [
            'ok'             => true,
            'msg'            => $enviado
                ? "Empresa creada. Se envió la invitación a {$correoAdmin}; falta que active su cuenta."
                : 'Empresa creada, pero el correo de invitación no se pudo enviar. Comparte el enlace de abajo con su administrador.',
            'correo_enviado' => $enviado,
            'empresa_id'     => $empresaId,
            'enlace'         => $enlace,
            'expira'         => $invitacion['expira'],
        ]);
    }

    private static function actualizar(array $input): void
    {
        if (!SessionManager::puede(Roles::EMPRESAS_EDITAR)) {
            self::responder(403, ['ok' => false, 'msg' => 'Sin permisos.']);
            return;
        }

        $id = (int) ($input['id'] ?? 0);

        if ($id <= 0) {
            self::responder(422, ['ok' => false, 'msg' => 'Empresa no indicada.']);
            return;
        }

        $ok = (new EmpresaModel())->actualizar($id, $input);

        self::responder($ok ? 200 : 500, [
            'ok'  => $ok,
            'msg' => $ok ? 'Empresa actualizada.' : 'No se pudo actualizar.',
        ]);
    }

    private static function cambiarEstado(array $input): void
    {
        if (!SessionManager::puede(Roles::EMPRESAS_SUSPENDER)) {
            self::responder(403, ['ok' => false, 'msg' => 'Sin permisos.']);
            return;
        }

        $id     = (int) ($input['id'] ?? 0);
        $activa = (bool) ($input['activa'] ?? false);

        if ($id <= 0) {
            self::responder(422, ['ok' => false, 'msg' => 'Empresa no indicada.']);
            return;
        }

        $ok = (new EmpresaModel())->cambiarEstado($id, $activa);

        self::responder($ok ? 200 : 500, [
            'ok'  => $ok,
            'msg' => $activa ? 'Empresa reactivada.' : 'Empresa suspendida.',
        ]);
    }

    /**
     * Baja lógica. No se borra físicamente: una empresa arrastra clientes,
     * pólizas y expedientes que pueden hacer falta después, incluso por
     * obligación legal.
     */
    private static function eliminar(array $input): void
    {
        if (!SessionManager::puede(Roles::EMPRESAS_SUSPENDER)) {
            self::responder(403, ['ok' => false, 'msg' => 'Sin permisos.']);
            return;
        }

        $id = (int) ($input['id'] ?? 0);

        if ($id <= 0) {
            self::responder(422, ['ok' => false, 'msg' => 'Empresa no indicada.']);
            return;
        }

        $ok = (new EmpresaModel())->eliminar($id);

        // Si el propietario estaba inspeccionando justo esa empresa, se sale.
        if ($ok && SessionManager::getEmpresaId() === $id) {
            SessionManager::entrarAEmpresa(null);
        }

        self::responder($ok ? 200 : 500, [
            'ok'  => $ok,
            'msg' => $ok ? 'Empresa dada de baja.' : 'No se pudo dar de baja.',
        ]);
    }

    /**
     * El propietario entra a ver una empresa concreta.
     *
     * Esto acota lo que ve, no le da permisos extra: sigue siendo propietario
     * y su rol no cambia. Con `id = 0` vuelve a la vista de plataforma.
     */
    private static function entrar(array $input): void
    {
        if (!SessionManager::puede(Roles::EMPRESAS_ENTRAR)) {
            self::responder(403, ['ok' => false, 'msg' => 'Sin permisos.']);
            return;
        }

        $id = (int) ($input['id'] ?? 0);

        if ($id === 0) {
            SessionManager::entrarAEmpresa(null);
            self::responder(200, ['ok' => true, 'msg' => 'Vista de plataforma.', 'empresa_id' => null]);
            return;
        }

        if (!(new EmpresaModel())->activa($id)) {
            self::responder(404, ['ok' => false, 'msg' => 'La empresa no existe o está suspendida.']);
            return;
        }

        SessionManager::entrarAEmpresa($id);

        self::responder(200, ['ok' => true, 'msg' => 'Dentro de la empresa.', 'empresa_id' => $id]);
    }

    // ── Utilidades ────────────────────────────────────────────────────────────

    /**
     * Enlace de activación. Se devuelve al propietario para que pueda pasarlo
     * por otro canal si el correo no llega: sin esto, una invitación perdida
     * deja la empresa bloqueada hasta que caduque.
     */
    private static function enlaceRegistro(string $token): string
    {
        $esquema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host    = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');

        return $esquema . '://' . $host . '/registro?token=' . urlencode($token);
    }

    private static function responder(int $codigo, array $cuerpo): void
    {
        http_response_code($codigo);
        echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
    }
}
