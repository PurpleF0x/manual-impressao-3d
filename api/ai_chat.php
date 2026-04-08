<?php
/**
 * api/ai_chat.php — Endpoint da IA principal (com histórico persistente)
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/ai_config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success'=>false,'error'=>'Método inválido.']); exit;
}

$input = $_POST;
if (empty($input)) {
    // fallback para JSON (compatibilidade)
    $json = file_get_contents('php://input');
    if ($json) $input = json_decode($json, true) ?: [];
}

if (!verifyCSRFToken($input['csrf_token'] ?? null)) {
    // Token inválido pode significar sessão expirada
    $hasSession = isset($_SESSION['csrf_token']);
    $msg = $hasSession
        ? 'Token de segurança inválido. Recarrega a página.'
        : 'Sessão expirada. Recarrega a página e tenta novamente.';
    echo json_encode(['success'=>false,'error'=>$msg]); exit;
}

$db          = getDB();
$currentUser = isLoggedIn() ? getCurrentUser() : null;
$action      = $input['action'] ?? 'chat';

// ── ELIMINAR CONVERSA ─────────────────────────────────────────
if ($action === 'delete') {
    if (!$currentUser) { echo json_encode(['success'=>false,'error'=>'Sessão expirada.']); exit; }
    $convId = (int)($input['conversation_id'] ?? 0);
    if (!$convId) { echo json_encode(['success'=>false,'error'=>'ID inválido.']); exit; }
    $st = $db->prepare("SELECT id FROM ai_conversations WHERE id=? AND user_id=? LIMIT 1");
    $st->execute([$convId, (int)$currentUser['id']]);
    if (!$st->fetch()) { echo json_encode(['success'=>false,'error'=>'Conversa não encontrada.']); exit; }
    $db->prepare("DELETE FROM ai_conversations WHERE id=?")->execute([$convId]);
    echo json_encode(['success'=>true]); exit;
}

// ── CHAT ──────────────────────────────────────────────────────
$message = trim($input['message'] ?? '');
if (!$message) { echo json_encode(['success'=>false,'error'=>'Mensagem vazia.']); exit; }
if (mb_strlen($message) > 2000) {
    echo json_encode(['success'=>false,'error'=>'Mensagem demasiado longa (máx. 2000 caracteres).']); exit;
}

$convId  = (int)($input['conversation_id'] ?? 0);
$section = trim($input['section'] ?? '');
$mode    = in_array($input['mode'] ?? '', ['beginner','advanced']) ? $input['mode'] : 'beginner';

$validSections = ['o-que-e','como-funciona','tipos-impressoras','iniciantes-vs-pro',
                  'filamentos','qual-usar','processo','problemas','dicas','software','glossario'];
if (!in_array($section, $validSections)) $section = '';

$sectionNames = [
    'o-que-e'           => 'O que é Impressão 3D',
    'como-funciona'     => 'Como Funciona',
    'tipos-impressoras' => 'Tipos de Impressoras',
    'iniciantes-vs-pro' => 'Iniciante vs Avançado',
    'filamentos'        => 'Filamentos',
    'qual-usar'         => 'Qual Filamento Usar',
    'processo'          => 'Parâmetros de Impressão',
    'problemas'         => 'Problemas e Soluções',
    'dicas'             => 'Dicas e Boas Práticas',
    'software'          => 'Software & Slicers',
    'glossario'         => 'Glossário',
];

// ── Rate limit para utilizadores não autenticados ─────────────
if (!$currentUser) {
    if (!isset($_SESSION['ai_count'])) $_SESSION['ai_count'] = 0;
    $_SESSION['ai_count']++;
    if ($_SESSION['ai_count'] > 15) {
        echo json_encode(['success'=>false,'error'=>'Limite atingido. Entra na tua conta para continuar sem limites.']);
        exit;
    }
}

// ── Garantir/obter conversa ───────────────────────────────────
$isNewConv = false;
$convTitle = null;

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

// ── Carregar histórico ────────────────────────────────────────
$history = [];
if ($currentUser && $convId) {
    $st = $db->prepare("SELECT role, content FROM ai_messages WHERE conversation_id=? ORDER BY created_at ASC");
    $st->execute([$convId]);
    $history = $st->fetchAll(PDO::FETCH_ASSOC);
}

// ── System prompt ─────────────────────────────────────────────
$levelDesc = $mode === 'advanced'
    ? 'Modo AVANÇADO — usa linguagem técnica, parâmetros específicos, termos em inglês quando pertinente.'
    : 'Modo BÁSICO — usa linguagem acessível, analogias simples, evita jargão desnecessário.';

$sectionDesc = $section
    ? "\nContexto da conversa: secção do manual \"" . ($sectionNames[$section] ?? $section) . "\". Prioriza este tema quando relevante."
    : '';

$systemPrompt = "Tu és o Print AI, o assistente digital oficial do Manual de Impressão 3D.

REGRAS DE PERSONALIDADE:
1. Especialista em impressão 3D (FDM, SLA, SLS).
2. Falas sempre em português de Portugal (PT-PT).
3. Sê direto, técnico mas acessível. Usa markdown para clareza.

PROTOCOLOS TÉCNICOS:
- {$levelDesc}
- {$sectionDesc}
- LIMITE: Máximo 400 palavras.
- VERACIDADE: Nunca inventes informação.

ÂMBITO: Responde apenas sobre impressão 3D e temas diretamente relacionados.";

// ── Montar mensagens ──────────────────────────────────────────
$messages = [['role'=>'system','content'=>$systemPrompt]];
foreach (array_slice($history, -20) as $h) {
    if (isset($h['role'],$h['content']) && in_array($h['role'],['user','assistant'])) {
        $messages[] = ['role'=>$h['role'],'content'=>mb_substr($h['content'],0,800)];
    }
}
$messages[] = ['role'=>'user','content'=>mb_substr($message,0,2000)];

// ── Chamada à API GEMINI (via OpenAI-compatible endpoint) ────
$payload = json_encode([
    'model'       => GEMINI_MODEL,
    'messages'    => $messages,
    'max_tokens'  => 700,
    'temperature' => 0.5,
    'stream'      => false,
]);

$ch = curl_init(GEMINI_API_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json','Authorization: Bearer '.GEMINI_API_KEY],
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => false,
]);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) { echo json_encode(['success'=>false,'error'=>'Erro de rede: '.$curlError]); exit; }

if (empty(GEMINI_API_KEY)) {
    echo json_encode(['success' => false, 'error' => 'Configuração: GEMINI_API_KEY não definida no servidor Render.']); exit;
}

$data = json_decode($response, true);
if ($httpCode !== 200 || !isset($data['choices'][0]['message']['content'])) {
    $errorDetail = $data['error']['message'] ?? null;
    if (!$errorDetail && isset($data['error']['code'])) {
        $errorDetail = "Erro Gemini ({$data['error']['code']}): " . ($data['error']['status'] ?? 'Verificar log.');
    }
    if (!$errorDetail) $errorDetail = 'Erro inesperado da API (HTTP ' . $httpCode . ').';

    error_log("Erro Gemini API ($httpCode): " . $response);
    echo json_encode(['success' => false, 'error' => $errorDetail]); exit;
}

$reply = trim($data['choices'][0]['message']['content']);

// ── Guardar na BD ─────────────────────────────────────────────
if ($currentUser && $convId) {
    $ins = $db->prepare("INSERT INTO ai_messages (conversation_id, role, content) VALUES (?,?,?)");
    $ins->execute([$convId,'user',$message]);
    $ins->execute([$convId,'assistant',$reply]);
    $db->prepare("UPDATE ai_conversations SET updated_at=NOW(), mode=? WHERE id=?")->execute([$mode,$convId]);

    if ($isNewConv) {
        $convTitle = mb_substr($message,0,60) . (mb_strlen($message)>60?'…':'');
        $db->prepare("UPDATE ai_conversations SET title=? WHERE id=?")->execute([$convTitle,$convId]);
    }
}

$out = ['success'=>true,'reply'=>$reply];
if ($currentUser && $convId) {
    $out['conversation_id'] = $convId;
    if ($convTitle) $out['title'] = $convTitle;
}
echo json_encode($out);