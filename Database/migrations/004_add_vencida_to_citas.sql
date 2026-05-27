-- Up
ALTER TABLE `citas` 
MODIFY COLUMN `estado` ENUM('programada','completada','cancelada','vencida') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'programada';

-- Down
ALTER TABLE `citas` 
MODIFY COLUMN `estado` ENUM('programada','completada','cancelada') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'programada';