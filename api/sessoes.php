<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/helpers.php';

setCorsHeaders();
$action = getAction();

switch ($action) {

    // ── Sessão em andamento ───────────────────────────────────
    case 'ativa':
        $sessao = getSessaoAtiva();
        jsonSuccess($sessao);

    // ── Listar todas as sessões ───────────────────────────────
    case 'listar':
        $stmt = db()->query(
            "SELECT * FROM sessoes_plenarias ORDER BY data DESC, id DESC LIMIT 100"
        );
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
            INSERT INTO sessoes_plenarias (numero, data, tipo, status)
            VALUES (?, ?, ?, 'em_andamento')
        ");
        $stmt->execute([$numero, $data, $tipo]);
        $id = (int)db()->lastInsertId();

        jsonSuccess(['id' => $id, 'numero' => $numero, 'data' => $data, 'tipo' => $tipo]);

    // ── Encerrar sessão ───────────────────────────────────────
    case 'encerrar':
        requireAdminAuth();

        $sessaoId = (int)requiredInput('sessao_id');

        // Verifica se há votação aberta
        $stmt = db()->prepare(
            "SELECT id FROM ordem_do_dia WHERE sessao_id = ? AND status_votacao = 'votando'"
        );
        $stmt->execute([$sessaoId]);
        if ($stmt->fetch()) {
            jsonError('Encerre a votação em aberto antes de encerrar a sessão.');
        }

        $stmt = db()->prepare(
            "UPDATE sessoes_plenarias SET status = 'encerrada' WHERE id = ? AND status = 'em_andamento'"
        );
        $stmt->execute([$sessaoId]);

        if ($stmt->rowCount() === 0) {
            jsonError('Sessão não encontrada ou já encerrada.');
        }

        jsonSuccess(['message' => 'Sessão encerrada com sucesso.']);

    default:
        jsonError('Ação inválida.');
}
