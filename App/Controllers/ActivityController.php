<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Security\SessionManager;

/**
 * ActivityController — keepalive de sesión.
 * GET /activity/ping
 */
class ActivityController
{
    public static function handle(): void
    {
        SessionManager::ping();
    }
}