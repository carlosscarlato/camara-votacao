<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/bootstrap.php';

setCorsHeaders();
startSession();
resolveTenant();
$action = getAction();

switch ($action) {

    // ── Sessão em andamento ───────────────────────────────────
    case 'ativa':
        $sessao = getSessaoAtiva();
        jsonSuccess($sessao);

    // ── Listar todas as sessões ───────────────────────────────
    case 'listar':
        $stmt = db()->prepare(
            "SELECT * FROM sessoes_plenarias WHERE tenant_id = ? ORDER BY data DESC, id DESC LIMIT 100"
        );
        $stmt->execute([tenantId()]);
        jsonSuccess($stmt->fetchAll());

    // ── Iniciar sessão ────────────────────────────────────────
    case 'iniciar':
        requireAdminAuth();

        // Garante que não há outra em andamento
        $ativa = getSessaoAtiva();
        if ($ativa) {
            jsonError('Já existe uma sessão em andamento (ID ' . $ativa['id'] . ').');
        }

        $numero = (string)requiredInput('numero');
        $data   = (string)requiredInput('data');
        $tipo   = input('tipo', 'ordinaria');

        if (!in_array($tipo, ['ordinaria','extraordinaria','especial'])) {
            jsonError('Tipo de sessão inválido.');
        }

        $stmt = db()->prepare("
            INSERT INTO sessoes_plenarias (tenant_id, numero, data, tipo, status)
            VALUES (?, ?, ?, ?, 'em_andamento')
        ");
        $stmt->execute([tenantId(), $numero, $data, $tipo]);
        $id = (int)db()->lastInsertId();

        jsonSuccess(['id' => $id, 'numero' => $numero, 'data' => $data, 'tipo' => $tipo]);

    // ── Encerrar sessão ───────────────────────────────────────
    case 'encerrar':
        requireAdminAuth();

        $sessaoId = (int)requiredInput('sessao_id');

        // Verifica se há votação aberta
        $stmt = db()->prepare(
            "SELECT od.id FROM ordem_do_dia od
             JOIN sessoes_plenarias s ON s.id = od.sessao_id
             WHERE od.sessao_id = ? AND od.status_votacao = 'votando' AND s.tenant_id = ?"
        );
        $stmt->execute([$sessaoId, tenantId()]);
        if ($stmt->fetch()) {
            jsonError('Encerre a votação em aberto antes de encerrar a sessão.');
        }

        $stmt = db()->prepare(
            "UPDATE sessoes_plenarias SET status = 'encerrada' WHERE id = ? AND status = 'em_andamento' AND tenant_id = ?"
        );
        $stmt->execute([$sessaoId, tenantId()]);

        if ($stmt->rowCount() === 0) {
            jsonError('Sessão não encontrada ou já encerrada.');
        }

        jsonSuccess(['message' => 'Sessão encerrada com sucesso.']);

    // ── Avançar fase da sessão ───────────────────────────────
    case 'avancar_parte':
        requireAdminAuth();

        $sessaoId = (int)requiredInput('sessao_id');
        $parte    = (string)requiredInput('parte');

        if (!in_array($parte, ['expediente', 'ordem_do_dia'])) {
            jsonError('Parte inválida.');
        }

        $stmt = db()->prepare(
            "SELECT id, parte_atual FROM sessoes_plenarias WHERE id = ? AND status = 'em_andamento' AND tenant_id = ?"
        );
        $stmt->execute([$sessaoId, tenantId()]);
        $sessao = $stmt->fetch();
        if (!$sessao) jsonError('Sessão não encontrada ou inativa.');

        $ordem = [null => 0, 'expediente' => 1, 'ordem_do_dia' => 2];
        $idxAtual = $ordem[$sessao['parte_atual']] ?? 0;
        $idxNova  = $ordem[$parte];

        if ($idxNova <= $idxAtual) {
            jsonError('Não é possível voltar para uma fase anterior.');
        }

        db()->prepare(
            "UPDATE sessoes_plenarias SET parte_atual = ? WHERE id = ?"
        )->execute([$parte, $sessaoId]);

        jsonSuccess(['parte_atual' => $parte]);

    default:
        jsonError('Ação inválida.');
}
