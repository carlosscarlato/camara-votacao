-- migrations/003_ai_chat.sql
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `ai_chat_logs` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `tenant_id`  INT UNSIGNED  NOT NULL,
  `user_id`    INT UNSIGNED  NOT NULL,
  `message`    TEXT          NOT NULL,
  `response`   TEXT          DEFAULT NULL,
  `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_user` (`tenant_id`, `user_id`),
  KEY `idx_created_at`  (`created_at`),
  CONSTRAINT `fk_acl_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_acl_user`   FOREIGN KEY (`user_id`)   REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT '003_ai_chat.sql executado com sucesso.' AS status;
