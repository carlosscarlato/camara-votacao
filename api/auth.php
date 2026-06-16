<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/logger.php';

setCorsHeaders();
startSession();

$action = getAction();

switch ($action) {

    // ── Login: Vereador (PIN) ────────────────────────────────
    case 'login_vereador':
        checkRateLimit('login_vereador_falhou', 10, 300);
        $vereadorId = (int)requiredInput('vereador_id');
        $pin        = (string)requiredInput('pin');

        $stmt = db()->prepare(
            "SELECT id, nome, partido, foto, pin FROM vereadores WHERE id = ? AND status = 'ativo'"
        );
        $stmt->execute([$vereadorId]);
        $vereador = $stmt->fetch();

        if (!$vereador || $vereador['pin'] !== $pin) {
            registrarLog('login_vereador_falhou', null, $vereadorId, "pin incorreto");
            jsonError('ID ou PIN inválido.', 401);
        }

        session_regenerate_id(true);
        $_SESSION['tipo']    = 'vereador';
        $_SESSION['id']      = $vereador['id'];
        $_SESSION['nome']    = $vereador['nome'];
        $_SESSION['partido'] = $vereador['partido'];
        $_SESSION['foto']    = $vereador['foto'];

        registrarLog('login_vereador', null, $vereador['id']);
        jsonSuccess([
            'id'      => $vereador['id'],
            'nome'    => $vereador['nome'],
            'partido' => $vereador['partido'],
            'foto'    => $vereador['foto'],
        ]);

    // ── Login: Admin/Operador (login+senha) ──────────────────
    case 'login_admin':
        checkRateLimit('login_admin_falhou', 5, 300);
        $loginInput = (string)requiredInput('login');
        $senha      = (string)requiredInput('senha');
        $remember   = (bool)input('remember', false);

        $stmt = db()->prepare(
            "SELECT id, nome, login, senha_hash, perfil FROM usuarios WHERE login = ? AND ativo = 1"
        );
        $stmt->execute([$loginInput]);
        $usuario = $stmt->fetch();

        if (!$usuario || !password_verify($senha, $usuario['senha_hash'])) {
            registrarLog('login_admin_falhou', null, null, "login: $loginInput");
            jsonError('Credenciais inválidas.', 401);
        }

        session_regenerate_id(true);
        $_SESSION['tipo']  = $usuario['perfil'];
        $_SESSION['id']    = $usuario['id'];
        $_SESSION['nome']  = $usuario['nome'];

        // ── Remember-me: cookie seguro por 30 dias ───────────
        if ($remember) {
            $token = bin2hex(random_bytes(32));
            setcookie('remember_token', $token, [
                'expires'  => time() + 30 * 86400,
                'path'     => '/',
                'httponly' => true,
                'secure'   => isProduction(),
                'samesite' => 'Strict',
            ]);
            db()->prepare(
                "UPDATE usuarios SET token_recuperacao = ?, token_expira_em = DATE_ADD(NOW(), INTERVAL 30 DAY) WHERE id = ?"
            )->execute([$token, $usuario['id']]);
        }

        registrarLog('login_admin', $usuario['id'], null, "perfil: {$usuario['perfil']}");
        jsonSuccess([
            'id'     => $usuario['id'],
            'nome'   => $usuario['nome'],
            'perfil' => $usuario['perfil'],
        ]);

    // ── Logout ────────────────────────────────────────────────
    case 'logout':
        registrarLog('logout', (int)($_SESSION['id'] ?? 0));
        if (!empty($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', ['expires' => time() - 3600, 'path' => '/']);
        }
        session_unset();
        session_destroy();
        jsonSuccess(['message' => 'Logout realizado.']);

    // ── Status da sessão atual ────────────────────────────────
    case 'status':
        jsonSuccess(getAuthInfo());

    // ── Lista de vereadores para a tela de login ─────────────
    case 'lista_vereadores':
        $stmt = db()->query(
            "SELECT id, nome, partido, foto FROM vereadores WHERE status = 'ativo' ORDER BY nome"
        );
        jsonSuccess($stmt->fetchAll());

    // ── Esqueci a senha (solicitar token) ─────────────────────
    case 'esqueci_senha':
        $email = trim((string)requiredInput('email'));

        $stmt = db()->prepare("SELECT id, nome FROM usuarios WHERE email = ? AND ativo = 1");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();

        // Sempre retorna sucesso (não revelar se e-mail existe)
        if ($usuario) {
            $token  = bin2hex(random_bytes(32));
            $expira = date('Y-m-d H:i:s', strtotime('+2 hours'));
            db()->prepare(
                "UPDATE usuarios SET token_recuperacao = ?, token_expira_em = ? WHERE id = ?"
            )->execute([$token, $expira, $usuario['id']]);

            // TODO: integrar PHPMailer/SMTP para envio real
            // Exemplo de link: BASE_URL . "/public/recuperar-senha/?token=$token"
            registrarLog('recuperacao_senha_solicitada', $usuario['id'], null, "email: $email");
        }
        jsonSuccess(['message' => 'Se o e-mail estiver cadastrado, você receberá um link em breve.']);

    // ── Redefinir senha via token ─────────────────────────────
    case 'redefinir_senha':
        $token     = trim((string)requiredInput('token'));
        $novaSenha = (string)requiredInput('nova_senha');

        if (strlen($novaSenha) < 8) {
            jsonError('A senha deve ter ao menos 8 caracteres.');
        }

        $stmt = db()->prepare("
            SELECT id FROM usuarios
            WHERE token_recuperacao = ? AND token_expira_em > NOW() AND ativo = 1
        ");
        $stmt->execute([$token]);
        $usuario = $stmt->fetch();

        if (!$usuario) jsonError('Token inválido ou expirado.', 400);

        $hash = password_hash($novaSenha, PASSWORD_BCRYPT, ['cost' => 12]);
        db()->prepare("
            UPDATE usuarios
            SET    senha_hash = ?, token_recuperacao = NULL, token_expira_em = NULL
            WHERE  id = ?
        ")->execute([$hash, $usuario['id']]);

        registrarLog('senha_redefinida', $usuario['id']);
        jsonSuccess(['message' => 'Senha redefinida com sucesso.']);

    // ── Verificar token de recuperação (validade) ─────────────
    case 'verificar_token':
        $token = trim((string)requiredInput('token'));
        $stmt  = db()->prepare("
            SELECT id FROM usuarios
            WHERE token_recuperacao = ? AND token_expira_em > NOW() AND ativo = 1
        ");
        $stmt->execute([$token]);
        $valid = (bool)$stmt->fetch();
        jsonSuccess(['valido' => $valid]);

    default:
        jsonError('Ação inválida.');
}
