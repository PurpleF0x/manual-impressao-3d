<?php
/**
 * landing.php — Página de entrada do site
 * Redireciona para o manual ou mostra a landing page
 */
require_once 'includes/functions.php';
$currentUser = isLoggedIn() ? getCurrentUser() : null;
?><!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manual de Impressão 3D — Aprende a imprimir em 3D</title>
<link rel="icon" type="image/x-icon"  href="/favicons/favicon-manual.ico">
<link rel="icon" type="image/svg+xml" href="/favicons/favicon-manual.svg">
<link rel="icon" type="image/png" sizes="32x32" href="/favicons/favicon-manual-32.png">
<meta property="og:title" content="Manual de Impressão 3D">
<meta property="og:description" content="Guia educativo completo de impressão 3D — do iniciante ao avançado.">
<meta property="og:image" content="https://manual-impressao-3d.free.nf/og-manual.jpg">
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800;900&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root{--bg:#0a0a0f;--surface:#111118;--surface2:#1a1a26;--accent:#00e5ff;--accent2:#ff6b35;--accent3:#7c3aed;--accent4:#00ff88;--text:#e8e8f0;--muted:#888899;--border:rgba(0,229,255,0.12)}
*{margin:0;padding:0;box-sizing:border-box}
html{scroll-behavior:smooth}
body{background:var(--bg);color:var(--text);font-family:'Inter',sans-serif;min-height:100vh;overflow-x:hidden}

/* Noise */
body::before{content:'';position:fixed;inset:0;pointer-events:none;z-index:0;
  background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.03'/%3E%3C/svg%3E");opacity:0.5}

/* Topbar */
.topbar{position:fixed;top:0;left:0;right:0;z-index:100;height:56px;background:rgba(10,10,15,0.85);backdrop-filter:blur(20px);border-bottom:1px solid var(--border);display:flex;align-items:center;padding:0 40px;gap:16px}
.logo{font-family:'Space Mono',monospace;font-size:12px;font-weight:700;color:var(--accent);letter-spacing:3px;text-decoration:none}
.logo span{color:var(--muted)}
.topbar-right{margin-left:auto;display:flex;gap:10px;align-items:center}
.btn{padding:8px 18px;border-radius:8px;font-family:'Space Mono',monospace;font-size:11px;font-weight:700;text-decoration:none;transition:all 0.2s;cursor:pointer;border:none}
.btn-ghost{background:none;border:1px solid var(--border);color:var(--muted)}
.btn-ghost:hover{border-color:var(--accent);color:var(--accent)}
.btn-primary{background:var(--accent);color:#000}
.btn-primary:hover{box-shadow:0 0 24px rgba(0,229,255,0.4);transform:translateY(-1px)}

/* Hero */
.hero{min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:100px 40px 60px;text-align:center;position:relative}
.hero-glow1{position:absolute;top:10%;right:10%;width:500px;height:500px;background:radial-gradient(circle,rgba(124,58,237,0.18) 0%,transparent 70%);pointer-events:none;animation:pulse 6s ease-in-out infinite}
.hero-glow2{position:absolute;bottom:15%;left:5%;width:400px;height:400px;background:radial-gradient(circle,rgba(0,229,255,0.1) 0%,transparent 70%);pointer-events:none;animation:pulse 6s ease-in-out infinite 3s}
.hero-grid{position:absolute;inset:0;background-image:linear-gradient(rgba(0,229,255,0.03) 1px,transparent 1px),linear-gradient(90deg,rgba(0,229,255,0.03) 1px,transparent 1px);background-size:40px 40px;animation:gridMove 25s linear infinite}
@keyframes gridMove{0%{transform:translate(0,0)}100%{transform:translate(40px,40px)}}
@keyframes pulse{0%,100%{opacity:0.5;transform:scale(1)}50%{opacity:0.8;transform:scale(1.05)}}

.hero-tag{display:inline-flex;align-items:center;gap:8px;background:rgba(0,229,255,0.08);border:1px solid rgba(0,229,255,0.25);border-radius:100px;padding:8px 20px;font-family:'Space Mono',monospace;font-size:11px;color:var(--accent);letter-spacing:1px;margin-bottom:28px;position:relative;z-index:1}
.hero-tag::before{content:'';width:6px;height:6px;background:var(--accent);border-radius:50%;animation:blink 2s infinite}
@keyframes blink{0%,100%{opacity:1;box-shadow:0 0 8px var(--accent)}50%{opacity:0.4}}

.hero h1{font-family:'Syne',sans-serif;font-size:clamp(42px,6vw,80px);font-weight:900;line-height:1.05;margin-bottom:24px;position:relative;z-index:1}
.hero h1 .line1{background:linear-gradient(135deg,#fff 0%,rgba(255,255,255,0.8) 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;display:block}
.hero h1 .line2{background:linear-gradient(135deg,var(--accent) 0%,var(--accent3) 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;display:block}

.hero-sub{font-size:18px;color:var(--muted);max-width:560px;line-height:1.7;margin:0 auto 40px;position:relative;z-index:1}

.hero-cta{display:flex;gap:14px;justify-content:center;flex-wrap:wrap;position:relative;z-index:1;margin-bottom:60px}
.cta-main{background:linear-gradient(135deg,var(--accent),var(--accent3));color:#000;padding:16px 32px;border-radius:12px;font-family:'Space Mono',monospace;font-size:13px;font-weight:700;text-decoration:none;transition:all 0.3s;display:inline-flex;align-items:center;gap:10px}
.cta-main:hover{transform:translateY(-3px);box-shadow:0 8px 32px rgba(0,229,255,0.35)}
.cta-sec{background:var(--surface);border:1px solid var(--border);color:var(--muted);padding:16px 28px;border-radius:12px;font-family:'Space Mono',monospace;font-size:13px;text-decoration:none;transition:all 0.3s;display:inline-flex;align-items:center;gap:10px}
.cta-sec:hover{border-color:var(--accent3);color:#a78bfa;transform:translateY(-2px)}

/* Stats row */
.stats-row{display:flex;gap:0;background:var(--surface);border:1px solid var(--border);border-radius:16px;overflow:hidden;position:relative;z-index:1;width:100%;max-width:700px;margin:0 auto}
.stat-box{flex:1;padding:20px;text-align:center;border-right:1px solid var(--border)}
.stat-box:last-child{border-right:none}
.stat-n{font-family:'Syne',sans-serif;font-size:28px;font-weight:900;color:var(--accent);line-height:1}
.stat-l{font-family:'Space Mono',monospace;font-size:9px;color:var(--muted);letter-spacing:2px;text-transform:uppercase;margin-top:5px}

/* Features */
.features{padding:80px 40px;max-width:1100px;margin:0 auto;position:relative;z-index:1}
.features-title{text-align:center;font-family:'Syne',sans-serif;font-size:36px;font-weight:800;color:#fff;margin-bottom:8px}
.features-sub{text-align:center;color:var(--muted);font-size:15px;margin-bottom:48px}
.features-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:20px}
.feat-card{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:28px;transition:all 0.3s;position:relative;overflow:hidden}
.feat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,var(--accent),var(--accent3));transform:scaleX(0);transform-origin:left;transition:transform 0.3s}
.feat-card:hover{border-color:rgba(0,229,255,0.25);transform:translateY(-4px);box-shadow:0 8px 32px rgba(0,0,0,0.3)}
.feat-card:hover::before{transform:scaleX(1)}
.feat-icon{font-size:36px;margin-bottom:16px;display:block}
.feat-title{font-family:'Syne',sans-serif;font-size:18px;font-weight:700;color:#fff;margin-bottom:8px}
.feat-desc{font-size:14px;color:var(--muted);line-height:1.7}
.feat-badge{font-family:'Space Mono',monospace;font-size:9px;font-weight:700;padding:3px 8px;border-radius:4px;margin-top:14px;display:inline-block}
.badge-free{background:rgba(0,255,136,0.1);color:var(--accent4);border:1px solid rgba(0,255,136,0.2)}
.badge-novo{background:rgba(124,58,237,0.1);color:#a78bfa;border:1px solid rgba(124,58,237,0.2)}

/* Para quem */
.for-who{padding:60px 40px;background:var(--surface);border-top:1px solid var(--border);border-bottom:1px solid var(--border);position:relative;z-index:1}
.for-who-inner{max-width:1100px;margin:0 auto}
.for-who h2{font-family:'Syne',sans-serif;font-size:30px;font-weight:800;color:#fff;text-align:center;margin-bottom:36px}
.who-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px}
.who-card{background:var(--surface2);border:1px solid var(--border);border-radius:12px;padding:20px;display:flex;gap:14px;align-items:flex-start;transition:border-color 0.2s}
.who-card:hover{border-color:rgba(0,229,255,0.2)}
.who-icon{font-size:28px;flex-shrink:0}
.who-title{font-size:15px;font-weight:600;color:#fff;margin-bottom:4px}
.who-desc{font-size:13px;color:var(--muted);line-height:1.5}

/* Footer */
.footer{padding:40px;text-align:center;color:var(--muted);font-family:'Space Mono',monospace;font-size:11px;position:relative;z-index:1}
.footer strong{color:var(--accent)}
.footer a{color:var(--muted);text-decoration:none;transition:color 0.2s}
.footer a:hover{color:var(--accent)}

@media(max-width:768px){
  .topbar{padding:0 20px}
  .hero{padding:80px 20px 40px}
  .stats-row{flex-direction:column}
  .stat-box{border-right:none;border-bottom:1px solid var(--border)}
  .stat-box:last-child{border-bottom:none}
  .features,.for-who{padding:50px 20px}
  .footer{padding:30px 20px}
}
</style>
</head>
<body>

<nav class="topbar">
    <a href="/" class="logo">3D<span>/</span>MANUAL</a>
    <div class="topbar-right">
        <?php if ($currentUser): ?>
            <span style="font-size:13px;color:var(--muted)"><?php echo sanitize($currentUser['full_name']); ?></span>
            <a href="/" class="btn btn-primary">Ir ao Manual →</a>
        <?php else: ?>
            <a href="/login" class="btn btn-ghost">Entrar</a>
            <a href="/register" class="btn btn-primary">Criar conta</a>
        <?php endif; ?>
    </div>
</nav>

<!-- Hero -->
<section class="hero">
    <div class="hero-grid"></div>
    <div class="hero-glow1"></div>
    <div class="hero-glow2"></div>

    <div class="hero-tag">📖 Manual Educativo · Gratuito · Sempre actualizado</div>

    <h1>
        <span class="line1">Aprende Impressão 3D</span>
        <span class="line2">do Zero ao Avançado</span>
    </h1>

    <p class="hero-sub">
        Um guia completo e gratuito sobre impressão 3D — desde os conceitos básicos
        até às técnicas profissionais. Para alunos, professores e makers.
    </p>

    <div class="hero-cta">
        <a href="/" class="cta-main">📖 Começar a ler o Manual</a>
        <a href="/forum/" class="cta-sec">🌐 Ir para o Fórum</a>
    </div>

    <div class="stats-row">
        <div class="stat-box"><div class="stat-n">11</div><div class="stat-l">Capítulos</div></div>
        <div class="stat-box"><div class="stat-n">3</div><div class="stat-l">Tecnologias</div></div>
        <div class="stat-box"><div class="stat-n">10+</div><div class="stat-l">Filamentos</div></div>
        <div class="stat-box"><div class="stat-n">2</div><div class="stat-l">Níveis</div></div>
    </div>
</section>

<!-- Features -->
<section class="features">
    <div class="features-title">O que encontras aqui</div>
    <div class="features-sub">Tudo o que precisas para começar e evoluir em impressão 3D</div>
    <div class="features-grid">
        <div class="feat-card">
            <span class="feat-icon">🎓</span>
            <div class="feat-title">Dois modos de leitura</div>
            <div class="feat-desc">Alterna entre <strong>Iniciante</strong> e <strong>Avançado</strong> em qualquer momento. O conteúdo adapta-se ao teu nível sem mudar de página.</div>
            <span class="feat-badge badge-free">GRATUITO</span>
        </div>
        <div class="feat-card">
            <span class="feat-icon">🖨️</span>
            <div class="feat-title">Guia completo de equipamentos</div>
            <div class="feat-desc">FDM, SLA, SLS — comparações detalhadas entre tecnologias, impressoras para iniciantes e opções profissionais com faixas de preço.</div>
        </div>
        <div class="feat-card">
            <span class="feat-icon">🧵</span>
            <div class="feat-title">Todos os filamentos explicados</div>
            <div class="feat-desc">PLA, PETG, ABS, TPU, Nylon, PEEK e mais — quando usar cada um, temperaturas, dicas e erros a evitar.</div>
        </div>
        <div class="feat-card">
            <span class="feat-icon">🛠️</span>
            <div class="feat-title">Resolução de problemas</div>
            <div class="feat-desc">Warping, stringing, layer splitting — guia de troubleshooting com causas e soluções para os problemas mais comuns.</div>
        </div>
        <div class="feat-card">
            <span class="feat-icon">🌐</span>
            <div class="feat-title">Fórum da comunidade</div>
            <div class="feat-desc">Tira dúvidas, partilha projetos e aprende com outros makers. Um fórum dedicado exclusivamente a impressão 3D.</div>
            <span class="feat-badge badge-novo">FÓRUM</span>
        </div>
        <div class="feat-card">
            <span class="feat-icon">🤖</span>
            <div class="feat-title">Assistente de IA integrado</div>
            <div class="feat-desc">O Print AI responde às tuas dúvidas técnicas em tempo real enquanto lês o manual. Disponível em todas as páginas.</div>
            <span class="feat-badge badge-novo">IA</span>
        </div>
    </div>
</section>

<!-- Para quem -->
<section class="for-who">
    <div class="for-who-inner">
        <h2>Para quem é este manual?</h2>
        <div class="who-grid">
            <div class="who-card">
                <span class="who-icon">🎒</span>
                <div>
                    <div class="who-title">Alunos</div>
                    <div class="who-desc">Estás a começar e queres perceber como funciona a impressão 3D de forma clara e estruturada.</div>
                </div>
            </div>
            <div class="who-card">
                <span class="who-icon">📐</span>
                <div>
                    <div class="who-title">Professores</div>
                    <div class="who-desc">Precisas de um recurso didático organizado para usar em sala de aula ou recomendar aos alunos.</div>
                </div>
            </div>
            <div class="who-card">
                <span class="who-icon">🔧</span>
                <div>
                    <div class="who-title">Hobbyistas</div>
                    <div class="who-desc">Já tens uma impressora mas queres aprofundar os conhecimentos técnicos e resolver problemas.</div>
                </div>
            </div>
            <div class="who-card">
                <span class="who-icon">🏭</span>
                <div>
                    <div class="who-title">Profissionais</div>
                    <div class="who-desc">Usas impressão 3D no trabalho e queres referências técnicas rápidas sobre materiais e parâmetros.</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Footer -->
<footer class="footer">
    <p style="margin-bottom:10px">
        <a href="/">📖 Manual</a>
        <span style="margin:0 12px;opacity:0.3">·</span>
        <a href="/forum/">🌐 Fórum</a>
        <span style="margin:0 12px;opacity:0.3">·</span>
        <a href="/login">Entrar</a>
        <span style="margin:0 12px;opacity:0.3">·</span>
        <a href="/register">Criar conta</a>
    </p>
    <p>Manual de Impressão 3D — Recurso educativo <strong>gratuito</strong> para professores e alunos</p>
</footer>

</body>
</html>
