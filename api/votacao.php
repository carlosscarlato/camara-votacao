<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/bootstrap.php';

setCorsHeaders();
startSession();
resolveTenant();
$action = getAction();

switch ($action) {

    // ── Registrar voto ────────────────────────────────────────
    case 'votar':
        $auth = requireVereadorAuth();

        $ordemDiaId = (int)requiredInput('ordem_dia_id');
        $voto       = strtoupper((string)requiredInput('voto'));

        if (!in_array($voto, ['SIM','NAO','ABSTENCAO'])) {
            jsonError('Voto inválido. Use SIM, NAO ou ABSTENCAO.');
        }

        // Verifica se a votação está realmente aberta e pertence ao tenant
        $stmt = db()->prepare(
            "SELECT od.id FROM ordem_do_dia od
             JOIN sessoes_plenarias s ON s.id = od.sessao_id
             WHERE od.id = ? AND od.status_votacao = 'votando' AND s.tenant_id = ?"
        );
        $stmt->execute([$ordemDiaId, tenantId()]);
        if (!$stmt->fetch()) {
            jsonError('Votação não está aberta para este item.');
        }

        // Verifica se o vereador já votou
        $stmt = db()->prepare(
            "SELECT id, voto FROM votos WHERE vereador_id = ? AND ordem_dia_id = ?"
        );
        $stmt->execute([$auth['id'], $ordemDiaId]);
        $existente = $stmt->fetch();

        if ($existente) {
            jsonError('Você já registrou seu voto: ' . $existente['voto'] . '.', 409);
        }

        // Insere o voto
        $stmt = db()->prepare(
            "INSERT INTO votos (vereador_id, ordem_dia_id, voto) VALUES (?, ?, ?)"
        );
        $stmt->execute([$auth['id'], $ordemDiaId, $voto]);

        jsonSuccess([
            'message'     => 'Voto registrado com sucesso.',
            'voto'        => $voto,
            'vereador_id' => $auth['id'],
        ]);

    // ── Resultado de uma votação ───────────────────────────────
    case 'resultado':
        $ordemDiaId = (int)requiredInput('ordem_dia_id');

        $stmt = db()->prepare("
            SELECT v.voto, ver.id AS vereador_id, ver.nome, ver.partido, ver.foto,
                   v.timestamp
            FROM   vereadores ver
            LEFT JOIN votos v
                   ON v.vereador_id = ver.id AND v.ordem_dia_id = ?
            WHERE  ver.status = 'ativo' AND ver.tenant_id = ?
            ORDER  BY ver.nome ASC
        ");
        $stmt->execute([$ordemDiaId, tenantId()]);
        $votos = $stmt->fetchAll();

        $placar = ['sim' => 0, 'nao' => 0, 'abstencao' => 0, 'ausente' => 0, 'pendente' => 0];
        foreach ($votos as $v) {
            match ($v['voto']) {
                'SIM'       => $placar['sim']++,
                'NAO'       => $placar['nao']++,
                'ABSTENCAO' => $placar['abstencao']++,
                'AUSENTE'   => $placar['ausente']++,
                default     => $placar['pendente']++,
            };
        }

        jsonSuccess(['votos' => $votos, 'placar' => $placar]);

    // ── Verificar meu voto ────────────────────────────────────
    case 'meu_voto':
        $auth       = requireVereadorAuth();
        $ordemDiaId = (int)requiredInput('ordem_dia_id');

        $stmt = db()->prepare(
            "SELECT voto, timestamp FROM votos WHERE vereador_id = ? AND ordem_dia_id = ?"
        );
        $stmt->execute([$auth['id'], $ordemDiaId]);
        $voto = $stmt->fetch();

        jsonSuccess($voto ?: ['voto' => null]);

    default:
        jsonError('Ação inválida.');
}
