-- ============================================================
-- UPDATE v2.0 — Sistema Comercial / Auditável
-- MySQL 8.0 compatível (sem ADD COLUMN IF NOT EXISTS)
-- Execute: mysql -u root -proot camara_votacao < update.sql
-- ============================================================
SET NAMES utf8mb4;

-- ── logs_sistema (auditoria imutável) ────────────────────────
CREATE TABLE IF NOT EXISTS `logs_sistema` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id`   INT UNSIGNED    DEFAULT NULL,
  `vereador_id`  INT UNSIGNED    DEFAULT NULL,
  `acao`         VARCHAR(100)    NOT NULL,
  `detalhes`     TEXT            DEFAULT NULL,
  `ip_origem`    VARCHAR(45)     NOT NULL DEFAULT '',
  `user_agent`   VARCHAR(500)    DEFAULT NULL,
  `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_usuario`    (`usuario_id`),
  KEY `idx_acao`       (`acao`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Adicionar colunas condicionalmente via procedure ─────────
DROP PROCEDURE IF EXISTS sp_add_column_if_missing;
DELIMITER $$
CREATE PROCEDURE sp_add_column_if_missing(
  IN p_table  VARCHAR(64),
  IN p_col    VARCHAR(64),
  IN p_def    TEXT
)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = p_table
      AND COLUMN_NAME  = p_col
  ) THEN
    SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN ', p_def);
    PREPARE s FROM @sql;
    EXECUTE s;
    DEALLOCATE PREPARE s;
  END IF;
END$$
DELIMITER ;

-- usuarios
CALL sp_add_column_if_missing('usuarios','email',            '`email`             VARCHAR(150)     DEFAULT NULL AFTER `login`');
CALL sp_add_column_if_missing('usuarios','permissao_level',  '`permissao_level`   TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER `perfil`');
CALL sp_add_column_if_missing('usuarios','token_recuperacao','`token_recuperacao` VARCHAR(64)      DEFAULT NULL');
CALL sp_add_column_if_missing('usuarios','token_expira_em',  '`token_expira_em`   DATETIME         DEFAULT NULL');

-- vereadores
CALL sp_add_column_if_missing('vereadores','email',   '`email`    VARCHAR(150) DEFAULT NULL AFTER `pin`');
CALL sp_add_column_if_missing('vereadores','cargo_id','`cargo_id` TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER `email`');

DROP PROCEDURE IF EXISTS sp_add_column_if_missing;

-- ── Índice único de e-mail (ignora se já existe) ─────────────
DROP PROCEDURE IF EXISTS sp_add_index_if_missing;
DELIMITER $$
CREATE PROCEDURE sp_add_index_if_missing()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'usuarios'
      AND INDEX_NAME   = 'uk_email'
  ) THEN
    ALTER TABLE `usuarios` ADD UNIQUE KEY `uk_email` (`email`);
  END IF;
END$$
DELIMITER ;
CALL sp_add_index_if_missing();
DROP PROCEDURE IF EXISTS sp_add_index_if_missing;

-- ── Emails padrão para usuários iniciais ─────────────────────
UPDATE `usuarios` SET `email` = 'admin@camara.gov.br'
  WHERE `login` = 'admin' AND (`email` IS NULL OR `email` = '');
UPDATE `usuarios` SET `email` = 'operador@camara.gov.br'
  WHERE `login` = 'operador' AND (`email` IS NULL OR `email` = '');

SELECT 'update.sql v2.0 executado com sucesso.' AS status;
