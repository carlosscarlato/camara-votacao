<?php
declare(strict_types=1);

// SSE: Aplicativo do Vereador
// Emite o estado da votação e se o vereador já votou

set_time_limit(0);
ignore_user_abort(true);
ini_set('output_buffering', 'off');
ini_set('zlib.output_compression', '0');

header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, no-store');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');
header('Access-Control-Allow-Origin: *');

while (ob_get_level() > 0) ob_end_clean();

require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/bootstrap.php';
if (session_status() === PHP_SESSION_NONE) session_start();
resolveTenant();
startSession();

// Lê os dados necessários da sessão...
$vereadorId = (int)($_SESSION['id'] ?? $_GET['vereador_id'] ?? 0);

// CRÍTICO: libera o lock do arquivo de sessão imediatamente.
// Sem isso, a sessão fica bloqueada durante toda a vida da conexão SSE,
// impedindo qualquer chamada de API do mesmo browser de executar session_start().
session_write_close();

if ($vereadorId === 0) {
    echo "event: error\ndata: " . json_encode(['message' => 'Vereador não identificado.']) . "\n\n";
    flush();
    exit;
}

function sendEvent(string $event, mixed $data): void
{
    echo "event: $event\n";
    echo "data: " . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
    if (ob_get_level() > 0) ob_flush();
    flush();
}

function getVereadorState(int $vereadorId): array
{
    $sessao  = getSessaoAtiva();
    $votacao = getVotacaoAtiva();

    $meuVoto    = null;
    $itemAtual  = null;

    if ($votacao) {
        $itemAtual = $votacao;
        if ($itemAtual['emendas']) {
            $itemAtual['emendas'] = json_decode($itemAtual['emendas'], true);
        }

        $stmt = db()->prepare(
            "SELECT voto FROM votos WHERE vereador_id = ? AND ordem_dia_id = ?"
        );
        $stmt->execute([$vereadorId, $votacao['id']]);
        $row = $stmt->fetch();
        $meuVoto = $row ? $row['voto'] : null;
    }

    // Agenda do dia
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

    // Tribuna ativa — informa se este vereador está na tribuna agora
    $tribuna        = getTribunaAtiva();
    $tribunaPayload = ['ativa' => false];
    if ($tribuna) {
        $tempoRestante  = calcularTempoRestante($tribuna);
        $tribunaPayload = [
            'ativa'            => true,
            'sou_eu'           => ((int)$tribuna['vereador_id'] === $vereadorId),
            'nome'             => $tribuna['nome'],
            'partido'          => $tribuna['partido'],
            'status'           => $tribuna['status'],
            'tempo_calculado'  => $tempoRestante,
            'tempo_inicial'    => (int)$tribuna['tempo_inicial_segundos'],
        ];
    }

    return [
        'sessao'         => $sessao,
        'votacao_aberta' => $votacao !== null,
        'ordem_dia_id'   => $votacao['id'] ?? null,
        'item_atual'     => $itemAtual,
        'meu_voto'       => $meuVoto,
        'ordem_dia'      => $ordemDia,
        'tribuna'        => $tribunaPayload,
        'ts'             => time(),
    ];
}

$lastHash = '';
sendEvent('connected', ['vereador_id' => $vereadorId]);

while (true) {
    if (connection_aborted()) break;

    try {
        $state = getVereadorState($vereadorId);
        $hash  = md5(json_encode($state));

        if ($hash !== $lastHash) {
            sendEvent('vereador_update', $state);
            $lastHash = $hash;
        } else {
            echo ": heartbeat " . time() . "\n\n";
            if (ob_get_level() > 0) ob_flush();
            flush();
        }
    } catch (Throwable $e) {
        sendEvent('error', ['message' => 'Erro interno.']);
    }

    sleep(1);
}
