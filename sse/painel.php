<?php
declare(strict_types=1);

// SSE: Painel do Plenário (TV/Projetor)
// Mantém a conexão aberta e emite o estado completo a cada 1 segundo

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

function sendEvent(string $event, mixed $data): void
{
    echo "event: $event\n";
    echo "data: " . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
    if (ob_get_level() > 0) ob_flush();
    flush();
}

function getPainelState(): array
{
    // Sessão ativa
    $sessao = getSessaoAtiva();

    // Votação ativa
    $votacao = getVotacaoAtiva();
    $dadosVotacao = null;

    if ($votacao) {
        $stmtVotos = db()->prepare("
            SELECT ver.id AS vereador_id, ver.nome, ver.partido, ver.foto,
                   v.voto, v.timestamp
            FROM   vereadores ver
            LEFT JOIN votos v
                   ON v.vereador_id = ver.id AND v.ordem_dia_id = ?
            WHERE  ver.status = 'ativo'
            ORDER  BY ver.nome ASC
        ");
        $stmtVotos->execute([$votacao['id']]);
        $votos = $stmtVotos->fetchAll();

        $placar = ['sim' => 0, 'nao' => 0, 'abstencao' => 0, 'pendente' => 0];
        foreach ($votos as $v) {
            match ($v['voto']) {
                'SIM'       => $placar['sim']++,
                'NAO'       => $placar['nao']++,
                'ABSTENCAO' => $placar['abstencao']++,
                default     => $placar['pendente']++,
            };
        }

        if ($votacao['emendas']) {
            $votacao['emendas'] = json_decode($votacao['emendas'], true);
        }

        $dadosVotacao = [
            'ativa'  => true,
            'item'   => $votacao,
            'votos'  => $votos,
            'placar' => $placar,
        ];
    }

    // Discussão ativa
    $discussao = getDiscussaoAtiva();
    $dadosDiscussao = $discussao
        ? ['ativa' => true, 'item' => $discussao]
        : ['ativa' => false];

    // Tribuna ativa
    $tribuna = getTribunaAtiva();
    $dadosTribuna = null;

    if ($tribuna) {
        $dadosTribuna = [
            'ativa'           => true,
            'id'              => $tribuna['id'],
            'vereador_id'     => $tribuna['vereador_id'],
            'nome'            => $tribuna['nome'],
            'partido'         => $tribuna['partido'],
            'foto'            => $tribuna['foto'],
            'status'          => $tribuna['status'],
            'tempo_inicial'   => $tribuna['tempo_inicial_segundos'],
            'tempo_calculado' => calcularTempoRestante($tribuna),
        ];
    }

    return [
        'sessao'    => $sessao,
        'votacao'   => $dadosVotacao ?? ['ativa' => false],
        'discussao' => $dadosDiscussao,
        'tribuna'   => $dadosTribuna ?? ['ativa' => false],
        'ts'        => time(),
    ];
}

$lastHash = '';

// Heartbeat imediato ao conectar
sendEvent('connected', ['message' => 'Painel SSE conectado.']);

while (true) {
    if (connection_aborted()) break;

    try {
        $state     = getPainelState();
        $stateHash = $state; unset($stateHash['ts']);
        $hash      = md5(json_encode($stateHash));

        if ($hash !== $lastHash) {
            sendEvent('painel_update', $state);
            $lastHash = $hash;
        } else {
            // Heartbeat para manter a conexão viva (comentário SSE)
            echo ": heartbeat " . time() . "\n\n";
            if (ob_get_level() > 0) ob_flush();
            flush();
        }
    } catch (Throwable $e) {
        sendEvent('error', ['message' => 'Erro interno no servidor.']);
    }

    sleep(1);
}
