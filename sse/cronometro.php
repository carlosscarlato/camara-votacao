<?php
declare(strict_types=1);

// SSE: Cronômetro da Tribuna
// Emite o tempo restante a cada segundo com alta precisão

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

function sendEvent(string $event, mixed $data): void
{
    echo "event: $event\n";
    echo "data: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
    if (ob_get_level() > 0) ob_flush();
    flush();
}

sendEvent('connected', ['message' => 'Cronômetro SSE conectado.']);

$lastStatus = '';

while (true) {
    if (connection_aborted()) break;

    try {
        $tribuna = getTribunaAtiva();

        if (!$tribuna) {
            $payload = [
                'ativa'           => false,
                'status'          => 'sem_tribuna',
                'tempo_calculado' => 0,
            ];
        } else {
            $restante = calcularTempoRestante($tribuna);

            // Se o tempo zerou, encerra automaticamente
            if ($restante <= 0 && $tribuna['status'] === 'falando') {
                db()->prepare("
                    UPDATE controle_tribuna
                    SET    status = 'encerrado', tempo_restante = 0, encerrado_em = NOW()
                    WHERE  id = ?
                ")->execute([$tribuna['id']]);
                $tribuna['status'] = 'encerrado';
                $restante = 0;
            }

            $payload = [
                'ativa'            => true,
                'id'               => $tribuna['id'],
                'vereador_id'      => $tribuna['vereador_id'],
                'nome'             => $tribuna['nome'],
                'partido'          => $tribuna['partido'],
                'foto'             => $tribuna['foto'],
                'status'           => $tribuna['status'],
                'tempo_inicial'    => $tribuna['tempo_inicial_segundos'],
                'tempo_calculado'  => $restante,
            ];
        }

        // Sempre emite — cronômetro precisa de atualização a cada segundo
        sendEvent('cronometro_update', $payload);

    } catch (Throwable $e) {
        sendEvent('error', ['message' => 'Erro interno no cronômetro.']);
    }

    sleep(1);
}
