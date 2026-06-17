-- migrations/002_reports.sql
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `scheduled_reports` (
  `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `tenant_id`    INT UNSIGNED  NOT NULL,
  `type`         VARCHAR(50)   NOT NULL,
  `frequency`    ENUM('daily','weekly','monthly') NOT NULL DEFAULT 'weekly',
  `recipients`   TEXT          NOT NULL,
  `filters`      JSON          DEFAULT NULL,
  `last_sent_at` TIMESTAMP     NULL,
  `active`       TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant`    (`tenant_id`),
  KEY `idx_frequency` (`frequency`),
  CONSTRAINT `fk_sr_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ai_report_history` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`     INT UNSIGNED NOT NULL,
  `usuario_id`    INT UNSIGNED NOT NULL,
  `question`      TEXT         NOT NULL,
  `sql_generated` TEXT         DEFAULT NULL,
  `row_count`     INT UNSIGNED DEFAULT NULL,
  `status`        ENUM('success','error','blocked') NOT NULL DEFAULT 'success',
  `error_msg`     VARCHAR(500) DEFAULT NULL,
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant`  (`tenant_id`),
  KEY `idx_usuario` (`usuario_id`),
  CONSTRAINT `fk_air_tenant`  FOREIGN KEY (`tenant_id`)  REFERENCES `tenants`  (`id`),
  CONSTRAINT `fk_air_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT '002_reports.sql executado com sucesso.' AS status;
