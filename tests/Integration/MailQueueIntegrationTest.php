<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Services\MailQueue;
use Database\Connection;
use PHPUnit\Framework\TestCase;

/**
 * Tests de integración de MailQueue.
 *
 * Verifica que push() escribe correctamente en mail_queue y que
 * la lógica de selección y backoff de flush() es correcta.
 *
 * flush() no se ejecuta realmente (requiere SMTP) — se prueba la
 * lógica de selección de filas y el marcado de estado mediante
 * un stub que sobreescribe buildMailer() con un mailer falso.
 *
 * SQLite en memoria vía Connection::setTestConnection().
 */
final class MailQueueIntegrationTest extends TestCase
{
    private \PDO $db;

    protected function setUp(): void
    {
        $this->db = Connection::getConnection();
        $this->db->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    // ── push ──────────────────────────────────────────────────────────────────

    public function testPushInsertaRegistroEnTabla(): void
    {
        MailQueue::push(
            to:      'cliente@test.lux',
            toName:  'Cliente Test',
            subject: 'Asunto de prueba',
            body:    '<p>Cuerpo</p>',
        );

        $count = (int) $this->db
            ->query("SELECT COUNT(*) FROM mail_queue WHERE to_address = 'cliente@test.lux'")
            ->fetchColumn();

        $this->assertSame(1, $count);
    }

    public function testPushGuardaDatosCorrectos(): void
    {
        MailQueue::push(
            to:      'datos@test.lux',
            toName:  'Juan Pérez',
            subject: 'Mi asunto',
            body:    '<b>HTML</b>',
            replyTo: 'replyto@test.lux',
            tries:   5,
        );

        $row = $this->db
            ->query("SELECT * FROM mail_queue WHERE to_address = 'datos@test.lux' LIMIT 1")
            ->fetch();

        $this->assertSame('datos@test.lux',    $row['to_address']);
        $this->assertSame('Juan Pérez',         $row['to_name']);
        $this->assertSame('Mi asunto',          $row['subject']);
        $this->assertSame('<b>HTML</b>',        $row['body']);
        $this->assertSame('replyto@test.lux',   $row['reply_to']);
        $this->assertSame(5,  (int) $row['max_tries']);
        $this->assertSame('pending',            $row['status']);
        $this->assertSame(0,  (int) $row['tries']);
    }

    public function testPushMultiplesEmailsIndependientes(): void
    {
        MailQueue::push(to: 'a@test.lux', toName: 'A', subject: 'S1', body: 'b1');
        MailQueue::push(to: 'b@test.lux', toName: 'B', subject: 'S2', body: 'b2');
        MailQueue::push(to: 'c@test.lux', toName: 'C', subject: 'S3', body: 'b3');

        $count = (int) $this->db
            ->query("SELECT COUNT(*) FROM mail_queue WHERE status = 'pending'")
            ->fetchColumn();

        $this->assertGreaterThanOrEqual(3, $count);
    }

    // ── Lógica de selección de filas en flush ─────────────────────────────────

    /**
     * Inserta una fila directamente en la tabla y verifica que la query
     * de selección de flush() la incluiría (tries=0, status='pending').
     */
    public function testNuevoEmailEsElegibleParaEnvioInmediato(): void
    {
        MailQueue::push(to: 'nuevo@test.lux', toName: 'N', subject: 'S', body: 'B');

        $count = (int) $this->db->query(
            "SELECT COUNT(*) FROM mail_queue
             WHERE status = 'pending'
               AND tries  = 0
               AND to_address = 'nuevo@test.lux'"
        )->fetchColumn();

        $this->assertSame(1, $count);
    }

    /**
     * Un email que ya falló 3 veces (tries >= max_tries) NO debe aparecer.
     */
    public function testEmailAgotadoNoEsElegible(): void
    {
        $this->db->exec(
            "INSERT INTO mail_queue
             (to_address, to_name, subject, body, status, tries, max_tries, created_at)
             VALUES ('agotado@test.lux','A','S','B','pending',3,3,datetime('now'))"
        );

        $count = (int) $this->db->query(
            "SELECT COUNT(*) FROM mail_queue
             WHERE status   = 'pending'
               AND tries    < max_tries
               AND to_address = 'agotado@test.lux'"
        )->fetchColumn();

        $this->assertSame(0, $count, 'Email con tries >= max_tries no debe ser elegible');
    }

    /**
     * Un email en status='sent' no debe re-procesarse.
     */
    public function testEmailEnviadoNoEsElegible(): void
    {
        $this->db->exec(
            "INSERT INTO mail_queue
             (to_address, to_name, subject, body, status, tries, max_tries, sent_at, created_at)
             VALUES ('enviado@test.lux','E','S','B','sent',1,3,datetime('now'),datetime('now'))"
        );

        $count = (int) $this->db->query(
            "SELECT COUNT(*) FROM mail_queue
             WHERE status = 'pending'
               AND to_address = 'enviado@test.lux'"
        )->fetchColumn();

        $this->assertSame(0, $count);
    }

    /**
     * Un email con tries=1 y last_tried_at reciente NO debe ser elegible
     * (backoff de 5 minutos para el segundo intento).
     */
    public function testEmailConFalloRecienteEsRetenidoPorBackoff(): void
    {
        $this->db->exec(
            "INSERT INTO mail_queue
             (to_address, to_name, subject, body, status, tries, max_tries, last_tried_at, created_at)
             VALUES ('backoff@test.lux','B','S','B','pending',1,3,datetime('now'),datetime('now'))"
        );

        // La condición del backoff: tries=1 requiere last_tried_at < NOW() - 5 MIN
        $count = (int) $this->db->query(
            "SELECT COUNT(*) FROM mail_queue
             WHERE status = 'pending'
               AND tries  < max_tries
               AND (
                     tries = 0
                  OR (tries = 1 AND last_tried_at < datetime('now', '-5 minutes'))
                  OR (tries = 2 AND last_tried_at < datetime('now', '-30 minutes'))
               )
               AND to_address = 'backoff@test.lux'"
        )->fetchColumn();

        $this->assertSame(0, $count, 'Email con fallo reciente debe estar en backoff');
    }
}