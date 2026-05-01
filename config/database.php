<?php
/**
 * Configuração da Base de Dados — Manual de Impressão 3D
 * Otimizado para TiDB Cloud e Render
 */

// Configurações de ligação
define('DB_HOST',    getenv('DB_HOST')    ?: 'gateway01.eu-central-1.prod.aws.tidbcloud.com');
define('DB_PORT',    getenv('DB_PORT')    ?: 4000);
define('DB_NAME',    getenv('DB_NAME')    ?: 'manual_3d');
define('DB_USER',    getenv('DB_USER')    ?: '3VKDTpnqS1VYkC2.root');
define('DB_PASS',    getenv('DB_PASS')    ?: ''); // No Render, preenche no Dashboard (Environment)
define('DB_CHARSET', 'utf8mb4');

// Caminho do certificado CA para SSL (Obrigatório para TiDB Cloud)
$ca_bundle = '/etc/ssl/certs/ca-certificates.crt';

define('DB_OPTIONS', [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
    PDO::MYSQL_ATTR_SSL_CA       => file_exists($ca_bundle) ? $ca_bundle : null,
    PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
    // PERMITE EXECUTAR O FICHEIRO SQL INTEIRO DE UMA VEZ
    PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
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

        // Verificação: se a ligação deu OK mas a tabela principal não existe, tenta inicializar
        try {
            $pdo->query("SELECT 1 FROM users LIMIT 1");
        } catch (Exception $e) {
            if (initializeDatabase()) {
                // Reconecta para assumir as novas tabelas
                return new PDO($dsn, DB_USER, DB_PASS, DB_OPTIONS);
            }
        }
    } catch (PDOException $e) {
        // Se a BD não existir (Erro 1049), tenta criar e inicializar
        if ($e->getCode() == 1049 || str_contains($e->getMessage(), "doesn't exist")) {
            if (initializeDatabase()) {
                return new PDO($dsn, DB_USER, DB_PASS, DB_OPTIONS);
            }
        }
        die('Erro de ligação: ' . $e->getMessage());
    }
    return $pdo;
}

/**
 * Inicializa a base de dados usando o ficheiro SQL mestre
 */
function initializeDatabase(): bool {
    $sqlFile = __DIR__ . '/../sql/manual_3d.sql';
    if (!is_readable($sqlFile)) return false;

    try {
        // Liga sem selecionar DB primeiro para garantir que a pode criar
        $tmp_dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=' . DB_CHARSET;
        $tmp_pdo = new PDO($tmp_dsn, DB_USER, DB_PASS, DB_OPTIONS);

        $tmp_pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "`;");
        $tmp_pdo->exec("USE `" . DB_NAME . "`;");

        $query = file_get_contents($sqlFile);

        // Remove comandos que podem conflitar se a BD já existir parcialmente
        $query = preg_replace('/CREATE DATABASE IF NOT EXISTS `.*?`;/i', '', $query);
        $query = preg_replace('/USE `.*?`;/i', '', $query);

        $tmp_pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
        $tmp_pdo->exec($query);
        $tmp_pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

        return true;
    } catch (Exception $e) {
        error_log("Erro na inicialização automática: " . $e->getMessage());
        return false;
    }
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