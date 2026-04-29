<?php
/**
 * forum/api/ai.php — Endpoint da IA para o Fórum (Grok xAI)
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/ai_config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método inválido.']); exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

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

$systemPrompt = "Tu és o Forum AI, o assistente oficial do Fórum de Impressão 3D. Especialista em gestão de comunidades. Fala em PT-PT.";

$messages = [['role' => 'system', 'content' => $systemPrompt]];
foreach (array_slice($history, -6) as $h) {
    if (isset($h['role'], $h['content'])) {
        $messages[] = ['role' => $h['role'], 'content' => $h['content']];
    }
}
$messages[] = ['role' => 'user', 'content' => $message];

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

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($response, true);
if ($httpCode !== 200 || !isset($data['choices'][0]['message']['content'])) {
    $err = $data['error']['message'] ?? 'Erro na API Grok.';
    echo json_encode(['success' => false, 'error' => "Erro ($httpCode): $err"]); exit;
}

echo json_encode([
    'success' => true,
    'reply'   => trim($data['choices'][0]['message']['content'])
]);
