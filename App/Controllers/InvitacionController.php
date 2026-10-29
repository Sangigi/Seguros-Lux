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
 * InvitacionController — alta de usuarios dentro de una empresa (API JSON).
 *
 * La vía normal es la invitación: se manda por correo un enlace de un solo
 * uso y es la propia persona quien fija su contraseña al activarla, así que
 * nadie más llega a conocerla. Para quien no tiene correo a mano o necesita
 * dar de alta a alguien en el momento existe también el alta directa con una
 * contraseña inicial (`crear`), reservada a quien tiene USUARIOS_CREAR.
 *
 * Dos límites que no dependen de la interfaz, sino del servidor:
 *   - nadie puede invitar con un rol igual o superior al suyo
 *     (`Roles::asignablesPor`), lo que impide fabricarse un igual o
 *     promoverse creando una cuenta por encima;
 *   - no se pasa del máximo de usuarios del plan de la empresa.
 *
 * Todo ocurre dentro de la empresa activa. Un administrador no puede invitar
 * a la empresa de al lado ni cancelar una invitación que no es suya: el
 * `empresa_id` sale de la sesión, nunca de la petición.
 */
class InvitacionController
{
    public static function handle(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        SessionManager::requireAuthApi();
        SessionManager::requirePermisoApi(Roles::USUARIOS_INVITAR);

        match ($_SERVER['REQUEST_METHOD']) {
            'GET'  => self::listar(),
            'POST' => self::handlePost(),
            default => self::responder(405, ['ok' => false, 'msg' => 'Método no permitido.']),
        };
    }

    private static function empresaActiva(): ?int
    {
        return Tenant::id();
    }

    private static function listar(): void
    {
        $empresaId = self::empresaActiva();

        if ($empresaId === null) {
            self::responder(409, [
                'ok'  => false,
                'msg' => 'No hay empresa en contexto. Entra a una empresa para ver sus invitaciones.',
            ]);
            return;
        }

        self::responder(200, [
            'ok'           => true,
            'invitaciones' => (new InvitacionModel())->pendientesDe($empresaId),
            'roles'        => array_map(
                static fn(string $r): array => ['valor' => $r, 'etiqueta' => Roles::etiqueta($r)],
                Roles::asignablesPor(SessionManager::getRole())
            ),
        ]);
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

        $empresaId = self::empresaActiva();

        if ($empresaId === null) {
            self::responder(409, ['ok' => false, 'msg' => 'No hay empresa en contexto.']);
            return;
        }

        match ((string) ($input['accion'] ?? 'invitar')) {
            'invitar'  => self::invitar($input, $empresaId),
            'crear'    => self::crear($input, $empresaId),
            'cancelar' => self::cancelar($input, $empresaId),
            'reenviar' => self::reenviar($input, $empresaId),
            default    => self::responder(400, ['ok' => false, 'msg' => 'Acción desconocida.']),
        };
    }

    private static function invitar(array $input, int $empresaId): void
    {
        $resultado = (new InvitacionModel())->invitar(
            $input,
            $empresaId,
            SessionManager::getAgentId(),
            SessionManager::getRole()
        );

        if (!($resultado['ok'] ?? false)) {
            self::responder(422, ['ok' => false, 'msg' => $resultado['error'] ?? 'No se pudo invitar.']);
            return;
        }

        $correo = strtolower(trim((string) ($input['correo'] ?? '')));
        $nombre = trim((string) ($input['nombre'] ?? '') . ' ' . (string) ($input['apellido'] ?? ''));
        $enlace = self::enlaceRegistro($resultado['token']);

        $enviado = InvitacionCorreo::enviar(
            $correo,
            $nombre,
            self::nombreEmpresa($empresaId),
            (string) ($input['rol'] ?? Roles::ASESOR),
            $enlace,
            $resultado['expira']
        );

        self::responder(201, [
            'ok'             => true,
            'msg'            => self::mensajeEnvio($enviado, $correo),
            'correo_enviado' => $enviado,
            'enlace'         => $enlace,
            'expira'         => $resultado['expira'],
        ]);
    }

    /**
     * Alta directa con una contraseña inicial que pone quien da de alta.
     *
     * Los mismos límites que la invitación: solo roles por debajo del propio,
     * dentro de la empresa activa y sin pasar el cupo del plan.
     */
    private static function crear(array $input, int $empresaId): void
    {
        if (!SessionManager::puede(Roles::USUARIOS_CREAR)) {
            self::responder(403, ['ok' => false, 'msg' => 'Sin permiso para crear usuarios.']);
            return;
        }

        $correo   = strtolower(trim((string) ($input['correo'] ?? '')));
        $nombre   = trim((string) ($input['nombre'] ?? ''));
        $apellido = trim((string) ($input['apellido'] ?? ''));
        $telefono = trim((string) ($input['telefono'] ?? ''));
        $rol      = (string) ($input['rol'] ?? Roles::ASESOR);
        $password = (string) ($input['password'] ?? '');

        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            self::responder(422, ['ok' => false, 'msg' => 'El correo no es válido.']);
            return;
        }

        if ($nombre === '') {
            self::responder(422, ['ok' => false, 'msg' => 'El nombre es obligatorio.']);
            return;
        }

        if (!in_array($rol, Roles::asignablesPor(SessionManager::getRole()), true)) {
            self::responder(422, ['ok' => false, 'msg' => 'No puedes asignar ese perfil.']);
            return;
        }

        $errorPass = InvitacionModel::validarPassword($password);
        if ($errorPass !== null) {
            self::responder(422, ['ok' => false, 'msg' => $errorPass]);
            return;
        }

        $id = (new AgentModel())->crear($nombre, $apellido, $correo, $telefono, $password, $rol, null, $empresaId);

        if ($id === 0) {
            self::responder(422, [
                'ok'  => false,
                'msg' => 'No se creó: ya hay un usuario con ese correo en la empresa, o se alcanzó el máximo de usuarios del plan.',
            ]);
            return;
        }

        self::responder(201, [
            'ok'  => true,
            'msg' => 'Usuario creado. Ya puede entrar con su correo y la contraseña que le asignaste.',
            'id'  => $id,
        ]);
    }

    private static function cancelar(array $input, int $empresaId): void
    {
        $id = (int) ($input['id'] ?? 0);

        if ($id <= 0) {
            self::responder(422, ['ok' => false, 'msg' => 'Invitación no indicada.']);
            return;
        }

        // El modelo exige la empresa además del id: aunque alguien mande el id
        // de una invitación de otra empresa, no encuentra fila que cancelar.
        $ok = (new InvitacionModel())->cancelar($id, $empresaId);

        self::responder($ok ? 200 : 404, [
            'ok'  => $ok,
            'msg' => $ok ? 'Invitación cancelada.' : 'No se encontró esa invitación.',
        ]);
    }

    private static function reenviar(array $input, int $empresaId): void
    {
        $id = (int) ($input['id'] ?? 0);

        if ($id <= 0) {
            self::responder(422, ['ok' => false, 'msg' => 'Invitación no indicada.']);
            return;
        }

        $nueva = (new InvitacionModel())->reenviar($id, $empresaId);

        if ($nueva === null) {
            self::responder(404, ['ok' => false, 'msg' => 'No se encontró esa invitación.']);
            return;
        }

        $enlace  = self::enlaceRegistro($nueva['token']);
        $enviado = InvitacionCorreo::enviar(
            $nueva['correo'],
            $nueva['nombre'],
            self::nombreEmpresa($empresaId),
            $nueva['rol'],
            $enlace,
            $nueva['expira']
        );

        self::responder(200, [
            'ok'             => true,
            'msg'            => self::mensajeEnvio($enviado, $nueva['correo'], 'Invitación reenviada con un enlace nuevo'),
            'correo_enviado' => $enviado,
            'enlace'         => $enlace,
            'expira'         => $nueva['expira'],
        ]);
    }

    private static function nombreEmpresa(int $empresaId): string
    {
        return (string) ((new EmpresaModel())->find($empresaId)['nombre'] ?? 'Seguros Lux');
    }

    private static function mensajeEnvio(bool $enviado, string $correo, string $hecho = 'Invitación creada'): string
    {
        return $enviado
            ? "{$hecho} y enviada por correo a {$correo}."
            : "{$hecho}, pero el correo no se pudo enviar. Comparte con la persona el enlace de abajo.";
    }

    /**
     * El enlace se devuelve a quien invita para que pueda pasarlo por otro
     * canal si el correo no llega. El token solo existe en claro en esta
     * respuesta: en la base queda guardado su hash.
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
