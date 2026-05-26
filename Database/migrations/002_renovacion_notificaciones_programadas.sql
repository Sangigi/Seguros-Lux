-- ─────────────────────────────────────────────────────────────────────────────
-- Migración 002: Registro de notificaciones de renovación ya enviadas
-- Requerida por: App/Cron/CronRenovaciones.php (y los crons anteriores)
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS renovacion_notificaciones_programadas (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    poliza_id        INT UNSIGNED NOT NULL,
    agente_id        INT UNSIGNED NOT NULL,
    dias_antes       TINYINT UNSIGNED NOT NULL,
    enviada          TINYINT(1) NOT NULL DEFAULT 0,
    enviada_en       DATETIME          DEFAULT NULL,
    fecha_programada DATE       NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_poliza_dias (poliza_id, dias_antes),
    INDEX idx_agente   (agente_id),
    INDEX idx_enviada  (enviada)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
