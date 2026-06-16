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
    http_response_code(503);
    echo json_encode(['success' => false, 'disabled' => true, 'error' => 'Módulo de IA não configurado.']);
    exit;
}

$action    = getAction();
$tid       = tenantId();
$aiService = new \App\Services\AIReportService($apiKey);

switch ($action) {

    case 'gerar':
        $question = trim((string)requiredInput('question'));
        if (strlen($question) < 5)   jsonError('Pergunta muito curta.');
        if (strlen($question) > 500) jsonError('Pergunta muito longa (máx 500 caracteres).');

        // Cache de schema na sessão por 10 min
        if (empty($_SESSION['ai_schema_cache']) || (time() - ($_SESSION['ai_schema_time'] ?? 0)) > 600) {
            $_SESSION['ai_schema_cache'] = \App\Services\AIReportService::buildSchema(db());
            $_SESSION['ai_schema_time']  = time();
        }

        $result = $aiService->generateSQL($question, $tid, $_SESSION['ai_schema_cache']);

        // Registra no histórico
        $status = $result['sql'] ? 'success' : 'error';
        db()->prepare("
            INSERT INTO ai_report_history (tenant_id, usuario_id, question, sql_generated, status, error_msg)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([$tid, (int)$_SESSION['id'], $question, $result['sql'], $status, $result['error']]);
        $histId = (int)db()->lastInsertId();

        if (!$result['sql']) {
            jsonError('Não foi possível gerar uma query: ' . ($result['explanation'] ?? ''));
        }

        if (!$aiService->validateSQL($result['sql'], $tid)) {
            db()->prepare("UPDATE ai_report_history SET status='blocked' WHERE id=?")->execute([$histId]);
            jsonError('A query gerada não passou na validação de segurança.');
        }

        // Executa com usuário read-only
        try {
            $roDsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
            $roPdo = new PDO($roDsn, 'webvoto_readonly', 'R3adOnly!2024', [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $stmt = $roPdo->prepare($result['sql']);
            $stmt->execute();
            $rows = $stmt->fetchAll();
            $cols = $rows ? array_keys($rows[0]) : [];
        } catch (\PDOException $e) {
            jsonError('Erro ao executar query: ' . $e->getMessage());
        }

        // Atualiza row_count
        db()->prepare("UPDATE ai_report_history SET row_count = ? WHERE id = ?")
            ->execute([count($rows), $histId]);

        jsonSuccess([
            'sql'         => $result['sql'],
            'explanation' => $result['explanation'],
            'colunas'     => $cols,
            'dados'       => $rows,
            'total'       => count($rows),
        ]);

    case 'historico':
        $stmt = db()->prepare("
            SELECT h.*, u.nome AS usuario_nome
            FROM   ai_report_history h
            JOIN   usuarios u ON u.id = h.usuario_id
            WHERE  h.tenant_id = ?
            ORDER  BY h.id DESC LIMIT 50
        ");
        $stmt->execute([$tid]);
        jsonSuccess($stmt->fetchAll());

    default:
        jsonError('Ação inválida.');
}
