<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';

// ── Sessão PHP ────────────────────────────────────────────
function startSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 28800,
            'path'     => '/',
            'secure'   => isProduction(), // true automaticamente em HTTPS
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

// ── Respostas JSON ─────────────────────────────────────────
function jsonSuccess(mixed $data = [], int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function jsonError(string $message, int $code = 400): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── CORS ───────────────────────────────────────────────────
function setCorsHeaders(): void
{
    $origin = defined('APP_DOMAIN') ? APP_DOMAIN : 'http://localhost';
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
    header('Access-Control-Allow-Credentials: true');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

// ── Rate limiting (brute force) ────────────────────────────
function checkRateLimit(string $chave, int $maxTentativas = 5, int $janelaSegundos = 300): void
{
    $ip   = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $stmt = db()->prepare("
        SELECT COUNT(*) FROM logs_sistema
        WHERE acao = ?
          AND ip_origem = ?
          AND created_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)
    ");
    $stmt->execute([$chave, $ip, $janelaSegundos]);
    if ((int)$stmt->fetchColumn() >= $maxTentativas) {
        http_response_code(429);
        header('Retry-After: ' . $janelaSegundos);
        die(json_encode([
            'success' => false,
            'error'   => 'Muitas tentativas. Aguarde ' . round($janelaSegundos / 60) . ' minuto(s).',
        ], JSON_UNESCAPED_UNICODE));
    }
}

// ── Detecta se está em produção (HTTPS) ────────────────────
function isProduction(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (int)($_SERVER['SERVER_PORT'] ?? 80) === 443;
}

// ── Autenticação ───────────────────────────────────────────
function requireVereadorAuth(): array
{
    startSession();
    if (empty($_SESSION['tipo']) || $_SESSION['tipo'] !== 'vereador') {
        jsonError('Autenticação necessária.', 401);
    }
    return [
        'id'   => (int)$_SESSION['id'],
        'nome' => $_SESSION['nome'],
    ];
}

function requireAdminAuth(): array
{
    startSession();
    if (empty($_SESSION['tipo']) || !in_array($_SESSION['tipo'], ['admin', 'operador'])) {
        jsonError('Acesso restrito.', 401);
    }
    return [
        'id'     => (int)$_SESSION['id'],
        'nome'   => $_SESSION['nome'],
        'perfil' => $_SESSION['tipo'],
    ];
}

function getAuthInfo(): array
{
    startSession();
    return [
        'autenticado' => !empty($_SESSION['tipo']),
        'tipo'        => $_SESSION['tipo'] ?? null,
        'id'          => (int)($_SESSION['id'] ?? 0),
        'nome'        => $_SESSION['nome'] ?? null,
    ];
}

// ── Input ──────────────────────────────────────────────────
function getAction(): string
{
    return trim($_GET['action'] ?? $_POST['action'] ?? '');
}

function getJson(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function input(string $key, mixed $default = null): mixed
{
    $body = getJson();
    if (array_key_exists($key, $body)) return $body[$key];
    if (isset($_POST[$key]))           return $_POST[$key];
    if (isset($_GET[$key]))            return $_GET[$key];
    return $default;
}

function requiredInput(string $key): mixed
{
    $val = input($key);
    if ($val === null || $val === '') {
        jsonError("Campo '$key' é obrigatório.");
    }
    return $val;
}

// ── Sessão plenária ativa ──────────────────────────────────
function getSessaoAtiva(): ?array
{
    $stmt = db()->prepare(
        "SELECT * FROM sessoes_plenarias WHERE status = 'em_andamento' ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute();
    return $stmt->fetch() ?: null;
}

// ── Votação ativa ──────────────────────────────────────────
function getVotacaoAtiva(): ?array
{
    $stmt = db()->prepare("
        SELECT od.*, p.numero, p.ano, p.tipo, p.ementa, p.link_documento,
               p.pareceres, p.emendas, p.autor
        FROM   ordem_do_dia od
        JOIN   proposicoes   p  ON p.id = od.proposicao_id
        JOIN   sessoes_plenarias s ON s.id = od.sessao_id
        WHERE  od.status_votacao = 'votando'
          AND  s.status = 'em_andamento'
        LIMIT  1
    ");
    $stmt->execute();
    return $stmt->fetch() ?: null;
}

// ── Discussão ativa ────────────────────────────────────────
function getDiscussaoAtiva(): ?array
{
    $stmt = db()->prepare("
        SELECT od.*, p.numero, p.ano, p.tipo, p.ementa, p.autor
        FROM   ordem_do_dia od
        JOIN   proposicoes   p  ON p.id = od.proposicao_id
        JOIN   sessoes_plenarias s ON s.id = od.sessao_id
        WHERE  od.status_votacao = 'em_discussao'
          AND  s.status = 'em_andamento'
        LIMIT  1
    ");
    $stmt->execute();
    return $stmt->fetch() ?: null;
}

// ── Tribuna ativa ──────────────────────────────────────────
function getTribunaAtiva(): ?array
{
    $stmt = db()->prepare("
        SELECT ct.*, v.nome, v.partido, v.foto
        FROM   controle_tribuna ct
        JOIN   vereadores v ON v.id = ct.vereador_id
        JOIN   sessoes_plenarias s ON s.id = ct.sessao_id
        WHERE  ct.status IN ('aguardando','falando','pausado')
          AND  s.status = 'em_andamento'
        ORDER  BY ct.id DESC
        LIMIT  1
    ");
    $stmt->execute();
    return $stmt->fetch() ?: null;
}

// ── Cálculo de tempo restante ──────────────────────────────
function calcularTempoRestante(array $tribuna): int
{
    if ($tribuna['status'] === 'encerrado') return 0;
    if ($tribuna['status'] === 'pausado')   return (int)$tribuna['tempo_restante'];
    if ($tribuna['status'] === 'aguardando') return (int)$tribuna['tempo_restante'];

    // falando: descontar o tempo decorrido desde iniciado_em
    $iniciado = strtotime($tribuna['iniciado_em']);
    $decorrido = time() - $iniciado;
    $restante  = (int)$tribuna['tempo_restante'] - $decorrido;
    return max(0, $restante);
}
