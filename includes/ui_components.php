<?php
/**
 * Componentes Reutilizáveis de UI
 */

function renderSearchPill() {
    ?>
    <div class="search-pill-container" onclick="openGlobalSearch()">
        <div class="search-pill">
            <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
            <span>Procurar...</span>
            <span class="search-shortcut">Ctrl K</span>
        </div>
    </div>

    <!-- Overlay de Pesquisa -->
    <div id="global-search-overlay" class="search-overlay" style="display:none;">
        <div class="search-modal">
            <div class="search-header">
                <input type="text" id="global-search-input" placeholder="O que procuras hoje?" autocomplete="off">
            </div>
            <div id="search-results" class="search-results-body">
                <div class="search-placeholder">Escreve algo para começar a pesquisar...</div>
            </div>
        </div>
    </div>

    <style>
    .search-pill-container {
        padding: 10px;
        width: 100%;
        max-width: 300px;
    }
    .search-pill {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 20px;
        padding: 8px 15px;
        display: flex;
        align-items: center;
        gap: 10px;
        cursor: pointer;
        transition: all 0.2s;
        color: rgba(255, 255, 255, 0.7);
        font-size: 0.9rem;
    }
    .search-pill:hover {
        background: rgba(255, 255, 255, 0.1);
        border-color: var(--accent4, #00e5ff);
    }
    .search-icon { width: 18px; height: 18px; }
    .search-shortcut {
        margin-left: auto;
        font-size: 0.7rem;
        background: rgba(255,255,255,0.1);
        padding: 2px 6px;
        border-radius: 4px;
        opacity: 0.5;
    }

    /* Overlay */
    .search-overlay {
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0, 0, 0, 0.4);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        z-index: 9999;
        display: flex;
        justify-content: center;
        padding-top: 100px;
        animation: fadeIn 0.2s ease;
    }
    .search-modal {
        width: 90%;
        max-width: 600px;
        background: rgba(30, 30, 30, 0.95);
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 16px;
        box-shadow: 0 20px 50px rgba(0,0,0,0.5);
        overflow: hidden;
        height: fit-content;
        max-height: 70vh;
    }
    .search-header {
        padding: 20px;
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    #global-search-input {
        width: 100%;
        background: transparent;
        border: none;
        color: white;
        font-size: 1.2rem;
        outline: none;
    }
    .search-results-body {
        padding: 10px;
        overflow-y: auto;
    }
    .search-result-item {
        padding: 12px;
        border-radius: 8px;
        cursor: pointer;
        transition: background 0.2s;
        display: flex;
        flex-direction: column;
        gap: 4px;
        text-decoration: none;
        color: white;
    }
    .search-result-item:hover {
        background: rgba(255,255,255,0.05);
    }
    .result-source {
        font-size: 0.7rem;
        text-transform: uppercase;
        color: var(--accent4, #00e5ff);
        font-weight: bold;
    }
    .result-title { font-weight: 500; }

    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    </style>

    <script>
    function openGlobalSearch() {
        document.getElementById('global-search-overlay').style.display = 'flex';
        document.getElementById('global-search-input').focus();
    }

    // Fechar ao clicar fora
    document.getElementById('global-search-overlay').addEventListener('click', function(e) {
        if (e.target === this) this.style.display = 'none';
    });

    // Atalhos
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            openGlobalSearch();
        }
        if (e.key === 'Escape') {
            document.getElementById('global-search-overlay').style.display = 'none';
        }
    });

    // Lógica de Pesquisa Real-time
    let searchTimeout;
    document.getElementById('global-search-input').addEventListener('input', function(e) {
        clearTimeout(searchTimeout);
        const q = e.target.value;
        if (q.length < 2) return;

        searchTimeout = setTimeout(async () => {
            const res = await fetch(`api/search.php?q=${encodeURIComponent(q)}`);
            const data = await res.json();
            const container = document.getElementById('search-results');
            container.innerHTML = '';

            // Renderizar Manual
            data.manual.forEach(item => {
                container.innerHTML += `
                    <a href="${item.url}" class="search-result-item">
                        <span class="result-source">Manual Técnico</span>
                        <span class="result-title">${item.title}</span>
                    </a>
                `;
            });

            // Renderizar Fórum
            data.forum.forEach(item => {
                container.innerHTML += `
                    <a href="forum/topico.php?id=${item.id}" class="search-result-item">
                        <span class="result-source">Discussão Fórum</span>
                        <span class="result-title">${item.title}</span>
                    </a>
                `;
            });

            if (data.manual.length === 0 && data.forum.length === 0) {
                container.innerHTML = '<div class="search-placeholder">Sem resultados encontrados.</div>';
            }
        }, 300);
    });
    </script>
    <?php
}

function renderKarmaPopover(int $userId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT karma_total FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $karma = (int)$stmt->fetchColumn();
    $level = getUserLevel($karma);
    ?>
    <div id="karma-popover" class="karma-popover" style="display:none;">
        <div class="karma-content">
            <div class="karma-header" style="border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom:10px; margin-bottom:15px;">
                <span class="karma-total">⚡ <?= $karma ?> XP</span>
                <span class="karma-level-badge" style="background: <?= $level['color'] ?>22; color: <?= $level['color'] ?>; border: 1px solid <?= $level['color'] ?>44;">
                    <?= $level['name'] ?>
                </span>
            </div>

            <div class="xp-list">
                <div class="xp-title">⚡ Como ganhar XP</div>
                <div class="xp-item"><span>📝 Publicar um post</span> <span class="xp-val">+15</span></div>
                <div class="xp-item"><span>💬 Dar uma resposta</span> <span class="xp-val">+5</span></div>
                <div class="xp-item"><span>👍 Voto positivo recebido</span> <span class="xp-val">+3</span></div>
                <div class="xp-item"><span>👎 Voto negativo recebido</span> <span class="xp-val text-red">-2</span></div>
                <div class="xp-item"><span>⭐ Voto em resposta</span> <span class="xp-val">+1</span></div>
            </div>

            <div class="level-tiers" style="margin-top:20px; border-top: 1px solid rgba(255,255,255,0.05); padding-top:15px;">
                <div class="xp-title">🏆 Níveis</div>
                <div class="xp-item <?= $karma < 20 ? 'active' : '' ?>"><span>🌱 Novo</span> <span>0+</span></div>
                <div class="xp-item <?= ($karma >= 20 && $karma < 50) ? 'active' : '' ?>"><span>✅ Membro</span> <span>20+</span></div>
                <div class="xp-item <?= ($karma >= 50 && $karma < 100) ? 'active' : '' ?>"><span>🔥 Ativo</span> <span>50+</span></div>
                <div class="xp-item <?= ($karma >= 100 && $karma < 200) ? 'active' : '' ?>"><span>⭐ Veterano</span> <span>100+</span></div>
                <div class="xp-item <?= ($karma >= 200 && $karma < 500) ? 'active' : '' ?>"><span>💎 Especialista</span> <span>200+</span></div>
                <div class="xp-item <?= $karma >= 500 ? 'active' : '' ?>"><span>🏆 Lendário</span> <span>500+</span></div>
            </div>
        </div>
    </div>

    <style>
    .karma-popover {
        position: fixed;
        top: 60px;
        right: 20px;
        width: 280px;
        background: rgba(20, 20, 28, 0.95);
        backdrop-filter: blur(20px);
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 16px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.4);
        z-index: 10000;
        animation: slideIn 0.3s cubic-bezier(0.18, 0.89, 0.32, 1.28);
        padding: 20px;
    }
    .karma-total { font-weight: 800; font-size: 1.2rem; color: #ffd700; }
    .karma-level-badge {
        float: right; padding: 2px 8px; border-radius: 6px; font-size: 0.7rem; font-weight: bold; text-transform: uppercase;
    }
    .xp-title { font-size: 0.75rem; color: var(--muted, #888); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px; font-weight: bold; }
    .xp-item { display: flex; justify-content: space-between; font-size: 0.85rem; padding: 4px 0; color: rgba(255,255,255,0.8); }
    .xp-item.active { color: var(--accent4, #00ff88); font-weight: bold; }
    .xp-val { color: var(--accent4, #00ff88); font-family: 'Space Mono', monospace; font-weight: bold; }
    .xp-val.text-red { color: #ff4b2b; }
    @keyframes slideIn { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    </style>

    <script>
    function toggleKarma() {
        const p = document.getElementById('karma-popover');
        p.style.display = (p.style.display === 'none') ? 'block' : 'none';
    }
    // Fechar ao clicar fora
    document.addEventListener('click', function(e) {
        const p = document.getElementById('karma-popover');
        const trigger = document.querySelector('.karma-trigger');
        if (p && p.style.display === 'block' && !p.contains(e.target) && (!trigger || !trigger.contains(e.target))) {
            p.style.display = 'none';
        }
    });
    </script>
    <?php
}
