# Manual Técnico — Sistema de Votação Eletrônica
**WebVoto SaaS | Versão 3.0 | Atualizado: 2026-06-17**

---

## Stack Tecnológico

| Componente | Tecnologia |
|------------|-----------|
| Backend | PHP 8.2 (sem framework) |
| Banco de dados | MySQL 8.0+ / MariaDB 10.6+ |
| Comunicação em tempo real | Server-Sent Events (SSE) |
| Frontend | HTML5 + Tailwind CSS (CDN) |
| Gráficos | Chart.js 4.4 (CDN) |
| Servidor web | Apache 2.4 |
| PDF | Dompdf 2.x (via Composer) |
| Excel/CSV | PhpSpreadsheet 3.x (via Composer) |
| IA | Anthropic Claude (via `ANTHROPIC_API_KEY`) |
| Autoload | PSR-4 via Composer (`App\` → `app/`) |

---

## Instalação Local (XAMPP)

### Pré-requisitos
- XAMPP com Apache 2.4 + PHP 8.2
- MySQL 8.0+ (pode ser standalone, não precisa ser o MariaDB do XAMPP)
- Git + SSH key configurada para GitHub
- Composer (para PDF/Excel em produção)

### Passos

```bash
# 1. Clonar em htdocs (ou criar junction para o repo)
git clone git@github.com:carlosscarlato/camara-votacao.git C:\xampp\htdocs\camara-votacao

# 2. Copiar e preencher credenciais locais
cp config/database.example.php config/database.php
# Editar database.php: DB_USER, DB_PASS, APP_DOMAIN

# 3. Criar banco e rodar schema base
mysql -u root -pSUASENHA -e "CREATE DATABASE camara_votacao CHARSET utf8mb4"
mysql -u root -pSUASENHA camara_votacao < database.sql

# 4. Rodar migrations em ordem
mysql -u root -pSUASENHA camara_votacao < migrations/001_multi_tenant.sql
mysql -u root -pSUASENHA camara_votacao < migrations/002_reports.sql
mysql -u root -pSUASENHA camara_votacao < migrations/003_ai_chat.sql
mysql -u root -pSUASENHA camara_votacao < migrations/004_complementar.sql

# 5. Instalar dependências Composer (opcional — PDF/Excel)
composer install
```

> `config/database.php` está no `.gitignore` — nunca commitar.

---

## Estrutura de Arquivos

```
camara-votacao/
├── api/
│   ├── auth.php                  # Login, logout, recuperação de senha
│   ├── admin.php                 # CRUD usuários, vereadores, logs (requer admin)
│   ├── analytics.php             # KPIs e gráficos para dashboard
│   ├── ata.php                   # Geração de ata HTML/PDF
│   ├── ordem_dia.php             # Pauta da sessão
│   ├── sessoes.php               # CRUD sessões plenárias
│   ├── tribuna.php               # Controle da tribuna
│   ├── votacao.php               # Registro de votos
│   ├── resultados_publicos.php   # Histórico público (sem auth)
│   ├── tenants.php               # CRUD tenants (super admin)
│   ├── white-label.php           # Configurações visuais do tenant
│   ├── reports.php               # Exportação: PDF/Excel/CSV/JSON
│   ├── ai-reports.php            # Relatórios por linguagem natural (IA)
│   ├── ai-chat.php               # Chat com IA
│   └── v1/index.php              # REST API pública (Bearer token)
├── app/
│   ├── Controllers/ReportController.php
│   ├── Middleware/TenantMiddleware.php
│   └── Services/
│       ├── AIChatService.php
│       ├── AIReportService.php
│       └── ReportExportService.php
├── config/
│   ├── database.php              # ⚠ NÃO commitar (gitignore)
│   ├── database.example.php      # Modelo para novos devs
│   ├── helpers.php               # Auth, JSON, input, rate limit
│   ├── logger.php                # registrarLog() → logs_sistema
│   └── bootstrap.php             # resolveTenant() por HTTP_HOST
├── sse/
│   ├── painel.php                # SSE: estado completo do plenário (2s)
│   ├── vereador.php              # SSE: estado por vereador (?vereador_id=)
│   └── cronometro.php            # SSE: cronômetro da tribuna
├── public/
│   ├── admin/
│   │   ├── index.html            # Dashboard (Chart.js)
│   │   ├── dashboard.html        # Dashboard renovado v3.0
│   │   ├── usuarios.html         # RBAC: usuários e vereadores
│   │   ├── relatorios.html       # Exportação de relatórios
│   │   ├── relatorios-ia.html    # Relatórios com IA
│   │   └── white-label.html      # Personalização do tenant
│   ├── assets/js/
│   │   ├── ai-chat-widget.js     # Widget de chat flutuante
│   │   └── powered-by.js         # Footer "Powered by WebVoto"
│   └── css/tenant.php            # CSS dinâmico com cores do tenant
├── migrations/
│   ├── 001_multi_tenant.sql
│   ├── 002_reports.sql
│   ├── 003_ai_chat.sql
│   └── 004_complementar.sql
├── .github/workflows/
│   ├── deploy.yml                # Deploy automático em push no main
│   └── setup-vps.yml             # Configuração inicial da VPS
├── composer.json                 # dompdf + phpspreadsheet
├── database.sql                  # Schema base v1.0
├── update.sql                    # Migração v2.0
└── run_migrations.php            # Utilitário local para migrations
```

---

## Banco de Dados

### Diagrama geral

```
tenants ──────────────────────────────────────────────┐
  id, name, slug, plan, status                        │ (tenant_id em todas)
       │                                              │
  tenant_settings    tenant_domains    tenant_users   │
  (cores, smtp...)   (domínios)        (roles)        │
                                                      │
usuarios ──────────────── sessoes_plenarias ◄──────────┤
  id, tenant_id,              id, tenant_id,           │
  nome, login, email,         numero, data, tipo,      │
  senha_hash, perfil          status, usuario_id       │
                                    │                  │
vereadores                    ordem_do_dia ◄───────────┤
  id, tenant_id,                id, tenant_id,         │
  nome, partido, pin,           sessao_id,             │
  email, status                 proposicao_id,         │
                                status_votacao         │
votos                               │                  │
  id, tenant_id,             proposicoes ◄─────────────┤
  vereador_id,                 id, tenant_id,          │
  ordem_dia_id,                numero, tipo, ementa    │
  voto, vote_hash                                      │
                                                       │
logs_sistema          audit_log                        │
  (auditoria leve)    (auditoria completa before/after)│
                                                       │
api_tokens   plans   billing   scheduled_reports ◄─────┘
ai_chat_logs   ai_report_history
notification_logs   notification_templates
```

### Migrations

| Arquivo | O que faz |
|---------|-----------|
| `database.sql` | Schema base v1.0 — 9 tabelas |
| `update.sql` | Migração v2.0 — campos email, token, logs_sistema |
| `001_multi_tenant.sql` | Cria tabelas de tenant + adiciona `tenant_id` em todas as tabelas existentes |
| `002_reports.sql` | `scheduled_reports`, `ai_report_history` |
| `003_ai_chat.sql` | `ai_chat_logs` |
| `004_complementar.sql` | `notification_logs`, `notification_templates`, `audit_log`, `plans`, `billing`, `api_tokens`, `vote_hash` em votos |

---

## Multi-Tenant — Como Funciona

O tenant é resolvido automaticamente pelo domínio HTTP em `config/bootstrap.php`:

```
Requisição HTTP
  → HTTP_HOST = "webvoto.sazio.com.br"
  → bootstrap.php: SELECT tenant FROM tenant_domains WHERE domain = ?
  → define('TENANT_ID', 1)
  → Todos os SELECTs filtram por tenant_id = TENANT_ID
```

Em desenvolvimento local (`localhost`), o tenant 1 é assumido como fallback.

---

## APIs — Referência Completa

### `api/auth.php`

| Action | Método | Auth | Descrição |
|--------|--------|------|-----------|
| `login_admin` | POST | — | Login + remember-me 30 dias |
| `login_vereador` | POST | — | Login por PIN |
| `logout` | POST | — | Encerra sessão |
| `status` | GET | — | Dados da sessão atual |
| `lista_vereadores` | GET | — | Vereadores ativos do tenant |
| `esqueci_senha` | POST | — | Gera token de recuperação |
| `redefinir_senha` | POST | — | Redefine senha via token |
| `verificar_token` | POST | — | Valida token de recuperação |

### `api/admin.php`

| Action | Perfil |
|--------|--------|
| `listar_usuarios` / `criar_usuario` / `editar_usuario` / `resetar_senha` | admin |
| `listar_vereadores` / `criar_vereador` / `editar_vereador` / `deletar_vereador` | admin |
| `listar_logs` | admin |

### `api/analytics.php`

| Action | Retorno |
|--------|---------|
| `resumo` | KPIs: sessões, votações, aprovadas, vereadores |
| `votacoes_por_mes` | Array mês a mês: total, aprovadas, rejeitadas |
| `assiduidade` | Presença % por vereador |
| `aprovacao_partido` | SIM/NÃO/ABST por partido |
| `ultimas_sessoes` | Últimas 10 sessões |

### `api/reports.php`

```
GET /api/reports.php?action=exportar&tipo=sessoes&formato=pdf
GET /api/reports.php?action=exportar&tipo=votacoes&formato=excel
GET /api/reports.php?action=exportar&tipo=vereadores&formato=csv
GET /api/reports.php?action=exportar&tipo=proposicoes&formato=json
```

Tipos disponíveis: `sessoes`, `votacoes`, `vereadores`, `proposicoes`, `tribuna`, `logs`

### `api/ai-reports.php`

```
POST /api/ai-reports.php?action=perguntar
Body: { "pergunta": "Quais foram as votações mais polêmicas do último mês?" }
```

Requer `ANTHROPIC_API_KEY` no ambiente. Sem a chave, retorna erro informativo sem quebrar o sistema.

### `api/ai-chat.php`

```
POST /api/ai-chat.php?action=mensagem
Body: { "mensagem": "Como funciona o sistema de votação?" }
```

### `api/white-label.php`

| Action | Descrição |
|--------|-----------|
| `obter` | Retorna configurações visuais do tenant |
| `salvar` | Atualiza logo, cores, contato, SMTP, webhooks |

### `api/tenants.php` (super admin)

| Action | Descrição |
|--------|-----------|
| `listar` | Lista todos os tenants |
| `criar` | Cria novo tenant com slug e plano |
| `editar` | Atualiza nome, plano, status |

### REST API v1 — `/api/v1/`

Autenticação: `Authorization: Bearer <token>`
Rate limit: 100 req/hora por tenant

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/api/v1/sessions` | Lista sessões plenárias |
| GET | `/api/v1/sessions/{id}/results` | Resultados de uma sessão |
| GET | `/api/v1/vereadores` | Lista vereadores |
| GET | `/api/v1/reports?tipo=sessoes` | Dados de relatório |

---

## Server-Sent Events (SSE)

### `sse/painel.php`
- Evento: `painel_update`
- Payload: sessão ativa, votação corrente, placar, votos nominais, tribuna
- Intervalo: 2 segundos

### `sse/vereador.php`
- Evento: `vereador_update`
- Parâmetro: `?vereador_id=<ID>`
- Payload: sessão, ordem do dia, votação aberta, voto registrado

### `sse/cronometro.php`
- Evento: `cronometro_update`
- Payload: tribuna ativa, tempo calculado em tempo real

---

## Segurança

### Autenticação interna (sessão PHP)
- `session_regenerate_id()` no login
- Cookie `HttpOnly`, `SameSite=Strict`
- Remember-me: token SHA-256 64 bytes, 30 dias
- Recuperação: token 64 bytes, 2 horas, uso único

### API v1 (Bearer token)
```php
$token = hash('sha256', $bearerRaw);
// SELECT FROM api_tokens WHERE token = ? AND revoked = 0
```

### Senhas
```php
$hash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);
password_verify($senha, $hash);
```

### Auditoria
```php
// Log leve (auth e admin)
registrarLog('acao', $usuarioId, $vereadorId, 'detalhes');

// Log completo (API v1 e operações críticas) → audit_log
INSERT INTO audit_log (tenant_id, user_id, entity, action, before_val, after_val, ip)
```

### Em produção
- `'secure' => true` nos cookies (requer HTTPS)
- `APP_DOMAIN` definido no `database.php` para CORS restrito
- `ANTHROPIC_API_KEY` como variável de ambiente do servidor (nunca no código)

---

## Failover de Voto

O terminal do vereador salva a intenção antes de enviar:

```javascript
sessionStorage.setItem('voto_pendente', JSON.stringify({ ordem_dia_id, voto }));
```

Se falhar, tenta até 3 vezes com backoff exponencial (2s, 4s, 6s). Ao reconectar via SSE, `verificarVotoPendente()` é chamada automaticamente.

---

## CI/CD — Deploy Automático

```
git push origin main
       ↓
GitHub Actions (.github/workflows/deploy.yml)
       ↓
SSH na VPS
  git config --global --add safe.directory /var/www/camara-votacao
  git fetch origin main
  git reset --hard origin/main
  bash deploy.sh
```

**Secrets necessários no GitHub:**

| Secret | Descrição |
|--------|-----------|
| `VPS_HOST` | IP ou hostname da VPS |
| `VPS_USER` | Usuário SSH |
| `VPS_PASSWORD` | Senha SSH |
| `GH_PAT` | Personal Access Token (para clone inicial) |

**Primeira configuração da VPS:** executar `setup-vps.yml` manualmente em Actions → Run workflow.

---

## Composer — Dependências

```json
{
  "require": {
    "php": ">=8.2",
    "dompdf/dompdf": "^2.0",
    "phpoffice/phpspreadsheet": "^3.0"
  },
  "autoload": {
    "psr-4": { "App\\": "app/" }
  }
}
```

```bash
composer install          # instalar
composer install --no-dev # produção
```

Sem o `vendor/`, PDF e Excel desabilitam graciosamente — o sistema continua funcionando com JSON e CSV.

---

## Logs e Monitoramento

```sql
-- Auditoria leve (ações de auth e admin)
SELECT l.*, u.nome AS usuario
FROM logs_sistema l
LEFT JOIN usuarios u ON u.id = l.usuario_id
ORDER BY l.id DESC LIMIT 100;

-- Auditoria completa (API e operações críticas)
SELECT * FROM audit_log
WHERE tenant_id = 1
ORDER BY created_at DESC LIMIT 100;

-- Histórico de perguntas IA
SELECT * FROM ai_report_history
ORDER BY created_at DESC LIMIT 50;
```
