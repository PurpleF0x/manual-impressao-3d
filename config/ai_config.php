<?php
/**
 * config/ai_config.php — Configuração da IA (Groq Cloud)
 */

// A API Key deve ser configurada no Render como GROQ_API_KEY
$envKey = getenv('GROQ_API_KEY') ?: getenv('GROK_API_KEY');
$envKey = $envKey ?: ($_ENV['GROQ_API_KEY'] ?? ($_SERVER['GROQ_API_KEY'] ?? ''));

define('GROQ_API_KEY', $envKey);

// Modelo gratuito e potente do Groq
define('GROQ_MODEL', 'llama-3.3-70b-versatile');
define('GROQ_API_URL', 'https://api.groq.com/openai/v1/chat/completions');
