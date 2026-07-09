<?php
/**
 * admin_db_inspector.php — Visualizador de Tabelas (Apenas Master)
 */
require_once 'includes/functions.php';

if (!isLoggedIn() || getCurrentUser()['role'] !== 'master') {
    die('Acesso negado. Apenas para o Administrador Master.');
}

$db = getDB();
$tables = [];
try {
    $stmt = $db->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    die("Erro ao listar tabelas: " . $e->getMessage());
}

$selectedTable = $_GET['table'] ?? null;
$data = [];
$columns = [];

if ($selectedTable && in_array($selectedTable, $tables)) {
    try {
        $stmt = $db->query("SELECT * FROM `$selectedTable` LIMIT 100");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($data)) {
            $columns = array_keys($data[0]);
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>DB Inspector — Master</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Mono&family=Inter:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body { background: #0a0a0f; color: #e8e8f0; font-family: 'Inter', sans-serif; padding: 20px; }
        h1 { color: #00e5ff; font-family: 'Space Mono'; }
        .layout { display: grid; grid-template-columns: 250px 1fr; gap: 20px; }
        .sidebar { background: #111118; padding: 20px; border-radius: 10px; border: 1px solid #222; }
        .sidebar a { display: block; color: #888; text-decoration: none; padding: 5px 0; font-size: 14px; }
        .sidebar a:hover, .sidebar a.active { color: #00e5ff; }
        .content { background: #111118; padding: 20px; border-radius: 10px; border: 1px solid #222; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border: 1px solid #333; padding: 8px; text-align: left; }
        th { background: #1a1a26; color: #00e5ff; }
        tr:nth-child(even) { background: #14141d; }
    </style>
</head>
<body>
    <h1>🗄️ Database Inspector <small style="font-size: 12px; color: #888;">(Modo Master)</small></h1>
    <div class="layout">
        <div class="sidebar">
            <h3>Tabelas</h3>
            <?php foreach ($tables as $t): ?>
                <a href="?table=<?php echo $t; ?>" class="<?php echo $selectedTable === $t ? 'active' : ''; ?>">
                    <?php echo $t; ?>
                </a>
            <?php endforeach; ?>
            <br>
            <a href="index.php" style="color: #ff6b35;">← Voltar ao Site</a>
        </div>
        <div class="content">
            <?php if ($selectedTable): ?>
                <h2>Tabela: <?php echo $selectedTable; ?></h2>
                <?php if (!empty($data)): ?>
                    <table>
                        <thead>
                            <tr>
                                <?php foreach ($columns as $col): ?>
                                    <th><?php echo $col; ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data as $row): ?>
                                <tr>
                                    <?php foreach ($row as $val): ?>
                                        <td><?php echo htmlspecialchars($val ?? 'NULL'); ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>Tabela vazia ou sem dados para exibir.</p>
                <?php endif; ?>
            <?php else: ?>
                <p>Selecione uma tabela à esquerda para visualizar os dados.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
