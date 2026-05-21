<?php
declare(strict_types=1);

/**
 * App/Controllers/WhatsAppBot.php
 * Bot conversacional WhatsApp. Menú principal: Cotizar (Auto/Vida/GMM) + Gestión citas + Asesor.
 * FIX: $WA_LOG como constante para evitar error "null given" en funciones.
 */

use App\Models\CitaModel;
use App\Models\ClientModel;

$WA_TOKEN    = getenv('WHATSAPP_TOKEN')    ?: ($_ENV['WHATSAPP_TOKEN']    ?? '');
$WA_PHONE_ID = getenv('WHATSAPP_PHONE_ID') ?: ($_ENV['WHATSAPP_PHONE_ID'] ?? '');

if (!defined('WA_PROJECT_ROOT')) {
    define('WA_PROJECT_ROOT', dirname(__DIR__, 2));
}

// ── FIX CRÍTICO: usar define() en lugar de variable global ───────────────────
if (!defined('WA_LOG_PATH')) {
    define('WA_LOG_PATH', WA_PROJECT_ROOT . '/public/whatsapp/wa.log');
}

if (!function_exists('wa_bot_debug_log')) {
    function wa_bot_debug_log(string $message): void
    {
        if (!filter_var(getenv('WEBHOOK_DEBUG_LOG') ?: '0', FILTER_VALIDATE_BOOLEAN)) {
            return;
        }
        file_put_contents(WA_LOG_PATH, date('Y-m-d H:i:s') . ' ' . $message . "\n\n", FILE_APPEND);
    }
}

if (!$WA_TOKEN || !$WA_PHONE_ID) {
    error_log('WhatsAppBot: faltan WHATSAPP_TOKEN o WHATSAPP_PHONE_ID');
    return;
}

$citaModel   = new CitaModel();
$clientModel = new ClientModel();
$_waDb       = \Database\Connection::getConnection();

// ══════════════════════════════════════════════════════════════════════════════
// HELPERS DE ENVÍO (usan WA_LOG_PATH constante, no variable global)
// ══════════════════════════════════════════════════════════════════════════════

function waSend(string $to, string $text, string $pid, string $tok, ?array $buttons = null): void
{
    if ($buttons) {
        $btns = [];
        foreach (array_slice($buttons, 0, 3) as $i => $lbl) {
            $btns[] = ['type'=>'reply','reply'=>['id'=>'b'.$i,'title'=>mb_substr($lbl,0,20)]];
        }
        $body = ['messaging_product'=>'whatsapp','to'=>$to,'type'=>'interactive',
                 'interactive'=>['type'=>'button','body'=>['text'=>$text],'action'=>['buttons'=>$btns]]];
    } else {
        $body = ['messaging_product'=>'whatsapp','to'=>$to,'type'=>'text','text'=>['body'=>$text]];
    }
    $ch = curl_init("https://graph.facebook.com/v19.0/{$pid}/messages");
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($body),
        CURLOPT_HTTPHEADER=>['Content-Type: application/json',"Authorization: Bearer {$tok}"],
        CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10,
        CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);
    $resp=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
    wa_bot_debug_log("SEND {$to} http={$code}" . ($err ? " err={$err}" : '')
        . ' ' . substr((string) $resp, 0, 200));
}

function waList(string $to,string $hdr,string $body,string $btn,array $rows,string $pid,string $tok): void
{
    $secs=[['title'=>'Opciones','rows'=>[]]];
    foreach (array_slice($rows,0,10) as $i=>$r)
        $secs[0]['rows'][]=['id'=>'o'.$i,'title'=>mb_substr($r,0,24)];
    $pl=['messaging_product'=>'whatsapp','to'=>$to,'type'=>'interactive',
         'interactive'=>['type'=>'list','header'=>['type'=>'text','text'=>$hdr],
                         'body'=>['text'=>$body],
                         'action'=>['button'=>mb_substr($btn,0,20),'sections'=>$secs]]];
    $ch=curl_init("https://graph.facebook.com/v19.0/{$pid}/messages");
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($pl),
        CURLOPT_HTTPHEADER=>['Content-Type: application/json',"Authorization: Bearer {$tok}"],
        CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10,
        CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);
    $resp=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
    wa_bot_debug_log("LIST {$to} http={$code}" . ($err ? " err={$err}" : '')
        . ' ' . substr((string) $resp, 0, 200));
}

function waFile(string $p): string
{
    $d=WA_PROJECT_ROOT.'/App/Controllers/bot_sessions';
    if(!is_dir($d))mkdir($d,0755,true);
    return $d.'/wa_'.preg_replace('/\D/','',$p).'.json';
}
function waGetState(string $p): array
{
    $f = waFile($p);
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
function waSetState(string $p, array $s): void
{
    $f = waFile($p);
    if (empty($s)) { if (file_exists($f)) unlink($f); return; }
    $s['_ts'] = time(); // timestamp de última actividad
    file_put_contents($f, json_encode($s));
}
function waFecha(string $f): string
{
    $m=['','Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
    [$y,$mo,$d]=explode('-',$f); return (int)$d.' '.$m[(int)$mo].' '.$y;
}
function waResumen(array $c): string
{
    return "📅 *Fecha:* ".waFecha($c['fecha'])."\n🕐 *Hora:* ".substr($c['hora'],0,5)." hrs\n"
          ."📋 *Tipo:* ".ucfirst($c['tipo']??'llamada')."\n👤 *Asesor:* ".($c['agente_nombre']??'—')
          ."\n📌 *Estado:* ".ucfirst($c['estado']??'—');
}

function waGetCita(string $phone, $db): ?array
{
    // 1. Buscar por whatsapp_chat_id en clientes → cita activa (solo futuras)
    try {
        $st=$db->prepare(
            "SELECT ci.*, CONCAT(a.nombre,' ',a.apellido) AS agente_nombre
             FROM citas ci JOIN clientes cl ON cl.id=ci.cliente_id
             LEFT JOIN agentes a ON a.id=ci.agente_id
             WHERE cl.whatsapp_chat_id=? AND ci.estado='programada'
               AND TIMESTAMP(ci.fecha, ci.hora) >= NOW()
             ORDER BY ci.fecha ASC, ci.hora ASC LIMIT 1");
        $st->execute([$phone]); $r=$st->fetch(); if($r) return $r;
    } catch(\Throwable $e){}
    // 2. Fallback: últimos 10 dígitos del teléfono
    $last10=substr(preg_replace('/\D/','',$phone),-10);
    try {
        $st=$db->prepare(
            "SELECT ci.*, CONCAT(a.nombre,' ',a.apellido) AS agente_nombre
             FROM citas ci JOIN clientes cl ON cl.id=ci.cliente_id
             LEFT JOIN agentes a ON a.id=ci.agente_id
             WHERE (cl.telefono LIKE ? OR cl.telefono LIKE ? OR cl.telefono LIKE ?)
               AND ci.estado='programada'
               AND TIMESTAMP(ci.fecha, ci.hora) >= NOW()
             ORDER BY ci.fecha ASC, ci.hora ASC LIMIT 1");
        $st->execute(["%{$last10}","+52{$last10}","52{$last10}"]); $r=$st->fetch();
        if($r){ $db->prepare("UPDATE clientes SET whatsapp_chat_id=? WHERE id=?")->execute([$phone,$r['cliente_id']]); return $r; }
    } catch(\Throwable $e){}
    return null;
}

function waLinkPhone(string $phone,string $nombre,$db): void
{
    $partes=preg_split('/\s+/',trim($nombre),3);
    $n1=$partes[0]??''; $n2=$partes[1]??'';
    if(!$n1) return;
    try {
        $st=$db->prepare("SELECT id FROM clientes WHERE nombre LIKE ? AND apellido_paterno LIKE ? ORDER BY fecha_registro DESC LIMIT 1");
        $st->execute(["%{$n1}%","%{$n2}%"]); $row=$st->fetch();
        if($row) $db->prepare("UPDATE clientes SET whatsapp_chat_id=? WHERE id=?")->execute([$phone,$row['id']]);
    } catch(\Throwable $e){}
}

function waCotizarAuto(array $s): string
{
    $año=(int)($s['anio']??date('Y')); $ant=date('Y')-$año;
    if($ant<3)$b=9500; elseif($ant<7)$b=7500; else $b=5500;
    if(($s['uso']??'')==='plataforma')$b+=2000;
    return "🚗 *Cotización estimada*\n_{$s['marca']} {$s['modelo']} {$año}_\n\n"
          ."📋 *Básica:* ~$".number_format($b*.55,0)." MXN/año\n   RC + Médicos + Asistencia\n\n"
          ."📋 *Limitada:* ~$".number_format($b*.75,0)." MXN/año\n   + Robo total + Daños parciales\n\n"
          ."📋 *Amplia:* ~$".number_format($b,0)." MXN/año\n   + Daños materiales + Cristales + Auto sustituto\n\n"
          ."_Estimación orientativa. Cotización final con asesor._";
}

// ══════════════════════════════════════════════════════════════════════════════
// MENÚ PRINCIPAL (5 opciones via 2 mensajes)
// ══════════════════════════════════════════════════════════════════════════════

function waMenuPrincipal(string $phone, string $from, string $pid, string $tok, $db): void
{
    $cita = waGetCita($phone,$db);
    // Mensaje 1: opciones de cotización (3 botones)
    waSend($phone, "👋 ¡Hola, *{$from}*! Soy el asistente de *Seguros LUX* 🛡️\n\n"
        ."¿Qué tipo de seguro te interesa hoy?",$pid,$tok,
        ['🚗 Seguro de Auto','❤️ Seguro de Vida','🏥 GMM']);
    // Mensaje 2: opciones de servicio (hasta 3 botones)
    $btns2 = $cita ? ['📋 Ver mi cita','📞 Contactar asesor'] : ['📋 Ver mis citas','📞 Contactar asesor'];
    waSend($phone,"También puedes:",$pid,$tok,$btns2);
}

// ══════════════════════════════════════════════════════════════════════════════
// PARSEO
// ══════════════════════════════════════════════════════════════════════════════
try {

$msg   = $value['messages'][0];
$phone = $msg['from'];
$from  = $value['contacts'][0]['profile']['name'] ?? 'Cliente';
// LOG FORZADO (diagnóstico) — eliminar después de confirmar fix
file_put_contents(WA_LOG_PATH, date('Y-m-d H:i:s') . " RAW {$phone}: " . json_encode($msg) . "\n", FILE_APPEND);
wa_bot_debug_log("MSG {$phone} ({$from}): " . json_encode($msg));

$text='';$interactiveId='';
if($msg['type']==='text') $text=trim($msg['text']['body']??'');
elseif($msg['type']==='interactive'){
    $it=$msg['interactive']['type']??'';
    $replyKey=$it==='button_reply'?'button_reply':'list_reply';
    $text=trim($msg['interactive'][$replyKey]['title']??'');
    $interactiveId=trim($msg['interactive'][$replyKey]['id']??'');
}

$state   =waGetState($phone);
$textNorm=mb_strtolower(trim($text));
$step    =$state['step']??'';
// LOG FORZADO (diagnóstico)
file_put_contents(WA_LOG_PATH, date('Y-m-d H:i:s') . " STATE phone={$phone} step=[{$step}] text=[{$text}] iid=[{$interactiveId}] state=" . json_encode($state) . "\n", FILE_APPEND);

// Aliases
$alias=[
    '📋 ver mi cita'=>'/micita','📋 ver mis citas'=>'/micita',
    'mi cita'=>'/micita','ver mi cita'=>'/micita','mis citas'=>'/micita',
    '❌ cancelar cita'=>'/cancelar','cancelar cita'=>'/cancelar',
    '🔄 reprogramar'=>'/reprog','reprogramar'=>'/reprog',
    '🚪 salir'=>'/salir','salir'=>'/salir','exit'=>'/salir',
    '✅ sí, cancelar'=>'CONF_CANCEL','sí, cancelar'=>'CONF_CANCEL','si, cancelar'=>'CONF_CANCEL',
    '❌ no, conservar'=>'NO_CANCEL','no, conservar'=>'NO_CANCEL',
    '🚗 seguro de auto'=>'/auto','seguro de auto'=>'/auto','auto'=>'/auto','autos'=>'/auto',
    '❤️ seguro de vida'=>'/vida','seguro de vida'=>'/vida','vida'=>'/vida',
    '🏥 gmm'=>'/gmm','gmm'=>'/gmm','gastos médicos'=>'/gmm','gastos medicos'=>'/gmm',
    '📞 contactar asesor'=>'/asesor','contactar asesor'=>'/asesor','asesor'=>'/asesor','hablar con asesor'=>'/asesor',
    '🏠 menú principal'=>'/menu','menú'=>'/menu','menu'=>'/menu','inicio'=>'/menu',
    '📅 agendar asesoría'=>'/agendar','agendar asesoría'=>'/agendar','agendar'=>'/agendar',
    '📅 solicitar asesoría'=>'/agendar','solicitar asesoría'=>'/agendar',
];
if(isset($alias[$textNorm])) $text=$alias[$textNorm];

// Top-level commands reset flow
// EXCEPCIÓN: /cancelar desde dentro de un flujo de cita activo → ir directo al confirm_cancel
$toplevel=['/micita','/cancelar','/reprog','/salir','/menu','/auto','/vida','/gmm','/asesor'];
$flujoCita=['confirm_cancel','reprog_fecha','reprog_hora'];
if(!empty($state)&&in_array($text,$toplevel,true)){
    // Si el usuario pide cancelar estando en un flujo de cita, conservar el cita_id
    if($text==='/cancelar'&&in_array($step,$flujoCita,true)&&!empty($state['cita_id'])){
        $citaId=$state['cita_id'];
        waSetState($phone,['step'=>'confirm_cancel','cita_id'=>$citaId]);
        $state=['step'=>'confirm_cancel','cita_id'=>$citaId]; $step='confirm_cancel';
        // No hacemos return aquí; dejamos caer al bloque confirm_cancel abajo
    } else {
        waSetState($phone,[]); $state=[]; $step='';
    }
}

// ── /salir ────────────────────────────────────────────────────────────────────
if($text==='/salir'){
    waSetState($phone,[]);
    waSend($phone,"✅ ¡Hasta pronto! Escribe *menú* cuando quieras 👋",$WA_PHONE_ID,$WA_TOKEN);
    return;
}

// ── /asesor ───────────────────────────────────────────────────────────────────
if($text==='/asesor'){
    waSetState($phone,[]);
    waSend($phone,"📞 *Contacta a un asesor directamente:*\n\n☎️ *800 123 4567*\n"
        ."📧 contacto@seguroslux.mx\n🌐 seguroslux.mx/contacto\n\n"
        ."Horario: Lun-Vie 9am–7pm · Sáb 9am–2pm",$WA_PHONE_ID,$WA_TOKEN,
        ['🏠 Menú principal','📋 Ver mi cita']);
    return;
}

// ── /agendar ─────────────────────────────────────────────────────────────────
if($text==='/agendar'){
    waSetState($phone,[]);
    waSend($phone,"📅 *Agenda tu cita gratis:*\n\n🌐 *seguroslux.mx/formulario*\n\n"
        ."O llama: ☎️ *800 123 4567*",$WA_PHONE_ID,$WA_TOKEN,
        ['🏠 Menú principal','📋 Ver mi cita','📞 Contactar asesor']);
    return;
}

// ── Menú / Bienvenida ─────────────────────────────────────────────────────────
$esMenu=in_array($text,['/menu','/start'])||str_starts_with($text,'/start')
    ||in_array($textNorm,['hola','hi','buenos días','buenos dias','buenas','inicio']);
$esDesdeForm=str_starts_with($textNorm,'hola, acabo de agendar una cita');

if($esMenu||$esDesdeForm){
    waSetState($phone,[]);
    if($esDesdeForm){
        waLinkPhone($phone,$from,$_waDb);
        $cita=waGetCita($phone,$_waDb);
        $msgC="✅ *¡Cita registrada!*\n\n"
            .($cita?"Tu cita:\n\n".waResumen($cita)."\n\n":"")
            ."Tu asesor se pondrá en contacto pronto. Escribe *mi cita* para ver los detalles.";
        waSend($phone,$msgC,$WA_PHONE_ID,$WA_TOKEN,['📋 Ver mi cita','🏠 Menú principal','📞 Contactar asesor']);
        return;
    }
    waMenuPrincipal($phone,$from,$WA_PHONE_ID,$WA_TOKEN,$_waDb);
    return;
}

// ══════════════════════════════════════════════════════════════════════════════
// FLUJOS COTIZACIÓN — AUTO
// ══════════════════════════════════════════════════════════════════════════════
if($text==='/auto'){
    waSetState($phone,['flow'=>'auto','step'=>'marca']);
    waSend($phone,"🚗 *Cotización de Auto*\n\n¿Cuál es la *marca* de tu vehículo?\n_(Ej: Nissan, Toyota, Honda, VW...)_",$WA_PHONE_ID,$WA_TOKEN);
    return;
}
if($step==='marca'&&($state['flow']??'')==='auto'){
    waSetState($phone,array_merge($state,['marca'=>$text,'step'=>'modelo']));
    waSend($phone,"✅ *{$text}* — ¿Cuál es el *modelo*?\n_(Ej: Versa, Corolla, Jetta...)_",$WA_PHONE_ID,$WA_TOKEN);
    return;
}
if($step==='modelo'&&($state['flow']??'')==='auto'){
    waSetState($phone,array_merge($state,['modelo'=>$text,'step'=>'anio']));
    waSend($phone,"✅ ¿De qué *año* es tu {$state['marca']} {$text}?\n_(Ej: 2024, 2022, 2019...)_",$WA_PHONE_ID,$WA_TOKEN);
    return;
}
if($step==='anio'&&($state['flow']??'')==='auto'){
    $año=(int)preg_replace('/\D/','',$text);
    if($año<1990||$año>(int)date('Y')+1){waSend($phone,"⚠️ Año no válido. Ingresa entre 1990 y ".(date('Y')+1),$WA_PHONE_ID,$WA_TOKEN);return;}
    waSetState($phone,array_merge($state,['anio'=>$año,'step'=>'uso']));
    waSend($phone,"✅ ¿Cuál es el *uso principal* del vehículo?",$WA_PHONE_ID,$WA_TOKEN,
        ['Particular','Trabajo/Oficina','Plataforma (Uber/Didi)']);
    return;
}
if($step==='uso'&&($state['flow']??'')==='auto'){
    $uso=str_contains(mb_strtolower($text),'plataforma')||str_contains(mb_strtolower($text),'uber')?'plataforma':'particular';
    $s2=array_merge($state,['uso'=>$uso,'step'=>'cotizado']);
    waSetState($phone,$s2);
    waSend($phone,waCotizarAuto($s2),$WA_PHONE_ID,$WA_TOKEN);
    waSend($phone,"¿Qué deseas hacer?",$WA_PHONE_ID,$WA_TOKEN,['📅 Agendar con asesor','📋 Ver mi cita','🏠 Menú principal']);
    return;
}

// ══════════════════════════════════════════════════════════════════════════════
// FLUJOS COTIZACIÓN — VIDA
// ══════════════════════════════════════════════════════════════════════════════
if($text==='/vida'){
    waSetState($phone,['flow'=>'vida','step'=>'edad_vida']);
    waSend($phone,"❤️ *Seguro de Vida*\n\nTe haré unas preguntas rápidas.\n¿Cuántos años tienes?",$WA_PHONE_ID,$WA_TOKEN);
    return;
}
if($step==='edad_vida'){
    $edad=(int)preg_replace('/\D/','',$text);
    if($edad<18||$edad>75){waSend($phone,"⚠️ La edad debe ser entre 18 y 75 años.",$WA_PHONE_ID,$WA_TOKEN);return;}
    waSetState($phone,['flow'=>'vida','step'=>'obj_vida','edad'=>$edad]);
    waSend($phone,"✅ ¿Cuál es tu objetivo principal?",$WA_PHONE_ID,$WA_TOKEN,
        ['Proteger a mi familia','Ahorro a largo plazo','Herencia/Retiro']);
    return;
}
if($step==='obj_vida'){
    $edad=$state['edad']??30;
    if($edad<35)$prima='~$350';elseif($edad<50)$prima='~$650';else$prima='~$1,200';
    waSetState($phone,[]);
    waSend($phone,"❤️ *Orientación — Seguro de Vida*\n\n"
        ."Con *{$edad} años* · Objetivo: _{$text}_\n\n"
        ."💡 Opciones recomendadas:\n"
        ."• *Temporal 20 años* — Protección familiar ({$prima}/mes est.)\n"
        ."• *Vida Entera* — Protección + Ahorro permanente\n"
        ."• *Dotal* — Ahorro a plazo fijo con cobertura\n\n"
        ."_Cotización final con asesor, sin costo ni compromiso._",$WA_PHONE_ID,$WA_TOKEN,
        ['📅 Agendar asesoría','📞 Contactar asesor','🏠 Menú principal']);
    return;
}

// ══════════════════════════════════════════════════════════════════════════════
// FLUJOS COTIZACIÓN — GMM
// ══════════════════════════════════════════════════════════════════════════════
if($text==='/gmm'){
    waSetState($phone,['flow'=>'gmm','step'=>'edad_gmm']);
    waSend($phone,"🏥 *Gastos Médicos Mayores*\n\n¿Cuántos años tienes?",$WA_PHONE_ID,$WA_TOKEN);
    return;
}
if($step==='edad_gmm'){
    $edad=(int)preg_replace('/\D/','',$text);
    if($edad<0||$edad>85){waSend($phone,"⚠️ Ingresa una edad válida.",$WA_PHONE_ID,$WA_TOKEN);return;}
    waSetState($phone,['flow'=>'gmm','step'=>'dep_gmm','edad'=>$edad]);
    waSend($phone,"✅ ¿El seguro incluiría más personas además de ti?",$WA_PHONE_ID,$WA_TOKEN,
        ['Solo yo','Pareja','Familia (con hijos)']);
    return;
}
if($step==='dep_gmm'){
    $edad=$state['edad']??30; $dep=$text;
    $esFam=str_contains(mb_strtolower($dep),'famil')||str_contains(mb_strtolower($dep),'hijos');
    $esPar=str_contains(mb_strtolower($dep),'parej');
    if($esFam){$b='~$18,000';$m='~$30,000';$p='~$50,000+';}
    elseif($esPar){$b='~$12,000';$m='~$20,000';$p='~$35,000+';}
    else{$b='~$7,000';$m='~$12,000';$p='~$22,000+';}
    waSetState($phone,[]);
    waSend($phone,"🏥 *GMM — {$edad} años / {$dep}*\n\n"
        ."💊 *Esencial:* {$b}/año — Hospitalización · Cirugías · Médicos\n"
        ."💊 *Estándar:* {$m}/año — + Red amplia · Suma mayor\n"
        ."💊 *Premium:* {$p}/año — + Internacional · Sin deducible · Top hospitales\n\n"
        ."_Cotización exacta requiere asesor por historial médico._",$WA_PHONE_ID,$WA_TOKEN,
        ['📅 Solicitar asesoría','📞 Contactar asesor','🏠 Menú principal']);
    return;
}

// ══════════════════════════════════════════════════════════════════════════════
// GESTIÓN DE CITAS
// ══════════════════════════════════════════════════════════════════════════════
if($text==='/micita'){
    $cita=waGetCita($phone,$_waDb);
    if(!$cita) waSend($phone,"📭 *Sin citas activas.*\n\n🌐 seguroslux.mx/formulario",$WA_PHONE_ID,$WA_TOKEN,
        ['🚗 Seguro de Auto','🏠 Menú principal']);
    else waSend($phone,"📋 *Tu cita activa:*\n\n".waResumen($cita)."\n\n¿Qué deseas hacer?",$WA_PHONE_ID,$WA_TOKEN,
        ['🔄 Reprogramar','❌ Cancelar cita','🚪 Salir']);
    return;
}
if($text==='/cancelar'){
    // Si hay una cita en sesión, verificar que sigue siendo válida (no puede estar completada o cancelada ya)
    $citaIdEnSesion = (int)($state['cita_id'] ?? 0);
    if($citaIdEnSesion){
        $stCheck=$_waDb->prepare("SELECT id, estado FROM citas WHERE id=? LIMIT 1");
        $stCheck->execute([$citaIdEnSesion]);
        $citaCheck=$stCheck->fetch();
        // Si la cita ya fue cancelada o completada, limpiar sesión y buscar de nuevo
        if(!$citaCheck || in_array($citaCheck['estado'],['cancelada','completada'],true)){
            $citaIdEnSesion=0;
            waSetState($phone,[]);
            $state=[]; $step='';
        }
    }
    $cita=$citaIdEnSesion ? null : waGetCita($phone,$_waDb);
    // Si venimos de sesión con cita_id válida (programada o vencida), reconstruir la cita
    if(!$cita && $citaIdEnSesion){
        $stCita=$_waDb->prepare(
            "SELECT ci.*, CONCAT(a.nombre,' ',a.apellido) AS agente_nombre
             FROM citas ci LEFT JOIN agentes a ON a.id=ci.agente_id
             WHERE ci.id=? LIMIT 1");
        $stCita->execute([$citaIdEnSesion]);
        $cita=$stCita->fetch() ?: null;
    }
    if(!$cita){waSend($phone,"📭 No tienes citas activas.",$WA_PHONE_ID,$WA_TOKEN,['🏠 Menú principal']);return;}
    waSetState($phone,['step'=>'confirm_cancel','cita_id'=>$cita['id']]);
    waSend($phone,"⚠️ *¿Confirmas cancelar tu cita?*\n\n".waResumen($cita),$WA_PHONE_ID,$WA_TOKEN,
        ['✅ Sí, cancelar','❌ No, conservar']);
    return;
}
if($text==='/reprog'){
    // Si hay una cita en sesión, verificar que sigue siendo válida
    $citaIdEnSesion = (int)($state['cita_id'] ?? 0);
    if($citaIdEnSesion){
        $stCheck=$_waDb->prepare("SELECT id, estado FROM citas WHERE id=? LIMIT 1");
        $stCheck->execute([$citaIdEnSesion]);
        $citaCheck=$stCheck->fetch();
        if(!$citaCheck || in_array($citaCheck['estado'],['cancelada','completada'],true)){
            $citaIdEnSesion=0;
            waSetState($phone,[]);
            $state=[]; $step='';
        }
    }
    $cita=$citaIdEnSesion ? null : waGetCita($phone,$_waDb);
    if(!$cita && $citaIdEnSesion){
        $stCita=$_waDb->prepare(
            "SELECT ci.*, CONCAT(a.nombre,' ',a.apellido) AS agente_nombre
             FROM citas ci LEFT JOIN agentes a ON a.id=ci.agente_id
             WHERE ci.id=? LIMIT 1");
        $stCita->execute([$citaIdEnSesion]);
        $cita=$stCita->fetch() ?: null;
    }
    if(!$cita){waSend($phone,"📭 No tienes citas activas.",$WA_PHONE_ID,$WA_TOKEN,['🏠 Menú principal']);return;}
    waSetState($phone,['step'=>'reprog_fecha','cita_id'=>$cita['id'],'agente_id'=>$cita['agente_id']]);
    waSend($phone,"🗓 *Reprogramar cita*\n\nEscribe la nueva fecha:\n*DD/MM/AAAA*\n\nEj: ".date('d/m/Y',strtotime('+3 days')),$WA_PHONE_ID,$WA_TOKEN);
    return;
}

// Flujos multi-paso citas
if($step==='confirm_cancel'){
    file_put_contents(WA_LOG_PATH, date('Y-m-d H:i:s') . " CANCEL_FLOW text=[{$text}] norm=[{$textNorm}] iid=[{$interactiveId}]\n", FILE_APPEND);

    // Si el usuario presiona Salir dentro del flujo de confirmación, cancelar el flujo
    if($text==='/salir' || in_array($textNorm,['🚪 salir','salir','exit'],true)){
        waSetState($phone,[]);
        waSend($phone,"✅ Operación cancelada. Tu cita sigue en pie. 👍",$WA_PHONE_ID,$WA_TOKEN,['🏠 Menú principal','📋 Ver mi cita']);
        return;
    }

    $esConfirmar=$text==='CONF_CANCEL'
        || in_array($textNorm,['✅ sí, cancelar','sí, cancelar','si, cancelar','sí','si','confirmar','yes'],true)
        || $interactiveId==='b0';
    $esConservar=$text==='NO_CANCEL'
        || in_array($textNorm,['❌ no, conservar','no, conservar','no','cancelar acción'],true)
        || $interactiveId==='b1';
    if($esConfirmar){
        $citaIdToCancel = (int)$state['cita_id'];
        $ok=$citaModel->cancelarPorId($citaIdToCancel,$phone);

        file_put_contents(WA_LOG_PATH,
            date('Y-m-d H:i:s') . " CANCEL_RESULT cita_id=[{$citaIdToCancel}] phone=[{$phone}] ok=[" . ($ok?'1':'0') . "]\n",
            FILE_APPEND);

        waSetState($phone,[]);
        if($ok){
            waSend($phone,"✅ *Cita cancelada correctamente.*\n\n¿Deseas agendar una nueva cita?\n🌐 seguroslux.mx/formulario",$WA_PHONE_ID,$WA_TOKEN,['🏠 Menú principal']);
        } else {
            waSend($phone,"⚠️ *No pudimos cancelar la cita automáticamente.*\n\nPor favor contacta directamente a tu asesor para cancelarla.\n📞 O escribe a nuestro equipo.",$WA_PHONE_ID,$WA_TOKEN,['📞 Contactar asesor','🏠 Menú principal']);
        }
    }elseif($esConservar){
        waSetState($phone,[]);
        $c=waGetCita($phone,$_waDb);
        waSend($phone,"👍 Cita conservada.\n\n".($c?waResumen($c):''),$WA_PHONE_ID,$WA_TOKEN,
            ['📋 Ver mi cita','🔄 Reprogramar','🏠 Menú principal']);
    }else{
        waSend($phone,"⚠️ Por favor usa los botones 👇",$WA_PHONE_ID,$WA_TOKEN,['✅ Sí, cancelar','❌ No, conservar']);
    }
    return;
}
if($step==='reprog_fecha'){
    if(!preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/',$text,$m)){
        waSend($phone,"⚠️ Formato: *DD/MM/AAAA* · Ej: ".date('d/m/Y',strtotime('+3 days')),$WA_PHONE_ID,$WA_TOKEN);
        return;
    }
    $fN="{$m[3]}-{$m[2]}-{$m[1]}"; $ts=strtotime($fN);
    if(!$ts||$ts<strtotime('today')){waSend($phone,"⚠️ La fecha debe ser futura.",$WA_PHONE_ID,$WA_TOKEN);return;}
    if(date('N',$ts)==7){waSend($phone,"⚠️ No atendemos domingos.",$WA_PHONE_ID,$WA_TOKEN);return;}
    // Filtrar horas pasadas si la fecha es hoy
    $horas=$citaModel->getHorasDisponibles((int)$state['agente_id'],$fN);
    if($fN===date('Y-m-d')){
        $limiteMin=time()+30*60; // +30 min buffer
        $horas=array_values(array_filter($horas,function($h) use($limiteMin,$fN){
            return strtotime($fN.' '.$h.':00') > $limiteMin;
        }));
    }
    if(empty($horas)){waSend($phone,"😔 Sin horarios el ".date('d/m/Y',$ts).". Escribe otra fecha (DD/MM/AAAA):",$WA_PHONE_ID,$WA_TOKEN);return;}
    // Guardar horas en state para resolver list_reply por índice en reprog_hora
    waSetState($phone,array_merge($state,['step'=>'reprog_hora','fecha_nueva'=>$fN,'horas_disponibles'=>array_values($horas)]));
    waList($phone,"✅ Fecha: ".date('d/m/Y',$ts),"Elige una hora disponible:","Ver horarios",$horas,$WA_PHONE_ID,$WA_TOKEN);
    return;
}
if($step==='reprog_hora'){
    file_put_contents(WA_LOG_PATH, date('Y-m-d H:i:s') . " REPROG_HORA text=[{$text}] norm=[{$textNorm}] iid=[{$interactiveId}] horas=" . json_encode($state['horas_disponibles'] ?? []) . "\n", FILE_APPEND);
    // Resolver hora: puede venir como "09:00" (texto) o vía list_reply id "o3" -> mapear al índice guardado
    $horaFinal=$text;
    if(!preg_match('/^\d{2}:\d{2}$/',$horaFinal) && preg_match('/^o(\d+)$/',$interactiveId,$mi)){
        $horasGuardadas=$state['horas_disponibles']??[];
        $horaFinal=$horasGuardadas[(int)$mi[1]]??'';
    }
    if(!preg_match('/^\d{2}:\d{2}$/',$horaFinal)){
        waSend($phone,"⚠️ Selecciona una hora de la lista 👇",$WA_PHONE_ID,$WA_TOKEN);
        return;
    }
    // Verificar que la hora siga disponible (alguien más pudo tomarla)
    if(!$citaModel->horaDisponible((int)$state['agente_id'],$state['fecha_nueva'],$horaFinal)){
        waSend($phone,"⚠️ Esa hora ya fue tomada. Elige otra:",$WA_PHONE_ID,$WA_TOKEN);
        $horas=$citaModel->getHorasDisponibles((int)$state['agente_id'],$state['fecha_nueva']);
        if(empty($horas)){
            waSetState($phone,[]);
            waSend($phone,"😔 No quedan horarios ese día. Escribe otra fecha (DD/MM/AAAA):",$WA_PHONE_ID,$WA_TOKEN);
            waSetState($phone,array_merge($state,['step'=>'reprog_fecha']));
        } else {
            waSetState($phone,array_merge($state,['horas_disponibles'=>array_values($horas)]));
            waList($phone,"Fecha: ".waFecha($state['fecha_nueva']),"Elige una hora disponible:","Ver horarios",$horas,$WA_PHONE_ID,$WA_TOKEN);
        }
        return;
    }
    $ok=$citaModel->reprogramar((int)$state['cita_id'],$phone,$state['fecha_nueva'],$horaFinal);
    waSetState($phone,[]);
    waSend($phone,$ok?"✅ *¡Cita reprogramada!*\n\n📅 ".waFecha($state['fecha_nueva'])."\n🕐 {$horaFinal} hrs"
        :"❌ No se pudo reprogramar.",$WA_PHONE_ID,$WA_TOKEN,['📋 Ver mi cita','🏠 Menú principal']);
    return;
}
if(in_array($text,['/agendar'],true)||str_contains($textNorm,'agendar')||str_contains($textNorm,'agendar')){
    waSend($phone,"📅 Para agendar:\n🌐 *seguroslux.mx/formulario*\n\nO llama: ☎️ *800 123 4567*",$WA_PHONE_ID,$WA_TOKEN,
        ['🏠 Menú principal','📋 Ver mi cita']);
    return;
}

// ── CITA:token — vinculación desde link de WhatsApp ──────────────────────────
// El link generado por ClientController tiene la forma wa.me/...?text=CITA:abc123
// Cuando el cliente lo envía, vinculamos su número con la cita y mostramos el resumen.
if (str_starts_with($text, 'CITA:')) {
    $token = trim(substr($text, 5));
    $cita  = $citaModel->getByTelegramToken($token);
    if ($cita) {
        $_waDb->prepare("UPDATE clientes SET whatsapp_chat_id = ? WHERE id = ?")
              ->execute([$phone, $cita['cliente_id_real'] ?? $cita['cliente_id']]);
        waSend($phone,
            "✅ *¡Cita confirmada!*\n\n" . waResumen($cita) . "\n\n¿Qué deseas hacer?",
            $WA_PHONE_ID, $WA_TOKEN,
            ['🔄 Reprogramar', '❌ Cancelar cita', '🏠 Menú principal']
        );
    } else {
        waSend($phone,
            "⚠️ No encontré esa cita. ¿Registraste con otro número?\n\n"
            . "Escribe *mi cita* para buscar por tu número actual.",
            $WA_PHONE_ID, $WA_TOKEN,
            ['📋 Ver mis citas', '🏠 Menú principal']
        );
    }
    return;
}

// Fallback
waSetState($phone,[]);
waMenuPrincipal($phone,$from,$WA_PHONE_ID,$WA_TOKEN,$_waDb);

} catch (\Throwable $e) {
    error_log('WhatsAppBot: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    wa_bot_debug_log('EXC: ' . $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine());
}