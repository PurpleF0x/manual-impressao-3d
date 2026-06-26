<?php
require_once 'includes/functions.php';
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calculadora de Impressão 3D — Manual 3D</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0a0a0f; --surface: #111118; --surface2: #1a1a26;
            --accent: #00e5ff; --accent2: #ff6b35; --text: #e8e8f0; --muted: #888899;
        }
        body { background: var(--bg); color: var(--text); font-family: 'Inter', sans-serif; padding: 40px 20px; line-height: 1.6; }
        .container { max-width: 800px; margin: 0 auto; }
        .back-link { display: inline-block; margin-bottom: 20px; color: var(--accent); text-decoration: none; font-size: 14px; }
        h1 { font-family: 'Syne', sans-serif; font-size: 32px; color: #fff; margin-bottom: 30px; }
        .card { background: var(--surface); border: 1px solid rgba(0,229,255,0.1); border-radius: 20px; padding: 30px; margin-bottom: 24px; box-shadow: 0 10px 40px rgba(0,0,0,0.5); }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .input-group { margin-bottom: 15px; }
        label { display: block; font-size: 12px; color: var(--muted); margin-bottom: 8px; text-transform: uppercase; font-family: 'Space Mono', monospace; }
        input { width: 100%; background: var(--surface2); border: 1px solid rgba(255,255,255,0.1); padding: 12px; border-radius: 8px; color: #fff; font-size: 14px; box-sizing: border-box; }
        .result-box { background: rgba(0,229,255,0.05); border: 1px solid var(--accent); border-radius: 12px; padding: 20px; margin-top: 20px; text-align: center; }
        .result-val { font-size: 28px; font-family: 'Syne', sans-serif; color: var(--accent); font-weight: 800; }
        .result-label { font-size: 12px; color: var(--muted); margin-bottom: 5px; }
        @media (max-width: 600px) { .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="back-link">← Voltar ao Manual</a>
        <h1>Calculadoras <span>Maker</span></h1>

        <!-- Calculadora de Custo de Filamento -->
        <div class="card">
            <h2 style="margin-top:0; color:var(--accent2); font-size:18px; margin-bottom:20px;">💰 Calculadora de Custo de Material</h2>
            <div class="grid">
                <div class="input-group">
                    <label>Preço da Bobine (€)</label>
                    <input type="number" id="bobinePrice" value="20" step="0.01" oninput="calcCost()">
                </div>
                <div class="input-group">
                    <label>Peso da Bobine (g)</label>
                    <input type="number" id="bobineWeight" value="1000" oninput="calcCost()">
                </div>
                <div class="input-group">
                    <label>Peso da Peça (g)</label>
                    <input type="number" id="pieceWeight" value="50" oninput="calcCost()">
                </div>
                <div class="input-group">
                    <label>Margem de Erro/Falha (%)</label>
                    <input type="number" id="failMargin" value="10" oninput="calcCost()">
                </div>
            </div>
            <div class="result-box">
                <div class="result-label">Custo Estimado do Material</div>
                <div class="result-val" id="totalCost">1,10 €</div>
            </div>
        </div>

        <!-- Calculadora de Consumo Elétrico -->
        <div class="card">
            <h2 style="margin-top:0; color:var(--accent); font-size:18px; margin-bottom:20px;">⚡ Estimativa de Energia</h2>
            <div class="grid">
                <div class="input-group">
                    <label>Consumo Impressora (Watts)</label>
                    <input type="number" id="wattage" value="150" oninput="calcEnergy()">
                </div>
                <div class="input-group">
                    <label>Tempo de Impressão (Horas)</label>
                    <input type="number" id="hours" value="4" oninput="calcEnergy()">
                </div>
                <div class="input-group">
                    <label>Preço KWh em Portugal (€)</label>
                    <input type="number" id="kwhPrice" value="0.22" step="0.001" oninput="calcEnergy()">
                </div>
            </div>
            <div class="result-box" style="border-color: var(--accent2);">
                <div class="result-label">Custo de Eletricidade</div>
                <div class="result-val" id="energyCost" style="color: var(--accent2);">0,13 €</div>
            </div>
        </div>
    </div>

    <script>
        function calcCost() {
            let price = parseFloat(document.getElementById('bobinePrice').value) || 0;
            let totalWeight = parseFloat(document.getElementById('bobineWeight').value) || 1;
            let pieceWeight = parseFloat(document.getElementById('pieceWeight').value) || 0;
            let margin = parseFloat(document.getElementById('failMargin').value) || 0;

            let costPerGram = price / totalWeight;
            let baseCost = pieceWeight * costPerGram;
            let total = baseCost + (baseCost * (margin/100));

            document.getElementById('totalCost').innerText = total.toLocaleString('pt-PT', { style: 'currency', currency: 'EUR' });
        }

        function calcEnergy() {
            let watts = parseFloat(document.getElementById('wattage').value) || 0;
            let hours = parseFloat(document.getElementById('hours').value) || 0;
            let price = parseFloat(document.getElementById('kwhPrice').value) || 0;

            // Watts -> KWh
            let kwh = (watts * hours) / 1000;
            let total = kwh * price;

            document.getElementById('energyCost').innerText = total.toLocaleString('pt-PT', { style: 'currency', currency: 'EUR' });
        }
    </script>
</body>
</html>
