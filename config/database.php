<?php
/**
 * Configuração da Base de Dados — Manual de Impressão 3D
 */

define('DB_HOST',    getenv('DB_HOST')    ?: 'localhost');
define('DB_PORT',    getenv('DB_PORT')    ?: 3306);
define('DB_NAME',    getenv('DB_NAME')    ?: 'manual_3d');
define('DB_USER',    getenv('DB_USER')    ?: 'root');
define('DB_PASS',    getenv('DB_PASS')    ?: '');
define('DB_CHARSET', 'utf8mb4');

define('DB_OPTIONS', [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
]);

/**
 * Garante as tabelas estruturais que podem faltar no backup SQL
 */
function ensureStructuralTables(PDO $pdo): void {
    // Tabelas do Fórum
    $pdo->exec("CREATE TABLE IF NOT EXISTS forum_communities (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        slug VARCHAR(100) NOT NULL UNIQUE,
        description TEXT,
        image_url VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS forum_posts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        community_id INT,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;");

    // Tabelas da IA
    $pdo->exec("CREATE TABLE IF NOT EXISTS ai_conversations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        title VARCHAR(255),
        section_context VARCHAR(50),
        mode ENUM('beginner', 'advanced') DEFAULT 'beginner',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS ai_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conversation_id INT,
        role ENUM('system', 'user', 'assistant') NOT NULL,
        content TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;");
}

/**
 * Inicializa a base de dados a partir do sql/database.sql
 */
function initializeDatabase(): bool {
    $files = [
        __DIR__ . '/../sql/database.sql',
        __DIR__ . '/../sql/if0_41343921_manual_3d.sql'
    ];

    $sqlFile = null;
    foreach ($files as $f) {
        if (is_readable($f)) { $sqlFile = $f; break; }
    }

    try {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, DB_OPTIONS);

        if ($sqlFile) {
            $query = file_get_contents($sqlFile);
            // Limpeza básica de comandos do InfinityFree
            $query = preg_replace('/USE `.*?`;/i', '', $query);
            $pdo->exec($query);
        }

        ensureStructuralTables($pdo);
        return true;
    } catch (PDOException $e) {
        error_log('Erro ao inicializar BD: ' . $e->getMessage());
        return false;
    }
}

function applyMigrationIfNeeded(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $migFile = __DIR__ . '/../sql/migration_comments.sql';
    if (is_readable($migFile)) {
        try {
            $cols = $pdo->query("SHOW COLUMNS FROM comments LIKE 'parent_id'")->fetchAll();
            if (empty($cols)) $pdo->exec(file_get_contents($migFile));
        } catch (Exception $e) {}
    }
    // Sempre garantir tabelas estruturais no arranque
    ensureStructuralTables($pdo);
}

function getDB(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, DB_OPTIONS);
        applyMigrationIfNeeded($pdo);
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'Unknown database') || str_contains($e->getMessage(), "doesn't exist")) {
            if (initializeDatabase()) return new PDO($dsn, DB_USER, DB_PASS, DB_OPTIONS);
        }
        die('Erro de ligação: ' . $e->getMessage());
    }
    return $pdo;
}

function logActivity(?int $userId, string $action, string $details = null): void {
    try {
        $db = getDB();
        $db->prepare('INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?,?,?,?)')
           ->execute([$userId, $action, $details, $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
    } catch (Exception $e) {}
}
