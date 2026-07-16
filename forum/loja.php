<?php
/**
 * forum/loja.php — Loja de itens de personalização
 */
require_once __DIR__ . '/../includes/functions.php';
// Helper: path do avatar relativo ao forum/
function avPath($url) {
    if (!$url) return '';
    if (strpos($url,'http')===0) return $url;
    return '../' . ltrim($url, '/');
}
if (!isLoggedIn()) { header('Location: /login?redirect=forum/loja'); exit; }
$currentUser = getCurrentUser();
$uid = (int)$currentUser['id'];
$db  = getDB();

// ── Garantir tabelas ──────────────────────────────────────────
try { $db->exec("CREATE TABLE IF NOT EXISTS shop_items (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(100) NOT NULL,
    description  VARCHAR(255),
    category     ENUM('frame','background','banner','accent','badge','medal') NOT NULL,
    item_key     VARCHAR(50) NOT NULL UNIQUE,
    css_value    TEXT NOT NULL,
    preview_css  TEXT,
    price        INT DEFAULT 100,
    source       ENUM('shop','community','achievement') DEFAULT 'shop',
    community_id INT NULL,
    is_active    TINYINT(1) DEFAULT 1,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch(Exception $e){}

try { $db->exec("CREATE TABLE IF NOT EXISTS user_inventory (
    user_id   INT NOT NULL,
    item_id   INT NOT NULL,
    obtained_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, item_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES shop_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch(Exception $e){}

try { $db->exec("CREATE TABLE IF NOT EXISTS user_profile_config (
    user_id       INT PRIMARY KEY,
    frame_key     VARCHAR(50) NULL,
    background_key VARCHAR(50) NULL,
    banner_url    VARCHAR(500) NULL,
    accent_color  VARCHAR(20) NULL,
    top_badges    TEXT NULL,
    coins         INT DEFAULT 0,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch(Exception $e){}

try { $db->exec("ALTER TABLE shop_items MODIFY COLUMN category ENUM('frame','background','banner','accent','badge','medal') NOT NULL"); } catch(Exception $e){}
try { $db->exec("ALTER TABLE user_profile_config ADD COLUMN IF NOT EXISTS top_badges TEXT NULL"); } catch(Exception $e){}
try { $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS coins INT DEFAULT 0"); } catch(Exception $e){}

// ── Seed de items ─────────────────────────────────────────────
$existingCount = (int)$db->query("SELECT COUNT(*) FROM shop_items")->fetchColumn();
if (true) { // Sempre verificar novos items (INSERT IGNORE protege duplicados
    $items = array(
        // Frames
        array('Cyan Neon','Frame neon ciano brilhante','frame','frame_cyan_neon',
            'border: 3px solid #00e5ff; box-shadow: 0 0 12px #00e5ff, 0 0 24px rgba(0,229,255,0.4); border-radius: 50%;',
            'border:3px solid #00e5ff;box-shadow:0 0 12px #00e5ff;border-radius:50%',50,'shop',null),
        array('Purple Glow','Frame roxa brilhante','frame','frame_purple_glow',
            'border: 3px solid #7c3aed; box-shadow: 0 0 12px #7c3aed, 0 0 24px rgba(124,58,237,0.5); border-radius: 50%;',
            'border:3px solid #7c3aed;box-shadow:0 0 12px #7c3aed;border-radius:50%',50,'shop',null),
        array('Fire','Frame laranja ardente','frame','frame_fire',
            'border: 3px solid #ff6b35; box-shadow: 0 0 12px #ff6b35, 0 0 20px rgba(255,107,53,0.5); border-radius: 50%;',
            'border:3px solid #ff6b35;box-shadow:0 0 12px #ff6b35;border-radius:50%',50,'shop',null),
        array('Gradient Spin','Frame com gradiente animado rotativo','frame','frame_gradient_spin',
            'border: 3px solid transparent; border-radius: 50%; background: linear-gradient(#111118,#111118) padding-box, conic-gradient(from 0deg, #00e5ff, #7c3aed, #ff6b35, #00e5ff) border-box; animation: spinFrame 3s linear infinite;',
            'border:3px solid transparent;background:linear-gradient(#111118,#111118) padding-box,conic-gradient(from 0deg,#00e5ff,#7c3aed,#ff6b35,#00e5ff) border-box;border-radius:50%',150,'shop',null),
        array('Rainbow Pulse','Frame arco-íris pulsante','frame','frame_rainbow_pulse',
            'border: 4px solid transparent; border-radius: 50%; background: linear-gradient(#111118,#111118) padding-box, linear-gradient(45deg,#ff0080,#ff6b35,#ffcc00,#00ff88,#00e5ff,#7c3aed) border-box; animation: pulseFrame 2s ease-in-out infinite;',
            'border:4px solid transparent;background:linear-gradient(#111118,#111118) padding-box,linear-gradient(45deg,#ff0080,#ff6b35,#ffcc00,#00ff88,#00e5ff,#7c3aed) border-box;border-radius:50%',200,'shop',null),
        array('Gold Legend','Frame dourada de lendário','frame','frame_gold_legend',
            'border: 4px solid #ffcc00; box-shadow: 0 0 16px #ffcc00, 0 0 32px rgba(255,204,0,0.4), inset 0 0 8px rgba(255,204,0,0.1); border-radius: 50%; animation: pulseFrame 2s ease-in-out infinite;',
            'border:4px solid #ffcc00;box-shadow:0 0 16px #ffcc00;border-radius:50%',300,'shop',null),
        array('Pixel Border','Frame pixel art 8-bit','frame','frame_pixel',
            'border: 4px solid #00ff88; image-rendering: pixelated; border-radius: 4px; box-shadow: 4px 4px 0 #007744, -4px -4px 0 #00ffaa;',
            'border:4px solid #00ff88;border-radius:4px;box-shadow:4px 4px 0 #007744,-4px -4px 0 #00ffaa',100,'shop',null),
        array('Ice Crystal','Frame cristal de gelo','frame','frame_ice',
            'border: 3px solid #a0e4ff; box-shadow: 0 0 10px rgba(160,228,255,0.7), 0 0 20px rgba(160,228,255,0.3); border-radius: 50%; filter: drop-shadow(0 0 6px #a0e4ff);',
            'border:3px solid #a0e4ff;box-shadow:0 0 10px rgba(160,228,255,0.7);border-radius:50%',120,'shop',null),
        // Backgrounds
        array('Matrix Rain','Fundo com chuva de código verde','background','bg_matrix',
            '--custom-bg: #0a0a0f; --custom-overlay: radial-gradient(ellipse at 20% 50%, rgba(0,255,65,0.05) 0%, transparent 50%), radial-gradient(ellipse at 80% 20%, rgba(0,229,255,0.04) 0%, transparent 50%);',
            'background:#0a0a0f',80,'shop',null),
        array('Deep Space','Fundo cosmos estrelado','background','bg_space',
            '--custom-bg: #050510; --custom-overlay: radial-gradient(ellipse at 30% 30%, rgba(124,58,237,0.12) 0%, transparent 40%), radial-gradient(ellipse at 70% 70%, rgba(0,229,255,0.08) 0%, transparent 40%);',
            'background:#050510',80,'shop',null),
        array('Sunset','Fundo pôr do sol','background','bg_sunset',
            '--custom-bg: #0f0510; --custom-overlay: radial-gradient(ellipse at 50% 100%, rgba(255,107,53,0.15) 0%, transparent 60%), radial-gradient(ellipse at 50% 0%, rgba(124,58,237,0.1) 0%, transparent 50%);',
            'background:#0f0510',80,'shop',null),
        array('Neon City','Fundo cidade neon','background','bg_neon_city',
            '--custom-bg: #08080f; --custom-overlay: linear-gradient(180deg, rgba(0,229,255,0.03) 0%, transparent 40%), repeating-linear-gradient(90deg, rgba(0,229,255,0.01) 0px, rgba(0,229,255,0.01) 1px, transparent 1px, transparent 60px);',
            'background:#08080f',120,'shop',null),
        array('Lava','Fundo lava borbulhante','background','bg_lava',
            '--custom-bg: #0f0500; --custom-overlay: radial-gradient(ellipse at 20% 80%, rgba(255,50,0,0.12) 0%, transparent 50%), radial-gradient(ellipse at 80% 20%, rgba(255,150,0,0.08) 0%, transparent 50%);',
            'background:#0f0500',120,'shop',null),
        // Accent colors
        array('Plasma Pink','Cor de destaque rosa plasma','accent','accent_pink',
            '#ff0080','background:#ff0080;border-radius:50%;width:24px;height:24px',60,'shop',null),
        array('Emerald','Cor de destaque esmeralda','accent','accent_emerald',
            '#00ff88','background:#00ff88;border-radius:50%;width:24px;height:24px',60,'shop',null),
        array('Solar Gold','Cor de destaque dourada solar','accent','accent_gold',
            '#ffcc00','background:#ffcc00;border-radius:50%;width:24px;height:24px',60,'shop',null),
        array('Crimson','Cor de destaque carmesim','accent','accent_crimson',
            '#ff2244','background:#ff2244;border-radius:50%;width:24px;height:24px',60,'shop',null),
        array('Violet','Cor de destaque violeta','accent','accent_violet',
            '#aa44ff','background:#aa44ff;border-radius:50%;width:24px;height:24px',60,'shop',null),
        // Badges
        array('Ajudante','Atribuído a quem ajuda a comunidade','badge','badge_helper',
            '🤝','',0,'achievement',null),
        array('Pioneiro','Membro fundador do fórum','badge','badge_pioneer',
            '🚀','',0,'achievement',null),
        array('Membro de Elite','Reconhecimento por contribuições excepcionais','badge','badge_elite',
            '💎','',0,'achievement',null),
        array('Mestre 3D','Conhecimento técnico profundo demonstrado','badge','badge_master',
            '⚙️','',0,'achievement',null),
        array('Cliente VIP','Completou a missão Entusiasta da Loja','badge','badge_shop_enthusiast',
            '🛒','',0,'achievement',null),
        array('Chama Constante','Alcançou uma streak de 7 dias','badge','badge_streak_7',
            '🔥','',0,'achievement',null),
        array('Mestre da Semana','Completou a maratona semanal de missões','badge','badge_weekly_master',
            '🌟','',0,'achievement',null),
    );
    $ins = $db->prepare("INSERT INTO shop_items (name,description,category,item_key,css_value,preview_css,price,source,community_id) VALUES (?,?,?,?,?,?,?,?,?)");
    foreach ($items as $i) { try { $ins->execute($i); } catch(Exception $e){} }
}

// ── Moedas do utilizador ──────────────────────────────────────
$config = $db->prepare("SELECT * FROM user_profile_config WHERE user_id=?");
$config->execute(array($uid));
$profileConfig = $config->fetch();
if (!$profileConfig) {
    // Tenta obter moedas legadas calculando uma vez se for o primeiro acesso à nova loja
    try {
        $postCount   = (int)$db->query("SELECT COUNT(*) FROM forum_posts WHERE user_id=$uid AND status='approved'")->fetchColumn();
        $replyCount  = (int)$db->query("SELECT COUNT(*) FROM forum_replies WHERE user_id=$uid")->fetchColumn();
        $karmaPos    = (int)$db->query("SELECT COALESCE(SUM(GREATEST(vote_score,0)),0) FROM forum_posts WHERE user_id=$uid")->fetchColumn();
        $legacyCoins = $postCount * 10 + $replyCount * 3 + $karmaPos * 2;
    } catch(Exception $e){ $legacyCoins = 0; }

    $db->prepare("INSERT INTO user_profile_config (user_id, coins) VALUES (?,?)")->execute(array($uid, $legacyCoins));
    $profileConfig = array('user_id'=>$uid,'frame_key'=>null,'background_key'=>null,'banner_url'=>null,'accent_color'=>null,'coins'=>$legacyCoins);
}
$coins = (int)($profileConfig['coins'] ?? 0);

// ── Inventário ────────────────────────────────────────────────
$inventory = array();
$inv = $db->prepare("SELECT item_id FROM user_inventory WHERE user_id=?");
$inv->execute(array($uid));
foreach ($inv->fetchAll() as $r) $inventory[] = (int)$r['item_id'];

// ── POST: comprar item ────────────────────────────────────────
$flash = ''; $flashType = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? null)) {
    $action = $_POST['action'] ?? '';
    if ($action === 'buy') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $item = $db->prepare("SELECT * FROM shop_items WHERE id=? AND is_active=1");
        $item->execute(array($itemId)); $item = $item->fetch();
        if (!$item) { $flash = 'Item não encontrado.'; $flashType = 'error'; }
        elseif (in_array($itemId, $inventory)) { $flash = 'Já tens este item.'; $flashType = 'error'; }
        elseif ($coins < (int)$item['price']) { $flash = 'Moedas insuficientes.'; $flashType = 'error'; }
        else {
            $db->prepare("INSERT INTO user_inventory (user_id,item_id) VALUES (?,?)")->execute(array($uid,$itemId));
            $newCoins = $coins - (int)$item['price'];
            $db->prepare("UPDATE user_profile_config SET coins=? WHERE user_id=?")->execute(array($newCoins,$uid));
            $coins = $newCoins;
            $inventory[] = $itemId;
            $flash = '🎉 ' . $item['name'] . ' adicionado ao teu inventário!';

            // Missão: Entusiasta da Loja
            updateMissionProgress($uid, 'shop_enthusiast');
        }
    }
}

// ── Buscar items por categoria ────────────────────────────────
$allItems = $db->query("SELECT * FROM shop_items WHERE is_active=1 ORDER BY category, price ASC")->fetchAll();
$byCategory = array('frame'=>array(),'background'=>array(),'accent'=>array(),'badge'=>array(),'medal'=>array());
foreach ($allItems as $i) { $byCategory[$i['category']][] = $i; }

$csrf = generateCSRFToken();
$unreadMsgs = 0;
try {
    $um = $db->prepare("SELECT COUNT(*) FROM private_messages WHERE receiver_id=? AND read_at IS NULL");
    $um->execute(array($uid)); $unreadMsgs = (int)$um->fetchColumn();
} catch(Exception $e){}
?><!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
    <link rel="icon" type="image/x-icon"  href="../favicons/favicon-loja.ico">
    <link rel="icon" type="image/svg+xml" href="../favicons/favicon-loja.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="../favicons/favicon-loja-32.png">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Loja de Itens — Fórum 3D</title>
<link rel="icon" type="image/x-icon" href="../favicon.ico">
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800;900&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root{--bg:#0a0a0f;--surface:#111118;--surface2:#1a1a26;--surface3:#222235;--accent:#00e5ff;--accent2:#ff6b35;--accent3:#7c3aed;--accent4:#00ff88;--text:#e8e8f0;--muted:#888899;--border:rgba(0,229,255,0.1);--border2:rgba(255,255,255,0.06)}
*{margin:0;padding:0;box-sizing:border-box}
body{background:var(--bg);color:var(--text);font-family:'Inter',sans-serif;min-height:100vh}
body::before{content:'';position:fixed;inset:0;pointer-events:none;z-index:0;background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.03'/%3E%3C/svg%3E");opacity:0.4}

.topbar{position:sticky;top:0;z-index:100;background:rgba(10,10,15,0.92);backdrop-filter:blur(16px);border-bottom:1px solid var(--border);padding:0 32px;display:flex;align-items:center;gap:16px;height:58px}
.topbar-logo{font-family:'Space Mono',monospace;font-size:11px;font-weight:700;color:var(--accent);letter-spacing:3px;text-decoration:none}
.topbar-logo span{color:var(--muted)}
.topbar-right{display:flex;align-items:center;gap:10px;margin-left:auto}
.topbar-btn{background:none;border:1px solid var(--border2);border-radius:8px;padding:7px 14px;color:var(--muted);font-family:'Space Mono',monospace;font-size:10px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;transition:all 0.2s}
.topbar-btn:hover{border-color:var(--accent);color:var(--accent)}
.topbar-av{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--accent3),var(--accent));display:flex;align-items:center;justify-content:center;font-family:'Space Mono',monospace;font-size:11px;font-weight:700;color:#000;overflow:hidden;text-decoration:none;flex-shrink:0}
.topbar-av img{width:100%;height:100%;object-fit:cover;border-radius:50%}
.notif-badge{background:var(--accent2);color:#fff;border-radius:100px;font-size:9px;padding:1px 5px;font-family:'Space Mono',monospace;font-weight:700}

/* Coins badge */
.coins-badge{display:inline-flex;align-items:center;gap:6px;background:rgba(255,204,0,0.1);border:1px solid rgba(255,204,0,0.3);border-radius:20px;padding:6px 14px;font-family:'Space Mono',monospace;font-size:12px;font-weight:700;color:#ffcc00}

/* Hero loja */
.shop-hero{padding:40px 40px 0;max-width:1100px;margin:0 auto;position:relative;z-index:1}
.shop-hero-title{font-family:'Syne',sans-serif;font-size:32px;font-weight:900;color:#fff;margin-bottom:6px}
.shop-hero-sub{font-size:14px;color:var(--muted);margin-bottom:24px}
.coins-info{display:flex;align-items:center;gap:20px;flex-wrap:wrap;margin-bottom:32px}
.coins-big{font-family:'Syne',sans-serif;font-size:42px;font-weight:900;color:#ffcc00;line-height:1}
.coins-earn{background:var(--surface);border:1px solid var(--border2);border-radius:12px;padding:14px 18px}
.coins-earn-title{font-family:'Space Mono',monospace;font-size:9px;color:var(--muted);letter-spacing:2px;text-transform:uppercase;margin-bottom:8px}
.coins-rule{display:flex;justify-content:space-between;gap:20px;font-size:12px;color:var(--muted);padding:3px 0}
.coins-rule span{font-family:'Space Mono',monospace;font-size:11px;font-weight:700;color:#ffcc00}

/* Flash */
.flash{padding:12px 18px;border-radius:10px;margin:0 40px 16px;font-size:13px;position:relative;z-index:1;max-width:1100px;margin-left:auto;margin-right:auto}
.flash.success{background:rgba(0,255,136,0.07);border:1px solid rgba(0,255,136,0.2);color:var(--accent4)}
.flash.error{background:rgba(255,68,68,0.07);border:1px solid rgba(255,68,68,0.2);color:#ff8888}

/* Tabs */
.shop-tabs{display:flex;gap:4px;padding:0 40px;max-width:1100px;margin:0 auto 24px;position:relative;z-index:1;flex-wrap:wrap}
.shop-tab{background:var(--surface);border:1px solid var(--border2);border-radius:9px;padding:9px 18px;font-family:'Space Mono',monospace;font-size:10px;color:var(--muted);cursor:pointer;transition:all 0.2s}
.shop-tab:hover{border-color:var(--accent);color:var(--accent)}
.shop-tab.active{background:rgba(0,229,255,0.08);border-color:var(--accent);color:var(--accent)}

/* Grid de items */
.shop-content{padding:0 40px 60px;max-width:1100px;margin:0 auto;position:relative;z-index:1}
.items-section{display:none}
.items-section.active{display:block}
.items-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px}

/* Item card */
.item-card{background:var(--surface);border:1px solid var(--border2);border-radius:14px;overflow:hidden;transition:all 0.2s;position:relative}
.item-card:hover{border-color:rgba(0,229,255,0.2);transform:translateY(-3px);box-shadow:0 8px 24px rgba(0,0,0,0.3)}
.item-card.owned{border-color:rgba(0,255,136,0.3)}
.item-preview{height:120px;display:flex;align-items:center;justify-content:center;background:var(--surface2);position:relative;overflow:hidden}
.preview-avatar{width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,var(--accent3),var(--accent));display:flex;align-items:center;justify-content:center;font-family:'Space Mono',monospace;font-size:18px;font-weight:700;color:#000}
.preview-bg{position:absolute;inset:0}
.preview-accent{width:40px;height:40px;border-radius:50%}
.item-body{padding:14px 16px}
.item-name{font-family:'Syne',sans-serif;font-size:14px;font-weight:700;color:#fff;margin-bottom:4px}
.item-desc{font-size:11px;color:var(--muted);margin-bottom:12px;line-height:1.4}
.item-footer{display:flex;align-items:center;justify-content:space-between}
.item-price{font-family:'Space Mono',monospace;font-size:13px;font-weight:700;color:#ffcc00;display:flex;align-items:center;gap:5px}
.item-price.free{color:var(--accent4)}
.owned-badge{font-family:'Space Mono',monospace;font-size:9px;font-weight:700;background:rgba(0,255,136,0.1);color:var(--accent4);border:1px solid rgba(0,255,136,0.25);border-radius:20px;padding:4px 10px}
.buy-btn{background:linear-gradient(135deg,#ffcc00,#ff9900);border:none;border-radius:8px;padding:8px 16px;color:#000;font-family:'Space Mono',monospace;font-size:10px;font-weight:700;cursor:pointer;transition:all 0.2s}
.buy-btn:hover{opacity:0.85;transform:scale(1.03)}
.buy-btn:disabled{opacity:0.35;cursor:not-allowed;transform:none}
.cat-badge{position:absolute;top:8px;left:8px;font-family:'Space Mono',monospace;font-size:8px;font-weight:700;padding:2px 7px;border-radius:4px;text-transform:uppercase}
.cat-frame{background:rgba(0,229,255,0.1);color:var(--accent)}
.cat-background{background:rgba(124,58,237,0.1);color:#a78bfa}
.cat-accent{background:rgba(255,204,0,0.1);color:#ffcc00}

/* Animações para preview */
@keyframes spinFrame{from{filter:hue-rotate(0deg)}to{filter:hue-rotate(360deg)}}
@keyframes pulseFrame{0%,100%{box-shadow:0 0 8px currentColor,0 0 16px rgba(255,255,255,0.1)}50%{box-shadow:0 0 16px currentColor,0 0 32px rgba(255,255,255,0.2)}}
@keyframes spinBorder{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}

/* Preview modal */
.preview-modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.85);z-index:9000;align-items:center;justify-content:center;padding:20px}
.preview-modal-overlay.open{display:flex}
.preview-modal{background:#111118;border:1px solid rgba(0,229,255,0.2);border-radius:18px;padding:28px;max-width:440px;width:100%;text-align:center;position:relative}
.preview-modal-title{font-family:'Syne',sans-serif;font-size:20px;font-weight:800;color:#fff;margin-bottom:6px}
.preview-modal-sub{font-size:12px;color:var(--muted);margin-bottom:24px}
.preview-av-wrap{width:100px;height:100px;border-radius:50%;background:linear-gradient(135deg,var(--accent3),var(--accent));display:flex;align-items:center;justify-content:center;font-family:'Space Mono',monospace;font-size:28px;font-weight:700;color:#000;margin:0 auto 20px;transition:all 0.3s;overflow:hidden}
.preview-bg-wrap{width:100%;height:120px;border-radius:12px;margin-bottom:20px;display:flex;align-items:center;justify-content:center;transition:background 0.3s;border:1px solid var(--border2)}
.preview-close{position:absolute;top:14px;right:16px;background:none;border:none;color:var(--muted);font-size:20px;cursor:pointer}
@media(max-width:768px){.shop-hero,.shop-content,.shop-tabs{padding-left:20px;padding-right:20px}}
.bc-bar{background:var(--surface);border-bottom:1px solid var(--border2);padding:8px 32px;position:relative;z-index:5}
.bc-inner{display:flex;align-items:center;gap:6px;font-family:'Space Mono',monospace;font-size:10px;color:var(--muted);flex-wrap:wrap}
.bc-link{color:var(--muted);text-decoration:none;transition:color 0.15s}.bc-link:hover{color:var(--accent)}
.bc-sep{opacity:0.4}.bc-current{color:var(--text)}
</style>
</head>
<body>

<nav class="topbar">
    <a href="/forum/" class="topbar-logo">3D<span>/</span>FÓRUM</a>
    <span style="color:var(--muted);font-size:12px">/ Loja</span>
    <div class="topbar-right">
        <div class="coins-badge">🪙 <?php echo number_format($coins); ?></div>
        <a href="/" class="topbar-btn">← Manual</a>
        <a href="mensagens" class="topbar-btn">💬<?php if($unreadMsgs>0): ?> <span class="notif-badge"><?php echo $unreadMsgs; ?></span><?php endif; ?></a>
        <a href="perfil?id=<?php echo (int)($_SESSION['user_id'] ?? 0); ?>" class="topbar-av">
            <?php $av=$currentUser['avatar_url']??''; if($av): ?><img src="<?php echo sanitize(avPath($av)); ?>" alt=""><?php else: echo mb_substr($currentUser['full_name'],0,2); endif; ?>
        </a>
    </div>
</nav>
<!-- Breadcrumb -->
<div class="bc-bar">
    <div class="bc-inner">
        <a href="/" class="bc-link">📖 Manual</a>
        <span class="bc-sep">›</span>
        <a href="/forum/" class="bc-link">🌐 Fórum</a>
        <span class="bc-sep">›</span><span class="bc-current">🛒 Loja</span>
    </div>
</div>

<?php if ($flash): ?>
<div class="flash <?php echo $flashType; ?>"><?php echo sanitize($flash); ?></div>
<?php endif; ?>

<div class="shop-hero">
    <div class="shop-hero-title">🛒 Loja de Personalização</div>
    <div class="shop-hero-sub">Usa as tuas moedas para desbloquear frames, fundos e cores de destaque para o teu perfil.</div>

    <div class="coins-info">
        <div>
            <div style="font-family:'Space Mono',monospace;font-size:10px;color:var(--muted);letter-spacing:2px;text-transform:uppercase;margin-bottom:6px">As tuas moedas</div>
            <div class="coins-big">🪙 <?php echo number_format($coins); ?></div>
        </div>
        <div class="coins-earn">
            <div class="coins-earn-title">⚡ Como ganhar moedas</div>
            <div class="coins-rule">📝 Por cada post publicado <span>+10 🪙</span></div>
            <div class="coins-rule">💬 Por cada resposta dada <span>+3 🪙</span></div>
            <div class="coins-rule">👍 Por cada voto positivo recebido <span>+2 🪙</span></div>
        </div>
    </div>
</div>

<!-- Tabs -->
<div class="shop-tabs">
    <button class="shop-tab active" onclick="switchTab('frames',this)">🖼️ Frames</button>
    <button class="shop-tab" onclick="switchTab('backgrounds',this)">🌌 Fundos</button>
    <button class="shop-tab" onclick="switchTab('accents',this)">🎨 Cores</button>
    <button class="shop-tab" onclick="switchTab('badges',this)">🏅 Emblemas</button>
</div>

<div class="shop-content">

    <!-- Frames -->
    <div class="items-section active" id="tab-frames">
        <div class="items-grid">
            <?php foreach ($byCategory['frame'] as $item):
                $owned = in_array((int)$item['id'], $inventory);
                $canAfford = $coins >= (int)$item['price'];
            ?>
            <div class="item-card <?php echo $owned?'owned':''; ?>">
                <span class="cat-badge cat-frame">FRAME</span>
                <div class="item-preview">
                    <div class="preview-avatar" style="<?php echo htmlspecialchars($item['css_value']); ?>"><?php echo mb_substr($currentUser['full_name'],0,2); ?></div>
                </div>
                <div class="item-body">
                    <div class="item-name"><?php echo sanitize($item['name']); ?></div>
                    <div class="item-desc"><?php echo sanitize($item['description']); ?></div>
                    <div class="item-footer">
                        <div class="item-price">🪙 <?php echo number_format((int)$item['price']); ?></div>
                        <?php if ($owned): ?>
                            <span class="owned-badge">✓ TENS</span>
                        <?php else: ?>
                            <div style="display:flex;gap:6px;align-items:center">
                                <button type="button" class="buy-btn" style="background:rgba(0,229,255,0.1);border:1px solid rgba(0,229,255,0.3);color:var(--accent)" onclick="previewItem('frame','<?php echo htmlspecialchars($item['css_value']); ?>','<?php echo sanitize($item['name']); ?>')">👁️ Testar</button>
                                <form method="POST" style="margin:0">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                    <input type="hidden" name="action" value="buy">
                                    <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                    <button type="submit" class="buy-btn" <?php echo !$canAfford?'disabled title="Moedas insuficientes"':''; ?>>COMPRAR</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Backgrounds -->
    <div class="items-section" id="tab-backgrounds">
        <div class="items-grid">
            <?php foreach ($byCategory['background'] as $item):
                $owned = in_array((int)$item['id'], $inventory);
                $canAfford = $coins >= (int)$item['price'];
                // Extrair preview color do css_value
                preg_match('/--custom-bg:\s*([^;]+)/', $item['css_value'], $bgMatch);
                $bgColor = $bgMatch[1] ?? '#0a0a0f';
            ?>
            <div class="item-card <?php echo $owned?'owned':''; ?>">
                <span class="cat-badge cat-background">FUNDO</span>
                <div class="item-preview" style="background:<?php echo htmlspecialchars($bgColor); ?>">
                    <div style="text-align:center">
                        <div style="font-size:28px;margin-bottom:4px">🌌</div>
                        <div style="font-family:'Space Mono',monospace;font-size:9px;color:rgba(255,255,255,0.4)"><?php echo sanitize($item['name']); ?></div>
                    </div>
                </div>
                <div class="item-body">
                    <div class="item-name"><?php echo sanitize($item['name']); ?></div>
                    <div class="item-desc"><?php echo sanitize($item['description']); ?></div>
                    <div class="item-footer">
                        <div class="item-price">🪙 <?php echo number_format((int)$item['price']); ?></div>
                        <?php if ($owned): ?>
                            <span class="owned-badge">✓ TENS</span>
                        <?php else: ?>
                            <div style="display:flex;gap:6px;align-items:center">
                                <button type="button" class="buy-btn" style="background:rgba(0,229,255,0.1);border:1px solid rgba(0,229,255,0.3);color:var(--accent)" onclick="previewItem('background','<?php echo htmlspecialchars($bgColor); ?>','<?php echo sanitize($item['name']); ?>')">👁️ Testar</button>
                                <form method="POST" style="margin:0">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                    <input type="hidden" name="action" value="buy">
                                    <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                    <button type="submit" class="buy-btn" <?php echo !$canAfford?'disabled title="Moedas insuficientes"':''; ?>>COMPRAR</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Accent colors -->
    <div class="items-section" id="tab-accents">
        <div class="items-grid">
            <?php foreach ($byCategory['accent'] as $item):
                $owned = in_array((int)$item['id'], $inventory);
                $canAfford = $coins >= (int)$item['price'];
            ?>
            <div class="item-card <?php echo $owned?'owned':''; ?>">
                <span class="cat-badge cat-accent">COR</span>
                <div class="item-preview">
                    <div style="width:64px;height:64px;border-radius:50%;background:<?php echo htmlspecialchars($item['css_value']); ?>;box-shadow:0 0 20px <?php echo htmlspecialchars($item['css_value']); ?>66"></div>
                </div>
                <div class="item-body">
                    <div class="item-name"><?php echo sanitize($item['name']); ?></div>
                    <div class="item-desc"><?php echo sanitize($item['description']); ?></div>
                    <div class="item-footer">
                        <div class="item-price">🪙 <?php echo number_format((int)$item['price']); ?></div>
                        <?php if ($owned): ?>
                            <span class="owned-badge">✓ TENS</span>
                        <?php else: ?>
                            <form method="POST" style="margin:0">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                <input type="hidden" name="action" value="buy">
                                <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                <button type="submit" class="buy-btn" <?php echo !$canAfford?'disabled title="Moedas insuficientes"':''; ?>>COMPRAR</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Badges -->
    <div class="items-section" id="tab-badges">
        <div class="items-grid">
            <?php foreach ($byCategory['badge'] as $item):
                $owned = in_array((int)$item['id'], $inventory);
                $canAfford = $coins >= (int)$item['price'];
            ?>
            <div class="item-card <?php echo $owned?'owned':''; ?>">
                <span class="cat-badge cat-accent" style="background:rgba(167,139,250,0.1);color:#a78bfa">EMBLEMA</span>
                <div class="item-preview">
                    <div style="font-size:48px"><?php echo htmlspecialchars($item['css_value']); ?></div>
                </div>
                <div class="item-body">
                    <div class="item-name"><?php echo sanitize($item['name']); ?></div>
                    <div class="item-desc"><?php echo sanitize($item['description']); ?></div>
                    <div class="item-footer">
                        <div class="item-price"><?php echo $item['source']==='achievement' ? '🏆 Conquista' : '🪙 '.number_format((int)$item['price']); ?></div>
                        <?php if ($owned): ?>
                            <span class="owned-badge">✓ TENS</span>
                        <?php elseif ($item['source'] === 'shop'): ?>
                            <form method="POST" style="margin:0">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                <input type="hidden" name="action" value="buy">
                                <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                <button type="submit" class="buy-btn" <?php echo !$canAfford?'disabled title="Moedas insuficientes"':''; ?>>COMPRAR</button>
                            </form>
                        <?php else: ?>
                            <span class="owned-badge" style="background:rgba(255,255,255,0.05);color:var(--muted)">DESBLOQUEÁVEL</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

</div>

<script>
function switchTab(tab, btn) {
    document.querySelectorAll('.shop-tab').forEach(function(b){ b.classList.remove('active'); });
    document.querySelectorAll('.items-section').forEach(function(s){ s.classList.remove('active'); });
    btn.classList.add('active');
    document.getElementById('tab-' + tab).classList.add('active');
}
</script>

<!-- Modal de preview -->
<div class="preview-modal-overlay" id="previewModalOverlay" onclick="if(event.target===this)closePreview()">
<div class="preview-modal">
    <button class="preview-close" onclick="closePreview()">✕</button>
    <div class="preview-modal-title" id="previewItemName">Preview</div>
    <div class="preview-modal-sub">Assim ficaria no teu perfil</div>
    <div id="previewContent"></div>
    <p style="font-size:12px;color:var(--muted);margin-top:12px">Compra o item para aplicares permanentemente.</p>
</div>
</div>

<script>
function previewItem(type, cssOrColor, name) {
    document.getElementById('previewItemName').textContent = name;
    var content = document.getElementById('previewContent');
    var initials = '<?php echo $initials; ?>';
    if (type === 'frame') {
        content.innerHTML = '<div class="preview-av-wrap" style="' + cssOrColor + '">' + initials + '</div>' +
            '<p style="font-size:12px;color:var(--muted)">Frame aplicada ao teu avatar</p>';
    } else if (type === 'background') {
        content.innerHTML = '<div class="preview-bg-wrap" style="background:' + cssOrColor + '">' +
            '<span style="font-family:Space Mono,monospace;font-size:11px;color:rgba(255,255,255,0.5)">Fundo da página</span></div>';
    }
    document.getElementById('previewModalOverlay').classList.add('open');
}
function closePreview() {
    document.getElementById('previewModalOverlay').classList.remove('open');
}
</script>
</body>
</html>