<?php
namespace Tests\Controllers;

trait AuthSessionHelper {
    protected function autenticarSesion(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['agente_id']     = 1;
        $_SESSION['agente_rol']    = 'asesor';
        $_SESSION['logged_in']     = true;
        $_SESSION['last_activity'] = time();
    }

    protected function limpiarSesion(): void {
        $_SESSION = [];
    }
}