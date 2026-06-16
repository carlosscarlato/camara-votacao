-- ============================================================
-- SISTEMA DE VOTAÇÃO ELETRÔNICA - CÂMARA DE VEREADORES
-- Versão: 1.0 | Charset: utf8mb4
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

CREATE DATABASE IF NOT EXISTS `camara_votacao`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE `camara_votacao`;

-- -------------------------------------------------------
-- vereadores
-- -------------------------------------------------------
CREATE TABLE `vereadores` (
  `id`         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `nome`       VARCHAR(150)    NOT NULL,
  `partido`    VARCHAR(50)     NOT NULL,
  `foto`       VARCHAR(255)    NOT NULL DEFAULT 'assets/img/default-avatar.svg',
  `status`     ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
  `pin`        CHAR(6)         NOT NULL DEFAULT '123456',
  `created_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- usuarios (Mesa Diretora / Operadores)
-- -------------------------------------------------------
CREATE TABLE `usuarios` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `nome`        VARCHAR(150)    NOT NULL,
  `login`       VARCHAR(50)     NOT NULL,
  `senha_hash`  VARCHAR(255)    NOT NULL,
  `perfil`      ENUM('admin','operador') NOT NULL DEFAULT 'operador',
  `ativo`       TINYINT(1)      NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_login` (`login`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- sessoes_plenarias
-- -------------------------------------------------------
CREATE TABLE `sessoes_plenarias` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `numero`      VARCHAR(20)     NOT NULL,
  `data`        DATE            NOT NULL,
  `tipo`        ENUM('ordinaria','extraordinaria','especial') NOT NULL DEFAULT 'ordinaria',
  `status`      ENUM('agendada','em_andamento','encerrada') NOT NULL DEFAULT 'agendada',
  `usuario_id`  INT UNSIGNED    DEFAULT NULL,
  `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_data`   (`data`),
  CONSTRAINT `fk_sessao_usuario` FOREIGN KEY (`usuario_id`)
    REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- proposicoes
-- -------------------------------------------------------
CREATE TABLE `proposicoes` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `numero`         VARCHAR(20)  NOT NULL,
  `ano`            YEAR         NOT NULL,
  `tipo`           ENUM('Projeto de Lei','Projeto de Lei Complementar','Requerimento',
                        'Indicação','Moção','Decreto Legislativo','Resolução') NOT NULL,
  `ementa`         TEXT         NOT NULL,
  `link_documento` VARCHAR(500) DEFAULT NULL,
  `pareceres`      TEXT         DEFAULT NULL,
  `emendas`        JSON         DEFAULT NULL,
  `autor`          VARCHAR(150) DEFAULT NULL,
  `status`         ENUM('em_tramitacao','aprovado','rejeitado','arquivado','retirado')
                               NOT NULL DEFAULT 'em_tramitacao',
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_numero_ano_tipo` (`numero`, `ano`, `tipo`),
  KEY `idx_tipo`   (`tipo`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- ordem_do_dia
-- -------------------------------------------------------
CREATE TABLE `ordem_do_dia` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sessao_id`       INT UNSIGNED NOT NULL,
  `proposicao_id`   INT UNSIGNED NOT NULL,
  `ordem_exibicao`  SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `status_votacao`  ENUM('pendente','em_discussao','votando','encerrada','retirada')
                                NOT NULL DEFAULT 'pendente',
  `tipo_votacao`    ENUM('nominal','simbolica','secreta') NOT NULL DEFAULT 'nominal',
  `resultado`       ENUM('aprovado','rejeitado','empate','nao_votado') DEFAULT NULL,
  `votos_sim`       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `votos_nao`       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `votos_abstencao` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `votos_ausente`   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `aberto_em`       DATETIME DEFAULT NULL,
  `encerrado_em`    DATETIME DEFAULT NULL,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sessao`      (`sessao_id`),
  KEY `idx_proposicao`  (`proposicao_id`),
  KEY `idx_status`      (`status_votacao`),
  CONSTRAINT `fk_od_sessao`      FOREIGN KEY (`sessao_id`)     REFERENCES `sessoes_plenarias` (`id`),
  CONSTRAINT `fk_od_proposicao`  FOREIGN KEY (`proposicao_id`) REFERENCES `proposicoes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- votos
-- -------------------------------------------------------
CREATE TABLE `votos` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `vereador_id`  INT UNSIGNED NOT NULL,
  `ordem_dia_id` INT UNSIGNED NOT NULL,
  `voto`         ENUM('SIM','NAO','ABSTENCAO','AUSENTE') NOT NULL,
  `timestamp`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_vereador_votacao` (`vereador_id`, `ordem_dia_id`),
  KEY `idx_ordem_dia` (`ordem_dia_id`),
  KEY `idx_voto`      (`voto`),
  CONSTRAINT `fk_voto_vereador`  FOREIGN KEY (`vereador_id`)  REFERENCES `vereadores` (`id`),
  CONSTRAINT `fk_voto_ordem_dia` FOREIGN KEY (`ordem_dia_id`) REFERENCES `ordem_do_dia` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- controle_tribuna
-- -------------------------------------------------------
CREATE TABLE `controle_tribuna` (
  `id`                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sessao_id`              INT UNSIGNED NOT NULL,
  `vereador_id`            INT UNSIGNED NOT NULL,
  `tempo_inicial_segundos` SMALLINT UNSIGNED NOT NULL DEFAULT 300,
  `tempo_restante`         SMALLINT UNSIGNED NOT NULL DEFAULT 300,
  `status`                 ENUM('aguardando','falando','pausado','encerrado')
                                        NOT NULL DEFAULT 'aguardando',
  `iniciado_em`            DATETIME DEFAULT NULL,
  `pausado_em`             DATETIME DEFAULT NULL,
  `encerrado_em`           DATETIME DEFAULT NULL,
  `created_at`             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sessao`   (`sessao_id`),
  KEY `idx_vereador` (`vereador_id`),
  KEY `idx_status`   (`status`),
  CONSTRAINT `fk_tribuna_sessao`   FOREIGN KEY (`sessao_id`)   REFERENCES `sessoes_plenarias` (`id`),
  CONSTRAINT `fk_tribuna_vereador` FOREIGN KEY (`vereador_id`) REFERENCES `vereadores` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- tramitacao_proposicoes (auditoria pública)
-- -------------------------------------------------------
CREATE TABLE `tramitacao_proposicoes` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `proposicao_id`  INT UNSIGNED NOT NULL,
  `sessao_id`      INT UNSIGNED NOT NULL,
  `ordem_dia_id`   INT UNSIGNED NOT NULL,
  `evento`         VARCHAR(100) NOT NULL,
  `descricao`      TEXT         NOT NULL,
  `resultado`      ENUM('aprovado','rejeitado','empate','nao_votado') DEFAULT NULL,
  `votos_sim`      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `votos_nao`      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `votos_abstencao`SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `detalhes_json`  JSON         DEFAULT NULL,
  `registrado_em`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_proposicao` (`proposicao_id`),
  KEY `idx_sessao`     (`sessao_id`),
  CONSTRAINT `fk_tram_proposicao` FOREIGN KEY (`proposicao_id`) REFERENCES `proposicoes`        (`id`),
  CONSTRAINT `fk_tram_sessao`     FOREIGN KEY (`sessao_id`)     REFERENCES `sessoes_plenarias`  (`id`),
  CONSTRAINT `fk_tram_ordem_dia`  FOREIGN KEY (`ordem_dia_id`)  REFERENCES `ordem_do_dia`       (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- Dados iniciais
-- -------------------------------------------------------

-- Usuários (senhas geradas via setup.php)
INSERT INTO `usuarios` (`nome`, `login`, `senha_hash`, `perfil`) VALUES
('Presidente da Mesa', 'admin',    '$2y$12$placeholder_run_setup_php', 'admin'),
('Operador Sessão',   'operador', '$2y$12$placeholder_run_setup_php', 'operador');

-- Vereadores de exemplo
INSERT INTO `vereadores` (`nome`, `partido`, `foto`, `pin`) VALUES
('João da Silva',      'PT',          'assets/img/default-avatar.svg', '111111'),
('Maria Oliveira',     'PSDB',        'assets/img/default-avatar.svg', '222222'),
('Carlos Souza',       'PL',          'assets/img/default-avatar.svg', '333333'),
('Ana Santos',         'MDB',         'assets/img/default-avatar.svg', '444444'),
('Pedro Lima',         'PP',          'assets/img/default-avatar.svg', '555555'),
('Lucia Ferreira',     'PDT',         'assets/img/default-avatar.svg', '666666'),
('Roberto Alves',      'Republicanos','assets/img/default-avatar.svg', '777777'),
('Sandra Costa',       'Avante',      'assets/img/default-avatar.svg', '888888'),
('Marcos Pereira',     'PSD',         'assets/img/default-avatar.svg', '999999');

-- Proposições de exemplo
INSERT INTO `proposicoes` (`numero`, `ano`, `tipo`, `ementa`, `autor`) VALUES
('001', 2024, 'Projeto de Lei',
 'Dispõe sobre a criação do programa de pavimentação das vias rurais do município e dá outras providências.',
 'João da Silva'),
('002', 2024, 'Projeto de Lei',
 'Institui a política municipal de segurança alimentar e nutricional, cria o Conselho Municipal e dá outras providências.',
 'Maria Oliveira'),
('003', 2024, 'Requerimento',
 'Requer informações ao Poder Executivo acerca dos procedimentos da licitação nº 012/2024 referente à limpeza pública.',
 'Carlos Souza'),
('001', 2024, 'Indicação',
 'Indica ao Poder Executivo Municipal a implantação de lombadas eletrônicas na Rua das Flores, nº 350.',
 'Ana Santos'),
('004', 2024, 'Projeto de Lei',
 'Institui o Programa Municipal de Incentivo ao Empreendedorismo Jovem e dá outras providências.',
 'Pedro Lima');

COMMIT;
