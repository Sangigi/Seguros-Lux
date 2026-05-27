<?php
// public/SessionPing.php
require_once dirname(__DIR__) . '/.env.php';
require_once dirname(__DIR__) . '/Core/Autoloader.php';

use App\Security\SessionManager;

SessionManager::start();
http_response_code(SessionManager::isAuthenticated() ? 200 : 401);
header('Content-Type: application/json');
echo json_encode(['ok' => SessionManager::isAuthenticated()]);