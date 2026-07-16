<?php
/**
 * contacto.php — Página de contacto profissional
 */
require_once 'includes/functions.php';
require_once 'includes/mail_config.php';

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? null)) {
        $error = 'Erro de segurança. Tente novamente.';
    } else {
        $nome    = sanitize($_POST['nome'] ?? '');
        $email   = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $assunto = sanitize($_POST['assunto'] ?? '');
        $mensagem = sanitize($_POST['mensagem'] ?? '');

        if (empty($nome) || empty($email) || empty($mensagem)) {
            $error = 'Por favor, preencha todos os campos obrigatórios.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'O email introduzido não é válido.';
        } else {
            // Preparar o corpo do email para ti
            $corpoHtml = "
                <h2>Novo contacto via Website</h2>
                <p><strong>Nome:</strong> {$nome}</p>
                <p><strong>Email:</strong> {$email}</p>
                <p><strong>Assunto:</strong> {$assunto}</p>
                <p><strong>Mensagem:</strong><br>" . nl2br($mensagem) . "</p>
            ";

            // Enviar para o teu novo email de contacto profissional
            $enviado = sendEmail('contacto@manual-3d.pt', 'Admin Manual 3D', "CONTACTO: $assunto", getEmailTemplate($corpoHtml));

            if ($enviado) {
                $success = true;
            } else {
                $error = 'Ocorreu um erro ao enviar a mensagem. Tente mais tarde ou envie direto para contacto@manual-3d.pt';
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
    <title>Contacto — Manual de Impressão 3D</title>
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
            min-height: 100vh; display: flex; flex-direction: column; align-items: center; padding: 60px 20px;
        }
        .container { max-width: 600px; width: 100%; background: var(--surface); border: 1px solid var(--border); border-radius: 24px; padding: 40px; box-shadow: 0 20px 60px rgba(0,0,0,0.5); }
        .back-link { display: inline-flex; align-items: center; gap: 8px; color: var(--muted); text-decoration: none; font-size: 13px; margin-bottom: 24px; transition: color 0.2s; }
        .back-link:hover { color: var(--accent); }

        h1 { font-family: 'Syne', sans-serif; font-size: 32px; font-weight: 800; color: #fff; margin-bottom: 8px; }
        .subtitle { color: var(--muted); margin-bottom: 32px; font-size: 15px; }

        .form-group { margin-bottom: 20px; }
        label { display: block; font-family: 'Space Mono', monospace; font-size: 11px; color: var(--accent); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; }
        input, select, textarea {
            width: 100%; background: var(--surface2); border: 1px solid var(--border); border-radius: 12px;
            padding: 14px 18px; color: var(--text); font-family: 'Inter', sans-serif; font-size: 14px; transition: all 0.2s;
        }
        input:focus, select:focus, textarea:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 20px rgba(0,229,255,0.1); }

        .btn-submit {
            width: 100%; background: linear-gradient(135deg, var(--accent), #7c3aed); border: none; border-radius: 12px;
            padding: 16px; color: #000; font-family: 'Space Mono', monospace; font-size: 13px; font-weight: 700;
            cursor: pointer; transition: transform 0.2s, box-shadow 0.2s; margin-top: 10px;
        }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,229,255,0.3); }

        .alert { padding: 16px; border-radius: 12px; margin-bottom: 24px; font-size: 14px; }
        .alert-success { background: rgba(0,255,136,0.1); border: 1px solid rgba(0,255,136,0.3); color: #00ff88; }
        .alert-error { background: rgba(255,68,68,0.1); border: 1px solid rgba(255,68,68,0.3); color: #ff8888; }

        .info-footer { margin-top: 30px; text-align: center; border-top: 1px solid var(--border); padding-top: 20px; font-size: 12px; color: var(--muted); }
    </style>
</head>
<body>
    <div class="container">
        <a href="/index" class="back-link">← Voltar</a>
        <h1>Entra em <span>Contacto</span></h1>
        <p class="subtitle">Dúvidas, parcerias ou propostas de publicidade.</p>

        <?php if ($success): ?>
            <div class="alert alert-success">
                ✨ <strong>Mensagem enviada!</strong> Obrigado pelo contacto. Responderei o mais brevemente possível.
            </div>
            <a href="/index" class="btn-submit" style="text-align:center; text-decoration:none; display:block;">Voltar ao Manual</a>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="alert alert-error">⚠️ <?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">

                <div class="form-group">
                    <label>O teu Nome</label>
                    <input type="text" name="nome" placeholder="Ex: João Silva" required value="<?php echo sanitize($_POST['nome'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>O teu Email</label>
                    <input type="email" name="email" placeholder="Ex: joao@email.com" required value="<?php echo sanitize($_POST['email'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>Assunto</label>
                    <select name="assunto">
                        <option value="Geral">Assunto Geral</option>
                        <option value="Parceria">Proposta de Parceria</option>
                        <option value="Publicidade">Publicidade / Anúncios</option>
                        <option value="Erro no Site">Reportar Erro no Site</option>
                        <option value="Outro">Outro</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Mensagem</label>
                    <textarea name="mensagem" rows="5" placeholder="Escreve aqui a tua mensagem..." required><?php echo sanitize($_POST['mensagem'] ?? ''); ?></textarea>
                </div>

                <button type="submit" class="btn-submit">ENVIAR MENSAGEM</button>
            </form>
        <?php endif; ?>

        <div class="info-footer">
            Podes também enviar diretamente para <br>
            <strong>contacto@manual-3d.pt</strong>
        </div>
    </div>
</body>
</html>
