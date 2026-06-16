<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/../../config/bootstrap.php';

if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
}

header('Content-Type: application/json; charset=utf-8');
setCorsHeaders();

function authenticateToken(): array
{
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!str_starts_with($auth, 'Bearer ')) {
        http_response_code(401);
        die(json_encode(['success' => false, 'error' => 'Token não fornecido.']));
    }
    $token = hash('sha256', substr($auth, 7));
    $stmt  = db()->prepare("
        SELECT at.*, t.id AS tenant_id, t.status AS tenant_status
        FROM   api_tokens at JOIN tenants t ON t.id = at.tenant_id
        WHERE  at.token = ? AND at.revoked = 0
    ");
    $stmt->execute([$token]);
    $row = $stmt->fetch();

    if (!$row || $row['tenant_status'] !== 'active') {
        http_response_code(401);
        die(json_encode(['success' => false, 'error' => 'Token inválido.']));
    }

    // Rate limit 100 req/hora
    $cnt = db()->prepare("
        SELECT COUNT(*) FROM audit_log
        WHERE  tenant_id = ? AND action = 'api_request'
          AND  created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $cnt->execute([$row['tenant_id']]);
    if ((int)$cnt->fetchColumn() >= 100) {
        http_response_code(429);
        die(json_encode(['success' => false, 'error' => 'Rate limit: máx 100 req/hora.']));
    }

    if (!defined('TENANT_ID'))   define('TENANT_ID',   (int)$row['tenant_id']);
    if (!defined('TENANT_SLUG')) define('TENANT_SLUG', '');
    if (!defined('TENANT_PLAN')) define('TENANT_PLAN', '');

    db()->prepare("UPDATE api_tokens SET last_used = NOW() WHERE id = ?")->execute([$row['id']]);

    $ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '')[0]);
    db()->prepare("INSERT INTO audit_log (tenant_id, user_id, entity, action, ip) VALUES (?,?,?,?,?)")
        ->execute([$row['tenant_id'], (int)$row['user_id'], 'api', 'api_request', $ip]);

    return $row;
}

function apiSuccess(array $data, int $total = 0): never
{
    echo json_encode([
        'success' => true,
        'data'    => $data,
        'meta'    => ['total' => $total ?: count($data), 'page' => 1],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function apiError(string $msg, int $code = 400): never
{
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

$tkn    = authenticateToken();
$tid    = (int)$tkn['tenant_id'];
$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$parts  = array_values(array_filter(explode('/', preg_replace('#.*/api/v1#', '', $uri))));
$res    = $parts[0] ?? '';
$id     = isset($parts[1]) && is_numeric($parts[1]) ? (int)$parts[1] : null;
$sub    = $parts[2] ?? '';

match (true) {

    $res === 'sessions' && $method === 'GET' && $id === null
        => (function () use ($tid) {
            $s = db()->prepare("SELECT * FROM sessoes_plenarias WHERE tenant_id = ? ORDER BY data DESC LIMIT 100");
            $s->execute([$tid]);
            apiSuccess($s->fetchAll());
        })(),

    $res === 'sessions' && $id !== null && $sub === 'results' && $method === 'GET'
        => (function () use ($tid, $id) {
            $s = db()->prepare("
                SELECT od.*, p.numero, p.tipo, p.ementa
                FROM   ordem_do_dia od
                JOIN   proposicoes p ON p.id = od.proposicao_id
                WHERE  od.sessao_id = ? AND od.resultado IS NOT NULL
                  AND  EXISTS (SELECT 1 FROM sessoes_plenarias sp WHERE sp.id = od.sessao_id AND sp.tenant_id = ?)
            ");
            $s->execute([$id, $tid]);
            apiSuccess($s->fetchAll());
        })(),

    $res === 'vereadores' && $method === 'GET'
        => (function () use ($tid) {
            $s = db()->prepare("SELECT id, nome, partido, status FROM vereadores WHERE tenant_id = ? ORDER BY nome");
            $s->execute([$tid]);
            apiSuccess($s->fetchAll());
        })(),

    $res === 'reports' && $method === 'GET'
        => (function () use ($tid) {
            $tipo = $_GET['tipo'] ?? 'sessoes';
            $c    = new \App\Controllers\ReportController(db(), $tid);
            try {
                $r = $c->query(['tipo' => $tipo]);
                apiSuccess($r['dados']);
            } catch (\InvalidArgumentException $e) {
                apiError($e->getMessage());
            }
        })(),

    default => apiError("Endpoint não encontrado: $res", 404),
};
