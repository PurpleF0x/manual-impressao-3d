<?php
/**
 * Envio de Email via Resend API (HTTP)
 * Substitui o PHPMailer para evitar bloqueios de porta SMTP no Render.
 */

define('RESEND_API_KEY', getenv('RESEND_API_KEY'));
define('MAIL_FROM',      'onboarding@resend.dev'); // Email padrão de teste do Resend
define('MAIL_FROM_NAME', 'Manual Impressão 3D');

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

    if (!$apiKey) {
        error_log("Erro: RESEND_API_KEY não configurada.");
        return false;
    }

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

    if ($httpCode >= 200 && $httpCode < 300) {
        return true;
    } else {
        error_log("Erro Resend API ($httpCode): " . $response);
        return false;
    }
}

/**
 * Email de boas-vindas após registo.
 */
function sendWelcomeEmail(string $toEmail, string $toName): bool {
    $subject = 'Bem-vindo ao Manual de Impressão 3D!';
    $html = "
    <div style='font-family: sans-serif; max-width: 600px; margin: 0 auto; color: #333;'>
        <h2 style='color: #00e5ff;'>Olá, {$toName}! 👋</h2>
        <p>A tua conta foi criada com sucesso no <strong>Manual de Impressão 3D</strong>.</p>
        <p>Já podes aceder a todo o conteúdo, guardar favoritos e deixar comentários.</p>
        <br>
        <a href='https://manual-impressao-3d.onrender.com'
           style='background:#00e5ff; color:#000; padding:12px 24px;
                  text-decoration:none; border-radius:6px; font-weight:bold; display:inline-block;'>
            Ir para o Manual
        </a>
    </div>";

    return sendEmail($toEmail, $toName, $subject, $html);
}

/**
 * Email de recuperação de password.
 */
function sendPasswordResetEmail(string $toEmail, string $toName, string $resetToken, string $resetUrl): bool {
    $subject = 'Recuperação de Password — Manual 3D';
    $html = "
    <div style='font-family: sans-serif; max-width: 600px; margin: 0 auto; color: #333;'>
        <h2 style='color: #ff6b35;'>Recuperação de Password</h2>
        <p>Olá <strong>{$toName}</strong>,</p>
        <p>Recebemos um pedido para redefinir a tua password.</p>
        <p>Clica no botão abaixo — o link é válido durante 1 hora.</p>
        <br>
        <a href='{$resetUrl}'
           style='background:#ff6b35; color:#fff; padding:12px 24px;
                  text-decoration:none; border-radius:6px; font-weight:bold; display:inline-block;'>
            Redefinir Password
        </a>
    </div>";

    return sendEmail($toEmail, $toName, $subject, $html);
}
