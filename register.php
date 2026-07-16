<?php
require_once 'includes/functions.php';

if (isLoggedIn()) {
    redirect('/index');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = "Token de segurança inválido.";
    } else {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';
        
        if (strlen($username) < 3 || strlen($username) > 50) {
            $errors[] = "O nome de utilizador deve ter entre 3 e 50 caracteres.";
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $errors[] = "O nome de utilizador só pode conter letras, números e underscores.";
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Email inválido.";
        }
        if (strlen($fullName) < 2 || strlen($fullName) > 100) {
            $errors[] = "O nome completo deve ter entre 2 e 100 caracteres.";
        }
        if (strlen($password) < 8) {
            $errors[] = "A palavra-passe deve ter pelo menos 8 caracteres.";
        }
        if ($password !== $passwordConfirm) {
            $errors[] = "As palavras-passe não coincidem.";
        }
        if (!isset($_POST['accept_terms'])) {
            $errors[] = "Deves aceitar os Termos de Utilização e a Política de Privacidade.";
        }
        
        if (empty($errors)) {
            $db = getDB();
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                $errors[] = "Nome de utilizador ou email já registado.";
            }
        }
        
        if (empty($errors)) {
            $db = getDB();
            $passwordHash = hashPassword($password);
            $avatar = generateAvatar($fullName);
            
            $stmt = $db->prepare("INSERT INTO users (username, email, password_hash, full_name, avatar) VALUES (?, ?, ?, ?, ?)");
            
            try {
                $stmt->execute([$username, $email, $passwordHash, $fullName, $avatar]);
                $userId = $db->lastInsertId();
                
                // Redirecionar imediatamente para sucesso
                setFlashMessage('success', 'Registo efetuado com sucesso! Já podes entrar.');

                if (function_exists('fastcgi_finish_request')) {
                    session_write_close();
                    header("Location: /login");
                    fastcgi_finish_request();
                }

                require_once 'includes/mail_config.php';
                sendWelcomeEmail($email, $fullName);
                logActivity($userId, 'register', "Novo registo: $username");

                if (!headers_sent()) redirect('/login');
                exit;
            } catch (PDOException $e) {
                $errors[] = "Erro ao registar. Tente novamente.";
            }
        }
    }
}
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
    <title>Registo - Manual de Impressão 3D</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0a0a0f;
            --surface: #111118;
            --surface2: #1a1a26;
            --surface3: #222235;
            --accent: #00e5ff;
            --accent2: #ff6b35;
            --accent3: #7c3aed;
            --text: #e8e8f0;
            --muted: #888899;
            --border: rgba(0,229,255,0.15);
            --success: #00ff88;
            --error: #ff4444;
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
            background-image: linear-gradient(rgba(10, 10, 15, 0.85), rgba(10, 10, 15, 0.85)), url('https://images.unsplash.com/photo-1705423184656-78e7189f7678?q=80&w=2070&auto=format&fit=crop');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.04'/%3E%3C/svg%3E");
            pointer-events: none;
            z-index: 0;
        }

        .auth-container {
            width: 100%;
            max-width: 480px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 40px;
            position: relative;
            z-index: 1;
        }

        .auth-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .auth-logo {
            font-family: 'Space Mono', monospace;
            font-size: 11px;
            color: var(--accent);
            letter-spacing: 3px;
            text-transform: uppercase;
            margin-bottom: 12px;
        }

        .auth-header h1 {
            font-family: 'Syne', sans-serif;
            font-size: 28px;
            font-weight: 800;
            color: #fff;
        }

        .auth-header h1 span { color: var(--accent); }

        .auth-header p {
            color: var(--muted);
            font-size: 14px;
            margin-top: 8px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-family: 'Space Mono', monospace;
            font-size: 11px;
            color: var(--muted);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .form-group input {
            width: 100%;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 14px 18px;
            color: var(--text);
            font-family: 'Inter', sans-serif;
            font-size: 15px;
            transition: all 0.2s;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 20px rgba(0,229,255,0.1);
        }

        .form-group input::placeholder {
            color: var(--muted);
            opacity: 0.6;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        /* ── Olho da senha ── */
        .input-wrapper {
            position: relative;
        }
        .input-wrapper input {
            padding-right: 48px !important;
        }
        .toggle-password {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: var(--muted);
            padding: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.2s;
            line-height: 1;
        }
        .toggle-password:hover { color: var(--accent); }
        .toggle-password svg {
            width: 18px;
            height: 18px;
            pointer-events: none;
        }

        .password-strength {
            height: 4px;
            background: var(--surface3);
            border-radius: 2px;
            margin-top: 8px;
            overflow: hidden;
        }

        .password-strength-bar {
            height: 100%;
            width: 0;
            border-radius: 2px;
            transition: all 0.3s;
        }

        .password-strength-bar.weak   { width: 33%; background: var(--error); }
        .password-strength-bar.medium { width: 66%; background: var(--accent2); }
        .password-strength-bar.strong { width: 100%; background: var(--success); }

        .btn-primary {
            width: 100%;
            background: linear-gradient(135deg, var(--accent), var(--accent3));
            border: none;
            border-radius: 12px;
            padding: 16px;
            color: #000;
            font-family: 'Space Mono', monospace;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 1px;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 8px;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(0,229,255,0.3);
        }

        .auth-footer {
            text-align: center;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid var(--border);
        }

        .auth-footer p {
            color: var(--muted);
            font-size: 14px;
        }

        .auth-footer a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
        }

        .auth-footer a:hover { text-decoration: underline; }

        .alert {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-error {
            background: rgba(255,68,68,0.1);
            border: 1px solid rgba(255,68,68,0.3);
            color: #ff6666;
        }

        .alert-success {
            background: rgba(0,255,136,0.1);
            border: 1px solid rgba(0,255,136,0.3);
            color: #00ff88;
        }

        .error-list {
            list-style: none;
            padding: 0;
        }

        .error-list li {
            padding: 6px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .error-list li::before { content: '⚠️'; }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--muted);
            text-decoration: none;
            font-size: 13px;
            margin-bottom: 20px;
            transition: color 0.2s;
        }

        .back-link:hover { color: var(--accent); }

        @media (max-width: 600px) {
            .auth-container { padding: 28px; }
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <a href="/index" class="back-link">← Voltar ao Manual</a>
        
        <div class="auth-header">
            <div class="auth-logo">Manual Educativo</div>
            <h1>Criar <span>Conta</span></h1>
            <p>Junta-te à comunidade de impressão 3D</p>
        </div>

        <?php showFlashMessage(); ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul class="error-list">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo sanitize($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <div class="form-row">
                <div class="form-group">
                    <label for="username">Nome de Utilizador</label>
                    <input type="text" id="username" name="username" placeholder="ex: joao_silva" 
                           value="<?php echo sanitize($_POST['username'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="full_name">Nome Completo</label>
                    <input type="text" id="full_name" name="full_name" placeholder="ex: João Silva" 
                           value="<?php echo sanitize($_POST['full_name'] ?? ''); ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="ex: joao@email.pt" 
                       value="<?php echo sanitize($_POST['email'] ?? ''); ?>" required>
            </div>

            <div class="form-group">
                <label for="password">Palavra-passe</label>
                <div class="input-wrapper">
                    <input type="password" id="password" name="password" placeholder="Mínimo 8 caracteres" required>
                    <button type="button" class="toggle-password" onclick="togglePassword('password', this)" aria-label="Mostrar/ocultar palavra-passe">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                    </button>
                </div>
                <div class="password-strength">
                    <div class="password-strength-bar" id="strengthBar"></div>
                </div>
            </div>

            <div class="form-group">
                <label for="password_confirm">Confirmar Palavra-passe</label>
                <div class="input-wrapper">
                    <input type="password" id="password_confirm" name="password_confirm" placeholder="Repete a palavra-passe" required>
                    <button type="button" class="toggle-password" onclick="togglePassword('password_confirm', this)" aria-label="Mostrar/ocultar confirmação">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="checkbox-group" style="display: flex; align-items: flex-start; gap: 10px; margin-bottom: 20px;">
                <input type="checkbox" id="accept_terms" name="accept_terms" required style="width: 20px; height: 20px; accent-color: var(--accent); cursor: pointer; margin-top: 3px;">
                <label for="accept_terms" style="color: var(--muted); font-size: 13px; cursor: pointer; line-height: 1.5;">
                    Li e aceito os <a href="/terms" target="_blank" style="color: var(--accent); text-decoration: none; font-weight: 600;">Termos de Utilização</a> e a <a href="/privacy" target="_blank" style="color: var(--accent); text-decoration: none; font-weight: 600;">Política de Privacidade</a>.
                </label>
            </div>

            <button type="submit" class="btn-primary">CRIAR CONTA</button>
        </form>

        <div class="auth-footer">
            <p>Já tens conta? <a href="/login">Fazer Login</a></p>
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

        // Força da palavra-passe
        document.getElementById('password').addEventListener('input', function() {
            var password = this.value;
            var bar = document.getElementById('strengthBar');
            var strength = 0;
            if (password.length >= 8) strength++;
            if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;
            bar.className = 'password-strength-bar';
            if (strength <= 1) bar.classList.add('weak');
            else if (strength === 2) bar.classList.add('medium');
            else bar.classList.add('strong');
        });
    </script>
</body>
</html>