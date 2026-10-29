<?php
namespace Tests\Controllers;

trait AuthSessionHelper {
    protected function autenticarSesion(int $agenteId = 1, string $rol = 'asesor', int $empresaId = 1): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        // SessionManager llama a Tenant::desdeSesion() en cada petición y lee
        // la empresa de aquí. Un login real la deja puesta; sin ella la sesión
        // de prueba queda sin empresa y toda consulta de negocio falla, que es
        // justo lo que debe pasar.
        $_SESSION['empresa_id']      = $empresaId;
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
        // Vaciar la sesión no basta: Tenant guarda la empresa en estático y
        // sobreviviría a la prueba siguiente. Se restaura la empresa por
        // defecto del bootstrap para que cada test arranque igual.
        \App\Security\Tenant::reset();
        \App\Security\Tenant::set(1);
    }
}