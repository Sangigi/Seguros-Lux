<?php
declare(strict_types=1);

namespace App\Security;

use App\Models\CatalogoModel;

/**
 * Filtro de ramo único del CRM.
 *
 * Se elige en un solo sitio, el menú lateral, y lo aplican Clientes,
 * Cotizaciones, Citas, Dashboard y Reportes. Antes cada pantalla tenía sus
 * propias pestañas de ramo. El menú siempre muestra el ramo elegido, así que
 * el filtro nunca está activo sin que se vea.
 *
 * Se guarda en la sesión y se valida en cada lectura contra los ramos activos
 * de la empresa: si el administrador desactiva ese ramo, o el propietario
 * cambia a una empresa que no lo tiene, el filtro vuelve a "todos".
 */
final class FiltroRamo
{
    private const CLAVE = 'filtro_ramo';

    /** Ramo elegido, o null para todos. */
    public static function actual(): ?string
    {
        $ramo = $_SESSION[self::CLAVE] ?? null;

        if (!is_string($ramo) || $ramo === '') {
            return null;
        }

        return isset(self::activos()[$ramo]) ? $ramo : null;
    }

    /** Fija el ramo; null, 'todos' o una clave no activa lo quitan. */
    public static function fijar(?string $ramo): void
    {
        if ($ramo !== null && isset(self::activos()[$ramo])) {
            $_SESSION[self::CLAVE] = $ramo;
            return;
        }

        unset($_SESSION[self::CLAVE]);
    }

    /**
     * Ramo para la página actual. Acepta un ?ramo= de enlaces anteriores y lo
     * adopta como filtro común, para que todo el CRM siga viendo lo mismo.
     */
    public static function desdePeticion(): ?string
    {
        if (isset($_GET['ramo']) && is_string($_GET['ramo'])) {
            self::fijar($_GET['ramo']);
        }

        return self::actual();
    }

    /** @return array<string,string> clave => etiqueta de los ramos activos */
    public static function activos(): array
    {
        try {
            return Tenant::hayContexto() ? (new CatalogoModel())->ramos() : [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}
