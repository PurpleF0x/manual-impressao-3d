<?php
/**
 * API de Pesquisa Global Híbrida (Manual + Fórum)
 */
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

$query = $_GET['q'] ?? '';
if (strlen($query) < 2) {
    echo json_encode(['results' => []]);
    exit;
}

$db = getDB();
$results = [
    'manual' => [],
    'forum'  => []
];

// 1. Pesquisa no Fórum (Tópicos aprovados)
try {
    $stmt = $db->prepare("
        SELECT id, title, content, 'forum' as source
        FROM comments
        WHERE status = 'aprovado' AND parent_id IS NULL
        AND (title LIKE ? OR content LIKE ?)
        ORDER BY created_at DESC LIMIT 5
    ");
    $searchTerm = "%$query%";
    $stmt->execute([$searchTerm, $searchTerm]);
    $results['forum'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Search Error (Forum): " . $e->getMessage());
}

// 2. Pesquisa no Manual (Simulada por agora, futuramente ler ficheiros ou tabela de artigos)
// Por agora, vamos buscar tópicos do manual se tivéssemos uma tabela 'articles'.
// Como o manual parece ser estático/Markdown, vamos deixar o placeholder:
$results['manual'] = [
    ['title' => 'Introdução ao Warping', 'url' => 'manual.php?topico=warping'],
    ['title' => 'Calibração de Extrusora', 'url' => 'manual.php?topico=calibracao']
];

echo json_encode($results);
