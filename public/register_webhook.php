<?php
declare(strict_types=1);

/**
 * Registra en Telegram la URL del webhook.
 *
 * Es una acción de administración, no una página: reapunta por dónde entra
 * el bot. Antes estaba abierta, de modo que cualquiera que diera con la URL
 * podía dispararla y leer la respuesta de la API de Telegram.
 *
 * Ahora pide lo mismo que el diagnóstico: la utilidad habilitada por entorno
 * y una sesión de propietario. Ante el fallo responde 404 y no 403, para no
 * confirmar que el archivo existe.
 */

$projectRoot = dirname(__DIR__);
if (file_exists($projectRoot . '/.env.php')) {
    require_once $projectRoot . '/.env.php';
}
if (file_exists($projectRoot . '/vendor/autoload.php')) {
    require_once $projectRoot . '/vendor/autoload.php';
}
require_once $projectRoot . '/Core/Autoloader.php';

$rwNoExiste = static function (): never {
    http_response_code(404);
    echo 'Not Found';
    exit;
};

if (!filter_var(getenv('DIAGNOSTICO_HABILITADO') ?: '0', FILTER_VALIDATE_BOOLEAN)) {
    $rwNoExiste();
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ((string) ($_SESSION['agente_rol'] ?? '') !== \App\Security\Roles::PROPIETARIO) {
    $rwNoExiste();
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
    // El secreto viaja aquí una sola vez. A partir de entonces Telegram lo
    // manda en cada update (cabecera X-Telegram-Bot-Api-Secret-Token) y el
    // webhook rechaza lo que no lo traiga. Sin esto, el endpoint acepta
    // updates de cualquiera que conozca la URL.
    CURLOPT_POSTFIELDS     => json_encode(array_filter([
        'url'          => $webhookUrl,
        'secret_token' => getenv('TELEGRAM_WEBHOOK_SECRET') ?: null,
    ])),
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