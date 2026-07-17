<?php
/**
 * forum/perfil.php — Perfil público de um utilizador no fórum
 */
require_once __DIR__ . '/../includes/functions.php';

$db = getDB();

$targetId = (int)($_GET['id'] ?? 0);
if ($targetId < 1) { header('Location: /forum/'); exit; }

$currentUser = isLoggedIn() ? getCurrentUser() : null;

// ── Garantir tabela de XP ─────────────────────────────────────
try { $db->exec("CREATE TABLE IF NOT EXISTS forum_user_xp (
    user_id      INT NOT NULL,
    community_id INT NOT NULL,
    xp           INT DEFAULT 0,
    PRIMARY KEY (user_id, community_id),
    FOREIGN KEY (user_id)      REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (community_id) REFERENCES forum_communities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch(Exception $e){}

// ── Utilizador ────────────────────────────────────────────────
try {
    $stmt = $db->prepare("SELECT id, full_name, username, avatar_url, bio, location, experience_level, created_at, is_active FROM users WHERE id=? LIMIT 1");
    $stmt->execute(array($targetId));
    $user = $stmt->fetch();
} catch(Exception $e) { $user = null; }

if (!$user || !$user['is_active']) {
    http_response_code(404);
    die('<div style="font-family:monospace;text-align:center;padding:80px;color:#888;background:#0a0a0f;min-height:100vh"><p style="font-size:48px">👤</p><h2 style="color:#fff;margin:16px 0">Utilizador não encontrado</h2><a href="/forum/" style="color:#00e5ff">← Voltar ao fórum</a></div>');
}

// ── Personalização do perfil ─────────────────────────────────
$customConfig = array('frame_key'=>null,'background_key'=>null,'banner_url'=>null,'accent_color'=>null,'top_badges'=>null);
try {
    $cq = $db->prepare("SELECT * FROM user_profile_config WHERE user_id=?");
    $cq->execute(array($targetId));
    $cc = $cq->fetch();
    if ($cc) {
        $customConfig = $cc;
        // Auto-fix para top_badges se necessário
        if (!isset($cc['top_badges'])) {
             try { $db->exec("ALTER TABLE user_profile_config ADD COLUMN top_badges TEXT NULL"); } catch(Exception $e){}
        }
    }
} catch(Exception $e){}

// Buscar Badges (Usando a lógica centralizada com fallback)
$badgeData = getTopBadges($targetId);

// Buscar CSS do frame e background
$frameCSS = '';
$bgCSS    = '';
if (!empty($customConfig['frame_key'])) {
    try {
        $fq = $db->prepare("SELECT css_value FROM shop_items WHERE item_key=? LIMIT 1");
        $fq->execute(array($customConfig['frame_key']));
        $fr = $fq->fetch();
        if ($fr) $frameCSS = $fr['css_value'];
    } catch(Exception $e){}
}
if (!empty($customConfig['background_key'])) {
    try {
        $bq = $db->prepare("SELECT css_value FROM shop_items WHERE item_key=? LIMIT 1");
        $bq->execute(array($customConfig['background_key']));
        $br = $bq->fetch();
        if ($br) $bgCSS = $br['css_value'];
    } catch(Exception $e){}
}
$accentColor = !empty($customConfig['accent_color']) ? $customConfig['accent_color'] : '#00e5ff';
$bannerUrl   = !empty($customConfig['banner_url']) ? avPath($customConfig['banner_url']) : '';

// ── Estatísticas globais ──────────────────────────────────────
$stats = array('posts'=>0,'replies'=>0,'post_karma'=>0,'reply_karma'=>0,'communities'=>0,'owned'=>0);
try {
    $r = $db->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(vote_score),0) as karma FROM forum_posts WHERE user_id=?");
    $r->execute(array($targetId)); $row = $r->fetch();
    $stats['posts']       = (int)$row['cnt'];
    $stats['post_karma']  = (int)$row['karma'];
} catch(Exception $e){}
try {
    $r = $db->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(vote_score),0) as karma FROM forum_replies WHERE user_id=?");
    $r->execute(array($targetId)); $row = $r->fetch();
    $stats['replies']      = (int)$row['cnt'];
    $stats['reply_karma']  = (int)$row['karma'];
} catch(Exception $e){}
try {
    $r = $db->prepare("SELECT COUNT(*) as cnt, SUM(role='owner') as owned FROM forum_memberships WHERE user_id=?");
    $r->execute(array($targetId)); $row = $r->fetch();
    $stats['communities'] = (int)$row['cnt'];
    $stats['owned']       = (int)$row['owned'];
} catch(Exception $e){}
$stats['total_karma'] = $stats['post_karma'] + $stats['reply_karma'];

// ── XP por comunidade ─────────────────────────────────────────
// Calcular e guardar XP: post=+15, reply=+5, voto_pos=+3, voto_neg=-2
try {
    // Recalcular XP de cada comunidade onde o user tem actividade
    $commsWithActivity = $db->prepare("
        SELECT DISTINCT community_id FROM forum_posts WHERE user_id=?
    ");
    $commsWithActivity->execute(array($targetId));
    $activeComms = $commsWithActivity->fetchAll(PDO::FETCH_COLUMN);

    foreach ($activeComms as $cid) {
        // Posts nesta comunidade
        $pq = $db->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(GREATEST(vote_score,0)),0) as pos, COALESCE(SUM(GREATEST(-vote_score,0)),0) as neg FROM forum_posts WHERE user_id=? AND community_id=?");
        $pq->execute(array($targetId, $cid)); $pr = $pq->fetch();
        $postXp = (int)$pr['cnt'] * 15 + (int)$pr['pos'] * 3 - (int)$pr['neg'] * 2;

        // Replies a posts desta comunidade
        $rq = $db->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(GREATEST(fr.vote_score,0)),0) as pos, COALESCE(SUM(GREATEST(-fr.vote_score,0)),0) as neg FROM forum_replies fr JOIN forum_posts fp ON fp.id=fr.post_id WHERE fr.user_id=? AND fp.community_id=?");
        $rq->execute(array($targetId, $cid)); $rr = $rq->fetch();
        $replyXp = (int)$rr['cnt'] * 5 + (int)$rr['pos'] * 1 - (int)$rr['neg'] * 1;

        $totalXp = max(0, $postXp + $replyXp);
        $db->prepare("INSERT INTO forum_user_xp (user_id, community_id, xp) VALUES (?,?,?) ON DUPLICATE KEY UPDATE xp=?")->execute(array($targetId, $cid, $totalXp, $totalXp));
    }
} catch(Exception $e){}

// ── Comunidades com XP ────────────────────────────────────────
$communities = array();
try {
    $cq = $db->prepare("
        SELECT fc.id, fc.name, fc.slug, fc.icon, fc.banner_color, fc.member_count,
               fm.role, COALESCE(fx.xp, 0) as xp
        FROM forum_memberships fm
        JOIN forum_communities fc ON fc.id = fm.community_id
        LEFT JOIN forum_user_xp fx ON fx.user_id=fm.user_id AND fx.community_id=fm.community_id
        WHERE fm.user_id=? AND fc.is_active=1
        ORDER BY xp DESC, fm.joined_at DESC
    ");
    $cq->execute(array($targetId));
    $communities = $cq->fetchAll();
} catch(Exception $e){}

// ── Helper: calcular nível de XP ─────────────────────────────
function xpLevel($xp) {
    if ($xp >= 500) return array('Lendário',   '🏆', '#ffcc00',  'rgba(255,204,0,0.15)');
    if ($xp >= 200) return array('Especialista','💎', '#00e5ff',  'rgba(0,229,255,0.12)');
    if ($xp >= 100) return array('Veterano',    '⭐', '#a78bfa',  'rgba(124,58,237,0.12)');
    if ($xp >= 50)  return array('Ativo',       '🔥', '#ff6b35',  'rgba(255,107,53,0.12)');
    if ($xp >= 20)  return array('Membro',      '✅', '#00ff88',  'rgba(0,255,136,0.1)');
    return              array('Novo',           '🌱', '#888899',  'rgba(136,136,153,0.1)');
}
function xpToNext($xp) {
    $tiers = array(20,50,100,200,500);
    foreach ($tiers as $t) { if ($xp < $t) return $t; }
    return null;
}

// ── Posts recentes ────────────────────────────────────────────
$recentPosts = array();
try {
    $pq = $db->prepare("
        SELECT fp.id, fp.title, fp.vote_score, fp.reply_count, fp.created_at, fp.flair,
               fc.name as comm_name, fc.slug as comm_slug, fc.icon as comm_icon
        FROM forum_posts fp
        JOIN forum_communities fc ON fc.id = fp.community_id
        WHERE fp.user_id=? AND fp.status='approved'
        ORDER BY fp.created_at DESC LIMIT 10
    ");
    $pq->execute(array($targetId));
    $recentPosts = $pq->fetchAll();
} catch(Exception $e){}

// ── Respostas recentes ────────────────────────────────────────
$recentReplies = array();
try {
    $rq = $db->prepare("
        SELECT fr.id, fr.content, fr.vote_score, fr.created_at,
               fp.id as post_id, fp.title as post_title,
               fc.name as comm_name, fc.slug as comm_slug
        FROM forum_replies fr
        JOIN forum_posts fp ON fp.id = fr.post_id
        JOIN forum_communities fc ON fc.id = fp.community_id
        WHERE fr.user_id=? AND fp.status='approved'
        ORDER BY fr.created_at DESC LIMIT 8
    ");
    $rq->execute(array($targetId));
    $recentReplies = $rq->fetchAll();
} catch(Exception $e){}

// ── Equipamento (Printers, Slicers, Materials) ────────────────
$printers = []; $slicers = []; $materials = [];
try {
    $pq = $db->prepare("SELECT * FROM user_printers WHERE user_id=? ORDER BY created_at DESC");
    $pq->execute([$targetId]); $printers = $pq->fetchAll();

    $sq = $db->prepare("SELECT * FROM user_slicers WHERE user_id=? ORDER BY created_at DESC");
    $sq->execute([$targetId]); $slicers = $sq->fetchAll();

    $mq = $db->prepare("SELECT * FROM user_materials WHERE user_id=? ORDER BY created_at DESC");
    $mq->execute([$targetId]); $materials = $mq->fetchAll();
} catch(Exception $e){}

// Buscar Inventário completo para "Conquistas"
$inventoryBadges = [];
try {
    $iq = $db->prepare("SELECT si.* FROM shop_items si JOIN user_inventory ui ON ui.item_id=si.id WHERE ui.user_id=? AND si.category IN ('badge','medal','accent') ORDER BY si.name ASC");
    $iq->execute(array($targetId));
    $inventoryBadges = $iq->fetchAll();
} catch(Exception $e){}

// ── Mensagens não lidas (topbar) ──────────────────────────────
$unreadMsgs = 0;
if ($currentUser) {
    try {
        $um = $db->prepare("SELECT COUNT(*) FROM private_messages WHERE receiver_id=? AND read_at IS NULL");
        $um->execute(array((int)$currentUser['id']));
        $unreadMsgs = (int)$um->fetchColumn();
    } catch(Exception $e){}
}

function fmtTime($ts) {
    $d = time() - strtotime($ts);
    if ($d < 3600)  return floor($d/60).'min atrás';
    if ($d < 86400) return floor($d/3600).'h atrás';
    return date('d/m/Y', strtotime($ts));
}

$initials = mb_substr($user['full_name'] ?? '??', 0, 2);
$csrf = generateCSRFToken();

// ── Guardar personalização (só o próprio utilizador) ─────────
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && $currentUser
    && (int)$currentUser['id'] === $targetId
    && verifyCSRFToken($_POST['csrf_token'] ?? null)
    && ($_POST['action'] ?? '') === 'save_customization') {

    $newFrame   = $_POST['frame_key']       ?? null;
    $newBg      = $_POST['background_key']  ?? null;
    $newBanner  = trim($_POST['banner_url'] ?? '');
    $newAccent  = $_POST['accent_color']    ?? null;

    // Validar banner URL
    if ($newBanner && !filter_var($newBanner, FILTER_VALIDATE_URL)) $newBanner = '';

    // Validar frame e background (têm de estar no inventário)
    $validateItem = function($key, $category) use ($db, $uid) {
        if (!$key) return null;
        try {
            $q = $db->prepare("SELECT si.item_key FROM shop_items si JOIN user_inventory ui ON ui.item_id=si.id WHERE si.item_key=? AND si.category=? AND ui.user_id=? LIMIT 1");
            $q->execute(array($key, $category, $uid));
            return $q->fetchColumn() ?: null;
        } catch(Exception $e){ return null; }
    };
    $uid = (int)$currentUser['id'];
    $newFrame = $validateItem($newFrame, 'frame');
    $newBg    = $validateItem($newBg, 'background');
    if ($newAccent) {
        // Validar accent (tem de estar no inventário)
        try {
            $aq = $db->prepare("SELECT si.css_value FROM shop_items si JOIN user_inventory ui ON ui.item_id=si.id WHERE si.css_value=? AND si.category='accent' AND ui.user_id=? LIMIT 1");
            $aq->execute(array($newAccent, $uid));
            $newAccent = $aq->fetchColumn() ?: null;
        } catch(Exception $e){ $newAccent = null; }
    }

    try {
        $db->prepare("INSERT INTO user_profile_config (user_id,frame_key,background_key,banner_url,accent_color)
            VALUES (?,?,?,?,?)
            ON DUPLICATE KEY UPDATE frame_key=VALUES(frame_key), background_key=VALUES(background_key),
            banner_url=VALUES(banner_url), accent_color=VALUES(accent_color)")
            ->execute(array($targetId, $newFrame, $newBg ?: null, $newBanner ?: null, $newAccent));
        // Recarregar config
        $customConfig['frame_key']      = $newFrame;
        $customConfig['background_key'] = $newBg;
        $customConfig['banner_url']     = $newBanner;
        $customConfig['accent_color']   = $newAccent;
        $accentColor = $newAccent ?: '#00e5ff';
        $bannerUrl   = $newBanner ? avPath($newBanner) : '';
        // Recarregar frame/bg CSS
        if ($newFrame) {
            $fq = $db->prepare("SELECT css_value FROM shop_items WHERE item_key=? LIMIT 1");
            $fq->execute(array($newFrame)); $fr = $fq->fetch();
            $frameCSS = $fr ? $fr['css_value'] : '';
        } else { $frameCSS = ''; }
        if ($newBg) {
            $bq = $db->prepare("SELECT css_value FROM shop_items WHERE item_key=? LIMIT 1");
            $bq->execute(array($newBg)); $br = $bq->fetch();
            $bgCSS = $br ? $br['css_value'] : '';
        } else { $bgCSS = ''; }
        $saveFlash = '✅ Personalização guardada!';
    } catch(Exception $e) {
        $saveFlash = '⚠️ Erro ao guardar: ' . $e->getMessage();
    }
}
$saveFlash = $saveFlash ?? null;

?><!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
    <link rel="icon" type="image/x-icon"  href="../favicons/favicon-perfil.ico">
    <link rel="icon" type="image/svg+xml" href="../favicons/favicon-perfil.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="../favicons/favicon-perfil-32.png">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo sanitize($user['full_name']); ?> — Perfil no Fórum 3D</title>
<link rel="icon" type="image/x-icon"  href="../favicon.ico">
<link rel="icon" type="image/svg+xml" href="../favicon.svg">
<link rel="icon" type="image/png" sizes="32x32" href="../favicon-32.png">
<link rel="apple-touch-icon" sizes="192x192" href="../favicon-192.png">
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800;900&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root{--bg:#0a0a0f;--surface:#111118;--surface2:#1a1a26;--surface3:#222235;--accent:<?php echo htmlspecialchars($accentColor); ?>;--accent2:#ff6b35;--accent3:#7c3aed;--accent4:#00ff88;--text:#e8e8f0;--muted:#888899;--border:rgba(0,229,255,0.1);--border2:rgba(255,255,255,0.06)}
*{margin:0;padding:0;box-sizing:border-box}
body{background:var(--bg);color:var(--text);font-family:'Inter',sans-serif;min-height:100vh}
<?php if ($bgCSS): ?>
body{<?php echo preg_replace('/--custom-bg:\s*[^;]+;/', '', $bgCSS); ?>}
body::after{content:'';position:fixed;inset:0;pointer-events:none;z-index:0;<?php
    preg_match('/--custom-overlay:\s*(.+)/s', $bgCSS, $m);
    if (!empty($m[1])) echo 'background:' . trim(rtrim($m[1],';')) . ';';
?>opacity:1}
<?php endif; ?>
body::before{content:'';position:fixed;inset:0;pointer-events:none;z-index:0;background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.03'/%3E%3C/svg%3E");opacity:0.4}

/* Topbar */
.topbar{position:sticky;top:0;z-index:100;background:rgba(10,10,15,0.92);backdrop-filter:blur(16px);border-bottom:1px solid var(--border);padding:0 32px;display:flex;align-items:center;gap:16px;height:58px}
.topbar-logo{font-family:'Space Mono',monospace;font-size:11px;font-weight:700;color:var(--accent);letter-spacing:3px;text-transform:uppercase;text-decoration:none;white-space:nowrap}
.topbar-logo span{color:var(--muted)}
.topbar-right{display:flex;align-items:center;gap:10px;margin-left:auto}
.topbar-btn{background:none;border:1px solid var(--border2);border-radius:8px;padding:7px 14px;color:var(--muted);font-family:'Space Mono',monospace;font-size:10px;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:6px;transition:all 0.2s;white-space:nowrap}
.topbar-btn:hover{border-color:var(--accent);color:var(--accent);background:rgba(0,229,255,0.05)}
.topbar-btn.primary{background:var(--accent);color:#000;border-color:transparent;font-weight:700}
.notif-badge{background:var(--accent2);color:#fff;border-radius:100px;font-size:9px;padding:1px 5px;font-family:'Space Mono',monospace;font-weight:700}
.topbar-av{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--accent3),var(--accent));display:flex;align-items:center;justify-content:center;font-family:'Space Mono',monospace;font-size:11px;font-weight:700;color:#000;overflow:hidden;text-decoration:none;flex-shrink:0}
.topbar-av img{width:100%;height:100%;object-fit:cover;border-radius:50%}

/* Hero banner */
.hero-banner{height:180px;position:relative;overflow:hidden;background:linear-gradient(135deg,#0d0d1a,#111118)}
.hero-banner-grid{position:absolute;inset:0;background-image:linear-gradient(rgba(0,229,255,0.05) 1px,transparent 1px),linear-gradient(90deg,rgba(0,229,255,0.05) 1px,transparent 1px);background-size:32px 32px}
.hero-banner-glow{position:absolute;top:-60px;right:-60px;width:340px;height:340px;background:radial-gradient(circle,rgba(124,58,237,0.2) 0%,transparent 70%);pointer-events:none}
.hero-banner-glow2{position:absolute;bottom:-40px;left:10%;width:280px;height:280px;background:radial-gradient(circle,rgba(0,229,255,0.1) 0%,transparent 70%);pointer-events:none}

/* Hero perfil */
.hero-body{position:relative;z-index:1;padding:0 40px 0;max-width:1100px;margin:0 auto}
.hero-av-wrap{position:relative;margin-top:-56px;display:inline-block}
.hero-av{width:112px;height:112px;border-radius:50%;border:4px solid var(--bg);background:linear-gradient(135deg,var(--accent3),var(--accent));display:flex;align-items:center;justify-content:center;font-family:'Space Mono',monospace;font-size:34px;font-weight:700;color:#000;overflow:hidden;box-shadow:0 0 32px rgba(0,229,255,0.25)}
.hero-av img{width:100%;height:100%;object-fit:cover;border-radius:50%}

.hero-info-row{display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:16px;padding:12px 0 24px}
.hero-info-left{}
.hero-name{font-family:'Syne',sans-serif;font-size:26px;font-weight:900;color:#fff;line-height:1.1;margin-bottom:4px}
.hero-username{font-family:'Space Mono',monospace;font-size:12px;color:var(--accent);margin-bottom:8px}
.hero-bio{font-size:13px;color:var(--muted);max-width:500px;line-height:1.6}
.hero-meta{display:flex;gap:14px;flex-wrap:wrap;margin-top:8px}
.hero-meta-item{font-family:'Space Mono',monospace;font-size:10px;color:var(--muted);display:flex;align-items:center;gap:5px}

.hero-actions{display:flex;gap:8px;flex-shrink:0}
.btn-msg{background:linear-gradient(135deg,var(--accent),var(--accent3));border:none;border-radius:9px;padding:10px 20px;color:#000;font-family:'Space Mono',monospace;font-size:11px;font-weight:700;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:6px;transition:opacity 0.2s}
.btn-msg:hover{opacity:0.85}
.btn-report{background:rgba(255,68,68,0.08);border:1px solid rgba(255,68,68,0.25);border-radius:9px;padding:10px 16px;color:#ff7777;font-family:'Space Mono',monospace;font-size:11px;font-weight:700;cursor:pointer;transition:all 0.2s}
.btn-report:hover{background:rgba(255,68,68,0.16)}

/* Stats bar */
.stats-bar{background:var(--surface);border-top:1px solid var(--border2);border-bottom:1px solid var(--border2)}
.stats-inner{max-width:1100px;margin:0 auto;padding:0 40px;display:flex;gap:0}
.stat-item{flex:1;padding:18px 0;text-align:center;border-right:1px solid var(--border2);position:relative}
.stat-item:last-child{border-right:none}
.stat-num{font-family:'Syne',sans-serif;font-size:24px;font-weight:900;color:var(--accent);line-height:1}
.stat-num.karma-pos{color:var(--accent2)}
.stat-num.karma-neg{color:#7c9aff}
.stat-lbl{font-family:'Space Mono',monospace;font-size:9px;color:var(--muted);letter-spacing:2px;text-transform:uppercase;margin-top:4px}

/* Layout */
.layout{max-width:1100px;margin:0 auto;padding:28px 40px;display:grid;grid-template-columns:1fr 300px;gap:24px;position:relative;z-index:1}

/* Tabs Bar (Estilo Perfil) */
.tabs-bar{display:flex;gap:0;border-bottom:1px solid var(--border2);margin-bottom:24px;overflow-x:auto}
.tab-btn{padding:14px 20px;border:none;background:transparent;color:var(--muted);font-family:'Space Mono',monospace;font-size:10px;letter-spacing:1px;text-transform:uppercase;cursor:pointer;border-bottom:2px solid transparent;transition:all 0.2s;white-space:nowrap}
.tab-btn:hover{color:var(--text)}
.tab-btn.active{color:var(--accent);border-bottom-color:var(--accent)}
.tab-panel{display:none;animation:fadeIn 0.3s ease}
.tab-panel.active{display:block}
@keyframes fadeIn{from{opacity:0;transform:translateY(4px)}to{opacity:1;transform:translateY(0)}}

/* Secções */
.section-title{font-family:'Space Mono',monospace;font-size:10px;font-weight:700;color:var(--muted);letter-spacing:2px;text-transform:uppercase;margin-bottom:14px;display:flex;align-items:center;gap:8px}
.section-title span{background:var(--surface2);border:1px solid var(--border2);border-radius:20px;padding:2px 10px;color:var(--text);font-size:10px}

/* Post cards */
.post-card{background:var(--surface);border:1px solid var(--border2);border-radius:12px;padding:16px 18px;margin-bottom:10px;transition:border-color 0.2s;display:flex;gap:14px}
.post-card:hover{border-color:rgba(0,229,255,0.2)}
.post-vote{font-family:'Space Mono',monospace;font-size:13px;font-weight:700;color:var(--muted);flex-shrink:0;width:36px;text-align:center;padding-top:2px}
.post-vote.pos{color:var(--accent2)}.post-vote.neg{color:#7c9aff}
.post-content{flex:1;min-width:0}
.post-comm{font-family:'Space Mono',monospace;font-size:9px;color:var(--muted);margin-bottom:5px;display:flex;align-items:center;gap:5px}
.post-title-link{font-size:14px;font-weight:600;color:var(--text);text-decoration:none;line-height:1.4;display:block;margin-bottom:6px;transition:color 0.2s}
.post-title-link:hover{color:var(--accent)}
.post-footer-row{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.post-meta-tag{font-family:'Space Mono',monospace;font-size:9px;color:var(--muted)}
.flair-mini{font-family:'Space Mono',monospace;font-size:9px;font-weight:700;padding:2px 7px;border-radius:20px;text-transform:uppercase}
.flair-discussao_tecnica{background:rgba(124,58,237,0.06);color:#a78bfa;border:1px solid rgba(124,58,237,0.15)}
.flair-debate{background:rgba(124,58,237,0.06);color:#a78bfa;border:1px solid rgba(124,58,237,0.15)}
.flair-showcase{background:rgba(0,229,255,0.06);color:#00e5ff;border:1px solid rgba(0,229,255,0.15)}
.flair-humor{background:rgba(0,229,255,0.06);color:#00e5ff;border:1px solid rgba(0,229,255,0.15)}
.flair-pergunta{background:rgba(124,58,237,0.08);color:#a78bfa;border:1px solid rgba(124,58,237,0.2)}
.flair-tutorial{background:rgba(0,229,255,0.08);color:var(--accent);border:1px solid rgba(0,229,255,0.15)}
.flair-projeto{background:rgba(0,255,136,0.06);color:var(--accent4);border:1px solid rgba(0,255,136,0.15)}
.flair-ajuda{background:rgba(255,107,53,0.08);color:var(--accent2);border:1px solid rgba(255,107,53,0.2)}

/* Reply cards */
.reply-card{background:var(--surface);border:1px solid var(--border2);border-radius:12px;padding:14px 18px;margin-bottom:10px;transition:border-color 0.2s}
.reply-card:hover{border-color:rgba(0,229,255,0.2)}
.reply-post-link{font-family:'Space Mono',monospace;font-size:9px;color:var(--muted);margin-bottom:6px;display:flex;align-items:center;gap:5px;text-decoration:none;transition:color 0.2s}
.reply-post-link:hover{color:var(--accent)}
.reply-text{font-size:13px;color:var(--text);line-height:1.6;margin-bottom:8px;word-break:break-word}
.reply-footer{display:flex;align-items:center;gap:10px}

/* Sidebar — comunidades com XP */
.comm-xp-card{background:var(--surface);border:1px solid var(--border2);border-radius:12px;padding:14px 16px;margin-bottom:10px;transition:border-color 0.2s;text-decoration:none;display:block}
.comm-xp-card:hover{border-color:rgba(0,229,255,0.2)}
.comm-xp-top{display:flex;align-items:center;gap:10px;margin-bottom:10px}
.comm-xp-icon{width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.comm-xp-name{font-size:13px;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.comm-xp-role{font-family:'Space Mono',monospace;font-size:8px;font-weight:700;padding:2px 6px;border-radius:4px;flex-shrink:0}
.role-owner{background:rgba(255,204,0,0.1);color:#ffcc00;border:1px solid rgba(255,204,0,0.2)}
.role-moderator{background:rgba(124,58,237,0.1);color:#a78bfa;border:1px solid rgba(124,58,237,0.2)}
.role-member{background:rgba(0,229,255,0.06);color:var(--accent);border:1px solid rgba(0,229,255,0.15)}

/* XP bar */
.xp-bar-wrap{margin-top:4px}
.xp-level-row{display:flex;align-items:center;justify-content:space-between;margin-bottom:5px}
.xp-level-badge{font-family:'Space Mono',monospace;font-size:9px;font-weight:700;padding:2px 8px;border-radius:20px}
.xp-amount{font-family:'Space Mono',monospace;font-size:9px;color:var(--muted)}
.xp-bar{height:5px;background:var(--surface3);border-radius:100px;overflow:hidden}
.xp-fill{height:100%;border-radius:100px;transition:width 0.8s ease;background:linear-gradient(90deg,var(--accent3),var(--accent))}
.xp-next{font-family:'Space Mono',monospace;font-size:9px;color:var(--muted);margin-top:4px;text-align:right}

/* Empty state */
.empty-state{text-align:center;padding:32px;color:var(--muted)}
.empty-state-icon{font-size:36px;margin-bottom:10px}

/* XP legend */
.xp-legend{background:var(--surface);border:1px solid var(--border2);border-radius:12px;padding:14px 16px;margin-top:14px}
.xp-legend-title{font-family:'Space Mono',monospace;font-size:9px;font-weight:700;color:var(--muted);letter-spacing:2px;text-transform:uppercase;margin-bottom:10px}

/* Equipment Cards */
.item-card{background:var(--surface);border:1px solid var(--border2);border-radius:12px;padding:16px 18px;margin-bottom:12px;transition:all 0.2s}
.item-card:hover{border-color:rgba(0,229,255,0.2);transform:translateX(4px)}
.item-title{font-family:'Syne',sans-serif;font-weight:700;font-size:15px;color:#fff;margin-bottom:4px}
.item-sub{font-size:12px;color:var(--muted);margin-top:2px}
.tag{display:inline-block;padding:2px 10px;border-radius:20px;font-family:'Space Mono',monospace;font-size:9px;background:var(--surface2);color:var(--muted);border:1px solid var(--border2);margin-top:8px;text-transform:uppercase}
.tag.fdm{background:rgba(0,229,255,0.06);color:var(--accent);border-color:rgba(0,229,255,0.15)}
.tag.sla{background:rgba(124,58,237,0.06);color:#a78bfa;border-color:rgba(124,58,237,0.15)}
.tag.sls{background:rgba(255,107,53,0.06);color:var(--accent2);border-color:rgba(255,107,53,0.15)}
.tag.msla{background:rgba(0,255,136,0.06);color:var(--accent4);border-color:rgba(0,255,136,0.15)}

.xp-rule{display:flex;align-items:center;justify-content:space-between;padding:5px 0;border-bottom:1px solid var(--border2);font-size:12px;color:var(--muted)}
.xp-rule:last-child{border-bottom:none}
.xp-rule-val{font-family:'Space Mono',monospace;font-size:10px;font-weight:700}
.xp-rule-val.pos{color:var(--accent4)}
.xp-rule-val.neg{color:#ff7777}

/* Modal report */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.75);z-index:9000;align-items:center;justify-content:center;padding:20px}
.modal-overlay.open{display:flex}
.modal{background:var(--surface);border:1px solid rgba(0,229,255,0.15);border-radius:18px;padding:32px;max-width:440px;width:100%;position:relative}
.modal-close{position:absolute;top:14px;right:16px;background:transparent;border:none;color:var(--muted);font-size:20px;cursor:pointer}
.modal-label{font-family:'Space Mono',monospace;font-size:10px;color:var(--accent2);letter-spacing:2px;text-transform:uppercase;margin-bottom:4px}
.modal-title{font-family:'Syne',sans-serif;font-size:18px;font-weight:800;color:#fff;margin-bottom:20px}
.form-label{display:block;font-family:'Space Mono',monospace;font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:7px}
.form-select,.form-textarea{width:100%;background:var(--surface2);border:1px solid var(--border2);border-radius:9px;padding:11px 14px;color:var(--text);font-family:'Inter',sans-serif;font-size:13px;transition:border-color 0.2s;margin-bottom:14px}
.form-select:focus,.form-textarea:focus{outline:none;border-color:var(--accent)}
.form-textarea{resize:vertical;min-height:70px}
.report-status{display:none;padding:10px 14px;border-radius:8px;margin-bottom:12px;font-size:13px}
.report-status.success{background:rgba(0,255,136,0.07);border:1px solid rgba(0,255,136,0.2);color:var(--accent4)}
.report-status.error{background:rgba(255,68,68,0.07);border:1px solid rgba(255,68,68,0.2);color:#ff8888}
.modal-actions{display:flex;gap:8px}
.btn-submit-report{background:rgba(255,68,68,0.1);border:1px solid rgba(255,68,68,0.25);color:#ff7777;border-radius:8px;padding:10px 18px;font-family:'Space Mono',monospace;font-size:10px;font-weight:700;cursor:pointer}
.btn-cancel{background:none;border:1px solid var(--border2);color:var(--muted);border-radius:8px;padding:10px 14px;font-family:'Space Mono',monospace;font-size:10px;cursor:pointer}

@keyframes spinFrame{0%{filter:hue-rotate(0deg)}100%{filter:hue-rotate(360deg)}}
@keyframes pulseFrame{0%,100%{filter:brightness(1)}50%{filter:brightness(1.3)}}
@keyframes spinBorder{from{filter:hue-rotate(0deg)}to{filter:hue-rotate(360deg)}}
@keyframes fireFlicker{0%{box-shadow:0 0 10px #ff4400,0 0 20px #ff6600}100%{box-shadow:0 0 16px #ff2200,0 0 30px #ff8800}}
@keyframes iceShimmer{0%{box-shadow:0 0 8px #a0e4ff}100%{box-shadow:0 0 16px #e0f8ff,0 0 36px rgba(160,228,255,0.7)}}
@keyframes electricPulse{0%{box-shadow:0 0 4px #ffff00}100%{box-shadow:0 0 10px #ffff00,0 0 24px rgba(255,255,0,0.8)}}
@media(max-width:900px){.layout{grid-template-columns:1fr}.stats-inner{gap:0;flex-wrap:wrap}.stat-item{min-width:50%}.hero-body{padding:0 20px}.stats-inner{padding:0 20px}.layout{padding:20px}}

.bc-bar{background:var(--surface);border-bottom:1px solid var(--border2);padding:8px 32px;position:relative;z-index:5}
.bc-inner{display:flex;align-items:center;gap:6px;font-family:'Space Mono',monospace;font-size:10px;color:var(--muted);flex-wrap:wrap}
.bc-link{color:var(--muted);text-decoration:none;transition:color 0.15s}.bc-link:hover{color:var(--accent)}
.bc-sep{opacity:0.4}.bc-current{color:var(--text)}
</style>
</head>
<body>

<?php if (!empty($saveFlash)): ?>
<div style="position:fixed;top:68px;right:24px;z-index:9999;background:<?php echo strpos($saveFlash,'✅')!==false?'rgba(0,255,136,0.1)':'rgba(255,68,68,0.1)'; ?>;border:1px solid <?php echo strpos($saveFlash,'✅')!==false?'rgba(0,255,136,0.3)':'rgba(255,68,68,0.3)'; ?>;color:<?php echo strpos($saveFlash,'✅')!==false?'#00ff88':'#ff8888'; ?>;border-radius:10px;padding:12px 18px;font-family:'Space Mono',monospace;font-size:11px;animation:slideIn 0.3s ease">
    <?php echo sanitize($saveFlash); ?>
</div>
<style>@keyframes slideIn{from{opacity:0;transform:translateX(20px)}to{opacity:1;transform:translateX(0)}}</style>
<?php endif; ?>

<nav class="topbar">
    <a href="/forum/" class="topbar-logo">3D<span>/</span>FÓRUM</a>
    <div style="font-size:12px;color:var(--muted)">/ <span style="color:var(--text)"><?php echo sanitize($user['full_name']); ?></span></div>
    <div class="topbar-right">
        <a href="/" class="topbar-btn">← Manual</a>
        
        <?php if ($currentUser): ?>
            <a href="/forum/mensagens" class="topbar-btn">
                💬<?php if ($unreadMsgs > 0): ?> <span class="notif-badge"><?php echo $unreadMsgs; ?></span><?php endif; ?>
            </a>
            <a href="/forum/perfil?id=<?php echo (int)($_SESSION['user_id'] ?? 0); ?>" class="topbar-av">
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
        <span class="bc-sep">›</span><span class="bc-current">👤 Perfil: <?php echo sanitize($user['username']); ?></span>
    </div>
</div>

<!-- Banner -->
<div class="hero-banner" <?php if($bannerUrl): ?>style="background:url('<?php echo htmlspecialchars($bannerUrl); ?>') center/cover no-repeat"<?php endif; ?>>
    <?php if (!$bannerUrl): ?>
    <div class="hero-banner-grid"></div>
    <div class="hero-banner-glow"></div>
    <div class="hero-banner-glow2"></div>
    <?php endif; ?>
</div>

<!-- Perfil -->
<div class="hero-body">
    <div style="display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-top:-56px;padding-bottom:0">
        <div class="hero-av-wrap">
            <div class="hero-av">
                <?php if (!empty($user['avatar_url'])): ?><img src="<?php echo sanitize(avPath($user['avatar_url'])); ?>" alt="" onerror="this.style.display='none'; this.parentElement.textContent='<?php echo sanitize($initials); ?>'"><?php else: echo sanitize($initials); endif; ?>
            </div>
            <?php if ($frameCSS): ?><style>.hero-av{<?php echo htmlspecialchars($frameCSS); ?>}</style><?php endif; ?>
        </div>
        <div class="hero-actions" style="margin-bottom:8px">
            <?php if ($currentUser && (int)$currentUser['id'] !== $targetId): ?>
            <a href="/forum/mensagens?user=<?php echo $targetId; ?>" class="btn-msg">💬 Enviar Mensagem</a>
            <?php endif; ?>
            <?php if ($currentUser && (int)$currentUser['id'] === $targetId): ?>
            <a href="/forum/personalizacao" class="btn-msg" style="background:rgba(255,204,0,0.1);border:1px solid rgba(255,204,0,0.3);color:#ffcc00">🎨 Personalizar</a>
            <?php endif; ?>
            <button class="btn-report" onclick="document.getElementById('reportModal').classList.add('open')">🚨 Reportar</button>
        </div>
    </div>

    <div style="padding:14px 0 20px">
        <div style="display:flex;align-items:center;gap:15px;margin-bottom:4px">
            <div class="hero-name"><?php echo sanitize($user['full_name']); ?></div>
            <?php if (!empty($badgeData)): ?>
            <div class="top-badges-row" style="display:flex;gap:6px">
                <?php foreach($badgeData as $badge): ?>
                    <div class="badge-mini" title="<?php echo sanitize($badge['name']); ?>" style="width:24px;height:24px;background:var(--surface2);border:1px solid var(--border);border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:12px;cursor:help">
                        <?php if($badge['category'] === 'badge' || $badge['category'] === 'medal'): ?>
                            <?php echo htmlspecialchars($badge['css_value']); ?>
                        <?php elseif($badge['category'] === 'frame'): ?>
                            <div style="width:14px;height:14px;border-radius:50%;<?php echo $badge['css_value']; ?>"></div>
                        <?php elseif($badge['category'] === 'accent'): ?>
                            <div style="width:12px;height:12px;border-radius:50%;background:<?php echo $badge['css_value']; ?>"></div>
                        <?php else: ?>
                            🏅
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="hero-username">@<?php echo sanitize($user['username']); ?></div>
        <?php if (!empty($user['bio'])): ?><div class="hero-bio"><?php echo nl2br(sanitize($user['bio'])); ?></div><?php endif; ?>
        <div class="hero-meta">
            <?php if (!empty($user['location'])): ?><span class="hero-meta-item">📍 <?php echo sanitize($user['location']); ?></span><?php endif; ?>
            <span class="hero-meta-item">📅 Membro desde <?php echo date('M Y', strtotime($user['created_at'])); ?></span>
            <?php if (!empty($user['experience_level'])): ?>
            <?php $lvlMap=array('iniciante'=>'🎓 Iniciante','intermedio'=>'🔧 Intermédio','avancado'=>'🔬 Avançado','profissional'=>'🏆 Profissional'); ?>
            <span class="hero-meta-item"><?php echo $lvlMap[$user['experience_level']] ?? '🎓 Iniciante'; ?></span>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Stats bar -->
<div class="stats-bar">
    <div class="stats-inner">
        <div class="stat-item">
            <div class="stat-num"><?php echo $stats['posts']; ?></div>
            <div class="stat-lbl">Posts</div>
        </div>
        <div class="stat-item">
            <div class="stat-num"><?php echo $stats['replies']; ?></div>
            <div class="stat-lbl">Respostas</div>
        </div>
        <div class="stat-item">
            <div class="stat-num <?php echo $stats['total_karma']>0?'karma-pos':($stats['total_karma']<0?'karma-neg':''); ?>"><?php echo ($stats['total_karma']>0?'+':'').$stats['total_karma']; ?></div>
            <div class="stat-lbl">Karma Total</div>
        </div>
        <div class="stat-item">
            <div class="stat-num"><?php echo $stats['communities']; ?></div>
            <div class="stat-lbl">Comunidades</div>
        </div>
        <div class="stat-item">
            <div class="stat-num"><?php echo $stats['owned']; ?></div>
            <div class="stat-lbl">Criadas</div>
        </div>
        <div class="stat-item">
            <div class="stat-num" style="color:#ffcc00">🪙 <?php echo number_format($customConfig['coins'] ?? 0); ?></div>
            <div class="stat-lbl">Moedas</div>
        </div>
    </div>
</div>

<!-- Layout principal -->
<div class="layout">
<main>

    <div class="tabs-bar">
        <button class="tab-btn active" onclick="switchTab('atividade', this)">Atividade</button>
        <button class="tab-btn" onclick="switchTab('equipamento', this)">Equipamento</button>
        <button class="tab-btn" onclick="switchTab('comunidades', this)">Comunidades</button>
    </div>

    <!-- TAB: ATIVIDADE -->
    <div class="tab-panel active" id="tab-atividade">
        <!-- Posts recentes -->
        <div style="margin-bottom:28px">
            <div class="section-title">📝 Posts publicados <span><?php echo count($recentPosts); ?></span></div>
            <?php if (empty($recentPosts)): ?>
            <div class="empty-state"><div class="empty-state-icon">📝</div><p>Ainda sem posts publicados.</p></div>
            <?php else: ?>
            <?php foreach ($recentPosts as $p):
                $vs = (int)$p['vote_score'];
                $vCls = $vs>0?'pos':($vs<0?'neg':'');
            ?>
            <div class="post-card">
                <div class="post-vote <?php echo $vCls; ?>"><?php echo ($vs>0?'+':'').$vs; ?></div>
                <div class="post-content">
                    <div class="post-comm">
                        <span><?php echo $p['comm_icon']; ?></span>
                        <a href="/forum/comunidade?slug=<?php echo urlencode($p['comm_slug']); ?>" style="color:var(--muted);text-decoration:none;transition:color 0.2s" onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='var(--muted)'"><?php echo sanitize($p['comm_name']); ?></a>
                    </div>
                    <a href="/forum/topico?id=<?php echo $p['id']; ?>" class="post-title-link"><?php echo sanitize($p['title']); ?></a>
                    <div class="post-footer-row">
                        <?php if (!empty($p['flair'])): ?>
                        <span class="flair-mini flair-<?php echo $p['flair']; ?>"><?php
                            $fi=array('pergunta'=>'❓','tutorial'=>'📖','projeto'=>'🏗️','discussao_tecnica'=>'🔬','showcase'=>'📸','debate'=>'🔬','humor'=>'📸','ajuda'=>'🆘','noticia'=>'📰');
                            echo $fi[$p['flair']]??$p['flair'];
                        ?></span>
                        <?php endif; ?>
                        <span class="post-meta-tag">💬 <?php echo (int)$p['reply_count']; ?></span>
                        <span class="post-meta-tag"><?php echo fmtTime($p['created_at']); ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
            <?php if (count($recentPosts) >= 10): ?>
            <div id="loadMorePosts" style="text-align:center;padding:16px">
                <button onclick="loadMore('posts')" style="background:var(--surface);border:1px solid var(--border2);border-radius:9px;padding:10px 22px;color:var(--muted);font-family:'Space Mono',monospace;font-size:10px;cursor:pointer;transition:all 0.2s" onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='var(--muted)'">Carregar mais posts ↓</button>
            </div>
            <?php endif; ?>
        </div>

        <!-- Respostas recentes -->
        <div>
            <div class="section-title">💬 Respostas dadas <span><?php echo count($recentReplies); ?></span></div>
            <?php if (empty($recentReplies)): ?>
            <div class="empty-state"><div class="empty-state-icon">💬</div><p>Ainda sem respostas.</p></div>
            <?php else: ?>
            <?php foreach ($recentReplies as $r):
                $vs = (int)$r['vote_score'];
                $vCls = $vs>0?'karma-pos':($vs<0?'karma-neg':'');
            ?>
            <div class="reply-card">
                <a href="/forum/topico?id=<?php echo $r['post_id']; ?>" class="reply-post-link">
                    ↩ Em: <strong style="color:var(--text)"><?php echo sanitize(mb_substr($r['post_title'],0,60)).(mb_strlen($r['post_title'])>60?'…':''); ?></strong>
                    <span style="color:var(--border2)">·</span> <?php echo sanitize($r['comm_name']); ?>
                </a>
                <div class="reply-text"><?php echo nl2br(sanitize(mb_substr($r['content'],0,200))).(mb_strlen($r['content'])>200?'…':''); ?></div>
                <div class="reply-footer">
                    <span class="post-meta-tag <?php echo $vCls; ?>" style="font-family:'Space Mono',monospace;font-size:10px"><?php echo ($vs>0?'+':'').$vs; ?> votos</span>
                    <span class="post-meta-tag"><?php echo fmtTime($r['created_at']); ?></span>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- TAB: EQUIPAMENTO -->
    <div class="tab-panel" id="tab-equipamento">
        <div style="margin-bottom:28px">
            <div class="section-title">🖨️ Impressoras <span><?php echo count($printers); ?></span></div>
            <?php if (empty($printers)): ?>
                <div class="empty-state">Nenhuma impressora registada.</div>
            <?php else: ?>
                <?php foreach($printers as $p): ?>
                <div class="item-card">
                    <div class="item-title"><?php echo sanitize($p['brand']); ?> <?php echo sanitize($p['model']); ?></div>
                    <?php if(!empty($p['bed_size'])): ?><div class="item-sub">📐 <?php echo sanitize($p['bed_size']); ?></div><?php endif; ?>
                    <?php if(!empty($p['notes'])): ?><div class="item-sub">💬 <?php echo sanitize($p['notes']); ?></div><?php endif; ?>
                    <span class="tag <?php echo strtolower($p['type']); ?>"><?php echo $p['type']; ?></span>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div style="margin-bottom:28px">
            <div class="section-title">⚙️ Slicers <span><?php echo count($slicers); ?></span></div>
            <?php if (empty($slicers)): ?>
                <div class="empty-state">Nenhum slicer registado.</div>
            <?php else: ?>
                <?php foreach($slicers as $s): ?>
                <div class="item-card">
                    <div class="item-title"><?php echo sanitize($s['name']); ?></div>
                    <?php if(!empty($s['version'])): ?><div class="item-sub">v<?php echo sanitize($s['version']); ?></div><?php endif; ?>
                    <?php if(!empty($s['notes'])): ?><div class="item-sub">💬 <?php echo sanitize($s['notes']); ?></div><?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div>
            <div class="section-title">🧵 Materiais <span><?php echo count($materials); ?></span></div>
            <?php if (empty($materials)): ?>
                <div class="empty-state">Nenhum material registado.</div>
            <?php else: ?>
                <?php foreach($materials as $m): ?>
                <div class="item-card">
                    <div class="item-title"><?php echo sanitize($m['material']); ?></div>
                    <?php if(!empty($m['brand'])): ?><div class="item-sub">🏷️ <?php echo sanitize($m['brand']); ?></div><?php endif; ?>
                    <?php if(!empty($m['notes'])): ?><div class="item-sub">💬 <?php echo sanitize($m['notes']); ?></div><?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- TAB: COMUNIDADES (Mobile Friendly) -->
    <div class="tab-panel" id="tab-comunidades">
        <div class="section-title">🏘️ Comunidades Participantes</div>
        <div id="comunidades-tab-content">
            <!-- Injetado via JS ou carregado aqui -->
        </div>
    </div>

</main>

<!-- Sidebar -->
<aside>
    <!-- Comunidades com XP -->
    <div id="sidebar-comms" style="margin-bottom:20px">
        <div class="section-title">🏘️ Comunidades <span><?php echo count($communities); ?></span></div>
        <?php if (empty($communities)): ?>
        <div class="empty-state" style="padding:20px"><div class="empty-state-icon">🏘️</div><p style="font-size:12px">Ainda sem comunidades.</p></div>
        <?php else: ?>
        <?php foreach ($communities as $comm):
            $xp    = (int)$comm['xp'];
            $lvl   = xpLevel($xp);
            $next  = xpToNext($xp);
            $prev  = array(0,20,50,100,200);
            $tiers = array(20,50,100,200,500);
            $prevTier = 0;
            foreach ($tiers as $i => $t) { if ($xp < $t) { $prevTier = $prev[$i]; break; } }
            $pct = $next ? min(100, round(($xp - $prevTier) / ($next - $prevTier) * 100)) : 100;
        ?>
        <a href="/forum/comunidade?slug=<?php echo urlencode($comm['slug']); ?>" class="comm-xp-card">
            <div class="comm-xp-top">
                <div class="comm-xp-icon" style="background:linear-gradient(135deg,<?php echo htmlspecialchars($comm['banner_color']); ?>33,<?php echo htmlspecialchars($comm['banner_color']); ?>11)"><?php echo $comm['icon']; ?></div>
                <div style="flex:1;min-width:0">
                    <div class="comm-xp-name"><?php echo sanitize($comm['name']); ?></div>
                    <div style="font-family:'Space Mono',monospace;font-size:9px;color:var(--muted)"><?php echo number_format($comm['member_count']); ?> membros</div>
                </div>
                <span class="comm-xp-role role-<?php echo $comm['role']; ?>"><?php echo strtoupper($comm['role']); ?></span>
            </div>
            <div class="xp-bar-wrap">
                <div class="xp-level-row">
                    <span class="xp-level-badge" style="background:<?php echo $lvl[3]; ?>;color:<?php echo $lvl[2]; ?>;border:1px solid <?php echo $lvl[2]; ?>44"><?php echo $lvl[1]; ?> <?php echo $lvl[0]; ?></span>
                    <span class="xp-amount"><?php echo $xp; ?> XP</span>
                </div>
                <div class="xp-bar">
                    <div class="xp-fill" style="width:<?php echo $pct; ?>%"></div>
                </div>
                <?php if ($next): ?>
                <div class="xp-next"><?php echo $next - $xp; ?> XP para <?php echo xpLevel($next)[0]; ?></div>
                <?php else: ?>
                <div class="xp-next">Nível máximo atingido 🏆</div>
                <?php endif; ?>
            </div>
        </a>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Conquistas / Inventário -->
    <?php if (!empty($inventoryBadges)): ?>
    <div style="background:var(--surface);border:1px solid var(--border2);border-radius:12px;padding:14px 16px;margin-top:14px">
        <div class="xp-legend-title">🏆 Conquistas e Itens</div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(40px, 1fr));gap:8px;margin-top:10px">
            <?php foreach($inventoryBadges as $ib): ?>
                <div class="inventory-badge-box" title="<?php echo sanitize($ib['name']); ?>" style="aspect-ratio:1;background:var(--surface2);border:1px solid var(--border2);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:18px;cursor:help;transition:all 0.2s" onmouseover="this.style.borderColor='var(--accent)';this.style.transform='scale(1.1)'" onmouseout="this.style.borderColor='var(--border2)';this.style.transform='scale(1)'">
                    <?php if($ib['category'] === 'accent'): ?>
                        <div style="width:16px;height:16px;border-radius:50%;background:<?php echo $ib['css_value']; ?>;box-shadow:0 0 10px <?php echo $ib['css_value']; ?>66"></div>
                    <?php elseif($ib['category'] === 'badge' || $ib['category'] === 'medal'): ?>
                        <?php echo htmlspecialchars($ib['css_value']); ?>
                    <?php else: ?>
                        🏅
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Legenda XP -->
    <div class="xp-legend">
        <div class="xp-legend-title">⚡ Como ganhar XP</div>
        <div class="xp-rule"><span>📝 Publicar um post</span><span class="xp-rule-val pos">+15 XP</span></div>
        <div class="xp-rule"><span>💬 Dar uma resposta</span><span class="xp-rule-val pos">+5 XP</span></div>
        <div class="xp-rule"><span>👍 Voto positivo recebido</span><span class="xp-rule-val pos">+3 XP</span></div>
        <div class="xp-rule"><span>👎 Voto negativo recebido</span><span class="xp-rule-val neg">-2 XP</span></div>
        <div class="xp-rule"><span>⭐ Voto em resposta</span><span class="xp-rule-val pos">+1 XP</span></div>
        <div style="margin-top:12px;padding-top:10px;border-top:1px solid var(--border2)">
            <?php foreach(array(array('🌱','Novo',0),array('✅','Membro',20),array('🔥','Ativo',50),array('⭐','Veterano',100),array('💎','Especialista',200),array('🏆','Lendário',500)) as $tier): ?>
            <div style="display:flex;align-items:center;justify-content:space-between;padding:3px 0;font-size:11px;color:var(--muted)">
                <span><?php echo $tier[0]; ?> <?php echo $tier[1]; ?></span>
                <span style="font-family:'Space Mono',monospace;font-size:9px"><?php echo $tier[2]; ?>+ XP</span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</aside>
</div>

<!-- Modal report -->
<div class="modal-overlay" id="reportModal">
    <div class="modal">
        <button class="modal-close" onclick="document.getElementById('reportModal').classList.remove('open')">✕</button>
        <div class="modal-label">Reportar</div>
        <div class="modal-title">Reportar <?php echo sanitize($user['full_name']); ?></div>
        <div id="reportStatus" class="report-status"></div>
        <label class="form-label">Motivo *</label>
        <select id="reportReason" class="form-select">
            <option value="">Seleciona um motivo...</option>
            <option value="conteudo_obsceno">Conteúdo obsceno</option>
            <option value="linguagem_ofensiva">Linguagem ofensiva</option>
            <option value="spam">Spam</option>
            <option value="informacao_falsa">Informação falsa</option>
            <option value="outro">Outro</option>
        </select>
        <label class="form-label">Descrição (opcional)</label>
        <textarea id="reportDescription" class="form-textarea" placeholder="Descreve o que aconteceu..."></textarea>
        <div class="modal-actions">
            <button class="btn-submit-report" onclick="submitReport()">🚨 ENVIAR</button>
            <button class="btn-cancel" onclick="document.getElementById('reportModal').classList.remove('open')">Cancelar</button>
        </div>
    </div>
</div>

<script>
// TABS
function switchTab(tab, btn) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + tab).classList.add('active');
    if (btn) btn.classList.add('active');

    // Se for a tab de comunidades, clonar o conteúdo do sidebar se estiver vazio
    if (tab === 'comunidades') {
        const container = document.getElementById('comunidades-tab-content');
        if (container && container.innerHTML.trim() === '') {
            const sidebarComms = document.getElementById('sidebar-comms');
            if (sidebarComms) {
                container.innerHTML = sidebarComms.innerHTML;
            }
        }
    }
}

// Preview do banner
function previewBanner(url) {
    var wrap = document.getElementById('bannerPreviewWrap');
    var img  = document.getElementById('bannerPreviewImg');
    if (!wrap || !img) return;
    if (url && url.match(/^https?:\/\//)) {
        img.src = url;
        img.onload  = function(){ wrap.style.display = 'block'; };
        img.onerror = function(){ wrap.style.display = 'none'; };
    } else {
        wrap.style.display = 'none';
    }
}
function clearBanner() {
    var inp = document.getElementById('bannerInput');
    if (inp) inp.value = '';
    var wrap = document.getElementById('bannerPreviewWrap');
    if (wrap) wrap.style.display = 'none';
}

// Highlight visual ao selecionar radio
document.addEventListener('change', function(e) {
    if (e.target.name === 'frame_key') {
        document.querySelectorAll('.frame-radio').forEach(function(r) {
            var av = r.nextElementSibling;
            if (av) av.style.outlineColor = r.checked ? 'white' : 'transparent';
        });
    }
});

async function submitReport() {
    var reason = document.getElementById('reportReason').value;
    var desc   = document.getElementById('reportDescription').value;
    if (!reason) { showStatus('error','⚠️ Seleciona um motivo.'); return; }
    try {
        var res  = await fetch('../api/reports.php',{method:'POST',headers:{'Content-Type':'application/json'},
            body:JSON.stringify({action:'report_user',csrf_token:'<?php echo $csrf; ?>',reported_id:<?php echo $targetId; ?>,reason:reason,description:desc})});
        var data = await res.json();
        if (data.success) { showStatus('success','✅ '+data.message); setTimeout(function(){document.getElementById('reportModal').classList.remove('open');},2000); }
        else showStatus('error','⚠️ '+(data.error||'Erro.'));
    } catch(e) { showStatus('error','⚠️ Erro de rede.'); }
}
function showStatus(type,msg){var el=document.getElementById('reportStatus');el.className='report-status '+type;el.textContent=msg;el.style.display='block';}
document.getElementById('reportModal').addEventListener('click',function(e){if(e.target===this)this.classList.remove('open');});
</script>
</body>
</html>