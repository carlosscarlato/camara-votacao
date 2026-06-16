// public/assets/js/ai-chat-widget.js — Widget flutuante de Chat IA
(function () {
  'use strict';
  const BASE = window.APP_BASE || '../..';

  const widget = document.createElement('div');
  widget.innerHTML = `
    <button id="ai-chat-toggle" title="Chat IA Estratégico"
      style="position:fixed;bottom:24px;right:24px;z-index:9998;width:52px;height:52px;border-radius:50%;
             background:#7c3aed;color:#fff;border:none;cursor:pointer;font-size:22px;
             box-shadow:0 4px 16px rgba(124,58,237,.5);display:flex;align-items:center;justify-content:center;">✨</button>
    <div id="ai-chat-panel"
      style="display:none;position:fixed;bottom:88px;right:24px;z-index:9999;width:400px;height:500px;
             background:#fff;border-radius:16px;box-shadow:0 8px 32px rgba(0,0,0,.18);
             flex-direction:column;overflow:hidden;border:1px solid #e5e7eb;">
      <div style="background:#7c3aed;color:#fff;padding:12px 16px;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;">
        <span style="font-weight:700;font-size:14px;">✨ Chat IA Estratégico</span>
        <div style="display:flex;gap:8px;align-items:center;">
          <button id="ai-export-btn" style="background:rgba(255,255,255,.2);border:none;color:#fff;padding:3px 8px;border-radius:6px;cursor:pointer;font-size:11px;">Exportar</button>
          <button id="ai-clear-btn"  style="background:rgba(255,255,255,.2);border:none;color:#fff;padding:3px 8px;border-radius:6px;cursor:pointer;font-size:11px;">Limpar</button>
          <button id="ai-close-btn"  style="background:none;border:none;color:#fff;cursor:pointer;font-size:20px;line-height:1;">×</button>
        </div>
      </div>
      <div id="ai-messages" style="flex:1;overflow-y:auto;padding:12px;display:flex;flex-direction:column;gap:10px;min-height:0;"></div>
      <div id="ai-typing" style="display:none;padding:0 12px 4px;font-size:12px;color:#7c3aed;font-style:italic;flex-shrink:0;">IA digitando...</div>
      <div style="padding:10px;border-top:1px solid #f3f4f6;display:flex;gap:8px;flex-shrink:0;">
        <textarea id="ai-input" rows="2" placeholder="Faça uma pergunta estratégica..."
          style="flex:1;border:1px solid #d1d5db;border-radius:8px;padding:8px;font-size:13px;resize:none;font-family:inherit;outline:none;"></textarea>
        <button id="ai-send-btn"
          style="background:#7c3aed;color:#fff;border:none;border-radius:8px;padding:8px 14px;cursor:pointer;font-weight:700;align-self:flex-end;flex-shrink:0;">→</button>
      </div>
    </div>
  `;
  document.body.appendChild(widget);

  function loadMarked(cb) {
    if (typeof marked !== 'undefined') { cb(); return; }
    const s = document.createElement('script');
    s.src = 'https://cdn.jsdelivr.net/npm/marked/marked.min.js';
    s.onload = cb;
    document.head.appendChild(s);
  }

  const panel   = document.getElementById('ai-chat-panel');
  const toggle  = document.getElementById('ai-chat-toggle');
  const input   = document.getElementById('ai-input');
  const sendBtn = document.getElementById('ai-send-btn');
  const msgs    = document.getElementById('ai-messages');
  const typing  = document.getElementById('ai-typing');
  let open = false;

  function togglePanel() {
    open = !open;
    panel.style.display = open ? 'flex' : 'none';
    toggle.style.display = open ? 'none' : 'flex';
    if (open) { loadHistory(); input.focus(); }
  }

  toggle.addEventListener('click', togglePanel);
  document.getElementById('ai-close-btn').addEventListener('click', () => {
    open = false; panel.style.display = 'none'; toggle.style.display = 'flex';
  });

  function addMessage(role, content) {
    const div = document.createElement('div');
    const isUser = role === 'user';
    div.style.cssText = `max-width:85%;padding:10px 12px;border-radius:12px;font-size:13px;line-height:1.5;word-break:break-word;
      ${isUser ? 'align-self:flex-end;background:#7c3aed;color:#fff;' : 'align-self:flex-start;background:#f3f4f6;color:#1f2937;'}`;
    if (isUser) {
      div.textContent = content;
    } else {
      loadMarked(() => { div.innerHTML = (typeof marked !== 'undefined') ? marked.parse(content) : content; });
      const copy = document.createElement('button');
      copy.textContent = 'Copiar';
      copy.style.cssText = 'display:block;margin-top:6px;font-size:11px;border:1px solid #d1d5db;background:#fff;color:#374151;padding:2px 8px;border-radius:4px;cursor:pointer;';
      copy.onclick = () => navigator.clipboard.writeText(content).then(() => {
        copy.textContent = 'Copiado!';
        setTimeout(() => { copy.textContent = 'Copiar'; }, 2000);
      });
      div.appendChild(copy);
    }
    msgs.appendChild(div);
    msgs.scrollTop = msgs.scrollHeight;
  }

  async function loadHistory() {
    try {
      const r = await fetch(`${BASE}/api/ai-chat.php?action=historico`);
      const j = await r.json();
      if (!j.success) return;
      msgs.innerHTML = '';
      j.data.forEach(m => addMessage(m.role, m.content));
    } catch {}
  }

  async function sendMessage() {
    const msg = input.value.trim();
    if (!msg) return;
    input.value = '';
    addMessage('user', msg);
    typing.style.display = 'block';
    sendBtn.disabled = true;
    try {
      const r = await fetch(`${BASE}/api/ai-chat.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'enviar', message: msg }),
      });
      const j = await r.json();
      typing.style.display = 'none';
      sendBtn.disabled = false;
      addMessage('assistant', j.success ? j.data.response : '⚠️ ' + (j.error || 'Erro.'));
    } catch (e) {
      typing.style.display = 'none';
      sendBtn.disabled = false;
      addMessage('assistant', '⚠️ Erro de comunicação: ' + e.message);
    }
  }

  sendBtn.addEventListener('click', sendMessage);
  input.addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
  });

  document.getElementById('ai-clear-btn').addEventListener('click', async () => {
    if (!confirm('Limpar histórico desta sessão?')) return;
    await fetch(`${BASE}/api/ai-chat.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'limpar' }),
    });
    msgs.innerHTML = '';
  });

  document.getElementById('ai-export-btn').addEventListener('click', () => {
    window.open(`${BASE}/api/ai-chat.php?action=exportar`, '_blank');
  });
})();
