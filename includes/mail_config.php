<?php
/**
 * Envio de Email via Resend SMTP — PHPMailer (sem Composer)
 *
 * Estrutura de ficheiros necessária no teu site:
 *   includes/
 *     mail_config.php   ← este ficheiro
 *     PHPMailer/
 *       Exception.php
 *       PHPMailer.php
 *       SMTP.php
 *
 * Como instalar o PHPMailer manualmente:
 *   1. Vai a https://github.com/PHPMailer/PHPMailer
 *   2. Clica em Code → Download ZIP e extrai
 *   3. Dentro do ZIP, entra na pasta "src/"
 *   4. Copia os 3 ficheiros (Exception.php, PHPMailer.php, SMTP.php)
 *      para a pasta "includes/PHPMailer/" no teu site
 */

// Carregar PHPMailer (sem Composer)
require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// ─── CREDENCIAIS RESEND ───────────────────────────────────────
define('MAIL_FROM',     'onboarding@resend.dev');  // Remetente (não precisas de verificar domínio)
define('MAIL_FROM_NAME','Manual Impressão 3D');     // Nome que aparece no email
define('MAIL_PASS',     're_A1x7daYu_C3d5cT95LKoS3LkXs8PcJUSY');     // A tua API Key do Resend (começa por "re_")
define('MAIL_REPLY_TO', '3d.escolas@gmail.com');   // Respostas chegam ao teu Gmail
// ──────────────────────────────────────────────────────────────

/**
 * Envia um email via Resend SMTP.
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
        $mail->Host       = 'smtp.resend.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'resend';   // Sempre "resend" — não mudes isto
        $mail->Password   = MAIL_PASS;  // A tua API Key (re_...)
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        // ── Remetente e destinatário ──
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $toName);
        $mail->addReplyTo(MAIL_REPLY_TO, MAIL_FROM_NAME);

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
        <a href='https://o-teu-site.infinityfreeapp.com'
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
 * $resetUrl é construída no recuperar_password.php com o host correto.
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
