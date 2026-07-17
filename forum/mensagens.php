<?php
/**
 * forum/mensagens.php — Mensagens privadas
 */
require_once __DIR__ . '/../includes/functions.php';

// ── AJAX: pesquisa de utilizadores ───────────────────────────
if (isset($_GET['ajax_search'])) {
    header('Content-Type: application/json; charset=utf-8');
    if (!isLoggedIn()) { echo '[]'; exit; }
    $q    = trim($_GET['q'] ?? '');
    $uid  = (int)$_SESSION['user_id'];
    $db   = getDB();
    if (mb_strlen($q) < 2) { echo '[]'; exit; }
    $like = '%' . $q . '%';
    try {
        $sr = $db->prepare("
            SELECT id, full_name, username, avatar_url
            FROM users
            WHERE is_active = 1
              AND id != ?
              AND (username LIKE ? OR full_name LIKE ?)
            ORDER BY full_name ASC
            LIMIT 10
        ");
        $sr->execute(array($uid, $like, $like));
        $results = $sr->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($results);
    } catch(Exception $e) {
        echo '[]';
    }
    exit;
}

if (!isLoggedIn()) { header('Location: /login'); exit; }
$currentUser = getCurrentUser();
$uid = (int)$currentUser['id'];
$db  = getDB();

// Garantir tabela
try { $db->exec("CREATE TABLE IF NOT EXISTS private_messages (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    sender_id   INT NOT NULL,
    receiver_id INT NOT NULL,
    content     TEXT NOT NULL,
    read_at     TIMESTAMP NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id)   REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_sender   (sender_id),
    INDEX idx_receiver (receiver_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"); } catch(Exception $e){}

// Conversa aberta via GET
$openUserId = (int)($_GET['user'] ?? 0);
$openUser   = null;
if ($openUserId > 0 && $openUserId !== $uid) {
    $ou = $db->prepare("SELECT id,full_name,username,avatar_url FROM users WHERE id=? AND is_active=1");
    $ou->execute(array($openUserId));
    $openUser = $ou->fetch();
}

// Marcar mensagens como lidas
if ($openUser) {
    $db->prepare("UPDATE private_messages SET read_at=NOW() WHERE sender_id=? AND receiver_id=? AND read_at IS NULL")
       ->execute(array($openUserId, $uid));
}

// ── Lista de conversas ────────────────────────────────────────
// Buscar a última mensagem de cada conversa
$stmt = $db->prepare("
    SELECT
        u.id as other_id,
        u.full_name, u.username, u.avatar_url,
        m.content as last_msg,
        m.created_at as last_time,
        (SELECT COUNT(*) FROM private_messages WHERE sender_id = u.id AND receiver_id = ? AND read_at IS NULL) as unread_count
    FROM users u
    JOIN (
        SELECT
            CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END as other_id,
            MAX(id) as max_id
        FROM private_messages
        WHERE sender_id = ? OR receiver_id = ?
        GROUP BY other_id
    ) last_msgs ON u.id = last_msgs.other_id
    JOIN private_messages m ON m.id = last_msgs.max_id
    ORDER BY last_time DESC
    LIMIT 50
");
$stmt->execute([$uid, $uid, $uid, $uid]);
$convList = $stmt->fetchAll();

// ── Mensagens da conversa aberta ─────────────────────────────
$messages = array();
if ($openUser) {
    $stmt = $db->prepare("
        SELECT pm.*, 
               s.full_name as sender_name, s.username as sender_username, s.avatar_url as sender_avatar,
               r.full_name as receiver_name
        FROM private_messages pm
        JOIN users s ON s.id = pm.sender_id
        JOIN users r ON r.id = pm.receiver_id
        WHERE (pm.sender_id = ? AND pm.receiver_id = ?)
           OR (pm.sender_id = ? AND pm.receiver_id = ?)
        ORDER BY pm.created_at ASC
        LIMIT 200
    ");
    $stmt->execute([$uid, $openUser['id'], $openUser['id'], $uid]);
    $messages = $stmt->fetchAll();
}

// Mensagens não lidas total
$totalUnread = 0;
$ur = $db->prepare("SELECT COUNT(*) FROM private_messages WHERE receiver_id=? AND read_at IS NULL");
$ur->execute(array($uid));
$totalUnread = (int)$ur->fetchColumn();

// Pesquisa de utilizadores para nova conversa
$searchResults = array();
$searchQ = trim($_GET['search'] ?? '');
if ($searchQ && mb_strlen($searchQ) >= 2) {
    $like = '%' . $searchQ . '%';
    $sr = $db->prepare("SELECT id,full_name,username,avatar_url FROM users WHERE is_active=1 AND id!=? AND (username LIKE ? OR full_name LIKE ?) LIMIT 8");
    $sr->execute(array($uid, $like, $like));
    $searchResults = $sr->fetchAll();
}

$csrf = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
    <link rel="icon" type="image/x-icon"  href="../favicons/favicon-mensagens.ico">
    <link rel="icon" type="image/svg+xml" href="../favicons/favicon-mensagens.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="../favicons/favicon-mensagens-32.png">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mensagens — Fórum 3D</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800;900&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root{--bg:#0a0a0f;--surface:#111118;--surface2:#1a1a26;--surface3:#222235;--accent:#00e5ff;--accent2:#ff6b35;--accent3:#7c3aed;--accent4:#00ff88;--text:#e8e8f0;--muted:#888899;--border:rgba(0,229,255,0.1);--border2:rgba(255,255,255,0.06)}
*{margin:0;padding:0;box-sizing:border-box}
html,body{height:100%}
body{background:var(--bg);color:var(--text);font-family:'Inter',sans-serif;display:flex;flex-direction:column}
body::before{content:'';position:fixed;inset:0;pointer-events:none;z-index:0;background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.03'/%3E%3C/svg%3E");opacity:0.4}

/* Topbar */
.topbar{flex-shrink:0;position:relative;z-index:100;background:rgba(10,10,15,0.95);border-bottom:1px solid var(--border);padding:0 32px;display:flex;align-items:center;gap:14px;height:58px}
.topbar-logo{font-family:'Space Mono',monospace;font-size:11px;font-weight:700;color:var(--accent);letter-spacing:3px;text-transform:uppercase;text-decoration:none;white-space:nowrap}
.topbar-logo span{color:var(--muted)}
.topbar-right{display:flex;align-items:center;gap:10px;margin-left:auto}
.topbar-btn{background:none;border:1px solid var(--border2);border-radius:8px;padding:7px 14px;color:var(--muted);font-family:'Space Mono',monospace;font-size:10px;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:6px;transition:all 0.2s;white-space:nowrap}
.topbar-btn:hover{border-color:var(--accent);color:var(--accent);background:rgba(0,229,255,0.05)}
.topbar-av{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--accent3),var(--accent));display:flex;align-items:center;justify-content:center;font-family:'Space Mono',monospace;font-size:11px;font-weight:700;color:#000;overflow:hidden;text-decoration:none;flex-shrink:0}
.topbar-av img{width:100%;height:100%;object-fit:cover;border-radius:50%}
.unread-badge{background:var(--accent2);color:#fff;border-radius:100px;font-size:9px;padding:1px 6px;font-family:'Space Mono',monospace;font-weight:700}

/* Layout principal — 3 colunas */
.msg-layout{flex:1;display:grid;grid-template-columns:280px 1fr;min-height:0;position:relative;z-index:1}

/* ── Sidebar de conversas ── */
.conv-sidebar{background:var(--surface);border-right:1px solid var(--border2);display:flex;flex-direction:column;overflow:hidden}
.conv-sidebar-header{padding:16px 18px;border-bottom:1px solid var(--border2);flex-shrink:0}
.conv-sidebar-title{font-family:'Syne',sans-serif;font-size:16px;font-weight:800;color:#fff;margin-bottom:12px;display:flex;align-items:center;gap:8px}
.conv-search-wrap{position:relative}
.conv-search{width:100%;background:var(--surface2);border:1px solid var(--border2);border-radius:8px;padding:8px 12px 8px 32px;color:var(--text);font-family:'Inter',sans-serif;font-size:13px;transition:border-color 0.2s}
.conv-search:focus{outline:none;border-color:var(--accent)}
.conv-search::placeholder{color:var(--muted);opacity:0.6}
.conv-search-icon{position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:13px;pointer-events:none}

.conv-list{flex:1;overflow-y:auto;padding:6px 0}
.conv-list::-webkit-scrollbar{width:4px}
.conv-list::-webkit-scrollbar-track{background:transparent}
.conv-list::-webkit-scrollbar-thumb{background:var(--border2);border-radius:4px}

.conv-item{display:flex;align-items:center;gap:12px;padding:12px 18px;cursor:pointer;transition:background 0.15s;text-decoration:none;border-left:3px solid transparent}
.conv-item:hover{background:var(--surface2)}
.conv-item.active{background:rgba(0,229,255,0.06);border-left-color:var(--accent)}
.conv-av{width:42px;height:42px;border-radius:50%;background:linear-gradient(135deg,var(--accent3),var(--accent));display:flex;align-items:center;justify-content:center;font-family:'Space Mono',monospace;font-size:14px;font-weight:700;color:#000;overflow:hidden;flex-shrink:0;position:relative}
.conv-av img{width:100%;height:100%;object-fit:cover;border-radius:50%}
.conv-av-badge{position:absolute;bottom:0;right:0;width:12px;height:12px;background:var(--accent4);border-radius:50%;border:2px solid var(--surface)}
.conv-info{flex:1;min-width:0}
.conv-name{font-size:13px;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:2px}
.conv-preview{font-size:12px;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.conv-meta{display:flex;flex-direction:column;align-items:flex-end;gap:4px;flex-shrink:0}
.conv-time{font-family:'Space Mono',monospace;font-size:9px;color:var(--muted)}
.conv-unread{background:var(--accent);color:#000;border-radius:100px;font-family:'Space Mono',monospace;font-size:9px;font-weight:700;padding:2px 6px;min-width:18px;text-align:center}

/* Pesquisa de utilizadores */
.search-results{padding:6px 0;border-top:1px solid var(--border2)}
.search-result-item{display:flex;align-items:center;gap:10px;padding:10px 18px;cursor:pointer;transition:background 0.15s;text-decoration:none}
.search-result-item:hover{background:var(--surface2)}
.search-av{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--accent3),var(--accent));display:flex;align-items:center;justify-content:center;font-family:'Space Mono',monospace;font-size:12px;font-weight:700;color:#000;overflow:hidden;flex-shrink:0}
.search-av img{width:100%;height:100%;object-fit:cover;border-radius:50%}
.search-name{font-size:13px;font-weight:600;color:var(--text)}
.search-username{font-family:'Space Mono',monospace;font-size:10px;color:var(--muted)}

/* ── Sugestões ── */
.sugg-section { border-top: 1px solid var(--border2); padding: 14px 0 6px; }
.sugg-title { font-family: 'Space Mono', monospace; font-size: 9px; font-weight: 700; color: var(--muted); letter-spacing: 2px; text-transform: uppercase; padding: 0 18px; margin-bottom: 8px; display: flex; align-items: center; gap: 6px; }
.sugg-item { display: flex; align-items: center; gap: 10px; padding: 9px 18px; transition: background 0.15s; text-decoration: none; }
.sugg-item:hover { background: var(--surface2); }
.sugg-av { width: 34px; height: 34px; border-radius: 50%; background: linear-gradient(135deg, var(--accent3), var(--accent)); display: flex; align-items: center; justify-content: center; font-family: 'Space Mono', monospace; font-size: 11px; font-weight: 700; color: #000; overflow: hidden; flex-shrink: 0; }
.sugg-av img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
.sugg-info { flex: 1; min-width: 0; }
.sugg-name { font-size: 12px; font-weight: 600; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sugg-common { font-family: 'Space Mono', monospace; font-size: 9px; color: var(--muted); margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sugg-msg-btn { background: rgba(0,229,255,0.08); border: 1px solid rgba(0,229,255,0.2); border-radius: 6px; padding: 4px 10px; color: var(--accent); font-family: 'Space Mono', monospace; font-size: 9px; cursor: pointer; text-decoration: none; white-space: nowrap; transition: all 0.15s; flex-shrink: 0; }
.sugg-msg-btn:hover { background: rgba(0,229,255,0.15); }
.no-convs{text-align:center;padding:40px 20px;color:var(--muted)}
.no-convs-icon{font-size:36px;margin-bottom:10px}
.no-convs p{font-size:12px;line-height:1.6}

/* ── Área de chat ── */
.chat-area{display:flex;flex-direction:column;overflow:hidden;background:var(--bg)}

/* Header do chat */
.chat-header{flex-shrink:0;padding:14px 22px;background:var(--surface);border-bottom:1px solid var(--border2);display:flex;align-items:center;gap:14px}
.chat-header-av{width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,var(--accent3),var(--accent));display:flex;align-items:center;justify-content:center;font-family:'Space Mono',monospace;font-size:14px;font-weight:700;color:#000;overflow:hidden;flex-shrink:0}
.chat-header-av img{width:100%;height:100%;object-fit:cover;border-radius:50%}
.chat-header-info{flex:1}
.chat-header-name{font-family:'Syne',sans-serif;font-size:16px;font-weight:700;color:#fff}
.chat-header-username{font-family:'Space Mono',monospace;font-size:10px;color:var(--muted)}
.chat-header-actions{display:flex;gap:8px}
.chat-action-btn{background:none;border:1px solid var(--border2);border-radius:7px;padding:6px 12px;color:var(--muted);font-family:'Space Mono',monospace;font-size:10px;cursor:pointer;text-decoration:none;transition:all 0.2s}
.chat-action-btn:hover{border-color:var(--accent);color:var(--accent)}

/* Mensagens */
.messages-container{flex:1;overflow-y:auto;padding:20px 24px;display:flex;flex-direction:column;gap:4px}
.messages-container::-webkit-scrollbar{width:4px}
.messages-container::-webkit-scrollbar-track{background:transparent}
.messages-container::-webkit-scrollbar-thumb{background:var(--border2);border-radius:4px}

/* Agrupamento de mensagens */
.msg-day-divider{text-align:center;margin:16px 0 10px;position:relative}
.msg-day-divider::before{content:'';position:absolute;left:0;right:0;top:50%;height:1px;background:var(--border2)}
.msg-day-divider span{background:var(--bg);padding:0 12px;font-family:'Space Mono',monospace;font-size:10px;color:var(--muted);position:relative;z-index:1}

.msg-group{display:flex;flex-direction:column;gap:2px;margin-bottom:10px}
.msg-group.mine{align-items:flex-end}
.msg-group.theirs{align-items:flex-start}

.msg-group-header{display:flex;align-items:center;gap:8px;margin-bottom:4px;padding:0 4px}
.msg-group.mine .msg-group-header{flex-direction:row-reverse}
.msg-group-av{width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,var(--accent3),var(--accent));display:flex;align-items:center;justify-content:center;font-family:'Space Mono',monospace;font-size:10px;font-weight:700;color:#000;overflow:hidden;flex-shrink:0}
.msg-group-av img{width:100%;height:100%;object-fit:cover;border-radius:50%}
.msg-group-name{font-size:12px;font-weight:600;color:var(--muted)}
.msg-group-time{font-family:'Space Mono',monospace;font-size:10px;color:var(--muted);opacity:0.6}

.msg-bubble{max-width:68%;padding:10px 14px;border-radius:14px;font-size:13px;line-height:1.6;word-break:break-word;white-space:pre-wrap;position:relative}
.msg-group.mine .msg-bubble{background:linear-gradient(135deg,var(--accent),#00b8d9);color:#000;border-radius:14px 4px 14px 14px}
.msg-group.theirs .msg-bubble{background:var(--surface2);color:var(--text);border-radius:4px 14px 14px 14px;border:1px solid var(--border2)}
.msg-bubble:first-of-type{}
.msg-bubble.last.mine{border-radius:14px 4px 4px 14px}
.msg-bubble.last.theirs{border-radius:4px 14px 14px 4px}

/* Input de mensagem */
.chat-input-wrap{flex-shrink:0;padding:14px 22px;background:var(--surface);border-top:1px solid var(--border2)}
.chat-input-inner{display:flex;align-items:flex-end;gap:10px;background:var(--surface2);border:1px solid var(--border2);border-radius:14px;padding:10px 14px;transition:border-color 0.2s}
.chat-input-inner:focus-within{border-color:var(--accent)}
.chat-textarea{flex:1;background:none;border:none;color:var(--text);font-family:'Inter',sans-serif;font-size:13px;resize:none;min-height:22px;max-height:120px;line-height:1.6;overflow-y:auto}
.chat-textarea:focus{outline:none}
.chat-textarea::placeholder{color:var(--muted);opacity:0.6}
.chat-send-btn{width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,var(--accent),var(--accent3));border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;transition:opacity 0.2s;margin-bottom:1px}
.chat-send-btn:hover{opacity:0.85}
.chat-send-btn:disabled{opacity:0.3;cursor:not-allowed}
.chat-input-hint{font-family:'Space Mono',monospace;font-size:10px;color:var(--muted);margin-top:6px;text-align:right}

/* Empty chat */
.empty-chat{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:40px;text-align:center;color:var(--muted)}
.empty-chat-icon{font-size:56px;margin-bottom:16px;opacity:0.6}
.empty-chat-title{font-family:'Syne',sans-serif;font-size:20px;font-weight:800;color:#fff;margin-bottom:8px}
.empty-chat-sub{font-size:13px;line-height:1.7;max-width:320px}
.empty-chat-hint{margin-top:20px;font-family:'Space Mono',monospace;font-size:10px;color:var(--muted);background:var(--surface2);border:1px solid var(--border2);border-radius:8px;padding:10px 16px}

@media(max-width:700px){
    .msg-layout{grid-template-columns:1fr}
    .conv-sidebar{display:<?php echo $openUser?'none':'flex'; ?>}
    .chat-area{display:<?php echo $openUser?'flex':'none'; ?>}
}
.bc-bar{background:var(--surface);border-bottom:1px solid var(--border2);padding:8px 32px;position:relative;z-index:5}
.bc-inner{display:flex;align-items:center;gap:6px;font-family:'Space Mono',monospace;font-size:10px;color:var(--muted);flex-wrap:wrap}
.bc-link{color:var(--muted);text-decoration:none;transition:color 0.15s}.bc-link:hover{color:var(--accent)}
.bc-sep{opacity:0.4}.bc-current{color:var(--text)}
</style>
</head>
<body>

<nav class="topbar">
    <a href="/forum/" class="topbar-logo">3D<span>/</span>FÓRUM</a>
    <div style="font-family:'Space Mono',monospace;font-size:10px;color:var(--muted)">/ Mensagens</div>
    <div class="topbar-right">
        <a href="/forum/" class="topbar-btn">← Fórum</a>
        <a href="perfil?id=<?php echo (int)($_SESSION['user_id'] ?? 0); ?>" class="topbar-av">
                    <?php $av=$currentUser['avatar_url']??''; if($av): ?><img src="<?php echo sanitize(avPath($av)); ?>" alt="" onerror="this.style.display='none'; this.parentElement.textContent='<?php echo mb_substr($currentUser['full_name'],0,2); ?>'"><?php else: echo mb_substr($currentUser['full_name'],0,2); endif; ?>
        </a>
    </div>
</nav>
<!-- Breadcrumb -->
<div class="bc-bar">
    <div class="bc-inner">
        <a href="/" class="bc-link">📖 Manual</a>
        <span class="bc-sep">›</span>
        <a href="/forum/" class="bc-link">🌐 Fórum</a>
        <span class="bc-sep">›</span><span class="bc-current">✉️ Mensagens</span>
    </div>
</div>

<div class="msg-layout">

    <!-- Sidebar de conversas -->
    <aside class="conv-sidebar">
        <div class="conv-sidebar-header">
            <div class="conv-sidebar-title">
                💬 Mensagens
                <?php if ($totalUnread > 0): ?>
                <span class="unread-badge"><?php echo $totalUnread; ?></span>
                <?php endif; ?>
            </div>
            <form method="GET" action="" class="conv-search-wrap">
                <span class="conv-search-icon">🔍</span>
                <input type="text" name="search" class="conv-search"
                    placeholder="Procurar por nome ou @..."
                    value="<?php echo sanitize($searchQ); ?>"
                    id="searchInput"
                    autocomplete="off">
                <?php if ($openUserId): ?><input type="hidden" name="user" value="<?php echo $openUserId; ?>"><?php endif; ?>
            </form>
        </div>

        <!-- Resultados de pesquisa (preenchido via JS) -->
        <div id="searchResultsWrap"></div>

        <!-- Lista de conversas -->
        <div class="conv-list">
            <?php
            // Filtrar conversas existentes pela pesquisa
            $convListFiltered = $convList;
            if ($searchQ) {
                $sq = mb_strtolower($searchQ);
                $convListFiltered = array_filter($convList, function($c) use ($sq) {
                    return mb_strpos(mb_strtolower($c['full_name']), $sq) !== false
                        || mb_strpos(mb_strtolower($c['username']), $sq) !== false;
                });
            }
            ?>
            <?php if (empty($convListFiltered) && !$searchQ): ?>
            <div class="no-convs">
                <div class="no-convs-icon">✉️</div>
                <p>Ainda não tens conversas.<br>Pesquisa um utilizador para começar.</p>
            </div>
            <?php elseif (empty($convListFiltered) && $searchQ && empty($searchResults)): ?>
            <div class="no-convs">
                <div class="no-convs-icon">🔍</div>
                <p>Nenhuma conversa ou utilizador<br>encontrado para "<strong><?php echo sanitize($searchQ); ?></strong>".</p>
            </div>
            <?php else: ?>
            <?php foreach ($convListFiltered as $conv):
                $isActive = $openUser && (int)$conv['other_id'] === (int)$openUser['id'];
                $ci = mb_substr($conv['full_name']??'??',0,2);
                $unread = (int)$conv['unread_count'];
                $preview = mb_substr($conv['last_msg']??'',0,40).(mb_strlen($conv['last_msg']??'')>40?'…':'');
                $timeStr = '';
                if (!empty($conv['last_time'])) {
                    $diff=time()-strtotime($conv['last_time']);
                    if($diff<3600) $timeStr=floor($diff/60).'m';
                    elseif($diff<86400) $timeStr=floor($diff/3600).'h';
                    else $timeStr=date('d/m',strtotime($conv['last_time']));
                }
            ?>
            <a href="mensagens?user=<?php echo $conv['other_id']; ?>" class="conv-item <?php echo $isActive?'active':''; ?>">
                <div class="conv-av">
                    <?php $cav=$conv['avatar_url']??''; if($cav): ?><img src="<?php echo sanitize(avPath($cav)); ?>" alt="" onerror="this.style.display='none'; this.parentElement.textContent='<?php echo sanitize($ci); ?>'"><?php else: echo sanitize($ci); endif; ?>
                </div>
                <div class="conv-info">
                    <div class="conv-name"><?php echo sanitize($conv['full_name']); ?></div>
                    <div class="conv-preview"><?php echo sanitize($preview); ?></div>
                </div>
                <div class="conv-meta">
                    <div class="conv-time"><?php echo $timeStr; ?></div>
                    <?php if ($unread > 0): ?>
                    <div class="conv-unread"><?php echo $unread; ?></div>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Sugestões: pessoas que podes conhecer -->
        <?php if (!empty($suggestions)): ?>
        <div class="sugg-section">
            <div class="sugg-title">👥 Pessoas que podes conhecer</div>
            <?php foreach ($suggestions as $sug):
                $si = mb_substr($sug['full_name'] ?? '??', 0, 2);
                $n  = (int)$sug['common_count'];
                $commonText = $n === 1 ? '1 comunidade em comum' : $n . ' comunidades em comum';
                $commNames  = mb_substr($sug['common_comms'] ?? '', 0, 36) . (mb_strlen($sug['common_comms'] ?? '') > 36 ? '…' : '');
            ?>
            <div class="sugg-item">
                <div class="sugg-av">
                    <?php $sav=$sug['avatar_url']??''; if ($sav): ?><img src="<?php echo sanitize(avPath($sav)); ?>" alt=""><?php else: echo sanitize($si); endif; ?>
                </div>
                <div class="sugg-info">
                    <div class="sugg-name"><?php echo sanitize($sug['full_name']); ?></div>
                    <div class="sugg-common" title="<?php echo sanitize($sug['common_comms'] ?? ''); ?>">
                        <?php echo $commonText; ?><?php if ($commNames): ?> · <?php echo sanitize($commNames); ?><?php endif; ?>
                    </div>
                </div>
                <a href="mensagens?user=<?php echo $sug['id']; ?>" class="sugg-msg-btn">💬 Msg</a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </aside>

    <!-- Área de chat -->
    <div class="chat-area">
        <?php if ($openUser): ?>

        <!-- Header -->
        <div class="chat-header">
            <div class="chat-header-av">
                <?php $oav=$openUser['avatar_url']??''; if($oav): ?><img src="<?php echo sanitize(avPath($oav)); ?>" alt="" onerror="this.style.display='none'; this.parentElement.textContent='<?php echo mb_substr($openUser['full_name'],0,2); ?>'"><?php else: echo mb_substr($openUser['full_name'],0,2); endif; ?>
            </div>
            <div class="chat-header-info">
                <div class="chat-header-name"><?php echo sanitize($openUser['full_name']); ?></div>
                <div class="chat-header-username">@<?php echo sanitize($openUser['username']); ?></div>
            </div>
            <div class="chat-header-actions">
                <a href="perfil?id=<?php echo $openUser['id']; ?>" class="chat-action-btn">👤 Perfil</a>
            </div>
        </div>

        <!-- Mensagens -->
        <div class="messages-container" id="messagesContainer">
            <?php if (empty($messages)): ?>
            <div style="text-align:center;padding:40px;color:var(--muted)">
                <div style="font-size:32px;margin-bottom:10px">👋</div>
                <p style="font-size:13px">Início da conversa com <strong style="color:var(--text)"><?php echo sanitize($openUser['full_name']); ?></strong>.<br>Diz olá!</p>
            </div>
            <?php else:
                // Agrupar mensagens por remetente consecutivo e por dia
                $prevSenderId = null;
                $prevDate = null;
                $groupMsgs = array();
                $groupSender = null;

                function flushGroup($msgs, $sender, $myUid) {
                    if (empty($msgs) || !$sender) return;
                    $isMine = (int)($sender['id'] ?? 0) === $myUid;
                    $side = $isMine ? 'mine' : 'theirs';
                    $initials = mb_substr($sender['full_name']??$sender['name']??'??',0,2);
                    echo '<div class="msg-group ' . $side . '">';
                    echo '<div class="msg-group-header">';
                    echo '<div class="msg-group-av">';
                    if (!empty($sender['avatar'])) echo '<img src="' . htmlspecialchars(avPath($sender['avatar'])) . '" alt="" onerror="this.style.display=\'none\'; this.parentElement.textContent=\'' . htmlspecialchars($initials) . '\'">';
                    else echo htmlspecialchars($initials);
                    echo '</div>';
                    echo '<span class="msg-group-name">' . htmlspecialchars($sender['name'] ?? $sender['full_name'] ?? 'Utilizador') . '</span>';
                    $lastMsg = end($msgs);
                    $diff = time() - strtotime($lastMsg['created_at']);
                    $timeStr = $diff < 3600 ? floor($diff/60).'min' : ($diff < 86400 ? floor($diff/3600).'h' : date('H:i', strtotime($lastMsg['created_at'])));
                    echo '<span class="msg-group-time">' . $timeStr . '</span>';
                    echo '</div>';
                    foreach ($msgs as $i => $m) {
                        $isLast = $i === count($msgs)-1;
                        echo '<div id="msg-' . (int)$m['id'] . '" class="msg-bubble' . ($isLast?' last':'') . ' ' . $side . '">';
                        echo htmlspecialchars($m['content']);
                        echo '</div>';
                    }
                    echo '</div>';
                }

                foreach ($messages as $msg) {
                    $msgDate = date('Y-m-d', strtotime($msg['created_at']));
                    if ($msgDate !== $prevDate) {
                        // Flush grupo anterior
                        if (!empty($groupMsgs)) {
                            flushGroup($groupMsgs, $groupSender, $uid);
                            $groupMsgs = array();
                        }
                        // Divisor de dia
                        $today = date('Y-m-d');
                        $yesterday = date('Y-m-d', strtotime('-1 day'));
                        if ($msgDate === $today) $dayLabel = 'Hoje';
                        elseif ($msgDate === $yesterday) $dayLabel = 'Ontem';
                        else $dayLabel = date('d/m/Y', strtotime($msg['created_at']));
                        echo '<div class="msg-day-divider"><span>' . $dayLabel . '</span></div>';
                        $prevDate = $msgDate;
                    }
                    if ((int)$msg['sender_id'] !== $prevSenderId || $groupSender === null) {
                        if (!empty($groupMsgs)) {
                            flushGroup($groupMsgs, $groupSender, $uid);
                            $groupMsgs = array();
                        }
                        $groupSender = array(
                            'id' => $msg['sender_id'],
                            'name' => $msg['sender_name'],
                            'avatar' => $msg['sender_avatar'] ?? ''
                        );
                        $prevSenderId = (int)$msg['sender_id'];
                    }
                    $groupMsgs[] = $msg;
                }
                if (!empty($groupMsgs)) flushGroup($groupMsgs, $groupSender, $uid);
            ?>
            <?php endif; ?>
        </div>

        <!-- Input -->
        <div class="chat-input-wrap">
            <div class="chat-input-inner">
                <textarea class="chat-textarea" id="msgInput"
                    placeholder="Escreve uma mensagem…"
                    rows="1"
                    maxlength="2000"
                    onkeydown="onMsgKeydown(event)"
                    oninput="autoResize(this)"></textarea>
                <button class="chat-send-btn" id="sendBtn" onclick="sendMessage()" title="Enviar">➤</button>
            </div>
            <div class="chat-input-hint">Enter para enviar · Shift+Enter para nova linha</div>
        </div>

        <?php else: ?>
        <!-- Nenhuma conversa aberta -->
        <div class="empty-chat">
            <div class="empty-chat-icon">💬</div>
            <div class="empty-chat-title">Mensagens privadas</div>
            <div class="empty-chat-sub">Comunica diretamente com outros membros da comunidade de forma privada.</div>
            <div class="empty-chat-hint">🔍 Pesquisa um utilizador à esquerda para começar uma conversa</div>
        </div>
        <?php endif; ?>
    </div>

</div>

<script>
var CSRF      = '<?php echo $csrf; ?>';
var MY_ID     = <?php echo $uid; ?>;
var OTHER_ID  = <?php echo $openUser ? (int)$openUser['id'] : 'null'; ?>;
var pollTimer = null;

// ── Auto-resize textarea ──────────────────────────────────────
function autoResize(el) {
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 120) + 'px';
}

// ── Enviar com Enter ──────────────────────────────────────────
function onMsgKeydown(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
}

// ── Enviar mensagem ───────────────────────────────────────────
async function sendMessage() {
    var input = document.getElementById('msgInput');
    var btn   = document.getElementById('sendBtn');
    var content = input ? input.value.trim() : '';
    if (!content || !OTHER_ID) return;
    btn.disabled = true;
    try {
        var res = await fetch('api/forum.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action:'send_message', csrf_token:CSRF, receiver_id:OTHER_ID, content:content})
        });
        var data = await res.json();
        if (data.success) {
            input.value = '';
            input.style.height = 'auto';
            appendMessage(data.message);
            lastMsgId = Math.max(lastMsgId, parseInt(data.message.id));
            scrollToBottom();
        } else {
            alert('⚠️ ' + (data.error || 'Erro ao enviar.'));
        }
    } catch(e) {
        alert('⚠️ Erro de rede.');
    }
    btn.disabled = false;
    input.focus();
}

// ── Adicionar mensagem ao DOM ─────────────────────────────────
function avPath(url) {
    if (!url) return '';
    if (url.indexOf('http') === 0) return url;
    // Devolver sempre a partir da raiz do domínio
    return '/' + url.replace(/^\/+/, '');
}

function appendMessage(msg) {
    var container = document.getElementById('messagesContainer');
    if (!container || document.getElementById('msg-' + msg.id)) return;

    // Remover empty state se existir
    var empty = container.querySelector('div[style*="text-align:center"]');
    if (empty) empty.remove();

    var isMine = parseInt(msg.sender_id) === MY_ID;
    var side   = isMine ? 'mine' : 'theirs';
    var initials = (msg.sender_name || '??').split(' ').map(function(n){return n[0]||'';}).join('').toUpperCase().slice(0,2);

    // Verificar se o último grupo é do mesmo remetente
    var lastGroup = container.querySelector('.msg-group:last-child');
    if (lastGroup && lastGroup.classList.contains(side)) {
        // Adicionar ao grupo existente
        var bubble = document.createElement('div');
        bubble.id = 'msg-' + msg.id;
        bubble.className = 'msg-bubble ' + side;
        bubble.textContent = msg.content;
        // Remover 'last' do anterior
        var prevLast = lastGroup.querySelector('.msg-bubble.last');
        if (prevLast) prevLast.classList.remove('last');
        bubble.classList.add('last');
        lastGroup.appendChild(bubble);
    } else {
        // Novo grupo
        var group = document.createElement('div');
        group.className = 'msg-group ' + side;

        var header = document.createElement('div');
        header.className = 'msg-group-header';

        var avDiv = document.createElement('div');
        avDiv.className = 'msg-group-av';
        if (msg.sender_avatar) {
            var img = document.createElement('img');
            img.src = avPath(msg.sender_avatar);
            avDiv.appendChild(img);
        } else {
            avDiv.textContent = initials;
        }

        var nameSpan = document.createElement('span');
        nameSpan.className = 'msg-group-name';
        nameSpan.textContent = msg.sender_name || '';

        var timeSpan = document.createElement('span');
        timeSpan.className = 'msg-group-time';
        timeSpan.textContent = 'agora';

        header.appendChild(avDiv);
        header.appendChild(nameSpan);
        header.appendChild(timeSpan);

        var bubble = document.createElement('div');
        bubble.id = 'msg-' + msg.id;
        bubble.className = 'msg-bubble last ' + side;
        bubble.textContent = msg.content;

        group.appendChild(header);
        group.appendChild(bubble);
        container.appendChild(group);
    }
}

// ── Scroll para baixo ─────────────────────────────────────────
function scrollToBottom() {
    var c = document.getElementById('messagesContainer');
    if (c) c.scrollTop = c.scrollHeight;
}

// ── Polling de novas mensagens ────────────────────────────────
var lastMsgId = <?php echo !empty($messages) ? (int)end($messages)['id'] : 0; ?>;

async function pollMessages() {
    if (!OTHER_ID) return;
    try {
        var res = await fetch('api/forum.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action:'poll_messages', csrf_token:CSRF, other_id:OTHER_ID, last_id:lastMsgId})
        });
        var data = await res.json();
        if (data.success && data.messages && data.messages.length > 0) {
            data.messages.forEach(function(msg) {
                appendMessage(msg);
                lastMsgId = Math.max(lastMsgId, parseInt(msg.id));
            });
            scrollToBottom();
        }
    } catch(e) {}
}

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Init
if (OTHER_ID) {
    scrollToBottom();
    pollTimer = setInterval(pollMessages, 4000);
}

// Parar polling ao sair da página
window.addEventListener('beforeunload', function() {
    if (pollTimer) clearInterval(pollTimer);
});

// ── Pesquisa com debounce ─────────────────────────────────────
(function(){
    var input = document.getElementById('searchInput');
    if (!input) return;
    var timer = null;
    input.addEventListener('input', function() {
        clearTimeout(timer);
        var q = input.value.trim();
        if (q.length === 0) {
            // Limpar resultados e mostrar conversas
            var sr = document.getElementById('searchResultsWrap');
            if (sr) sr.innerHTML = '';
            return;
        }
        timer = setTimeout(function() {
            searchUsers(q);
        }, 300);
    });
})();

async function searchUsers(q) {
    if (q.length < 2) return;
    try {
        var res  = await fetch('mensagens.php?ajax_search=1&q=' + encodeURIComponent(q));
        var data = await res.json();
        var wrap = document.getElementById('searchResultsWrap');
        if (!wrap) return;
        if (!data.length) {
            wrap.innerHTML = '<div style="padding:14px 18px;font-size:12px;color:var(--muted)">Nenhum utilizador encontrado com esse nome.</div>';
            return;
        }
        wrap.innerHTML = '<div style="font-family:\'Space Mono\',monospace; font-size:9px; color:var(--accent); letter-spacing:2px; text-transform:uppercase; padding:10px 18px 4px;">Utilizadores Encontrados</div>';
        data.forEach(function(u) {
            var a = document.createElement('a');
            a.href = 'mensagens?user=' + parseInt(u.id);
            a.className = 'search-result-item';

            var avDiv = document.createElement('div');
            avDiv.className = 'search-av';
            if (u.avatar_url) {
                var img = document.createElement('img');
                img.src = avPath(u.avatar_url);
                img.style.width = '100%';
                img.style.height = '100%';
                img.style.objectFit = 'cover';
                img.style.borderRadius = '50%';
                avDiv.appendChild(img);
            } else {
                avDiv.textContent = (u.full_name || '??').substring(0, 2).toUpperCase();
            }

            var infoDiv = document.createElement('div');
            var nameDiv = document.createElement('div');
            nameDiv.className = 'search-name';
            nameDiv.textContent = u.full_name;

            var userDiv = document.createElement('div');
            userDiv.className = 'search-username';
            userDiv.textContent = '@' + u.username;

            infoDiv.appendChild(nameDiv);
            infoDiv.appendChild(userDiv);

            a.appendChild(avDiv);
            a.appendChild(infoDiv);
            wrap.appendChild(a);
        });
    } catch(e) { console.error(e); }
}

</script>
</body>
</html>