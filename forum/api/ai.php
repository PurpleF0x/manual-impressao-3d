<?php
/**
 * forum/api/ai.php — Endpoint da IA para o Fórum
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/ai_config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método inválido.']); exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

$message = trim($input['message'] ?? '');
$history = $input['history'] ?? [];

if (!$message) {
    echo json_encode(['success' => false, 'error' => 'Mensagem vazia.']); exit;
}

if (!isset($_SESSION['ai_count'])) $_SESSION['ai_count'] = 0;
$_SESSION['ai_count']++;
if ($_SESSION['ai_count'] > 20) {
    echo json_encode(['success' => false, 'error' => 'Limite atingido nesta sessão.']); exit;
}

$systemPrompt = "Tu és o Forum AI, o assistente oficial do Fórum de Impressão 3D.

REGRAS DE PERSONALIDADE:
1. Especialista em gestão de comunidades e suporte ao utilizador.
2. Falas sempre em português de Portugal (PT-PT).
3. Sê amigável, claro e objetivo.

PROTOCOLOS:
- Suporte técnico sobre posts, comunidades, flairs e moderação.
- ÂMBITO: Apenas temas relacionados com o fórum e impressão 3D.
- LIMITE: Máximo 250 palavras.";

$messages = [['role' => 'system', 'content' => $systemPrompt]];
foreach (array_slice($history, -6) as $h) {
    if (isset($h['role'], $h['content'])) {
        $messages[] = ['role' => $h['role'], 'content' => mb_substr($h['content'], 0, 500)];
    }
}
$messages[] = ['role' => 'user', 'content' => mb_substr($message, 0, 1000)];

$payload = json_encode([
    'model'       => GEMINI_MODEL,
    'messages'    => $messages,
    'max_tokens'  => 600,
    'temperature' => 0.5,
    'stream'      => false,
]);

$ch = curl_init(GEMINI_API_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . GEMINI_API_KEY
    ],
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => false,
]);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo json_encode(['success' => false, 'error' => 'Erro de rede: ' . $curlError]); exit;
}

$data = json_decode($response, true);
if ($httpCode !== 200 || !isset($data['choices'][0]['message']['content'])) {
    $errorDetail = $data['error']['message'] ?? null;
    if (!$errorDetail) {
        $errorDetail = 'Erro HTTP ' . $httpCode . ': ' . mb_substr(strip_tags($response), 0, 150);
    }
    echo json_encode(['success' => false, 'error' => $errorDetail]); exit;
}

echo json_encode([
    'success' => true,
    'reply'   => trim($data['choices'][0]['message']['content']),
    'tokens'  => $data['usage']['total_tokens'] ?? 0
]);
