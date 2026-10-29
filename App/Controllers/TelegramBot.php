<?php
declare(strict_types=1);

/**
 * App/Controllers/TelegramBot.php
 *
 * REQUIERE en scope (inyectado por telegram_webhook.php):
 *   - $update         → array decodificado del JSON de Telegram
 *   - TG_PROJECT_ROOT → constante con ruta raíz del proyecto
 *   - .env.php ya cargado
 *   - Autoloader ya cargado
 */

use App\Models\CitaModel;
use App\Models\ClientModel;
use App\Security\EmpresaPublica;
use App\Security\Tenant;
use Database\Connection;

const MAX_CITAS_ACTIVAS = 3;

$BOT_TOKEN   = getenv('TELEGRAM_BOT_TOKEN') ?: ($_ENV['TELEGRAM_BOT_TOKEN'] ?? '');
$citaModel   = new CitaModel();
$clientModel = new ClientModel();

// ═════════════════════════════════════════════════════════════════════════════
// HELPERS — SESIÓN
// ═════════════════════════════════════════════════════════════════════════════

function tgSessionFile(string $chatId): string
{
    $dir = __DIR__ . '/bot_sessions';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    return $dir . '/tg_' . preg_replace('/\D/', '', $chatId) . '.json';
}

function tgGetState(string $chatId): array
{
    $f = tgSessionFile($chatId);
    if (!file_exists($f)) return [];
    $data = json_decode(file_get_contents($f), true) ?: [];
    // Expirar sesión si lleva más de 30 minutos sin actividad
    $maxIdle = 30 * 60;
    if (!empty($data) && isset($data['_ts']) && (time() - $data['_ts']) > $maxIdle) {
        unlink($f);
        return [];
    }
    return $data;
}

function tgSetState(string $chatId, array $state): void
{
    $f = tgSessionFile($chatId);
    if (empty($state)) { if (file_exists($f)) unlink($f); return; }
    $state['_ts'] = time(); // timestamp de última actividad
    file_put_contents($f, json_encode($state));
}

// ═════════════════════════════════════════════════════════════════════════════
// HELPERS — ENVÍO
// ═════════════════════════════════════════════════════════════════════════════

function tgMenuPrincipal(): array
{
    return [['📋 Mis citas', '❓ Ayuda'], ['🚪 Salir']];
}

function tgSend(string $chatId, string $text, string $token, ?array $keyboard = null): void
{
    $kb   = $keyboard ?? tgMenuPrincipal();
    $data = [
        'chat_id'      => $chatId,
        'text'         => $text,
        'parse_mode'   => 'HTML',
        'reply_markup' => json_encode([
            'keyboard'          => $kb,
            'resize_keyboard'   => true,
            'one_time_keyboard' => false,
        ]),
    ];
    $ch = curl_init("https://api.telegram.org/bot{$token}/sendMessage");
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($data),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    curl_exec($ch);
    curl_close($ch);
}

// ═════════════════════════════════════════════════════════════════════════════
// HELPERS — FORMATO
// ═════════════════════════════════════════════════════════════════════════════

function tgFormatFecha(string $fecha): string
{
    $meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio',
              'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    [$y, $m, $d] = explode('-', $fecha);
    $ts   = mktime(0, 0, 0, (int)$m, (int)$d, (int)$y);
    $dias = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];
    return ucfirst($dias[(int)date('w', $ts)]) . ', ' . (int)$d . ' de ' . $meses[(int)$m] . ' de ' . $y;
}

function tgResumenCita(array $cita, int $num = 0): string
{
    $iconos = ['🔵','🟢','🟡'];
    $ico    = $num > 0 ? ($iconos[$num - 1] ?? '📌') : '📌';
    $label  = $num > 0 ? "<b>Cita #{$num}</b> {$ico}\n" : '';
    return "{$label}"
         . "📅 " . tgFormatFecha($cita['fecha']) . "\n"
         . "🕐 " . substr($cita['hora'], 0, 5) . " hrs  ·  " . ucfirst($cita['tipo'] ?? 'llamada') . "\n"
         . "👤 Asesor: " . ($cita['agente_nombre'] ?? '—') . "\n"
         . "📌 Estado: " . ucfirst($cita['estado'] ?? '—');
}

function tgMostrarCitas(string $chatId, array $citas, string $token, string $contexto = 'ver'): void
{
    $total = count($citas);
    $texto = "📋 <b>Tienes {$total} cita" . ($total > 1 ? 's' : '') . " activa" . ($total > 1 ? 's' : '') . ":</b>\n\n";
    foreach ($citas as $i => $cita) {
        $texto .= tgResumenCita($cita, $i + 1) . "\n\n";
    }

    if ($total === 1) {
        $accion = match($contexto) {
            'cancelar'    => "¿Confirmas que deseas <b>cancelar</b> esta cita?",
            'reprogramar' => "¿Deseas <b>reprogramar</b> esta cita?",
            default       => "¿Qué deseas hacer con tu cita?",
        };
        $texto .= $accion;
        $teclado = match($contexto) {
            'cancelar'    => [['✅ Sí, cancelar', '❌ No, conservar'], ['🚪 Salir']],
            'reprogramar' => [['🔄 Reprogramar esta cita', '🚪 Salir']],
            default       => [['🔄 Reprogramar', '❌ Cancelar'], ['🚪 Salir']],
        };
        tgSend($chatId, $texto, $token, $teclado);
        return;
    }

    // Múltiples → botones de selección con texto plano (sin emojis numéricos para evitar variantes unicode)
    $accionLabel = match($contexto) {
        'cancelar'    => 'cancelar',
        'reprogramar' => 'reprogramar',
        default       => 'gestionar',
    };
    $texto .= "👆 Elige el número de la cita que deseas <b>{$accionLabel}</b>:";

    $btnNums = [];
    for ($i = 0; $i < $total; $i++) {
        // Texto simple sin emojis numéricos para máxima compatibilidad
        $btnNums[] = 'Cita #' . ($i + 1);
    }
    tgSend($chatId, $texto, $token, [$btnNums, ['🚪 Salir']]);
}

// ═════════════════════════════════════════════════════════════════════════════
// HELPERS — CONSULTA MÚLTIPLES CITAS
// ═════════════════════════════════════════════════════════════════════════════

function tgGetCitasActivas(string $chatId, CitaModel $citaModel): array
{
    return $citaModel->getCitasActivasByChatId($chatId, MAX_CITAS_ACTIVAS);
}


// ═════════════════════════════════════════════════════════════════════════════
// EMPRESA DE LA CONVERSACIÓN (el webhook llega sin sesión)
// ═════════════════════════════════════════════════════════════════════════════

/**
 * A qué empresa pertenece esta conversación de Telegram.
 *
 * Telegram no manda empresa: manda un chat_id y, en el alta, un token. Se
 * sigue el patrón del contrato para webhooks: identificar el registro, leer
 * su empresa en modo plataforma y entrar a ella. El orden va de lo más
 * específico a lo más general:
 *
 *   1. /start <token> → la cita que generó ese enlace. Es el caso del alta:
 *      el chat todavía no está vinculado a ningún cliente.
 *   2. chat_id ya vinculado → el cliente que lo tiene guardado.
 *   3. Nada de lo anterior → empresa por defecto de la plataforma.
 *
 * El caso 3 no abre ninguna puerta: si la búsqueda en modo plataforma no
 * encontró ese chat_id en NINGUNA empresa, tampoco lo va a encontrar en la
 * por defecto. Lo único que consigue es que el bot pueda seguir contestando
 * (ayuda, bienvenida) en vez de reventar por falta de contexto. Este bot no
 * da de alta clientes, así que no hay riesgo de crear nada en la empresa
 * equivocada.
 */
function tgResolverEmpresa(string $chatId, string $text): int
{
    return Tenant::sinRestriccion(static function () use ($chatId, $text): int {
        $db = Connection::getConnection();

        if (str_starts_with($text, '/start')) {
            $token = trim(explode(' ', $text, 2)[1] ?? '');
            if ($token !== '') {
                // tenant-ok: el webhook no sabe de qué empresa es hasta resolver la cita del token
                $st = $db->prepare('SELECT empresa_id FROM citas WHERE telegram_token = ? LIMIT 1');
                $st->execute([$token]);
                $empresa = (int) $st->fetchColumn();
                if ($empresa > 0) {
                    return $empresa;
                }
            }
        }

        // tenant-ok: el chat_id es global de Telegram; hay que buscarlo en todas las empresas para saber a cuál entrar
        $st = $db->prepare('SELECT empresa_id FROM clientes WHERE telegram_chat_id = ? LIMIT 1');
        $st->execute([$chatId]);
        $empresa = (int) $st->fetchColumn();
        if ($empresa > 0) {
            return $empresa;
        }

        return EmpresaPublica::porDefecto();
    });
}

// ═════════════════════════════════════════════════════════════════════════════
// PARSEAR UPDATE
// ═════════════════════════════════════════════════════════════════════════════

$msg    = $update['message'];
$chatId = (string) $msg['chat']['id'];
$text   = trim($msg['text'] ?? '');
$from   = $msg['from']['first_name'] ?? 'amigo';
$state  = tgGetState($chatId);

// ── Empresa de la conversación ───────────────────────────────────────────────
// Sin sesión no hay empresa en contexto y toda consulta fallaría (fail-closed).
// Se resuelve aquí y TODO el flujo del bot corre dentro de esa empresa, de
// modo que las citas que lea, cancele o reprograme sean solo las suyas.
$tgEmpresaId = tgResolverEmpresa($chatId, $text);

Tenant::comoEmpresa($tgEmpresaId, static function () use (
    $chatId, $text, $from, $state, $BOT_TOKEN, $citaModel, $clientModel
): void {

    // Si el mensaje no tiene texto (sticker, foto, audio, contacto, etc.)
    // responder amablemente y salir — no hay nada más que procesar.
    if ($text === '') {
        tgSend($chatId,
            "😊 Solo puedo entender mensajes de texto por el momento.\n\nUsa el menú de abajo para decirme qué necesitas. 👇",
            $BOT_TOKEN
        );
        return;
    }

    // ── Normalizar botones del teclado → comandos internos ────────────────────────
    $alias = [
        '📋 mis citas'            => '/miscitas',
        '❓ ayuda'                => '/ayuda',
        '🚪 salir'                => '/salir',
        '🔄 reprogramar'          => '/reprogramar',
        '🔄 reprogramar esta cita'=> '/reprogramar',
        '❌ cancelar'             => '/cancelar',
        '✅ sí, cancelar'         => 'CONFIRMAR_CANCELAR',
        '❌ no, conservar'        => 'NO_CANCELAR',
    ];
    $textNorm = mb_strtolower(trim($text));
    if (isset($alias[$textNorm])) $text = $alias[$textNorm];

    // ── Detección robusta de selección numérica ───────────────────────────────────
    // Captura: "Cita #1", "cita #2", emojis numéricos, "1", "2", "3" solos, etc.
    if (preg_match('/cita\s*#?\s*([123])/iu', $textNorm, $sel)) {
        $text = 'SEL_' . $sel[1];
    } elseif (preg_match('/^([123])$/', trim($text), $sel)) {
        $text = 'SEL_' . $sel[1];
    }

    // Reiniciar flujo si llega comando de primer nivel con flujo activo
    $primerNivel = ['/miscitas', '/ayuda', '/salir', '/start', '/cancelar', '/reprogramar'];
    if (!empty($state) && in_array($text, $primerNivel, true)) {
        tgSetState($chatId, []);
        $state = [];
    }

    $step = $state['step'] ?? '';

    // ═════════════════════════════════════════════════════════════════════════════
    // /salir
    // ═════════════════════════════════════════════════════════════════════════════
    if ($text === '/salir') {
        tgSetState($chatId, []);
        tgSend($chatId,
            "¡Hasta luego, <b>{$from}</b>! 👋\n\n"
            . "Fue un placer. Recuerda que estoy aquí cuando lo necesites. ¡Que tengas un excelente día! ☀️",
            $BOT_TOKEN
        );
        return;
    }

    // ═════════════════════════════════════════════════════════════════════════════
    // /ayuda
    // ═════════════════════════════════════════════════════════════════════════════
    if ($text === '/ayuda') {
        tgSend($chatId,
            "🤖 <b>Soy el asistente de Seguros Lux.</b> Estoy aquí para facilitarte la vida 😊\n\n"
            . "📋 <b>Mis citas</b> — Ver todas tus citas activas (hasta " . MAX_CITAS_ACTIVAS . ")\n"
            . "🔄 <b>Reprogramar</b> — Cambiar la fecha u hora de una cita\n"
            . "❌ <b>Cancelar</b> — Cancelar una cita activa\n\n"
            . "Si necesitas hablar con un asesor directamente, no dudes en llamarnos.\n\n"
            . "¡Cualquier cosa, aquí estoy! 💙",
            $BOT_TOKEN
        );
        return;
    }

    // ═════════════════════════════════════════════════════════════════════════════
    // /start [token]
    // ═════════════════════════════════════════════════════════════════════════════
    if (str_starts_with($text, '/start')) {
        $token = explode(' ', $text, 2)[1] ?? '';

        if (!$token) {
            $citas = tgGetCitasActivas($chatId, $citaModel);
            if ($citas) {
                $total  = count($citas);
                $tGiro  = $total === 1
                    ? "Tu cita activa:\n\n" . tgResumenCita($citas[0])
                    : "Tienes <b>{$total} citas activas</b>. Escribe <b>Mis citas</b> para verlas.";
                tgSend($chatId, "👋 ¡Hola de nuevo, <b>{$from}</b>! Qué gusto 😊\n\n{$tGiro}", $BOT_TOKEN);
            } else {
                tgSend($chatId,
                    "👋 ¡Hola, <b>{$from}</b>! Bienvenido al asistente de <b>Seguros Lux</b> 🎉\n\n"
                    . "Para agendar una cita visita:\n🌐 <b>seguroslux.mx/formulario</b>\n\n"
                    . "¡Será un placer atenderte! 😊",
                    $BOT_TOKEN
                );
            }
            return;
        }

        $cita = $citaModel->getByTelegramToken($token);
        if (!$cita) {
            tgSend($chatId,
                "😕 Parece que este enlace ya no es válido o ya fue utilizado.\n\n"
                . "Escribe <b>Mis citas</b> para ver tu información. 😊",
                $BOT_TOKEN
            );
            return;
        }

        $clientModel->saveTelegramChatId((int) $cita['cliente_id_real'], $chatId);
        tgSetState($chatId, []);
        tgSend($chatId,
            "🎉 ¡Todo listo, <b>{$cita['cliente_nombre']}</b>!\n\n"
            . "Tu cita quedó confirmada:\n\n"
            . tgResumenCita($cita) . "\n\n"
            . "Desde aquí podrás consultar, reprogramar o cancelar tu cita. ¡Estamos para servirte! 💙",
            $BOT_TOKEN
        );
        return;
    }

    // ═════════════════════════════════════════════════════════════════════════════
    // /miscitas
    // ═════════════════════════════════════════════════════════════════════════════
    if ($text === '/miscitas') {
        $citas = tgGetCitasActivas($chatId, $citaModel);
        if (empty($citas)) {
            tgSend($chatId,
                "📭 <b>No tienes citas activas en este momento.</b>\n\n"
                . "Cuando quieras agendar una nueva:\n🌐 <b>seguroslux.mx/formulario</b>\n\n"
                . "¡Con gusto te atendemos! 😊",
                $BOT_TOKEN
            );
            return;
        }
        tgSetState($chatId, ['citas_cache' => $citas]);
        tgMostrarCitas($chatId, $citas, $BOT_TOKEN, 'ver');
        return;
    }

    // ═════════════════════════════════════════════════════════════════════════════
    // /cancelar
    // ═════════════════════════════════════════════════════════════════════════════
    if ($text === '/cancelar') {
        $citas = tgGetCitasActivas($chatId, $citaModel);
        if (empty($citas)) {
            tgSend($chatId, "📭 No tienes citas activas para cancelar.\n\nSi necesitas algo más, ¡aquí estoy! 😊", $BOT_TOKEN);
            return;
        }
        if (count($citas) === 1) {
            tgSetState($chatId, ['step' => 'confirmar_cancelar', 'cita_id' => $citas[0]['id']]);
            tgSend($chatId,
                "⚠️ <b>¿Deseas cancelar esta cita?</b>\n\n"
                . tgResumenCita($citas[0]) . "\n\nPodrás agendar una nueva cuando lo desees. 🙌",
                $BOT_TOKEN,
                [['✅ Sí, cancelar', '❌ No, conservar'], ['🚪 Salir']]
            );
            return;
        }
        tgSetState($chatId, ['step' => 'seleccionar_cancelar', 'citas_cache' => $citas]);
        tgMostrarCitas($chatId, $citas, $BOT_TOKEN, 'cancelar');
        return;
    }

    // ═════════════════════════════════════════════════════════════════════════════
    // /reprogramar
    // ═════════════════════════════════════════════════════════════════════════════
    if ($text === '/reprogramar') {
        $citas = tgGetCitasActivas($chatId, $citaModel);
        if (empty($citas)) {
            tgSend($chatId,
                "📭 No tienes citas activas para reprogramar.\n\n¿Quieres agendar una?\n🌐 <b>seguroslux.mx/formulario</b>",
                $BOT_TOKEN
            );
            return;
        }
        if (count($citas) === 1) {
            tgSetState($chatId, ['step' => 'esperando_fecha', 'cita_id' => $citas[0]['id'], 'agente_id' => $citas[0]['agente_id']]);
            tgSend($chatId,
                "🗓 <b>Vamos a reprogramar tu cita.</b>\n\n"
                . tgResumenCita($citas[0]) . "\n\n"
                . "¿Qué fecha prefieres? Escríbela así:\n<b>DD/MM/AAAA</b>\n\nEjemplo: <b>" . date('d/m/Y', strtotime('+3 days')) . "</b>",
                $BOT_TOKEN, [['🚪 Salir']]
            );
            return;
        }
        tgSetState($chatId, ['step' => 'seleccionar_reprogramar', 'citas_cache' => $citas]);
        tgMostrarCitas($chatId, $citas, $BOT_TOKEN, 'reprogramar');
        return;
    }

    // ═════════════════════════════════════════════════════════════════════════════
    // FLUJOS MULTI-PASO
    // ═════════════════════════════════════════════════════════════════════════════

    // ── Seleccionar cita para cancelar ───────────────────────────────────────────
    if ($step === 'seleccionar_cancelar') {
        $idx   = match($text) { 'SEL_1' => 0, 'SEL_2' => 1, 'SEL_3' => 2, default => null };
        $citas = $state['citas_cache'] ?? [];

        if ($idx === null || !isset($citas[$idx])) {
            $btnNums = [];
            for ($i = 0; $i < count($citas); $i++) $btnNums[] = 'Cita #' . ($i + 1);
            tgSend($chatId, "Por favor usa los botones para elegir qué cita cancelar 👇", $BOT_TOKEN, [$btnNums, ['🚪 Salir']]);
            return;
        }

        $elegida = $citas[$idx];
        tgSetState($chatId, ['step' => 'confirmar_cancelar', 'cita_id' => $elegida['id']]);
        tgSend($chatId,
            "⚠️ <b>¿Confirmas cancelar esta cita?</b>\n\n"
            . tgResumenCita($elegida) . "\n\nPodrás agendar una nueva cuando quieras. 🙌",
            $BOT_TOKEN, [['✅ Sí, cancelar', '❌ No, conservar'], ['🚪 Salir']]
        );
        return;
    }

    // ── Confirmar cancelación ────────────────────────────────────────────────────
    if ($step === 'confirmar_cancelar') {
        if (in_array($text, ['CONFIRMAR_CANCELAR', 'SÍ', 'SI'], true)) {
            $ok = $citaModel->cancelarPorId((int) $state['cita_id'], $chatId);
            tgSetState($chatId, []);
            tgSend($chatId,
                $ok
                    ? "✅ <b>¡Cita cancelada con éxito!</b>\n\nLamentamos no poder verte esta vez. "
                      . "Cuando quieras reagendar, estaremos aquí con gusto.\n🌐 <b>seguroslux.mx/formulario</b>\n\n¡Hasta pronto! 😊"
                    : "😕 No se pudo cancelar en este momento.\n\nIntenta de nuevo o contacta directamente a tu asesor.",
                $BOT_TOKEN
            );
            return;
        }
        if (in_array($text, ['NO_CANCELAR', 'NO'], true)) {
            tgSetState($chatId, []);
            tgSend($chatId,
                "¡Perfecto! 🙌 Tu cita sigue en pie y te esperamos con gusto.\n\n¿Hay algo más en lo que pueda ayudarte?",
                $BOT_TOKEN
            );
            return;
        }
        tgSend($chatId, "Por favor usa los botones para confirmar 👇", $BOT_TOKEN,
            [['✅ Sí, cancelar', '❌ No, conservar'], ['🚪 Salir']]);
        return;
    }

    // ── Seleccionar cita para reprogramar ────────────────────────────────────────
    if ($step === 'seleccionar_reprogramar') {
        $idx   = match($text) { 'SEL_1' => 0, 'SEL_2' => 1, 'SEL_3' => 2, default => null };
        $citas = $state['citas_cache'] ?? [];

        if ($idx === null || !isset($citas[$idx])) {
            $btnNums = [];
            for ($i = 0; $i < count($citas); $i++) $btnNums[] = 'Cita #' . ($i + 1);
            tgSend($chatId, "Por favor usa los botones para elegir qué cita reprogramar 👇", $BOT_TOKEN, [$btnNums, ['🚪 Salir']]);
            return;
        }

        $elegida = $citas[$idx];
        tgSetState($chatId, ['step' => 'esperando_fecha', 'cita_id' => $elegida['id'], 'agente_id' => $elegida['agente_id']]);
        tgSend($chatId,
            "🗓 <b>Reprogramando esta cita:</b>\n\n"
            . tgResumenCita($elegida) . "\n\n"
            . "¿Qué fecha te queda mejor? Escríbela así:\n<b>DD/MM/AAAA</b>\n\nEjemplo: <b>" . date('d/m/Y', strtotime('+3 days')) . "</b>",
            $BOT_TOKEN, [['🚪 Salir']]
        );
        return;
    }

    // ── Esperando nueva fecha ─────────────────────────────────────────────────────
    if ($step === 'esperando_fecha') {
        if (!preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $text, $m)) {
            tgSend($chatId,
                "😅 No reconocí ese formato.\n\nEscribe la fecha así: <b>DD/MM/AAAA</b>\nEjemplo: <b>" . date('d/m/Y', strtotime('+3 days')) . "</b>",
                $BOT_TOKEN, [['🚪 Salir']]
            );
            return;
        }
        $fechaNueva = $m[3] . '-' . $m[2] . '-' . $m[1];
        $ts         = strtotime($fechaNueva);

        if (!$ts || $ts < strtotime('today')) {
            tgSend($chatId, "⚠️ ¡Esa fecha ya pasó! Necesitamos una fecha futura 😄", $BOT_TOKEN, [['🚪 Salir']]);
            return;
        }
        if (date('N', $ts) == 7) {
            tgSend($chatId, "😊 ¡Los domingos descansamos! Elige un día de lunes a sábado.", $BOT_TOKEN, [['🚪 Salir']]);
            return;
        }

        $horas = $citaModel->getHorasDisponibles((int) $state['agente_id'], $fechaNueva);
        // Filtrar horas pasadas si la fecha es hoy
        if ($fechaNueva === date('Y-m-d')) {
            $limiteMin = time() + 30 * 60;
            $horas = array_values(array_filter($horas, function($h) use ($limiteMin, $fechaNueva) {
                return strtotime($fechaNueva . ' ' . $h . ':00') > $limiteMin;
            }));
        }
        if (empty($horas)) {
            tgSend($chatId,
                "😔 No hay horarios disponibles el <b>" . tgFormatFecha($fechaNueva) . "</b>.\n\n¿Quieres intentar con otra fecha?",
                $BOT_TOKEN, [['🚪 Salir']]
            );
            return;
        }

        tgSetState($chatId, array_merge($state, ['step' => 'esperando_hora', 'fecha_nueva' => $fechaNueva, 'horas_disponibles' => array_values($horas)]));
        $grupos   = array_chunk($horas, 4);
        $grupos[] = ['🚪 Salir'];
        tgSend($chatId,
            "✅ <b>" . tgFormatFecha($fechaNueva) . "</b>\n\n¡Tenemos horarios disponibles! Elige el que más te acomode:",
            $BOT_TOKEN, $grupos
        );
        return;
    }

    // ── Esperando hora ────────────────────────────────────────────────────────────
    if ($step === 'esperando_hora') {
        if (!preg_match('/^\d{2}:\d{2}$/', $text)) {
            tgSend($chatId, "Por favor elige una de las horas disponibles usando los botones 👇", $BOT_TOKEN, [['🚪 Salir']]);
            return;
        }
        // Verificar que la hora siga disponible (alguien más pudo tomarla)
        if (!$citaModel->horaDisponible((int) $state['agente_id'], $state['fecha_nueva'], $text)) {
            $horasAct = $citaModel->getHorasDisponibles((int) $state['agente_id'], $state['fecha_nueva']);
            if (empty($horasAct)) {
                tgSetState($chatId, array_merge($state, ['step' => 'esperando_fecha']));
                tgSend($chatId, "😔 Esa hora ya fue tomada y no quedan más ese día.\nEscribe otra fecha (DD/MM/AAAA):", $BOT_TOKEN, [['🚪 Salir']]);
            } else {
                $grupos = array_chunk($horasAct, 4); $grupos[] = ['🚪 Salir'];
                tgSetState($chatId, array_merge($state, ['horas_disponibles' => array_values($horasAct)]));
                tgSend($chatId, "⚠️ Esa hora ya fue tomada. Elige otra:", $BOT_TOKEN, $grupos);
            }
            return;
        }
        $ok = $citaModel->reprogramar((int) $state['cita_id'], $chatId, $state['fecha_nueva'], $text);
        tgSetState($chatId, []);
        tgSend($chatId,
            $ok
                ? "🎉 <b>¡Cita reprogramada con éxito!</b>\n\n📅 " . tgFormatFecha($state['fecha_nueva'])
                  . "\n🕐 {$text} hrs\n\nTe enviaremos un recordatorio una hora antes. ¡Nos vemos pronto! 😊"
                : "😕 No se pudo reprogramar en este momento.\nEscribe <b>Reprogramar</b> para intentar de nuevo.",
            $BOT_TOKEN
        );
        return;
    }

    // ═════════════════════════════════════════════════════════════════════════════
    // SELECCIÓN SIN STEP ACTIVO (usuario eligió cita desde /miscitas para ver acciones)
    // ═════════════════════════════════════════════════════════════════════════════
    if (isset($state['citas_cache']) && str_starts_with($text, 'SEL_')) {
        $idx   = ((int) substr($text, 4)) - 1;
        $citas = $state['citas_cache'];

        if (!isset($citas[$idx])) {
            $btnNums = [];
            for ($i = 0; $i < count($citas); $i++) $btnNums[] = 'Cita #' . ($i + 1);
            tgSend($chatId, "Usa los botones para elegir 👇", $BOT_TOKEN, [$btnNums, ['🚪 Salir']]);
            return;
        }

        $elegida = $citas[$idx];
        tgSetState($chatId, ['cita_seleccionada' => $elegida, 'citas_cache' => $citas]);
        tgSend($chatId,
            tgResumenCita($elegida) . "\n\n¿Qué deseas hacer con esta cita?",
            $BOT_TOKEN,
            [['🔄 Reprogramar', '❌ Cancelar'], ['🚪 Salir']]
        );
        return;
    }

    // Si hay cita seleccionada en estado y llega acción
    if (!empty($state['cita_seleccionada'])) {
        $cSel = $state['cita_seleccionada'];

        if ($text === '/cancelar') {
            tgSetState($chatId, ['step' => 'confirmar_cancelar', 'cita_id' => $cSel['id']]);
            tgSend($chatId,
                "⚠️ <b>¿Confirmas cancelar esta cita?</b>\n\n" . tgResumenCita($cSel) . "\n\nPodrás agendar una nueva cuando lo desees. 🙌",
                $BOT_TOKEN, [['✅ Sí, cancelar', '❌ No, conservar'], ['🚪 Salir']]
            );
            return;
        }
        if ($text === '/reprogramar') {
            tgSetState($chatId, ['step' => 'esperando_fecha', 'cita_id' => $cSel['id'], 'agente_id' => $cSel['agente_id']]);
            tgSend($chatId,
                "🗓 <b>Reprogramando esta cita:</b>\n\n" . tgResumenCita($cSel) . "\n\n¿Qué fecha te queda mejor?\n<b>DD/MM/AAAA</b>\n\nEjemplo: <b>" . date('d/m/Y', strtotime('+3 days')) . "</b>",
                $BOT_TOKEN, [['🚪 Salir']]
            );
            return;
        }
    }

    // ═════════════════════════════════════════════════════════════════════════════
    // FALLBACK
    // ═════════════════════════════════════════════════════════════════════════════
    if (empty($state)) {
        tgSend($chatId,
            "👋 ¡Hola, <b>{$from}</b>! No entendí bien tu mensaje.\n\nUsa el menú de abajo para decirme qué necesitas. ¡Estoy aquí! 😊",
            $BOT_TOKEN
        );
    }

});
