-- ─────────────────────────────────────────────────────────────────────────────
-- Migración 001: Cola de emails asíncrona
-- Requerida por: App/Services/MailQueue.php
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS mail_queue (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    to_email     VARCHAR(255) NOT NULL,
    to_name      VARCHAR(255) NOT NULL DEFAULT '',
    subject      VARCHAR(500) NOT NULL,
    body         LONGTEXT     NOT NULL,
    reply_to     VARCHAR(255)          DEFAULT NULL,
    status       ENUM('pending','sending','sent','failed') NOT NULL DEFAULT 'pending',
    tries        TINYINT UNSIGNED NOT NULL DEFAULT 0,
    max_tries    TINYINT UNSIGNED NOT NULL DEFAULT 3,
    last_tried_at DATETIME             DEFAULT NULL,
    sent_at      DATETIME              DEFAULT NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_status_tries (status, tries),
    INDEX idx_created      (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
