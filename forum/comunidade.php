<?php
/**
 * forum/comunidade.php — Página de uma comunidade
 */
require_once __DIR__ . '/../includes/functions.php';
// Helper: path do avatar relativo ao forum/
function avPath($url) {
    if (!$url) return '';
    if (strpos($url,'http')===0) return $url;
    return '../' . ltrim($url, '/');
}

$currentUser = isLoggedIn() ? getCurrentUser() : null;
$db  = getDB();
$slug = trim($_GET['slug'] ?? '');

if (!$slug) { header('Location: index.php'); exit; }

$stmt = $db->prepare("SELECT fc.*, u.username as owner_name, u.full_name as owner_full
    FROM forum_communities fc JOIN users u ON u.id=fc.created_by
    WHERE fc.slug=? AND fc.is_active=1");
$stmt->execute(array($slug));
$community = $stmt->fetch();
if (!$community) { http_response_code(404); die('<p style="color:#888;padding:40px;font-family:monospace">Comunidade não encontrada.</p>'); }

$commId = (int)$community['id'];

$isMember = false;
$memberRole = null;
if ($currentUser) {
    $ms = $db->prepare("SELECT role FROM forum_memberships WHERE user_id=? AND community_id=?");
    $ms->execute(array((int)$currentUser['id'], $commId));
    $mr = $ms->fetch();
    if ($mr) { $isMember = true; $memberRole = $mr['role']; }
}
$isOwner = $currentUser && (int)$community['created_by'] === (int)$currentUser['id'];
$isGlobMod = $currentUser && in_array($currentUser['role'] ?? '', array('admin','moderator'));
$canMod  = $isOwner || in_array($memberRole, array('owner','admin','moderator')) || $isGlobMod;

$sort    = $_GET['sort'] ?? 'recente';
$orderBy = $sort === 'popular' ? 'fp.vote_score DESC, fp.created_at DESC' : 'fp.is_pinned DESC, fp.created_at DESC';

// Flash message vinda do criar_post.php
$flashMsg = '';
if (!empty($_SESSION['forum_flash'])) {
    $flashMsg = $_SESSION['forum_flash'];
    unset($_SESSION['forum_flash']);
}

// Regras de visibilidade de posts
$postWhere = "AND (fp.status='approved' OR fp.status IS NULL)";
if ($canMod) {
    // Moderadores vêem tudo menos os rejeitados
    $postWhere = "AND fp.status != 'rejected'";
} elseif ($currentUser) {
    // Utilizador vê aprovados + os seus próprios pendentes
    $postWhere = "AND (fp.status='approved' OR fp.status IS NULL OR (fp.status='pending' AND fp.user_id = ".(int)$currentUser['id']."))";
}

$posts = $db->query("
    SELECT fp.*, u.full_name, u.username, u.avatar_url
    FROM forum_posts fp JOIN users u ON u.id=fp.user_id
    WHERE fp.community_id=$commId $postWhere
    ORDER BY $orderBy
    LIMIT 50
")->fetchAll();

$userVotes = array();
if ($currentUser && !empty($posts)) {
    $ids = implode(',', array_map(function($p){ return (int)$p['id']; }, $posts));
    $vr = $db->query("SELECT post_id, value FROM forum_post_votes WHERE user_id=".(int)$currentUser['id']." AND post_id IN ($ids)");
    foreach ($vr->fetchAll() as $v) $userVotes[$v['post_id']] = $v['value'];
}

$topMembers = $db->query("
    SELECT u.id, u.full_name, u.username, u.avatar_url, fm.role, fm.joined_at
    FROM forum_memberships fm JOIN users u ON u.id=fm.user_id
    WHERE fm.community_id=$commId
    ORDER BY FIELD(fm.role,'owner','admin','moderator','member'), fm.joined_at ASC
    LIMIT 10
")->fetchAll();

$csrf        = generateCSRFToken();
$bannerColor = $community['banner_color'] ?: '#00e5ff';

// ── Flair badge helper ─────────────────────────────────────────
function renderFlairBadgeComm($flair) {
    if (!$flair) return '';
    $map = array(
        'pergunta' => array('❓', 'PERGUNTA'),
        'tutorial' => array('📖', 'TUTORIAL'),
        'projeto'  => array('🏗️', 'PROJETO'),
        'ajuda'    => array('🆘', 'AJUDA'),
        'noticia'  => array('📰', 'NOTÍCIA'),
        'debate'   => array('💬', 'DEBATE'),
        'humor'    => array('😄', 'HUMOR'),
        'spoiler'  => array('⚠️', 'SPOILER'),
    );
    if (!isset($map[$flair])) return '';
    // Usar concatenação sem aspas simples dentro da string
    $style = 'display:inline-flex;align-items:center;gap:4px;'
           . 'font-family:Space Mono,monospace;font-size:9px;font-weight:700;'
           . 'padding:2px 8px;border-radius:20px;text-transform:uppercase;'
           . 'background:rgba(0,229,255,0.08);color:var(--accent);'
           . 'border:1px solid rgba(0,229,255,0.2)';
    return '<span style="' . $style . '">' . $map[$flair][0] . ' ' . $map[$flair][1] . '</span>';
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="description" content="<?php echo sanitize(mb_substr($community['description'] ?? '', 0, 155)); ?>">
    <link rel="icon" type="image/x-icon"  href="../favicons/favicon-forum.ico">
    <link rel="icon" type="image/svg+xml" href="../favicons/favicon-forum.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="../favicons/favicon-forum-32.png">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo sanitize($community['name']); ?> — Fórum 3D</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800;900&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root{--bg:#0a0a0f;--surface:#111118;--surface2:#1a1a26;--surface3:#222235;--accent:#00e5ff;--accent2:#ff6b35;--accent3:#7c3aed;--accent4:#00ff88;--text:#e8e8f0;--muted:#888899;--border:rgba(0,229,255,0.1);--border2:rgba(255,255,255,0.06);--comm-color:<?php echo htmlspecialchars($bannerColor); ?>}
*{margin:0;padding:0;box-sizing:border-box}
body{background:var(--bg);color:var(--text);font-family:'Inter',sans-serif;min-height:100vh}

.topbar{position:sticky;top:0;z-index:100;background:rgba(10,10,15,0.92);backdrop-filter:blur(16px);border-bottom:1px solid var(--border);padding:0 32px;display:flex;align-items:center;gap:16px;height:58px}
.topbar-logo{font-family:'Space Mono',monospace;font-size:11px;font-weight:700;color:var(--accent);letter-spacing:3px;text-transform:uppercase;text-decoration:none}
.topbar-logo span{color:var(--muted)}
.topbar-btn{background:none;border:1px solid var(--border2);border-radius:8px;padding:7px 14px;color:var(--muted);font-family:'Space Mono',monospace;font-size:10px;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:6px;transition:all 0.2s;white-space:nowrap}
.topbar-btn:hover{border-color:var(--accent);color:var(--accent);background:rgba(0,229,255,0.05)}
.topbar-btn.primary{background:var(--accent);color:#000;border-color:transparent;font-weight:700}
.topbar-av{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--accent3),var(--accent));display:flex;align-items:center;justify-content:center;font-family:'Space Mono',monospace;font-size:11px;font-weight:700;color:#000;overflow:hidden;text-decoration:none;flex-shrink:0}
.topbar-av img{width:100%;height:100%;object-fit:cover;border-radius:50%}
.ms-actions{margin-left:auto;display:flex;align-items:center;gap:10px}

.comm-banner{height:160px;position:relative;overflow:hidden;background:linear-gradient(135deg,<?php echo $bannerColor; ?>33,<?php echo $bannerColor; ?>11,#0a0a0f)}
.comm-banner::after{content:'';position:absolute;inset:0;background:linear-gradient(to bottom,transparent 40%,var(--bg))}
.comm-banner-pattern{position:absolute;inset:0;opacity:0.06;background-image:repeating-linear-gradient(45deg,<?php echo $bannerColor; ?> 0,<?php echo $bannerColor; ?> 1px,transparent 0,transparent 50%);background-size:20px 20px}

.comm-header{max-width:100%;margin:0;padding:0 32px;position:relative;margin-top:-52px;z-index:2;margin-bottom:0}
.comm-header-inner{display:flex;align-items:flex-end;gap:20px;margin-bottom:20px}
.comm-icon-big{width:80px;height:80px;border-radius:20px;border:3px solid var(--bg);background:linear-gradient(135deg,<?php echo $bannerColor; ?>44,<?php echo $bannerColor; ?>22);display:flex;align-items:center;justify-content:center;font-size:40px;flex-shrink:0}
.comm-meta{flex:1;padding-bottom:4px}
.comm-title{font-family:'Syne',sans-serif;font-size:26px;font-weight:900;color:#fff;line-height:1.1;margin-bottom:4px}
.comm-slug{font-family:'Space Mono',monospace;font-size:10px;color:var(--muted);letter-spacing:1px}
.comm-actions-bar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-top:12px;padding-bottom:16px;border-bottom:1px solid var(--border2)}
.comm-stat-pill{font-family:'Space Mono',monospace;font-size:10px;color:var(--muted);background:var(--surface2);border:1px solid var(--border2);border-radius:20px;padding:4px 12px;display:inline-flex;align-items:center;gap:5px}
.join-btn{background:linear-gradient(135deg,<?php echo $bannerColor; ?>,<?php echo $bannerColor; ?>99);border:none;border-radius:9px;padding:10px 22px;color:#000;font-family:'Space Mono',monospace;font-size:11px;font-weight:700;cursor:pointer;letter-spacing:1px;transition:opacity 0.2s}
.join-btn:hover{opacity:0.85}
.join-btn.leave{background:var(--surface2);color:var(--muted);border:1px solid var(--border2)}
.join-btn.leave:hover{border-color:#ff4444;color:#ff4444}
.comm-desc{font-size:13px;color:var(--muted);line-height:1.6;margin-top:0;padding-bottom:20px}

.layout{max-width:100%;margin:0;padding:0 32px 40px;display:grid;grid-template-columns:1fr 280px;gap:24px}

.sort-bar{display:flex;align-items:center;gap:8px;margin-bottom:16px;flex-wrap:wrap}
.sort-bar a{font-family:'Space Mono',monospace;font-size:10px;padding:5px 12px;border-radius:6px;border:1px solid var(--border2);color:var(--muted);text-decoration:none;transition:all 0.2s}
.sort-bar a.active,.sort-bar a:hover{border-color:var(--comm-color);color:var(--comm-color);background:rgba(0,229,255,0.05)}
.new-post-btn{margin-left:auto;background:linear-gradient(135deg,var(--comm-color),<?php echo $bannerColor; ?>88);border:none;border-radius:9px;padding:9px 18px;color:#000;font-family:'Space Mono',monospace;font-size:10px;font-weight:700;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:6px;letter-spacing:0.5px;transition:opacity 0.2s}
.new-post-btn:hover{opacity:0.85}

.post-card{background:var(--surface);border:1px solid var(--border2);border-radius:14px;margin-bottom:10px;display:flex;overflow:hidden;transition:border-color 0.2s,transform 0.15s}
.post-card:hover{border-color:rgba(0,229,255,0.2);transform:translateY(-1px)}
.post-vote{display:flex;flex-direction:column;align-items:center;gap:4px;padding:16px 12px;background:rgba(0,0,0,0.15);min-width:52px;flex-shrink:0}
.vote-btn{background:none;border:none;cursor:pointer;color:var(--muted);font-size:16px;line-height:1;padding:4px;border-radius:4px;transition:all 0.15s;display:flex;align-items:center;justify-content:center}
.vote-btn:hover{background:var(--surface2)}
.vote-btn.up.active{color:var(--accent2)}
.vote-btn.down.active{color:#7c9aff}
.vote-score{font-family:'Space Mono',monospace;font-size:12px;font-weight:700;color:var(--text);line-height:1}
.vote-score.positive{color:var(--accent2)}.vote-score.negative{color:#7c9aff}
.post-body{flex:1;padding:16px 18px;min-width:0}
.post-title{font-family:'Syne',sans-serif;font-size:16px;font-weight:700;color:#fff;text-decoration:none;line-height:1.35;display:block;margin-bottom:8px;transition:color 0.2s}
.post-title:hover{color:var(--comm-color)}
.post-excerpt{font-size:13px;color:var(--muted);line-height:1.6;margin-bottom:12px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.post-meta{display:flex;align-items:center;gap:14px;flex-wrap:wrap}
.post-author{display:flex;align-items:center;gap:7px;font-size:12px;color:var(--muted);text-decoration:none;transition:color 0.2s}
.post-author:hover{color:var(--text)}
.post-author-av{width:22px;height:22px;border-radius:50%;background:linear-gradient(135deg,var(--accent3),var(--accent));display:flex;align-items:center;justify-content:center;font-family:'Space Mono',monospace;font-size:8px;font-weight:700;color:#000;overflow:hidden;flex-shrink:0}
.post-author-av img{width:100%;height:100%;object-fit:cover;border-radius:50%}
.post-stat{display:flex;align-items:center;gap:5px;font-family:'Space Mono',monospace;font-size:10px;color:var(--muted)}
.post-time{font-size:11px;color:var(--muted);margin-left:auto}
.post-locked{font-family:'Space Mono',monospace;font-size:9px;color:#ff8888;background:rgba(255,68,68,0.1);border:1px solid rgba(255,68,68,0.2);border-radius:4px;padding:2px 7px}
.post-pinned{font-family:'Space Mono',monospace;font-size:9px;color:var(--accent4);background:rgba(0,255,136,0.1);border:1px solid rgba(0,255,136,0.2);border-radius:4px;padding:2px 7px}

.sidebar-card{background:var(--surface);border:1px solid var(--border2);border-radius:14px;margin-bottom:16px;overflow:hidden}
.sidebar-card-header{padding:16px 18px 12px;border-bottom:1px solid var(--border2)}
.sidebar-card-title{font-family:'Space Mono',monospace;font-size:10px;font-weight:700;color:var(--muted);letter-spacing:2px;text-transform:uppercase}
.member-row{display:flex;align-items:center;gap:10px;padding:10px 18px;border-bottom:1px solid var(--border2);text-decoration:none;transition:background 0.2s}
.member-row:last-child{border-bottom:none}
.member-row:hover{background:var(--surface2)}
.member-av{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--accent3),var(--accent));display:flex;align-items:center;justify-content:center;font-family:'Space Mono',monospace;font-size:11px;font-weight:700;color:#000;overflow:hidden;flex-shrink:0}
.member-av img{width:100%;height:100%;object-fit:cover;border-radius:50%}
.member-info{flex:1;min-width:0}
.member-name{font-size:13px;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.member-role-badge{font-family:'Space Mono',monospace;font-size:9px;font-weight:700;padding:2px 7px;border-radius:4px}
.member-role-badge.owner{background:rgba(255,107,53,0.15);color:var(--accent2)}
.member-role-badge.moderator{background:rgba(0,229,255,0.1);color:var(--accent)}
.member-role-badge.member{background:var(--surface2);color:var(--muted)}

.empty-posts{text-align:center;padding:50px 20px;color:var(--muted)}
.empty-posts .icon{font-size:48px;margin-bottom:14px}

@media(max-width:900px){
    .topbar{padding:10px 16px;height:auto;min-height:58px;flex-wrap:wrap;gap:10px}
    .topbar-btn{white-space:nowrap}
    .layout{grid-template-columns:1fr;padding:0 16px 40px}
    .comm-header-inner{flex-direction:column;align-items:flex-start}
    .comm-banner{height:120px}
    .comm-header{padding:0 16px}
}
@media(max-width:560px){
    .topbar{padding:10px 12px;gap:8px}
    .topbar-logo{letter-spacing:2px;font-size:10px}
    .topbar-btn{padding:7px 10px;font-size:9px;max-width:96px;overflow:hidden;text-overflow:ellipsis}
    .topbar-av{width:30px;height:30px}
}

/* ── Preferências ── */
.prefs-btn {
    position: relative;
    background: none;
    border: 1px solid var(--border2);
    border-radius: 8px;
    padding: 7px 12px;
    color: var(--muted);
    font-size: 16px;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    line-height: 1;
}
.prefs-btn:hover { border-color: var(--accent); color: var(--accent); background: rgba(0,229,255,0.05); }
.prefs-btn.active { border-color: var(--accent); color: var(--accent); background: rgba(0,229,255,0.08); }
.prefs-btn .prefs-label {
    font-family: 'Space Mono', monospace;
    font-size: 10px;
    letter-spacing: 0.5px;
}

.prefs-panel {
    display: none;
    position: absolute;
    top: calc(100% + 10px);
    right: 0;
    width: 280px;
    background: #16161f;
    border: 1px solid rgba(0,229,255,0.2);
    border-radius: 14px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.6), 0 0 0 1px rgba(0,229,255,0.05);
    z-index: 9999;
    overflow: hidden;
    animation: prefsIn 0.15s ease;
}
.prefs-panel.open { display: block; }
@keyframes prefsIn { from { opacity:0; transform:translateY(-6px); } to { opacity:1; transform:translateY(0); } }

.prefs-panel-header {
    padding: 14px 18px 10px;
    border-bottom: 1px solid rgba(255,255,255,0.06);
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.prefs-panel-title {
    font-family: 'Space Mono', monospace;
    font-size: 10px;
    font-weight: 700;
    color: var(--accent);
    letter-spacing: 2px;
    text-transform: uppercase;
}
.prefs-panel-close {
    background: none;
    border: none;
    color: var(--muted);
    font-size: 18px;
    cursor: pointer;
    line-height: 1;
    padding: 2px 4px;
    border-radius: 4px;
    transition: color 0.15s;
}
.prefs-panel-close:hover { color: var(--text); }

.prefs-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 18px;
    border-bottom: 1px solid rgba(255,255,255,0.04);
    gap: 14px;
}
.prefs-item:last-child { border-bottom: none; }
.prefs-item-info { flex: 1; min-width: 0; }
.prefs-item-label {
    font-size: 13px;
    font-weight: 600;
    color: var(--text);
    margin-bottom: 2px;
    display: flex;
    align-items: center;
    gap: 7px;
}
.prefs-item-desc { font-size: 11px; color: var(--muted); line-height: 1.4; }

/* Toggle switch */
.prefs-toggle {
    position: relative;
    width: 42px;
    height: 24px;
    flex-shrink: 0;
}
.prefs-toggle input {
    opacity: 0;
    width: 0;
    height: 0;
    position: absolute;
}
.prefs-toggle-track {
    position: absolute;
    inset: 0;
    background: var(--surface3);
    border-radius: 24px;
    cursor: pointer;
    transition: background 0.2s;
    border: 1px solid rgba(255,255,255,0.08);
}
.prefs-toggle input:checked + .prefs-toggle-track {
    background: var(--accent);
    border-color: var(--accent);
}
.prefs-toggle-track::after {
    content: '';
    position: absolute;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: #fff;
    top: 2px;
    left: 2px;
    transition: transform 0.2s;
    box-shadow: 0 1px 3px rgba(0,0,0,0.4);
}
.prefs-toggle input:checked + .prefs-toggle-track::after {
    transform: translateX(18px);
}

.bc-bar{background:var(--surface);border-bottom:1px solid var(--border2);padding:8px 32px;position:relative;z-index:5}
.bc-inner{max-width:1400px;margin:0 auto;display:flex;align-items:center;gap:6px;font-family:'Space Mono',monospace;font-size:10px;color:var(--muted);flex-wrap:wrap}
.bc-link{color:var(--muted);text-decoration:none;transition:color 0.15s}.bc-link:hover{color:var(--accent)}
.bc-sep{opacity:0.4}.bc-current{color:var(--text)}
</style>
</head>
<body>

<nav class="topbar">
    <a href="index.php" class="topbar-logo">3D<span>/</span>FÓRUM</a>
    <span style="color:var(--muted);font-size:12px">/ <?php echo sanitize($community['name']); ?></span>
    <div class="ms-actions">
        <a href="../index.php" class="topbar-btn">← Manual</a>
        
        <?php if ($currentUser && in_array($currentUser['role']??'',['master','admin','moderator'])): ?><a href="admin.php" class="topbar-btn" style="color:#ff6b35;border-color:rgba(255,107,53,0.3)">⚔️ Admin</a><?php endif; ?>

<!-- Botão de preferências no topbar (inserir antes do último item do topbar-actions/topbar-right) -->
<div style="position:relative" id="prefsWrap">
    <button class="prefs-btn" id="prefsBtn" onclick="togglePrefsPanel()" title="Preferências de conteúdo">
        ⚙️ <span class="prefs-label">Preferências</span>
    </button>
    <div class="prefs-panel" id="prefsPanel">
        <div class="prefs-panel-header">
            <span class="prefs-panel-title">Preferências de Conteúdo</span>
            <button class="prefs-panel-close" onclick="togglePrefsPanel()">×</button>
        </div>
        <div class="prefs-item">
            <div class="prefs-item-info">
                <div class="prefs-item-label">⚠️ Spoilers</div>
                <div class="prefs-item-desc">Ocultar automaticamente conteúdo marcado como spoiler</div>
            </div>
            <label class="prefs-toggle">
                <input type="checkbox" id="pref-hideSpoiler" onchange="updatePref('hideSpoiler', this.checked)">
                <span class="prefs-toggle-track"></span>
            </label>
        </div>
    </div>
</div>

        <?php if ($currentUser): ?>
            <a href="mensagens.php" class="topbar-btn">💬</a>
            <a href="perfil.php?id=<?php echo (int)($_SESSION['user_id'] ?? 0); ?>" class="topbar-av">
                <?php $av=$currentUser['avatar_url']??''; if($av): ?><img src="<?php echo sanitize(avPath($av)); ?>" alt=""><?php else: echo mb_substr($currentUser['full_name'],0,2); endif; ?>
            </a>
        <?php else: ?>
            <a href="../login.php?redirect=forum/index.php" class="topbar-btn primary">Entrar</a>
        <?php endif; ?>
    </div>
</nav>

<!-- Breadcrumb -->
<div class="bc-bar" style="">
    <div class="bc-inner">
        <a href="index.php" style="color:var(--muted);text-decoration:none" onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='var(--muted)'">🌐 Fórum</a>
        <span>›</span>
        <span style="color:var(--text)"><?php echo $community['icon']; ?> <?php echo sanitize($community['name']); ?></span>
    </div>
</div>

<div class="comm-banner">
    <div class="comm-banner-pattern"></div>
</div>

<div class="comm-header">
    <div class="comm-header-inner">
        <div class="comm-icon-big"><?php echo $community['icon']; ?></div>
        <div class="comm-meta">
            <div class="comm-title"><?php echo sanitize($community['name']); ?></div>
            <div class="comm-slug">c/<?php echo sanitize($community['slug']); ?></div>
        </div>
    </div>
    <div class="comm-actions-bar">
        <span class="comm-stat-pill">👥 <?php echo number_format($community['member_count']); ?> membros</span>
        <span class="comm-stat-pill">📝 <?php echo number_format($community['post_count']); ?> posts</span>
        <span class="comm-stat-pill">🗓️ Criada <?php echo date('d/m/Y', strtotime($community['created_at'])); ?></span>
        <?php if ($currentUser): ?>
        <button class="join-btn <?php echo $isMember ? 'leave' : ''; ?>" id="joinBtn"
            onclick="toggleJoin(<?php echo $commId; ?>, this)"
            data-joined="<?php echo $isMember ? '1' : '0'; ?>">
            <?php echo $isMember ? '✓ Membro — Sair' : '+ Entrar na comunidade'; ?>
        </button>
        <?php endif; ?>
        <?php if ($canMod): ?>
        <a href="gerir_comunidade.php?id=<?php echo $commId; ?>" class="topbar-btn" style="margin-left:auto">⚙️ Gerir</a>
        <?php endif; ?>
    </div>
    <?php if (!empty($community['description'])): ?>
    <div class="comm-desc"><?php echo sanitize($community['description']); ?></div>
    <?php endif; ?>
</div>

<div class="layout">
    <main>
        <div class="sort-bar">
            <a href="?slug=<?php echo urlencode($slug); ?>&sort=recente" class="<?php echo $sort==='recente'?'active':''; ?>">⏱ Recente</a>
            <a href="?slug=<?php echo urlencode($slug); ?>&sort=popular" class="<?php echo $sort==='popular'?'active':''; ?>">🔥 Popular</a>
            <?php if ($currentUser): ?>
            <a href="criar_post.php?comm=<?php echo urlencode($slug); ?>" class="new-post-btn">✏️ NOVO POST</a>
            <?php endif; ?>
        </div>

        <?php if ($flashMsg): ?>
        <div style="margin-bottom:16px;padding:14px 18px;background:rgba(124,58,237,0.08);border:1px solid rgba(124,58,237,0.25);border-radius:12px;font-size:13px;color:#c4b5fd;display:flex;align-items:center;gap:10px">
            <span style="font-size:18px;flex-shrink:0">🛡️</span>
            <span><?php echo sanitize($flashMsg); ?></span>
        </div>
        <?php endif; ?>

        <?php if ($canMod): ?>
        <?php
        $pendCount = 0;
        try {
            $pc = $db->prepare("SELECT COUNT(*) FROM forum_posts WHERE community_id=? AND status='pending'");
            $pc->execute(array($commId));
            $pendCount = (int)$pc->fetchColumn();
        } catch(Exception $e){}
        ?>
        <?php if ($pendCount > 0): ?>
        <div style="margin-bottom:16px;padding:13px 18px;background:rgba(255,204,0,0.06);border:1px solid rgba(255,204,0,0.2);border-radius:12px;font-size:13px;color:#ffcc00;display:flex;align-items:center;gap:10px">
            <span style="font-size:16px;flex-shrink:0">⏳</span>
            <span><strong><?php echo $pendCount; ?> post<?php echo $pendCount!==1?'s':''; ?> pendente<?php echo $pendCount!==1?'s':''; ?></strong> aguarda<?php echo $pendCount===1?'':'m'; ?> aprovação.</span>
            <a href="gerir_comunidade.php?id=<?php echo $commId; ?>" style="margin-left:auto;font-family:'Space Mono',monospace;font-size:10px;color:#ffcc00;text-decoration:none;border:1px solid rgba(255,204,0,0.3);padding:5px 12px;border-radius:7px;white-space:nowrap">Moderar →</a>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <?php if (empty($posts)): ?>
        <div class="empty-posts">
            <div class="icon">📭</div>
            <p>Ainda não há posts aqui.<br>Sê o primeiro a publicar!</p>
            <?php if ($currentUser): ?>
            <a href="criar_post.php?comm=<?php echo urlencode($slug); ?>" style="display:inline-block;margin-top:16px;background:var(--comm-color);color:#000;padding:10px 22px;border-radius:8px;text-decoration:none;font-family:'Space Mono',monospace;font-size:11px;font-weight:700">CRIAR POST</a>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <?php foreach ($posts as $post):
            $score    = (int)$post['vote_score'];
            $userVote = isset($userVotes[$post['id']]) ? (int)$userVotes[$post['id']] : 0;
            $scoreCls = $score > 0 ? 'positive' : ($score < 0 ? 'negative' : '');
            $initials = mb_substr($post['full_name'] ?? '??', 0, 2);
        ?>
        <div class="post-card" id="post-<?php echo $post['id']; ?>">
            <div class="post-vote">
                <button class="vote-btn up <?php echo $userVote===1 ? 'active' : ''; ?>" onclick="votePost(<?php echo $post['id']; ?>, 1, this)">▲</button>
                <div class="vote-score <?php echo $scoreCls; ?>" id="score-<?php echo $post['id']; ?>"><?php echo $score; ?></div>
                <button class="vote-btn down <?php echo $userVote===-1 ? 'active' : ''; ?>" onclick="votePost(<?php echo $post['id']; ?>, -1, this)">▼</button>
            </div>
            <div class="post-body">
                <?php if ($post['is_pinned'] || $post['is_locked'] || !empty($post['flair'])): ?>
                <div style="display:flex;align-items:center;gap:6px;margin-bottom:6px;flex-wrap:wrap">
                    <?php if ($post['is_pinned']): ?><span class="post-pinned">📌 FIXADO</span><?php endif; ?>
                    <?php if ($post['is_locked']): ?><span class="post-locked">🔒 FECHADO</span><?php endif; ?>
                    <?php if (!empty($post['flair'])): echo renderFlairBadgeComm($post['flair']); endif; ?>
                </div>
                <?php endif; ?>
                <a href="topico.php?id=<?php echo $post['id']; ?>" class="post-title"><?php echo sanitize($post['title']); ?></a>
                <?php if (!empty($post['content'])): ?>
                <div class="post-excerpt"><?php echo sanitize(mb_substr($post['content'], 0, 200)); ?></div>
                <?php endif; ?>
                <div class="post-meta">
                    <a href="perfil.php?id=<?php echo $post['user_id']; ?>" class="post-author">
                        <div class="post-author-av"><?php if (!empty($post['avatar_url'])): ?><img src="<?php echo sanitize(avPath($post['avatar_url'])); ?>" alt=""><?php else: echo sanitize($initials); endif; ?></div>
                        <?php echo sanitize($post['username']); ?>
                    </a>
                    <span class="post-stat">💬 <?php echo (int)$post['reply_count']; ?></span>
                    <span class="post-time"><?php
                        $diff = time() - strtotime($post['created_at']);
                        if ($diff < 3600)  echo floor($diff / 60) . 'min';
                        elseif ($diff < 86400) echo floor($diff / 3600) . 'h';
                        else echo date('d/m/Y', strtotime($post['created_at']));
                    ?></span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </main>

    <aside>
        <div class="sidebar-card" style="padding:18px;margin-bottom:16px">
            <div style="font-family:'Space Mono',monospace;font-size:10px;color:var(--muted);letter-spacing:2px;text-transform:uppercase;margin-bottom:10px">Sobre</div>
            <p style="font-size:13px;color:var(--muted);line-height:1.6;margin-bottom:14px"><?php echo !empty($community['description']) ? sanitize($community['description']) : 'Sem descrição.'; ?></p>
            <div style="font-size:12px;color:var(--muted)">Criada por <a href="perfil.php?id=<?php echo $community['created_by']; ?>" style="color:var(--accent);text-decoration:none">@<?php echo sanitize($community['owner_name']); ?></a></div>
        </div>

        <?php if (!empty($topMembers)): ?>
        <div class="sidebar-card">
            <div class="sidebar-card-header">
                <span class="sidebar-card-title">Membros (<?php echo number_format($community['member_count']); ?>)</span>
            </div>
            <?php foreach ($topMembers as $m):
                $mi = mb_substr($m['full_name'] ?? '??', 0, 2);
            ?>
            <a href="perfil.php?id=<?php echo $m['id']; ?>" class="member-row">
                <div class="member-av"><?php if (!empty($m['avatar_url'])): ?><img src="<?php echo sanitize(avPath($m['avatar_url'])); ?>" alt=""><?php else: echo sanitize($mi); endif; ?></div>
                <div class="member-info">
                    <div class="member-name"><?php echo sanitize($m['full_name']); ?></div>
                </div>
                <span class="member-role-badge <?php echo $m['role']; ?>">
                    <?php echo $m['role'] === 'owner' ? '👑' : ($m['role'] === 'admin' ? '🛡️' : ($m['role'] === 'moderator' ? '🔰' : '')); ?>
                    <?php echo ucfirst($m['role']); ?>
                </span>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </aside>
</div>

<script>
var CSRF    = '<?php echo $csrf; ?>';
var CUR_UID = <?php echo $currentUser ? (int)$currentUser['id'] : 'null'; ?>;

async function votePost(postId, value, btn) {
    if (!CUR_UID) { window.location.href = '../login.php?redirect=forum/index.php'; return; }
    try {
        var res  = await fetch('api/forum.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'vote_post', csrf_token: CSRF, post_id: postId, value: value})
        });
        if (!res.ok) { console.error('HTTP', res.status); return; }
        var text = await res.text();
        var data;
        try { data = JSON.parse(text); } catch(e) { console.error('JSON error:', text); return; }
        if (!data.success) { console.warn('vote error:', data.error); return; }
        var card    = document.getElementById('post-' + postId);
        var scoreEl = document.getElementById('score-' + postId);
        if (scoreEl) {
            scoreEl.textContent = data.score;
            scoreEl.className   = 'vote-score' + (data.score > 0 ? ' positive' : data.score < 0 ? ' negative' : '');
        }
        if (card) {
            card.querySelector('.vote-btn.up').classList.toggle('active',   data.user_vote ===  1);
            card.querySelector('.vote-btn.down').classList.toggle('active', data.user_vote === -1);
        }
    } catch(e) { console.error('votePost error:', e); }
}

async function toggleJoin(commId, btn) {
    if (!CUR_UID) { window.location.href = '../login.php?redirect=forum/index.php'; return; }
    var isJoined = btn.dataset.joined === '1';
    // Feedback imediato
    btn.disabled    = true;
    btn.textContent = '…';
    try {
        var res  = await fetch('api/forum.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action:       isJoined ? 'leave_community' : 'join_community',
                csrf_token:   CSRF,
                community_id: commId
            })
        });
        if (!res.ok) { console.error('HTTP join', res.status); btn.disabled = false; btn.textContent = isJoined ? '✓ Membro — Sair' : '+ Entrar na comunidade'; return; }
        var text = await res.text();
        var data;
        try { data = JSON.parse(text); } catch(e) { console.error('JSON join error:', text); btn.disabled = false; return; }
        if (!data.success) { console.warn('join error:', data.error); btn.disabled = false; btn.textContent = isJoined ? '✓ Membro — Sair' : '+ Entrar na comunidade'; return; }
        btn.dataset.joined = data.joined ? '1' : '0';
        btn.textContent    = data.joined ? '✓ Membro — Sair' : '+ Entrar na comunidade';
        btn.classList.toggle('leave', data.joined);
        btn.disabled = false;
    } catch(e) {
        console.error('toggleJoin error:', e);
        btn.disabled    = false;
        btn.textContent = isJoined ? '✓ Membro — Sair' : '+ Entrar na comunidade';
    }
}


// ── Preferências de conteúdo ──────────────────────────────────
function getPrefs() {
    try {
        var s = localStorage.getItem('forumPrefs');
        var d = {hideSpoiler: false};
        return s ? Object.assign(d, JSON.parse(s)) : d;
    } catch(e) { return {hideSpoiler: false}; }
}

function savePrefs(p) {
    try { localStorage.setItem('forumPrefs', JSON.stringify(p)); } catch(e) {}
}

function applyPrefs(p) {
    // Spoiler mask
    document.querySelectorAll('.spoiler-mask').forEach(function(mask) {
        mask.style.display = p.hideSpoiler ? 'flex' : 'none';
    });
}

function updatePref(key, value) {
    var p = getPrefs();
    p[key] = value;
    savePrefs(p);
    applyPrefs(p);
}

function togglePrefsPanel() {
    var panel = document.getElementById('prefsPanel');
    var btn   = document.getElementById('prefsBtn');
    if (!panel) return;
    var open = panel.classList.contains('open');
    panel.classList.toggle('open', !open);
    btn.classList.toggle('active', !open);
}

// Fechar ao clicar fora
document.addEventListener('click', function(e) {
    var wrap = document.getElementById('prefsWrap');
    if (wrap && !wrap.contains(e.target)) {
        var panel = document.getElementById('prefsPanel');
        var btn   = document.getElementById('prefsBtn');
        if (panel) panel.classList.remove('open');
        if (btn)   btn.classList.remove('active');
    }
});

// Inicializar toggles e aplicar preferências
(function() {
    var p = getPrefs();
    var tSpo = document.getElementById('pref-hideSpoiler');
    if (tSpo) tSpo.checked = p.hideSpoiler;
    applyPrefs(p);
})();

</script>
<!-- ═══════════════════════════════════════════════════════
     IA do Fórum — chatbot flutuante
     Colar antes do </body> em forum/index.php, topico.php, comunidade.php
     ═══════════════════════════════════════════════════ -->

<style>
/* ── Forum AI Widget ── */
#forumAI { position: fixed; bottom: 24px; right: 24px; z-index: 9999; font-family: 'Inter', sans-serif; }

#forumAI-btn {
    width: 52px; height: 52px; border-radius: 50%;
    background: linear-gradient(135deg, #7c3aed, #ff6b35);
    border: none; cursor: pointer; box-shadow: 0 4px 20px rgba(124,58,237,0.45);
    display: flex; align-items: center; justify-content: center;
    font-size: 22px; transition: all 0.3s;
}
#forumAI-btn:hover { transform: scale(1.08); box-shadow: 0 6px 28px rgba(124,58,237,0.6); }

#forumAI-panel {
    display: none; position: absolute; bottom: 64px; right: 0;
    width: 340px; height: 460px;
    background: #111118; border: 1px solid rgba(124,58,237,0.25);
    border-radius: 18px; box-shadow: 0 24px 60px rgba(0,0,0,0.7);
    flex-direction: column; overflow: hidden;
    animation: faiSlideUp 0.2s ease;
}
#forumAI-panel.open { display: flex; }
@keyframes faiSlideUp { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }

.fai-header {
    padding: 13px 16px;
    background: linear-gradient(135deg, rgba(124,58,237,0.1), rgba(255,107,53,0.08));
    border-bottom: 1px solid rgba(124,58,237,0.15);
    display: flex; align-items: center; gap: 10px;
}
.fai-av {
    width: 34px; height: 34px; border-radius: 50%;
    background: linear-gradient(135deg, #7c3aed, #ff6b35);
    display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0;
}
.fai-name { font-family: 'Syne', sans-serif; font-size: 13px; font-weight: 700; color: #fff; }
.fai-status { font-size: 10px; color: #a78bfa; display: flex; align-items: center; gap: 4px; }
.fai-status::before { content:''; width:5px; height:5px; background:#a78bfa; border-radius:50%; }
.fai-close { margin-left:auto; background:none; border:none; color:#888899; font-size:18px; cursor:pointer; padding:2px 6px; border-radius:6px; transition:color 0.2s; }
.fai-close:hover { color:#e8e8f0; }

.fai-messages { flex:1; overflow-y:auto; padding:14px; display:flex; flex-direction:column; gap:10px; }
.fai-messages::-webkit-scrollbar { width:4px; }
.fai-messages::-webkit-scrollbar-thumb { background:rgba(255,255,255,0.08); border-radius:4px; }

.fai-msg { max-width:88%; display:flex; flex-direction:column; }
.fai-msg.user { align-self:flex-end; }
.fai-msg.bot  { align-self:flex-start; }
.fai-bubble { padding:9px 13px; border-radius:13px; font-size:12px; line-height:1.6; word-break:break-word; }
.fai-msg.user .fai-bubble { background:rgba(124,58,237,0.18); border:1px solid rgba(124,58,237,0.25); color:#e8e8f0; border-bottom-right-radius:3px; }
.fai-msg.bot  .fai-bubble { background:#1a1a26; border:1px solid rgba(255,255,255,0.06); color:#e8e8f0; border-bottom-left-radius:3px; }
.fai-bubble strong { color:#a78bfa; }
.fai-bubble a { color:#00e5ff; }

.fai-typing { display:none; align-self:flex-start; background:#1a1a26; border:1px solid rgba(255,255,255,0.06); border-radius:13px; border-bottom-left-radius:3px; padding:10px 14px; gap:4px; flex-direction:row; align-items:center; }
.fai-typing.show { display:flex; }
.fai-dot { width:5px; height:5px; background:#888899; border-radius:50%; animation:faiDot 1.2s infinite; }
.fai-dot:nth-child(2){animation-delay:0.2s} .fai-dot:nth-child(3){animation-delay:0.4s}
@keyframes faiDot { 0%,80%,100%{transform:scale(1);opacity:0.4} 40%{transform:scale(1.2);opacity:1} }

.fai-chips { display:flex; flex-wrap:wrap; gap:5px; padding:0 14px 8px; }
.fai-chip { background:rgba(124,58,237,0.08); border:1px solid rgba(124,58,237,0.2); border-radius:20px; padding:4px 10px; color:#888899; font-size:10px; cursor:pointer; transition:all 0.15s; }
.fai-chip:hover { background:rgba(124,58,237,0.18); color:#a78bfa; }

.fai-input-row { padding:10px 12px; border-top:1px solid rgba(255,255,255,0.06); display:flex; gap:7px; align-items:flex-end; }
.fai-input { flex:1; background:#1a1a26; border:1px solid rgba(255,255,255,0.08); border-radius:9px; padding:8px 11px; color:#e8e8f0; font-family:'Inter',sans-serif; font-size:12px; resize:none; min-height:36px; max-height:90px; line-height:1.5; transition:border-color 0.2s; }
.fai-input:focus { outline:none; border-color:rgba(124,58,237,0.45); }
.fai-input::placeholder { color:#888899; opacity:0.7; }
.fai-send { width:34px; height:34px; border-radius:8px; flex-shrink:0; background:linear-gradient(135deg,#7c3aed,#ff6b35); border:none; cursor:pointer; font-size:14px; display:flex; align-items:center; justify-content:center; transition:opacity 0.2s; }
.fai-send:hover { opacity:0.85; }
.fai-send:disabled { opacity:0.35; cursor:not-allowed; }

@media(max-width:480px) { #forumAI-panel { width:calc(100vw - 32px); right:-8px; } }
</style>

<div id="forumAI">
    <button id="forumAI-btn" onclick="toggleForumAI()" title="Assistente do Fórum">
        💬
    </button>

    <div id="forumAI-panel">
        <div class="fai-header">
            <div class="fai-av">🛡️</div>
            <div>
                <div class="fai-name">Forum AI</div>
                <div class="fai-status">Assistente do Fórum</div>
            </div>
            <button class="fai-close" onclick="toggleForumAI()">×</button>
        </div>

        <div class="fai-messages" id="faiMessages">
            <div class="fai-msg bot">
                <div class="fai-bubble">
                    Olá! Sou o assistente do fórum. 🛡️<br><br>
                    Posso ajudar-te com problemas ao publicar, dúvidas sobre comunidades, regras, ou como contactar os administradores.
                </div>
            </div>
        </div>

        <div class="fai-typing" id="faiTyping">
            <div class="fai-dot"></div><div class="fai-dot"></div><div class="fai-dot"></div>
        </div>

        <div class="fai-chips" id="faiChips">
            <button class="fai-chip" onclick="sendFaiChip(this)">Não consigo publicar</button>
            <button class="fai-chip" onclick="sendFaiChip(this)">Como criar comunidade?</button>
            <button class="fai-chip" onclick="sendFaiChip(this)">Contactar administrador</button>
            <button class="fai-chip" onclick="sendFaiChip(this)">O que são flairs?</button>
        </div>

        <div class="fai-input-row">
            <textarea class="fai-input" id="faiInput" placeholder="Como posso ajudar?" rows="1"
                onkeydown="faiKeyDown(event)" oninput="faiAutoResize(this)"></textarea>
            <button class="fai-send" id="faiSendBtn" onclick="sendFaiMessage()">➤</button>
        </div>
    </div>
</div>

<script>
var faiHistory = [];
var faiOpen    = false;
var faiLoading = false;

function toggleForumAI() {
    faiOpen = !faiOpen;
    document.getElementById('forumAI-panel').classList.toggle('open', faiOpen);
    if (faiOpen) setTimeout(function(){ document.getElementById('faiInput').focus(); }, 100);
}

function faiKeyDown(e) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendFaiMessage(); }
}

function faiAutoResize(el) {
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 90) + 'px';
}

function sendFaiChip(btn) {
    document.getElementById('faiInput').value = btn.textContent;
    document.getElementById('faiChips').style.display = 'none';
    sendFaiMessage();
}

function faiAppendMsg(role, text) {
    var msgs = document.getElementById('faiMessages');
    var div  = document.createElement('div');
    div.className = 'fai-msg ' + role;
    var bubble = document.createElement('div');
    bubble.className = 'fai-bubble';
    var html = text
        .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
        .replace(/\*\*(.*?)\*\*/g,'<strong>$1</strong>')
        .replace(/\n/g,'<br>');
    bubble.innerHTML = html;
    div.appendChild(bubble);
    msgs.appendChild(div);
    msgs.scrollTop = msgs.scrollHeight;
}

async function sendFaiMessage() {
    if (faiLoading) return;
    var input = document.getElementById('faiInput');
    var msg   = input.value.trim();
    if (!msg) return;

    input.value = '';
    input.style.height = 'auto';
    faiLoading = true;
    document.getElementById('faiSendBtn').disabled = true;

    faiAppendMsg('user', msg);
    faiHistory.push({role:'user', content:msg});

    var typing = document.getElementById('faiTyping');
    typing.classList.add('show');
    document.getElementById('faiMessages').scrollTop = 99999;

    try {
        var res  = await fetch('../api/ai.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({mode:'forum', message:msg, history:faiHistory.slice(-6)})
        });
        var data = await res.json();
        typing.classList.remove('show');
        if (data.success) {
            faiAppendMsg('bot', data.reply);
            faiHistory.push({role:'assistant', content:data.reply});
        } else {
            faiAppendMsg('bot', '⚠️ ' + (data.error || 'Erro ao contactar a IA.'));
        }
    } catch(e) {
        typing.classList.remove('show');
        faiAppendMsg('bot', '⚠️ Erro de ligação. Tenta novamente.');
    }

    faiLoading = false;
    document.getElementById('faiSendBtn').disabled = false;
    document.getElementById('faiMessages').scrollTop = 99999;
}
</script>

</body>
</html>
