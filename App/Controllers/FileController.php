<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Security\SessionManager;
use App\Security\Tenant;
use Database\Connection;

/**
 * FileController — sirve archivos de `uploads/`.
 *
 * Comprobar que la ruta no se sale de `uploads/` evita leer el resto del
 * disco, pero no dice nada sobre de quién es el archivo. Con sesión iniciada,
 * cualquiera podía pedir cualquier expediente conociendo o adivinando su
 * ruta —y los nombres son predecibles: `cotizaciones/{id}/...`—, incluidos
 * los de otras empresas.
 *
 * Ahora, además de quedarse dentro de `uploads/`, hay que ser dueño: la ruta
 * identifica el registro del que cuelga el archivo y ese registro tiene que
 * ser de la empresa activa. Una ruta con una forma que no se reconoce se
 * rechaza, en vez de servirse por omisión.
 */
class FileController
{
    private const MIME_MAP = [
        'pdf'  => 'application/pdf',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'webp' => 'image/webp',
        'gif'  => 'image/gif',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'doc'  => 'application/msword',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'xls'  => 'application/vnd.ms-excel',
        'xml'  => 'application/xml',
        'txt'  => 'text/plain',
        'zip'  => 'application/zip',
    ];

    /**
     * ¿El archivo cuelga de un registro de la empresa activa?
     *
     * Las rutas tienen dos formas, y de ellas se deduce el dueño:
     *   cotizaciones/{cotizacion_id}/...  → la cotización
     *   polizas/{cliente_id}/...          → el cliente
     *
     * Cualquier otra forma se rechaza. Es deliberado: si mañana se guarda un
     * tipo de archivo nuevo, el fallo será «no se ve» y se arregla añadiendo
     * su caso aquí, en vez de quedar accesible para todo el mundo sin que
     * nadie se entere.
     */
    private static function esDeLaEmpresaActiva(string $relPath): bool
    {
        // Sin empresa en contexto no se sirve nada. El propietario de la
        // plataforma entra primero a una empresa; mientras no lo haga, no
        // está mirando expedientes de nadie.
        $empresaId = Tenant::id();

        if ($empresaId === null) {
            return false;
        }

        $partes = explode('/', trim(str_replace('\\', '/', $relPath), '/'));

        if (count($partes) < 2) {
            return false;
        }

        [$coleccion, $referencia] = $partes;
        $id = filter_var($referencia, FILTER_VALIDATE_INT);

        if ($id === false || $id <= 0) {
            return false;
        }

        $sql = match ($coleccion) {
            'cotizaciones' => 'SELECT 1 FROM cotizaciones WHERE id = ? AND empresa_id = ? LIMIT 1',
            'polizas'      => 'SELECT 1 FROM clientes     WHERE id = ? AND empresa_id = ? LIMIT 1',
            default        => null,
        };

        if ($sql === null) {
            return false;
        }

        try {
            $stmt = Connection::getConnection()->prepare($sql);
            $stmt->execute([$id, $empresaId]);

            return (bool) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            error_log('FileController: no se pudo verificar la propiedad del archivo: ' . $e->getMessage());

            return false;
        }
    }

    public static function handle(): void
    {
        SessionManager::requireAuth('/login');

        $relPath = $_GET['file'] ?? '';
        
        if (!$relPath) {
            http_response_code(400);
            echo 'Ruta no especificada.';
            return;
        }

        if (str_contains($relPath, '..')) {
            http_response_code(400);
            echo 'Ruta no válida.';
            return;
        }

        $uploadsRoot = realpath(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'uploads');
        if ($uploadsRoot === false) {
            http_response_code(500);
            echo 'Directorio de cargas no disponible.';
            return;
        }

        $candidate = $uploadsRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($relPath, '/'));
        $fullPath = realpath($candidate);

        if ($fullPath === false || !str_starts_with($fullPath, $uploadsRoot) || !is_file($fullPath)) {
            http_response_code(404);
            echo 'Archivo no encontrado o acceso denegado.';
            return;
        }

        // El archivo existe y está dentro de uploads/. Falta lo importante:
        // que sea de esta empresa. Misma respuesta que «no existe», para no
        // confirmar qué expedientes hay en la empresa de al lado.
        if (!self::esDeLaEmpresaActiva($relPath)) {
            http_response_code(404);
            echo 'Archivo no encontrado o acceso denegado.';
            return;
        }

        $ext  = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        $mime = self::MIME_MAP[$ext] ?? null;

        if (!$mime) {
            http_response_code(403);
            echo 'Tipo de archivo no permitido.';
            return;
        }

        $inlineMimes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'text/plain'];
        $disposition = in_array($mime, $inlineMimes, true) ? 'inline' : 'attachment';

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($fullPath));
        header('Content-Disposition: ' . $disposition . '; filename="' . basename($fullPath) . '"');
        header('Cache-Control: private, max-age=3600');
        readfile($fullPath);
    }
}