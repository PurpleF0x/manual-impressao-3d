<?php
/**
 * api/ai.php — Endpoint da IA (Grok xAI)
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

// ── ELIMINAR CONVERSA ─────────────────────────────────────────
if ($action === 'delete') {
    if (!verifyCSRFToken($input['csrf_token'] ?? null)) {
        echo json_encode(['success' => false, 'error' => 'Token inválido.']); exit;
    }
    $currentUser = isLoggedIn() ? getCurrentUser() : null;
    if (!$currentUser) { echo json_encode(['success' => false, 'error' => 'Sessão expirada.']); exit; }
    $db = getDB();
    $convId = (int)($input['conversation_id'] ?? 0);
    $st = $db->prepare("SELECT id FROM ai_conversations WHERE id=? AND user_id=? LIMIT 1");
    $st->execute([$convId, (int)$currentUser['id']]);
    if (!$st->fetch()) { echo json_encode(['success' => false, 'error' => 'Conversa não encontrada.']); exit; }
    $db->prepare("DELETE FROM ai_conversations WHERE id=?")->execute([$convId]);
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

    $systemPrompt = "Tu és o Print AI, o assistente digital oficial do Manual de Impressão 3D. Fala em PT-PT. {$levelDesc}{$sectionDesc}";

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
    $prompts = [
        'manual' => "Tu és o Print AI, assistente do Manual de Impressão 3D. Fala em PT-PT. Sê direto e técnico.",
        'forum'  => "Tu és o Forum AI, assistente do Fórum. Fala em PT-PT. Sê amigável."
    ];
    $systemPrompt = $prompts[$mode] ?? $prompts['manual'];

    $messages = [['role' => 'system', 'content' => $systemPrompt]];
    foreach (array_slice($history, -6) as $h) {
        if (isset($h['role'], $h['content'])) {
            $messages[] = ['role' => $h['role'], 'content' => $h['content']];
        }
    }
    $messages[] = ['role' => 'user', 'content' => $message];
}

// ── CHAMADA À API GROK ────────────────────────────────────────
if (empty(GROK_API_KEY)) {
    echo json_encode(['success' => false, 'error' => 'Configuração incompleta: GROK_API_KEY não encontrada no servidor.']);
    exit;
}

$payload = json_encode([
    'model'    => GROK_MODEL,
    'messages' => $messages,
    'temperature' => 0.7
]);

// LOG DE DEBUG PARA O PAYLOAD
error_log("Grok Payload: " . $payload);

$ch = curl_init(GROK_API_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . GROK_API_KEY
    ],
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => false,
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

    error_log("Grok API Error ($httpCode): " . print_r($data, true));

    echo json_encode([
        'success' => false,
        'error' => "Erro ($httpCode): $err",
        'debug' => (empty(GROK_API_KEY)) ? 'API Key em falta' : 'API Key configurada'
    ]);
    exit;
}

$reply = trim($data['choices'][0]['message']['content']);

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
