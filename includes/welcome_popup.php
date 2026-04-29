<?php
/**
 * includes/welcome_popup.php — Popup de Boas-vindas para novos utilizadores
 */
$is_forum_context = (strpos($_SERVER['SCRIPT_NAME'], '/forum/') !== false);
$manual_link = $is_forum_context ? '../index.php' : 'index.php';
$ai_page_link = $is_forum_context ? '../ai.php' : 'ai.php';
?>
<div id="welcomePopup" class="welcome-popup" style="display: none;">
    <div class="welcome-content">
        <button class="close-popup" onclick="closeWelcomePopup()" title="Fechar">&times;</button>
        <div class="welcome-header">
            <span class="welcome-icon">🚀</span>
            <h2>Bem-vindo ao Manual 3D!</h2>
        </div>
        <p>Parece que é a tua primeira vez por aqui. Sabias que temos uma <strong>IA Especialista</strong> pronta para tirar as tuas dúvidas?</p>
        <div class="welcome-actions">
            <a href="<?php echo $manual_link; ?>?section=o-que-e" class="btn-welcome-primary">Explorar o Manual</a>
            <button onclick="openAIChat()" class="btn-welcome-secondary">Falar com a IA</button>
        </div>
    </div>
</div>

<style>
.welcome-popup {
    position: fixed;
    bottom: 24px;
    right: 24px;
    width: 320px;
    background: #111118;
    border: 1px solid rgba(0, 229, 255, 0.2);
    border-radius: 18px;
    box-shadow: 0 20px 50px rgba(0,0,0,0.6);
    z-index: 10001;
    padding: 24px;
    font-family: 'Inter', sans-serif;
    animation: welcomeSlideUp 0.5s cubic-bezier(0.16, 1, 0.3, 1);
}
.welcome-content { position: relative; }
.close-popup {
    position: absolute;
    top: -12px;
    right: -8px;
    background: none;
    border: none;
    font-size: 20px;
    cursor: pointer;
    color: #888899;
    transition: color 0.2s;
}
.close-popup:hover { color: #fff; }
.welcome-header { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
.welcome-icon { font-size: 24px; }
.welcome-header h2 {
    margin: 0;
    font-family: 'Syne', sans-serif;
    font-size: 1.1rem;
    font-weight: 800;
    color: #fff;
}
.welcome-popup p {
    font-size: 0.9rem;
    color: #888899;
    line-height: 1.6;
    margin-bottom: 20px;
}
.welcome-popup p strong { color: #00e5ff; }
.welcome-actions { display: flex; flex-direction: column; gap: 8px; }
.btn-welcome-primary {
    background: linear-gradient(135deg, #00e5ff, #7c3aed);
    color: #000;
    padding: 10px;
    border-radius: 10px;
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 700;
    text-align: center;
    font-family: 'Space Mono', monospace;
    transition: transform 0.2s;
}
.btn-welcome-primary:hover { transform: translateY(-2px); }
.btn-welcome-secondary {
    background: rgba(255, 255, 255, 0.05);
    color: #e8e8f0;
    padding: 10px;
    border-radius: 10px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    cursor: pointer;
    font-size: 0.85rem;
    font-family: 'Space Mono', monospace;
    transition: all 0.2s;
}
.btn-welcome-secondary:hover { background: rgba(255, 255, 255, 0.1); border-color: rgba(0, 229, 255, 0.3); }

@keyframes welcomeSlideUp {
    from { transform: translateY(30px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

@media (max-width: 480px) {
    .welcome-popup { width: calc(100% - 48px); bottom: 16px; right: 24px; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (!localStorage.getItem('welcome_seen')) {
        setTimeout(() => {
            const popup = document.getElementById('welcomePopup');
            if (popup) popup.style.display = 'block';
        }, 4000);
    }
});

function closeWelcomePopup() {
    const popup = document.getElementById('welcomePopup');
    if (popup) {
        popup.style.opacity = '0';
        popup.style.transform = 'translateY(20px)';
        popup.style.transition = 'all 0.3s ease';
        setTimeout(() => {
            popup.style.display = 'none';
            localStorage.setItem('welcome_seen', 'true');
        }, 300);
    }
}

function openAIChat() {
    closeWelcomePopup();

    // Tentar encontrar o botão do Manual ou do Fórum
    const manualBtn = document.getElementById('printAI-btn');
    const forumBtn = document.getElementById('forumAI-btn');

    if (manualBtn) {
        if (typeof toggleAI === 'function') {
            toggleAI();
        } else {
            manualBtn.click();
        }
    } else if (forumBtn) {
        if (typeof toggleForumAI === 'function') {
            toggleForumAI();
        } else {
            forumBtn.click();
        }
    } else {
        window.location.href = '<?php echo $ai_page_link; ?>';
    }
}
</script>

