<?php
require_once 'includes/functions.php';
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calculadora de Impressao 3D - Manual 3D</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0a0a0f; --surface: #111118; --surface2: #1a1a26; --surface3: #222235;
            --accent: #00e5ff; --accent2: #ff6b35; --accent3: #7c3aed; --text: #e8e8f0; --muted: #888899;
            --border: rgba(0,229,255,0.12);
        }
        * { box-sizing: border-box; }
        body { background: var(--bg); color: var(--text); font-family: 'Inter', sans-serif; padding: 40px 20px; line-height: 1.6; margin: 0; }
        .container { max-width: 980px; margin: 0 auto; }
        .back-link { display: inline-block; margin-bottom: 20px; color: var(--accent); text-decoration: none; font-size: 14px; }
        h1 { font-family: 'Syne', sans-serif; font-size: clamp(30px, 5vw, 46px); color: #fff; margin: 0 0 8px; }
        .subtitle { color: var(--muted); margin: 0 0 28px; max-width: 680px; }
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: 18px; padding: 28px; margin-bottom: 24px; box-shadow: 0 10px 40px rgba(0,0,0,0.45); }
        .card h2 { margin: 0 0 20px; font-family: 'Syne', sans-serif; font-size: 20px; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
        .input-group { margin-bottom: 14px; }
        label { display: block; font-size: 11px; color: var(--muted); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 1px; font-family: 'Space Mono', monospace; }
        input, select { width: 100%; background: var(--surface2); border: 1px solid rgba(255,255,255,0.1); padding: 12px 14px; border-radius: 10px; color: #fff; font-size: 14px; }
        select { cursor: pointer; }
        input:focus, select:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 18px rgba(0,229,255,0.08); }
        .mode-toggle { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; background: var(--surface2); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 5px; margin-bottom: 20px; }
        .mode-btn { border: 0; border-radius: 8px; padding: 11px; background: transparent; color: var(--muted); font-family: 'Space Mono', monospace; font-size: 11px; font-weight: 700; letter-spacing: 1px; cursor: pointer; }
        .mode-btn.active { background: var(--accent); color: #000; }
        .mode-btn.advanced.active { background: var(--accent2); }
        .advanced-only { display: none; }
        body.mode-advanced .advanced-only { display: block; }
        .preset-note { border: 1px solid rgba(0,229,255,0.16); background: rgba(0,229,255,0.05); border-radius: 12px; padding: 12px 14px; color: var(--muted); font-size: 13px; margin-bottom: 18px; }
        .hint { color: var(--muted); font-size: 13px; margin: 10px 0 0; }
        .result-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; margin-top: 20px; }
        .result-box { background: rgba(0,229,255,0.05); border: 1px solid var(--accent); border-radius: 12px; padding: 18px; text-align: center; min-height: 112px; display: flex; flex-direction: column; justify-content: center; }
        .result-box.orange { background: rgba(255,107,53,0.05); border-color: var(--accent2); }
        .result-label { font-size: 11px; color: var(--muted); margin-bottom: 6px; text-transform: uppercase; letter-spacing: 1px; font-family: 'Space Mono', monospace; }
        .result-val { font-size: 26px; font-family: 'Syne', sans-serif; color: var(--accent); font-weight: 800; line-height: 1.1; }
        .result-box.orange .result-val { color: var(--accent2); }
        @media (max-width: 760px) {
            body { padding: 24px 14px; }
            .grid, .result-grid { grid-template-columns: 1fr; }
            .card { padding: 22px; border-radius: 14px; }
        }
    </style>
</head>
<body class="mode-beginner">
    <div class="container">
        <a href="index.php#ferramentas" class="back-link">&larr; Voltar ao Manual</a>
        <h1>Calculadoras Maker</h1>
        <p class="subtitle">Estima custos por tipo de filamento. O modo Iniciante usa presets seguros; o modo Avancado deixa ajustar densidade, diametro e parametros tecnicos.</p>

        <div class="card">
            <h2 style="color:var(--accent2)">Custo de material por filamento</h2>
            <div class="mode-toggle">
                <button class="mode-btn active" id="modeBeginner" type="button" onclick="setMode('beginner')">Iniciante</button>
                <button class="mode-btn advanced" id="modeAdvanced" type="button" onclick="setMode('advanced')">Avancado</button>
            </div>

            <div class="input-group">
                <label>Filamento</label>
                <select id="filamentType" onchange="applyFilamentPreset()">
                    <option value="PLA">PLA - facil e barato</option>
                    <option value="PETG">PETG - resistente</option>
                    <option value="ABS">ABS - calor e impacto</option>
                    <option value="ASA">ASA - exterior e UV</option>
                    <option value="TPU">TPU - flexivel</option>
                    <option value="NYLON">Nylon/PA - engenharia</option>
                    <option value="PACF">PA-CF - carbono</option>
                    <option value="PEEK">PEEK - industrial</option>
                </select>
            </div>
            <div class="preset-note" id="filamentNote"></div>

            <div class="grid">
                <div class="input-group">
                    <label>Preco da bobine (EUR)</label>
                    <input type="number" id="bobinePrice" value="20" step="0.01" oninput="calcAll()">
                </div>
                <div class="input-group">
                    <label>Peso da bobine (g)</label>
                    <input type="number" id="bobineWeight" value="1000" oninput="calcAll()">
                </div>
                <div class="input-group">
                    <label>Peso da peca (g)</label>
                    <input type="number" id="pieceWeight" value="50" oninput="calcAll()">
                </div>
                <div class="input-group">
                    <label>Margem de falha (%)</label>
                    <input type="number" id="failMargin" value="10" oninput="calcAll()">
                </div>
                <div class="input-group advanced-only">
                    <label>Densidade (g/cm3)</label>
                    <input type="number" id="density" value="1.24" step="0.01" oninput="calcAll()">
                </div>
                <div class="input-group advanced-only">
                    <label>Diametro do filamento (mm)</label>
                    <input type="number" id="diameter" value="1.75" step="0.01" oninput="calcAll()">
                </div>
            </div>

            <div class="result-grid">
                <div class="result-box">
                    <div class="result-label">Material</div>
                    <div class="result-val" id="totalCost">1,10 EUR</div>
                </div>
                <div class="result-box">
                    <div class="result-label">Filamento usado</div>
                    <div class="result-val" id="lengthEstimate">16 m</div>
                </div>
                <div class="result-box orange">
                    <div class="result-label">Total estimado</div>
                    <div class="result-val" id="grandTotal">1,23 EUR</div>
                </div>
            </div>
        </div>

        <div class="card">
            <h2 style="color:var(--accent)">Energia e parametros</h2>
            <div class="grid">
                <div class="input-group">
                    <label>Consumo da impressora (W)</label>
                    <input type="number" id="wattage" value="150" oninput="calcAll()">
                </div>
                <div class="input-group">
                    <label>Tempo de impressao (horas)</label>
                    <input type="number" id="hours" value="4" oninput="calcAll()">
                </div>
                <div class="input-group">
                    <label>Preco kWh (EUR)</label>
                    <input type="number" id="kwhPrice" value="0.22" step="0.001" oninput="calcAll()">
                </div>
            </div>
            <p class="hint advanced-only" id="tempHint"></p>
            <div class="result-box orange" style="margin-top:20px">
                <div class="result-label">Eletricidade</div>
                <div class="result-val" id="energyCost">0,13 EUR</div>
            </div>
        </div>
    </div>

    <script>
        const presets = {
            PLA:   { price: 20, density: 1.24, failB: 10, failA: 6,  watts: 120, temp: '190-220 C / cama 50-60 C', note: 'Mais simples para comecar. Ideal para aulas, prototipos e pecas decorativas.' },
            PETG:  { price: 25, density: 1.27, failB: 12, failA: 8,  watts: 145, temp: '230-250 C / cama 70-85 C', note: 'Boa escolha para pecas mais resistentes. Pode criar fios se a retracao nao estiver afinada.' },
            ABS:   { price: 24, density: 1.04, failB: 22, failA: 14, watts: 190, temp: '230-250 C / cama 95-110 C / camara recomendada', note: 'Mais dificil. Requer ventilacao e controlo de temperatura para reduzir warping.' },
            ASA:   { price: 30, density: 1.07, failB: 20, failA: 12, watts: 190, temp: '240-260 C / cama 95-110 C / camara recomendada', note: 'Semelhante ao ABS, mas melhor para exterior e resistencia UV.' },
            TPU:   { price: 35, density: 1.21, failB: 18, failA: 10, watts: 135, temp: '220-240 C / cama 40-60 C / velocidade baixa', note: 'Flexivel. Funciona melhor com extrusora direct drive e velocidades reduzidas.' },
            NYLON: { price: 45, density: 1.14, failB: 28, failA: 16, watts: 210, temp: '250-280 C / cama 80-100 C / secagem obrigatoria', note: 'Material tecnico. Absorve humidade e deve ser seco antes da impressao.' },
            PACF:  { price: 70, density: 1.20, failB: 26, failA: 14, watts: 220, temp: '260-290 C / bico endurecido / secagem obrigatoria', note: 'Rigido e leve, mas abrasivo. Usa bico hardened steel.' },
            PEEK:  { price: 150, density: 1.30, failB: 35, failA: 20, watts: 420, temp: '380-420 C / cama 120 C+ / camara aquecida', note: 'Industrial. So faz sentido em impressoras preparadas para alta temperatura.' }
        };

        let currentMode = 'beginner';

        function eur(value) {
            return value.toLocaleString('pt-PT', { style: 'currency', currency: 'EUR' });
        }

        function setMode(mode) {
            currentMode = mode;
            document.body.classList.toggle('mode-advanced', mode === 'advanced');
            document.getElementById('modeBeginner').classList.toggle('active', mode === 'beginner');
            document.getElementById('modeAdvanced').classList.toggle('active', mode === 'advanced');
            applyFilamentPreset();
        }

        function applyFilamentPreset() {
            const key = document.getElementById('filamentType').value;
            const p = presets[key];
            document.getElementById('bobinePrice').value = p.price;
            document.getElementById('density').value = p.density;
            document.getElementById('failMargin').value = currentMode === 'advanced' ? p.failA : p.failB;
            document.getElementById('wattage').value = p.watts;
            document.getElementById('filamentNote').textContent = p.note;
            document.getElementById('tempHint').textContent = 'Perfil recomendado: ' + p.temp;
            calcAll();
        }

        function calcAll() {
            const price = parseFloat(document.getElementById('bobinePrice').value) || 0;
            const totalWeight = parseFloat(document.getElementById('bobineWeight').value) || 1;
            const pieceWeight = parseFloat(document.getElementById('pieceWeight').value) || 0;
            const margin = parseFloat(document.getElementById('failMargin').value) || 0;
            const density = parseFloat(document.getElementById('density').value) || 1.24;
            const diameterMm = parseFloat(document.getElementById('diameter').value) || 1.75;
            const watts = parseFloat(document.getElementById('wattage').value) || 0;
            const hours = parseFloat(document.getElementById('hours').value) || 0;
            const kwhPrice = parseFloat(document.getElementById('kwhPrice').value) || 0;

            const costPerGram = price / totalWeight;
            const baseCost = pieceWeight * costPerGram;
            const materialCost = baseCost + (baseCost * (margin / 100));
            const energyCost = ((watts * hours) / 1000) * kwhPrice;

            const volumeCm3 = pieceWeight / density;
            const radiusCm = (diameterMm / 10) / 2;
            const areaCm2 = Math.PI * radiusCm * radiusCm;
            const lengthMeters = areaCm2 > 0 ? (volumeCm3 / areaCm2) / 100 : 0;

            document.getElementById('totalCost').innerText = eur(materialCost);
            document.getElementById('energyCost').innerText = eur(energyCost);
            document.getElementById('grandTotal').innerText = eur(materialCost + energyCost);
            document.getElementById('lengthEstimate').innerText = lengthMeters.toLocaleString('pt-PT', { maximumFractionDigits: 1 }) + ' m';
        }

        applyFilamentPreset();
    </script>
</body>
</html>
