<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Models\AgentModel;
use App\Models\EmpresaModel;
use App\Models\InvitacionModel;
use App\Security\Roles;
use App\Security\Tenant;
use App\Services\InvitacionCorreo;
use PHPUnit\Framework\TestCase;

/**
 * El alta de empresas y el registro por invitación, de principio a fin.
 *
 * Lo que se prueba aquí no es que los formularios pinten bien, sino que el
 * camino completo —crear empresa, invitar, activar— deja al usuario en la
 * empresa correcta y que los atajos no funcionan: reutilizar un token,
 * invitarse un rol por encima del propio, o pasarse del plan contratado.
 */
class RegistroInvitacionTest extends TestCase
{
    private \PDO $pdo;
    private EmpresaModel $empresas;
    private InvitacionModel $invitaciones;
    private int $empresaId;

    /** Contraseña que cumple los requisitos del modelo. */
    private const PASSWORD = 'Un4-Clave-Larga';

    protected function setUp(): void
    {
        $this->pdo          = \Database\Connection::getConnection();
        $this->empresas     = new EmpresaModel();
        $this->invitaciones = new InvitacionModel();

        Tenant::reset();
        Tenant::set(1);

        $this->empresaId = $this->empresas->crear([
            'nombre'       => 'Empresa De Prueba ' . uniqid(),
            'plan'         => 'basico',
            'max_usuarios' => 3,
        ], 1);

        $this->assertGreaterThan(0, $this->empresaId, 'No se pudo crear la empresa de prueba.');
    }

    protected function tearDown(): void
    {
        $this->pdo->prepare('DELETE FROM invitaciones WHERE empresa_id = ?')->execute([$this->empresaId]);
        $this->pdo->prepare('DELETE FROM agentes WHERE empresa_id = ?')->execute([$this->empresaId]);
        $this->pdo->prepare('DELETE FROM empresas WHERE id = ?')->execute([$this->empresaId]);

        Tenant::reset();
        Tenant::set(1);
    }

    private function invitar(string $correo, string $rol = Roles::ASESOR, string $comoRol = Roles::PROPIETARIO): array
    {
        return $this->invitaciones->invitar(
            ['correo' => $correo, 'nombre' => 'Nuevo', 'apellido' => 'Usuario', 'rol' => $rol],
            $this->empresaId,
            1,
            $comoRol
        );
    }

    // ── Camino feliz ──────────────────────────────────────────────────────────

    public function testElAltaCreaElUsuarioEnLaEmpresaDeLaInvitacion(): void
    {
        $inv = $this->invitar('admin@empresa-prueba.mx', Roles::SUPERADMIN);
        $this->assertTrue($inv['ok'], $inv['error'] ?? '');

        $alta = $this->invitaciones->aceptar($inv['token'], self::PASSWORD, ['nombre' => 'Ada']);
        $this->assertTrue($alta['ok'], $alta['error'] ?? '');

        $stmt = $this->pdo->prepare('SELECT empresa_id, rol, nombre FROM agentes WHERE correo = ?');
        $stmt->execute(['admin@empresa-prueba.mx']);
        $agente = $stmt->fetch();

        $this->assertIsArray($agente, 'La activación no creó el usuario.');
        $this->assertSame($this->empresaId, (int) $agente['empresa_id'], 'El usuario quedó en otra empresa.');
        $this->assertSame(Roles::SUPERADMIN, $agente['rol']);
        $this->assertSame('Ada', $agente['nombre']);
    }

    public function testLaContrasenaQuedaGuardadaComoHashYNoEnClaro(): void
    {
        $inv = $this->invitar('hash@empresa-prueba.mx');
        $this->invitaciones->aceptar($inv['token'], self::PASSWORD, ['nombre' => 'Hash']);

        $stmt = $this->pdo->prepare('SELECT contrasena FROM agentes WHERE correo = ?');
        $stmt->execute(['hash@empresa-prueba.mx']);
        $guardada = (string) $stmt->fetchColumn();

        $this->assertNotSame(self::PASSWORD, $guardada, 'La contraseña se guardó en claro.');
        $this->assertTrue(password_verify(self::PASSWORD, $guardada), 'El hash no corresponde a la contraseña.');
    }

    // ── El token ──────────────────────────────────────────────────────────────

    public function testElTokenNoSePuedeUsarDosVeces(): void
    {
        $inv = $this->invitar('doble@empresa-prueba.mx');

        $primera = $this->invitaciones->aceptar($inv['token'], self::PASSWORD, ['nombre' => 'Primera']);
        $segunda = $this->invitaciones->aceptar($inv['token'], self::PASSWORD, ['nombre' => 'Segunda']);

        $this->assertTrue($primera['ok']);
        $this->assertFalse($segunda['ok'], 'El mismo token creó dos cuentas.');

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM agentes WHERE correo = ?');
        $stmt->execute(['doble@empresa-prueba.mx']);
        $this->assertSame(1, (int) $stmt->fetchColumn());
    }

    public function testUnTokenInventadoNoActivaNada(): void
    {
        $resultado = $this->invitaciones->aceptar(str_repeat('ab', 32), self::PASSWORD, ['nombre' => 'Nadie']);

        $this->assertFalse($resultado['ok']);
    }

    public function testUnaInvitacionCanceladaDejaDeServir(): void
    {
        $inv = $this->invitar('cancelada@empresa-prueba.mx');

        $this->assertTrue($this->invitaciones->cancelar((int) $inv['id'], $this->empresaId));

        $resultado = $this->invitaciones->aceptar($inv['token'], self::PASSWORD, ['nombre' => 'Tarde']);
        $this->assertFalse($resultado['ok'], 'Una invitación cancelada siguió activando cuentas.');
    }

    public function testUnaInvitacionCaducadaDejaDeServir(): void
    {
        $inv = $this->invitar('caducada@empresa-prueba.mx');

        $this->pdo->prepare('UPDATE invitaciones SET expira_en = ? WHERE id = ?')
                  ->execute([date('Y-m-d H:i:s', time() - 3600), (int) $inv['id']]);

        $resultado = $this->invitaciones->aceptar($inv['token'], self::PASSWORD, ['nombre' => 'Tarde']);
        $this->assertFalse($resultado['ok'], 'Una invitación caducada siguió activando cuentas.');
    }

    /**
     * Una invitación de otra empresa no se cancela aunque se acierte el id:
     * el modelo exige empresa además de id.
     */
    public function testNoSePuedeCancelarLaInvitacionDeOtraEmpresa(): void
    {
        $inv = $this->invitar('ajena@empresa-prueba.mx');

        $otraEmpresa = $this->empresaId + 999;

        $this->assertFalse($this->invitaciones->cancelar((int) $inv['id'], $otraEmpresa));
    }

    // ── Límites ───────────────────────────────────────────────────────────────

    public function testNadieInvitaConUnRolIgualOSuperiorAlSuyo(): void
    {
        $comoCoordinador = $this->invitar('sube@empresa-prueba.mx', Roles::SUPERADMIN, Roles::COORDINADOR);

        $this->assertFalse($comoCoordinador['ok'], 'Un coordinador invitó a un superadmin.');
    }

    public function testNoSePuedeInvitarDosVecesAlMismoCorreo(): void
    {
        $this->assertTrue($this->invitar('repe@empresa-prueba.mx')['ok']);
        $this->assertFalse($this->invitar('repe@empresa-prueba.mx')['ok'], 'Se duplicó la invitación.');
    }

    public function testNoSePasaDelMaximoDeUsuariosDelPlan(): void
    {
        // El plan de esta empresa admite 3.
        foreach (['u1', 'u2', 'u3'] as $u) {
            $inv = $this->invitar($u . '@empresa-prueba.mx');
            $this->assertTrue($inv['ok'], $inv['error'] ?? '');
            $this->invitaciones->aceptar($inv['token'], self::PASSWORD, ['nombre' => $u]);
        }

        $cuarto = $this->invitar('u4@empresa-prueba.mx');

        $this->assertFalse($cuarto['ok'], 'Se superó el máximo de usuarios del plan.');
    }

    public function testUnCorreoInvalidoNoGeneraInvitacion(): void
    {
        $this->assertFalse($this->invitar('esto-no-es-un-correo')['ok']);
    }

    public function testUnaContrasenaDebilNoActivaLaCuenta(): void
    {
        $inv = $this->invitar('debil@empresa-prueba.mx');

        $resultado = $this->invitaciones->aceptar($inv['token'], '123', ['nombre' => 'Debil']);

        $this->assertFalse($resultado['ok']);

        // Y la invitación sigue utilizable: un intento fallido no la quema.
        $this->assertTrue(
            $this->invitaciones->aceptar($inv['token'], self::PASSWORD, ['nombre' => 'Debil'])['ok'],
            'Un intento con contraseña débil inutilizó la invitación.'
        );
    }

    // ── Mismo correo en dos empresas ──────────────────────────────────────────

    /**
     * Un asesor puede trabajar para dos correredurías con el mismo correo.
     * Antes el correo era único global y la segunda alta fallaba.
     */
    public function testElMismoCorreoPuedeExistirEnDosEmpresas(): void
    {
        $segunda = $this->empresas->crear([
            'nombre'       => 'Segunda Empresa ' . uniqid(),
            'plan'         => 'basico',
            'max_usuarios' => 5,
        ], 1);

        $correo = 'compartido@empresa-prueba.mx';

        $invA = $this->invitar($correo);
        $this->assertTrue($this->invitaciones->aceptar($invA['token'], self::PASSWORD, ['nombre' => 'EnA'])['ok']);

        $invB = $this->invitaciones->invitar(
            ['correo' => $correo, 'nombre' => 'EnB', 'rol' => Roles::ASESOR],
            $segunda,
            1,
            Roles::PROPIETARIO
        );
        $this->assertTrue($invB['ok'], $invB['error'] ?? '');
        $this->assertTrue($this->invitaciones->aceptar($invB['token'], self::PASSWORD, ['nombre' => 'EnB'])['ok']);

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM agentes WHERE correo = ?');
        $stmt->execute([$correo]);

        $this->assertSame(2, (int) $stmt->fetchColumn(), 'El mismo correo no pudo existir en dos empresas.');

        // Limpieza de la segunda empresa.
        $this->pdo->prepare('DELETE FROM agentes WHERE empresa_id = ?')->execute([$segunda]);
        $this->pdo->prepare('DELETE FROM invitaciones WHERE empresa_id = ?')->execute([$segunda]);
        $this->pdo->prepare('DELETE FROM empresas WHERE id = ?')->execute([$segunda]);
    }

    // ── Alta directa y reenvío ────────────────────────────────────────────────

    public function testElAltaDirectaCreaElUsuarioEnSuEmpresa(): void
    {
        $id = (new AgentModel())->crear(
            'Directo', 'Alta', 'directo@empresa-prueba.mx', '', self::PASSWORD, Roles::ASESOR, null, $this->empresaId
        );

        $this->assertGreaterThan(0, $id);

        $stmt = $this->pdo->prepare('SELECT empresa_id, rol, contrasena FROM agentes WHERE id = ?');
        $stmt->execute([$id]);
        $fila = $stmt->fetch();

        $this->assertSame($this->empresaId, (int) $fila['empresa_id']);
        $this->assertSame(Roles::ASESOR, $fila['rol']);
        $this->assertTrue(password_verify(self::PASSWORD, $fila['contrasena']));
    }

    /**
     * Un propietario creado dentro de una empresa vería todas: el alta directa
     * lo rechaza aunque alguien mande ese rol.
     */
    public function testNoSePuedeCrearUnPropietarioDesdeUnaEmpresa(): void
    {
        $id = (new AgentModel())->crear(
            'Falso', 'Dueño', 'dueno@empresa-prueba.mx', '', self::PASSWORD, Roles::PROPIETARIO, null, $this->empresaId
        );

        $this->assertSame(0, $id, 'Se creó un propietario de plataforma dentro de una empresa.');
    }

    public function testReenviarDevuelveLosDatosParaElCorreo(): void
    {
        $inv   = $this->invitar('reenvio@empresa-prueba.mx', Roles::COORDINADOR);
        $nueva = $this->invitaciones->reenviar((int) $inv['id'], $this->empresaId);

        $this->assertSame('reenvio@empresa-prueba.mx', $nueva['correo']);
        $this->assertSame('Nuevo Usuario', $nueva['nombre']);
        $this->assertSame(Roles::COORDINADOR, $nueva['rol']);
        $this->assertNotSame($inv['token'], $nueva['token'], 'El reenvío no cambió el token.');
    }

    /** Sin SMTP configurado el correo no sale, pero la invitación sigue en pie. */
    public function testSinSmtpElCorreoNoSeEnviaPeroNoRompe(): void
    {
        $this->assertFalse(InvitacionCorreo::enviar(
            'alguien@empresa-prueba.mx',
            'Alguien',
            'Empresa',
            Roles::ASESOR,
            'https://ejemplo.mx/registro?token=x',
            date('Y-m-d H:i:s')
        ));
    }
}
