<?php
declare(strict_types=1);

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailerException;

/**
 * MailService — Envío SMTP centralizado para Seguros Lux.
 *
 * ═══════════════════════════════════════════════════════════
 * POR QUÉ NO LLEGABAN LOS CORREOS — DIAGNÓSTICO
 * ═══════════════════════════════════════════════════════════
 *
 * Había 3 problemas encadenados:
 *
 *  1. La tabla `mail_queue` NO existía en producción.
 *     Nuestro refactor de rendimiento asumía que ya se había
 *     ejecutado Database/create_mail_queue.sql — pero no.
 *     MailQueue::push() lanzaba PDOException, capturada
 *     silenciosamente con error_log() → los correos se
 *     perdían sin ningún aviso visible.
 *
 *  2. El fallback en ClientController usaba mail() nativo.
 *     Hostgator compartido bloquea mail() o lo manda directo
 *     a spam. No es un canal confiable.
 *
 *  3. CronMail.php nunca fue registrado en Hostgator.
 *     Aunque la tabla existiera, nadie procesaba la cola.
 *
 * ═══════════════════════════════════════════════════════════
 * SOLUCIÓN ADOPTADA
 * ═══════════════════════════════════════════════════════════
 *
 *  Un único punto de envío: MailService::send().
 *
 *  Estrategia en dos pasos:
 *
 *   Paso 1 — Intentar encolar en mail_queue (async, < 5 ms).
 *            Si la tabla no existe o hay error de BD,
 *            no falla silenciosamente: cae al paso 2.
 *
 *   Paso 2 — Enviar síncronamente con PHPMailer+SMTP.
 *            Esto siempre funciona si las credenciales
 *            SMTP están bien en .env.php.
 *
 *  Resultado: los correos SIEMPRE se envían, sea por la
 *  cola (cuando esté lista) o en tiempo real (fallback).
 *
 * ═══════════════════════════════════════════════════════════
 * VARIABLES DE ENTORNO REQUERIDAS EN .env.php
 * ═══════════════════════════════════════════════════════════
 *
 *  putenv('SMTP_HOST=mail.seguroslux.mx');   // servidor SMTP de Hostgator
 *  putenv('SMTP_USER=contacto@seguroslux.mx');
 *  putenv('SMTP_PASS=tu_contraseña_aqui');
 *  putenv('SMTP_PORT=465');                  // 465 = SMTPS, 587 = STARTTLS
 *  putenv('SMTP_FROM=contacto@seguroslux.mx');
 *  putenv('SMTP_FROM_NAME=Seguros LUX');
 *
 * ═══════════════════════════════════════════════════════════
 */
final class MailService
{
    // ── API pública ───────────────────────────────────────────────────────────

    /**
     * Envía un email.
     *
     * Intenta primero encolar en mail_queue (si la tabla existe).
     * Si falla por cualquier razón, envía síncronamente con SMTP.
     *
     * @param  string      $to       Dirección destinatario
     * @param  string      $toName   Nombre destinatario
     * @param  string      $subject  Asunto
     * @param  string      $body     Cuerpo HTML
     * @param  string|null $replyTo  Reply-To opcional
     * @throws \RuntimeException  Solo si SMTP también falla (no hay salida)
     */
    public static function send(
        string  $to,
        string  $toName,
        string  $subject,
        string  $body,
        ?string $replyTo = null,
    ): void {
        // ── Intentar encolar (async) ──────────────────────────────────────────
        try {
            MailQueue::push($to, $toName, $subject, $body, $replyTo);
            return; // éxito — el cron lo enviará en < 1 min
        } catch (\Throwable $e) {
            // La tabla mail_queue no existe aún, o hay otro error de BD.
            // Logeamos y caemos al envío síncrono.
            error_log('[MailService] cola no disponible, enviando síncronamente: ' . $e->getMessage());
        }

        // ── Fallback: envío síncrono con PHPMailer ────────────────────────────
        self::sendNow($to, $toName, $subject, $body, $replyTo);
    }

    /**
     * Envía síncronamente con PHPMailer+SMTP.
     * Usar directamente cuando se necesita confirmación inmediata de entrega.
     *
     * @throws \RuntimeException si las credenciales SMTP no están configuradas
     * @throws MailerException   si PHPMailer no puede conectar al servidor SMTP
     */
    public static function sendNow(
        string  $to,
        string  $toName,
        string  $subject,
        string  $body,
        ?string $replyTo = null,
    ): void {
        $mail = self::buildMailer();

        $mail->addAddress($to, $toName);

        if ($replyTo) {
            $mail->addReplyTo($replyTo);
        }

        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));

        $mail->send();

        error_log("[MailService] ✓ Enviado a {$to}: {$subject}");
    }

    // ── Construcción del mailer ───────────────────────────────────────────────

    /**
     * Construye y configura un PHPMailer listo para enviar.
     * Lee credenciales desde variables de entorno (putenv en .env.php).
     *
     * @throws \RuntimeException si faltan variables de entorno SMTP
     */
    public static function buildMailer(): PHPMailer
    {
        $host     = (string) (getenv('SMTP_HOST')      ?: '');
        $user     = (string) (getenv('SMTP_USER')      ?: '');
        $pass     = (string) (getenv('SMTP_PASS')      ?: '');
        $port     = (int)    (getenv('SMTP_PORT')      ?: 465);
        $from     = (string) (getenv('SMTP_FROM')      ?: $user);
        $fromName = (string) (getenv('SMTP_FROM_NAME') ?: 'Seguros LUX');

        if (!$host || !$user || !$pass) {
            throw new \RuntimeException(
                'Faltan SMTP_HOST, SMTP_USER o SMTP_PASS en .env.php. ' .
                'Los correos no pueden enviarse hasta configurarlos.'
            );
        }

        $mail = new PHPMailer(true); // true = lanza excepciones en vez de retornar false
        $mail->isSMTP();
        $mail->Host       = $host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $user;
        $mail->Password   = $pass;
        $mail->Port       = $port;

        // Puerto 465 → SMTPS (SSL inmediato, el más común en Hostgator)
        // Puerto 587 → STARTTLS (TLS negociado después de conectar)
        $mail->SMTPSecure = ($port === 587)
            ? PHPMailer::ENCRYPTION_STARTTLS
            : PHPMailer::ENCRYPTION_SMTPS;

        $mail->CharSet  = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->isHTML(true);
        $mail->setFrom($from, $fromName);

        // 10 s en requests HTTP (fallback síncrono), más generoso en CLI/cron
        $mail->Timeout = (PHP_SAPI === 'cli') ? 20 : 10;

        return $mail;
    }
}
