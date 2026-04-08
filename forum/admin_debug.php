<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/functions.php';

echo "<pre style='font-family:monospace;padding:20px;background:#111;color:#0f0;font-size:12px'>";
echo "=== ADMIN DEBUG 5 ===\n\n";

// Testar inclusão linha a linha — ler o ficheiro e executar em chunks
$adminPath = __DIR__ . '/admin.php';
$content = file_get_contents($adminPath);

// Encontrar todos os 'function' definidos no admin.php
preg_match_all('/^function\s+(\w+)/m', $content, $m);
echo "Funções no admin.php: " . implode(', ', $m[1]) . "\n\n";

// Testar se alguma já existe
foreach ($m[1] as $fn) {
    if (function_exists($fn)) {
        echo "CONFLITO: $fn() já existe em functions.php !\n";
    }
}

// Mostrar linhas 30-50 do admin.php
echo "\n--- Linhas 30-55 do admin.php ---\n";
$lines = explode("\n", $content);
for ($i = 29; $i <= 54 && $i < count($lines); $i++) {
    echo ($i+1) . ": " . $lines[$i] . "\n";
}

// Tentar incluir e capturar erro exacto com Throwable
echo "\n--- Inclusão directa ---\n";
try {
    ob_start();
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET['tab'] = 'stats';
    $_GET['q'] = '';
    include $adminPath;
    $out = ob_get_clean();
    echo "OK — " . strlen($out) . " bytes\n";
} catch (ParseError $e) {
    ob_end_clean();
    echo "ParseError linha " . $e->getLine() . ": " . $e->getMessage() . "\n";
} catch (Error $e) {
    ob_end_clean();
    echo "Error linha " . $e->getLine() . ": " . $e->getMessage() . "\n";
} catch (Throwable $e) {
    ob_end_clean();
    echo get_class($e) . " linha " . $e->getLine() . ": " . $e->getMessage() . "\n";
}

echo "\n=== FIM ===\n</pre>";