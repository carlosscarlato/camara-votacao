<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/bootstrap.php';

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

setCorsHeaders();
startSession();
resolveTenant();
requireAdminAuth();

$apiKey = defined('ANTHROPIC_API_KEY') ? ANTHROPIC_API_KEY : (getenv('ANTHROPIC_API_KEY') ?: '');
if (!$apiKey) {
    jsonError('API key da IA não configurada.', 503);
}

$action  = getAction();
$tid     = tenantId();
$auth    = getAuthInfo();
$service = new \App\Services\AIChatService($apiKey, db(), $tid, (int)$auth['id']);

switch ($action) {

    case 'enviar':
        $message = trim((string)requiredInput('message'));
        $result  = $service->chat($message);
        if (!$result['success']) jsonError($result['error']);
        jsonSuccess(['response' => $result['response']]);

    case 'historico':
        jsonSuccess($service->getSessionHistory());

    case 'limpar':
        $service->clearHistory();
        jsonSuccess(['message' => 'Histórico da sessão limpo.']);

    case 'exportar':
        $history = $service->getSessionHistory();
        $txt     = "=== Conversa com IA — WebVoto ===\nExportado em: " . date('d/m/Y H:i') . "\n\n";
        foreach ($history as $msg) {
            $role = $msg['role'] === 'user' ? 'Você' : 'IA';
            $txt .= "[$role]\n{$msg['content']}\n\n";
        }
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="conversa-ia.txt"');
        echo $txt;
        exit;

    default:
        jsonError('Ação inválida.');
}
