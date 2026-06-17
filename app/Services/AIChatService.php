<?php
declare(strict_types=1);
namespace App\Services;

use PDO;

class AIChatService
{
    private string $model = 'claude-sonnet-4-6';
    private int $maxMessagesPerHour = 20;
    private int $maxHistoryMessages = 20;

    public function __construct(
        private string $apiKey,
        private PDO    $db,
        private int    $tenantId,
        private int    $userId
    ) {}

    public function chat(string $userMessage): array
    {
        if (strlen(trim($userMessage)) < 2) {
            return ['success' => false, 'error' => 'Mensagem muito curta.'];
        }
        if (strlen($userMessage) > 1000) {
            return ['success' => false, 'error' => 'Mensagem muito longa (máx 1000 caracteres).'];
        }
        if (!$this->checkRateLimit()) {
            return ['success' => false, 'error' => 'Limite de 20 mensagens por hora atingido.'];
        }

        if (!isset($_SESSION['ai_chat_history'])) {
            $_SESSION['ai_chat_history'] = [];
        }

        $kpis     = $this->getKpis();
        $tenant   = $this->getTenantName();
        $system   = $this->buildSystemPrompt($tenant, $kpis);
        $history  = array_slice($_SESSION['ai_chat_history'], -$this->maxHistoryMessages);
        $messages = array_merge($history, [['role' => 'user', 'content' => $userMessage]]);

        $payload = [
            'model'      => $this->model,
            'max_tokens' => 1024,
            'system'     => $system,
            'messages'   => $messages,
        ];

        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            return ['success' => false, 'error' => "Erro na API da IA (HTTP $httpCode)."];
        }

        $data  = json_decode($response, true);
        $reply = $data['content'][0]['text'] ?? '';

        $_SESSION['ai_chat_history'][] = ['role' => 'user',      'content' => $userMessage];
        $_SESSION['ai_chat_history'][] = ['role' => 'assistant', 'content' => $reply];

        $this->saveLog($userMessage, $reply);
        return ['success' => true, 'response' => $reply];
    }

    public function clearHistory(): void
    {
        $_SESSION['ai_chat_history'] = [];
    }

    public function getSessionHistory(): array
    {
        return $_SESSION['ai_chat_history'] ?? [];
    }

    private function buildSystemPrompt(string $tenantName, array $kpis): string
    {
        $abertas    = $kpis['sessoes_abertas']  ?? 0;
        $total      = $kpis['total_sessoes']    ?? 0;
        $vereadores = $kpis['total_vereadores'] ?? 0;
        $votos      = $kpis['total_votos']      ?? 0;
        $ultima     = $kpis['ultima_sessao']    ?? 'nenhuma';

        return <<<SYSTEM
Você é um assistente estratégico do sistema WebVoto para o cliente {$tenantName}.

Você tem acesso somente-leitura aos seguintes dados resumidos deste cliente:

- Total de sessões plenárias: {$total} (sendo {$abertas} em andamento agora)
- Total de vereadores ativos: {$vereadores}
- Total de votos computados: {$votos}
- Última sessão encerrada: {$ultima}

Responda sempre em português, de forma objetiva e estratégica.
Nunca invente dados — use apenas os fornecidos acima ou diga que não tem a informação.
Você pode formatar respostas com Markdown (tabelas, listas, negrito).
Nunca execute ações, apenas analise e responda.
SYSTEM;
    }

    private function getKpis(): array
    {
        try {
            $tid = $this->tenantId;
            $row = $this->db->prepare("
                SELECT
                  (SELECT COUNT(*) FROM sessoes_plenarias WHERE tenant_id = ?)                                AS total_sessoes,
                  (SELECT COUNT(*) FROM sessoes_plenarias WHERE tenant_id = ? AND status='em_andamento')     AS sessoes_abertas,
                  (SELECT COUNT(*) FROM vereadores        WHERE tenant_id = ? AND status='ativo')            AS total_vereadores,
                  (SELECT COUNT(*) FROM votos v JOIN ordem_do_dia od ON od.id = v.ordem_dia_id
                   JOIN sessoes_plenarias s ON s.id = od.sessao_id WHERE s.tenant_id = ?)                   AS total_votos,
                  (SELECT numero FROM sessoes_plenarias WHERE tenant_id = ? AND status='encerrada'
                   ORDER BY id DESC LIMIT 1)                                                                  AS ultima_sessao
            ");
            $row->execute([$tid, $tid, $tid, $tid, $tid]);
            return $row->fetch() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function getTenantName(): string
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT COALESCE(ts.company_name, t.name) FROM tenants t
                 LEFT JOIN tenant_settings ts ON ts.tenant_id = t.id WHERE t.id = ?"
            );
            $stmt->execute([$this->tenantId]);
            return (string)($stmt->fetchColumn() ?: 'WebVoto');
        } catch (\Throwable) {
            return 'WebVoto';
        }
    }

    private function checkRateLimit(): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM ai_chat_logs
            WHERE tenant_id = ? AND user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute([$this->tenantId, $this->userId]);
        return (int)$stmt->fetchColumn() < $this->maxMessagesPerHour;
    }

    private function saveLog(string $message, string $response): void
    {
        try {
            $this->db->prepare("INSERT INTO ai_chat_logs (tenant_id, user_id, message, response) VALUES (?,?,?,?)")
                ->execute([$this->tenantId, $this->userId, $message, $response]);
        } catch (\Throwable) {}
    }
}
