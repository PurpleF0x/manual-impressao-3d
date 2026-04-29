<?php
/**
 * config/ai_config.php — Configuração da IA (Grok xAI)
 */

// A API Key deve ser configurada no Render como GROK_API_KEY
$envKey = getenv('GROK_API_KEY') ?: ($_ENV['GROK_API_KEY'] ?? ($_SERVER['GROK_API_KEY'] ?? ''));
define('GROK_API_KEY', $envKey);

// Configurações Grok
define('GROK_MODEL', 'grok-beta');
define('GROK_API_URL', 'https://api.x.ai/v1/chat/completions');
