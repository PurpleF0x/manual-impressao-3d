<?php
/**
 * config/ai_config.php — Configuração da IA (Gemini via OpenAI-compatible endpoint)
 */

// A API Key deve ser configurada no Render como GEMINI_API_KEY
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: '');

// Usamos o endpoint compatível com OpenAI do Google Gemini
define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/openai/v1/chat/completions');

// Modelo sugerido: gemini-1.5-flash (rápido e gratuito dentro dos limites)
define('GEMINI_MODEL',   'gemini-1.5-flash');
