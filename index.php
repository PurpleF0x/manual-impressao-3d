<?php
require_once 'includes/functions.php';
require_once 'includes/user_notices.php';
require_once 'includes/missions.php';

if (isLoggedIn()) {
    $userId = (int)$_SESSION['user_id'];
    updateMissionProgress($userId, 'read_sections');
}

// --- SEO Dinâmico por Capítulo ---
$chapterMap = [
    'o-que-e-impressao-3d'      => ['id' => 'o-que-e', 'title' => 'O que é a Impressão 3D? — Guia Completo', 'desc' => 'Descobre o que é a fabricação aditiva, como funciona e por que está a revolucionar a indústria.'],
    'historia-da-impressao-3d' => ['id' => 'historia', 'title' => 'História e Evolução da Impressão 3D', 'desc' => 'Desde Chuck Hull até às bio-impressoras modernas: a linha do tempo da tecnologia 3D.'],
    'tipos-de-impressoras-3d'  => ['id' => 'tipos', 'title' => 'Tipos de Impressoras 3D (FDM, SLA, SLS)', 'desc' => 'Compara as diferentes tecnologias de impressão 3D e escolhe a melhor para o teu projeto.'],
    'materiais-e-filamentos'   => ['id' => 'materiais', 'title' => 'Guia de Materiais: PLA, PETG, ABS e mais', 'desc' => 'Tudo sobre filamentos 3D: temperaturas, resistência e aplicações de cada material.'],
    'anatomia-da-impressora'   => ['id' => 'partes', 'title' => 'Anatomia e Componentes da Impressora 3D', 'desc' => 'Conhece cada peça da tua máquina: extrusoras, hot-ends, motores e sensores.'],
    'parametros-de-impressao'  => ['id' => 'processo', 'title' => 'Parâmetros de Impressão: Altura, Infill e Velocidade', 'desc' => 'Aprende a configurar o teu slicer para obter resultados profissionais em cada peça.'],
    'problemas-comuns-solucoes' => ['id' => 'problemas', 'title' => 'Resolução de Problemas (Troubleshooting) 3D', 'desc' => 'Como resolver Warping, Stringing, Under-extrusion e outras falhas comuns.'],
    'software-essencial-3d'    => ['id' => 'software', 'title' => 'Slicers e Software de Modelação 3D', 'desc' => 'Os melhores programas gratuitos para criar e preparar os teus modelos 3D.'],
    'glossario-termos-tecnicos' => ['id' => 'glossario', 'title' => 'Glossário Técnico de Impressão 3D', 'desc' => 'Dicionário completo com todos os termos essenciais para a comunidade maker.'],
    'ferramentas-de-calculo'   => ['id' => 'ferramentas', 'title' => 'Calculadora de Custos de Impressão 3D', 'desc' => 'Ferramenta gratuita para estimar o gasto de filamento e eletricidade das tuas peças.'],
];

$reqChapter = $_GET['chapter'] ?? '';
$seo = $chapterMap[$reqChapter] ?? [
    'id' => '',
    'title' => 'Manual de Impressão 3D — Guia Educativo Completo',
    'desc' => 'Aprende tudo sobre impressão 3D: manuais técnicos, fórum da comunidade maker e assistência via IA.'
];
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf" content="<?php echo generateCSRFToken(); ?>">
<title><?php echo $seo['title']; ?></title>
<meta name="description" content="<?php echo $seo['desc']; ?>">
<link rel="canonical" href="https://manual-3d.pt/<?php echo $reqChapter ? 'manual/'.$reqChapter : ''; ?>">

<!-- OG TAGS -->
<meta property="og:type"        content="website">
<meta property="og:url"         content="https://manual-3d.pt/<?php echo $reqChapter ? 'manual/'.$reqChapter : ''; ?>">
<meta property="og:title"       content="<?php echo $seo['title']; ?>">
<meta property="og:description" content="<?php echo $seo['desc']; ?>">
<meta property="og:image"       content="https://manual-3d.pt/og-manual.png">
<meta name="twitter:card"       content="summary_large_image">
<meta name="twitter:image"      content="https://manual-3d.pt/og-manual.png">

<!-- Structured Data for Google -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "WebSite",
  "name": "Manual de Impressão 3D",
  "url": "https://manual-3d.pt/",
  "logo": "https://manual-3d.pt/og-manual.png",
  "description": "Guia educativo completo de impressão 3D — do iniciante ao avançado."
}
</script>

<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.3/dist/confetti.browser.min.js"></script>
<!-- ═══════════════════════════════════════════════════════
     IA do Manual — chatbot flutuante
     Colar antes do </body> no manual-impressao-3d.html
     ═══════════════════════════════════════════════════ -->

<style>
/* ── Missions Widget ── */
#missionsWidget {
    position: fixed;
    bottom: 24px;
    left: 304px; /* 280px (sidebar) + 24px gap */
    z-index: 1600;
    font-family: 'Inter', sans-serif;
    pointer-events: none;
}

#missions-btn {
    width: 56px; height: 56px; border-radius: 50%;
    background: linear-gradient(135deg, #ff6b35, #7c3aed);
    border: none; cursor: pointer; box-shadow: 0 4px 20px rgba(255,107,53,0.4);
    display: flex; align-items: center; justify-content: center;
    font-size: 24px; transition: all 0.3s; position: relative;
    pointer-events: auto;
}
#missions-btn:hover { transform: scale(1.08); box-shadow: 0 6px 28px rgba(255,107,53,0.55); }
#missions-btn::after {
    content: 'Missões';
    position: absolute;
    left: 66px;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(17,17,24,0.96);
    border: 1px solid rgba(255,107,53,0.26);
    border-radius: 999px;
    padding: 7px 12px;
    color: #fff;
    font-family: 'Space Mono', monospace;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 1px;
    text-transform: uppercase;
    white-space: nowrap;
    box-shadow: 0 10px 30px rgba(0,0,0,0.35);
}

#missions-panel {
    display: none; position: absolute; bottom: 68px; left: 0;
    width: 340px; max-height: 480px;
    background: #111118; border: 1px solid rgba(255,107,53,0.2);
    border-radius: 18px; box-shadow: 0 24px 60px rgba(0,0,0,0.7);
    flex-direction: column; overflow: hidden;
    animation: aiSlideUp 0.2s ease;
}
#missions-panel.open { display: flex; }

#missions-panel {
    display: none; position: absolute; bottom: 80px; right: 0;
    width: 350px; max-height: 500px;
    background: #111118; border: 1px solid rgba(255,107,53,0.3);
    border-radius: 20px; box-shadow: 0 24px 60px rgba(0,0,0,0.8);
    flex-direction: column; overflow: hidden;
    animation: aiSlideUp 0.2s ease;
}
#missions-panel.open { display: flex; }
#missions-btn .mission-notif {
    position: absolute; top: -2px; right: -2px; width: 14px; height: 14px;
    background: #00ff88; border: 1px solid #0a0a0f; border-radius: 50%;
    display: none; animation: aiBounce 2s infinite;
}

#missions-panel {
    display: none; position: absolute; bottom: 68px; left: 0;
    width: 340px; max-height: 480px;
    background: #111118; border: 1px solid rgba(255,107,53,0.2);
    border-radius: 18px; box-shadow: 0 24px 60px rgba(0,0,0,0.7);
    flex-direction: column; overflow: hidden;
    animation: aiSlideUp 0.2s ease;
    pointer-events: auto;
}
#missions-panel.open { display: flex; }

.missions-header {
    padding: 14px 18px; background: linear-gradient(135deg, rgba(255,107,53,0.08), rgba(124,58,237,0.08));
    border-bottom: 1px solid rgba(255,107,53,0.1);
    display: flex; align-items: center; justify-content: space-between;
}
.missions-header h3 { font-family: 'Syne', sans-serif; font-size: 14px; font-weight: 700; color: #fff; margin: 0; }

.missions-list { padding: 16px; overflow-y: auto; display: flex; flex-direction: column; gap: 12px; }
.mission-card {
    background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06);
    border-radius: 12px; padding: 12px; display: flex; gap: 12px; align-items: center;
    transition: all 0.2s;
}
.mission-card.completed { border-color: rgba(0,255,136,0.3); background: rgba(0,255,136,0.03); }
.mission-icon { font-size: 20px; width: 40px; height: 40px; background: rgba(255,255,255,0.05); border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.mission-info { flex: 1; }
.mission-title { font-size: 13px; font-weight: 600; color: #fff; margin-bottom: 2px; }
.mission-desc { font-size: 11px; color: var(--muted); line-height: 1.3; }
.mission-progress-bar { height: 4px; background: rgba(255,255,255,0.1); border-radius: 2px; margin-top: 8px; overflow: hidden; }
.mission-progress-fill { height: 100%; background: var(--accent2); width: 0%; transition: width 0.3s; }
.mission-card.completed .mission-progress-fill { background: #00ff88; }

.mission-reward {
    font-family: 'Space Mono', monospace; font-size: 10px; color: var(--accent2);
    display: flex; gap: 8px; margin-top: 4px;
}
.mission-reward span { color: #00ff88; }

.claim-btn {
    background: #00ff88; border: none; border-radius: 6px; padding: 6px 10px;
    font-family: 'Space Mono', monospace; font-size: 9px; font-weight: 700; color: #000;
    cursor: pointer; transition: all 0.2s;
}
.claim-btn:hover { transform: scale(1.05); }
.claim-btn:disabled { background: rgba(255,255,255,0.1); color: var(--muted); cursor: not-allowed; }

/* ── Print AI Widget ── */
#printAI { position: fixed; bottom: 24px; right: 24px; z-index: 9999; font-family: 'Inter', sans-serif; }

#printAI-btn {
    width: 56px; height: 56px; border-radius: 50%;
    background: linear-gradient(135deg, #00e5ff, #7c3aed);
    border: none; cursor: pointer; box-shadow: 0 4px 20px rgba(0,229,255,0.4);
    display: flex; align-items: center; justify-content: center;
    font-size: 24px; transition: all 0.3s; position: relative;
}
#printAI-btn:hover { transform: scale(1.08); box-shadow: 0 6px 28px rgba(0,229,255,0.55); }
#printAI-btn .ai-notif {
    position: absolute; top: -2px; right: -2px; width: 14px; height: 14px;
    background: #ff6b35; border-radius: 50%; border: 2px solid #0a0a0f;
    animation: aiBounce 2s infinite;
}
@keyframes aiBounce { 0%,100%{transform:scale(1)} 50%{transform:scale(1.3)} }

#printAI-panel {
    display: none; position: absolute; bottom: 68px; right: 0;
    width: 360px; height: 500px;
    background: #111118; border: 1px solid rgba(0,229,255,0.2);
    border-radius: 18px; box-shadow: 0 24px 60px rgba(0,0,0,0.7);
    flex-direction: column; overflow: hidden;
    animation: aiSlideUp 0.2s ease;
}
#printAI-panel.open { display: flex; }
@keyframes aiSlideUp { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }

.ai-header {
    padding: 14px 18px; background: linear-gradient(135deg, rgba(0,229,255,0.08), rgba(124,58,237,0.08));
    border-bottom: 1px solid rgba(0,229,255,0.1);
    display: flex; align-items: center; gap: 10px;
}
.ai-header-av {
    width: 36px; height: 36px; border-radius: 50%;
    background: linear-gradient(135deg, #00e5ff, #7c3aed);
    display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0;
}
.ai-header-name { font-family: 'Syne', sans-serif; font-size: 14px; font-weight: 700; color: #fff; }
.ai-header-status { font-size: 11px; color: #00ff88; display: flex; align-items: center; gap: 4px; }
.ai-header-status::before { content: ''; width: 6px; height: 6px; background: #00ff88; border-radius: 50%; }
.ai-close {
    margin-left: auto; background: none; border: none; color: #888899;
    font-size: 20px; cursor: pointer; padding: 2px 6px; border-radius: 6px;
    transition: color 0.2s;
}
.ai-close:hover { color: #e8e8f0; }

.ai-messages {
    flex: 1; overflow-y: auto; padding: 16px; display: flex;
    flex-direction: column; gap: 12px;
}
.ai-messages::-webkit-scrollbar { width: 4px; }
.ai-messages::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.08); border-radius: 4px; }

.ai-msg { max-width: 88%; display: flex; flex-direction: column; gap: 3px; }
.ai-msg.user { align-self: flex-end; }
.ai-msg.bot  { align-self: flex-start; }
.ai-bubble {
    padding: 10px 14px; border-radius: 14px; font-size: 13px; line-height: 1.6;
    word-break: break-word;
}
.ai-msg.user .ai-bubble {
    background: linear-gradient(135deg, rgba(0,229,255,0.15), rgba(124,58,237,0.15));
    border: 1px solid rgba(0,229,255,0.2); color: #e8e8f0;
    border-bottom-right-radius: 4px;
}
.ai-msg.bot .ai-bubble {
    background: #1a1a26; border: 1px solid rgba(255,255,255,0.06);
    color: #e8e8f0; border-bottom-left-radius: 4px;
}
.ai-bubble strong { color: #00e5ff; }
.ai-bubble ul { padding-left: 16px; margin: 4px 0; }
.ai-bubble code { background: rgba(0,229,255,0.08); padding: 1px 5px; border-radius: 4px; font-family: 'Space Mono', monospace; font-size: 11px; }

.ai-typing {
    display: none; align-self: flex-start;
    background: #1a1a26; border: 1px solid rgba(255,255,255,0.06);
    border-radius: 14px; border-bottom-left-radius: 4px;
    padding: 12px 16px; gap: 4px; flex-direction: row; align-items: center;
}
.ai-typing.show { display: flex; }
.ai-dot { width: 6px; height: 6px; background: #888899; border-radius: 50%; animation: aiDot 1.2s infinite; }
.ai-dot:nth-child(2) { animation-delay: 0.2s; }
.ai-dot:nth-child(3) { animation-delay: 0.4s; }
@keyframes aiDot { 0%,80%,100%{transform:scale(1);opacity:0.4} 40%{transform:scale(1.2);opacity:1} }

.ai-suggestions {
    display: flex; flex-wrap: wrap; gap: 6px; padding: 0 16px 10px;
}
.ai-sug-btn {
    background: rgba(0,229,255,0.06); border: 1px solid rgba(0,229,255,0.18);
    border-radius: 20px; padding: 5px 12px; color: #888899;
    font-size: 11px; cursor: pointer; transition: all 0.15s; white-space: nowrap;
}
.ai-sug-btn:hover { background: rgba(0,229,255,0.12); color: #00e5ff; border-color: rgba(0,229,255,0.35); }

.ai-input-row {
    padding: 12px 14px; border-top: 1px solid rgba(255,255,255,0.06);
    display: flex; gap: 8px; align-items: flex-end;
}
.ai-input {
    flex: 1; background: #1a1a26; border: 1px solid rgba(255,255,255,0.08);
    border-radius: 10px; padding: 9px 12px; color: #e8e8f0;
    font-family: 'Inter', sans-serif; font-size: 13px;
    resize: none; min-height: 38px; max-height: 100px; line-height: 1.5;
    transition: border-color 0.2s; overflow-y: auto;
}
.ai-input:focus { outline: none; border-color: rgba(0,229,255,0.4); }
.ai-input::placeholder { color: #888899; opacity: 0.7; }
.ai-send {
    width: 36px; height: 36px; border-radius: 9px; flex-shrink: 0;
    background: linear-gradient(135deg, #00e5ff, #7c3aed);
    border: none; cursor: pointer; font-size: 15px;
    display: flex; align-items: center; justify-content: center;
    transition: opacity 0.2s;
}
.ai-send:hover { opacity: 0.85; }
.ai-send:disabled { opacity: 0.35; cursor: not-allowed; }

@media(max-width:480px) {
    #printAI-panel { width: calc(100vw - 32px); right: -8px; }
}

@media (max-width: 1024px) {
    #missionsWidget {
        left: 16px;
        bottom: 18px;
    }
    #missions-panel {
        width: min(360px, calc(100vw - 32px));
        max-height: min(520px, calc(100vh - 120px));
    }
}

@media (max-width: 560px) {
    #missionsWidget {
        left: 14px;
        bottom: 14px;
    }
    #missions-btn {
        width: 54px;
        height: 54px;
    }
    #missions-btn::after {
        display: none;
    }
    #missions-panel {
        position: fixed;
        left: 12px;
        right: 12px;
        bottom: 78px;
        width: auto;
        max-height: calc(100vh - 108px);
    }
}
</style>

<!-- Botão flutuante Missões -->
<?php if (isLoggedIn()): ?>
<div id="missionsWidget">
    <button id="missions-btn" onclick="toggleMissions()" title="Missões Diárias">
        🎯
        <span class="mission-notif" id="missionNotif"></span>
    </button>

    <div id="missions-panel">
        <div class="missions-header">
            <h3>Missões Diárias</h3>
            <button class="ai-close" onclick="toggleMissions()">×</button>
        </div>
        <div class="missions-list" id="missionsList">
            <!-- JS dynamic -->
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Botão flutuante -->
<div id="printAI">
    <button id="printAI-btn" onclick="toggleAI()" title="Assistente de Impressão 3D">
        🤖
        <span class="ai-notif" id="aiNotif"></span>
    </button>

    <div id="printAI-panel">
        <div class="ai-header">
            <div class="ai-header-av">🤖</div>
            <div>
                <div class="ai-header-name">Print AI</div>
                <div class="ai-header-status">Online</div>
            </div>
            <button class="ai-close" onclick="toggleAI()">×</button>
        </div>

        <div class="ai-messages" id="aiMessages">
            <div class="ai-msg bot">
                <div class="ai-bubble">
                    Olá! Sou o <strong>Print AI</strong>, o teu assistente de impressão 3D. 🖨️<br><br>
                    Posso ajudar-te com dúvidas técnicas, materiais, problemas de impressão e muito mais. Como posso ajudar?
                </div>
            </div>
        </div>

        <div class="ai-typing" id="aiTyping">
            <div class="ai-dot"></div>
            <div class="ai-dot"></div>
            <div class="ai-dot"></div>
        </div>

        <div class="ai-suggestions" id="aiSuggestions">
            <button class="ai-sug-btn" onclick="sendSuggestion(this)">Qual filamento usar?</button>
            <button class="ai-sug-btn" onclick="sendSuggestion(this)">Stringing — como resolver?</button>
            <button class="ai-sug-btn" onclick="sendSuggestion(this)">PLA vs PETG</button>
            <button class="ai-sug-btn" onclick="sendSuggestion(this)">Warping nas peças</button>
        </div>

        <div class="ai-input-row">
            <textarea class="ai-input" id="aiInput" placeholder="Pergunta sobre impressão 3D…"
                rows="1" onkeydown="aiKeyDown(event)" oninput="aiAutoResize(this)"></textarea>
            <button class="ai-send" id="aiSendBtn" onclick="sendAIMessage()">➤</button>
        </div>
        <div style="padding: 0 14px 10px; font-size: 9px; color: var(--muted); text-align: center; opacity: 0.7;">
            A IA pode cometer erros. Confirma informações críticas. <br>
            Uso limitado por sessão.
        </div>
    </div>
</div>

<script>
var aiHistory  = [];
var aiOpen     = false;
var aiLoading  = false;

function toggleAI() {
    aiOpen = !aiOpen;
    var panel = document.getElementById('printAI-panel');
    var notif = document.getElementById('aiNotif');
    panel.classList.toggle('open', aiOpen);
    if (aiOpen) {
        notif.style.display = 'none';
        setTimeout(function(){ document.getElementById('aiInput').focus(); }, 100);
    }
}

function aiKeyDown(e) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendAIMessage(); }
}

function aiAutoResize(el) {
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 100) + 'px';
}

function sendSuggestion(btn) {
    document.getElementById('aiInput').value = btn.textContent;
    document.getElementById('aiSuggestions').style.display = 'none';
    sendAIMessage();
}

function appendMsg(role, text) {
    var msgs = document.getElementById('aiMessages');
    var div  = document.createElement('div');
    div.className = 'ai-msg ' + role;
    var bubble = document.createElement('div');
    bubble.className = 'ai-bubble';
    // Markdown básico
    var html = text
        .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
        .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
        .replace(/`([^`]+)`/g, '<code>$1</code>')
        .replace(/^- (.+)$/gm, '<li>$1</li>')
        .replace(/(<li>.*<\/li>)/s, '<ul>$1</ul>')
        .replace(/\n/g, '<br>');
    bubble.innerHTML = html;
    div.appendChild(bubble);
    msgs.appendChild(div);
    msgs.scrollTop = msgs.scrollHeight;
}

var aiConversationId = null;

async function sendAIMessage() {
    if (aiLoading) return;
    var input = document.getElementById('aiInput');
    var msg = input.value.trim();
    if (!msg) return;

    appendMsg('user', msg);
    input.value = '';
    input.style.height = 'auto';
    aiLoading = true;

    var typing = document.createElement('div');
    typing.className = 'ai-typing show';
    typing.innerHTML = '<div class="ai-dot"></div><div class="ai-dot"></div><div class="ai-dot"></div>';
    document.getElementById('aiMessages').appendChild(typing);
    document.getElementById('aiMessages').scrollTop = document.getElementById('aiMessages').scrollHeight;

    try {
        const currentMode = localStorage.getItem('mode') || 'beginner';
        const res = await fetch('api/ai.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                message: msg,
                mode: 'manual',
                ai_mode: currentMode,
                history: aiHistory
            })
        });
        const data = await res.json();
        typing.remove();
        if (data.success) {
            appendMsg('bot', data.reply);
            aiHistory.push({role:'user', content: msg});
            aiHistory.push({role:'assistant', content: data.reply});
            if (aiHistory.length > 10) aiHistory.shift();
        } else {
            appendMsg('bot', '⚠️ ' + (data.error || 'Erro na resposta.'));
        }
    } catch(e) {
        if (document.querySelector('.ai-typing')) document.querySelector('.ai-typing').remove();
        appendMsg('bot', '⚠️ Erro de rede ou na ligação ao servidor.');
    } finally {
        aiLoading = false;
    }
}

// ── Daily Missions Logic ──
var missionsOpen = false;

function toggleMissions() {
    missionsOpen = !missionsOpen;
    var panel = document.getElementById('missions-panel');
    panel.classList.toggle('open', missionsOpen);
    if (missionsOpen) {
        loadMissions();
        document.getElementById('missionNotif').style.display = 'none';
    }
}

async function loadMissions() {
    try {
        const res = await fetch('api/missions.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ action: 'list' })
        });
        const data = await res.json();
        if (data.success) {
            renderMissions(data.missions, data.definitions);
            checkPendingClaims(data.missions);
        }
    } catch(e) { console.error(e); }
}

function renderMissions(userMissions, definitions) {
    const list = document.getElementById('missionsList');
    let html = '';

    const dailyKeys = Object.keys(definitions).filter(k => !definitions[k].type || definitions[k].type === 'daily');
    const weeklyKeys = Object.keys(definitions).filter(k => definitions[k].type === 'weekly');

    html += '<div style="font-family:Syne; font-size:11px; color:var(--accent); margin-bottom:8px; opacity:0.8; text-transform:uppercase; letter-spacing:1px;">Diárias</div>';

    dailyKeys.forEach(key => {
        const def = definitions[key];
        const progress = userMissions.list[key] || { current: 0, completed: false, claimed: false };
        html += createMissionCardHtml(key, def, progress);
    });

    if (weeklyKeys.length > 0) {
        html += '<div style="font-family:Syne; font-size:11px; color:var(--accent3); margin:16px 0 8px; opacity:0.8; text-transform:uppercase; letter-spacing:1px;">Semanais</div>';
        weeklyKeys.forEach(key => {
            const def = definitions[key];
            const progress = userMissions.weekly_list[key] || { current: 0, completed: false, claimed: false };
            html += createMissionCardHtml(key, def, progress);
        });
    }

    list.innerHTML = html;
}

function createMissionCardHtml(key, def, progress) {
    const percent = Math.min(100, (progress.current / def.goal) * 100);
    return `
        <div class="mission-card ${progress.completed ? 'completed' : ''}">
            <div class="mission-icon">${def.icon}</div>
            <div class="mission-info">
                <div class="mission-title">${def.title}</div>
                <div class="mission-desc">${def.desc}</div>
                <div class="mission-reward">+${def.xp} XP <span>+${def.gp} GP</span></div>
                <div class="mission-progress-bar">
                    <div class="mission-progress-fill" style="width: ${percent}%; background: ${def.type === 'weekly' ? 'var(--accent3)' : 'var(--accent2)'}"></div>
                </div>
            </div>
            ${progress.completed && !progress.claimed ?
                `<button class="claim-btn" onclick="claimReward('${key}', this)">Reclamar</button>` :
                (progress.claimed ? '✅' : `<div style="font-size:10px; color:var(--muted)">${progress.current}/${def.goal}</div>`)
            }
        </div>
    `;
}

function checkPendingClaims(userMissions) {
    let pending = false;
    for (const key in userMissions.list) {
        if (userMissions.list[key].completed && !userMissions.list[key].claimed) {
            pending = true;
            break;
        }
    }
    if (!pending && userMissions.weekly_list) {
        for (const key in userMissions.weekly_list) {
            if (userMissions.weekly_list[key].completed && !userMissions.weekly_list[key].claimed) {
                pending = true;
                break;
            }
        }
    }
    document.getElementById('missionNotif').style.display = pending ? 'block' : 'none';
}

async function claimReward(key, btn) {
    if (btn) {
        if (btn.disabled) return;
        btn.disabled = true;
        btn.style.opacity = '0.5';
        btn.innerHTML = '...';
    }
    try {
        const res = await fetch('api/missions.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ action: 'claim', mission_key: key })
        });
        const data = await res.json();
        if (data.success) {
            // Sucesso visual imediato
            if (btn) {
                btn.parentElement.innerHTML = '✅';
            }

            // Efeito de Confetti
            confetti({
                particleCount: 150,
                spread: 70,
                origin: { y: 0.6 },
                colors: ['#00ff88', '#ff6b35', '#7c3aed', '#00e5ff']
            });

            // Som de sucesso
            new Audio('assets/audio/success.mp3').play();

            // Atualiza os dados por trás
            loadMissions();

            // Notificação flutuante
            const notification = document.createElement('div');
            notification.style.cssText = 'position:fixed; top:20px; left:50%; transform:translateX(-50%); background:#00ff88; color:#000; padding:12px 24px; border-radius:30px; font-weight:700; z-index:100000; box-shadow:0 10px 30px rgba(0,255,136,0.3);';
            notification.innerHTML = `✨ +${data.xp} XP e +${data.gp} GP Reclamados!`;
            document.body.appendChild(notification);
            setTimeout(() => {
                notification.style.opacity = '0';
                notification.style.transition = 'opacity 0.5s';
                setTimeout(() => notification.remove(), 500);
            }, 3000);
        } else {
            if (btn) {
                btn.disabled = false;
                btn.style.opacity = '1';
                btn.innerHTML = 'Reclamar';
            }
            alert(data.error || 'Erro ao reclamar.');
        }
    } catch(e) {
        console.error(e);
        if (btn) {
            btn.disabled = false;
            btn.style.opacity = '1';
            btn.innerHTML = 'Reclamar';
        }
    }
}

// Auto load missions check every 30s
if (document.getElementById('missionsWidget')) {
    setInterval(loadMissions, 30000);
    setTimeout(loadMissions, 1000);
}
</script>
<style>
  :root {
    --bg: #0a0a0f;
    --surface: #111118;
    --surface2: #1a1a26;
    --surface3: #222235;
    --accent: #00e5ff;
    --accent2: #ff6b35;
    --accent3: #7c3aed;
    --accent4: #00ff88;
    --text: #e8e8f0;
    --muted: #888899;
    --border: rgba(0,229,255,0.15);
    --glow: 0 0 40px rgba(0,229,255,0.15);
    --glow2: 0 0 40px rgba(255,107,53,0.15);
  }

  * { margin: 0; padding: 0; box-sizing: border-box; }
  
  html { scroll-behavior: smooth; }
  
  body {
    background: var(--bg);
    color: var(--text);
    font-family: 'Inter', sans-serif;
    overflow-x: hidden;
    line-height: 1.6;
  }

  body::before {
    content: '';
    position: fixed;
    inset: 0;
    background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.04'/%3E%3C/svg%3E");
    pointer-events: none;
    z-index: 9999;
    opacity: 0.4;
  }

  /* User Bar */
  .user-bar {
    position: fixed;
    top: 0;
    right: 0;
    left: 280px;
    height: 50px;
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding: 0 24px;
    z-index: 90;
    gap: 16px;
  }

  .user-info {
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--muted);
    font-size: 13px;
  }

  .user-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--accent), var(--accent3));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 700;
    color: #fff;
  }

  .user-name {
    color: var(--text);
    font-weight: 600;
  }

  .user-role {
    font-family: 'Space Mono', monospace;
    font-size: 9px;
    padding: 2px 8px;
    border-radius: 4px;
    text-transform: uppercase;
  }
  .user-role.owner { background: linear-gradient(135deg, #7c3aed, var(--accent2)); color: #fff; font-weight: 700; border: 1px solid rgba(255,255,255,0.2); }
  .user-role.master { background: rgba(255,204,0,0.2); color: #ffcc00; border: 1px solid rgba(255,204,0,0.3); }
  .user-role.admin { background: rgba(255,107,53,0.2); color: var(--accent2); }
  .user-role.moderator { background: rgba(124,58,237,0.2); color: #a78bfa; }
  .user-role.user { background: rgba(0,229,255,0.1); color: var(--accent); }

  .btn-auth {
    padding: 8px 16px;
    border-radius: 8px;
    font-family: 'Space Mono', monospace;
    font-size: 11px;
    text-decoration: none;
    transition: all 0.2s;
  }

  .btn-login {
    background: transparent;
    border: 1px solid var(--border);
    color: var(--muted);
  }

  .btn-login:hover {
    border-color: var(--accent);
    color: var(--accent);
  }

  .btn-register {
    background: var(--accent);
    border: 1px solid var(--accent);
    color: #000;
  }

  .btn-register:hover {
    box-shadow: 0 0 20px rgba(0,229,255,0.3);
  }

  .btn-logout {
    background: transparent;
    border: 1px solid var(--border);
    color: var(--muted);
    padding: 6px 12px;
    font-size: 10px;
  }

        /* novo botão de perfil */
        .btn-profile {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--muted);
            padding: 6px 12px;
            font-size: 10px;
            margin-right: 8px;
        }

        .btn-profile:hover {
            border-color: var(--accent);
            color: var(--accent);
        }

  /* SIDEBAR */
  nav {
    position: fixed;
    left: 0; top: 0; bottom: 0;
    width: 280px;
    background: var(--surface);
    border-right: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    padding: 32px 0;
    z-index: 100;
    overflow-y: auto;
    transition: transform 0.3s ease;
  }

  .nav-logo {
    padding: 0 24px 24px;
    border-bottom: 1px solid var(--border);
    margin-bottom: 16px;
  }

  .nav-logo .label {
    font-family: 'Space Mono', monospace;
    font-size: 10px;
    color: var(--accent);
    letter-spacing: 3px;
    text-transform: uppercase;
    margin-bottom: 8px;
  }

  .nav-logo h1 {
    font-family: 'Syne', sans-serif;
    font-size: 22px;
    font-weight: 800;
    line-height: 1.2;
    color: #fff;
  }

  .nav-logo h1 span { color: var(--accent); }

  .level-toggle {
    margin: 0 16px 20px;
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 4px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 4px;
  }

  .toggle-btn {
    padding: 10px 0;
    border: none;
    border-radius: 7px;
    font-family: 'Space Mono', monospace;
    font-size: 10px;
    letter-spacing: 1px;
    text-transform: uppercase;
    cursor: pointer;
    transition: all 0.25s;
    background: transparent;
    color: var(--muted);
  }

  .toggle-btn.active {
    background: var(--accent);
    color: #000;
    font-weight: 700;
    box-shadow: 0 0 16px rgba(0,229,255,0.35);
  }

  .toggle-btn.active.pro {
    background: var(--accent2);
    box-shadow: 0 0 16px rgba(255,107,53,0.35);
  }

  nav a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 24px;
    color: var(--muted);
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.2s;
    border-left: 3px solid transparent;
    position: relative;
  }

  nav a:hover, nav a.active {
    color: var(--accent);
    border-left-color: var(--accent);
    background: rgba(0,229,255,0.05);
  }

  nav a .icon { width: 20px; font-size: 16px; text-align: center; }

  .nav-section {
    font-family: 'Space Mono', monospace;
    font-size: 9px;
    letter-spacing: 3px;
    color: var(--muted);
    text-transform: uppercase;
    padding: 20px 24px 8px;
    opacity: 0.6;
  }

  .nav-badge {
    margin-left: auto;
    font-family: 'Space Mono', monospace;
    font-size: 8px;
    padding: 2px 6px;
    border-radius: 4px;
    letter-spacing: 1px;
  }

  .nav-badge.beg { background: rgba(0,229,255,0.1); color: var(--accent); }
  .nav-badge.pro { background: rgba(255,107,53,0.1); color: var(--accent2); }

  /* MAIN */
  main { margin-left: 280px; min-height: 100vh; padding-top: 50px; }

  /* HERO */
  .hero {
    position: relative;
    padding: 60px 60px 60px;
    overflow: hidden;
    border-bottom: 1px solid var(--border);
    background: linear-gradient(135deg, #0a0a0f 0%, #0d0d1a 50%, #0a0a0f 100%);
    min-height: 45vh;
    display: flex;
    flex-direction: column;
    justify-content: center;
  }

  .hero-grid {
    position: absolute; inset: 0;
    background-image:
      linear-gradient(rgba(0,229,255,0.04) 1px, transparent 1px),
      linear-gradient(90deg, rgba(0,229,255,0.04) 1px, transparent 1px);
    background-size: 40px 40px;
    animation: gridMove 20s linear infinite;
  }

  @keyframes gridMove {
    0% { transform: translate(0, 0); }
    100% { transform: translate(40px, 40px); }
  }

  .hero-glow  { 
    position: absolute; top: -100px; right: -100px; width: 600px; height: 600px; 
    background: radial-gradient(circle, rgba(124,58,237,0.25) 0%, transparent 70%); 
    pointer-events: none;
    animation: pulseGlow 4s ease-in-out infinite;
  }
  
  .hero-glow2 { 
    position: absolute; bottom: -80px; left: 30%; width: 500px; height: 500px; 
    background: radial-gradient(circle, rgba(0,229,255,0.15) 0%, transparent 70%); 
    pointer-events: none;
    animation: pulseGlow 4s ease-in-out infinite 2s;
  }

  @keyframes pulseGlow {
    0%, 100% { opacity: 0.5; transform: scale(1); }
    50% { opacity: 0.8; transform: scale(1.1); }
  }

  .hero-tag {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(0,229,255,0.1);
    border: 1px solid rgba(0,229,255,0.3);
    border-radius: 100px;
    padding: 8px 18px;
    font-family: 'Space Mono', monospace;
    font-size: 11px;
    color: var(--accent);
    letter-spacing: 1px;
    margin-bottom: 24px;
    position: relative;
    width: fit-content;
  }

  .hero-tag::before {
    content: '';
    width: 6px; height: 6px;
    background: var(--accent);
    border-radius: 50%;
    animation: pulse 2s infinite;
  }

  @keyframes pulse {
    0%, 100% { opacity: 1; transform: scale(1); box-shadow: 0 0 10px var(--accent); }
    50% { opacity: 0.5; transform: scale(0.8); }
  }

  .hero h2 {
    font-family: 'Syne', sans-serif;
    font-size: clamp(40px, 5vw, 68px);
    font-weight: 800;
    line-height: 1.05;
    position: relative;
    margin-bottom: 24px;
    background: linear-gradient(135deg, #fff 0%, var(--accent) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
  }

  .hero h2 em { 
    font-style: normal; 
    background: linear-gradient(135deg, var(--accent) 0%, var(--accent3) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    display: block;
  }

  .hero p { 
    font-size: 17px; 
    color: var(--muted); 
    max-width: 580px; 
    line-height: 1.8; 
    position: relative; 
  }

  .hero-badges { display: flex; gap: 12px; margin-top: 40px; flex-wrap: wrap; position: relative; }

  .badge {
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 10px 18px;
    font-size: 12px;
    color: var(--muted);
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s;
  }

  .badge:hover {
    border-color: var(--accent);
    transform: translateY(-2px);
    box-shadow: 0 4px 20px rgba(0,229,255,0.1);
  }

  .badge span { color: var(--text); font-weight: 600; }

  .quick-links {
    display: flex;
    gap: 10px;
    margin-top: 24px;
    flex-wrap: wrap;
  }

  .quick-link {
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 10px 18px;
    color: var(--muted);
    text-decoration: none;
    font-size: 12px;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .quick-link:hover {
    border-color: var(--accent);
    color: var(--accent);
    transform: translateY(-2px);
  }

  /* LEVEL BAR */
  .level-bar {
    position: sticky;
    top: 50px;
    z-index: 50;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    padding: 12px 60px;
    border-bottom: 1px solid var(--border);
    backdrop-filter: blur(12px);
    background: rgba(10,10,15,0.9);
    transition: all 0.3s;
  }

  .level-bar .level-label {
    font-family: 'Space Mono', monospace;
    font-size: 11px;
    letter-spacing: 2px;
    text-transform: uppercase;
  }

  .level-bar.beginner .level-label { color: var(--accent); }
  .level-bar.advanced .level-label { color: var(--accent2); }

  .level-bar .level-desc {
    font-size: 12px;
    color: var(--muted);
  }

  .level-dot {
    width: 8px; height: 8px;
    border-radius: 50%;
    animation: pulse 2s infinite;
  }

  .beginner .level-dot { background: var(--accent); }
  .advanced .level-dot { background: var(--accent2); }

  /* SECTIONS */
  .section {
    padding: 70px 60px;
    border-bottom: 1px solid var(--border);
  }

  .section-header {
    display: flex;
    align-items: flex-start;
    gap: 20px;
    margin-bottom: 40px;
  }

  .section-number {
    font-family: 'Space Mono', monospace;
    font-size: 52px;
    font-weight: 700;
    color: var(--border);
    line-height: 1;
    flex-shrink: 0;
    margin-top: -6px;
    transition: color 0.3s;
  }

  .section:hover .section-number {
    color: var(--accent);
  }

  .section-title h2 {
    font-family: 'Syne', sans-serif;
    font-size: 34px;
    font-weight: 800;
    color: #fff;
    margin-bottom: 10px;
  }

  .section-title p { color: var(--muted); font-size: 15px; }

  .for-beginner, .for-advanced {
    transition: opacity 0.3s, transform 0.3s;
  }

  body.mode-beginner .for-advanced { display: none; }
  body.mode-advanced .for-beginner { display: none; }

  /* CARDS */
  .cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }

  .card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 28px;
    transition: all 0.3s;
    position: relative;
    overflow: hidden;
  }

  .card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--accent), var(--accent3));
    transform: scaleX(0);
    transform-origin: left;
    transition: transform 0.3s;
  }

  .card:hover { 
    border-color: rgba(0,229,255,0.3); 
    transform: translateY(-4px); 
    box-shadow: var(--glow);
    background: var(--surface2);
  }
  
  .card:hover::before { transform: scaleX(1); }
  .card-icon { font-size: 32px; margin-bottom: 16px; display: block; transition: transform 0.3s; }
  .card:hover .card-icon { transform: scale(1.1); }
  .card h3 { font-family: 'Syne', sans-serif; font-size: 18px; font-weight: 700; color: #fff; margin-bottom: 12px; }
  .card p { font-size: 14px; color: var(--muted); line-height: 1.7; }

  /* FILAMENT TABLE */
  .filament-grid { display: grid; gap: 14px; }

  .filament-row {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 22px 28px;
    display: grid;
    grid-template-columns: 130px 90px 1fr auto;
    align-items: center;
    gap: 24px;
    transition: all 0.3s;
  }

  .filament-row:hover { 
    border-color: rgba(0,229,255,0.3); 
    background: var(--surface2);
    transform: translateX(8px);
  }

  .filament-name { font-family: 'Syne', sans-serif; font-weight: 700; font-size: 17px; color: #fff; }
  .filament-temp { font-family: 'Space Mono', monospace; font-size: 12px; color: var(--accent2); }
  .filament-desc { font-size: 14px; color: var(--muted); line-height: 1.6; }
  .filament-tags { display: flex; flex-direction: column; gap: 6px; }

  .tag {
    font-size: 10px;
    font-family: 'Space Mono', monospace;
    padding: 4px 10px;
    border-radius: 4px;
    white-space: nowrap;
  }

  .tag-beginner { background: rgba(0,229,255,0.1); color: var(--accent); border: 1px solid rgba(0,229,255,0.2); }
  .tag-advanced { background: rgba(255,107,53,0.1); color: var(--accent2); border: 1px solid rgba(255,107,53,0.2); }
  .tag-pro      { background: rgba(124,58,237,0.1); color: #a78bfa; border: 1px solid rgba(124,58,237,0.2); }

  /* PRINTER COMPARISON */
  .printer-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }

  .printer-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 18px;
    overflow: hidden;
    transition: all 0.3s;
  }

  .printer-card:hover { 
    box-shadow: var(--glow); 
    border-color: rgba(0,229,255,0.3);
    transform: translateY(-4px);
  }

  .printer-header { padding: 28px; background: var(--surface2); border-bottom: 1px solid var(--border); }
  .printer-header .level { font-family: 'Space Mono', monospace; font-size: 10px; letter-spacing: 2px; color: var(--accent); text-transform: uppercase; margin-bottom: 10px; }
  .printer-header h3 { font-family: 'Syne', sans-serif; font-size: 24px; font-weight: 800; color: #fff; margin-bottom: 8px; }
  .printer-header p { font-size: 14px; color: var(--muted); }

  .printer-body { padding: 28px; }

  .printer-feature { display: flex; align-items: flex-start; gap: 14px; padding: 12px 0; border-bottom: 1px solid rgba(255,255,255,0.05); }
  .printer-feature:last-child { border-bottom: none; }
  .feat-icon { font-size: 18px; flex-shrink: 0; margin-top: 2px; }
  .feat-text strong { display: block; font-size: 14px; font-weight: 600; color: var(--text); margin-bottom: 4px; }
  .feat-text span { font-size: 13px; color: var(--muted); }

  .printer-footer {
    padding: 18px 28px;
    background: rgba(0,229,255,0.03);
    border-top: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
  }

  .price-range .label { color: var(--muted); font-size: 10px; display: block; margin-bottom: 2px; font-family: 'Space Mono', monospace; }
  .price-range .value { color: var(--accent); font-family: 'Space Mono', monospace; font-size: 14px; }

  /* PROCESS STEPS */
  .process { display: flex; flex-direction: column; gap: 0; position: relative; }

  .process::before {
    content: '';
    position: absolute;
    left: 28px; top: 48px; bottom: 48px;
    width: 2px;
    background: linear-gradient(to bottom, var(--accent), var(--accent3), var(--accent2));
    opacity: 0.4;
  }

  .step { display: flex; gap: 28px; padding: 24px 0; }

  .step-num {
    width: 56px; height: 56px;
    border-radius: 50%;
    background: var(--surface2);
    border: 2px solid var(--accent);
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: 'Space Mono', monospace;
    font-size: 16px;
    font-weight: 700;
    color: var(--accent);
    flex-shrink: 0;
    position: relative;
    z-index: 1;
    box-shadow: 0 0 24px rgba(0,229,255,0.25);
    transition: all 0.3s;
  }
  
  .step:hover .step-num {
    transform: scale(1.1);
    box-shadow: 0 0 32px rgba(0,229,255,0.4);
  }

  .step-content { flex: 1; padding-top: 12px; }
  .step-content h3 { font-family: 'Syne', sans-serif; font-size: 20px; font-weight: 700; color: #fff; margin-bottom: 10px; }
  .step-content p { font-size: 14px; color: var(--muted); line-height: 1.8; }

  /* TIP BOXES */
  .tip-box {
    background: var(--surface);
    border: 1px solid rgba(255,107,53,0.3);
    border-left: 4px solid var(--accent2);
    border-radius: 14px;
    padding: 24px 28px;
    margin: 24px 0;
    transition: all 0.3s;
  }

  .tip-box:hover {
    border-color: var(--accent2);
    box-shadow: var(--glow2);
  }

  .tip-box .tip-header { display: flex; align-items: center; gap: 10px; font-family: 'Syne', sans-serif; font-size: 15px; font-weight: 700; color: var(--accent2); margin-bottom: 10px; }
  .tip-box p { font-size: 14px; color: var(--muted); line-height: 1.7; }

  .warning-box {
    background: rgba(124,58,237,0.05);
    border: 1px solid rgba(124,58,237,0.3);
    border-left: 4px solid var(--accent3);
    border-radius: 14px;
    padding: 24px 28px;
    margin: 24px 0;
    transition: all 0.3s;
  }

  .warning-box:hover {
    border-color: var(--accent3);
    box-shadow: 0 0 30px rgba(124,58,237,0.2);
  }

  .warning-box .tip-header { color: #a78bfa; }

  /* GLOSSARY */
  .glossary { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }

  .glossary-item { 
    background: var(--surface); 
    border: 1px solid var(--border); 
    border-radius: 12px; 
    padding: 20px 24px;
    transition: all 0.3s;
  }
  
  .glossary-item:hover {
    border-color: rgba(0,229,255,0.3);
    transform: translateY(-2px);
  }
  
  .glossary-item dt { font-family: 'Space Mono', monospace; font-size: 13px; color: var(--accent); margin-bottom: 6px; text-transform: uppercase; letter-spacing: 1px; }
  .glossary-item dd { font-size: 14px; color: var(--muted); line-height: 1.6; }

  /* TABLE */
  .comparison-table { width: 100%; border-collapse: collapse; font-size: 14px; }
  .comparison-table th { font-family: 'Space Mono', monospace; font-size: 11px; letter-spacing: 2px; text-transform: uppercase; color: var(--muted); text-align: left; padding: 16px 20px; border-bottom: 1px solid var(--border); }
  .comparison-table td { padding: 16px 20px; border-bottom: 1px solid rgba(255,255,255,0.04); color: var(--text); }
  .comparison-table tr:hover td { background: rgba(0,229,255,0.03); }

  /* MISC */
  h4 { font-family: 'Syne', sans-serif; font-size: 18px; font-weight: 700; color: #fff; margin: 28px 0 16px; }
  p { line-height: 1.8; font-size: 15px; color: var(--muted); margin-bottom: 14px; }
  p strong { color: var(--text); }

  .inline-tag { 
    display: inline-block; 
    background: rgba(0,229,255,0.1); 
    color: var(--accent); 
    font-family: 'Space Mono', monospace; 
    font-size: 11px; 
    padding: 3px 10px; 
    border-radius: 4px; 
    margin: 0 3px; 
    border: 1px solid rgba(0,229,255,0.2);
  }

  /* COMMENTS SECTION */
  .comments-section {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 18px;
    padding: 32px;
    margin-top: 24px;
  }

  .comments-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 16px;
  }

  .comments-header h3 {
    font-family: 'Syne', sans-serif;
    font-size: 22px;
    color: #fff;
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .comments-filter {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
  }

  .filter-btn {
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 8px 16px;
    font-family: 'Space Mono', monospace;
    font-size: 11px;
    color: var(--muted);
    cursor: pointer;
    transition: all 0.2s;
  }

  .filter-btn:hover, .filter-btn.active {
    background: var(--accent);
    color: #000;
    border-color: var(--accent);
  }

  .comment-form {
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 24px;
    margin-bottom: 24px;
  }

  .comment-form.disabled {
    opacity: 0.6;
    pointer-events: none;
  }

  .login-prompt {
    text-align: center;
    padding: 40px;
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 14px;
    margin-bottom: 24px;
  }

  .login-prompt p {
    margin-bottom: 16px;
    font-size: 15px;
  }

  .login-prompt .btn-group {
    display: flex;
    gap: 12px;
    justify-content: center;
    flex-wrap: wrap;
  }

  .form-group {
    margin-bottom: 16px;
  }

  .form-group label {
    display: block;
    font-family: 'Space Mono', monospace;
    font-size: 11px;
    color: var(--muted);
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 1px;
  }

  .form-group input,
  .form-group textarea,
  .form-group select {
    width: 100%;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 14px 18px;
    color: var(--text);
    font-family: 'Inter', sans-serif;
    font-size: 14px;
    transition: all 0.2s;
  }

  .form-group input:focus,
  .form-group textarea:focus,
  .form-group select:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 20px rgba(0,229,255,0.1);
  }

  .form-group textarea {
    min-height: 100px;
    resize: vertical;
  }

  .form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
  }

  .submit-btn {
    background: linear-gradient(135deg, var(--accent), var(--accent3));
    border: none;
    border-radius: 10px;
    padding: 14px 28px;
    color: #000;
    font-family: 'Space Mono', monospace;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 1px;
    cursor: pointer;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
  }

  .submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 30px rgba(0,229,255,0.3);
  }

  .submit-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }

  .comments-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .comment {
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 20px 24px;
    transition: all 0.3s;
    animation: slideIn 0.4s ease;
  }

  @keyframes slideIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
  }

  .comment:hover {
    border-color: rgba(0,229,255,0.2);
  }

  .comment-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
  }

  .comment-author {
    display: flex;
    align-items: center;
    gap: 12px;
  }

  .avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--accent), var(--accent3));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    font-weight: 700;
    color: #fff;
  }

  .author-info h4 {
    font-family: 'Syne', sans-serif;
    font-size: 15px;
    color: #fff;
    margin: 0;
  }

  .author-info .meta {
    font-family: 'Space Mono', monospace;
    font-size: 10px;
    color: var(--muted);
    display: flex;
    gap: 12px;
    align-items: center;
  }

  .comment-category {
    background: rgba(0,229,255,0.1);
    color: var(--accent);
    font-family: 'Space Mono', monospace;
    font-size: 9px;
    padding: 4px 10px;
    border-radius: 4px;
    text-transform: uppercase;
    letter-spacing: 1px;
  }

  .comment-category.problema { background: rgba(255,107,53,0.1); color: var(--accent2); }
  .comment-category.dica { background: rgba(0,255,136,0.1); color: var(--accent4); }
  .comment-category.duvida { background: rgba(124,58,237,0.1); color: #a78bfa; }

  .comment-content {
    font-size: 14px;
    color: var(--text);
    line-height: 1.7;
    margin-bottom: 16px;
  }

  .comment-title {
    font-family: 'Syne', sans-serif;
    font-size: 16px;
    color: #fff;
    margin-bottom: 8px;
  }

  .comment-status {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-family: 'Space Mono', monospace;
    font-size: 9px;
    padding: 2px 8px;
    border-radius: 4px;
    margin-left: 8px;
  }

  .comment-status.open { background: rgba(0,229,255,0.1); color: var(--accent); }
  .comment-status.solved { background: rgba(0,255,136,0.1); color: var(--accent4); }
  .comment-status.closed { background: rgba(136,136,153,0.1); color: var(--muted); }

  .comment-actions {
    display: flex;
    gap: 16px;
    align-items: center;
  }

  .action-btn {
    background: transparent;
    border: none;
    color: var(--muted);
    font-family: 'Space Mono', monospace;
    font-size: 11px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s;
    padding: 6px 12px;
    border-radius: 6px;
  }

  .action-btn:hover {
    color: var(--accent);
    background: rgba(0,229,255,0.05);
  }

  .action-btn.liked {
    color: var(--accent2);
  }

  .replies {
    margin-top: 16px;
    margin-left: 32px;
    padding-left: 24px;
    border-left: 2px solid var(--border);
    display: flex;
    flex-direction: column;
    gap: 12px;
  }

  .reply {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 16px 20px;
  }

  .reply.solution {
    border-color: var(--accent4);
    background: rgba(0,255,136,0.05);
  }

  .solution-badge {
    background: var(--accent4);
    color: #000;
    font-family: 'Space Mono', monospace;
    font-size: 8px;
    padding: 2px 8px;
    border-radius: 4px;
    margin-left: 8px;
  }

  .reply-form {
    margin-top: 12px;
    margin-left: 32px;
    display: none;
  }

  .reply-form.active {
    display: block;
  }

  .reply-form textarea {
    width: 100%;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 12px 16px;
    color: var(--text);
    font-family: 'Inter', sans-serif;
    font-size: 13px;
    margin-bottom: 10px;
    min-height: 80px;
    resize: vertical;
  }

  .reply-form textarea:focus {
    outline: none;
    border-color: var(--accent);
  }

  .reply-buttons {
    display: flex;
    gap: 10px;
  }

  .btn-small {
    padding: 8px 16px;
    font-size: 11px;
  }

  .btn-cancel {
    background: var(--surface3);
    color: var(--muted);
  }

  .btn-cancel:hover {
    background: var(--surface2);
    color: var(--text);
  }

  .stats-bar {
    display: flex;
    gap: 24px;
    margin-bottom: 24px;
    padding: 16px 20px;
    background: var(--surface2);
    border-radius: 10px;
    flex-wrap: wrap;
  }

  .stat {
    display: flex;
    align-items: center;
    gap: 8px;
    font-family: 'Space Mono', monospace;
    font-size: 12px;
    color: var(--muted);
  }

  .stat span {
    color: var(--accent);
    font-weight: 700;
    font-size: 16px;
  }

  .loading {
    text-align: center;
    padding: 40px;
    color: var(--muted);
  }

  .empty-state {
    text-align: center;
    padding: 60px 40px;
    color: var(--muted);
  }

  .empty-state-icon {
    font-size: 48px;
    margin-bottom: 16px;
  }

  /* FOOTER */
  footer { 
    padding: 50px 60px; 
    text-align: center; 
    color: var(--muted); 
    font-size: 13px; 
    font-family: 'Space Mono', monospace; 
    border-top: 1px solid var(--border);
    background: var(--surface);
  }
  
  footer strong { color: var(--accent); }
  footer p { margin-bottom: 8px; }

  /* SCROLLBAR */
  ::-webkit-scrollbar { width: 8px; }
  ::-webkit-scrollbar-track { background: var(--bg); }
  ::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }
  ::-webkit-scrollbar-thumb:hover { background: rgba(0,229,255,0.3); }

  /* MOBILE MENU TOGGLE */
  .menu-toggle {
    display: none;
    position: fixed;
    top: 12px;
    left: 12px;
    z-index: 2500;
    width: 48px;
    height: 48px;
    background: rgba(17, 17, 24, 0.98);
    border: 1px solid var(--accent);
    border-radius: 12px;
    padding: 0;
    cursor: pointer;
    color: var(--accent);
    font-size: 24px;
    line-height: 1;
    align-items: center;
    justify-content: center;
    box-shadow: 0 8px 32px rgba(0,0,0,0.5);
    backdrop-filter: blur(12px);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  }
  .menu-toggle:hover {
    transform: scale(1.05);
    background: var(--accent);
    color: #000;
  }

  .sidebar-backdrop {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.52);
    z-index: 1100;
    backdrop-filter: blur(2px);
  }
  .sidebar-backdrop.open { display: block; }

  /* RESPONSIVE */
  @media (max-width: 1024px) {
    nav { 
      transform: translateX(-100%);
      width: 280px;
      z-index: 3000;
      padding-top: 80px;
    }
    
    nav.open {
      transform: translateX(0);
    }
    
    .menu-toggle {
      display: flex;
    }
    
    .user-bar {
      left: 0;
      padding: 0 16px 0 72px;
      z-index: 1000;
    }
    
    main { margin-left: 0; }
    .section { padding: 50px 24px; }
    .hero { padding: 80px 24px 50px; min-height: auto; }
    .printer-grid { grid-template-columns: 1fr; }
    .glossary { grid-template-columns: 1fr; }
    .filament-row { grid-template-columns: 1fr; gap: 12px; }
    .form-row { grid-template-columns: 1fr; }
    .comments-header { flex-direction: column; align-items: flex-start; }
    .level-bar { padding: 12px 24px; top: 50px; }
    .stats-bar { justify-content: center; }
  }

  @media (max-width: 560px) {
    .user-bar {
      gap: 8px;
      padding-left: 66px;
    }
    .user-info {
      min-width: 0;
      gap: 8px;
    }
    .user-name {
      max-width: 34vw;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    .user-role {
      display: none;
    }
    .btn-auth {
      padding: 7px 10px;
      font-size: 10px;
    }
    .hero {
      padding-top: 92px;
    }
  }

  /* BACK TO TOP */
  .back-to-top {
    position: fixed;
    bottom: 30px;
    right: 30px;
    width: 50px;
    height: 50px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s;
    z-index: 100;
    font-size: 20px;
  }

  .back-to-top.visible {
    opacity: 1;
    visibility: visible;
  }

  .back-to-top:hover {
    background: var(--accent);
    border-color: var(--accent);
    transform: translateY(-4px);
  }

  /* PROGRESS BAR */
  .progress-bar {
    position: fixed;
    top: 50px;
    left: 280px;
    right: 0;
    height: 4px;
    background: var(--surface);
    z-index: 1000;
  }

  .progress-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--accent), var(--accent3));
    width: 0%;
    transition: width 0.1s;
    box-shadow: 0 0 6px rgba(0,229,255,0.5);
  }

  /* ADS PLACEHOLDERS */
  .ad-slot {
    background: rgba(255,255,255,0.02);
    border: 1px dashed var(--border);
    border-radius: 12px;
    margin: 20px 0;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--muted);
    font-family: 'Space Mono', monospace;
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 2px;
    min-height: 90px;
    overflow: hidden;
  }
  .ad-slot::before { content: 'Publicidade'; opacity: 0.5; }

  @media (max-width: 1024px) {
    .progress-bar {
      left: 0;
      top: 50px;
    }
  }

  /* SEARCH BOX */
  .search-box {
    position: relative;
    margin: 0 16px 20px;
    z-index: 110;
  }

  .search-box input {
    width: 100%;
    background: rgba(26, 26, 38, 0.6);
    backdrop-filter: blur(12px) saturate(180%);
    -webkit-backdrop-filter: blur(12px) saturate(180%);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-bottom: 2px solid var(--border);
    border-radius: 8px;
    padding: 12px 16px 12px 40px;
    color: var(--text);
    font-family: 'Inter', sans-serif;
    font-size: 13px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
  }

  .search-box input:focus {
    outline: none;
    background: rgba(34, 34, 53, 0.8);
    border-color: rgba(0,229,255,0.4);
    border-bottom-color: var(--accent);
    box-shadow: 0 8px 24px rgba(0,229,255,0.15);
    transform: translateY(-1px);
  }

  .search-box::before {
    content: '🔍';
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 14px;
    opacity: 0.7;
    z-index: 1;
    transition: all 0.2s;
  }

  .search-box:focus-within::before {
    opacity: 1;
    color: var(--accent);
  }

  /* Search Results Dropdown */
  .search-results {
    position: absolute;
    top: calc(100% + 8px);
    left: 0;
    right: 0;
    background: rgba(17, 17, 24, 0.85);
    backdrop-filter: blur(20px) saturate(180%);
    -webkit-backdrop-filter: blur(20px) saturate(180%);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.4);
    max-height: 400px;
    overflow-y: auto;
    display: none;
    z-index: 120;
    animation: searchReveal 0.2s ease-out;
  }

  @keyframes searchReveal {
    from { opacity: 0; transform: translateY(-10px) scale(0.98); }
    to { opacity: 1; transform: translateY(0) scale(1); }
  }

  .search-results.active { display: block; }

  .search-result-item {
    padding: 12px 16px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    flex-direction: column;
    gap: 4px;
    text-decoration: none;
  }

  .search-result-item:last-child { border-bottom: none; }

  .search-result-item:hover {
    background: rgba(0, 229, 255, 0.08);
  }

  .search-result-title {
    font-size: 13px;
    font-weight: 600;
    color: var(--text);
  }

  .search-result-category {
    font-family: 'Space Mono', monospace;
    font-size: 9px;
    text-transform: uppercase;
    color: var(--accent);
    letter-spacing: 1px;
  }

  .search-no-results {
    padding: 20px;
    text-align: center;
    color: var(--muted);
    font-size: 13px;
  }

/* Tempo de leitura */
.read-time {
    font-family: 'Space Mono', monospace;
    font-size: 10px;
    color: var(--muted);
    display: flex;
    align-items: center;
    gap: 5px;
    margin-top: 6px;
}
 
/* Navegação próximo/anterior */
.section-nav {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 40px;
    padding-top: 24px;
    border-top: 1px solid var(--border);
    flex-wrap: wrap;
    gap: 12px;
}
.section-nav-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 12px 18px;
    color: var(--muted);
    text-decoration: none;
    font-size: 13px;
    transition: all 0.2s;
}
.section-nav-btn:hover {
    border-color: var(--accent);
    color: var(--accent);
    transform: translateY(-2px);
}
.section-nav-btn.next {
    margin-left: auto;
    text-align: right;
}
.section-nav-label {
    font-family: 'Space Mono', monospace;
    font-size: 9px;
    text-transform: uppercase;
    letter-spacing: 1px;
    display: block;
    opacity: 0.6;
    margin-bottom: 2px;
}

.section-forum-link {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-top: 32px;
    padding: 14px 20px;
    background: rgba(0,229,255,0.05);
    border: 1px solid rgba(0,229,255,0.2);
    border-radius: 10px;
    font-size: 13px;
    color: var(--muted);
    flex-wrap: wrap;
}
.section-forum-link a {
    font-family: 'Space Mono', monospace;
    font-size: 11px;
    font-weight: 700;
    color: var(--accent);
    text-decoration: none;
    letter-spacing: 0.5px;
    white-space: nowrap;
    transition: opacity 0.2s;
}
.section-forum-link a:hover { opacity: 0.7; }

/* Índice flutuante */
.floating-toc {
    position: fixed;
    right: 24px;
    top: 50%;
    transform: translateY(-50%);
    z-index: 80;
    display: flex;
    flex-direction: column;
    gap: 4px;
    opacity: 0;
    transition: opacity 0.3s;
    pointer-events: none;
}
.floating-toc.visible {
    opacity: 1;
    pointer-events: all;
}
.toc-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--border);
    border: 1px solid rgba(255,255,255,0.1);
    cursor: pointer;
    transition: all 0.2s;
    position: relative;
}
.toc-dot:hover,
.toc-dot.active {
    background: var(--accent);
    transform: scale(1.4);
}
.toc-dot::after {
    content: attr(data-label);
    position: absolute;
    right: 18px;
    top: 50%;
    transform: translateY(-50%);
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 6px;
    padding: 4px 10px;
    font-family: 'Space Mono', monospace;
    font-size: 10px;
    color: var(--text);
    white-space: nowrap;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.2s;
}
.toc-dot:hover::after {
    opacity: 1;
}
@media (max-width: 1200px) {
    .floating-toc { display: none; }
}
</style>
</head>
<body class="mode-beginner">
<?php renderUserNotice(); ?>
<button class="menu-toggle" id="menuToggle" onclick="toggleMenu()" aria-label="Abrir menu" aria-expanded="false">&#9776;</button>
<div class="sidebar-backdrop" id="sidebarBackdrop" onclick="closeMenu()"></div>

<!-- User Bar -->
<div class="user-bar">
  <?php if (isLoggedIn()): 
    $user = getCurrentUser();
  ?>
    <div class="user-info">
      <div class="user-avatar">
        <?php if (!empty($user['avatar_url'])): ?>
          <img src="<?php echo sanitize(avPath($user['avatar_url'])); ?>" alt="" style="width:100%; height:100%; object-fit:cover; border-radius:50%;" onerror="this.style.display='none'; this.parentElement.textContent='<?php echo $user['avatar']; ?>'">
        <?php else: ?>
          <?php echo $user['avatar']; ?>
        <?php endif; ?>
      </div>
      <span class="user-name"><?php echo sanitize($user['full_name']); ?></span>
      <span class="user-role <?php echo $user['role']; ?>"><?php echo $user['role']; ?></span>
    </div>
    
    <a href="/perfil" class="btn-auth btn-profile">Perfil</a>
    <a href="/logout" class="btn-auth btn-logout">Sair</a>
  <?php else: ?>
    <a href="/login" class="btn-auth btn-login">Entrar</a>
    <a href="/register" class="btn-auth btn-register">Criar Conta</a>
<?php endif; ?>
</div>

<div class="progress-bar">
  <div class="progress-fill" id="progressFill"></div>
</div>

<!-- SIDEBAR -->
<nav id="sidebar">
  <div class="nav-logo">
    <div class="label">Manual Educativo</div>
    <h1>Impressão<br><span>3D</span></h1>
  </div>

  <div class="search-box">
    <input type="text" placeholder="Pesquisar..." id="searchInput" oninput="handleSearch(this.value)" autocomplete="off">
    <div id="searchResults" class="search-results"></div>
  </div>

  <div class="level-toggle">
    <button class="toggle-btn active" id="btn-beginner" onclick="setMode('beginner')">🎓 Iniciante</button>
    <button class="toggle-btn" id="btn-advanced" onclick="setMode('advanced')">🔬 Avançado</button>
  </div>

  <div class="nav-section">Introdução</div>
  <a href="#inicio" class="active"><span class="icon">🏠</span  > Início</a>
  <a href="#o-que-e"><span class="icon">🔍</span> O que é Impressão 3D?</a>
  <a href="#como-funciona"><span class="icon">⚙️</span> Como Funciona</a>

  <div class="nav-section">Equipamentos</div>
  <a href="#tipos-impressoras"><span class="icon">🖨️</span> Tipos de Impressoras</a>
  <a href="#iniciantes-vs-pro"><span class="icon">📊</span> Iniciantes vs Profissional</a>

  <div class="nav-section">Materiais</div>
  <a href="#filamentos"><span class="icon">🧵</span> Tipos de Filamento</a>
  <a href="#comparador"><span class="icon">⚖️</span> Comparador de Filamentos</a>
  <a href="#qual-usar"><span class="icon">🎯</span> Qual Filamento Usar</a>

  <div class="nav-section">Prática</div>
  <a href="#processo"><span class="icon">🔄</span> Processo de Impressão</a>
  <a href="#problemas"><span class="icon">🛠️</span> Problemas Comuns</a>
  <a href="#dicas"><span class="icon">💡</span> Dicas e Boas Práticas</a>

  <div class="nav-section">Referência</div>
  <a href="#software"><span class="icon">💻</span> Software (Slicers)</a>
  <a href="#glossario"><span class="icon">📖</span> Glossário</a>
  
  <div class="nav-section">Comunidade</div>
  <a href="#comentarios"><span class="icon">💬</span> Dúvidas & Comentários</a>
  <a href="forum/" style="color:#a78bfa;border-left-color:rgba(124,58,237,0.5)"><span class="icon">🌐</span> Fórum Global</a>
  <a href="/ai" style="color:#00e5ff;border-left-color:rgba(0,229,255,0.4)"><span class="icon">🤖</span> Print AI <span class="nav-badge beg">IA</span></a>
</nav>

<div class="floating-toc" id="floatingToc">
    <div class="toc-dot" data-target="inicio"     data-label="Início"></div>
    <div class="toc-dot" data-target="o-que-e"    data-label="O que é?"></div>
    <div class="toc-dot" data-target="como-funciona" data-label="Como Funciona"></div>
    <div class="toc-dot" data-target="tipos-impressoras" data-label="Tipos de Impressoras"></div>
    <div class="toc-dot" data-target="iniciantes-vs-pro" data-label="Iniciantes vs Pro"></div>
    <div class="toc-dot" data-target="filamentos" data-label="Filamentos"></div>
    <div class="toc-dot" data-target="comparador" data-label="Comparador"></div>
    <div class="toc-dot" data-target="qual-usar"  data-label="Qual Filamento?"></div>
    <div class="toc-dot" data-target="processo"   data-label="Parâmetros"></div>
    <div class="toc-dot" data-target="problemas"  data-label="Problemas Comuns"></div>
    <div class="toc-dot" data-target="dicas"      data-label="Dicas"></div>
    <div class="toc-dot" data-target="software"   data-label="Software"></div>
    <div class="toc-dot" data-target="glossario"  data-label="Glossário"></div>
    <div class="toc-dot" data-target="comentarios" data-label="Comentários"></div>
</div>

<!-- MAIN -->
<main>

  <!-- HERO -->
  <div class="hero" id="inicio">
    <div class="hero-grid"></div>
    <div class="hero-glow"></div>
    <div class="hero-glow2"></div>
    <div class="hero-tag">v2.0 — Professores & Alunos</div>
    <h2>O Guia Completo de<br><em>Impressão 3D</em></h2>
    <p>Do conceito básico ao uso profissional — aprende tudo o que precisas de saber para criar, imprimir e resolver problemas com impressoras 3D.</p>
    <div class="hero-badges">
      <div class="badge"><span>11</span> Capítulos</div>
      <div class="badge"><span>FDM · SLA · SLS</span> Tecnologias</div>
      <div class="badge"><span>10+</span> Tipos de Filamento</div>
      <div class="badge"><span>Iniciante → Pro</span> Nível</div>
    </div>
    <div class="quick-links">
      <a href="#o-que-e" class="quick-link">📚 Começar a Aprender</a>
      <a href="#problemas" class="quick-link">🛠️ Resolver Problemas</a>
      <a href="#comentarios" class="quick-link">💬 Fazer uma Pergunta</a>
      <a href="forum/" class="quick-link" style="border-color:rgba(124,58,237,0.35);background:rgba(124,58,237,0.05)">🌐 Fórum Global</a>
    </div>
  </div>

  <!-- LEVEL BAR -->
  <div class="level-bar beginner" id="level-bar">
    <div class="level-dot"></div>
    <div class="level-label" id="level-label">MODO INICIANTE</div>
    <div class="level-desc" id="level-desc">— Conteúdo simplificado para quem está a começar</div>
  </div>

  <!-- O QUE É -->
  <section class="section" id="o-que-e">
    <div class="section-header">
      <div class="section-number">01</div>
      <div class="section-title">
        <h2>O que é a Impressão 3D?</h2>
        <p>Conceitos fundamentais para começar</p>
      </div>
    </div>

    <div class="for-beginner">
      <p>A <strong>impressão 3D</strong> é uma tecnologia que cria objetos físicos a partir de um ficheiro digital. Imagina uma impressora normal que em vez de imprimir numa folha, vai construindo um objeto camada por camada até ficar completo.</p>
      <p>Podes imprimir quase tudo — brinquedos, peças de substituição, arte, ferramentas, modelos para estudar. Se conseguires desenhar no computador, consegues imprimir!</p>
      <div class="cards" style="margin-top:24px;">
        <div class="card">
          <span class="card-icon">📐</span>
          <h3>Começa num ficheiro</h3>
          <p>Crias ou descarregas um modelo 3D no computador. É como o molde do que vais imprimir.</p>
        </div>
        <div class="card">
          <span class="card-icon">🔄</span>
          <h3>Camada por camada</h3>
          <p>A impressora constrói o objeto em camadas muito finas, uma em cima da outra, até estar pronto.</p>
        </div>
        <div class="card">
          <span class="card-icon">🌍</span>
          <h3>Usos no dia a dia</h3>
          <p>Medicina, educação, arte, engenharia, brinquedos, peças de reposição — as possibilidades são quase infinitas.</p>
        </div>
      </div>
    </div>

    <div class="for-advanced">
      <p>A <strong>impressão 3D</strong>, ou <strong>fabricação aditiva</strong>, é um processo de manufatura que constrói objetos tridimensionais depositando ou solidificando material camada por camada a partir de um modelo digital. Ao contrário dos processos subtrativos (fresagem CNC, torneamento), a fabricação aditiva minimiza o desperdício de material e permite geometrias com liberdade de design quase ilimitada — incluindo estruturas ocas, reticuladas e canais internos inacessíveis por outros métodos.</p>
      <p>A tecnologia surgiu em 1983 com a patente de Charles Hull para estereolitografia (SLA). O movimento open-source RepRap (2005) democratizou o acesso, e hoje a impressão 3D é usada desde protótipos rápidos a produção em série em setores como aeroespacial, medicina, automóvel e eletrónica.</p>
      <div class="cards" style="margin-top:24px;">
        <div class="card">
          <span class="card-icon">🏭</span>
          <h3>Fabricação Aditiva</h3>
          <p>Material é adicionado camada a camada — ao contrário da maquinagem CNC que remove. Reduz desperdício e permite geometrias impossíveis de fabricar de outra forma.</p>
        </div>
        <div class="card">
          <span class="card-icon">📐</span>
          <h3>Modelo Digital (CAD)</h3>
          <p>Tudo começa com um ficheiro 3D no formato STL ou 3MF. Podes criá-lo em software CAD ou descarregar modelos de plataformas como Thingiverse ou Printables.</p>
        </div>
        <div class="card">
          <span class="card-icon">🔬</span>
          <h3>Precisão em Camadas</h3>
          <p>A espessura de cada camada varia entre 0.05mm e 0.4mm consoante a tecnologia. Camadas mais finas = mais detalhe, maior tempo de impressão e melhor resistência anisotrópica.</p>
        </div>
        <div class="card">
          <span class="card-icon">🌍</span>
          <h3>Aplicações Industriais</h3>
          <p>Medicina (implantes, próteses), aeroespacial (peças de motor), automóvel (protótipos), eletrónica (gabinetes) e manufatura distribuída.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- COMO FUNCIONA -->
  <section class="section" id="como-funciona">
    <div class="section-header">
      <div class="section-number">02</div>
      <div class="section-title">
        <h2>Como Funciona?</h2>
        <p>O processo passo a passo</p>
      </div>
    </div>

    <div class="for-beginner">
      <div class="process">
        <div class="step">
          <div class="step-num">1</div>
          <div class="step-content">
            <h3>Desenha ou descarrega o modelo</h3>
            <p>Usa o <strong>Tinkercad</strong> (gratuito, funciona no browser) para criar o teu objeto no computador. Ou vai ao <strong>Thingiverse</strong> e descarrega um modelo já feito por outra pessoa.</p>
          </div>
        </div>
        <div class="step">
          <div class="step-num">2</div>
          <div class="step-content">
            <h3>Prepara o ficheiro no Slicer</h3>
            <p>Abre o modelo no <strong>PrusaSlicer</strong> ou <strong>Ultimaker Cura</strong> (gratuitos). O programa vai dividir o modelo em camadas e criar as instruções para a impressora.</p>
          </div>
        </div>
        <div class="step">
          <div class="step-num">3</div>
          <div class="step-content">
            <h3>Prepara a impressora</h3>
            <p>Coloca o filamento (o plástico), verifica se a cama está nivelada e define a temperatura certa para o material que vais usar.</p>
          </div>
        </div>
        <div class="step">
          <div class="step-num">4</div>
          <div class="step-content">
            <h3>Imprime!</h3>
            <p>A impressora faz tudo sozinha. Podes deixá-la a trabalhar. Dependendo do tamanho pode demorar desde 20 minutos até algumas horas.</p>
          </div>
        </div>
        <div class="step">
          <div class="step-num">5</div>
          <div class="step-content">
            <h3>Remove e finaliza</h3>
            <p>Tira a peça da cama, remove os suportes (se houver) e lixa ou pinta se quiseres um acabamento melhor.</p>
          </div>
        </div>
      </div>
    </div>

    <div class="for-advanced">
      <div class="process">
        <div class="step">
          <div class="step-num">1</div>
          <div class="step-content">
            <h3>Design CAD e exportação</h3>
            <p>Cria o modelo em software paramétrico (Fusion 360, SolidWorks) ou orgânico (Blender). Exporta em <strong>STL</strong> (mesh triangulado) ou <strong>3MF</strong> (preferível — inclui escala, cores e configurações). Verifica manifold geometry e orienta as faces normais corretamente antes de exportar.</p>
          </div>
        </div>
        <div class="step">
          <div class="step-num">2</div>
          <div class="step-content">
            <h3>Slicing e otimização de parâmetros</h3>
            <p>Importa no slicer e define: altura de camada, perímetros, infill (padrão e densidade), temperatura do bico e cama, velocidade, retraction, cooling e suportes. Perfis de material bem calibrados são fundamentais — salva-os para reutilização.</p>
          </div>
        </div>
        <div class="step">
          <div class="step-num">3</div>
          <div class="step-content">
            <h3>Calibração e preparação</h3>
            <p>Verifica o e-steps da extrusora, PID tuning do hot-end, tensão das correias e nivelamento da cama (manual ou ABL — BLTouch, CR Touch, Eddy). Para filamentos técnicos, seca o material previamente e usa câmara fechada.</p>
          </div>
        </div>
        <div class="step">
          <div class="step-num">4</div>
          <div class="step-content">
            <h3>Impressão e monitorização</h3>
            <p>A impressora executa o G-code. Usa Octoprint, Klipper ou a app do fabricante para monitorização remota com deteção de erros por IA (spaghetti detection). Verifica a primeira camada antes de te afastar.</p>
          </div>
        </div>
        <div class="step">
          <div class="step-num">5</div>
          <div class="step-content">
            <h3>Pós-processamento técnico</h3>
            <p>Remoção de suportes, lixagem progressiva (80→400 grit), acetone smoothing (ABS/ASA), resin coating, painting, annealing (PLA/PETG a 70–80°C para melhorar Tg), inserção de heat-set inserts ou roscagem.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- TIPOS DE IMPRESSORAS -->
  <section class="section" id="tipos-impressoras">
    <div class="section-header">
      <div class="section-number">03</div>
      <div class="section-title">
        <h2>Tipos de Impressoras</h2>
        <p>As principais tecnologias de impressão 3D</p>
      </div>
    </div>

    <div class="for-beginner">
      <div class="cards">
        <div class="card">
          <span class="card-icon">🔥</span>
          <h3>FDM — A mais comum</h3>
          <p>Derrete plástico e deposita-o camada por camada. É a mais barata e fácil de usar. É esta que provavelmente vais encontrar na escola. Usa bobines de filamento plástico.</p>
          <br><div class="tag tag-beginner">IDEAL PARA INICIANTES</div>
        </div>
        <div class="card">
          <span class="card-icon">🌊</span>
          <h3>SLA / Resina</h3>
          <p>Usa luz UV para solidificar resina líquida. Produz objetos com muito mais detalhe que o FDM, mas requer mais cuidado com os materiais (resina é tóxica).</p>
          <br><div class="tag tag-advanced">INTERMÉDIO</div>
        </div>
        <div class="card">
          <span class="card-icon">✨</span>
          <h3>SLS — Industrial</h3>
          <p>Usa um laser para fundir pó. Produz peças muito resistentes sem precisar de suportes. Usada em fábricas e hospitais. O equipamento custa dezenas de milhares de euros.</p>
          <br><div class="tag tag-pro">PROFISSIONAL</div>
        </div>
      </div>
    </div>

    <div class="for-advanced">
      <div class="cards">
        <div class="card">
          <span class="card-icon">🔥</span>
          <h3>FDM — Fused Deposition Modeling</h3>
          <p>Extrusão de termoplástico fundido por um bico aquecido. Arquitetura Cartesiana, CoreXY ou Delta. Extrusora Bowden vs Direct Drive. Resolução XY limitada pelo diâmetro do bico (0.2–0.8mm). Ideal para protótipos funcionais e peças estruturais.</p>
          <br><div class="tag tag-beginner">INICIANTE</div>
        </div>
        <div class="card">
          <span class="card-icon">🌊</span>
          <h3>SLA — Stereolithography</h3>
          <p>Fotopolimerização por laser UV ponto-a-ponto. Resolução XY ~25–50µm. Requer post-cure em câmara UV. Resinas standard, engineering, castable e biocompatíveis. Menor throughput mas detalhe superior ao FDM.</p>
          <br><div class="tag tag-advanced">INTERMÉDIO</div>
        </div>
        <div class="card">
          <span class="card-icon">💡</span>
          <h3>MSLA / LCD</h3>
          <p>Fotopolimerização por ecrã LCD monochrome com luz UV. Toda a camada é curada de uma vez — velocidade independente da complexidade da camada. Resolução tipicamente 4K–12K. Mais acessível que SLA point-by-point.</p>
          <br><div class="tag tag-advanced">INTERMÉDIO</div>
        </div>
        <div class="card">
          <span class="card-icon">✨</span>
          <h3>SLS — Selective Laser Sintering</h3>
          <p>Sinterização de pó polimérico por laser CO₂. Sem suportes necessários (o pó suporta a peça). Nylon PA12/PA11, TPU, Polypropylene. Peças isotrópicas de alta resistência. Requer gestão de pó e câmara de N₂.</p>
          <br><div class="tag tag-pro">PROFISSIONAL</div>
        </div>
        <div class="card">
          <span class="card-icon">🔩</span>
          <h3>DMLS / SLM — Metal</h3>
          <p>Fusão de pó metálico por laser de fibra (200–500W). Materiais: Ti6Al4V, Inconel 718, AlSi10Mg, aço inox 316L. Necessita suportes metálicos e tratamento térmico pós-impressão (HIP, stress relief). Câmara de argon inerte.</p>
          <br><div class="tag tag-pro">INDUSTRIAL</div>
        </div>
        <div class="card">
          <span class="card-icon">📦</span>
          <h3>PolyJet / MJF</h3>
          <p>Projeção de gotículas de fotopolímero com cura UV simultânea. Multi-material e multi-cor numa única peça. Resolução até 16µm em Z. MJF (HP) usa agente de fusão + luz IR sobre pó — alta produtividade industrial.</p>
          <br><div class="tag tag-pro">PROFISSIONAL</div>
        </div>
      </div>
    </div>
  </section>

  <!-- INICIANTES VS PRO -->
  <section class="section" id="iniciantes-vs-pro">
    <div class="section-header">
      <div class="section-number">04</div>
      <div class="section-title">
        <h2>Iniciantes vs Profissional</h2>
        <p>Qual impressora escolher para cada nível?</p>
      </div>
    </div>

    <div class="printer-grid">
      <div class="printer-card">
        <div class="printer-header">
          <div class="level">Para Iniciantes / Escolas</div>
          <h3>Impressoras de Entrada</h3>
          <p>Acessíveis, fáceis de configurar, grande comunidade</p>
        </div>
        <div class="printer-body">
          <div class="printer-feature">
            <span class="feat-icon">💰</span>
            <div class="feat-text">
              <strong>Preço acessível</strong>
              <span class="for-beginner">Entre 150€ e 600€. Ótimas para escolas e hobbyistas.</span>
              <span class="for-advanced">150€–600€. ROI rápido em prototipagem. TCO baixo com manutenção simples.</span>
            </div>
          </div>
          <div class="printer-feature">
            <span class="feat-icon">🔧</span>
            <div class="feat-text">
              <strong>Fácil configuração</strong>
              <span class="for-beginner">Muitos modelos chegam pré-montados com nivelamento automático.</span>
              <span class="for-advanced">ABL integrado (BLTouch/CR Touch/Eddy Coil). Firmware Marlin ou Klipper. Semi-montados com commissioning mínimo.</span>
            </div>
          </div>
          <div class="printer-feature">
            <span class="feat-icon">🎓</span>
            <div class="feat-text">
              <strong>Modelos recomendados</strong>
              <span>Bambu Lab A1 Mini, Prusa Mini+, Creality Ender 3 V3, Bambu Lab P1S</span>
            </div>
          </div>
          <div class="printer-feature">
            <span class="feat-icon">🧵</span>
            <div class="feat-text">
              <strong>Filamentos suportados</strong>
              <span class="for-beginner">PLA, PETG, TPU</span>
              <span class="for-advanced">PLA, PETG, TPU (alguns modelos também ABS/ASA com câmara improvisada)</span>
            </div>
          </div>
        </div>
        <div class="printer-footer">
          <div class="price-range">
            <span class="label">Faixa de Preço</span>
            <span class="value">150€ — 600€</span>
          </div>
          <div class="tag tag-beginner">RECOMENDADO P/ ESCOLAS</div>
        </div>
      </div>

      <div class="printer-card">
        <div class="printer-header">
          <div class="level" style="color: var(--accent2)">Para Empresas / Profissionais</div>
          <h3>Impressoras Profissionais</h3>
          <p>Alto desempenho, multi-material, produção em série</p>
        </div>
        <div class="printer-body">
          <div class="printer-feature">
            <span class="feat-icon">⚡</span>
            <div class="feat-text">
              <strong>Velocidade e precisão</strong>
              <span class="for-beginner">Impressão muito mais rápida e com qualidade mais consistente.</span>
              <span class="for-advanced">300–600mm/s com Input Shaping (resonance compensation). Repetibilidade dimensional ±0.1mm ou melhor.</span>
            </div>
          </div>
          <div class="printer-feature">
            <span class="feat-icon">🧪</span>
            <div class="feat-text">
              <strong>Filamentos de engenharia</strong>
              <span class="for-beginner">Suportam plásticos especiais muito mais resistentes ao calor e impacto.</span>
              <span class="for-advanced">ASA, Nylon PA6/PA12, PA-CF, PA-GF, PEEK, PEI, PC, PC-CF. Hot-end >300°C e câmara ativa a 60–70°C.</span>
            </div>
          </div>
          <div class="printer-feature">
            <span class="feat-icon">🔄</span>
            <div class="feat-text">
              <strong>Multi-material</strong>
              <span class="for-beginner">Podem imprimir com vários materiais ou cores ao mesmo tempo.</span>
              <span class="for-advanced">Sistemas AMS/AMS Lite (Bambu), MMU3 (Prusa) — até 16 filamentos. Suportes solúveis (PVA, BVOH) para geometrias complexas.</span>
            </div>
          </div>
          <div class="printer-feature">
            <span class="feat-icon">🏭</span>
            <div class="feat-text">
              <strong>Modelos recomendados</strong>
              <span>Bambu Lab X1E, Ultimaker S5/S7, Formlabs Form 4, Stratasys F370</span>
            </div>
          </div>
        </div>
        <div class="printer-footer">
          <div class="price-range">
            <span class="label">Faixa de Preço</span>
            <span class="value">1.500€ — 50.000€+</span>
          </div>
          <div class="tag tag-pro">EMPRESAS</div>
        </div>
      </div>
    </div>

    <br>
    <h4>Comparação Rápida</h4>
    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: 14px; overflow: hidden;">
      <table class="comparison-table">
        <thead>
          <tr>
            <th>Característica</th>
            <th>Iniciante (FDM simples)</th>
            <th>Avançado (FDM multi-mat.)</th>
            <th>Profissional (SLS/Resina)</th>
          </tr>
        </thead>
        <tbody>
          <tr><td>Facilidade de uso</td><td>●●●●●</td><td>●●●○○</td><td>●●○○○</td></tr>
          <tr><td>Qualidade de detalhe</td><td>●●●○○</td><td>●●●●○</td><td>●●●●●</td></tr>
          <tr><td>Velocidade de impressão</td><td>●●●○○</td><td>●●●●●</td><td>●●●○○</td></tr>
          <tr><td>Variedade de materiais</td><td>●●○○○</td><td>●●●●○</td><td>●●●●●</td></tr>
          <tr><td>Custo de aquisição</td><td>150€ – 400€</td><td>400€ – 2.000€</td><td>2.000€ – 50.000€+</td></tr>
        </tbody>
      </table>
    </div>
  </section>

  <!-- FILAMENTOS -->
  <section class="section" id="filamentos">
    <div class="section-header">
      <div class="section-number">05</div>
      <div class="section-title">
        <h2>Tipos de Filamento</h2>
        <p>Materiais para impressão FDM e as suas características</p>
      </div>
    </div>

    <div class="filament-grid">
      <div class="filament-row">
        <div class="filament-name">PLA</div>
        <div class="filament-temp">190–220°C</div>
        <div class="for-beginner filament-desc">O mais fácil de imprimir. Feito de amido de milho, é biodegradável. Ótimo para começar — decora, protótipos, objetos do dia a dia. Não resiste muito ao calor.</div>
        <div class="for-advanced filament-desc"><strong>Ácido Polilático</strong> — Tg ~60°C, frágil mas fácil de imprimir. Boa adesão entre camadas. Não resiste a UV ou humidade prolongada. Anneal a 70°C para melhorar Tg (com distorção). Pós-processamento fácil (lixagem, pintura, acetona não funciona).</div>
        <div class="filament-tags"><span class="tag tag-beginner">INICIANTE</span></div>
      </div>
      <div class="filament-row">
        <div class="filament-name">PETG</div>
        <div class="filament-temp">230–250°C</div>
        <div class="for-beginner filament-desc">Mais resistente que o PLA e ainda fácil de imprimir. Ótimo para peças que precisam de aguentar mais esforço ou temperatura ligeiramente mais alta.</div>
        <div class="for-advanced filament-desc"><strong>Politereftalato de Etileno com Glicol</strong> — Tg ~80°C, boa tenacidade, semi-flexível. Excelente adesão inter-camada. Hygroscópico mas menos que Nylon. Tende a stringing — requer retraction afinado. Resistente a químicos e UV moderado.</div>
        <div class="filament-tags"><span class="tag tag-beginner">INICIANTE</span></div>
      </div>
      <div class="filament-row">
        <div class="filament-name">ABS</div>
        <div class="filament-temp">230–250°C</div>
        <div class="for-beginner filament-desc">Plástico muito resistente ao impacto e ao calor. Mais difícil de imprimir — precisa de um espaço fechado e ventilado. Evita em sala de aula sem ventilação adequada.</div>
        <div class="for-advanced filament-desc"><strong>Acrilonitrila Butadieno Estireno</strong> — Tg ~105°C. Alta contração térmica (warping intenso). Requer câmara fechada e cama a 100°C+. Emite estireno (VOC) — ventilação obrigatória. Suavização com acetona. Boa usinabilidade.</div>
        <div class="filament-tags"><span class="tag tag-advanced">INTERMÉDIO</span></div>
      </div>
      <div class="filament-row">
        <div class="filament-name">ASA</div>
        <div class="filament-temp">240–260°C</div>
        <div class="for-beginner filament-desc">Como o ABS mas aguenta muito melhor o sol e a chuva. Ideal para peças que ficam no exterior. Também precisa de câmara fechada.</div>
        <div class="for-advanced filament-desc"><strong>Acrilonitrila Estireno Acrilato</strong> — Substituto do ABS com resistência UV superior. Menos warping que ABS. Bom para aplicações outdoor: sinalização, suportes de painel solar, peças automóvel exteriores. Câmara fechada recomendada.</div>
        <div class="filament-tags"><span class="tag tag-advanced">INTERMÉDIO</span></div>
      </div>
      <div class="filament-row">
        <div class="filament-name">TPU</div>
        <div class="filament-temp">220–240°C</div>
        <div class="for-beginner filament-desc">Plástico flexível e elástico! Perfeito para capas de telemóvel, solas de sapatos ou qualquer coisa que precise de dobrar sem partir.</div>
        <div class="for-advanced filament-desc"><strong>Poliuretano Termoplástico</strong> — Shore 85A–95A. Necessita extrusora Direct Drive (Bowden causa atolamentos). Velocidade reduzida (20–30mm/s). Boa resistência química. Variantes: TPE (mais mole), TPC (mais resistente ao calor).</div>
        <div class="filament-tags"><span class="tag tag-advanced">INTERMÉDIO</span></div>
      </div>
      <div class="filament-row">
        <div class="filament-name">Nylon (PA)</div>
        <div class="filament-temp">250–280°C</div>
        <div class="for-beginner filament-desc">Material de engenharia muito resistente ao desgaste. Usado em engrenagens e peças mecânicas. Difícil de imprimir e absorve muita humidade do ar.</div>
        <div class="for-advanced filament-desc"><strong>Poliamida PA6/PA12</strong> — Alta resistência ao desgaste e química. Altamente hygroscópico — secar a 70°C/8h e usar em secador ativo. Requer câmara fechada (60°C+). PA12 tem menor absorção de humidade que PA6. Base para compósitos PA-CF e PA-GF.</div>
        <div class="filament-tags"><span class="tag tag-pro">PROFISSIONAL</span></div>
      </div>
      <div class="filament-row">
        <div class="filament-name">PA-CF</div>
        <div class="filament-temp">260–290°C</div>
        <div class="for-beginner filament-desc">Nylon reforçado com fibra de carbono. Extremamente leve e rígido. Usado em drones e peças de desempenho. Precisa de impressoras especiais.</div>
        <div class="for-advanced filament-desc"><strong>Poliamida com Fibra de Carbono</strong> — Stiffness muito superior ao Nylon puro. Abrasivo — bico de aço endurecido (hardened steel) obrigatório. Melhor relação rigidez/peso para aplicações estruturais. Alternativas: PA-GF (glass fiber — melhor isotrópico).</div>
        <div class="filament-tags"><span class="tag tag-pro">PROFISSIONAL</span></div>
      </div>
      <div class="filament-row">
        <div class="filament-name">PEEK</div>
        <div class="filament-temp">380–420°C</div>
        <div class="for-beginner filament-desc">O plástico de maior performance. Resiste a temperaturas extremas e produtos químicos agressivos. Usado em aviões e implantes médicos. Precisas de uma impressora especial.</div>
        <div class="for-advanced filament-desc"><strong>Poliéter Éter Cetona</strong> — Tg ~143°C, Tm ~343°C. Resistência química e mecânica extremas. Requer hot-end >400°C (bico de cobre ou aço endurecido), cama a 120°C+ e câmara a 80–90°C. Biocompatível para implantes. Custo elevado (~150€/kg).</div>
        <div class="filament-tags"><span class="tag tag-pro">INDUSTRIAL</span></div>
      </div>
    </div>
    <div class="section-forum-link">
      <span>💬 Tens dúvidas sobre filamentos?</span>
      <a href="forum/comunidade.php?slug=materiais-filamentos">Discute no Fórum →</a>
    </div>
  </section>

  <!-- COMPARADOR DE MATERIAIS -->
  <section class="section" id="comparador">
    <div class="section-header">
      <div class="section-number">05B</div>
      <div class="section-title">
        <h2>Comparador de Materiais</h2>
        <p>Dados técnicos para ajudar na escolha certa</p>
      </div>
    </div>
    <div style="overflow-x:auto; background:var(--surface); border:1px solid var(--border); border-radius:14px;">
        <table class="comparison-table">
            <thead>
                <tr>
                    <th>Filamento</th>
                    <th>Facilidade</th>
                    <th>Resistência</th>
                    <th>Resist. Térmica</th>
                    <th>Warping</th>
                </tr>
            </thead>
            <tbody>
                <!-- MODO INICIANTE -->
                <tr class="for-beginner"><td>PLA</td><td>⭐⭐⭐⭐⭐</td><td>⭐⭐</td><td>⭐⭐</td><td>Mínimo</td></tr>
                <tr class="for-beginner"><td>PLA+</td><td>⭐⭐⭐⭐⭐</td><td>⭐⭐⭐</td><td>⭐⭐</td><td>Baixo</td></tr>
                <tr class="for-beginner"><td>PETG</td><td>⭐⭐⭐⭐</td><td>⭐⭐⭐</td><td>⭐⭐⭐</td><td>Médio</td></tr>
                <tr class="for-beginner"><td>LW-PLA</td><td>⭐⭐⭐⭐</td><td>⭐⭐</td><td>⭐⭐</td><td>Baixo</td></tr>

                <!-- MODO AVANÇADO -->
                <tr class="for-advanced"><td>ABS</td><td>⭐⭐</td><td>⭐⭐⭐⭐</td><td>⭐⭐⭐⭐</td><td>Elevado</td></tr>
                <tr class="for-advanced"><td>ASA</td><td>⭐⭐</td><td>⭐⭐⭐⭐</td><td>⭐⭐⭐⭐⭐</td><td>Elevado</td></tr>
                <tr class="for-advanced"><td>TPU</td><td>⭐⭐⭐</td><td>⭐⭐⭐⭐⭐</td><td>⭐⭐</td><td>Baixo</td></tr>
                <tr class="for-advanced"><td>Nylon (PA)</td><td>⭐</td><td>⭐⭐⭐⭐⭐</td><td>⭐⭐⭐⭐⭐</td><td>Crítico</td></tr>
                <tr class="for-advanced"><td>PC (Policarbonato)</td><td>⭐</td><td>⭐⭐⭐⭐⭐</td><td>⭐⭐⭐⭐⭐</td><td>Extremo</td></tr>
                <tr class="for-advanced"><td>Carbon Fiber (PA-CF)</td><td>⭐⭐</td><td>⭐⭐⭐⭐⭐</td><td>⭐⭐⭐⭐⭐</td><td>Crítico</td></tr>
                <tr class="for-advanced"><td>PEEK</td><td>🚫 (Industrial)</td><td>⭐⭐⭐⭐⭐</td><td>⭐⭐⭐⭐⭐</td><td>Extremo</td></tr>
            </tbody>
        </table>
    </div>
  </section>

  <!-- QUAL FILAMENTO USAR -->
  <section class="section" id="qual-usar">
    <div class="section-header">
      <div class="section-number">06</div>
      <div class="section-title">
        <h2>Qual Filamento Usar?</h2>
        <p>Guia de seleção por aplicação</p>
      </div>
    </div>

    <div class="cards">
      <div class="card">
        <span class="card-icon">🎨</span>
        <h3>Objetos Decorativos</h3>
        <p class="for-beginner">Usa <span class="inline-tag">PLA</span>. Fácil de pintar, mil cores disponíveis. Experimenta os PLA especiais: madeira, mármore, glitter!</p>
        <p class="for-advanced">Usa <span class="inline-tag">PLA</span> ou <span class="inline-tag">PLA+</span>. Boa usinabilidade pós-impressão. Lixagem progressiva + filler primer para acabamento profissional. Silk PLA para efeito metálico.</p>
      </div>
      <div class="card">
        <span class="card-icon">🔩</span>
        <h3>Peças Funcionais</h3>
        <p class="for-beginner">Usa <span class="inline-tag">PETG</span> para uso geral. Aguenta mais esforço e calor que o PLA.</p>
        <p class="for-advanced">Usa <span class="inline-tag">PETG</span> para uso geral, <span class="inline-tag">Nylon</span> para engrenagens e desgaste, <span class="inline-tag">PC</span> para impacto e calor elevado. Considera infill gyroid a 40%+ e 4+ perímetros para resistência máxima.</p>
      </div>
      <div class="card">
        <span class="card-icon">☀️</span>
        <h3>Uso no Exterior</h3>
        <p class="for-beginner">Usa <span class="inline-tag">ASA</span>. Aguenta o sol, a chuva e o vento. Evita PLA que se deforma ao calor.</p>
        <p class="for-advanced">Usa <span class="inline-tag">ASA</span> como primeira opção. Para exposição química ou mecânica severa, <span class="inline-tag">PC</span> ou <span class="inline-tag">Nylon PA12</span>. Evita PETG em climas quentes (Tg ~80°C é marginal).</p>
      </div>
      <div class="card">
        <span class="card-icon">📱</span>
        <h3>Peças Flexíveis</h3>
        <p class="for-beginner">Usa <span class="inline-tag">TPU</span>. Elástico, não parte, perfeito para capas e proteções.</p>
        <p class="for-advanced">Usa <span class="inline-tag">TPU</span> (Shore 95A para rigidez, 85A para flexibilidade). Direct Drive obrigatório. Velocidade 20–30mm/s, sem retraction agressivo. Para maior resistência ao calor considera TPC.</p>
      </div>
      <div class="card">
        <span class="card-icon">🏫</span>
        <h3>Sala de Aula</h3>
        <p class="for-beginner">Quase sempre <span class="inline-tag">PLA</span>. Seguro, fácil, barato. É a escolha certa para projetos escolares.</p>
        <p class="for-advanced">Usa <span class="inline-tag">PLA</span> exclusivamente em espaços sem ventilação forçada. Emissão de partículas ~10× menor que ABS. <span class="inline-tag">PLA+</span> para peças com requisitos mecânicos ligeiros.</p>
      </div>
      <div class="card">
        <span class="card-icon">✈️</span>
        <h3>Alta Performance</h3>
        <p class="for-beginner">Usa <span class="inline-tag">PA-CF</span> ou <span class="inline-tag">PEEK</span>. Extremamente resistentes. Precisas de impressoras especiais e experiência.</p>
        <p class="for-advanced">Usa <span class="inline-tag">PA-CF</span> para relação rigidez/peso, <span class="inline-tag">PEEK</span> ou <span class="inline-tag">PEKK</span> para temperatura e química extremas. Bico hardened obrigatório. Valida com ensaios mecânicos (tração, flexão) para uso crítico.</p>
      </div>
    </div>
  </section>

  <div class="ad-slot" style="max-width: 900px; margin: 20px auto;"></div>

  <!-- PROCESSO -->
  <section class="section" id="processo">
    <div class="section-header">
      <div class="section-number">07</div>
      <div class="section-title">
        <h2>Parâmetros de Impressão</h2>
        <p>Os ajustes que fazem a diferença</p>
      </div>
    </div>

    <div class="cards">
      <div class="card">
        <span class="card-icon">📏</span>
        <h3>Altura de Camada</h3>
        <p class="for-beginner"><strong>0.2mm</strong> é o ponto de partida ideal. Mais baixo = mais bonito mas mais lento. Mais alto = mais rápido mas menos detalhe.</p>
        <p class="for-advanced"><strong>0.1–0.15mm</strong> = detalhe máximo. <strong>0.2mm</strong> = equilíbrio (padrão). <strong>0.3–0.4mm</strong> = velocidade para peças funcionais. Não exceder 75% do diâmetro do bico. Adaptive layer height no slicer para otimização automática.</p>
      </div>
      <div class="card">
        <span class="card-icon">🔢</span>
        <h3>Preenchimento (Infill)</h3>
        <p class="for-beginner"><strong>15–20%</strong> serve para a maioria dos projetos. Só usa mais se a peça precisar de ser muito resistente.</p>
        <p class="for-advanced"><strong>10–15%</strong> decorativo. <strong>20–30%</strong> uso geral. <strong>40–60%</strong> funcional. <strong>80–100%</strong> resistência máxima. Padrão <em>Gyroid</em> ou <em>Honeycomb</em> para melhor relação resistência/material. Número de paredes impacta mais a resistência que o infill.</p>
      </div>
      <div class="card">
        <span class="card-icon">🐢</span>
        <h3>Velocidade</h3>
        <p class="for-beginner">Começa com velocidades moderadas. Mais devagar = melhor resultado, especialmente nas primeiras camadas.</p>
        <p class="for-advanced">PLA standard: 60–120mm/s. Impressoras com Input Shaping (Bambu, Klipper): 300–600mm/s sem perda de qualidade. Primeira camada sempre lenta (20–30mm/s). Calibra Pressure Advance/Linear Advance para extrusão consistente em aceleração.</p>
      </div>
      <div class="card">
        <span class="card-icon">🌡️</span>
        <h3>Temperatura</h3>
        <p class="for-beginner">Cada material tem a sua temperatura certa. Segue sempre as indicações do fabricante do filamento na embalagem.</p>
        <p class="for-advanced">Faz tower de temperatura para calibrar o ponto ótimo de cada spool. Cama aquecida essencial para ABS/ASA/PC/Nylon. PID tuning do hot-end para estabilidade ±1°C. Temperatura mais alta = melhor adesão inter-camada mas mais stringing.</p>
      </div>
      <div class="card">
        <span class="card-icon">🏗️</span>
        <h3>Suportes</h3>
        <p class="for-beginner">Necessários quando há partes "no ar" com mais de 45°. O slicer gera-os automaticamente. Tenta orientar o modelo para minimizá-los.</p>
        <p class="for-advanced">Tree supports para contacto mínimo e remoção fácil. Interface layers com Z-distance 0.2mm para separação limpa. Suportes solúveis (PVA, BVOH) para geometrias complexas com multi-material. Angulo de overhang típico: 45–60° sem suporte.</p>
      </div>
      <div class="card">
        <span class="card-icon">🧱</span>
        <h3>Paredes</h3>
        <p class="for-beginner">2–3 paredes é o standard. Mais paredes = peça mais resistente e acabamento exterior melhor.</p>
        <p class="for-advanced">3–4 perímetros para peças estruturais. Alinhar paredes com forças esperadas. Thin walls detection no slicer para features pequenas. "Walls before infill" para melhor dimensional accuracy em peças de precisão.</p>
      </div>
    </div>
    <div class="section-forum-link">
      <span>💬 Tens dúvidas sobre parâmetros de impressão?</span>
      <a href="forum/comunidade.php?slug=software-slicers">Discute no Fórum →</a>
    </div>
  </section>

  <!-- PROBLEMAS -->
  <section class="section" id="problemas">
    <div class="section-header">
      <div class="section-number">08</div>
      <div class="section-title">
        <h2>Problemas Comuns e Soluções</h2>
        <p>Guia de troubleshooting para impressão FDM</p>
      </div>
    </div>

    <div class="filament-grid">
      <div class="filament-row">
        <div class="filament-name" style="color: var(--accent2);">Warping</div>
        <div class="filament-temp" style="color:var(--muted); font-size:11px;">Peça descola</div>
        <div class="for-beginner filament-desc">A peça levanta das bordas. Solução: limpa bem a cama, aumenta a temperatura da cama, e adiciona um "brim" nas definições do slicer.</div>
        <div class="for-advanced filament-desc">Causas: gradiente térmico elevado, adesão insuficiente, contração do material. Soluções: aumentar temperatura cama, brim 5–10mm, glue stick (PVA), limpeza com IPA 99%, câmara fechada, draft shield. Para ABS: enclosure com temperatura controlada a 40–50°C.</div>
        <div></div>
      </div>
      <div class="filament-row">
        <div class="filament-name" style="color: var(--accent2);">Stringing</div>
        <div class="filament-temp" style="color:var(--muted); font-size:11px;">Fios entre peças</div>
        <div class="for-beginner filament-desc">Fios finos a ligar partes do modelo. Solução: reduz um pouco a temperatura do bico e ativa a retração no slicer.</div>
        <div class="for-advanced filament-desc">Causas: temperatura excessiva, retraction insuficiente, pressão residual. Soluções: reduzir temp 5–10°C, aumentar retraction (Direct Drive: 0.5–2mm; Bowden: 4–7mm), aumentar velocidade de travel, ativar "wipe while retracting". Calibrar Pressure Advance.</div>
        <div></div>
      </div>
      <div class="filament-row">
        <div class="filament-name" style="color: var(--accent2);">Layer Splitting</div>
        <div class="filament-temp" style="color:var(--muted); font-size:11px;">Camadas separam</div>
        <div class="for-beginner filament-desc">As camadas não colam bem umas às outras. Solução: aumenta a temperatura do bico e reduz a velocidade de impressão.</div>
        <div class="for-advanced filament-desc">Causas: temperatura baixa, velocidade excessiva, filamento húmido, under-extrusion. Soluções: aumentar temp, reduzir velocidade, secar filamento (PLA 45°C/4h, Nylon 70°C/8h), verificar e-steps e calibrar flow rate.</div>
        <div></div>
      </div>
      <div class="filament-row">
        <div class="filament-name" style="color: var(--accent2);">Under-extrusion</div>
        <div class="filament-temp" style="color:var(--muted); font-size:11px;">Falta material</div>
        <div class="for-beginner filament-desc">A peça tem buracos ou paredes muito fracas. Verifica se o filamento está bem carregado e se o bico não está entupido.</div>
        <div class="for-advanced filament-desc">Causas: bico parcialmente entupido, patinagem da extrusora, filamento húmido, flow rate baixo. Diagnóstico: cold pull, test de extrusão de 100mm. Soluções: limpeza do bico, calibrar e-steps, aumentar flow 5–10%, substituir bico se desgastado.</div>
        <div></div>
      </div>
      <div class="filament-row">
        <div class="filament-name" style="color: var(--accent2);">Ghosting</div>
        <div class="filament-temp" style="color:var(--muted); font-size:11px;">Ondulações</div>
        <div class="for-beginner filament-desc">Ondulações visíveis nas paredes. Solução: reduz a velocidade de impressão.</div>
        <div class="for-advanced filament-desc">Causas: ressonância mecânica, velocidade/aceleração excessiva, correias soltas. Soluções: apertar correias, reduzir aceleração, implementar Input Shaping (ADXL345 + Klipper/Marlin) para compensação de frequência de ressonância. Melhorar rigidez da estrutura.</div>
        <div></div>
      </div>
    </div>

    <div class="warning-box">
      <div class="tip-header">⚠️ Segurança Importante</div>
      <p class="for-beginner">Impressoras 3D produzem vapores durante a impressão. Usa sempre a impressora num espaço ventilado com janelas abertas. Em sala de aula, usa sempre PLA — é o mais seguro de todos os filamentos.</p>
      <p class="for-advanced">Impressoras FDM emitem UFPs (Ultra Fine Particles) e VOCs — especialmente ABS (estireno), ASA e Nylon. Recomendado: câmara fechada com filtração HEPA + carvão ativado, extração ativa com ventilação para o exterior. Em laboratório escolar: usar exclusivamente PLA ou PLA+ em espaços ventilados. Monitorizar CO₂ e COV com sensor dedicado.</p>
    </div>
    <div class="section-forum-link">
      <span>💬 Tens um problema por resolver?</span>
      <a href="forum/comunidade.php?slug=troubleshooting">Discute no Fórum →</a>
    </div>
  </section>

  <!-- DICAS -->
  <section class="section" id="dicas">
    <div class="section-header">
      <div class="section-number">09</div>
      <div class="section-title">
        <h2>Dicas e Boas Práticas</h2>
        <p>O que a experiência ensina</p>
      </div>
    </div>

    <div class="cards">
      <div class="card">
        <span class="card-icon">🎯</span>
        <h3>Primeira camada é tudo</h3>
        <p class="for-beginner">Se a primeira camada não ficar bem colada e lisa, a impressão vai falhar. Vale a pena parar e ajustar antes de continuar.</p>
        <p class="for-advanced">A primeira camada determina 80% do sucesso. Live Adjust Z durante a impressão para afinação em tempo real. Deve ficar ligeiramente "squished" — bom fluxo lateral sem ranhuras visíveis nem bolhas.</p>
      </div>
      <div class="card">
        <span class="card-icon">🧪</span>
        <h3>Testa sempre primeiro</h3>
        <p class="for-beginner">Antes de imprimir uma peça grande, imprime um cubo pequeno de teste para garantir que as definições estão certas.</p>
        <p class="for-advanced">Imprime cubos de calibração XYZ, temperature towers e retraction tests ao mudar de filamento ou spool. Salva os perfis validados. Usa ferramentas como Ellis' Print Tuning Guide para calibração sistemática.</p>
      </div>
      <div class="card">
        <span class="card-icon">🔄</span>
        <h3>Orientação do modelo</h3>
        <p class="for-beginner">Como coloca o objeto na cama afeta o resultado. Tenta minimizar as partes que ficam "no ar" para precisar de menos suportes.</p>
        <p class="for-advanced">Orienta a peça para alinhar as camadas perpendicularmente às forças críticas (resistência 40–60% menor paralela às camadas). Minimize suportes mas não comprometas orientação estrutural. Considera dividir peças complexas em partes sem suportes.</p>
      </div>
      <div class="card">
        <span class="card-icon">💾</span>
        <h3>Guarda os teus perfis</h3>
        <p class="for-beginner">Quando encontrares as definições certas para um material, guarda-as no slicer para reutilizar depois.</p>
        <p class="for-advanced">Cria perfis por filamento e por impressora. Documenta marca, temperatura, velocidade, flow e retraction. Usa controlo de versões (Git) para perfis de equipa. Inclui data e spool ID para rastreabilidade.</p>
      </div>
      <div class="card">
        <span class="card-icon">🧹</span>
        <h3>Manutenção regular</h3>
        <p class="for-beginner">Limpa a cama com álcool antes de cada impressão. Lubrifica os eixos de vez em quando.</p>
        <p class="for-advanced">Lubrifica eixos lineares mensalmente (PTFE ou lubrificante de litio). Tensão das correias verificada semanalmente. Substitui bico a cada 3–6 meses (hardened steel a cada 1–2kg de filamento abrasivo). Limpa hot-end com cold pull regularmente. Log de manutenção com horas de impressão.</p>
      </div>
      <div class="card">
        <span class="card-icon">📷</span>
        <h3>Monitoriza remotamente</h3>
        <p class="for-beginner">Há apps que te permitem ver a impressão à distância. Assim não precisas de estar sempre junto à máquina.</p>
        <p class="for-advanced">Instala OrcaSlicer com monitorização, Obico (ex-The Spaghetti Detective) para AI failure detection, ou usa Bambu Handy/app. Integra com Home Assistant para alertas. Considera câmara de time-lapse para documentação e diagnóstico.</p>
      </div>
    </div>
  </section>

  <!-- SOFTWARE -->
  <section class="section" id="software">
    <div class="section-header">
      <div class="section-number">10</div>
      <div class="section-title">
        <h2>Software Essencial</h2>
        <p>Ferramentas para design e preparação de impressão</p>
      </div>
    </div>

    <h4>Slicers</h4>
    <div class="cards">
      <div class="card">
        <span class="card-icon">🟠</span>
        <h3>PrusaSlicer</h3>
        <p class="for-beginner">Gratuito, fácil de usar e com ótimos perfis pré-definidos. Perfeito para começar.</p>
        <p class="for-advanced">Open-source, baseado em Slic3r. Suporte para multi-material, adaptive layer height, modifier meshes, variable infill. Comunidade ativa e perfis de qualidade para a maioria das impressoras.</p>
        <br><div class="tag tag-beginner">GRATUITO</div>
      </div>
      <div class="card">
        <span class="card-icon">🔵</span>
        <h3>OrcaSlicer</h3>
        <p class="for-beginner">Versão melhorada do PrusaSlicer. Fácil de instalar e funciona com quase todas as impressoras.</p>
        <p class="for-advanced">Fork do Bambu Studio com calibrações automáticas avançadas (pressure advance, flow ratio, tolerance test). Input shaping integrado. Suporte universal e desenvolvimento muito ativo. Recomendado para utilizadores avançados.</p>
        <br><div class="tag tag-beginner">GRATUITO</div>
      </div>
      <div class="card">
        <span class="card-icon">🟡</span>
        <h3>Ultimaker Cura</h3>
        <p class="for-beginner">Um dos mais populares do mundo. Interface amigável com muitas opções para aprender gradualmente.</p>
        <p class="for-advanced">Marketplace de plugins extensível. Bom para workflow de equipa com perfis partilhados. Marketplace de materiais certificados. Engine bem testado mas desenvolvimento mais lento que OrcaSlicer.</p>
        <br><div class="tag tag-beginner">GRATUITO</div>
      </div>
    </div>

    <h4>Design 3D (CAD)</h4>
    <div class="cards">
      <div class="card">
        <span class="card-icon">🎮</span>
        <h3>Tinkercad</h3>
        <p class="for-beginner">100% no browser, completamente gratuito. Ideal para crianças e principiantes. Arrasta e combina formas para criar o teu modelo.</p>
        <p class="for-advanced">CSG (Constructive Solid Geometry) simplificado. Bom para prototipagem muito rápida e ensino. Exporta STL diretamente. Limitado para designs complexos ou paramétricos — considera migrar para Fusion 360.</p>
        <br><div class="tag tag-beginner">IDEAL P/ ESCOLAS</div>
      </div>
      <div class="card">
        <span class="card-icon">🔧</span>
        <h3>Fusion 360</h3>
        <p class="for-beginner">Software profissional gratuito para estudantes. Permite criar peças mecânicas precisas com medidas exatas.</p>
        <p class="for-advanced">CAD/CAM/CAE integrado. Modelação paramétrica com timeline de features. Simulação estática e modal. Integração CAM para CNC. Free para uso pessoal/startup com limitações. Standard profissional da indústria.</p>
        <br><div class="tag tag-advanced">INTERMÉDIO</div>
      </div>
      <div class="card">
        <span class="card-icon">🌐</span>
        <h3>Blender</h3>
        <p class="for-beginner">Software gratuito para criar modelos orgânicos e artísticos. Tem muitos tutoriais no YouTube para aprender.</p>
        <p class="for-advanced">Modelação polygonal, sculpting, procedural (Geometry Nodes). Ideal para modelos orgânicos, arte e design. Verifica manifold geometry antes de exportar para impressão (plugin 3D Print Toolbox incluído). Comunidade enorme.</p>
        <br><div class="tag tag-advanced">AVANÇADO</div>
      </div>
    </div>

    <h4>Repositórios de Modelos Gratuitos</h4>
    <div class="cards">
      <div class="card">
        <span class="card-icon">🐙</span>
        <h3>Printables.com</h3>
        <p>Plataforma da Prusa com milhares de modelos gratuitos de alta qualidade, avaliações verificadas e sistema de pontos por downloads.</p>
      </div>
      <div class="card">
        <span class="card-icon">🌍</span>
        <h3>Thingiverse</h3>
        <p>O maior repositório de modelos 3D gratuitos. Peças funcionais, arte, miniaturas e objetos úteis — tens de tudo.</p>
      </div>
      <div class="card">
        <span class="card-icon">🔮</span>
        <h3>MyMiniFactory</h3>
        <p>Foco em modelos premium e comunidade de designers. Tem modelos gratuitos e pagos. Ótimo para miniaturas de jogos.</p>
      </div>
    </div>
  </section>

  <!-- GLOSSÁRIO -->
  <section class="section" id="glossario">
    <div class="section-header">
      <div class="section-number">11</div>
      <div class="section-title">
        <h2>Glossário</h2>
        <p>Termos essenciais da impressão 3D</p>
      </div>
    </div>

    <dl class="glossary">
      <div class="glossary-item">
        <dt>G-code</dt>
        <dd class="for-beginner">As instruções que a impressora segue. Gerado automaticamente pelo slicer a partir do teu modelo.</dd>
        <dd class="for-advanced">Linguagem de controlo numérico para máquinas CNC e impressoras 3D. Gerado pelo slicer. Contém movimentos XYZ, temperaturas, velocidades e caudais de extrusão.</dd>
      </div>
      <div class="glossary-item">
        <dt>Slicer</dt>
        <dd>Software que converte um modelo 3D em camadas e gera o G-code. Cura, PrusaSlicer e OrcaSlicer são os mais populares.</dd>
      </div>
      <div class="glossary-item">
        <dt>Infill / Preenchimento</dt>
        <dd class="for-beginner">Estrutura interna da peça. Em percentagem — mais % = mais sólido e resistente mas usa mais material.</dd>
        <dd class="for-advanced">Estrutura interna gerada pelo slicer. Padrões: grid, gyroid, honeycomb, lightning. Gyroid oferece melhor isotropia. A densidade e o padrão afetam resistência, flexibilidade e tempo de impressão.</dd>
      </div>
      <div class="glossary-item">
        <dt>Bed Leveling</dt>
        <dd class="for-beginner">Ajustar a cama para que esteja à mesma distância do bico em toda a sua área. Essencial para boa impressão.</dd>
        <dd class="for-advanced">Calibração da planeza e altura do Z-offset entre bico e cama. Manual (papel/cartão) ou automático (ABL com sensor BLTouch, CR Touch, Klicky, Eddy Coil). Mesh bed leveling compensa irregularidades da superfície.</dd>
      </div>
      <div class="glossary-item">
        <dt>Retraction</dt>
        <dd class="for-beginner">Recuo do filamento quando a impressora se move sem imprimir. Evita que fiquem fios entre partes da peça.</dd>
        <dd class="for-advanced">Recuo do filamento para reduzir pressão no hot-end durante movimentos de travel. Direct Drive: 0.5–2mm. Bowden: 4–7mm. Velocidade: 25–45mm/s. Pressure Advance/Linear Advance para compensação dinâmica.</dd>
      </div>
      <div class="glossary-item">
        <dt>Warping</dt>
        <dd>Deformação das bordas da peça por contração térmica. Mais comum em ABS, ASA e Nylon sem câmara fechada.</dd>
      </div>
      <div class="glossary-item">
        <dt>STL / 3MF</dt>
        <dd class="for-beginner">Formatos de ficheiro para modelos 3D. Como um .pdf mas para objetos 3D — é o que envias para a impressora.</dd>
        <dd class="for-advanced">STL: mesh de triângulos, sem cor ou escala, amplamente suportado. 3MF: formato moderno com metadados completos (escala, cor, material, thumbnail). Preferir 3MF para preservar configurações entre slicer e impressora.</dd>
      </div>
      <div class="glossary-item">
        <dt>CAD</dt>
        <dd>Computer-Aided Design. Software para criar modelos 3D digitais (Tinkercad, Fusion 360, SolidWorks, Blender).</dd>
      </div>
      <div class="glossary-item">
        <dt>Input Shaping</dt>
        <dd class="for-beginner">Tecnologia que permite imprimir muito mais rápido sem perder qualidade, compensando as vibrações da máquina.</dd>
        <dd class="for-advanced">Algoritmo de compensação de ressonância (Zeta/MZV/EI) medido com acelerómetro (ADXL345). Cancela as frequências de ressonância da estrutura, permitindo acelerações de 5000–20000mm/s² sem ghosting/ringing.</dd>
      </div>
      <div class="glossary-item">
        <dt>Hygroscópico</dt>
        <dd class="for-beginner">Materiais que absorvem humidade do ar. O filamento húmido faz bolhas durante a impressão.</dd>
        <dd class="for-advanced">Propriedade de absorver água da atmosfera. PA, TPU, PC e PVA são altamente hygroscópicos. Filamento húmido causa: bolhas, stringing excessivo, underextrusion, degradação de propriedades mecânicas. Guardar em caixas herméticas com sílica gel.</dd>
      </div>
      <div class="glossary-item">
        <dt>Hot-end</dt>
        <dd class="for-beginner">A parte quente da impressora que derrete o filamento. Inclui o bico (a ponta de onde sai o plástico).</dd>
        <dd class="for-advanced">Conjunto termico: heater block, cartridge heater, thermistor/thermocouple, heat break (barreira térmica), heat sink e nozzle. Bicos em latão (standard), aço endurecido (abrasivos) ou cobre (alta condutividade). Diâmetros: 0.2–1.0mm.</dd>
      </div>
      <div class="glossary-item">
        <dt>Pressure Advance</dt>
        <dd class="for-beginner">Ajuste que melhora a qualidade dos cantos e detalhes da impressão.</dd>
        <dd class="for-advanced">Algoritmo (Klipper) / Linear Advance (Marlin) que pré-carrega e alivia pressão no hot-end em antecipação às acelerações. Elimina oozing em cantos e blob no inicio de linhas. Calibrado com torre de pressure advance ou script.</dd>
      </div>
    </dl>
  </section>

  <!-- CALCULADORAS -->
  <section class="section" id="ferramentas">
    <div class="card" style="background:linear-gradient(135deg, rgba(0,229,255,0.1), rgba(124,58,237,0.1)); border-color: var(--accent);">
        <div style="display:flex; align-items:center; gap:20px; flex-wrap:wrap">
            <div style="font-size:40px">🧮</div>
            <div style="flex:1">
                <h3 style="margin:0; font-family:'Syne'">Ferramentas de Cálculo</h3>
                <p style="color:var(--muted); font-size:14px">Estima o custo do filamento e o gasto elétrico de cada impressão.</p>
            </div>
            <a href="/calculadora" class="btn-auth btn-profile" style="background:var(--accent); color:#000; border:none; padding:12px 24px">ABRIR CALCULADORA</a>
        </div>
    </div>
</section>


  <?php require_once 'comments_component.php'; ?>

  <footer style="padding: 40px 60px 120px; border-top: 1px solid var(--border); margin-top: 50px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 24px;">

      <!-- Lado Esquerdo: Copyright -->
      <div style="text-align: left;">
        <p style="font-size: 11px; color: var(--muted); margin: 0; font-family: 'Space Mono', monospace; letter-spacing: 0.5px;">
          © <?php echo date('Y'); ?> <strong>Manual de Impressão 3D</strong>
        </p>
      </div>

      <!-- Lado Direito: Social e Legal -->
      <div style="display: flex; gap: 20px; align-items: center; margin-left: auto;">
        <div style="display: flex; gap: 15px;">
          <a href="https://github.com/PurpleF0x" target="_blank" style="color: var(--muted); text-decoration: none; font-size: 18px; opacity: 0.6; transition: 0.2s;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0.6" title="GitHub">
            <svg style="width:20px;height:20px;fill:currentColor" viewBox="0 0 24 24"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/></svg>
          </a>
          <a href="https://www.linkedin.com/in/martim-s%C3%A1-2719351ba/" target="_blank" style="color: var(--muted); text-decoration: none; font-size: 18px; opacity: 0.6; transition: 0.2s;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0.6" title="LinkedIn">
            <svg style="width:20px;height:20px;fill:currentColor" viewBox="0 0 24 24"><path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/></svg>
          </a>
        </div>
        <div style="width: 1px; height: 15px; background: var(--border);"></div>
        <a href="/sobre" style="color: var(--accent); text-decoration: none; font-size: 11px; font-family: 'Space Mono', monospace; font-weight: bold;">Sobre</a>
        <a href="/contacto" style="color: var(--muted); text-decoration: none; font-size: 11px; font-family: 'Space Mono', monospace;">Contacto</a>
        <a href="/terms" style="color: var(--muted); text-decoration: none; font-size: 11px; font-family: 'Space Mono', monospace;">Termos</a>
        <a href="/privacy" style="color: var(--muted); text-decoration: none; font-size: 11px; font-family: 'Space Mono', monospace;">Privacidade</a>
        <a href="/suporte" style="color: var(--muted); text-decoration: none; font-size: 11px; font-family: 'Space Mono', monospace;">Suporte</a>
      </div>

    </div>
  </footer>

</main>

<button class="back-to-top" id="backToTop" onclick="scrollToTop()">↑</button>

<script>
  // Modo Iniciante/Avançado
  function setMode(mode) {
    const body = document.body;
    const bar = document.getElementById('level-bar');
    const label = document.getElementById('level-label');
    const desc = document.getElementById('level-desc');
    const btnBeg = document.getElementById('btn-beginner');
    const btnAdv = document.getElementById('btn-advanced');

    if (mode === 'beginner') {
      body.className = 'mode-beginner';
      bar.className = 'level-bar beginner';
      label.textContent = 'MODO INICIANTE';
      desc.textContent = '— Conteúdo simplificado para quem está a começar';
      btnBeg.classList.add('active');
      btnBeg.classList.remove('pro');
      btnAdv.classList.remove('active');
      localStorage.setItem('mode', 'beginner');
    } else {
      body.className = 'mode-advanced';
      bar.className = 'level-bar advanced';
      label.textContent = 'MODO AVANÇADO';
      desc.textContent = '— Conteúdo técnico detalhado para utilizadores experientes';
      btnAdv.classList.add('active', 'pro');
      btnBeg.classList.remove('active');
      localStorage.setItem('mode', 'advanced');
    }
  }

  const savedMode = localStorage.getItem('mode');
  if (savedMode === 'advanced') {
    setMode('advanced');
  }

  function setMenuOpen(open) {
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    const toggle = document.getElementById('menuToggle');
    sidebar.classList.toggle('open', open);
    if (backdrop) backdrop.classList.toggle('open', open);
    if (toggle) {
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      toggle.setAttribute('aria-label', open ? 'Fechar menu' : 'Abrir menu');
      toggle.innerHTML = open ? '&times;' : '&#9776;';
    }
    document.body.classList.toggle('sidebar-open', open);
  }

  function toggleMenu() {
    setMenuOpen(!document.getElementById('sidebar').classList.contains('open'));
  }

  function closeMenu() {
    setMenuOpen(false);
  }

  const sections = document.querySelectorAll('section[id], div[id]');
  const navLinks = document.querySelectorAll('nav a');

  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        navLinks.forEach(link => link.classList.remove('active'));
        const active = document.querySelector(`nav a[href="#${entry.target.id}"]`);
        if (active) active.classList.add('active');
      }
    });
  }, { threshold: 0.2 });

  sections.forEach(s => observer.observe(s));

  navLinks.forEach(link => {
    link.addEventListener('click', e => {
      const href = link.getAttribute('href');
      // Deixar links externos funcionar normalmente (fórum, perfil, etc.)
      if (!href || !href.startsWith('#')) return;
      e.preventDefault();
      const target = document.querySelector(href);
      if (target) {
        target.scrollIntoView({ behavior: 'smooth' });
        closeMenu();
      }
    });
  });

  window.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeMenu();
  });

  window.addEventListener('resize', () => {
    if (window.innerWidth > 1024) closeMenu();
  });

  window.addEventListener('scroll', () => {
    const scrollTop = window.scrollY;
    const docHeight = document.documentElement.scrollHeight - window.innerHeight;
    const progress = (scrollTop / docHeight) * 100;
    document.getElementById('progressFill').style.width = progress + '%';

    const backToTop = document.getElementById('backToTop');
    if (scrollTop > 500) {
      backToTop.classList.add('visible');
    } else {
      backToTop.classList.remove('visible');
    }
  });

  function scrollToTop() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  function handleSearch(query) {
    const resultsContainer = document.getElementById('searchResults');
    const queryLower = query.toLowerCase().trim();

    if (queryLower.length < 2) {
      resultsContainer.classList.remove('active');
      return;
    }

    const sections = document.querySelectorAll('.section');
    let resultsHtml = '';
    let matchCount = 0;

    sections.forEach(section => {
      const h2 = section.querySelector('h2');
      const title = h2 ? h2.textContent : 'Secção';
      const text = section.textContent.toLowerCase();
      const id = section.id;

      if (text.includes(queryLower)) {
        matchCount++;
        resultsHtml += `
          <a href="#${id}" class="search-result-item" onclick="closeSearch()">
            <span class="search-result-category">Manual</span>
            <span class="search-result-title">${title}</span>
          </a>
        `;
      }
    });

    // Adicionar link para pesquisa no fórum se não houver muitos resultados aqui
    resultsHtml += `
      <a href="forum/index.php" class="search-result-item">
        <span class="search-result-category">Fórum</span>
        <span class="search-result-title">Procurar "${query}" no fórum global →</span>
      </a>
    `;

    if (matchCount === 0 && queryLower.length >= 2) {
      resultsContainer.innerHTML = `
        <div class="search-no-results">
          Sem resultados no manual para "${query}"
        </div>
        <a href="forum/index.php" class="search-result-item">
          <span class="search-result-category">Fórum</span>
          <span class="search-result-title">Tentar no fórum global →</span>
        </a>
      `;
    } else {
      resultsContainer.innerHTML = resultsHtml;
    }

    resultsContainer.classList.add('active');
  }

  function closeSearch() {
    document.getElementById('searchResults').classList.remove('active');
  }

  // Fechar ao clicar fora
  document.addEventListener('click', function(e) {
    if (!document.querySelector('.search-box').contains(e.target)) {
      closeSearch();
    }
  });

  function searchContent() {
    // Mantido para compatibilidade se necessário, mas handleSearch é o novo padrão
    const query = document.getElementById('searchInput').value.toLowerCase();
    const allSections = document.querySelectorAll('.section');
    
    allSections.forEach(section => {
      const text = section.textContent.toLowerCase();
      if (text.includes(query)) {
        section.style.opacity = '1';
        section.style.display = 'block';
      } else {
        section.style.opacity = '0.3';
      }
      
      if (query === '') {
        section.style.opacity = '1';
      }
    });
  }
// Tempo de leitura
function calcReadTime(sectionEl) {
    var text = sectionEl.innerText || sectionEl.textContent || '';
    var words = text.trim().split(/\s+/).length;
    var mins = Math.max(2, Math.round(words / 120));
    return mins;
}
 
document.querySelectorAll('.section').forEach(function(sec) {
    var header = sec.querySelector('.section-title');
    if (!header) return;
    var mins = calcReadTime(sec);
    var rt = document.createElement('div');
    rt.className = 'read-time';
    rt.innerHTML = '⏱ ' + mins + ' min de leitura';
    header.appendChild(rt);
});

const sectionMap = [
  { id: "o-que-e", next: "como-funciona" },
  { id: "como-funciona", prev: "o-que-e", next: "tipos-impressoras" },
  { id: "tipos-impressoras", prev: "como-funciona", next: "iniciantes-vs-pro" },
  { id: "iniciantes-vs-pro", prev: "tipos-impressoras", next: "filamentos" },
  { id: "filamentos", prev: "iniciantes-vs-pro", next: "comparador" },
  { id: "comparador", prev: "filamentos", next: "qual-usar" },
  { id: "qual-usar", prev: "comparador", next: "processo" },
  { id: "processo", prev: "qual-usar", next: "problemas" },
  { id: "problemas", prev: "processo", next: "dicas" },
  { id: "dicas", prev: "problemas", next: "software" },
  { id: "software", prev: "dicas", next: "glossario" },
  { id: "glossario", prev: "software" }
];

sectionMap.forEach(sec => {
  const section = document.getElementById(sec.id);
  if (!section) return;

  const nav = document.createElement("div");
  nav.className = "section-nav";

  let html = "";

  if (sec.prev) {
    const prevH2 = document.querySelector(`#${sec.prev} h2`);
    html += `
      <a href="#${sec.prev}" class="section-nav-btn">
        <div>
          <span class="section-nav-label">← Capítulo anterior</span>
          ${prevH2 ? prevH2.innerText : sec.prev}
        </div>
      </a>
    `;
  } else {
    html += `<span></span>`;
  }

  if (sec.next) {
    const nextH2 = document.querySelector(`#${sec.next} h2`);
    html += `
      <a href="#${sec.next}" class="section-nav-btn next">
        <div>
          <span class="section-nav-label">Próximo capítulo</span>
          ${nextH2 ? nextH2.innerText : sec.next} →
        </div>
      </a>
    `;
  }

  nav.innerHTML = html;
  section.appendChild(nav);
});
// Índice flutuante
(function() {
    var toc = document.getElementById('floatingToc');
    if (!toc) return;
    var dots = Array.from(toc.querySelectorAll('.toc-dot'));
 
    // Click nos dots
    dots.forEach(function(dot) {
        dot.addEventListener('click', function() {
            var target = document.getElementById(dot.dataset.target);
            if (target) target.scrollIntoView({ behavior: 'smooth' });
        });
    });
 
    // Mostrar após scroll de 300px
    function updateToc() {
        var scrollY = window.scrollY;
 
        // Mostrar/esconder
        if (scrollY > 300) {
            toc.classList.add('visible');
        } else {
            toc.classList.remove('visible');
        }
 
        // Destacar secção activa
        var active = null;
        dots.forEach(function(dot) {
            var el = document.getElementById(dot.dataset.target);
            if (!el) return;
            var rect = el.getBoundingClientRect();
            if (rect.top <= 120) active = dot;
        });
        dots.forEach(function(d) { d.classList.remove('active'); });
        if (active) active.classList.add('active');
    }
 
    window.addEventListener('scroll', updateToc, { passive: true });
    updateToc();
})();

// --- Scroll Automático para Capítulo via URL ---
window.addEventListener('DOMContentLoaded', () => {
    const activeSectionId = '<?php echo $seo['id']; ?>';
    if (activeSectionId) {
        const target = document.getElementById(activeSectionId);
        if (target) {
            setTimeout(() => {
                target.scrollIntoView({ behavior: 'smooth' });
            }, 800); // Delay ligeiro para garantir renderização
        }
    }
});
</script>
<?php require_once 'includes/welcome_popup.php'; ?>
</body>
</html>
