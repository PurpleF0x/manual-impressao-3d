<?php
ob_start();
/**
 * forum/criar_post.php — Criar novo post
 */
require_once __DIR__ . '/../includes/functions.php';
// Helper: path do avatar relativo ao forum/
function avPath($url) {
    if (!$url) return '';
    if (strpos($url,'http')===0) return $url;
    return '../' . ltrim($url, '/');
}

if (!isLoggedIn()) { header('Location: ../login.php'); exit; }
$currentUser = getCurrentUser();
$db  = getDB();

// Comunidade pré-selecionada via GET
$preSlug = trim($_GET['comm'] ?? '');
$preComm = null;
if ($preSlug) {
    $ps = $db->prepare("SELECT id,name,slug,icon,banner_color FROM forum_communities WHERE slug=? AND is_active=1");
    $ps->execute(array($preSlug));
    $preComm = $ps->fetch();
}

// Todas as comunidades (para o select)
$allComms = $db->query("SELECT id,name,slug,icon FROM forum_communities WHERE is_active=1 ORDER BY name ASC")->fetchAll();

// Comunidades do utilizador (sidebar)
$myCommunities = array();
$myc = $db->prepare("
    SELECT fc.id, fc.name, fc.slug, fc.icon, fc.banner_color, fc.member_count
    FROM forum_memberships fm JOIN forum_communities fc ON fc.id=fm.community_id
    WHERE fm.user_id=? AND fc.is_active=1 ORDER BY fm.joined_at DESC LIMIT 8
");
$myc->execute(array((int)$currentUser['id']));
$myCommunities = $myc->fetchAll();

$recentComms = isset($_SESSION['forum_recent']) ? $_SESSION['forum_recent'] : array();

$csrf = generateCSRFToken();
$error = '';
$success = false;

// ── POST ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? null)) {
    $commId  = (int)($_POST['community_id'] ?? 0);
    $title   = trim($_POST['title']   ?? '');
    $content = trim($_POST['content'] ?? '');
    $flair   = trim($_POST['flair']   ?? '');
    $imageUrl  = null;
    $imageType = null;

    // Processar Upload de Imagem
    if (isset($_FILES['post_image']) && $_FILES['post_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['post_image'];
        $allowedTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp'
        ];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);

        if ($file['size'] > 5 * 1024 * 1024) {
            $error = 'A imagem nÃ£o pode ultrapassar 5MB.';
        } elseif (isset($allowedTypes[$mime])) {
            $uploadDir = __DIR__ . '/../uploads/posts/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            $ext = $allowedTypes[$mime];
            $fileName = 'post_' . time() . '_' . uniqid() . '.' . $ext;
            $dest = $uploadDir . $fileName;

            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $imageUrl = 'uploads/posts/' . $fileName;
                $imageType = 'upload';
            } else {
                $error = 'NÃ£o foi possÃ­vel guardar a imagem. Tenta novamente.';
            }
        } else {
            $error = 'Formato de imagem invÃ¡lido. Usa JPG, PNG, GIF ou WebP.';
        }
    } elseif (isset($_FILES['post_image']) && $_FILES['post_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $error = 'NÃ£o foi possÃ­vel carregar a imagem. Confirma o tamanho e tenta novamente.';
    }

    // Fallback: Imagem por URL se não houve upload
    if (!$imageUrl) {
        $imgUrlInput = trim($_POST['image_url'] ?? '');
        if ($imgUrlInput && filter_var($imgUrlInput, FILTER_VALIDATE_URL)) {
            $imageUrl  = $imgUrlInput;
            $imageType = 'url';
        }
    }
    $validFlairs = array('','showcase','tutorial','noticia','pergunta','projeto','ajuda','discussao_tecnica','spoiler');
    if (!in_array($flair, $validFlairs)) $flair = '';

    try { $db->exec("ALTER TABLE forum_posts ADD COLUMN IF NOT EXISTS image_url VARCHAR(500) DEFAULT NULL"); } catch(Exception $e){}
    try { $db->exec("ALTER TABLE forum_posts ADD COLUMN IF NOT EXISTS image_type ENUM('upload','url') DEFAULT NULL"); } catch(Exception $e){}

    // Proteção anti-duplo submit
    $submitToken = $_POST['submit_token'] ?? '';
    if ($submitToken && isset($_SESSION['last_submit_token']) && $_SESSION['last_submit_token'] === $submitToken) {
        // Duplo submit — redirecionar sem erro
        header('Location: index.php'); exit;
    }
    if ($submitToken) $_SESSION['last_submit_token'] = $submitToken;

    if (!$error && $commId < 1) $error = 'Seleciona uma comunidade.';
    elseif (mb_strlen($title) < 3)  $error = 'O título precisa de ter pelo menos 3 caracteres.';
    elseif (mb_strlen($title) > 300) $error = 'O título não pode ter mais de 300 caracteres.';
    else {
        // Verificar comunidade
        $cs = $db->prepare("SELECT id,slug FROM forum_communities WHERE id=? AND is_active=1");
        $cs->execute(array($commId));
        $comm = $cs->fetch();
        if (!$comm) {
            $error = 'Comunidade inválida.';
        } else {
            // Garantir colunas necessárias para moderação
            try { $db->exec("ALTER TABLE forum_posts ADD COLUMN IF NOT EXISTS flair VARCHAR(20) DEFAULT NULL"); } catch(Exception $e){}
            try { $db->exec("ALTER TABLE forum_posts ADD COLUMN IF NOT EXISTS status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved'"); } catch(Exception $e){}
            try { $db->exec("ALTER TABLE forum_posts ADD COLUMN IF NOT EXISTS moderated_by INT NULL"); } catch(Exception $e){}
            try { $db->exec("ALTER TABLE forum_posts ADD COLUMN IF NOT EXISTS moderated_at DATETIME NULL"); } catch(Exception $e){}
            try { $db->exec("ALTER TABLE forum_posts ADD COLUMN IF NOT EXISTS rejection_reason VARCHAR(500) NULL"); } catch(Exception $e){}
            try { $db->exec("ALTER TABLE forum_memberships MODIFY COLUMN role ENUM('owner','admin','moderator','member') NOT NULL DEFAULT 'member'"); } catch(Exception $e){}
            try { $db->exec("CREATE TABLE IF NOT EXISTS forum_moderation_log (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, post_id INT NOT NULL, moderator_id INT NOT NULL, action ENUM('approved','rejected') NOT NULL, reason VARCHAR(500) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_log_post (post_id), INDEX idx_log_mod (moderator_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"); } catch(Exception $e){}

            // Rate limit: máx 3 posts pendentes por utilizador por comunidade por hora
            $rl = $db->prepare("SELECT COUNT(*) FROM forum_posts WHERE user_id=? AND community_id=? AND status='pending' AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
            $rl->execute(array((int)$currentUser['id'], $commId));
            if ((int)$rl->fetchColumn() >= 3) {
                $error = 'Tens demasiados posts pendentes nesta comunidade. Aguarda que a equipa os reveja antes de publicares mais.';
            } else {
                // Verificar se é staff ou moderador desta comunidade → aprovação automática
                $isStaff = in_array($currentUser['role'] ?? '', array('admin','moderator','master'));
                $isCommMod = false;
                if (!$isStaff) {
                    $modCheck = $db->prepare("SELECT role FROM forum_memberships WHERE user_id=? AND community_id=? AND role IN ('owner','admin','moderator')");
                    $modCheck->execute(array((int)$currentUser['id'], $commId));
                    if ($modCheck->fetch()) $isCommMod = true;
                }

                // Aprovação automática se for staff ou se a comunidade não exigir aprovação
                if ($isStaff || $isCommMod || empty($comm['requires_approval'])) {
                    $postStatus = 'approved';
                } else {
                    $postStatus = 'pending';
                }

                $ins = $db->prepare("INSERT INTO forum_posts (community_id,user_id,title,content,type,flair,image_url,image_type,status) VALUES (?,?,?,?,'text',?,?,?,?)");
                $ins->execute(array($commId,(int)$currentUser['id'],$title,$content?:null,$flair?:null,$imageUrl,$imageType,$postStatus));
                $newPostId = (int)$db->lastInsertId();

                if ($postStatus === 'approved') {
                    // Atribuição imediata de Karma/XP e Moedas apenas se for aprovado (moderadores)
                    addXP((int)$currentUser['id'], 15, "Publicou novo post #$newPostId", 10);

                    $db->prepare("UPDATE forum_communities SET post_count=post_count+1 WHERE id=?")->execute(array($commId));
                    ob_end_clean();
                    header('Location: topico.php?id='.$newPostId);
                    exit;
                } else {
                    // Post pendente — será recompensado apenas após aprovação em api/forum.php
                    $_SESSION['forum_flash'] = 'O teu post foi submetido e aguarda aprovação pela equipa da comunidade.';
                    ob_end_clean();
                    header('Location: comunidade.php?slug='.$comm['slug']);
                    exit;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
    <link rel="icon" type="image/x-icon"  href="../favicons/favicon-forum.ico">
    <link rel="icon" type="image/svg+xml" href="../favicons/favicon-forum.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="../favicons/favicon-forum-32.png">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Criar post — Fórum 3D</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800;900&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root{--bg:#0a0a0f;--surface:#111118;--surface2:#1a1a26;--surface3:#222235;--accent:#00e5ff;--accent2:#ff6b35;--accent3:#7c3aed;--accent4:#00ff88;--text:#e8e8f0;--muted:#888899;--border:rgba(0,229,255,0.1);--border2:rgba(255,255,255,0.06)}
*{margin:0;padding:0;box-sizing:border-box}
body{background:var(--bg);color:var(--text);font-family:'Inter',sans-serif;min-height:100vh}
body::before{content:'';position:fixed;inset:0;pointer-events:none;background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.03'/%3E%3C/svg%3E");opacity:0.4}

.topbar{position:sticky;top:0;z-index:100;background:rgba(10,10,15,0.92);backdrop-filter:blur(16px);border-bottom:1px solid var(--border);padding:0 32px;display:flex;align-items:center;gap:16px;height:58px}
.topbar-logo{font-family:'Space Mono',monospace;font-size:11px;font-weight:700;color:var(--accent);letter-spacing:3px;text-transform:uppercase;text-decoration:none}
.topbar-logo span{color:var(--muted)}
.topbar-right{display:flex;align-items:center;gap:10px;margin-left:auto}
.topbar-btn{background:none;border:1px solid var(--border2);border-radius:8px;padding:7px 14px;color:var(--muted);font-family:'Space Mono',monospace;font-size:10px;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:6px;transition:all 0.2s}
.topbar-btn:hover{border-color:var(--accent);color:var(--accent);background:rgba(0,229,255,0.05)}
.topbar-av{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--accent3),var(--accent));display:flex;align-items:center;justify-content:center;font-family:'Space Mono',monospace;font-size:11px;font-weight:700;color:#000;overflow:hidden;text-decoration:none;flex-shrink:0}
.topbar-av img{width:100%;height:100%;object-fit:cover;border-radius:50%}

.layout{max-width:1000px;margin:0 auto;padding:32px 24px;display:grid;grid-template-columns:1fr 260px;gap:24px;position:relative;z-index:1}

/* Formulário */
.form-card{background:var(--surface);border:1px solid var(--border2);border-radius:16px;overflow:hidden}
.form-card-header{padding:22px 26px 18px;border-bottom:1px solid var(--border2)}
.form-card-title{font-family:'Syne',sans-serif;font-size:20px;font-weight:800;color:#fff;margin-bottom:4px}
.form-card-sub{font-size:12px;color:var(--muted)}
.form-body{padding:24px 26px;display:flex;flex-direction:column;gap:20px}

.form-group{display:flex;flex-direction:column;gap:7px}
.form-label{font-family:'Space Mono',monospace;font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:1px;display:flex;align-items:center;justify-content:space-between}
.form-label span{color:var(--accent2);font-size:9px}
.form-input,.form-select,.form-textarea{background:var(--surface2);border:1px solid var(--border2);border-radius:10px;padding:12px 16px;color:var(--text);font-family:'Inter',sans-serif;font-size:14px;transition:border-color 0.2s;width:100%}
.form-input:focus,.form-select:focus,.form-textarea:focus{outline:none;border-color:var(--accent)}
.form-input::placeholder,.form-textarea::placeholder{color:var(--muted);opacity:0.6}
.form-select{appearance:none;cursor:pointer;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%23888899' stroke-width='1.5' fill='none'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 14px center;padding-right:40px}
.form-select option{background:var(--surface2)}
.form-textarea{min-height:200px;resize:vertical;line-height:1.7;font-size:14px}

.char-hint{font-family:'Space Mono',monospace;font-size:10px;color:var(--muted);text-align:right;margin-top:4px}

/* Preview comunidade */
.comm-preview{display:none;align-items:center;gap:10px;padding:12px 14px;background:var(--surface2);border:1px solid var(--border2);border-radius:10px;margin-top:8px}
.comm-preview.show{display:flex}
.comm-preview-icon{width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;background:rgba(0,229,255,0.08)}
.comm-preview-name{font-size:13px;font-weight:600;color:var(--text)}
.comm-preview-link{font-family:'Space Mono',monospace;font-size:10px;color:var(--accent);margin-left:auto;text-decoration:none;transition:opacity 0.2s}
.comm-preview-link:hover{opacity:0.7}

.submit-btn{background:linear-gradient(135deg,var(--accent),var(--accent3));border:none;border-radius:10px;padding:13px 28px;color:#000;font-family:'Space Mono',monospace;font-size:12px;font-weight:700;cursor:pointer;letter-spacing:1px;transition:opacity 0.2s;align-self:flex-start}
.submit-btn:hover{opacity:0.88}
.submit-btn:disabled{opacity:0.4;cursor:not-allowed}
.cancel-btn{background:none;border:1px solid var(--border2);border-radius:10px;padding:13px 20px;color:var(--muted);font-family:'Space Mono',monospace;font-size:12px;cursor:pointer;text-decoration:none;transition:all 0.2s}
.cancel-btn:hover{border-color:var(--accent);color:var(--accent)}

.error-box{background:rgba(255,68,68,0.06);border:1px solid rgba(255,68,68,0.25);border-radius:10px;padding:14px 16px;font-size:13px;color:#ff8888}

/* Guidelines */
.guide-card{background:var(--surface);border:1px solid var(--border2);border-radius:14px;padding:18px;margin-bottom:14px}
.guide-title{font-family:'Space Mono',monospace;font-size:9px;font-weight:700;color:var(--muted);letter-spacing:2px;text-transform:uppercase;margin-bottom:12px}
.guide-item{display:flex;gap:10px;font-size:12px;color:var(--muted);line-height:1.5;margin-bottom:10px}
.guide-item:last-child{margin-bottom:0}
.guide-item-icon{flex-shrink:0;font-size:14px}

/* Sidebar comunidades */
.sidebar-card{background:var(--surface);border:1px solid var(--border2);border-radius:14px;margin-bottom:14px;overflow:hidden}
.sc-header{padding:14px 16px 10px;border-bottom:1px solid var(--border2);display:flex;align-items:center;justify-content:space-between}
.sc-title{font-family:'Space Mono',monospace;font-size:9px;font-weight:700;color:var(--muted);letter-spacing:2px;text-transform:uppercase}
.sc-comm-row{display:flex;align-items:center;gap:10px;padding:10px 16px;border-bottom:1px solid var(--border2);text-decoration:none;transition:background 0.2s;cursor:pointer}
.sc-comm-row:last-child{border-bottom:none}
.sc-comm-row:hover{background:var(--surface2)}
.sc-comm-icon{width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0}
.sc-comm-name{font-size:12px;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sc-comm-meta{font-family:'Space Mono',monospace;font-size:9px;color:var(--muted);margin-top:1px}

@media(max-width:800px){
    .layout{grid-template-columns:1fr;padding:16px}
    .topbar{padding:10px 16px;height:auto;min-height:58px;flex-wrap:wrap;gap:10px}
}
@media(max-width:560px){
    .topbar{padding:10px 12px;gap:8px}
    .topbar-logo{letter-spacing:2px;font-size:10px}
    .topbar-right{gap:8px;margin-left:auto}
    .topbar-btn{padding:7px 10px;font-size:9px}
    .topbar-av{width:30px;height:30px}
}

/* Flair grid */
.flair-grid { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 6px; }
.flair-opt {
    background: var(--surface2); border: 1.5px solid var(--border2);
    border-radius: 20px; padding: 7px 14px; cursor: pointer;
    font-family: 'Inter', sans-serif; font-size: 12px; color: var(--muted);
    display: inline-flex; align-items: center; gap: 6px;
    transition: all 0.15s;
}
.flair-opt:hover { border-color: rgba(0,229,255,0.4); color: var(--text); background: rgba(0,229,255,0.05); }
.flair-opt.selected { border-color: var(--accent); color: var(--accent); background: rgba(0,229,255,0.08); font-weight: 600; }
.flair-spoiler { }
.flair-spoiler.selected { border-color: #ffcc00; color: #ffcc00; background: rgba(255,204,0,0.08); }

</style>
</head>
<body>

<nav class="topbar">
    <a href="index.php" class="topbar-logo">3D<span>/</span>FÓRUM</a>
    <div class="topbar-right">
        <a href="index.php" class="topbar-btn">← Fórum</a>
        <a href="perfil.php?id=<?php echo (int)($_SESSION['user_id'] ?? 0); ?>" class="topbar-av">
            <?php $av=$currentUser['avatar_url']??''; if($av): ?><img src="<?php echo sanitize(avPath($av)); ?>" alt=""><?php else: echo mb_substr($currentUser['full_name'],0,2); endif; ?>
        </a>
    </div>
</nav>

<div class="layout">
<main>
    <div class="form-card">
        <div class="form-card-header">
            <div class="form-card-title">✏️ Criar novo post</div>
            <div class="form-card-sub">Partilha com a comunidade</div>
        </div>
        <div style="background:rgba(0,229,255,0.04);border-bottom:1px solid var(--border2);padding:12px 26px;font-size:13px;color:var(--muted);display:flex;align-items:center;gap:10px">
            🖨️ <span><strong style="color:var(--text)">Este fórum é dedicado a impressão 3D.</strong> Posts devem ser sobre impressoras, filamentos, slicers ou projetos. Conteúdo fora do tema será removido.</span>
        </div>
        <form method="POST" class="form-body" id="postForm" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
        <input type="hidden" name="submit_token" value="<?php echo bin2hex(random_bytes(16)); ?>">

            <?php if ($error): ?>
            <div class="error-box">⚠️ <?php echo sanitize($error); ?></div>
            <?php endif; ?>

            <!-- Comunidade -->
            <div class="form-group">
                <label class="form-label">Comunidade <span>OBRIGATÓRIO</span></label>
                <select name="community_id" id="commSelect" class="form-select" onchange="onCommChange(this)" required>
                    <option value="">— Escolhe uma comunidade —</option>
                    <?php foreach ($allComms as $c): ?>
                    <option value="<?php echo $c['id']; ?>"
                        data-icon="<?php echo htmlspecialchars($c['icon']); ?>"
                        data-name="<?php echo htmlspecialchars($c['name']); ?>"
                        data-slug="<?php echo htmlspecialchars($c['slug']); ?>"
                        <?php echo ($preComm && (int)$preComm['id']===(int)$c['id']) ? 'selected' : ''; ?>>
                        <?php echo $c['icon']; ?> <?php echo sanitize($c['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <div class="comm-preview" id="commPreview">
                    <div class="comm-preview-icon" id="cpIcon"></div>
                    <div class="comm-preview-name" id="cpName"></div>
                    <a href="#" class="comm-preview-link" id="cpLink" target="_blank">Ver comunidade →</a>
                </div>
            </div>

            <!-- Título -->
            <div class="form-group">
                <label class="form-label">Título <span>OBRIGATÓRIO</span></label>
                <input type="text" name="title" class="form-input" id="titleInput"
                    placeholder="Um título claro e descritivo…"
                    maxlength="300" required
                    value="<?php echo sanitize($_POST['title'] ?? ''); ?>"
                    oninput="document.getElementById('titleChars').textContent=this.value.length">
                <div class="char-hint"><span id="titleChars"><?php echo mb_strlen($_POST['title']??''); ?></span>/300</div>
            </div>

            <!-- Conteúdo -->
            <div class="form-group">
                <label class="form-label">Conteúdo <span style="color:var(--muted)">OPCIONAL</span></label>
                <textarea name="content" class="form-textarea" id="contentTA"
                    placeholder="Descreve o teu post com mais detalhe… (opcional)"
                    maxlength="10000"
                    oninput="document.getElementById('contentChars').textContent=this.value.length"><?php echo sanitize($_POST['content'] ?? ''); ?></textarea>
                <div class="char-hint"><span id="contentChars"><?php echo mb_strlen($_POST['content']??''); ?></span>/10000</div>
            </div>


            <!-- Flair / Tag de conteúdo -->
            <div class="form-group">
                <label class="form-label">Tag de Conteúdo <span style="color:var(--muted);font-size:9px">OPCIONAL</span></label>
                <div class="flair-grid" id="flairGrid">
                    <input type="hidden" name="flair" id="flairInput" value="">
                    <button type="button" class="flair-opt" data-flair="" onclick="selectFlair('',this)">
                        <span>🏷️</span> Sem tag
                    </button>
                    <button type="button" class="flair-opt" data-flair="pergunta" onclick="selectFlair('pergunta',this)">
                        <span>❓</span> Pergunta
                    </button>
                    <button type="button" class="flair-opt" data-flair="tutorial" onclick="selectFlair('tutorial',this)">
                        <span>📖</span> Tutorial
                    </button>
                    <button type="button" class="flair-opt" data-flair="projeto" onclick="selectFlair('projeto',this)">
                        <span>🏗️</span> Projeto
                    </button>
                    <button type="button" class="flair-opt" data-flair="ajuda" onclick="selectFlair('ajuda',this)">
                        <span>🆘</span> Ajuda
                    </button>
                    <button type="button" class="flair-opt" data-flair="noticia" onclick="selectFlair('noticia',this)">
                        <span>📰</span> Notícia
                    </button>
                    <button type="button" class="flair-opt" data-flair="discussao_tecnica" onclick="selectFlair('discussao_tecnica',this)">
                        <span>🔬</span> Discussão Técnica
                    </button>
                    <button type="button" class="flair-opt" data-flair="showcase" onclick="selectFlair('showcase',this)">
                        <span>📸</span> Showcase
                    </button>
                    <button type="button" class="flair-opt flair-spoiler" data-flair="spoiler" onclick="selectFlair('spoiler',this)">
                        <span>⚠️</span> Spoiler
                    </button>
                </div>
                <div id="flairWarning" style="display:none;margin-top:8px;padding:10px 14px;border-radius:8px;font-size:12px"></div>
            </div>

            <!-- Upload de Imagem -->
            <div class="form-group">
                <label class="form-label">Upload de Imagem <span style="color:var(--muted);font-size:9px">OPCIONAL</span></label>
                <input type="file" name="post_image" id="fileInput" class="form-input" accept="image/*" onchange="previewFile(this)">
                <div id="filePreviewWrap" style="display:none;margin-top:10px;position:relative">
                    <img id="filePreview" style="max-width:100%;max-height:260px;border-radius:10px;border:1px solid var(--border2);object-fit:contain">
                    <button type="button" onclick="clearFileImg()" style="position:absolute;top:8px;right:8px;background:rgba(0,0,0,0.7);border:none;border-radius:50%;width:28px;height:28px;color:#fff;cursor:pointer;font-size:14px;line-height:1">×</button>
                </div>
            </div>

            <div style="text-align: center; margin: 10px 0; color: var(--muted); font-size: 11px;">OU</div>

            <!-- Imagem via URL -->
            <div class="form-group">
                <label class="form-label">Imagem por URL <span style="color:var(--muted);font-size:9px">OPCIONAL</span></label>
                <input type="text" name="image_url" id="imgUrlInput" class="form-input"
                    placeholder="URL direto da imagem — ex: https://i.imgur.com/abc.jpg"
                    oninput="previewUrl(this.value)">
                <div style="margin-top:6px;font-family:'Space Mono',monospace;font-size:10px;color:var(--muted)">
                    💡 Se não quiseres carregar do teu PC, cola um link direto (.jpg .png .gif .webp).
                </div>
                <div id="imgUrlPreviewWrap" style="display:none;margin-top:10px;position:relative">
                    <img id="imgUrlPreview" style="max-width:100%;max-height:260px;border-radius:10px;border:1px solid var(--border2);object-fit:contain">
                    <button type="button" onclick="clearUrlImg()" style="position:absolute;top:8px;right:8px;background:rgba(0,0,0,0.7);border:none;border-radius:50%;width:28px;height:28px;color:#fff;cursor:pointer;font-size:14px;line-height:1">×</button>
                </div>
            </div>

            <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
                <button type="submit" class="submit-btn" id="submitBtn">📨 SUBMETER POST</button>
                <a href="<?php echo $preComm ? 'comunidade.php?slug='.urlencode($preComm['slug']) : 'index.php'; ?>" class="cancel-btn">Cancelar</a>
            </div>
            <div style="margin-top:14px;padding:12px 16px;background:rgba(124,58,237,0.07);border:1px solid rgba(124,58,237,0.2);border-radius:10px;font-size:12px;color:var(--muted);display:flex;align-items:flex-start;gap:8px">
                <span style="font-size:15px;flex-shrink:0">🛡️</span>
                <span>O teu post ficará <strong style="color:var(--text)">pendente de aprovação</strong> pela equipa da comunidade antes de ser publicado. Receberás feedback assim que for revisto.</span>
            </div>
        </form>
    </div>
</main>

<aside>
    <!-- Guidelines -->
    <div class="guide-card">
        <div class="guide-title">📋 Boas práticas</div>
        <div class="guide-item"><span class="guide-item-icon">✅</span>Usa um título claro que descreva bem o assunto.</div>
        <div class="guide-item"><span class="guide-item-icon">✅</span>Escolhe a comunidade mais relevante para o teu post.</div>
        <div class="guide-item"><span class="guide-item-icon">✅</span>Inclui detalhes suficientes para que possas receber ajuda.</div>
        <div class="guide-item"><span class="guide-item-icon">🚫</span>Não publiques spam ou conteúdo ofensivo.</div>
        <div class="guide-item"><span class="guide-item-icon">🚫</span>Não repitas posts que já existam.</div>
    </div>

    <!-- Visitadas recentemente -->
    <?php if (!empty($recentComms)): ?>
    <div class="sidebar-card">
        <div class="sc-header"><span class="sc-title">🕐 Visitadas recentemente</span></div>
        <?php foreach (array_slice($recentComms,0,5) as $rc): ?>
        <div class="sc-comm-row" onclick="selectComm(<?php echo (int)$rc['id']; ?>)">
            <div class="sc-comm-icon" style="background:rgba(0,229,255,0.08)"><?php echo $rc['icon']; ?></div>
            <div style="flex:1;min-width:0">
                <div class="sc-comm-name"><?php echo sanitize($rc['name']); ?></div>
                <div class="sc-comm-meta">c/<?php echo sanitize($rc['slug']); ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Minhas comunidades -->
    <?php if (!empty($myCommunities)): ?>
    <div class="sidebar-card">
        <div class="sc-header"><span class="sc-title">⭐ As minhas comunidades</span></div>
        <?php foreach ($myCommunities as $mc): ?>
        <div class="sc-comm-row" onclick="selectComm(<?php echo (int)$mc['id']; ?>)">
            <div class="sc-comm-icon" style="background:linear-gradient(135deg,<?php echo htmlspecialchars($mc['banner_color']); ?>22,<?php echo htmlspecialchars($mc['banner_color']); ?>44)"><?php echo $mc['icon']; ?></div>
            <div style="flex:1;min-width:0">
                <div class="sc-comm-name"><?php echo sanitize($mc['name']); ?></div>
                <div class="sc-comm-meta"><?php echo number_format($mc['member_count']); ?> membros</div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</aside>
</div>

<script>
// Mostrar preview da comunidade selecionada
function onCommChange(sel) {
    var opt = sel.options[sel.selectedIndex];
    var preview = document.getElementById('commPreview');
    if (!opt.value) { preview.classList.remove('show'); return; }
    document.getElementById('cpIcon').textContent = opt.dataset.icon || '💬';
    document.getElementById('cpName').textContent = opt.dataset.name || '';
    var cpLink = document.getElementById('cpLink');
    cpLink.href = 'comunidade.php?slug=' + encodeURIComponent(opt.dataset.slug || '');
    preview.classList.add('show');
}

// Clicar numa comunidade da sidebar seleciona-a no select
function selectComm(commId) {
    var sel = document.getElementById('commSelect');
    for (var i=0; i<sel.options.length; i++) {
        if (parseInt(sel.options[i].value) === commId) {
            sel.selectedIndex = i;
            onCommChange(sel);
            sel.scrollIntoView({behavior:'smooth',block:'center'});
            break;
        }
    }
}


function previewUrl(url) {
    // ... código existente ...
}

function previewFile(input) {
    var wrap = document.getElementById('filePreviewWrap');
    var img = document.getElementById('filePreview');
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            img.src = e.target.result;
            wrap.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    } else {
        wrap.style.display = 'none';
    }
}

function clearFileImg() {
    document.getElementById('fileInput').value = '';
    document.getElementById('filePreviewWrap').style.display = 'none';
}

function clearUrlImg() {
    document.getElementById('imgUrlInput').value = '';
    document.getElementById('imgUrlPreviewWrap').style.display = 'none';
    document.getElementById('imgUrlError').style.display = 'none';
}

// Init se há comunidade pré-selecionada
(function(){
    var sel = document.getElementById('commSelect');
    if (sel && sel.value) onCommChange(sel);
})();

// ── Selecionar flair ──────────────────────────────────────────
function selectFlair(flair, el) {
    document.querySelectorAll('.flair-opt').forEach(function(b){ b.classList.remove('selected'); });
    el.classList.add('selected');
    document.getElementById('flairInput').value = flair;
    var warn = document.getElementById('flairWarning');
    if (!warn) return;
    if (flair === 'spoiler') {
        warn.style.display  = 'block';
        warn.style.background = 'rgba(255,204,0,0.07)';
        warn.style.border   = '1px solid rgba(255,204,0,0.3)';
        warn.style.color    = '#ffcc00';
        warn.innerHTML = '⚠️ <strong>Spoiler</strong> — O conteúdo ficará oculto até o leitor clicar para revelar.';
    } else {
        warn.style.display = 'none';
    }
}

// Prevenir duplo submit
(function(){
    var form = document.getElementById('postForm');
    if (form) {
        form.addEventListener('submit', function() {
            var btn = document.getElementById('submitBtn');
            if (btn) { btn.disabled = true; btn.textContent = 'A publicar…'; }
        });
    }
})();
</script>
</body>
</html>
