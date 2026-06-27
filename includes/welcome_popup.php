<?php
/**
 * includes/welcome_popup.php
 * Mostra um mini tutorial (onboarding) para novos utilizadores.
 * Design compacto no canto inferior direito.
 */

if (!isLoggedIn()) return;

$user = getCurrentUser();
$db = getDB();
$stmt = $db->prepare("SELECT has_seen_tutorial FROM user_profile_config WHERE user_id = ?");
$stmt->execute([$user['id']]);
$hasSeen = (bool)$stmt->fetchColumn();

if ($hasSeen) return;
?>

<!-- Tutorial UI Compacta -->
<div id="onboardingOverlay" style="position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:100000; display:flex; align-items:flex-end; justify-content:flex-end; padding: 100px 30px; pointer-events: none; transition:opacity 0.5s ease;">
    <div id="onboardingCard" style="background:var(--surface); border:1px solid var(--accent); border-radius:18px; padding:25px; max-width:320px; width:100%; position:relative; box-shadow:0 10px 40px rgba(0,0,0,0.8); pointer-events: auto; animation: onboardingSlideIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);">

        <div id="tutorialContent">
            <div id="step-0">
                <div style="font-size:32px; margin-bottom:15px; text-align:center;">🚀</div>
                <h2 style="font-family:'Syne'; font-size:18px; text-align:center; margin-bottom:10px; color:#fff;">Bem-vindo ao Manual 3D!</h2>
                <p style="color:var(--muted); text-align:center; font-size:13px; line-height:1.5;">Acabaste de entrar na maior plataforma educativa de Impressão 3D. Deixa-nos mostrar-te o básico.</p>
                <button onclick="nextStep(1)" style="width:100%; margin-top:20px; background:var(--accent); color:#000; border:none; padding:10px; border-radius:8px; font-weight:700; cursor:pointer; font-family:'Space Mono'; font-size:12px;">VAMOS A ISSO!</button>
            </div>

            <div id="step-1" style="display:none;">
                <div style="font-size:28px; margin-bottom:12px;">📖</div>
                <h3 style="font-family:'Syne'; color:#fff; font-size:16px; margin-bottom:8px;">O Teu Manual</h3>
                <p style="color:var(--muted); font-size:13px;">Aqui tens capítulos organizados do iniciante ao avançado. O teu progresso é guardado automaticamente.</p>
                <button onclick="nextStep(2)" style="width:100%; margin-top:20px; background:var(--accent); color:#000; border:none; padding:10px; border-radius:8px; font-weight:700; cursor:pointer; font-size:12px;">PRÓXIMO</button>
            </div>

            <div id="step-2" style="display:none;">
                <div style="font-size:28px; margin-bottom:12px;">🌱</div>
                <h3 style="font-family:'Syne'; color:#fff; font-size:16px; margin-bottom:8px;">Planta Maker</h3>
                <p style="color:var(--muted); font-size:13px;">Quanto mais aprendes, mais a tua planta cresce. Completa missões para ganhar XP e GP.</p>
                <button onclick="nextStep(3)" style="width:100%; margin-top:20px; background:var(--accent); color:#000; border:none; padding:10px; border-radius:8px; font-weight:700; cursor:pointer; font-size:12px;">ENTENDI!</button>
            </div>

            <div id="step-3" style="display:none;">
                <div style="font-size:28px; margin-bottom:12px;">🤖</div>
                <h3 style="font-family:'Syne'; color:#fff; font-size:16px; margin-bottom:8px;">Print AI</h3>
                <p style="color:var(--muted); font-size:13px;">Dúvidas? Clica no robô aqui em baixo para falar com a nossa IA especializada.</p>
                <button onclick="finishTutorial()" style="width:100%; margin-top:20px; background:var(--accent4); color:#000; border:none; padding:10px; border-radius:8px; font-weight:700; cursor:pointer; font-family:'Space Mono'; font-size:12px;">COMEÇAR AGORA!</button>
            </div>
        </div>

        <div id="onboardingDots" style="display:flex; gap:6px; justify-content:center; margin-top:15px;">
            <div class="dot" style="width:6px; height:6px; border-radius:50%; background:var(--accent);"></div>
            <div class="dot" style="width:6px; height:6px; border-radius:50%; background:rgba(255,255,255,0.1);"></div>
            <div class="dot" style="width:6px; height:6px; border-radius:50%; background:rgba(255,255,255,0.1);"></div>
            <div class="dot" style="width:6px; height:6px; border-radius:50%; background:rgba(255,255,255,0.1);"></div>
        </div>
    </div>
</div>

<style>
@keyframes onboardingSlideIn {
    from { opacity: 0; transform: translateX(50px); }
    to { opacity: 1; transform: translateX(0); }
}
@media (max-width: 480px) {
    #onboardingOverlay { align-items: center; justify-content: center; padding: 20px; }
    #onboardingCard { max-width: 100%; }
}
</style>

<script>
function nextStep(step) {
    for (let i = 0; i <= 3; i++) {
        let el = document.getElementById('step-' + i);
        if (el) el.style.display = 'none';
    }
    document.getElementById('step-' + step).style.display = 'block';
    const dots = document.querySelectorAll('#onboardingDots .dot');
    dots.forEach((dot, idx) => {
        dot.style.background = (idx === step) ? 'var(--accent)' : 'rgba(255,255,255,0.1)';
    });
}
async function finishTutorial() {
    const overlay = document.getElementById('onboardingOverlay');
    overlay.style.opacity = '0';
    try {
        await fetch('api/complete_tutorial.php', { method: 'POST' });
    } catch(e) { console.error(e); }
    setTimeout(() => {
        overlay.remove();
        if (typeof confetti === 'function') {
            confetti({
                particleCount: 150,
                spread: 70,
                origin: { y: 0.6 }
            });
        }
    }, 500);
}
</script>
