<?php
namespace Tests\Controllers;

trait AuthSessionHelper {
    protected function autenticarSesion(int $agenteId = 1, string $rol = 'asesor'): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['agente_id']       = $agenteId;
        $_SESSION['agente']          = 'test@seguroslux.mx';
        $_SESSION['agente_nombre']   = 'Test';
        $_SESSION['agente_apellido'] = 'User';
        $_SESSION['agente_rol']      = $rol;
        $_SESSION['logged_in']       = true;
        $_SESSION['LAST_ACTIVITY']   = time();
        $_SESSION['fp_ip']           = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '');
        $_SESSION['fp_ua']           = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
    }

    protected function limpiarSesion(): void {
        $_SESSION = [];
    }
}