<?php
/**
 * api/comments.php — API de comentários
 * Aceita POST JSON. GET suportado apenas para 'list' e 'stats'.
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/comments.php';
require_once __DIR__ . '/../includes/mail_config.php';

header('Content-Type: application/json; charset=utf-8');

// ─── PERSPECTIVE API ──────────────────────────────────────────
// Obtém a chave em: https://perspectiveapi.com → Get started
define('PERSPECTIVE_API_KEY', 'AIzaSyArbfs-DkYAm8xyGIXmTXjwK1oI2FYm3qo');

/**
 * Analisa o texto com a Perspective API.
 * Devolve o score de toxicidade (0.0 a 1.0) ou -1 em caso de erro.
 */
function getToxicityScore(string $text): float {
    if (PERSPECTIVE_API_KEY === 'AIzaSyArbfs-DkYAm8xyGIXmTXjwK1oI2FYm3qo') {
        return -1; // API não configurada — deixa passar para moderação humana
    }

    $payload = json_encode([
        'comment'             => ['text' => $text],
        'languages'           => ['pt'],
        'requestedAttributes' => ['TOXICITY' => (object)[]]
    ]);

    $ch = curl_init(
        'https://commentanalyzer.googleapis.com/v1alpha1/comments:analyze?key=' . PERSPECTIVE_API_KEY
    );
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 5,
    ]);

    $response  = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError || !$response) {
        error_log('Perspective API erro: ' . $curlError);
        return -1;
    }

    $data = json_decode($response, true);
    return (float)($data['attributeScores']['TOXICITY']['summaryScore']['value'] ?? -1);
}

/**
 * Decide o estado do comentário com base no score:
 *
 *  score < 0    → API indisponível  → pendente (moderação humana)
 *  0.00 – 0.59  → conteúdo normal  → aprovado automaticamente
 *  0.60 – 0.84  → conteúdo duvidoso → pendente (moderação humana)
 *  ≥ 0.85       → conteúdo ofensivo → bloqueado (não guarda)
 */
function evaluateToxicity(float $score): string {
    if ($score < 0)    return 'pendente';
    if ($score < 0.50) return 'aprovado';
    if ($score < 0.65) return 'pendente';
    return 'bloqueado';
}
// ─────────────────────────────────────────────────────────────

$input  = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;
$action = $input['action'] ?? $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && !in_array($action, ['list','stats'], true)
    && !verifyCSRFToken($input['csrf_token'] ?? null)
) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Token de segurança inválido.']);
    exit;
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'error' => 'Precisas de estar logado.']);
        exit;
    }
}

$user = isLoggedIn() ? getCurrentUser() : null;

switch ($action) {

    // ── Criar comentário / resposta ──────────────────────────────
    case 'create':
    case 'add':
        requireLogin();
        $content  = trim($input['content'] ?? '');
        $category = $input['category'] ?? 'geral';
        $section  = $input['section']  ?? 'geral';
        $parentId = !empty($input['parent_id']) ? (int)$input['parent_id'] : null;

        if (strlen($content) < 3) {
            echo json_encode(['success' => false, 'error' => 'O comentário é demasiado curto (mínimo 3 caracteres).']);
            exit;
        }
        if (strlen($content) > 2000) {
            echo json_encode(['success' => false, 'error' => 'O comentário não pode ultrapassar 2000 caracteres.']);
            exit;
        }
        if (!in_array($category, ['duvida','problema','dica','geral'], true)) $category = 'geral';

        // ── Lista negra de conteúdo proibido ───────────────────
        $blacklist = [
            'pedofilia', 'pedófilo', 'pedofilo', 'pedo',
            'abuso infantil', 'abuso de menores',
            'nazi', 'nazista',
            'nigger', 'nigga',
            'violação', 'violar',
            'suicídio', 'suicidio', 'matar-me', 'matar-se',
        ];
        $contentLower = mb_strtolower($content, 'UTF-8');
        foreach ($blacklist as $word) {
            if (str_contains($contentLower, $word)) {
                logActivity((int)$user['id'], 'comment_blocked_blacklist', "word={$word}");
                echo json_encode(['success' => false, 'error' => '⚠️ O teu comentário foi bloqueado por conter conteúdo proibido.']);
                exit;
            }
        }
        // ──────────────────────────────────────────────────────

        // ── Verificação de toxicidade ──────────────────────────
        $score     = getToxicityScore($content);
        $toxResult = evaluateToxicity($score);

        if ($toxResult === 'bloqueado') {
            echo json_encode([
                'success' => false,
                'error'   => '⚠️ O teu comentário foi bloqueado por conter linguagem ofensiva. Por favor reformula a mensagem.'
            ]);
            exit;
        }

        // Guardar com o estado decidido pela IA
        $id = createCommentWithStatus((int)$user['id'], $content, $category, $section, $parentId, $toxResult);

        if ($id === false) {
            echo json_encode(['success' => false, 'error' => 'Não foi possível criar o comentário. O comentário pai pode não estar aprovado.']);
            exit;
        }

        // Atribuição de Recompensa se aprovado automaticamente pela IA
        if ($toxResult === 'aprovado') {
            addXP((int)$user['id'], 5, "Comentário aprovado automaticamente: #$id", 3);
        }

        $message = $toxResult === 'aprovado'
            ? '✅ Comentário publicado com sucesso!'
            : '⏳ Comentário submetido! Ficará visível após moderação.';

        // Notificar moderadores só se ficou pendente
        if ($toxResult === 'pendente') {
            notifyModerators(
                'Novo comentário pendente',
                "{$user['full_name']} submeteu um comentário que aguarda moderação (ID #{$id})."
            );
        }

        // Notificar autor do comentário pai (se for resposta)
        if ($parentId !== null) {
            $db = getDB();
            $pr = $db->prepare('SELECT user_id FROM comments WHERE id = ?');
            $pr->execute([$parentId]);
            $pRow = $pr->fetch();
            if ($pRow && (int)$pRow['user_id'] !== (int)$user['id']) {
                $replyMsg = $toxResult === 'aprovado'
                    ? "{$user['full_name']} respondeu ao teu comentário."
                    : "{$user['full_name']} respondeu ao teu comentário. A resposta ficará visível após moderação.";
                createNotification((int)$pRow['user_id'], 'comment_reply', $id, $replyMsg);
                sendEmailNotificationResend((int)$pRow['user_id'], 'Alguém respondeu ao teu comentário', $replyMsg);
            }
        }

        logActivity((int)$user['id'], 'comment_created', "comment_id={$id},status={$toxResult}");
        echo json_encode(['success' => true, 'message' => $message, 'id' => $id, 'status' => $toxResult]);
        break;

    // ── Aprovar ──────────────────────────────────────────────────
    case 'approve':
        requireLogin();
        if (!canModerate($user)) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'Sem permissão.']); exit; }
        $cid = (int)($input['comment_id'] ?? 0);
        if ($cid < 1) { echo json_encode(['success'=>false,'error'=>'ID inválido.']); exit; }
        if (approveComment($cid, (int)$user['id'])) {
            $db  = getDB();
            $row = $db->prepare('SELECT user_id FROM comments WHERE id = ?');
            $row->execute([$cid]);
            $r = $row->fetch();
            if ($r) {
                // Atribuição de Recompensa (XP/Coins) ao ser aprovado manualmente
                addXP((int)$r['user_id'], 5, "Comentário aprovado: #$cid", 3);

                sendEmailNotificationResend(
                    (int)$r['user_id'],
                    'O teu comentário foi aprovado!',
                    'O teu comentário foi aprovado e já está visível na comunidade. Obrigado pela tua contribuição!'
                );
            }
            logActivity((int)$user['id'], 'comment_approved', "comment_id={$cid}");
            echo json_encode(['success'=>true,'message'=>'Comentário aprovado.']);
        } else {
            echo json_encode(['success'=>false,'error'=>'Comentário não encontrado ou já processado.']);
        }
        break;

    // ── Rejeitar ─────────────────────────────────────────────────
    case 'reject':
        requireLogin();
        if (!canModerate($user)) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'Sem permissão.']); exit; }
        $cid = (int)($input['comment_id'] ?? 0);
        if ($cid < 1) { echo json_encode(['success'=>false,'error'=>'ID inválido.']); exit; }
        if (rejectComment($cid, (int)$user['id'])) {
            $db  = getDB();
            $row = $db->prepare('SELECT user_id FROM comments WHERE id = ?');
            $row->execute([$cid]);
            $r = $row->fetch();
            if ($r) {
                sendEmailNotificationResend(
                    (int)$r['user_id'],
                    'O teu comentário foi rejeitado',
                    'O teu comentário foi rejeitado pelo moderador por não cumprir as regras da comunidade.'
                );
            }
            logActivity((int)$user['id'], 'comment_rejected', "comment_id={$cid}");
            echo json_encode(['success'=>true,'message'=>'Comentário rejeitado.']);
        } else {
            echo json_encode(['success'=>false,'error'=>'Comentário não encontrado ou já processado.']);
        }
        break;

    // ── Like ─────────────────────────────────────────────────────
    case 'like':
        requireLogin();
        $cid = (int)($input['comment_id'] ?? 0);
        if ($cid < 1) { echo json_encode(['success'=>false,'error'=>'ID inválido.']); exit; }
        $result = toggleCommentLike($cid, (int)$user['id']);

        // Atribuição de Recompensa ao autor do comentário por receber um like
        if ($result['liked']) {
            $db = getDB();
            $stmt = $db->prepare("SELECT user_id FROM comments WHERE id = ?");
            $stmt->execute([$cid]);
            $author = $stmt->fetch();
            if ($author && (int)$author['user_id'] !== (int)$user['id']) {
                addXP((int)$author['user_id'], 3, "Recebeu Like no comentário #$cid", 2);
            }
        }

        echo json_encode(['success'=>true,'liked'=>$result['liked'],'count'=>$result['count']]);
        break;

    // ── Listar comentários aprovados ─────────────────────────────
    case 'list':
        $section  = $input['section'] ?? $_GET['section'] ?? 'geral';
        $limit    = min((int)($input['limit']  ?? $_GET['limit']  ?? 20), 100);
        $offset   = max((int)($input['offset'] ?? $_GET['offset'] ?? 0),  0);
        $comments = getApprovedComments($section, $limit, $offset);

        if ($user && $comments) {
            $db = getDB();
            $lk = $db->prepare('SELECT id FROM comment_likes WHERE comment_id=? AND user_id=?');
            foreach ($comments as &$c) {
                $lk->execute([$c['id'], $user['id']]);
                $c['user_liked'] = (bool)$lk->fetch();
                foreach ($c['replies'] as &$r) {
                    $lk->execute([$r['id'], $user['id']]);
                    $r['user_liked'] = (bool)$lk->fetch();
                }
                unset($r);
            }
            unset($c);
        } else {
            foreach ($comments as &$c) {
                $c['user_liked'] = false;
                foreach ($c['replies'] as &$r) { $r['user_liked'] = false; } unset($r);
            }
            unset($c);
        }
        echo json_encode(['success'=>true,'comments'=>$comments]);
        break;

    // ── Listar pendentes (moderação) ─────────────────────────────
    case 'list_pending':
        requireLogin();
        if (!canModerate($user)) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'Sem permissão.']); exit; }
        $pending = getPendingComments();
        echo json_encode(['success'=>true,'comments'=>$pending,'total'=>count($pending)]);
        break;

    // ── Comentários de um utilizador (perfil) ────────────────────
    case 'list_user':
        $uid = (int)($input['user_id'] ?? $_GET['user_id'] ?? 0);
        if ($uid < 1) { echo json_encode(['success'=>false,'error'=>'ID de utilizador inválido.']); exit; }
        $comments = getUserComments($uid, (int)($input['limit'] ?? 50));
        echo json_encode(['success'=>true,'comments'=>$comments]);
        break;

    // ── Marcar notificações como lidas ───────────────────────────
    case 'mark_notifications_read':
        if ($user) markNotificationsRead((int)$user['id']);
        echo json_encode(['success'=>true]);
        break;

    // ── Estatísticas ─────────────────────────────────────────────
    case 'stats':
        echo json_encode(['success'=>true,'stats'=>getStats()]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success'=>false,'error'=>'Ação desconhecida: ' . htmlspecialchars($action)]);
}

/**
 * Cria comentário com estado definido pela IA.
 */
function createCommentWithStatus(int $userId, string $content, string $category, string $section, ?int $parentId, string $status): int|false {
    $db = getDB();
    if ($parentId !== null) {
        $check = $db->prepare("SELECT id, status FROM comments WHERE id = ?");
        $check->execute([$parentId]);
        $parent = $check->fetch();
        if (!$parent || $parent['status'] !== 'aprovado') return false;
    }
    $stmt = $db->prepare(
        "INSERT INTO comments (user_id, parent_id, section, category, content, status)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$userId, $parentId, $section, $category, $content, $status]);
    return (int) $db->lastInsertId();
}

/**
 * Envia email de notificação via Resend (substitui o @mail() antigo).
 */
function sendEmailNotificationResend(int $userId, string $subject, string $body): void {
    try {
        $db   = getDB();
        $stmt = $db->prepare("SELECT email, full_name FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $u = $stmt->fetch();
        if (!$u || empty($u['email'])) return;

        $html = "
        <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>
            <h3 style='color:#00e5ff;'>Olá, {$u['full_name']}!</h3>
            <p style='color:#333;line-height:1.6;'>{$body}</p>
            <br>
            <a href='https://manual-impressao-3d.free.nf'
               style='background:#00e5ff;color:#000;padding:10px 22px;
                      text-decoration:none;border-radius:6px;font-weight:bold;'>
                Ir para o Manual
            </a>
            <br><br>
            <p style='color:#888;font-size:12px;'>Manual de Impressão 3D</p>
        </div>";

        sendEmail($u['email'], $u['full_name'], $subject, $html);
    } catch (Exception $e) {
        error_log('Erro ao enviar email: ' . $e->getMessage());
    }
}

/**
 * Notifica todos os admins/moderadores via Resend.
 */
function notifyModerators(string $subject, string $body): void {
    try {
        $db   = getDB();
        $stmt = $db->query("SELECT id FROM users WHERE role IN ('admin','moderator') AND is_active = TRUE");
        foreach ($stmt->fetchAll() as $mod) {
            sendEmailNotificationResend((int)$mod['id'], $subject, $body);
        }
    } catch (Exception $e) {
        error_log('Erro ao notificar moderadores: ' . $e->getMessage());
    }
}