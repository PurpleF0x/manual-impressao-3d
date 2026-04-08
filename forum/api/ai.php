<?php
/**
 * api/ai.php — Endpoint da IA (Groq)
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
    'manual' => 'És um assistente especializado em impressão 3D chamado "Print AI". 
Falas sempre em português de Portugal.
Ajudas utilizadores do Manual de Impressão 3D com:
- Dúvidas técnicas sobre impressão 3D (FDM, SLA, SLS, etc.)
- Recomendação de materiais e filamentos (PLA, PETG, ABS, TPU, etc.)
- Diagnóstico e resolução de problemas de impressão (stringing, warping, layer adhesion, etc.)
- Configurações de slicer (Cura, PrusaSlicer, etc.)
- Escolha e manutenção de impressoras
- Projetos e design para impressão 3D

Sê direto, técnico mas acessível. Usa formatação markdown quando útil (listas, negrito).
Mantém respostas concisas (máx 300 palavras) salvo se a pergunta exigir detalhe.
Não respondas a perguntas fora do tema de impressão 3D.',

    'forum' => 'És um assistente do Fórum de Impressão 3D chamado "Forum AI".
Falas sempre em português de Portugal.
Ajudas utilizadores com:
- Problemas técnicos ao publicar posts ou criar comunidades
- Dúvidas sobre regras e funcionamento do fórum
- Reportar problemas aos administradores
- Orientação sobre como usar as funcionalidades do fórum (flairs, comunidades, mensagens privadas, etc.)
- Mediar conflitos ou dúvidas sobre moderação

Se o utilizador precisar de contactar um administrador, diz-lhe que pode enviar uma mensagem privada a qualquer utilizador com o papel de "admin" ou usar o sistema de reports.
Sê amigável, claro e objetivo. Máx 200 palavras por resposta.
Não respondas a perguntas fora do tema do fórum ou impressão 3D.'
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

// Chamada à API Groq
$payload = json_encode(array(
    'model'       => GROQ_MODEL,
    'messages'    => $messages,
    'max_tokens'  => 600,
    'temperature' => 0.7,
    'stream'      => false,
));

$ch = curl_init(GROQ_API_URL);
curl_setopt_array($ch, array(
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => array(
        'Content-Type: application/json',
        'Authorization: Bearer ' . GROQ_API_KEY,
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

$data = json_decode($response, true);

if ($httpCode !== 200 || !isset($data['choices'][0]['message']['content'])) {
    $errMsg = $data['error']['message'] ?? 'Erro desconhecido da API.';
    echo json_encode(array('success' => false, 'error' => $errMsg));
    exit;
}

$reply = $data['choices'][0]['message']['content'];
echo json_encode(array(
    'success' => true,
    'reply'   => $reply,
    'tokens'  => $data['usage']['total_tokens'] ?? 0,
));