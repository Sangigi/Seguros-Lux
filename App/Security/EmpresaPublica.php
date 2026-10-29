<?php
declare(strict_types=1);

namespace App\Security;

use Database\Connection;
use Throwable;

/**
 * EmpresaPublica — a qué empresa pertenece una petición que llega sin sesión.
 *
 * Los formularios públicos y los webhooks entran sin empresa en contexto, y
 * por el principio fail-closed de `Tenant` sus consultas fallan. Antes de
 * tocar nada hay que decidir de qué empresa es el lead, y hacerlo en un solo
 * sitio: si cada endpoint improvisa su propio criterio, tarde o temprano uno
 * creará clientes en la empresa equivocada —o peor, sin empresa— y nadie los
 * volverá a ver.
 *
 * ORDEN DE RESOLUCIÓN (el mismo para todos los endpoints públicos):
 *
 *   a) Identificador explícito y verificable en la petición: el slug de la
 *      empresa (campo `empresa_slug` del formulario). Se valida contra la
 *      tabla `empresas`: tiene que existir, estar activa y no estar borrada.
 *      No se confía en el dato de entrada, se comprueba.
 *
 *   b) Si no viene, o no resuelve, la empresa por defecto de la plataforma:
 *      la de menor id activa. Es la misma que usó la migración 005 para el
 *      backfill, así que un formulario antiguo —sin slug— sigue cayendo donde
 *      caía antes de la migración.
 *
 * Nunca devuelve 0 ni null: un lead sin empresa quedaría invisible para
 * todos, que es justo lo que hay que evitar. Si no hay ninguna empresa activa
 * lanza, porque en ese punto no hay respuesta correcta posible.
 *
 * Esta clase consulta `empresas`, que es tabla de plataforma y no se filtra
 * por empresa (igual que la declara EmpresaModel con $tenantScoped = false).
 */
final class EmpresaPublica
{
    /**
     * Campo por el que un formulario público declara su empresa.
     *
     * Se llama `empresa_slug` y no `empresa` a propósito: varios formularios
     * ya usan `empresa` para el nombre del negocio del cliente (ramo
     * empresas), y confundirlos metería leads en la empresa equivocada.
     */
    public const CAMPO_SLUG = 'empresa_slug';

    /** Caché por request: estas consultas se repiten en un mismo lead. */
    private static ?int $porDefecto = null;

    /** @var array<string,int|null> */
    private static array $porSlug = [];

    private function __construct() {}

    /**
     * Empresa de una petición sin sesión: el slug si es válido, si no la
     * empresa por defecto de la plataforma.
     */
    public static function resolver(?string $slug): int
    {
        $id = self::desdeSlug($slug);

        return $id ?? self::porDefecto();
    }

    /**
     * Empresa de un slug, solo si existe, está activa y no está borrada.
     * Devuelve null cuando el slug no viene o no corresponde a ninguna: el
     * dato lo manda el navegador, así que no basta con que venga, tiene que
     * comprobarse contra la tabla.
     */
    public static function desdeSlug(?string $slug): ?int
    {
        $slug = trim((string) $slug);
        if ($slug === '') {
            return null;
        }

        if (array_key_exists($slug, self::$porSlug)) {
            return self::$porSlug[$slug];
        }

        try {
            $stmt = Connection::getConnection()->prepare(
                'SELECT id FROM empresas
                 WHERE slug = ? AND activo = 1 AND eliminado_en IS NULL
                 LIMIT 1'
            );
            $stmt->execute([$slug]);
            $id = $stmt->fetchColumn();

            return self::$porSlug[$slug] = ($id !== false && (int) $id > 0) ? (int) $id : null;
        } catch (Throwable $e) {
            error_log('[EmpresaPublica] Error resolviendo slug: ' . $e->getMessage());

            return self::$porSlug[$slug] = null;
        }
    }

    /**
     * Empresa por defecto de la plataforma: la de menor id activa.
     *
     * No es un número mágico ni una constante a mano porque el id 1 puede
     * desaparecer (empresa suspendida o borrada) y entonces los leads sin
     * slug tienen que seguir cayendo en algún sitio real.
     */
    public static function porDefecto(): int
    {
        if (self::$porDefecto !== null) {
            return self::$porDefecto;
        }

        $id = Connection::getConnection()->query(
            'SELECT MIN(id) FROM empresas WHERE activo = 1 AND eliminado_en IS NULL'
        )->fetchColumn();

        if ($id === false || (int) $id <= 0) {
            throw new \RuntimeException(
                'No hay ninguna empresa activa en la plataforma: no se puede '
                . 'asignar una petición pública a ninguna empresa.'
            );
        }

        return self::$porDefecto = (int) $id;
    }

    /**
     * True si ese id corresponde a una empresa activa.
     * Para validar identificadores que no vienen por slug (mapeos de webhook).
     */
    public static function existeActiva(int $empresaId): bool
    {
        if ($empresaId <= 0) {
            return false;
        }

        try {
            $stmt = Connection::getConnection()->prepare(
                'SELECT 1 FROM empresas
                 WHERE id = ? AND activo = 1 AND eliminado_en IS NULL
                 LIMIT 1'
            );
            $stmt->execute([$empresaId]);

            return (bool) $stmt->fetchColumn();
        } catch (Throwable $e) {
            error_log('[EmpresaPublica] Error validando empresa: ' . $e->getMessage());

            return false;
        }
    }

    /** Solo para pruebas: olvida lo memorizado en esta request. */
    public static function olvidarCache(): void
    {
        self::$porDefecto = null;
        self::$porSlug    = [];
    }
}
