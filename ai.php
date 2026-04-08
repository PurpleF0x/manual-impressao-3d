<?php
/**
 * ai.php — Assistente Print AI (página completa com histórico)
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/ai_config.php';

$currentUser = isLoggedIn() ? getCurrentUser() : null;
$db = getDB();
$csrf = generateCSRFToken();

// ── Contexto inicial via query string ─────────────────────────
$sectionParam   = trim($_GET['section'] ?? '');
$validSections  = ['o-que-e','como-funciona','tipos-impressoras','iniciantes-vs-pro',
                   'filamentos','qual-usar','processo','problemas','dicas','software','glossario'];
$sectionContext = in_array($sectionParam, $validSections) ? $sectionParam : '';

$sectionLabels = [
    'o-que-e'          => 'O que é Impressão 3D',
    'como-funciona'    => 'Como Funciona',
    'tipos-impressoras' => 'Tipos de Impressoras',
    'iniciantes-vs-pro' => 'Iniciante vs Avançado',
    'filamentos'       => 'Filamentos',
    'qual-usar'        => 'Qual Filamento Usar',
    'processo'         => 'Parâmetros de Impressão',
    'problemas'        => 'Problemas e Soluções',
    'dicas'            => 'Dicas e Boas Práticas',
    'software'         => 'Software & Slicers',
    'glossario'        => 'Glossário',
];

// ── Carregar conversas do utilizador ──────────────────────────
$conversations  = [];
$activeConvId   = isset($_GET['conv']) ? (int)$_GET['conv'] : 0;
$activeMessages = [];
$activeConv     = null;

if ($currentUser) {
    $stmt = $db->prepare("SELECT * FROM ai_conversations WHERE user_id=? ORDER BY updated_at DESC LIMIT 50");
    $stmt->execute([(int)$currentUser['id']]);
    $conversations = $stmt->fetchAll();

    if ($activeConvId) {
        $found = false;
        foreach ($conversations as $c) {
            if ((int)$c['id'] === $activeConvId) { $found = true; $activeConv = $c; break; }
        }
        if (!$found) $activeConvId = 0;
    }

    if ($activeConvId) {
        $stmt2 = $db->prepare("SELECT * FROM ai_messages WHERE conversation_id=? ORDER BY created_at ASC");
        $stmt2->execute([$activeConvId]);
        $activeMessages = $stmt2->fetchAll();
    }
}

// ── Avatar helper ─────────────────────────────────────────────
function avPathAi($url) {
    if (!$url) return '';
    if (strpos($url,'http')===0) return $url;
    return ltrim($url, '/');
}

// ── Renderizar markdown no lado do servidor ───────────────────
function renderMarkdown(string $text): string {
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $text = preg_replace('/```\w*\n?([\s\S]*?)```/', '<pre><code>$1</code></pre>', $text);
    $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);
    $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
    $text = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $text);
    $text = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $text);
    $text = preg_replace('/^- (.+)$/m', '<li>$1</li>', $text);
    $text = preg_replace('/(<li>.*<\/li>\n?)+/', '<ul>$0</ul>', $text);
    $paras = preg_split('/\n{2,}/', $text);
    $out = [];
    foreach ($paras as $p) {
        $p = trim($p);
        if (!$p) continue;
        if (preg_match('/^<(ul|ol|pre|h3)/', $p)) { $out[] = $p; continue; }
        $out[] = '<p>' . nl2br($p) . '</p>';
    }
    return implode("\n", $out);
}

$pageTitle = $activeConv ? htmlspecialchars($activeConv['title']) : 'Print AI — Assistente de Impressão 3D';
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $pageTitle ?></title>
<link rel="icon" type="image/svg+xml" href="favicons/favicon-manual.svg">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800&family=Inter:wght@300;400;500&display=swap">
<style>
:root {
    --bg:       #0a0a0f;
    --surface:  #111118;
    --surface2: #1a1a26;
    --surface3: #222235;
    --accent:   #00e5ff;
    --accent2:  #ff6b35;
    --accent3:  #7c3aed;
    --accent4:  #00ff88;
    --text:     #e8e8f0;
    --muted:    #888899;
    --border:   rgba(0,229,255,0.12);
    --border2:  rgba(255,255,255,0.06);
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body { height: 100%; overflow: hidden; }
body { background: var(--bg); color: var(--text); font-family: 'Inter', sans-serif; font-size: 14px; display: flex; flex-direction: column; }

/* ── TOPBAR ─────────────────────────────────────────────────── */
.topbar {
    height: 54px; background: rgba(10,10,15,0.95); backdrop-filter: blur(16px);
    border-bottom: 1px solid var(--border2); display: flex; align-items: center;
    padding: 0 20px; gap: 16px; flex-shrink: 0; z-index: 50;
}
.topbar-logo {
    font-family: 'Space Mono', monospace; font-size: 11px; font-weight: 700;
    color: var(--accent); letter-spacing: 3px; text-transform: uppercase;
    text-decoration: none; white-space: nowrap; display: flex; align-items: center; gap: 8px;
}
.topbar-logo .logo-icon {
    width: 28px; height: 28px;
    background: linear-gradient(135deg, var(--accent3), var(--accent));
    border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 14px;
}
.topbar-sep { color: var(--border2); font-size: 18px; }
.topbar-conv-title {
    font-family: 'Syne', sans-serif; font-size: 14px; font-weight: 600;
    color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    max-width: 300px;
}
.topbar-actions { display: flex; align-items: center; gap: 8px; margin-left: auto; }
.topbar-btn {
    background: none; border: 1px solid var(--border2); border-radius: 8px; padding: 6px 12px;
    color: var(--muted); font-family: 'Space Mono', monospace; font-size: 10px; cursor: pointer;
    text-decoration: none; display: inline-flex; align-items: center; gap: 6px;
    transition: all 0.2s; white-space: nowrap;
}
.topbar-btn:hover { border-color: var(--accent); color: var(--accent); background: rgba(0,229,255,0.05); }
.topbar-btn.primary { background: var(--accent); color: #000; border-color: transparent; font-weight: 700; }
.topbar-btn.primary:hover { background: #00c8e0; }
.topbar-avatar {
    width: 30px; height: 30px; border-radius: 50%;
    background: linear-gradient(135deg, var(--accent3), var(--accent));
    display: flex; align-items: center; justify-content: center;
    font-family: 'Space Mono', monospace; font-size: 10px; font-weight: 700; color: #000;
    overflow: hidden; text-decoration: none; flex-shrink: 0;
}
.topbar-avatar img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
.sidebar-toggle { display: none; background: none; border: none; color: var(--muted); font-size: 20px; cursor: pointer; padding: 4px; flex-shrink: 0; }

/* ── LAYOUT ─────────────────────────────────────────────────── */
.app-layout { display: flex; flex: 1; overflow: hidden; }

/* ── SIDEBAR ─────────────────────────────────────────────────── */
.sidebar {
    width: 256px; flex-shrink: 0; background: var(--surface);
    border-right: 1px solid var(--border2); display: flex; flex-direction: column; overflow: hidden;
}
.sidebar-header { padding: 14px 14px 12px; border-bottom: 1px solid var(--border2); flex-shrink: 0; }
.sidebar-label {
    font-family: 'Space Mono', monospace; font-size: 9px; font-weight: 700;
    color: var(--muted); letter-spacing: 2px; text-transform: uppercase; margin-bottom: 10px;
}
.new-conv-btn {
    width: 100%; background: var(--accent); color: #000; border: none; border-radius: 8px;
    padding: 8px 14px; font-family: 'Space Mono', monospace; font-size: 10px; font-weight: 700;
    cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px;
    transition: background 0.2s; text-decoration: none; letter-spacing: 0.5px;
}
.new-conv-btn:hover { background: #00c8e0; }

.sidebar-list { flex: 1; overflow-y: auto; padding: 6px; }
.sidebar-list::-webkit-scrollbar { width: 3px; }
.sidebar-list::-webkit-scrollbar-thumb { background: var(--surface3); border-radius: 2px; }

.conv-item {
    display: flex; align-items: flex-start; gap: 8px; padding: 9px 10px;
    border-radius: 8px; cursor: pointer; text-decoration: none; color: var(--muted);
    transition: all 0.15s; margin-bottom: 1px; position: relative; border: 1px solid transparent;
}
.conv-item:hover { background: var(--surface2); color: var(--text); }
.conv-item.active { background: rgba(0,229,255,0.07); color: var(--text); border-color: rgba(0,229,255,0.15); }
.conv-icon { font-size: 13px; flex-shrink: 0; margin-top: 1px; opacity: 0.7; }
.conv-info { min-width: 0; flex: 1; }
.conv-title { font-size: 12px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; line-height: 1.3; color: inherit; }
.conv-meta { font-family: 'Space Mono', monospace; font-size: 9px; color: var(--muted); margin-top: 3px; }
.conv-section {
    font-family: 'Space Mono', monospace; font-size: 8px; padding: 2px 5px;
    border-radius: 4px; background: rgba(124,58,237,0.12); color: #c4b5fd;
    white-space: nowrap; flex-shrink: 0; margin-top: 1px; max-width: 80px;
    overflow: hidden; text-overflow: ellipsis;
}
.conv-delete {
    position: absolute; right: 4px; top: 50%; transform: translateY(-50%);
    opacity: 0; background: none; border: none; cursor: pointer; color: var(--muted);
    font-size: 15px; padding: 4px; border-radius: 4px; transition: all 0.15s; line-height: 1;
}
.conv-item:hover .conv-delete { opacity: 1; }
.conv-delete:hover { color: var(--accent2); background: rgba(255,107,53,0.1); }

.sidebar-empty { padding: 24px 14px; text-align: center; color: var(--muted); font-size: 12px; line-height: 1.6; }
.sidebar-empty .ei { font-size: 28px; margin-bottom: 10px; }

.sidebar-footer { padding: 12px 14px; border-top: 1px solid var(--border2); flex-shrink: 0; }
.guest-card {
    background: rgba(0,229,255,0.04); border: 1px solid rgba(0,229,255,0.12);
    border-radius: 10px; padding: 11px 13px; font-size: 11px; color: var(--muted); line-height: 1.55;
}
.guest-card a { color: var(--accent); text-decoration: none; font-weight: 600; }
.guest-card a:hover { text-decoration: underline; }

/* ── CHAT AREA ──────────────────────────────────────────────── */
.chat-area { flex: 1; display: flex; flex-direction: column; overflow: hidden; }

.context-banner {
    padding: 8px 24px; background: rgba(124,58,237,0.07); border-bottom: 1px solid rgba(124,58,237,0.18);
    display: flex; align-items: center; gap: 10px; font-size: 12px; color: #c4b5fd; flex-shrink: 0;
}
.context-banner a { color: #a78bfa; text-decoration: none; }
.context-banner a:hover { text-decoration: underline; }
.context-close { margin-left: auto; background: none; border: none; cursor: pointer; color: var(--muted); font-size: 16px; padding: 0 4px; line-height: 1; transition: color 0.15s; }
.context-close:hover { color: var(--text); }

.messages { flex: 1; overflow-y: auto; padding: 28px 24px; display: flex; flex-direction: column; gap: 22px; }
.messages::-webkit-scrollbar { width: 4px; }
.messages::-webkit-scrollbar-thumb { background: var(--surface3); border-radius: 2px; }

/* Welcome */
.welcome { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 40px 24px; text-align: center; }
.welcome-icon {
    width: 76px; height: 76px;
    background: linear-gradient(135deg, var(--accent3), var(--accent));
    border-radius: 22px; display: flex; align-items: center; justify-content: center;
    font-size: 34px; margin-bottom: 22px; box-shadow: 0 0 50px rgba(0,229,255,0.18);
}
.welcome h2 { font-family: 'Syne', sans-serif; font-size: 28px; font-weight: 800; color: var(--text); margin-bottom: 10px; }
.welcome p { color: var(--muted); font-size: 14px; max-width: 440px; line-height: 1.65; margin-bottom: 32px; }
.suggestions { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; width: 100%; max-width: 520px; }
.sug-btn {
    background: var(--surface2); border: 1px solid var(--border2); border-radius: 10px;
    padding: 13px 14px; color: var(--muted); font-size: 12px; cursor: pointer;
    text-align: left; transition: all 0.2s; line-height: 1.45; font-family: 'Inter', sans-serif;
}
.sug-btn:hover { border-color: var(--accent); color: var(--text); background: rgba(0,229,255,0.05); transform: translateY(-1px); }
.sug-btn .si { font-size: 16px; display: block; margin-bottom: 6px; }

/* Bubbles */
.msg { display: flex; gap: 12px; animation: msgIn 0.2s ease-out; }
@keyframes msgIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; } }
.msg.user { flex-direction: row-reverse; align-self: flex-end; max-width: 75%; }
.msg.assistant { align-self: flex-start; max-width: 82%; }

.msg-av {
    width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; font-size: 15px; overflow: hidden;
}
.msg.user .msg-av {
    background: linear-gradient(135deg, var(--accent3), var(--accent));
    font-family: 'Space Mono', monospace; font-size: 10px; font-weight: 700; color: #000;
}
.msg.user .msg-av img { width:100%;height:100%;object-fit:cover;border-radius:50%; }
.msg.assistant .msg-av { background: rgba(0,229,255,0.06); border: 1px solid var(--border); }

.msg-body { min-width: 0; }
.msg.user .msg-body { display: flex; flex-direction: column; align-items: flex-end; }

.msg-bubble { padding: 12px 16px; border-radius: 14px; font-size: 14px; line-height: 1.65; word-break: break-word; }
.msg.user .msg-bubble { background: var(--accent3); color: #fff; border-bottom-right-radius: 4px; }
.msg.assistant .msg-bubble { background: var(--surface2); color: var(--text); border: 1px solid var(--border2); border-bottom-left-radius: 4px; }

/* Markdown in assistant bubbles */
.msg.assistant .msg-bubble p { margin-bottom: 10px; }
.msg.assistant .msg-bubble p:last-child { margin-bottom: 0; }
.msg.assistant .msg-bubble strong { color: var(--accent); font-weight: 600; }
.msg.assistant .msg-bubble em { color: #c4b5fd; }
.msg.assistant .msg-bubble code { background: rgba(0,229,255,0.08); border: 1px solid rgba(0,229,255,0.15); padding: 1px 6px; border-radius: 4px; font-family: 'Space Mono', monospace; font-size: 12px; color: var(--accent); }
.msg.assistant .msg-bubble pre { background: var(--surface3); border: 1px solid var(--border2); border-radius: 8px; padding: 12px 14px; overflow-x: auto; margin: 10px 0; }
.msg.assistant .msg-bubble pre code { background: none; border: none; padding: 0; font-size: 12px; color: var(--text); }
.msg.assistant .msg-bubble ul, .msg.assistant .msg-bubble ol { padding-left: 20px; margin: 8px 0; }
.msg.assistant .msg-bubble li { margin-bottom: 4px; }
.msg.assistant .msg-bubble h3 { font-family: 'Syne', sans-serif; font-size: 14px; font-weight: 700; color: var(--accent); margin: 12px 0 6px; }

.msg-time { font-family: 'Space Mono', monospace; font-size: 9px; color: var(--muted); margin-top: 5px; padding: 0 4px; }

/* Typing */
.msg.typing .msg-bubble { padding: 14px 18px; }
.dots { display: flex; gap: 5px; align-items: center; }
.dots span { width: 7px; height: 7px; background: var(--muted); border-radius: 50%; animation: dot 1.2s infinite; }
.dots span:nth-child(2) { animation-delay: .2s; }
.dots span:nth-child(3) { animation-delay: .4s; }
@keyframes dot { 0%,60%,100%{transform:translateY(0);opacity:.4} 30%{transform:translateY(-5px);opacity:1} }

/* ── INPUT BAR ──────────────────────────────────────────────── */
.input-bar { padding: 14px 24px 18px; border-top: 1px solid var(--border2); flex-shrink: 0; }
.input-wrap {
    background: var(--surface); border: 1px solid var(--border2); border-radius: 14px;
    display: flex; align-items: flex-end; gap: 10px; padding: 10px 12px 10px 16px;
    transition: border-color 0.2s; max-width: 860px; margin: 0 auto;
}
.input-wrap:focus-within { border-color: rgba(0,229,255,0.4); }
#msgInput {
    flex: 1; background: none; border: none; outline: none; color: var(--text);
    font-family: 'Inter', sans-serif; font-size: 14px; line-height: 1.5;
    resize: none; max-height: 160px; min-height: 24px; padding: 0;
}
#msgInput::placeholder { color: var(--muted); }
.input-right { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
.mode-toggle { display: flex; background: var(--surface2); border-radius: 6px; padding: 2px; gap: 2px; }
.mode-btn {
    padding: 4px 8px; border-radius: 4px; border: none; background: none;
    font-family: 'Space Mono', monospace; font-size: 9px; color: var(--muted);
    cursor: pointer; transition: all 0.15s; letter-spacing: 0.3px;
}
.mode-btn.active { background: var(--surface3); color: var(--text); }
.send-btn {
    width: 36px; height: 36px; background: var(--accent); color: #000; border: none;
    border-radius: 10px; cursor: pointer; display: flex; align-items: center;
    justify-content: center; font-size: 18px; font-weight: 700; transition: all 0.2s; flex-shrink: 0;
}
.send-btn:hover { background: #00c8e0; transform: scale(1.05); }
.send-btn:disabled { background: var(--surface3); color: var(--muted); cursor: not-allowed; transform: none; }
.input-hint { text-align: center; font-size: 11px; color: var(--muted); margin-top: 7px; max-width: 860px; margin-left: auto; margin-right: auto; }
.input-hint a { color: var(--accent); text-decoration: none; }
.input-hint kbd { background: var(--surface2); padding: 1px 5px; border-radius: 3px; font-size: 10px; font-family: 'Space Mono', monospace; }

/* ── RESPONSIVE ─────────────────────────────────────────────── */
@media (max-width: 768px) {
    .sidebar { position: fixed; left: -260px; top: 54px; bottom: 0; z-index: 40; transition: left 0.3s; box-shadow: none; }
    .sidebar.open { left: 0; box-shadow: 4px 0 24px rgba(0,0,0,0.6); }
    .sidebar-toggle { display: block; }
    .topbar-conv-title { display: none; }
    .messages { padding: 16px; }
    .input-bar { padding: 10px 14px 14px; }
    .suggestions { grid-template-columns: 1fr; }
    .msg.user, .msg.assistant { max-width: 92%; }
}
::-webkit-scrollbar { width: 5px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--surface3); border-radius: 3px; }
</style>
</head>
<body>

<nav class="topbar">
    <button class="sidebar-toggle" onclick="toggleSidebar()" aria-label="Histórico">☰</button>
    <a href="index.php" class="topbar-logo">
        <span class="logo-icon">🤖</span>
        Print AI
    </a>
    <?php if ($activeConv): ?>
    <span class="topbar-sep">·</span>
    <span class="topbar-conv-title" id="convTitle"><?= htmlspecialchars($activeConv['title']) ?></span>
    <?php else: ?>
    <span class="topbar-conv-title" id="convTitle" style="display:none"></span>
    <?php endif; ?>
    <div class="topbar-actions">
        <a href="index.php" class="topbar-btn">← Manual</a>
        <a href="forum/index.php" class="topbar-btn">Fórum</a>
        <?php if ($currentUser): ?>
        <a href="forum/perfil.php?id=<?= (int)$currentUser['id'] ?>" class="topbar-avatar">
            <?php if (!empty($currentUser['avatar_url'])): ?>
                <img src="<?= sanitize(avPathAi($currentUser['avatar_url'])) ?>" alt="">
            <?php else: ?>
                <?= sanitize(mb_substr($currentUser['full_name'],0,2)) ?>
            <?php endif; ?>
        </a>
        <?php else: ?>
        <a href="login.php?redirect=ai.php" class="topbar-btn primary">Entrar</a>
        <?php endif; ?>
    </div>
</nav>

<div class="app-layout">

    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-label">Histórico</div>
            <a href="ai.php<?= $sectionContext ? '?section='.$sectionContext : '' ?>" class="new-conv-btn">
                <span>✦</span> Nova Conversa
            </a>
        </div>

        <div class="sidebar-list" id="convList">
            <?php if (!$currentUser): ?>
                <div class="sidebar-empty">
                    <div class="ei">💬</div>
                    <p>Entra para guardar o histórico das tuas conversas.</p>
                </div>
            <?php elseif (empty($conversations)): ?>
                <div class="sidebar-empty" id="emptyState">
                    <div class="ei">🖨️</div>
                    <p>Ainda não tens conversas.<br>Começa agora!</p>
                </div>
            <?php else: ?>
                <?php foreach ($conversations as $conv):
                    $isActive = (int)$conv['id'] === $activeConvId;
                    $ctxLabel = $sectionLabels[$conv['section_context'] ?? ''] ?? null;
                ?>
                <a href="ai.php?conv=<?= (int)$conv['id'] ?>"
                   class="conv-item <?= $isActive ? 'active' : '' ?>"
                   id="ci-<?= (int)$conv['id'] ?>">
                    <span class="conv-icon">💬</span>
                    <div class="conv-info">
                        <div class="conv-title"><?= sanitize($conv['title']) ?></div>
                        <div class="conv-meta"><?= date('d/m H:i', strtotime($conv['updated_at'])) ?></div>
                    </div>
                    <?php if ($ctxLabel): ?>
                    <span class="conv-section"><?= sanitize(mb_strtolower($ctxLabel)) ?></span>
                    <?php endif; ?>
                    <button class="conv-delete" onclick="deleteConv(event,<?= (int)$conv['id'] ?>)" title="Eliminar">×</button>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if (!$currentUser): ?>
        <div class="sidebar-footer">
            <div class="guest-card">
                <strong style="color:var(--text)">💡 Guarda o histórico</strong><br>
                <a href="login.php?redirect=ai.php">Entra</a> ou <a href="register.php">cria conta</a> para guardar as conversas em qualquer dispositivo.
            </div>
        </div>
        <?php endif; ?>
    </aside>

    <!-- CHAT -->
    <main class="chat-area">

        <?php if ($sectionContext && !$activeConvId): ?>
        <div class="context-banner" id="ctxBanner">
            <span>📖</span>
            <span>Contexto: <a href="index.php#<?= sanitize($sectionContext) ?>"><?= sanitize($sectionLabels[$sectionContext] ?? $sectionContext) ?></a></span>
            <button class="context-close" onclick="document.getElementById('ctxBanner').remove()">×</button>
        </div>
        <?php endif; ?>

        <div class="messages" id="messages">
            <?php if (empty($activeMessages)): ?>
            <div class="welcome" id="welcome">
                <div class="welcome-icon">🤖</div>
                <h2>Print AI</h2>
                <p>O teu assistente especializado em impressão 3D. Pergunta sobre filamentos, troubleshooting, slicers ou qualquer dúvida técnica.</p>
                <div class="suggestions">
                    <button class="sug-btn" onclick="useSug(this)">
                        <span class="si">🌡️</span>Qual a temperatura ideal para imprimir PETG?
                    </button>
                    <button class="sug-btn" onclick="useSug(this)">
                        <span class="si">🔧</span>A minha impressão está a fazer stringing, como resolver?
                    </button>
                    <button class="sug-btn" onclick="useSug(this)">
                        <span class="si">🖨️</span>Qual a melhor impressora para iniciantes até 300€?
                    </button>
                    <button class="sug-btn" onclick="useSug(this)">
                        <span class="si">📐</span>Como configurar o first layer no Cura?
                    </button>
                </div>
            </div>
            <?php else: ?>
                <?php foreach ($activeMessages as $msg):
                    $isUser = $msg['role'] === 'user';
                ?>
                <div class="msg <?= $isUser ? 'user' : 'assistant' ?>">
                    <div class="msg-av">
                        <?php if ($isUser): ?>
                            <?php if (!empty($currentUser['avatar_url'])): ?><img src="<?= sanitize(avPathAi($currentUser['avatar_url'])) ?>" alt=""><?php else: ?><?= sanitize(mb_substr($currentUser['full_name']??'?',0,2)) ?><?php endif; ?>
                        <?php else: ?>🤖<?php endif; ?>
                    </div>
                    <div class="msg-body">
                        <div class="msg-bubble"><?= $isUser ? sanitize($msg['content']) : renderMarkdown($msg['content']) ?></div>
                        <div class="msg-time"><?= date('H:i', strtotime($msg['created_at'])) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="input-bar">
            <div class="input-wrap">
                <textarea id="msgInput" placeholder="Faz uma pergunta sobre impressão 3D…" rows="1"
                    onkeydown="handleKey(event)" oninput="autoResize(this)"></textarea>
                <div class="input-right">
                    <div class="mode-toggle">
                        <button class="mode-btn active" id="btnB" onclick="setMode('beginner')">BÁSICO</button>
                        <button class="mode-btn" id="btnA" onclick="setMode('advanced')">AVANÇADO</button>
                    </div>
                    <button class="send-btn" id="sendBtn" onclick="send()" title="Enviar (Enter)">↑</button>
                </div>
            </div>
            <div class="input-hint">
                <?php if ($currentUser): ?>
                    Conversas guardadas automaticamente · <kbd>Enter</kbd> envia, <kbd>Shift+Enter</kbd> nova linha
                <?php else: ?>
                    <a href="login.php?redirect=ai.php">Entra</a> para guardar conversas · <kbd>Enter</kbd> para enviar
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<script>
var CSRF    = '<?= $csrf ?>';
var HAS_USER = <?= $currentUser ? 'true' : 'false' ?>;
var CONV_ID  = <?= $activeConvId ?: 'null' ?>;
var SECTION  = '<?= sanitize($sectionContext) ?>';
var MODE     = localStorage.getItem('ai_mode') || 'beginner';
var LOADING  = false;
var SEC_LABELS = <?= json_encode($sectionLabels) ?>;

// ── Avatar markup for JS-appended messages ─────────────────
<?php if ($currentUser && !empty($currentUser['avatar_url'])): ?>
var USER_AV = '<img src="<?= sanitize(avPathAi($currentUser['avatar_url'])) ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%">';
<?php elseif ($currentUser): ?>
var USER_AV = '<?= sanitize(mb_substr($currentUser['full_name']??'?',0,2)) ?>';
<?php else: ?>
var USER_AV = '👤';
<?php endif; ?>

setMode(MODE, true);
scrollBot(false);

function setMode(m, silent) {
    MODE = m;
    if (!silent) localStorage.setItem('ai_mode', m);
    document.getElementById('btnB').classList.toggle('active', m==='beginner');
    document.getElementById('btnA').classList.toggle('active', m==='advanced');
}

function autoResize(el) { el.style.height='auto'; el.style.height=Math.min(el.scrollHeight,160)+'px'; }
function handleKey(e) { if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();send();} }
function scrollBot(smooth) { var m=document.getElementById('messages'); m.scrollTo({top:m.scrollHeight,behavior:smooth?'smooth':'auto'}); }
function now() { var d=new Date(); return d.getHours().toString().padStart(2,'0')+':'+d.getMinutes().toString().padStart(2,'0'); }
function esc(s) { return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

function useSug(btn) {
    var si = btn.querySelector('.si');
    var txt = si ? btn.textContent.replace(si.textContent,'').trim() : btn.textContent.trim();
    document.getElementById('msgInput').value = txt;
    autoResize(document.getElementById('msgInput'));
    send();
}

// Lightweight markdown → HTML
function md(text) {
    text = text.replace(/```[\w]*\n?([\s\S]*?)```/g, '<pre><code>$1</code></pre>');
    text = text.replace(/`([^`]+)`/g, '<code>$1</code>');
    text = text.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
    text = text.replace(/\*(.+?)\*/g, '<em>$1</em>');
    text = text.replace(/^### (.+)$/gm, '<h3>$1</h3>');
    text = text.replace(/^- (.+)$/gm, '<li>$1</li>');
    text = text.replace(/(<li>[\s\S]*?<\/li>\n?)+/g, '<ul>$&</ul>');
    return text.split('\n\n').map(function(p){
        p=p.trim(); if(!p) return '';
        if(/^<(ul|ol|pre|h3)/.test(p)) return p;
        return '<p>'+p.replace(/\n/g,'<br>')+'</p>';
    }).filter(Boolean).join('\n');
}

function appendMsg(role, html, time) {
    var w = document.getElementById('welcome');
    if (w) w.remove();
    var av = role==='user'
        ? '<div class="msg-av">'+USER_AV+'</div>'
        : '<div class="msg-av">🤖</div>';
    var div = document.createElement('div');
    div.className = 'msg '+role;
    div.innerHTML = av+'<div class="msg-body"><div class="msg-bubble">'+html+'</div><div class="msg-time">'+(time||now())+'</div></div>';
    document.getElementById('messages').appendChild(div);
    scrollBot(true);
    return div;
}

function showTyping() {
    var d=document.createElement('div');
    d.className='msg assistant typing'; d.id='typing';
    d.innerHTML='<div class="msg-av">🤖</div><div class="msg-body"><div class="msg-bubble"><div class="dots"><span></span><span></span><span></span></div></div></div>';
    document.getElementById('messages').appendChild(d);
    scrollBot(true);
}

async function send() {
    if (LOADING) return;
    var inp = document.getElementById('msgInput');
    var msg = inp.value.trim();
    if (!msg) return;
    LOADING = true;
    inp.value = ''; inp.style.height='auto';
    document.getElementById('sendBtn').disabled = true;
    appendMsg('user', esc(msg));
    showTyping();
    try {
        var res = await fetch('api/ai.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({mode:'assistant', ai_mode:MODE, csrf_token:CSRF, message:msg, conversation_id:CONV_ID||null, section:SECTION})
        });
        var rawText = await res.text();
        var data;
        try { data = JSON.parse(rawText); } catch(parseErr) { console.error('HTTP '+res.status, rawText); appendMsg('assistant','<p style="color:var(--accent2)">Erro do servidor HTTP '+res.status+'. Ver consola F12.</p>'); LOADING=false; document.getElementById('sendBtn').disabled=false; return; }
        var t = document.getElementById('typing'); if(t) t.remove();
        if (data.success) {
            appendMsg('assistant', md(data.reply));
            if (!CONV_ID && data.conversation_id) {
                CONV_ID = data.conversation_id;
                history.replaceState(null,'','ai.php?conv='+CONV_ID);
                addToSidebar(CONV_ID, data.title||'Nova conversa');
            }
            if (data.title) setTitle(CONV_ID, data.title);
        } else {
            appendMsg('assistant','<p style="color:var(--accent2)">⚠️ '+esc(data.error||'Erro desconhecido.')+'</p>');
        }
    } catch(e) {
        var t=document.getElementById('typing'); if(t) t.remove();
        console.error('Fetch/parse error:', e);
        appendMsg('assistant','<p style="color:var(--accent2)">⚠️ Sem resposta do servidor. Verifica a tua ligação e tenta novamente.</p>');
    }
    LOADING=false;
    document.getElementById('sendBtn').disabled=false;
    document.getElementById('msgInput').focus();
}

function addToSidebar(id, title) {
    if (!HAS_USER) return;
    var list = document.getElementById('convList');
    var empty = list.querySelector('.sidebar-empty'); if(empty) empty.remove();
    var badge = SECTION && SEC_LABELS[SECTION] ? '<span class="conv-section">'+SEC_LABELS[SECTION].toLowerCase()+'</span>' : '';
    var d = new Date();
    var t = d.getDate().toString().padStart(2,'0')+'/'+(d.getMonth()+1).toString().padStart(2,'0')+' '+d.getHours().toString().padStart(2,'0')+':'+d.getMinutes().toString().padStart(2,'0');
    var a = document.createElement('a');
    a.href='ai.php?conv='+id; a.className='conv-item active'; a.id='ci-'+id;
    a.innerHTML='<span class="conv-icon">💬</span><div class="conv-info"><div class="conv-title">'+esc(title)+'</div><div class="conv-meta">'+t+'</div></div>'+badge+'<button class="conv-delete" onclick="deleteConv(event,'+id+')" title="Eliminar">×</button>';
    list.insertBefore(a, list.firstChild);
}

function setTitle(id, title) {
    var el = document.querySelector('#ci-'+id+' .conv-title');
    if (el) el.textContent = title;
    var ct = document.getElementById('convTitle');
    ct.textContent = title; ct.style.display='';
}

async function deleteConv(e, id) {
    e.preventDefault(); e.stopPropagation();
    if (!confirm('Eliminar esta conversa?')) return;
    try {
        var res = await fetch('api/ai.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({mode:'assistant',action:'delete',csrf_token:CSRF,conversation_id:id})});
        var rawText = await res.text();
        var data;
        try { data = JSON.parse(rawText); } catch(parseErr) { console.error('HTTP '+res.status, rawText); appendMsg('assistant','<p style="color:var(--accent2)">Erro do servidor HTTP '+res.status+'. Ver consola F12.</p>'); LOADING=false; document.getElementById('sendBtn').disabled=false; return; }
        if (data.success) {
            var el=document.getElementById('ci-'+id); if(el) el.remove();
            if (CONV_ID===id) window.location.href='ai.php';
        }
    } catch(e){}
}

function toggleSidebar() { document.getElementById('sidebar').classList.toggle('open'); }

// Close sidebar on outside click (mobile)
document.addEventListener('click',function(e){
    var sb=document.getElementById('sidebar');
    if(sb.classList.contains('open')&&!sb.contains(e.target)&&!e.target.closest('.sidebar-toggle')) sb.classList.remove('open');
});
</script>
</body>
</html>