<?php
/**
 * forum/gerir_comunidade.php — Gerir configurações da comunidade
 */
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) { header('Location: ../login.php?redirect=forum/index.php'); exit; }
$currentUser = getCurrentUser();
$uid = (int)$currentUser['id'];
$db  = getDB();

// Garantir colunas necessárias
try { $db->exec("ALTER TABLE forum_communities ADD COLUMN IF NOT EXISTS description VARCHAR(500) DEFAULT NULL"); } catch(Exception $e){}

$commId = (int)($_GET['id'] ?? 0);
if ($commId < 1) { header('Location: index.php'); exit; }

// Buscar comunidade
$stmt = $db->prepare("SELECT * FROM forum_communities WHERE id=? AND is_active=1");
$stmt->execute(array($commId));
$comm = $stmt->fetch();
if (!$comm) { http_response_code(404); die('<p style="color:#888;padding:40px;font-family:monospace">Comunidade não encontrada.</p>'); }

// Verificar permissão — owner ou mod global
$isGlobMod = in_array($currentUser['role'] ?? '', array('admin','moderator'));
$isOwner   = (int)$comm['created_by'] === $uid;
$ms = $db->prepare("SELECT role FROM forum_memberships WHERE user_id=? AND community_id=?");
$ms->execute(array($uid, $commId));
$mr = $ms->fetch();
$isCommMod = $mr && in_array($mr['role'], array('owner','moderator'));

if (!$isOwner && !$isCommMod && !$isGlobMod) {
    http_response_code(403);
    die('<p style="color:#888;padding:40px;font-family:monospace">Sem permissão.</p>');
}

$flash = '';
$flashType = 'success';

// ── POST ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? null)) {
    $action = $_POST['action'] ?? '';

    // ── Gestão de roles de membros (apenas owner) ─────────────
    if ($action === 'set_member_role' && $isOwner) {
        $targetId = (int)($_POST['user_id'] ?? 0);
        $newRole  = trim($_POST['new_role'] ?? '');
        if (!in_array($newRole, array('admin','moderator','member'))) {
            $flash = 'Role inválido.'; $flashType = 'error';
        } elseif ($targetId === $uid) {
            $flash = 'Não podes alterar o teu próprio role.'; $flashType = 'error';
        } else {
            $tm = $db->prepare("SELECT role FROM forum_memberships WHERE user_id=? AND community_id=?");
            $tm->execute(array($targetId,$commId)); $tm=$tm->fetch();
            if (!$tm) { $flash='Utilizador não é membro desta comunidade.'; $flashType='error'; }
            elseif ($tm['role']==='owner') { $flash='Não podes alterar o role do criador.'; $flashType='error'; }
            else {
                $db->prepare("UPDATE forum_memberships SET role=? WHERE user_id=? AND community_id=?")
                   ->execute(array($newRole,$targetId,$commId));
                $flash = 'Role atualizado com sucesso.';
            }
        }
    }

    // ── Moderação de posts pendentes ──────────────────────────
    if (in_array($action, array('approve_post','reject_post')) && ($isOwner || $isCommMod || $isGlobMod)) {
        $postId = (int)($_POST['post_id'] ?? 0);
        $reason = trim($_POST['rejection_reason'] ?? '');
        if ($postId > 0) {
            $ps = $db->prepare("SELECT id,community_id,status FROM forum_posts WHERE id=? AND community_id=?");
            $ps->execute(array($postId,$commId)); $pendPost=$ps->fetch();
            if ($pendPost && $pendPost['status']==='pending') {
                $db->beginTransaction();
                try {
                    if ($action==='approve_post') {
                        $db->prepare("UPDATE forum_posts SET status='approved',moderated_by=?,moderated_at=NOW() WHERE id=?")->execute(array($uid,$postId));
                        $db->prepare("UPDATE forum_communities SET post_count=post_count+1 WHERE id=?")->execute(array($commId));
                        $db->prepare("INSERT INTO forum_moderation_log (post_id,moderator_id,action) VALUES (?,?,'approved')")->execute(array($postId,$uid));
                        $flash = 'Post aprovado e publicado.';
                    } else {
                        $db->prepare("UPDATE forum_posts SET status='rejected',moderated_by=?,moderated_at=NOW(),rejection_reason=? WHERE id=?")->execute(array($uid,$reason?:null,$postId));
                        $db->prepare("INSERT INTO forum_moderation_log (post_id,moderator_id,action,reason) VALUES (?,?,'rejected',?)")->execute(array($postId,$uid,$reason?:null));
                        $flash = 'Post rejeitado.';
                    }
                    $db->commit();
                } catch(Exception $e) { $db->rollBack(); $flash='Erro ao processar.'; $flashType='error'; }
            } else { $flash='Post não encontrado ou já processado.'; $flashType='error'; }
        }
    }

    if ($action === 'update_info') {
        // ... (código existente) ...
    }

    // Ações de Moderação de Posts
    if ($action === 'approve_post' || $action === 'reject_post') {
        $postId = (int)($_POST['post_id'] ?? 0);
        if ($postId > 0) {
            if ($action === 'approve_post') {
                $db->prepare("UPDATE forum_posts SET status='approved', moderated_by=?, moderated_at=NOW() WHERE id=? AND community_id=?")
                   ->execute([$uid, $postId, $commId]);
                $db->prepare("UPDATE forum_communities SET post_count = post_count + 1 WHERE id=?")->execute([$commId]);
                $flash = "Post aprovado com sucesso!";
            } else {
                $db->prepare("UPDATE forum_posts SET status='rejected', moderated_by=?, moderated_at=NOW() WHERE id=? AND community_id=?")
                   ->execute([$uid, $postId, $commId]);
                $flash = "Post rejeitado.";
                $flashType = "error";
            }
        }
    }
}

$csrf = generateCSRFToken();
$bannerColor = $comm['banner_color'] ?: '#00e5ff';

// Garantir colunas de moderação (safe — ignora se já existem)
try { $db->exec("ALTER TABLE forum_communities ADD COLUMN IF NOT EXISTS requires_approval TINYINT(1) DEFAULT 0"); } catch(Exception $e){}
try { $db->exec("ALTER TABLE forum_posts ADD COLUMN IF NOT EXISTS status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved'"); } catch(Exception $e){}
try { $db->exec("ALTER TABLE forum_posts ADD COLUMN IF NOT EXISTS moderated_by INT NULL"); } catch(Exception $e){}
try { $db->exec("ALTER TABLE forum_posts ADD COLUMN IF NOT EXISTS moderated_at DATETIME NULL"); } catch(Exception $e){}
try { $db->exec("ALTER TABLE forum_posts ADD COLUMN IF NOT EXISTS rejection_reason VARCHAR(500) NULL"); } catch(Exception $e){}
try { $db->exec("ALTER TABLE forum_memberships MODIFY COLUMN role ENUM('owner','admin','moderator','member') NOT NULL DEFAULT 'member'"); } catch(Exception $e){}
try { $db->exec("CREATE TABLE IF NOT EXISTS forum_moderation_log (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, post_id INT NOT NULL, moderator_id INT NOT NULL, action ENUM('approved','rejected') NOT NULL, reason VARCHAR(500) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_log_post (post_id), INDEX idx_log_mod (moderator_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"); } catch(Exception $e){}

// Posts pendentes desta comunidade
$pendingPosts = array();
try {
    $pp = $db->prepare("SELECT fp.id,fp.title,fp.content,fp.flair,fp.created_at, u.id as author_id,u.username,u.full_name,u.avatar_url FROM forum_posts fp JOIN users u ON u.id=fp.user_id WHERE fp.community_id=? AND fp.status='pending' ORDER BY fp.created_at ASC");
    $pp->execute(array($commId));
    $pendingPosts = $pp->fetchAll();
} catch(Exception $e){}

// Membros da comunidade (para gestão de roles)
$members = array();
try {
    $mb = $db->prepare("SELECT u.id,u.username,u.full_name,u.avatar_url,fm.role,fm.joined_at FROM forum_memberships fm JOIN users u ON u.id=fm.user_id WHERE fm.community_id=? ORDER BY FIELD(fm.role,'owner','admin','moderator','member'),fm.joined_at ASC LIMIT 100");
    $mb->execute(array($commId));
    $members = $mb->fetchAll();
} catch(Exception $e){}
?><!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
    <link rel="icon" type="image/x-icon"  href="../favicons/favicon-forum.ico">
    <link rel="icon" type="image/svg+xml" href="../favicons/favicon-forum.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="../favicons/favicon-forum-32.png">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gerir — <?php echo sanitize($comm['name']); ?></title>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root{--bg:#0a0a0f;--surface:#111118;--surface2:#1a1a26;--surface3:#222235;--accent:#00e5ff;--accent2:#ff6b35;--accent3:#7c3aed;--accent4:#00ff88;--text:#e8e8f0;--muted:#888899;--border:rgba(0,229,255,0.1);--border2:rgba(255,255,255,0.06);--comm:<?php echo htmlspecialchars($bannerColor); ?>}
*{margin:0;padding:0;box-sizing:border-box}
body{background:var(--bg);color:var(--text);font-family:'Inter',sans-serif;min-height:100vh}
.topbar{position:sticky;top:0;z-index:100;background:rgba(10,10,15,0.95);border-bottom:1px solid var(--border);padding:0 32px;display:flex;align-items:center;gap:16px;height:58px}
.topbar-logo{font-family:'Space Mono',monospace;font-size:11px;font-weight:700;color:var(--accent);letter-spacing:3px;text-transform:uppercase;text-decoration:none}
.topbar-logo span{color:var(--muted)}
.topbar-right{display:flex;align-items:center;gap:10px;margin-left:auto}
.topbar-btn{background:none;border:1px solid var(--border2);border-radius:8px;padding:7px 14px;color:var(--muted);font-family:'Space Mono',monospace;font-size:10px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;transition:all 0.2s}
.topbar-btn:hover{border-color:var(--accent);color:var(--accent)}

.page{max-width:760px;margin:0 auto;padding:36px 24px}
.page-header{margin-bottom:28px}
.page-label{font-family:'Space Mono',monospace;font-size:10px;color:var(--accent);letter-spacing:3px;text-transform:uppercase;margin-bottom:8px}
.page-title{font-family:'Syne',sans-serif;font-size:26px;font-weight:800;color:#fff;margin-bottom:4px;display:flex;align-items:center;gap:10px}
.page-sub{font-size:13px;color:var(--muted)}

.card{background:var(--surface);border:1px solid var(--border2);border-radius:16px;overflow:hidden;margin-bottom:20px}
.card-header{padding:18px 22px;border-bottom:1px solid var(--border2);display:flex;align-items:center;gap:10px}
.card-header-icon{width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0}
.card-header-title{font-family:'Syne',sans-serif;font-size:15px;font-weight:700;color:#fff}
.card-header-sub{font-size:12px;color:var(--muted);margin-top:1px}
.card-body{padding:22px}

.flash{padding:12px 18px;border-radius:10px;font-size:13px;margin-bottom:20px;display:flex;align-items:center;gap:8px}
.flash.success{background:rgba(0,255,136,0.07);border:1px solid rgba(0,255,136,0.25);color:var(--accent4)}
.flash.error{background:rgba(255,68,68,0.07);border:1px solid rgba(255,68,68,0.25);color:#ff8888}

/* Form */
.form-group{margin-bottom:16px}
.form-group:last-child{margin-bottom:0}
.form-label{font-family:'Space Mono',monospace;font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:1px;display:block;margin-bottom:7px}
.form-input,.form-textarea{width:100%;background:var(--surface2);border:1px solid var(--border2);border-radius:10px;padding:12px 16px;color:var(--text);font-family:'Inter',sans-serif;font-size:14px;transition:border-color 0.2s}
.form-input:focus,.form-textarea:focus{outline:none;border-color:var(--accent)}
.form-textarea{min-height:80px;resize:vertical;line-height:1.6}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px}

/* Icon picker mini */
.icon-grid{display:flex;flex-wrap:wrap;gap:6px;margin-top:6px}
.icon-opt{width:38px;height:38px;border-radius:8px;background:var(--surface2);border:1.5px solid var(--border2);display:flex;align-items:center;justify-content:center;font-size:18px;cursor:pointer;transition:all 0.15s}
.icon-opt:hover{border-color:rgba(0,229,255,0.4);transform:scale(1.08)}
.icon-opt.selected{border-color:var(--accent);background:rgba(0,229,255,0.08)}

.color-grid{display:flex;flex-wrap:wrap;gap:7px;margin-top:6px}
.color-opt{width:32px;height:32px;border-radius:50%;cursor:pointer;border:3px solid transparent;transition:all 0.15s}
.color-opt:hover{transform:scale(1.12)}
.color-opt.selected{border-color:#fff;box-shadow:0 0 0 2px rgba(255,255,255,0.3)}

.btn{padding:11px 22px;border:none;border-radius:10px;font-family:'Space Mono',monospace;font-size:11px;font-weight:700;cursor:pointer;transition:all 0.2s;display:inline-flex;align-items:center;gap:7px}
.btn-primary{background:linear-gradient(135deg,var(--accent),var(--accent3));color:#000}
.btn-primary:hover{opacity:0.88;transform:translateY(-1px)}
.btn-danger{background:rgba(255,68,68,0.1);color:#ff7777;border:1px solid rgba(255,68,68,0.25)}
.btn-danger:hover{background:rgba(255,68,68,0.2)}

/* Preview banner */
.comm-preview{border-radius:12px;overflow:hidden;border:1px solid var(--border2);margin-bottom:16px}
.comm-preview-banner{height:60px;position:relative;transition:background 0.3s}
.comm-preview-banner-pat{position:absolute;inset:0;opacity:0.07;background-image:repeating-linear-gradient(45deg,var(--comm) 0,var(--comm) 1px,transparent 0,transparent 50%);background-size:12px 12px}
.comm-preview-body{background:var(--surface);padding:10px 14px;display:flex;align-items:center;gap:10px}
.comm-preview-icon{width:48px;height:48px;border-radius:12px;border:2px solid var(--bg);display:flex;align-items:center;justify-content:center;font-size:22px;margin-top:-24px;flex-shrink:0}
.comm-preview-name{font-family:'Syne',sans-serif;font-size:14px;font-weight:700;color:#fff}
.comm-preview-slug{font-family:'Space Mono',monospace;font-size:9px;color:var(--muted)}

/* Danger zone */
.danger-zone{border:1px solid rgba(255,68,68,0.2);border-radius:12px;padding:18px;background:rgba(255,68,68,0.03)}
.danger-title{font-family:'Space Mono',monospace;font-size:10px;font-weight:700;color:#ff8888;letter-spacing:2px;text-transform:uppercase;margin-bottom:12px}
.bc-bar{background:var(--surface);border-bottom:1px solid var(--border2);padding:8px 32px;position:relative;z-index:5}
.bc-inner{max-width:1400px;margin:0 auto;display:flex;align-items:center;gap:6px;font-family:'Space Mono',monospace;font-size:10px;color:var(--muted);flex-wrap:wrap}
.bc-link{color:var(--muted);text-decoration:none;transition:color 0.15s}.bc-link:hover{color:var(--accent)}
.bc-sep{opacity:0.4}.bc-current{color:var(--text)}
</style>
</head>
<body>

<nav class="topbar">
    <a href="index.php" class="topbar-logo">3D<span>/</span>FÓRUM</a>
    <span style="color:var(--muted);font-size:12px">/ <a href="comunidade.php?slug=<?php echo urlencode($comm['slug']); ?>" style="color:var(--muted);text-decoration:none"><?php echo $comm['icon']; ?> <?php echo sanitize($comm['name']); ?></a> / Gerir</span>
    <div class="topbar-right">
        <a href="comunidade.php?slug=<?php echo urlencode($comm['slug']); ?>" class="topbar-btn">← Voltar à comunidade</a>
    </div>
</nav>
<!-- Breadcrumb -->
<div class="bc-bar">
    <div class="bc-inner">
        <a href="../index.php" class="bc-link">📖 Manual</a>
        <span class="bc-sep">›</span>
        <a href="index.php" class="bc-link">🌐 Fórum</a>
        <span class="bc-sep">›</span><span class="bc-current">⚙️ Gerir Comunidade</span>
    </div>
</div>

<div class="page">
    <div class="page-header">
        <div class="page-title"><?php echo $comm['icon']; ?> <?php echo sanitize($comm['name']); ?></div>
        <div class="page-sub">Configura as definições da tua comunidade</div>
    </div>

    <?php if ($flash): ?>
    <div class="flash <?php echo $flashType; ?>"><?php echo $flashType === 'success' ? '✓' : '⚠️'; ?> <?php echo sanitize($flash); ?></div>
    <?php endif; ?>

    <style>
        .tabs-bar { display: flex; gap: 8px; margin-bottom: 20px; border-bottom: 1px solid var(--border2); padding-bottom: 10px; }
        .tab-btn { background: none; border: 1px solid var(--border2); border-radius: 8px; padding: 10px 20px; color: var(--muted); font-family: 'Space Mono', monospace; font-size: 11px; cursor: pointer; transition: all 0.2s; }
        .tab-btn.active { background: var(--accent); color: #000; border-color: var(--accent); font-weight: 700; }
        .tab-panel { display: none; }
        .tab-panel.active { display: block; }
        .pending-card { background: var(--surface2); border: 1px solid var(--border2); border-radius: 12px; padding: 16px; margin-bottom: 12px; }
    </style>

    <div class="tabs-bar">
        <button class="tab-btn active" onclick="switchTab('info', this)">Definições</button>
        <button class="tab-btn" onclick="switchTab('membros', this)">Membros (<?php echo count($members); ?>)</button>
        <?php
        $pendingPosts = $db->query("SELECT fp.*, u.full_name, u.username FROM forum_posts fp JOIN users u ON u.id=fp.user_id WHERE community_id=$commId AND status='pending' ORDER BY created_at DESC")->fetchAll();
        if (count($pendingPosts) > 0 || !empty($comm['requires_approval'])): ?>
            <button class="tab-btn" onclick="switchTab('pendentes', this)" style="<?php echo count($pendingPosts)>0 ? 'color:var(--accent2); border-color:var(--accent2);' : ''; ?>">
                Pendentes (<?php echo count($pendingPosts); ?>)
            </button>
        <?php endif; ?>
    </div>

    <!-- TAB: INFO -->
    <div class="tab-panel active" id="tab-info">
        <div class="card">
            <div class="card-header">
            <div class="card-header-icon" style="background:rgba(0,229,255,0.08)">✏️</div>
            <div>
                <div class="card-header-title">Informações</div>
                <div class="card-header-sub">Nome, descrição e aparência visual</div>
            </div>
        </div>
        <div class="card-body">
            <!-- Preview -->
            <div class="comm-preview" id="commPreview">
                <div class="comm-preview-banner" id="prevBanner" style="background:linear-gradient(135deg,<?php echo $bannerColor; ?>33,<?php echo $bannerColor; ?>11,#0a0a0f)">
                    <div class="comm-preview-banner-pat" id="prevPat" style="background-image:repeating-linear-gradient(45deg,<?php echo $bannerColor; ?> 0,<?php echo $bannerColor; ?> 1px,transparent 0,transparent 50%);background-size:12px 12px"></div>
                </div>
                <div class="comm-preview-body">
                    <div class="comm-preview-icon" id="prevIcon" style="background:linear-gradient(135deg,<?php echo $bannerColor; ?>44,<?php echo $bannerColor; ?>22)"><?php echo $comm['icon']; ?></div>
                    <div>
                        <div class="comm-preview-name" id="prevName"><?php echo sanitize($comm['name']); ?></div>
                        <div class="comm-preview-slug">c/<?php echo sanitize($comm['slug']); ?></div>
                    </div>
                </div>
            </div>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                <input type="hidden" name="action" value="update_info">
                <input type="hidden" name="icon" id="iconInput" value="<?php echo htmlspecialchars($comm['icon']); ?>">
                <input type="hidden" name="banner_color" id="colorInput" value="<?php echo htmlspecialchars($bannerColor); ?>">

                <div class="form-row" style="margin-bottom:16px">
                    <div class="form-group" style="margin-bottom:0">
                        <label class="form-label">Nome da comunidade</label>
                        <input type="text" name="name" class="form-input" value="<?php echo sanitize($comm['name']); ?>"
                               maxlength="80" oninput="document.getElementById('prevName').textContent=this.value||'Nome'" required>
                    </div>
                    <div class="form-group" style="margin-bottom:0">
                        <label class="form-label">Ícone</label>
                        <div class="icon-grid">
                            <?php foreach(['💬','🖨️','🔧','⚙️','🎨','🧪','💡','📐','🔩','🏗️','🌟','🎯','🚀','🔬','💎','🛠️','📦','🌐','⚡','🔥'] as $ico): ?>
                            <div class="icon-opt <?php echo $comm['icon']===$ico?'selected':''; ?>"
                                 onclick="selIcon('<?php echo $ico; ?>',this)"><?php echo $ico; ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Descrição</label>
                    <textarea name="description" class="form-textarea" maxlength="500" placeholder="Descreve o propósito desta comunidade…"><?php echo sanitize($comm['description'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Cor do banner</label>
                    <div class="color-grid">
                        <?php foreach(['#00e5ff','#ff6b35','#7c3aed','#00ff88','#ff4488','#ffcc00','#00aaff','#ff6600','#aa44ff','#44ffaa'] as $col): ?>
                        <div class="color-opt <?php echo $bannerColor===$col?'selected':''; ?>"
                             style="background:<?php echo $col; ?>"
                             onclick="selColor('<?php echo $col; ?>',this)"></div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-group" style="display:flex;align-items:center;gap:12px;background:rgba(255,255,255,0.03);padding:15px;border-radius:10px;border:1px solid var(--border2);margin-bottom:20px">
                    <input type="checkbox" name="requires_approval" id="reqApp" style="width:18px;height:18px;accent-color:var(--accent)" <?php echo (!empty($comm['requires_approval'])) ? 'checked' : ''; ?>>
                    <label for="reqApp" style="cursor:pointer;flex:1">
                        <div style="font-size:13px;font-weight:700;color:#fff">Moderação de Posts</div>
                        <div style="font-size:11px;color:var(--muted)">Se ativado, novos posts de membros comuns precisarão de aprovação manual.</div>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary">💾 GUARDAR INFORMAÇÕES</button>
            </form>
        </div>
    </div>
    </div>

    <!-- TAB: MEMBROS -->
    <div class="tab-panel" id="tab-membros">
        <!-- Estatísticas rápidas -->
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:20px">
        <?php
        $postCount   = (int)$comm['post_count'];
        $memberCount = (int)$comm['member_count'];
        $replyCount  = 0;
        $pendingCount = count($pendingPosts);
        try {
            $rc = $db->query("SELECT COALESCE(SUM(reply_count),0) FROM forum_posts WHERE community_id=$commId");
            $replyCount = (int)$rc->fetchColumn();
        } catch(Exception $e){}
        ?>
        <?php foreach(array(array('📝','Posts publicados',$postCount),array('👥','Membros',$memberCount),array('💬','Respostas',$replyCount)) as $s): ?>
        <div style="background:var(--surface);border:1px solid var(--border2);border-radius:12px;padding:16px 18px">
            <div style="font-size:20px;margin-bottom:6px"><?php echo $s[0]; ?></div>
            <div style="font-family:'Syne',sans-serif;font-size:22px;font-weight:800;color:var(--accent)"><?php echo number_format($s[2]); ?></div>
            <div style="font-family:'Space Mono',monospace;font-size:9px;color:var(--muted);letter-spacing:1px;text-transform:uppercase;margin-top:2px"><?php echo $s[1]; ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Posts pendentes de aprovação -->
    <div class="card">
        <div class="card-header">
            <div class="card-header-icon" style="background:rgba(255,204,0,0.1)">⏳</div>
            <div style="flex:1">
                <div class="card-header-title">Posts Pendentes de Aprovação</div>
                <div class="card-header-sub">Revê e aprova ou rejeita os posts submetidos pelos membros</div>
            </div>
            <?php if ($pendingCount > 0): ?>
            <span style="background:rgba(255,204,0,0.15);color:#ffcc00;border:1px solid rgba(255,204,0,0.3);font-family:'Space Mono',monospace;font-size:10px;font-weight:700;padding:4px 10px;border-radius:20px"><?php echo $pendingCount; ?> pendente<?php echo $pendingCount!==1?'s':''; ?></span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if (empty($pendingPosts)): ?>
            <div style="text-align:center;padding:32px 0;color:var(--muted)">
                <div style="font-size:32px;margin-bottom:10px">✅</div>
                <div style="font-size:14px">Nenhum post pendente. Tudo em dia!</div>
            </div>
            <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:14px">
                <?php foreach($pendingPosts as $p): ?>
                <div style="background:var(--surface2);border:1px solid rgba(255,204,0,0.15);border-radius:12px;padding:16px 18px">
                    <div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:12px">
                        <div style="flex:1;min-width:0">
                            <div style="font-family:'Syne',sans-serif;font-size:14px;font-weight:700;color:#fff;margin-bottom:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?php echo sanitize($p['title']); ?></div>
                            <div style="display:flex;align-items:center;gap:8px;font-family:'Space Mono',monospace;font-size:10px;color:var(--muted)">
                                <span>@<?php echo sanitize($p['username']); ?></span>
                                <span>·</span>
                                <span><?php echo date('d/m/Y H:i', strtotime($p['created_at'])); ?></span>
                                <?php if ($p['flair']): ?><span style="background:rgba(0,229,255,0.08);color:var(--accent);border:1px solid rgba(0,229,255,0.2);padding:1px 7px;border-radius:10px"><?php echo sanitize($p['flair']); ?></span><?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php if ($p['content']): ?>
                    <div style="font-size:13px;color:var(--muted);line-height:1.6;margin-bottom:12px;max-height:80px;overflow:hidden;-webkit-mask-image:linear-gradient(to bottom,#000 60%,transparent)"><?php echo nl2br(sanitize(mb_substr($p['content'],0,300))); ?></div>
                    <?php endif; ?>
                    <div style="display:flex;gap:8px;flex-wrap:wrap">
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                            <input type="hidden" name="action"  value="approve_post">
                            <input type="hidden" name="post_id" value="<?php echo (int)$p['id']; ?>">
                            <button type="submit" class="btn" style="background:rgba(0,255,136,0.1);color:var(--accent4);border:1px solid rgba(0,255,136,0.25);font-size:10px;padding:8px 16px">✅ APROVAR</button>
                        </form>
                        <form method="POST" style="display:inline;display:flex;gap:6px;align-items:center">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                            <input type="hidden" name="action"  value="reject_post">
                            <input type="hidden" name="post_id" value="<?php echo (int)$p['id']; ?>">
                            <input type="text" name="rejection_reason" placeholder="Motivo (opcional)" style="background:var(--surface);border:1px solid var(--border2);border-radius:8px;padding:7px 12px;color:var(--text);font-family:'Inter',sans-serif;font-size:12px;width:180px">
                            <button type="submit" class="btn" style="background:rgba(255,68,68,0.08);color:#ff8888;border:1px solid rgba(255,68,68,0.25);font-size:10px;padding:8px 16px">❌ REJEITAR</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Gestão de membros e roles (apenas owner) -->
    <?php if ($isOwner || $isGlobMod): ?>
    <div class="card">
        <div class="card-header">
            <div class="card-header-icon" style="background:rgba(124,58,237,0.12)">👥</div>
            <div>
                <div class="card-header-title">Equipa da Comunidade</div>
                <div class="card-header-sub">Atribui ou remove roles de administrador e moderador</div>
            </div>
        </div>
        <div class="card-body">
            <div style="font-family:'Space Mono',monospace;font-size:10px;color:var(--muted);margin-bottom:14px;padding:10px 14px;background:var(--surface2);border-radius:8px;line-height:1.6">
                <strong style="color:var(--text)">Hierarquia:</strong> 👑 Owner (criador) &gt; 🛡️ Admin &gt; 🔰 Moderador &gt; Membro<br>
                Admins e moderadores podem aprovar/rejeitar posts. Apenas o owner pode gerir roles.
            </div>
            <?php if (!empty($members)): ?>
            <div style="display:flex;flex-direction:column;gap:8px">
                <?php foreach($members as $m): ?>
                <?php $roleColors = array('owner'=>'#ff6b35','admin'=>'#00e5ff','moderator'=>'#7c3aed','member'=>'#888899'); $rc=$roleColors[$m['role']]??'#888899'; ?>
                <div style="display:flex;align-items:center;gap:12px;padding:10px 14px;background:var(--surface2);border-radius:10px;border:1px solid var(--border2)">
                    <div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--accent3),var(--accent));display:flex;align-items:center;justify-content:center;font-family:'Space Mono',monospace;font-size:11px;font-weight:700;color:#000;flex-shrink:0;overflow:hidden">
                        <?php if($m['avatar_url']): ?><img src="<?php echo sanitize('../'.$m['avatar_url']); ?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%"><?php else: echo mb_substr($m['full_name'],0,2); endif; ?>
                    </div>
                    <div style="flex:1;min-width:0">
                        <div style="font-size:13px;font-weight:600;color:var(--text)"><?php echo sanitize($m['full_name']); ?></div>
                        <div style="font-family:'Space Mono',monospace;font-size:10px;color:var(--muted)">@<?php echo sanitize($m['username']); ?></div>
                    </div>
                    <span style="font-family:'Space Mono',monospace;font-size:9px;font-weight:700;padding:3px 9px;border-radius:20px;background:<?php echo $rc; ?>22;color:<?php echo $rc; ?>;border:1px solid <?php echo $rc; ?>44;flex-shrink:0">
                        <?php echo $m['role']==='owner'?'👑 OWNER':($m['role']==='admin'?'🛡️ ADMIN':($m['role']==='moderator'?'🔰 MOD':'MEMBRO')); ?>
                    </span>
                    <?php if ($m['role']!=='owner' && ($isOwner || $isGlobMod)): ?>
                    <form method="POST" style="display:flex;gap:6px;align-items:center">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                        <input type="hidden" name="action"  value="set_member_role">
                        <input type="hidden" name="user_id" value="<?php echo (int)$m['id']; ?>">
                        <select name="new_role" style="background:var(--surface);border:1px solid var(--border2);border-radius:7px;padding:6px 10px;color:var(--text);font-family:'Space Mono',monospace;font-size:10px;cursor:pointer">
                            <option value="member"    <?php echo $m['role']==='member'?   'selected':''; ?>>Membro</option>
                            <option value="moderator" <?php echo $m['role']==='moderator'?'selected':''; ?>>Moderador</option>
                            <option value="admin"     <?php echo $m['role']==='admin'?    'selected':''; ?>>Admin</option>
                        </select>
                        <button type="submit" class="btn btn-primary" style="font-size:10px;padding:7px 14px">Guardar</button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div style="text-align:center;padding:20px 0;color:var(--muted);font-size:13px">Nenhum membro ainda.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- TAB: PENDENTES -->
    <div class="tab-panel" id="tab-pendentes">
        <div class="card">
            <div class="card-header">
                <div class="card-header-icon" style="background:rgba(255,107,53,0.1)">⏳</div>
                <div>
                    <div class="card-header-title">Posts Aguardando Aprovação</div>
                    <div class="card-header-sub">Estes posts só aparecerão na comunidade após serem aceites</div>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($pendingPosts)): ?>
                    <div class="empty-state">Nenhum post pendente de momento.</div>
                <?php else: ?>
                    <?php foreach ($pendingPosts as $pp): ?>
                    <div class="pending-card">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10px">
                            <div>
                                <div style="font-weight:700; color:#fff; font-size:15px"><?php echo sanitize($pp['title']); ?></div>
                                <div style="font-size:11px; color:var(--muted)">Por @<?php echo sanitize($pp['username']); ?> em <?php echo date('d/m/Y H:i', strtotime($pp['created_at'])); ?></div>
                            </div>
                            <div style="display:flex; gap:8px">
                                <form method="POST" style="margin:0">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                    <input type="hidden" name="action" value="approve_post">
                                    <input type="hidden" name="post_id" value="<?php echo $pp['id']; ?>">
                                    <button type="submit" class="btn btn-primary" style="padding:6px 12px; font-size:10px; background:var(--accent4); color:#000">APROVAR</button>
                                </form>
                                <form method="POST" style="margin:0">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                    <input type="hidden" name="action" value="reject_post">
                                    <input type="hidden" name="post_id" value="<?php echo $pp['id']; ?>">
                                    <button type="submit" class="btn btn-danger" style="padding:6px 12px; font-size:10px" onclick="return confirm('Rejeitar este post?')">REJEITAR</button>
                                </form>
                            </div>
                        </div>
                        <?php if($pp['content']): ?>
                            <div style="font-size:13px; color:var(--muted); line-height:1.5; background:rgba(0,0,0,0.2); padding:10px; border-radius:8px"><?php echo nl2br(sanitize(mb_substr($pp['content'], 0, 300))); ?>...</div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<script>
function switchTab(tabId, btn) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + tabId).classList.add('active');
    btn.classList.add('active');
}
function selIcon(icon, el) {
    document.querySelectorAll('.icon-opt').forEach(function(x){ x.classList.remove('selected'); });
    el.classList.add('selected');
    document.getElementById('iconInput').value = icon;
    document.getElementById('prevIcon').textContent = icon;
}

function selColor(color, el) {
    document.querySelectorAll('.color-opt').forEach(function(x){ x.classList.remove('selected'); });
    el.classList.add('selected');
    document.getElementById('colorInput').value = color;
    var b = document.getElementById('prevBanner');
    var p = document.getElementById('prevPat');
    var i = document.getElementById('prevIcon');
    if (b) b.style.background = 'linear-gradient(135deg,'+color+'33,'+color+'11,#0a0a0f)';
    if (p) p.style.backgroundImage = 'repeating-linear-gradient(45deg,'+color+' 0,'+color+' 1px,transparent 0,transparent 50%)';
    if (i) i.style.background = 'linear-gradient(135deg,'+color+'44,'+color+'22)';
}
</script>
</body>
</html>