<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Security\SessionManager;

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