<?php
/**
 * Funções Auxiliares — Manual de Impressão 3D
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start(['cookie_httponly' => true, 'cookie_secure' => false]);
}

require_once __DIR__ . '/../config/database.php';

$_CACHE = [];

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
}

function getCurrentUser(): ?array {
    if (!isLoggedIn()) return null;
    global $_CACHE;
    if (!isset($_CACHE['current_user'])) {
        $db   = getDB();
        $stmt = $db->prepare('SELECT * FROM users WHERE id = ? AND is_active = TRUE LIMIT 1');
        $stmt->execute([$_SESSION['user_id']]);
        $_CACHE['current_user'] = $stmt->fetch() ?: false;
    }
    return $_CACHE['current_user'] ?: null;
}

function isAdmin(): bool {
    $u = getCurrentUser();
    return $u && in_array($u['role'], ['master', 'admin'], true);
}

function isModerator(): bool {
    $u = getCurrentUser();
    return $u && in_array($u['role'], ['master', 'moderator', 'admin'], true);
}

/**
 * Verifica se um utilizador pode moderar (aceita array ou usa currentUser).
 * Usado em comments_component.php e moderacao.php.
 */
function canModerate(?array $user = null): bool {
    if ($user === null) $user = getCurrentUser();
    return $user && in_array($user['role'] ?? '', ['master', 'admin', 'moderator'], true);
}

function generateAvatar(string $name): string {
    $initials = '';
    foreach (explode(' ', trim($name)) as $part) {
        $i = strtoupper(substr($part, 0, 1));
        if ($i !== '') {
            $initials .= $i;
            if (strlen($initials) >= 2) break;
        }
    }
    return substr($initials, 0, 2) ?: '??';
}

function sanitize(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function formatDate(?string $date, string $format = 'd/m/Y H:i'): string {
    if (!$date) return '';
    return date($format, strtotime($date));
}

function truncate(string $text, int $length = 100): string {
    return strlen($text) <= $length ? $text : substr($text, 0, $length) . '…';
}

function generateCSRFToken(): string {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken(?string $token): bool {
    return $token !== null
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function setFlashMessage(string $type, string $message): void {
    $type = in_array($type, ['success', 'error', 'info', 'warning'], true) ? $type : 'info';
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlashMessage(): ?array {
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

function showFlashMessage(): void {
    $flash = getFlashMessage();
    if (!$flash) return;
    $map = ['success'=>'alert-success','error'=>'alert-error','warning'=>'alert-warning','info'=>'alert-info'];
    $cls = $map[$flash['type']] ?? 'alert-info';
    echo '<div class="alert ' . $cls . '">' . sanitize($flash['message']) . '</div>';
}

function redirect(string $url): never {
    if (headers_sent()) {
        echo '<script>window.location.href="' . sanitize($url) . '";</script>';
    } else {
        header('Location: ' . $url);
    }
    exit;
}

function getCommentsCount(?string $category = null): int {
    global $_CACHE;
    $key = 'cmtcount_' . ($category ?? 'all');
    if (isset($_CACHE[$key])) return $_CACHE[$key];
    $db  = getDB();
    $sql = "SELECT COUNT(*) FROM comments WHERE status = 'aprovado' AND parent_id IS NULL";
    $params = [];
    if ($category) { $sql .= ' AND category = ?'; $params[] = $category; }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $count = (int) $stmt->fetchColumn();
    $_CACHE[$key] = $count;
    return $count;
}

function getComments(?string $category = null, int $limit = 20, int $offset = 0): array {
    $db  = getDB();
    $sql = "SELECT c.id, c.user_id, c.category, c.title, c.content,
                   c.status, c.likes, c.created_at, c.updated_at,
                   u.username, u.full_name, u.avatar, u.avatar_url,
                   (SELECT COUNT(*) FROM comments r WHERE r.parent_id = c.id AND r.status = 'aprovado') AS reply_count,
                   (SELECT COUNT(*) FROM comment_likes cl WHERE cl.comment_id = c.id) AS like_count
            FROM comments c
            JOIN users u ON c.user_id = u.id
            WHERE c.status = 'aprovado' AND c.parent_id IS NULL";
    $params = [];
    if ($category && $category !== 'all') {
        $sql .= ' AND c.category = ?';
        $params[] = $category;
    }
    $sql .= ' ORDER BY c.created_at DESC LIMIT ? OFFSET ?';
    $params[] = (int)$limit;
    $params[] = (int)$offset;
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getReplies(int $commentId): array {
    $db   = getDB();
    $stmt = $db->prepare(
        "SELECT c.id, c.user_id, c.parent_id, c.content, c.likes, c.created_at,
                u.username, u.full_name, u.avatar, u.avatar_url,
                (SELECT COUNT(*) FROM comment_likes cl WHERE cl.comment_id = c.id) AS like_count
         FROM comments c
         JOIN users u ON c.user_id = u.id
         WHERE c.parent_id = ? AND c.status = 'aprovado'
         ORDER BY c.created_at ASC"
    );
    $stmt->execute([$commentId]);
    return $stmt->fetchAll();
}

function hasLiked(int $userId, string $targetType, int $targetId): bool {
    $db   = getDB();
    $stmt = $db->prepare(
        'SELECT 1 FROM comment_likes WHERE user_id = ? AND comment_id = ? LIMIT 1'
    );
    $stmt->execute([$userId, $targetId]);
    return $stmt->fetch() !== false;
}

function getStats(): array {
    global $_CACHE;
    if (isset($_CACHE['stats'], $_CACHE['stats_time']) && time() - $_CACHE['stats_time'] < 300) {
        return $_CACHE['stats'];
    }
    try {
        $db = getDB();
        $stats = [
            'total_comments'  => (int)$db->query("SELECT COUNT(*) FROM comments WHERE parent_id IS NULL")->fetchColumn(),
            'total_questions' => (int)$db->query("SELECT COUNT(*) FROM comments WHERE category='duvida' AND parent_id IS NULL")->fetchColumn(),
            'total_approved'  => (int)$db->query("SELECT COUNT(*) FROM comments WHERE status='aprovado' AND parent_id IS NULL")->fetchColumn(),
            'total_pending'   => (int)$db->query("SELECT COUNT(*) FROM comments WHERE status='pendente'")->fetchColumn(),
            'total_users'     => (int)$db->query("SELECT COUNT(*) FROM users WHERE is_active=TRUE")->fetchColumn(),
            'total_replies'   => (int)$db->query("SELECT COUNT(*) FROM comments WHERE parent_id IS NOT NULL AND status='aprovado'")->fetchColumn(),
        ];
        $_CACHE['stats']      = $stats;
        $_CACHE['stats_time'] = time();
        return $stats;
    } catch (Exception $e) {
        error_log('Erro getStats: ' . $e->getMessage());
        return array_fill_keys(['total_comments','total_questions','total_approved','total_pending','total_users','total_replies'], 0);
    }
}

function isValidEmail(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function hashPassword(string $password): string {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
}

function verifyPassword(string $password, string $hash): bool {
    return password_verify($password, $hash);
}

/**
 * Sistema de XP e Karma
 */
function addXP(int $userId, int $amount, string $reason): bool {
    $db = getDB();
    try {
        $db->beginTransaction();

        // Log da transação
        $stmt = $db->prepare("INSERT INTO xp_log (user_id, xp_amount, reason) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $amount, $reason]);

        // Atualiza o total no perfil do utilizador
        $stmt = $db->prepare("UPDATE users SET karma_total = karma_total + ? WHERE id = ?");
        $stmt->execute([$amount, $userId]);

        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Erro addXP: " . $e->getMessage());
        return false;
    }
}

function getUserLevel(int $xp): array {
    if ($xp >= 500) return ['name' => 'Lendário', 'color' => '#ff4500', 'min' => 500];
    if ($xp >= 200) return ['name' => 'Especialista', 'color' => '#00e5ff', 'min' => 200];
    if ($xp >= 100) return ['name' => 'Veterano', 'color' => '#ffd700', 'min' => 100];
    if ($xp >= 50)  return ['name' => 'Ativo', 'color' => '#c0c0c0', 'min' => 50];
    if ($xp >= 20)  return ['name' => 'Membro', 'color' => '#cd7f32', 'min' => 20];
    return ['name' => 'Novo', 'color' => '#888', 'min' => 0];
}
