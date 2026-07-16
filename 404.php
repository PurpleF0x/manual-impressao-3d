<?php
/**
 * 404.php — Página de erro personalizada
 */
require_once 'includes/functions.php';
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 — Página Não Encontrada</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
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
            height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center;
            overflow: hidden;
        }

        /* Grid background similar ao Manual */
        .grid {
            position: absolute; inset: 0;
            background-image: linear-gradient(rgba(0,229,255,0.03) 1px, transparent 1px),
                              linear-gradient(90deg, rgba(0,229,255,0.03) 1px, transparent 1px);
            background-size: 40px 40px; z-index: 0;
        }

        .container { position: relative; z-index: 1; text-align: center; padding: 20px; }

        .error-code {
            font-family: 'Syne', sans-serif; font-size: 120px; font-weight: 900;
            background: linear-gradient(135deg, var(--accent), var(--accent3));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            line-height: 1; margin-bottom: 10px;
        }

        .printer-icon { font-size: 60px; margin-bottom: 20px; animation: wobble 2s infinite ease-in-out; display: block; }

        @keyframes wobble {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            25% { transform: translateY(-10px) rotate(-5deg); }
            75% { transform: translateY(-5px) rotate(5deg); }
        }

        h1 { font-family: 'Syne', sans-serif; font-size: 24px; font-weight: 700; margin-bottom: 16px; }
        p { color: var(--muted); margin-bottom: 32px; max-width: 400px; }

        .btn-home {
            background: var(--accent); color: #000; padding: 14px 30px; border-radius: 12px;
            text-decoration: none; font-family: 'Space Mono', monospace; font-size: 13px; font-weight: 700;
            transition: all 0.2s; display: inline-block;
        }
        .btn-home:hover { transform: translateY(-3px); box-shadow: 0 10px 30px rgba(0,229,255,0.3); }

        .glitch-text { font-family: 'Space Mono', monospace; font-size: 10px; color: var(--accent2); margin-top: 20px; opacity: 0.6; }
    </style>
</head>
<body>
    <div class="grid"></div>

    <div class="container">
        <span class="printer-icon">🖨️⚠️</span>
        <div class="error-code">404</div>
        <h1>Peça não encontrada</h1>
        <p>Parece que o caminho que tentaste imprimir não existe ou o ficheiro G-code foi movido.</p>

        <a href="/index" class="btn-home">REINICIAR IMPRESSÃO (HOME)</a>

        <div class="glitch-text">> Error_Log: layer_height_not_found_at_this_url</div>
    </div>
</body>
</html>
