<?php
/**
 * suporte.php — Página de Apoio ao Utilizador
 */
require_once 'includes/functions.php';
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suporte e Ajuda — Manual 3D</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0a0a0f; --surface: #111118; --surface2: #1a1a26;
            --accent: #00e5ff; --accent2: #ff6b35; --accent3: #7c3aed;
            --text: #e8e8f0; --muted: #888899; --border: rgba(0,229,255,0.15);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: var(--bg); color: var(--text);
            font-family: 'Inter', sans-serif; line-height: 1.6;
            background-image: linear-gradient(rgba(10, 10, 15, 0.9), rgba(10, 10, 15, 0.9)), url('https://images.unsplash.com/photo-1581092160562-40aa08e78837?q=80&w=2070&auto=format&fit=crop');
            background-size: cover; background-position: center; background-attachment: fixed;
            min-height: 100vh; display: flex; flex-direction: column; align-items: center; padding: 60px 20px;
        }
        .container { max-width: 700px; width: 100%; background: var(--surface); border: 1px solid var(--border); border-radius: 24px; padding: 40px; box-shadow: 0 20px 60px rgba(0,0,0,0.5); }
        .back-link { display: inline-flex; align-items: center; gap: 8px; color: var(--muted); text-decoration: none; font-size: 13px; margin-bottom: 24px; transition: color 0.2s; }
        .back-link:hover { color: var(--accent); }
        h1 { font-family: 'Syne', sans-serif; font-size: 32px; font-weight: 800; color: #fff; margin-bottom: 30px; text-align: center; }
        h1 span { color: var(--accent); }
        .faq-item { background: var(--surface2); border: 1px solid var(--border2); border-radius: 12px; padding: 20px; margin-bottom: 16px; }
        .faq-q { font-weight: 700; color: var(--accent); margin-bottom: 8px; font-size: 15px; }
        .faq-a { color: var(--muted); font-size: 14px; }
        .contact-box { margin-top: 40px; text-align: center; padding: 30px; border: 1px dashed var(--border); border-radius: 16px; }
        .contact-box p { margin-bottom: 20px; }
        .btn-mail { background: var(--accent); color: #000; padding: 12px 24px; border-radius: 10px; text-decoration: none; font-weight: 700; font-family: 'Space Mono', monospace; font-size: 13px; display: inline-block; transition: transform 0.2s; }
        .btn-mail:hover { transform: translateY(-2px); box-shadow: 0 0 20px rgba(0, 229, 255, 0.4); }
        .copy-email { margin-top: 15px; font-family: 'Space Mono', monospace; font-size: 12px; color: var(--muted); cursor: pointer; text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="back-link">← Voltar ao Início</a>
        <h1>Suporte ao <span>Utilizador</span></h1>

        <div class="faq-item">
            <div class="faq-q">Perdi o acesso ao meu email de registo. E agora?</div>
            <div class="faq-a">Se não consegues aceder ao teu email para recuperar a password, entra em contacto connosco através do botão abaixo. Terás de indicar o teu nome de utilizador e responder a algumas perguntas para validarmos a tua identidade.</div>
        </div>

        <div class="faq-item">
            <div class="faq-q">O link de recuperação de password não chegou.</div>
            <div class="faq-a">Verifica sempre a tua pasta de <strong>SPAM ou Lixo Eletrónico</strong>. Como o nosso sistema é educativo, alguns filtros podem ser rigorosos.</div>
        </div>

        <div class="faq-item">
            <div class="faq-q">O meu post no fórum não aparece.</div>
            <div class="faq-a">Algumas comunidades têm a moderação ativa. O teu post pode estar na fila de aprovação dos administradores.</div>
        </div>

        <div class="contact-box">
            <p>Não encontraste resposta? Envia-nos um email direto.</p>
            <a href="mailto:3d.escolas@gmail.com?subject=Suporte Manual 3D" class="btn-mail">ENVIAR EMAIL DE AJUDA</a>
            <div class="copy-email" onclick="copyEmail()">Ou clica aqui para copiar o email: 3d.escolas@gmail.com</div>
        </div>
    </div>

    <script>
        function copyEmail() {
            navigator.clipboard.writeText('3d.escolas@gmail.com');
            alert('Email copiado para a área de transferência!');
        }
    </script>
</body>
</html>
