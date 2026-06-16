<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/bootstrap.php';
if (session_status() === PHP_SESSION_NONE) session_start();
resolveTenant();

$d = getTenantData();

header('Content-Type: text/css; charset=utf-8');
header('Cache-Control: public, max-age=300');

echo ":root {\n";
echo "  --color-primary:   " . htmlspecialchars($d['primary_color']   ?? '#1e3a5f') . ";\n";
echo "  --color-secondary: " . htmlspecialchars($d['secondary_color'] ?? '#3b82f6') . ";\n";
echo "  --color-accent:    " . htmlspecialchars($d['accent_color']    ?? '#10b981') . ";\n";
echo "  --font-family:     " . htmlspecialchars($d['font_family']     ?? 'Segoe UI, Arial, sans-serif') . ";\n";
echo "}\n";
echo "body { font-family: var(--font-family); }\n";

if (!empty($d['custom_css'])) {
    echo "\n/* CSS personalizado do tenant */\n";
    echo $d['custom_css'];
}
