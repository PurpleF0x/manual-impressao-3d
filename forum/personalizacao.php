<?php
/**
 * forum/personalizacao.php — Personalização do perfil (estilo Steam)
 */
require_once __DIR__ . '/../includes/functions.php';
// Helper: path do avatar relativo ao forum/
function avPath($url) {
    if (!$url) return '';
    if (strpos($url,'http')===0) return $url;
    return '../' . ltrim($url, '/');
}
if (!isLoggedIn()) { header('Location: ../login.php?redirect=forum/personalizacao.php'); exit; }
$currentUser = getCurrentUser();
$uid = (int)$currentUser['id'];
$db  = getDB();

// ── Garantir tabelas ──────────────────────────────────────────
try { $db->exec("CREATE TABLE IF NOT EXISTS shop_items (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, description VARCHAR(255), category ENUM('frame','background','banner','accent','badge','medal') NOT NULL, item_key VARCHAR(50) NOT NULL UNIQUE, css_value TEXT NOT NULL, preview_css TEXT, price INT DEFAULT 100, source ENUM('shop','community','achievement') DEFAULT 'shop', community_id INT NULL, is_active TINYINT(1) DEFAULT 1, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch(Exception $e){}
try { $db->exec("CREATE TABLE IF NOT EXISTS user_inventory (user_id INT NOT NULL, item_id INT NOT NULL, obtained_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (user_id, item_id), FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY (item_id) REFERENCES shop_items(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch(Exception $e){}
try { $db->exec("ALTER TABLE shop_items MODIFY COLUMN category ENUM('frame','background','banner','accent','badge','medal') NOT NULL"); } catch(Exception $e){}
try { $db->exec("ALTER TABLE user_profile_config ADD COLUMN IF NOT EXISTS top_badges TEXT NULL"); } catch(Exception $e){}
try { $db->exec("ALTER TABLE user_profile_config ADD COLUMN IF NOT EXISTS coins INT DEFAULT 0"); } catch(Exception $e){}

// ── Seed items se vazio ───────────────────────────────────────
if ((int)$db->query("SELECT COUNT(*) FROM shop_items")->fetchColumn() === 0) {
    $items = array(
        array('Cyan Neon','Frame neon ciano','frame','frame_cyan_neon','border:3px solid #00e5ff;box-shadow:0 0 14px #00e5ff,0 0 28px rgba(0,229,255,0.4);border-radius:50%','',50),
        array('Purple Glow','Frame roxa brilhante','frame','frame_purple_glow','border:3px solid #7c3aed;box-shadow:0 0 14px #7c3aed,0 0 28px rgba(124,58,237,0.5);border-radius:50%','',50),
        array('Fire','Frame laranja ardente','frame','frame_fire','border:3px solid #ff6b35;box-shadow:0 0 14px #ff6b35,0 0 28px rgba(255,107,53,0.5);border-radius:50%','',50),
        array('Gradient Spin','Frame gradiente rotativo','frame','frame_gradient_spin','border:3px solid transparent;border-radius:50%;background:linear-gradient(#111118,#111118) padding-box,conic-gradient(from 0deg,#00e5ff,#7c3aed,#ff6b35,#00e5ff) border-box;animation:spinBorder 3s linear infinite','',150),
        array('Rainbow Pulse','Frame arco-íris pulsante','frame','frame_rainbow_pulse','border:4px solid transparent;border-radius:50%;background:linear-gradient(#111118,#111118) padding-box,linear-gradient(45deg,#ff0080,#ff6b35,#ffcc00,#00ff88,#00e5ff,#7c3aed) border-box;animation:pulseGlow 2s ease-in-out infinite','',200),
        array('Gold Legend','Frame dourada lendária','frame','frame_gold_legend','border:4px solid #ffcc00;box-shadow:0 0 18px #ffcc00,0 0 36px rgba(255,204,0,0.4);border-radius:50%;animation:pulseGlow 2s ease-in-out infinite','',300),
        array('Pixel Border','Frame pixel art 8-bit','frame','frame_pixel','border:4px solid #00ff88;border-radius:4px;box-shadow:4px 4px 0 #007744,-4px -4px 0 #00ffaa','',100),
        array('Ice Crystal','Frame cristal de gelo','frame','frame_ice','border:3px solid #a0e4ff;box-shadow:0 0 12px rgba(160,228,255,0.8),0 0 24px rgba(160,228,255,0.3);border-radius:50%','',120),
        array('Matrix Rain','Fundo chuva de código','background','bg_matrix','--custom-bg:#040d04;--custom-overlay:radial-gradient(ellipse at 20% 50%,rgba(0,255,65,0.08) 0%,transparent 50%)','',80),
        array('Deep Space','Fundo cosmos estrelado','background','bg_space','--custom-bg:#050510;--custom-overlay:radial-gradient(ellipse at 30% 30%,rgba(124,58,237,0.14) 0%,transparent 40%),radial-gradient(ellipse at 70% 70%,rgba(0,229,255,0.09) 0%,transparent 40%)','',80),
        array('Sunset','Fundo pôr do sol','background','bg_sunset','--custom-bg:#0f0510;--custom-overlay:radial-gradient(ellipse at 50% 100%,rgba(255,107,53,0.18) 0%,transparent 60%),radial-gradient(ellipse at 50% 0%,rgba(124,58,237,0.1) 0%,transparent 50%)','',80),
        array('Neon City','Fundo cidade neon','background','bg_neon_city','--custom-bg:#08080f;--custom-overlay:linear-gradient(180deg,rgba(0,229,255,0.04) 0%,transparent 40%),repeating-linear-gradient(90deg,rgba(0,229,255,0.015) 0px,rgba(0,229,255,0.015) 1px,transparent 1px,transparent 60px)','',120),
        array('Lava','Fundo lava borbulhante','background','bg_lava','--custom-bg:#0f0500;--custom-overlay:radial-gradient(ellipse at 20% 80%,rgba(255,50,0,0.14) 0%,transparent 50%),radial-gradient(ellipse at 80% 20%,rgba(255,150,0,0.09) 0%,transparent 50%)','',120),
        array('Plasma Pink','Cor destaque rosa','accent','accent_pink','#ff0080','',60),
        array('Emerald','Cor destaque esmeralda','accent','accent_emerald','#00ff88','',60),
        array('Solar Gold','Cor destaque dourada','accent','accent_gold','#ffcc00','',60),
        array('Crimson','Cor destaque carmesim','accent','accent_crimson','#ff2244','',60),
        array('Violet','Cor destaque violeta','accent','accent_violet','#aa44ff','',60),
        array('Membro Ativo','Atribuído ao atingir 20 XP','badge','lvl_membro','🛡️','',0),
        array('Veterano','Atribuído ao atingir 100 XP','badge','lvl_veterano','🎖️','',0),
        array('Lenda do Fórum','Atribuído ao atingir 500 XP','badge','lvl_lendario','👑','',0),
        array('Ajudante','Atribuído a quem ajuda a comunidade','badge','badge_helper','🤝','',0),
        array('Pioneiro','Membro fundador do fórum','badge','badge_pioneer','🚀','',0),
        array('Membro de Elite','Reconhecimento por contribuições excepcionais','badge','badge_elite','💎','',0),
        array('Mestre 3D','Conhecimento técnico profundo demonstrado','badge','badge_master','⚙️','',0),
    );
    $ins = $db->prepare("INSERT IGNORE INTO shop_items (name,description,category,item_key,css_value,preview_css,price) VALUES (?,?,?,?,?,?,?)");
    foreach ($items as $i) { try { $ins->execute($i); } catch(Exception $e){} }
}

// Calcular moedas por actividade (Sync legado se não houver config)
$config = array('frame_key'=>null,'background_key'=>null,'banner_url'=>null,'accent_color'=>null,'top_badges'=>null,'coins'=>0);
try {
    $cq = $db->prepare("SELECT * FROM user_profile_config WHERE user_id=?");
    $cq->execute(array($uid)); $cc = $cq->fetch();
    if ($cc) {
        $config = $cc;
        if (!isset($cc['top_badges'])) {
             try { $db->exec("ALTER TABLE user_profile_config ADD COLUMN top_badges TEXT NULL"); } catch(Exception $e){}
        }
    } else {
        // Tenta obter moedas legadas calculando uma vez se for o primeiro acesso
        try {
            $postCount   = (int)$db->query("SELECT COUNT(*) FROM forum_posts WHERE user_id=$uid AND status='approved'")->fetchColumn();
            $replyCount  = (int)$db->query("SELECT COUNT(*) FROM forum_replies WHERE user_id=$uid")->fetchColumn();
            $karmaPos    = (int)$db->query("SELECT COALESCE(SUM(GREATEST(vote_score,0)),0) FROM forum_posts WHERE user_id=$uid")->fetchColumn();
            $legacyCoins = $postCount * 10 + $replyCount * 3 + $karmaPos * 2;
        } catch(Exception $e){ $legacyCoins = 0; }

        $db->prepare("INSERT INTO user_profile_config (user_id,coins) VALUES (?,?)")->execute(array($uid, $legacyCoins));
        $config['coins'] = $legacyCoins;
    }
} catch(Exception $e){}

$coins = (int)$config['coins'];

// ── Inventário ────────────────────────────────────────────────
if ($currentUser['role'] === 'master') {
    $invQ = $db->query("SELECT * FROM shop_items WHERE is_active=1 ORDER BY category, price");
    $myItems = $invQ->fetchAll();
} else {
    $invQ = $db->prepare("SELECT si.* FROM user_inventory ui JOIN shop_items si ON si.id=ui.item_id WHERE ui.user_id=? ORDER BY si.category,si.price");
    $invQ->execute(array($uid));
    $myItems = $invQ->fetchAll();
}
$myFrames  = array_values(array_filter($myItems, function($i){ return $i['category']==='frame'; }));
$myBgs     = array_values(array_filter($myItems, function($i){ return $i['category']==='background'; }));
$myAccents = array_values(array_filter($myItems, function($i){ return $i['category']==='accent'; }));
$myBadges  = array_values(array_filter($myItems, function($i){ return in_array($i['category'], ['badge', 'medal']); }));

// ── Secção activa (GET) ───────────────────────────────────────
$section = $_GET['s'] ?? 'avatar';
$validSections = array('avatar','frame','background','banner','accent','badges');
if (!in_array($section, $validSections)) $section = 'avatar';

// ── POST: guardar ─────────────────────────────────────────────
$flash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? null)) {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $field = $_POST['field'] ?? '';
        $value = $_POST['value'] ?? null;

        if ($field === 'frame_key') {
            if ($value === '') $value = null;
            if ($value) {
                if ($currentUser['role'] === 'master') {
                    $chk = $db->prepare("SELECT 1 FROM shop_items WHERE item_key=? AND category='frame'");
                    $chk->execute(array($value));
                } else {
                    $chk = $db->prepare("SELECT 1 FROM user_inventory ui JOIN shop_items si ON si.id=ui.item_id WHERE si.item_key=? AND si.category='frame' AND ui.user_id=?");
                    $chk->execute(array($value,$uid));
                }
                if (!$chk->fetch()) $value = null;
            }
            $db->prepare("UPDATE user_profile_config SET frame_key=? WHERE user_id=?")->execute(array($value,$uid));
            $config['frame_key'] = $value;
            $flash = array('ok', '✅ Frame atualizada!');
        } elseif ($field === 'background_key') {
            if ($value === '') $value = null;
            if ($value) {
                if ($currentUser['role'] === 'master') {
                    $chk = $db->prepare("SELECT 1 FROM shop_items WHERE item_key=? AND category='background'");
                    $chk->execute(array($value));
                } else {
                    $chk = $db->prepare("SELECT 1 FROM user_inventory ui JOIN shop_items si ON si.id=ui.item_id WHERE si.item_key=? AND si.category='background' AND ui.user_id=?");
                    $chk->execute(array($value,$uid));
                }
                if (!$chk->fetch()) $value = null;
            }
            $db->prepare("UPDATE user_profile_config SET background_key=? WHERE user_id=?")->execute(array($value,$uid));
            $config['background_key'] = $value;
            $flash = array('ok', '✅ Fundo atualizado!');
        } elseif ($field === 'banner_url') {
            $value = trim($value ?? '');
            if ($value && !filter_var($value, FILTER_VALIDATE_URL)) $value = '';
            $db->prepare("UPDATE user_profile_config SET banner_url=? WHERE user_id=?")->execute(array($value ?: null,$uid));
            $config['banner_url'] = $value;
            $flash = array('ok', '✅ Banner atualizado!');
        } elseif ($field === 'accent_color') {
            if ($value === '') $value = null;
            if ($value) {
                if ($currentUser['role'] === 'master') {
                    $chk = $db->prepare("SELECT 1 FROM shop_items WHERE css_value=? AND category='accent'");
                    $chk->execute(array($value));
                } else {
                    $chk = $db->prepare("SELECT 1 FROM user_inventory ui JOIN shop_items si ON si.id=ui.item_id WHERE si.css_value=? AND si.category='accent' AND ui.user_id=?");
                    $chk->execute(array($value,$uid));
                }
                if (!$chk->fetch()) $value = null;
            }
            $db->prepare("UPDATE user_profile_config SET accent_color=? WHERE user_id=?")->execute(array($value,$uid));
            $config['accent_color'] = $value;
            $flash = array('ok', '✅ Cor de destaque atualizada!');
        } elseif ($field === 'top_badges') {
            $badges = $_POST['badges'] ?? [];
            if (!is_array($badges)) $badges = [];
            $badges = array_slice($badges, 0, 3);
            // Validar se pertencem ao inventário
            $validBadges = [];
            foreach ($badges as $bid) {
                if ($currentUser['role'] === 'master') {
                    $chk = $db->prepare("SELECT 1 FROM shop_items WHERE id=? AND category IN ('badge','medal')");
                    $chk->execute(array($bid));
                } else {
                    $chk = $db->prepare("SELECT 1 FROM user_inventory ui JOIN shop_items si ON si.id=ui.item_id WHERE ui.item_id=? AND ui.user_id=? AND si.category IN ('badge','medal')");
                    $chk->execute(array($bid, $uid));
                }
                if ($chk->fetch()) $validBadges[] = (int)$bid;
            }
            $val = json_encode($validBadges);
            $db->prepare("UPDATE user_profile_config SET top_badges=? WHERE user_id=?")->execute(array($val, $uid));
            $config['top_badges'] = $val;
            $flash = array('ok', '✅ Top 3 Badges atualizados!');
        }
    }
}

// ── CSS do frame e background (para preview) ─────────────────
$frameCSS = '';
if (!empty($config['frame_key'])) {
    try {
        $fq = $db->prepare("SELECT css_value FROM shop_items WHERE item_key=?");
        $fq->execute(array($config['frame_key'])); $fr = $fq->fetch();
        if ($fr) $frameCSS = $fr['css_value'];
    } catch(Exception $e){}
}
$bgPreviewColor = '#0a0a0f';
if (!empty($config['background_key'])) {
    try {
        $bq = $db->prepare("SELECT css_value FROM shop_items WHERE item_key=?");
        $bq->execute(array($config['background_key'])); $br = $bq->fetch();
        if ($br) { preg_match('/--custom-bg:\s*([^;]+)/', $br['css_value'], $m); $bgPreviewColor = trim($m[1] ?? '#0a0a0f'); }
    } catch(Exception $e){}
}
$accentColor = !empty($config['accent_color']) ? $config['accent_color'] : '#00e5ff';
$bannerUrl   = $config['banner_url'] ?? '';
$initials    = mb_substr($currentUser['full_name'] ?? '??', 0, 2);

$unreadMsgs = 0;
try {
    $um = $db->prepare("SELECT COUNT(*) FROM private_messages WHERE receiver_id=? AND read_at IS NULL");
    $um->execute(array($uid)); $unreadMsgs = (int)$um->fetchColumn();
} catch(Exception $e){}

$csrf = generateCSRFToken();
?><!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
    <link rel="icon" type="image/x-icon"  href="../favicons/favicon-personalizacao.ico">
    <link rel="icon" type="image/svg+xml" href="../favicons/favicon-personalizacao.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="../favicons/favicon-personalizacao-32.png">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Personalização — Fórum 3D</title>
<link rel="icon" type="image/x-icon" href="../favicon.ico">
<link rel="icon" type="image/svg+xml" href="../favicon.svg">
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800;900&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root{--bg:#0a0a0f;--surface:#111118;--surface2:#1a1a26;--surface3:#222235;--accent:<?php echo htmlspecialchars($accentColor); ?>;--accent2:#ff6b35;--accent3:#7c3aed;--accent4:#00ff88;--text:#e8e8f0;--muted:#888899;--border:rgba(0,229,255,0.1);--border2:rgba(255,255,255,0.06)}
*{margin:0;padding:0;box-sizing:border-box}
body{background:var(--bg);color:var(--text);font-family:'Inter',sans-serif;min-height:100vh}
body::before{content:'';position:fixed;inset:0;pointer-events:none;z-index:0;background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.03'/%3E%3C/svg%3E");opacity:0.4}

/* Topbar */
.topbar{position:sticky;top:0;z-index:200;background:rgba(10,10,15,0.95);backdrop-filter:blur(16px);border-bottom:1px solid var(--border);padding:0 28px;display:flex;align-items:center;gap:14px;height:56px}
.topbar-logo{font-family:'Space Mono',monospace;font-size:11px;font-weight:700;color:var(--accent);letter-spacing:3px;text-transform:uppercase;text-decoration:none}
.topbar-logo span{color:var(--muted)}
.topbar-right{display:flex;align-items:center;gap:8px;margin-left:auto}
.topbar-btn{background:none;border:1px solid var(--border2);border-radius:8px;padding:6px 13px;color:var(--muted);font-family:'Space Mono',monospace;font-size:10px;text-decoration:none;display:inline-flex;align-items:center;gap:5px;transition:all 0.2s}
.topbar-btn:hover{border-color:var(--accent);color:var(--accent)}
.coins-pill{display:inline-flex;align-items:center;gap:5px;background:rgba(255,204,0,0.08);border:1px solid rgba(255,204,0,0.25);border-radius:20px;padding:5px 12px;font-family:'Space Mono',monospace;font-size:11px;font-weight:700;color:#ffcc00}
.topbar-av{width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,var(--accent3),var(--accent));display:flex;align-items:center;justify-content:center;font-family:'Space Mono',monospace;font-size:10px;font-weight:700;color:#000;overflow:hidden;text-decoration:none}
.topbar-av img{width:100%;height:100%;object-fit:cover;border-radius:50%}

/* Layout principal */
.page{display:grid;grid-template-columns:220px 1fr 320px;gap:0;min-height:calc(100vh - 56px);position:relative;z-index:1}

/* ── Sidebar esquerda (navegação) ── */
.nav-sidebar{background:var(--surface);border-right:1px solid var(--border2);padding:24px 0}
.nav-back{display:flex;align-items:center;gap:8px;padding:10px 20px;color:var(--muted);text-decoration:none;font-family:'Space Mono',monospace;font-size:10px;letter-spacing:1px;transition:color 0.2s;margin-bottom:8px}
.nav-back:hover{color:var(--accent)}
.nav-divider{height:1px;background:var(--border2);margin:8px 20px 16px}
.nav-section-label{font-family:'Space Mono',monospace;font-size:9px;color:var(--muted);letter-spacing:2px;text-transform:uppercase;padding:0 20px 8px}
.nav-item{display:flex;align-items:center;gap:10px;padding:11px 20px;color:var(--muted);text-decoration:none;font-size:13px;transition:all 0.15s;cursor:pointer;border:none;background:none;width:100%;text-align:left;font-family:'Inter',sans-serif;position:relative}
.nav-item:hover{color:var(--text);background:rgba(255,255,255,0.03)}
.nav-item.active{color:var(--accent);background:rgba(0,229,255,0.05)}
.nav-item.active::before{content:'';position:absolute;left:0;top:0;bottom:0;width:3px;background:var(--accent);border-radius:0 2px 2px 0}
.nav-item-icon{font-size:16px;width:22px;text-align:center;flex-shrink:0}
.nav-owned{font-family:'Space Mono',monospace;font-size:8px;background:rgba(0,229,255,0.08);color:var(--accent);border-radius:20px;padding:2px 7px;margin-left:auto}
.nav-divider2{height:1px;background:var(--border2);margin:16px 20px}
.nav-shop-btn{display:flex;align-items:center;gap:10px;padding:11px 20px;color:#ffcc00;font-size:13px;text-decoration:none;transition:all 0.15s;font-family:'Inter',sans-serif}
.nav-shop-btn:hover{background:rgba(255,204,0,0.05)}

/* ── Área de conteúdo central ── */
.content-area{padding:32px 36px;overflow-y:auto}
.content-title{font-family:'Syne',sans-serif;font-size:22px;font-weight:800;color:#fff;margin-bottom:6px}
.content-sub{font-size:13px;color:var(--muted);margin-bottom:28px}

/* Grid de items */
.items-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:14px}
.item-tile{background:var(--surface);border:2px solid var(--border2);border-radius:12px;overflow:hidden;cursor:pointer;transition:all 0.2s;position:relative}
.item-tile:hover{border-color:rgba(0,229,255,0.3);transform:translateY(-2px)}
.item-tile.selected{border-color:var(--accent);box-shadow:0 0 0 1px var(--accent)}
.item-tile.selected::after{content:'✓';position:absolute;top:8px;right:8px;width:22px;height:22px;background:var(--accent);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#000;display:grid;place-items:center}
.item-tile-preview{height:100px;display:flex;align-items:center;justify-content:center;background:var(--surface2)}
.item-tile-body{padding:10px 12px}
.item-tile-name{font-size:12px;font-weight:600;color:var(--text);margin-bottom:2px}
.item-tile-desc{font-size:10px;color:var(--muted);line-height:1.4}
.tile-av{width:56px;height:56px;border-radius:50%;background:linear-gradient(135deg,var(--accent3),var(--accent));display:flex;align-items:center;justify-content:center;font-family:'Space Mono',monospace;font-size:14px;font-weight:700;color:#000}
.empty-inv{text-align:center;padding:48px 24px;color:var(--muted)}
.empty-inv-icon{font-size:40px;margin-bottom:12px}
.empty-inv p{font-size:13px;margin-bottom:16px}
.goto-shop{background:rgba(255,204,0,0.1);border:1px solid rgba(255,204,0,0.3);border-radius:9px;padding:10px 20px;color:#ffcc00;font-family:'Space Mono',monospace;font-size:10px;font-weight:700;text-decoration:none;display:inline-block;transition:all 0.2s}
.goto-shop:hover{background:rgba(255,204,0,0.2)}

/* Banner section */
.banner-input-wrap{margin-bottom:16px}
.banner-label{font-family:'Space Mono',monospace;font-size:10px;color:var(--muted);letter-spacing:1px;text-transform:uppercase;margin-bottom:8px;display:block}
.banner-input{width:100%;background:var(--surface2);border:1px solid var(--border2);border-radius:10px;padding:12px 16px;color:var(--text);font-family:'Inter',sans-serif;font-size:14px;transition:border-color 0.2s;outline:none}
.banner-input:focus{border-color:var(--accent)}
.banner-preview-box{height:100px;border-radius:10px;border:1px solid var(--border2);overflow:hidden;margin-bottom:12px;background:var(--surface2);display:flex;align-items:center;justify-content:center}
.banner-preview-box img{width:100%;height:100%;object-fit:cover}
.banner-hint{font-family:'Space Mono',monospace;font-size:10px;color:var(--muted);margin-bottom:20px}
.save-btn{background:linear-gradient(135deg,var(--accent),var(--accent3));border:none;border-radius:10px;padding:12px 28px;color:#000;font-family:'Space Mono',monospace;font-size:11px;font-weight:700;cursor:pointer;letter-spacing:1px;transition:opacity 0.2s}
.save-btn:hover{opacity:0.85}

/* Accent swatches */
.accent-grid{display:flex;gap:14px;flex-wrap:wrap;margin-bottom:28px}
.accent-swatch{width:52px;height:52px;border-radius:50%;cursor:pointer;border:3px solid transparent;transition:all 0.2s;position:relative}
.accent-swatch:hover{transform:scale(1.1)}
.accent-swatch.selected{border-color:#fff;box-shadow:0 0 0 2px rgba(255,255,255,0.3)}
.accent-swatch.selected::after{content:'✓';position:absolute;inset:0;display:grid;place-items:center;font-size:16px;font-weight:700;color:#fff;text-shadow:0 1px 3px rgba(0,0,0,0.5)}

/* ── Preview sidebar direita ── */
.preview-sidebar{background:var(--surface);border-left:1px solid var(--border2);padding:24px 20px;position:sticky;top:56px;height:calc(100vh - 56px);overflow-y:auto;display:flex;flex-direction:column;gap:0}
.preview-label{font-family:'Space Mono',monospace;font-size:9px;color:var(--muted);letter-spacing:2px;text-transform:uppercase;margin-bottom:16px}

/* Mini-perfil preview (estilo Steam) */
.mini-profile{background:linear-gradient(135deg,#0d0d1a,#111118);border:1px solid var(--border2);border-radius:14px;overflow:hidden;margin-bottom:20px}
.mini-banner{height:70px;position:relative;overflow:hidden;background:linear-gradient(135deg,#0d0d1a,#111118)}
.mini-banner img{width:100%;height:100%;object-fit:cover}
.mini-banner-grid{position:absolute;inset:0;background-image:linear-gradient(rgba(0,229,255,0.06) 1px,transparent 1px),linear-gradient(90deg,rgba(0,229,255,0.06) 1px,transparent 1px);background-size:20px 20px}
.mini-body{padding:10px 14px 14px;background:var(--surface)}
.mini-av-row{display:flex;align-items:flex-end;gap:10px;margin-top:-26px;margin-bottom:8px}
.mini-av{width:52px;height:52px;border-radius:50%;background:linear-gradient(135deg,var(--accent3),var(--accent));display:flex;align-items:center;justify-content:center;font-family:'Space Mono',monospace;font-size:14px;font-weight:700;color:#000;overflow:hidden;border:3px solid var(--surface);flex-shrink:0}
.mini-av img{width:100%;height:100%;object-fit:cover;border-radius:50%}
.mini-online{display:flex;align-items:center;gap:5px;font-size:10px;color:var(--accent4);font-family:'Space Mono',monospace;margin-bottom:2px}
.mini-online::before{content:'';width:6px;height:6px;background:var(--accent4);border-radius:50%}
.mini-name{font-family:'Syne',sans-serif;font-size:15px;font-weight:800;color:#fff;line-height:1.1}
.mini-username{font-family:'Space Mono',monospace;font-size:9px;color:var(--muted);margin-top:2px}
.mini-stats{display:flex;gap:12px;margin-top:10px;padding-top:10px;border-top:1px solid var(--border2)}
.mini-stat{text-align:center;flex:1}
.mini-stat-n{font-family:'Syne',sans-serif;font-size:16px;font-weight:800;color:var(--accent)}
.mini-stat-l{font-family:'Space Mono',monospace;font-size:8px;color:var(--muted);letter-spacing:1px;text-transform:uppercase}

/* Preview do fundo */
.bg-preview-box{border-radius:10px;overflow:hidden;border:1px solid var(--border2);height:80px;display:flex;align-items:center;justify-content:center;margin-bottom:20px;transition:background 0.4s}
.bg-preview-label{font-family:'Space Mono',monospace;font-size:9px;color:rgba(255,255,255,0.4);letter-spacing:1px}

/* Flash */
.flash-toast{position:fixed;top:70px;right:20px;z-index:9999;padding:12px 18px;border-radius:10px;font-family:'Space Mono',monospace;font-size:11px;font-weight:700;animation:toastIn 0.3s ease;pointer-events:none}
.flash-toast.ok{background:rgba(0,255,136,0.12);border:1px solid rgba(0,255,136,0.3);color:var(--accent4)}
.flash-toast.err{background:rgba(255,68,68,0.12);border:1px solid rgba(255,68,68,0.3);color:#ff8888}
@keyframes toastIn{from{opacity:0;transform:translateX(16px)}to{opacity:1;transform:translateX(0)}}

/* Animações de frame */
@keyframes spinBorder{from{filter:hue-rotate(0deg)}to{filter:hue-rotate(360deg)}}
@keyframes pulseGlow{0%,100%{filter:brightness(1)}50%{filter:brightness(1.4)}}
@keyframes fireFlicker{0%{box-shadow:0 0 10px #ff4400,0 0 20px #ff6600,0 0 40px rgba(255,100,0,0.3)}100%{box-shadow:0 0 16px #ff2200,0 0 30px #ff8800,0 0 60px rgba(255,80,0,0.5)}}
@keyframes iceShimmer{0%{box-shadow:0 0 8px #a0e4ff,0 0 20px rgba(160,228,255,0.4)}100%{box-shadow:0 0 16px #e0f8ff,0 0 36px rgba(160,228,255,0.7)}}
@keyframes electricPulse{0%{box-shadow:0 0 4px #ffff00,0 0 10px rgba(255,255,0,0.4)}100%{box-shadow:0 0 10px #ffff00,0 0 24px rgba(255,255,0,0.8)}}

@media(max-width:1100px){.page{grid-template-columns:180px 1fr 260px}}
@media(max-width:900px){.page{grid-template-columns:1fr}.nav-sidebar,.preview-sidebar{display:none}}
.bc-bar{background:var(--surface);border-bottom:1px solid var(--border2);padding:8px 32px;position:relative;z-index:5}
.bc-inner{max-width:1400px;margin:0 auto;display:flex;align-items:center;gap:6px;font-family:'Space Mono',monospace;font-size:10px;color:var(--muted);flex-wrap:wrap}
.bc-link{color:var(--muted);text-decoration:none;transition:color 0.15s}.bc-link:hover{color:var(--accent)}
.bc-sep{opacity:0.4}.bc-current{color:var(--text)}
</style>
</head>
<body>

<nav class="topbar">
    <a href="index.php" class="topbar-logo">3D<span>/</span>FÓRUM</a>
    <span style="color:var(--muted);font-size:12px">/ Personalização</span>
    <div class="topbar-right">
        <div class="coins-pill">🪙 <?php echo number_format($coins); ?></div>
        <a href="mensagens.php" class="topbar-btn">💬<?php if($unreadMsgs>0): ?> <span style="background:var(--accent2);color:#fff;border-radius:100px;font-size:9px;padding:1px 5px"><?php echo $unreadMsgs; ?></span><?php endif; ?></a>
        <a href="perfil.php?id=<?php echo (int)($_SESSION['user_id'] ?? 0); ?>" class="topbar-av">
            <?php $av=$currentUser['avatar_url']??''; if($av): ?><img src="<?php echo sanitize(avPath($av)); ?>" alt=""><?php else: echo $initials; endif; ?>
        </a>
    </div>
</nav>
<!-- Breadcrumb -->
<div class="bc-bar">
    <div class="bc-inner">
        <a href="../index.php" class="bc-link">📖 Manual</a>
        <span class="bc-sep">›</span>
        <a href="index.php" class="bc-link">🌐 Fórum</a>
        <span class="bc-sep">›</span><span class="bc-current">🎨 Personalização</span>
    </div>
</div>

<?php if ($flash): ?>
<div class="flash-toast <?php echo $flash[0]; ?>" id="flashToast"><?php echo sanitize($flash[1]); ?></div>
<script>setTimeout(function(){var t=document.getElementById('flashToast');if(t)t.style.opacity='0';},3000);</script>
<?php endif; ?>

<div class="page">

<!-- ── Sidebar esquerda ── -->
<nav class="nav-sidebar">
    <a href="perfil.php?id=<?php echo $uid; ?>" class="nav-back">← Voltar ao perfil</a>
    <div class="nav-divider"></div>
    <div class="nav-section-label">Personalização</div>

    <a href="?s=avatar" class="nav-item <?php echo $section==='avatar'?'active':''; ?>">
        <span class="nav-item-icon">👤</span> Avatar
    </a>
    <a href="?s=frame" class="nav-item <?php echo $section==='frame'?'active':''; ?>">
        <span class="nav-item-icon">🖼️</span> Frame do Avatar
        <?php if (!empty($myFrames)): ?><span class="nav-owned"><?php echo count($myFrames); ?></span><?php endif; ?>
    </a>
    <a href="?s=background" class="nav-item <?php echo $section==='background'?'active':''; ?>">
        <span class="nav-item-icon">🌌</span> Fundo da Página
        <?php if (!empty($myBgs)): ?><span class="nav-owned"><?php echo count($myBgs); ?></span><?php endif; ?>
    </a>
    <a href="?s=banner" class="nav-item <?php echo $section==='banner'?'active':''; ?>">
        <span class="nav-item-icon">🎨</span> Banner do Perfil
    </a>
    <a href="?s=accent" class="nav-item <?php echo $section==='accent'?'active':''; ?>">
        <span class="nav-item-icon">🎯</span> Cor de Destaque
        <?php if (!empty($myAccents)): ?><span class="nav-owned"><?php echo count($myAccents); ?></span><?php endif; ?>
    </a>

    <a href="?s=badges" class="nav-item <?php echo $section==='badges'?'active':''; ?>">
        <span class="nav-item-icon">🏅</span> Top 3 Badges
    </a>

    <div class="nav-divider2"></div>
    <div class="nav-section-label">Loja</div>
    <a href="loja.php" class="nav-shop-btn">
        <span class="nav-item-icon">🛒</span> Loja de Itens
    </a>
    <a href="loja.php" class="nav-item" style="font-size:11px;padding:6px 20px;color:var(--muted)">
        <span></span> 🪙 <?php echo number_format($coins); ?> moedas disponíveis
    </a>
</nav>

<!-- ── Área de conteúdo ── -->
<div class="content-area">

<?php if ($section === 'avatar'): ?>
    <div class="content-title">Avatar</div>
    <div class="content-sub">O teu avatar é a imagem que aparece no teu perfil e nos posts. Podes alterá-lo nas definições do teu perfil.</div>
    <div style="background:var(--surface);border:1px solid var(--border2);border-radius:14px;padding:28px;max-width:500px">
        <div style="display:flex;align-items:center;gap:20px;margin-bottom:20px">
            <div style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,var(--accent3),var(--accent));display:flex;align-items:center;justify-content:center;font-family:'Space Mono',monospace;font-size:24px;font-weight:700;color:#000;overflow:hidden;<?php echo $frameCSS; ?>">
                <?php $av=$currentUser['avatar_url']??''; if($av): ?><img src="<?php echo sanitize(avPath($av)); ?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%"><?php else: echo $initials; endif; ?>
            </div>
            <div>
                <div style="font-family:'Syne',sans-serif;font-size:16px;font-weight:700;color:#fff;margin-bottom:4px"><?php echo sanitize($currentUser['full_name']); ?></div>
                <div style="font-family:'Space Mono',monospace;font-size:11px;color:var(--accent)">@<?php echo sanitize($currentUser['username']); ?></div>
            </div>
        </div>
        <p style="font-size:13px;color:var(--muted);margin-bottom:16px">Para alterar a foto de perfil, vai às definições do teu perfil no manual.</p>
        <a href="../perfil.php" style="background:var(--surface2);border:1px solid var(--border2);border-radius:9px;padding:10px 18px;color:var(--muted);font-family:'Space Mono',monospace;font-size:10px;text-decoration:none;transition:all 0.2s;display:inline-block" onmouseover="this.style.color='var(--accent)';this.style.borderColor='rgba(0,229,255,0.3)'" onmouseout="this.style.color='var(--muted)';this.style.borderColor='var(--border2)'">Ir para Definições do Perfil →</a>
    </div>

<?php elseif ($section === 'frame'): ?>
    <div class="content-title">Frame do Avatar</div>
    <div class="content-sub">Seleciona uma frame para aparecer à volta do teu avatar. As frames são desbloqueadas na loja.</div>
    <?php if (empty($myFrames)): ?>
    <div class="empty-inv"><div class="empty-inv-icon">🖼️</div><p>Ainda não tens frames desbloqueadas.</p><a href="loja.php" class="goto-shop">🛒 Ir à Loja</a></div>
    <?php else: ?>
    <div class="items-grid">
        <!-- Nenhuma frame -->
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="field" value="frame_key">
            <input type="hidden" name="value" value="">
            <button type="submit" class="item-tile <?php echo empty($config['frame_key'])?'selected':''; ?>" style="width:100%;text-align:left;cursor:pointer">
                <div class="item-tile-preview">
                    <div class="tile-av"><?php echo $initials; ?></div>
                </div>
                <div class="item-tile-body">
                    <div class="item-tile-name">Nenhuma</div>
                    <div class="item-tile-desc">Sem frame</div>
                </div>
            </button>
        </form>
        <?php foreach ($myFrames as $f): ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="field" value="frame_key">
            <input type="hidden" name="value" value="<?php echo htmlspecialchars($f['item_key']); ?>">
            <button type="submit" class="item-tile <?php echo $config['frame_key']===$f['item_key']?'selected':''; ?>" style="width:100%;text-align:left;cursor:pointer">
                <div class="item-tile-preview">
                    <div class="tile-av" style="<?php echo htmlspecialchars($f['css_value']); ?>"><?php echo $initials; ?></div>
                </div>
                <div class="item-tile-body">
                    <div class="item-tile-name"><?php echo sanitize($f['name']); ?></div>
                    <div class="item-tile-desc"><?php echo sanitize($f['description']); ?></div>
                </div>
            </button>
        </form>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

<?php elseif ($section === 'background'): ?>
    <div class="content-title">Fundo da Página</div>
    <div class="content-sub">Escolhe o fundo que aparece no teu perfil público. Visível para todos os utilizadores.</div>
    <?php if (empty($myBgs)): ?>
    <div class="empty-inv"><div class="empty-inv-icon">🌌</div><p>Ainda não tens fundos desbloqueados.</p><a href="loja.php" class="goto-shop">🛒 Ir à Loja</a></div>
    <?php else: ?>
    <div class="items-grid">
        <!-- Padrão -->
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="field" value="background_key">
            <input type="hidden" name="value" value="">
            <button type="submit" class="item-tile <?php echo empty($config['background_key'])?'selected':''; ?>" style="width:100%;text-align:left;cursor:pointer">
                <div class="item-tile-preview" style="background:#0a0a0f">
                    <span style="font-size:24px">🌑</span>
                </div>
                <div class="item-tile-body">
                    <div class="item-tile-name">Padrão</div>
                    <div class="item-tile-desc">Fundo padrão do fórum</div>
                </div>
            </button>
        </form>
        <?php foreach ($myBgs as $bg):
            preg_match('/--custom-bg:\s*([^;]+)/', $bg['css_value'], $m);
            $bgCol = trim($m[1] ?? '#0a0a0f');
        ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="field" value="background_key">
            <input type="hidden" name="value" value="<?php echo htmlspecialchars($bg['item_key']); ?>">
            <button type="submit" class="item-tile <?php echo $config['background_key']===$bg['item_key']?'selected':''; ?>" style="width:100%;text-align:left;cursor:pointer">
                <div class="item-tile-preview" style="background:<?php echo htmlspecialchars($bgCol); ?>">
                    <div style="font-family:'Space Mono',monospace;font-size:10px;color:rgba(255,255,255,0.5);text-align:center;padding:8px"><?php echo sanitize($bg['name']); ?></div>
                </div>
                <div class="item-tile-body">
                    <div class="item-tile-name"><?php echo sanitize($bg['name']); ?></div>
                    <div class="item-tile-desc"><?php echo sanitize($bg['description']); ?></div>
                </div>
            </button>
        </form>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

<?php elseif ($section === 'banner'): ?>
    <div class="content-title">Banner do Perfil</div>
    <div class="content-sub">Cola o URL de uma imagem ou GIF para o banner que aparece no topo do teu perfil.</div>
    <div style="max-width:520px">
        <div class="banner-preview-box" id="bannerPreviewBox" style="background:<?php echo $bannerUrl?'transparent':'var(--surface2)'; ?>">
            <?php if ($bannerUrl): ?>
            <img src="<?php echo sanitize($bannerUrl); ?>" id="bannerPreviewImg" style="width:100%;height:100%;object-fit:cover">
            <?php else: ?>
            <span id="bannerPreviewPlaceholder" style="font-size:28px">🖼️</span>
            <img id="bannerPreviewImg" style="display:none;width:100%;height:100%;object-fit:cover">
            <?php endif; ?>
        </div>
        <form method="POST" id="bannerForm">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="field" value="banner_url">
            <label class="banner-label">URL da imagem ou GIF</label>
            <input type="text" name="value" id="bannerInput" class="banner-input"
                value="<?php echo sanitize($bannerUrl); ?>"
                placeholder="https://i.imgur.com/exemplo.gif"
                oninput="livePreviewBanner(this.value)">
            <div class="banner-hint" style="margin-bottom:16px">Suporta GIFs animados · Recomendado: <a href="https://imgur.com" target="_blank" style="color:var(--accent)">imgur.com</a> · <a href="https://giphy.com" target="_blank" style="color:var(--accent)">giphy.com</a></div>
            <div style="display:flex;gap:10px">
                <button type="submit" class="save-btn">💾 GUARDAR BANNER</button>
                <button type="button" onclick="clearBannerField()" style="background:rgba(255,68,68,0.08);border:1px solid rgba(255,68,68,0.2);border-radius:10px;padding:12px 18px;color:#ff8888;font-family:'Space Mono',monospace;font-size:11px;cursor:pointer">✕ Remover</button>
            </div>
        </form>
    </div>

<?php elseif ($section === 'accent'): ?>
    <div class="content-title">Cor de Destaque</div>
    <div class="content-sub">Escolhe a cor de destaque que aparece no teu perfil público. Altera links, bordas e elementos de UI.</div>
    <?php if (empty($myAccents)): ?>
    <div class="empty-inv"><div class="empty-inv-icon">🎨</div><p>Ainda não tens cores desbloqueadas.</p><a href="loja.php" class="goto-shop">🛒 Ir à Loja</a></div>
    <?php else: ?>
    <div class="accent-grid" style="margin-bottom:28px">
        <!-- Padrão -->
        <form method="POST" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="field" value="accent_color">
            <input type="hidden" name="value" value="">
            <button type="submit" class="accent-swatch <?php echo empty($config['accent_color'])?'selected':''; ?>" style="background:#00e5ff;box-shadow:0 0 10px #00e5ff44" title="Padrão (Cyan)"></button>
        </form>
        <?php foreach ($myAccents as $ac): ?>
        <form method="POST" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="field" value="accent_color">
            <input type="hidden" name="value" value="<?php echo htmlspecialchars($ac['css_value']); ?>">
            <button type="submit" class="accent-swatch <?php echo $config['accent_color']===$ac['css_value']?'selected':''; ?>" style="background:<?php echo htmlspecialchars($ac['css_value']); ?>;box-shadow:0 0 10px <?php echo htmlspecialchars($ac['css_value']); ?>44" title="<?php echo sanitize($ac['name']); ?>"></button>
        </form>
        <?php endforeach; ?>
    </div>
    <div style="background:var(--surface);border:1px solid var(--border2);border-radius:12px;padding:16px 18px;max-width:400px">
        <div style="font-family:'Space Mono',monospace;font-size:9px;color:var(--muted);letter-spacing:2px;text-transform:uppercase;margin-bottom:10px">Pré-visualização</div>
        <div style="font-size:13px;color:var(--muted);margin-bottom:8px">Link de exemplo: <a href="#" style="color:<?php echo htmlspecialchars($accentColor); ?>;text-decoration:none">Ver comunidade →</a></div>
        <div style="height:3px;background:<?php echo htmlspecialchars($accentColor); ?>;border-radius:100px;width:60%;opacity:0.6;margin-bottom:8px"></div>
        <div style="border:1px solid <?php echo htmlspecialchars($accentColor); ?>44;border-radius:8px;padding:8px 12px;font-family:'Space Mono',monospace;font-size:10px;color:<?php echo htmlspecialchars($accentColor); ?>">Elemento destacado</div>
    </div>
    <?php endif; ?>

<?php elseif ($section === 'badges'): ?>
    <div class="content-title">Top 3 Badges</div>
    <div class="content-sub">Seleciona até 3 badges/insígnias para destacar no teu perfil.</div>

    <?php if (empty($myItems)): ?>
        <div class="empty-inv"><div class="empty-inv-icon">🏅</div><p>Ainda não tens badges desbloqueados.</p><a href="loja.php" class="goto-shop">🛒 Ir à Loja</a></div>
    <?php else: ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="field" value="top_badges">

            <div class="items-grid">
                <?php
                $currentBadges = json_decode($config['top_badges'] ?? '[]', true);
                foreach ($myBadges as $item):
                    $isSelected = in_array((int)$item['id'], $currentBadges);
                ?>
                <label class="item-tile <?php echo $isSelected ? 'selected' : ''; ?>" style="cursor:pointer">
                    <input type="checkbox" name="badges[]" value="<?php echo $item['id']; ?>" <?php echo $isSelected ? 'checked' : ''; ?> style="display:none" onchange="this.parentElement.classList.toggle('selected', this.checked); updateBadgeCount()">
                    <div class="item-tile-preview">
                        <span style="font-size:32px"><?php echo htmlspecialchars($item['css_value']); ?></span>
                    </div>
                    <div class="item-tile-body">
                        <div class="item-tile-name"><?php echo sanitize($item['name']); ?></div>
                        <div class="item-tile-desc"><?php echo sanitize($item['description']); ?></div>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>

            <div style="margin-top:24px; display:flex; align-items:center; gap:20px">
                <button type="submit" class="save-btn">💾 GUARDAR SELEÇÃO</button>
                <span id="badgeCount" style="font-family:'Space Mono',monospace; font-size:12px; color:var(--muted)">0 / 3 selecionados</span>
            </div>
        </form>
        <script>
        function updateBadgeCount() {
            const checked = document.querySelectorAll('input[name="badges[]"]:checked');
            document.getElementById('badgeCount').textContent = checked.length + ' / 3 selecionados';
            if (checked.length > 3) {
                alert('Podes selecionar no máximo 3 badges.');
                event.target.checked = false;
                event.target.parentElement.classList.remove('selected');
                updateBadgeCount();
            }
        }
        updateBadgeCount();
        </script>
    <?php endif; ?>

<?php endif; ?>

<!-- ── Preview sidebar direita ── -->
<aside class="preview-sidebar">
    <div class="preview-label">Pré-visualização do Perfil</div>

    <!-- Mini-perfil -->
    <div class="mini-profile">
        <div class="mini-banner" id="previewBanner">
            <?php if ($bannerUrl): ?><img src="<?php echo sanitize($bannerUrl); ?>" id="previewBannerImg" style="width:100%;height:100%;object-fit:cover"><?php else: ?><div class="mini-banner-grid"></div><img id="previewBannerImg" style="display:none"><?php endif; ?>
        </div>
        <div class="mini-body">
            <div class="mini-av-row">
                <div class="mini-av" id="previewAv" style="<?php echo $frameCSS; ?>">
                    <?php if (!empty($currentUser['avatar_url'])): ?><img src="<?php echo sanitize(avPath($currentUser['avatar_url'])); ?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%"><?php else: echo $initials; endif; ?>
                </div>
                <div>
                    <div class="mini-online">Online</div>
                </div>
            </div>
            <div class="mini-name"><?php echo sanitize($currentUser['full_name']); ?></div>
            <div class="mini-username">@<?php echo sanitize($currentUser['username']); ?></div>
            <div class="mini-stats">
                <div class="mini-stat"><div class="mini-stat-n" id="previewAccentEl" style="color:<?php echo htmlspecialchars($accentColor); ?>">—</div><div class="mini-stat-l">Posts</div></div>
                <div class="mini-stat"><div class="mini-stat-n" style="color:<?php echo htmlspecialchars($accentColor); ?>">—</div><div class="mini-stat-l">Karma</div></div>
                <div class="mini-stat"><div class="mini-stat-n" style="color:<?php echo htmlspecialchars($accentColor); ?>">—</div><div class="mini-stat-l">XP</div></div>
            </div>
        </div>
    </div>

    <!-- Fundo preview -->
    <div class="preview-label">Fundo da Página</div>
    <div class="bg-preview-box" id="bgPreviewBox" style="background:<?php echo htmlspecialchars($bgPreviewColor); ?>">
        <span class="bg-preview-label" id="bgPreviewLabel"><?php echo empty($config['background_key']) ? 'PADRÃO' : strtoupper($config['background_key']); ?></span>
    </div>

    <!-- Cor de destaque preview -->
    <div class="preview-label">Cor de Destaque</div>
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px">
        <div style="width:36px;height:36px;border-radius:50%;background:<?php echo htmlspecialchars($accentColor); ?>;box-shadow:0 0 12px <?php echo htmlspecialchars($accentColor); ?>66" id="accentPreviewDot"></div>
        <div style="font-family:'Space Mono',monospace;font-size:11px;color:var(--muted)" id="accentPreviewHex"><?php echo htmlspecialchars($accentColor); ?></div>
    </div>

    <!-- Moedas -->
    <div style="background:rgba(255,204,0,0.06);border:1px solid rgba(255,204,0,0.2);border-radius:10px;padding:14px;margin-top:auto">
        <div style="font-family:'Space Mono',monospace;font-size:9px;color:var(--muted);letter-spacing:2px;text-transform:uppercase;margin-bottom:6px">Moedas</div>
        <div style="font-family:'Syne',sans-serif;font-size:24px;font-weight:900;color:#ffcc00;margin-bottom:4px">🪙 <?php echo number_format($coins); ?></div>
        <div style="font-size:11px;color:var(--muted);margin-bottom:10px">+10 por post · +3 por resposta · +2 por voto</div>
        <a href="loja.php" style="display:block;text-align:center;background:rgba(255,204,0,0.1);border:1px solid rgba(255,204,0,0.25);border-radius:8px;padding:8px;color:#ffcc00;font-family:'Space Mono',monospace;font-size:10px;font-weight:700;text-decoration:none;transition:all 0.2s" onmouseover="this.style.background='rgba(255,204,0,0.2)'" onmouseout="this.style.background='rgba(255,204,0,0.1)'">🛒 Ir à Loja</a>
    </div>
</aside>

</div><!-- /.page -->

<script>
// Preview do banner em tempo real
function livePreviewBanner(url) {
    var img   = document.getElementById('bannerPreviewImg');
    var ph    = document.getElementById('bannerPreviewPlaceholder');
    var box   = document.getElementById('bannerPreviewBox');
    var pImg  = document.getElementById('previewBannerImg');
    if (!img) return;
    if (url && url.match(/^https?:\/\//)) {
        img.onload  = function(){ if(ph) ph.style.display='none'; img.style.display='block'; box.style.background='transparent'; };
        img.onerror = function(){ img.style.display='none'; if(ph) ph.style.display='block'; };
        img.src = url;
        if (pImg) { pImg.src=url; pImg.style.display='block'; }
    } else {
        img.style.display='none';
        if(ph) ph.style.display='block';
        if(pImg) pImg.style.display='none';
    }
}

function clearBannerField() {
    var inp = document.getElementById('bannerInput');
    if (inp) { inp.value = ''; livePreviewBanner(''); }
}

// Highlight do item seleccionado (visual feedback antes de submeter)
document.querySelectorAll('.item-tile').forEach(function(tile) {
    tile.addEventListener('mouseenter', function(){ if(!this.classList.contains('selected')) this.style.borderColor='rgba(0,229,255,0.4)'; });
    tile.addEventListener('mouseleave', function(){ if(!this.classList.contains('selected')) this.style.borderColor=''; });
});
</script>
</body>
</html>