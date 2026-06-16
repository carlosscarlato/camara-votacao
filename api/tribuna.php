<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/helpers.php';

setCorsHeaders();
$action = getAction();

switch ($action) {

    // ── Status atual da tribuna ───────────────────────────────
    case 'status':
        $tribuna = getTribunaAtiva();
        if ($tribuna) {
            $tribuna['tempo_calculado'] = calcularTempoRestante($tribuna);
        }
        jsonSuccess($tribuna);

    // ── Listar vereadores para seleção ────────────────────────
    case 'lista_vereadores':
        $stmt = db()->query(
            "SELECT id, nome, partido, foto FROM vereadores WHERE status = 'ativo' ORDER BY nome"
        );
        jsonSuccess($stmt->fetchAll());

    // ── Iniciar tribuna ───────────────────────────────────────
    case 'iniciar':
        requireAdminAuth();

        $sessaoId    = (int)requiredInput('sessao_id');
        $vereadorId  = (int)requiredInput('vereador_id');
        $tempoSegundos = (int)input('tempo_segundos', 300);

        // Encerra qualquer tribuna anterior ainda ativa
        db()->prepare("
            UPDATE controle_tribuna
            SET    status = 'encerrado', encerrado_em = NOW()
            WHERE  sessao_id = ? AND status IN ('aguardando','falando','pausado')
        ")->execute([$sessaoId]);

        $stmt = db()->prepare("
            INSERT INTO controle_tribuna
                (sessao_id, vereador_id, tempo_inicial_segundos, tempo_restante, status, iniciado_em)
            VALUES (?, ?, ?, ?, 'falando', NOW())
        ");
        $stmt->execute([$sessaoId, $vereadorId, $tempoSegundos, $tempoSegundos]);

        jsonSuccess(['id' => (int)db()->lastInsertId()]);

    // ── Pausar ────────────────────────────────────────────────
    case 'pausar':
        requireAdminAuth();

        $id = (int)requiredInput('id');

        $stmt = db()->prepare(
            "SELECT tempo_restante, iniciado_em, status FROM controle_tribuna WHERE id = ?"
        );
        $stmt->execute([$id]);
        $tribuna = $stmt->fetch();
        if (!$tribuna) jsonError('Tribuna não encontrada.');
        if ($tribuna['status'] !== 'falando') jsonError('Só é possível pausar uma tribuna em andamento.');

        $restante = calcularTempoRestante($tribuna);

        db()->prepare("
            UPDATE controle_tribuna
            SET    status = 'pausado', tempo_restante = ?, pausado_em = NOW()
            WHERE  id = ?
        ")->execute([$restante, $id]);

        jsonSuccess(['tempo_restante' => $restante]);

    // ── Retomar ───────────────────────────────────────────────
    case 'retomar':
        requireAdminAuth();

        $id = (int)requiredInput('id');
        $stmt = db()->prepare("SELECT status FROM controle_tribuna WHERE id = ?");
        $stmt->execute([$id]);
        $tribuna = $stmt->fetch();
        if (!$tribuna) jsonError('Tribuna não encontrada.');
        if ($tribuna['status'] !== 'pausado') jsonError('Só é possível retomar uma tribuna pausada.');

        db()->prepare("
            UPDATE controle_tribuna
            SET    status = 'falando', iniciado_em = NOW(), pausado_em = NULL
            WHERE  id = ?
        ")->execute([$id]);

        jsonSuccess(['message' => 'Tribuna retomada.']);

    // ── Encerrar ──────────────────────────────────────────────
    case 'encerrar':
        requireAdminAuth();

        $id = (int)requiredInput('id');
        $stmt = db()->prepare("
            UPDATE controle_tribuna
            SET    status = 'encerrado', tempo_restante = 0, encerrado_em = NOW()
            WHERE  id = ? AND status IN ('aguardando','falando','pausado')
        ");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) jsonError('Tribuna não encontrada ou já encerrada.');
        jsonSuccess(['message' => 'Tribuna encerrada.']);

    default:
        jsonError('Ação inválida.');
}
