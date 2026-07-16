<?php
/**
 * dashboard.php — Conceito de Dashboard Maker (Apenas Master)
 */
require_once 'includes/functions.php';
require_once 'includes/missions.php';

if (!isLoggedIn()) redirect('login.php');
$currentUser = getCurrentUser();

// Apenas MASTER pode ver este conceito
if (($currentUser['role'] ?? '') !== 'master') {
    http_response_code(403);
    die('<p style="color:red;padding:40px;font-family:sans-serif">Apenas o nível Master tem acesso a esta pré-visualização do Dashboard.</p>');
}

$db = getDB();
$uid = (int)$currentUser['id'];

// Carregar dados dinâmicos
ensureUserProfileConfig($uid);
$config = $db->query("SELECT * FROM user_profile_config WHERE user_id=$uid")->fetch();
$growthPoints = (int)($config['growth_points'] ?? 0);
$karma = (int)($config['karma_total'] ?? 0);

$plantLevel = floor($growthPoints / 100);
$plantProgress = $growthPoints % 100;
$stages = ['Semente', 'Broto', 'Plântula', 'Pequena Árvore', 'Árvore Maker', 'Grande Carvalho Tech'];
$currentStage = $stages[min($plantLevel, count($stages)-1)];
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Maker — Manual 3D</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0a0a0f; --surface: #111118; --surface2: #1a1a26;
            --accent: #00e5ff; --accent2: #ff6b35; --accent3: #7c3aed; --accent4: #00ff88;
            --text: #e8e8f0; --muted: #888899; --border: rgba(0,229,255,0.12);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: var(--bg); color: var(--text); font-family: 'Inter', sans-serif; overflow-x: hidden; }

        .layout { display: grid; grid-template-columns: 280px 1fr; min-height: 100vh; }

        .sidebar { background: var(--surface); border-right: 1px solid var(--border); padding: 40px 30px; }
        .sidebar-logo { font-family: 'Syne', sans-serif; font-size: 24px; font-weight: 800; margin-bottom: 50px; }
        .sidebar-logo span { color: var(--accent); }
        .nav-item { display: flex; align-items: center; gap: 12px; padding: 15px 0; color: var(--muted); text-decoration: none; transition: 0.2s; font-size: 14px; }
        .nav-item:hover, .nav-item.active { color: #fff; }
        .nav-item.active { font-weight: 700; color: var(--accent); }

        .main { padding: 40px 60px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; }
        .welcome h1 { font-family: 'Syne', sans-serif; font-size: 28px; margin-bottom: 5px; }
        .welcome p { color: var(--muted); font-size: 14px; }

        .dashboard-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 30px; }
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: 24px; padding: 30px; position: relative; overflow: hidden; }

        /* Widgets */
        .widget-title { font-family: 'Space Mono', monospace; font-size: 11px; text-transform: uppercase; letter-spacing: 2px; color: var(--muted); margin-bottom: 20px; display: flex; justify-content: space-between; }

        .growth-box { background: linear-gradient(135deg, rgba(0,255,136,0.05), transparent); }
        .growth-stage { font-family: 'Syne', sans-serif; font-size: 24px; color: var(--accent4); margin-bottom: 15px; }
        .progress-bar { height: 10px; background: rgba(255,255,255,0.05); border-radius: 20px; overflow: hidden; }
        .progress-fill { height: 100%; background: var(--accent4); box-shadow: 0 0 20px rgba(0,255,136,0.3); transition: 1s cubic-bezier(0.4, 0, 0.2, 1); }

        .stat-group { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 30px; }
        .stat-item { background: var(--surface2); padding: 20px; border-radius: 18px; border: 1px solid var(--border); }
        .stat-val { font-family: 'Syne', sans-serif; font-size: 22px; color: #fff; }
        .stat-lbl { font-size: 11px; color: var(--muted); }

        .tools-list { display: flex; flex-direction: column; gap: 12px; }
        .tool-btn { background: var(--surface2); border: 1px solid var(--border); border-radius: 15px; padding: 18px; display: flex; align-items: center; gap: 15px; text-decoration: none; color: #fff; transition: 0.2s; }
        .tool-btn:hover { border-color: var(--accent); transform: translateX(5px); }
        .tool-icon { font-size: 24px; }

        .mission-mini { background: rgba(255,107,53,0.05); border-color: rgba(255,107,53,0.2); }

        @media (max-width: 1100px) { .layout { grid-template-columns: 1fr; } .sidebar { display: none; } .dashboard-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="layout">
        <aside class="sidebar">
            <div class="sidebar-logo">MANUAL <span>3D</span></div>
            <nav>
                <a href="#" class="nav-item active">🏠 Dashboard</a>
                <a href="/index" class="nav-item">📖 Continuar Manual</a>
                <a href="/forum/" class="nav-item">🌐 Fórum Global</a>
                <a href="/perfil" class="nav-item">👤 O meu Perfil</a>
                <a href="/calculadora" class="nav-item">🧮 Calculadoras</a>
                <a href="/moderacao" class="nav-item">🛡️ Moderação</a>
            </nav>
        </aside>

        <main class="main">
            <div class="header">
                <div class="welcome">
                    <h1>Olá, <?php echo explode(' ', $currentUser['full_name'])[0]; ?>! 👋</h1>
                    <p>Pronto para mais um dia de fabricação aditiva?</p>
                </div>
                <div style="text-align: right">
                    <div style="font-family:'Space Mono'; font-size:12px; color:var(--accent)">MODO MASTER ATIVO</div>
                    <div style="font-size:11px; color:var(--muted)">Versão de Pré-visualização do Dashboard</div>
                </div>
            </div>

            <div class="dashboard-grid">
                <section>
                    <div class="card growth-box">
                        <div class="widget-title">
                            <span>🌱 Evolução da Planta Maker</span>
                            <span><?php echo $growthPoints; ?> GP</span>
                        </div>
                        <div class="growth-stage"><?php echo $currentStage; ?></div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $plantProgress; ?>%"></div>
                        </div>
                        <p style="font-size:12px; color:var(--muted); margin-top:15px">Faltam <?php echo (100 - $plantProgress); ?> GP para o próximo estágio da tua planta.</p>

                        <div class="stat-group">
                            <div class="stat-item">
                                <div class="stat-val"><?php echo number_format($karma); ?></div>
                                <div class="stat-lbl">XP Acumulado</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-val">🛡️ Master</div>
                                <div class="stat-lbl">Nível de Conta</div>
                            </div>
                        </div>
                    </div>

                    <div class="card mission-mini" style="margin-top: 30px">
                        <div class="widget-title">🚀 Missões em Destaque</div>
                        <div style="display:flex; gap:15px; align-items:center">
                            <div style="font-size:32px">🎯</div>
                            <div>
                                <h4 style="font-family:'Syne'">Membro Ativo</h4>
                                <p style="font-size:12px; color:var(--muted)">Publica 1 comentário hoje para ganhar 20 XP e 10 GP.</p>
                            </div>
                            <a href="/index#comentarios" class="tool-btn" style="margin-left:auto; padding:10px 20px; font-size:11px">IR AGORA</a>
                        </div>
                    </div>
                </section>

                <aside>
                    <div class="card">
                        <div class="widget-title">🛠️ Ferramentas Rápidas</div>
                        <div class="tools-list">
                            <a href="/calculadora" class="tool-btn">
                                <span class="tool-icon">💰</span>
                                <div>
                                    <div style="font-weight:700">Calculadora</div>
                                    <div style="font-size:10px; color:var(--muted)">Custos e Energia</div>
                                </div>
                            </a>
                            <a href="/forum/criar_post" class="tool-btn">
                                <span class="tool-icon">✏️</span>
                                <div>
                                    <div style="font-weight:700">Novo Post</div>
                                    <div style="font-size:10px; color:var(--muted)">Partilhar dúvidas</div>
                                </div>
                            </a>
                            <a href="https://tinkercad.com" target="_blank" class="tool-btn">
                                <span class="tool-icon">🎨</span>
                                <div>
                                    <div style="font-weight:700">Tinkercad</div>
                                    <div style="font-size:10px; color:var(--muted)">Desenho 3D Online</div>
                                </div>
                            </a>
                        </div>
                    </div>
                </aside>
            </div>
        </main>
    </div>
</body>
</html>
