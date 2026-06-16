<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/logger.php';
require_once __DIR__ . '/../config/bootstrap.php';

setCorsHeaders();
startSession();
resolveTenant();
$auth = requireAdminAuth();

$ordemDiaId = (int)($_GET['ordem_dia_id'] ?? 0);
if (!$ordemDiaId) jsonError('Parâmetro ordem_dia_id obrigatório.');

// ── Buscar dados da votação ───────────────────────────────────
$stmt = db()->prepare("
    SELECT od.*,
           p.tipo   AS prop_tipo,
           p.numero AS prop_numero,
           p.ano    AS prop_ano,
           p.ementa, p.autor,
           s.numero AS sessao_numero,
           s.data   AS sessao_data,
           s.tipo   AS sessao_tipo
    FROM   ordem_do_dia od
    JOIN   proposicoes        p ON p.id = od.proposicao_id
    JOIN   sessoes_plenarias  s ON s.id = od.sessao_id
    WHERE  od.id = ?
");
$stmt->execute([$ordemDiaId]);
$votacao = $stmt->fetch();
if (!$votacao) jsonError('Votação não encontrada.', 404);

// ── Buscar votos nominais ─────────────────────────────────────
$stmtV = db()->prepare("
    SELECT vt.voto, ver.nome, ver.partido
    FROM   votos vt
    JOIN   vereadores ver ON ver.id = vt.vereador_id
    WHERE  vt.ordem_dia_id = ?
    ORDER  BY ver.nome
");
$stmtV->execute([$ordemDiaId]);
$votos = $stmtV->fetchAll();

// ── Gerar HTML da ata ─────────────────────────────────────────
$dataFormatada = date('d/m/Y', strtotime($votacao['sessao_data']));
$agora         = date('d/m/Y H:i:s');
$sessaoTipo    = ucfirst($votacao['sessao_tipo']);

$resultadoLabels = [
    'aprovado'   => 'APROVADO',
    'rejeitado'  => 'REJEITADO',
    'empate'     => 'EMPATE',
    'nao_votado' => 'NÃO VOTADO',
];
$resultadoTexto = $resultadoLabels[$votacao['resultado']] ?? strtoupper((string)$votacao['resultado']);
$resultadoCss   = match($votacao['resultado']) {
    'aprovado'  => 'color:#166534;background:#dcfce7;border:2px solid #86efac',
    'rejeitado' => 'color:#991b1b;background:#fee2e2;border:2px solid #fca5a5',
    'empate'    => 'color:#1e40af;background:#dbeafe;border:2px solid #93c5fd',
    default     => 'color:#475569;background:#f1f5f9;border:2px solid #cbd5e1',
};

$linhasVotos = '';
foreach ($votos as $v) {
    [$icon, $cor] = match($v['voto']) {
        'SIM'       => ['✔ SIM',      'color:#166534'],
        'NAO'       => ['✗ NÃO',      'color:#991b1b'],
        'ABSTENCAO' => ['≈ ABSTENCAO','color:#854d0e'],
        default     => ['— AUSENTE',  'color:#94a3b8'],
    };
    $linhasVotos .= "
        <tr>
          <td style='padding:7px 14px;border-bottom:1px solid #e2e8f0'>{$v['nome']}</td>
          <td style='padding:7px 14px;border-bottom:1px solid #e2e8f0;color:#64748b'>{$v['partido']}</td>
          <td style='padding:7px 14px;border-bottom:1px solid #e2e8f0;font-weight:700;$cor'>$icon</td>
        </tr>";
}

$html = <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Ata de Votação — {$votacao['prop_tipo']} nº {$votacao['prop_numero']}/{$votacao['prop_ano']}</title>
<style>
  @media print { body { margin: 0; } .no-print { display: none !important; } }
  body  { font-family: 'Arial', sans-serif; font-size: 12px; color: #1e293b; margin: 0; padding: 40px; }
  h1    { font-size: 20px; text-align: center; color: #1e3a5f; margin-bottom: 4px; letter-spacing: 1px; }
  h2    { font-size: 13px; text-align: center; color: #64748b; font-weight: normal; margin-top: 0; margin-bottom: 24px; }
  .meta { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 18px; margin: 16px 0; line-height: 1.8; }
  .resultado { font-size: 22px; font-weight: 900; text-align: center; padding: 12px 20px; border-radius: 10px; margin: 18px 0; letter-spacing: 2px; }
  .placar   { display: flex; gap: 16px; justify-content: center; margin: 12px 0 20px; }
  .placar span { padding: 8px 20px; border-radius: 8px; font-weight: 700; font-size: 13px; }
  .sim  { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
  .nao  { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
  .abst { background: #fef9c3; color: #854d0e; border: 1px solid #fde047; }
  .ause { background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1; }
  table { width: 100%; border-collapse: collapse; margin-top: 12px; }
  thead th { background: #1e3a5f; color: #fff; padding: 9px 14px; text-align: left; font-size: 11px; letter-spacing: .5px; }
  .footer { text-align: center; margin-top: 36px; padding-top: 16px; border-top: 1px solid #e2e8f0;
            color: #94a3b8; font-size: 10px; line-height: 1.6; }
  .btn-print { display: inline-block; background: #1e3a5f; color: #fff; padding: 10px 24px;
               border-radius: 8px; cursor: pointer; border: none; font-size: 13px; font-weight: 700;
               margin-bottom: 20px; }
</style>
</head>
<body>

<div class="no-print" style="text-align:center;margin-bottom:16px">
  <button class="btn-print" onclick="window.print()">🖨️ Imprimir / Salvar PDF</button>
  <button class="btn-print" style="background:#475569;margin-left:8px" onclick="window.close()">✕ Fechar</button>
</div>

<h1>🏛️ CÂMARA MUNICIPAL</h1>
<h2>ATA DE VOTAÇÃO — Sessão $sessaoTipo nº {$votacao['sessao_numero']} — $dataFormatada</h2>

<div class="meta">
  <strong>Proposição:</strong> {$votacao['prop_tipo']} nº {$votacao['prop_numero']}/{$votacao['prop_ano']}<br>
  <strong>Ementa:</strong> {$votacao['ementa']}<br>
  <strong>Autoria:</strong> {$votacao['autor']}<br>
  <strong>Tipo de Votação:</strong> {$votacao['tipo_votacao']}<br>
  <strong>Abertura:</strong> {$votacao['aberto_em']} &nbsp;|&nbsp;
  <strong>Encerramento:</strong> {$votacao['encerrado_em']}
</div>

<div class="resultado" style="{$resultadoCss}">$resultadoTexto</div>

<div class="placar">
  <span class="sim">✔ SIM: {$votacao['votos_sim']}</span>
  <span class="nao">✗ NÃO: {$votacao['votos_nao']}</span>
  <span class="abst">≈ ABST.: {$votacao['votos_abstencao']}</span>
  <span class="ause">— AUSENTES: {$votacao['votos_ausente']}</span>
</div>

<h3 style="color:#1e3a5f;border-bottom:2px solid #1e3a5f;padding-bottom:6px">Votação Nominal</h3>
<table>
  <thead>
    <tr><th>Vereador</th><th>Partido</th><th>Voto</th></tr>
  </thead>
  <tbody>$linhasVotos</tbody>
</table>

<div class="footer">
  Documento gerado em $agora &nbsp;|&nbsp; Sistema de Votação Eletrônica &nbsp;|&nbsp; Câmara Municipal<br>
  Este documento tem validade oficial conforme Lei Municipal de Transparência.
</div>
</body>
</html>
HTML;

registrarLog('ata_gerada', $auth['id'], null, "ordem_dia_id: $ordemDiaId");

// ── Formato: html (padrão) ou pdf via Dompdf ─────────────────
$formato = strtolower($_GET['formato'] ?? 'html');

if ($formato === 'pdf') {
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
        $dompdf = new \Dompdf\Dompdf(['enable_remote' => true, 'chroot' => __DIR__ . '/../public']);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $filename = "ata_votacao_{$ordemDiaId}_" . date('Ymd') . ".pdf";
        $dompdf->stream($filename, ['Attachment' => true]);
        exit;
    }
    // Fallback: abre HTML com hint para imprimir como PDF
}

header('Content-Type: text/html; charset=utf-8');
echo $html;
