<?php
/**
 * config/ai_config.php — Configuração da IA (OpenAI-Compatible API)
 */

// A API Key deve ser configurada no ambiente (Render/Aiven) como AI_API_KEY
$envKey = getenv('AI_API_KEY') ?: getenv('GROQ_API_KEY') ?: getenv('GROK_API_KEY');
$envKey = $envKey ?: ($_ENV['AI_API_KEY'] ?? ($_SERVER['AI_API_KEY'] ?? ''));

define('AI_API_KEY', $envKey);

// Modelo Qwen 3.6 27b - Versão mais recente e eficiente (Agosto 2026)
define('AI_MODEL', 'qwen/qwen3.6-27b');

// URL da API (Pode ser Groq, OpenRouter, ou outros provedores compatíveis com OpenAI)
define('AI_API_URL', 'https://api.groq.com/openai/v1/chat/completions');
