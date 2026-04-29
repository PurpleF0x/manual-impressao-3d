<?php
/**
 * api/ai_chat.php — Endpoint da IA principal (Grok xAI)
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/ai_config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success'=>false,'error'=>'Método inválido.']); exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

if (!verifyCSRFToken($input['csrf_token'] ?? null)) {
    echo json_encode(['success'=>false,'error'=>'Sessão expirada. Recarrega a página.']); exit;
}

$db          = getDB();
$currentUser = isLoggedIn() ? getCurrentUser() : null;
$message     = trim($input['message'] ?? '');
$convId      = (int)($input['conversation_id'] ?? 0);
$section     = trim($input['section'] ?? '');
$mode        = in_array($input['ai_mode'] ?? '', ['beginner','advanced']) ? $input['ai_mode'] : 'beginner';

if (!$message) { echo json_encode(['success'=>false,'error'=>'Mensagem vazia.']); exit; }

// Contexto e Histórico
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
        $st->execute([(int)$currentUser['id'], 'Nova conversa', $section ?: null, $mode]);
        $convId = (int)$db->lastInsertId();
    }
}

$history = [];
if ($currentUser && $convId) {
    $st = $db->prepare("SELECT role, content FROM ai_messages WHERE conversation_id=? ORDER BY created_at ASC");
    $st->execute([$convId]);
    $history = $st->fetchAll(PDO::FETCH_ASSOC);
}

$systemPrompt = "Tu és o Print AI, o assistente oficial do Manual de Impressão 3D. Fala em PT-PT. Modo: " . ($mode === 'advanced' ? 'Técnico' : 'Simples');

$messages = [['role' => 'system', 'content' => $systemPrompt]];
foreach (array_slice($history, -10) as $h) {
    $messages[] = ['role' => $h['role'], 'content' => $h['content']];
}
$messages[] = ['role' => 'user', 'content' => $message];

// Chamada Grok
$payload = json_encode([
    'model' => GROK_MODEL,
    'messages' => $messages,
    'temperature' => 0.7
]);

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
curl_close($ch);

$data = json_decode($response, true);
if ($httpCode !== 200 || !isset($data['choices'][0]['message']['content'])) {
    echo json_encode(['success' => false, 'error' => 'Erro na API Grok ('.$httpCode.')']); exit;
}

$reply = trim($data['choices'][0]['message']['content']);

if ($currentUser && $convId) {
    $db->prepare("INSERT INTO ai_messages (conversation_id, role, content) VALUES (?,?,?)")->execute([$convId, 'user', $message]);
    $db->prepare("INSERT INTO ai_messages (conversation_id, role, content) VALUES (?,?,?)")->execute([$convId, 'assistant', $reply]);
    if ($isNewConv) {
        $title = mb_substr($message, 0, 50) . '...';
        $db->prepare("UPDATE ai_conversations SET title=? WHERE id=?")->execute([$title, $convId]);
    }
}

echo json_encode(['success' => true, 'reply' => $reply, 'conversation_id' => $convId]);
