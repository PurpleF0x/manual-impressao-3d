<?php
/**
 * api/complete_tutorial.php
 * Marca o tutorial como visto para o utilizador atual.
 */

require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(['success' => false]);
    exit;
}

$user = getCurrentUser();
$db   = getDB();

try {
    $db->prepare("UPDATE user_profile_config SET has_seen_tutorial = 1 WHERE user_id = ?")
       ->execute([(int)$user['id']]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
