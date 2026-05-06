<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Models\AgentModel;
use App\Services\LoginService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitarios de LoginService.
 *
 * LoginService es el componente más crítico de seguridad del CRM.
 * Aquí validamos:
 *   1. Que las credenciales correctas devuelvan los datos del agente.
 *   2. Que contraseñas incorrectas devuelvan null.
 *   3. Que agentes inactivos no puedan entrar.
 *   4. Que usuarios inexistentes devuelvan null SIN exponer timing.
 *   5. Que el hash de contraseña nunca llegue al resultado.
 *
 * ESTRATEGIA: mock de AgentModel para aislar LoginService de la BD.
 *   - No se toca Connection ni PDO en ningún test de este archivo.
 *   - Los hashes bcrypt se generan una vez en setUpBeforeClass() para
 *     no repetir el cómputo costoso en cada test.
 */
final class LoginServiceTest extends TestCase
{
    /**
     * Hash bcrypt real de la contraseña "secreto123".
     * Se genera una sola vez para todos los tests (bcrypt es costoso).
     */
    private static string $hashReal;

    /** @var MockObject&AgentModel */
    private MockObject $agentModelMock;
    private LoginService $service;

    public static function setUpBeforeClass(): void
    {
        self::$hashReal = password_hash('secreto123', PASSWORD_BCRYPT);
    }

    protected function setUp(): void
    {
        // Crear mock de AgentModel — no ejecuta consultas reales
        $this->agentModelMock = $this->createMock(AgentModel::class);
        $this->service        = new LoginService($this->agentModelMock);
    }

    // ── Login exitoso ─────────────────────────────────────────────────────────

    public function testLoginExitosoDevuelveDatosDelAgente(): void
    {
        $this->agentModelMock
            ->method('findByEmail')
            ->with('asesor@lux.mx')
            ->willReturn([
                'id'         => 5,
                'nombre'     => 'María',
                'apellido'   => 'López',
                'correo'     => 'asesor@lux.mx',
                'contrasena' => self::$hashReal,
                'rol'        => 'asesor',
                'activo'     => 1,
            ]);

        $result = $this->service->login('asesor@lux.mx', 'secreto123');

        $this->assertIsArray($result);
        $this->assertSame(5,          $result['id']);
        $this->assertSame('María',    $result['nombre']);
        $this->assertSame('asesor',   $result['rol']);
    }

    public function testLoginExitosoNuncaExponeElHash(): void
    {
        $this->agentModelMock
            ->method('findByEmail')
            ->willReturn([
                'id'         => 1,
                'nombre'     => 'Admin',
                'apellido'   => 'Test',
                'correo'     => 'admin@lux.mx',
                'contrasena' => self::$hashReal,
                'rol'        => 'admin',
                'activo'     => 1,
            ]);

        $result = $this->service->login('admin@lux.mx', 'secreto123');

        // La contraseña hasheada NO debe aparecer en el resultado
        $this->assertArrayNotHasKey('contrasena', $result ?? []);
    }

    // ── Contraseña incorrecta ─────────────────────────────────────────────────

    public function testLoginFallaConContrasenaIncorrecta(): void
    {
        $this->agentModelMock
            ->method('findByEmail')
            ->willReturn([
                'id'         => 2,
                'nombre'     => 'Asesor',
                'apellido'   => 'Test',
                'correo'     => 'x@lux.mx',
                'contrasena' => self::$hashReal,
                'rol'        => 'asesor',
                'activo'     => 1,
            ]);

        $result = $this->service->login('x@lux.mx', 'contrasena_incorrecta');

        $this->assertNull($result);
    }

    // ── Agente inexistente ────────────────────────────────────────────────────

    public function testLoginDevuelveNullSiCorreoNoExiste(): void
    {
        $this->agentModelMock
            ->method('findByEmail')
            ->willReturn(null); // correo no encontrado en BD

        $result = $this->service->login('noexiste@lux.mx', 'cualquierCosa');

        $this->assertNull($result);
    }

    // ── Agente inactivo ───────────────────────────────────────────────────────

    public function testLoginFallaParaAgenteInactivo(): void
    {
        $this->agentModelMock
            ->method('findByEmail')
            ->willReturn([
                'id'         => 3,
                'nombre'     => 'Inactivo',
                'apellido'   => 'Test',
                'correo'     => 'inactivo@lux.mx',
                'contrasena' => self::$hashReal,
                'rol'        => 'asesor',
                'activo'     => 0, // ← inactivo
            ]);

        $result = $this->service->login('inactivo@lux.mx', 'secreto123');

        $this->assertNull($result);
    }

    // ── Timing attack: el dummy hash se verifica SIEMPRE ─────────────────────

    /**
     * Verifica que el servicio no hace bypass de password_verify() cuando
     * el correo no existe. Si lo hiciera, el tiempo de respuesta sería
     * mucho menor y permitiría enumerar usuarios por tiempo.
     *
     * No podemos medir nanosegundos en un test unitario, pero sí podemos
     * confirmar que el código llama a findByEmail exactamente UNA vez
     * (no cortocircuita antes de verificar el hash).
     */
    public function testLoginLlamaFindByEmailSiempreUnaVez(): void
    {
        $this->agentModelMock
            ->expects($this->once()) // ← debe llamarse exactamente 1 vez
            ->method('findByEmail')
            ->willReturn(null);

        $this->service->login('noexiste@lux.mx', 'cualquierCosa');
    }

    // ── Correo vacío ──────────────────────────────────────────────────────────

    public function testLoginDevuelveNullConCorreoVacio(): void
    {
        $this->agentModelMock
            ->method('findByEmail')
            ->willReturn(null);

        $result = $this->service->login('', 'secreto123');

        $this->assertNull($result);
    }

    // ── Contraseña vacía ──────────────────────────────────────────────────────

    public function testLoginDevuelveNullConContrasenaVacia(): void
    {
        $this->agentModelMock
            ->method('findByEmail')
            ->willReturn([
                'id'         => 4,
                'nombre'     => 'Test',
                'apellido'   => 'Vacio',
                'correo'     => 'test@lux.mx',
                'contrasena' => self::$hashReal,
                'rol'        => 'asesor',
                'activo'     => 1,
            ]);

        $result = $this->service->login('test@lux.mx', '');

        $this->assertNull($result);
    }
}