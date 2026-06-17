<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

function resolveTenant(): void
{
    $host = strtolower(trim($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $host = explode(':', $host)[0];

    $stmt = db()->prepare("
        SELECT t.id, t.name, t.slug, t.plan, t.status,
               ts.primary_color, ts.secondary_color, ts.accent_color,
               ts.font_family, ts.company_name, ts.logo_path,
               ts.favicon_path, ts.custom_css, ts.show_powered_by,
               ts.session_timeout_minutes
        FROM   tenant_domains td
        JOIN   tenants t          ON t.id = td.tenant_id
        LEFT JOIN tenant_settings ts ON ts.tenant_id = t.id
        WHERE  td.domain = ?
        LIMIT  1
    ");
    $stmt->execute([$host]);
    $tenant = $stmt->fetch();

    if (!$tenant) {
        $stmt = db()->prepare("
            SELECT t.id, t.name, t.slug, t.plan, t.status,
                   ts.primary_color, ts.secondary_color, ts.accent_color,
                   ts.font_family, ts.company_name, ts.logo_path,
                   ts.favicon_path, ts.custom_css, ts.show_powered_by,
                   ts.session_timeout_minutes
            FROM   tenants t
            LEFT JOIN tenant_settings ts ON ts.tenant_id = t.id
            WHERE  t.id = 1 LIMIT 1
        ");
        $stmt->execute();
        $tenant = $stmt->fetch();
    }

    if (!$tenant || $tenant['status'] !== 'active') {
        http_response_code(503);
        die(json_encode(['success' => false, 'error' => 'Serviço indisponível.'], JSON_UNESCAPED_UNICODE));
    }

    if (!defined('TENANT_ID'))   define('TENANT_ID',   (int)$tenant['id']);
    if (!defined('TENANT_SLUG')) define('TENANT_SLUG', (string)$tenant['slug']);
    if (!defined('TENANT_PLAN')) define('TENANT_PLAN', (string)$tenant['plan']);

    $_SESSION['tenant_id']   = (int)$tenant['id'];
    $_SESSION['tenant_data'] = $tenant;
}

function getTenantId(): int
{
    return defined('TENANT_ID') ? TENANT_ID : 1;
}

function getTenantData(): array
{
    return $_SESSION['tenant_data'] ?? [];
}
