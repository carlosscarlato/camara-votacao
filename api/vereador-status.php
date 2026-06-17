<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) session_start();
resolveTenant();
startSession();

$vereadorId = (int)($_SESSION['id'] ?? $_GET['vereador_id'] ?? 0);
session_write_close();

if ($vereadorId === 0) {
    jsonError('Vereador não identificado.', 401);
}

$sessao  = getSessaoAtiva();
$votacao = getVotacaoAtiva();

$meuVoto   = null;
$itemAtual = null;

if ($votacao) {
    $itemAtual = $votacao;
    if ($itemAtual['emendas']) {
        $itemAtual['emendas'] = json_decode($itemAtual['emendas'], true);
    }
    $stmt = db()->prepare(
        "SELECT voto FROM votos WHERE vereador_id = ? AND ordem_dia_id = ?"
    );
    $stmt->execute([$vereadorId, $votacao['id']]);
    $row     = $stmt->fetch();
    $meuVoto = $row ? $row['voto'] : null;
}

$ordemDia = [];
if ($sessao) {
    $stmt = db()->prepare("
        SELECT od.id, od.ordem_exibicao, od.status_votacao, od.resultado,
               p.numero, p.ano, p.tipo, p.ementa, p.autor
        FROM   ordem_do_dia od
        JOIN   proposicoes   p ON p.id = od.proposicao_id
        WHERE  od.sessao_id = ?
        ORDER  BY od.ordem_exibicao ASC
    ");
    $stmt->execute([$sessao['id']]);
    $ordemDia = $stmt->fetchAll();
}

$tribuna        = getTribunaAtiva();
$tribunaPayload = ['ativa' => false];
if ($tribuna) {
    $tempoRestante  = calcularTempoRestante($tribuna);
    $tribunaPayload = [
        'ativa'           => true,
        'sou_eu'          => ((int)$tribuna['vereador_id'] === $vereadorId),
        'nome'            => $tribuna['nome'],
        'partido'         => $tribuna['partido'],
        'status'          => $tribuna['status'],
        'tempo_calculado' => $tempoRestante,
        'tempo_inicial'   => (int)$tribuna['tempo_inicial_segundos'],
    ];
}

jsonSuccess([
    'sessao'         => $sessao,
    'votacao_aberta' => $votacao !== null,
    'ordem_dia_id'   => $votacao['id'] ?? null,
    'item_atual'     => $itemAtual,
    'meu_voto'       => $meuVoto,
    'ordem_dia'      => $ordemDia,
    'tribuna'        => $tribunaPayload,
    'ts'             => time(),
]);
