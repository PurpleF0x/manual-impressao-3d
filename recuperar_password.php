<?php
/**
 * recuperar_password.php
 */
require_once 'includes/functions.php';
require_once 'includes/mail_config.php';

if (isLoggedIn()) redirect('index.php');

$db = getDB();

try {
    $db->exec("CREATE TABLE IF NOT EXISTS password_resets (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        email      VARCHAR(100) NOT NULL,
        token      VARCHAR(64)  NOT NULL UNIQUE,
        expires_at TIMESTAMP    NOT NULL,
        used       TINYINT(1)   DEFAULT 0,
        created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_token (token),
        INDEX idx_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) {}

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Token de segurança inválido. Recarrega a página.';
    } else {
        $email = trim(strtolower($_POST['email'] ?? ''));

        if (empty($email) || !isValidEmail($email)) {
            $errors[] = 'Indica um endereço de email válido.';
        } else {
            $stmt = $db->prepare("SELECT id, full_name, email FROM users WHERE email = ? AND is_active = TRUE LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                $db->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);

                $token     = bin2hex(random_bytes(32));
                $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

                $db->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)")
                   ->execute([$email, $token, $expiresAt]);

                logActivity(null, 'password_reset_requested', "email={$email}");

                // Proteção contra Host Header Injection - usar domínio fixo se disponível
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';

                // Lista de hosts permitidos (whitelist)
                $allowedHosts = ['localhost', 'manual-impressao-3d.onrender.com', 'manual-3d.local'];
                if (!in_array($host, $allowedHosts)) {
                    $host = 'manual-impressao-3d.onrender.com'; // Fallback para o domínio oficial
                }

                $dir      = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
                $resetUrl = "{$protocol}://{$host}{$dir}/reset_password.php?token={$token}";

                sendPasswordResetEmail($user['email'], $user['full_name'], $token, $resetUrl);
            }

            $success = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="google-adsense-ads-free" content="true">
    <title>Recuperar Palavra-passe — Manual de Impressão 3D</title>
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
        .back-link {
            display: inline-flex; align-items: center; gap: 8px;
            color: var(--muted); text-decoration: none; font-size: 13px;
            margin-bottom: 24px; transition: color 0.2s;
        }
        .back-link:hover { color: var(--accent); }
        .header { text-align: center; margin-bottom: 32px; }
        .header .logo { font-family: 'Space Mono', monospace; font-size: 11px; color: var(--accent); letter-spacing: 3px; text-transform: uppercase; margin-bottom: 12px; }
        .header h1 { font-family: 'Syne', sans-serif; font-size: 26px; font-weight: 800; color: #fff; }
        .header h1 span { color: var(--accent); }
        .header p { color: var(--muted); font-size: 14px; margin-top: 8px; line-height: 1.6; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-family: 'Space Mono', monospace; font-size: 11px; color: var(--muted); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 1px; }
        .form-group input { width: 100%; background: var(--surface2); border: 1px solid var(--border); border-radius: 12px; padding: 14px 18px; color: var(--text); font-family: 'Inter', sans-serif; font-size: 15px; transition: all 0.2s; }
        .form-group input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 20px rgba(0,229,255,0.1); }
        .form-group input::placeholder { color: var(--muted); opacity: 0.6; }
        .btn { width: 100%; background: linear-gradient(135deg, var(--accent), var(--accent3)); border: none; border-radius: 12px; padding: 16px; color: #000; font-family: 'Space Mono', monospace; font-size: 13px; font-weight: 700; letter-spacing: 1px; cursor: pointer; transition: all 0.3s; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 8px 30px rgba(0,229,255,0.3); }
        .alert { padding: 14px 18px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: rgba(0,255,136,0.08); border: 1px solid rgba(0,255,136,0.25); color: var(--success); }
        .alert-error   { background: rgba(255,68,68,0.08); border: 1px solid rgba(255,68,68,0.25); color: #ff8888; }
        .footer { text-align: center; margin-top: 24px; padding-top: 24px; border-top: 1px solid var(--border); color: var(--muted); font-size: 14px; }
        .footer a { color: var(--accent); text-decoration: none; }
        .footer a:hover { text-decoration: underline; }
    </style>
</head>
<body>
<div class="container">
    <a href="login.php" class="back-link">← Voltar ao login</a>

    <div class="header">
        <div class="logo">Manual Educativo</div>
        <h1>Recuperar <span>Acesso</span></h1>
        <p>Indica o email da tua conta e enviamos um link para criares uma nova palavra-passe.</p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error"><?php echo sanitize($errors[0]); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            &#x2705; Se o email estiver registado, receberás em breve um link para redefinir a palavra-passe.<br>
            <strong style="display:block;margin-top:10px;color:#fff;">⚠️ ATENÇÃO: Verifica a tua pasta de Lixo Eletrónico ou SPAM.</strong>
            <small style="margin-top:6px;display:block;opacity:0.8">O link é válido por 1 hora.</small>
        </div>
        <div class="footer">
            <a href="login.php">← Voltar ao login</a>
        </div>
    <?php else: ?>
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <div class="form-group">
                <label for="email">Email da conta</label>
                <input type="email" id="email" name="email"
                       placeholder="o-teu@email.pt"
                       value="<?php echo sanitize($_POST['email'] ?? ''); ?>"
                       required autofocus>
            </div>
            <button type="submit" class="btn">ENVIAR LINK DE RECUPERAÇÃO</button>
        </form>
        <div class="footer">
            Já tens acesso? <a href="login.php">Fazer login</a>
        </div>
    <?php endif; ?>
</div>
</body>
</html>