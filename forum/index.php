<?php
/**
 * forum/index.php — Página principal do fórum
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/user_notices.php';

$currentUser = isLoggedIn() ? getCurrentUser() : null;
$db = getDB();

// ── Garantir tabelas ──────────────────────────────────────────
try { $db->exec("CREATE TABLE IF NOT EXISTS forum_communities (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(100) NOT NULL,
    slug         VARCHAR(100) NOT NULL UNIQUE,
    description  TEXT,
    icon         VARCHAR(10)  DEFAULT '💬',
    banner_color VARCHAR(20)  DEFAULT '#00e5ff',
    created_by   INT NOT NULL,
    member_count INT DEFAULT 0,
    post_count   INT DEFAULT 0,
    is_active    TINYINT(1) DEFAULT 1,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"); } catch(Exception $e){}

try { $db->exec("CREATE TABLE IF NOT EXISTS forum_memberships (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT NOT NULL,
    community_id INT NOT NULL,
    role         ENUM('member','moderator','owner') DEFAULT 'member',
    joined_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_member (user_id, community_id),
    FOREIGN KEY (user_id)      REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (community_id) REFERENCES forum_communities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"); } catch(Exception $e){}

try { $db->exec("CREATE TABLE IF NOT EXISTS forum_posts (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    community_id INT NOT NULL,
    user_id      INT NOT NULL,
    title        VARCHAR(300) NOT NULL,
    content      TEXT,
    type         ENUM('text','link','image') DEFAULT 'text',
    vote_score   INT DEFAULT 0,
    reply_count  INT DEFAULT 0,
    is_pinned    TINYINT(1) DEFAULT 0,
    is_locked    TINYINT(1) DEFAULT 0,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP NULL,
    FOREIGN KEY (community_id) REFERENCES forum_communities(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)      REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"); } catch(Exception $e){}

try { $db->exec("CREATE TABLE IF NOT EXISTS forum_post_votes (
    user_id  INT NOT NULL,
    post_id  INT NOT NULL,
    value    TINYINT NOT NULL DEFAULT 1,
    PRIMARY KEY (user_id, post_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (post_id) REFERENCES forum_posts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"); } catch(Exception $e){}

try { $db->exec("CREATE TABLE IF NOT EXISTS forum_replies (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    post_id     INT NOT NULL,
    parent_id   INT NULL,
    user_id     INT NOT NULL,
    content     TEXT NOT NULL,
    vote_score  INT DEFAULT 0,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES forum_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"); } catch(Exception $e){}

try { $db->exec("CREATE TABLE IF NOT EXISTS private_messages (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    sender_id   INT NOT NULL,
    receiver_id INT NOT NULL,
    content     TEXT NOT NULL,
    read_at     TIMESTAMP NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id)   REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"); } catch(Exception $e){}

// ── Dados ─────────────────────────────────────────────────────
$feedTab = $_GET['feed'] ?? 'recent';
$flairFilter = $_GET['flair'] ?? null;

$feedOrder = 'fp.created_at DESC';
if ($feedTab === 'popular') {
    $feedOrder = '(fp.vote_score * 2 + fp.reply_count * 5) DESC, fp.created_at DESC';
}

$feedPosts = array();
try {
    $mineJoin = '';
    $whereParts = array("fc.is_active = 1", "(fp.status = 'approved' OR fp.status IS NULL)");

    if ($feedTab === 'mine' && $currentUser) {
        $mineJoin  = "JOIN forum_memberships fm ON fm.community_id=fp.community_id AND fm.user_id=" . (int)$currentUser['id'];
    } elseif ($feedTab === 'mine') {
        $feedPosts = array();
        goto skip_feed;
    }

    if ($flairFilter) {
        $whereParts[] = "fp.flair = " . $db->quote($flairFilter);
    }

    if ($feedTab === 'popular') {
        // Posts populares dos últimos 30 dias (mais flexível que 7)
        $whereParts[] = "fp.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    }

    $whereSql = implode(" AND ", $whereParts);

    $feedPosts = $db->query("
        SELECT fp.*, fc.name as community_name, fc.slug as community_slug,
               fc.icon as community_icon, fc.banner_color,
               u.full_name, u.username, u.avatar_url
        FROM forum_posts fp
        JOIN forum_communities fc ON fc.id = fp.community_id
        JOIN users u ON u.id = fp.user_id
        $mineJoin
        WHERE $whereSql
        ORDER BY $feedOrder
        LIMIT 40
    ")->fetchAll();
    skip_feed:;
} catch(Exception $e) {
    try {
        $feedPosts = $db->query("
            SELECT fp.*, fc.name as community_name, fc.slug as community_slug,
                   fc.icon as community_icon, fc.banner_color,
                   u.full_name, u.username, u.avatar_url
            FROM forum_posts fp
            JOIN forum_communities fc ON fc.id = fp.community_id
            JOIN users u ON u.id = fp.user_id
            WHERE fc.is_active = 1 AND (fp.status = 'approved' OR fp.status IS NULL)
            ORDER BY fp.created_at DESC
            LIMIT 30
        ")->fetchAll();
    } catch(Exception $e2) { $feedPosts = array(); }
}

$userVotes = array();
if ($currentUser && !empty($feedPosts)) {
    try {
        $ids = implode(',', array_map(function($p){ return (int)$p['id']; }, $feedPosts));
        $vr = $db->query("SELECT post_id, value FROM forum_post_votes WHERE user_id=".(int)$currentUser['id']." AND post_id IN ($ids)");
        foreach ($vr->fetchAll() as $v) $userVotes[$v['post_id']] = $v['value'];
    } catch(Exception $e){}
}

$communities = $db->query("
    SELECT fc.*, u.username as owner_name
    FROM forum_communities fc JOIN users u ON u.id=fc.created_by
    WHERE fc.is_active=1 ORDER BY fc.member_count DESC, fc.created_at DESC LIMIT 20
")->fetchAll();

$myCommIds = array();
if ($currentUser) {
    $mc = $db->prepare("SELECT community_id FROM forum_memberships WHERE user_id=?");
    $mc->execute(array((int)$currentUser['id']));
    foreach ($mc->fetchAll() as $r) $myCommIds[] = (int)$r['community_id'];
}

$unreadMsgs = 0;
if ($currentUser) {
    $um = $db->prepare("SELECT COUNT(*) FROM private_messages WHERE receiver_id=? AND read_at IS NULL");
    $um->execute(array((int)$currentUser['id']));
    $unreadMsgs = (int)$um->fetchColumn();
}

$totalPosts = $db->query("SELECT COUNT(*) FROM forum_posts WHERE status='approved' OR status IS NULL")->fetchColumn();
$totalComms = $db->query("SELECT COUNT(*) FROM forum_communities WHERE is_active=1")->fetchColumn();
$totalUsers = $db->query("SELECT COUNT(*) FROM users WHERE is_active=1")->fetchColumn();

$csrf = generateCSRFToken();

function renderFlairBadgeFeed($flair) {
    if (!$flair) return '';
    $map = array(
        'pergunta'=>array('❓','PERGUNTA'),
        'tutorial'=>array('📖','TUTORIAL'),
        'projeto'=>array('🏗️','PROJETO'),
        'ajuda'=>array('🆘','AJUDA'),
        'noticia'=>array('📰','NOTÍCIA'),
        'discussao_tecnica'=>array('🔬','DISCUSSÃO TÉCNICA'),
        'showcase'=>array('📸','SHOWCASE'),
        'debate'=>array('🔬','DISCUSSÃO TÉCNICA'),
        'humor'=>array('📸','SHOWCASE'),
        'spoiler'=>array('⚠️','SPOILER')
    );
    if (!isset($map[$flair])) return '';
    return '<span class="flair-badge flair-' . $flair . '">' . $map[$flair][0] . ' ' . $map[$flair][1] . '</span>';
}
?><!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="description" content="Fórum de impressão 3D — posts, comunidades e dúvidas técnicas">
    <meta property="og:type"        content="website">
    <meta property="og:title"       content="Fórum 3D — Comunidade de Impressão 3D">
    <meta property="og:description" content="Comunidade de impressão 3D — partilha projetos, tira dúvidas e aprende.">
    <meta property="og:image"       content="https://manual-3d.pt/og-forum.jpg">
    <meta name="twitter:card"       content="summary_large_image">
    <meta name="twitter:image"      content="https://manual-3d.pt/og-forum.jpg">
    <link rel="icon" type="image/x-icon"  href="../favicons/favicon-forum.ico">
    <link rel="icon" type="image/svg+xml" href="../favicons/favicon-forum.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="../favicons/favicon-forum-32.png">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Fórum — Manual de Impressão 3D</title>
<!-- Google AdSense -->
<meta name="google-adsense-account" content="ca-pub-4562191239161971">
<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-4562191239161971" crossorigin="anonymous"></script>

<!-- Ad Block Recovery -->
<script async src="https://fundingchoicesmessages.google.com/i/pub-4562191239161971?ers=1"></script><script>(function() {function signalGooglefcPresent() {if (!window.frames['googlefcPresent']) {if (document.body) {const iframe = document.createElement('iframe'); iframe.style = 'width: 0; height: 0; border: none; z-index: -1000; left: -1000px; top: -1000px;'; iframe.style.display = 'none'; iframe.name = 'googlefcPresent'; document.body.appendChild(iframe);} else {setTimeout(signalGooglefcPresent, 0);}}}signalGooglefcPresent();})();</script>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800;900&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root{--bg:#0a0a0f;--surface:#111118;--surface2:#1a1a26;--surface3:#222235;--accent:#00e5ff;--accent2:#ff6b35;--accent3:#7c3aed;--accent4:#00ff88;--text:#e8e8f0;--muted:#888899;--border:rgba(0,229,255,0.1);--border2:rgba(255,255,255,0.06)}
*{margin:0;padding:0;box-sizing:border-box}
body{background:var(--bg);color:var(--text);font-family:'Inter',sans-serif;min-height:100vh}
body::before{content:'';position:fixed;inset:0;pointer-events:none;z-index:0;background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.03'/%3E%3C/svg%3E");opacity:0.4}

.topbar{position:sticky;top:0;z-index:100;background:rgba(10,10,15,0.92);backdrop-filter:blur(16px);border-bottom:1px solid var(--border);padding:0 32px;display:flex;align-items:center;gap:20px;height:58px}
.topbar-logo{font-family:'Space Mono',monospace;font-size:11px;font-weight:700;color:var(--accent);letter-spacing:3px;text-transform:uppercase;text-decoration:none;white-space:nowrap}
.topbar-logo span{color:var(--muted)}
.topbar-search{flex:1;max-width:420px;position:relative}
.topbar-search input{
    width:100%;
    background:rgba(26,26,38,0.5);
    backdrop-filter:blur(10px) saturate(180%);
    -webkit-backdrop-filter:blur(10px) saturate(180%);
    border:1px solid rgba(255,255,255,0.08);
    border-bottom:2px solid var(--border);
    border-radius:10px;
    padding:9px 14px 9px 38px;
    color:var(--text);
    font-family:'Inter',sans-serif;
    font-size:13px;
    transition:all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.topbar-search input:focus{
    outline:none;
    background:rgba(34,34,53,0.7);
    border-color:rgba(0,229,255,0.3);
    border-bottom-color:var(--accent);
    box-shadow: 0 8px 20px rgba(0,229,255,0.12);
    transform:translateY(-1px);
}
.topbar-search input::placeholder{color:var(--muted);opacity:0.6}
.topbar-search-icon{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:14px;pointer-events:none;transition:color 0.2s}
.topbar-search:focus-within .topbar-search-icon { color: var(--accent); }

/* Global Search Dropdown Windows 11 Style */
.global-search-results {
    position: absolute;
    top: calc(100% + 12px);
    left: 0;
    right: 0;
    background: rgba(17, 17, 24, 0.8);
    backdrop-filter: blur(25px) saturate(200%);
    -webkit-backdrop-filter: blur(25px) saturate(200%);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    box-shadow: 0 24px 48px rgba(0,0,0,0.5), 0 0 0 1px rgba(255,255,255,0.05);
    max-height: 450px;
    overflow-y: auto;
    display: none;
    z-index: 120;
    animation: winSearchReveal 0.25s cubic-bezier(0.1, 0.9, 0.2, 1);
}

@keyframes winSearchReveal {
    from { opacity: 0; transform: translateY(-12px) scale(0.97); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

.global-search-results.active { display: block; }

.search-group-label {
    padding: 12px 16px 6px;
    font-family: 'Space Mono', monospace;
    font-size: 9px;
    text-transform: uppercase;
    color: var(--accent);
    letter-spacing: 2px;
    opacity: 0.8;
}

.search-item {
    padding: 10px 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    text-decoration: none;
    transition: all 0.2s;
    border-left: 3px solid transparent;
}

.search-item:hover {
    background: rgba(255, 255, 255, 0.05);
    border-left-color: var(--accent);
}

.search-item-icon {
    width: 32px;
    height: 32px;
    background: var(--surface3);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    flex-shrink: 0;
}

.search-item-info { flex: 1; min-width: 0; }
.search-item-title {
    font-size: 13px;
    font-weight: 600;
    color: var(--text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: block;
}
.search-item-sub {
    font-size: 11px;
    color: var(--muted);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: block;
}

.search-no-res {
    padding: 30px 20px;
    text-align: center;
    color: var(--muted);
}
.topbar-actions{display:flex;align-items:center;gap:10px;margin-left:auto}
.topbar-btn{background:none;border:1px solid var(--border2);border-radius:8px;padding:7px 14px;color:var(--muted);font-family:'Space Mono',monospace;font-size:10px;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:6px;transition:all 0.2s;white-space:nowrap}
.topbar-btn:hover{border-color:var(--accent);color:var(--accent);background:rgba(0,229,255,0.05)}
.topbar-btn.primary{background:var(--accent);color:#000;border-color:transparent;font-weight:700}
.topbar-btn.primary:hover{background:#00c8e0;color:#000}
.notif-badge{background:var(--accent2);color:#fff;border-radius:100px;font-size:9px;padding:1px 5px;font-family:'Space Mono',monospace;font-weight:700}
.topbar-avatar{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--accent3),var(--accent));display:flex;align-items:center;justify-content:center;font-family:'Space Mono',monospace;font-size:11px;font-weight:700;color:#000;overflow:hidden;text-decoration:none;flex-shrink:0}
.topbar-avatar img{width:100%;height:100%;object-fit:cover;border-radius:50%}

.layout{max-width:100%;margin:0;padding:28px 32px;display:grid;grid-template-columns:1fr 300px;gap:24px;position:relative;z-index:1}

.feed-header{display:flex;align-items:center;gap:10px;margin-bottom:20px}
.feed-tabs{display:flex;gap:4px;background:var(--surface);border:1px solid var(--border2);border-radius:10px;padding:4px}
.feed-tab{background:none;border:none;border-radius:7px;padding:7px 16px;color:var(--muted);font-family:'Space Mono',monospace;font-size:10px;cursor:pointer;transition:all 0.2s;white-space:nowrap}
.feed-tab.active{background:var(--surface2);color:var(--accent)}
.feed-tab:hover:not(.active){color:var(--text)}
.create-post-btn{margin-left:auto;background:linear-gradient(135deg,var(--accent),var(--accent3));border:none;border-radius:9px;padding:9px 18px;color:#000;font-family:'Space Mono',monospace;font-size:10px;font-weight:700;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:6px;letter-spacing:0.5px;transition:opacity 0.2s}
.create-post-btn:hover{opacity:0.88}

.post-card{background:var(--surface);border:1px solid var(--border2);border-radius:14px;margin-bottom:10px;display:flex;overflow:hidden;transition:border-color 0.2s,transform 0.15s}
.post-card:hover{border-color:rgba(0,229,255,0.2);transform:translateY(-1px)}
.post-vote{display:flex;flex-direction:column;align-items:center;gap:4px;padding:16px 12px;background:rgba(0,0,0,0.15);min-width:52px;flex-shrink:0}
.vote-btn{background:none;border:none;cursor:pointer;color:var(--muted);font-size:16px;line-height:1;padding:4px;border-radius:4px;transition:all 0.15s}
.vote-btn:hover{background:var(--surface2)}
.vote-btn.up.active{color:var(--accent2)}
.vote-btn.down.active{color:#7c9aff}
.vote-score{font-family:'Space Mono',monospace;font-size:12px;font-weight:700;color:var(--text);line-height:1}
.vote-score.positive{color:var(--accent2)}.vote-score.negative{color:#7c9aff}
.post-body{flex:1;padding:16px 18px;min-width:0}
.post-community{display:inline-flex;align-items:center;gap:6px;font-family:'Space Mono',monospace;font-size:9px;font-weight:700;color:var(--muted);letter-spacing:1px;text-transform:uppercase;text-decoration:none;margin-bottom:6px;transition:color 0.2s}
.post-community:hover{color:var(--accent)}
.post-title{font-family:'Syne',sans-serif;font-size:16px;font-weight:700;color:#fff;text-decoration:none;line-height:1.35;display:block;margin-bottom:8px;transition:color 0.2s}
.post-title:hover{color:var(--accent)}
.post-excerpt{font-size:13px;color:var(--muted);line-height:1.6;margin-bottom:12px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.post-meta{display:flex;align-items:center;gap:14px;flex-wrap:wrap}
.post-author{display:flex;align-items:center;gap:7px;font-size:12px;color:var(--muted);text-decoration:none;transition:color 0.2s}
.post-author:hover{color:var(--text)}
.post-author-av{width:22px;height:22px;border-radius:50%;background:linear-gradient(135deg,var(--accent3),var(--accent));display:flex;align-items:center;justify-content:center;font-family:'Space Mono',monospace;font-size:8px;font-weight:700;color:#000;overflow:hidden;flex-shrink:0}
.post-author-av img{width:100%;height:100%;object-fit:cover;border-radius:50%}
.post-stat{display:flex;align-items:center;gap:5px;font-family:'Space Mono',monospace;font-size:10px;color:var(--muted)}
.post-time{font-size:11px;color:var(--muted);margin-left:auto}

.sidebar-card{background:var(--surface);border:1px solid var(--border2);border-radius:14px;margin-bottom:16px;overflow:hidden}
.sidebar-card-header{padding:16px 18px 12px;border-bottom:1px solid var(--border2);display:flex;align-items:center;justify-content:space-between}
.sidebar-card-title{font-family:'Space Mono',monospace;font-size:10px;font-weight:700;color:var(--muted);letter-spacing:2px;text-transform:uppercase}
.sidebar-card-action{font-family:'Space Mono',monospace;font-size:9px;color:var(--accent);text-decoration:none;letter-spacing:0.5px;transition:opacity 0.2s}
.sidebar-card-action:hover{opacity:0.7}
.comm-row{display:flex;align-items:center;gap:12px;padding:11px 18px;border-bottom:1px solid var(--border2);text-decoration:none;transition:background 0.2s}
.comm-row:last-child{border-bottom:none}
.comm-row:hover{background:var(--surface2)}
.comm-icon-wrap{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.comm-info{flex:1;min-width:0}
.comm-name{font-size:13px;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.comm-members{font-family:'Space Mono',monospace;font-size:10px;color:var(--muted);margin-top:1px}
.comm-join-btn{background:none;border:1px solid var(--border);border-radius:6px;padding:4px 10px;color:var(--muted);font-family:'Space Mono',monospace;font-size:9px;cursor:pointer;transition:all 0.2s;flex-shrink:0;white-space:nowrap}
.comm-join-btn:hover,.comm-join-btn.joined{border-color:var(--accent);color:var(--accent);background:rgba(0,229,255,0.06)}
.comm-join-btn.joined{color:var(--accent4);border-color:rgba(0,255,136,0.3);background:rgba(0,255,136,0.05)}
.create-comm-card{background:linear-gradient(135deg,rgba(0,229,255,0.06),rgba(124,58,237,0.06));border:1px solid rgba(0,229,255,0.15);border-radius:14px;padding:22px;margin-bottom:16px;text-align:center}
.create-comm-icon{font-size:36px;margin-bottom:10px}
.create-comm-title{font-family:'Syne',sans-serif;font-size:16px;font-weight:800;color:#fff;margin-bottom:6px}
.create-comm-sub{font-size:12px;color:var(--muted);margin-bottom:16px;line-height:1.5}
.create-comm-btn{display:inline-block;background:linear-gradient(135deg,var(--accent),var(--accent3));color:#000;font-family:'Space Mono',monospace;font-size:10px;font-weight:700;padding:10px 20px;border-radius:8px;text-decoration:none;letter-spacing:1px;transition:opacity 0.2s}
.create-comm-btn:hover{opacity:0.85}
.stats-row{display:flex;align-items:center;justify-content:space-between;padding:10px 18px;border-bottom:1px solid var(--border2)}
.stats-row:last-child{border-bottom:none}
.stats-label{font-size:12px;color:var(--muted)}
.stats-value{font-family:'Space Mono',monospace;font-size:12px;font-weight:700;color:var(--text)}
.empty-feed{text-align:center;padding:60px 20px;color:var(--muted)}
.empty-feed .icon{font-size:52px;margin-bottom:16px}
.empty-feed p{font-size:14px;line-height:1.7}

.flair-badge{display:inline-flex;align-items:center;gap:4px;font-family:'Space Mono',monospace;font-size:9px;font-weight:700;padding:2px 8px;border-radius:20px;letter-spacing:0.5px;text-transform:uppercase;vertical-align:middle}
.flair-pergunta{background:rgba(124,58,237,0.1);color:#a78bfa;border:1px solid rgba(124,58,237,0.25)}
.flair-tutorial{background:rgba(0,229,255,0.1);color:var(--accent);border:1px solid rgba(0,229,255,0.2)}
.flair-projeto{background:rgba(0,255,136,0.08);color:var(--accent4);border:1px solid rgba(0,255,136,0.2)}
.flair-ajuda{background:rgba(255,107,53,0.1);color:var(--accent2);border:1px solid rgba(255,107,53,0.25)}
.flair-noticia{background:rgba(0,229,255,0.08);color:var(--accent);border:1px solid rgba(0,229,255,0.15)}
.flair-discussao_tecnica{background:rgba(124,58,237,0.08);color:#a78bfa;border:1px solid rgba(124,58,237,0.18)}
.flair-debate{background:rgba(124,58,237,0.08);color:#a78bfa;border:1px solid rgba(124,58,237,0.18)}
.flair-showcase{background:rgba(0,229,255,0.08);color:#00e5ff;border:1px solid rgba(0,229,255,0.2)}
.flair-humor{background:rgba(0,229,255,0.08);color:#00e5ff;border:1px solid rgba(0,229,255,0.2)}
.flair-spoiler{background:rgba(255,204,0,0.1);color:#ffcc00;border:1px solid rgba(255,204,0,0.3)}
@media(max-width:1100px){.layout{grid-template-columns:1fr 260px}}
@media(max-width:900px){
    .layout{grid-template-columns:1fr;padding:16px 20px}
    .topbar{padding:10px 16px;height:auto;min-height:58px;flex-wrap:wrap;align-items:center;gap:10px}
    .topbar-search{order:3;flex-basis:100%;max-width:none}
    .topbar-actions{gap:8px}
}
@media(max-width:560px){
    .topbar{padding:10px 12px;gap:8px}
    .topbar-logo{letter-spacing:2px;font-size:10px}
    .topbar-actions{margin-left:0;flex:1;justify-content:flex-end;min-width:0}
    .topbar-btn{padding:7px 10px;font-size:9px}
    .topbar-btn:not(.primary){max-width:92px;overflow:hidden;text-overflow:ellipsis}
    .topbar-avatar{width:30px;height:30px}
    .feed-header{align-items:stretch;flex-direction:column}
    .feed-tabs{overflow-x:auto}
    .create-post-btn{margin-left:0;justify-content:center}
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
.bc-inner{display:flex;align-items:center;gap:6px;font-family:'Space Mono',monospace;font-size:10px;color:var(--muted);flex-wrap:wrap}
.bc-link{color:var(--muted);text-decoration:none;transition:color 0.15s}.bc-link:hover{color:var(--accent)}
.bc-sep{opacity:0.4}.bc-current{color:var(--text)}
</style>
</head>
<body>
<?php renderUserNotice(); ?>

<nav class="topbar">
    <a href="index.php" class="topbar-logo">3D<span>/</span>FÓRUM</a>
    <div class="topbar-search">
        <span class="topbar-search-icon">🔍</span>
        <input type="text" placeholder="Pesquisar no fórum e manual…" oninput="handleGlobalSearch(this.value)" autocomplete="off" id="globalSearchInput">
        <div id="globalSearchResults" class="global-search-results"></div>
    </div>
    <div class="topbar-actions">
        <a href="/" class="topbar-btn">← Manual</a>
        <?php if ($currentUser && in_array($currentUser['role']??'',['master','admin','moderator'])): ?><a href="admin" class="topbar-btn" style="color:#ff6b35;border-color:rgba(255,107,53,0.3)">⚔️ Admin</a><?php endif; ?>
        <?php if ($currentUser): ?>
            <a href="mensagens" class="topbar-btn">
                💬 Mensagens
                <?php if ($unreadMsgs > 0): ?><span class="notif-badge"><?php echo $unreadMsgs; ?></span><?php endif; ?>
            </a>
            <a href="perfil?id=<?php echo (int)($_SESSION['user_id'] ?? 0); ?>" class="topbar-avatar">
                <?php $av=$currentUser['avatar_url']??''; if($av): ?><img src="<?php echo sanitize(avPath($av)); ?>" alt=""><?php else: echo mb_substr($currentUser['full_name'],0,2); endif; ?>
            </a>
        <?php else: ?>
            <a href="/login?redirect=forum/" class="topbar-btn primary">Entrar</a>
        <?php endif; ?>
    </div>
</nav>
<!-- Breadcrumb -->
<div class="bc-bar">
    <div class="bc-inner">
        <a href="/" class="bc-link">📖 Manual</a>
        <span class="bc-sep">›</span>
        <a href="/forum/" class="bc-link">🌐 Fórum</a>
    </div>
</div>

<div class="layout">
    <main>
        <div class="feed-header">
            <div class="feed-tabs">
                <a href="?feed=recent" class="feed-tab <?php echo $feedTab==='recent'?'active':''; ?>" style="text-decoration:none">⏱ Recente</a>
                <a href="?feed=popular" class="feed-tab <?php echo $feedTab==='popular'?'active':''; ?>" style="text-decoration:none">🔥 Popular</a>
                <?php if ($currentUser && !empty($myCommIds)): ?>
                <a href="?feed=mine" class="feed-tab <?php echo $feedTab==='mine'?'active':''; ?>" style="text-decoration:none">⭐ As minhas</a>
                <?php endif; ?>
            </div>
            <?php if ($currentUser): ?>
            <a href="criar_post" class="create-post-btn">✏️ NOVO POST</a>
            <?php endif; ?>
        </div>

        <?php
        $flairs = array(
            'pergunta'=>array('❓','Pergunta'),
            'tutorial'=>array('📖','Tutorial'),
            'projeto'=>array('🏗️','Projeto'),
            'ajuda'=>array('🆘','Ajuda'),
            'discussao_tecnica'=>array('🔬','Discussão'),
            'showcase'=>array('📸','Showcase')
        );
        ?>
        <div style="display:flex;gap:8px;overflow-x:auto;padding-bottom:12px;margin-bottom:10px;scrollbar-width:none">
            <a href="?feed=<?php echo $feedTab; ?>" class="flair-badge <?php echo !$flairFilter?'active':''; ?>" style="text-decoration:none; white-space:nowrap; cursor:pointer; <?php echo !$flairFilter?'background:var(--accent);color:#000;border-color:var(--accent)':''; ?>">Todas</a>
            <?php foreach($flairs as $key => $f): ?>
                <a href="?feed=<?php echo $feedTab; ?>&flair=<?php echo $key; ?>"
                   class="flair-badge flair-<?php echo $key; ?> <?php echo $flairFilter===$key?'active':''; ?>"
                   style="text-decoration:none; white-space:nowrap; cursor:pointer; <?php echo $flairFilter===$key?'box-shadow:0 0 10px currentColor; transform:scale(1.05)':''; ?>">
                    <?php echo $f[0]; ?> <?php echo $f[1]; ?>
                </a>
            <?php endforeach; ?>
        </div>

        <div id="feedContainer">
        <?php if (empty($feedPosts)): ?>
            <div class="empty-feed">
                <div class="icon">🌱</div>
                <p>O fórum está a começar.<br>Cria a primeira comunidade e publica o primeiro post!</p>
                <?php if ($currentUser): ?>
                <a href="criar_comunidade.php" style="display:inline-block;margin-top:16px;background:var(--accent);color:#000;padding:10px 22px;border-radius:8px;text-decoration:none;font-family:'Space Mono',monospace;font-size:11px;font-weight:700">+ CRIAR COMUNIDADE</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php foreach ($feedPosts as $post):
                $score    = (int)$post['vote_score'];
                $userVote = isset($userVotes[$post['id']]) ? (int)$userVotes[$post['id']] : 0;
                $scoreCls = $score>0?'positive':($score<0?'negative':'');
                $initials = mb_substr($post['full_name']??'??',0,2);
            ?>
            <div class="post-card" id="post-<?php echo $post['id']; ?>" data-flair="<?php echo htmlspecialchars($post['flair'] ?? ''); ?>">
                <div class="post-vote">
                    <button class="vote-btn up <?php echo $userVote===1?'active':''; ?>" onclick="votePost(<?php echo $post['id']; ?>,1,this)">▲</button>
                    <div class="vote-score <?php echo $scoreCls; ?>" id="score-<?php echo $post['id']; ?>"><?php echo $score; ?></div>
                    <button class="vote-btn down <?php echo $userVote===-1?'active':''; ?>" onclick="votePost(<?php echo $post['id']; ?>,-1,this)">▼</button>
                </div>
                <div class="post-body">
                    <a href="comunidade?slug=<?php echo urlencode($post['community_slug']); ?>" class="post-community">
                        <?php echo $post['community_icon']; ?> <?php echo sanitize($post['community_name']); ?>
                    </a>
                    <?php if (!empty($post['flair'])): ?><?php echo renderFlairBadgeFeed($post['flair']); ?> <?php endif; ?>
                    <a href="topico?id=<?php echo $post['id']; ?>" class="post-title"><?php echo sanitize($post['title']); ?></a>
                    <?php if (!empty($post['content'])): ?>
                    <div class="post-excerpt"><?php echo sanitize(mb_substr($post['content'],0,200)); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($post['image_url'])): ?>
                    <div style="margin-bottom:10px">
                        <img src="<?php echo htmlspecialchars(postImagePath($post['image_url'])); ?>" alt=""
                             style="max-height:160px;max-width:100%;border-radius:8px;border:1px solid var(--border2);object-fit:cover">
                    </div>
                    <?php endif; ?>
                    <div class="post-meta">
                        <a href="perfil?id=<?php echo $post['user_id']; ?>" class="post-author">
                            <div class="post-author-av"><?php if(!empty($post['avatar_url'])): ?><img src="<?php echo sanitize(avPath($post['avatar_url'])); ?>" alt="" onerror="this.style.display='none'; this.parentElement.textContent='<?php echo sanitize($initials); ?>'"><?php else: echo sanitize($initials); endif; ?></div>
                            <?php echo sanitize($post['username']); ?>
                        </a>
                        <span class="post-stat">💬 <?php echo (int)$post['reply_count']; ?></span>
                        <span class="post-time"><?php
                            $diff=time()-strtotime($post['created_at']);
                            if($diff<3600) echo floor($diff/60).'min';
                            elseif($diff<86400) echo floor($diff/3600).'h';
                            else echo date('d/m/Y',strtotime($post['created_at']));
                        ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
        </div>
    </main>

    <aside>
        <?php if ($currentUser): ?>
        <div class="create-comm-card">
            <div class="create-comm-icon">🏗️</div>
            <div class="create-comm-title">Cria a tua comunidade</div>
            <div class="create-comm-sub">Reúne pessoas com os mesmos interesses em impressão 3D.</div>
            <a href="criar_comunidade" class="create-comm-btn">+ CRIAR AGORA</a>
        </div>
        <?php endif; ?>

        <?php if (!empty($communities)): ?>
        <div class="sidebar-card">
            <div class="sidebar-card-header">
                <span class="sidebar-card-title">Comunidades</span>
            </div>
            <?php foreach (array_slice($communities,0,8) as $comm):
                $isJoined = in_array((int)$comm['id'], $myCommIds);
            ?>
            <a href="comunidade?slug=<?php echo urlencode($comm['slug']); ?>" class="comm-row">
                <div class="comm-icon-wrap" style="background:linear-gradient(135deg,<?php echo htmlspecialchars($comm['banner_color']); ?>22,<?php echo htmlspecialchars($comm['banner_color']); ?>44)">
                    <?php echo $comm['icon']; ?>
                </div>
                <div class="comm-info">
                    <div class="comm-name"><?php echo sanitize($comm['name']); ?></div>
                    <div class="comm-members"><?php echo number_format($comm['member_count']); ?> membros</div>
                </div>
                <?php if ($currentUser): ?>
                <button class="comm-join-btn <?php echo $isJoined?'joined':''; ?>"
                    onclick="event.preventDefault();toggleJoin(<?php echo $comm['id']; ?>,this)"
                    data-joined="<?php echo $isJoined?'1':'0'; ?>">
                    <?php echo $isJoined?'✓ Membro':'+ Entrar'; ?>
                </button>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="sidebar-card">
            <div class="sidebar-card-header">
                <span class="sidebar-card-title">📖 Manual</span>
                <a href="/" class="sidebar-card-action">Ver tudo →</a>
            </div>
            <div style="padding:12px 18px;display:flex;flex-direction:column;gap:6px">
                <a href="/#filamentos" style="display:flex;align-items:center;gap:8px;text-decoration:none;color:var(--text);font-size:12px;padding:6px 8px;border-radius:8px;transition:background 0.15s" onmouseover="this.style.background='rgba(0,229,255,0.06)'" onmouseout="this.style.background='transparent'"><span>🧪</span> Materiais &amp; Filamentos</a>
                <a href="/#tipos-impressoras" style="display:flex;align-items:center;gap:8px;text-decoration:none;color:var(--text);font-size:12px;padding:6px 8px;border-radius:8px;transition:background 0.15s" onmouseover="this.style.background='rgba(0,229,255,0.06)'" onmouseout="this.style.background='transparent'"><span>🖨️</span> Tipos de Impressoras</a>
                <a href="/#processo" style="display:flex;align-items:center;gap:8px;text-decoration:none;color:var(--text);font-size:12px;padding:6px 8px;border-radius:8px;transition:background 0.15s" onmouseover="this.style.background='rgba(0,229,255,0.06)'" onmouseout="this.style.background='transparent'"><span>⚙️</span> Parâmetros de Impressão</a>
                <a href="/#problemas" style="display:flex;align-items:center;gap:8px;text-decoration:none;color:var(--text);font-size:12px;padding:6px 8px;border-radius:8px;transition:background 0.15s" onmouseover="this.style.background='rgba(0,229,255,0.06)'" onmouseout="this.style.background='transparent'"><span>🔧</span> Troubleshooting</a>
                <a href="/#software" style="display:flex;align-items:center;gap:8px;text-decoration:none;color:var(--text);font-size:12px;padding:6px 8px;border-radius:8px;transition:background 0.15s" onmouseover="this.style.background='rgba(0,229,255,0.06)'" onmouseout="this.style.background='transparent'"><span>💻</span> Software &amp; Slicers</a>
            </div>
        </div>

        <div class="sidebar-card">
            <div class="sidebar-card-header"><span class="sidebar-card-title">Estatísticas</span></div>
            <div class="stats-row"><span class="stats-label">📝 Posts</span><span class="stats-value"><?php echo number_format($totalPosts); ?></span></div>
            <div class="stats-row"><span class="stats-label">🏘️ Comunidades</span><span class="stats-value"><?php echo number_format($totalComms); ?></span></div>
            <div class="stats-row"><span class="stats-label">👥 Utilizadores</span><span class="stats-value"><?php echo number_format($totalUsers); ?></span></div>
        </div>
    </aside>
</div>

<script>
var CSRF = '<?php echo $csrf; ?>';
var CUR_UID = <?php echo $currentUser ? (int)$currentUser['id'] : 'null'; ?>;

async function votePost(postId, value, btn) {
    if (!CUR_UID) { window.location.href='../login.php?redirect=forum/index.php'; return; }
    try {
        var res=await fetch('api/forum.php',{method:'POST',headers:{'Content-Type':'application/json'},
            body:JSON.stringify({action:'vote_post',csrf_token:CSRF,post_id:postId,value:value})});
        if (!res.ok) { console.error('HTTP',res.status); return; }
        var text=await res.text();
        var data; try{data=JSON.parse(text);}catch(e){console.error('JSON parse error:',text);return;}
        if (!data.success) { console.warn('vote error:',data.error); if(data.error&&data.error.indexOf('Token')>=0){alert('⚠️ '+data.error);} return; }
        var card=document.getElementById('post-'+postId);
        var scoreEl=document.getElementById('score-'+postId);
        if (scoreEl){scoreEl.textContent=data.score;scoreEl.className='vote-score'+(data.score>0?' positive':data.score<0?' negative':'');}
        card.querySelector('.vote-btn.up').classList.toggle('active',data.user_vote===1);
        card.querySelector('.vote-btn.down').classList.toggle('active',data.user_vote===-1);
    }catch(e){console.error(e);}
}

async function toggleJoin(commId, btn) {
    if (!CUR_UID) { window.location.href='../login.php?redirect=forum/index.php'; return; }
    var isJoined=btn.dataset.joined==='1';
    try {
        var res=await fetch('api/forum.php',{method:'POST',headers:{'Content-Type':'application/json'},
            body:JSON.stringify({action:isJoined?'leave_community':'join_community',csrf_token:CSRF,community_id:commId})});
        if (!res.ok) { console.error('HTTP join',res.status); return; }
        var text=await res.text();
        var data; try{data=JSON.parse(text);}catch(e){console.error('JSON join error:',text);return;}
        if (!data.success) { console.warn('join error:',data.error); if(data.error&&data.error.indexOf('Token')>=0){alert('⚠️ '+data.error);} return; }
        btn.dataset.joined=data.joined?'1':'0';
        btn.textContent=data.joined?'✓ Membro':'+ Entrar';
        btn.classList.toggle('joined',data.joined);
    }catch(e){}
}

function switchFeed(type, btn) {
    document.querySelectorAll('.feed-tab').forEach(function(b){b.classList.remove('active');});
    btn.classList.add('active');
    var container=document.getElementById('feedContainer');
    var cards=Array.from(container.querySelectorAll('.post-card'));
    cards.sort(function(a,b){
        if (type==='popular') return parseInt(b.querySelector('.vote-score').textContent||0)-parseInt(a.querySelector('.vote-score').textContent||0);
        return 0;
    });
    cards.forEach(function(c){container.appendChild(c);});
}

function searchForum(val) {
    val=val.toLowerCase().trim();
    document.querySelectorAll('.post-card').forEach(function(card){
        var title=(card.querySelector('.post-title')||{}).textContent||'';
        var excerpt=(card.querySelector('.post-excerpt')||{}).textContent||'';
        var comm=(card.querySelector('.post-community')||{}).textContent||'';
        card.style.display=(!val||title.toLowerCase().indexOf(val)>=0||excerpt.toLowerCase().indexOf(val)>=0||comm.toLowerCase().indexOf(val)>=0)?'flex':'none';
    });
}

async function handleGlobalSearch(query) {
    const resultsContainer = document.getElementById('globalSearchResults');
    const q = query.toLowerCase().trim();

    if (q.length < 2) {
        resultsContainer.classList.remove('active');
        return;
    }

    let html = '';

    // 1. Pesquisa Local nos Posts do Feed
    const localPosts = [];
    document.querySelectorAll('.post-card').forEach(card => {
        const title = card.querySelector('.post-title').textContent;
        const comm = card.querySelector('.post-community').textContent;
        const id = card.id.replace('post-', '');
        if (title.toLowerCase().includes(q) || comm.toLowerCase().includes(q)) {
            localPosts.push({ id, title, comm: comm.trim() });
        }
    });

    if (localPosts.length > 0) {
        html += '<div class="search-group-label">Posts no Feed</div>';
        localPosts.slice(0, 5).forEach(p => {
            html += `
                <a href="topico.php?id=${p.id}" class="search-item">
                    <div class="search-item-icon">📝</div>
                    <div class="search-item-info">
                        <span class="search-item-title">${p.title}</span>
                        <span class="search-item-sub">${p.comm}</span>
                    </div>
                </a>
            `;
        });
    }

    // 2. Atalhos do Manual
    const manualTopics = [
        { t: 'Filamentos', h: '../index.php#filamentos', i: '🧪' },
        { t: 'Troubleshooting', h: '../index.php#problemas', i: '🔧' },
        { t: 'Parâmetros', h: '../index.php#processo', i: '⚙️' },
        { t: 'Impressoras', h: '../index.php#tipos-impressoras', i: '🖨️' }
    ].filter(m => m.t.toLowerCase().includes(q));

    if (manualTopics.length > 0) {
        html += '<div class="search-group-label">Manual de Impressão</div>';
        manualTopics.forEach(m => {
            html += `
                <a href="${m.h}" class="search-item">
                    <div class="search-item-icon">${m.i}</div>
                    <div class="search-item-info">
                        <span class="search-item-title">${m.t}</span>
                        <span class="search-item-sub">Capítulo do Manual</span>
                    </div>
                </a>
            `;
        });
    }

    // 3. Pesquisa na API (Comunidades)
    try {
        const res = await fetch('api/forum.php?action=search_meta&q=' + encodeURIComponent(q));
        const data = await res.json();
        if (data.success && data.communities && data.communities.length > 0) {
            html += '<div class="search-group-label">Comunidades</div>';
            data.communities.forEach(c => {
                html += `
                    <a href="comunidade.php?slug=${c.slug}" class="search-item">
                        <div class="search-item-icon">${c.icon}</div>
                        <div class="search-item-info">
                            <span class="search-item-title">${c.name}</span>
                            <span class="search-item-sub">${c.member_count} membros</span>
                        </div>
                    </a>
                `;
            });
        }
    } catch(e) {}

    if (!html) {
        html = '<div class="search-no-res">Sem resultados para "' + query + '"</div>';
    }

    resultsContainer.innerHTML = html;
    resultsContainer.classList.add('active');
}

// Fechar ao clicar fora
document.addEventListener('click', function(e) {
    const searchBox = document.querySelector('.topbar-search');
    if (searchBox && !searchBox.contains(e.target)) {
        document.getElementById('globalSearchResults').classList.remove('active');
    }
});


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
    // Spoiler no feed — blur excerpt
    document.querySelectorAll('.post-card[data-flair="spoiler"]').forEach(function(card) {
        var excerpt  = card.querySelector('.post-excerpt');
        var existing = card.querySelector('.spoiler-feed-overlay');
        if (p.hideSpoiler) {
            if (excerpt) excerpt.style.filter = 'blur(4px)';
            if (!existing) {
                var ov = document.createElement('div');
                ov.className = 'spoiler-feed-overlay';
                ov.style.cssText = 'position:absolute;top:0;left:0;right:0;bottom:0;display:flex;align-items:center;justify-content:center;z-index:5;cursor:pointer;border-radius:inherit';
                ov.innerHTML = '<span style="background:rgba(255,204,0,0.1);border:1px solid rgba(255,204,0,0.3);border-radius:8px;padding:6px 14px;font-family:Space Mono,monospace;font-size:10px;color:#ffcc00;font-weight:700">⚠️ SPOILER — clica para revelar</span>';
                ov.onclick = function(){ if(excerpt) excerpt.style.filter='none'; ov.remove(); };
                card.style.position = 'relative';
                card.appendChild(ov);
            }
        } else {
            if (excerpt) excerpt.style.filter = 'none';
            if (existing) existing.remove();
        }
    });
    // Spoiler mask no tópico aberto
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

<?php require_once '../includes/welcome_popup.php'; ?>
</body>
</html>
