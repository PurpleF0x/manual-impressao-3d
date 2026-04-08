<?php
/**
 * api/ai.php — Endpoint da IA (Groq)
 * Modos: 'manual' (bot flutuante), 'forum' (bot fórum), 'assistant' (página completa c/ histórico)
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/ai_config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método inválido.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

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

// ── VALIDAÇÃO ─────────────────────────────────────────────────
if (!$message) {
    echo json_encode(['success' => false, 'error' => 'Mensagem vazia.']); exit;
}

// ── MODO ASSISTANT ────────────────────────────────────────────
if ($mode === 'assistant') {

    if (!verifyCSRFToken($input['csrf_token'] ?? null)) {
        $msg = isset($_SESSION['csrf_token']) ? 'Token inválido. Recarrega a página.' : 'Sessão expirada. Recarrega a página.';
        echo json_encode(['success' => false, 'error' => $msg]); exit;
    }

    if (mb_strlen($message) > 2000) {
        echo json_encode(['success' => false, 'error' => 'Mensagem demasiado longa (máx. 2000 caracteres).']); exit;
    }

    $db          = getDB();
    $currentUser = isLoggedIn() ? getCurrentUser() : null;
    $convId      = (int)($input['conversation_id'] ?? 0);
    $section     = trim($input['section'] ?? '');
    $aiMode      = in_array($input['ai_mode'] ?? '', ['beginner','advanced']) ? $input['ai_mode'] : 'beginner';

    $validSections = ['o-que-e','como-funciona','tipos-impressoras','iniciantes-vs-pro',
                      'filamentos','qual-usar','processo','problemas','dicas','software','glossario'];
    if (!in_array($section, $validSections)) $section = '';

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
            $st->execute([(int)$currentUser['id'], 'Nova conversa', $section ?: null, $aiMode]);
            $convId = (int)$db->lastInsertId();
        }
    }

    $history = [];
    if ($currentUser && $convId) {
        $st = $db->prepare("SELECT role, content FROM ai_messages WHERE conversation_id=? ORDER BY created_at ASC");
        $st->execute([$convId]);
        $history = $st->fetchAll(PDO::FETCH_ASSOC);
    }

    $levelDesc   = $aiMode === 'advanced'
        ? 'Modo AVANÇADO — usa linguagem técnica, parâmetros específicos, termos em inglês quando pertinente.'
        : 'Modo BÁSICO — usa linguagem acessível, analogias simples, evita jargão desnecessário.';
    $sectionDesc = $section ? "\nContexto: secção \"" . ($sectionNames[$section] ?? $section) . "\". Prioriza este tema quando relevante." : '';

    $systemPrompt = "És o Print AI, o assistente oficial do Manual de Impressão 3D.\nFalas sempre em português de Portugal (não brasileiro).\nEspecialização: impressão 3D FDM, SLA, SLS — filamentos, impressoras, slicers, troubleshooting, design para impressão 3D.\n{$levelDesc}{$sectionDesc}\nRegras: responde apenas sobre impressão 3D. Sê direto e usa markdown quando clarifica. Máx 400 palavras salvo detalhe técnico necessário. Nunca inventes informação.";

    $messages = [['role' => 'system', 'content' => $systemPrompt]];
    foreach (array_slice($history, -20) as $h) {
        if (isset($h['role'], $h['content']) && in_array($h['role'], ['user','assistant'])) {
            $messages[] = ['role' => $h['role'], 'content' => mb_substr($h['content'], 0, 800)];
        }
    }
    $messages[] = ['role' => 'user', 'content' => mb_substr($message, 0, 2000)];
    $maxTokens  = 700;

} else {
    // ── MODOS MANUAL e FORUM — comportamento original ─────────
    if (!isset($_SESSION['ai_count'])) $_SESSION['ai_count'] = 0;
    $_SESSION['ai_count']++;
    if ($_SESSION['ai_count'] > 20) {
        echo json_encode(['success' => false, 'error' => 'Limite de mensagens atingido para esta sessão. Recarrega a página para continuar.']); exit;
    }

    $history = $input['history'] ?? [];

    $systemPrompts = [
        'manual' => "És um assistente especializado em impressão 3D chamado \"Print AI\".\nFalas sempre em português de Portugal.\nAjudas utilizadores do Manual de Impressão 3D com:\n- Dúvidas técnicas sobre impressão 3D (FDM, SLA, SLS, etc.)\n- Recomendação de materiais e filamentos (PLA, PETG, ABS, TPU, etc.)\n- Diagnóstico e resolução de problemas de impressão (stringing, warping, layer adhesion, etc.)\n- Configurações de slicer (Cura, PrusaSlicer, etc.)\n- Escolha e manutenção de impressoras\n- Projetos e design para impressão 3D\n\nSê direto, técnico mas acessível. Usa formatação markdown quando útil (listas, negrito).\nMantém respostas concisas (máx 300 palavras) salvo se a pergunta exigir detalhe.\nNão respondas a perguntas fora do tema de impressão 3D.",
        'forum'  => "És um assistente do Fórum de Impressão 3D chamado \"Forum AI\".\nFalas sempre em português de Portugal.\nAjudas utilizadores com:\n- Problemas técnicos ao publicar posts ou criar comunidades\n- Dúvidas sobre regras e funcionamento do fórum\n- Reportar problemas aos administradores\n- Orientação sobre como usar as funcionalidades do fórum (flairs, comunidades, mensagens privadas, etc.)\n- Mediar conflitos ou dúvidas sobre moderação\n\nSe o utilizador precisar de contactar um administrador, diz-lhe que pode enviar uma mensagem privada a qualquer utilizador com o papel de \"admin\" ou usar o sistema de reports.\nSê amigável, claro e objetivo. Máx 200 palavras por resposta.\nNão respondas a perguntas fora do tema do fórum ou impressão 3D.",
    ];

    $systemPrompt = $systemPrompts[$mode] ?? $systemPrompts['manual'];

    $messages = [['role' => 'system', 'content' => $systemPrompt]];
    foreach (array_slice($history, -6) as $h) {
        if (isset($h['role'], $h['content']) && in_array($h['role'], ['user','assistant'])) {
            $messages[] = ['role' => $h['role'], 'content' => mb_substr($h['content'], 0, 500)];
        }
    }
    $messages[] = ['role' => 'user', 'content' => mb_substr($message, 0, 1000)];
    $maxTokens  = 600;
}

// ── CHAMADA À API GROQ ────────────────────────────────────────
$payload = json_encode([
    'model'       => GROQ_MODEL,
    'messages'    => $messages,
    'max_tokens'  => $maxTokens,
    'temperature' => 0.7,
    'stream'      => false,
]);

$ch = curl_init(GROQ_API_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Authorization: Bearer ' . GROQ_API_KEY],
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
    echo json_encode(['success' => false, 'error' => $data['error']['message'] ?? 'Erro desconhecido da API.']); exit;
}

$reply = trim($data['choices'][0]['message']['content']);

// ── GUARDAR NA BD (só modo assistant) ────────────────────────
if ($mode === 'assistant' && isset($currentUser) && $currentUser && $convId) {
    $ins = $db->prepare("INSERT INTO ai_messages (conversation_id, role, content) VALUES (?,?,?)");
    $ins->execute([$convId, 'user', $message]);
    $ins->execute([$convId, 'assistant', $reply]);
    $db->prepare("UPDATE ai_conversations SET updated_at=NOW(), mode=? WHERE id=?")->execute([$aiMode, $convId]);
    if ($isNewConv) {
        $convTitle = mb_substr($message, 0, 60) . (mb_strlen($message) > 60 ? '…' : '');
        $db->prepare("UPDATE ai_conversations SET title=? WHERE id=?")->execute([$convTitle, $convId]);
    }
}

$out = ['success' => true, 'reply' => $reply, 'tokens' => $data['usage']['total_tokens'] ?? 0];
if ($mode === 'assistant' && isset($currentUser) && $currentUser && $convId) {
    $out['conversation_id'] = $convId;
    if (isset($convTitle) && $convTitle) $out['title'] = $convTitle;
}
echo json_encode($out);