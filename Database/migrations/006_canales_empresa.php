<?php
declare(strict_types=1);

/**
 * 006_canales_empresa — enruta los webhooks entrantes a su empresa.
 *
 * El problema que resuelve:
 *
 * Un webhook de WhatsApp, Telegram o Meta llega sin sesión y sin saber de
 * quién es. Lo único que trae es un identificador del canal por el que entró
 * (`phone_number_id`, el id del bot, el `form_id` de un anuncio). Mientras el
 * CRM era de una sola empresa eso daba igual: todo iba al mismo sitio. En
 * multiempresa, un mensaje que no se sabe de quién es no se puede procesar,
 * porque el cliente que genere acabaría en la empresa equivocada —o peor, en
 * ninguna, invisible para todos.
 *
 * Una tabla de canales en lugar de columnas en `empresas`:
 *   - una empresa puede tener varios números o varios formularios;
 *   - añadir un canal nuevo no obliga a tocar el esquema;
 *   - la búsqueda por identificador es un índice único, que es justo lo que
 *     hace el webhook en cada petición.
 *
 * Idempotente. `down()` solo elimina la tabla: no toca datos de negocio.
 */

use Database\SchemaHelper;

return [

    'up' => function (PDO $db, SchemaHelper $s): void {

        $esSqlite = $s->esSqlite();
        $ts       = $esSqlite ? "(datetime('now'))" : 'CURRENT_TIMESTAMP';
        $ai       = $esSqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
        $suffix   = $esSqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        if (!$s->tablaExiste('empresa_canales')) {
            $db->exec("
                CREATE TABLE empresa_canales (
                    id             {$ai},
                    empresa_id     " . $s->tipoEntero() . " NOT NULL,
                    canal          VARCHAR(20)  NOT NULL,
                    identificador  VARCHAR(120) NOT NULL,
                    etiqueta       VARCHAR(100) DEFAULT NULL,
                    activo         TINYINT      NOT NULL DEFAULT 1,
                    creado_en      DATETIME     NOT NULL DEFAULT {$ts}
                ){$suffix}
            ");

            // Único por (canal, identificador): un mismo número de WhatsApp no
            // puede pertenecer a dos empresas. Si llegara a ocurrir, el webhook
            // tendría que adivinar, y adivinar aquí es filtrar datos.
            $s->crearIndice('empresa_canales', 'uq_canal_identificador', ['canal', 'identificador'], true);
            $s->crearIndice('empresa_canales', 'idx_canal_empresa', ['empresa_id', 'canal']);

            $s->agregarFk(
                'empresa_canales',
                'fk_canales_empresa',
                'empresa_id',
                'empresas',
                'id',
                'CASCADE'
            );
        }

        // Los canales que ya estaban configurados por variables de entorno
        // pertenecen a la empresa que existía antes de la migración: sin esto,
        // al desplegar, los webhooks en marcha dejarían de resolver y los
        // mensajes entrantes se perderían en silencio.
        $empresaDefault = (int) $db->query('SELECT MIN(id) FROM empresas')->fetchColumn();

        if ($empresaDefault > 0) {
            $semillas = [
                ['whatsapp', (string) (getenv('WHATSAPP_PHONE_ID') ?: '')],
                ['telegram', (string) (getenv('TELEGRAM_BOT_ID')   ?: '')],
            ];

            foreach ($semillas as [$canal, $identificador]) {
                if ($identificador === '') {
                    continue;
                }

                $ya = $db->prepare(
                    'SELECT COUNT(*) FROM empresa_canales WHERE canal = ? AND identificador = ?'
                );
                $ya->execute([$canal, $identificador]);

                if ((int) $ya->fetchColumn() > 0) {
                    continue;
                }

                $db->prepare(
                    'INSERT INTO empresa_canales (empresa_id, canal, identificador, etiqueta, activo, creado_en)
                     VALUES (?, ?, ?, ?, 1, ' . ($esSqlite ? "datetime('now')" : 'NOW()') . ')'
                )->execute([$empresaDefault, $canal, $identificador, 'Migrado desde .env']);
            }
        }
    },

    'down' => function (PDO $db, SchemaHelper $s): void {
        $s->quitarFk('empresa_canales', 'fk_canales_empresa');
        $db->exec('DROP TABLE IF EXISTS empresa_canales');
    },
];
