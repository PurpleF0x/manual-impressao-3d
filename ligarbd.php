<?php
/**
 * Compatibilidade com código legado
 * Este ficheiro está deprecado — prefira usar config/database.php
 */

// Para compatibilidade, alias com a função PDO
class DatabaseLegacy {
    private static $instance = null;
    
    public static function connect() {
        if (self::$instance === null) {
            require_once __DIR__ . '/config/database.php';
            self::$instance = getDB();
        }
        return self::$instance;
    }
}

// Manter compatibilidade com código antigo
$conn = DatabaseLegacy::connect();
?>