<?php
/**
 * api/dismiss_notice.php
 * Chamado via AJAX quando o utilizador clica "Entendi" no aviso.
 * Limpa o warning_message da BD para não aparecer novamente.
 */

require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(['success' => false]);
    exit;
}

$user = getCurrentUser();
$db   = getDB();

// Limpar aviso (não apagar suspensão)
$db->prepare("UPDATE users SET warning_message=NULL, warning_at=NULL WHERE id=?")
   ->execute([(int)$user['id']]);

echo json_encode(['success' => true]);
