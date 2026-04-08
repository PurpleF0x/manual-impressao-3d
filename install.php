<?php
/**
 * Script de Instalação - Manual de Impressão 3D
 * Execute este ficheiro uma única vez para configurar a base de dados
 */

// Verificar se já está instalado
if (file_exists('config/installed.lock')) {
    die('O sistema já está instalado. Elimine este ficheiro por segurança.');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = $_POST['db_host'] ?? 'localhost';
    $dbName = $_POST['db_name'] ?? 'manual_3d';
    $dbUser = $_POST['db_user'] ?? 'root';
    $dbPass = $_POST['db_pass'] ?? '';
    
    $adminUser = $_POST['admin_user'] ?? 'admin';
    $adminEmail = $_POST['admin_email'] ?? 'admin@exemplo.pt';
    $adminPass = $_POST['admin_pass'] ?? '';
    
    try {
        // Testar conexão
        $pdo = new PDO("mysql:host=$dbHost;charset=utf8mb4", $dbUser, $dbPass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Criar base de dados
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$dbName`");
        
        // Criar tabelas
        $sql = file_get_contents('database.sql');
        $pdo->exec($sql);
        
        // Atualizar password do admin
        if (!empty($adminPass)) {
            $passwordHash = password_hash($adminPass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE username = 'admin'");
            $stmt->execute([$passwordHash]);
        }
        
        // Atualizar configuração
        $configContent = "<?php
/**
 * Configuração da Base de Dados
 * Manual de Impressão 3D
 */

define('DB_HOST', '$dbHost');
define('DB_NAME', '$dbName');
define('DB_USER', '$dbUser');
define('DB_PASS', '$dbPass');
define('DB_CHARSET', 'utf8mb4');

\$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

function getDB() {
    static \$pdo = null;
    
    if (\$pdo === null) {
        try {
            \$dsn = \"mysql:host=\" . DB_HOST . \";dbname=\" . DB_NAME . \";charset=\" . DB_CHARSET;
            \$pdo = new PDO(\$dsn, DB_USER, DB_PASS, \$GLOBALS['options']);
        } catch (PDOException \$e) {
            die(\"Erro de conexão: \" . \$e->getMessage());
        }
    }
    
    return \$pdo;
}

function logActivity(\$userId, \$action, \$details = null) {
    \$db = getDB();
    \$stmt = \$db->prepare(\"INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)\");
    \$stmt->execute([\$userId, \$action, \$details, \$_SERVER['REMOTE_ADDR']]);
}
?>";
        
        file_put_contents('config/database.php', $configContent);
        
        // Criar ficheiro de lock
        file_put_contents('config/installed.lock', date('Y-m-d H:i:s'));
        
        $success = 'Instalação concluída com sucesso! <a href="index.php">Ir para o site</a>';
        
    } catch (PDOException $e) {
        $error = 'Erro na base de dados: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalação - Manual de Impressão 3D</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0a0a0f;
            --surface: #111118;
            --surface2: #1a1a26;
            --accent: #00e5ff;
            --accent2: #ff6b35;
            --text: #e8e8f0;
            --muted: #888899;
            --border: rgba(0,229,255,0.15);
            --success: #00ff88;
            --error: #ff4444;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            background: var(--bg);
            color: var(--text);
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        .install-container {
            width: 100%;
            max-width: 600px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 40px;
        }

        .install-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .install-header h1 {
            font-family: 'Syne', sans-serif;
            font-size: 28px;
        }

        .install-header h1 span {
            color: var(--accent);
        }

        .install-header p {
            color: var(--muted);
            margin-top: 8px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-family: 'Space Mono', monospace;
            font-size: 11px;
            color: var(--muted);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .form-group input {
            width: 100%;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 14px 18px;
            color: var(--text);
            font-family: 'Inter', sans-serif;
            font-size: 15px;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--accent);
        }

        .form-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border);
        }

        .form-section h3 {
            font-family: 'Syne', sans-serif;
            font-size: 16px;
            margin-bottom: 16px;
            color: var(--accent);
        }

        .btn-primary {
            width: 100%;
            background: linear-gradient(135deg, var(--accent), var(--accent3));
            border: none;
            border-radius: 12px;
            padding: 16px;
            color: #000;
            font-family: 'Space Mono', monospace;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 1px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(0,229,255,0.3);
        }

        .alert {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-success {
            background: rgba(0,255,136,0.1);
            border: 1px solid rgba(0,255,136,0.3);
            color: var(--success);
        }

        .alert-error {
            background: rgba(255,68,68,0.1);
            border: 1px solid rgba(255,68,68,0.3);
            color: #ff6666;
        }

        .alert a {
            color: inherit;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="install-header">
            <h1>Instalação <span>Manual 3D</span></h1>
            <p>Configura o teu sistema em poucos passos</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php elseif ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (!$success): ?>
        <form method="POST" action="">
            <div class="form-section">
                <h3>🔌 Configuração da Base de Dados</h3>
                <div class="form-group">
                    <label>Servidor MySQL</label>
                    <input type="text" name="db_host" value="localhost" required>
                </div>
                <div class="form-group">
                    <label>Nome da Base de Dados</label>
                    <input type="text" name="db_name" value="manual_3d" required>
                </div>
                <div class="form-group">
                    <label>Utilizador MySQL</label>
                    <input type="text" name="db_user" value="root" required>
                </div>
                <div class="form-group">
                    <label>Password MySQL</label>
                    <input type="password" name="db_pass" placeholder="Deixa em branco se não tiver password">
                </div>
            </div>

            <div class="form-section">
                <h3>👤 Conta de Administrador</h3>
                <div class="form-group">
                    <label>Username Admin</label>
                    <input type="text" name="admin_user" value="admin" required>
                </div>
                <div class="form-group">
                    <label>Email Admin</label>
                    <input type="email" name="admin_email" value="admin@exemplo.pt" required>
                </div>
                <div class="form-group">
                    <label>Password Admin</label>
                    <input type="password" name="admin_pass" placeholder="Mínimo 8 caracteres" required>
                </div>
            </div>

            <button type="submit" class="btn-primary">INSTALAR SISTEMA</button>
        </form>
        <?php endif; ?>
    </div>
</body>
</html>
