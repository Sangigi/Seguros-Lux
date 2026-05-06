<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Security\SessionManager;
use App\Security\SecurityLogger;

class LogoutController
{
    public static function handle(): void
    {
        SessionManager::start();

        $token = filter_input(INPUT_POST, 'csrf_token')
              ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

        if (!SessionManager::validateCsrf((string)$token)) {
            SecurityLogger::log('csrf_rejected', [
                'action' => 'logout',
                'ip'     => $_SERVER['REMOTE_ADDR'] ?? '',
            ]);

            $base = getenv('APP_URL') ?: '';
            header("Location: {$base}/login");
            exit;
        }

        SessionManager::destroy();

        $base = getenv('APP_URL') ?: '';
        header("Location: {$base}/login");
        exit;
    }
}