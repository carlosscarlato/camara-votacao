# SPECIFICATION.md — Sistema de Votação Eletrônica Câmara Municipal
> Versão 2.1 | Atualizado: 2026-06-03

## Status Geral

| Módulo | Status |
|--------|--------|
| Core de votação (SSE + API) | ✅ Concluído v1.0 |
| Terminal do Vereador | ✅ Concluído v1.0 |
| Painel do Plenário (TV) | ✅ Concluído v1.0 |
| Terminal de Controle (Mesa) | ✅ Concluído v1.0 |
| Portal de Transparência básico | ✅ Concluído v1.0 |
| **update.sql v2.0** | ✅ Concluído v2.0 |
| **config/logger.php** | ✅ Concluído v2.0 |
| **api/auth.php** — remember-me + recuperação | ✅ Concluído v2.0 |
| **api/admin.php** — RBAC | ✅ Concluído v2.0 |
| **api/analytics.php** | ✅ Concluído v2.0 |
| **api/ata.php** — PDF export | ✅ Concluído v2.0 |
| **Landing Page** (public/index.html) | ✅ Concluído v2.0 |
| **Histórico de Sessões** (public/sessoes/) | ✅ Concluído v2.0 |
| **Login seguro** (public/login/) | ✅ Concluído v2.0 |
| **Recuperação de Senha** (public/recuperar-senha/) | ✅ Concluído v2.0 |
| **Dashboard Admin** (public/admin/) | ✅ Concluído v2.0 |
| **RBAC UI** (public/admin/usuarios.html) | ✅ Concluído v2.0 |
| **Failover de voto** (SessionStorage + retry) | ✅ Concluído v2.0 |
| **MANUAL_USUARIO.md** | ✅ Concluído v2.0 |
| **MANUAL_TECNICO.md** | ✅ Concluído v2.0 |
| **Deleção de vereadores** (admin/usuarios.html) | ✅ Concluído v2.1 |

---

## Arquitetura do Sistema

```
camara-votacao/
├── api/              # Endpoints PHP (JSON REST)
│   ├── auth.php      # Login vereador/admin, remember-me, recuperação
│   ├── admin.php     # RBAC: CRUD usuários/vereadores, logs
│   ├── analytics.php # Dados para gráficos do dashboard
│   ├── ata.php       # Geração de ata em HTML/PDF
│   ├── ordem_dia.php # Pauta: listar, adicionar, votação
│   ├── sessoes.php   # Sessões plenárias
│   ├── tribuna.php   # Controle da tribuna
│   ├── votacao.php   # Registro de votos
│   └── resultados_publicos.php
├── config/
│   ├── database.php  # Singleton PDO
│   ├── helpers.php   # Auth, JSON, input
│   └── logger.php    # Auditoria → logs_sistema
├── sse/              # Server-Sent Events (tempo real)
│   ├── painel.php
│   ├── vereador.php
│   └── cronometro.php
├── public/
│   ├── index.html          # Landing page pública
│   ├── sessoes/            # Histórico de sessões
│   ├── login/              # Login seguro
│   ├── recuperar-senha/    # Recuperação de senha
│   ├── vereador/           # Terminal do vereador
│   ├── painel/             # Painel TV
│   ├── controle/           # Mesa Diretora
│   ├── publico/            # Portal transparência
│   └── admin/              # Dashboard + RBAC
│       ├── index.html      # Dashboard analytics
│       └── usuarios.html   # Gerenciamento
├── database.sql      # Schema inicial v1.0
├── update.sql        # Migração v2.0
├── setup.php         # Inicialização de senhas
├── SPECIFICATION.md  # Este arquivo
├── MANUAL_USUARIO.md
└── MANUAL_TECNICO.md
```

---

## Banco de Dados — Tabelas

| Tabela | Descrição |
|--------|-----------|
| `usuarios` | Usuários da mesa diretora (admin/operador) |
| `vereadores` | Cadastro dos vereadores com PIN |
| `sessoes_plenarias` | Sessões ordinárias/extraordinárias/especiais |
| `proposicoes` | Projetos de lei, requerimentos, etc. |
| `ordem_do_dia` | Itens da pauta com status de votação |
| `votos` | Registro imutável de cada voto |
| `controle_tribuna` | Uso da tribuna com cronômetro |
| `tramitacao_proposicoes` | Histórico de tramitação |
| `logs_sistema` | **Auditoria imutável** de todas as ações |

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
| Gerenciamento Users | `/public/admin/usuarios.html` | Admin |

---

## Segurança

- Senhas: `password_hash()` com bcrypt, custo 12
- Sessões: `session_regenerate_id()` no login, cookie `HttpOnly + SameSite=Strict`
- Remember-me: token 64 hex armazenado no banco com expiração de 30 dias
- Recuperação: token 64 hex, expiração 2h, invalidado após uso
- Logs: toda ação de autenticação e administrativa registrada em `logs_sistema`
- CORS: restrito em produção (atualmente `*` para desenvolvimento)

---

## Funcionalidades por Interface

### `api/admin.php` — Ações disponíveis

| Ação | Perfil | Descrição |
|------|--------|-----------|
| `listar_usuarios` | admin | Lista todos os usuários da mesa |
| `criar_usuario` | admin | Cria usuário com nome, login, e-mail, perfil e senha |
| `editar_usuario` | admin | Atualiza nome, perfil, e-mail e status ativo/inativo |
| `resetar_senha` | admin | Redefine a senha de qualquer usuário |
| `listar_vereadores` | admin/operador | Lista todos os vereadores |
| `criar_vereador` | admin | Cria vereador com nome, partido, PIN, e-mail e cargo |
| `editar_vereador` | admin | Atualiza dados; PIN só alterado se informado |
| `deletar_vereador` | admin | Exclui permanentemente — bloqueado se houver votos ou histórico de tribuna |
| `listar_logs` | admin | Retorna logs de auditoria com paginação (limit/offset) |

### `public/admin/usuarios.html` — Gerenciamento de Acesso

**Aba Usuários da Mesa:**
- Listar usuários com nome, login, e-mail, perfil (Admin/Operador) e status
- Criar novo usuário (senha obrigatória no cadastro)
- Editar usuário (nome, e-mail, perfil, status ativo/inativo)
- Reset de senha via prompt inline

**Aba Vereadores:**
- Listar vereadores com nome, partido, e-mail, PIN e status
- Criar novo vereador
- Editar vereador (PIN opcional — deixar vazio mantém o atual)
- **Excluir vereador** — exclusão permanente com confirmação; bloqueada se existirem votos ou uso de tribuna registrado (recomenda inativar nesses casos)

---

## Pendências Futuras (v3.0)

- [ ] Integração PHPMailer/SMTP para envio real de e-mails
- [ ] Instalação do Dompdf via Composer para PDF real
- [ ] HTTPS obrigatório (ajustar `secure=true` nos cookies)
- [ ] Rate limiting nas rotas de autenticação
- [ ] 2FA para administradores
- [ ] Upload de foto dos vereadores
