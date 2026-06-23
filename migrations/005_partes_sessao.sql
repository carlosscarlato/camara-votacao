-- 005: Partes da sessão (Expediente / Ordem do Dia)

ALTER TABLE sessoes_plenarias
  ADD COLUMN parte_atual ENUM('expediente','ordem_do_dia') DEFAULT NULL
  AFTER status;

ALTER TABLE ordem_do_dia
  ADD COLUMN parte ENUM('expediente','ordem_do_dia') NOT NULL DEFAULT 'ordem_do_dia'
  AFTER sessao_id,
  ADD COLUMN tipo_aprovacao ENUM('votacao','aclamacao') NOT NULL DEFAULT 'votacao'
  AFTER encerrado_em;
