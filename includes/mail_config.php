<?php
/**
 * Envio de Email via Gmail SMTP — PHPMailer (sem Composer)
 *
 * Estrutura de ficheiros:
 *   includes/
 *     mail_config.php
 *     PHPMailer/ (Exception.php, PHPMailer.php, SMTP.php)
 */

// Carregar PHPMailer (sem Composer)
require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// ─── CREDENCIAIS GMAIL (VIA ENV VARS NO RENDER) ───────────────
define('MAIL_HOST',     'smtp.gmail.com');
define('MAIL_USERNAME', getenv('GMAIL_USER') ?: '3d.escolas@gmail.com');
define('MAIL_PASSWORD', getenv('GMAIL_PASSWORD')); // Definir no Render (Palavra-passe de App)
define('MAIL_PORT',     587);
define('MAIL_FROM_NAME','Manual Impressão 3D');
// ──────────────────────────────────────────────────────────────

/**
 * Envia um email via Gmail SMTP.
 */
function sendEmail(
    string $toEmail,
    string $toName,
    string $subject,
    string $bodyHtml,
    string $bodyText = null
): bool {
    $mail = new PHPMailer(true);

    try {
        // ── Servidor SMTP ──
        $mail->isSMTP();
        // Forçar IPv4 se houver problemas de rede (smtp.gmail.com resolve para IPv6 às vezes)
        $mail->Host       = gethostbyname(MAIL_HOST);
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL Direto
        $mail->Port       = 465;
        $mail->CharSet    = 'UTF-8';

        // Opções de SSL para ambientes Cloud (Render/Docker)
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];

        // ── Remetente e destinatário ──
        $mail->setFrom(MAIL_USERNAME, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $toName);
        $mail->addReplyTo(MAIL_USERNAME, MAIL_FROM_NAME);

        // ── Conteúdo ──
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $bodyHtml;
        $mail->AltBody = $bodyText ?? strip_tags($bodyHtml);

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log('Erro ao enviar email para ' . $toEmail . ': ' . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Email de boas-vindas após registo.
 */
function sendWelcomeEmail(string $toEmail, string $toName): bool {
    $subject = 'Bem-vindo ao Manual de Impressão 3D!';

    $html = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
        <h2 style='color: #00e5ff;'>Olá, {$toName}! 👋</h2>
        <p>A tua conta foi criada com sucesso no <strong>Manual de Impressão 3D</strong>.</p>
        <p>Já podes aceder a todo o conteúdo do manual, guardar favoritos e deixar comentários.</p>
        <br>
        <a href='https://manual-impressao-3d.onrender.com'
           style='background:#00e5ff; color:#000; padding:12px 24px;
                  text-decoration:none; border-radius:6px; font-weight:bold;'>
            Ir para o Manual
        </a>
        <br><br>
        <p style='color:#888; font-size:12px;'>
            Se não criaste esta conta, podes ignorar este email.
        </p>
    </div>";

    return sendEmail($toEmail, $toName, $subject, $html);
}

/**
 * Email de recuperação de password.
 */
function sendPasswordResetEmail(string $toEmail, string $toName, string $resetToken, string $resetUrl): bool {
    $subject = 'Recuperação de Password — Manual 3D';

    $html = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
        <h2 style='color: #ff6b35;'>Recuperação de Password</h2>
        <p>Olá <strong>{$toName}</strong>,</p>
        <p>Recebemos um pedido para redefinir a tua password.</p>
        <p>Clica no botão abaixo — o link é válido durante <strong>1 hora</strong>.</p>
        <br>
        <a href='{$resetUrl}'
           style='background:#ff6b35; color:#fff; padding:12px 24px;
                  text-decoration:none; border-radius:6px; font-weight:bold;'>
            Redefinir Password
        </a>
        <br><br>
        <p style='color:#888; font-size:12px;'>
            Se não pediste a recuperação, ignora este email — a tua conta está segura.
        </p>
    </div>";

    return sendEmail($toEmail, $toName, $subject, $html);
}
