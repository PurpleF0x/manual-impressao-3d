<?php
/**
 * Ficheiro de Teste SMTP no Render
 */
require_once 'includes/functions.php';
require_once 'includes/mail_config.php';

echo "<h1>Teste de Envio de Email</h1>";

// 1. Verificar se as variáveis de ambiente estão a ser lidas
$user = getenv('GMAIL_USER') ?: 'Não definida';
$pass = getenv('GMAIL_PASSWORD') ? '****** (Definida)' : 'Não definida';

echo "<p><strong>Configuração:</strong></p>";
echo "<ul>";
echo "<li>Utilizador: $user</li>";
echo "<li>Palavra-passe: $pass</li>";
echo "</ul>";

if ($user === 'Não definida' || $pass === 'Não definida') {
    echo "<p style='color:red;'>⚠️ Erro: As variáveis de ambiente não estão configuradas no Render!</p>";
    exit;
}

// 2. Tentar enviar o email
echo "<p>A tentar enviar email para <strong>$user</strong>...</p>";

$assunto = "Teste do Sistema — Manual 3D";
$corpo   = "<h2>Olá!</h2><p>Se estás a ler isto, o motor SMTP no Render está <strong>operacional</strong>.</p><hr><p>Manual de Impressão 3D</p>";

$resultado = sendEmail($user, "Admin Manual", $assunto, $corpo);

if ($resultado) {
    echo "<h2 style='color:green;'>✅ SUCESSO!</h2>";
    echo "<p>O email foi aceite pelo servidor da Google. Verifica a tua caixa de entrada (e a pasta de spam).</p>";
} else {
    echo "<h2 style='color:red;'>❌ FALHOU</h2>";
    echo "<p>O envio falhou. Verificaste se a 'Palavra-passe de App' está correta e sem espaços?</p>";
    echo "<p>Consulta os <strong>Logs</strong> no painel do Render para ver o erro detalhado do PHPMailer.</p>";
}

echo "<br><a href='index.php'>Voltar ao Início</a>";
