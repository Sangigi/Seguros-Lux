<?php
declare(strict_types=1);

/**
 * public/telegram/telegram_webhook.php
 * Webhook receptor de Telegram. AUTÓNOMO — no depende de index.php ni ROOT.
 *
 * Ubicación en servidor: /public/telegram/telegram_webhook.php
 */

// ── 1. Ruta base del proyecto (sube 2 niveles: public/telegram → raíz) ────────
define('TG_PROJECT_ROOT', dirname(__DIR__, 2));

$envFile = TG_PROJECT_ROOT . '/.env.php';
if (is_file($envFile)) {
    require_once $envFile;
}

$tgDebugLog = filter_var(getenv('WEBHOOK_DEBUG_LOG') ?: '0', FILTER_VALIDATE_BOOLEAN);
$tgLogFile = __DIR__ . '/tg.log';

// ── 2. Autenticar el origen ──────────────────────────────────────────────────
//
// Este endpoint es público por necesidad: lo llama Telegram, no un usuario.
// Sin comprobar quién llama, cualquiera que conozca la URL puede enviar
// updates falsos y manejar el bot —dar de alta clientes, provocar respuestas
// hacia un chat_id suyo—. No hay sesión que valga aquí.
//
// Telegram ofrece para esto un secreto compartido: se declara al registrar el
// webhook (parámetro `secret_token` de setWebhook) y viene en cada petición
// en la cabecera X-Telegram-Bot-Api-Secret-Token.
//
// Si no hay secreto configurado se rechaza, en vez de aceptar a todo el
// mundo: una protección que se apaga sola cuando falta configuración no
// protege nada. Para activarlo: define TELEGRAM_WEBHOOK_SECRET y vuelve a
// registrar el webhook con register_webhook.php.
$tgSecreto  = (string) (getenv('TELEGRAM_WEBHOOK_SECRET') ?: '');
$tgRecibido = (string) ($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '');

if ($tgSecreto === '') {
    error_log('telegram_webhook: falta TELEGRAM_WEBHOOK_SECRET; se rechaza la petición.');
    http_response_code(403);
    echo json_encode(['ok' => false]);
    exit;
}

// hash_equals: la comparación no debe delatar cuántos caracteres se acertaron.
if (!hash_equals($tgSecreto, $tgRecibido)) {
    error_log('telegram_webhook: secreto inválido desde ' . ($_SERVER['REMOTE_ADDR'] ?? '?'));
    http_response_code(403);
    echo json_encode(['ok' => false]);
    exit;
}

// ── 3. Leer body UNA SOLA VEZ ────────────────────────────────────────────────
$rawBody = file_get_contents('php://input');

if ($tgDebugLog) {
    file_put_contents(
        $tgLogFile,
        date('Y-m-d H:i:s') . "\n" . $rawBody . "\n\n",
        FILE_APPEND
    );
}

// ── 4. Decodificar update ─────────────────────────────────────────────────────
$update = json_decode($rawBody, true);

if (!is_array($update)) {
    http_response_code(200);
    echo json_encode(['ok' => true]);
    exit;
}

// Solo procesar updates que tengan 'message' con texto.
// Ignorar silenciosamente: edited_message, channel_post, callback_query, 
// delivery/read status, stickers, fotos, etc.
if (!isset($update['message']['chat']['id']) || !isset($update['message']['text'])) {
    http_response_code(200);
    echo json_encode(['ok' => true]);
    exit;
}

// ── 5. Requiere .env.php para procesar mensajes ──────────────────────────────
if (!is_file($envFile)) {
    error_log("telegram_webhook: .env.php no encontrado en {$envFile}");
    if ($tgDebugLog) {
        file_put_contents($tgLogFile, date('Y-m-d H:i:s') . " ERROR: .env.php no encontrado\n\n", FILE_APPEND);
    }
    http_response_code(200);
    echo json_encode(['ok' => true]);
    exit;
}

// ── 6. Cargar Autoloader PSR-4 ────────────────────────────────────────────────
require_once TG_PROJECT_ROOT . '/Core/Autoloader.php';

// ── 7. Ejecutar lógica del bot ────────────────────────────────────────────────
// $update ya está disponible en scope para TelegramBot.php
require_once TG_PROJECT_ROOT . '/App/Controllers/TelegramBot.php';

// ── 8. Respuesta 200 obligatoria a Telegram ───────────────────────────────────
http_response_code(200);
echo json_encode(['ok' => true]);
exit;