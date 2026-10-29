<?php
declare(strict_types=1);

namespace App\Security;

/**
 * Roles — jerarquía y permisos del CRM, en un solo lugar.
 *
 * Antes la autorización vivía repartida en comprobaciones sueltas
 * (`if (!$isSuperadmin) { ... }`) dentro de vistas de cientos de líneas,
 * heredando variables del scope padre. Eso hace imposible responder
 * "¿quién puede hacer qué?" sin leer el sistema entero, y cada vista nueva
 * es una oportunidad de olvidar la comprobación.
 *
 * Aquí la respuesta es una matriz: un permiso con nombre, y los roles que
 * lo tienen. Añadir una capacidad es añadir una línea, no auditar vistas.
 *
 * JERARQUÍA
 *   propietario   — dueño de la plataforma. Ve todas las empresas y es el
 *                   único que puede darlas de alta. No pertenece a ninguna.
 *   superadmin    — administrador de SU empresa. Manda dentro de ella y
 *                   nunca ve nada de otra.
 *   administrador
 *   coordinador   — operación: emisión, cobros, cartera completa de su empresa.
 *   agente
 *   asesor        — su propia cartera.
 */
final class Roles
{
    public const PROPIETARIO   = 'propietario';
    public const SUPERADMIN    = 'superadmin';
    public const ADMINISTRADOR = 'administrador';
    public const COORDINADOR   = 'coordinador';
    public const AGENTE        = 'agente';
    public const ASESOR        = 'asesor';

    /** Nivel jerárquico. Mayor número = más autoridad. */
    public const NIVELES = [
        self::ASESOR        => 1,
        self::AGENTE        => 1,
        self::COORDINADOR   => 2,
        self::ADMINISTRADOR => 2,
        self::SUPERADMIN    => 3,
        self::PROPIETARIO   => 4,
    ];

    public const ETIQUETAS = [
        self::ASESOR        => 'Asesor',
        self::AGENTE        => 'Agente',
        self::COORDINADOR   => 'Coordinador',
        self::ADMINISTRADOR => 'Administrador',
        self::SUPERADMIN    => 'Administrador de empresa',
        self::PROPIETARIO   => 'Propietario de plataforma',
    ];

    // ── Permisos ──────────────────────────────────────────────────────────────

    // Plataforma — solo el propietario
    public const EMPRESAS_VER        = 'empresas.ver';
    public const EMPRESAS_CREAR      = 'empresas.crear';
    public const EMPRESAS_EDITAR     = 'empresas.editar';
    public const EMPRESAS_SUSPENDER  = 'empresas.suspender';
    public const EMPRESAS_ENTRAR     = 'empresas.entrar';
    public const CONFIG_PLATAFORMA   = 'config.plataforma';

    // Dentro de la empresa
    public const USUARIOS_VER        = 'usuarios.ver';
    public const USUARIOS_CREAR      = 'usuarios.crear';
    public const USUARIOS_EDITAR     = 'usuarios.editar';
    public const USUARIOS_DESACTIVAR = 'usuarios.desactivar';
    public const USUARIOS_INVITAR    = 'usuarios.invitar';
    public const CONFIG_EMPRESA      = 'config.empresa';

    // Operación
    public const CLIENTES_VER_TODOS  = 'clientes.ver_todos';
    public const CLIENTES_ELIMINAR   = 'clientes.eliminar';
    public const COTIZACIONES_TODAS  = 'cotizaciones.ver_todas';
    public const EMISION_GESTIONAR   = 'emision.gestionar';
    public const PAGOS_CONFIRMAR     = 'pagos.confirmar';
    public const REPORTES_EMPRESA    = 'reportes.empresa';
    public const REGISTROS_ELIMINADOS = 'registros.ver_eliminados';

    /**
     * Matriz permiso => roles que lo tienen.
     *
     * El propietario NO aparece en los permisos de operación: su trabajo es
     * administrar la plataforma, no la cartera de un cliente. Si necesita
     * operar dentro de una empresa, entra a ella explícitamente
     * (EMPRESAS_ENTRAR), lo que queda registrado.
     */
    private const MATRIZ = [
        // ── Plataforma ──
        self::EMPRESAS_VER       => [self::PROPIETARIO],
        self::EMPRESAS_CREAR     => [self::PROPIETARIO],
        self::EMPRESAS_EDITAR    => [self::PROPIETARIO],
        self::EMPRESAS_SUSPENDER => [self::PROPIETARIO],
        self::EMPRESAS_ENTRAR    => [self::PROPIETARIO],
        self::CONFIG_PLATAFORMA  => [self::PROPIETARIO],

        // ── Gestión de usuarios de la empresa ──
        self::USUARIOS_VER        => [self::PROPIETARIO, self::SUPERADMIN, self::ADMINISTRADOR, self::COORDINADOR],
        self::USUARIOS_CREAR      => [self::PROPIETARIO, self::SUPERADMIN],
        self::USUARIOS_INVITAR    => [self::PROPIETARIO, self::SUPERADMIN],
        self::USUARIOS_EDITAR     => [self::PROPIETARIO, self::SUPERADMIN],
        self::USUARIOS_DESACTIVAR => [self::PROPIETARIO, self::SUPERADMIN],
        self::CONFIG_EMPRESA      => [self::SUPERADMIN],

        // ── Operación ──
        self::CLIENTES_VER_TODOS   => [self::SUPERADMIN, self::ADMINISTRADOR, self::COORDINADOR],
        self::CLIENTES_ELIMINAR    => [self::SUPERADMIN],
        self::COTIZACIONES_TODAS   => [self::SUPERADMIN, self::ADMINISTRADOR, self::COORDINADOR],
        self::EMISION_GESTIONAR    => [self::SUPERADMIN, self::ADMINISTRADOR, self::COORDINADOR],
        self::PAGOS_CONFIRMAR      => [self::SUPERADMIN, self::ADMINISTRADOR, self::COORDINADOR],
        self::REPORTES_EMPRESA     => [self::SUPERADMIN, self::ADMINISTRADOR, self::COORDINADOR],
        self::REGISTROS_ELIMINADOS => [self::SUPERADMIN],
    ];

    // ── Consulta ──────────────────────────────────────────────────────────────

    public static function tienePermiso(string $rol, string $permiso): bool
    {
        return in_array($rol, self::MATRIZ[$permiso] ?? [], true);
    }

    public static function nivel(string $rol): int
    {
        // Un rol desconocido obtiene 0: no pasa ninguna comprobación.
        return self::NIVELES[$rol] ?? 0;
    }

    public static function alMenos(string $rol, string $minimo): bool
    {
        return self::nivel($rol) >= self::nivel($minimo) && self::nivel($rol) > 0;
    }

    public static function existe(string $rol): bool
    {
        return isset(self::NIVELES[$rol]);
    }

    public static function etiqueta(string $rol): string
    {
        return self::ETIQUETAS[$rol] ?? ucfirst($rol);
    }

    public static function esPropietario(string $rol): bool
    {
        return $rol === self::PROPIETARIO;
    }

    /** Roles que administran: ven más que su propia cartera. */
    public static function esAdministrativo(string $rol): bool
    {
        return self::nivel($rol) >= 2;
    }

    /**
     * Permisos efectivos de un rol. Útil para pintar menús sin repetir
     * la lógica de autorización en cada vista.
     *
     * @return array<int,string>
     */
    public static function permisosDe(string $rol): array
    {
        $out = [];
        foreach (self::MATRIZ as $permiso => $roles) {
            if (in_array($rol, $roles, true)) {
                $out[] = $permiso;
            }
        }
        return $out;
    }

    /**
     * Roles que un usuario puede asignar a otros.
     *
     * Nadie puede crear a alguien de su mismo nivel o superior: eso impide
     * que un administrador de empresa se fabrique un igual, o que alguien
     * se promueva creando una cuenta por encima de la suya. El propietario
     * es único y no se asigna desde la interfaz.
     *
     * @return array<int,string>
     */
    public static function asignablesPor(string $rol): array
    {
        $nivel = self::nivel($rol);

        if ($nivel <= 0) {
            return [];
        }

        $out = [];
        foreach (self::NIVELES as $candidato => $nivelCandidato) {
            if ($candidato === self::PROPIETARIO) {
                continue;
            }
            if ($nivelCandidato < $nivel) {
                $out[] = $candidato;
            }
        }

        return $out;
    }
}
