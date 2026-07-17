<?php
/**
 * perfil_publico.php — Perfil público de um utilizador
 * URL: perfil_publico.php?id=X
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/comments.php';

if (!isLoggedIn()) redirect('login.php');

$viewer   = getCurrentUser();
$targetId = (int)($_GET['id'] ?? 0);

if ($targetId < 1) redirect('perfil.php');
if ($targetId === (int)$viewer['id']) redirect('perfil.php');

$db = getDB();

$stmt = $db->prepare(
    "SELECT id, full_name, username, role, avatar_url, avatar,
            bio, location, website, experience_level, created_at, is_active
     FROM users WHERE id = ? LIMIT 1"
);
$stmt->execute([$targetId]);
$profile = $stmt->fetch();

if (!$profile || !$profile['is_active']) {
    http_response_code(404);
    die('<div style="font-family:Arial;text-align:center;padding:80px;color:#888">
        <p style="font-size:48px">👤</p>
        <h2 style="color:#fff">Utilizador não encontrado</h2>
        <p><a href="index.php" style="color:#00e5ff">← Voltar ao início</a></p>
    </div>');
}

foreach (['bio TEXT', 'location VARCHAR(100)', 'website VARCHAR(255)', 'avatar_url VARCHAR(500)', "experience_level ENUM('iniciante','intermedio','avancado','profissional') DEFAULT 'iniciante'"] as $col) {
    try { $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS {$col}"); } catch(Exception $e){}
}

$printers  = $db->prepare("SELECT * FROM user_printers  WHERE user_id=? ORDER BY created_at DESC"); $printers->execute([$targetId]);  $printers=$printers->fetchAll();
$slicers   = $db->prepare("SELECT * FROM user_slicers   WHERE user_id=? ORDER BY created_at DESC"); $slicers->execute([$targetId]);   $slicers=$slicers->fetchAll();
$materials = $db->prepare("SELECT * FROM user_materials WHERE user_id=? ORDER BY created_at DESC"); $materials->execute([$targetId]); $materials=$materials->fetchAll();

$myComments = getUserComments($targetId);

$expLvl    = $profile['experience_level'] ?? 'iniciante';
$expLabels = ['iniciante'=>'Iniciante','intermedio'=>'Intermédio','avancado'=>'Avançado','profissional'=>'Profissional'];
$bio       = $profile['bio']      ?? '';
$loc       = $profile['location'] ?? '';
$web       = $profile['website']  ?? '';
$avUrl     = $profile['avatar_url'] ?? '';

// XP e Emblemas
$karmaTotal    = (int)($profile['karma_total'] ?? 0);
$currentLevel  = getUserLevel($karmaTotal);
$nextLevelXP   = $currentLevel['next'];
$xpProgress    = 100;
if ($nextLevelXP) {
    $range = $nextLevelXP - $currentLevel['min'];
    $currentRange = $karmaTotal - $currentLevel['min'];
    $xpProgress = min(100, max(0, round(($currentRange / $range) * 100)));
}
$topBadges = getTopBadges($targetId);

$csrf = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
    <link rel="icon" type="image/x-icon"  href="/favicons/favicon-perfil.ico">
    <link rel="icon" type="image/svg+xml" href="/favicons/favicon-perfil.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicons/favicon-perfil-32.png">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Perfil de <?php echo sanitize($profile['full_name']); ?> — Manual 3D</title>
<link rel="icon" type="image/x-icon"  href="/favicon.ico">
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png">
<link rel="apple-touch-icon" sizes="192x192" href="/favicon-192.png">
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
<style>
:root {
    --bg:#0a0a0f; --surface:#111118; --surface2:#1a1a26; --surface3:#222235;
    --accent:#00e5ff; --accent2:#ff6b35; --accent3:#7c3aed; --accent4:#00ff88;
    --text:#e8e8f0; --muted:#888899; --border:rgba(0,229,255,0.15);
}
*{margin:0;padding:0;box-sizing:border-box}
body{background:var(--bg);color:var(--text);font-family:'Inter',sans-serif}

.topbar{position:sticky;top:0;z-index:100;height:52px;background:var(--surface);
    border-bottom:1px solid var(--border);display:flex;align-items:center;
    justify-content:space-between;padding:0 32px;gap:16px}
.topbar-left{display:flex;align-items:center;gap:16px}
.back-btn{display:flex;align-items:center;gap:8px;color:var(--muted);text-decoration:none;
    font-family:'Space Mono',monospace;font-size:11px;padding:8px 14px;border-radius:8px;
    border:1px solid var(--border);transition:all 0.2s}
.back-btn:hover{color:var(--accent);border-color:rgba(0,229,255,0.3)}
.topbar-title{font-family:'Syne',sans-serif;font-size:16px;font-weight:700;color:#fff}
.topbar-right{display:flex;align-items:center;gap:10px}
.viewer-info{display:flex;align-items:center;gap:8px;font-size:12px;color:var(--muted)}
.viewer-avatar{width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,var(--accent),var(--accent3));display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#fff;overflow:hidden}
.viewer-avatar img{width:100%;height:100%;object-fit:cover}

.hero{position:relative;padding:52px 60px 44px;overflow:hidden;
    border-bottom:1px solid var(--border);
    background:linear-gradient(135deg,#0a0a0f 0%,#0d0d1a 50%,#0a0a0f 100%)}
.hero-grid{position:absolute;inset:0;background-image:linear-gradient(rgba(0,229,255,0.04) 1px,transparent 1px),linear-gradient(90deg,rgba(0,229,255,0.04) 1px,transparent 1px);background-size:40px 40px;animation:gridMove 20s linear infinite}
@keyframes gridMove{0%{transform:translate(0,0)}100%{transform:translate(40px,40px)}}
.hero-glow{position:absolute;top:-80px;right:-80px;width:400px;height:400px;background:radial-gradient(circle,rgba(124,58,237,0.18) 0%,transparent 70%);pointer-events:none}
.hero-inner{position:relative;z-index:1;display:flex;gap:32px;align-items:flex-start;flex-wrap:wrap}

.hero-avatar{width:100px;height:100px;border-radius:50%;border:3px solid var(--accent);
    background:linear-gradient(135deg,var(--accent3),var(--accent));
    display:flex;align-items:center;justify-content:center;
    font-family:'Syne',sans-serif;font-size:32px;font-weight:800;color:#fff;
    box-shadow:0 0 28px rgba(0,229,255,0.2);overflow:hidden;flex-shrink:0}
.hero-avatar img{width:100%;height:100%;object-fit:cover;border-radius:50%}

.hero-info{flex:1;min-width:200px}
.hero-name{font-family:'Syne',sans-serif;font-size:30px;font-weight:800;color:#fff;line-height:1.1;margin-bottom:4px}
.hero-username{font-family:'Space Mono',monospace;font-size:12px;color:var(--accent);margin-bottom:14px}
.hero-meta{display:flex;gap:12px;flex-wrap:wrap;align-items:center;margin-bottom:10px}
.badge-exp{padding:4px 14px;border-radius:100px;font-family:'Space Mono',monospace;font-size:10px;letter-spacing:1px;text-transform:uppercase;font-weight:700}
.badge-exp.iniciante{background:rgba(0,229,255,0.1);color:var(--accent);border:1px solid rgba(0,229,255,0.3)}
.badge-exp.intermedio{background:rgba(255,107,53,0.1);color:var(--accent2);border:1px solid rgba(255,107,53,0.3)}
.badge-exp.avancado{background:rgba(124,58,237,0.1);color:#a78bfa;border:1px solid rgba(124,58,237,0.3)}
  .badge-exp.profissional{background:rgba(0,255,136,0.1);color:var(--accent4);border:1px solid rgba(0,255,136,0.3)}

  /* WIDGET XP */
  .xp-widget{background:rgba(0,0,0,0.2);border:1px solid var(--border);border-radius:12px;padding:12px 16px;min-width:200px;text-align:left}
  .xp-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px}
  .xp-level-name{font-family:'Space Mono',monospace;font-size:10px;text-transform:uppercase;letter-spacing:1px;font-weight:700}
  .xp-value{font-family:'Space Mono',monospace;font-size:11px;color:var(--muted)}
  .xp-bar-bg{height:6px;background:rgba(255,255,255,0.05);border-radius:10px;overflow:hidden}
  .xp-bar-fill{height:100%;transition:width 0.5s ease-out;box-shadow:0 0 10px rgba(0,229,255,0.3)}
  .xp-next{font-family:'Space Mono',monospace;font-size:9px;color:var(--muted);margin-top:6px;text-align:right}

  .badges-row{display:flex;gap:8px;margin-top:12px}
  .badge-slot{width:32px;height:32px;border-radius:8px;background:var(--surface3);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:18px;transition:all 0.2s}
  .badge-slot:hover{transform:scale(1.1);border-color:var(--accent)}
.meta-item{display:flex;align-items:center;gap:5px;font-size:13px;color:var(--muted)}
.meta-item a{color:var(--accent);text-decoration:none}
.hero-bio{color:var(--muted);font-size:14px;line-height:1.7;max-width:520px}

.hero-stats{display:flex;flex-direction:column;gap:14px;flex-shrink:0}
.stat-box{text-align:right}
.stat-num{font-family:'Syne',sans-serif;font-size:26px;font-weight:800;color:var(--accent);line-height:1}
.stat-lbl{font-family:'Space Mono',monospace;font-size:9px;color:var(--muted);letter-spacing:2px;text-transform:uppercase;margin-top:2px}

.btn-message{position:absolute;top:20px;right:140px;z-index:2;
    display:inline-flex;align-items:center;gap:7px;
    background:rgba(0,229,255,0.08);border:1px solid rgba(0,229,255,0.25);
    border-radius:9px;padding:8px 16px;color:var(--accent);
    font-family:'Space Mono',monospace;font-size:10px;font-weight:700;
    letter-spacing:0.5px;text-decoration:none;
    transition:all 0.2s}
.btn-message:hover{background:rgba(0,229,255,0.16);border-color:rgba(0,229,255,0.45)}

.btn-report{position:absolute;top:20px;right:24px;z-index:2;
    background:rgba(255,68,68,0.08);border:1px solid rgba(255,68,68,0.25);
    color:#ff7777;border-radius:8px;padding:8px 16px;
    font-family:'Space Mono',monospace;font-size:10px;font-weight:700;
    cursor:pointer;transition:all 0.2s}
.btn-report:hover{background:rgba(255,68,68,0.18);border-color:rgba(255,68,68,0.45)}

main{max-width:960px;margin:0 auto;padding:40px 32px}
.section{margin-bottom:40px}
.section-title{font-family:'Syne',sans-serif;font-size:18px;font-weight:800;color:#fff;
    margin-bottom:16px;display:flex;align-items:center;gap:10px}
.section-title .count{font-family:'Space Mono',monospace;font-size:11px;color:var(--muted);
    background:var(--surface2);padding:3px 10px;border-radius:100px}

.items-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px}
.item-card{background:var(--surface);border:1px solid var(--border);border-radius:12px;
    padding:18px 20px;transition:all 0.2s}
.item-card:hover{border-color:rgba(0,229,255,0.25);transform:translateY(-2px)}
.item-name{font-family:'Syne',sans-serif;font-size:15px;font-weight:700;color:#fff;margin-bottom:6px}
.item-sub{font-size:12px;color:var(--muted);margin-top:4px}
.tag{display:inline-block;padding:3px 10px;border-radius:20px;font-family:'Space Mono',monospace;font-size:10px;background:var(--surface3);color:var(--muted);border:1px solid var(--border);margin-top:8px}
.tag.fdm{background:rgba(0,229,255,0.07);color:var(--accent)}
.tag.sla{background:rgba(124,58,237,0.1);color:#a78bfa}
.tag.sls{background:rgba(255,107,53,0.1);color:var(--accent2)}
.tag.msla{background:rgba(0,255,136,0.07);color:var(--accent4)}
.tag.outro{background:rgba(136,136,153,0.1);color:var(--muted)}

.empty{color:var(--muted);font-size:14px;padding:20px 0}

.comment-card{background:var(--surface);border:1px solid var(--border);border-radius:12px;
    padding:18px 20px;margin-bottom:10px;transition:border-color 0.2s}
.comment-card:hover{border-color:rgba(0,229,255,0.2)}
.comment-meta{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:10px}
.cat-badge{padding:3px 10px;border-radius:4px;font-family:'Space Mono',monospace;font-size:10px}
.comment-text{font-size:14px;color:var(--text);line-height:1.7}
.comment-footer{margin-top:10px;font-size:12px;color:var(--muted)}

.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.75);
    z-index:9000;align-items:center;justify-content:center;padding:20px}
.modal-overlay.open{display:flex}
.modal{background:#111118;border:1px solid rgba(0,229,255,0.15);border-radius:20px;
    padding:36px;max-width:480px;width:100%;position:relative}
.modal-close{position:absolute;top:16px;right:16px;background:transparent;border:none;
    color:#888;font-size:20px;cursor:pointer;transition:color 0.2s}
.modal-close:hover{color:#fff}
.modal-label{font-family:'Space Mono',monospace;font-size:10px;color:var(--accent2);
    letter-spacing:2px;text-transform:uppercase;margin-bottom:6px}
.modal-title{font-family:'Syne',sans-serif;font-size:20px;font-weight:800;color:#fff;margin-bottom:24px}
.form-label{display:block;font-family:'Space Mono',monospace;font-size:10px;color:var(--muted);
    text-transform:uppercase;letter-spacing:1px;margin-bottom:8px}
.form-select,.form-textarea{width:100%;background:var(--surface2);border:1px solid var(--border);border-radius:10px;
    padding:12px 16px;color:var(--text);font-family:'Inter',sans-serif;font-size:14px;
    transition:border-color 0.2s;margin-bottom:16px}
.form-select:focus,.form-textarea:focus{outline:none;border-color:var(--accent)}
.form-textarea{resize:vertical;min-height:80px}
.form-select{appearance:none;cursor:pointer}
.modal-actions{display:flex;gap:10px;margin-top:4px}
.btn-submit-report{background:rgba(255,68,68,0.12);border:1px solid rgba(255,68,68,0.3);
    color:#ff7777;border-radius:8px;padding:11px 22px;font-family:'Space Mono',monospace;
    font-size:11px;font-weight:700;cursor:pointer;transition:all 0.2s}
.btn-submit-report:hover{background:rgba(255,68,68,0.22)}
.btn-cancel{background:transparent;border:1px solid var(--border);color:var(--muted);
    border-radius:8px;padding:11px 18px;font-family:'Space Mono',monospace;font-size:11px;cursor:pointer}
.report-status{display:none;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:13px}
.report-status.success{background:rgba(0,255,136,0.08);border:1px solid rgba(0,255,136,0.25);color:var(--accent4)}
.report-status.error{background:rgba(255,68,68,0.08);border:1px solid rgba(255,68,68,0.25);color:#ff8888}

@media(max-width:768px){
    .hero{padding:40px 20px 32px}
    main{padding:28px 16px}
    .hero-stats{flex-direction:row;gap:20px}
    .stat-box{text-align:left}
    .btn-report{top:12px;right:12px}
    .btn-message{top:12px;right:110px}
}
</style>
</head>
<body>

<div class="topbar">
    <div class="topbar-left">
        <a href="javascript:history.back()" class="back-btn">← Voltar</a>
        <div class="topbar-title">Perfil de <?php echo sanitize($profile['full_name']); ?></div>
    </div>
    <div class="topbar-right">
        <div class="viewer-info">
            <div class="viewer-avatar">
                <?php if (!empty($viewer['avatar_url'])): ?>
                    <img src="<?php echo sanitize(avPath($viewer['avatar_url'])); ?>" alt="">
                <?php else: ?>
                    <?php echo sanitize($viewer['avatar'] ?? '??'); ?>
                <?php endif; ?>
            </div>
            <span><?php echo sanitize($viewer['full_name']); ?></span>
        </div>
        <a href="perfil.php" style="color:var(--accent);font-family:'Space Mono',monospace;font-size:11px;text-decoration:none;padding:6px 12px;border:1px solid rgba(0,229,255,0.2);border-radius:8px">O meu perfil</a>
    </div>
</div>

<div class="hero">
    <div class="hero-grid"></div>
    <div class="hero-glow"></div>

    <!-- Botão mensagem — só para utilizadores logados que não são o próprio perfil -->
    <?php if ((int)($_SESSION['user_id'] ?? 0) !== (int)$profile['id']): ?>
    <a href="forum/mensagens.php?user=<?php echo (int)$profile['id']; ?>" class="btn-message">
        💬 Enviar Mensagem
    </a>
    <?php endif; ?>

    <!-- Botão reportar -->
    <button class="btn-report" onclick="document.getElementById('reportModal').classList.add('open')">
        🚨 Reportar
    </button>

    <div class="hero-inner">
        <div class="hero-avatar">
            <?php if (!empty($avUrl)): ?>
                <img src="<?php echo sanitize(avPath($avUrl)); ?>" alt="Foto de perfil">
            <?php else: ?>
                <?php echo sanitize(mb_substr($profile['full_name'], 0, 2)); ?>
            <?php endif; ?>
        </div>

        <div class="hero-info">
            <div class="hero-name"><?php echo sanitize($profile['full_name']); ?></div>
            <div class="hero-username">@<?php echo sanitize($profile['username']); ?></div>
            <div class="hero-meta">
                <span class="badge-exp" style="background:<?php echo $currentLevel['color']; ?>22; color:<?php echo $currentLevel['color']; ?>; border:1px solid <?php echo $currentLevel['color']; ?>55">
                    <?php echo $currentLevel['name']; ?>
                </span>
                <?php if (!empty($loc)): ?><span class="meta-item">📍 <?php echo sanitize($loc); ?></span><?php endif; ?>
                <?php if (!empty($web)): ?><span class="meta-item">🌐 <a href="<?php echo sanitize($web); ?>" target="_blank" rel="noopener noreferrer"><?php echo sanitize(parse_url($web, PHP_URL_HOST) ?: $web); ?></a></span><?php endif; ?>
                <span class="meta-item">📅 Membro desde <?php echo date('M Y', strtotime($profile['created_at'])); ?></span>
            </div>
            <div class="badges-row">
                <?php foreach($topBadges as $tb): ?>
                    <div class="badge-slot" title="<?php echo sanitize($tb['name'] . ': ' . $tb['desc']); ?>">
                        <?php if($tb['category'] === 'badge' || $tb['category'] === 'medal'): ?>
                            <?php echo $tb['icon']; ?>
                        <?php elseif($tb['category'] === 'frame'): ?>
                            <div style="width:18px;height:18px;border-radius:50%;<?php echo $tb['icon']; ?>"></div>
                        <?php elseif($tb['category'] === 'accent'): ?>
                            <div style="width:16px;height:16px;border-radius:50%;background:<?php echo $tb['icon']; ?>"></div>
                        <?php else: ?>
                            🏅
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if (!empty($bio)): ?>
                <div class="hero-bio"><?php echo nl2br(sanitize($bio)); ?></div>
            <?php endif; ?>
        </div>

        <div class="hero-stats">
            <div class="xp-widget">
                <div class="xp-header">
                    <span class="xp-level-name" style="color:<?php echo $currentLevel['color']; ?>"><?php echo $currentLevel['name']; ?></span>
                    <span class="xp-value"><?php echo number_format($karmaTotal); ?> XP</span>
                </div>
                <div class="xp-bar-bg">
                    <div class="xp-bar-fill" style="width: <?php echo $xpProgress; ?>%; background: <?php echo $currentLevel['color']; ?>"></div>
                </div>
                <?php if ($nextLevelXP): ?>
                    <div class="xp-next">Faltam <?php echo ($nextLevelXP - $karmaTotal); ?> XP para o nível <?php echo getUserLevel($nextLevelXP)['name']; ?></div>
                <?php endif; ?>
            </div>

            <?php
            // Carregar GP para o perfil público
            $stmtGP = $db->prepare("SELECT growth_points FROM user_profile_config WHERE user_id = ?");
            $stmtGP->execute([$targetId]);
            $gpVal = (int)($stmtGP->fetchColumn() ?: 0);
            $plantLevel = floor($gpVal / 100);
            $plantProgress = $gpVal % 100;
            $stages = ['Semente', 'Broto', 'Plântula', 'Pequena Árvore', 'Árvore Maker', 'Grande Carvalho Tech'];
            $currentStage = $stages[min($plantLevel, count($stages)-1)];
            ?>
            <div class="growth-widget" style="margin-top: 12px; background: rgba(0, 255, 136, 0.05); border: 1px solid rgba(0, 255, 136, 0.1); border-radius: 12px; padding: 10px 14px; text-align: left;">
                <div style="display: flex; justify-content: space-between; font-family: 'Space Mono', monospace; font-size: 9px; color: var(--accent4); text-transform: uppercase; margin-bottom: 6px;">
                    <span>🌱 Planta</span>
                    <span><?php echo $gpVal; ?> GP</span>
                </div>
                <div style="height: 5px; background: rgba(255,255,255,0.05); border-radius: 10px; overflow: hidden; margin-bottom: 6px;">
                    <div style="width: <?php echo $plantProgress; ?>%; height: 100%; background: var(--accent4);"></div>
                </div>
                <div style="font-size: 10px; color: var(--muted); text-align: right;">Estágio: <strong style="color:#fff"><?php echo $currentStage; ?></strong></div>
            </div>

            <div style="display:flex; gap:16px; margin-top:8px; justify-content: flex-end">
                <div class="stat-box"><div class="stat-num"><?php echo count($printers); ?></div><div class="stat-lbl">Impressoras</div></div>
                <div class="stat-box"><div class="stat-num"><?php echo count($slicers); ?></div><div class="stat-lbl">Slicers</div></div>
                <div class="stat-box"><div class="stat-num"><?php echo count($materials); ?></div><div class="stat-lbl">Materiais</div></div>
            </div>
        </div>
    </div>
</div>

<main>

    <div class="section">
        <div class="section-title">🖨️ Impressoras <span class="count"><?php echo count($printers); ?></span></div>
        <?php if (empty($printers)): ?>
            <div class="empty">Nenhuma impressora registada.</div>
        <?php else: ?>
            <div class="items-grid">
                <?php foreach ($printers as $p): ?>
                <div class="item-card">
                    <div class="item-name"><?php echo sanitize($p['brand']); ?> <?php echo sanitize($p['model']); ?></div>
                    <?php if (!empty($p['bed_size'])): ?><div class="item-sub">📐 <?php echo sanitize($p['bed_size']); ?></div><?php endif; ?>
                    <?php if (!empty($p['notes'])): ?><div class="item-sub">💬 <?php echo sanitize($p['notes']); ?></div><?php endif; ?>
                    <span class="tag <?php echo strtolower($p['type']); ?>"><?php echo $p['type']; ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="section">
        <div class="section-title">⚙️ Slicers <span class="count"><?php echo count($slicers); ?></span></div>
        <?php if (empty($slicers)): ?>
            <div class="empty">Nenhum slicer registado.</div>
        <?php else: ?>
            <div class="items-grid">
                <?php foreach ($slicers as $s): ?>
                <div class="item-card">
                    <div class="item-name"><?php echo sanitize($s['name']); ?></div>
                    <?php if (!empty($s['version'])): ?><div class="item-sub">v<?php echo sanitize($s['version']); ?></div><?php endif; ?>
                    <?php if (!empty($s['notes'])): ?><div class="item-sub">💬 <?php echo sanitize($s['notes']); ?></div><?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="section">
        <div class="section-title">🧵 Materiais <span class="count"><?php echo count($materials); ?></span></div>
        <?php if (empty($materials)): ?>
            <div class="empty">Nenhum material registado.</div>
        <?php else: ?>
            <div class="items-grid">
                <?php foreach ($materials as $m): ?>
                <div class="item-card">
                    <div class="item-name"><?php echo sanitize($m['material']); ?></div>
                    <?php if (!empty($m['brand'])): ?><div class="item-sub">🏷️ <?php echo sanitize($m['brand']); ?></div><?php endif; ?>
                    <?php if (!empty($m['notes'])): ?><div class="item-sub">💬 <?php echo sanitize($m['notes']); ?></div><?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($myComments)): ?>
    <div class="section">
        <div class="section-title">💬 Comentários na comunidade <span class="count"><?php echo count($myComments); ?></span></div>
        <?php
        $catLabels = ['duvida'=>'DÚVIDA','problema'=>'PROBLEMA','dica'=>'DICA','geral'=>'GERAL'];
        $catBg     = ['duvida'=>'rgba(124,58,237,0.1)','problema'=>'rgba(255,107,53,0.1)','dica'=>'rgba(0,255,136,0.07)','geral'=>'rgba(0,229,255,0.07)'];
        $catColor  = ['duvida'=>'#a78bfa','problema'=>'var(--accent2)','dica'=>'var(--accent4)','geral'=>'var(--accent)'];
        foreach (array_slice($myComments, 0, 10) as $c):
            $cat = $c['category'] ?? 'geral';
        ?>
        <div class="comment-card">
            <div class="comment-meta">
                <span class="cat-badge" style="background:<?php echo $catBg[$cat]??'rgba(0,229,255,0.07)'; ?>;color:<?php echo $catColor[$cat]??'var(--accent)'; ?>"><?php echo $catLabels[$cat]??strtoupper($cat); ?></span>
                <?php if ($c['parent_id']): ?><span style="font-family:'Space Mono',monospace;font-size:10px;color:var(--muted)">↩ RESPOSTA</span><?php endif; ?>
                <span style="font-family:'Space Mono',monospace;font-size:10px;color:var(--muted);margin-left:auto"><?php echo date('d/m/Y', strtotime($c['created_at'])); ?></span>
            </div>
            <div class="comment-text"><?php echo nl2br(sanitize(mb_substr($c['content'], 0, 300))); ?><?php echo mb_strlen($c['content']) > 300 ? '…' : ''; ?></div>
            <div class="comment-footer">❤️ <?php echo (int)($c['like_count'] ?? 0); ?> likes</div>
        </div>
        <?php endforeach; ?>
        <?php if (count($myComments) > 10): ?>
            <p style="text-align:center;margin-top:16px"><a href="index.php#comentarios" style="color:var(--accent);font-family:'Space Mono',monospace;font-size:12px">Ver mais na comunidade →</a></p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</main>

<div class="modal-overlay" id="reportModal">
    <div class="modal">
        <button class="modal-close" onclick="document.getElementById('reportModal').classList.remove('open')">✕</button>
        <div class="modal-label">Reportar Utilizador</div>
        <div class="modal-title">Reportar <?php echo sanitize($profile['full_name']); ?></div>
        <div id="reportStatus" class="report-status"></div>
        <label class="form-label">Motivo *</label>
        <select id="reportReason" class="form-select">
            <option value="">Seleciona um motivo...</option>
            <option value="conteudo_obsceno">Conteúdo obsceno no perfil</option>
            <option value="linguagem_ofensiva">Linguagem ofensiva</option>
            <option value="spam">Spam</option>
            <option value="informacao_falsa">Informação falsa</option>
            <option value="outro">Outro</option>
        </select>
        <label class="form-label">Descrição (opcional)</label>
        <textarea id="reportDescription" class="form-textarea" placeholder="Descreve o que aconteceu..."></textarea>
        <div class="modal-actions">
            <button class="btn-submit-report" onclick="submitReport()">🚨 ENVIAR REPORT</button>
            <button class="btn-cancel" onclick="document.getElementById('reportModal').classList.remove('open')">Cancelar</button>
        </div>
    </div>
</div>

<script>
async function submitReport() {
    var reason      = document.getElementById('reportReason').value;
    var description = document.getElementById('reportDescription').value;
    if (!reason) { showStatus('error', '⚠️ Seleciona um motivo.'); return; }
    try {
        var res  = await fetch('api/reports.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({
                action:      'report_user',
                csrf_token:  '<?php echo $csrf; ?>',
                reported_id: <?php echo $targetId; ?>,
                reason:      reason,
                description: description
            })
        });
        var data = await res.json();
        if (data.success) {
            showStatus('success', '✅ ' + data.message);
            setTimeout(function(){ document.getElementById('reportModal').classList.remove('open'); }, 2500);
        } else {
            showStatus('error', '⚠️ ' + (data.error || 'Erro desconhecido.'));
        }
    } catch(e) {
        showStatus('error', '⚠️ Erro de rede. Tenta novamente.');
    }
}

function showStatus(type, msg) {
    var el = document.getElementById('reportStatus');
    el.className = 'report-status ' + type;
    el.textContent = msg;
    el.style.display = 'block';
}

document.getElementById('reportModal').addEventListener('click', function(e) {
    if (e.target === this) this.classList.remove('open');
});
</script>
</body>
</html>