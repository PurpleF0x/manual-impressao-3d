<?php
/**
 * includes/user_notices.php
 *
 * Incluir NO TOPO do index.php (antes do HTML), após require_once dos includes:
 *   require_once 'includes/user_notices.php';
 *
 * Depois, no body do index.php, chamar:
 *   <?php renderUserNotice(); ?>
 *
 * Funcionalidades:
 *  - Suspensão: bloqueia acesso se ainda ativa, ou limpa automaticamente se expirou
 *  - Aviso: mostra modal ao utilizador uma vez por sessão
 *  - Banimento: redireciona para logout (conta inativa)
 */

function checkAndHandleUserStatus() {
    if (!isLoggedIn()) return null;

    $user = getCurrentUser();
    $db   = getDB();

    // ── Banimento (is_active = 0) ─────────────────────────────────────────────
    $stmt = $db->prepare("SELECT is_active, warning_message, warning_at, suspension_message, suspension_until FROM users WHERE id=?");
    $stmt->execute([(int)$user['id']]);
    $row = $stmt->fetch();

    if (!$row || !$row['is_active']) {
        session_destroy();
        header('Location: login.php?banned=1');
        exit;
    }

    // ── Suspensão ─────────────────────────────────────────────────────────────
    if (!empty($row['suspension_until'])) {
        $until = strtotime($row['suspension_until']);
        if ($until > time()) {
            return array(
                'type'    => 'suspension',
                'message' => $row['suspension_message'],
                'until'   => $row['suspension_until'],
            );
        } else {
            $db->prepare("UPDATE users SET suspension_message=NULL, suspension_until=NULL WHERE id=?")
               ->execute([(int)$user['id']]);
        }
    }

    // ── Aviso ─────────────────────────────────────────────────────────────────
    if (!empty($row['warning_message'])) {
        if (empty($_SESSION['warning_seen_' . $user['id']])) {
            $_SESSION['warning_seen_' . $user['id']] = true;
            return array(
                'type'    => 'warning',
                'message' => $row['warning_message'],
                'at'      => $row['warning_at'],
            );
        }
    }

    return null;
}

function renderUserNotice() {
    $notice = checkAndHandleUserStatus();
    if (!$notice) return;

    if ($notice['type'] === 'suspension') {
        $until   = date('d/m/Y \à\s H:i', strtotime($notice['until']));
        $message = htmlspecialchars($notice['message'] ? $notice['message'] : '');
        echo '
        <div id="userNoticeOverlay" style="
            position:fixed;inset:0;z-index:99999;
            background:rgba(0,0,0,0.85);
            display:flex;align-items:center;justify-content:center;
            padding:20px;backdrop-filter:blur(8px)">
            <div style="
                background:#111118;border:1px solid rgba(124,58,237,0.4);
                border-radius:20px;padding:44px;max-width:520px;width:100%;
                text-align:center;position:relative">
                <div style="font-size:56px;margin-bottom:16px">&#x1F512;</div>
                <div style="font-family:\'Space Mono\',monospace;font-size:10px;
                    color:#a78bfa;letter-spacing:3px;text-transform:uppercase;margin-bottom:10px">
                    Suspens&#xe3;o
                </div>
                <h2 style="font-family:\'Syne\',sans-serif;font-size:26px;font-weight:800;
                    color:#fff;margin-bottom:16px">Conta Temporariamente Suspensa</h2>
                <div style="background:rgba(124,58,237,0.08);border:1px solid rgba(124,58,237,0.2);
                    border-radius:12px;padding:18px 22px;margin-bottom:20px;
                    font-size:15px;color:#e8e8f0;line-height:1.7;text-align:left">
                    ' . $message . '
                </div>
                <p style="color:#888;font-size:13px;margin-bottom:24px">
                    A tua conta estar&#xe1; suspensa at&#xe9; <strong style="color:#a78bfa">' . $until . '</strong>.<br>
                    Ap&#xf3;s esta data, o acesso ser&#xe1; automaticamente restaurado.
                </p>
                <a href="logout.php" style="
                    display:inline-block;background:rgba(124,58,237,0.15);
                    border:1px solid rgba(124,58,237,0.3);color:#a78bfa;
                    border-radius:10px;padding:12px 28px;font-family:\'Space Mono\',monospace;
                    font-size:11px;font-weight:700;text-decoration:none;
                    letter-spacing:1px">
                    SAIR DA CONTA
                </a>
            </div>
        </div>';

    } elseif ($notice['type'] === 'warning') {
        $message = htmlspecialchars($notice['message'] ? $notice['message'] : '');
        $at      = $notice['at'] ? date('d/m/Y', strtotime($notice['at'])) : '';
        $at_html = $at ? '<br><span style="font-size:11px;opacity:0.6">Emitido a ' . $at . '</span>' : '';
        echo '
        <div id="userNoticeOverlay" style="
            position:fixed;inset:0;z-index:99999;
            background:rgba(0,0,0,0.80);
            display:flex;align-items:center;justify-content:center;
            padding:20px;backdrop-filter:blur(6px)">
            <div style="
                background:#111118;border:1px solid rgba(255,107,53,0.35);
                border-radius:20px;padding:44px;max-width:520px;width:100%;
                text-align:center">
                <div style="font-size:56px;margin-bottom:16px">&#x26A0;&#xFE0F;</div>
                <div style="font-family:\'Space Mono\',monospace;font-size:10px;
                    color:#ff6b35;letter-spacing:3px;text-transform:uppercase;margin-bottom:10px">
                    Aviso Oficial
                </div>
                <h2 style="font-family:\'Syne\',sans-serif;font-size:26px;font-weight:800;
                    color:#fff;margin-bottom:16px">Recebeste um Aviso</h2>
                <div style="background:rgba(255,107,53,0.07);border:1px solid rgba(255,107,53,0.2);
                    border-radius:12px;padding:18px 22px;margin-bottom:20px;
                    font-size:15px;color:#e8e8f0;line-height:1.7;text-align:left">
                    ' . $message . '
                </div>
                <p style="color:#888;font-size:13px;margin-bottom:24px">
                    Por favor corrige o comportamento indicado acima.<br>
                    Caso contr&#xe1;rio, poder&#xe3;o ser tomadas medidas mais graves.
                    ' . $at_html . '
                </p>
                <button onclick="dismissNotice()" style="
                    background:linear-gradient(135deg,#ff6b35,#ff4444);
                    border:none;color:#fff;border-radius:10px;
                    padding:12px 32px;font-family:\'Space Mono\',monospace;
                    font-size:11px;font-weight:700;cursor:pointer;
                    letter-spacing:1px">
                    ENTENDI E ACEITO
                </button>
            </div>
        </div>
        <script>
        function dismissNotice() {
            document.getElementById(\'userNoticeOverlay\').style.display = \'none\';
            fetch(\'api/dismiss_notice.php\', {
                method: \'POST\',
                headers: {\'Content-Type\':\'application/json\'},
                body: JSON.stringify({csrf_token: document.querySelector(\'meta[name="csrf"]\') ? document.querySelector(\'meta[name="csrf"]\').content : \'\'})
            });
        }
        </script>';
    }
}