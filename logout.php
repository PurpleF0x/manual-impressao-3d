<?php
require_once 'includes/functions.php';

$userId = $_SESSION['user_id'] ?? null;

// Eliminar sessão persistente
if (isset($_COOKIE['remember_token'])) {
    $db = getDB();
    $db->prepare("DELETE FROM user_sessions WHERE session_token = ?")->execute([$_COOKIE['remember_token']]);
    setcookie('remember_token', '', time() - 3600, '/', '', false, true);
}

// Log da atividade
if ($userId) {
    logActivity($userId, 'logout', "Logout efetuado");
}

// Destruir sessão
session_destroy();

setFlashMessage('success', 'Sessão terminada com sucesso.');
redirect('index.php');
?>
