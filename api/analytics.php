<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/helpers.php';

setCorsHeaders();
requireAdminAuth();

$action = getAction();

switch ($action) {

    // ── Resumo geral (cards do dashboard) ────────────────────
    case 'resumo':
        $row = db()->query("
            SELECT
              (SELECT COUNT(*) FROM sessoes_plenarias)                    AS total_sessoes,
              (SELECT COUNT(*) FROM sessoes_plenarias
               WHERE  status = 'encerrada')                               AS sessoes_encerradas,
              (SELECT COUNT(*) FROM proposicoes)                           AS total_proposicoes,
              (SELECT COUNT(*) FROM ordem_do_dia
               WHERE  status_votacao = 'encerrada')                       AS total_votacoes,
              (SELECT COUNT(*) FROM ordem_do_dia
               WHERE  resultado = 'aprovado')                             AS aprovadas,
              (SELECT COUNT(*) FROM ordem_do_dia
               WHERE  resultado = 'rejeitado')                            AS rejeitadas,
              (SELECT COUNT(*) FROM ordem_do_dia
               WHERE  resultado = 'empate')                               AS empates,
              (SELECT COUNT(*) FROM vereadores WHERE status = 'ativo')    AS vereadores_ativos,
              (SELECT COUNT(*) FROM usuarios    WHERE ativo = 1)          AS usuarios_ativos,
              (SELECT COUNT(*) FROM logs_sistema)                         AS total_logs
        ")->fetch();
        jsonSuccess($row);

    // ── Votações por mês (gráfico de linha) ──────────────────
    case 'votacoes_por_mes':
        $rows = db()->query("
            SELECT DATE_FORMAT(s.data, '%Y-%m')      AS mes,
                   DATE_FORMAT(s.data, '%b/%Y')      AS mes_label,
                   COUNT(od.id)                      AS total,
                   SUM(od.resultado = 'aprovado')    AS aprovadas,
                   SUM(od.resultado = 'rejeitado')   AS rejeitadas,
                   SUM(od.resultado = 'empate')      AS empates
            FROM   ordem_do_dia od
            JOIN   sessoes_plenarias s ON s.id = od.sessao_id
            WHERE  od.status_votacao = 'encerrada'
            GROUP  BY mes, mes_label
            ORDER  BY mes
            LIMIT  12
        ")->fetchAll();
        jsonSuccess($rows);

    // ── Assiduidade dos vereadores (gráfico de barras) ───────
    case 'assiduidade':
        $rows = db()->query("
            SELECT
              v.nome,
              v.partido,
              COUNT(DISTINCT vt.ordem_dia_id)                                    AS participacoes,
              SUM(vt.voto <> 'AUSENTE')                                          AS presencas,
              SUM(vt.voto = 'AUSENTE')                                           AS ausencias,
              ROUND(SUM(vt.voto <> 'AUSENTE') / NULLIF(COUNT(*),0) * 100, 1)    AS pct_presenca
            FROM   vereadores v
            LEFT JOIN votos vt ON vt.vereador_id = v.id
            WHERE  v.status = 'ativo'
            GROUP  BY v.id, v.nome, v.partido
            ORDER  BY pct_presenca DESC
        ")->fetchAll();
        jsonSuccess($rows);

    // ── Aprovação por partido (gráfico pizza/barras) ─────────
    case 'aprovacao_partido':
        $rows = db()->query("
            SELECT
              v.partido,
              SUM(vt.voto = 'SIM')       AS votos_sim,
              SUM(vt.voto = 'NAO')       AS votos_nao,
              SUM(vt.voto = 'ABSTENCAO') AS abstencoes,
              COUNT(*)                   AS total_votos
            FROM   votos vt
            JOIN   vereadores v ON v.id = vt.vereador_id
            GROUP  BY v.partido
            ORDER  BY votos_sim DESC
        ")->fetchAll();
        jsonSuccess($rows);

    // ── Últimas sessões (tabela recente) ─────────────────────
    case 'ultimas_sessoes':
        $rows = db()->query("
            SELECT s.id, s.numero, s.data, s.tipo, s.status,
                   COUNT(od.id)                                   AS total_itens,
                   SUM(od.resultado = 'aprovado')                 AS aprovados,
                   SUM(od.resultado = 'rejeitado')                AS rejeitados
            FROM   sessoes_plenarias s
            LEFT JOIN ordem_do_dia od ON od.sessao_id = s.id
            GROUP  BY s.id
            ORDER  BY s.data DESC
            LIMIT  10
        ")->fetchAll();
        jsonSuccess($rows);

    default:
        jsonError('Ação inválida.');
}
