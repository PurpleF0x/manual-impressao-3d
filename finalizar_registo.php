<?php
/**
 * finalizar_registo.php — Setup inicial para utilizadores Google
 * Este ficheiro garante que utilizadores que entram via Google escolham um username único
 * e completem o seu perfil antes de acederem à plataforma.
 */
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    header('Location: /login');
    exit;
}

$user = getCurrentUser();
$db = getDB();

// Se o utilizador já tem um username definido e não está no fluxo de novo registo, manda para a home
if (!empty($user['username']) && !isset($_SESSION['new_google_user'])) {
    header('Location: /');
    exit;
}

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? null)) {
        $error = 'Erro de segurança. Tente novamente.';
    } else {
        $fullName = trim($_POST['full_name'] ?? '');
        $userName = trim($_POST['username'] ?? '');
        $expLevel = $_POST['experience_level'] ?? 'iniciante';

        // Validações rigorosas
        if (strlen($fullName) < 2) {
            $error = 'O teu nome deve ter pelo menos 2 caracteres.';
        } elseif (strlen($userName) < 3) {
            $error = 'O teu @username deve ter pelo menos 3 caracteres.';
        } elseif (strlen($userName) > 30) {
            $error = 'O teu @username é demasiado longo.';
        } elseif (!preg_match('/^[a-z0-9._]+$/', $userName)) {
            $error = 'O username só pode conter letras minúsculas, números, pontos e underscores.';
        } else {
            // Verificar disponibilidade do username
            $check = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $check->execute([$userName, $user['id']]);
            if ($check->fetch()) {
                $error = 'Este @username já está a ser utilizado por outro explorador.';
            } else {
                try {
                    $db->beginTransaction();

                    // Atualizar dados principais
                    $stmt = $db->prepare("UPDATE users SET full_name = ?, username = ?, experience_level = ? WHERE id = ?");
                    $stmt->execute([$fullName, $userName, $expLevel, $user['id']]);

                    // Garantir que a config de perfil existe
                    ensureUserProfileConfig((int)$user['id']);

                    // Recompensa inicial por completar perfil
                    addXP((int)$user['id'], 50, "Completou o perfil inicial", 25);

                    $db->commit();

                    $_SESSION['username'] = $userName;
                    unset($_SESSION['new_google_user']);
                    $success = true;

                    header('Refresh: 2; url=/');
                } catch (Exception $e) {
                    if ($db->inTransaction()) $db->rollBack();
                    $error = 'Erro ao guardar: ' . $e->getMessage();
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
            --bg: #0a0a0f;
            --surface: #111118;
            --surface2: #1a1a26;
            --accent: #00e5ff;
            --accent2: #ff6b35;
            --text: #e8e8f0;
            --muted: #888899;
            --border: rgba(0,229,255,0.15);
            --grad: linear-gradient(135deg, #00e5ff 0%, #7c3aed 100%);
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
            padding: 20px;
            background-image:
                radial-gradient(circle at 20% 20%, rgba(0, 229, 255, 0.05) 0%, transparent 40%),
                radial-gradient(circle at 80% 80%, rgba(124, 58, 237, 0.05) 0%, transparent 40%);
        }

        .card {
            max-width: 450px;
            width: 100%;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            position: relative;
            z-index: 1;
        }

        .card::before {
            content: '';
            position: absolute;
            inset: -1px;
            background: var(--grad);
            border-radius: 25px;
            z-index: -1;
            opacity: 0.3;
        }

        .header { text-align: center; margin-bottom: 32px; }

        .logo-placeholder {
            width: 64px;
            height: 64px;
            background: var(--grad);
            border-radius: 16px;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Syne', sans-serif;
            font-weight: 800;
            font-size: 24px;
            color: #000;
            box-shadow: 0 0 20px rgba(0, 229, 255, 0.3);
        }

        h1 {
            font-family: 'Syne', sans-serif;
            font-size: 26px;
            font-weight: 800;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        .subtitle {
            color: var(--muted);
            font-size: 14px;
        }

        .form-group { margin-bottom: 20px; }

        label {
            display: block;
            font-family: 'Space Mono', monospace;
            font-size: 11px;
            color: var(--accent);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
            font-weight: 700;
        }

        .input-wrapper { position: relative; }

        .input-wrapper span.at {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--muted);
            font-family: 'Space Mono', monospace;
        }

        input, select {
            width: 100%;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 14px 18px;
            color: #fff;
            font-size: 15px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        input[name="username"] { padding-left: 32px; }

        input:focus, select:focus {
            outline: none;
            border-color: var(--accent);
            background: #1e1e2d;
            box-shadow: 0 0 0 4px rgba(0, 229, 255, 0.1);
        }

        input[readonly] {
            opacity: 0.6;
            cursor: not-allowed;
            border-style: dashed;
        }

        .btn-primary {
            width: 100%;
            background: var(--grad);
            color: #fff;
            border: none;
            padding: 16px;
            border-radius: 14px;
            font-family: 'Space Mono', monospace;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(124, 58, 237, 0.3);
        }

        .btn-primary:active { transform: translateY(0); }

        .alert {
            padding: 14px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-error {
            background: rgba(255, 68, 68, 0.1);
            border: 1px solid rgba(255, 68, 68, 0.2);
            color: #ff8888;
        }

        .alert-success {
            background: rgba(0, 229, 255, 0.1);
            border: 1px solid rgba(0, 229, 255, 0.2);
            color: var(--accent);
        }

        .xp-badge {
            display: inline-block;
            background: rgba(255, 215, 0, 0.1);
            color: #ffd700;
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            margin-top: 4px;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <div class="logo-placeholder">3D</div>
            <h1>Benvindo ao Hub!</h1>
            <p class="subtitle">Personaliza a tua identidade na nossa comunidade.</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <div>
                    <strong>Tudo pronto!</strong><br>
                    <span style="font-size: 12px;">A redirecionar-te para a plataforma...</span>
                    <div class="xp-badge">+50 XP RECOMPENSA</div>
                </div>
            </div>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <span>⚠️</span>
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">

                <div class="form-group">
                    <label>E-mail Verificado</label>
                    <input type="text" value="<?php echo sanitize($user['email']); ?>" readonly>
                </div>

                <div class="form-group">
                    <label>Nome Completo</label>
                    <input type="text" name="full_name" placeholder="Como te devemos chamar?" required value="<?php echo sanitize($fullName ?? $user['full_name']); ?>">
                </div>

                <div class="form-group">
                    <label>O teu @username único</label>
                    <div class="input-wrapper">
                        <span class="at">@</span>
                        <input type="text" name="username" placeholder="ex: maker_pro" required value="<?php echo sanitize($userName ?? ''); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Nível de Experiência</label>
                    <select name="experience_level">
                        <option value="iniciante">Estou a começar agora</option>
                        <option value="intermedio">Já tenho alguma prática</option>
                        <option value="avancado">Dominio técnico avançado</option>
                        <option value="profissional">Trabalho profissionalmente</option>
                    </select>
                </div>

                <button type="submit" class="btn-primary">Finalizar Registo</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
