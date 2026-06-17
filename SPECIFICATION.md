# SPECIFICATION.md — Sistema de Votação Eletrônica Câmara Municipal
> Versão 3.0 | Atualizado: 2026-06-17

## Status Geral

| Módulo | Status |
|--------|--------|
| Core de votação (SSE + API) | ✅ Concluído v1.0 |
| Terminal do Vereador | ✅ Concluído v1.0 |
| Painel do Plenário (TV) | ✅ Concluído v1.0 |
| Terminal de Controle (Mesa) | ✅ Concluído v1.0 |
| Portal de Transparência básico | ✅ Concluído v1.0 |
| update.sql v2.0 | ✅ Concluído v2.0 |
| config/logger.php | ✅ Concluído v2.0 |
| api/auth.php — remember-me + recuperação | ✅ Concluído v2.0 |
| api/admin.php — RBAC | ✅ Concluído v2.0 |
| api/analytics.php | ✅ Concluído v2.0 |
| api/ata.php — PDF export | ✅ Concluído v2.0 |
| Landing Page (public/index.html) | ✅ Concluído v2.0 |
| Histórico de Sessões (public/sessoes/) | ✅ Concluído v2.0 |
| Login seguro (public/login/) | ✅ Concluído v2.0 |
| Recuperação de Senha (public/recuperar-senha/) | ✅ Concluído v2.0 |
| Dashboard Admin (public/admin/) | ✅ Concluído v2.0 |
| RBAC UI (public/admin/usuarios.html) | ✅ Concluído v2.0 |
| Failover de voto (SessionStorage + retry) | ✅ Concluído v2.0 |
| Deleção de vereadores (admin/usuarios.html) | ✅ Concluído v2.1 |
| Toggle visibilidade de senha (todas as telas de login) | ✅ Concluído v2.1 |
| **Multi-tenant SaaS — foundation** (tasks 1-6) | ✅ Concluído v3.0 |
| **tenant_id isolation em todas as APIs** (task 7) | ✅ Concluído v3.0 |
| **Tenants API + White Label API** (tasks 8-9) | ✅ Concluído v3.0 |
| **White Label admin panel** (task 10) | ✅ Concluído v3.0 |
| **Reports API — PDF/Excel/CSV/JSON** (tasks 14-17) | ✅ Concluído v3.0 |
| **AI Reports API** (task 16) | ✅ Concluído v3.0 |
| **AI Chat API + widget** (tasks 18-25) | ✅ Concluído v3.0 |
| **Dashboard analytics renovado** (task 18) | ✅ Concluído v3.0 |
| **RESTful API v1 pública** (tasks 19-25) | ✅ Concluído v3.0 |
| **Powered-by footer widget** | ✅ Concluído v3.0 |
| **Migrations estruturadas** (001 a 004) | ✅ Concluído v3.0 |
| **CI/CD corrigido** (deploy.yml + setup-vps.yml) | ✅ Concluído v3.0 |

---

## Arquitetura do Sistema

```
camara-votacao/
├── api/                          # Endpoints PHP internos (sessão PHP)
│   ├── auth.php                  # Login vereador/admin, remember-me, recuperação
│   ├── admin.php                 # RBAC: CRUD usuários/vereadores, logs
│   ├── analytics.php             # Dados para gráficos do dashboard
│   ├── ata.php                   # Geração de ata em HTML/PDF
│   ├── ordem_dia.php             # Pauta: listar, adicionar, votação
│   ├── sessoes.php               # Sessões plenárias CRUD
│   ├── tribuna.php               # Controle da tribuna
│   ├── votacao.php               # Registro de votos
│   ├── resultados_publicos.php   # Histórico público (sem auth)
│   ├── tenants.php               # CRUD de tenants (super admin)
│   ├── white-label.php           # Configurações visuais do tenant
│   ├── reports.php               # Exportação PDF/Excel/CSV/JSON
│   ├── ai-reports.php            # Relatórios gerados por IA (requer ANTHROPIC_API_KEY)
│   ├── ai-chat.php               # Chat com IA (requer ANTHROPIC_API_KEY)
│   └── v1/
│       └── index.php             # REST API pública — Bearer token, rate limit 100 req/h
├── app/                          # Classes PSR-4 (Composer autoload)
│   ├── Controllers/
│   │   └── ReportController.php  # Orquestra exportações
│   ├── Middleware/
│   │   └── TenantMiddleware.php  # Resolve tenant por domínio
│   └── Services/
│       ├── AIChatService.php     # Integração Anthropic Claude
│       ├── AIReportService.php   # Geração de SQL por IA
│       └── ReportExportService.php # PDF (Dompdf) + Excel (PhpSpreadsheet)
├── config/
│   ├── database.php              # Singleton PDO — NÃO commitar
│   ├── database.example.php      # Modelo para novos devs
│   ├── helpers.php               # Auth, JSON, input, rate limit
│   ├── logger.php                # Auditoria → logs_sistema
│   └── bootstrap.php             # Resolve tenant por domínio/host
├── sse/                          # Server-Sent Events (tempo real)
│   ├── painel.php                # Estado completo do plenário
│   ├── vereador.php              # Estado personalizado por vereador
│   └── cronometro.php            # Cronômetro da tribuna
├── public/
│   ├── index.html                # Landing page pública
│   ├── sessoes/                  # Histórico de sessões (público)
│   ├── login/                    # Login unificado (admin + vereador)
│   ├── recuperar-senha/          # Recuperação de senha
│   ├── vereador/                 # Terminal do vereador (SSE + failover)
│   ├── painel/                   # Painel TV (SSE)
│   ├── controle/                 # Mesa Diretora
│   ├── publico/                  # Portal transparência
│   ├── admin/
│   │   ├── index.html            # Dashboard analytics (Chart.js)
│   │   ├── usuarios.html         # RBAC: CRUD usuários e vereadores
│   │   ├── dashboard.html        # Dashboard renovado v3.0
│   │   ├── relatorios.html       # Exportação PDF/Excel/CSV/JSON
│   │   ├── relatorios-ia.html    # Relatórios em linguagem natural (IA)
│   │   └── white-label.html      # Personalização visual do tenant
│   ├── assets/
│   │   └── js/
│   │       ├── ai-chat-widget.js # Widget de chat flutuante com IA
│   │       └── powered-by.js     # Footer "Powered by WebVoto"
│   └── css/
│       └── tenant.php            # CSS dinâmico por tenant (cores, fonte)
├── migrations/                   # Scripts SQL incrementais (rodar em ordem)
│   ├── 001_multi_tenant.sql      # Tabelas de tenant + tenant_id em todas as tabelas
│   ├── 002_reports.sql           # scheduled_reports, ai_report_history
│   ├── 003_ai_chat.sql           # ai_chat_logs
│   └── 004_complementar.sql      # notification_logs, audit_log, plans, billing, api_tokens
├── .github/
│   └── workflows/
│       ├── deploy.yml            # CI/CD: push em main → deploy VPS via SSH
│       └── setup-vps.yml         # Workflow único para primeira configuração da VPS
├── composer.json                 # Dependências: dompdf, phpspreadsheet
├── composer.lock
├── database.sql                  # Schema base v1.0
├── update.sql                    # Migração v2.0
├── run_migrations.php            # Utilitário local para rodar migrations
├── deploy.sh                     # Script de deploy executado na VPS
├── SPECIFICATION.md              # Este arquivo
├── MANUAL_USUARIO.md
└── MANUAL_TECNICO.md
```

---

## Banco de Dados — Tabelas

### Tabelas originais (v1.0 / v2.0)

| Tabela | Descrição |
|--------|-----------|
| `usuarios` | Usuários da mesa diretora (admin/operador) |
| `vereadores` | Cadastro dos vereadores com PIN |
| `sessoes_plenarias` | Sessões ordinárias/extraordinárias/especiais |
| `proposicoes` | Projetos de lei, requerimentos, etc. |
| `ordem_do_dia` | Itens da pauta com status de votação |
| `votos` | Registro imutável de cada voto (+ vote_hash SHA-256 v3.0) |
| `controle_tribuna` | Uso da tribuna com cronômetro |
| `tramitacao_proposicoes` | Histórico de tramitação |
| `logs_sistema` | Auditoria imutável de todas as ações |

### Tabelas SaaS (v3.0 — migrations 001–004)

| Tabela | Descrição |
|--------|-----------|
| `tenants` | Cadastro de cada câmara cliente (plano free/starter/pro/enterprise) |
| `tenant_settings` | Configurações visuais, SMTP, webhooks, social de cada tenant |
| `tenant_domains` | Domínios associados a cada tenant (resolve por HTTP_HOST) |
| `tenant_users` | Vínculo usuário ↔ tenant com papel (super_admin/admin/operator/voter) |
| `super_admin_settings` | Configurações globais da plataforma WebVoto |
| `plans` | Planos de cobrança (free/starter/pro/enterprise) com limites |
| `billing` | Faturas e pagamentos por tenant |
| `api_tokens` | Tokens Bearer para acesso à REST API v1 |
| `scheduled_reports` | Relatórios agendados (daily/weekly/monthly) |
| `ai_report_history` | Histórico de perguntas e SQLs gerados pela IA |
| `ai_chat_logs` | Histórico de mensagens do chat com IA |
| `notification_logs` | Log de envios (email/whatsapp/sms) |
| `notification_templates` | Templates de notificação por tenant |
| `audit_log` | Log detalhado de auditoria com before/after JSON |

> Todas as tabelas originais receberam coluna `tenant_id` (FK → `tenants.id`) na migration 001.

---

## Interfaces e Acessos

| Interface | URL | Autenticação |
|-----------|-----|-------------|
| Landing Page | `/public/` | Pública |
| Histórico de Sessões | `/public/sessoes/` | Pública |
| Portal Transparência | `/public/publico/` | Pública |
| Painel TV | `/public/painel/` | Pública |
| Login | `/public/login/` | N/A |
| Recuperar Senha | `/public/recuperar-senha/` | N/A |
| Terminal Vereador | `/public/vereador/` | PIN |
| Terminal Controle | `/public/controle/` | Admin/Operador |
| Dashboard Admin | `/public/admin/` | Admin |
| Dashboard Renovado | `/public/admin/dashboard.html` | Admin |
| Gerenciamento Users | `/public/admin/usuarios.html` | Admin |
| Relatórios | `/public/admin/relatorios.html` | Admin |
| Relatórios com IA | `/public/admin/relatorios-ia.html` | Admin |
| White Label | `/public/admin/white-label.html` | Admin |
| REST API v1 | `/api/v1/` | Bearer Token |

---

## Segurança

- Senhas: `password_hash()` com bcrypt, custo 12
- Sessões: `session_regenerate_id()` no login, cookie `HttpOnly + SameSite=Strict`
- Remember-me: token 64 hex armazenado no banco com expiração de 30 dias
- Recuperação: token 64 hex, expiração 2h, invalidado após uso
- Logs: toda ação de autenticação e administrativa registrada em `logs_sistema`
- CORS: restrito ao `APP_DOMAIN` em produção
- API v1: Bearer token (SHA-256), rate limit 100 req/hora por tenant
- Votos: `vote_hash` SHA-256 garante imutabilidade

---

## Planos da Plataforma

| Plano | Sessões | Vereadores | IA | API | Powered-by | Preço/mês |
|-------|---------|------------|----|-----|------------|-----------|
| Free | 5 | 9 | não | não | sim | R$ 0 |
| Starter | 30 | 21 | não | não | sim | R$ 99 |
| Pro | 200 | 55 | sim | sim | não | R$ 299 |
| Enterprise | ilimitado | ilimitado | sim | sim | não | R$ 899 |

---

## CI/CD — Deploy Automático

- **Trigger:** push em `main`
- **Workflow:** `.github/workflows/deploy.yml`
- **Secrets necessários:** `VPS_HOST`, `VPS_USER`, `VPS_PASSWORD`, `GH_PAT`
- **Fluxo:** GitHub Actions → SSH na VPS → `git reset --hard origin/main` → `bash deploy.sh`
- **Primeira vez:** `setup-vps.yml` (executar manualmente via Actions)

---

## Pendências Futuras (v4.0)

- [ ] PHPMailer/SMTP para envio real de e-mails de recuperação de senha
- [ ] HTTPS obrigatório (ajustar `secure=true` nos cookies)
- [ ] 2FA para administradores
- [ ] Upload de foto dos vereadores
- [ ] Painel super admin para gerenciar todos os tenants
- [ ] Webhook de notificação por evento (votação encerrada, sessão iniciada)
- [ ] App mobile (React Native ou PWA) para vereadores
