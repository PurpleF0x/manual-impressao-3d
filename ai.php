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
    --bg:       #0d0d12;
    --surface:  #14141c;
    --surface2: #1c1c28;
    --surface3: #252535;
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

/* ── LAYOUT ─────────────────────────────────────────────────── */
.app-layout { display: flex; flex: 1; overflow: hidden; height: 100vh; }

/* ── RAILS SIDEBAR (ICONS) ──────────────────────────────────── */
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
.rails-btn.new:hover { color: var(--accent); border-color: var(--accent); }

.rails-spacer { flex: 1; }
.rails-avatar {
    width: 32px; height: 32px; border-radius: 50%;
    background: linear-gradient(135deg, var(--accent3), var(--accent));
    color: #000; font-size: 10px; font-weight: 800;
    display: flex; align-items: center; justify-content: center; overflow: hidden;
    margin-bottom: 4px; border: 1px solid var(--border2); text-decoration: none;
}
.rails-avatar img { width: 100%; height: 100%; object-fit: cover; }

/* ── SIDEBAR (HISTORY) ───────────────────────────────────────── */
.sidebar {
    width: 260px; flex-shrink: 0; background: var(--surface);
    border-right: 1px solid var(--border2); display: flex; flex-direction: column;
    overflow: hidden; transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1), margin-left 0.3s;
}
.sidebar.collapsed { width: 0; border-right: none; }
.sidebar-header { padding: 22px 18px 14px; border-bottom: 1px solid var(--border2); flex-shrink: 0; min-width: 260px; }
.sidebar-label {
    font-family: 'Space Mono', monospace; font-size: 10px; font-weight: 700;
    color: var(--muted); letter-spacing: 2px; text-transform: uppercase; margin-bottom: 12px;
}
.new-conv-btn {
    width: 100%; background: var(--surface2); color: var(--text); border: 1px solid var(--border2); border-radius: 10px;
    padding: 10px 14px; font-family: 'Space Mono', monospace; font-size: 11px; font-weight: 600;
    cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px;
    transition: all 0.2s; text-decoration: none;
}
.new-conv-btn:hover { background: var(--surface3); border-color: var(--accent); color: var(--accent); }

.sidebar-list { flex: 1; overflow-y: auto; padding: 8px; min-width: 260px; }
.sidebar-list::-webkit-scrollbar { width: 4px; }
.sidebar-list::-webkit-scrollbar-thumb { background: var(--surface3); border-radius: 2px; }

.conv-item {
    display: flex; align-items: center; gap: 10px; padding: 10px 12px;
    border-radius: 10px; cursor: pointer; text-decoration: none; color: var(--muted);
    transition: all 0.2s; margin-bottom: 2px; position: relative; border: 1px solid transparent;
}
.conv-item:hover { background: var(--surface2); color: var(--text); }
.conv-item.active { background: rgba(0,229,255,0.06); color: var(--text); border-color: rgba(0,229,255,0.1); }
.conv-icon { font-size: 14px; flex-shrink: 0; opacity: 0.6; }
.conv-info { min-width: 0; flex: 1; }
.conv-title { font-size: 12.5px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; line-height: 1.3; }
.conv-delete {
    opacity: 0; background: none; border: none; cursor: pointer; color: var(--muted);
    font-size: 16px; padding: 4px; border-radius: 6px; transition: all 0.15s; line-height: 1;
}
.conv-item:hover .conv-delete { opacity: 1; }
.conv-delete:hover { color: var(--accent2); background: rgba(255,107,53,0.1); }

.sidebar-empty { padding: 40px 20px; text-align: center; color: var(--muted); font-size: 12px; line-height: 1.6; }
.sidebar-empty .ei { font-size: 32px; margin-bottom: 12px; opacity: 0.5; }

/* ── CHAT AREA ──────────────────────────────────────────────── */
.chat-area { flex: 1; display: flex; flex-direction: column; overflow: hidden; background: var(--bg); position: relative; }

.chat-header {
    height: 52px; border-bottom: 1px solid var(--border2); display: flex; align-items: center;
    padding: 0 20px; gap: 12px; flex-shrink: 0; background: rgba(13,13,18,0.8); backdrop-filter: blur(10px); z-index: 40;
}
.chat-title { font-family: 'Syne', sans-serif; font-size: 14px; font-weight: 700; color: var(--text); flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.chat-actions { display: flex; align-items: center; gap: 8px; }
.chat-btn {
    background: none; border: 1px solid var(--border2); border-radius: 8px; padding: 6px 12px;
    color: var(--muted); font-family: 'Space Mono', monospace; font-size: 10px; cursor: pointer;
    text-decoration: none; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s;
}
.chat-btn:hover { border-color: var(--accent); color: var(--accent); background: rgba(0,229,255,0.05); }

.messages { flex: 1; overflow-y: auto; padding: 40px 20px; display: flex; flex-direction: column; gap: 32px; scroll-behavior: smooth; }
.messages-inner { max-width: 800px; margin: 0 auto; width: 100%; display: flex; flex-direction: column; gap: 32px; }

/* Welcome */
.welcome { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 60px 20px; text-align: center; }
.welcome-icon {
    width: 80px; height: 80px; background: linear-gradient(135deg, var(--accent3), var(--accent));
    border-radius: 24px; display: flex; align-items: center; justify-content: center;
    font-size: 38px; margin-bottom: 24px; box-shadow: 0 0 40px rgba(0,229,255,0.15);
}
.welcome h2 { font-family: 'Syne', sans-serif; font-size: 32px; font-weight: 800; color: var(--text); margin-bottom: 12px; }
.welcome p { color: var(--muted); font-size: 15px; max-width: 480px; line-height: 1.6; margin-bottom: 40px; }
.suggestions { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; width: 100%; max-width: 600px; }
.sug-btn {
    background: var(--surface); border: 1px solid var(--border2); border-radius: 12px;
    padding: 16px; color: var(--muted); font-size: 13px; cursor: pointer;
    text-align: left; transition: all 0.2s; line-height: 1.5;
}
.sug-btn:hover { border-color: var(--accent); color: var(--text); background: var(--surface2); transform: translateY(-2px); }

/* Bubbles */
.msg { display: flex; gap: 20px; animation: msgIn 0.3s ease-out; }
@keyframes msgIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; } }
.msg.user { flex-direction: row-reverse; }

.msg-av {
    width: 36px; height: 36px; border-radius: 10px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; font-size: 18px; overflow: hidden;
}
.msg.user .msg-av { background: var(--accent3); color: #fff; font-family: 'Space Mono', monospace; font-size: 11px; font-weight: 800; }
.msg.assistant .msg-av { background: #1a1a24; border: 1px solid var(--border); color: var(--accent); }

.msg-body { flex: 1; min-width: 0; }
.msg.user .msg-body { display: flex; flex-direction: column; align-items: flex-end; }

.msg-bubble { font-size: 15px; line-height: 1.7; word-break: break-word; color: var(--text); }
.msg.user .msg-bubble { background: var(--surface2); padding: 12px 18px; border-radius: 18px; border-top-right-radius: 4px; max-width: 85%; }

/* Markdown Styles */
.msg.assistant .msg-bubble p { margin-bottom: 14px; }
.msg.assistant .msg-bubble p:last-child { margin-bottom: 0; }
.msg.assistant .msg-bubble strong { color: var(--accent); font-weight: 600; }
.msg.assistant .msg-bubble code { background: rgba(0,229,255,0.08); border: 1px solid rgba(0,229,255,0.15); padding: 2px 6px; border-radius: 5px; font-family: 'Space Mono', monospace; font-size: 13px; color: var(--accent); }
.msg.assistant .msg-bubble pre { background: #08080c; border: 1px solid var(--border2); border-radius: 12px; padding: 16px; overflow-x: auto; margin: 16px 0; }
.msg.assistant .msg-bubble pre code { background: none; border: none; padding: 0; font-size: 13px; color: #ccc; }
.msg.assistant .msg-bubble h3 { font-family: 'Syne', sans-serif; font-size: 17px; font-weight: 700; color: var(--accent); margin: 24px 0 12px; }
.msg.assistant .msg-bubble ul { padding-left: 20px; margin: 12px 0; list-style: disc; }
.msg.assistant .msg-bubble li { margin-bottom: 6px; }

/* Typing Animation */
.dots { display: flex; gap: 5px; padding: 10px 0; }
.dots span { width: 8px; height: 8px; background: var(--accent); border-radius: 50%; opacity: 0.4; animation: dot 1.4s infinite; }
.dots span:nth-child(2) { animation-delay: .2s; }
.dots span:nth-child(3) { animation-delay: .4s; }
@keyframes dot { 0%,60%,100%{transform:translateY(0);opacity:.4} 30%{transform:translateY(-6px);opacity:1} }

/* ── INPUT BAR ──────────────────────────────────────────────── */
.input-area { padding: 0 20px 24px; flex-shrink: 0; }
.input-container { max-width: 800px; margin: 0 auto; position: relative; }
.input-wrap {
    background: var(--surface); border: 1px solid var(--border2); border-radius: 18px;
    display: flex; align-items: flex-end; gap: 12px; padding: 12px 14px 12px 20px;
    transition: all 0.2s; box-shadow: 0 4px 20px rgba(0,0,0,0.2);
}
.input-wrap:focus-within { border-color: rgba(0,229,255,0.3); background: var(--surface2); }
#msgInput {
    flex: 1; background: none; border: none; outline: none; color: var(--text);
    font-family: 'Inter', sans-serif; font-size: 15px; line-height: 1.6;
    resize: none; max-height: 200px; min-height: 24px; padding: 4px 0;
}
.send-btn {
    width: 40px; height: 40px; background: var(--accent); color: #000; border: none;
    border-radius: 12px; cursor: pointer; display: flex; align-items: center;
    justify-content: center; font-size: 20px; transition: all 0.2s; flex-shrink: 0;
}
.send-btn:hover:not(:disabled) { transform: scale(1.05); background: #fff; }
.send-btn:disabled { background: var(--surface3); color: var(--muted); cursor: not-allowed; }

.input-footer { text-align: center; font-size: 11px; color: var(--muted); margin-top: 10px; font-family: 'Space Mono', monospace; }

/* ── RESPONSIVE ─────────────────────────────────────────────── */
@media (max-width: 768px) {
    .rails-sidebar { display: none; }
    .sidebar { position: fixed; left: -260px; top: 0; bottom: 0; z-index: 100; }
    .sidebar.open { left: 0; width: 260px; }
    .messages { padding: 20px 15px; }
    .suggestions { grid-template-columns: 1fr; }
    .chat-header { padding: 0 15px; }
}
</style>
</head>
<body>

<div class="app-layout">

    <!-- RAILS SIDEBAR (ESTILO CHATGPT/DISCORD) -->
    <nav class="rails-sidebar">
        <button class="rails-btn" onclick="toggleSidebar()" title="Abrir/Fechar Histórico">
            <span id="sideIcon">📂</span>
        </button>
        <a href="ai.php" class="rails-btn new" title="Nova Conversa">
            <span>+</span>
        </a>

        <div class="rails-spacer"></div>

        <a href="forum/index.php" class="rails-btn" title="Ir para o Fórum">🌐</a>
        <a href="index.php" class="rails-btn" title="Voltar ao Manual">📖</a>

        <?php if ($currentUser): ?>
        <a href="forum/perfil.php?id=<?= (int)$currentUser['id'] ?>" class="rails-avatar">
            <?php if (!empty($currentUser['avatar_url'])): ?>
                <img src="<?= sanitize(avPathAi($currentUser['avatar_url'])) ?>" alt="">
            <?php else: ?>
                <?= sanitize(mb_substr($currentUser['full_name'],0,2)) ?>
            <?php endif; ?>
        </a>
        <?php else: ?>
        <a href="login.php?redirect=ai.php" class="rails-avatar" title="Entrar" style="background:var(--surface3); color:var(--muted)">?</a>
        <?php endif; ?>
    </nav>

    <!-- SIDEBAR DE HISTÓRICO -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-label">Histórico</div>
            <a href="ai.php<?= $sectionContext ? '?section='.$sectionContext : '' ?>" class="new-conv-btn">
                Nova Conversa
            </a>
        </div>

        <div class="sidebar-list" id="convList">
            <?php if (!$currentUser): ?>
                <div class="sidebar-empty">
                    <div class="ei">🔒</div>
                    <p>Entra para guardar o histórico das tuas conversas.</p>
                </div>
            <?php elseif (empty($conversations)): ?>
                <div class="sidebar-empty" id="emptyState">
                    <div class="ei">💬</div>
                    <p>Ainda não tens conversas.</p>
                </div>
            <?php else: ?>
                <?php foreach ($conversations as $conv):
                    $isActive = (int)$conv['id'] === $activeConvId;
                ?>
                <a href="ai.php?conv=<?= (int)$conv['id'] ?>"
                   class="conv-item <?= $isActive ? 'active' : '' ?>"
                   id="ci-<?= (int)$conv['id'] ?>">
                    <span class="conv-icon">📄</span>
                    <div class="conv-info">
                        <div class="conv-title"><?= sanitize($conv['title']) ?></div>
                    </div>
                    <button class="conv-delete" onclick="deleteConv(event,<?= (int)$conv['id'] ?>)" title="Eliminar">×</button>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </aside>

    <!-- ÁREA DE CHAT -->
    <main class="chat-area">
        <header class="chat-header">
            <div class="chat-title" id="convTitle">
                <?= $activeConv ? htmlspecialchars($activeConv['title']) : 'Nova Conversa' ?>
            </div>
            <div class="chat-actions">
                <a href="index.php" class="chat-btn">Manual</a>
                <?php if (!$currentUser): ?>
                <a href="login.php?redirect=ai.php" class="chat-btn" style="background:var(--accent); color:#000; border:none">Entrar</a>
                <?php endif; ?>
            </div>
        </header>

        <div class="messages" id="messages">
            <div class="messages-inner" id="messagesInner">
                <?php if (empty($activeMessages)): ?>
                <div class="welcome" id="welcome">
                    <div class="welcome-icon">🤖</div>
                    <h2>Print AI</h2>
                    <p>O teu assistente especializado em impressão 3D. Como posso ajudar hoje?</p>
                    <div class="suggestions">
                        <button class="sug-btn" onclick="useSug(this)">Qual a temperatura ideal para PETG?</button>
                        <button class="sug-btn" onclick="useSug(this)">Como resolver stringing na Ender 3?</button>
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
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="input-area">
            <div class="input-container">
                <div class="input-wrap">
                    <textarea id="msgInput" placeholder="Escreve uma mensagem..." rows="1"
                        onkeydown="handleKey(event)" oninput="autoResize(this)"></textarea>
                    <button class="send-btn" id="sendBtn" onclick="send()" title="Enviar">↑</button>
                </div>
                <p class="input-footer">Print AI pode cometer erros. Verifica informações importantes.</p>
            </div>
        </div>
    </main>
</div>

<script>
var CSRF    = '<?= $csrf ?>';
var HAS_USER = <?= $currentUser ? 'true' : 'false' ?>;
var CONV_ID  = <?= $activeConvId ?: 'null' ?>;
var MODE     = 'manual'; // Default mode for ai.php
var LOADING  = false;

<?php if ($currentUser && !empty($currentUser['avatar_url'])): ?>
var USER_AV = '<img src="<?= sanitize(avPathAi($currentUser['avatar_url'])) ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%">';
<?php elseif ($currentUser): ?>
var USER_AV = '<?= sanitize(mb_substr($currentUser['full_name']??'?',0,2)) ?>';
<?php else: ?>
var USER_AV = 'U';
<?php endif; ?>

function toggleSidebar() {
    const sb = document.getElementById('sidebar');
    const icon = document.getElementById('sideIcon');
    sb.classList.toggle('collapsed');
    // Em mobile, funciona como toggle de visibilidade
    if(window.innerWidth <= 768) {
        sb.classList.toggle('open');
    }
}

function autoResize(el) {
    el.style.height='auto';
    el.style.height=Math.min(el.scrollHeight, 200)+'px';
}

function handleKey(e) {
    if(e.key==='Enter' && !e.shiftKey) {
        e.preventDefault();
        send();
    }
}

function scrollBot() {
    const m = document.getElementById('messages');
    m.scrollTop = m.scrollHeight;
}

function useSug(btn) {
    document.getElementById('msgInput').value = btn.textContent;
    autoResize(document.getElementById('msgInput'));
    send();
}

// Simple Markdown Parser
function md(text) {
    text = text.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    text = text.replace(/```[\w]*\n?([\s\S]*?)```/g, '<pre><code>$1</code></pre>');
    text = text.replace(/`([^`]+)`/g, '<code>$1</code>');
    text = text.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
    text = text.replace(/^### (.+)$/gm, '<h3>$1</h3>');
    text = text.replace(/^- (.+)$/gm, '<li>$1</li>');

    return text.split('\n\n').map(p => {
        p = p.trim();
        if(!p) return '';
        if(p.startsWith('<pre') || p.startsWith('<h3') || p.startsWith('<li')) return p;
        return '<p>' + p.replace(/\n/g, '<br>') + '</p>';
    }).join('');
}

function appendMsg(role, content) {
    const w = document.getElementById('welcome');
    if(w) w.remove();

    const container = document.getElementById('messagesInner');
    const div = document.createElement('div');
    div.className = 'msg ' + role;

    const av = role === 'user' ? USER_AV : '🤖';
    const html = role === 'user' ? content.replace(/&/g,'&amp;').replace(/</g,'&lt;') : md(content);

    div.innerHTML = `
        <div class="msg-av">${av}</div>
        <div class="msg-body">
            <div class="msg-bubble">${html}</div>
        </div>
    `;

    container.appendChild(div);
    scrollBot();
    return div;
}

async function send() {
    if(LOADING) return;
    const inp = document.getElementById('msgInput');
    const msg = inp.value.trim();
    if(!msg) return;

    LOADING = true;
    inp.value = ''; inp.style.height = 'auto';
    document.getElementById('sendBtn').disabled = true;

    appendMsg('user', msg);

    const typing = document.createElement('div');
    typing.className = 'msg assistant';
    typing.innerHTML = '<div class="msg-av">🤖</div><div class="msg-body"><div class="dots"><span></span><span></span><span></span></div></div>';
    document.getElementById('messagesInner').appendChild(typing);
    scrollBot();

    try {
        const res = await fetch('api/ai.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({
                mode: 'assistant',
                message: msg,
                conversation_id: CONV_ID,
                csrf_token: CSRF
            })
        });

        const data = await res.json();
        typing.remove();

        if(data.success) {
            appendMsg('assistant', data.reply);
            if(!CONV_ID && data.conversation_id) {
                CONV_ID = data.conversation_id;
                window.history.replaceState(null, '', 'ai.php?conv=' + CONV_ID);
                updateSidebar(CONV_ID, data.title || msg.substring(0, 30));
            }
            if(data.title) document.getElementById('convTitle').textContent = data.title;
        } else {
            appendMsg('assistant', '⚠️ Erro: ' + (data.error || 'Desconhecido'));
        }
    } catch(e) {
        typing.remove();
        appendMsg('assistant', '⚠️ Erro na ligação ao servidor.');
    }

    LOADING = false;
    document.getElementById('sendBtn').disabled = false;
}

function updateSidebar(id, title) {
    if(!HAS_USER) return;
    const list = document.getElementById('convList');
    const empty = document.getElementById('emptyState');
    if(empty) empty.remove();

    const item = document.createElement('a');
    item.href = 'ai.php?conv=' + id;
    item.className = 'conv-item active';
    item.id = 'ci-' + id;
    item.innerHTML = `
        <span class="conv-icon">📄</span>
        <div class="conv-info"><div class="conv-title">${title}</div></div>
        <button class="conv-delete" onclick="deleteConv(event,${id})">×</button>
    `;
    list.prepend(item);
}

async function deleteConv(e, id) {
    e.preventDefault(); e.stopPropagation();
    if(!confirm('Eliminar conversa?')) return;

    try {
        const res = await fetch('api/ai.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({mode:'assistant', action:'delete', conversation_id:id, csrf_token:CSRF})
        });
        const data = await res.json();
        if(data.success) {
            document.getElementById('ci-' + id).remove();
            if(CONV_ID == id) window.location.href = 'ai.php';
        }
    } catch(e){}
}

scrollBot();
</script>
</body>
</html>