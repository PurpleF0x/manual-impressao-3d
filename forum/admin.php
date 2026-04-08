<?php
/**
 * forum/admin.php — Painel de Administração
 * Hierarquia: master > admin > moderator > user
 */
require_once __DIR__ . '/../includes/functions.php';
// Helper: path do avatar relativo ao forum/
function avPath($url) {
    if (!$url) return '';
    if (strpos($url,'http')===0) return $url;
    return '../' . ltrim($url, '/');
}
if (!isLoggedIn()) { header('Location: ../login.php?redirect=forum/admin.php'); exit; }
$currentUser = getCurrentUser();
$uid  = (int)$currentUser['id'];
$role = $currentUser['role'] ?? 'user';
$db   = getDB();

// ── Migração: adicionar role 'master' ao ENUM ─────────────────
try { $db->exec("ALTER TABLE users MODIFY COLUMN role ENUM('master','admin','moderator','user') DEFAULT 'user'"); } catch(Exception $e){}

// ── Tabela de logs de auditoria ───────────────────────────────
try { $db->exec("CREATE TABLE IF NOT EXISTS admin_logs (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    actor_id    INT NOT NULL,
    target_id   INT NULL,
    action      VARCHAR(50) NOT NULL,
    detail      TEXT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch(Exception $e){}

// ── Helpers de permissão ──────────────────────────────────────
function rl($r) {
    return array('master'=>4,'admin'=>3,'moderator'=>2,'user'=>1)[$r] ?? 1;
}
function amMaster($u)    { return ($u['role']??'') === 'master'; }
function amAdmin($u)     { return rl($u['role']??'') >= 3; }
function amMod($u)       { return rl($u['role']??'') >= 2; }
function amCanManage($actor, $target) {
    return rl($actor['role']??'') > rl($target['role']??'');
}
function addLog($db, $actorId, $targetId, $action, $detail) {
    try {
        $db->prepare("INSERT INTO admin_logs (actor_id,target_id,action,detail) VALUES (?,?,?,?)")
           ->execute(array($actorId, $targetId, $action, $detail));
    } catch(Exception $e){}
}

// Verificar acesso mínimo: moderador+
if (rl($role) < 2) {
    http_response_code(403);
    die('<div style="font-family:monospace;text-align:center;padding:80px;background:#0a0a0f;color:#888;min-height:100vh"><p style="font-size:48px">🚫</p><h2 style="color:#fff;margin:16px 0">Acesso negado</h2><a href="index.php" style="color:#00e5ff">← Voltar ao fórum</a></div>');
}

$tab    = $_GET['tab'] ?? 'stats';
$search = trim($_GET['q'] ?? '');
$flash  = null;

// ── POST: acções ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? null)) {
    $action   = $_POST['action']    ?? '';
    $targetId = (int)($_POST['target_id'] ?? 0);

    // Buscar target
    $tStmt = $db->prepare("SELECT id, full_name, username, role, is_active FROM users WHERE id=? LIMIT 1");
    $tStmt->execute(array($targetId)); $target = $tStmt->fetch();

    if ($target && amCanManage($currentUser, $target)) {

        if ($action === 'warn') {
            try {
            $reason = trim($_POST['reason'] ?? 'Comportamento inadequado');
            $db->prepare("INSERT INTO user_notices (user_id, type, message, created_by) VALUES (?,?,?,?)")
               ->execute(array($targetId,'warning',$reason,$uid));
            addLog($db,$uid,$targetId,'warn',"Aviso: $reason");
            $flash = array('ok', "⚠️ Aviso enviado a " . $target['full_name']);
            } catch(Exception $e){ $flash = array('err','Erro: '.$e->getMessage()); }

        } elseif ($action === 'suspend') {
            try {
            $days   = max(1,(int)($_POST['days']??1));
            $reason = trim($_POST['reason'] ?? 'Suspensão temporária');
            $until  = date('Y-m-d H:i:s', strtotime("+$days days"));
            try { $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS suspended_until DATETIME NULL"); } catch(Exception $e2){}
            $db->prepare("UPDATE users SET suspended_until=? WHERE id=?")->execute(array($until,$targetId));
            $db->prepare("INSERT INTO user_notices (user_id,type,message,created_by) VALUES (?,?,?,?)")
               ->execute(array($targetId,'suspension',"Suspenso por $days dia(s): $reason",$uid));
            addLog($db,$uid,$targetId,'suspend',"$days dia(s): $reason");
            $flash = array('ok', "🔒 {$target['full_name']} suspenso por $days dia(s)");
            } catch(Exception $e){ $flash = array('err','Erro: '.$e->getMessage()); }

        } elseif ($action === 'ban' && amAdmin($currentUser)) {
            $reason = trim($_POST['reason'] ?? 'Banimento permanente');
            $db->prepare("UPDATE users SET is_active=FALSE WHERE id=?")->execute(array($targetId));
            $db->prepare("INSERT INTO user_notices (user_id,type,message,created_by) VALUES (?,?,?,?)")
               ->execute(array($targetId,'ban',"Banido: $reason",$uid));
            addLog($db,$uid,$targetId,'ban',"Banimento: $reason");
            $flash = array('ok', "🚫 {$target['full_name']} banido");

        } elseif ($action === 'unban' && amAdmin($currentUser)) {
            $db->prepare("UPDATE users SET is_active=TRUE WHERE id=?")->execute(array($targetId));
            addLog($db,$uid,$targetId,'unban','Desbanido');
            $flash = array('ok', "✅ {$target['full_name']} desbanido");

        } elseif ($action === 'change_role' && amAdmin($currentUser)) {
            $newRole = $_POST['new_role'] ?? 'user';
            $allowed = amMaster($currentUser)
                ? array('master','admin','moderator','user')
                : array('moderator','user'); // admins só até moderator
            if (in_array($newRole, $allowed) && amCanManage($currentUser, $target)) {
                $db->prepare("UPDATE users SET role=? WHERE id=?")->execute(array($newRole,$targetId));
                addLog($db,$uid,$targetId,'change_role',"{$target['role']} → $newRole");
                $flash = array('ok', "✅ Cargo de {$target['full_name']} alterado para $newRole");
            } else {
                $flash = array('err', "⚠️ Sem permissão para atribuir este cargo");
            }

        } elseif ($action === 'change_name' && amAdmin($currentUser)) {
            $newName = trim($_POST['new_name'] ?? '');
            if (mb_strlen($newName) >= 2 && mb_strlen($newName) <= 80) {
                $oldName = $target['full_name'];
                $db->prepare("UPDATE users SET full_name=? WHERE id=?")->execute(array($newName,$targetId));
                addLog($db,$uid,$targetId,'change_name',"\"$oldName\" → \"$newName\"");
                $flash = array('ok', "✅ Nome alterado para \"$newName\"");
            }

        } elseif ($action === 'coins' && amMaster($currentUser)) {
            $amount = (int)($_POST['coins_amount'] ?? 0);
            try {
                $db->exec("CREATE TABLE IF NOT EXISTS user_profile_config (user_id INT PRIMARY KEY, coins INT DEFAULT 0, frame_key VARCHAR(50) NULL, background_key VARCHAR(50) NULL, banner_url VARCHAR(500) NULL, accent_color VARCHAR(20) NULL, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                $db->prepare("INSERT INTO user_profile_config (user_id,coins) VALUES (?,?) ON DUPLICATE KEY UPDATE coins=coins+?")->execute(array($targetId,$amount,$amount));
                $sign = $amount >= 0 ? "+$amount" : "$amount";
                addLog($db,$uid,$targetId,'coins',"$sign moedas");
                $flash = array('ok', "🪙 {$sign} moedas em {$target['full_name']}");
            } catch(Exception $e) { $flash = array('err','Erro: '.$e->getMessage()); }

        } elseif ($action === 'delete_post') {
            $postId = (int)($_POST['post_id'] ?? 0);
            try {
                $pp = $db->prepare("SELECT title FROM forum_posts WHERE id=?");
                $pp->execute(array($postId)); $pp = $pp->fetch();
                $db->prepare("DELETE FROM forum_posts WHERE id=?")->execute(array($postId));
                addLog($db,$uid,$targetId,'delete_post',"Post #$postId: " . ($pp['title'] ?? ''));
                $flash = array('ok', "🗑️ Post apagado");
            } catch(Exception $e){}

        } elseif ($action === 'delete_community' && amMaster($currentUser)) {
            $commId = (int)($_POST['comm_id'] ?? 0);
            try {
                $cp = $db->prepare("SELECT name FROM forum_communities WHERE id=?");
                $cp->execute(array($commId)); $cp = $cp->fetch();
                $db->prepare("UPDATE forum_communities SET is_active=0 WHERE id=?")->execute(array($commId));
                addLog($db,$uid,null,'delete_community',"Comunidade #$commId: " . ($cp['name'] ?? ''));
                $flash = array('ok', "🗑️ Comunidade desativada");
            } catch(Exception $e){}
        }
    } else {
        $flash = array('err', '⚠️ Sem permissão ou utilizador não encontrado');
    }
}

// ── Dados por tab ─────────────────────────────────────────────

// Stats
$stats = array();
if ($tab === 'stats') {
    try {
        $stats['users']       = (int)$db->query("SELECT COUNT(*) FROM users WHERE is_active=1")->fetchColumn();
        $stats['banned']      = (int)$db->query("SELECT COUNT(*) FROM users WHERE is_active=0")->fetchColumn();
        $stats['posts']       = (int)$db->query("SELECT COUNT(*) FROM forum_posts")->fetchColumn();
        $stats['replies']     = (int)$db->query("SELECT COUNT(*) FROM forum_replies")->fetchColumn();
        $stats['communities'] = (int)$db->query("SELECT COUNT(*) FROM forum_communities WHERE is_active=1")->fetchColumn();
        try { $stats['comments'] = (int)$db->query("SELECT COUNT(*) FROM comments WHERE status='aprovado'")->fetchColumn(); } catch(Exception $e){ $stats['comments']=0; }
        $stats['today_users'] = (int)$db->query("SELECT COUNT(*) FROM users WHERE DATE(created_at)=CURDATE()")->fetchColumn();
        $stats['today_posts'] = (int)$db->query("SELECT COUNT(*) FROM forum_posts WHERE DATE(created_at)=CURDATE()")->fetchColumn();
        // Utilizadores por role
        try {
            $rolesQ = $db->query("SELECT role, COUNT(*) as cnt FROM users GROUP BY role ORDER BY FIELD(role,'master','admin','moderator','user')");
            $stats['by_role'] = $rolesQ->fetchAll();
        } catch(Exception $e){
            try { $stats['by_role'] = $db->query("SELECT role, COUNT(*) as cnt FROM users GROUP BY role")->fetchAll(); } catch(Exception $e2){ $stats['by_role']=array(); }
        }
        // Posts últimos 7 dias
        try { $stats['week_posts'] = $db->query("SELECT DATE(created_at) as d, COUNT(*) as cnt FROM forum_posts WHERE created_at >= DATE_SUB(NOW(),INTERVAL 7 DAY) GROUP BY DATE(created_at) ORDER BY d")->fetchAll(); } catch(Exception $e){ $stats['week_posts']=array(); }
    } catch(Exception $e){}
}

// Utilizadores
$users = array(); $totalUsers = 0;
if ($tab === 'users') {
    try {
        $where = "WHERE 1=1";
        $params = array();
        if ($search) { $where .= " AND (full_name LIKE ? OR username LIKE ? OR email LIKE ?)"; $like = "%$search%"; $params = array($like,$like,$like); }
        try {
            $cntStmt = $db->prepare("SELECT COUNT(*) FROM users $where");
            $cntStmt->execute($params);
            $totalUsers = (int)$cntStmt->fetchColumn();
        } catch(Exception $e){ $totalUsers = 0; }
        // Verificar se last_login existe
        $hasLastLogin = false;
        try {
            $cols = $db->query("SHOW COLUMNS FROM users LIKE 'last_login'")->fetchAll();
            $hasLastLogin = !empty($cols);
        } catch(Exception $e){}
        $loginCol = $hasLastLogin ? ", last_login" : ", NULL as last_login";
        $uStmt = $db->prepare("SELECT id, full_name, username, email, role, is_active, created_at $loginCol FROM users $where ORDER BY created_at DESC LIMIT 50");
        $uStmt->execute($params);
        $users = $uStmt->fetchAll();
    } catch(Exception $e){}
}

// Logs
$logs = array();
if ($tab === 'logs') {
    try {
        $logs = $db->query("
            SELECT al.*, 
                   a.full_name as actor_name, a.username as actor_user, a.role as actor_role,
                   t.full_name as target_name, t.username as target_user
            FROM admin_logs al
            JOIN users a ON a.id = al.actor_id
            LEFT JOIN users t ON t.id = al.target_id
            ORDER BY al.created_at DESC LIMIT 100
        ")->fetchAll();
    } catch(Exception $e){}
}

// Comunidades
$communities = array();
if ($tab === 'communities') {
    try {
        $communities = $db->query("
            SELECT fc.*, u.full_name as owner_name, u.username as owner_user
            FROM forum_communities fc
            LEFT JOIN users u ON u.id = fc.created_by
            ORDER BY fc.created_at DESC
        ")->fetchAll();
    } catch(Exception $e){}
}

$csrf = generateCSRFToken();
$unreadMsgs = 0;
try { $um = $db->prepare("SELECT COUNT(*) FROM private_messages WHERE receiver_id=? AND read_at IS NULL"); $um->execute(array($uid)); $unreadMsgs = (int)$um->fetchColumn(); } catch(Exception $e){}

function roleBadge($r) {
    $map = array(
        'master'    => array('#ffcc00','rgba(255,204,0,0.15)','&#128081;'),
        'admin'     => array('#ff6b35','rgba(255,107,53,0.15)','&#128737;'),
        'moderator' => array('#a78bfa','rgba(124,58,237,0.15)','&#9876;'),
        'user'      => array('#888899','rgba(136,136,153,0.1)','&#128100;'),
    );
    $m = isset($map[$r]) ? $map[$r] : $map['user'];
    $label = strtoupper($r);
    return "<span style=\"font-family:monospace;font-size:9px;font-weight:700;padding:3px 8px;border-radius:20px;background:{$m[1]};color:{$m[0]};border:1px solid {$m[0]}44\">{$m[2]} {$label}</span>";
}
function fmtDt($ts) {
    if (!$ts) return '—';
    $d = time() - strtotime($ts);
    if ($d < 3600)  return floor($d/60).'min atrás';
    if ($d < 86400) return floor($d/3600).'h atrás';
    return date('d/m/Y H:i', strtotime($ts));
}
function actionLabel($a) {
    $map = array('warn'=>array('⚠️','#ffcc00'),'suspend'=>array('🔒','#ff6b35'),'ban'=>array('🚫','#ff4444'),'unban'=>array('✅','#00ff88'),'change_role'=>array('🎭','#a78bfa'),'change_name'=>array('✏️','#00e5ff'),'coins'=>array('🪙','#ffcc00'),'delete_post'=>array('🗑️','#ff4444'),'delete_community'=>array('🗑️','#ff4444'));
    return $map[$a] ?? array('•','#888899');
}
?><!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
    <link rel="icon" type="image/x-icon"  href="../favicons/favicon-admin.ico">
    <link rel="icon" type="image/svg+xml" href="../favicons/favicon-admin.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="../favicons/favicon-admin-32.png">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Painel de Administração — Fórum 3D</title>
<link rel="icon" type="image/x-icon"  href="../favicon.ico">
<link rel="icon" type="image/svg+xml" href="../favicon.svg">
<link rel="icon" type="image/png" sizes="32x32" href="../favicon-32.png">
<link rel="apple-touch-icon" sizes="192x192" href="../favicon-192.png">
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800;900&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root{--bg:#0a0a0f;--surface:#111118;--surface2:#1a1a26;--surface3:#222235;--accent:#00e5ff;--accent2:#ff6b35;--accent3:#7c3aed;--accent4:#00ff88;--text:#e8e8f0;--muted:#888899;--border:rgba(0,229,255,0.1);--border2:rgba(255,255,255,0.06);--master:#ffcc00;--admin:#ff6b35;--mod:#a78bfa}
*{margin:0;padding:0;box-sizing:border-box}
body{background:var(--bg);color:var(--text);font-family:'Inter',sans-serif;min-height:100vh}
body::before{content:'';position:fixed;inset:0;pointer-events:none;z-index:0;background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.03'/%3E%3C/svg%3E");opacity:0.4}

.topbar{position:sticky;top:0;z-index:100;background:rgba(10,10,15,0.95);backdrop-filter:blur(16px);border-bottom:1px solid var(--border);padding:0 28px;display:flex;align-items:center;gap:14px;height:56px}
.topbar-logo{font-family:'Space Mono',monospace;font-size:11px;font-weight:700;color:var(--accent);letter-spacing:3px;text-decoration:none}
.topbar-logo span{color:var(--muted)}
.topbar-right{display:flex;align-items:center;gap:8px;margin-left:auto}
.topbar-btn{background:none;border:1px solid var(--border2);border-radius:8px;padding:6px 13px;color:var(--muted);font-family:'Space Mono',monospace;font-size:10px;text-decoration:none;display:inline-flex;align-items:center;gap:5px;transition:all 0.2s}
.topbar-btn:hover{border-color:var(--accent);color:var(--accent)}
.topbar-av{width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,var(--accent3),var(--accent));display:flex;align-items:center;justify-content:center;font-family:'Space Mono',monospace;font-size:10px;font-weight:700;color:#000;overflow:hidden;text-decoration:none}
.topbar-av img{width:100%;height:100%;object-fit:cover;border-radius:50%}
.role-pill{font-family:'Space Mono',monospace;font-size:9px;font-weight:700;padding:4px 10px;border-radius:20px}
.role-pill.master{background:rgba(255,204,0,0.12);color:var(--master);border:1px solid rgba(255,204,0,0.3)}
.role-pill.admin{background:rgba(255,107,53,0.12);color:var(--admin);border:1px solid rgba(255,107,53,0.3)}
.role-pill.moderator{background:rgba(167,139,250,0.12);color:var(--mod);border:1px solid rgba(167,139,250,0.3)}

/* Layout */
.page{display:grid;grid-template-columns:220px 1fr;min-height:calc(100vh - 56px);position:relative;z-index:1}
.sidebar{background:var(--surface);border-right:1px solid var(--border2);padding:20px 0}
.sidebar-title{font-family:'Space Mono',monospace;font-size:9px;color:var(--muted);letter-spacing:2px;text-transform:uppercase;padding:0 18px 10px}
.nav-link{display:flex;align-items:center;gap:10px;padding:11px 18px;color:var(--muted);text-decoration:none;font-size:13px;transition:all 0.15s;border-left:3px solid transparent;position:relative}
.nav-link:hover{color:var(--text);background:rgba(255,255,255,0.02)}
.nav-link.active{color:var(--accent);border-left-color:var(--accent);background:rgba(0,229,255,0.04)}
.nav-link .icon{font-size:15px;width:20px;text-align:center}
.nav-divider{height:1px;background:var(--border2);margin:10px 18px}

/* Content */
.content{padding:28px 32px;overflow-y:auto}
.page-header{margin-bottom:24px}
.page-title{font-family:'Syne',sans-serif;font-size:22px;font-weight:800;color:#fff;margin-bottom:4px}
.page-sub{font-size:13px;color:var(--muted)}

/* Flash toast */
.flash-toast{position:fixed;top:66px;right:20px;z-index:9999;padding:12px 18px;border-radius:10px;font-family:'Space Mono',monospace;font-size:11px;font-weight:700;animation:toastIn 0.3s ease;max-width:340px}
.flash-toast.ok{background:rgba(0,255,136,0.1);border:1px solid rgba(0,255,136,0.3);color:var(--accent4)}
.flash-toast.err{background:rgba(255,68,68,0.1);border:1px solid rgba(255,68,68,0.3);color:#ff8888}
@keyframes toastIn{from{opacity:0;transform:translateX(14px)}to{opacity:1;transform:translateX(0)}}

/* Stats grid */
.stats-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:14px;margin-bottom:24px}
.stat-card{background:var(--surface);border:1px solid var(--border2);border-radius:12px;padding:18px 20px;transition:border-color 0.2s}
.stat-card:hover{border-color:rgba(0,229,255,0.2)}
.stat-label{font-family:'Space Mono',monospace;font-size:9px;color:var(--muted);letter-spacing:2px;text-transform:uppercase;margin-bottom:8px}
.stat-value{font-family:'Syne',sans-serif;font-size:28px;font-weight:900;color:var(--accent);line-height:1}
.stat-sub{font-size:11px;color:var(--muted);margin-top:4px}

/* Search bar */
.search-bar{display:flex;gap:10px;margin-bottom:20px}
.search-input{flex:1;background:var(--surface2);border:1px solid var(--border2);border-radius:9px;padding:10px 16px;color:var(--text);font-family:'Inter',sans-serif;font-size:13px;transition:border-color 0.2s;outline:none}
.search-input:focus{border-color:var(--accent)}
.search-btn{background:var(--accent);border:none;border-radius:9px;padding:10px 18px;color:#000;font-family:'Space Mono',monospace;font-size:10px;font-weight:700;cursor:pointer}

/* User table */
.user-table{width:100%;border-collapse:collapse}
.user-table th{font-family:'Space Mono',monospace;font-size:9px;color:var(--muted);letter-spacing:2px;text-transform:uppercase;text-align:left;padding:10px 14px;border-bottom:1px solid var(--border2)}
.user-table td{padding:12px 14px;border-bottom:1px solid rgba(255,255,255,0.03);vertical-align:middle}
.user-table tr:hover td{background:rgba(255,255,255,0.015)}
.user-av{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--accent3),var(--accent));display:flex;align-items:center;justify-content:center;font-family:'Space Mono',monospace;font-size:11px;font-weight:700;color:#000;overflow:hidden;flex-shrink:0}
.user-av img{width:100%;height:100%;object-fit:cover;border-radius:50%}
.user-name-cell{display:flex;align-items:center;gap:10px}
.user-fullname{font-size:13px;font-weight:600;color:var(--text)}
.user-username{font-family:'Space Mono',monospace;font-size:10px;color:var(--muted)}
.banned-row td{opacity:0.5}

/* Action buttons */
.act-btn{background:none;border:1px solid var(--border2);border-radius:6px;padding:5px 10px;color:var(--muted);font-family:'Space Mono',monospace;font-size:9px;cursor:pointer;transition:all 0.15s;white-space:nowrap}
.act-btn:hover{border-color:currentColor}
.act-btn.warn{color:#ffcc00}.act-btn.warn:hover{background:rgba(255,204,0,0.08)}
.act-btn.suspend{color:var(--accent2)}.act-btn.suspend:hover{background:rgba(255,107,53,0.08)}
.act-btn.ban{color:#ff4444}.act-btn.ban:hover{background:rgba(255,68,68,0.08)}
.act-btn.unban{color:var(--accent4)}.act-btn.unban:hover{background:rgba(0,255,136,0.08)}
.act-btn.role{color:#a78bfa}.act-btn.role:hover{background:rgba(124,58,237,0.08)}
.act-btn.name{color:var(--accent)}.act-btn.name:hover{background:rgba(0,229,255,0.08)}
.act-btn.coins{color:#ffcc00}.act-btn.coins:hover{background:rgba(255,204,0,0.08)}
.actions-cell{display:flex;gap:5px;flex-wrap:wrap}

/* Modal */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.8);z-index:9000;align-items:center;justify-content:center;padding:20px}
.modal-overlay.open{display:flex}
.modal{background:#111118;border:1px solid rgba(0,229,255,0.15);border-radius:16px;padding:28px;max-width:420px;width:100%;position:relative}
.modal-title{font-family:'Syne',sans-serif;font-size:18px;font-weight:800;color:#fff;margin-bottom:4px}
.modal-sub{font-size:12px;color:var(--muted);margin-bottom:20px}
.modal-close{position:absolute;top:14px;right:16px;background:none;border:none;color:var(--muted);font-size:20px;cursor:pointer}
.form-label{font-family:'Space Mono',monospace;font-size:10px;color:var(--muted);letter-spacing:1px;text-transform:uppercase;display:block;margin-bottom:7px}
.form-input,.form-select,.form-textarea{width:100%;background:var(--surface2);border:1px solid var(--border2);border-radius:9px;padding:11px 14px;color:var(--text);font-family:'Inter',sans-serif;font-size:13px;margin-bottom:14px;transition:border-color 0.2s;outline:none}
.form-input:focus,.form-select:focus{border-color:var(--accent)}
.form-textarea{resize:vertical;min-height:60px}
.modal-actions{display:flex;gap:8px}
.modal-btn{border:none;border-radius:8px;padding:10px 20px;font-family:'Space Mono',monospace;font-size:10px;font-weight:700;cursor:pointer;transition:all 0.2s}
.modal-btn.confirm{background:linear-gradient(135deg,var(--accent),var(--accent3));color:#000}
.modal-btn.danger{background:rgba(255,68,68,0.12);border:1px solid rgba(255,68,68,0.3);color:#ff7777}
.modal-btn.cancel{background:none;border:1px solid var(--border2);color:var(--muted)}

/* Logs */
.log-item{display:flex;align-items:flex-start;gap:12px;padding:12px 0;border-bottom:1px solid rgba(255,255,255,0.03)}
.log-icon{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0;background:var(--surface2)}
.log-text{flex:1;min-width:0}
.log-actor{font-size:12px;font-weight:600;color:var(--text)}
.log-detail{font-size:11px;color:var(--muted);margin-top:2px;line-height:1.4}
.log-time{font-family:'Space Mono',monospace;font-size:10px;color:var(--muted);flex-shrink:0}

/* Communities table */
.comm-row{background:var(--surface);border:1px solid var(--border2);border-radius:10px;padding:14px 16px;margin-bottom:10px;display:flex;align-items:center;gap:14px}
.comm-icon-box{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0}
.comm-info{flex:1;min-width:0}
.comm-name{font-size:14px;font-weight:600;color:var(--text)}
.comm-meta{font-family:'Space Mono',monospace;font-size:10px;color:var(--muted);margin-top:3px}

/* Role bar */
.role-bar-wrap{background:var(--surface);border:1px solid var(--border2);border-radius:12px;padding:18px 20px;margin-bottom:20px}
.role-bar-row{display:flex;align-items:center;gap:12px;margin-bottom:10px}
.role-bar-row:last-child{margin-bottom:0}
.role-bar-label{font-family:'Space Mono',monospace;font-size:10px;font-weight:700;width:90px;flex-shrink:0}
.role-bar-track{flex:1;height:8px;background:var(--surface3);border-radius:100px;overflow:hidden}
.role-bar-fill{height:100%;border-radius:100px}
.role-bar-count{font-family:'Space Mono',monospace;font-size:10px;color:var(--muted);width:40px;text-align:right;flex-shrink:0}

@media(max-width:900px){.page{grid-template-columns:1fr}.sidebar{display:none}}
.bc-bar{background:var(--surface);border-bottom:1px solid var(--border2);padding:8px 32px;position:relative;z-index:5}
.bc-inner{max-width:1400px;margin:0 auto;display:flex;align-items:center;gap:6px;font-family:'Space Mono',monospace;font-size:10px;color:var(--muted);flex-wrap:wrap}
.bc-link{color:var(--muted);text-decoration:none;transition:color 0.15s}.bc-link:hover{color:var(--accent)}
.bc-sep{opacity:0.4}.bc-current{color:var(--text)}
</style>
</head>
<body>

<nav class="topbar">
    <a href="index.php" class="topbar-logo">3D<span>/</span>FÓRUM</a>
    <span style="color:var(--muted);font-size:12px">/ Painel Admin</span>
    <span class="role-pill <?php echo $role; ?>"><?php echo strtoupper($role); ?></span>
    <div class="topbar-right">
        <a href="../index.php" class="topbar-btn">← Manual</a>
        <a href="mensagens.php" class="topbar-btn">💬<?php if($unreadMsgs>0): ?><span style="background:var(--accent2);color:#fff;border-radius:100px;font-size:9px;padding:1px 5px"><?php echo $unreadMsgs; ?></span><?php endif; ?></a>
        <a href="perfil.php?id=<?php echo $uid; ?>" class="topbar-av">
            <?php $av=$currentUser['avatar_url']??''; if($av): ?><img src="<?php echo sanitize(avPath($av)); ?>" alt=""><?php else: echo mb_substr($currentUser['full_name'],0,2); endif; ?>
        </a>
    </div>
</nav>
<!-- Breadcrumb -->
<div class="bc-bar">
    <div class="bc-inner">
        <a href="../index.php" class="bc-link">📖 Manual</a>
        <span class="bc-sep">›</span>
        <a href="index.php" class="bc-link">🌐 Fórum</a>
        <span class="bc-sep">›</span><span class="bc-current">⚔️ Administração</span>
    </div>
</div>

<?php if ($flash): ?>
<div class="flash-toast <?php echo $flash[0]; ?>" id="flashToast"><?php echo sanitize($flash[1]); ?></div>
<script>setTimeout(function(){var t=document.getElementById('flashToast');if(t){t.style.transition='opacity 0.5s';t.style.opacity='0';}},4000);</script>
<?php endif; ?>

<div class="page">

<!-- Sidebar -->
<nav class="sidebar">
    <div class="sidebar-title">Administração</div>
    <a href="?tab=stats"       class="nav-link <?php echo $tab==='stats'?'active':''; ?>"><span class="icon">📊</span> Estatísticas</a>
    <a href="?tab=users"       class="nav-link <?php echo $tab==='users'?'active':''; ?>"><span class="icon">👥</span> Utilizadores</a>
    <a href="?tab=communities" class="nav-link <?php echo $tab==='communities'?'active':''; ?>"><span class="icon">🏘️</span> Comunidades</a>
    <a href="?tab=logs"        class="nav-link <?php echo $tab==='logs'?'active':''; ?>"><span class="icon">📋</span> Logs de Auditoria</a>
    <div class="nav-divider"></div>
    <div class="sidebar-title">Links Rápidos</div>
    <a href="index.php"        class="nav-link"><span class="icon">🌐</span> Fórum</a>
    <a href="../moderacao.php" class="nav-link"><span class="icon">🛡️</span> Moderação Manual</a>
    <a href="loja.php"         class="nav-link"><span class="icon">🛒</span> Loja</a>
</nav>

<!-- Conteúdo -->
<div class="content">

<?php if ($tab === 'stats'): ?>
<div class="page-header">
    <div class="page-title">📊 Estatísticas Gerais</div>
    <div class="page-sub">Visão geral da plataforma em tempo real</div>
</div>

<div class="stats-grid">
    <div class="stat-card"><div class="stat-label">Utilizadores activos</div><div class="stat-value"><?php echo number_format($stats['users']??0); ?></div><div class="stat-sub">+<?php echo $stats['today_users']??0; ?> hoje</div></div>
    <div class="stat-card"><div class="stat-label">Banidos</div><div class="stat-value" style="color:#ff4444"><?php echo number_format($stats['banned']??0); ?></div></div>
    <div class="stat-card"><div class="stat-label">Posts no fórum</div><div class="stat-value"><?php echo number_format($stats['posts']??0); ?></div><div class="stat-sub">+<?php echo $stats['today_posts']??0; ?> hoje</div></div>
    <div class="stat-card"><div class="stat-label">Respostas</div><div class="stat-value"><?php echo number_format($stats['replies']??0); ?></div></div>
    <div class="stat-card"><div class="stat-label">Comunidades</div><div class="stat-value"><?php echo number_format($stats['communities']??0); ?></div></div>
    <div class="stat-card"><div class="stat-label">Comentários manual</div><div class="stat-value"><?php echo number_format($stats['comments']??0); ?></div></div>
</div>

<!-- Utilizadores por cargo -->
<div class="role-bar-wrap">
    <div style="font-family:'Space Mono',monospace;font-size:9px;color:var(--muted);letter-spacing:2px;text-transform:uppercase;margin-bottom:14px">Utilizadores por Cargo</div>
    <?php
    $roleColors = array('master'=>'#ffcc00','admin'=>'#ff6b35','moderator'=>'#a78bfa','user'=>'#00e5ff');
    $total = max(1, $stats['users'] ?? 1);
    foreach ($stats['by_role'] ?? array() as $r):
        $pct = round(($r['cnt'] / $total) * 100);
        $col = $roleColors[$r['role']] ?? '#888899';
    ?>
    <div class="role-bar-row">
        <div class="role-bar-label" style="color:<?php echo $col; ?>"><?php echo strtoupper($r['role']); ?></div>
        <div class="role-bar-track"><div class="role-bar-fill" style="width:<?php echo $pct; ?>%;background:<?php echo $col; ?>"></div></div>
        <div class="role-bar-count"><?php echo $r['cnt']; ?></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Posts últimos 7 dias -->
<?php if (!empty($stats['week_posts'])): ?>
<div style="background:var(--surface);border:1px solid var(--border2);border-radius:12px;padding:18px 20px">
    <div style="font-family:'Space Mono',monospace;font-size:9px;color:var(--muted);letter-spacing:2px;text-transform:uppercase;margin-bottom:14px">Posts — Últimos 7 dias</div>
    <?php $maxP = max(array_column($stats['week_posts'],'cnt')); ?>
    <?php foreach ($stats['week_posts'] as $dp): $pct = $maxP ? round($dp['cnt']/$maxP*100) : 0; ?>
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
        <div style="font-family:Space Mono,monospace;font-size:10px;color:var(--muted);width:70px"><?php echo date('d/m', strtotime($dp['d'])); ?></div>
        <div style="flex:1;height:6px;background:var(--surface3);border-radius:100px;overflow:hidden"><div style="height:100%;width:<?php echo $pct; ?>%;background:linear-gradient(90deg,var(--accent3),var(--accent));border-radius:100px"></div></div>
        <div style="font-family:Space Mono,monospace;font-size:10px;color:var(--accent);width:24px;text-align:right"><?php echo $dp['cnt']; ?></div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>


<?php elseif ($tab === 'users'): ?>
<div class="page-header">
    <div class="page-title">👥 Utilizadores</div>
    <div class="page-sub">Gerir utilizadores, cargos e sanções</div>
</div>

<form method="GET" class="search-bar">
    <input type="hidden" name="tab" value="users">
    <input type="text" name="q" class="search-input" placeholder="Pesquisar por nome, username ou email…" value="<?php echo sanitize($search); ?>">
    <button type="submit" class="search-btn">🔍 PESQUISAR</button>
</form>

<div style="overflow-x:auto">
<table class="user-table">
    <thead>
        <tr>
            <th>Utilizador</th>
            <th>Cargo</th>
            <th>Estado</th>
            <th>Registado</th>
            <th>Último login</th>
            <th>Acções</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($users as $u):
        $isBanned = !$u['is_active'];
        $isMe = (int)$u['id'] === $uid;
        $canAct = !$isMe && amCanManage($currentUser, $u);
        $initU = mb_substr($u['full_name']??'??',0,2);
    ?>
    <tr class="<?php echo $isBanned?'banned-row':''; ?>">
        <td>
            <div class="user-name-cell">
                <div class="user-av">
                    <img src="" alt="" style="display:none" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                    <span><?php echo sanitize($initU); ?></span>
                </div>
                <div>
                    <div class="user-fullname"><?php echo sanitize($u['full_name']); ?> <?php if($isMe): ?><span style="font-family:Space Mono,monospace;font-size:8px;color:var(--accent)">(tu)</span><?php endif; ?></div>
                    <div class="user-username">@<?php echo sanitize($u['username']); ?></div>
                </div>
            </div>
        </td>
        <td><?php echo roleBadge($u['role']); ?></td>
        <td>
            <?php if ($isBanned): ?>
            <span style="font-family:'Space Mono',monospace;font-size:9px;color:#ff4444;background:rgba(255,68,68,0.08);border:1px solid rgba(255,68,68,0.2);padding:3px 8px;border-radius:20px">🚫 BANIDO</span>
            <?php else: ?>
            <span style="font-family:'Space Mono',monospace;font-size:9px;color:var(--accent4);background:rgba(0,255,136,0.08);border:1px solid rgba(0,255,136,0.2);padding:3px 8px;border-radius:20px">✅ ATIVO</span>
            <?php endif; ?>
        </td>
        <td style="font-family:Space Mono,monospace;font-size:10px;color:var(--muted)"><?php echo fmtDt($u['created_at']); ?></td>
        <td style="font-family:Space Mono,monospace;font-size:10px;color:var(--muted)"><?php echo fmtDt($u['last_login'] ?? null); ?></td>
        <td>
            <?php if ($canAct): ?>
            <div class="actions-cell">
                <?php if (amMod($currentUser)): ?>
                <button class="act-btn warn" onclick="openModal('warn',<?php echo $u['id']; ?>,'<?php echo addslashes(sanitize($u['full_name'])); ?>')">⚠️ Avisar</button>
                <button class="act-btn suspend" onclick="openModal('suspend',<?php echo $u['id']; ?>,'<?php echo addslashes(sanitize($u['full_name'])); ?>')">🔒 Suspender</button>
                <?php endif; ?>
                <?php if (amAdmin($currentUser)): ?>
                <?php if (!$isBanned): ?><button class="act-btn ban" onclick="openModal('ban',<?php echo $u['id']; ?>,'<?php echo addslashes(sanitize($u['full_name'])); ?>')">🚫 Banir</button>
                <?php else: ?><button class="act-btn unban" onclick="quickAction('unban',<?php echo $u['id']; ?>)">✅ Desbanir</button><?php endif; ?>
                <button class="act-btn role" onclick="openModal('role',<?php echo $u['id']; ?>,'<?php echo addslashes(sanitize($u['full_name'])); ?>','<?php echo $u['role']; ?>')">🎭 Cargo</button>
                <button class="act-btn name" onclick="openModal('name',<?php echo $u['id']; ?>,'<?php echo addslashes(sanitize($u['full_name'])); ?>')">✏️ Nome</button>
                <?php endif; ?>
                <?php if (amMaster($currentUser)): ?>
                <button class="act-btn coins" onclick="openModal('coins',<?php echo $u['id']; ?>,'<?php echo addslashes(sanitize($u['full_name'])); ?>')">🪙 Moedas</button>
                <?php endif; ?>
                <a href="perfil.php?id=<?php echo $u['id']; ?>" class="act-btn" style="text-decoration:none">👤</a>
            </div>
            <?php elseif ($isMe): ?>
            <span style="font-size:11px;color:var(--muted)">—</span>
            <?php else: ?>
            <span style="font-size:11px;color:var(--muted)">Sem permissão</span>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($users)): ?>
    <tr><td colspan="6" style="text-align:center;padding:40px;color:var(--muted)">Nenhum utilizador encontrado.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
</div>


<?php elseif ($tab === 'communities'): ?>
<div class="page-header">
    <div class="page-title">🏘️ Comunidades</div>
    <div class="page-sub">Gerir comunidades do fórum</div>
</div>

<?php foreach ($communities as $comm): ?>
<div class="comm-row" style="<?php echo !$comm['is_active']?'opacity:0.5':''; ?>">
    <div class="comm-icon-box" style="background:linear-gradient(135deg,<?php echo htmlspecialchars($comm['banner_color']); ?>33,<?php echo htmlspecialchars($comm['banner_color']); ?>11)"><?php echo $comm['icon']; ?></div>
    <div class="comm-info">
        <div class="comm-name"><?php echo sanitize($comm['name']); ?> <?php if(!$comm['is_active']): ?><span style="font-family:Space Mono,monospace;font-size:9px;color:#ff4444">[INATIVA]</span><?php endif; ?></div>
        <div class="comm-meta">c/<?php echo sanitize($comm['slug']); ?> · <?php echo number_format($comm['member_count']); ?> membros · <?php echo number_format($comm['post_count']); ?> posts · Owner: <?php echo sanitize($comm['owner_name'] ?? '?'); ?></div>
    </div>
    <div style="display:flex;gap:6px;flex-shrink:0">
        <a href="comunidade.php?slug=<?php echo urlencode($comm['slug']); ?>" class="act-btn" style="text-decoration:none">👁️ Ver</a>
        <a href="gerir_comunidade.php?id=<?php echo $comm['id']; ?>" class="act-btn name" style="text-decoration:none">⚙️ Gerir</a>
        <?php if (amMaster($currentUser) && $comm['is_active']): ?>
        <form method="POST" style="margin:0" onsubmit="return confirm('Desativar esta comunidade?')">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <input type="hidden" name="action" value="delete_community">
            <input type="hidden" name="target_id" value="<?php echo $comm['created_by'] ?? 0; ?>">
            <input type="hidden" name="comm_id" value="<?php echo $comm['id']; ?>">
            <button type="submit" class="act-btn ban">🗑️ Desativar</button>
        </form>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
<?php if (empty($communities)): ?>
<div style="text-align:center;padding:40px;color:var(--muted)">Nenhuma comunidade encontrada.</div>
<?php endif; ?>


<?php elseif ($tab === 'logs'): ?>
<div class="page-header">
    <div class="page-title">📋 Logs de Auditoria</div>
    <div class="page-sub">Histórico de todas as acções administrativas</div>
</div>

<?php if (empty($logs)): ?>
<div style="text-align:center;padding:40px;color:var(--muted)">Ainda sem registos de auditoria.</div>
<?php else: ?>
<div style="background:var(--surface);border:1px solid var(--border2);border-radius:12px;padding:18px 20px">
<?php foreach ($logs as $log):
    $al = actionLabel($log['action']);
?>
<div class="log-item">
    <div class="log-icon" style="background:<?php echo $al[1]; ?>18;color:<?php echo $al[1]; ?>"><?php echo $al[0]; ?></div>
    <div class="log-text">
        <div class="log-actor">
            <?php echo roleBadge($log['actor_role']); ?>
            <strong style="color:var(--text);margin-left:6px"><?php echo sanitize($log['actor_name']); ?></strong>
            <span style="color:var(--muted);font-size:11px"> @<?php echo sanitize($log['actor_user']); ?></span>
            <?php if ($log['target_name']): ?>
            <span style="color:var(--muted);font-size:11px"> → </span>
            <strong style="color:var(--text)"><?php echo sanitize($log['target_name']); ?></strong>
            <span style="color:var(--muted);font-size:11px"> @<?php echo sanitize($log['target_user']); ?></span>
            <?php endif; ?>
        </div>
        <div class="log-detail">
            <span style="font-family:Space Mono,monospace;font-size:9px;font-weight:700;color:<?php echo $al[1]; ?>"><?php echo strtoupper($log['action']); ?></span>
            <?php if ($log['detail']): ?> — <?php echo sanitize($log['detail']); ?><?php endif; ?>
        </div>
    </div>
    <div class="log-time"><?php echo fmtDt($log['created_at']); ?></div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php endif; ?>

</div><!-- /.content -->
</div><!-- /.page -->

<!-- Modais de acção -->
<div class="modal-overlay" id="actionModal">
<div class="modal">
    <button class="modal-close" onclick="closeModal()">✕</button>
    <div class="modal-title" id="modalTitle">Acção</div>
    <div class="modal-sub" id="modalSub"></div>
    <form method="POST" id="modalForm">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
        <input type="hidden" name="action" id="modalAction">
        <input type="hidden" name="target_id" id="modalTargetId">

        <!-- Warn -->
        <div id="warnFields" style="display:none">
            <label class="form-label">Motivo do aviso</label>
            <textarea name="reason" class="form-textarea" placeholder="Descreve o motivo do aviso…"></textarea>
        </div>

        <!-- Suspend -->
        <div id="suspendFields" style="display:none">
            <label class="form-label">Duração (dias)</label>
            <input type="number" name="days" class="form-input" value="1" min="1" max="365">
            <label class="form-label">Motivo</label>
            <textarea name="reason" class="form-textarea" placeholder="Motivo da suspensão…"></textarea>
        </div>

        <!-- Ban -->
        <div id="banFields" style="display:none">
            <label class="form-label">Motivo do banimento</label>
            <textarea name="reason" class="form-textarea" placeholder="Motivo do banimento permanente…"></textarea>
        </div>

        <!-- Role -->
        <div id="roleFields" style="display:none">
            <label class="form-label">Novo cargo</label>
            <select name="new_role" class="form-select" id="roleSelect">
                <?php if (amMaster($currentUser)): ?>
                <option value="master">👑 Master</option>
                <option value="admin">🛡️ Admin</option>
                <?php endif; ?>
                <option value="moderator">⚔️ Moderador</option>
                <option value="user">👤 Utilizador</option>
            </select>
        </div>

        <!-- Name -->
        <div id="nameFields" style="display:none">
            <label class="form-label">Novo nome completo</label>
            <input type="text" name="new_name" class="form-input" placeholder="Nome completo…" maxlength="80">
        </div>

        <!-- Coins -->
        <div id="coinsFields" style="display:none">
            <label class="form-label">Quantidade (negativo para retirar)</label>
            <input type="number" name="coins_amount" class="form-input" value="100" placeholder="ex: 100 ou -50">
        </div>

        <div class="modal-actions">
            <button type="submit" class="modal-btn confirm" id="modalConfirmBtn">Confirmar</button>
            <button type="button" class="modal-btn cancel" onclick="closeModal()">Cancelar</button>
        </div>
    </form>
</div>
</div>

<!-- Quick action form (unban, etc) -->
<form method="POST" id="quickForm" style="display:none">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
    <input type="hidden" name="action" id="quickAction">
    <input type="hidden" name="target_id" id="quickTargetId">
</form>

<script>
var currentModalType = '';

function openModal(type, targetId, name, extra) {
    currentModalType = type;
    document.getElementById('modalAction').value  = type === 'role' ? 'change_role' : type === 'name' ? 'change_name' : type === 'coins' ? 'coins' : type;
    document.getElementById('modalTargetId').value = targetId;

    var titles = {warn:'⚠️ Avisar Utilizador',suspend:'🔒 Suspender Utilizador',ban:'🚫 Banir Utilizador',role:'🎭 Mudar Cargo',name:'✏️ Alterar Nome',coins:'🪙 Dar / Tirar Moedas'};
    document.getElementById('modalTitle').textContent = titles[type] || 'Acção';
    document.getElementById('modalSub').textContent = name || '';

    ['warn','suspend','ban','role','name','coins'].forEach(function(t){
        var el = document.getElementById(t+'Fields');
        if (el) el.style.display = t === type ? 'block' : 'none';
    });

    if (type === 'role' && extra) {
        var sel = document.getElementById('roleSelect');
        if (sel) { for(var i=0;i<sel.options.length;i++){ if(sel.options[i].value===extra){sel.selectedIndex=i;break;} } }
    }

    var btn = document.getElementById('modalConfirmBtn');
    btn.className = 'modal-btn ' + (type === 'ban' ? 'danger' : 'confirm');

    document.getElementById('actionModal').classList.add('open');
}

function closeModal() {
    document.getElementById('actionModal').classList.remove('open');
}

function quickAction(action, targetId) {
    if (!confirm('Confirmar acção: ' + action + '?')) return;
    document.getElementById('quickAction').value   = action;
    document.getElementById('quickTargetId').value = targetId;
    document.getElementById('quickForm').submit();
}

document.getElementById('actionModal').addEventListener('click', function(e){
    if (e.target === this) closeModal();
});
</script>
</body>
</html>