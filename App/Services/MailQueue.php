<?php
declare(strict_types=1);

namespace App\Services;

use Database\Connection;
use PDO;

/**
 * MailQueue — Cola de emails persistida en BD para envío asíncrono.
 *
 * PROBLEMA QUE RESUELVE:
 *   PHPMailer/SMTP es síncrono: el proceso PHP espera la respuesta del servidor
 *   de correo (TLS handshake + autenticación + DATA + respuesta final).
 *   En Hostgator con carga alta, este proceso puede tardar 2-8 segundos y
 *   bloquea la respuesta HTTP al usuario/bot.
 *
 * SOLUCIÓN:
 *   1. En el request HTTP:  MailQueue::push() — guarda el email en BD (< 5 ms).
 *   2. En un cron (cada minuto): MailQueue::flush() — envía los pendientes.
 *
 * TABLA REQUERIDA: mail_queue
 *   Ver: Database/migrations/create_mail_queue.sql
 *
 * FALLBACK SÍNCRONO:
 *   Si la tabla no existe o la BD falla, push() lanza la excepción para que
 *   el caller decida si enviar síncronamente o simplemente logear.
 *
 * USO:
 *   // En el controlador (request HTTP):
 *   MailQueue::push(
 *       to:      'cliente@example.com',
 *       toName:  'Juan Pérez',
 *       subject: '¡Tu solicitud fue recibida!',
 *       body:    $htmlBody,
 *   );
 *
 *   // En el cron (cron_mail.php, cada minuto):
 *   MailQueue::flush();
 */
final class MailQueue
{
    /**
     * Encola un email para envío asíncrono.
     *
     * @param string      $to      Dirección de destino.
     * @param string      $toName  Nombre del destinatario (puede ser vacío).
     * @param string      $subject Asunto del correo.
     * @param string      $body    Cuerpo HTML.
     * @param string|null $replyTo Dirección de Reply-To (opcional).
     * @param int         $tries   Máximo de intentos de envío (defecto: 3).
     */
    public static function push(
        string  $to,
        string  $toName,
        string  $subject,
        string  $body,
        ?string $replyTo = null,
        int     $tries   = 3,
    ): void {
        $db = Connection::getConnection();
        $db->prepare(
            'INSERT INTO mail_queue
             (to_address, to_name, subject, body, reply_to, max_tries, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        )->execute([$to, $toName, $subject, $body, $replyTo, $tries]);
    }

    /**
     * Procesa los emails pendientes de la cola.
     *
     * Diseñado para ejecutarse desde un cron job (ver App/Cron/CronMail.php).
     * Procesa en lotes de $batchSize para no saturar SMTP en un solo disparo.
     *
     * Algoritmo de reintento exponencial:
     *   Intento 1 → inmediatamente.
     *   Intento 2 → después de 5 min.
     *   Intento 3 → después de 30 min.
     *
     * @param int $batchSize Emails a procesar por ejecución de cron.
     */
    public static function flush(int $batchSize = 20): void
    {
        $db = Connection::getConnection();

        // Obtener emails pendientes respetando el backoff de reintentos
        $rows = $db->prepare(
            "SELECT id, to_address, to_name, subject, body, reply_to, tries
             FROM mail_queue
             WHERE status = 'pending'
               AND tries < max_tries
               AND (
                     last_tried_at IS NULL
                  OR (tries = 1 AND last_tried_at < NOW() - INTERVAL 5  MINUTE)
                  OR (tries = 2 AND last_tried_at < NOW() - INTERVAL 30 MINUTE)
               )
             ORDER BY created_at ASC
             LIMIT ?"
        );
        $rows->bindValue(1, $batchSize, PDO::PARAM_INT);
        $rows->execute();

        $mailer = self::buildMailer();

        foreach ($rows->fetchAll() as $row) {
            // Marcar como "en proceso" para evitar doble envío en crons concurrentes
            $db->prepare(
                "UPDATE mail_queue
                 SET status = 'sending', last_tried_at = NOW(), tries = tries + 1
                 WHERE id = ? AND status = 'pending'"
            )->execute([$row['id']]);

            try {
                $mailer->clearAddresses();
                $mailer->clearReplyTos();

                $mailer->addAddress($row['to_address'], $row['to_name']);

                if ($row['reply_to']) {
                    $mailer->addReplyTo($row['reply_to']);
                }

                $mailer->Subject = $row['subject'];
                $mailer->Body    = $row['body'];
                $mailer->send();

                // Marcar como enviado
                $db->prepare(
                    "UPDATE mail_queue SET status = 'sent', sent_at = NOW() WHERE id = ?"
                )->execute([$row['id']]);

            } catch (\Throwable $e) {
                // Registrar el error y devolver a 'pending' para reintento
                $db->prepare(
                    "UPDATE mail_queue
                     SET status = 'pending', last_error = ?, last_tried_at = NOW()
                     WHERE id = ?"
                )->execute([
                    mb_substr($e->getMessage(), 0, 500),
                    $row['id'],
                ]);

                error_log("[MailQueue] Error enviando mail #{$row['id']}: " . $e->getMessage());
            }
        }
    }

    /**
     * Construye el mailer usando las variables de entorno del CRM.
     * Reutiliza la misma lógica que el resto del sistema.
     */
    private static function buildMailer(): \PHPMailer\PHPMailer\PHPMailer
    {
        $host = (string) (getenv('SMTP_HOST') ?: '');
        $user = (string) (getenv('SMTP_USER') ?: '');
        $pass = (string) (getenv('SMTP_PASS') ?: '');
        $port = (int)    (getenv('SMTP_PORT') ?: 465);

        if ($host === '' || $user === '' || $pass === '') {
            throw new \RuntimeException(
                'Faltan SMTP_HOST, SMTP_USER o SMTP_PASS en variables de entorno.'
            );
        }

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $user;
        $mail->Password   = $pass;
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = $port;
        $mail->CharSet    = 'UTF-8';
        $mail->isHTML(true);
        $mail->setFrom($user, 'Seguros LUX');

        // Timeout más generoso en el cron (no bloquea al usuario)
        $mail->Timeout = 15;

        return $mail;
    }
}
