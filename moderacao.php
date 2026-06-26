<?php
/**
 * moderacao.php — Painel de moderação
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/comments.php';
require_once __DIR__ . '/includes/mail_config.php';

if (!isLoggedIn()) redirect('login.php');
$user = getCurrentUser();
if (!canModerate($user) && ($user['role']??'') !== 'master') { http_response_code(403); die('<p style="color:red;padding:40px">Acesso negado.</p>'); }

$db   = getDB();
$csrf = generateCSRFToken();
$flash = array();

// ── Garantir colunas ──────────────────────────────────────────
try { $db->exec("ALTER TABLE comments ADD COLUMN IF NOT EXISTS reject_reason VARCHAR(500) NULL DEFAULT NULL"); } catch(Exception $e){}
try { $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS warning_message TEXT DEFAULT NULL"); } catch(Exception $e){}
try { $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS warning_at TIMESTAMP NULL DEFAULT NULL"); } catch(Exception $e){}
try { $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS suspension_message TEXT DEFAULT NULL"); } catch(Exception $e){}
try { $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS suspension_until TIMESTAMP NULL DEFAULT NULL"); } catch(Exception $e){}

// Tabela reported_comments — usar reporter_id (consistente com api/report_comment.php)
try {
    $db->exec("CREATE TABLE IF NOT EXISTS reported_comments (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        comment_id  INT NOT NULL,
        reporter_id INT NOT NULL,
        reason      VARCHAR(255) DEFAULT NULL,
        description TEXT DEFAULT NULL,
        status      ENUM('pendente','analisado','resolvido') DEFAULT 'pendente',
        created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        resolved_at TIMESTAMP NULL,
        FOREIGN KEY (comment_id)  REFERENCES comments(id) ON DELETE CASCADE,
        FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch(Exception $e){}

// Garantir coluna description caso tabela já existisse sem ela
try { $db->exec("ALTER TABLE reported_comments ADD COLUMN IF NOT EXISTS description TEXT DEFAULT NULL"); } catch(Exception $e){}

try {
    $db->exec("CREATE TABLE IF NOT EXISTS reported_users (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        user_id         INT NOT NULL,
        reported_by     INT NOT NULL,
        reason          VARCHAR(255) DEFAULT NULL,
        description     TEXT DEFAULT NULL,
        status          ENUM('pendente','analisado','resolvido') DEFAULT 'pendente',
        action_taken    ENUM('none','warning','suspension','ban') DEFAULT 'none',
        admin_message   TEXT DEFAULT NULL,
        suspension_days INT DEFAULT NULL,
        created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        resolved_at     TIMESTAMP NULL,
        resolved_by     INT NULL,
        FOREIGN KEY (user_id)     REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (reported_by) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch(Exception $e){}

// ── Email helper ──────────────────────────────────────────────
function sendEmailNotificationMod($userId, $subject, $bodyHtml) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT email, full_name FROM users WHERE id=?");
        $stmt->execute(array($userId));
        $u = $stmt->fetch();
        if (!$u || empty($u['email'])) return;
        $html = "<div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto'>"
              . "<h3 style='color:#00e5ff'>Ola, " . htmlspecialchars($u['full_name']) . "!</h3>"
              . "<div style='color:#333;line-height:1.6'>{$bodyHtml}</div>"
              . "<br><a href='https://manual-impressao-3d.free.nf' style='background:#00e5ff;color:#000;padding:10px 22px;text-decoration:none;border-radius:6px;font-weight:bold'>Ir para o Manual</a>"
              . "<p style='color:#888;font-size:12px;margin-top:24px'>Manual de Impressao 3D</p></div>";
        sendEmail($u['email'], $u['full_name'], $subject, $html);
    } catch(Exception $e) { error_log('Email mod: ' . $e->getMessage()); }
}

// ── POST ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? null)) {
    $action    = $_POST['action']     ?? '';
    $commentId = (int)($_POST['comment_id'] ?? 0);
    $reason    = trim($_POST['reason'] ?? '');

    if ($action === 'resolve_report') {
        $db->prepare("UPDATE reported_comments SET status='resolvido', resolved_at=NOW(), resolved_by=? WHERE id=?")
           ->execute(array((int)$user['id'], (int)$_POST['report_id']));

        logAdminAction((int)$user['id'], null, 'resolve_comment_report', "report_id=" . $_POST['report_id']);
        $flash = array('type'=>'success','msg'=>'Reporte de comentario resolvido.');

    } elseif ($action === 'resolve_user_report') {
        $reportId     = (int)$_POST['report_id'];
        $targetId     = (int)$_POST['target_user_id'];
        $actionTaken  = $_POST['action_taken'] ?? 'none';
        $adminMessage = trim($_POST['admin_message'] ?? '');
        $suspDays     = max(1, min(365, (int)($_POST['suspension_days'] ?? 3)));
        if (!in_array($actionTaken, array('none','warning','suspension','ban'))) $actionTaken = 'none';

        $db->prepare("UPDATE reported_users SET status='resolvido', action_taken=?, admin_message=?, suspension_days=?, resolved_at=NOW(), resolved_by=? WHERE id=?")
           ->execute(array($actionTaken, $adminMessage ?: null, $actionTaken==='suspension' ? $suspDays : null, (int)$user['id'], $reportId));

        $stmt = $db->prepare("SELECT full_name, email FROM users WHERE id=?");
        $stmt->execute(array($targetId));
        $target = $stmt->fetch();
        $targetName = $target ? $target['full_name'] : 'Utilizador';

        if ($actionTaken === 'warning' && !empty($adminMessage)) {
            $db->prepare("UPDATE users SET warning_message=?, warning_at=NOW() WHERE id=?")
               ->execute(array($adminMessage, $targetId));
            sendEmailNotificationMod($targetId, 'Aviso - Manual de Impressao 3D',
                "<p>Recebeste um aviso da equipa de moderacao:</p>"
                . "<blockquote style='border-left:3px solid #ff6b35;padding-left:16px;margin:16px 0;color:#555'>" . htmlspecialchars($adminMessage) . "</blockquote>"
                . "<p>Por favor corrige o comportamento antes de serem tomadas medidas mais graves.</p>");
            $flash = array('type'=>'warning','msg'=>"Aviso enviado a {$targetName}.");

        } elseif ($actionTaken === 'suspension' && !empty($adminMessage)) {
            $until = date('Y-m-d H:i:s', strtotime("+{$suspDays} days"));
            $db->prepare("UPDATE users SET suspension_message=?, suspension_until=? WHERE id=?")
               ->execute(array($adminMessage, $until, $targetId));
            $untilFmt = date('d/m/Y', strtotime($until));
            sendEmailNotificationMod($targetId, 'Conta suspensa - Manual de Impressao 3D',
                "<p>A tua conta foi suspensa ate <strong>{$untilFmt}</strong>.</p>"
                . "<blockquote style='border-left:3px solid #ff6b35;padding-left:16px;margin:16px 0;color:#555'>" . htmlspecialchars($adminMessage) . "</blockquote>"
                . "<p>Apos o periodo de suspensao, a tua conta sera automaticamente reativada.</p>");
            $flash = array('type'=>'warning','msg'=>"Conta de {$targetName} suspensa por {$suspDays} dia(s).");

        } elseif ($actionTaken === 'ban') {
            $db->prepare("UPDATE users SET is_active=0 WHERE id=?")->execute(array($targetId));
            sendEmailNotificationMod($targetId, 'Conta banida - Manual de Impressao 3D',
                "<p>A tua conta foi permanentemente banida da plataforma.</p>"
                . ($adminMessage ? "<blockquote style='border-left:3px solid #ff4444;padding-left:16px;margin:16px 0;color:#555'>" . htmlspecialchars($adminMessage) . "</blockquote>" : "")
                . "<p>Se acreditas que isto foi um erro, contacta a administracao.</p>");
            $admins = $db->query("SELECT id, full_name, email FROM users WHERE role IN ('admin','master') AND is_active=1")->fetchAll();
            foreach ($admins as $adm) {
                if ((int)$adm['id'] === (int)$user['id']) continue;
                $html = "<div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto'>"
                      . "<h3 style='color:#ff4444'>Utilizador Banido</h3>"
                      . "<p><strong>Utilizador:</strong> " . htmlspecialchars($targetName) . "</p>"
                      . "<p><strong>Banido por:</strong> " . htmlspecialchars($user['full_name']) . "</p>"
                      . ($adminMessage ? "<p><strong>Motivo:</strong> " . htmlspecialchars($adminMessage) . "</p>" : "")
                      . "<a href='https://manual-impressao-3d.free.nf/moderacao.php' style='background:#ff4444;color:#fff;padding:10px 22px;text-decoration:none;border-radius:6px;font-weight:bold;display:inline-block;margin-top:12px'>Ver Moderacao</a></div>";
                sendEmail($adm['email'], $adm['full_name'], "Utilizador banido: {$targetName}", $html);
            }
            $flash = array('type'=>'danger','msg'=>"Utilizador {$targetName} banido permanentemente.");
        } else {
            $flash = array('type'=>'success','msg'=>'Report resolvido sem acao.');
        }
        logAdminAction((int)$user['id'], $targetId, 'resolve_user_report', "report_id={$reportId}, action={$actionTaken}, msg={$adminMessage}");
        logActivity((int)$user['id'], "user_{$actionTaken}", "target={$targetId}");

    } elseif ($action === 'approve' && $commentId > 0) {
        $db->prepare("UPDATE comments SET status='aprovado', reviewed_at=NOW(), reviewed_by=? WHERE id=?")
           ->execute(array((int)$user['id'], $commentId));
        $row = $db->prepare('SELECT user_id FROM comments WHERE id=?');
        $row->execute(array($commentId)); $r = $row->fetch();
        if ($r) {
            createNotification((int)$r['user_id'], 'comment_approved', $commentId, 'O teu comentario foi aprovado.');
            sendEmailNotificationMod((int)$r['user_id'], 'O teu comentario foi aprovado!', 'O teu comentario foi aprovado e ja esta visivel na comunidade.');
        }
        $flash = array('type'=>'success','msg'=>'Comentario aprovado.');
        logAdminAction((int)$user['id'], (int)$r['user_id'], 'approve_comment', "comment_id={$commentId}");

    } elseif ($action === 'reject' && $commentId > 0) {
        $db->prepare("UPDATE comments SET status='rejeitado', reviewed_at=NOW(), reviewed_by=?, reject_reason=? WHERE id=?")
           ->execute(array((int)$user['id'], $reason ?: null, $commentId));
        $row = $db->prepare('SELECT user_id FROM comments WHERE id=?');
        $row->execute(array($commentId)); $r = $row->fetch();
        if ($r) {
            $msg = $reason ? "O teu comentario foi rejeitado: {$reason}" : 'O teu comentario foi rejeitado.';
            createNotification((int)$r['user_id'], 'comment_rejected', $commentId, $msg);
            sendEmailNotificationMod((int)$r['user_id'], 'O teu comentario foi rejeitado', $msg);
        }
        $flash = array('type'=>'warning','msg'=>'Comentario rejeitado.');
        logAdminAction((int)$user['id'], (int)$r['user_id'], 'reject_comment', "comment_id={$commentId}, reason={$reason}");

    } elseif ($action === 'block' && $commentId > 0) {
        $db->prepare("UPDATE comments SET status='bloqueado', reviewed_at=NOW(), reviewed_by=?, reject_reason=? WHERE id=?")
           ->execute(array((int)$user['id'], $reason ?: 'Bloqueado pelo administrador', $commentId));
        $flash = array('type'=>'danger','msg'=>'Comentario bloqueado.');
        logAdminAction((int)$user['id'], null, 'block_comment', "comment_id={$commentId}, reason={$reason}");
    }
}

// ── Dados ─────────────────────────────────────────────────────
$stats = array();
foreach (array('pendente','aprovado','rejeitado','bloqueado') as $s) {
    $st = $db->prepare('SELECT COUNT(*) FROM comments WHERE status=?');
    $st->execute(array($s));
    $stats[$s] = (int)$st->fetchColumn();
}
$stats['total'] = array_sum($stats);

function getCommentsByStatus($status, $limit) {
    if (!$limit) $limit = 200;
    $db = getDB();
    $stmt = $db->prepare("SELECT c.*,u.full_name,u.username,u.avatar_url,u.avatar,rv.full_name AS reviewer_name
        FROM comments c
        JOIN users u ON u.id=c.user_id
        LEFT JOIN users rv ON rv.id=c.reviewed_by
        WHERE c.status=? ORDER BY c.created_at DESC LIMIT ?");
    $stmt->execute(array($status, $limit));
    return $stmt->fetchAll();
}

function getReportedComments($limit) {
    if (!$limit) $limit = 200;
    $db = getDB();
    // Usar reporter_id (nome correto da coluna criada em api/report_comment.php)
    $stmt = $db->prepare("SELECT rc.*,
        c.content as comment_text, c.status as comment_status,
        u.full_name, u.username, u.avatar_url,
        reporter.full_name as reporter_name
        FROM reported_comments rc
        JOIN comments c ON c.id=rc.comment_id
        JOIN users u ON u.id=c.user_id
        JOIN users reporter ON reporter.id=rc.reporter_id
        WHERE rc.status IN ('pendente','analisado')
        ORDER BY rc.created_at DESC LIMIT ?");
    $stmt->execute(array($limit));
    return $stmt->fetchAll();
}

function getReportedUsers($limit) {
    if (!$limit) $limit = 200;
    $db = getDB();
    $stmt = $db->prepare("SELECT ru.*,
        u.full_name, u.username, u.avatar_url, u.email, u.is_active,
        reporter.full_name as reporter_name
        FROM reported_users ru
        JOIN users u ON u.id=ru.user_id
        JOIN users reporter ON reporter.id=ru.reported_by
        WHERE ru.status IN ('pendente','analisado')
        ORDER BY ru.created_at DESC LIMIT ?");
    $stmt->execute(array($limit));
    return $stmt->fetchAll();
}

$pending          = getCommentsByStatus('pendente', 200);
$approved         = getCommentsByStatus('aprovado', 200);
$rejected         = getCommentsByStatus('rejeitado', 200);
$blocked          = getCommentsByStatus('bloqueado', 200);
$reportedComments = getReportedComments(200);
$reportedUsers    = getReportedUsers(200);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Moderação — Manual 3D</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
<style>
:root{--bg:#0a0a0f;--surface:#111118;--surface2:#1a1a26;--surface3:#222235;--accent:#00e5ff;--accent2:#ff6b35;--accent3:#7c3aed;--accent4:#00ff88;--text:#e8e8f0;--muted:#888899;--border:rgba(0,229,255,0.12);--danger:#ff4444}
*{margin:0;padding:0;box-sizing:border-box}
body{background:var(--bg);color:var(--text);font-family:'Inter',sans-serif;min-height:100vh}
.layout{display:flex;min-height:100vh}
.sidebar{width:260px;background:var(--surface);border-right:1px solid var(--border);padding:32px 20px;display:flex;flex-direction:column;gap:8px;position:sticky;top:0;height:100vh;overflow-y:auto;flex-shrink:0}
.main{flex:1;padding:40px;overflow-y:auto}
.sidebar-logo{font-family:'Space Mono',monospace;font-size:10px;color:var(--accent);letter-spacing:3px;text-transform:uppercase;margin-bottom:24px}
.sidebar-title{font-family:'Syne',sans-serif;font-size:20px;font-weight:800;color:#fff;margin-bottom:4px}
.sidebar-sub{font-size:12px;color:var(--muted);margin-bottom:28px}
.sidebar-sep{height:1px;background:var(--border);margin:16px 0}
.nav-label{font-family:'Space Mono',monospace;font-size:9px;color:var(--muted);letter-spacing:2px;text-transform:uppercase;margin-bottom:8px;padding:0 8px}
.nav-btn{display:flex;align-items:center;justify-content:space-between;padding:10px 14px;border-radius:10px;border:none;background:transparent;color:var(--muted);font-family:'Inter',sans-serif;font-size:13px;cursor:pointer;transition:all 0.2s;width:100%;text-align:left}
.nav-btn:hover{background:var(--surface2);color:var(--text)}
.nav-btn.active{background:rgba(0,229,255,0.08);color:var(--accent);border:1px solid rgba(0,229,255,0.15)}
.nav-btn .icon{margin-right:10px}
.nav-count{font-family:'Space Mono',monospace;font-size:10px;font-weight:700;padding:2px 7px;border-radius:100px}
.nav-count.pendente{background:rgba(255,107,53,0.2);color:var(--accent2)}
.nav-count.aprovado{background:rgba(0,255,136,0.15);color:var(--accent4)}
.nav-count.rejeitado{background:rgba(255,68,68,0.15);color:var(--danger)}
.nav-count.bloqueado{background:rgba(124,58,237,0.2);color:#a78bfa}
.back-link{display:flex;align-items:center;gap:8px;color:var(--muted);text-decoration:none;font-family:'Space Mono',monospace;font-size:10px;padding:10px 14px;border-radius:10px;transition:all 0.2s;margin-top:auto}
.back-link:hover{color:var(--accent);background:var(--surface2)}
.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:32px}
.stat-card{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px}
.stat-card .num{font-family:'Syne',sans-serif;font-size:32px;font-weight:800;line-height:1}
.stat-card .lbl{font-family:'Space Mono',monospace;font-size:9px;color:var(--muted);letter-spacing:2px;text-transform:uppercase;margin-top:6px}
.stat-card.total .num{color:var(--accent)}.stat-card.pendente .num{color:var(--accent2)}.stat-card.aprovado .num{color:var(--accent4)}.stat-card.rejeitado .num{color:var(--danger)}
.flash{padding:14px 20px;border-radius:10px;margin-bottom:24px;font-size:14px}
.flash.success{background:rgba(0,255,136,0.08);border:1px solid rgba(0,255,136,0.25);color:var(--accent4)}
.flash.warning{background:rgba(255,107,53,0.08);border:1px solid rgba(255,107,53,0.3);color:var(--accent2)}
.flash.danger{background:rgba(255,68,68,0.08);border:1px solid rgba(255,68,68,0.25);color:var(--danger)}
.tab-panel{display:none}.tab-panel.active{display:block}
.panel-title{font-family:'Syne',sans-serif;font-size:22px;font-weight:800;color:#fff;margin-bottom:6px}
.panel-sub{font-size:13px;color:var(--muted);margin-bottom:22px}
.comment-card{background:var(--surface);border:1px solid var(--border);border-radius:14px;margin-bottom:12px;overflow:hidden;transition:border-color 0.2s;cursor:pointer}
.comment-card:hover{border-color:rgba(0,229,255,0.3)}
.comment-card-header{padding:18px 20px;display:flex;align-items:center;gap:14px}
.avatar{width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,var(--accent),var(--accent3));display:flex;align-items:center;justify-content:center;font-weight:700;color:#fff;font-size:14px;overflow:hidden;flex-shrink:0}
.avatar img{width:100%;height:100%;object-fit:cover;border-radius:50%}
.card-info{flex:1;min-width:0}
.card-author{font-family:'Syne',sans-serif;font-size:14px;font-weight:700;color:#fff}
.card-meta{font-size:12px;color:var(--muted);margin-top:2px}
.card-preview{font-size:13px;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:300px}
.card-badges{display:flex;gap:8px;align-items:center;flex-shrink:0}
.badge{padding:3px 10px;border-radius:100px;font-family:'Space Mono',monospace;font-size:10px;font-weight:700}
.badge.pendente{background:rgba(255,107,53,0.15);color:var(--accent2)}.badge.aprovado{background:rgba(0,255,136,0.1);color:var(--accent4)}.badge.rejeitado{background:rgba(255,68,68,0.1);color:var(--danger)}.badge.bloqueado{background:rgba(124,58,237,0.15);color:#a78bfa}.badge.cat{background:var(--surface2);color:var(--muted)}
.expand-icon{color:var(--muted);font-size:12px;transition:transform 0.2s;flex-shrink:0}
.comment-expanded{display:none;border-top:1px solid var(--border);padding:20px;background:var(--surface2)}.comment-expanded.open{display:block}
.user-report-body{display:none;border-top:1px solid var(--border);padding:20px;background:var(--surface2)}.user-report-body.open{display:block}
.comment-full-text{font-size:14px;line-height:1.8;color:var(--text);background:var(--bg);border-radius:10px;padding:16px 18px;margin-bottom:18px;white-space:pre-wrap;word-break:break-word}
.reason-box{border-radius:10px;padding:14px 16px;margin-bottom:16px;font-size:13px}
.reason-box.orange{background:rgba(255,107,53,0.06);border:1px solid rgba(255,107,53,0.2);color:#ff9999}
.reason-box.red{background:rgba(255,68,68,0.06);border:1px solid rgba(255,68,68,0.2);color:#ff9999}
.form-group{display:flex;flex-direction:column;gap:6px}
.form-label{font-family:'Space Mono',monospace;font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:1px}
.form-input{background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:10px 14px;color:var(--text);font-family:'Inter',sans-serif;font-size:13px;transition:border-color 0.2s}
.form-input:focus{outline:none;border-color:var(--accent)}
.form-input::placeholder{color:var(--muted);opacity:0.6}
.form-select{appearance:none;cursor:pointer;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%23888899' stroke-width='1.5' fill='none'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;padding-right:36px}
.form-select option{background:var(--surface2)}
.form-textarea{resize:vertical;min-height:80px}
.action-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px}
.action-grid .full{grid-column:1/-1}
.susp-days-wrap{display:none}
.ban-warning{display:none;background:rgba(255,68,68,0.08);border:1px solid rgba(255,68,68,0.25);border-radius:8px;padding:12px 16px;font-size:12px;color:#ff8888;margin-bottom:12px}
.btn{padding:10px 20px;border:none;border-radius:8px;font-family:'Space Mono',monospace;font-size:11px;font-weight:700;cursor:pointer;transition:all 0.2s}
.btn:hover{transform:translateY(-1px)}
.btn-approve{background:rgba(0,255,136,0.1);color:var(--accent4);border:1px solid rgba(0,255,136,0.25)}.btn-approve:hover{background:rgba(0,255,136,0.2)}
.btn-reject{background:rgba(255,107,53,0.1);color:var(--accent2);border:1px solid rgba(255,107,53,0.25)}.btn-reject:hover{background:rgba(255,107,53,0.2)}
.btn-block{background:rgba(124,58,237,0.1);color:#a78bfa;border:1px solid rgba(124,58,237,0.25)}.btn-block:hover{background:rgba(124,58,237,0.2)}
.btn-warn{background:rgba(255,107,53,0.1);color:var(--accent2);border:1px solid rgba(255,107,53,0.25)}.btn-warn:hover{background:rgba(255,107,53,0.2)}
.btn-susp{background:rgba(124,58,237,0.1);color:#a78bfa;border:1px solid rgba(124,58,237,0.25)}.btn-susp:hover{background:rgba(124,58,237,0.2)}
.btn-ban{background:rgba(255,68,68,0.1);color:var(--danger);border:1px solid rgba(255,68,68,0.25)}.btn-ban:hover{background:rgba(255,68,68,0.2)}
.btn-none{background:rgba(0,229,255,0.08);color:var(--accent);border:1px solid rgba(0,229,255,0.2)}.btn-none:hover{background:rgba(0,229,255,0.15)}
.actions-row{display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end}
.reviewed-info{font-size:12px;color:var(--muted);margin-bottom:12px}
.empty-state{text-align:center;padding:60px 20px;color:var(--muted)}
.empty-state .icon{font-size:48px;margin-bottom:16px}
@media(max-width:900px){.layout{flex-direction:column}.sidebar{width:100%;height:auto;position:static}.main{padding:20px}.stats-grid{grid-template-columns:repeat(2,1fr)}.action-grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="layout">
<aside class="sidebar">
    <div>
        <div class="sidebar-logo">Manual 3D</div>
        <div class="sidebar-title">🛡️ Moderação</div>
        <div class="sidebar-sub"><?php echo sanitize($user['full_name']); ?> · <?php echo $user['role']; ?></div>
    </div>
    <div class="nav-label">Administração</div>
    <button class="nav-btn" id="nav-dashboard" onclick="switchTab('dashboard',this)"><span><span class="icon">📊</span>Dashboard Global</span></button>
    <div class="sidebar-sep"></div>
    <div class="nav-label">Fila de revisão</div>
    <button class="nav-btn active" id="nav-pendente" onclick="switchTab('pendente',this)"><span><span class="icon">⏳</span>Pendentes</span><span class="nav-count pendente"><?php echo count($pending); ?></span></button>
    <button class="nav-btn" id="nav-bloqueado" onclick="switchTab('bloqueado',this)"><span><span class="icon">🚫</span>Bloqueados</span><span class="nav-count bloqueado"><?php echo count($blocked); ?></span></button>
    <div class="sidebar-sep"></div>
    <div class="nav-label">Reportes</div>
    <button class="nav-btn" id="nav-reported_comments" onclick="switchTab('reported_comments',this)"><span><span class="icon">🚩</span>Coment. Reportados</span><span class="nav-count pendente"><?php echo count($reportedComments); ?></span></button>
    <button class="nav-btn" id="nav-reported_users" onclick="switchTab('reported_users',this)"><span><span class="icon">⚠️</span>Utilizadores Reportados</span><span class="nav-count pendente"><?php echo count($reportedUsers); ?></span></button>
    <div class="sidebar-sep"></div>
    <div class="nav-label">Histórico</div>
    <button class="nav-btn" id="nav-aprovado" onclick="switchTab('aprovado',this)"><span><span class="icon">✅</span>Aprovados</span><span class="nav-count aprovado"><?php echo count($approved); ?></span></button>
    <button class="nav-btn" id="nav-rejeitado" onclick="switchTab('rejeitado',this)"><span><span class="icon">✕</span>Rejeitados</span><span class="nav-count rejeitado"><?php echo count($rejected); ?></span></button>
    <div class="sidebar-sep"></div>
    <a href="index.php" class="back-link">← Voltar ao site</a>
</aside>

<main class="main">
    <div class="stats-grid">
        <div class="stat-card total"><div class="num"><?php echo $stats['total']; ?></div><div class="lbl">Total</div></div>
        <div class="stat-card pendente"><div class="num"><?php echo $stats['pendente']; ?></div><div class="lbl">Pendentes</div></div>
        <div class="stat-card aprovado"><div class="num"><?php echo $stats['aprovado']; ?></div><div class="lbl">Aprovados</div></div>
        <div class="stat-card rejeitado"><div class="num"><?php echo $stats['rejeitado']+$stats['bloqueado']; ?></div><div class="lbl">Rejeitados/Bloq.</div></div>
    </div>

    <?php if (!empty($flash)): ?>
    <div class="flash <?php echo $flash['type']; ?>"><?php echo sanitize($flash['msg']); ?></div>
    <?php endif; ?>

    <!-- Dashboard Global (Novo) -->
    <div class="tab-panel" id="tab-dashboard">
        <div class="panel-title">📊 Dashboard de Gestão</div>
        <div class="panel-sub">Visão geral do ecossistema Manual 3D</div>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:20px; margin-bottom:40px">
            <?php
            $totalUsers = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
            $totalPosts = $db->query("SELECT COUNT(*) FROM forum_posts")->fetchColumn();
            $totalComms = $db->query("SELECT COUNT(*) FROM forum_communities")->fetchColumn();
            $totalGP    = $db->query("SELECT SUM(growth_points) FROM user_profile_config")->fetchColumn();
            ?>
            <div class="stat-card" style="border-left: 4px solid var(--accent)">
                <div class="num" style="color:var(--accent)"><?php echo number_format($totalUsers); ?></div>
                <div class="lbl">Utilizadores Registados</div>
            </div>
            <div class="stat-card" style="border-left: 4px solid var(--accent3)">
                <div class="num" style="color:var(--accent3)"><?php echo number_format($totalPosts); ?></div>
                <div class="lbl">Posts no Fórum</div>
            </div>
            <div class="stat-card" style="border-left: 4px solid var(--accent4)">
                <div class="num" style="color:var(--accent4)"><?php echo number_format($totalGP); ?></div>
                <div class="lbl">GP Totais (Crescimento)</div>
            </div>
            <div class="stat-card" style="border-left: 4px solid var(--accent2)">
                <div class="num" style="color:var(--accent2)"><?php echo $totalComms; ?></div>
                <div class="lbl">Comunidades Ativas</div>
            </div>
        </div>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:24px">
            <div class="card" style="padding:24px">
                <h3 style="font-family:'Syne'; margin-bottom:15px">📈 Atividade Recente</h3>
                <div style="display:flex; flex-direction:column; gap:12px">
                    <?php
                    $recentActivity = $db->query("SELECT al.*, u.username FROM activity_logs al LEFT JOIN users u ON u.id=al.user_id ORDER BY al.created_at DESC LIMIT 5")->fetchAll();
                    foreach($recentActivity as $act): ?>
                        <div style="font-size:12px; border-bottom:1px solid var(--border); padding-bottom:8px">
                            <span style="color:var(--accent)">@<?php echo sanitize($act['username'] ?? 'Sistema'); ?></span>
                            <span style="color:var(--muted)">fez</span> <strong><?php echo sanitize($act['action']); ?></strong>
                            <div style="color:var(--muted); font-size:10px"><?php echo date('d/m H:i', strtotime($act['created_at'])); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="card" style="padding:24px">
                <h3 style="font-family:'Syne'; margin-bottom:15px">🛠️ Atalhos Rápidos</h3>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px">
                    <a href="forum/admin.php" class="btn btn-none" style="text-decoration:none; text-align:center; justify-content:center">Gere Utilizadores</a>
                    <a href="debug_email.php" class="btn btn-none" style="text-decoration:none; text-align:center; justify-content:center">Teste Email</a>
                    <a href="calculadora.php" class="btn btn-none" style="text-decoration:none; text-align:center; justify-content:center">Calculadora</a>
                    <a href="index.php" class="btn btn-none" style="text-decoration:none; text-align:center; justify-content:center">Ver Manual</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Pendentes -->
    <div class="tab-panel active" id="tab-pendente">
        <div class="panel-title">⏳ Pendentes</div><div class="panel-sub">Clica para expandir</div>
        <?php if(empty($pending)): ?><div class="empty-state"><div class="icon">✅</div><p>Nenhum comentário pendente!</p></div><?php else: foreach($pending as $c): renderCard($c,$csrf,array('approve','reject','block')); endforeach; endif; ?>
    </div>
    <!-- Bloqueados -->
    <div class="tab-panel" id="tab-bloqueado">
        <div class="panel-title">🚫 Bloqueados</div><div class="panel-sub">Bloqueados pela IA — aprova se for falso positivo</div>
        <?php if(empty($blocked)): ?><div class="empty-state"><div class="icon">🚫</div><p>Nenhum.</p></div><?php else: foreach($blocked as $c): renderCard($c,$csrf,array('approve','reject')); endforeach; endif; ?>
    </div>
    <!-- Comentários Reportados -->
    <div class="tab-panel" id="tab-reported_comments">
        <div class="panel-title">🚩 Comentários Reportados</div><div class="panel-sub">Marcados pela comunidade</div>
        <?php if(empty($reportedComments)): ?><div class="empty-state"><div class="icon">✅</div><p>Nenhum!</p></div><?php else: foreach($reportedComments as $rc): ?>
        <div class="comment-card" onclick="toggleCard('rc-<?php echo $rc['id']; ?>',event)">
            <div class="comment-card-header">
                <div class="avatar"><?php if(!empty($rc['avatar_url'])): ?><img src="<?php echo sanitize($rc['avatar_url']); ?>" alt=""><?php else: echo sanitize(mb_substr($rc['full_name']??'??',0,2)); endif; ?></div>
                <div class="card-info">
                    <div class="card-author"><?php echo sanitize($rc['full_name']); ?> <span style="color:var(--muted);font-weight:400;font-size:12px">@<?php echo sanitize($rc['username']); ?></span></div>
                    <div class="card-meta">Reportado por: <strong><?php echo sanitize($rc['reporter_name']); ?></strong></div>
                    <div class="card-preview"><?php echo sanitize(mb_substr($rc['comment_text'],0,80)); ?><?php echo mb_strlen($rc['comment_text'])>80?'…':''; ?></div>
                </div>
                <div class="card-badges"><span class="badge pendente"><?php echo strtoupper($rc['status']); ?></span><span class="expand-icon" id="icon-rc-<?php echo $rc['id']; ?>">▼</span></div>
            </div>
            <div class="comment-expanded" id="expanded-rc-<?php echo $rc['id']; ?>">
                <div class="reason-box orange"><strong>Motivo:</strong> <?php echo sanitize($rc['reason']??'Não especificado'); ?></div>
                <?php if(!empty($rc['description'])): ?><div class="reason-box orange" style="margin-top:-8px"><strong>Descrição:</strong> <?php echo sanitize($rc['description']); ?></div><?php endif; ?>
                <div class="comment-full-text"><?php echo sanitize($rc['comment_text']); ?></div>
                <form method="POST" onclick="event.stopPropagation()">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                    <input type="hidden" name="action" value="resolve_report">
                    <input type="hidden" name="report_id" value="<?php echo $rc['id']; ?>">
                    <button type="submit" class="btn btn-approve">✓ Marcar Resolvido</button>
                </form>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>

    <!-- Utilizadores Reportados -->
    <div class="tab-panel" id="tab-reported_users">
        <div class="panel-title">⚠️ Utilizadores Reportados</div>
        <div class="panel-sub">Escolhe a ação, escreve o motivo — o utilizador será notificado por email e verá no site</div>
        <?php if(empty($reportedUsers)): ?><div class="empty-state"><div class="icon">✅</div><p>Nenhum!</p></div><?php else: foreach($reportedUsers as $ru): ?>
        <div class="comment-card" onclick="toggleCard('ru-<?php echo $ru['id']; ?>',event)">
            <div class="comment-card-header">
                <div class="avatar"><?php if(!empty($ru['avatar_url'])): ?><img src="<?php echo sanitize($ru['avatar_url']); ?>" alt=""><?php else: echo sanitize(mb_substr($ru['full_name']??'??',0,2)); endif; ?></div>
                <div class="card-info">
                    <div class="card-author">
                        <?php echo sanitize($ru['full_name']); ?>
                        <span style="color:var(--muted);font-weight:400;font-size:12px">@<?php echo sanitize($ru['username']); ?></span>
                        <?php if(!$ru['is_active']): ?><span style="background:rgba(255,68,68,0.15);color:var(--danger);padding:2px 8px;border-radius:4px;font-family:'Space Mono',monospace;font-size:9px;margin-left:6px">BANIDO</span><?php endif; ?>
                    </div>
                    <div class="card-meta">Reportado por <strong><?php echo sanitize($ru['reporter_name']); ?></strong> · <?php echo date('d/m/Y H:i',strtotime($ru['created_at'])); ?></div>
                    <div class="card-preview"><?php echo sanitize(mb_substr($ru['reason']??'Sem motivo',0,80)); ?></div>
                </div>
                <div class="card-badges"><span class="badge pendente"><?php echo strtoupper($ru['status']); ?></span><span class="expand-icon" id="icon-ru-<?php echo $ru['id']; ?>">▼</span></div>
            </div>
            <div class="user-report-body" id="expanded-ru-<?php echo $ru['id']; ?>">
                <div class="reason-box orange">
                    <div><strong>Motivo do reporte:</strong> <?php echo sanitize($ru['reason']??'Não especificado'); ?></div>
                    <?php if(!empty($ru['description'])): ?><div style="margin-top:6px"><strong>Descrição:</strong> <?php echo sanitize($ru['description']); ?></div><?php endif; ?>
                </div>
                <form method="POST" onclick="event.stopPropagation()">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                    <input type="hidden" name="action" value="resolve_user_report">
                    <input type="hidden" name="report_id" value="<?php echo $ru['id']; ?>">
                    <input type="hidden" name="target_user_id" value="<?php echo $ru['user_id']; ?>">
                    <div class="action-grid">
                        <div class="form-group">
                            <label class="form-label">Ação</label>
                            <select name="action_taken" class="form-input form-select" onchange="onActionChange(<?php echo $ru['id']; ?>,this.value)">
                                <option value="none">Sem ação — só resolver</option>
                                <option value="warning">⚠️ Aviso</option>
                                <option value="suspension">🔒 Suspensão</option>
                                <option value="ban">🚫 Banimento permanente</option>
                            </select>
                        </div>
                        <div class="form-group susp-days-wrap" id="susp-<?php echo $ru['id']; ?>">
                            <label class="form-label">Dias de suspensão</label>
                            <input type="number" name="suspension_days" class="form-input" value="3" min="1" max="365">
                        </div>
                        <div class="form-group full">
                            <label class="form-label">Mensagem para o utilizador <span id="req-<?php echo $ru['id']; ?>" style="color:var(--accent2);font-size:9px"></span></label>
                            <textarea name="admin_message" class="form-input form-textarea" placeholder="Esta mensagem será mostrada ao utilizador no site e enviada por email..."></textarea>
                        </div>
                    </div>
                    <div class="ban-warning" id="ban-warn-<?php echo $ru['id']; ?>">🚫 <strong>Atenção:</strong> O banimento é permanente e imediato. Todos os administradores serão notificados.</div>
                    <button type="submit" class="btn btn-none" id="submit-<?php echo $ru['id']; ?>">✓ Resolver Report</button>
                </form>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>

    <!-- Aprovados -->
    <div class="tab-panel" id="tab-aprovado">
        <div class="panel-title">✅ Aprovados</div><div class="panel-sub">Comentários visíveis na comunidade</div>
        <?php if(empty($approved)): ?><div class="empty-state"><div class="icon">💬</div><p>Nenhum aprovado.</p></div><?php else: foreach($approved as $c): renderCard($c,$csrf,array('reject','block')); endforeach; endif; ?>
    </div>
    <!-- Rejeitados -->
    <div class="tab-panel" id="tab-rejeitado">
        <div class="panel-title">✕ Rejeitados</div>
        <?php if(empty($rejected)): ?><div class="empty-state"><div class="icon">📋</div><p>Nenhum rejeitado.</p></div><?php else: foreach($rejected as $c): renderCard($c,$csrf,array('approve','block')); endforeach; endif; ?>
    </div>
</main>
</div>

<?php
function renderCard($c, $csrf, $actions) {
    $initials = mb_substr($c['full_name']??'??', 0, 2);
    $preview  = mb_substr($c['content'], 0, 80) . (mb_strlen($c['content']) > 80 ? '…' : '');
    $catLabels = array('duvida'=>'DÚVIDA','problema'=>'PROBLEMA','dica'=>'DICA','geral'=>'GERAL');
    $cat = isset($catLabels[$c['category']??'geral']) ? $catLabels[$c['category']] : 'GERAL';
    $cid = 'card-' . $c['id'];
    ?>
    <div class="comment-card" id="<?php echo $cid; ?>" onclick="toggleCard('<?php echo $cid; ?>',event)">
        <div class="comment-card-header">
            <div class="avatar"><?php if(!empty($c['avatar_url'])): ?><img src="<?php echo sanitize($c['avatar_url']); ?>" alt=""><?php else: echo sanitize($initials); endif; ?></div>
            <div class="card-info">
                <div class="card-author"><?php echo sanitize($c['full_name']); ?> <span style="color:var(--muted);font-weight:400;font-size:12px">@<?php echo sanitize($c['username']); ?></span></div>
                <div class="card-meta"><?php echo date('d/m/Y H:i',strtotime($c['created_at'])); ?> · #<?php echo $c['id']; ?><?php echo !empty($c['parent_id']) ? ' · Resposta' : ''; ?></div>
                <div class="card-preview"><?php echo sanitize($preview); ?></div>
            </div>
            <div class="card-badges"><span class="badge cat"><?php echo $cat; ?></span><span class="badge <?php echo $c['status']; ?>"><?php echo strtoupper($c['status']); ?></span><span class="expand-icon" id="icon-<?php echo $cid; ?>">▼</span></div>
        </div>
        <div class="comment-expanded" id="expanded-<?php echo $cid; ?>">
            <div class="comment-full-text"><?php echo sanitize($c['content']); ?></div>
            <?php if(!empty($c['reject_reason'])): ?><div class="reason-box red"><strong>Motivo:</strong> <?php echo sanitize($c['reject_reason']); ?></div><?php endif; ?>
            <?php if(!empty($c['reviewer_name'])): ?><div class="reviewed-info">Revisto por <strong><?php echo sanitize($c['reviewer_name']); ?></strong><?php if(!empty($c['reviewed_at'])): ?> em <?php echo date('d/m/Y H:i',strtotime($c['reviewed_at'])); ?><?php endif; ?></div><?php endif; ?>
            <form method="POST" onclick="event.stopPropagation()" onsubmit="return confirmAction(this)">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                <input type="hidden" name="comment_id" value="<?php echo $c['id']; ?>">
                <input type="hidden" name="action" id="action-<?php echo $cid; ?>" value="">
                <div class="actions-row">
                    <div class="form-group" style="flex:1;min-width:200px"><label class="form-label">Motivo (opcional)</label><input type="text" name="reason" class="form-input" placeholder="Ex: Linguagem ofensiva..."></div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap">
                        <?php if(in_array('approve',$actions)): ?><button type="submit" class="btn btn-approve" onclick="setAction('<?php echo $cid; ?>','approve')">✓ Aprovar</button><?php endif; ?>
                        <?php if(in_array('reject',$actions)): ?><button type="submit" class="btn btn-reject" onclick="setAction('<?php echo $cid; ?>','reject')">✕ Rejeitar</button><?php endif; ?>
                        <?php if(in_array('block',$actions)): ?><button type="submit" class="btn btn-block" onclick="setAction('<?php echo $cid; ?>','block')">🚫 Bloquear</button><?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <?php
}
?>
<script>
function switchTab(tab,btn){document.querySelectorAll('.tab-panel').forEach(function(p){p.classList.remove('active')});document.querySelectorAll('.nav-btn').forEach(function(b){b.classList.remove('active')});document.getElementById('tab-'+tab).classList.add('active');btn.classList.add('active')}
function toggleCard(id,e){var ex=document.getElementById('expanded-'+id);var ic=document.getElementById('icon-'+id);if(!ex)return;var open=ex.classList.contains('open');ex.classList.toggle('open',!open);if(ic)ic.style.transform=open?'rotate(0deg)':'rotate(180deg)'}
function setAction(id,a){document.getElementById('action-'+id).value=a}
function confirmAction(f){var a=f.querySelector('input[name="action"]').value;var l={approve:'aprovar',reject:'rejeitar',block:'bloquear'};return confirm('Tens a certeza que queres '+(l[a]||a)+' este comentário?')}
function onActionChange(id,val){
    document.getElementById('susp-'+id).style.display=val==='suspension'?'flex':'none';
    document.getElementById('ban-warn-'+id).style.display=val==='ban'?'block':'none';
    document.getElementById('req-'+id).textContent=(['warning','suspension','ban'].indexOf(val)>=0)?'(obrigatório)':'';
    var labels={none:'✓ Resolver Report',warning:'⚠️ Enviar Aviso',suspension:'🔒 Suspender Conta',ban:'🚫 Banir Utilizador'};
    var btn=document.getElementById('submit-'+id);
    btn.textContent=labels[val]||'✓ Resolver';
    var cls={none:'btn-none',warning:'btn-warn',suspension:'btn-susp',ban:'btn-ban'};
    btn.className='btn '+(cls[val]||'btn-none');
}
</script>
</body>
</html>