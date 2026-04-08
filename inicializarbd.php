<?php
/**
 * Inicializador da Base de Dados — Manual de Impressão 3D
 * Acede a: http://tuosite.com/inicializarbd.php
 * Roda uma única vez para criar todas as tabelas
 */

// Proteger acesso (descomente para produção)
// if ($_SERVER['REMOTE_ADDR'] !== 'localhost' && $_SERVER['REMOTE_ADDR'] !== '127.0.0.1') {
//     die('Acesso negado.');
// }

require_once __DIR__ . '/config/database.php';

// Tentar inicializar
if (initializeDatabase()) {
    echo "<h2 style='color: green;'>✓ Base de dados inicializada com sucesso!</h2>";
    echo "<p>Todas as tabelas, índices e dados de exemplo foram criados.</p>";
    echo "<p><a href='index.php'>Voltar à página principal</a></p>";
} else {
    echo "<h2 style='color: red;'>✗ Erro ao inicializar a base de dados</h2>";
    echo "<p>Verifique os logs de erro ou as permissões do servidor MySQL.</p>";
}
?>