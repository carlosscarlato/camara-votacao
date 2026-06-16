<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/logger.php';
require_once __DIR__ . '/../config/bootstrap.php';

setCorsHeaders();
startSession();
resolveTenant();
requireSuperAdmin();

$action = getAction();

switch ($action) {

    case 'listar':
        $rows = db()->query("
            SELECT t.*, ts.company_name, ts.primary_color,
                   (SELECT COUNT(*) FROM usuarios u WHERE u.tenant_id = t.id) AS total_usuarios
            FROM   tenants t
            LEFT JOIN tenant_settings ts ON ts.tenant_id = t.id
            ORDER  BY t.created_at DESC
        ")->fetchAll();
        jsonSuccess($rows);

    case 'criar':
        $name   = trim((string)requiredInput('name'));
        $slug   = strtolower(trim(preg_replace('/[^a-z0-9-]+/', '-', (string)requiredInput('slug'))));
        $plan   = (string)input('plan', 'free');
        $domain = trim((string)requiredInput('domain'));

        if (!in_array($plan, ['free','starter','pro','enterprise'], true)) {
            jsonError('Plano inválido.');
        }

        db()->beginTransaction();
        try {
            db()->prepare("INSERT INTO tenants (name, slug, plan) VALUES (?,?,?)")
                ->execute([$name, $slug, $plan]);
            $tenantId = (int)db()->lastInsertId();

            db()->prepare("INSERT INTO tenant_settings (tenant_id) VALUES (?)")
                ->execute([$tenantId]);

            db()->prepare("INSERT INTO tenant_domains (tenant_id, domain, is_primary) VALUES (?,?,1)")
                ->execute([$tenantId, $domain]);

            db()->commit();
        } catch (\Throwable) {
            db()->rollBack();
            jsonError('Slug ou domínio já existe.', 409);
        }

        registrarLog('tenant_criado', (int)$_SESSION['id'], null, "id:$tenantId slug:$slug");
        jsonSuccess(['id' => $tenantId, 'message' => 'Tenant criado com sucesso.']);

    case 'editar':
        $id     = (int)requiredInput('id');
        $name   = trim((string)requiredInput('name'));
        $plan   = (string)input('plan', 'free');
        $status = (string)input('status', 'active');

        if (!in_array($status, ['active','inactive','suspended'], true)) jsonError('Status inválido.');
        if (!in_array($plan, ['free','starter','pro','enterprise'], true)) jsonError('Plano inválido.');

        db()->prepare("UPDATE tenants SET name=?, plan=?, status=? WHERE id=?")
            ->execute([$name, $plan, $status, $id]);
        registrarLog('tenant_editado', (int)$_SESSION['id'], null, "id:$id");
        jsonSuccess(['message' => 'Tenant atualizado.']);

    case 'super_admin_settings':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $fields = ['fantasy_name','company_name','cnpj','address','number','complement',
                       'neighborhood','city','state','zip','phone','whatsapp','email_support',
                       'website','copyright_text','privacy_policy_url','terms_url',
                       'smtp_from_name','smtp_from_email'];
            $set  = implode(', ', array_map(fn($f) => "`$f` = ?", $fields));
            $vals = array_map(fn($f) => input($f), $fields);
            $vals[] = 1;
            db()->prepare("UPDATE super_admin_settings SET $set WHERE id = ?")->execute($vals);
            jsonSuccess(['message' => 'Configurações salvas.']);
        }
        $row = db()->query("SELECT * FROM super_admin_settings WHERE id = 1")->fetch();
        jsonSuccess($row);

    default:
        jsonError('Ação inválida.');
}
