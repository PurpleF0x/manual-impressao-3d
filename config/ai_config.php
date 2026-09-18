<?php
/**
 * config/ai_config.php — Configuração da IA (Gemini API com Camada de Fallback OpenRouter)
 */

// Configurações principais via Variáveis de Ambiente do Render
$aiProvider = getenv('AI_PROVIDER') ?: 'gemini';

$geminiApiKey = getenv('GEMINI_API_KEY') ?: getenv('GOOGLE_API_KEY') ?: getenv('GOOGLE_AI_KEY') ?: '';
$geminiModel  = getenv('GEMINI_MODEL') ?: 'gemini-3.8-flash';
$geminiApiUrl = 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions';

$openrouterApiKey = getenv('OPENROUTER_API_KEY') ?: '';
$openrouterModel  = getenv('OPENROUTER_MODEL') ?: 'openrouter/free';
$openrouterApiUrl = 'https://openrouter.ai/api/v1/chat/completions';

// Constantes legadas mantidas apenas para compatibilidade retroativa geral
define('AI_API_KEY', $aiProvider === 'gemini' ? $geminiApiKey : $openrouterApiKey);
define('AI_MODEL', $aiProvider === 'gemini' ? $geminiModel : $openrouterModel);
define('AI_API_URL', $aiProvider === 'gemini' ? $geminiApiUrl : $openrouterApiUrl);

/**
 * Função utilitária para higienizar mensagens de erro antes de as escrever nos logs.
 * Remove chaves de API potenciais e caracteres de controlo indesejados.
 */
function sanitizeLogMessage($message) {
    if (empty($message)) return '';
    // Ocultar padrões comuns de chaves de API ou tokens de autenticação
    $message = preg_replace('/AIzaSy[A-Za-z0-9_\-]{31}/', 'AIzaSy***', $message);
    $message = preg_replace('/sk-[A-Za-z0-9]{32,}/', 'sk-***', $message);
    $message = preg_replace('/Bearer\s+[A-Za-z0-9_\-\.]+/i', 'Bearer ***', $message);
    // Remover quebras de linha e caracteres de controlo para manter o log limpo e seguro
    return trim(preg_replace('/[\r\n\t]+/', ' ', $message));
}

/**
 * Auxiliar para fazer chamadas HTTP POST para APIs compatíveis com OpenAI
 */
function callOpenAICompatibleAPI($url, $key, $model, $messages, $temperature = 0.7, $timeout = 30) {
    $payload = json_encode([
        'model'       => $model,
        'messages'    => $messages,
        'temperature' => $temperature,
        'stream'      => false
    ]);

    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $key
    ];

    if (strpos($url, 'openrouter.ai') !== false) {
        $headers[] = 'HTTP-Referer: https://manual-3d.pt';
        $headers[] = 'X-Title: Manual 3D';
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => $timeout,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return [
            'success'   => false,
            'error'     => 'cURL Error: ' . $curlError,
            'http_code' => 0
        ];
    }

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
        return [
            'success'   => false,
            'error'     => "API Error ($httpCode): $err",
            'http_code' => $httpCode
        ];
    }

    return [
        'success'   => true,
        'content'   => $data['choices'][0]['message']['content'],
        'http_code' => $httpCode
    ];
}

/**
 * Função de Abstração Principal para Geração de Respostas de IA com Camada de Fallback Inteligente
 */
function generateAIResponse($messages, $temperature = 0.7) {
    global $aiProvider, $geminiApiKey, $geminiModel, $geminiApiUrl, $openrouterApiKey, $openrouterModel, $openrouterApiUrl;

    if ($aiProvider === 'gemini') {
        // Tentar primeiro Provedor Principal: Gemini
        if (!empty($geminiApiKey)) {
            error_log("AI provider: Gemini");
            $res = callOpenAICompatibleAPI($geminiApiUrl, $geminiApiKey, $geminiModel, $messages, $temperature);

            if ($res['success']) {
                return [
                    'success' => true,
                    'reply'   => $res['content']
                ];
            }

            // Capturar o código HTTP da falha do Gemini
            $cleanError = sanitizeLogMessage($res['error']);
            error_log("AI provider failed: " . $cleanError);
        } else {
            error_log("AI provider failed: Gemini API Key is empty.");
        }

        // Acionar Fallback para o OpenRouter caso o Gemini falhe por qualquer erro e exista a chave configurada
        if (empty($openrouterApiKey)) {
            error_log("Fallback OpenRouter skipped: OPENROUTER_API_KEY is empty.");
            return [
                'success' => false,
                'error'   => 'O provedor principal (Gemini) falhou e o OpenRouter não está configurado no ambiente.'
            ];
        }

        error_log("Fallback: OpenRouter");
        $fallbackRes = callOpenAICompatibleAPI($openrouterApiUrl, $openrouterApiKey, $openrouterModel, $messages, $temperature);

        if ($fallbackRes['success']) {
            error_log("Fallback successful");
            return [
                'success' => true,
                'reply'   => $fallbackRes['content']
            ];
        } else {
            $cleanFallbackError = sanitizeLogMessage($fallbackRes['error']);
            error_log("Fallback failed: " . $cleanFallbackError);
            return [
                'success' => false,
                'error'   => 'De momento, o serviço de Assistente de IA está indisponível. Por favor, tente novamente mais tarde.'
            ];
        }
    } else {
        // Se o provedor principal estiver explicitly definido como openrouter ou outro customizado
        error_log("AI provider: " . $aiProvider);
        $url   = ($aiProvider === 'openrouter') ? $openrouterApiUrl : getenv('AI_API_URL');
        $key   = ($aiProvider === 'openrouter') ? $openrouterApiKey : getenv('AI_API_KEY');
        $model = ($aiProvider === 'openrouter') ? $openrouterModel : getenv('AI_MODEL');

        if (empty($key)) {
            return [
                'success' => false,
                'error'   => "Chave da API para o provedor '$aiProvider' não configurada."
            ];
        }

        $res = callOpenAICompatibleAPI($url, $key, $model, $messages, $temperature);
        if ($res['success']) {
            return [
                'success' => true,
                'reply'   => $res['content']
            ];
        } else {
            return [
                'success' => false,
                'error'   => $res['error']
            ];
        }
    }
}
