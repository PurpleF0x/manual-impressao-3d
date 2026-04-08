<?php
/**
 * migrar_master.php — Migração de role para Master
 * EXECUTAR UMA VEZ e apagar depois
 */
require_once __DIR__ . '/includes/functions.php';
$db = getDB();

echo "<pre style='font-family:monospace;padding:20px;background:#111;color:#0f0'>";
echo "=== MIGRAÇÃO PARA MASTER ===\n\n";

// 1. Adicionar 'master' ao ENUM
try {
    $db->exec("ALTER TABLE users MODIFY COLUMN role ENUM('master','admin','moderator','user') DEFAULT 'user'");
    echo "✓ ENUM actualizado (master adicionado)\n";
} catch(Exception $e) { echo "ENUM: " . $e->getMessage() . "\n"; }

// 2. Criar tabela de logs
try {
    $db->exec("CREATE TABLE IF NOT EXISTS admin_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        actor_id INT NOT NULL,
        target_id INT NULL,
        action VARCHAR(50) NOT NULL,
        detail TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "✓ Tabela admin_logs criada\n";
} catch(Exception $e) { echo "admin_logs: " . $e->getMessage() . "\n"; }

// 3. Adicionar coluna suspended_until
try {
    $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS suspended_until DATETIME NULL");
    echo "✓ Coluna suspended_until adicionada\n";
} catch(Exception $e) { echo "suspended_until: " . $e->getMessage() . "\n"; }

// 4. Mostrar utilizadores actuais
echo "\n--- Utilizadores por role ---\n";
$roles = $db->query("SELECT role, COUNT(*) as cnt FROM users GROUP BY role")->fetchAll();
foreach ($roles as $r) echo "  {$r['role']}: {$r['cnt']}\n";

// 5. Mudar utilizador com id=2 (ou o primeiro admin) para master
echo "\n--- Promover a Master ---\n";
echo "Qual utilizador deve ser Master?\n";
$admins = $db->query("SELECT id, full_name, username, role FROM users WHERE role='admin' ORDER BY id LIMIT 5")->fetchAll();
foreach ($admins as $a) echo "  id={$a['id']} | {$a['full_name']} (@{$a['username']}) — {$a['role']}\n";

echo "\nPara promover, abre este URL:\n";
echo "migrar_master.php?promote_id=X (substitui X pelo id)\n";

if (!empty($_GET['promote_id'])) {
    $pid = (int)$_GET['promote_id'];
    $db->prepare("UPDATE users SET role='master' WHERE id=?")->execute(array($pid));
    $u = $db->prepare("SELECT full_name, username FROM users WHERE id=?")->execute(array($pid)) ? $db->prepare("SELECT full_name, username FROM users WHERE id=$pid")->execute() : null;
    $uu = $db->query("SELECT full_name, username FROM users WHERE id=$pid")->fetch();
    echo "\n✅ Utilizador id=$pid (" . ($uu['full_name']??'?') . ") promovido a MASTER!\n";
    echo "Podes apagar este ficheiro.\n";
}

echo "\n=== FIM ===\n</pre>";
