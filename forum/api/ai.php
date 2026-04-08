<?php
/**
 * api/ai.php — Endpoint da IA (Gemini via OpenAI-compatible endpoint)
 * Usado pelo manual e pelo fórum
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/ai_config.php';

header('Content-Type: application/json; charset=utf-8');

// Apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('success' => false, 'error' => 'Método inválido.'));
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

$mode    = $input['mode']    ?? 'manual'; // 'manual' ou 'forum'
$message = trim($input['message'] ?? '');
$history = $input['history'] ?? array(); // array de {role, content}

if (!$message) {
    echo json_encode(array('success' => false, 'error' => 'Mensagem vazia.'));
    exit;
}

// Rate limit simples por sessão (máx 20 mensagens por sessão)
if (!isset($_SESSION['ai_count'])) $_SESSION['ai_count'] = 0;
$_SESSION['ai_count']++;
if ($_SESSION['ai_count'] > 20) {
    echo json_encode(array('success' => false, 'error' => 'Limite de mensagens atingido para esta sessão. Recarrega a página para continuar.'));
    exit;
}

// System prompts
$systemPrompts = array(
    'manual' => "Tu és o Print AI, o assistente digital especializado do Manual de Impressão 3D.

REGRAS DE PERSONALIDADE:
1. Especialista em impressão 3D (FDM, SLA, SLS, materiais, slicers).
2. Falas sempre em português de Portugal.
3. Sê direto, técnico mas acessível.

PROTOCOLOS:
- Diagnóstico de problemas (stringing, warping, etc.).
- Recomendações de hardware e software.
- LIMITE: Máximo 300 palavras.

ÂMBITO: Apenas temas relacionados com impressão 3D.",

    'forum' => "Tu és o Forum AI, o assistente oficial do Fórum de Impressão 3D.

REGRAS DE PERSONALIDADE:
1. Especialista em gestão de comunidades e suporte ao utilizador.
2. Falas sempre em português de Portugal.
3. Sê amigável, claro e objetivo.

PROTOCOLOS:
- Suporte técnico sobre posts, comunidades, flairs e moderação.
- Encaminhamento: Para admins ou sistema de reports se necessário.
- LIMITE: Máximo 200 palavras.

ÂMBITO: Apenas temas relacionados com o fórum e impressão 3D."
);

$systemPrompt = $systemPrompts[$mode] ?? $systemPrompts['manual'];

// Construir mensagens para a API
$messages = array(array('role' => 'system', 'content' => $systemPrompt));

// Adicionar histórico (máx últimas 6 mensagens para não gastar tokens)
$recentHistory = array_slice($history, -6);
foreach ($recentHistory as $h) {
    if (isset($h['role'], $h['content']) && in_array($h['role'], array('user','assistant'))) {
        $messages[] = array('role' => $h['role'], 'content' => mb_substr($h['content'], 0, 500));
    }
}

// Mensagem atual
$messages[] = array('role' => 'user', 'content' => mb_substr($message, 0, 1000));

// Chamada à API GEMINI (via OpenAI-compatible endpoint)
$payload = json_encode(array(
    'model'       => GEMINI_MODEL,
    'messages'    => $messages,
    'max_tokens'  => 600,
    'temperature' => 0.5,
    'stream'      => false,
));

$ch = curl_init(GEMINI_API_URL);
curl_setopt_array($ch, array(
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => array(
        'Content-Type: application/json',
        'Authorization: Bearer ' . GEMINI_API_KEY,
    ),
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => false,
));

$response = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo json_encode(array('success' => false, 'error' => 'Erro de rede: ' . $curlError));
    exit;
}

if (empty(GEMINI_API_KEY)) {
    echo json_encode(['success' => false, 'error' => 'Configuração: GEMINI_API_KEY não detetada no Render.']); exit;
}

$data = json_decode($response, true);

if ($httpCode !== 200 || !isset($data['choices'][0]['message']['content'])) {
    $errorDetail = $data['error']['message'] ?? null;
    if (!$errorDetail && isset($data['error']['code'])) {
        $errorDetail = "Erro Gemini ({$data['error']['code']}): " . ($data['error']['status'] ?? 'Verificar logs.');
    }
    if (!$errorDetail) $errorDetail = 'Erro inesperado da API (HTTP ' . $httpCode . ').';

    error_log("Erro Gemini API ($httpCode): " . $response);
    echo json_encode(['success' => false, 'error' => $errorDetail]); exit;
}

$reply = $data['choices'][0]['message']['content'];
echo json_encode(array(
    'success' => true,
    'reply'   => $reply,
    'tokens'  => $data['usage']['total_tokens'] ?? 0,
));