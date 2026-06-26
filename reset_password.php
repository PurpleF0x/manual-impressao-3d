<?php
/**
 * reset_password.php
 */
require_once 'includes/functions.php';

if (isLoggedIn()) redirect('index.php');

$db = getDB();
$token = $_GET['token'] ?? $_POST['token'] ?? '';
$errors = [];
$success = false;

if (empty($token)) {
    redirect('login.php');
}

// Verificar validade do token
$stmt = $db->prepare("SELECT * FROM password_resets WHERE token = ? AND used = 0 AND expires_at > NOW() LIMIT 1");
$stmt->execute([$token]);
$reset = $stmt->fetch();

if (!$reset) {
    $errors[] = "O link de recuperação é inválido ou já expirou.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $reset) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Token de segurança inválido.';
    } else {
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        if (strlen($password) < 8) {
            $errors[] = "A nova palavra-passe deve ter pelo menos 8 caracteres.";
        } elseif ($password !== $confirm) {
            $errors[] = "As palavras-passe não coincidem.";
        } else {
            $db->beginTransaction();
            try {
                $hash = hashPassword($password);

                // Atualizar password do utilizador
                $db->prepare("UPDATE users SET password_hash = ? WHERE email = ?")
                   ->execute([$hash, $reset['email']]);

                // Obter ID do utilizador para limpar sessões
                $stmtUser = $db->prepare("SELECT id, full_name, email FROM users WHERE email = ?");
                $stmtUser->execute([$reset['email']]);
                $userFound = $stmtUser->fetch();
                if ($userFound) {
                    // Invalidar sessões de "lembrar-me" antigas
                    $db->prepare("DELETE FROM user_sessions WHERE user_id = ?")->execute([$userFound['id']]);

                    // Enviar email de confirmação
                    require_once 'includes/mail_config.php';
                    sendPasswordChangedEmail($userFound['email'], $userFound['full_name']);
                }

                // Marcar token como usado
                $db->prepare("UPDATE password_resets SET used = 1 WHERE token = ?")
                   ->execute([$token]);

                logActivity(null, 'password_reset_success', "email={$reset['email']}");

                $db->commit();
                $success = true;
            } catch (Exception $e) {
                $db->rollBack();
                $errors[] = "Erro ao processar o pedido. Tenta novamente.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova Palavra-passe — Manual de Impressão 3D</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0a0a0f; --surface: #111118; --surface2: #1a1a26;
            --accent: #00e5ff; --accent2: #ff6b35; --accent3: #7c3aed;
            --text: #e8e8f0; --muted: #888899; --border: rgba(0,229,255,0.15);
            --success: #00ff88; --error: #ff4444;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: var(--bg); color: var(--text);
            font-family: 'Inter', sans-serif; min-height: 100vh;
            display: flex; align-items: center; justify-content: center; padding: 40px 20px;
        }
        body::before {
            content: ''; position: fixed; inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.04'/%3E%3C/svg%3E");
            pointer-events: none; z-index: 0;
        }
        .container {
            width: 100%; max-width: 440px; background: var(--surface);
            border: 1px solid var(--border); border-radius: 20px;
            padding: 40px; position: relative; z-index: 1;
        }
        .header { text-align: center; margin-bottom: 32px; }
        .header .logo { font-family: 'Space Mono', monospace; font-size: 11px; color: var(--accent); letter-spacing: 3px; text-transform: uppercase; margin-bottom: 12px; }
        .header h1 { font-family: 'Syne', sans-serif; font-size: 26px; font-weight: 800; color: #fff; }
        .header h1 span { color: var(--accent); }
        .header p { color: var(--muted); font-size: 14px; margin-top: 8px; line-height: 1.6; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-family: 'Space Mono', monospace; font-size: 11px; color: var(--muted); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 1px; }
        .form-group input { width: 100%; background: var(--surface2); border: 1px solid var(--border); border-radius: 12px; padding: 14px 18px; color: var(--text); font-family: 'Inter', sans-serif; font-size: 15px; transition: all 0.2s; }
        .form-group input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 20px rgba(0,229,255,0.1); }
        .btn { width: 100%; background: linear-gradient(135deg, var(--accent), var(--accent3)); border: none; border-radius: 12px; padding: 16px; color: #000; font-family: 'Space Mono', monospace; font-size: 13px; font-weight: 700; letter-spacing: 1px; cursor: pointer; transition: all 0.3s; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 8px 30px rgba(0,229,255,0.3); }
        .alert { padding: 14px 18px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: rgba(0,255,136,0.08); border: 1px solid rgba(0,255,136,0.25); color: var(--success); }
        .alert-error   { background: rgba(255,68,68,0.08); border: 1px solid rgba(255,68,68,0.25); color: #ff8888; }
        .footer { text-align: center; margin-top: 24px; padding-top: 24px; border-top: 1px solid var(--border); color: var(--muted); font-size: 14px; }
        .footer a { color: var(--accent); text-decoration: none; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div class="logo">Manual Educativo</div>
        <h1>Nova <span>Senha</span></h1>
        <p>Define a tua nova palavra-passe de acesso.</p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error"><?php echo sanitize($errors[0]); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            &#x2705; Palavra-passe alterada com sucesso! Já podes iniciar sessão.
        </div>
        <a href="login.php" class="btn" style="text-align:center;text-decoration:none;display:block">IR PARA LOGIN</a>
    <?php elseif ($reset): ?>
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="token" value="<?php echo sanitize($token); ?>">

            <div class="form-group">
                <label for="password">Nova Palavra-passe</label>
                <input type="password" id="password" name="password" placeholder="Mínimo 8 caracteres" required autofocus>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirmar Palavra-passe</label>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Repete a palavra-passe" required>
            </div>

            <button type="submit" class="btn">ALTERAR PALAVRA-PASSE</button>
        </form>
    <?php else: ?>
        <div class="footer">
            <a href="login.php">Voltar ao login</a>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
