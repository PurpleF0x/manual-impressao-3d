<?php
require_once __DIR__ . '/config/database.php';
$hash = password_hash('admin123', PASSWORD_BCRYPT, ['cost' => 10]);
try {
    $db = getDB();
    $stmt = $db->prepare("UPDATE users SET password_hash = ?, role = 'admin', is_active = 1 WHERE username = 'admin'");
    $stmt->execute([$hash]);
    if ($stmt->rowCount() === 0) {
        $db->prepare("INSERT INTO users (username, email, password_hash, full_name, role, is_active) VALUES ('admin','admin@exemplo.pt',?,'Administrador','admin',1)")->execute([$hash]);
        echo "<p style='color:green'>Conta admin criada.</p>";
    } else {
        echo "<p style='color:green'>Password atualizada para admin123.</p>";
    }
    echo "<p>Login: admin / admin123</p><p><a href='login.php'>Ir para o login</a></p>";
    echo "<p style='color:red'>APAGA ESTE FICHEIRO DO SERVIDOR!</p>";
    @unlink(__FILE__);
} catch (Exception $e) {
    echo "<p style='color:red'>Erro: " . htmlspecialchars($e->getMessage()) . "</p>";
}