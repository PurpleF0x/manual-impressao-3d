<?php
/**
 * Configuração da Base de Dados — Manual de Impressão 3D
 * Revertido para Aiven (ou qualquer MySQL padrão via Env Vars)
 */

define('DB_HOST',    getenv('DB_HOST'));
define('DB_PORT',    getenv('DB_PORT')    ?: 3306);
define('DB_NAME',    getenv('DB_NAME')    ?: 'PAP');
define('DB_USER',    getenv('DB_USER'));
define('DB_PASS',    getenv('DB_PASS'));
define('DB_CHARSET', 'utf8mb4');

define('DB_OPTIONS', [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
]);

/**
 * Retorna a ligação à Base de Dados (Singleton)
 */
function getDB(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, DB_OPTIONS);
    } catch (PDOException $e) {
        die('Erro de ligação: ' . $e->getMessage());
    }
    return $pdo;
}

/**
 * Regista atividades (Logs)
 */
function logActivity(?int $userId, string $action, string $details = null): void {
    try {
        $db = getDB();
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $db->prepare('INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?,?,?,?)')
           ->execute([$userId, $action, $details, $ip]);
    } catch (Exception $e) {}
}

/**
 * Regista ações administrativas (Auditoria)
 */
function logAdminAction(int $actorId, ?int $targetId, string $action, ?string $detail = null): void {
    try {
        $db = getDB();
        $db->prepare('INSERT INTO admin_logs (actor_id, target_id, action, detail) VALUES (?,?,?,?)')
           ->execute([$actorId, $targetId, $action, $detail]);
    } catch (Exception $e) {
        error_log("Erro logAdminAction: " . $e->getMessage());
    }
}
