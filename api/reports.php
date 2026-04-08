<?php
/**
 * api/reports.php — API de reports de utilizadores
 * Usa a tabela reported_users (compatível com moderacao.php)
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mail_config.php';

header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

if (!verifyCSRFToken($input['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Token de segurança inválido.']);
    exit;
}

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Precisas de estar logado para reportar.']);
    exit;
}

$user = getCurrentUser();
$db   = getDB();

// Garantir tabela (estrutura idêntica à do moderacao.php)
try {
    $db->exec("CREATE TABLE IF NOT EXISTS reported_users (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        user_id      INT NOT NULL,
        reported_by  INT NOT NULL,
        reason       VARCHAR(255) DEFAULT NULL,
        description  TEXT         DEFAULT NULL,
        status       ENUM('pendente','analisado','resolvido') DEFAULT 'pendente',
        action_taken ENUM('none','warning','suspension','ban') DEFAULT 'none',
        created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        resolved_at  TIMESTAMP    NULL,
        resolved_by  INT          NULL,
        FOREIGN KEY (user_id)     REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (reported_by) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) {}

$action = $input['action'] ?? '';

switch ($action) {

    case 'report_user':
        $reportedId  = (int)($input['reported_id']  ?? 0);
        $reason      = trim($input['reason']         ?? '');
        $description = trim($input['description']    ?? '');

        if ($reportedId < 1) {
            echo json_encode(['success' => false, 'error' => 'Utilizador inválido.']);
            exit;
        }
        if ((int)$user['id'] === $reportedId) {
            echo json_encode(['success' => false, 'error' => 'Não podes reportar-te a ti mesmo.']);
            exit;
        }

        $validReasons = ['conteudo_obsceno', 'linguagem_ofensiva', 'spam', 'informacao_falsa', 'outro'];
        if (!in_array($reason, $validReasons)) {
            echo json_encode(['success' => false, 'error' => 'Seleciona um motivo válido.']);
            exit;
        }

        // Bloquear reports duplicados nas últimas 24h
        $check = $db->prepare(
            "SELECT id FROM reported_users
             WHERE reported_by = ? AND user_id = ?
               AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
        $check->execute([(int)$user['id'], $reportedId]);
        if ($check->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Já reportaste este utilizador nas últimas 24 horas.']);
            exit;
        }

        $reasonLabels = [
            'conteudo_obsceno'   => 'Conteúdo obsceno',
            'linguagem_ofensiva' => 'Linguagem ofensiva',
            'spam'               => 'Spam',
            'informacao_falsa'   => 'Informação falsa',
            'outro'              => 'Outro',
        ];
        $reasonLabel = $reasonLabels[$reason] ?? $reason;

        // Inserir
        $db->prepare(
            "INSERT INTO reported_users (user_id, reported_by, reason, description)
             VALUES (?, ?, ?, ?)"
        )->execute([$reportedId, (int)$user['id'], $reasonLabel, $description ?: null]);

        logActivity((int)$user['id'], 'user_reported', "reported_id={$reportedId},reason={$reason}");

        // Buscar nome do reportado
        $stmt = $db->prepare("SELECT full_name FROM users WHERE id = ?");
        $stmt->execute([$reportedId]);
        $reportedName = ($stmt->fetch())['full_name'] ?? 'Utilizador';

        // Email para admins/moderadores
        $admins = $db->query(
            "SELECT email, full_name FROM users
             WHERE role IN ('admin','moderator') AND is_active = TRUE"
        )->fetchAll();

        foreach ($admins as $admin) {
            $descHtml = $description
                ? "<tr style='background:#1a1a26'><td style='padding:8px;color:#888'>Descrição</td><td style='padding:8px;color:#ccc'>" . htmlspecialchars($description) . "</td></tr>"
                : '';
            $html = "
            <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;background:#0a0a0f;padding:32px;border-radius:12px'>
                <h3 style='color:#ff6b35;margin-bottom:20px'>⚠️ Novo Report de Utilizador</h3>
                <table style='width:100%;border-collapse:collapse;margin-bottom:24px'>
                    <tr>
                        <td style='padding:10px;color:#888;width:150px'>Reportado por</td>
                        <td style='padding:10px;color:#e8e8f0'>{$user['full_name']}</td>
                    </tr>
                    <tr style='background:#1a1a26'>
                        <td style='padding:10px;color:#888'>Utilizador reportado</td>
                        <td style='padding:10px;color:#e8e8f0'>{$reportedName}</td>
                    </tr>
                    <tr>
                        <td style='padding:10px;color:#888'>Motivo</td>
                        <td style='padding:10px;color:#ff6b35'><strong>{$reasonLabel}</strong></td>
                    </tr>
                    {$descHtml}
                </table>
                <a href='https://manual-impressao-3d.free.nf/moderacao.php'
                   style='display:inline-block;background:#ff6b35;color:#000;padding:12px 28px;
                          text-decoration:none;border-radius:8px;font-weight:bold'>
                    🛡️ Ver Painel de Moderação
                </a>
                <p style='color:#555;font-size:12px;margin-top:28px'>Manual de Impressão 3D</p>
            </div>";

            sendEmail(
                $admin['email'],
                $admin['full_name'],
                "⚠️ Utilizador reportado: {$reportedName}",
                $html
            );
        }

        echo json_encode([
            'success' => true,
            'message' => 'Report enviado. A equipa de moderação irá analisar.'
        ]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Ação desconhecida.']);
}
