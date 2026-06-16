// public/assets/js/powered-by.js — Rodapé "Powered by" controlado pelo tenant
(async () => {
  try {
    const BASE = window.APP_BASE || '../..';
    const rWl = await fetch(`${BASE}/api/white-label.php?action=obter`);
    const jWl = await rWl.json();
    if (!jWl.success || !jWl.data || !jWl.data.show_powered_by) return;

    const rSa = await fetch(`${BASE}/api/tenants.php?action=super_admin_settings`);
    const jSa = await rSa.json();
    if (!jSa.success) return;

    const { copyright_text, website } = jSa.data;
    const year = new Date().getFullYear();
    const txt  = (copyright_text || 'Powered by WebVoto').replace(/\[ano\]/gi, year).replace(/\[year\]/gi, year);
    const url  = website || '#';

    const div = document.createElement('div');
    div.style.cssText = [
      'position:fixed', 'bottom:0', 'left:0', 'right:0', 'text-align:center',
      'font-size:11px', 'color:#94a3b8', 'padding:4px 8px',
      'background:rgba(255,255,255,0.9)', 'z-index:998',
      'border-top:1px solid #f1f5f9',
    ].join(';');
    div.innerHTML = `<a href="${url}" target="_blank" rel="noopener" style="color:inherit;text-decoration:none;">${txt} &copy; ${year}</a>`;
    document.body.appendChild(div);
  } catch {}
})();
