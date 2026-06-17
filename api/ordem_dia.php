<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/bootstrap.php';

setCorsHeaders();
startSession();
resolveTenant();
$action = getAction();

switch ($action) {

    // ── Listar itens da ordem do dia ──────────────────────────
    case 'listar':
        $sessaoId = (int)requiredInput('sessao_id');
        $stmt = db()->prepare("
            SELECT od.*, p.numero, p.ano, p.tipo, p.ementa,
                   p.link_documento, p.pareceres, p.emendas, p.autor
            FROM   ordem_do_dia od
            JOIN   proposicoes   p ON p.id = od.proposicao_id
            WHERE  od.sessao_id = ?
            ORDER  BY od.ordem_exibicao ASC
        ");
        $stmt->execute([$sessaoId]);
        $items = $stmt->fetchAll();

        foreach ($items as &$item) {
            if ($item['emendas']) {
                $item['emendas'] = json_decode($item['emendas'], true);
            }
        }

        jsonSuccess($items);

    // ── Detalhe de um item ────────────────────────────────────
    case 'item':
        $id = (int)requiredInput('id');
        $stmt = db()->prepare("
            SELECT od.*, p.numero, p.ano, p.tipo, p.ementa,
                   p.link_documento, p.pareceres, p.emendas, p.autor
            FROM   ordem_do_dia od
            JOIN   proposicoes   p ON p.id = od.proposicao_id
            WHERE  od.id = ?
        ");
        $stmt->execute([$id]);
        $item = $stmt->fetch();
        if (!$item) jsonError('Item não encontrado.', 404);

        if ($item['emendas']) {
            $item['emendas'] = json_decode($item['emendas'], true);
        }
        jsonSuccess($item);

    // ── Listar proposições cadastradas ────────────────────────
    case 'listar_proposicoes':
        requireAdminAuth();
        $stmt = db()->query(
            "SELECT id, numero, ano, tipo, ementa, autor FROM proposicoes ORDER BY ano DESC, numero ASC"
        );
        jsonSuccess($stmt->fetchAll());

    // ── Adicionar item à pauta ────────────────────────────────
    case 'adicionar':
        requireAdminAuth();

        $sessaoId      = (int)requiredInput('sessao_id');
        $proposicaoId  = (int)requiredInput('proposicao_id');
        $tipoVotacao   = input('tipo_votacao', 'nominal');

        // Valida sessão em andamento
        $stmt = db()->prepare(
            "SELECT id FROM sessoes_plenarias WHERE id = ? AND status = 'em_andamento'"
        );
        $stmt->execute([$sessaoId]);
        if (!$stmt->fetch()) jsonError('Sessão inativa ou inexistente.');

        // Valida proposição
        $stmt = db()->prepare("SELECT id FROM proposicoes WHERE id = ?");
        $stmt->execute([$proposicaoId]);
        if (!$stmt->fetch()) jsonError('Proposição não encontrada.');

        // Próxima ordem de exibição
        $stmt = db()->prepare(
            "SELECT COALESCE(MAX(ordem_exibicao), 0) + 1 AS prox FROM ordem_do_dia WHERE sessao_id = ?"
        );
        $stmt->execute([$sessaoId]);
        $prox = (int)$stmt->fetchColumn();

        $stmt = db()->prepare("
            INSERT INTO ordem_do_dia (sessao_id, proposicao_id, ordem_exibicao, tipo_votacao)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$sessaoId, $proposicaoId, $prox, $tipoVotacao]);

        jsonSuccess(['id' => (int)db()->lastInsertId(), 'ordem_exibicao' => $prox]);

    // ── Iniciar discussão ─────────────────────────────────────
    case 'iniciar_discussao':
        requireAdminAuth();

        $id = (int)requiredInput('id');
        $stmt = db()->prepare("
            UPDATE ordem_do_dia SET status_votacao = 'em_discussao'
            WHERE id = ? AND status_votacao = 'pendente'
        ");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) jsonError('Item não encontrado ou em estado inválido.');
        jsonSuccess(['message' => 'Discussão iniciada.']);

    // ── Abrir votação ─────────────────────────────────────────
    case 'abrir_votacao':
        requireAdminAuth();

        $id = (int)requiredInput('id');

        // Garante que não há outra votação aberta na mesma sessão
        $stmt = db()->prepare("
            SELECT od.id FROM ordem_do_dia od
            JOIN   sessoes_plenarias s ON s.id = od.sessao_id
            WHERE  od.status_votacao = 'votando'
              AND  s.status = 'em_andamento'
        ");
        $stmt->execute();
        if ($stmt->fetch()) jsonError('Já existe uma votação aberta. Encerre-a primeiro.');

        // Obtém todos vereadores ativos e registra AUSENTE para os que não votarem
        $stmt = db()->prepare("
            UPDATE ordem_do_dia
            SET    status_votacao = 'votando', aberto_em = NOW()
            WHERE  id = ? AND status_votacao IN ('pendente','em_discussao')
        ");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) jsonError('Item não encontrado ou em estado inválido.');
        jsonSuccess(['message' => 'Votação aberta.']);

    // ── Encerrar votação ──────────────────────────────────────
    case 'encerrar_votacao':
        requireAdminAuth();

        $id = (int)requiredInput('id');

        // Contabiliza votos finais
        $stmt = db()->prepare("
            SELECT
                SUM(voto = 'SIM')       AS sim,
                SUM(voto = 'NAO')       AS nao,
                SUM(voto = 'ABSTENCAO') AS abstencao,
                SUM(voto = 'AUSENTE')   AS ausente
            FROM votos WHERE ordem_dia_id = ?
        ");
        $stmt->execute([$id]);
        $placar = $stmt->fetch();

        $sim  = (int)($placar['sim'] ?? 0);
        $nao  = (int)($placar['nao'] ?? 0);
        $abst = (int)($placar['abstencao'] ?? 0);

        if ($sim > $nao)       $resultado = 'aprovado';
        elseif ($nao > $sim)   $resultado = 'rejeitado';
        elseif ($sim === $nao) $resultado = 'empate';
        else                   $resultado = 'nao_votado';

        // Vereadores que não votaram → AUSENTE
        $stmtVer = db()->query("SELECT id FROM vereadores WHERE status = 'ativo'");
        $vereadores = $stmtVer->fetchAll(PDO::FETCH_COLUMN);

        $stmtVotaram = db()->prepare(
            "SELECT vereador_id FROM votos WHERE ordem_dia_id = ?"
        );
        $stmtVotaram->execute([$id]);
        $votaram = $stmtVotaram->fetchAll(PDO::FETCH_COLUMN);

        $ausentes = array_diff($vereadores, $votaram);
        if (!empty($ausentes)) {
            $placeholders = implode(',', array_fill(0, count($ausentes), '(?,?,"AUSENTE")'));
            $values = [];
            foreach ($ausentes as $vid) {
                $values[] = $vid;
                $values[] = $id;
            }
            db()->prepare("INSERT IGNORE INTO votos (vereador_id, ordem_dia_id, voto) VALUES $placeholders")
                ->execute($values);
        }

        // Atualiza ordem_do_dia
        $stmt = db()->prepare("
            UPDATE ordem_do_dia
            SET    status_votacao = 'encerrada',
                   resultado       = ?,
                   votos_sim       = ?,
                   votos_nao       = ?,
                   votos_abstencao = ?,
                   votos_ausente   = ?,
                   encerrado_em    = NOW()
            WHERE  id = ? AND status_votacao = 'votando'
        ");
        $stmt->execute([$resultado, $sim, $nao, $abst, count($ausentes), $id]);

        if ($stmt->rowCount() === 0) jsonError('Votação não encontrada ou já encerrada.');

        // Registro de tramitação (auditoria pública)
        $stmtOD = db()->prepare(
            "SELECT proposicao_id, sessao_id FROM ordem_do_dia WHERE id = ?"
        );
        $stmtOD->execute([$id]);
        $od = $stmtOD->fetch();

        $stmtVotos = db()->prepare("
            SELECT v.voto, ver.nome, ver.partido
            FROM   votos v JOIN vereadores ver ON ver.id = v.vereador_id
            WHERE  v.ordem_dia_id = ?
        ");
        $stmtVotos->execute([$id]);
        $detalheVotos = $stmtVotos->fetchAll();

        db()->prepare("
            INSERT INTO tramitacao_proposicoes
                (proposicao_id, sessao_id, ordem_dia_id, evento, descricao,
                 resultado, votos_sim, votos_nao, votos_abstencao, detalhes_json)
            VALUES (?, ?, ?, 'VOTACAO_ENCERRADA', ?, ?, ?, ?, ?, ?)
        ")->execute([
            $od['proposicao_id'],
            $od['sessao_id'],
            $id,
            "Votação encerrada. Resultado: $resultado. Placar: {$sim} SIM, {$nao} NÃO, {$abst} ABSTENÇÃO.",
            $resultado,
            $sim,
            $nao,
            $abst,
            json_encode($detalheVotos, JSON_UNESCAPED_UNICODE),
        ]);

        // Atualiza status da proposição
        if (in_array($resultado, ['aprovado','rejeitado'])) {
            db()->prepare(
                "UPDATE proposicoes SET status = ? WHERE id = ?"
            )->execute([$resultado, $od['proposicao_id']]);
        }

        jsonSuccess([
            'resultado' => $resultado,
            'placar'    => ['sim' => $sim, 'nao' => $nao, 'abstencao' => $abst],
        ]);

    default:
        jsonError('Ação inválida.');
}
