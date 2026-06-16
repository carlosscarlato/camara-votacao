<?php
declare(strict_types=1);
namespace App\Services;

class AIReportService
{
    private string $model = 'claude-sonnet-4-6';

    public function __construct(private string $apiKey) {}

    public function generateSQL(string $question, int $tenantId, string $dbSchema): array
    {
        $systemPrompt = <<<PROMPT
Você é um especialista em SQL para MySQL 8.0. Converta a pergunta do usuário em uma query SQL válida.

REGRAS OBRIGATÓRIAS:
1. Retorne APENAS SELECT. Nunca DROP, DELETE, UPDATE, INSERT, TRUNCATE, ALTER, CREATE.
2. Toda query DEVE incluir a cláusula: WHERE <tabela_principal>.tenant_id = {$tenantId}
3. Use apenas as tabelas e colunas do schema fornecido.
4. Limite sempre com LIMIT 1000 no máximo.
5. Retorne JSON com dois campos: "sql" (string) e "explanation" (string em português).
6. Não inclua comentários SQL nem ponto-e-vírgula no final.
7. Se a pergunta não puder ser respondida com SELECT seguro, retorne {"sql": null, "explanation": "motivo"}.

SCHEMA DO BANCO:
{$dbSchema}
PROMPT;

        $payload = [
            'model'      => $this->model,
            'max_tokens' => 1024,
            'system'     => $systemPrompt,
            'messages'   => [['role' => 'user', 'content' => $question]],
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
            return ['sql' => null, 'explanation' => 'Erro na API de IA.', 'error' => "HTTP $httpCode"];
        }

        $data = json_decode($response, true);
        $text = $data['content'][0]['text'] ?? '';

        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $text, $m)) {
            $text = $m[1];
        }

        $parsed = json_decode(trim($text), true);
        if (!is_array($parsed) || !isset($parsed['sql'])) {
            return ['sql' => null, 'explanation' => 'Resposta inesperada da IA.', 'error' => $text];
        }

        return [
            'sql'         => $parsed['sql'],
            'explanation' => $parsed['explanation'] ?? '',
            'error'       => null,
        ];
    }

    public function validateSQL(?string $sql, int $tenantId): bool
    {
        if (empty($sql)) return false;
        if (!preg_match('/^\s*SELECT\s/i', $sql)) return false;

        $dangerous = ['DROP','DELETE','UPDATE','INSERT','TRUNCATE','ALTER','CREATE','EXEC','EXECUTE','UNION'];
        foreach ($dangerous as $keyword) {
            if (preg_match('/\b' . $keyword . '\b/i', $sql)) return false;
        }

        if (!str_contains($sql, "tenant_id = $tenantId")) return false;
        if (substr_count($sql, ';') > 0) return false;

        return true;
    }

    public static function buildSchema(\PDO $db): string
    {
        $tables = $db->query("SHOW TABLES")->fetchAll(\PDO::FETCH_COLUMN);
        $schema = '';
        foreach ($tables as $table) {
            $cols    = $db->query("DESCRIBE `$table`")->fetchAll();
            $colDefs = array_map(fn($c) => "  {$c['Field']} {$c['Type']}", $cols);
            $schema .= "TABLE $table (\n" . implode(",\n", $colDefs) . "\n)\n\n";
        }
        return $schema;
    }
}
