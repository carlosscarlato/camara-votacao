# Manual do Usuário — Sistema de Votação Eletrônica
**Câmara Municipal | Versão 2.0**

---

## Visão Geral

O sistema possui **6 interfaces** com funções distintas:

| Interface | URL | Público-alvo |
|-----------|-----|-------------|
| Landing Page | `/public/` | Cidadãos |
| Histórico de Sessões | `/public/sessoes/` | Cidadãos |
| Portal de Transparência | `/public/publico/` | Cidadãos |
| Login | `/public/login/` | Usuários internos |
| Terminal do Vereador | `/public/vereador/` | Parlamentares |
| Terminal de Controle | `/public/controle/` | Mesa Diretora |
| Painel do Plenário | `/public/painel/` | Projeção TV |
| Dashboard Administrativo | `/public/admin/` | Administrador |
| Gerenciamento de Acesso | `/public/admin/usuarios.html` | Administrador |

---

## 1. Acesso ao Sistema

### Login (Mesa Diretora / Admin)
1. Acesse `/public/login/`
2. Clique na aba **Mesa Diretora**
3. Informe **Login** e **Senha**
4. Marque "Lembrar-me" para manter sessão por 30 dias
5. Clique em **Entrar**

**Credenciais padrão:**
- Admin: `admin` / `admin123`
- Operador: `operador` / `operador123`

⚠️ Altere as senhas após o primeiro acesso via painel administrativo.

### Login (Vereador)
1. Acesse `/public/login/` ou `/public/vereador/`
2. Clique na aba **Vereador**
3. Selecione seu nome na lista
4. Informe seu **PIN de 6 dígitos**

### Recuperação de Senha
1. Na tela de login, clique em **Esqueci a senha**
2. Informe o e-mail cadastrado
3. Um link de recuperação será gerado (válido por 2 horas)
4. Acesse o link e defina a nova senha

---

## 2. Terminal de Controle (Mesa Diretora)

### 2.1 Iniciar Sessão Plenária
1. Faça login como **admin** ou **operador**
2. Preencha: Número (ex: `001/2024`), Data, Tipo
3. Clique em **▶ Iniciar Sessão**

### 2.2 Adicionar Item à Pauta
1. Selecione a **Proposição** no menu suspenso
2. Escolha o **Tipo de Votação**: Nominal, Simbólica ou Secreta
3. Clique em **Adicionar à Ordem do Dia**

### 2.3 Conduzir Votação
Cada item percorre:
```
Pendente → Em Discussão → Votando → Encerrada
```
- **💬 Discutir**: Abre debate
- **🗳️ Abrir Votação**: Habilita botões nos terminais dos vereadores
- **⏹ Encerrar Votação**: Apura resultado automaticamente

### 2.4 Controle da Tribuna
1. Selecione o vereador e o tempo (minutos)
2. Clique em **🎤 Iniciar Tribuna**
3. Use **⏸ Pausar / ▶ Retomar / ⏹ Encerrar** conforme necessário

### 2.5 Encerrar a Sessão
Clique em **Encerrar Sessão** e confirme.

---

## 3. Terminal do Vereador

### Votação
Quando a mesa abre uma votação, um **painel aparece automaticamente** com:
- ✔ **SIM** (verde)
- ✘ **NÃO** (vermelho)
- ≈ **ABSTER** (amarelo)

O voto é confirmado imediatamente e não pode ser alterado.

> **Failover de rede:** Se o Wi-Fi cair no momento do voto, o sistema salva a intenção localmente e reenvia automaticamente quando a conexão for restabelecida.

---

## 4. Painel do Plenário

Acesse `/public/painel/` em tela cheia (TV/projetor). Não requer login.

Exibe:
- Informações da sessão
- Grid de vereadores com foto
- Placar em tempo real durante votações
- Voto de cada parlamentar com ícone colorido
- Resultado final em destaque
- Cronômetro da tribuna com nome do vereador

---

## 5. Portal de Transparência e Sessões

### Portal (`/public/publico/`)
- Histórico de todas as votações encerradas
- Filtros por resultado
- Busca por ementa/número
- Detalhe com votos nominais de cada vereador

### Histórico de Sessões (`/public/sessoes/`)
- Grid de todas as sessões plenárias
- Filtro por tipo (Ordinária/Extraordinária/Especial)
- Clique em uma sessão para ver todas as votações
- Detalhe completo com votação nominal

---

## 6. Painel Administrativo

Acesse `/public/admin/` com login de **Administrador**.

### Dashboard
- **KPIs**: Sessões, Aprovadas, Rejeitadas, Vereadores ativos
- **Gráfico de barras**: Votações por mês
- **Gráfico de rosca**: Aprovadas vs Rejeitadas vs Empates
- **Gráfico horizontal**: Assiduidade (% de presença) de cada vereador
- **Logs de auditoria**: clique em "Logs" na barra lateral

### Gerenciamento de Acesso (`/public/admin/usuarios.html`)
- **Usuários da mesa**: CRUD completo, reset de senha
- **Vereadores**: Cadastro com nome, partido, PIN, e-mail, cargo

---

## 7. Exportação de Ata (PDF)

No Terminal de Controle, após encerrar uma votação, acesse:
```
/api/ata.php?ordem_dia_id=<ID>
```
A ata abre em nova aba com botão **Imprimir / Salvar PDF** (Ctrl+P no navegador).

Para gerar PDF nativo, instale o Dompdf via Composer (ver Manual Técnico).

---

## 8. PINs Padrão dos Vereadores

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

⚠️ Altere os PINs pelo painel de Gerenciamento de Acesso.
