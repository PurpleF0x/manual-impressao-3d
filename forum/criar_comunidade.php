<?php
/**
 * forum/criar_comunidade.php — Criar nova comunidade
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
$db = getDB();

$csrf  = generateCSRFToken();
$error = '';

// ── POST ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? null)) {
    $name        = trim($_POST['name']        ?? '');
    $slug        = trim($_POST['slug']        ?? '');
    $description = trim($_POST['description'] ?? '');
    $icon        = trim($_POST['icon']        ?? '💬');
    $bannerColor = trim($_POST['banner_color'] ?? '#00e5ff');

    // Sanitizar slug
    $slug = strtolower(preg_replace('/[^a-z0-9_-]/i', '', $slug));

    if (mb_strlen($name) < 3)         $error = 'O nome precisa de ter pelo menos 3 caracteres.';
    elseif (mb_strlen($name) > 80)    $error = 'O nome não pode ter mais de 80 caracteres.';
    elseif (mb_strlen($slug) < 3)     $error = 'O slug precisa de ter pelo menos 3 caracteres.';
    elseif (mb_strlen($slug) > 50)    $error = 'O slug não pode ter mais de 50 caracteres.';
    elseif (!preg_match('/^[a-z0-9_-]+$/', $slug)) $error = 'O slug só pode ter letras, números, _ e -.';
    elseif (mb_strlen($description) > 500) $error = 'A descrição não pode ter mais de 500 caracteres.';
    else {
        // Verificar slug único
        $ex = $db->prepare("SELECT id FROM forum_communities WHERE slug=?");
        $ex->execute(array($slug));
        if ($ex->fetch()) {
            $error = 'Este slug já está em uso. Escolhe outro.';
        } else {
            $ins = $db->prepare("INSERT INTO forum_communities (name,slug,description,icon,banner_color,created_by,member_count) VALUES (?,?,?,?,?,?,1)");
            $ins->execute(array($name,$slug,$description?:null,$icon,$bannerColor,(int)$currentUser['id']));
            $newId = (int)$db->lastInsertId();
            // Criador entra como owner
            $db->prepare("INSERT INTO forum_memberships (user_id,community_id,role) VALUES (?,?,'owner')")->execute(array((int)$currentUser['id'],$newId));
            header('Location: comunidade.php?slug='.urlencode($slug));
            exit;
        }
    }
}

$iconOptions = ['💬','🖨️','🔧','⚙️','🎨','🧪','💡','📐','🔩','🏗️','🌟','🎯','🚀','🔬','💎','🛠️','📦','🌐','⚡','🔥'];
$colorOptions = ['#00e5ff','#ff6b35','#7c3aed','#00ff88','#ff4488','#ffcc00','#00aaff','#ff6600','#aa44ff','#44ffaa'];
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
    <link rel="icon" type="image/x-icon"  href="../favicons/favicon-forum.ico">
    <link rel="icon" type="image/svg+xml" href="../favicons/favicon-forum.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="../favicons/favicon-forum-32.png">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Criar Comunidade — Fórum 3D</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800;900&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root{--bg:#0a0a0f;--surface:#111118;--surface2:#1a1a26;--surface3:#222235;--accent:#00e5ff;--accent2:#ff6b35;--accent3:#7c3aed;--accent4:#00ff88;--text:#e8e8f0;--muted:#888899;--border:rgba(0,229,255,0.1);--border2:rgba(255,255,255,0.06);--comm-color:#00e5ff}
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

.page-wrap{max-width:820px;margin:0 auto;padding:36px 24px;position:relative;z-index:1}

/* Preview da comunidade */
.comm-preview-card{border-radius:16px;overflow:hidden;margin-bottom:28px;border:1px solid var(--border2)}
.comm-preview-banner{height:100px;position:relative;transition:background 0.3s}
.comm-preview-banner-pattern{position:absolute;inset:0;opacity:0.07;background-image:repeating-linear-gradient(45deg,var(--comm-color) 0,var(--comm-color) 1px,transparent 0,transparent 50%);background-size:16px 16px}
.comm-preview-banner::after{content:'';position:absolute;inset:0;background:linear-gradient(to bottom,transparent 30%,var(--bg))}
.comm-preview-body{background:var(--surface);padding:0 20px 18px;position:relative;margin-top:-2px}
.comm-preview-icon-wrap{margin-top:-32px;margin-bottom:10px}
.comm-preview-icon{width:64px;height:64px;border-radius:16px;border:3px solid var(--bg);display:flex;align-items:center;justify-content:center;font-size:30px;transition:background 0.3s}
.comm-preview-name{font-family:'Syne',sans-serif;font-size:18px;font-weight:800;color:#fff;margin-bottom:2px}
.comm-preview-slug{font-family:'Space Mono',monospace;font-size:10px;color:var(--muted)}
.comm-preview-desc{font-size:13px;color:var(--muted);margin-top:8px;line-height:1.5;min-height:18px}
.preview-label{font-family:'Space Mono',monospace;font-size:9px;color:var(--accent);letter-spacing:2px;text-transform:uppercase;margin-bottom:12px;display:flex;align-items:center;gap:6px}
.preview-label::before{content:'';display:block;width:20px;height:1px;background:var(--accent)}

/* Form */
.form-card{background:var(--surface);border:1px solid var(--border2);border-radius:16px;overflow:hidden}
.form-section{padding:22px 24px;border-bottom:1px solid var(--border2)}
.form-section:last-child{border-bottom:none}
.form-section-title{font-family:'Space Mono',monospace;font-size:10px;font-weight:700;color:var(--muted);letter-spacing:2px;text-transform:uppercase;margin-bottom:16px;display:flex;align-items:center;gap:8px}
.form-section-title::after{content:'';flex:1;height:1px;background:var(--border2)}

.form-group{margin-bottom:16px}
.form-group:last-child{margin-bottom:0}
.form-label{font-family:'Space Mono',monospace;font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:1px;display:flex;align-items:center;justify-content:space-between;margin-bottom:7px}
.form-label .req{color:var(--accent2);font-size:9px}
.form-label .opt{color:var(--muted);font-size:9px;opacity:0.7}
.form-input,.form-textarea{width:100%;background:var(--surface2);border:1px solid var(--border2);border-radius:10px;padding:12px 16px;color:var(--text);font-family:'Inter',sans-serif;font-size:14px;transition:border-color 0.2s}
.form-input:focus,.form-textarea:focus{outline:none;border-color:var(--accent)}
.form-input::placeholder,.form-textarea::placeholder{color:var(--muted);opacity:0.5}
.form-textarea{min-height:100px;resize:vertical;line-height:1.6}
.form-hint{font-size:11px;color:var(--muted);margin-top:5px;line-height:1.4}
.char-count{font-family:'Space Mono',monospace;font-size:10px;color:var(--muted);text-align:right;margin-top:4px}

/* Slug preview */
.slug-preview{display:inline-flex;align-items:center;gap:4px;font-family:'Space Mono',monospace;font-size:11px;color:var(--accent);background:rgba(0,229,255,0.06);border:1px solid rgba(0,229,255,0.15);border-radius:6px;padding:4px 10px;margin-top:6px}
.slug-preview-base{color:var(--muted)}

/* Icon picker */
.icon-grid{display:flex;flex-wrap:wrap;gap:8px;margin-top:6px}
.icon-opt{width:44px;height:44px;border-radius:10px;background:var(--surface2);border:2px solid var(--border2);display:flex;align-items:center;justify-content:center;font-size:20px;cursor:pointer;transition:all 0.15s}
.icon-opt:hover{border-color:rgba(0,229,255,0.4);background:rgba(0,229,255,0.06);transform:scale(1.08)}
.icon-opt.selected{border-color:var(--accent);background:rgba(0,229,255,0.1)}

/* Color picker */
.color-grid{display:flex;flex-wrap:wrap;gap:8px;margin-top:6px}
.color-opt{width:36px;height:36px;border-radius:50%;cursor:pointer;border:3px solid transparent;transition:all 0.15s;position:relative}
.color-opt:hover{transform:scale(1.12)}
.color-opt.selected{border-color:#fff;box-shadow:0 0 0 2px rgba(255,255,255,0.3)}
.color-opt.selected::after{content:'✓';position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#fff;font-size:14px;font-weight:700;text-shadow:0 1px 3px rgba(0,0,0,0.5)}

/* Form actions */
.form-actions{display:flex;align-items:center;gap:12px;padding:22px 24px;background:rgba(0,0,0,0.1);flex-wrap:wrap}
.submit-btn{background:linear-gradient(135deg,var(--accent),var(--accent3));border:none;border-radius:10px;padding:13px 28px;color:#000;font-family:'Space Mono',monospace;font-size:12px;font-weight:700;cursor:pointer;letter-spacing:1px;transition:opacity 0.2s}
.submit-btn:hover{opacity:0.88}
.cancel-btn{background:none;border:1px solid var(--border2);border-radius:10px;padding:13px 20px;color:var(--muted);font-family:'Space Mono',monospace;font-size:12px;cursor:pointer;text-decoration:none;transition:all 0.2s}
.cancel-btn:hover{border-color:var(--accent);color:var(--accent)}

.error-box{background:rgba(255,68,68,0.06);border:1px solid rgba(255,68,68,0.25);border-radius:10px;padding:14px 16px;font-size:13px;color:#ff8888;margin-bottom:20px}
.bc-bar{background:var(--surface);border-bottom:1px solid var(--border2);padding:8px 32px;position:relative;z-index:5}
.bc-inner{max-width:1400px;margin:0 auto;display:flex;align-items:center;gap:6px;font-family:'Space Mono',monospace;font-size:10px;color:var(--muted);flex-wrap:wrap}
.bc-link{color:var(--muted);text-decoration:none;transition:color 0.15s}.bc-link:hover{color:var(--accent)}
.bc-sep{opacity:0.4}.bc-current{color:var(--text)}
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
<!-- Breadcrumb -->
<div class="bc-bar">
    <div class="bc-inner">
        <a href="../index.php" class="bc-link">📖 Manual</a>
        <span class="bc-sep">›</span>
        <a href="index.php" class="bc-link">🌐 Fórum</a>
        <span class="bc-sep">›</span><span class="bc-current">➕ Criar Comunidade</span>
    </div>
</div>

<div class="page-wrap">

    <div style="margin-bottom:24px">
        <div style="font-family:'Space Mono',monospace;font-size:10px;color:var(--accent);letter-spacing:3px;text-transform:uppercase;margin-bottom:8px">Fórum 3D</div>
        <h1 style="font-family:'Syne',sans-serif;font-size:28px;font-weight:900;color:#fff;margin-bottom:6px">Criar comunidade</h1>
        <p style="font-size:14px;color:var(--muted)">Cria um espaço para reunir pessoas com os mesmos interesses.</p>
    </div>

    <?php if ($error): ?>
    <div class="error-box">⚠️ <?php echo sanitize($error); ?></div>
    <?php endif; ?>

    <!-- Preview em tempo real -->
    <div class="preview-label">Pré-visualização</div>
    <div class="comm-preview-card">
        <div class="comm-preview-banner" id="previewBanner">
            <div class="comm-preview-banner-pattern" id="previewPattern"></div>
        </div>
        <div class="comm-preview-body">
            <div class="comm-preview-icon-wrap">
                <div class="comm-preview-icon" id="previewIcon">💬</div>
            </div>
            <div class="comm-preview-name" id="previewName">Nome da comunidade</div>
            <div class="comm-preview-slug">c/<span id="previewSlug">slug</span></div>
            <div class="comm-preview-desc" id="previewDesc">Descrição da comunidade aparece aqui…</div>
        </div>
    </div>

    <form method="POST" id="commForm">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
        <input type="hidden" name="icon"         id="iconInput"  value="💬">
        <input type="hidden" name="banner_color" id="colorInput" value="#00e5ff">

        <div class="form-card">

            <!-- Identidade -->
            <div class="form-section">
                <div class="form-section-title">01 — Identidade</div>

                <div class="form-group">
                    <label class="form-label">Nome <span class="req">OBRIGATÓRIO</span></label>
                    <input type="text" name="name" id="nameInput" class="form-input"
                        placeholder="ex: Impressão FDM, Resina UV, Slicers…"
                        maxlength="80" required
                        value="<?php echo sanitize($_POST['name'] ?? ''); ?>"
                        oninput="onNameChange(this.value)">
                    <div class="char-count"><span id="nameChars"><?php echo mb_strlen($_POST['name']??''); ?></span>/80</div>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        Slug (URL) <span class="req">OBRIGATÓRIO</span>
                    </label>
                    <input type="text" name="slug" id="slugInput" class="form-input"
                        placeholder="ex: impressao-fdm"
                        maxlength="50" required
                        value="<?php echo sanitize($_POST['slug'] ?? ''); ?>"
                        oninput="onSlugChange(this.value)">
                    <div class="slug-preview"><span class="slug-preview-base">forum/comunidade.php?slug=</span><span id="slugDisplay">…</span></div>
                    <div class="form-hint">Só letras minúsculas, números, - e _. Não pode ser alterado depois.</div>
                </div>

                <div class="form-group">
                    <label class="form-label">Descrição <span class="opt">OPCIONAL</span></label>
                    <textarea name="description" id="descInput" class="form-textarea"
                        placeholder="Do que trata esta comunidade? Que tipo de posts são bem-vindos?"
                        maxlength="500"
                        oninput="onDescChange(this.value)"><?php echo sanitize($_POST['description'] ?? ''); ?></textarea>
                    <div class="char-count"><span id="descChars"><?php echo mb_strlen($_POST['description']??''); ?></span>/500</div>
                </div>
            </div>

            <!-- Aparência -->
            <div class="form-section">
                <div class="form-section-title">02 — Aparência</div>

                <div class="form-group">
                    <label class="form-label">Ícone</label>
                    <div class="icon-grid">
                        <?php foreach ($iconOptions as $i => $ico): ?>
                        <div class="icon-opt <?php echo $i===0?'selected':''; ?>"
                             onclick="selectIcon('<?php echo $ico; ?>',this)"><?php echo $ico; ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-group" style="margin-top:8px">
                    <label class="form-label">Cor do banner</label>
                    <div class="color-grid">
                        <?php foreach ($colorOptions as $i => $col): ?>
                        <div class="color-opt <?php echo $i===0?'selected':''; ?>"
                             style="background:<?php echo $col; ?>"
                             onclick="selectColor('<?php echo $col; ?>',this)"></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

        </div>

        <div class="form-actions">
            <button type="submit" class="submit-btn">🏗️ CRIAR COMUNIDADE</button>
            <a href="index.php" class="cancel-btn">Cancelar</a>
        </div>
    </form>
</div>

<script>
var currentColor = '#00e5ff';
var currentIcon  = '💬';

function onNameChange(val) {
    document.getElementById('nameChars').textContent = val.length;
    document.getElementById('previewName').textContent = val || 'Nome da comunidade';
    // Auto-gerar slug se estiver vazio ou igual ao auto-gerado
    var slugEl = document.getElementById('slugInput');
    var autoSlug = val.toLowerCase()
        .replace(/[àáâãäå]/g,'a').replace(/[èéêë]/g,'e').replace(/[ìíîï]/g,'i')
        .replace(/[òóôõö]/g,'o').replace(/[ùúûü]/g,'u').replace(/[ç]/g,'c')
        .replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'').slice(0,50);
    slugEl.value = autoSlug;
    onSlugChange(autoSlug);
}

function onSlugChange(val) {
    var clean = val.toLowerCase().replace(/[^a-z0-9_-]/g,'');
    document.getElementById('slugInput').value = clean;
    document.getElementById('slugDisplay').textContent  = clean || '…';
    document.getElementById('previewSlug').textContent  = clean || 'slug';
}

function onDescChange(val) {
    document.getElementById('descChars').textContent    = val.length;
    document.getElementById('previewDesc').textContent  = val || 'Descrição da comunidade aparece aqui…';
}

function selectIcon(icon, el) {
    document.querySelectorAll('.icon-opt').forEach(function(x){x.classList.remove('selected');});
    el.classList.add('selected');
    currentIcon = icon;
    document.getElementById('iconInput').value      = icon;
    document.getElementById('previewIcon').textContent = icon;
}

function selectColor(color, el) {
    document.querySelectorAll('.color-opt').forEach(function(x){x.classList.remove('selected');});
    el.classList.add('selected');
    currentColor = color;
    document.getElementById('colorInput').value = color;
    // Atualizar preview
    document.getElementById('previewBanner').style.background =
        'linear-gradient(135deg, '+color+'33, '+color+'11, #0a0a0f)';
    document.getElementById('previewPattern').style.backgroundImage =
        'repeating-linear-gradient(45deg, '+color+' 0, '+color+' 1px, transparent 0, transparent 50%)';
    document.getElementById('previewIcon').style.background =
        'linear-gradient(135deg, '+color+'44, '+color+'22)';
    document.root && (document.root.style.setProperty('--comm-color', color));
}

// Init
(function(){
    var nameVal = document.getElementById('nameInput').value;
    if (nameVal) { document.getElementById('previewName').textContent = nameVal; }
    var descVal = document.getElementById('descInput').value;
    if (descVal) { document.getElementById('previewDesc').textContent = descVal; }
    var slugVal = document.getElementById('slugInput').value;
    if (slugVal) { document.getElementById('slugDisplay').textContent = slugVal; document.getElementById('previewSlug').textContent = slugVal; }
})();
</script>
</body>
</html>