<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/bootstrap.php';

setCorsHeaders();
startSession();
resolveTenant();

// Endpoint público — sem autenticação
$action = getAction();

switch ($action) {

    // ── Histórico de votações encerradas ──────────────────────
    case 'historico':
    default:
        $pagina  = max(1, (int)input('pagina', 1));
        $limite  = 20;
        $offset  = ($pagina - 1) * $limite;

        $stmt = db()->prepare("
            SELECT
                od.id                AS ordem_dia_id,
                od.encerrado_em,
                od.resultado,
                od.votos_sim,
                od.votos_nao,
                od.votos_abstencao,
                od.votos_ausente,
                s.numero             AS sessao_numero,
                s.data               AS sessao_data,
                s.tipo               AS sessao_tipo,
                p.numero             AS prop_numero,
                p.ano                AS prop_ano,
                p.tipo               AS prop_tipo,
                p.ementa             AS prop_ementa,
                p.autor              AS prop_autor
            FROM   ordem_do_dia od
            JOIN   sessoes_plenarias s ON s.id = od.sessao_id
            JOIN   proposicoes       p ON p.id = od.proposicao_id
            WHERE  od.status_votacao = 'encerrada'
            ORDER  BY od.encerrado_em DESC
            LIMIT  ? OFFSET ?
        ");
        $stmt->execute([$limite, $offset]);
        $votacoes = $stmt->fetchAll();

        $total = (int)db()->query(
            "SELECT COUNT(*) FROM ordem_do_dia WHERE status_votacao = 'encerrada'"
        )->fetchColumn();

        jsonSuccess([
            'votacoes'   => $votacoes,
            'total'      => $total,
            'pagina'     => $pagina,
            'por_pagina' => $limite,
        ]);

    // ── Detalhe de uma votação (como cada vereador votou) ─────
    case 'detalhe':
        $ordemDiaId = (int)requiredInput('ordem_dia_id');

        $stmt = db()->prepare("
            SELECT od.id, od.resultado, od.votos_sim, od.votos_nao,
                   od.votos_abstencao, od.votos_ausente, od.aberto_em, od.encerrado_em,
                   p.numero, p.ano, p.tipo, p.ementa, p.autor,
                   s.numero AS sessao_numero, s.data AS sessao_data
            FROM   ordem_do_dia od
            JOIN   proposicoes       p ON p.id = od.proposicao_id
            JOIN   sessoes_plenarias s ON s.id = od.sessao_id
            WHERE  od.id = ? AND od.status_votacao = 'encerrada'
        ");
        $stmt->execute([$ordemDiaId]);
        $item = $stmt->fetch();
        if (!$item) jsonError('Votação não encontrada ou ainda em aberto.', 404);

        $stmtVotos = db()->prepare("
            SELECT ver.nome, ver.partido, ver.foto, v.voto, v.timestamp
            FROM   votos v
            JOIN   vereadores ver ON ver.id = v.vereador_id
            WHERE  v.ordem_dia_id = ?
            ORDER  BY ver.nome ASC
        ");
        $stmtVotos->execute([$ordemDiaId]);
        $votos = $stmtVotos->fetchAll();

        jsonSuccess(['votacao' => $item, 'votos' => $votos]);

    // ── Tramitação de uma proposição ──────────────────────────
    case 'tramitacao':
        $proposicaoId = (int)requiredInput('proposicao_id');

        $stmtProp = db()->prepare(
            "SELECT * FROM proposicoes WHERE id = ?"
        );
        $stmtProp->execute([$proposicaoId]);
        $prop = $stmtProp->fetch();
        if (!$prop) jsonError('Proposição não encontrada.', 404);

        $stmtTram = db()->prepare("
            SELECT t.*, s.numero AS sessao_numero, s.data AS sessao_data
            FROM   tramitacao_proposicoes t
            JOIN   sessoes_plenarias s ON s.id = t.sessao_id
            WHERE  t.proposicao_id = ?
            ORDER  BY t.registrado_em ASC
        ");
        $stmtTram->execute([$proposicaoId]);
        $tramitacoes = $stmtTram->fetchAll();

        foreach ($tramitacoes as &$t) {
            if ($t['detalhes_json']) {
                $t['detalhes'] = json_decode($t['detalhes_json'], true);
                unset($t['detalhes_json']);
            }
        }

        jsonSuccess(['proposicao' => $prop, 'tramitacao' => $tramitacoes]);

    // ── Busca de proposições ──────────────────────────────────
    case 'buscar':
        $termo = '%' . trim((string)input('q', '')) . '%';
        $stmt = db()->prepare("
            SELECT p.id, p.numero, p.ano, p.tipo, p.ementa, p.autor, p.status
            FROM   proposicoes p
            WHERE  p.ementa LIKE ? OR p.numero LIKE ? OR p.autor LIKE ?
            ORDER  BY p.ano DESC, p.numero ASC
            LIMIT  50
        ");
        $stmt->execute([$termo, $termo, $termo]);
        jsonSuccess($stmt->fetchAll());
}
