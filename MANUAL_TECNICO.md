# Manual Técnico — Sistema de Votação Eletrônica
**Câmara Municipal | Versão 2.0**

---

## Stack Tecnológico

| Componente | Tecnologia |
|------------|-----------|
| Backend | PHP 8.2 (sem framework) |
| Banco de dados | MySQL 8.0 |
| Comunicação em tempo real | Server-Sent Events (SSE) |
| Frontend | HTML5 + Tailwind CSS (CDN) |
| Gráficos | Chart.js 4.4 (CDN) |
| Servidor web | Apache 2.4 (XAMPP) |
| PDF (produção) | Dompdf (opcional, via Composer) |

---

## Estrutura de Arquivos

```
camara-votacao/
├── api/
│   ├── admin.php           # CRUD usuários, vereadores, logs (requer admin)
│   ├── analytics.php       # Dados para dashboard (requer admin)
│   ├── ata.php             # Geração de ata HTML/PDF (requer admin)
│   ├── auth.php            # Login, logout, recuperação de senha
│   ├── ordem_dia.php       # Pauta: listar, adicionar, votação
│   ├── resultados_publicos.php # Histórico público (sem auth)
│   ├── sessoes.php         # CRUD sessões plenárias
│   ├── tribuna.php         # Controle da tribuna
│   └── votacao.php         # Registro de votos
├── config/
│   ├── database.php        # Singleton PDO (MySQL)
│   ├── helpers.php         # Auth, JSON, input sanitization
│   └── logger.php          # Auditoria → logs_sistema
├── sse/
│   ├── cronometro.php      # SSE: cronômetro da tribuna
│   ├── painel.php          # SSE: estado completo do plenário
│   └── vereador.php        # SSE: estado personalizado por vereador
├── public/
│   ├── index.html          # Landing page
│   ├── admin/
│   │   ├── index.html      # Dashboard analytics (Chart.js)
│   │   └── usuarios.html   # RBAC: CRUD usuários e vereadores
│   ├── controle/index.html # Terminal mesa diretora
│   ├── login/index.html    # Login unificado (admin + vereador)
│   ├── painel/index.html   # Painel TV (SSE)
│   ├── publico/index.html  # Portal transparência
│   ├── recuperar-senha/index.php # Recuperação de senha
│   ├── sessoes/index.html  # Histórico de sessões
│   └── vereador/index.html # Terminal do vereador (SSE + failover)
├── database.sql            # Schema v1.0
├── update.sql              # Migração v2.0
├── setup.php               # Inicialização de senhas
├── SPECIFICATION.md        # Status e arquitetura do projeto
├── MANUAL_USUARIO.md       # Este arquivo (usuário)
└── MANUAL_TECNICO.md       # Este arquivo (técnico)
```

---

## Banco de Dados

### Diagrama de Tabelas

```
usuarios ──────────────────── sessoes_plenarias
  id, nome, login, email,          id, numero, data, tipo, status
  senha_hash, perfil,              usuario_id (FK)
  permissao_level,
  token_recuperacao              ordem_do_dia
                                   sessao_id (FK)
vereadores                         proposicao_id (FK)
  id, nome, partido, pin,          status_votacao, resultado
  email, cargo_id, status          votos_sim/nao/abstencao

votos                            proposicoes
  vereador_id (FK)                 numero, ano, tipo, ementa
  ordem_dia_id (FK)                status
  voto (SIM/NAO/ABSTENCAO/AUSENTE)

controle_tribuna               logs_sistema (auditoria imutável)
  sessao_id, vereador_id           usuario_id, acao, detalhes
  tempo_inicial, status            ip_origem, user_agent, created_at
```

### Migração v2.0

Após atualizar os arquivos, execute:
```bash
mysql -u root -pSUASENHA camara_votacao < update.sql
```

Campos adicionados:
- `usuarios`: `email`, `permissao_level`, `token_recuperacao`, `token_expira_em`
- `vereadores`: `email`, `cargo_id`
- Nova tabela: `logs_sistema`

---

## APIs — Referência

### `api/auth.php`

| Action | Método | Auth | Descrição |
|--------|--------|------|-----------|
| `login_admin` | POST | — | Login admin/operador + remember-me |
| `login_vereador` | POST | — | Login vereador por PIN |
| `logout` | POST | — | Encerra sessão |
| `status` | GET | — | Retorna dados da sessão atual |
| `lista_vereadores` | GET | — | Lista vereadores ativos |
| `esqueci_senha` | POST | — | Gera token de recuperação |
| `redefinir_senha` | POST | — | Redefine senha via token |
| `verificar_token` | POST | — | Valida token de recuperação |

### `api/admin.php`

| Action | Auth necessária |
|--------|----------------|
| `listar_usuarios` / `criar_usuario` / `editar_usuario` / `resetar_senha` | admin |
| `listar_vereadores` / `criar_vereador` / `editar_vereador` | admin |
| `listar_logs` | admin |

### `api/analytics.php`

| Action | Retorno |
|--------|---------|
| `resumo` | KPIs: sessões, votações, aprovadas, etc. |
| `votacoes_por_mes` | Array por mês: total, aprovadas, rejeitadas |
| `assiduidade` | Array por vereador: presenças, % assiduidade |
| `aprovacao_partido` | Votos SIM/NÃO/ABST por partido |
| `ultimas_sessoes` | Últimas 10 sessões com totais |

### `api/ata.php`

```
GET /api/ata.php?ordem_dia_id=<ID>&formato=html  → HTML para impressão
GET /api/ata.php?ordem_dia_id=<ID>&formato=pdf   → PDF via Dompdf (se instalado)
```

---

## Server-Sent Events (SSE)

### `sse/painel.php`
- Evento: `painel_update`
- Payload: sessão ativa, votação corrente, placar, votos nominais, tribuna
- Polling: 2 segundos no banco

### `sse/vereador.php`
- Evento: `vereador_update`
- Parâmetro: `?vereador_id=<ID>`
- Payload: sessão, ordem do dia, votação aberta, voto do próprio vereador

### `sse/cronometro.php`
- Evento: `cronometro_update`
- Payload: tribuna ativa, tempo calculado em tempo real

---

## Segurança

### Autenticação
- Sessões PHP com `session_regenerate_id()` no login
- Cookie: `HttpOnly`, `SameSite=Strict`
- Remember-me: token de 64 bytes hex, expiração 30 dias
- Recuperação de senha: token de 64 bytes hex, expiração 2 horas, uso único

### Senhas
```php
$hash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);
password_verify($senha, $hash);
```

### Auditoria
Toda ação de auth e admin é registrada em `logs_sistema`:
```php
registrarLog('acao', $usuarioId, $vereadorId, 'detalhes opcionais');
```

### Em produção
- Ativar `'secure' => true` nos cookies (requer HTTPS)
- Ajustar `setCorsHeaders()` para domínio específico
- Remover `setup.php` após uso inicial

---

## Instalação em Produção

### Requisitos
- PHP 8.1+
- MySQL 8.0+ ou MariaDB 10.6+
- Apache 2.4 com `mod_rewrite`

### Passos
```bash
# 1. Clonar/copiar projeto para /var/www/html/camara-votacao
# 2. Criar banco
mysql -u root -p -e "CREATE DATABASE camara_votacao CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root -p camara_votacao < database.sql
mysql -u root -p camara_votacao < update.sql

# 3. Configurar credenciais
vim config/database.php

# 4. Inicializar senhas
php setup.php  # ou acessar via navegador

# 5. Remover setup.php
rm setup.php
```

### Dompdf (PDF nativo)
```bash
composer require dompdf/dompdf
```
Após instalação, `/api/ata.php?formato=pdf` gera PDF real.

---

## Failover de Voto

O terminal do vereador salva a intenção de voto no `SessionStorage` antes de enviar:

```javascript
sessionStorage.setItem('voto_pendente', JSON.stringify({ordem_dia_id, voto}));
```

Se a requisição falhar, o sistema tenta até 3 vezes com backoff exponencial (2s, 4s, 6s). Ao reconectar via SSE, `verificarVotoPendente()` é chamada automaticamente.

---

## Configuração do Banco de Dados

`config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'camara_votacao');
define('DB_USER', 'root');
define('DB_PASS', 'sua_senha');
define('DB_CHARSET', 'utf8mb4');
```

---

## Logs e Monitoramento

Verificar logs de auditoria:
```sql
SELECT l.*, u.nome AS usuario
FROM logs_sistema l
LEFT JOIN usuarios u ON u.id = l.usuario_id
ORDER BY l.id DESC
LIMIT 100;
```

Ou via interface: `/public/admin/` → seção **Logs**.
