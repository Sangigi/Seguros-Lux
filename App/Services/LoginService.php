<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\AgentModel;

/**
 * Servicio de autenticación.
 *
 * Mejoras de seguridad aplicadas:
 *  - Timing attack fix: password_verify() se ejecuta SIEMPRE,
 *    incluso cuando el correo no existe, para que el tiempo de
 *    respuesta sea constante y no revele si un email está registrado.
 *  - El hash dummy usa el mismo coste (12) que PASSWORD_BCRYPT por defecto.
 *
 * MULTIEMPRESA
 *  Desde que el correo es único por empresa y no globalmente, una persona
 *  puede tener cuenta en varias empresas con el mismo correo. El login
 *  prueba la contraseña contra todas sus cuentas:
 *    - una coincidencia  → entra directo;
 *    - varias            → se le pide elegir empresa;
 *    - ninguna           → credenciales inválidas, sin decir por qué.
 *
 *  Nunca se revela en qué empresas existe un correo antes de validar la
 *  contraseña: eso convertiría el login en un directorio de clientes.
 */
class LoginService
{
    /**
     * Hash bcrypt inerte usado cuando el correo no existe en BD.
     * Permite que password_verify() tarde el mismo tiempo que con un hash real,
     * eliminando el timing side-channel de enumeración de usuarios.
     *
     * ⚠️  NO cambies este valor: fue generado con password_hash('dummy', PASSWORD_BCRYPT).
     */
    private const DUMMY_HASH = '$2y$12$invalidhashpadXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX';

    public function __construct(private AgentModel $agentModel) {}

    /**
     * Verifica credenciales y devuelve los datos del agente (sin hash).
     * Devuelve null si son inválidas, si la cuenta está inactiva o si su
     * empresa está suspendida.
     *
     * @param int|null $empresaId Empresa elegida cuando el correo existe en varias.
     */
    public function login(string $correo, string $contrasena, ?int $empresaId = null): ?array
    {
        $resultado = $this->intentar($correo, $contrasena, $empresaId);

        return $resultado['estado'] === 'ok' ? $resultado['agente'] : null;
    }

    /**
     * Igual que login(), pero distingue el caso "el correo existe en varias
     * empresas y hay que elegir una".
     *
     * @return array{estado:string, agente?:array<string,mixed>, empresas?:array<int,array{id:int,nombre:string}>}
     */
    public function intentar(string $correo, string $contrasena, ?int $empresaId = null): array
    {
        $cuentas = $this->agentModel->cuentasPorCorreo($correo);

        // Sin cuentas: se verifica igual contra el hash inerte para que el
        // tiempo de respuesta no delate si el correo existe.
        if ($cuentas === []) {
            password_verify($contrasena, self::DUMMY_HASH);
            return ['estado' => 'invalido'];
        }

        if ($empresaId !== null) {
            $cuentas = array_values(array_filter(
                $cuentas,
                static fn(array $c): bool => (int) $c['empresa_id'] === $empresaId
            ));

            if ($cuentas === []) {
                password_verify($contrasena, self::DUMMY_HASH);
                return ['estado' => 'invalido'];
            }
        }

        // Se prueban TODAS las cuentas antes de decidir: salir en la primera
        // coincidencia haría que el tiempo de respuesta dependiera de en qué
        // empresa está la cuenta.
        $validas = [];
        foreach ($cuentas as $cuenta) {
            $hash = (string) ($cuenta['contrasena'] ?? self::DUMMY_HASH);

            if (password_verify($contrasena, $hash)) {
                $validas[] = $cuenta;
            }
        }

        // Solo cuentas activas, de empresas activas.
        $utilizables = array_values(array_filter($validas, static function (array $c): bool {
            $cuentaActiva  = (int) ($c['activo'] ?? 1) === 1;
            $empresaActiva = $c['empresa_id'] === null || (int) ($c['empresa_activa'] ?? 1) === 1;

            return $cuentaActiva && $empresaActiva;
        }));

        if ($utilizables === []) {
            return ['estado' => $validas === [] ? 'invalido' : 'inactivo'];
        }

        if (count($utilizables) > 1) {
            return [
                'estado'   => 'elegir_empresa',
                'empresas' => array_map(static fn(array $c): array => [
                    'id'     => (int) $c['empresa_id'],
                    'nombre' => (string) ($c['empresa_nombre'] ?? 'Empresa'),
                ], $utilizables),
            ];
        }

        $agente = $utilizables[0];

        // Rehash transparente si el coste de bcrypt ha cambiado
        $hash = (string) $agente['contrasena'];
        if (password_needs_rehash($hash, PASSWORD_BCRYPT)) {
            $this->agentModel->actualizarHash(
                (int) $agente['id'],
                password_hash($contrasena, PASSWORD_BCRYPT)
            );
        }

        $this->agentModel->registrarAcceso((int) $agente['id']);

        unset($agente['contrasena']); // nunca exponer el hash

        return ['estado' => 'ok', 'agente' => $agente];
    }
}
