<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';

/**
 * Registra ação no log de auditoria (logs_sistema).
 * Falhas silenciosas — nunca derruba a aplicação.
 */
function registrarLog(
    string  $acao,
    ?int    $usuarioId  = null,
    ?int    $vereadorId = null,
    ?string $detalhes   = null
): void {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['REMOTE_ADDR']
        ?? 'unknown';

    // Pega só o primeiro IP em caso de proxy chain
    $ip = trim(explode(',', $ip)[0]);
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

    try {
        db()->prepare("
            INSERT INTO logs_sistema
                   (usuario_id, vereador_id, acao, detalhes, ip_origem, user_agent)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([$usuarioId, $vereadorId, $acao, $detalhes, $ip, $ua]);
    } catch (\Throwable) {
        // intencional: log nunca pode derrubar a aplicação
    }
}
