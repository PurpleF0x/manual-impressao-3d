<?php
require_once 'includes/functions.php';

// Destino após login — padrão: index.php
// O fórum passa ?redirect=forum/ para voltar ao fórum
$redirectTo = 'index.php';
if (!empty($_GET['redirect'])) {
    // Validar: só permite paths relativos simples (sem http, sem ..)
    $raw = $_GET['redirect'];
    if (preg_match('/^[a-zA-Z0-9_\-\/\.]+$/', $raw) && strpos($raw, '..') === false) {
        $redirectTo = $raw;
    }
}
// Preservar redirect no POST
if (!empty($_POST['redirect'])) {
    $raw = $_POST['redirect'];
    if (preg_match('/^[a-zA-Z0-9_\-\/\.]+$/', $raw) && strpos($raw, '..') === false) {
        $redirectTo = $raw;
    }
}

if (isLoggedIn()) {
    redirect($redirectTo);
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = "Token de segurança inválido.";
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);
        
        if (empty($username) || empty($password)) {
            $errors[] = "Preenche todos os campos.";
        } elseif (!isset($_POST['accept_terms'])) {
            $errors[] = "Deves aceitar os Termos de Utilização e a Política de Privacidade.";
        } else {
            $db = getDB();
            
            $stmt = $db->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = TRUE");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                // Verificar Suspensão
                if (!empty($user['suspension_until'])) {
                    $until = strtotime($user['suspension_until']);
                    if ($until > time()) {
                        $date = date('d/m/Y H:i', $until);
                        $errors[] = "A tua conta está suspensa até $date. Motivo: " . ($user['suspension_message'] ?: 'Não especificado.');
                        goto skip_login;
                    } else {
                        // Limpar suspensão expirada
                        $db->prepare("UPDATE users SET suspension_message=NULL, suspension_until=NULL WHERE id=?")->execute([$user['id']]);
                    }
                }

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];

                // Mudar para o fim do processo
                if (function_exists('fastcgi_finish_request')) {
                    header("Location: $redirectTo");
                    session_write_close();
                    fastcgi_finish_request();
                }

                $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
                logActivity($user['id'], 'login', "Login efetuado: {$user['username']}");
                
                if (!headers_sent()) redirect($redirectTo);
                exit;

                skip_login:;
            } else {
                $errors[] = "Nome de utilizador/email ou palavra-passe incorretos.";
                logActivity(null, 'login_failed', "Tentativa falhada: $username");
            }
        }
    }
}

if (!isLoggedIn() && isset($_COOKIE['remember_token'])) {
    $db = getDB();
    $stmt = $db->prepare("SELECT u.* FROM users u 
                          JOIN user_sessions s ON u.id = s.user_id 
                          WHERE s.session_token = ? AND s.expires_at > NOW() AND u.is_active = TRUE");
    $stmt->execute([$_COOKIE['remember_token']]);
    $user = $stmt->fetch();
    
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        redirect($redirectTo);
    }
}

// Label do botão "voltar" conforme origem
$backLabel = strpos($redirectTo, 'forum') !== false ? '← Voltar ao Fórum' : '← Voltar ao Manual';
$backHref  = strpos($redirectTo, 'forum') !== false ? $redirectTo : 'index.php';
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/x-icon"  href="/favicons/favicon-login.ico">
    <link rel="icon" type="image/svg+xml" href="/favicons/favicon-login.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicons/favicon-login-32.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="google-adsense-ads-free" content="true">
    <title>Login - Manual de Impressão 3D</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0a0a0f; --surface: #111118; --surface2: #1a1a26; --surface3: #222235;
            --accent: #00e5ff; --accent2: #ff6b35; --accent3: #7c3aed;
            --text: #e8e8f0; --muted: #888899; --border: rgba(0,229,255,0.15);
            --success: #00ff88; --error: #ff4444;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: var(--bg);
            color: var(--text);
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            background-image: linear-gradient(rgba(10, 10, 15, 0.85), rgba(10, 10, 15, 0.85)), url('https://images.unsplash.com/photo-1633533402143-0870562a9457?q=80&w=2070&auto=format&fit=crop');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
        }
        body::before { content: ''; position: fixed; inset: 0; background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.04'/%3E%3C/svg%3E"); pointer-events: none; z-index: 0; }
        .auth-container { width: 100%; max-width: 440px; background: var(--surface); border: 1px solid var(--border); border-radius: 20px; padding: 40px; position: relative; z-index: 1; }
        .auth-header { text-align: center; margin-bottom: 32px; }
        .auth-logo { font-family: 'Space Mono', monospace; font-size: 11px; color: var(--accent); letter-spacing: 3px; text-transform: uppercase; margin-bottom: 12px; }
        .auth-header h1 { font-family: 'Syne', sans-serif; font-size: 28px; font-weight: 800; color: #fff; }
        .auth-header h1 span { color: var(--accent); }
        .auth-header p { color: var(--muted); font-size: 14px; margin-top: 8px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-family: 'Space Mono', monospace; font-size: 11px; color: var(--muted); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 1px; }
        .form-group input[type="text"], .form-group input[type="password"], .form-group input[type="email"] { width: 100%; background: var(--surface2); border: 1px solid var(--border); border-radius: 12px; padding: 14px 18px; color: var(--text); font-family: 'Inter', sans-serif; font-size: 15px; transition: all 0.2s; }
        .form-group input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 20px rgba(0,229,255,0.1); }
        .form-group input::placeholder { color: var(--muted); opacity: 0.6; }
        .input-wrapper { position: relative; }
        .input-wrapper input { padding-right: 48px !important; }
        .toggle-password { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: var(--muted); padding: 4px; display: flex; align-items: center; justify-content: center; transition: color 0.2s; line-height: 1; }
        .toggle-password:hover { color: var(--accent); }
        .toggle-password svg { width: 18px; height: 18px; pointer-events: none; }
        .checkbox-group { display: flex; align-items: center; gap: 10px; margin-bottom: 20px; }
        .checkbox-group input[type="checkbox"] { width: 20px; height: 20px; accent-color: var(--accent); cursor: pointer; }
        .checkbox-group label { color: var(--muted); font-size: 14px; cursor: pointer; }
        .btn-primary { width: 100%; background: linear-gradient(135deg, var(--accent), var(--accent3)); border: none; border-radius: 12px; padding: 16px; color: #000; font-family: 'Space Mono', monospace; font-size: 13px; font-weight: 700; letter-spacing: 1px; cursor: pointer; transition: all 0.3s; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 30px rgba(0,229,255,0.3); }
        .auth-footer { text-align: center; margin-top: 24px; padding-top: 24px; border-top: 1px solid var(--border); }
        .auth-footer p { color: var(--muted); font-size: 14px; margin-bottom: 8px; }
        .auth-footer a { color: var(--accent); text-decoration: none; font-weight: 600; }
        .auth-footer a:hover { text-decoration: underline; }
        .alert { padding: 14px 18px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; }
        .alert-error { background: rgba(255,68,68,0.1); border: 1px solid rgba(255,68,68,0.3); color: #ff6666; }
        .alert-success { background: rgba(0,255,136,0.1); border: 1px solid rgba(0,255,136,0.3); color: #00ff88; }
        .back-link { display: inline-flex; align-items: center; gap: 8px; color: var(--muted); text-decoration: none; font-size: 13px; margin-bottom: 20px; transition: color 0.2s; }
        .back-link:hover { color: var(--accent); }
        .forgot-password { text-align: right; margin-top: -10px; margin-bottom: 16px; }
        .forgot-password a { color: var(--muted); font-size: 12px; text-decoration: none; }
        .forgot-password a:hover { color: var(--accent); }
        @media (max-width: 600px) { .auth-container { padding: 28px; } }
    </style>
    <script src="https://accounts.google.com/gsi/client" async defer></script>
</head>
<body>
    <div class="auth-container">
        <a href="<?php echo htmlspecialchars($backHref); ?>" class="back-link"><?php echo $backLabel; ?></a>
        
        <div class="auth-header">
            <div class="auth-logo">Manual Educativo</div>
            <h1>Iniciar <span>Sessão</span></h1>
            <p>Entra na tua conta para participar</p>
        </div>

        <?php showFlashMessage(); ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error"><?php echo sanitize($errors[0]); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <!-- Preservar destino no POST -->
            <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirectTo); ?>">
            
            <div class="form-group">
                <label for="username">Nome de Utilizador ou Email</label>
                <input type="text" id="username" name="username" placeholder="username ou email@exemplo.pt" 
                       value="<?php echo sanitize($_POST['username'] ?? ''); ?>" required autofocus>
            </div>

            <div class="form-group">
                <label for="password">Palavra-passe</label>
                <div class="input-wrapper">
                    <input type="password" id="password" name="password" placeholder="A tua palavra-passe" required>
                    <button type="button" class="toggle-password" onclick="togglePassword('password', this)" aria-label="Mostrar/ocultar palavra-passe">
                        <svg id="eye-password" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="forgot-password">
                <a href="recuperar_password.php">Esqueceste-te da palavra-passe?</a>
            </div>

            <div class="checkbox-group">
                <input type="checkbox" id="remember" name="remember">
                <label for="remember">Lembrar-me neste dispositivo</label>
            </div>

            <div class="checkbox-group" style="display: flex; align-items: flex-start; gap: 10px; margin-bottom: 20px;">
                <input type="checkbox" id="accept_terms" name="accept_terms" required style="width: 20px; height: 20px; accent-color: var(--accent); cursor: pointer; margin-top: 3px;">
                <label for="accept_terms" style="color: var(--muted); font-size: 13px; cursor: pointer; line-height: 1.5;">
                    Aceito os <a href="terms.php" target="_blank" style="color: var(--accent); text-decoration: none; font-weight: 600;">Termos</a> e a <a href="privacy.php" target="_blank" style="color: var(--accent); text-decoration: none; font-weight: 600;">Política de Privacidade</a>.
                </label>
            </div>

            <button type="submit" class="btn-primary">ENTRAR</button>
        </form>

        <div style="margin: 20px 0; text-align: center; position: relative;">
            <hr style="border: 0; border-top: 1px solid var(--border); margin: 0;">
            <span style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: var(--surface); padding: 0 10px; font-size: 11px; color: var(--muted); font-family: 'Space Mono';">OU</span>
        </div>

        <div id="g_id_onload"
             data-client_id="<?php require_once 'config/google_config.php'; echo GOOGLE_CLIENT_ID; ?>"
             data-context="signin"
             data-ux_mode="popup"
             data-login_uri="https://manual-3d.pt/api/google_auth.php"
             data-auto_prompt="false">
        </div>
        <div class="g_id_signin"
             data-type="standard"
             data-shape="rectangular"
             data-theme="outline"
             data-text="signin_with"
             data-size="large"
             data-logo_alignment="left"
             data-width="360">
        </div>

        <div class="auth-footer" style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border); padding-top: 20px; margin-top: 20px;">
            <p style="margin:0; font-size: 11px;">© 2024 Manual de Impressão 3D</p>
            <div style="display: flex; gap: 10px;">
                <a href="register.php" style="font-size: 13px;">Criar Conta</a>
                <span style="color: var(--muted);">|</span>
                <a href="suporte.php" style="font-size: 13px;">Suporte</a>
            </div>
        </div>
    </div>

    <script>
        function togglePassword(inputId, btn) {
            var input = document.getElementById(inputId);
            var isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            btn.innerHTML = isHidden
                ? '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;pointer-events:none"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 012.163-3.592M6.228 6.228A9.97 9.97 0 0112 5c4.478 0 8.268 2.943 9.542 7a9.97 9.97 0 01-4.43 5.291M3 3l18 18"/></svg>'
                : '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;pointer-events:none"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>';
            btn.style.color = isHidden ? 'var(--accent)' : 'var(--muted)';
        }
    </script>
</body>
</html>