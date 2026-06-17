# Manual do Usuário — Sistema de Votação Eletrônica
**WebVoto SaaS | Versão 3.0 | Atualizado: 2026-06-17**

---

## Visão Geral

O sistema possui **interfaces públicas** (para cidadãos) e **interfaces restritas** (para a câmara):

| Interface | URL | Público-alvo |
|-----------|-----|-------------|
| Landing Page | `/public/` | Cidadãos |
| Histórico de Sessões | `/public/sessoes/` | Cidadãos |
| Portal de Transparência | `/public/publico/` | Cidadãos |
| Painel do Plenário (TV) | `/public/painel/` | Cidadãos / projeção |
| Login | `/public/login/` | Usuários internos |
| Terminal do Vereador | `/public/vereador/` | Parlamentares |
| Terminal de Controle | `/public/controle/` | Mesa Diretora |
| Dashboard Administrativo | `/public/admin/` | Administrador |
| Gerenciamento de Acesso | `/public/admin/usuarios.html` | Administrador |
| Relatórios | `/public/admin/relatorios.html` | Administrador |
| Relatórios com IA | `/public/admin/relatorios-ia.html` | Administrador |
| White Label | `/public/admin/white-label.html` | Administrador |

---

## 1. Acesso ao Sistema

### Login (Mesa Diretora / Admin)
1. Acesse `/public/login/`
2. Clique na aba **Mesa Diretora**
3. Informe **Login** e **Senha** — use o ícone de olho para revelar a senha
4. Marque "Lembrar-me" para manter sessão por 30 dias
5. Clique em **Entrar**

**Credenciais padrão:**
- Admin: `admin` / `admin123`
- Operador: `operador` / `operador123`

⚠️ Altere as senhas após o primeiro acesso em `/public/admin/usuarios.html`.

### Login (Vereador)
1. Acesse `/public/login/` ou `/public/vereador/`
2. Clique na aba **Vereador**
3. Selecione seu nome na lista
4. Informe seu **PIN de 6 dígitos** — use o ícone de olho se precisar conferir o que digitou

### Recuperação de Senha
1. Na tela de login, clique em **Esqueci a senha**
2. Informe o e-mail cadastrado
3. Um link de recuperação é gerado (válido por 2 horas)
4. Acesse o link e defina a nova senha

---

## 2. Terminal de Controle (Mesa Diretora)

Acesse `/public/controle/` com login admin ou operador.

### 2.1 Iniciar Sessão Plenária
1. Preencha: Número (ex: `001/2026`), Data, Tipo (Ordinária/Extraordinária/Especial)
2. Clique em **▶ Iniciar Sessão**

### 2.2 Adicionar Item à Pauta
1. Selecione a **Proposição** no menu suspenso
2. Escolha o **Tipo de Votação**: Nominal, Simbólica ou Secreta
3. Clique em **Adicionar à Ordem do Dia**

### 2.3 Conduzir Votação
Cada item percorre os seguintes estados:
```
Pendente → Em Discussão → Votando → Encerrada
```
- **💬 Discutir** — abre o debate
- **🗳️ Abrir Votação** — habilita botões nos terminais dos vereadores
- **⏹ Encerrar Votação** — apura resultado automaticamente

### 2.4 Controle da Tribuna
1. Selecione o vereador e o tempo (minutos)
2. Clique em **🎤 Iniciar Tribuna**
3. Use **⏸ Pausar / ▶ Retomar / ⏹ Encerrar** conforme necessário

### 2.5 Encerrar a Sessão
Clique em **Encerrar Sessão** e confirme.

---

## 3. Terminal do Vereador

Acesse `/public/vereador/`, selecione seu nome e informe o PIN.

### Votação
Quando a mesa abre uma votação, um painel aparece automaticamente com:
- ✔ **SIM** (verde)
- ✘ **NÃO** (vermelho)
- ≈ **ABSTER** (amarelo)

O voto é confirmado imediatamente e **não pode ser alterado**.

> **Failover de rede:** se o Wi-Fi cair no momento do voto, o sistema salva a intenção localmente e reenvia quando a conexão for restabelecida (até 3 tentativas).

---

## 4. Painel do Plenário (TV)

Acesse `/public/painel/` em tela cheia (TV ou projetor). **Não requer login.**

Exibe em tempo real:
- Informações da sessão em andamento
- Grid de vereadores com foto
- Placar durante votações
- Voto de cada parlamentar com ícone colorido
- Resultado final em destaque
- Cronômetro da tribuna com nome do vereador

---

## 5. Portal de Transparência e Sessões

### Portal (`/public/publico/`)
- Histórico de todas as votações encerradas
- Filtros por resultado (aprovado/rejeitado/empate)
- Busca por ementa ou número
- Detalhe com votos nominais de cada vereador

### Histórico de Sessões (`/public/sessoes/`)
- Grade de todas as sessões plenárias
- Filtro por tipo (Ordinária/Extraordinária/Especial)
- Clique em uma sessão para ver todas as votações com detalhe nominal

---

## 6. Dashboard Administrativo

Acesse `/public/admin/` com login de **Administrador**.

### KPIs exibidos
- Total de sessões realizadas
- Votações aprovadas, rejeitadas e empatadas
- Número de vereadores ativos
- Taxa de aprovação geral

### Gráficos
- **Barras** — votações por mês
- **Rosca** — aprovadas vs rejeitadas vs empates
- **Horizontal** — assiduidade (% de presença) por vereador

### Logs de auditoria
Clique em **Logs** na barra lateral para ver todas as ações registradas no sistema.

---

## 7. Relatórios (`/public/admin/relatorios.html`)

Exporte dados da câmara em quatro formatos:

| Formato | Uso ideal |
|---------|-----------|
| **PDF** | Impressão e arquivo oficial |
| **Excel** | Análise e cruzamento de dados |
| **CSV** | Importação em outros sistemas |
| **JSON** | Integração com APIs externas |

**Tipos de relatório disponíveis:**
- Sessões plenárias
- Votações
- Vereadores
- Proposições
- Tribuna
- Logs de auditoria

**Como usar:**
1. Selecione o tipo de relatório
2. Selecione o período
3. Escolha o formato
4. Clique em **Exportar**

---

## 8. Relatórios com IA (`/public/admin/relatorios-ia.html`)

Faça perguntas em português e a IA gera o relatório automaticamente.

**Exemplos de perguntas:**
- *"Quais foram as proposições mais votadas nos últimos 3 meses?"*
- *"Qual vereador teve maior índice de abstenção em 2026?"*
- *"Quantas sessões extraordinárias foram realizadas este ano?"*

> Disponível apenas para tenants com plano **Pro** ou **Enterprise**, e requer que o servidor tenha a `ANTHROPIC_API_KEY` configurada.

---

## 9. White Label (`/public/admin/white-label.html`)

Personalize a identidade visual da câmara no sistema.

### Identidade visual
- Logotipo e favicon
- Cor primária, secundária e de destaque
- Família de fonte
- CSS customizado (avançado)

### Informações da câmara
- Nome, slogan, CNPJ
- Endereço completo
- Telefone, WhatsApp, e-mail de contato
- Site e redes sociais (LinkedIn, Instagram, YouTube, Facebook)

### Configurações avançadas
- SMTP próprio para envio de e-mails (host, porta, usuário, senha, TLS/SSL)
- URL de webhook para integrações externas
- Texto do rodapé de e-mails
- Links de termos de uso e política de privacidade

---

## 10. Gerenciamento de Acesso (`/public/admin/usuarios.html`)

### Aba: Usuários da Mesa

| Ação | Como fazer |
|------|-----------|
| Criar usuário | Preencha nome, login, e-mail, perfil e senha → Salvar |
| Editar | Clique no usuário → altere os campos desejados → Salvar |
| Ativar/Inativar | Toggle de status na lista |
| Reset de senha | Clique em "Resetar senha" e informe a nova |

**Perfis disponíveis:**
- **Admin** — acesso completo, incluindo gerenciamento de usuários
- **Operador** — acesso ao terminal de controle, sem gerenciamento

### Aba: Vereadores

| Ação | Como fazer |
|------|-----------|
| Criar | Preencha nome, partido, PIN, e-mail, cargo → Salvar |
| Editar | Clique no vereador → altere os campos (PIN vazio mantém o atual) |
| Excluir | Botão excluir → confirmação → **irreversível** se sem votos registrados |

> Vereadores com votos ou uso de tribuna registrados **não podem ser excluídos** — inative-os.

---

## 11. Exportação de Ata

No Terminal de Controle, após encerrar uma votação:
```
/api/ata.php?ordem_dia_id=<ID>
```
A ata abre em nova aba com botão **Imprimir / Salvar PDF** (Ctrl+P no navegador).

---

## 12. PINs Padrão dos Vereadores

| Vereador | Partido | PIN |
|----------|---------|-----|
| João da Silva | PT | `111111` |
| Maria Oliveira | PSDB | `222222` |
| Carlos Souza | PL | `333333` |
| Ana Santos | MDB | `444444` |
| Pedro Lima | PP | `555555` |
| Lucia Ferreira | PDT | `666666` |
| Roberto Alves | Republicanos | `777777` |
| Sandra Costa | Avante | `888888` |
| Marcos Pereira | PSD | `999999` |

⚠️ Altere os PINs pelo painel de Gerenciamento de Acesso antes de colocar em produção.
