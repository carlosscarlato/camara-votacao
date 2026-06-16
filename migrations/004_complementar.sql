-- migrations/004_complementar.sql
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `notification_logs` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `tenant_id`  INT UNSIGNED  NOT NULL,
  `type`       ENUM('email','whatsapp','sms') NOT NULL,
  `recipient`  VARCHAR(200)  NOT NULL,
  `subject`    VARCHAR(255)  DEFAULT NULL,
  `template`   VARCHAR(100)  DEFAULT NULL,
  `status`     ENUM('sent','failed','pending') NOT NULL DEFAULT 'pending',
  `error_msg`  VARCHAR(500)  DEFAULT NULL,
  `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  CONSTRAINT `fk_nl_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notification_templates` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `tenant_id`  INT UNSIGNED  NOT NULL,
  `slug`       VARCHAR(80)   NOT NULL,
  `channel`    ENUM('email','whatsapp') NOT NULL DEFAULT 'email',
  `subject`    VARCHAR(255)  DEFAULT NULL,
  `body`       TEXT          NOT NULL,
  `active`     TINYINT(1)    NOT NULL DEFAULT 1,
  `updated_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_slug_channel` (`tenant_id`, `slug`, `channel`),
  CONSTRAINT `fk_nt_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `audit_log` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`  INT UNSIGNED    NOT NULL,
  `user_id`    INT UNSIGNED    DEFAULT NULL,
  `entity`     VARCHAR(80)     NOT NULL,
  `entity_id`  INT UNSIGNED    DEFAULT NULL,
  `action`     VARCHAR(80)     NOT NULL,
  `before_val` JSON            DEFAULT NULL,
  `after_val`  JSON            DEFAULT NULL,
  `ip`         VARCHAR(45)     NOT NULL DEFAULT '',
  `user_agent` VARCHAR(500)    DEFAULT NULL,
  `created_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant`  (`tenant_id`),
  KEY `idx_entity`  (`entity`, `entity_id`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `fk_al_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- vote_hash SHA-256
DROP PROCEDURE IF EXISTS sp_vote_hash;
DELIMITER $$
CREATE PROCEDURE sp_vote_hash()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'votos' AND COLUMN_NAME = 'vote_hash'
  ) THEN
    ALTER TABLE `votos`
      ADD COLUMN `vote_hash` CHAR(64) DEFAULT NULL AFTER `voto`,
      ADD UNIQUE KEY `uk_vote_hash` (`vote_hash`);
  END IF;
END$$
DELIMITER ;
CALL sp_vote_hash();
DROP PROCEDURE IF EXISTS sp_vote_hash;

CREATE TABLE IF NOT EXISTS `plans` (
  `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `name`            VARCHAR(80)   NOT NULL,
  `slug`            VARCHAR(40)   NOT NULL,
  `max_sessions`    INT           NOT NULL DEFAULT 10,
  `max_vereadores`  INT           NOT NULL DEFAULT 9,
  `max_proposicoes` INT           NOT NULL DEFAULT 100,
  `has_ai`          TINYINT(1)    NOT NULL DEFAULT 0,
  `has_api`         TINYINT(1)    NOT NULL DEFAULT 0,
  `show_powered_by` TINYINT(1)    NOT NULL DEFAULT 1,
  `price_monthly`   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `active`          TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `plans` (`name`,`slug`,`max_sessions`,`max_vereadores`,`has_ai`,`has_api`,`show_powered_by`,`price_monthly`) VALUES
('Free',       'free',       5,   9,  0, 0, 1, 0.00),
('Starter',    'starter',    30,  21, 0, 0, 1, 99.00),
('Pro',        'pro',        200, 55, 1, 1, 0, 299.00),
('Enterprise', 'enterprise', -1,  -1, 1, 1, 0, 899.00);

CREATE TABLE IF NOT EXISTS `billing` (
  `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `tenant_id`        INT UNSIGNED  NOT NULL,
  `plan_id`          INT UNSIGNED  NOT NULL,
  `amount`           DECIMAL(10,2) NOT NULL,
  `status`           ENUM('pending','paid','overdue','cancelled') NOT NULL DEFAULT 'pending',
  `due_date`         DATE          NOT NULL,
  `paid_at`          TIMESTAMP     NULL,
  `invoice_pdf_path` VARCHAR(500)  DEFAULT NULL,
  `created_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_b_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`),
  CONSTRAINT `fk_b_plan`   FOREIGN KEY (`plan_id`)   REFERENCES `plans`   (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `api_tokens` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`  INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NOT NULL,
  `token`      CHAR(64)     NOT NULL,
  `name`       VARCHAR(100) DEFAULT NULL,
  `last_used`  TIMESTAMP    NULL,
  `revoked`    TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_token` (`token`),
  KEY `idx_tenant` (`tenant_id`),
  CONSTRAINT `fk_at_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_at_user`   FOREIGN KEY (`user_id`)   REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT '004_complementar.sql executado com sucesso.' AS status;
