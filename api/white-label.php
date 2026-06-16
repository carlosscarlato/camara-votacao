<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/logger.php';
require_once __DIR__ . '/../config/bootstrap.php';

setCorsHeaders();
startSession();
resolveTenant();
requireAdminAuth();

$action = getAction();
$tid    = tenantId();

switch ($action) {

    case 'obter':
        $stmt = db()->prepare("SELECT * FROM tenant_settings WHERE tenant_id = ?");
        $stmt->execute([$tid]);
        jsonSuccess($stmt->fetch());

    case 'salvar':
        $fields = ['primary_color','secondary_color','accent_color','font_family',
                   'company_name','slogan','cnpj','address','number','complement',
                   'neighborhood','city','state','zip','country','phone','whatsapp',
                   'email_contact','website','social_linkedin','social_instagram',
                   'social_youtube','social_facebook','custom_css','email_footer_text',
                   'terms_of_use_url','privacy_policy_url'];

        $set  = implode(', ', array_map(fn($f) => "`$f` = ?", $fields));
        $vals = array_map(fn($f) => input($f), $fields);
        $vals[] = $tid;

        db()->prepare("UPDATE tenant_settings SET $set WHERE tenant_id = ?")->execute($vals);
        registrarLog('white_label_salvo', (int)$_SESSION['id'], null, "tenant:$tid");
        jsonSuccess(['message' => 'Configurações visuais salvas.']);

    case 'upload_logo':
        $tipo = (string)input('tipo', 'logo');
        if (!in_array($tipo, ['logo','favicon','login_background'], true)) jsonError('Tipo inválido.');

        if (empty($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
            jsonError('Nenhum arquivo enviado.');
        }
        $file    = $_FILES['arquivo'];
        $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = $tipo === 'favicon' ? ['ico','png','svg'] : ['png','jpg','jpeg','svg','webp'];
        if (!in_array($ext, $allowed, true)) jsonError("Formato inválido para $tipo.");
        if ($file['size'] > 5 * 1024 * 1024) jsonError('Arquivo muito grande. Máximo 5 MB.');

        $dir = __DIR__ . "/../public/assets/tenants/{$tid}/";
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $filename = "{$tipo}_{$tid}.{$ext}";
        if (!move_uploaded_file($file['tmp_name'], $dir . $filename)) jsonError('Erro ao salvar arquivo.');

        $col  = match ($tipo) {
            'logo'             => 'logo_path',
            'favicon'          => 'favicon_path',
            'login_background' => 'login_background_image',
        };
        $path = "assets/tenants/{$tid}/{$filename}";
        db()->prepare("UPDATE tenant_settings SET `$col` = ? WHERE tenant_id = ?")
            ->execute([$path, $tid]);

        jsonSuccess(['path' => $path, 'message' => 'Upload realizado com sucesso.']);

    default:
        jsonError('Ação inválida.');
}
