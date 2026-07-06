<?php
require_once 'includes/functions.php';
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sobre o Projeto - Manual de Impressão 3D</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0a0a0f; --surface: #111118; --surface2: #1a1a26;
            --accent: #00e5ff; --text: #e8e8f0; --muted: #888899; --border: rgba(0,229,255,0.15);
        }
        body { background: var(--bg); color: var(--text); font-family: 'Inter', sans-serif; line-height: 1.8; padding: 40px 20px; }
        .container { max-width: 800px; margin: 0 auto; background: var(--surface); border: 1px solid var(--border); border-radius: 24px; padding: 60px; }
        h1 { font-family: 'Syne'; font-size: 42px; color: var(--accent); margin-bottom: 20px; }
        h2 { font-family: 'Syne'; color: #fff; margin-top: 40px; }
        p { margin-bottom: 20px; color: var(--muted); }
        .back-link { display: inline-block; margin-bottom: 30px; color: var(--accent); text-decoration: none; font-family: 'Space Mono'; font-size: 13px; }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="back-link">← Voltar ao Manual</a>
        <h1>Sobre o <span>Projeto</span></h1>

        <p>O <strong>Manual de Impressão 3D</strong> é uma plataforma educativa desenvolvida como Prova de Aptidão Profissional (PAP). O seu objetivo principal é centralizar o conhecimento técnico sobre a fabricação aditiva, tornando-o acessível tanto para alunos como para professores e entusiastas.</p>

        <h2>Missão Educativa</h2>
        <p>Acreditamos que a impressão 3D é uma ferramenta fundamental para o futuro da indústria e da educação STEAM. Esta plataforma utiliza técnicas de gamificação para incentivar o estudo contínuo, permitindo que os utilizadores acompanhem o seu progresso através de uma "Planta Maker" virtual que cresce à medida que adquirem novos conhecimentos.</p>

        <h2>Tecnologia e Inovação</h2>
        <p>O site integra inteligência artificial de última geração para fornecer suporte técnico instantâneo, auxiliando na resolução de problemas comuns como warping, stringing ou sub-extrusão. Além disso, promovemos a partilha de conhecimento através de um fórum dedicado à comunidade maker portuguesa.</p>

        <h2>O Autor</h2>
        <p>Este projeto foi idealizado e desenvolvido por <strong>Martim Sá</strong>, com o intuito de aplicar competências de desenvolvimento full-stack, gestão de bases de dados e integração de inteligência artificial num contexto prático e útil para a comunidade.</p>

        <div style="margin-top: 60px; padding-top: 30px; border-top: 1px solid var(--border); font-size: 12px; color: var(--muted); text-align: center;">
            © 2024 Manual de Impressão 3D · Todos os direitos reservados.
        </div>
    </div>
</body>
</html>
