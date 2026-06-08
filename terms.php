<?php
require_once 'includes/functions.php';
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Termos de Utilização — Manual de Impressão 3D</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0a0a0f; --surface: #111118; --surface2: #1a1a26;
            --accent: #00e5ff; --accent2: #ff6b35; --accent3: #7c3aed;
            --text: #e8e8f0; --muted: #888899; --border: rgba(0,229,255,0.15);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: var(--bg); color: var(--text); font-family: 'Inter', sans-serif; line-height: 1.6; padding: 40px 20px; }
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

        <h1>Termos de <span>Utilização</span></h1>
        <p>Última atualização: <?php echo date('d/m/Y'); ?></p>

        <h2>1. Aceitação dos Termos</h2>
        <p>Ao aceder e utilizar o Manual de Impressão 3D, concordas em cumprir e estar vinculado aos seguintes Termos de Utilização. Se não concordares com algum destes termos, não deves utilizar a aplicação.</p>

        <h2>2. Descrição do Serviço</h2>
        <p>O Manual de Impressão 3D é uma plataforma educativa que fornece informações, guias e uma comunidade para entusiastas de impressão 3D. O serviço inclui acesso a conteúdos técnicos, fóruns de discussão e um assistente de inteligência artificial.</p>

        <h2>3. Registo e Conta</h2>
        <p>Para aceder a certas funcionalidades, poderás ter de criar uma conta. És responsável por manter a confidencialidade da tua palavra-passe e por todas as atividades que ocorram na tua conta.</p>
        <ul>
            <li>Deves fornecer informações verdadeiras e completas.</li>
            <li>É proibida a criação de contas com nomes de utilizador ofensivos.</li>
            <li>Reservamo-nos o direito de suspender contas que violem as nossas regras de comunidade.</li>
        </ul>

        <h2>4. Conduta do Utilizador</h2>
        <p>Ao participar na nossa comunidade, concordas em:</p>
        <ul>
            <li>Não publicar conteúdo ilegal, ofensivo, difamatório ou abusivo.</li>
            <li>Não fazer spam ou publicidade não autorizada.</li>
            <li>Respeitar os outros membros da comunidade.</li>
            <li>Não tentar interferir com o funcionamento técnico da aplicação.</li>
        </ul>

        <h2>5. Propriedade Intelectual</h2>
        <p>O conteúdo original do Manual de Impressão 3D está protegido por direitos de autor. Podes partilhar os links para o manual para fins educativos, mas não podes copiar e vender o conteúdo como se fosse teu.</p>

        <h2>6. Limitação de Responsabilidade</h2>
        <p>A utilização das informações contidas neste manual é por tua conta e risco. Não nos responsabilizamos por danos causados a equipamentos de impressão 3D ou lesões resultantes do uso indevido das técnicas aqui descritas.</p>

        <h2>7. Alterações aos Termos</h2>
        <p>Podemos atualizar estes termos periodicamente. O uso continuado da aplicação após as alterações constitui a aceitação dos novos termos.</p>

        <div style="margin-top: 40px; padding-top: 24px; border-top: 1px solid var(--border); text-align: center;">
            <p style="font-size: 12px; color: var(--muted);">© <?php echo date('Y'); ?> Manual de Impressão 3D — Todos os direitos reservados.</p>
        </div>
    </div>
</body>
</html>
