<?php
declare(strict_types=1);

$projectRoot = dirname(__DIR__);
if (file_exists($projectRoot . '/.env.php')) {
    require_once $projectRoot . '/.env.php';
}

$token = getenv('TELEGRAM_BOT_TOKEN') ?: '';
$webhookUrl = getenv('TELEGRAM_WEBHOOK_URL') ?: 'https://seguroslux.mx/telegram/webhook';

if ($token === '') {
    http_response_code(500);
    echo 'Falta TELEGRAM_BOT_TOKEN en variables de entorno.';
    exit;
}

$url = "https://api.telegram.org/bot{$token}/setWebhook";

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode(['url' => $webhookUrl]),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
]);

$response = json_decode(curl_exec($ch), true);
curl_close($ch);

echo '<pre>';
var_dump($response);
echo '</pre>';