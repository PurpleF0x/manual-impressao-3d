<?php
/**
 * finalizar_registo.php — Setup inicial para utilizadores Google
 */
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    header('Location: /login');
    exit;
}

$user = getCurrentUser();
$db = getDB();
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? null)) {
        $error = 'Erro de segurança. Tente novamente.';
    } else {
        $fullName = trim($_POST['full_name'] ?? '');
        $userName = trim($_POST['username'] ?? '');
        $expLevel = $_POST['experience_level'] ?? 'iniciante';

        if (strlen($fullName) < 2) {
            $error = 'O teu nome deve ter pelo menos 2 caracteres.';
        } elseif (strlen($userName) < 3) {
            $error = 'O teu @username deve ter pelo menos 3 caracteres.';
        } elseif (!preg_match('/^[a-zA-Z0-9._]+$/', $userName)) {
            $error = 'O username só pode conter letras, números, pontos e underscores.';
        } else {
            // Verificar se o username já existe
            $check = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $check->execute([$userName, $user['id']]);
            if ($check->fetch()) {
                $error = 'Este username já está a ser utilizado por outra pessoa.';
            } else {
                // Atualizar perfil
                $stmt = $db->prepare("UPDATE users SET full_name = ?, username = ?, experience_level = ? WHERE id = ?");
                if ($stmt->execute([$fullName, $userName, $expLevel, $user['id']])) {
                    $_SESSION['username'] = $userName;
                    $success = true;
                    // Redirecionar após 2 segundos via JS ou direto
                    header('Refresh: 2; url=/');
                } else {
                    $error = 'Ocorreu um erro ao guardar os teus dados.';
                }
            }
        }
    }
}

$csrf = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finalizar Registo — Manual de Impressão 3D</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0a0a0f; --surface: #111118; --surface2: #1a1a26;
            --accent: #00e5ff; --accent2: #ff6b35; --text: #e8e8f0;
            --muted: #888899; --border: rgba(0,229,255,0.15);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: var(--bg); color: var(--text);
            font-family: 'Inter', sans-serif; line-height: 1.6;
            min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 20px;
        }
        .container { max-width: 480px; width: 100%; background: var(--surface); border: 1px solid var(--border); border-radius: 24px; padding: 40px; box-shadow: 0 20px 60px rgba(0,0,0,0.5); position: relative; overflow: hidden; }
        .container::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, var(--accent), #7c3aed); }

        h1 { font-family: 'Syne', sans-serif; font-size: 28px; font-weight: 800; color: #fff; margin-bottom: 8px; text-align: center; }
        .subtitle { color: var(--muted); margin-bottom: 32px; font-size: 14px; text-align: center; }

        .form-group { margin-bottom: 20px; }
        label { display: block; font-family: 'Space Mono', monospace; font-size: 11px; color: var(--accent); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; }
        input, select {
            width: 100%; background: var(--surface2); border: 1px solid var(--border); border-radius: 12px;
            padding: 14px 18px; color: var(--text); font-family: 'Inter', sans-serif; font-size: 14px; transition: all 0.2s;
        }
        input:focus, select:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 20px rgba(0,229,255,0.1); }
        input[readonly] { opacity: 0.5; cursor: not-allowed; border-color: transparent; }

        .btn-submit {
            width: 100%; background: linear-gradient(135deg, var(--accent), #7c3aed); border: none; border-radius: 12px;
            padding: 16px; color: #000; font-family: 'Space Mono', monospace; font-size: 13px; font-weight: 700;
            cursor: pointer; transition: transform 0.2s, box-shadow 0.2s; margin-top: 10px;
        }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,229,255,0.3); }

        .alert { padding: 16px; border-radius: 12px; margin-bottom: 24px; font-size: 14px; text-align: center; }
        .alert-success { background: rgba(0,255,136,0.1); border: 1px solid rgba(0,255,136,0.3); color: #00ff88; }
        .alert-error { background: rgba(255,68,68,0.1); border: 1px solid rgba(255,68,68,0.3); color: #ff8888; }

        .avatar-preview { width: 80px; height: 80px; border-radius: 50%; border: 2px solid var(--accent); margin: 0 auto 24px; display: block; object-fit: cover; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Bem-vindo, <span>Explorador</span>!</h1>
        <p class="subtitle">Quase lá. Confirma os teus dados para começares a tua jornada 3D.</p>

        <?php if ($success): ?>
            <div class="alert alert-success">
                ✨ <strong>Perfil configurado!</strong><br>A redirecionar para o manual...
            </div>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="alert alert-error">⚠️ <?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($user['avatar_url']): ?>
                <img src="<?php echo avPath($user['avatar_url']); ?>" class="avatar-preview" alt="Avatar">
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">

                <div class="form-group">
                    <label>Email (Não editável)</label>
                    <input type="email" value="<?php echo sanitize($user['email']); ?>" readonly>
                </div>

                <div class="form-group">
                    <label>O teu Nome Real</label>
                    <input type="text" name="full_name" placeholder="Ex: João Silva" required value="<?php echo sanitize($fullName ?? $user['full_name']); ?>">
                </div>

                <div class="form-group">
                    <label>Escolhe o teu @username</label>
                    <input type="text" name="username" placeholder="Ex: joao.maker" required value="<?php echo sanitize($userName ?? $user['username']); ?>">
                </div>

                <div class="form-group">
                    <label>Nível de Experiência</label>
                    <select name="experience_level">
                        <option value="iniciante">🎓 Iniciante (Estou a começar)</option>
                        <option value="intermedio">🔧 Intermédio (Já imprimo algumas peças)</option>
                        <option value="avancado">🔬 Avançado (Conheço bem os parâmetros)</option>
                        <option value="profissional">🏆 Profissional (Trabalho na área)</option>
                    </select>
                </div>

                <button type="submit" class="btn-submit">FINALIZAR E COMEÇAR</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
