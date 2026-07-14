<?php
/**
 * Ficheiro de Teste Profissional de Emails
 */
require_once 'includes/functions.php';
require_once 'includes/mail_config.php';

$emailTeste = 'manual3d.projetos@gmail.com'; // Altera para o teu email de teste

echo "<h1>Painel de Teste de Comunicações</h1>";
echo "<p>Domínio Configurado: <strong>" . SITE_URL . "</strong></p>";

if (isset($_GET['acao'])) {
    if ($_GET['acao'] === 'boasvindas') {
        echo "<p>A enviar email de boas-vindas...</p>";
        $res = sendWelcomeEmail($emailTeste, "Explorador 3D");
    } elseif ($_GET['acao'] === 'recuperacao') {
        echo "<p>A enviar email de recuperação...</p>";
        $res = sendPasswordResetEmail($emailTeste, "Utilizador Teste", "token_123", SITE_URL . "/reset_password.php?token=123");
    }

    if ($res) {
        echo "<h2 style='color:green;'>✅ Email enviado com sucesso para $emailTeste!</h2>";
    } else {
        echo "<h2 style='color:red;'>❌ Erro ao enviar. Verifica os logs do Render.</h2>";
    }
}

echo "<ul>";
echo "<li><a href='?acao=boasvindas'>Testar Email de Boas-vindas</a></li>";
echo "<li><a href='?acao=recuperacao'>Testar Email de Recuperação</a></li>";
echo "</ul>";
echo "<br><a href='index.php'>Voltar ao Início</a>";
