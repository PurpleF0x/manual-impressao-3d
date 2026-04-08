<?php
/**
 * includes/comments.php
 * Funções auxiliares para o sistema de comentários
 */

function createComment(int $userId, string $content, string $category = 'geral', string $section = 'geral', ?int $parentId = null): int|false {
    $db = getDB();
    if ($parentId !== null) {
        $check = $db->prepare("SELECT id, status FROM comments WHERE id = ?");
        $check->execute([$parentId]);
        $parent = $check->fetch();
        if (!$parent || $parent['status'] !== 'aprovado') return false;
    }
    $stmt = $db->prepare(
        "INSERT INTO comments (user_id, parent_id, section, category, content, status)
         VALUES (?, ?, ?, ?, ?, 'pendente')"
    );
    $stmt->execute([$userId, $parentId, $section, $category, $content]);
    return (int) $db->lastInsertId();
}

function approveComment(int $commentId, int $reviewerId): bool {
    $db = getDB();
    $stmt = $db->prepare(
        "UPDATE comments SET status='aprovado', reviewed_at=NOW(), reviewed_by=?
         WHERE id=? AND status='pendente'"
    );
    $stmt->execute([$reviewerId, $commentId]);
    if ($stmt->rowCount() > 0) {
        $info = $db->prepare("SELECT user_id FROM comments WHERE id=?");
        $info->execute([$commentId]);
        $row = $info->fetch();
        if ($row) {
            createNotification($row['user_id'], 'comment_approved', $commentId,
                'O teu comentário foi aprovado e já está visível.');
        }
        return true;
    }
    return false;
}

function rejectComment(int $commentId, int $reviewerId): bool {
    $db = getDB();
    $stmt = $db->prepare(
        "UPDATE comments SET status='rejeitado', reviewed_at=NOW(), reviewed_by=?
         WHERE id=? AND status='pendente'"
    );
    $stmt->execute([$reviewerId, $commentId]);
    if ($stmt->rowCount() > 0) {
        $info = $db->prepare("SELECT user_id FROM comments WHERE id=?");
        $info->execute([$commentId]);
        $row = $info->fetch();
        if ($row) {
            createNotification($row['user_id'], 'comment_rejected', $commentId,
                'O teu comentário foi rejeitado pelo moderador.');
        }
        return true;
    }
    return false;
}

function getApprovedComments(string $section = 'geral', int $limit = 50, int $offset = 0): array {
    $db = getDB();
    try {
        $stmt = $db->prepare(
            "SELECT c.*, u.full_name, u.username, u.avatar_url, u.avatar,
                    (SELECT COUNT(*) FROM comment_likes cl WHERE cl.comment_id = c.id) AS like_count
             FROM comments c
             JOIN users u ON u.id = c.user_id
             WHERE c.status = 'aprovado' AND c.parent_id IS NULL AND c.section = ?
             ORDER BY c.created_at DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute([$section, $limit, $offset]);
        $comments = $stmt->fetchAll();
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Unknown column') !== false) {
            $stmt = $db->prepare(
                "SELECT c.*, u.full_name, u.username, u.avatar_url, u.avatar,
                        (SELECT COUNT(*) FROM comment_likes cl WHERE cl.comment_id = c.id) AS like_count
                 FROM comments c
                 JOIN users u ON u.id = c.user_id
                 WHERE c.status = 'aprovado' AND c.section = ?
                 ORDER BY c.created_at DESC
                 LIMIT ? OFFSET ?"
            );
            $stmt->execute([$section, $limit, $offset]);
            $comments = $stmt->fetchAll();
            foreach ($comments as &$c) { $c['parent_id'] = null; $c['replies'] = []; }
        } else { throw $e; }
    }

    foreach ($comments as &$comment) {
        try {
            $rStmt = $db->prepare(
                "SELECT c.*, u.full_name, u.username, u.avatar_url, u.avatar,
                        (SELECT COUNT(*) FROM comment_likes cl WHERE cl.comment_id = c.id) AS like_count
                 FROM comments c
                 JOIN users u ON u.id = c.user_id
                 WHERE c.status = 'aprovado' AND c.parent_id = ?
                 ORDER BY c.created_at ASC"
            );
            $rStmt->execute([$comment['id']]);
            $comment['replies'] = $rStmt->fetchAll();
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Unknown column') !== false) {
                $comment['replies'] = [];
            } else { throw $e; }
        }
    }
    return $comments;
}

function getPendingComments(int $limit = 100): array {
    $db = getDB();
    $stmt = $db->prepare(
        "SELECT c.*, u.full_name, u.username, u.avatar_url, u.avatar
         FROM comments c
         JOIN users u ON u.id = c.user_id
         WHERE c.status = 'pendente'
         ORDER BY c.created_at ASC
         LIMIT ?"
    );
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

function getUserComments(int $userId, int $limit = 50): array {
    $db = getDB();
    try {
        $stmt = $db->prepare(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM comment_likes cl WHERE cl.comment_id = c.id) AS like_count,
                    p.full_name AS parent_author
             FROM comments c
             LEFT JOIN comments pc ON pc.id = c.parent_id
             LEFT JOIN users p ON p.id = pc.user_id
             WHERE c.user_id = ? AND c.status = 'aprovado'
             ORDER BY c.created_at DESC
             LIMIT ?"
        );
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Unknown column') !== false) {
            $stmt = $db->prepare(
                "SELECT c.*,
                        (SELECT COUNT(*) FROM comment_likes cl WHERE cl.comment_id = c.id) AS like_count
                 FROM comments c
                 WHERE c.user_id = ? AND c.status = 'aprovado'
                 ORDER BY c.created_at DESC
                 LIMIT ?"
            );
            $stmt->execute([$userId, $limit]);
            $results = $stmt->fetchAll();
            foreach ($results as &$r) { $r['parent_id'] = null; $r['parent_author'] = null; }
            return $results;
        }
        throw $e;
    }
}

function toggleCommentLike(int $commentId, int $userId): array {
    $db = getDB();
    $check = $db->prepare("SELECT id FROM comment_likes WHERE comment_id=? AND user_id=?");
    $check->execute([$commentId, $userId]);
    if ($check->fetch()) {
        $db->prepare("DELETE FROM comment_likes WHERE comment_id=? AND user_id=?")->execute([$commentId, $userId]);
        $liked = false;
    } else {
        $db->prepare("INSERT INTO comment_likes (comment_id, user_id) VALUES (?,?)")->execute([$commentId, $userId]);
        $liked = true;
    }
    $count = $db->prepare("SELECT COUNT(*) FROM comment_likes WHERE comment_id=?");
    $count->execute([$commentId]);
    return ['liked' => $liked, 'count' => (int) $count->fetchColumn()];
}

function createNotification(int $userId, string $type, int $commentId, string $message): void {
    try {
        $db = getDB();
        $db->prepare(
            "INSERT INTO notifications (user_id, type, comment_id, message) VALUES (?,?,?,?)"
        )->execute([$userId, $type, $commentId, $message]);
    } catch (Exception $e) {
        error_log("Erro ao criar notificação: " . $e->getMessage());
    }
}

function sendEmailNotification(int $userId, string $subject, string $body): void {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT email, full_name FROM users WHERE id=?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if (!$user || empty($user['email'])) return;
        $headers  = "From: noreply@manual3d.local\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $fullBody = "Olá {$user['full_name']},\r\n\r\n{$body}\r\n\r\n---\r\nManual de Impressão 3D";
        @mail($user['email'], $subject, $fullBody, $headers);
    } catch (Exception $e) {
        error_log("Erro ao enviar email: " . $e->getMessage());
    }
}

function getUnreadNotifications(int $userId): array {
    $db = getDB();
    $stmt = $db->prepare(
        "SELECT * FROM notifications WHERE user_id=? AND is_read=0 ORDER BY created_at DESC LIMIT 20"
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function markNotificationsRead(int $userId): void {
    $db = getDB();
    $db->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$userId]);
}

/**
 * Verifica se utilizador pode moderar.
 * Inclui master, admin e moderator.
 */
if (!function_exists('canModerate')) {
    function canModerate($user): bool {
        return in_array($user['role'] ?? '', ['master', 'admin', 'moderator'], true);
    }
}