<?php
declare(strict_types=1);

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailerException;

/**
 * MailService — Envío SMTP centralizado para Seguros Lux.
 *
 * ═══════════════════════════════════════════════════════════
 * ARQUITECTURA DE ENVÍO
 * ═══════════════════════════════════════════════════════════
 *
 *  Un único punto de envío: MailService::send()
 *
 *  Todos los correos se encolan en mail_queue (< 5 ms por
 *  request). El cron CronMail.php los despacha cada minuto
 *  con backoff exponencial en los reintentos.
 *
 *  Requisito: la tabla mail_queue debe existir en BD.
 *  Ver: Database/migrations/create_mail_queue.sql
 *  Si la tabla falta, send() lanzará una excepción — NO
 *  silencia el error, para que sea visible de inmediato.
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
     * Encola un email para envío asíncrono vía CronMail.
     *
     * El email se persiste en mail_queue en < 5 ms sin bloquear
     * el request HTTP. El cron lo enviará en la próxima ejecución
     * (máx. 1 minuto) con reintentos automáticos.
     *
     * @param  string      $to       Dirección destinatario
     * @param  string      $toName   Nombre destinatario
     * @param  string      $subject  Asunto
     * @param  string      $body     Cuerpo HTML
     * @param  string|null $replyTo  Reply-To opcional
     * @throws \RuntimeException  Si la tabla mail_queue no existe o hay error de BD
     */
    public static function send(
        string  $to,
        string  $toName,
        string  $subject,
        string  $body,
        ?string $replyTo = null,
    ): void {
        MailQueue::push($to, $toName, $subject, $body, $replyTo);
        error_log("[MailService] Encolado para {$to}: {$subject}");
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

        // Timeout generoso para el cron (no bloquea al usuario)
        $mail->Timeout = 20;

        return $mail;
    }
}