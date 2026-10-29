<?php
declare(strict_types=1);

namespace App\Security;

use RuntimeException;

/**
 * Tenant — contexto de empresa para la request actual.
 *
 * Toda consulta a una tabla de negocio debe filtrarse por la empresa activa.
 * Esta clase es la única fuente de verdad de "¿qué empresa está operando?".
 *
 * PRINCIPIO: fail-closed.
 *   Si no hay contexto de empresa, las consultas NO devuelven todo: fallan.
 *   Devolver todo ante la duda es exactamente cómo se filtran datos entre
 *   empresas, así que el valor por defecto es "nada" y nunca "todo".
 *
 * El acceso sin restricción (propietario de la plataforma, crons que recorren
 * todas las empresas) existe, pero hay que pedirlo explícitamente con
 * `sinRestriccion()`, queda acotado a ese bloque y se registra. No es un
 * estado en el que el sistema pueda quedarse por accidente.
 */
final class Tenant
{
    private static ?int $empresaId = null;

    /** Profundidad del modo sin restricción (soporta anidamiento). */
    private static int $global = 0;

    /** Empresa que el propietario está inspeccionando, si aplica. */
    private static ?int $suplantada = null;

    public const SESSION_KEY = 'empresa_id';

    private function __construct() {}

    // ── Contexto ──────────────────────────────────────────────────────────────

    /**
     * Fija la empresa activa de la request.
     * Lo llama el front-controller tras autenticar, nunca el código de negocio.
     */
    public static function set(?int $empresaId): void
    {
        self::$empresaId = ($empresaId !== null && $empresaId > 0) ? $empresaId : null;
    }

    /**
     * Empresa activa, o null si no hay (propietario sin empresa seleccionada,
     * proceso CLI antes de elegir empresa, visitante anónimo).
     */
    public static function id(): ?int
    {
        return self::$suplantada ?? self::$empresaId;
    }

    /**
     * Empresa activa, obligatoria. Lanza si no hay contexto.
     * Es lo que usa BaseModel: sin empresa no se consulta nada.
     */
    public static function requireId(): int
    {
        $id = self::id();

        if ($id === null) {
            throw new RuntimeException(
                'No hay empresa en contexto. Toda consulta de negocio debe ejecutarse '
                . 'dentro de una empresa, o explícitamente con Tenant::sinRestriccion().'
            );
        }

        return $id;
    }

    public static function hayContexto(): bool
    {
        return self::id() !== null;
    }

    public static function reset(): void
    {
        self::$empresaId  = null;
        self::$global     = 0;
        self::$suplantada = null;
    }

    // ── Acceso global (plataforma) ────────────────────────────────────────────

    /**
     * True si estamos dentro de un bloque sin restricción de empresa.
     * BaseModel lo consulta para decidir si añade el filtro.
     */
    public static function esGlobal(): bool
    {
        return self::$global > 0;
    }

    /**
     * Ejecuta $fn viendo TODAS las empresas.
     *
     * Para el panel del propietario y para los crons que recorren la
     * plataforma entera. El modo se restaura siempre, incluso si $fn lanza,
     * para que una excepción no deje la request en modo global.
     *
     * @template T
     * @param  callable():T $fn
     * @return T
     */
    public static function sinRestriccion(callable $fn): mixed
    {
        self::$global++;
        try {
            return $fn();
        } finally {
            self::$global--;
        }
    }

    /**
     * Ejecuta $fn como si fuéramos una empresa concreta.
     * Lo usa el propietario al entrar a administrar una empresa, y los crons
     * al procesar empresa por empresa.
     *
     * @template T
     * @param  callable():T $fn
     * @return T
     */
    public static function comoEmpresa(int $empresaId, callable $fn): mixed
    {
        $previa           = self::$suplantada;
        self::$suplantada = $empresaId > 0 ? $empresaId : null;

        try {
            return $fn();
        } finally {
            self::$suplantada = $previa;
        }
    }

    // ── Integración con la sesión ─────────────────────────────────────────────

    /**
     * Resuelve la empresa desde la sesión ya iniciada.
     * El propietario no tiene empresa propia: opera en modo plataforma hasta
     * que elige una, momento en el que se guarda en `empresa_activa`.
     */
    public static function desdeSesion(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            self::set(null);
            return;
        }

        $rol = (string) ($_SESSION['agente_rol'] ?? '');

        if ($rol === Roles::PROPIETARIO) {
            // El propietario no pertenece a ninguna empresa; puede estar
            // inspeccionando una, y eso NO le da permisos extra: solo acota
            // lo que ve.
            $activa = isset($_SESSION['empresa_activa'])
                ? (int) $_SESSION['empresa_activa']
                : null;

            self::set(null);
            self::$suplantada = ($activa !== null && $activa > 0) ? $activa : null;
            return;
        }

        self::$suplantada = null;
        self::set(isset($_SESSION[self::SESSION_KEY]) ? (int) $_SESSION[self::SESSION_KEY] : null);
    }

    // ── Caché ─────────────────────────────────────────────────────────────────

    /**
     * Fragmento que identifica el tenant dentro de una clave de caché.
     *
     * Sin esto, dos empresas que ejecutan la misma consulta comparten entrada
     * en APCu —que es una caché de proceso compartida entre workers— y la
     * segunda recibe los datos de la primera. Cada clave de caché tiene que
     * llevar la empresa dentro.
     */
    public static function claveCache(): string
    {
        if (self::esGlobal()) {
            return 'g';
        }

        $id = self::id();

        return $id === null ? 'none' : 'e' . $id;
    }
}
