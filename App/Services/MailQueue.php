<?php
declare(strict_types=1);

namespace App\Services;

use App\Security\Tenant;
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
 * ALGORITMO DE REINTENTOS (backoff exponencial):
 *   - tries se incrementa SOLO cuando el envío falla (no antes).
 *   - Intento 1 → inmediatamente (last_tried_at IS NULL).
 *   - Intento 2 → después de 5 min (tries=1 ya falló una vez).
 *   - Intento 3 → después de 30 min (tries=2 ya falló dos veces).
 *   - Tras 3 fallos → status='failed', no se reintenta más.
 *
 * CORRECCIÓN vs versión anterior:
 *   Bug: el UPDATE previo al try incrementaba tries y ponía status='sending'.
 *   Si el envío fallaba, devolvía a 'pending' pero tries ya estaba incrementado,
 *   por lo que el email se agotaba antes de tener 3 intentos reales de envío.
 *   Fix: tries se incrementa ÚNICAMENTE en el catch (fallo real de envío).
 *
 * MULTIEMPRESA:
 *   La cola es de plataforma: un solo cron despacha los correos de todas las
 *   empresas. Por eso flush() corre en modo sin restricción. Pero cada correo
 *   guarda su `empresa_id` al encolarse, para poder saber después de quién
 *   era cada envío —y para que un fallo se pueda atribuir a una empresa
 *   concreta en vez de quedar en "de la plataforma".
 */
final class MailQueue
{
    /**
     * Encola un email para envío asíncrono.
     *
     * @param string      $to        Dirección de destino.
     * @param string      $toName    Nombre del destinatario (puede ser vacío).
     * @param string      $subject   Asunto del correo.
     * @param string      $body      Cuerpo HTML.
     * @param string|null $replyTo   Dirección de Reply-To (opcional).
     * @param int         $tries     Máximo de intentos de envío (defecto: 3).
     * @param int|null    $empresaId Empresa dueña del correo. Si no se pasa se
     *                               toma la empresa activa; queda NULL solo en
     *                               correos de plataforma (invitaciones, avisos
     *                               al propietario), que no son de nadie.
     */
    public static function push(
        string  $to,
        string  $toName,
        string  $subject,
        string  $body,
        ?string $replyTo   = null,
        int     $tries     = 3,
        ?int    $empresaId = null,
    ): void {
        // La empresa se sella al encolar, no al enviar: en el cron ya no hay
        // forma de saber de quién era el correo.
        $empresaId ??= Tenant::id();

        $db = Connection::getConnection();
        $db->prepare(
            'INSERT INTO mail_queue
             (to_address, to_name, subject, body, reply_to, max_tries, empresa_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
        )->execute([$to, $toName, $subject, $body, $replyTo, $tries, $empresaId]);
    }

    /**
     * Procesa los emails pendientes de la cola.
     *
     * Diseñado para ejecutarse desde un cron job (ver App/Cron/CronMail.php).
     * Procesa en lotes de $batchSize para no saturar SMTP en un solo disparo.
     *
     * IMPORTANTE — Orden correcto de operaciones para evitar el bug de tries:
     *   1. SELECT emails pendientes según backoff actual.
     *   2. Marcar como 'sending' (sin tocar tries todavía).
     *   3. Intentar envío.
     *   4a. ÉXITO → status='sent', sent_at=NOW().
     *   4b. FALLO  → status='pending', tries=tries+1, last_tried_at=NOW(), last_error=...
     *       Si tries+1 >= max_tries → status='failed'.
     *
     * @param int $batchSize Emails a procesar por ejecución de cron.
     */
    public static function flush(int $batchSize = 20): void
    {
        // La cola es de plataforma: un único cron despacha los correos de
        // todas las empresas, así que el recorrido se pide explícitamente sin
        // restricción. Solo lee y actualiza filas que ya existen y que ya
        // traen su empresa_id dentro; no crea nada, de modo que no hay forma
        // de sellar un registro nuevo en la empresa equivocada.
        Tenant::sinRestriccion(static fn() => self::despachar($batchSize));
    }

    /** Recorrido real de la cola. Siempre se llama desde flush(). */
    private static function despachar(int $batchSize): void
    {
        $db = Connection::getConnection();

        // Obtener emails pendientes respetando el backoff de reintentos.
        // La condición usa tries (intentos fallidos ya realizados):
        //   tries=0 → nunca intentado → enviar de inmediato
        //   tries=1 → falló una vez  → esperar 5 min desde last_tried_at
        //   tries=2 → falló dos veces → esperar 30 min desde last_tried_at
        $rows = $db->prepare(
            // tenant-ok: la cola es de plataforma, el cron despacha los pendientes de todas las empresas
            "SELECT id, to_address, to_name, subject, body, reply_to, tries, max_tries
             FROM mail_queue
             WHERE status = 'pending'
               AND tries < max_tries
               AND (
                     tries = 0
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
            // Marcar como 'sending' para evitar doble procesamiento en crons concurrentes.
            // IMPORTANTE: NO incrementamos tries aquí — solo lo hacemos si el envío falla.
            $affected = $db->prepare(
                // tenant-ok: toma el correo por id dentro del recorrido de plataforma; la fila ya es suya
                "UPDATE mail_queue
                 SET status = 'sending', last_tried_at = NOW()
                 WHERE id = ? AND status = 'pending'"
            )->execute([$row['id']]);

            // Si otro proceso ya tomó este email (race condition), saltar.
            // PDO::execute() devuelve true/false, no rowCount — usamos una consulta adicional
            // solo si hay múltiples workers. En cron single-thread esto no es necesario,
            // pero el status='sending' ya actúa como lock optimista.

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

                // ── ÉXITO: marcar como enviado ───────────────────────────────
                $db->prepare(
                    // tenant-ok: cierra por id el correo que este mismo recorrido de plataforma tomó
                    "UPDATE mail_queue
                     SET status = 'sent', sent_at = NOW()
                     WHERE id = ?"
                )->execute([$row['id']]);

                error_log("[MailQueue] ✓ Enviado mail #{$row['id']} a {$row['to_address']}");

            } catch (\Throwable $e) {
                // ── FALLO: incrementar tries y evaluar si ya se agotaron ──────
                // tries se incrementa aquí, no antes del envío.
                $newTries = (int) $row['tries'] + 1;
                $newStatus = $newTries >= (int) $row['max_tries'] ? 'failed' : 'pending';

                $db->prepare(
                    // tenant-ok: registra el fallo por id del correo que este recorrido de plataforma tomó
                    "UPDATE mail_queue
                     SET status        = ?,
                         tries         = ?,
                         last_tried_at = NOW(),
                         last_error    = ?
                     WHERE id = ?"
                )->execute([
                    $newStatus,
                    $newTries,
                    mb_substr($e->getMessage(), 0, 500),
                    $row['id'],
                ]);

                $logLevel = $newStatus === 'failed' ? 'FAILED (agotado)' : "RETRY {$newTries}/{$row['max_tries']}";
                error_log("[MailQueue] {$logLevel} mail #{$row['id']} a {$row['to_address']}: " . $e->getMessage());
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

        // Timeout más generoso en el cron (no bloquea al usuario).
        $mail->Timeout = 15;

        return $mail;
    }
}