<?php
/**
 * Configuração da Base de Dados — Manual de Impressão 3D
 */

define('DB_HOST',    getenv('DB_HOST')    ?: 'sql303.infinityfree.com');
define('DB_PORT',    getenv('DB_PORT')    ?: 3306);
define('DB_NAME',    getenv('DB_NAME')    ?: 'if0_41343921_manual_3d');
define('DB_USER',    getenv('DB_USER')    ?: 'if0_41343921');
define('DB_PASS',    getenv('DB_PASS')    ?: 'eBZRgR0bkaEB');
define('DB_CHARSET', 'utf8mb4');

define('DB_OPTIONS', [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    // ESSENCIAL PARA O AIVEN: Ativa o SSL para ligações remotas seguras
    PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
]);

/**
 * Inicializa a base de dados a partir do sql/database.sql
 */
function initializeDatabase(): bool {
    // Tenta encontrar o ficheiro SQL (nome original ou genérico)
    $files = [
        __DIR__ . '/../sql/database.sql',
        __DIR__ . '/../sql/if0_41343921_manual_3d.sql'
    ];

    $sqlFile = null;
    foreach ($files as $f) {
        if (is_readable($f)) {
            $sqlFile = $f;
            break;
        }
    }

    if (!$sqlFile) return false;

    try {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, DB_OPTIONS);
        $pdo->exec(file_get_contents($sqlFile));
        return true;
    } catch (PDOException $e) {
        error_log('Erro ao inicializar BD: ' . $e->getMessage());
        return false;
    }
}

/**
 * Aplica a migração de comentários avançados numa BD já existente.
 * Chamada uma vez por request (flag estática).
 */
function applyMigrationIfNeeded(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $done = true;

    $migFile = __DIR__ . '/../sql/migration_comments.sql';
    if (!is_readable($migFile)) return;

    try {
        // Verificar se a migração já foi aplicada: coluna parent_id existe?
        $cols = $pdo->query("SHOW COLUMNS FROM comments LIKE 'parent_id'")->fetchAll();
        if (!empty($cols)) return; // já aplicada

        $pdo->exec(file_get_contents($migFile));
    } catch (PDOException $e) {
        // Não bloquear o arranque por erros de migração
        error_log('Aviso migração: ' . $e->getMessage());
    }
}

/**
 * Devolve uma ligação PDO (singleton por request).
 */
function getDB(): PDO {
    static $pdo = null;
    static $tried = false;

    if ($pdo !== null) return $pdo;

    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT
         . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, DB_OPTIONS);
        applyMigrationIfNeeded($pdo);
    } catch (PDOException $e) {
        if (!$tried && (
            str_contains($e->getMessage(), 'Unknown database') ||
            str_contains($e->getMessage(), "doesn't exist")
        )) {
            $tried = true;
            if (initializeDatabase()) {
                try {
                    $pdo = new PDO($dsn, DB_USER, DB_PASS, DB_OPTIONS);
                } catch (PDOException $e2) {
                    die('Falha na ligação à BD: ' . $e2->getMessage());
                }
            } else {
                die('Não foi possível inicializar a base de dados.');
            }
        } else {
            die('Erro de ligação à BD: ' . $e->getMessage());
        }
    }

    return $pdo;
}

/**
 * Regista atividade do utilizador.
 */
function logActivity(?int $userId, string $action, string $details = null): void {
    try {
        $db = getDB();
        $db->prepare(
            'INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?,?,?,?)'
        )->execute([$userId, $action, $details, $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
    } catch (Exception $e) {
        error_log('Erro ao registar atividade: ' . $e->getMessage());
    }
}
