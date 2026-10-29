<?php
declare(strict_types=1);

namespace App\Models;

use App\Security\Roles;
use App\Security\Tenant;

/**
 * InvitacionModel — altas de usuario por invitación.
 *
 * Nadie se registra solo: alguien con permiso invita, y el invitado usa un
 * enlace con token para poner su contraseña. Eso evita que el formulario de
 * registro sea una puerta abierta a crear cuentas en una empresa ajena.
 *
 * DECISIONES DE SEGURIDAD
 *
 *  - El token se guarda HASHEADO (SHA-256). Si alguien lee la base de datos
 *    no puede usar las invitaciones pendientes: tendría los hashes, no los
 *    enlaces. El token en claro existe solo el tiempo de enviar el correo.
 *
 *  - Las invitaciones caducan (7 días por defecto). Un enlace que sirve para
 *    siempre es una credencial permanente repartida por correo.
 *
 *  - La empresa y el rol viven en la invitación, no en el formulario. Quien
 *    acepta no puede elegir en qué empresa entra ni con qué permisos, por
 *    mucho que manipule el POST.
 *
 *  - Quien invita no puede otorgar un rol igual o superior al suyo
 *    (Roles::asignablesPor), así que una invitación no sirve para escalar.
 */
class InvitacionModel extends BaseModel
{
    protected string $table = 'invitaciones';
    protected int    $cacheTtl = 0; // tabla de alta rotación: sin caché

    protected array $defaultColumns = [
        'id', 'empresa_id', 'correo', 'nombre', 'apellido', 'telefono',
        'rol', 'puesto', 'estado', 'invitado_por', 'agente_id',
        'expira_en', 'aceptada_en', 'creado_en',
    ];

    protected array $fillable = [
        'empresa_id'   => 'int',
        'correo'       => 'string',
        'nombre'       => 'string',
        'apellido'     => 'string',
        'telefono'     => 'string',
        'rol'          => 'string',
        'puesto'       => 'string',
        'token_hash'   => 'string',
        'estado'       => 'string',
        'invitado_por' => 'int',
        'expira_en'    => 'string',
    ];

    public const VIGENCIA_DIAS = 7;

    /**
     * Lo que se le pide a la contraseña, en una frase.
     *
     * Vive junto a validarPassword() a propósito: si el texto que se le
     * enseña a la gente estuviera escrito en la vista, acabaría diciendo una
     * cosa mientras el validador comprueba otra.
     */
    public const REQUISITOS_PASSWORD =
        'Mínimo 10 caracteres. Evita contraseñas obvias o un solo carácter repetido.';

    public const PENDIENTE = 'pendiente';
    public const ACEPTADA  = 'aceptada';
    public const CANCELADA = 'cancelada';

    // ── Creación ──────────────────────────────────────────────────────────────

    /**
     * Crea una invitación y devuelve el token EN CLARO para enviarlo por
     * correo. Es la única vez que el token existe sin hashear; después solo
     * queda su hash en la base.
     *
     * @param  array<string,mixed> $datos
     * @return array{ok:bool,error?:string,id?:int,token?:string,expira?:string}
     */
    public function invitar(array $datos, int $empresaId, int $invitadoPor, string $rolDeQuienInvita): array
    {
        $correo = strtolower(trim((string) ($datos['correo'] ?? '')));

        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'El correo no es válido.'];
        }

        $rol = (string) ($datos['rol'] ?? Roles::ASESOR);

        // Quien invita no puede crear a alguien de su nivel o por encima.
        if (!in_array($rol, Roles::asignablesPor($rolDeQuienInvita), true)) {
            return ['ok' => false, 'error' => 'No puedes asignar ese rol.'];
        }

        // ¿Ya existe un usuario con ese correo en esta empresa?
        $stmt = $this->db()->prepare(
            'SELECT 1 FROM agentes WHERE correo = ? AND empresa_id = ? LIMIT 1'
        );
        $stmt->execute([$correo, $empresaId]);
        if ($stmt->fetch()) {
            return ['ok' => false, 'error' => 'Ya existe un usuario con ese correo en la empresa.'];
        }

        // ¿Hay una invitación pendiente todavía válida?
        $stmt = $this->db()->prepare(
            "SELECT id FROM invitaciones
             WHERE correo = ? AND empresa_id = ? AND estado = ? AND expira_en > NOW() LIMIT 1"
        );
        $stmt->execute([$correo, $empresaId, self::PENDIENTE]);
        if ($stmt->fetch()) {
            return ['ok' => false, 'error' => 'Ya hay una invitación pendiente para ese correo.'];
        }

        // ¿La empresa tiene cupo en su plan?
        if (!(new EmpresaModel())->admiteOtroUsuario($empresaId)) {
            return ['ok' => false, 'error' => 'La empresa alcanzó el máximo de usuarios de su plan.'];
        }

        $token  = bin2hex(random_bytes(32));
        $expira = date('Y-m-d H:i:s', time() + (self::VIGENCIA_DIAS * 86400));

        $puesto = (string) ($datos['puesto'] ?? $rol);
        if (!isset(AgentModel::PUESTOS[$puesto])) {
            $puesto = $rol;
        }

        $id = Tenant::comoEmpresa($empresaId, fn() => $this->insert([
            'empresa_id'   => $empresaId,
            'correo'       => $correo,
            'nombre'       => trim((string) ($datos['nombre']   ?? '')),
            'apellido'     => trim((string) ($datos['apellido'] ?? '')),
            'telefono'     => trim((string) ($datos['telefono'] ?? '')),
            'rol'          => $rol,
            'puesto'       => $puesto,
            'token_hash'   => $this->hashToken($token),
            'estado'       => self::PENDIENTE,
            'invitado_por' => $invitadoPor,
            'expira_en'    => $expira,
        ]));

        if ($id === 0) {
            return ['ok' => false, 'error' => 'No se pudo crear la invitación.'];
        }

        return ['ok' => true, 'id' => $id, 'token' => $token, 'expira' => $expira];
    }

    // ── Validación y aceptación ───────────────────────────────────────────────

    /**
     * Busca una invitación válida a partir del token en claro.
     * Devuelve null si no existe, ya se usó, se canceló o caducó: el que
     * presenta el enlace no debe poder distinguir entre esos casos.
     */
    public function porToken(string $token): ?array
    {
        if ($token === '' || !ctype_xdigit($token)) {
            return null;
        }

        // tenant-ok: el token se canjea antes de que exista contexto de empresa;
        // la empresa se deduce de la propia invitación
        $stmt = $this->db()->prepare(
            'SELECT i.*, e.nombre AS empresa_nombre, e.activo AS empresa_activa
             FROM invitaciones i
             JOIN empresas e ON e.id = i.empresa_id
             WHERE i.token_hash = ? LIMIT 1'
        );
        $stmt->execute([$this->hashToken($token)]);
        $inv = $stmt->fetch();

        if ($inv === false) {
            return null;
        }

        if ((string) $inv['estado'] !== self::PENDIENTE) {
            return null;
        }

        if (strtotime((string) $inv['expira_en']) < time()) {
            return null;
        }

        if ((int) $inv['empresa_activa'] !== 1) {
            return null;
        }

        return $inv;
    }

    /**
     * Acepta la invitación creando el usuario con la contraseña elegida.
     *
     * Todo ocurre en una transacción: o queda el usuario creado y la
     * invitación marcada, o no queda nada. Si no, un fallo a mitad dejaría
     * una invitación consumida sin cuenta, o una cuenta con la invitación
     * todavía utilizable.
     *
     * @return array{ok:bool,error?:string,agente_id?:int,empresa_id?:int}
     */
    public function aceptar(string $token, string $password, array $datos = []): array
    {
        $inv = $this->porToken($token);

        if ($inv === null) {
            return ['ok' => false, 'error' => 'La invitación no es válida o ya expiró.'];
        }

        $errorPass = self::validarPassword($password);
        if ($errorPass !== null) {
            return ['ok' => false, 'error' => $errorPass];
        }

        $empresaId = (int) $inv['empresa_id'];

        if (!(new EmpresaModel())->admiteOtroUsuario($empresaId)) {
            return ['ok' => false, 'error' => 'La empresa alcanzó el máximo de usuarios de su plan.'];
        }

        $nombre   = trim((string) ($datos['nombre']   ?? $inv['nombre']));
        $apellido = trim((string) ($datos['apellido'] ?? $inv['apellido']));
        $telefono = trim((string) ($datos['telefono'] ?? $inv['telefono']));

        if ($nombre === '') {
            return ['ok' => false, 'error' => 'El nombre es obligatorio.'];
        }

        $db = $this->db();

        try {
            $db->beginTransaction();

            // Se vuelve a comprobar el correo dentro de la transacción: entre
            // la validación y aquí pudo crearse ese usuario.
            $chk = $db->prepare('SELECT 1 FROM agentes WHERE correo = ? AND empresa_id = ? LIMIT 1');
            $chk->execute([$inv['correo'], $empresaId]);
            if ($chk->fetch()) {
                $db->rollBack();
                return ['ok' => false, 'error' => 'Ya existe un usuario con ese correo.'];
            }

            $ins = $db->prepare(
                'INSERT INTO agentes
                    (empresa_id, nombre, apellido, correo, telefono, contrasena, rol, puesto, activo, creado_en, creado_por)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), ?)'
            );
            $ins->execute([
                $empresaId,
                $nombre,
                $apellido,
                $inv['correo'],
                $telefono,
                password_hash($password, PASSWORD_BCRYPT),
                $inv['rol'],
                $inv['puesto'],
                (int) $inv['invitado_por'],
            ]);

            $agenteId = (int) $db->lastInsertId();

            // El UPDATE exige estado pendiente: si dos peticiones llegan a la
            // vez con el mismo token, solo una afecta una fila y la otra se
            // deshace. El token no se puede usar dos veces.
            // tenant-ok: aceptación del token, aún sin sesión; el id de la
            // invitación ya resolvió su empresa
            $upd = $db->prepare(
                'UPDATE invitaciones
                    SET estado = ?, aceptada_en = NOW(), agente_id = ?
                  WHERE id = ? AND estado = ?'
            );
            $upd->execute([self::ACEPTADA, $agenteId, (int) $inv['id'], self::PENDIENTE]);

            if ($upd->rowCount() !== 1) {
                $db->rollBack();
                return ['ok' => false, 'error' => 'La invitación ya fue utilizada.'];
            }

            $db->commit();

            return ['ok' => true, 'agente_id' => $agenteId, 'empresa_id' => $empresaId];

        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return ['ok' => false, 'error' => 'No se pudo completar el registro.'];
        }
    }

    // ── Gestión ───────────────────────────────────────────────────────────────

    /** @return array<int,array<string,mixed>> */
    public function pendientesDe(int $empresaId): array
    {
        $stmt = $this->db()->prepare(
            "SELECT i.id, i.correo, i.nombre, i.apellido, i.rol, i.puesto,
                    i.estado, i.expira_en, i.creado_en,
                    a.nombre AS invitado_por_nombre
             FROM invitaciones i
             LEFT JOIN agentes a ON a.id = i.invitado_por
             WHERE i.empresa_id = ?
             ORDER BY (i.estado = 'pendiente') DESC, i.creado_en DESC
             LIMIT 200"
        );
        $stmt->execute([$empresaId]);

        return $stmt->fetchAll();
    }

    public function cancelar(int $id, int $empresaId): bool
    {
        $stmt = $this->db()->prepare(
            'UPDATE invitaciones SET estado = ?, cancelada_en = NOW()
             WHERE id = ? AND empresa_id = ? AND estado = ?'
        );
        $stmt->execute([self::CANCELADA, $id, $empresaId, self::PENDIENTE]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Reemite una invitación: invalida el token anterior y genera uno nuevo.
     * Devuelve el token en claro, o null si no se pudo.
     */
    public function reenviar(int $id, int $empresaId): ?array
    {
        $stmt = $this->db()->prepare(
            'SELECT * FROM invitaciones WHERE id = ? AND empresa_id = ? LIMIT 1'
        );
        $stmt->execute([$id, $empresaId]);
        $inv = $stmt->fetch();

        if ($inv === false || (string) $inv['estado'] === self::ACEPTADA) {
            return null;
        }

        $token  = bin2hex(random_bytes(32));
        $expira = date('Y-m-d H:i:s', time() + (self::VIGENCIA_DIAS * 86400));

        $upd = $this->db()->prepare(
            'UPDATE invitaciones
                SET token_hash = ?, estado = ?, expira_en = ?, cancelada_en = NULL
              WHERE id = ? AND empresa_id = ?'
        );
        $upd->execute([$this->hashToken($token), self::PENDIENTE, $expira, $id, $empresaId]);

        if ($upd->rowCount() === 0) {
            return null;
        }

        return [
            'token'  => $token,
            'correo' => (string) $inv['correo'],
            'nombre' => trim((string) $inv['nombre'] . ' ' . (string) $inv['apellido']),
            'rol'    => (string) $inv['rol'],
            'expira' => $expira,
        ];
    }

    /** Marca como expiradas las invitaciones vencidas (para el cron). */
    public function purgarExpiradas(): int
    {
        // tenant-ok: barrido de plataforma; caducar no expone datos de nadie
        $stmt = $this->db()->prepare(
            "UPDATE invitaciones SET estado = 'expirada'
              WHERE estado = ? AND expira_en < NOW()"
        );
        $stmt->execute([self::PENDIENTE]);

        return $stmt->rowCount();
    }

    // ── Utilidades ────────────────────────────────────────────────────────────

    private function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Reglas de contraseña. Devuelve el mensaje de error, o null si es válida.
     *
     * Se exige longitud antes que composición: una frase larga resiste mucho
     * mejor que "Abc1!" y es más fácil de recordar, así que el mínimo es 10.
     */
    public static function validarPassword(string $password): ?string
    {
        $largo = mb_strlen($password);

        if ($largo < 10) {
            return 'La contraseña debe tener al menos 10 caracteres.';
        }

        if ($largo > 200) {
            return 'La contraseña es demasiado larga.';
        }

        // Rechaza las obvias, que son las que de verdad se usan.
        $comunes = [
            'contrasena', 'contraseña', 'password', '1234567890', 'qwertyuiop',
            'seguroslux', 'administrador', 'bienvenido',
        ];
        $normal = mb_strtolower($password);
        foreach ($comunes as $c) {
            if ($normal === $c || str_starts_with($normal, $c)) {
                return 'Esa contraseña es demasiado común. Elige otra.';
            }
        }

        if (preg_match('/^(.)\1+$/u', $password)) {
            return 'La contraseña no puede ser un solo carácter repetido.';
        }

        return null;
    }
}
