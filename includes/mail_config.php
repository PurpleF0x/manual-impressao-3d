<?php
/**
 * Envio de Email via Resend API (HTTP) - Versão Pro com Domínio Personalizado
 */

define('RESEND_API_KEY', getenv('RESEND_API_KEY'));
define('MAIL_FROM',      'no-reply@manual-3d.pt');
define('MAIL_FROM_NAME', 'Manual de Impressão 3D');
define('SITE_URL',       'https://manual-3d.pt'); // O teu novo domínio

/**
 * Envia um email via Resend API usando CURL.
 */
function sendEmail(
    string $toEmail,
    string $toName,
    string $subject,
    string $bodyHtml,
    string $bodyText = null
): bool {
    $apiKey = RESEND_API_KEY;
    if (!$apiKey) return false;

    $payload = [
        'from'    => MAIL_FROM_NAME . ' <' . MAIL_FROM . '>',
        'to'      => [$toEmail],
        'subject' => $subject,
        'html'    => $bodyHtml,
        'text'    => $bodyText ?? strip_tags($bodyHtml),
    ];

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ($httpCode >= 200 && $httpCode < 300);
}

/**
 * Template base para manter a consistência visual
 */
function getEmailTemplate($content) {
    $year = date('Y');
    return "
    <div style='background-color: #0a0a0f; padding: 40px 20px; font-family: sans-serif; color: #e8e8f0;'>
        <div style='max-width: 600px; margin: 0 auto; background: #111118; border: 1px solid #1a1a26; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,0.5);'>
            <div style='padding: 30px; text-align: center; border-bottom: 1px solid #1a1a26;'>
                <h1 style='color: #00e5ff; margin: 0; font-size: 24px; letter-spacing: 1px;'>MANUAL 3D</h1>
            </div>
            <div style='padding: 40px 30px;'>
                {$content}
            </div>
            <div style='padding: 30px; background: #0d0d12; text-align: center; font-size: 12px; color: #888899;'>
                <p style='margin: 0 0 10px;'>&copy; {$year} Manual de Impressão 3D — Guia Educativo</p>
                <p style='margin: 0;'>
                    <a href='" . SITE_URL . "/terms.php' style='color: #888899; text-decoration: underline;'>Termos</a> &bull;
                    <a href='" . SITE_URL . "/privacy.php' style='color: #888899; text-decoration: underline;'>Privacidade</a> &bull;
                    <a href='" . SITE_URL . "/suporte.php' style='color: #888899; text-decoration: underline;'>Suporte</a>
                </p>
            </div>
        </div>
    </div>";
}

/**
 * Email de boas-vindas
 */
function sendWelcomeEmail(string $toEmail, string $toName): bool {
    $subject = "Bem-vindo à revolução 3D, {$toName}! 🚀";
    $content = "
        <h2 style='color: #fff; margin-top: 0;'>Olá, {$toName}! 👋</h2>
        <p style='font-size: 16px; line-height: 1.6;'>A tua jornada no mundo da fabricação aditiva começa agora. A tua conta foi criada com sucesso no <strong>Manual de Impressão 3D</strong>.</p>
        <p style='font-size: 16px; line-height: 1.6;'>Já podes explorar os capítulos, participar no fórum e usar a nossa IA para tirar dúvidas.</p>
        <div style='padding: 30px 0; text-align: center;'>
            <a href='" . SITE_URL . "' style='background: #00e5ff; color: #000; padding: 14px 30px; border-radius: 8px; text-decoration: none; font-weight: bold; font-size: 14px; display: inline-block;'>ACEDER AO MANUAL</a>
        </div>
        <p style='font-size: 14px; color: #888899;'>Se não criaste esta conta, podes ignorar este email.</p>";

    return sendEmail($toEmail, $toName, $subject, getEmailTemplate($content));
}

/**
 * Email de recuperação de password
 */
function sendPasswordResetEmail(string $toEmail, string $toName, string $resetToken, string $resetUrl): bool {
    $subject = "Recuperação de Acesso — Manual 3D";
    $content = "
        <h2 style='color: #fff; margin-top: 0;'>Pedido de Nova Senha</h2>
        <p style='font-size: 16px; line-height: 1.6;'>Recebemos um pedido para redefinir a palavra-passe da tua conta <strong>{$toName}</strong>.</p>
        <p style='font-size: 16px; line-height: 1.6;'>Clica no botão abaixo para escolher uma nova senha. Por questões de segurança, este link expira em 1 hora.</p>
        <div style='padding: 30px 0; text-align: center;'>
            <a href='{$resetUrl}' style='background: #ff6b35; color: #fff; padding: 14px 30px; border-radius: 8px; text-decoration: none; font-weight: bold; font-size: 14px; display: inline-block;'>REDEFINIR PALAVRA-PASSE</a>
        </div>
        <p style='font-size: 14px; color: #888899;'>Se não pediste esta alteração, a tua conta continua segura e podes ignorar este aviso.</p>";

    return sendEmail($toEmail, $toName, $subject, getEmailTemplate($content));
}
