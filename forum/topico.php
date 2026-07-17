<?php
/**
 * forum/topico.php — Página de um tópico/post com respostas
 */
require_once __DIR__ . '/../includes/functions.php';

$currentUser = isLoggedIn() ? getCurrentUser() : null;
$db  = getDB();

$postId = (int)($_GET['id'] ?? 0);
if ($postId < 1) { header('Location: index.php'); exit; }

/* Schema migrations moved to maintenance or handled conditionally */
// try { $db->exec("ALTER TABLE forum_posts ADD COLUMN IF NOT EXISTS flair VARCHAR(20) DEFAULT NULL"); } catch(Exception $e){}
// ... (Consider moving these to a central migration file)


// ── Post ──────────────────────────────────────────────────────
try {
    $stmt = $db->prepare("
        SELECT fp.*, fc.name as comm_name, fc.slug as comm_slug, fc.icon as comm_icon,
               fc.banner_color, fc.id as comm_id,
               u.full_name, u.username, u.avatar_url, u.id as author_id
        FROM forum_posts fp
        JOIN forum_communities fc ON fc.id = fp.community_id
        JOIN users u ON u.id = fp.user_id
        WHERE fp.id = ?
    ");
    $stmt->execute(array($postId));
    $post = $stmt->fetch();

    if (!$post || ($post['status'] !== 'approved' && !canModerate($currentUser) && (int)$post['author_id'] !== (int)($currentUser['id']??-1))) {
        http_response_code(404);
        die('
        <!DOCTYPE html>
        <html lang="pt">
        <head>
            <meta charset="UTF-8">
            <title>Post não encontrado — Fórum 3D</title>
            <link href="https://fonts.googleapis.com/css2?family=Syne:wght@800&family=Inter:wght@400&display=swap" rel="stylesheet">
            <style>
                body { background: #0a0a0f; color: #e8e8f0; font-family: "Inter", sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; text-align: center; }
                .container { padding: 40px; background: #111118; border: 1px solid rgba(0, 229, 255, 0.15); border-radius: 20px; box-shadow: 0 20px 50px rgba(0,0,0,0.5); max-width: 400px; }
                h1 { font-family: "Syne", sans-serif; font-size: 42px; margin: 0 0 10px; color: #00e5ff; }
                p { opacity: 0.6; margin-bottom: 30px; }
                .btn { background: #00e5ff; color: #000; padding: 12px 24px; border-radius: 10px; text-decoration: none; font-weight: 700; transition: all 0.2s; }
                .btn:hover { box-shadow: 0 0 20px rgba(0, 229, 255, 0.4); transform: translateY(-2px); }
            </style>
        </head>
        <body>
            <div class="container">
                <h1>404</h1>
                <p>O post que procuras já não existe ou foi removido pela moderação.</p>
                <a href="index.php" class="btn">Voltar ao Fórum</a>
            </div>
        </body>
        </html>
        ');
    }
} catch(Exception $e) {
    die('<p style="color:#888;padding:40px;font-family:monospace">Erro ao carregar post: ' . htmlspecialchars($e->getMessage()) . '</p>');
}

if (!$post) {
    http_response_code(404);
    die('<p style="color:#888;padding:40px;font-family:monospace">Post não encontrado.</p>');
}

$commId      = (int)$post['comm_id'];
$bannerColor = $post['banner_color'] ?: '#00e5ff';

// ── Membro? ───────────────────────────────────────────────────
$isMember = false; $memberRole = null;
if ($currentUser) {
    try {
        $ms = $db->prepare("SELECT role FROM forum_memberships WHERE user_id=? AND community_id=?");
        $ms->execute(array((int)$currentUser['id'], $commId));
        $mr = $ms->fetch();
        if ($mr) { $isMember = true; $memberRole = $mr['role']; }
    } catch(Exception $e){}
}

$isCommMod  = in_array($memberRole, array('owner','admin','moderator'));
$isGlobMod  = $currentUser && in_array($currentUser['role'] ?? '', array('admin','moderator','master'));
$isAuthor   = $currentUser && (int)$post['author_id'] === (int)($currentUser['id'] ?? 0);
$canMod     = $currentUser && ($isGlobMod || $isCommMod || $isAuthor);
$canDelete  = $currentUser && ($isGlobMod || $isCommMod);

// Bloquear acesso apenas a posts explicitamente pendentes, para utilizadores sem permissão
$postStatus = $post['status'] ?? 'approved';
if ($postStatus === 'pending') {
    $canViewPending = $isAuthor || $isCommMod || $isGlobMod;
    if (!$canViewPending) {
        http_response_code(403);
        die('<p style="color:#888;padding:40px;font-family:monospace">Este post ainda não foi aprovado.</p>');
    }
} elseif ($postStatus === 'rejected') {
    // Posts rejeitados só visíveis para o autor e moderadores
    $canViewRejected = $isAuthor || $isCommMod || $isGlobMod;
    if (!$canViewRejected) {
        http_response_code(404);
        die('<p style="color:#888;padding:40px;font-family:monospace">Post não encontrado.</p>');
    }
}

// Labels de conteúdo
$postFlair      = $post['flair'] ?? '';

// Guardar visita recente na sessão
if (!isset($_SESSION['forum_recent'])) $_SESSION['forum_recent'] = array();
$recent = $_SESSION['forum_recent'];
$recent = array_filter($recent, function($r) use ($commId){ return (int)$r['id'] !== $commId; });
array_unshift($recent, array('id'=>$commId,'name'=>$post['comm_name'],'slug'=>$post['comm_slug'],'icon'=>$post['comm_icon']));
$_SESSION['forum_recent'] = array_slice(array_values($recent), 0, 5);

// ── Voto do utilizador no post ────────────────────────────────
$postUserVote = 0;
if ($currentUser) {
    try {
        $pv = $db->prepare("SELECT value FROM forum_post_votes WHERE user_id=? AND post_id=?");
        $pv->execute(array((int)$currentUser['id'], $postId));
        $pvr = $pv->fetch();
        if ($pvr) $postUserVote = (int)$pvr['value'];
    } catch(Exception $e){}
}

// ── Respostas ─────────────────────────────────────────────────
$replies = array();
try {
    $rStmt = $db->prepare("
        SELECT fr.*, u.full_name, u.username, u.avatar_url
        FROM forum_replies fr
        JOIN users u ON u.id = fr.user_id
        WHERE fr.post_id = ?
        ORDER BY fr.created_at ASC
    ");
    $rStmt->execute(array($postId));
    $replies = $rStmt->fetchAll();
} catch(Exception $e){}

// ── Votos nas respostas ───────────────────────────────────────
$replyVotes = array();
if ($currentUser && !empty($replies)) {
    try {
        $rids = implode(',', array_map(function($r){ return (int)$r['id']; }, $replies));
        $rvq  = $db->query("SELECT reply_id, value FROM forum_reply_votes WHERE user_id=" . (int)$currentUser['id'] . " AND reply_id IN ($rids)");
        foreach ($rvq->fetchAll() as $v) $replyVotes[$v['reply_id']] = $v['value'];
    } catch(Exception $e){}
}

// ── Organizar em árvore (Fixados primeiro) ───────────────────
$topReplies = array();
$childMap   = array();
foreach ($replies as $r) {
    if (!$r['parent_id']) {
        $topReplies[] = $r;
    } else {
        $childMap[(int)$r['parent_id']][] = $r;
    }
}
usort($topReplies, function($a, $b) {
    if ($a['is_pinned'] != $b['is_pinned']) return $b['is_pinned'] - $a['is_pinned'];
    return strtotime($a['created_at']) - strtotime($b['created_at']);
});

// ── Comunidades do utilizador (sidebar) ──────────────────────
$myCommunities = array();
$recentComms   = isset($_SESSION['forum_recent']) ? $_SESSION['forum_recent'] : array();
if ($currentUser) {
    try {
        $myc = $db->prepare("
            SELECT fc.id, fc.name, fc.slug, fc.icon, fc.banner_color, fc.member_count
            FROM forum_memberships fm
            JOIN forum_communities fc ON fc.id = fm.community_id
            WHERE fm.user_id = ? AND fc.is_active = 1
            ORDER BY fm.joined_at DESC LIMIT 8
        ");
        $myc->execute(array((int)$currentUser['id']));
        $myCommunities = $myc->fetchAll();
    } catch(Exception $e){}
}

// ── Mensagens não lidas ───────────────────────────────────────
$unreadMsgs = 0;
if ($currentUser) {
    try {
        $um = $db->prepare("SELECT COUNT(*) FROM private_messages WHERE receiver_id=? AND read_at IS NULL");
        $um->execute(array((int)$currentUser['id']));
        $unreadMsgs = (int)$um->fetchColumn();
    } catch(Exception $e){}
}

$csrf = generateCSRFToken();

// ── Helper: flair badge ───────────────────────────────────────
function renderFlairBadge($flair) {
    if (!$flair) return '';
    $map = array(
        'pergunta' => array('❓', 'PERGUNTA', 'flair-pergunta'),
        'tutorial' => array('📖', 'TUTORIAL', 'flair-tutorial'),
        'projeto'  => array('🏗️', 'PROJETO',  'flair-projeto'),
        'ajuda'    => array('🆘', 'AJUDA',    'flair-ajuda'),
        'noticia'  => array('📰', 'NOTÍCIA',  'flair-noticia'),
        'discussao_tecnica' => array('🔬', 'DISCUSSÃO TÉCNICA', 'flair-discussao_tecnica'),
        'showcase'  => array('📸', 'SHOWCASE',  'flair-showcase'),
        'debate'   => array('🔬', 'DISCUSSÃO TÉCNICA',   'flair-discussao_tecnica'),
        'humor'    => array('📸', 'SHOWCASE',    'flair-showcase'),
    );
    if (!isset($map[$flair])) return '';
    $f = $map[$flair];
    return '<span class="flair-badge ' . $f[2] . '">' . $f[0] . ' ' . $f[1] . '</span>';
}

// ── Helper: formatar tempo ────────────────────────────────────
function fmtTime($ts) {
    $diff = time() - strtotime($ts);
    if ($diff < 3600)  return floor($diff / 60) . 'min atrás';
    if ($diff < 86400) return floor($diff / 3600) . 'h atrás';
    return date('d/m/Y', strtotime($ts));
}
function fmtTimeShort($ts) {
    $diff = time() - strtotime($ts);
    if ($diff < 3600)  return floor($diff / 60) . 'min';
    if ($diff < 86400) return floor($diff / 3600) . 'h';
    return date('d/m/Y', strtotime($ts));
}
?><!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="description" content="<?php echo sanitize(mb_substr($post['content'] ?? '', 0, 155)); ?>">
    <link rel="icon" type="image/x-icon"  href="../favicons/favicon-forum.ico">
    <link rel="icon" type="image/svg+xml" href="../favicons/favicon-forum.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="../favicons/favicon-forum-32.png">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo sanitize($post['title']); ?> — Fórum 3D</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800;900&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root{--bg:#0a0a0f;--surface:#111118;--surface2:#1a1a26;--surface3:#222235;--accent:#00e5ff;--accent2:#ff6b35;--accent3:#7c3aed;--accent4:#00ff88;--text:#e8e8f0;--muted:#888899;--border:rgba(0,229,255,0.1);--border2:rgba(255,255,255,0.06);--comm:<?php echo htmlspecialchars($bannerColor); ?>}
*{margin:0;padding:0;box-sizing:border-box}
body{background:var(--bg);color:var(--text);font-family:'Inter',sans-serif;min-height:100vh}
body::before{content:'';position:fixed;inset:0;pointer-events:none;z-index:0;background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.03'/%3E%3C/svg%3E");opacity:0.4}
.topbar{position:sticky;top:0;z-index:100;background:rgba(10,10,15,0.92);backdrop-filter:blur(16px);border-bottom:1px solid var(--border);padding:0 32px;display:flex;align-items:center;gap:16px;height:58px}
.topbar-logo{font-family:'Space Mono',monospace;font-size:11px;font-weight:700;color:var(--accent);letter-spacing:3px;text-transform:uppercase;text-decoration:none;white-space:nowrap}
.topbar-logo span{color:var(--muted)}
.breadcrumb{display:flex;align-items:center;gap:8px;font-size:12px;color:var(--muted);overflow:hidden;flex:1;min-width:0}
.breadcrumb a{color:var(--muted);text-decoration:none;white-space:nowrap;transition:color 0.2s}
.breadcrumb a:hover{color:var(--accent)}
.breadcrumb-sep{color:var(--border2);flex-shrink:0}
.breadcrumb-current{color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.topbar-right{display:flex;align-items:center;gap:10px;margin-left:auto;flex-shrink:0}
.topbar-btn{background:none;border:1px solid var(--border2);border-radius:8px;padding:7px 14px;color:var(--muted);font-family:'Space Mono',monospace;font-size:10px;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:6px;transition:all 0.2s;white-space:nowrap}
.topbar-btn:hover{border-color:var(--accent);color:var(--accent);background:rgba(0,229,255,0.05)}
.topbar-btn.primary{background:var(--accent);color:#000;border-color:transparent;font-weight:700}
.notif-badge{background:var(--accent2);color:#fff;border-radius:100px;font-size:9px;padding:1px 5px;font-family:'Space Mono',monospace;font-weight:700}
.topbar-av{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--accent3),var(--accent));display:flex;align-items:center;justify-content:center;font-family:'Space Mono',monospace;font-size:11px;font-weight:700;color:#000;overflow:hidden;text-decoration:none;flex-shrink:0}
.topbar-av img{width:100%;height:100%;object-fit:cover;border-radius:50%}

.layout{max-width:100%;padding:28px 32px;display:grid;grid-template-columns:1fr 280px;gap:24px;position:relative;z-index:1}

/* Post */
.post-main{background:var(--surface);border:1px solid var(--border2);border-radius:16px;overflow:hidden;margin-bottom:20px}
.post-top{display:flex}
.post-vote-col{display:flex;flex-direction:column;align-items:center;gap:6px;padding:20px 14px;background:rgba(0,0,0,0.18);flex-shrink:0;width:56px}
.vote-btn{background:none;border:none;cursor:pointer;color:var(--muted);font-size:18px;line-height:1;padding:5px;border-radius:6px;transition:all 0.15s;display:flex;align-items:center;justify-content:center;width:34px;height:34px}
.vote-btn:hover{background:var(--surface2)}
.vote-btn.up.active{color:var(--accent2);background:rgba(255,107,53,0.1)}
.vote-btn.down.active{color:#7c9aff;background:rgba(124,154,255,0.1)}
.vote-score{font-family:'Space Mono',monospace;font-size:14px;font-weight:700;color:var(--text);line-height:1;min-width:28px;text-align:center}
.vote-score.pos{color:var(--accent2)}.vote-score.neg{color:#7c9aff}
.post-content-col{flex:1;padding:22px 24px;min-width:0}
.post-community-tag{display:inline-flex;align-items:center;gap:6px;font-family:'Space Mono',monospace;font-size:9px;font-weight:700;color:var(--muted);letter-spacing:1px;text-transform:uppercase;text-decoration:none;margin-bottom:10px;padding:4px 10px;background:var(--surface2);border-radius:6px;border:1px solid var(--border2);transition:all 0.2s}
.post-community-tag:hover{border-color:var(--comm);color:var(--comm)}
.post-title{font-family:'Syne',sans-serif;font-size:22px;font-weight:800;color:#fff;line-height:1.3;margin-bottom:14px;margin-top:8px}
.post-body-text{font-size:14px;line-height:1.8;color:var(--text);white-space:pre-wrap;word-break:break-word;margin-bottom:18px}
.post-footer{display:flex;align-items:center;gap:14px;flex-wrap:wrap;padding-top:14px;border-top:1px solid var(--border2)}
.post-author-row{display:flex;align-items:center;gap:8px;text-decoration:none;transition:opacity 0.2s}
.post-author-row:hover{opacity:0.8}
.author-av{width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,var(--accent3),var(--accent));display:flex;align-items:center;justify-content:center;font-family:'Space Mono',monospace;font-size:10px;font-weight:700;color:#000;overflow:hidden;flex-shrink:0}
.author-av img{width:100%;height:100%;object-fit:cover;border-radius:50%}
.author-name{font-size:13px;font-weight:600;color:var(--text)}
.author-username{font-size:11px;color:var(--muted)}
.post-time-tag{font-family:'Space Mono',monospace;font-size:10px;color:var(--muted)}
.post-action-btn{background:none;border:none;cursor:pointer;color:var(--muted);font-family:'Space Mono',monospace;font-size:10px;padding:5px 10px;border-radius:6px;transition:all 0.15s;display:inline-flex;align-items:center;gap:5px}
.post-action-btn:hover{background:var(--surface2);color:var(--text)}
.post-action-btn.danger:hover{color:#ff4444}

/* Flair */
.flair-badge{display:inline-flex;align-items:center;gap:5px;font-family:'Space Mono',monospace;font-size:10px;font-weight:700;padding:3px 10px;border-radius:20px;letter-spacing:0.5px;text-transform:uppercase}
.flair-pergunta{background:rgba(124,58,237,0.1);color:#a78bfa;border:1px solid rgba(124,58,237,0.3)}
.flair-tutorial{background:rgba(0,229,255,0.1);color:var(--accent);border:1px solid rgba(0,229,255,0.25)}
.flair-projeto{background:rgba(0,255,136,0.08);color:var(--accent4);border:1px solid rgba(0,255,136,0.25)}
.flair-ajuda{background:rgba(255,107,53,0.1);color:var(--accent2);border:1px solid rgba(255,107,53,0.3)}
.flair-noticia{background:rgba(0,229,255,0.08);color:var(--accent);border:1px solid rgba(0,229,255,0.2)}
.flair-discussao_tecnica{background:rgba(124,58,237,0.08);color:#a78bfa;border:1px solid rgba(124,58,237,0.2)}
.flair-debate{background:rgba(124,58,237,0.08);color:#a78bfa;border:1px solid rgba(124,58,237,0.2)}
.flair-showcase{background:rgba(0,229,255,0.08);color:#00e5ff;border:1px solid rgba(0,229,255,0.2)}
.flair-humor{background:rgba(0,229,255,0.08);color:#00e5ff;border:1px solid rgba(0,229,255,0.2)}

/* Spoiler */
.spoiler-content{position:relative}
.spoiler-mask{position:absolute;inset:0;background:rgba(0,0,0,0.85);backdrop-filter:blur(8px);border-radius:8px;display:flex;flex-direction:column;align-items:center;justify-content:center;cursor:pointer;gap:8px;z-index:2}
/* Spoiler oculto por defeito — JS revela se preferência estiver desativada */
.spoiler-content .post-body-text{visibility:hidden}
.spoiler-content.revealed .post-body-text{visibility:visible}
.spoiler-content.revealed .spoiler-mask{display:none}
.spoiler-mask:hover{background:rgba(0,0,0,0.75)}
.spoiler-mask-label{font-family:'Space Mono',monospace;font-size:12px;color:#ffcc00;letter-spacing:2px;text-transform:uppercase}
.spoiler-mask-sub{font-size:11px;color:rgba(255,255,255,0.5)}

/* Locked */
.locked-banner{background:rgba(255,68,68,0.06);border:1px solid rgba(255,68,68,0.2);border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;color:#ff8888;display:flex;align-items:center;gap:8px}

/* Responder */
.reply-form-wrap{background:var(--surface);border:1px solid var(--border2);border-radius:14px;padding:20px;margin-bottom:20px}
.reply-form-title{font-family:'Space Mono',monospace;font-size:10px;color:var(--muted);letter-spacing:2px;text-transform:uppercase;margin-bottom:14px}
.reply-textarea{width:100%;background:var(--surface2);border:1px solid var(--border2);border-radius:10px;padding:14px 16px;color:var(--text);font-family:'Inter',sans-serif;font-size:14px;min-height:100px;resize:vertical;transition:border-color 0.2s;line-height:1.7}
.reply-textarea:focus{outline:none;border-color:var(--accent)}
.reply-textarea::placeholder{color:var(--muted);opacity:0.6}
.reply-form-actions{display:flex;align-items:center;gap:10px;margin-top:10px;flex-wrap:wrap}
.reply-submit-btn{background:linear-gradient(135deg,var(--accent),var(--accent3));border:none;border-radius:9px;padding:10px 22px;color:#000;font-family:'Space Mono',monospace;font-size:11px;font-weight:700;cursor:pointer;letter-spacing:1px;transition:opacity 0.2s}
.reply-submit-btn:hover{opacity:0.85}
.reply-submit-btn:disabled{opacity:0.4;cursor:not-allowed}
.char-count{font-family:'Space Mono',monospace;font-size:10px;color:var(--muted);margin-left:auto}
.reply-status{font-size:13px;color:var(--muted)}

/* Respostas */
.replies-header{font-family:'Space Mono',monospace;font-size:10px;color:var(--muted);letter-spacing:2px;text-transform:uppercase;margin-bottom:14px;display:flex;align-items:center;gap:8px}
.replies-count{background:var(--surface2);border:1px solid var(--border2);border-radius:20px;padding:3px 10px;color:var(--text)}
.reply-card{display:flex;background:var(--surface);border:1px solid var(--border2);border-radius:12px;margin-bottom:10px;overflow:hidden;transition:border-color 0.2s}
.reply-card:hover{border-color:rgba(0,229,255,0.15)}
.reply-vote-col{display:flex;flex-direction:column;align-items:center;gap:4px;padding:14px 10px;background:rgba(0,0,0,0.12);flex-shrink:0;width:46px}
.rv-btn{background:none;border:none;cursor:pointer;color:var(--muted);font-size:14px;padding:3px;border-radius:4px;transition:all 0.15s}
.rv-btn:hover{background:var(--surface2)}
.rv-btn.up.active{color:var(--accent2)}
.rv-btn.down.active{color:#7c9aff}
.rv-score{font-family:'Space Mono',monospace;font-size:11px;font-weight:700;color:var(--text)}
.rv-score.pos{color:var(--accent2)}.rv-score.neg{color:#7c9aff}
.reply-body{flex:1;padding:14px 16px;min-width:0}
.reply-author-row{display:flex;align-items:center;gap:8px;margin-bottom:10px;flex-wrap:wrap}
.reply-av{width:26px;height:26px;border-radius:50%;background:linear-gradient(135deg,var(--accent3),var(--accent));display:flex;align-items:center;justify-content:center;font-family:'Space Mono',monospace;font-size:9px;font-weight:700;color:#000;overflow:hidden;flex-shrink:0}
.reply-av img{width:100%;height:100%;object-fit:cover;border-radius:50%}
.reply-author-name{font-size:13px;font-weight:600;color:var(--text);text-decoration:none;transition:color 0.2s}
.reply-author-name:hover{color:var(--accent)}
.reply-author-user{font-family:'Space Mono',monospace;font-size:10px;color:var(--muted)}
.reply-time{font-family:'Space Mono',monospace;font-size:10px;color:var(--muted);margin-left:auto}
.reply-op-badge{font-family:'Space Mono',monospace;font-size:8px;font-weight:700;background:rgba(0,229,255,0.1);color:var(--accent);border:1px solid rgba(0,229,255,0.2);border-radius:4px;padding:2px 6px}
.reply-pin-badge { font-family: 'Space Mono', monospace; font-size: 8px; font-weight: 700; background: rgba(0,255,136,0.1); color: var(--accent4); padding: 2px 6px; border-radius: 4px; border: 1px solid rgba(0,255,136,0.2); display: flex; align-items: center; gap: 3px; }
.reply-card.pinned { border-left: 2px solid var(--accent4) !important; background: rgba(0,255,136,0.02); }
.reply-text{font-size:13px;line-height:1.7;color:var(--text);word-break:break-word;white-space:pre-wrap;margin-bottom:10px}
.reply-actions{display:flex;gap:4px;flex-wrap:wrap}
.reply-action-btn{background:none;border:none;cursor:pointer;color:var(--muted);font-family:'Space Mono',monospace;font-size:10px;padding:4px 8px;border-radius:6px;transition:all 0.15s}
.reply-action-btn:hover{background:var(--surface2);color:var(--text)}
.sub-replies{margin-top:10px;padding-left:20px;border-left:2px solid var(--border2);display:flex;flex-direction:column;gap:8px}
.sub-reply-form{display:none;margin-top:10px;background:var(--surface2);border-radius:8px;padding:12px}
.sub-reply-form.open{display:block}
.sub-reply-form textarea{width:100%;background:var(--surface);border:1px solid var(--border2);border-radius:7px;padding:10px 12px;color:var(--text);font-family:'Inter',sans-serif;font-size:13px;min-height:64px;resize:vertical;transition:border-color 0.2s}
.sub-reply-form textarea:focus{outline:none;border-color:var(--accent)}
.sub-reply-btns{display:flex;gap:8px;margin-top:8px}
.sub-reply-submit{background:linear-gradient(135deg,var(--accent),var(--accent3));border:none;border-radius:7px;padding:7px 14px;color:#000;font-family:'Space Mono',monospace;font-size:10px;font-weight:700;cursor:pointer}
.sub-reply-cancel{background:none;border:1px solid var(--border2);border-radius:7px;padding:7px 12px;color:var(--muted);font-family:'Space Mono',monospace;font-size:10px;cursor:pointer}

/* Sidebar */
.sidebar-card{background:var(--surface);border:1px solid var(--border2);border-radius:14px;margin-bottom:14px;overflow:hidden}
.sc-header{padding:14px 16px 10px;border-bottom:1px solid var(--border2);display:flex;align-items:center;justify-content:space-between}
.sc-title{font-family:'Space Mono',monospace;font-size:9px;font-weight:700;color:var(--muted);letter-spacing:2px;text-transform:uppercase}
.sc-link{font-family:'Space Mono',monospace;font-size:9px;color:var(--accent);text-decoration:none}
.sc-comm-row{display:flex;align-items:center;gap:10px;padding:10px 16px;border-bottom:1px solid var(--border2);text-decoration:none;transition:background 0.2s}
.sc-comm-row:last-child{border-bottom:none}
.sc-comm-row:hover{background:var(--surface2)}
.sc-comm-icon{width:32px;height:32px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.sc-comm-info{flex:1;min-width:0}
.sc-comm-name{font-size:12px;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sc-comm-meta{font-family:'Space Mono',monospace;font-size:9px;color:var(--muted);margin-top:1px}
.sc-comm-dot{width:6px;height:6px;border-radius:50%;flex-shrink:0}
.comm-about-card{background:linear-gradient(135deg,rgba(0,229,255,0.04),rgba(124,58,237,0.04));border:1px solid rgba(0,229,255,0.12);border-radius:14px;padding:18px;margin-bottom:14px}
.comm-about-icon{font-size:28px;margin-bottom:8px}
.comm-about-name{font-family:'Syne',sans-serif;font-size:15px;font-weight:800;color:#fff;margin-bottom:4px}
.comm-about-slug{font-family:'Space Mono',monospace;font-size:9px;color:var(--muted);margin-bottom:10px}
.comm-about-stat{font-family:'Space Mono',monospace;font-size:10px;color:var(--muted);margin-bottom:8px;display:flex;align-items:center;gap:6px}
.comm-join-btn{display:block;text-align:center;width:100%;border:none;border-radius:9px;padding:10px;font-family:'Space Mono',monospace;font-size:10px;font-weight:700;cursor:pointer;letter-spacing:1px;transition:opacity 0.2s;margin-top:12px}
.comm-join-btn.join{background:linear-gradient(135deg,var(--comm),#7c3aed);color:#000}
.comm-join-btn.leave{background:var(--surface2);color:var(--muted);border:1px solid var(--border2)}
.comm-join-btn:hover{opacity:0.82}
.comm-view-link{display:block;text-align:center;font-family:'Space Mono',monospace;font-size:10px;color:var(--muted);margin-top:8px;text-decoration:none;transition:color 0.2s}
.comm-view-link:hover{color:var(--accent)}

@media(max-width:900px){
    .layout{grid-template-columns:1fr;padding:16px}
    .topbar{padding:10px 16px;height:auto;min-height:58px;flex-wrap:wrap;gap:10px}
    .breadcrumb{order:3;flex-basis:100%;font-size:11px}
    .post-title{font-size:18px}
}
@media(max-width:560px){
    .topbar{padding:10px 12px;gap:8px}
    .topbar-logo{letter-spacing:2px;font-size:10px}
    .topbar-right{gap:8px;margin-left:auto;min-width:0}
    .topbar-btn{padding:7px 10px;font-size:9px}
    .topbar-btn:not(.primary){max-width:92px;overflow:hidden;text-overflow:ellipsis}
    .topbar-av{width:30px;height:30px}
    .post-top{flex-direction:column}
    .post-vote-col{width:100%;flex-direction:row;justify-content:center;padding:12px}
    .post-content-col{padding:18px}
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
.bc-link{color:var(--muted);text-decoration:none;transition:color 0.15s}.bc-link:hover{color:var(--accent)}
.bc-sep{opacity:0.4}.bc-current{color:var(--text)}
</style>
</head>
<body>

<nav class="topbar">
    <a href="/forum/" class="topbar-logo">3D<span>/</span>FÓRUM</a>
    <div class="breadcrumb">
        <span class="breadcrumb-sep">/</span>
        <a href="comunidade?slug=<?php echo urlencode($post['comm_slug']); ?>"><?php echo $post['comm_icon']; ?> <?php echo sanitize($post['comm_name']); ?></a>
        <span class="breadcrumb-sep">/</span>
        <span class="breadcrumb-current"><?php echo sanitize($post['title']); ?></span>
    </div>
    <div class="topbar-right">
        <a href="/" class="topbar-btn">← Manual</a>
        
        <?php if ($currentUser && in_array($currentUser['role']??'',['master','admin','moderator'])): ?><a href="admin" class="topbar-btn" style="color:#ff6b35;border-color:rgba(255,107,53,0.3)">⚔️ Admin</a><?php endif; ?>

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
            <a href="mensagens" class="topbar-btn">
                💬<?php if ($unreadMsgs > 0): ?> <span class="notif-badge"><?php echo $unreadMsgs; ?></span><?php endif; ?>
            </a>
            <a href="perfil?id=<?php echo (int)($_SESSION['user_id'] ?? 0); ?>" class="topbar-av">
                <?php $av = $currentUser['avatar_url'] ?? ''; if ($av): ?><img src="<?php echo sanitize(avPath($av)); ?>" alt=""><?php else: echo mb_substr($currentUser['full_name'], 0, 2); endif; ?>
            </a>
        <?php else: ?>
            <a href="/login?redirect=forum/topico?id=<?php echo (int)($post['id'] ?? 0); ?>" class="topbar-btn primary">Entrar</a>
        <?php endif; ?>
    </div>
</nav>

<!-- Breadcrumb -->
<div class="bc-bar">
    <div class="bc-inner">
        <a href="/" class="bc-link">📖 Manual</a>
        <span class="bc-sep">›</span>
        <a href="/forum/" class="bc-link">🌐 Fórum</a>
        <span class="bc-sep">›</span>
        <a href="comunidade?slug=<?php echo urlencode($post['comm_slug'] ?? ''); ?>" class="bc-link"><?php echo $post['comm_icon'] ?? ''; ?> <?php echo sanitize($post['community_name'] ?? ''); ?></a>
        <span class="bc-sep">›</span>
        <span class="bc-current"><?php echo sanitize(mb_substr($post['title'],0,55)).(mb_strlen($post['title'])>55?'…':''); ?></span>
    </div>
</div>

<div class="layout">
<main>

<!-- Post principal -->
<div class="post-main">
    <div class="post-top">
        <div class="post-vote-col">
            <button class="vote-btn up <?php echo $postUserVote === 1 ? 'active' : ''; ?>" onclick="votePost(<?php echo $postId; ?>, 1, this)">▲</button>
            <div class="vote-score <?php echo $post['vote_score'] > 0 ? 'pos' : ($post['vote_score'] < 0 ? 'neg' : ''); ?>" id="post-score"><?php echo (int)$post['vote_score']; ?></div>
            <button class="vote-btn down <?php echo $postUserVote === -1 ? 'active' : ''; ?>" onclick="votePost(<?php echo $postId; ?>, -1, this)">▼</button>
        </div>
        <div class="post-content-col">
            <a href="comunidade?slug=<?php echo urlencode($post['comm_slug']); ?>" class="post-community-tag">
                <?php echo $post['comm_icon']; ?> <?php echo sanitize($post['comm_name']); ?>
            </a>
            <?php if (!empty($post['flair'])): ?>
                <?php echo renderFlairBadge($post['flair']); ?>
            <?php endif; ?>
            <?php if (!empty($post['is_pinned'])): ?>
                <span style="font-family:'Space Mono',monospace;font-size:9px;background:rgba(0,255,136,0.1);color:var(--accent4);border:1px solid rgba(0,255,136,0.2);border-radius:4px;padding:2px 8px;margin-left:6px">📌 FIXADO</span>
            <?php endif; ?>
            <h1 class="post-title"><?php echo sanitize($post['title']); ?></h1>

            <?php if (!empty($post['content'])): ?>
                <?php if (!empty($post['flair']) && $post['flair'] === 'spoiler'): ?>
                    <div class="spoiler-content" id="spoilerWrap">
                        <div class="spoiler-mask" id="spoilerMask" onclick="revealSpoiler()">
                            <span style="font-size:28px">⚠️</span>
                            <span class="spoiler-mask-label">Contém Spoiler</span>
                            <span class="spoiler-mask-sub">Clica para revelar</span>
                        </div>
                        <div class="post-body-text" style="min-height:60px"><?php echo sanitize($post['content']); ?></div>
                    </div>
                <?php else: ?>
                    <div class="post-body-text"><?php echo sanitize($post['content']); ?></div>
                <?php endif; ?>
            <?php endif; ?>
            <?php if (!empty($post['image_url'])): ?>
            <div style="margin-bottom:18px">
                <img src="<?php echo htmlspecialchars(postImagePath($post['image_url'])); ?>" alt="Imagem do post"
                     style="max-width:100%;max-height:500px;border-radius:12px;border:1px solid var(--border2);object-fit:contain;display:block;cursor:pointer"
                     onclick="this.style.maxHeight=this.style.maxHeight==='none'?'500px':'none'"
                     title="Clica para expandir">
            </div>
            <?php endif; ?>

            <div class="post-footer">
                <a href="perfil?id=<?php echo $post['author_id']; ?>" class="post-author-row">
                    <div class="author-av">
                        <?php if (!empty($post['avatar_url'])): ?><img src="<?php echo sanitize(avPath($post['avatar_url'])); ?>" alt="" onerror="this.style.display='none'; this.parentElement.textContent='<?php echo sanitize(mb_substr($post['full_name'] ?? '??', 0, 2)); ?>'"><?php else: echo mb_substr($post['full_name'] ?? '??', 0, 2); endif; ?>
                    </div>
                    <div>
                        <div class="author-name"><?php echo sanitize($post['full_name']); ?></div>
                        <div class="author-username">@<?php echo sanitize($post['username']); ?></div>
                    </div>
                </a>
                <?php if ($currentUser && (int)$currentUser['id'] !== (int)$post['author_id']): ?>
                <a href="mensagens?user=<?php echo $post['author_id']; ?>" class="post-action-btn" title="Enviar mensagem privada" style="color:var(--accent);border:1px solid rgba(0,229,255,0.2);border-radius:6px">💬 Msg</a>
                <?php endif; ?>
                <span class="post-time-tag"><?php echo fmtTime($post['created_at']); ?></span>
                <span class="post-time-tag">💬 <?php echo (int)$post['reply_count']; ?> respostas</span>
                <?php if ($currentUser && ((int)$currentUser['id'] === (int)$post['author_id'] || $canDelete)): ?>
                    <button class="post-action-btn danger" onclick="deletePost(<?php echo $postId; ?>)">🗑️ Apagar</button>
                <?php endif; ?>
                <?php if ($canMod): ?>
                    <button class="post-action-btn" onclick="togglePin(<?php echo $postId; ?>, <?php echo !empty($post['is_pinned']) ? '1' : '0'; ?>)"><?php echo !empty($post['is_pinned']) ? '📌 Desprender' : '📌 Fixar'; ?></button>
                    <button class="post-action-btn" onclick="toggleLock(<?php echo $postId; ?>, <?php echo !empty($post['is_locked']) ? '1' : '0'; ?>)"><?php echo !empty($post['is_locked']) ? '🔓 Abrir' : '🔒 Fechar'; ?></button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Formulário de resposta -->
<?php if (!empty($post['is_locked'])): ?>
    <div class="locked-banner">🔒 Este tópico está fechado — não é possível responder.</div>
<?php elseif ($currentUser): ?>
    <div class="reply-form-wrap">
        <div class="reply-form-title">ESCREVER RESPOSTA</div>
        <textarea class="reply-textarea" id="replyText" placeholder="Escreve a tua resposta…" maxlength="5000" oninput="document.getElementById('replyChars').textContent=this.value.length"></textarea>
        <div class="reply-form-actions">
            <button class="reply-submit-btn" onclick="submitReply(null)">💬 PUBLICAR RESPOSTA</button>
            <span class="char-count"><span id="replyChars">0</span>/5000</span>
            <span id="replyStatus" class="reply-status"></span>
        </div>
    </div>
<?php else: ?>
    <div style="background:var(--surface);border:1px solid var(--border2);border-radius:14px;padding:22px;text-align:center;margin-bottom:20px">
        <p style="color:var(--muted);margin-bottom:12px;font-size:14px">Entra para participar na discussão.</p>
        <a href="/login?redirect=forum/" style="background:var(--accent);color:#000;padding:10px 22px;border-radius:8px;text-decoration:none;font-family:'Space Mono',monospace;font-size:11px;font-weight:700">🔑 ENTRAR</a>
    </div>
<?php endif; ?>

<!-- Respostas -->
<?php if (!empty($topReplies)): ?>
<div class="replies-header">
    RESPOSTAS <span class="replies-count"><?php echo count($replies); ?></span>
</div>
<div id="repliesContainer">
<?php foreach ($topReplies as $reply):
    $rScore    = (int)$reply['vote_score'];
    $rUserVote = isset($replyVotes[$reply['id']]) ? (int)$replyVotes[$reply['id']] : 0;
    $rScoreCls = $rScore > 0 ? 'pos' : ($rScore < 0 ? 'neg' : '');
    $rInitials = mb_substr($reply['full_name'] ?? '??', 0, 2);
    $isOP      = (int)$reply['user_id'] === (int)$post['author_id'];
    $children  = isset($childMap[(int)$reply['id']]) ? $childMap[(int)$reply['id']] : array();
?>
<div class="reply-card <?php echo !empty($reply['is_pinned']) ? 'pinned' : ''; ?>" id="reply-<?php echo $reply['id']; ?>">
    <div class="reply-vote-col">
        <button class="rv-btn up <?php echo $rUserVote === 1 ? 'active' : ''; ?>" onclick="voteReply(<?php echo $reply['id']; ?>, 1, this)">▲</button>
        <div class="rv-score <?php echo $rScoreCls; ?>" id="rscore-<?php echo $reply['id']; ?>"><?php echo $rScore; ?></div>
        <button class="rv-btn down <?php echo $rUserVote === -1 ? 'active' : ''; ?>" onclick="voteReply(<?php echo $reply['id']; ?>, -1, this)">▼</button>
    </div>
    <div class="reply-body">
        <div class="reply-author-row">
            <a href="perfil?id=<?php echo $reply['user_id']; ?>" style="display:flex;align-items:center;gap:7px;text-decoration:none">
                <div class="reply-av"><?php if (!empty($reply['avatar_url'])): ?><img src="<?php echo sanitize(avPath($reply['avatar_url'])); ?>" alt="" onerror="this.style.display='none'; this.parentElement.textContent='<?php echo sanitize($rInitials); ?>'"><?php else: echo sanitize($rInitials); endif; ?></div>
                <span class="reply-author-name"><?php echo sanitize($reply['full_name']); ?></span>
                <span class="reply-author-user">@<?php echo sanitize($reply['username']); ?></span>
            </a>
            <?php if ($isOP): ?><span class="reply-op-badge">OP</span><?php endif; ?>
            <?php if (!empty($reply['is_pinned'])): ?><span class="reply-pin-badge">📌 FIXADO PELO AUTOR</span><?php endif; ?>
            <span class="reply-time"><?php echo fmtTimeShort($reply['created_at']); ?></span>
        </div>
        <div class="reply-text"><?php echo sanitize($reply['content']); ?></div>
        <div class="reply-actions">
            <?php if (empty($post['is_locked']) && $currentUser): ?>
                <button class="reply-action-btn" onclick="toggleSubReplyForm(<?php echo $reply['id']; ?>)">↩ Responder</button>
            <?php endif; ?>
            <?php
                $canPinReply = $currentUser && ((int)$currentUser['id'] === (int)$post['author_id'] || $canMod);
                if ($canPinReply):
            ?>
                <button class="reply-action-btn" onclick="togglePinReply(<?php echo $reply['id']; ?>, <?php echo !empty($reply['is_pinned']) ? 1 : 0; ?>)" style="color:var(--accent4)"><?php echo !empty($reply['is_pinned']) ? '📍 Desafixar' : '📌 Fixar'; ?></button>
            <?php endif; ?>
            <?php if ($canMod || ($currentUser && (int)$currentUser['id'] === (int)$reply['user_id'])): ?>
                <button class="reply-action-btn" onclick="deleteReply(<?php echo $reply['id']; ?>)" style="color:#ff8888">🗑️</button>
            <?php endif; ?>
        </div>

        <?php if (!empty($children)): ?>
        <div class="sub-replies">
            <?php foreach ($children as $child):
                $cScore    = (int)$child['vote_score'];
                $cUserVote = isset($replyVotes[$child['id']]) ? (int)$replyVotes[$child['id']] : 0;
                $cInitials = mb_substr($child['full_name'] ?? '??', 0, 2);
                $cIsOP     = (int)$child['user_id'] === (int)$post['author_id'];
            ?>
            <div class="reply-card" id="reply-<?php echo $child['id']; ?>" style="margin-bottom:0">
                <div class="reply-vote-col">
                    <button class="rv-btn up <?php echo $cUserVote === 1 ? 'active' : ''; ?>" onclick="voteReply(<?php echo $child['id']; ?>, 1, this)">▲</button>
                    <div class="rv-score <?php echo $cScore > 0 ? 'pos' : ($cScore < 0 ? 'neg' : ''); ?>" id="rscore-<?php echo $child['id']; ?>"><?php echo $cScore; ?></div>
                    <button class="rv-btn down <?php echo $cUserVote === -1 ? 'active' : ''; ?>" onclick="voteReply(<?php echo $child['id']; ?>, -1, this)">▼</button>
                </div>
                <div class="reply-body">
                    <div class="reply-author-row">
                        <a href="perfil?id=<?php echo $child['user_id']; ?>" style="display:flex;align-items:center;gap:7px;text-decoration:none">
                            <div class="reply-av"><?php if (!empty($child['avatar_url'])): ?><img src="<?php echo sanitize(avPath($child['avatar_url'])); ?>" alt="" onerror="this.style.display='none'; this.parentElement.textContent='<?php echo sanitize($cInitials); ?>'"><?php else: echo sanitize($cInitials); endif; ?></div>
                            <span class="reply-author-name"><?php echo sanitize($child['full_name']); ?></span>
                            <span class="reply-author-user">@<?php echo sanitize($child['username']); ?></span>
                        </a>
                        <?php if ($cIsOP): ?><span class="reply-op-badge">OP</span><?php endif; ?>
                        <span class="reply-time"><?php echo fmtTimeShort($child['created_at']); ?></span>
                    </div>
                    <div class="reply-text"><?php echo sanitize($child['content']); ?></div>
                    <?php if ($canMod || ($currentUser && (int)$currentUser['id'] === (int)$child['user_id'])): ?>
                        <div class="reply-actions">
                            <button class="reply-action-btn" onclick="deleteReply(<?php echo $child['id']; ?>)" style="color:#ff8888">🗑️</button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (empty($post['is_locked']) && $currentUser): ?>
        <div class="sub-reply-form" id="srform-<?php echo $reply['id']; ?>">
            <textarea id="srtext-<?php echo $reply['id']; ?>" placeholder="Responder a @<?php echo sanitize($reply['username']); ?>…" maxlength="2000"></textarea>
            <div class="sub-reply-btns">
                <button class="sub-reply-submit" onclick="submitReply(<?php echo $reply['id']; ?>)">Responder</button>
                <button class="sub-reply-cancel" onclick="toggleSubReplyForm(<?php echo $reply['id']; ?>)">Cancelar</button>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
</div>
<?php else: ?>
<div style="text-align:center;padding:40px;color:var(--muted)">
    <div style="font-size:40px;margin-bottom:12px">💬</div>
    <p style="font-size:14px">Ainda não há respostas. Sê o primeiro a responder!</p>
</div>
<?php endif; ?>

</main>

<!-- Sidebar -->
<aside>
    <div class="comm-about-card">
        <div class="comm-about-icon"><?php echo $post['comm_icon']; ?></div>
        <div class="comm-about-name"><?php echo sanitize($post['comm_name']); ?></div>
        <div class="comm-about-slug">c/<?php echo sanitize($post['comm_slug']); ?></div>
        <?php
        $mcStat = 0; $pcStat = 0;
        try {
            $s = $db->prepare("SELECT member_count, post_count FROM forum_communities WHERE id=?");
            $s->execute(array($commId)); $sr = $s->fetch();
            if ($sr) { $mcStat = (int)$sr['member_count']; $pcStat = (int)$sr['post_count']; }
        } catch(Exception $e){}
        ?>
        <div class="comm-about-stat">👥 <?php echo number_format($mcStat); ?> membros</div>
        <div class="comm-about-stat">📝 <?php echo number_format($pcStat); ?> posts</div>
        <?php if ($currentUser): ?>
        <button class="comm-join-btn <?php echo $isMember ? 'leave' : 'join'; ?>" id="joinBtn"
            onclick="toggleJoin(<?php echo $commId; ?>, this)"
            data-joined="<?php echo $isMember ? '1' : '0'; ?>">
            <?php echo $isMember ? '✓ Membro — Sair' : '+ Entrar na comunidade'; ?>
        </button>
        <?php endif; ?>
        <a href="comunidade?slug=<?php echo urlencode($post['comm_slug']); ?>" class="comm-view-link">Ver comunidade →</a>
    </div>

    <?php if (!empty($recentComms) && count($recentComms) > 1): ?>
    <div class="sidebar-card">
        <div class="sc-header"><span class="sc-title">🕐 Visitadas recentemente</span></div>
        <?php foreach ($recentComms as $rc):
            if ((int)$rc['id'] === $commId) continue;
        ?>
        <a href="comunidade?slug=<?php echo urlencode($rc['slug']); ?>" class="sc-comm-row">
            <div class="sc-comm-icon" style="background:rgba(0,229,255,0.08)"><?php echo $rc['icon']; ?></div>
            <div class="sc-comm-info">
                <div class="sc-comm-name"><?php echo sanitize($rc['name']); ?></div>
                <div class="sc-comm-meta">c/<?php echo sanitize($rc['slug']); ?></div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($myCommunities)): ?>
    <div class="sidebar-card">
        <div class="sc-header">
            <span class="sc-title">⭐ As minhas comunidades</span>
            <a href="/forum/" class="sc-link">Ver todas</a>
        </div>
        <?php foreach ($myCommunities as $mc):
            $isActive = (int)$mc['id'] === $commId;
        ?>
        <a href="comunidade?slug=<?php echo urlencode($mc['slug']); ?>" class="sc-comm-row" <?php echo $isActive ? 'style="background:rgba(0,229,255,0.05)"' : ''; ?>>
            <div class="sc-comm-icon" style="background:linear-gradient(135deg,<?php echo htmlspecialchars($mc['banner_color']); ?>22,<?php echo htmlspecialchars($mc['banner_color']); ?>44)"><?php echo $mc['icon']; ?></div>
            <div class="sc-comm-info">
                <div class="sc-comm-name"><?php echo sanitize($mc['name']); ?></div>
                <div class="sc-comm-meta"><?php echo number_format($mc['member_count']); ?> membros</div>
            </div>
            <?php if ($isActive): ?><div class="sc-comm-dot" style="background:var(--accent)"></div><?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>
    <?php elseif ($currentUser): ?>
    <div class="sidebar-card" style="padding:18px;text-align:center">
        <div style="font-size:28px;margin-bottom:8px">🏘️</div>
        <p style="font-size:12px;color:var(--muted);margin-bottom:12px">Ainda não és membro de nenhuma comunidade.</p>
        <a href="/forum/" style="font-family:'Space Mono',monospace;font-size:10px;color:var(--accent);text-decoration:none">Explorar →</a>
    </div>
    <?php endif; ?>
</aside>
</div>

<script>
var CSRF    = '<?php echo $csrf; ?>';
var CUR_UID = <?php echo $currentUser ? (int)$currentUser['id'] : 'null'; ?>;
var POST_ID = <?php echo $postId; ?>;

function revealSpoiler() {
    var wrap = document.getElementById('spoilerWrap');
    var mask = document.getElementById('spoilerMask');
    if (wrap) wrap.classList.add('revealed');
    if (mask) mask.style.display = 'none';
}

async function apiCall(body) {
    var res  = await fetch('api/forum.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body)});
    var text = await res.text();
    try { return JSON.parse(text); } catch(e) { console.error('API response:', text); return {success:false, error:'Resposta inválida'}; }
}

async function votePost(postId, value, btn) {
    if (!CUR_UID) { window.location.href = '../login.php?redirect=forum/index.php'; return; }
    var data = await apiCall({action:'vote_post', csrf_token:CSRF, post_id:postId, value:value});
    if (!data.success) { console.warn(data.error); return; }
    var sc = document.getElementById('post-score');
    if (sc) { sc.textContent = data.score; sc.className = 'vote-score' + (data.score>0?' pos':data.score<0?' neg':''); }
    document.querySelectorAll('.post-vote-col .vote-btn.up').forEach(function(b){ b.classList.toggle('active', data.user_vote===1); });
    document.querySelectorAll('.post-vote-col .vote-btn.down').forEach(function(b){ b.classList.toggle('active', data.user_vote===-1); });
}

async function voteReply(replyId, value, btn) {
    if (!CUR_UID) { window.location.href = '../login.php?redirect=forum/index.php'; return; }
    var data = await apiCall({action:'vote_reply', csrf_token:CSRF, reply_id:replyId, value:value});
    if (!data.success) { console.warn(data.error); return; }
    var sc = document.getElementById('rscore-'+replyId);
    if (sc) { sc.textContent = data.score; sc.className = 'rv-score' + (data.score>0?' pos':data.score<0?' neg':''); }
    var card = document.getElementById('reply-'+replyId);
    if (card) {
        card.querySelector('.rv-btn.up').classList.toggle('active', data.user_vote===1);
        card.querySelector('.rv-btn.down').classList.toggle('active', data.user_vote===-1);
    }
}

async function submitReply(parentId) {
    if (!CUR_UID) { window.location.href = '../login.php?redirect=forum/index.php'; return; }
    var ta      = parentId ? document.getElementById('srtext-'+parentId) : document.getElementById('replyText');
    var status  = document.getElementById('replyStatus');
    var content = ta ? ta.value.trim() : '';
    if (!content) { if(status) status.textContent = '⚠️ Escreve algo primeiro.'; return; }
    var btn = parentId ? ta.closest('.sub-reply-form').querySelector('.sub-reply-submit') : document.querySelector('.reply-submit-btn');
    if (btn) btn.disabled = true;
    if (status) status.textContent = 'A publicar…';
    var data = await apiCall({action:'create_reply', csrf_token:CSRF, post_id:POST_ID, parent_id:parentId||null, content:content});
    if (data.success) {
        window.location.reload();
    } else {
        if (status) status.textContent = '⚠️ ' + (data.error || 'Erro.');
        if (btn) btn.disabled = false;
    }
}

function toggleSubReplyForm(replyId) {
    var f = document.getElementById('srform-'+replyId);
    if (!f) return;
    var open = f.classList.contains('open');
    document.querySelectorAll('.sub-reply-form').forEach(function(x){ x.classList.remove('open'); });
    if (!open) { f.classList.add('open'); var ta = f.querySelector('textarea'); if (ta) setTimeout(function(){ ta.focus(); }, 50); }
}

async function deleteReply(replyId) {
    if (!confirm('Apagar esta resposta?')) return;
    var data = await apiCall({action:'delete_reply', csrf_token:CSRF, reply_id:replyId});
    if (data.success) { var el = document.getElementById('reply-'+replyId); if (el) el.remove(); }
    else alert('⚠️ ' + (data.error || 'Erro.'));
}

async function deletePost(postId) {
    if (!confirm('Apagar este post permanentemente?')) return;
    var data = await apiCall({action:'delete_post', csrf_token:CSRF, post_id:postId});
    if (data.success) window.location.href = 'index.php';
    else alert('⚠️ ' + (data.error || 'Erro.'));
}

async function togglePin(postId, current) {
    var data = await apiCall({action:'toggle_pin', csrf_token:CSRF, post_id:postId, value:current?0:1});
    if (data.success) window.location.reload();
}
async function togglePinReply(replyId, current) {
    var data = await apiCall({action:'toggle_pin_reply', csrf_token:CSRF, reply_id:replyId, value:current?0:1});
    if (data.success) window.location.reload();
    else alert(data.error || 'Erro ao fixar resposta.');
}
async function toggleLock(postId, current) {
    var data = await apiCall({action:'toggle_lock', csrf_token:CSRF, post_id:postId, value:current?0:1});
    if (data.success) window.location.reload();
}

async function toggleJoin(commId, btn) {
    if (!CUR_UID) { window.location.href = '../login.php?redirect=forum/index.php'; return; }
    var isJoined = btn.dataset.joined === '1';
    var data = await apiCall({action: isJoined ? 'leave_community' : 'join_community', csrf_token:CSRF, community_id:commId});
    if (!data.success) { console.warn(data.error); return; }
    btn.dataset.joined = data.joined ? '1' : '0';
    btn.textContent    = data.joined ? '✓ Membro — Sair' : '+ Entrar na comunidade';
    btn.className      = 'comm-join-btn ' + (data.joined ? 'leave' : 'join');
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
    // Spoiler — mostrar/esconder mask e conteúdo
    document.querySelectorAll('.spoiler-content').forEach(function(wrap) {
        if (p.hideSpoiler) {
            // Ocultar conteúdo, mostrar mask
            wrap.classList.remove('revealed');
            var mask = wrap.querySelector('.spoiler-mask');
            if (mask) mask.style.display = 'flex';
        } else {
            // Revelar conteúdo, esconder mask
            wrap.classList.add('revealed');
            var mask = wrap.querySelector('.spoiler-mask');
            if (mask) mask.style.display = 'none';
        }
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
