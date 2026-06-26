<?php
require_once 'includes/functions.php';
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Política de Privacidade — Manual de Impressão 3D</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0a0a0f; --surface: #111118; --surface2: #1a1a26;
            --accent: #00e5ff; --accent2: #ff6b35; --accent3: #7c3aed;
            --text: #e8e8f0; --muted: #888899; --border: rgba(0,229,255,0.15);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: var(--bg);
            color: var(--text);
            font-family: 'Inter', sans-serif;
            line-height: 1.6;
            padding: 40px 20px;
            background-image: linear-gradient(rgba(10, 10, 15, 0.92), rgba(10, 10, 15, 0.92)), url('https://images.unsplash.com/photo-1518770660439-4636190af475?q=80&w=2070&auto=format&fit=crop');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
        }
        .container { max-width: 800px; margin: 0 auto; background: var(--surface); border: 1px solid var(--border); border-radius: 20px; padding: 40px; }
        h1 { font-family: 'Syne', sans-serif; font-size: 32px; font-weight: 800; color: #fff; margin-bottom: 24px; }
        h1 span { color: var(--accent); }
        h2 { font-family: 'Syne', sans-serif; font-size: 20px; font-weight: 700; color: var(--accent); margin-top: 32px; margin-bottom: 16px; }
        p { margin-bottom: 16px; color: var(--muted); }
        ul { margin-bottom: 16px; padding-left: 20px; color: var(--muted); }
        li { margin-bottom: 8px; }
        .back-link { display: inline-flex; align-items: center; gap: 8px; color: var(--muted); text-decoration: none; font-size: 13px; margin-bottom: 24px; transition: color 0.2s; }
        .back-link:hover { color: var(--accent); }
    </style>
</head>
<body>
    <div class="container">
        <a href="javascript:history.back()" class="back-link">← Voltar</a>

        <h1>Política de <span>Privacidade</span></h1>
        <p>Última atualização: <?php echo date('d/m/Y'); ?></p>

        <h2>1. Informações que Recolhemos</h2>
        <p>Recolhemos informações para fornecer melhores serviços a todos os nossos utilizadores. As informações que recolhemos incluem:</p>
        <ul>
            <li><strong>Informações de Conta:</strong> Nome de utilizador, endereço de email e palavra-passe (encriptada).</li>
            <li><strong>Perfil do Utilizador:</strong> Nome completo, bio, localização e website (se fornecidos).</li>
            <li><strong>Dados de Utilização:</strong> Informações sobre como utilizas a aplicação, incluindo comentários, preferências e progresso nas missões.</li>
            <li><strong>Interações com a IA:</strong> Histórico de conversas com o Print AI para melhorar as respostas.</li>
        </ul>

        <h2>2. Como Utilizamos as Informações</h2>
        <p>Utilizamos os dados recolhidos para:</p>
        <ul>
            <li>Gerir a tua conta e fornecer acesso às funcionalidades.</li>
            <li>Personalizar a tua experiência na plataforma.</li>
            <li>Moderar a comunidade e garantir um ambiente seguro.</li>
            <li>Melhorar o nosso conteúdo e assistente de IA.</li>
            <li>Enviar notificações importantes sobre a tua conta.</li>
        </ul>

        <h2>3. Cookies e Publicidade</h2>
        <p>Utilizamos cookies para melhorar a experiência do utilizador e para fins estatísticos. Além disso, utilizamos serviços de terceiros como o <strong>Google AdSense</strong> para apresentar anúncios. Estes parceiros podem utilizar cookies para personalizar os anúncios com base nas suas visitas a este e a outros sites na internet.</p>
        <p>Pode optar por desativar a publicidade personalizada visitando as Definições de Anúncios do Google.</p>

        <h2>4. Partilha de Dados</h2>
        <p>Não vendemos os teus dados pessoais a terceiros. As tuas informações de perfil público (nome de utilizador, bio, emblemas) são visíveis para outros membros da comunidade.</p>

        <h2>4. Segurança</h2>
        <p>Implementamos medidas de segurança técnicas e organizacionais para proteger os teus dados contra acesso não autorizado, alteração ou destruição. No entanto, nenhum método de transmissão pela Internet é 100% seguro.</p>

        <h2>5. Os Teus Direitos</h2>
        <p>Tens o direito de aceder, retificar ou eliminar os teus dados pessoais. Podes atualizar o teu perfil a qualquer momento na secção "Perfil". Se desejares eliminar a tua conta permanentemente, contacta a moderação.</p>

        <h2>6. Cookies</h2>
        <p>Utilizamos cookies para manter a tua sessão ativa e lembrar as tuas preferências. Podes configurar o teu navegador para recusar cookies, mas algumas funcionalidades da aplicação podem não funcionar corretamente.</p>

        <div style="margin-top: 40px; padding-top: 24px; border-top: 1px solid var(--border); text-align: center;">
            <p style="font-size: 12px; color: var(--muted);">© <?php echo date('Y'); ?> Manual de Impressão 3D — Respeitamos a tua privacidade.</p>
        </div>
    </div>
</body>
</html>
