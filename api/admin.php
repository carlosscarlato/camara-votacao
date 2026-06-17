<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/logger.php';
require_once __DIR__ . '/../config/bootstrap.php';

setCorsHeaders();
startSession();
resolveTenant();
$auth = requireAdminAuth();

function requireAdmin(array $auth): void
{
    if ($auth['perfil'] !== 'admin') {
        jsonError('Apenas administradores podem executar esta ação.', 403);
    }
}

$action = getAction();

switch ($action) {

    // ────────────────────────────────────────────────────────
    //  USUÁRIOS
    // ────────────────────────────────────────────────────────

    case 'listar_usuarios':
        requireAdmin($auth);
        $stmt = db()->prepare("
            SELECT id, nome, login, email, perfil, permissao_level, ativo, created_at
            FROM   usuarios WHERE tenant_id = ? ORDER BY nome
        ");
        $stmt->execute([tenantId()]);
        jsonSuccess($stmt->fetchAll());

    case 'criar_usuario':
        requireAdmin($auth);
        $nome   = trim((string)requiredInput('nome'));
        $login  = trim((string)requiredInput('login'));
        $email  = trim((string)input('email', '')) ?: null;
        $senha  = (string)requiredInput('senha');
        $perfil = (string)input('perfil', 'operador');

        if (!in_array($perfil, ['admin', 'operador'], true)) {
            jsonError('Perfil inválido. Use admin ou operador.');
        }
        if (strlen($senha) < 8) jsonError('Senha mínima: 8 caracteres.');

        $hash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);
        try {
            db()->prepare("
                INSERT INTO usuarios (tenant_id, nome, login, email, senha_hash, perfil)
                VALUES (?, ?, ?, ?, ?, ?)
            ")->execute([tenantId(), $nome, $login, $email, $hash, $perfil]);
        } catch (\PDOException) {
            jsonError('Login ou e-mail já cadastrado.', 409);
        }
        $newId = (int)db()->lastInsertId();
        registrarLog('usuario_criado', $auth['id'], null, "id: $newId login: $login");
        jsonSuccess(['id' => $newId, 'message' => 'Usuário criado com sucesso.']);

    case 'editar_usuario':
        requireAdmin($auth);
        $id     = (int)requiredInput('id');
        $nome   = trim((string)requiredInput('nome'));
        $perfil = (string)input('perfil', 'operador');
        $ativo  = (int)(bool)input('ativo', true);
        $email  = trim((string)input('email', '')) ?: null;

        if (!in_array($perfil, ['admin', 'operador'], true)) {
            jsonError('Perfil inválido.');
        }
        db()->prepare("
            UPDATE usuarios SET nome = ?, perfil = ?, ativo = ?, email = ? WHERE id = ?
        ")->execute([$nome, $perfil, $ativo, $email, $id]);
        registrarLog('usuario_editado', $auth['id'], null, "id: $id");
        jsonSuccess(['message' => 'Usuário atualizado.']);

    case 'resetar_senha':
        requireAdmin($auth);
        $id    = (int)requiredInput('id');
        $senha = (string)requiredInput('senha');
        if (strlen($senha) < 8) jsonError('Senha mínima: 8 caracteres.');
        $hash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);
        db()->prepare("UPDATE usuarios SET senha_hash = ? WHERE id = ?")
            ->execute([$hash, $id]);
        registrarLog('senha_resetada_admin', $auth['id'], null, "usuario_id: $id");
        jsonSuccess(['message' => 'Senha resetada com sucesso.']);

    // ────────────────────────────────────────────────────────
    //  VEREADORES
    // ────────────────────────────────────────────────────────

    case 'listar_vereadores':
        $stmt = db()->prepare("
            SELECT id, nome, partido, email, cargo_id, status, pin, foto, created_at
            FROM   vereadores WHERE tenant_id = ? ORDER BY nome
        ");
        $stmt->execute([tenantId()]);
        jsonSuccess($stmt->fetchAll());

    case 'criar_vereador':
        requireAdmin($auth);
        $nome    = trim((string)requiredInput('nome'));
        $partido = trim((string)requiredInput('partido'));
        $pin     = trim((string)input('pin', '123456'));
        $email   = trim((string)input('email', '')) ?: null;
        $cargo   = (int)input('cargo_id', 1);

        if (!preg_match('/^\d{6}$/', $pin)) jsonError('PIN deve ter exatamente 6 dígitos.');

        db()->prepare("
            INSERT INTO vereadores (tenant_id, nome, partido, pin, email, cargo_id) VALUES (?,?,?,?,?,?)
        ")->execute([tenantId(), $nome, $partido, $pin, $email, $cargo]);
        $newId = (int)db()->lastInsertId();
        registrarLog('vereador_criado', $auth['id'], $newId, "nome: $nome");
        jsonSuccess(['id' => $newId, 'message' => 'Vereador criado com sucesso.']);

    case 'editar_vereador':
        requireAdmin($auth);
        $id      = (int)requiredInput('id');
        $nome    = trim((string)requiredInput('nome'));
        $partido = trim((string)requiredInput('partido'));
        $status  = (string)input('status', 'ativo');
        $pin     = trim((string)input('pin', ''));
        $email   = trim((string)input('email', '')) ?: null;
        $cargo   = (int)input('cargo_id', 1);

        if (!in_array($status, ['ativo', 'inativo'], true)) jsonError('Status inválido.');
        if ($pin !== '' && !preg_match('/^\d{6}$/', $pin)) jsonError('PIN deve ter 6 dígitos.');

        if ($pin !== '') {
            db()->prepare("
                UPDATE vereadores SET nome=?,partido=?,status=?,pin=?,email=?,cargo_id=? WHERE id=?
            ")->execute([$nome, $partido, $status, $pin, $email, $cargo, $id]);
        } else {
            db()->prepare("
                UPDATE vereadores SET nome=?,partido=?,status=?,email=?,cargo_id=? WHERE id=?
            ")->execute([$nome, $partido, $status, $email, $cargo, $id]);
        }
        registrarLog('vereador_editado', $auth['id'], $id);
        jsonSuccess(['message' => 'Vereador atualizado.']);

    case 'upload_foto_vereador':
        requireAdmin($auth);
        $id = (int)requiredInput('id');

        if (empty($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
            jsonError('Nenhum arquivo enviado.');
        }

        $file = $_FILES['foto'];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'svg'], true)) {
            jsonError('Formato inválido. Use JPG, PNG, WebP ou SVG.');
        }
        if ($file['size'] > 3 * 1024 * 1024) {
            jsonError('Arquivo muito grande. Máximo 3 MB.');
        }

        $dir      = __DIR__ . '/../public/assets/img/vereadores/';
        $filename = "vereador_{$id}.{$ext}";
        if (!move_uploaded_file($file['tmp_name'], $dir . $filename)) {
            jsonError('Erro ao salvar o arquivo no servidor.');
        }

        $fotoPath = "assets/img/vereadores/{$filename}";
        db()->prepare("UPDATE vereadores SET foto = ? WHERE id = ?")->execute([$fotoPath, $id]);
        registrarLog('foto_vereador_atualizada', $auth['id'], $id);
        jsonSuccess(['foto' => $fotoPath, 'message' => 'Foto atualizada com sucesso.']);

    case 'deletar_vereador':
        requireAdmin($auth);
        $id = (int)requiredInput('id');

        $stmtV = db()->prepare("SELECT COUNT(*) FROM votos WHERE vereador_id = ?");
        $stmtV->execute([$id]);
        if ((int)$stmtV->fetchColumn() > 0) {
            jsonError('Não é possível excluir: vereador possui votos registrados. Use "Inativar" para desabilitar o acesso.', 409);
        }

        $stmtT = db()->prepare("SELECT COUNT(*) FROM controle_tribuna WHERE vereador_id = ?");
        $stmtT->execute([$id]);
        if ((int)$stmtT->fetchColumn() > 0) {
            jsonError('Não é possível excluir: vereador possui histórico de tribuna. Use "Inativar" para desabilitar o acesso.', 409);
        }

        $stmtN = db()->prepare("SELECT nome FROM vereadores WHERE id = ? AND tenant_id = ?");
        $stmtN->execute([$id, tenantId()]);
        $vereador = $stmtN->fetch();
        if (!$vereador) jsonError('Vereador não encontrado.', 404);

        db()->prepare("DELETE FROM vereadores WHERE id = ? AND tenant_id = ?")->execute([$id, tenantId()]);
        registrarLog('vereador_deletado', $auth['id'], null, "id: $id nome: {$vereador['nome']}");
        jsonSuccess(['message' => 'Vereador excluído com sucesso.']);

    // ────────────────────────────────────────────────────────
    //  LOGS DE AUDITORIA
    // ────────────────────────────────────────────────────────

    case 'listar_logs':
        requireAdmin($auth);
        $limit  = min((int)input('limit', 100), 500);
        $offset = (int)input('offset', 0);

        $tid2 = tenantId();
        $stmt = db()->prepare("
            SELECT l.id, l.acao, l.detalhes, l.ip_origem, l.created_at,
                   u.nome AS usuario_nome,
                   v.nome AS vereador_nome
            FROM   logs_sistema l
            LEFT JOIN usuarios   u ON u.id = l.usuario_id
            LEFT JOIN vereadores v ON v.id = l.vereador_id
            WHERE  l.tenant_id = ?
            ORDER  BY l.id DESC
            LIMIT  ? OFFSET ?
        ");
        $stmt->execute([$tid2, $limit, $offset]);
        $tcnt = db()->prepare("SELECT COUNT(*) FROM logs_sistema WHERE tenant_id = ?");
        $tcnt->execute([$tid2]);
        $total = (int)$tcnt->fetchColumn();
        jsonSuccess(['logs' => $stmt->fetchAll(), 'total' => $total]);

    default:
        jsonError('Ação inválida.');
}
