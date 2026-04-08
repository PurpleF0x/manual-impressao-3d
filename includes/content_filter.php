<?php
/**
 * includes/content_filter.php
 * Filtragem de conteúdo reutilizável — usada em comentários, perfil, etc.
 *
 * Uso:
 *   require_once 'includes/content_filter.php';
 *   $result = checkContent("texto a verificar");
 *   if ($result !== true) {
 *       $errors[] = $result; // $result é a mensagem de erro
 *   }
 */

// ─── CHAVE DA PERSPECTIVE API ─────────────────────────────────
// (mesma chave usada em api/comments.php)
if (!defined('PERSPECTIVE_API_KEY')) {
    define('PERSPECTIVE_API_KEY', 'A_TUA_CHAVE_PERSPECTIVE_API');
}
// ─────────────────────────────────────────────────────────────

/**
 * Lista negra de conteúdo sempre proibido,
 * independentemente do score da IA.
 */
function getBlacklist(): array {
    return [
        'pedofilia', 'pedófilo', 'pedofilo', 'pedofile',
        'abuso infantil', 'abuso de menores', 'abuso sexual',
        'nazi', 'nazista', 'nazism',
        'nigger', 'nigga',
        'violação', 'violar',
        'suicídio', 'suicidio', 'matar-me', 'matar-se',
        'terrorismo', 'terrorista',
    ];
}

/**
 * Verifica o texto contra a lista negra.
 * Devolve true se passou, ou a mensagem de erro se bloqueado.
 */
function checkBlacklist(string $text): bool|string {
    $lower = mb_strtolower($text, 'UTF-8');
    foreach (getBlacklist() as $word) {
        if (str_contains($lower, $word)) {
            return '⚠️ O conteúdo foi bloqueado por conter termos proibidos.';
        }
    }
    return true;
}

/**
 * Verifica o texto com a Perspective API.
 * Devolve o score (0.0–1.0) ou -1 em caso de erro/API não configurada.
 */
function getPerspectiveScore(string $text): float {
    if (PERSPECTIVE_API_KEY === 'A_TUA_CHAVE_PERSPECTIVE_API') {
        return -1;
    }

    $payload = json_encode([
        'comment'             => ['text' => $text],
        'languages'           => ['pt'],
        'requestedAttributes' => ['TOXICITY' => (object)[]]
    ]);

    $ch = curl_init(
        'https://commentanalyzer.googleapis.com/v1alpha1/comments:analyze?key=' . PERSPECTIVE_API_KEY
    );
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 5,
    ]);

    $response  = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError || !$response) return -1;

    $data = json_decode($response, true);
    return (float)($data['attributeScores']['TOXICITY']['summaryScore']['value'] ?? -1);
}

/**
 * Função principal — verifica um texto com lista negra + Perspective API.
 *
 * Devolve:
 *   true          → conteúdo aprovado (publicar diretamente)
 *   'pendente'    → conteúdo duvidoso (guardar mas assinalar para revisão)
 *   string        → mensagem de erro (bloquear e mostrar ao utilizador)
 */
function checkContent(string $text): bool|string {
    // 1. Lista negra — bloqueia imediatamente
    $blacklistResult = checkBlacklist($text);
    if ($blacklistResult !== true) {
        return $blacklistResult;
    }

    // 2. Perspective API
    $score = getPerspectiveScore($text);

    if ($score >= 0.65) {
        return '⚠️ O conteúdo foi bloqueado por conter linguagem ofensiva. Por favor reformula.';
    }

    return true; // aprovado
}

/**
 * Verifica vários campos de uma só vez.
 * Devolve true se todos passaram, ou a primeira mensagem de erro.
 *
 * Uso:
 *   $result = checkFields(['Nome da impressora' => $brand, 'Modelo' => $model]);
 */
function checkFields(array $fields): bool|string {
    foreach ($fields as $label => $value) {
        if (empty(trim($value))) continue; // campos vazios ignorados
        $result = checkContent($value);
        if ($result !== true) {
            return "Campo '{$label}': {$result}";
        }
    }
    return true;
}
