<?php
/**
 * ai.php — Assistente Print AI (Limpo e Restaurado)
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

// ── Renderizar markdown no lado do servidor ───────────────────
function renderMarkdown(string $text): string {
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    // Blocos de código
    $text = preg_replace('/```\w*\n?([\s\S]*?)```/', '<pre><code>$1</code></pre>', $text);
    $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);
    // Tipografia
    $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
    $text = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $text);
    // Títulos e Listas
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
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800&family=Inter:wght@300;400;500&display=swap">
<style>
:root {
    --bg:       #0d0d12;
    --surface:  #14141c;
    --surface2: #1c1c28;
    --surface3: #252535;
    --accent:   #00e5ff;
    --accent-rgb: 0, 229, 255;
    --accent2:  #ff6b35;
    --accent2-rgb: 255, 107, 53;
    --accent3:  #7c3aed;
    --accent4:  #00ff88;
    --text:     #e8e8f0;
    --muted:    #888899;
    --border:   rgba(0,229,255,0.15);
    --border2:  rgba(255,255,255,0.06);
}
body.orange-mode {
    --accent: var(--accent2);
    --accent-rgb: var(--accent2-rgb);
    --border: rgba(255,107,53,0.25);
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body { height: 100%; overflow: hidden; }
body { background: var(--bg); color: var(--text); font-family: 'Inter', sans-serif; font-size: 14px; display: flex; flex-direction: column; transition: background 0.3s; }

/* ── LAYOUT ─────────────────────────────────────────────────── */
.app-layout { display: flex; flex: 1; overflow: hidden; height: 100vh; }

/* ── RAILS SIDEBAR ─────────────────────────────────────────── */
.rails-sidebar {
    width: 52px; background: #08080c; border-right: 1px solid var(--border2);
    display: flex; flex-direction: column; align-items: center; padding: 16px 0; gap: 20px; flex-shrink: 0; z-index: 60;
}
.rails-btn {
    width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;
    border-radius: 10px; color: var(--muted); cursor: pointer; transition: all 0.2s;
    font-size: 18px; text-decoration: none; border: 1px solid transparent; background: none;
}
.rails-btn:hover, .rails-btn.active { color: var(--accent); background: rgba(0,229,255,0.05); border-color: rgba(0,229,255,0.1); }
.rails-btn.new { color: var(--text); border-color: var(--border2); margin-top: 4px; }
.rails-spacer { flex: 1; }
.rails-avatar {
    width: 32px; height: 32px; border-radius: 50%;
    background: linear-gradient(135deg, var(--accent3), var(--accent));
    color: #fff; font-size: 10px; font-weight: 800;
    display: flex; align-items: center; justify-content: center; overflow: hidden;
    margin-bottom: 4px; border: 1px solid var(--border2); text-decoration: none;
}
.rails-avatar img { width: 100%; height: 100%; object-fit: cover; }

/* ── SIDEBAR (HISTORY) ───────────────────────────────────────── */
.sidebar {
    width: 260px; flex-shrink: 0; background: var(--surface);
    border-right: 1px solid var(--border2); display: flex; flex-direction: column;
    overflow: hidden; transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
    .sidebar.collapsed { width: 0; border-right: none; }
    .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); z-index: 90; }
    @media (max-width: 768px) {
        .sidebar { position: fixed; left: -280px; top: 0; bottom: 0; z-index: 100; box-shadow: 20px 0 50px rgba(0,0,0,0.5); width: 280px; }
        .sidebar.open { left: 0; }
        .sidebar-overlay.open { display: block; }
    }
.sidebar-label { font-family: 'Space Mono', monospace; font-size: 10px; font-weight: 700; color: var(--muted); letter-spacing: 2px; text-transform: uppercase; margin-bottom: 12px; }
.new-conv-btn {
    width: 100%; background: var(--surface2); color: var(--text); border: 1px solid var(--border2); border-radius: 10px;
    padding: 10px 14px; font-family: 'Space Mono', monospace; font-size: 11px; font-weight: 600;
    cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.2s; text-decoration: none;
}
.new-conv-btn:hover { background: var(--surface3); border-color: var(--accent); color: var(--accent); }

.sidebar-list { flex: 1; overflow-y: auto; padding: 8px; min-width: 260px; }
.conv-item {
    display: flex; align-items: center; gap: 10px; padding: 10px 12px;
    border-radius: 10px; cursor: pointer; text-decoration: none; color: var(--muted);
    transition: all 0.2s; margin-bottom: 2px; position: relative; border: 1px solid transparent;
}
.conv-item:hover { background: var(--surface2); color: var(--text); }
.conv-item.active { background: rgba(0,229,255,0.06); color: var(--text); border-color: rgba(0,229,255,0.1); }
.conv-title { font-size: 12.5px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1; }
.conv-delete { opacity: 0; background: none; border: none; cursor: pointer; color: var(--muted); font-size: 16px; padding: 4px; transition: all 0.15s; }
.conv-item:hover .conv-delete { opacity: 1; }
.conv-delete:hover { color: var(--accent2); }

/* ── CHAT AREA ──────────────────────────────────────────────── */
.chat-area { flex: 1; display: flex; flex-direction: column; overflow: hidden; background: var(--bg); position: relative; }

.messages { flex: 1; overflow-y: auto; padding: 40px 20px; display: flex; flex-direction: column; scroll-behavior: smooth; }
.messages-inner { max-width: 800px; margin: 0 auto; width: 100%; display: flex; flex-direction: column; gap: 32px; }

/* Typing Indicator */
.typing { display: flex; gap: 4px; padding: 12px 18px; background: var(--surface2); border-radius: 18px; width: fit-content; border-top-left-radius: 4px; margin-top: 10px; }
.typing span { width: 6px; height: 6px; background: var(--muted); border-radius: 50%; animation: blink 1.4s infinite both; }
.typing span:nth-child(2) { animation-delay: 0.2s; }
.typing span:nth-child(3) { animation-delay: 0.4s; }
@keyframes blink { 0%, 80%, 100% { opacity: 0.2; } 40% { opacity: 1; } }

/* Bubbles */
.msg { display: flex; gap: 20px; animation: msgIn 0.3s ease-out; }
@keyframes msgIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; } }
.msg.user { flex-direction: row-reverse; }

.msg-av { width: 36px; height: 36px; border-radius: 10px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 18px; overflow: hidden; }
.msg.user .msg-av { background: var(--accent3); color: #fff; font-family: 'Space Mono', monospace; font-size: 11px; font-weight: 800; }
.msg.assistant .msg-av { background: #1a1a24; border: 1px solid var(--border); color: var(--accent); }

.msg-body { flex: 1; min-width: 0; }
.msg-bubble { font-size: 15px; line-height: 1.7; word-break: break-word; color: var(--text); }
.msg-bubble strong { color: var(--accent); font-weight: 800; text-shadow: 0 0 10px rgba(var(--accent-rgb), 0.2); }
.msg.user .msg-bubble { background: var(--surface2); padding: 12px 18px; border-radius: 18px; border-top-right-radius: 4px; float: right; max-width: 85%; }
.msg-time { font-family: 'Space Mono', monospace; font-size: 10px; color: var(--muted); margin-top: 6px; display: block; }
.msg.user .msg-time { text-align: right; }

/* Welcome */
.welcome { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 40px 20px; text-align: center; }
.welcome-icon { width: 80px; height: 80px; background: linear-gradient(135deg, var(--accent3), var(--accent)); border-radius: 24px; display: flex; align-items: center; justify-content: center; font-size: 38px; margin-bottom: 24px; box-shadow: 0 0 40px rgba(0,229,255,0.15); }
.suggestions { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; width: 100%; max-width: 600px; margin-top: 30px; }
.sug-btn { background: var(--surface); border: 1px solid var(--border2); border-radius: 12px; padding: 16px; color: var(--muted); font-size: 13px; cursor: pointer; text-align: left; transition: all 0.2s; }
.sug-btn:hover { border-color: var(--accent); color: var(--text); background: var(--surface2); transform: translateY(-2px); }

/* Input Bar */
.input-area { padding: 0 20px 24px; flex-shrink: 0; }
.input-container { max-width: 800px; margin: 0 auto; }
.input-wrap {
    background: var(--surface); border: 1px solid var(--border2); border-radius: 18px;
    display: flex; align-items: flex-end; gap: 12px; padding: 12px 14px;
    transition: all 0.2s; box-shadow: 0 4px 20px rgba(0,0,0,0.2);
}
#msgInput { flex: 1; background: none; border: none; outline: none; color: var(--text); font-family: 'Inter', sans-serif; font-size: 15px; line-height: 1.6; resize: none; max-height: 200px; min-height: 24px; }

.input-actions { display: flex; align-items: center; gap: 10px; }
.mode-toggle { display: flex; background: var(--surface2); border-radius: 8px; padding: 3px; gap: 3px; }
.mode-btn { padding: 5px 10px; border-radius: 6px; border: none; background: none; font-family: 'Space Mono', monospace; font-size: 10px; color: var(--muted); cursor: pointer; transition: all 0.2s; }
.mode-btn.active { background: var(--surface3); color: var(--accent); }
#btnA.active { color: var(--accent2); border-color: rgba(255,107,53,0.3); }

.send-btn { width: 38px; height: 38px; background: var(--accent); color: #000; border: none; border-radius: 10px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 18px; transition: all 0.2s; box-shadow: 0 0 15px rgba(var(--accent-rgb), 0.3); }
    .send-btn:hover { background: #fff; transform: scale(1.05); box-shadow: 0 0 25px rgba(var(--accent-rgb), 0.5); }

    /* Mobile Menu Trigger */
    .mobile-nav { display: none; position: absolute; top: 16px; left: 16px; z-index: 80; }
    @media (max-width: 768px) {
        .mobile-nav { display: block; }
        .rails-sidebar { display: none; }
        .chat-area { padding-top: 50px; }
    }

.input-footer { text-align: center; font-size: 11px; color: var(--muted); margin-top: 10px; font-family: 'Space Mono', monospace; }

@media (max-width: 768px) {
    .rails-sidebar { display: none; }
    .sidebar { position: fixed; left: -260px; top: 0; bottom: 0; z-index: 100; }
    .sidebar.open { left: 0; width: 260px; }
}
</style>
</head>
<body>

<div class="app-layout">
    <nav class="rails-sidebar">
        <button class="rails-btn" onclick="toggleSidebar()" title="Abrir/Fechar Histórico"><i class="fas fa-folder"></i></button>
        <a href="ai.php" class="rails-btn new" title="Nova Conversa"><i class="fas fa-plus"></i></a>
        <div class="rails-spacer"></div>
        <a href="forum/" class="rails-btn" title="Fórum"><i class="fas fa-globe"></i></a>
        <a href="index.php" class="rails-btn" title="Manual"><i class="fas fa-book"></i></a>
        <?php if ($currentUser): ?>
        <a href="forum/perfil.php?id=<?= (int)$currentUser['id'] ?>" class="rails-avatar">
            <?php if (!empty($currentUser['avatar_url'])): ?>
                <img src="<?= sanitize($currentUser['avatar_url']) ?>" alt="">
            <?php else: ?>
                <?= sanitize(generateAvatar($currentUser['full_name'])) ?>
            <?php endif; ?>
        </a>
        <?php endif; ?>
    </nav>

    <aside class="sidebar collapsed" id="sidebar">
        <div class="sidebar-header" style="padding: 20px 16px 12px; border-bottom: 1px solid var(--border2); margin-bottom: 8px;">
            <div class="sidebar-label" style="margin-bottom: 0;">Histórico</div>
        </div>
        <div class="sidebar-list" id="convList">
            <?php foreach ($conversations as $conv): $isActive = (int)$conv['id'] === $activeConvId; ?>
            <a href="ai.php?conv=<?= (int)$conv['id'] ?>" class="conv-item <?= $isActive ? 'active' : '' ?>" id="ci-<?= (int)$conv['id'] ?>">
                <i class="far fa-file-alt"></i>
                <div class="conv-title"><?= sanitize($conv['title']) ?></div>
                <button class="conv-delete" onclick="deleteConv(event,<?= (int)$conv['id'] ?>)">×</button>
            </a>
            <?php endforeach; ?>
        </div>
    </aside>

    <main class="chat-area">
        <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
        <div class="mobile-nav">
            <button class="rails-btn" onclick="toggleSidebar()" style="background:var(--surface); border:1px solid var(--border2)"><i class="fas fa-bars"></i></button>
        </div>
        <div class="messages" id="messages">
            <div class="messages-inner" id="messagesInner">
                <?php if (empty($activeMessages)): ?>
                <div class="welcome" id="welcome">
                    <div class="welcome-icon">🤖</div>
                    <h2>Print AI</h2>
                    <p>O teu assistente especializado em impressão 3D.</p>
                    <div class="suggestions">
                        <button class="sug-btn" onclick="useSug(this)">Qual a temperatura para PETG?</button>
                        <button class="sug-btn" onclick="useSug(this)">Como resolver stringing?</button>
                    </div>
                </div>
                <?php else: ?>
                    <?php foreach ($activeMessages as $msg): $isUser = $msg['role'] === 'user'; ?>
                    <div class="msg <?= $isUser ? 'user' : 'assistant' ?>">
                        <div class="msg-av"><?= $isUser ? 'U' : '🤖' ?></div>
                        <div class="msg-body">
                            <div class="msg-bubble"><?= $isUser ? sanitize($msg['content']) : renderMarkdown($msg['content']) ?></div>
                            <span class="msg-time"><?= date('H:i', strtotime($msg['created_at'])) ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="input-area">
            <div class="input-container">
                <div class="input-wrap">
                    <textarea id="msgInput" placeholder="Escreve uma mensagem..." rows="1" onkeydown="handleKey(event)" oninput="autoResize(this)"></textarea>
                    <div class="input-actions">
                        <div class="mode-toggle">
                            <button class="mode-btn active" id="btnB" onclick="setMode('beginner')">BÁSICO</button>
                            <button class="mode-btn" id="btnA" onclick="setMode('advanced')">AVANÇADO</button>
                        </div>
                        <button class="send-btn" id="sendBtn" onclick="send()"><i class="fas fa-arrow-up"></i></button>
                    </div>
                </div>
                <p class="input-footer">Print AI · <kbd>Enter</kbd> envia · <kbd>Shift+Enter</kbd> nova linha</p>
            </div>
        </div>
    </main>
</div>

<script>
var CSRF = '<?= $csrf ?>';
var CONV_ID = <?= $activeConvId ?: 'null' ?>;
var MODE = localStorage.getItem('ai_mode') || 'beginner';
var LOADING = false;

function setMode(m) {
    MODE = m; localStorage.setItem('ai_mode', m);
    document.getElementById('btnB').classList.toggle('active', m==='beginner');
    document.getElementById('btnA').classList.toggle('active', m==='advanced');
    document.body.classList.toggle('orange-mode', m==='advanced');
}
setMode(MODE);

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (window.innerWidth <= 768) {
        sidebar.classList.toggle('open');
        overlay.classList.toggle('open');
    } else {
        sidebar.classList.toggle('collapsed');
    }
}
function autoResize(el) { el.style.height='auto'; el.style.height=Math.min(el.scrollHeight, 200)+'px'; }
function handleKey(e) { if(e.key==='Enter' && !e.shiftKey) { e.preventDefault(); send(); } }
function scrollBot() { const m = document.getElementById('messages'); m.scrollTop = m.scrollHeight; }

async function deleteConv(e, id) {
    e.preventDefault(); e.stopPropagation();
    if(!confirm('Apagar esta conversa?')) return;
    try {
        const res = await fetch('api/ai.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({action:'delete', conversation_id:id, csrf_token:CSRF})
        });
        const data = await res.json();
        if(data.success) {
            const el = document.getElementById('ci-'+id);
            if(el) el.remove();
            if(CONV_ID === id) window.location.href = 'ai.php';
        }
    } catch(e) { alert('Erro ao eliminar.'); }
}

function useSug(btn) { document.getElementById('msgInput').value = btn.textContent; autoResize(document.getElementById('msgInput')); send(); }

function md(text) {
    // Escape HTML para evitar XSS antes de aplicar markdown
    text = text.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');

    // Blocos de código
    text = text.replace(/```\w*\n?([\s\S]*?)```/g, '<pre><code>$1</code></pre>');
    text = text.replace(/`([^`]+)`/g, '<code>$1</code>');

    // Tipografia (Negrito e Itálico)
    text = text.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
    text = text.replace(/\*(.+?)\*/g, '<em>$1</em>');

    // Títulos e Listas
    text = text.replace(/^### (.+)$/gm, '<h3>$1</h3>');
    text = text.replace(/^- (.+)$/gm, '<li>$1</li>');
    text = text.replace(/(<li>.*<\/li>\n?)+/g, '<ul>$0</ul>');

    // Parágrafos e quebras de linha
    return text.split(/\n{2,}/).map(p => {
        p = p.trim();
        if(!p) return '';
        // Não envolver em <p> se já for um bloco estrutural
        if(p.match(/^<(ul|pre|h3)/)) return p;
        return `<p>${p.replace(/\n/g, '<br>')}</p>`;
    }).join('\n');
}

function appendMsg(role, content) {
    if(document.getElementById('welcome')) document.getElementById('welcome').remove();
    const div = document.createElement('div');
    div.className = 'msg ' + role;
    const time = new Date().getHours().toString().padStart(2,'0') + ':' + new Date().getMinutes().toString().padStart(2,'0');
    div.innerHTML = `<div class="msg-av">${role==='user'?'U':'🤖'}</div><div class="msg-body"><div class="msg-bubble">${role==='user'?content:md(content)}</div><span class="msg-time">${time}</span></div>`;
    document.getElementById('messagesInner').appendChild(div);
    scrollBot();
}

async function send() {
    if(LOADING) return;
    const inp = document.getElementById('msgInput');
    const msg = inp.value.trim();
    if(!msg) return;
    LOADING = true; inp.value = ''; inp.style.height = 'auto';
    appendMsg('user', msg);

    // Typing Indicator
    const typing = document.createElement('div');
    typing.id = 'typing-indicator';
    typing.className = 'msg assistant';
    typing.innerHTML = `<div class="msg-av">🤖</div><div class="msg-body"><div class="typing"><span></span><span></span><span></span></div></div>`;
    document.getElementById('messagesInner').appendChild(typing);
    scrollBot();

    try {
        const res = await fetch('api/ai.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({mode:'assistant', ai_mode:MODE, message:msg, conversation_id:CONV_ID, csrf_token:CSRF})
        });
        const data = await res.json();
        if(document.getElementById('typing-indicator')) document.getElementById('typing-indicator').remove();
        if(data.success) {
            appendMsg('assistant', data.reply);
            if(!CONV_ID && data.conversation_id) { CONV_ID = data.conversation_id; window.history.replaceState(null,'','ai.php?conv='+CONV_ID); }
        }
    } catch(e) {
        if(document.getElementById('typing-indicator')) document.getElementById('typing-indicator').remove();
        appendMsg('assistant', '⚠️ Erro na ligação.');
    }
    LOADING = false;
}
scrollBot();
</script>
</body>
</html>
