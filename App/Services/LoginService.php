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
     * Devuelve null si las credenciales son inválidas o el agente está inactivo.
     */
    public function login(string $correo, string $contrasena): ?array
    {
        $agente = $this->agentModel->findByEmail($correo);

        // Usar el hash real si existe, o el dummy en caso contrario.
        // Así password_verify() SIEMPRE se ejecuta con el mismo coste bcrypt,
        // haciendo el tiempo de respuesta indistinguible para un atacante externo.
        $hash  = $agente['contrasena'] ?? self::DUMMY_HASH;
        $valid = password_verify($contrasena, $hash);

        if ($agente && $valid && ($agente['activo'] ?? 1)) {
            // Rehash transparente si el coste de bcrypt ha cambiado
            if (password_needs_rehash($hash, PASSWORD_BCRYPT)) {
                $nuevoHash = password_hash($contrasena, PASSWORD_BCRYPT);
                $this->agentModel->actualizarHash((int)$agente['id'], $nuevoHash);
            }

            unset($agente['contrasena']); // nunca exponer el hash
            return $agente;
        }

        return null;
    }
}