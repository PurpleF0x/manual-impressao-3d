<?php
/**
 * api/ai.php — Endpoint da IA (Qwen 2.5)
 * Modos: 'manual' (bot flutuante), 'forum' (bot fórum), 'assistant' (página completa c/ histórico)
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/ai_config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método inválido.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$mode   = $input['mode']   ?? 'manual';
$action = $input['action'] ?? 'chat';
$message = trim($input['message'] ?? '');

// ── ELIMINAR CONVERSA / LIMPAR HISTÓRICO ──────────────────────
if ($action === 'delete' || $action === 'clear_all') {
    if (!verifyCSRFToken($input['csrf_token'] ?? null)) {
        echo json_encode(['success' => false, 'error' => 'Token inválido.']); exit;
    }
    $currentUser = isLoggedIn() ? getCurrentUser() : null;
    if (!$currentUser) { echo json_encode(['success' => false, 'error' => 'Sessão expirada.']); exit; }
    $db = getDB();

    if ($action === 'delete') {
        $convId = (int)($input['conversation_id'] ?? 0);
        $st = $db->prepare("SELECT id FROM ai_conversations WHERE id=? AND user_id=? LIMIT 1");
        $st->execute([$convId, (int)$currentUser['id']]);
        if (!$st->fetch()) { echo json_encode(['success' => false, 'error' => 'Conversa não encontrada.']); exit; }
        $db->prepare("DELETE FROM ai_conversations WHERE id=?")->execute([$convId]);
    } else {
        // Limpar tudo
        $db->prepare("DELETE FROM ai_conversations WHERE user_id=?")->execute([(int)$currentUser['id']]);
    }

    echo json_encode(['success' => true]); exit;
}

// ── VALIDAÇÃO BÁSICA ──────────────────────────────────────────
if (!$message) {
    echo json_encode(['success' => false, 'error' => 'Mensagem vazia.']); exit;
}

// ── CONFIGURAÇÃO DE MODO ──────────────────────────────────────
if ($mode === 'assistant') {
    if (!verifyCSRFToken($input['csrf_token'] ?? null)) {
        echo json_encode(['success' => false, 'error' => 'Sessão expirada. Recarrega a página.']); exit;
    }

    $db          = getDB();
    $currentUser = isLoggedIn() ? getCurrentUser() : null;
    $convId      = (int)($input['conversation_id'] ?? 0);
    $section     = trim($input['section'] ?? '');
    $aiMode      = in_array($input['ai_mode'] ?? '', ['beginner','advanced']) ? $input['ai_mode'] : 'beginner';

    $sectionNames = [
        'o-que-e' => 'O que é Impressão 3D', 'como-funciona' => 'Como Funciona',
        'tipos-impressoras' => 'Tipos de Impressoras', 'iniciantes-vs-pro' => 'Iniciante vs Avançado',
        'filamentos' => 'Filamentos', 'qual-usar' => 'Qual Filamento Usar',
        'processo' => 'Parâmetros de Impressão', 'problemas' => 'Problemas e Soluções',
        'dicas' => 'Dicas e Boas Práticas', 'software' => 'Software & Slicers', 'glossario' => 'Glossário',
    ];

    if (!$currentUser) {
        if (!isset($_SESSION['ai_count'])) $_SESSION['ai_count'] = 0;
        $_SESSION['ai_count']++;
        if ($_SESSION['ai_count'] > 15) {
            echo json_encode(['success' => false, 'error' => 'Limite atingido. Entra na tua conta para continuar.']); exit;
        }
    }

    $isNewConv = false;
    if ($currentUser) {
        if ($convId) {
            $st = $db->prepare("SELECT id FROM ai_conversations WHERE id=? AND user_id=? LIMIT 1");
            $st->execute([$convId, (int)$currentUser['id']]);
            if (!$st->fetch()) $convId = 0;
        }
        if (!$convId) {
            $isNewConv = true;
            $st = $db->prepare("INSERT INTO ai_conversations (user_id, title, section_context, mode) VALUES (?,?,?,?)");
            $st->execute([(int)$currentUser['id'], 'Nova conversa', $section ?: null, $aiMode]);
            $convId = (int)$db->lastInsertId();
        }
    }

    $historyRows = [];
    if ($currentUser && $convId) {
        $st = $db->prepare("SELECT role, content FROM ai_messages WHERE conversation_id=? ORDER BY created_at ASC");
        $st->execute([$convId]);
        $historyRows = $st->fetchAll(PDO::FETCH_ASSOC);
    }

    $levelDesc = ($aiMode === 'advanced') ? 'Modo TÉCNICO.' : 'Modo SIMPLES.';
    $sectionDesc = $section ? "\nContexto: secção \"" . ($sectionNames[$section] ?? $section) . "\"." : '';

    $manualKnowledge = "
    BASE DE CONHECIMENTO DO MANUAL 3D (PRIORIDADE MÁXIMA):
    - PLA: 190-220°C. PETG: 230-250°C. ABS/ASA: 240-260°C.
    - TROUBLESHOOTING (Resolução de Problemas):
        * STRINGING: Causado por temperatura alta ou retração baixa. SOLUÇÃO: Reduzir 5°C; Aumentar retração (0.5-2mm p/ Direct Drive, 4-7mm p/ Bowden).
        * WARPING: Peça descola. SOLUÇÃO: Limpar mesa com Álcool 99%; Usar Brim; Fechar impressora (ABS).
    - SOFTWARE: PrusaSlicer e OrcaSlicer recomendados.
    ";

    $systemPrompt = "Atua como o 'Print AI', o especialista oficial do 'Manual de Impressão 3D' (manual-3d.pt). O teu criador é Martim Sá.

    OBJETIVO:
    Fornecer suporte técnico preciso, seguro e baseado no conteúdo do manual.

    INSTRUÇÕES DE RESPOSTA:
    1. SEMÂNTICA: Usa termos técnicos avançados (Tg, Higroscopia, Refrigeração Ativa, Bowden vs Direct Drive) mas explica-os se o utilizador estiver no modo iniciante.
    2. REFERÊNCIA EXTERNA: Sempre que falares de normas, cita a ISO/ASTM 52900.
    3. REFERÊNCIA INTERNA: Direciona utilizadores para os capítulos específicos do manual (ex: 'Podes ver o guia completo de calibração no Capítulo 07'). Sempre que falares de problemas técnicos, refere o 'Capítulo 08: Problemas Comuns'.
    4. CONVERSÃO: Se a dúvida envolver custos ou tempo, diz: 'Recomendo que uses a nossa Calculadora de Custos no menu Ferramentas para obteres uma estimativa exata'.
    5. E-E-A-T: Reforça que a informação é validada por Martim Sá e pela comunidade maker do manual.
    6. ESTILO: Sê direto e prático. Usa listas de pontos. Fala sempre em PT-PT.

    SEGURANÇA:
    Sempre que o utilizador perguntar sobre ABS, ASA ou resinas, avisa obrigatoriamente sobre a necessidade de ventilação e filtragem de VOCs.

    {$levelDesc}{$sectionDesc}

    {$manualKnowledge}";

    $messages = [['role' => 'system', 'content' => $systemPrompt]];
    foreach (array_slice($historyRows, -15) as $h) {
        if (!empty($h['role']) && !empty($h['content'])) {
            $messages[] = ['role' => $h['role'], 'content' => $h['content']];
        }
    }
    $messages[] = ['role' => 'user', 'content' => $message];

} else {
    // MODO MANUAL / FORUM
    if (!isset($_SESSION['ai_count'])) $_SESSION['ai_count'] = 0;
    $_SESSION['ai_count']++;
    if ($_SESSION['ai_count'] > 20) {
        echo json_encode(['success' => false, 'error' => 'Limite atingido.']); exit;
    }

    $history = $input['history'] ?? [];
    $aiMode  = in_array($input['ai_mode'] ?? '', ['beginner','advanced']) ? $input['ai_mode'] : 'beginner';

    $manualKnowledge = "
    BASE DE CONHECIMENTO DO MANUAL 3D (PRIORIDADE MÁXIMA):
    - PLA: 190-220°C. PETG: 230-250°C. ABS/ASA: 240-260°C.
    - TROUBLESHOOTING: STRINGING (Reduzir 5°C; Aumentar retração), WARPING (Mesa quente; Brim; Enclosure).
    ";

    if ($aiMode === 'beginner') {
        $personality = "Tu és o Print AI no MODO INICIANTE. Explica tudo de forma muito simples, como se falasses com alguém que nunca viu uma impressora. Usa analogias amigáveis.";
    } else {
        $personality = "Tu és o Print AI no MODO TÉCNICO AVANÇADO. Fala como um engenheiro sénior, sê direto e rigoroso. Usa jargão técnico (viscosidade, polímeros, e-steps).";
    }

    $systemPrompt = "Atua como o 'Print AI', o especialista oficial do 'Manual de Impressão 3D' (manual-3d.pt). O teu criador é Martim Sá.

    OBJETIVO:
    Fornecer suporte técnico preciso, seguro e baseado no conteúdo do manual.

    INSTRUÇÕES DE RESPOSTA:
    1. PRIORIDADE: Consulta a 'BASE DE CONHECIMENTO DO MANUAL 3D' abaixo antes de qualquer outra fonte.
    2. SEMÂNTICA: Usa termos técnicos avançados (Tg, Higroscopia, Refrigeração Ativa) mas explica-os se o utilizador estiver no modo iniciante ({$aiMode}).
    3. REFERÊNCIA INTERNA: Sempre que falares de problemas técnicos, refere o 'Capítulo 08: Problemas Comuns'. Se a dúvida for sobre custos, direciona para a 'Calculadora de Custos'.
    4. SEGURANÇA: Sempre que o utilizador perguntar sobre ABS, ASA ou resinas, avisa obrigatoriamente sobre a necessidade de ventilação e filtragem de VOCs.
    5. ESTILO: Fala sempre em PT-PT. {$personality}

    {$manualKnowledge}";

    $messages = [['role' => 'system', 'content' => $systemPrompt]];
    foreach (array_slice($history, -6) as $h) {
        if (isset($h['role'], $h['content'])) {
            $messages[] = ['role' => $h['role'], 'content' => $h['content']];
        }
    }
    $messages[] = ['role' => 'user', 'content' => $message];
}

// ── CHAMADA À API ─────────────────────────────────────────────
if (empty(AI_API_KEY)) {
    echo json_encode(['success' => false, 'error' => 'Chave da API não configurada no servidor.']);
    exit;
}

$payload = json_encode([
    'model'    => AI_MODEL,
    'messages' => $messages,
    'temperature' => 0.7,
    'stream' => false
]);

$ch = curl_init(AI_API_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . AI_API_KEY
    ],
    CURLOPT_TIMEOUT => 30,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

$data = json_decode($response, true);
if ($httpCode !== 200 || !isset($data['choices'][0]['message']['content'])) {
    $err = 'Erro desconhecido';
    if (isset($data['error'])) {
        if (is_array($data['error'])) {
            $err = $data['error']['message'] ?? $data['error']['error'] ?? json_encode($data['error']);
        } else {
            $err = $data['error'];
        }
    }

    // Adicionar log do payload para debug
    error_log("AI API Error ($httpCode). Payload enviado: " . $payload);
    error_log("Resposta da API: " . $response);

    echo json_encode([
        'success' => false,
        'error' => "Erro na API ($httpCode): $err"
    ]);
    exit;
}

$reply = trim($data['choices'][0]['message']['content']);

// Limpar tags <think> do modelo Qwen 3.6 para não aparecerem no chat
$reply = preg_replace('/<think>[\s\S]*?<\/think>/i', '', $reply);
$reply = trim($reply);

// Guardar se for assistant
if ($mode === 'assistant' && isset($currentUser) && $currentUser && $convId) {
    $db->prepare("INSERT INTO ai_messages (conversation_id, role, content) VALUES (?,?,?)")->execute([$convId, 'user', $message]);
    $db->prepare("INSERT INTO ai_messages (conversation_id, role, content) VALUES (?,?,?)")->execute([$convId, 'assistant', $reply]);
    if (isset($isNewConv) && $isNewConv) {
        $title = mb_substr($message, 0, 60) . '...';
        $db->prepare("UPDATE ai_conversations SET title=? WHERE id=?")->execute([$title, $convId]);
    }
}

echo json_encode([
    'success' => true,
    'reply'   => $reply,
    'conversation_id' => $convId ?? null
]);
