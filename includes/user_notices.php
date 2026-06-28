<?php
/**
 * includes/user_notices.php - OTIMIZADO
 */

function checkAndHandleUserStatus() {
    if (!isLoggedIn()) return null;

    // Cache de 1 minuto para não saturar a base de dados
    if (isset($_SESSION['last_status_check']) && (time() - $_SESSION['last_status_check'] < 60)) {
        return $_SESSION['current_notice'] ?? null;
    }

    $user = getCurrentUser();
    $db   = getDB();

    $stmt = $db->prepare("SELECT is_active, warning_message, warning_at, suspension_message, suspension_until FROM users WHERE id=?");
    $stmt->execute([(int)$user['id']]);
    $row = $stmt->fetch();

    $notice = null;

    if (!$row || !$row['is_active']) {
        session_destroy();
        header('Location: login.php?banned=1');
        exit;
    }

    if (!empty($row['suspension_until'])) {
        $until = strtotime($row['suspension_until']);
        if ($until > time()) {
            $notice = array('type' => 'suspension', 'message' => $row['suspension_message'], 'until' => $row['suspension_until']);
        }
    }

    if (!$notice && !empty($row['warning_message'])) {
        if (empty($_SESSION['warning_seen_' . $user['id']])) {
            $notice = array('type' => 'warning', 'message' => $row['warning_message'], 'at' => $row['warning_at']);
        }
    }

    $_SESSION['last_status_check'] = time();
    $_SESSION['current_notice'] = $notice;
    return $notice;
}

function renderUserNotice() {
    $notice = checkAndHandleUserStatus();
    if (!$notice) return;

    if ($notice['type'] === 'suspension') {
        $until   = date('d/m/Y \à\s H:i', strtotime($notice['until']));
        $message = htmlspecialchars($notice['message'] ?: 'Sem motivo especificado.');
        echo '<div id="userNoticeOverlay" style="position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,0.85);display:flex;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(8px)">
            <div style="background:#111118;border:1px solid rgba(124,58,237,0.4);border-radius:20px;padding:44px;max-width:520px;width:100%;text-align:center;">
                <div style="font-size:56px;margin-bottom:16px">🔒</div>
                <h2 style="color:#fff;margin-bottom:16px">Conta Suspensa</h2>
                <div style="background:rgba(124,58,237,0.08);padding:18px;border-radius:12px;margin-bottom:20px;color:#e8e8f0;text-align:left">'.$message.'</div>
                <p style="color:#888;margin-bottom:24px">Até: <strong>'.$until.'</strong></p>
                <a href="logout.php" style="background:#7c3aed;color:#fff;padding:12px 28px;border-radius:10px;text-decoration:none;">SAIR DA CONTA</a>
            </div>
        </div>';
    } elseif ($notice['type'] === 'warning') {
        $message = htmlspecialchars($notice['message']);
        echo '<div id="userNoticeOverlay" style="position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,0.8);display:flex;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(6px)">
            <div style="background:#111118;border:1px solid rgba(255,107,53,0.35);border-radius:20px;padding:44px;max-width:520px;width:100%;text-align:center">
                <div style="font-size:56px;margin-bottom:16px">⚠️</div>
                <h2 style="color:#fff;margin-bottom:16px">Aviso de Conduta</h2>
                <div style="background:rgba(255,107,53,0.07);padding:18px;border-radius:12px;margin-bottom:20px;color:#e8e8f0;text-align:left">'.$message.'</div>
                <button onclick="dismissNotice()" style="background:#ff6b35;color:#fff;border:none;padding:12px 32px;border-radius:10px;cursor:pointer">ENTENDI E ACEITO</button>
            </div>
        </div>
        <script>
        function dismissNotice() {
            document.getElementById("userNoticeOverlay").style.display = "none";
            fetch("api/dismiss_notice.php", { method: "POST" });
        }
        </script>';
    }
}
