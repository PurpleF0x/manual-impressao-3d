<?php
/**
 * Funções Auxiliares — Manual de Impressão 3D
 */

// Segurança de Sessão
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
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
        // Garantir que a coluna karma_total e tabela xp_log existem (Auto-fix)
        try {
            $stmt = $db->prepare('SELECT karma_total FROM users LIMIT 1');
            $stmt->execute();
        } catch (Exception $e) {
            $db->exec("ALTER TABLE users ADD COLUMN karma_total INT DEFAULT 0");
            $db->exec("ALTER TABLE users ADD COLUMN prefs_show_karma TINYINT(1) DEFAULT 1");
            $db->exec("ALTER TABLE users ADD COLUMN top_badges TEXT DEFAULT NULL");
        }

        try {
            $db->query("SELECT 1 FROM xp_log LIMIT 1");
        } catch (Exception $e) {
            $db->exec("CREATE TABLE xp_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                xp_amount INT NOT NULL,
                reason VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        }

        $stmt = $db->prepare('SELECT * FROM users WHERE id = ? AND is_active = TRUE LIMIT 1');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        if ($user) {
            checkDailyStreak((int)$user['id']);
            $_CACHE['current_user'] = $user;
        } else {
            $_CACHE['current_user'] = false;
        }
    }
    return $_CACHE['current_user'] ?: null;
}

/**
 * Sistema de Streak e Recompensas Diárias
 */
function checkDailyStreak(int $userId): void {
    if (isset($_SESSION['streak_checked_today'])) return;

    $db = getDB();
    try {
        $stmt = $db->prepare("SELECT streak_count, last_streak_date, growth_points FROM user_profile_config WHERE user_id = ?");
        $stmt->execute([$userId]);
        $config = $stmt->fetch();

        if (!$config) {
            $db->prepare("INSERT IGNORE INTO user_profile_config (user_id, streak_count, last_streak_date, growth_points) VALUES (?, 1, CURDATE(), 10)")->execute([$userId]);
            addXP($userId, 10, "Bónus de primeiro acesso", 5);
            $_SESSION['streak_checked_today'] = true;
            return;
        }

        $lastDate = $config['last_streak_date'];
        $today    = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        if ($lastDate === $today) {
            $_SESSION['streak_checked_today'] = true;
            return;
        }

        if ($lastDate === $yesterday) {
            // Continua a streak
            $newStreak = (int)$config['streak_count'] + 1;
            $db->prepare("UPDATE user_profile_config SET streak_count = ?, last_streak_date = ?, growth_points = growth_points + 10 WHERE user_id = ?")
               ->execute([$newStreak, $today, $userId]);
            addXP($userId, 10 + min($newStreak, 20), "Bónus diário (Streak: $newStreak)", 5 + min($newStreak, 10));

            // Recompensa de Badge por Streak (7 dias)
            if ($newStreak >= 7) {
                awardItem($userId, 'badge_streak_7');
            }
        } else {
            // Perdeu a streak
            $db->prepare("UPDATE user_profile_config SET streak_count = 1, last_streak_date = ?, growth_points = growth_points + 5 WHERE user_id = ?")
               ->execute([$today, $userId]);
            addXP($userId, 10, "Bónus diário (Streak reiniciada)", 5);
        }

        $_SESSION['streak_checked_today'] = true;
    } catch (Exception $e) {
        error_log("Erro checkDailyStreak: " . $e->getMessage());
    }
}

function getStreakColor(int $days): string {
    if ($days >= 366) return '#a855f7'; // Roxo (+365)
    if ($days >= 291) return '#000000'; // Preto (291-365)
    if ($days >= 221) return '#ffffff'; // Branco (221-290)
    if ($days >= 151) return '#ef4444'; // Vermelho (151-220)
    if ($days >= 91)  return '#f97316'; // Laranja (91-150)
    if ($days >= 31)  return '#22c55e'; // Verde (31-90)
    return '#eab308'; // Amarelo (1-30)
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
    $parts = explode(' ', trim($name));
    foreach ($parts as $part) {
        $i = strtoupper(substr($part, 0, 1));
        if ($i !== '') {
            $initials .= $i;
            if (strlen($initials) >= 2) break;
        }
    }
    return $initials ?: '??';
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
 * Sistema de XP e Karma (e Moedas da Loja)
 */
function addXP(int $userId, int $xpAmount, string $reason, int $coinAmount = 0): bool {
    $db = getDB();
    try {
        $db->beginTransaction();

        // Log da transação de XP
        $stmt = $db->prepare("INSERT INTO xp_log (user_id, xp_amount, reason) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $xpAmount, $reason]);

        // Atualiza o total no perfil do utilizador (Karma/XP)
        $stmt = $db->prepare("UPDATE users SET karma_total = karma_total + ? WHERE id = ?");
        $stmt->execute([$xpAmount, $userId]);

        // Atualiza moedas na loja (unificação da economia)
        if ($coinAmount !== 0) {
            // Garantir que a tabela existe (fallback caso a loja não tenha sido aberta)
            try {
                $db->query("SELECT coins FROM user_profile_config LIMIT 1");
            } catch (Exception $e) {
                $db->exec("CREATE TABLE IF NOT EXISTS user_profile_config (
                    user_id INT PRIMARY KEY,
                    frame_key VARCHAR(50) NULL,
                    background_key VARCHAR(50) NULL,
                    banner_url VARCHAR(500) NULL,
                    accent_color VARCHAR(20) NULL,
                    top_badges TEXT NULL,
                    coins INT DEFAULT 0,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            }

            $stmt = $db->prepare("UPDATE user_profile_config SET coins = coins + ? WHERE user_id = ?");
            $stmt->execute([$coinAmount, $userId]);

            // Se não afetou nenhuma linha, o registo pode não existir
            if ($stmt->rowCount() === 0) {
                $check = $db->prepare("SELECT 1 FROM user_profile_config WHERE user_id = ?");
                $check->execute([$userId]);
                if (!$check->fetch()) {
                    $ins = $db->prepare("INSERT INTO user_profile_config (user_id, coins) VALUES (?, ?)");
                    $ins->execute([$userId, max(0, $coinAmount)]);
                }
            }
        }

        $db->commit();
        return true;
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log("Erro addXP: " . $e->getMessage());
        return false;
    }
}

function getUserLevel(int $xp): array {
    if ($xp >= 500) return ['name' => 'Lendário', 'color' => '#ff4500', 'min' => 500, 'next' => null];
    if ($xp >= 200) return ['name' => 'Especialista', 'color' => '#00e5ff', 'min' => 200, 'next' => 500];
    if ($xp >= 100) return ['name' => 'Veterano', 'color' => '#ffd700', 'min' => 100, 'next' => 200];
    if ($xp >= 50)  return ['name' => 'Ativo', 'color' => '#c0c0c0', 'min' => 50, 'next' => 100];
    if ($xp >= 20)  return ['name' => 'Membro', 'color' => '#cd7f32', 'min' => 20, 'next' => 50];
    return ['name' => 'Novo', 'color' => '#888', 'min' => 0, 'next' => 20];
}

/**
 * Atribui um item ao inventário do utilizador.
 */
function awardItem(int $userId, string $itemKey): bool {
    $db = getDB();
    try {
        $stmt = $db->prepare("SELECT id FROM shop_items WHERE item_key = ? LIMIT 1");
        $stmt->execute([$itemKey]);
        $itemId = $stmt->fetchColumn();
        if (!$itemId) return false;

        $stmt = $db->prepare("INSERT IGNORE INTO user_inventory (user_id, item_id) VALUES (?, ?)");
        return $stmt->execute([$userId, $itemId]);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Retorna os emblemas disponíveis para o utilizador com base no seu inventário (loja/conquistas).
 */
function getAvailableBadges(int $userId): array {
    $db = getDB();
    $badges = [];

    // Garantir que o utilizador tem os emblemas de nível básicos (Auto-grant)
    try {
        $stmt = $db->prepare("SELECT karma_total FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $xp = (int)$stmt->fetchColumn();

        if ($xp >= 500) awardItem($userId, 'lvl_lendario');
        if ($xp >= 100) awardItem($userId, 'lvl_veterano');
        if ($xp >= 20)  awardItem($userId, 'lvl_membro');

        // Outras conquistas automáticas (ex: Pioneiro se tiver posts)
        $posts = (int)$db->query("SELECT COUNT(*) FROM forum_posts WHERE user_id = $userId")->fetchColumn();
        if ($posts >= 1) awardItem($userId, 'badge_pioneer');
    } catch (Exception $e) {}

    try {
        $stmt = $db->prepare("
            SELECT si.id, si.name, si.description as `desc`, si.css_value as icon
            FROM user_inventory ui
            JOIN shop_items si ON ui.item_id = si.id
            WHERE ui.user_id = ? AND si.category IN ('badge', 'medal')
        ");
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll();
        foreach ($rows as $r) {
            $badges[] = [
                'id'   => $r['id'],
                'name' => $r['name'],
                'icon' => $r['icon'],
                'desc' => $r['desc']
            ];
        }
    } catch (Exception $e) {}

    return $badges;
}

/**
 * Retorna os Top 3 emblemas selecionados pelo utilizador (Sincronizado com Personalização).
 */
function getTopBadges(int $userId): array {
    $db = getDB();

    // 1. Tentar obter da tabela de personalização (Novo Sistema)
    try {
        $stmt = $db->prepare("SELECT top_badges FROM user_profile_config WHERE user_id = ?");
        $stmt->execute([$userId]);
        $val = $stmt->fetchColumn();
        if ($val) {
            $ids = json_decode($val, true) ?: [];
            if (!empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $stmt = $db->prepare("SELECT id, name, description, category, css_value FROM shop_items WHERE id IN ($placeholders)");
                $stmt->execute($ids);
                $items = $stmt->fetchAll();

                $top = [];
                foreach ($ids as $id) {
                    foreach ($items as $item) {
                        if ((int)$item['id'] === (int)$id) {
                            $top[] = $item;
                            break;
                        }
                    }
                }
                return array_slice($top, 0, 3);
            }
        }
    } catch (Exception $e) {}

    // 2. Fallback para a tabela users (Sistema Legado)
    $stmt = $db->prepare("SELECT top_badges FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $val = $stmt->fetchColumn();
    if (!$val) return [];

    $selectedKeys = json_decode($val, true) ?: [];
    $top = [];
    foreach ($selectedKeys as $key) {
        $stmt = $db->prepare("SELECT id, name, description, category, css_value FROM shop_items WHERE item_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $item = $stmt->fetch();
        if ($item) $top[] = $item;
    }

    return array_slice($top, 0, 3);
}
