<?php
// Detecta se é etapa de redefinição (token na URL) ou solicitação de e-mail
$token = trim($_GET['token'] ?? '');
$etapa = $token !== '' ? 'redefinir' : 'solicitar';
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Recuperar Senha — Câmara Municipal</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
  body { font-family: 'Segoe UI', Arial, sans-serif; }
  .bg-rec { background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 60%, #0f172a 100%); }
  input:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.2); }
</style>
</head>
<body class="bg-rec min-h-screen flex items-center justify-center p-4">

<div class="w-full max-w-md">
  <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
    <div class="bg-slate-800 px-8 py-6 text-center">
      <div class="text-5xl mb-2">🔐</div>
      <h1 class="text-white font-black text-xl">Recuperação de Senha</h1>
      <p class="text-slate-400 text-sm mt-1">Câmara Municipal — Sistema de Votação</p>
    </div>

    <div class="px-8 py-7">

<?php if ($etapa === 'solicitar'): ?>
      <!-- ── Etapa 1: informar e-mail ──────────────────────── -->
      <div id="tela-solicitar">
        <p class="text-slate-600 text-sm mb-5">
          Informe o e-mail cadastrado na sua conta. Você receberá um link para redefinir a senha.
        </p>
        <div class="space-y-4">
          <div>
            <label class="block text-sm text-slate-500 mb-1 font-semibold">E-mail</label>
            <input id="inp-email" type="email" placeholder="seu@email.com.br"
              class="w-full border border-slate-300 rounded-xl px-4 py-3 text-slate-800 text-sm">
          </div>
          <p id="msg-solicitar" class="text-sm hidden"></p>
          <button onclick="solicitar()"
            class="w-full bg-blue-600 hover:bg-blue-700 text-white font-black py-3 rounded-xl text-base transition">
            Enviar link de recuperação
          </button>
        </div>
      </div>
<?php else: ?>
      <!-- ── Etapa 2: nova senha via token ────────────────── -->
      <div id="tela-redefinir">
        <p class="text-slate-600 text-sm mb-5">Digite a nova senha para a sua conta.</p>
        <div class="space-y-4">
          <div>
            <label class="block text-sm text-slate-500 mb-1 font-semibold">Nova Senha</label>
            <input id="inp-nova-senha" type="password" placeholder="Mínimo 8 caracteres"
              class="w-full border border-slate-300 rounded-xl px-4 py-3 text-slate-800 text-sm">
          </div>
          <div>
            <label class="block text-sm text-slate-500 mb-1 font-semibold">Confirmar Senha</label>
            <input id="inp-conf-senha" type="password" placeholder="Repita a senha"
              class="w-full border border-slate-300 rounded-xl px-4 py-3 text-slate-800 text-sm">
          </div>
          <p id="msg-redefinir" class="text-sm hidden"></p>
          <button onclick="redefinir()"
            class="w-full bg-blue-600 hover:bg-blue-700 text-white font-black py-3 rounded-xl text-base transition">
            Redefinir Senha
          </button>
        </div>
      </div>
<?php endif; ?>

    </div>
  </div>

  <p class="text-center text-slate-400 text-xs mt-5">
    <a href="../login/" class="hover:text-white transition">← Voltar ao login</a>
  </p>
</div>

<script>
const BASE = '../..';

async function solicitar() {
  const email = document.getElementById('inp-email').value.trim();
  const msg   = document.getElementById('msg-solicitar');
  if (!email) { msg.textContent = 'Informe um e-mail válido.'; msg.className = 'text-sm text-red-500'; msg.classList.remove('hidden'); return; }

  const r    = await fetch(`${BASE}/api/auth.php?action=esqueci_senha`, {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email }),
  });
  const json = await r.json();
  msg.textContent  = json.success
    ? '✅ ' + json.data.message
    : '❌ ' + (json.error || 'Erro ao processar.');
  msg.className = json.success ? 'text-sm text-green-700 font-semibold' : 'text-sm text-red-500';
  msg.classList.remove('hidden');
}

async function redefinir() {
  const novaSenha = document.getElementById('inp-nova-senha').value;
  const conf      = document.getElementById('inp-conf-senha').value;
  const msg       = document.getElementById('msg-redefinir');
  const token     = '<?= htmlspecialchars($token, ENT_QUOTES) ?>';

  if (novaSenha.length < 8) { msg.textContent = 'A senha deve ter ao menos 8 caracteres.'; msg.className='text-sm text-red-500'; msg.classList.remove('hidden'); return; }
  if (novaSenha !== conf)   { msg.textContent = 'As senhas não coincidem.';                msg.className='text-sm text-red-500'; msg.classList.remove('hidden'); return; }

  const r    = await fetch(`${BASE}/api/auth.php?action=redefinir_senha`, {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ token, nova_senha: novaSenha }),
  });
  const json = await r.json();
  msg.textContent = json.success ? '✅ Senha redefinida! Redirecionando...' : '❌ ' + (json.error || 'Token inválido.');
  msg.className   = json.success ? 'text-sm text-green-700 font-semibold' : 'text-sm text-red-500';
  msg.classList.remove('hidden');
  if (json.success) setTimeout(() => window.location.href = '../login/', 2000);
}
</script>
</body>
</html>
