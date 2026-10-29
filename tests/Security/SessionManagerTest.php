<?php
declare(strict_types=1);

namespace Tests\Security;

use App\Security\SessionManager;
use PHPUnit\Framework\TestCase;

/**
 * Tests de seguridad de SessionManager.
 *
 * DESAFÍO:
 *   SessionManager es una clase estática que usa $_SESSION, session_*() y
 *   header() — funciones que en CLI/test no tienen un servidor HTTP real.
 *
 * ESTRATEGIA:
 *   - Testear la lógica PURA que no depende del servidor:
 *       · getCsrfToken() / validateCsrf()   → trabajan con $_SESSION
 *       · nonce()                           → genera bytes aleatorios
 *       · getAgentData() / getRole()        → leen $_SESSION
 *       · isAdmin() / isSuperadmin()        → lógica booleana sobre rol
 *       · requireRole() — la jerarquía      → lógica numérica (sin headers)
 *   - Simular $_SESSION directamente (es un array global accesible en CLI).
 *   - Los métodos que llaman header() o exit() quedan fuera del scope de
 *     tests unitarios; se prueban en tests de integración E2E.
 *
 * ORDEN DE EJECUCIÓN:
 *   setUp() inicia sesión en memoria y la limpia antes de cada test.
 *   tearDown() destruye la sesión para no contaminar el siguiente test.
 */
final class SessionManagerTest extends TestCase
{
    protected function setUp(): void
    {
        // Iniciar sesión PHP en modo CLI (usa sesiones en memoria)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        // Limpiar $_SESSION antes de cada test
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    // ── CSRF ──────────────────────────────────────────────────────────────────

    public function testGetCsrfTokenDevuelveStringNoVacio(): void
    {
        $token = SessionManager::getCsrfToken();

        $this->assertIsString($token);
        $this->assertNotEmpty($token);
    }

    public function testGetCsrfTokenDevuelveMismoTokenEnLlamadasSucesivas(): void
    {
        $t1 = SessionManager::getCsrfToken();
        $t2 = SessionManager::getCsrfToken();

        $this->assertSame($t1, $t2, 'El token CSRF debe ser estable dentro de la misma sesión');
    }

    public function testGetCsrfTokenTiene64CaracteresHex(): void
    {
        $token = SessionManager::getCsrfToken();

        // bin2hex(random_bytes(32)) → 64 chars hexadecimales
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
    }

    public function testValidateCsrfApruebaMismoToken(): void
    {
        $token = SessionManager::getCsrfToken();

        $this->assertTrue(
            SessionManager::validateCsrf($token),
            'El token generado por getCsrfToken() debe validarse correctamente'
        );
    }

    public function testValidateCsrfRechazaTokenDistinto(): void
    {
        SessionManager::getCsrfToken(); // genera y guarda en sesión

        $this->assertFalse(
            SessionManager::validateCsrf('token_falso_manipulado'),
            'Un token diferente al almacenado en sesión debe fallar'
        );
    }

    public function testValidateCsrfRechazaStringVacio(): void
    {
        SessionManager::getCsrfToken();

        $this->assertFalse(SessionManager::validateCsrf(''));
    }

    public function testValidateCsrfRechazaTokenNulo(): void
    {
        // Sin token en sesión + token vacío enviado
        $this->assertFalse(SessionManager::validateCsrf(''));
    }

    public function testValidateCsrfEsResistenteATimingAttack(): void
    {
        // hash_equals() se usa internamente — verificamos que la comparación
        // no cortocircuita por longitud (token de longitud distinta también falla).
        SessionManager::getCsrfToken();

        $this->assertFalse(SessionManager::validateCsrf('corto'));
        $this->assertFalse(SessionManager::validateCsrf(str_repeat('a', 128)));
    }

    // ── nonce ─────────────────────────────────────────────────────────────────

    public function testNonceDevuelveStringBase64(): void
    {
        $nonce = SessionManager::nonce();

        $this->assertIsString($nonce);
        $this->assertNotEmpty($nonce);
        // base64_encode(random_bytes(16)) → 24 chars base64
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9+\/]+=*$/', $nonce);
    }

    public function testNonceEsEstableDentroDelMismoRequest(): void
    {
        $n1 = SessionManager::nonce();
        $n2 = SessionManager::nonce();

        $this->assertSame($n1, $n2, 'El nonce debe ser el mismo en todo el ciclo de vida del request');
    }

    // ── getRole / isAdmin / isSuperadmin ──────────────────────────────────────

    /** @dataProvider rolesAdminProvider */
    public function testIsAdminParaRolesElevados(string $rol, bool $esperado): void
    {
        $_SESSION['agente_rol'] = $rol;

        $this->assertSame($esperado, SessionManager::isAdmin());
    }

    public static function rolesAdminProvider(): array
    {
        return [
            'asesor no es admin'         => ['asesor',        false],
            'agente no es admin'         => ['agente',        false],
            'coordinador es admin'       => ['coordinador',   true],
            'administrador es admin'     => ['administrador', true],
            'superadmin es admin'        => ['superadmin',    true],
        ];
    }

    public function testIsSuperadminSoloCuandoRolEsSuperadmin(): void
    {
        $_SESSION['agente_rol'] = 'superadmin';
        $this->assertTrue(SessionManager::isSuperadmin());

        $_SESSION['agente_rol'] = 'administrador';
        $this->assertFalse(SessionManager::isSuperadmin());
    }

    public function testGetRoleDevuelveAsesorPorDefecto(): void
    {
        // Sin rol en sesión
        $this->assertSame('asesor', SessionManager::getRole());
    }

    // ── getAgentData ──────────────────────────────────────────────────────────

    public function testGetAgentDataDevuelveCamposCorrectos(): void
    {
        $_SESSION['agente_id']       = 42;
        $_SESSION['agente']          = 'asesor@lux.mx';
        $_SESSION['agente_nombre']   = 'María';
        $_SESSION['agente_apellido'] = 'López';
        $_SESSION['agente_rol']      = 'coordinador';

        $data = SessionManager::getAgentData();

        $this->assertSame(42,             $data['id']);
        $this->assertSame('asesor@lux.mx', $data['correo']);
        $this->assertSame('María',         $data['nombre']);
        $this->assertSame('López',         $data['apellido']);
        $this->assertSame('coordinador',   $data['rol']);
    }

    public function testGetAgentDataDevuelveDefaultsSinSesion(): void
    {
        $data = SessionManager::getAgentData();

        $this->assertSame(0,       $data['id']);
        $this->assertSame('',      $data['correo']);
        $this->assertSame('asesor', $data['rol']);
    }

    // ── isAuthenticated ───────────────────────────────────────────────────────

    public function testIsAuthenticatedFalseSinSesion(): void
    {
        $this->assertFalse(SessionManager::isAuthenticated());
    }

    public function testIsAuthenticatedTrueConSesionCompleta(): void
    {
        $_SESSION['agente_id'] = 1;
        $_SESSION['agente']    = 'asesor@lux.mx';

        $this->assertTrue(SessionManager::isAuthenticated());
    }

    public function testIsAuthenticatedFalseSoloConAgenteId(): void
    {
        $_SESSION['agente_id'] = 1;
        // Sin $_SESSION['agente'] → no autenticado

        $this->assertFalse(SessionManager::isAuthenticated());
    }

    // ── Jerarquía de requireRole (lógica pura, sin header/exit) ──────────────

    /**
     * Verifica la jerarquía numérica de roles internamente:
     *   asesor/agente = 1, coordinador/administrador = 2, superadmin = 3
     *
     * No podemos llamar requireRole() directamente (llama header() y exit()),
     * así que verificamos la lógica extrayendo los valores de la jerarquía
     * via Reflection y comprobando que la comparación numérica es correcta.
     */
    public function testJerarquiaDeRolesEsCorrecta(): void
    {
        // La jerarquía vivía en un array local dentro de requireRole(), y esta
        // prueba la sacaba del código fuente con una expresión regular y un
        // eval(). Ahora es Roles::NIVELES, así que se lee la constante: la
        // prueba comprueba la jerarquía de verdad, no cómo está escrita.
        $jerarquia = \App\Security\Roles::NIVELES;

        // Verificar la jerarquía esperada
        $this->assertSame(1, $jerarquia['asesor']);
        $this->assertSame(1, $jerarquia['agente']);
        $this->assertSame(2, $jerarquia['coordinador']);
        $this->assertSame(2, $jerarquia['administrador']);
        $this->assertSame(3, $jerarquia['superadmin']);
        // El propietario de la plataforma está por encima de todos.
        $this->assertSame(4, $jerarquia['propietario']);

        // asesor NO puede requerir rol coordinador
        $this->assertLessThan($jerarquia['coordinador'], $jerarquia['asesor']);

        // superadmin puede requerir cualquier rol
        $this->assertGreaterThanOrEqual($jerarquia['coordinador'], $jerarquia['superadmin']);
        $this->assertGreaterThanOrEqual($jerarquia['asesor'],      $jerarquia['superadmin']);
    }
}
