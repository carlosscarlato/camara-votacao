-- migrations/001_multi_tenant.sql
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ── tenants ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `tenants` (
  `id`         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(150)    NOT NULL,
  `slug`       VARCHAR(80)     NOT NULL,
  `plan`       ENUM('free','starter','pro','enterprise') NOT NULL DEFAULT 'free',
  `status`     ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `tenants` (`id`, `name`, `slug`, `plan`, `status`)
VALUES (1, 'Câmara Municipal', 'camara-municipal', 'pro', 'active');

-- ── tenant_settings ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `tenant_settings` (
  `id`                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`              INT UNSIGNED NOT NULL,
  `logo_path`              VARCHAR(500) DEFAULT NULL,
  `favicon_path`           VARCHAR(500) DEFAULT NULL,
  `login_background_image` VARCHAR(500) DEFAULT NULL,
  `primary_color`          VARCHAR(7)   NOT NULL DEFAULT '#1e3a5f',
  `secondary_color`        VARCHAR(7)   NOT NULL DEFAULT '#3b82f6',
  `accent_color`           VARCHAR(7)   NOT NULL DEFAULT '#10b981',
  `font_family`            VARCHAR(100) NOT NULL DEFAULT 'Segoe UI, Arial, sans-serif',
  `company_name`           VARCHAR(150) DEFAULT NULL,
  `slogan`                 VARCHAR(255) DEFAULT NULL,
  `cnpj`                   VARCHAR(20)  DEFAULT NULL,
  `address`                VARCHAR(255) DEFAULT NULL,
  `number`                 VARCHAR(10)  DEFAULT NULL,
  `complement`             VARCHAR(100) DEFAULT NULL,
  `neighborhood`           VARCHAR(100) DEFAULT NULL,
  `city`                   VARCHAR(100) DEFAULT NULL,
  `state`                  CHAR(2)      DEFAULT NULL,
  `zip`                    VARCHAR(10)  DEFAULT NULL,
  `country`                VARCHAR(60)  NOT NULL DEFAULT 'Brasil',
  `phone`                  VARCHAR(20)  DEFAULT NULL,
  `whatsapp`               VARCHAR(20)  DEFAULT NULL,
  `email_contact`          VARCHAR(150) DEFAULT NULL,
  `website`                VARCHAR(255) DEFAULT NULL,
  `social_linkedin`        VARCHAR(255) DEFAULT NULL,
  `social_instagram`       VARCHAR(255) DEFAULT NULL,
  `social_youtube`         VARCHAR(255) DEFAULT NULL,
  `social_facebook`        VARCHAR(255) DEFAULT NULL,
  `custom_css`             TEXT         DEFAULT NULL,
  `email_footer_text`      VARCHAR(500) DEFAULT NULL,
  `terms_of_use_url`       VARCHAR(500) DEFAULT NULL,
  `privacy_policy_url`     VARCHAR(500) DEFAULT NULL,
  `show_powered_by`        TINYINT(1)   NOT NULL DEFAULT 1,
  `session_timeout_minutes` SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  `twofa_required`         TINYINT(1)   NOT NULL DEFAULT 0,
  `smtp_host`              VARCHAR(150) DEFAULT NULL,
  `smtp_port`              SMALLINT UNSIGNED DEFAULT 587,
  `smtp_user`              VARCHAR(150) DEFAULT NULL,
  `smtp_pass`              VARCHAR(255) DEFAULT NULL,
  `smtp_encryption`        ENUM('tls','ssl','none') DEFAULT 'tls',
  `whatsapp_api_url`       VARCHAR(500) DEFAULT NULL,
  `whatsapp_api_token`     VARCHAR(500) DEFAULT NULL,
  `webhook_url`            VARCHAR(500) DEFAULT NULL,
  `webhook_secret`         VARCHAR(100) DEFAULT NULL,
  `created_at`             TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`             TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant` (`tenant_id`),
  CONSTRAINT `fk_ts_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `tenant_settings` (`tenant_id`) VALUES (1);

-- ── tenant_domains ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `tenant_domains` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`  INT UNSIGNED NOT NULL,
  `domain`     VARCHAR(255) NOT NULL,
  `is_primary` TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_domain` (`domain`),
  CONSTRAINT `fk_td_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `tenant_domains` (`tenant_id`, `domain`, `is_primary`)
VALUES (1, 'webvoto.sazio.com.br', 1), (1, 'localhost', 0);

-- ── tenant_users ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `tenant_users` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`  INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NOT NULL,
  `role`       ENUM('super_admin','admin','operator','voter') NOT NULL DEFAULT 'operator',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_user` (`tenant_id`, `user_id`),
  CONSTRAINT `fk_tu_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tu_user`   FOREIGN KEY (`user_id`)   REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `tenant_users` (`tenant_id`, `user_id`, `role`)
SELECT 1, id, IF(perfil='admin','admin','operator') FROM `usuarios`;

-- ── super_admin_settings ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS `super_admin_settings` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `logo_path`          VARCHAR(500) DEFAULT NULL,
  `logo_dark_path`     VARCHAR(500) DEFAULT NULL,
  `fantasy_name`       VARCHAR(150) DEFAULT NULL,
  `company_name`       VARCHAR(150) DEFAULT NULL,
  `cnpj`               VARCHAR(20)  DEFAULT NULL,
  `address`            VARCHAR(255) DEFAULT NULL,
  `number`             VARCHAR(10)  DEFAULT NULL,
  `complement`         VARCHAR(100) DEFAULT NULL,
  `neighborhood`       VARCHAR(100) DEFAULT NULL,
  `city`               VARCHAR(100) DEFAULT NULL,
  `state`              CHAR(2)      DEFAULT NULL,
  `zip`                VARCHAR(10)  DEFAULT NULL,
  `country`            VARCHAR(60)  NOT NULL DEFAULT 'Brasil',
  `phone`              VARCHAR(20)  DEFAULT NULL,
  `whatsapp`           VARCHAR(20)  DEFAULT NULL,
  `email_support`      VARCHAR(150) DEFAULT NULL,
  `website`            VARCHAR(255) DEFAULT NULL,
  `social_linkedin`    VARCHAR(255) DEFAULT NULL,
  `social_instagram`   VARCHAR(255) DEFAULT NULL,
  `social_youtube`     VARCHAR(255) DEFAULT NULL,
  `social_facebook`    VARCHAR(255) DEFAULT NULL,
  `copyright_text`     VARCHAR(255) NOT NULL DEFAULT 'Powered by WebVoto',
  `privacy_policy_url` VARCHAR(500) DEFAULT NULL,
  `terms_url`          VARCHAR(500) DEFAULT NULL,
  `smtp_from_name`     VARCHAR(100) DEFAULT NULL,
  `smtp_from_email`    VARCHAR(150) DEFAULT NULL,
  `updated_at`         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `super_admin_settings` (`id`, `fantasy_name`, `copyright_text`)
VALUES (1, 'WebVoto', 'Powered by WebVoto © 2026');

-- ── Adicionar tenant_id nas tabelas existentes ────────────────
DROP PROCEDURE IF EXISTS sp_tenant_col;
DELIMITER $$
CREATE PROCEDURE sp_tenant_col(IN p_table VARCHAR(64))
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND COLUMN_NAME = 'tenant_id'
  ) THEN
    SET @sql = CONCAT(
      'ALTER TABLE `', p_table, '`',
      ' ADD COLUMN `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `id`,',
      ' ADD KEY `idx_tenant_id` (`tenant_id`),',
      ' ADD CONSTRAINT `fk_', p_table, '_tenant`',
      ' FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)'
    );
    PREPARE s FROM @sql;
    EXECUTE s;
    DEALLOCATE PREPARE s;
  END IF;
END$$
DELIMITER ;

CALL sp_tenant_col('vereadores');
CALL sp_tenant_col('usuarios');
CALL sp_tenant_col('sessoes_plenarias');
CALL sp_tenant_col('proposicoes');
CALL sp_tenant_col('ordem_do_dia');
CALL sp_tenant_col('votos');
CALL sp_tenant_col('controle_tribuna');
CALL sp_tenant_col('tramitacao_proposicoes');
CALL sp_tenant_col('logs_sistema');

DROP PROCEDURE IF EXISTS sp_tenant_col;
SET FOREIGN_KEY_CHECKS = 1;

SELECT 'migration 001_multi_tenant executada com sucesso.' AS status;
