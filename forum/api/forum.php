<?php
/**
 * forum/api/forum.php — API do fórum
 * Ações: vote_post, join_community, leave_community
 */

register_shutdown_function(function() {
    $e = error_get_last();
    if ($e && in_array($e['type'], array(E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR))) {
        while (ob_get_level()) ob_end_clean();
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('success'=>false,'error'=>'Erro interno: '.$e['message']));
    }
});

// Limpar qualquer output anterior (BOM, whitespace do functions.php)
while (ob_get_level()) ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

// forum/ está 1 nível abaixo da raiz
require_once dirname(__DIR__) . '/../includes/functions.php';
// Helper: verificar se utilizador é moderador global
function isGlobalMod($u) {
    return $u && in_array($u['role'] ?? '', array('admin','moderator'));
}


// Limpar output que functions.php possa ter gerado
while (ob_get_level() > 0) { $buf = ob_get_clean(); if (trim($buf)) error_log('API leaked output: ' . substr($buf,0,200)); }

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

$csrfToken = $input['csrf_token'] ?? null;
if (!verifyCSRFToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(array(
        'success' => false,
        'error'   => 'Token de segurança inválido. Recarrega a página.',
        'debug'   => 'token_received: ' . (empty($csrfToken) ? 'VAZIO' : 'presente')
    ));
    exit;
}

if (!isLoggedIn()) {
    echo json_encode(array('success'=>false,'error'=>'Sessão expirada. Faz login novamente.'));
    exit;
}

$user   = getCurrentUser();
$uid    = (int)$user['id'];
$action = $input['action'] ?? '';
$db     = getDB();

// ── Votar num post ────────────────────────────────────────────
if ($action === 'vote_post') {
    $postId = (int)($input['post_id'] ?? 0);
    $value  = (int)($input['value']   ?? 0);
    if ($postId < 1 || !in_array($value, array(1,-1))) {
        echo json_encode(array('success'=>false,'error'=>'Parâmetros inválidos.')); exit;
    }
    $ps = $db->prepare("SELECT id, user_id, vote_score FROM forum_posts WHERE id=?");
    $ps->execute(array($postId));
    $post = $ps->fetch();
    if (!$post) { echo json_encode(array('success'=>false,'error'=>'Post não encontrado.')); exit; }
    if ((int)$post['user_id'] === $uid) { echo json_encode(array('success'=>false,'error'=>'Não podes votar no teu próprio post.')); exit; }

    $cv = $db->prepare("SELECT value FROM forum_post_votes WHERE user_id=? AND post_id=?");
    $cv->execute(array($uid, $postId));
    $current = $cv->fetch();

    $newScore = (int)$post['vote_score'];
    $userVote = 0;

    if ($current) {
        if ((int)$current['value'] === $value) {
            $db->prepare("DELETE FROM forum_post_votes WHERE user_id=? AND post_id=?")->execute(array($uid,$postId));
            $newScore -= $value; $userVote = 0;
        } else {
            $db->prepare("UPDATE forum_post_votes SET value=? WHERE user_id=? AND post_id=?")->execute(array($value,$uid,$postId));
            $newScore += $value * 2; $userVote = $value;
        }
    } else {
        $db->prepare("INSERT INTO forum_post_votes (user_id,post_id,value) VALUES (?,?,?)")->execute(array($uid,$postId,$value));
        $newScore += $value; $userVote = $value;
    }

    $db->prepare("UPDATE forum_posts SET vote_score=? WHERE id=?")->execute(array($newScore,$postId));

    // Atribuição de Karma/XP ao autor do post (+3 Like, -2 Dislike) e Moedas (+2 por Like)
    if ($userVote !== 0) {
        $xp = ($userVote === 1) ? 3 : -2;
        $coins = ($userVote === 1) ? 2 : 0;
        $reason = ($userVote === 1) ? "Recebeu Like no post #$postId" : "Recebeu Dislike no post #$postId";
        addXP((int)$post['user_id'], $xp, $reason, $coins);
    }

    echo json_encode(array('success'=>true,'score'=>$newScore,'user_vote'=>$userVote));
    exit;
}

// ── Entrar numa comunidade ────────────────────────────────────
if ($action === 'join_community') {
    $commId = (int)($input['community_id'] ?? 0);
    if ($commId < 1) { echo json_encode(array('success'=>false,'error'=>'Comunidade inválida.')); exit; }
    $cs = $db->prepare("SELECT id FROM forum_communities WHERE id=? AND is_active=1");
    $cs->execute(array($commId));
    if (!$cs->fetch()) { echo json_encode(array('success'=>false,'error'=>'Comunidade não encontrada.')); exit; }
    $ex = $db->prepare("SELECT id FROM forum_memberships WHERE user_id=? AND community_id=?");
    $ex->execute(array($uid,$commId));
    if (!$ex->fetch()) {
        $db->prepare("INSERT INTO forum_memberships (user_id,community_id,role) VALUES (?,?,'member')")->execute(array($uid,$commId));
        $db->prepare("UPDATE forum_communities SET member_count=member_count+1 WHERE id=?")->execute(array($commId));
    }
    echo json_encode(array('success'=>true,'joined'=>true));
    exit;
}

// ── Sair de uma comunidade ────────────────────────────────────
if ($action === 'leave_community') {
    $commId = (int)($input['community_id'] ?? 0);
    if ($commId < 1) { echo json_encode(array('success'=>false,'error'=>'Comunidade inválida.')); exit; }
    $ow = $db->prepare("SELECT id FROM forum_communities WHERE id=? AND created_by=?");
    $ow->execute(array($commId,$uid));
    if ($ow->fetch()) { echo json_encode(array('success'=>false,'error'=>'O criador não pode sair da comunidade.')); exit; }
    $del = $db->prepare("DELETE FROM forum_memberships WHERE user_id=? AND community_id=?");
    $del->execute(array($uid,$commId));
    if ($del->rowCount() > 0) {
        $db->prepare("UPDATE forum_communities SET member_count=GREATEST(0,member_count-1) WHERE id=?")->execute(array($commId));
    }
    echo json_encode(array('success'=>true,'joined'=>false));
    exit;
}

// ── Criar resposta ────────────────────────────────────────────
if ($action === 'create_reply') {
    $postId   = (int)($input['post_id']   ?? 0);
    $parentId = isset($input['parent_id']) && $input['parent_id'] ? (int)$input['parent_id'] : null;
    $content  = trim($input['content'] ?? '');
    if ($postId < 1 || mb_strlen($content) < 1) { echo json_encode(array('success'=>false,'error'=>'Conteúdo inválido.')); exit; }
    if (mb_strlen($content) > 5000) { echo json_encode(array('success'=>false,'error'=>'Resposta demasiado longa.')); exit; }
    $ps = $db->prepare("SELECT id, is_locked FROM forum_posts WHERE id=?");
    $ps->execute(array($postId)); $post=$ps->fetch();
    if (!$post) { echo json_encode(array('success'=>false,'error'=>'Post não encontrado.')); exit; }
    if ($post['is_locked']) { echo json_encode(array('success'=>false,'error'=>'Este tópico está fechado.')); exit; }
    $ins = $db->prepare("INSERT INTO forum_replies (post_id,parent_id,user_id,content) VALUES (?,?,?,?)");
    $ins->execute(array($postId,$parentId,$uid,$content));
    $replyId = (int)$db->lastInsertId();
    $db->prepare("UPDATE forum_posts SET reply_count=reply_count+1 WHERE id=?")->execute(array($postId));

    // Atribuição de Karma/XP (+5 por resposta) e Moedas (+3)
    addXP($uid, 5, "Publicou resposta no post #$postId", 3);

    echo json_encode(array('success'=>true,'reply_id'=>$replyId));
    exit;
}

// ── Votar resposta ────────────────────────────────────────────
if ($action === 'vote_reply') {
    $replyId = (int)($input['reply_id'] ?? 0);
    $value   = (int)($input['value']    ?? 0);
    if ($replyId < 1 || !in_array($value, array(1,-1))) { echo json_encode(array('success'=>false,'error'=>'Inválido.')); exit; }
    try { $db->exec("CREATE TABLE IF NOT EXISTS forum_reply_votes (user_id INT NOT NULL,reply_id INT NOT NULL,value TINYINT NOT NULL DEFAULT 1,PRIMARY KEY (user_id,reply_id),FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,FOREIGN KEY (reply_id) REFERENCES forum_replies(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"); } catch(Exception $e){}
    $rs = $db->prepare("SELECT id,user_id,vote_score FROM forum_replies WHERE id=?");
    $rs->execute(array($replyId)); $reply=$rs->fetch();
    if (!$reply) { echo json_encode(array('success'=>false,'error'=>'Resposta não encontrada.')); exit; }
    if ((int)$reply['user_id']===$uid) { echo json_encode(array('success'=>false,'error'=>'Não podes votar na tua resposta.')); exit; }
    $cv = $db->prepare("SELECT value FROM forum_reply_votes WHERE user_id=? AND reply_id=?");
    $cv->execute(array($uid,$replyId)); $current=$cv->fetch();
    $newScore=(int)$reply['vote_score']; $userVote=0;
    if ($current) {
        if ((int)$current['value']===$value) { $db->prepare("DELETE FROM forum_reply_votes WHERE user_id=? AND reply_id=?")->execute(array($uid,$replyId)); $newScore-=$value; $userVote=0; }
        else { $db->prepare("UPDATE forum_reply_votes SET value=? WHERE user_id=? AND reply_id=?")->execute(array($value,$uid,$replyId)); $newScore+=$value*2; $userVote=$value; }
    } else { $db->prepare("INSERT INTO forum_reply_votes (user_id,reply_id,value) VALUES (?,?,?)")->execute(array($uid,$replyId,$value)); $newScore+=$value; $userVote=$value; }
    $db->prepare("UPDATE forum_replies SET vote_score=? WHERE id=?")->execute(array($newScore,$replyId));

    // Atribuição de Karma/XP ao autor da resposta (+3 Like, -2 Dislike) e Moedas (+2 por Like)
    if ($userVote !== 0) {
        $xp = ($userVote === 1) ? 3 : -2;
        $coins = ($userVote === 1) ? 2 : 0;
        $reason = ($userVote === 1) ? "Recebeu Like na resposta #$replyId" : "Recebeu Dislike na resposta #$replyId";
        addXP((int)$reply['user_id'], $xp, $reason, $coins);
    }

    echo json_encode(array('success'=>true,'score'=>$newScore,'user_vote'=>$userVote));
    exit;
}

// ── Apagar resposta ───────────────────────────────────────────
if ($action === 'delete_reply') {
    $replyId = (int)($input['reply_id'] ?? 0);
    if ($replyId < 1) { echo json_encode(array('success'=>false,'error'=>'Inválido.')); exit; }
    $rs = $db->prepare("SELECT id,user_id,post_id FROM forum_replies WHERE id=?");
    $rs->execute(array($replyId)); $reply=$rs->fetch();
    if (!$reply) { echo json_encode(array('success'=>false,'error'=>'Não encontrado.')); exit; }
    $isOwn = (int)$reply['user_id']===$uid;
    $isMod = isGlobalMod($user);
    if (!$isOwn && !$isMod) { echo json_encode(array('success'=>false,'error'=>'Sem permissão.')); exit; }
    $db->prepare("DELETE FROM forum_replies WHERE id=?")->execute(array($replyId));
    $db->prepare("UPDATE forum_posts SET reply_count=GREATEST(0,reply_count-1) WHERE id=?")->execute(array((int)$reply['post_id']));
    echo json_encode(array('success'=>true));
    exit;
}

// ── Apagar post ───────────────────────────────────────────────
if ($action === 'delete_post') {
    $postId = (int)($input['post_id'] ?? 0);
    if ($postId < 1) { echo json_encode(array('success'=>false,'error'=>'Inválido.')); exit; }
    $ps = $db->prepare("SELECT id,user_id,community_id,status FROM forum_posts WHERE id=?");
    $ps->execute(array($postId)); $post=$ps->fetch();
    if (!$post) { echo json_encode(array('success'=>false,'error'=>'Não encontrado.')); exit; }
    // Verificar permissão: autor, moderador global, ou owner/admin/mod da comunidade
    $isAuthor  = (int)$post['user_id'] === $uid;
    $isGlobMod = isGlobalMod($user);
    $isCommMod = false;
    if (!$isAuthor && !$isGlobMod) {
        $cm = $db->prepare("SELECT role FROM forum_memberships WHERE user_id=? AND community_id=? AND role IN ('owner','admin','moderator')");
        $cm->execute(array($uid, (int)$post['community_id']));
        $isCommMod = (bool)$cm->fetch();
    }
    if (!$isAuthor && !$isGlobMod && !$isCommMod) { echo json_encode(array('success'=>false,'error'=>'Sem permissão.')); exit; }
    $db->prepare("DELETE FROM forum_posts WHERE id=?")->execute(array($postId));
    // Só decrementar contador se o post estava aprovado (contribuiu para a contagem)
    if ($post['status'] === 'approved') {
        $db->prepare("UPDATE forum_communities SET post_count=GREATEST(0,post_count-1) WHERE id=?")->execute(array((int)$post['community_id']));
    }
    echo json_encode(array('success'=>true));
    exit;
}

// ── Aprovar post ──────────────────────────────────────────────
if ($action === 'approve_post') {
    $postId = (int)($input['post_id'] ?? 0);
    if ($postId < 1) { echo json_encode(array('success'=>false,'error'=>'Inválido.')); exit; }

    $ps = $db->prepare("SELECT id,community_id,status,user_id FROM forum_posts WHERE id=?");
    $ps->execute(array($postId)); $post=$ps->fetch();
    if (!$post) { echo json_encode(array('success'=>false,'error'=>'Post não encontrado.')); exit; }
    if ($post['status'] !== 'pending') { echo json_encode(array('success'=>false,'error'=>'O post já foi processado.')); exit; }

    // Verificar permissão de moderação
    $canMod = isGlobalMod($user);
    if (!$canMod) {
        $cm = $db->prepare("SELECT role FROM forum_memberships WHERE user_id=? AND community_id=? AND role IN ('owner','admin','moderator')");
        $cm->execute(array($uid, (int)$post['community_id']));
        $canMod = (bool)$cm->fetch();
    }
    if (!$canMod) { echo json_encode(array('success'=>false,'error'=>'Sem permissão para moderar.')); exit; }

    $db->beginTransaction();
    try {
        $db->prepare("UPDATE forum_posts SET status='approved', moderated_by=?, moderated_at=NOW() WHERE id=? AND status='pending'")
           ->execute(array($uid, $postId));
        $db->prepare("UPDATE forum_communities SET post_count=post_count+1 WHERE id=?")
           ->execute(array((int)$post['community_id']));
        $db->prepare("INSERT INTO forum_moderation_log (post_id,moderator_id,action) VALUES (?,?,'approved')")
           ->execute(array($postId,$uid));

        // Atribuição de Recompensa ao autor (agora que o post foi aprovado)
        addXP((int)$post['user_id'], 15, "Post aprovado pela moderação: #$postId", 10);

        $db->commit();
        echo json_encode(array('success'=>true,'status'=>'approved'));
    } catch(Exception $e) {
        $db->rollBack();
        echo json_encode(array('success'=>false,'error'=>'Erro interno.'));
    }
    exit;
}

// ── Rejeitar post ─────────────────────────────────────────────
if ($action === 'reject_post') {
    $postId = (int)($input['post_id'] ?? 0);
    $reason = trim($input['reason'] ?? '');
    if ($postId < 1) { echo json_encode(array('success'=>false,'error'=>'Inválido.')); exit; }

    $ps = $db->prepare("SELECT id,community_id,status FROM forum_posts WHERE id=?");
    $ps->execute(array($postId)); $post=$ps->fetch();
    if (!$post) { echo json_encode(array('success'=>false,'error'=>'Post não encontrado.')); exit; }
    if ($post['status'] !== 'pending') { echo json_encode(array('success'=>false,'error'=>'O post já foi processado.')); exit; }

    $canMod = isGlobalMod($user);
    if (!$canMod) {
        $cm = $db->prepare("SELECT role FROM forum_memberships WHERE user_id=? AND community_id=? AND role IN ('owner','admin','moderator')");
        $cm->execute(array($uid, (int)$post['community_id']));
        $canMod = (bool)$cm->fetch();
    }
    if (!$canMod) { echo json_encode(array('success'=>false,'error'=>'Sem permissão para moderar.')); exit; }

    $db->beginTransaction();
    try {
        $db->prepare("UPDATE forum_posts SET status='rejected', moderated_by=?, moderated_at=NOW(), rejection_reason=? WHERE id=? AND status='pending'")
           ->execute(array($uid, $reason ?: null, $postId));
        $db->prepare("INSERT INTO forum_moderation_log (post_id,moderator_id,action,reason) VALUES (?,?,'rejected',?)")
           ->execute(array($postId,$uid,$reason ?: null));
        $db->commit();
        echo json_encode(array('success'=>true,'status'=>'rejected'));
    } catch(Exception $e) {
        $db->rollBack();
        echo json_encode(array('success'=>false,'error'=>'Erro interno.'));
    }
    exit;
}

// ── Alterar role de membro da comunidade ──────────────────────
if ($action === 'set_member_role') {
    $commId   = (int)($input['community_id'] ?? 0);
    $targetId = (int)($input['user_id']       ?? 0);
    $newRole  = trim($input['new_role']        ?? '');

    if ($commId < 1 || $targetId < 1) { echo json_encode(array('success'=>false,'error'=>'Parâmetros inválidos.')); exit; }
    if (!in_array($newRole, array('admin','moderator','member'))) { echo json_encode(array('success'=>false,'error'=>'Role inválido.')); exit; }
    if ($targetId === $uid) { echo json_encode(array('success'=>false,'error'=>'Não podes alterar o teu próprio role.')); exit; }

    // Apenas o owner ou mod global pode alterar roles
    $isOwner = false;
    $ow = $db->prepare("SELECT id FROM forum_communities WHERE id=? AND created_by=?");
    $ow->execute(array($commId,$uid));
    if ($ow->fetch()) $isOwner = true;

    if (!$isOwner && !isGlobalMod($user)) { echo json_encode(array('success'=>false,'error'=>'Apenas o criador da comunidade pode gerir roles.')); exit; }

    // Verificar que o target é membro e não é owner
    $tm = $db->prepare("SELECT role FROM forum_memberships WHERE user_id=? AND community_id=?");
    $tm->execute(array($targetId,$commId)); $targetMember=$tm->fetch();
    if (!$targetMember) { echo json_encode(array('success'=>false,'error'=>'Utilizador não é membro desta comunidade.')); exit; }
    if ($targetMember['role'] === 'owner') { echo json_encode(array('success'=>false,'error'=>'Não podes alterar o role do criador.')); exit; }

    $db->prepare("UPDATE forum_memberships SET role=? WHERE user_id=? AND community_id=?")
       ->execute(array($newRole,$targetId,$commId));
    echo json_encode(array('success'=>true,'new_role'=>$newRole));
    exit;
}

// ── Fixar post ────────────────────────────────────────────────
if ($action === 'toggle_pin') {
    if (!isGlobalMod($user)) { echo json_encode(array('success'=>false,'error'=>'Sem permissão.')); exit; }
    $postId = (int)($input['post_id'] ?? 0);
    $value  = (int)($input['value']   ?? 0);
    $db->prepare("UPDATE forum_posts SET is_pinned=? WHERE id=?")->execute(array($value?1:0,$postId));
    echo json_encode(array('success'=>true,'pinned'=>(bool)$value));
    exit;
}

// ── Fechar/abrir post ─────────────────────────────────────────
if ($action === 'toggle_lock') {
    if (!isGlobalMod($user)) { echo json_encode(array('success'=>false,'error'=>'Sem permissão.')); exit; }
    $postId = (int)($input['post_id'] ?? 0);
    $value  = (int)($input['value']   ?? 0);
    $db->prepare("UPDATE forum_posts SET is_locked=? WHERE id=?")->execute(array($value?1:0,$postId));
    echo json_encode(array('success'=>true,'locked'=>(bool)$value));
    exit;
}

// ── Enviar mensagem privada ───────────────────────────────────
if ($action === 'send_message') {
    $receiverId = (int)($input['receiver_id'] ?? 0);
    $content    = trim($input['content'] ?? '');
    if ($receiverId < 1 || $receiverId === $uid) { echo json_encode(array('success'=>false,'error'=>'Destinatário inválido.')); exit; }
    if (mb_strlen($content) < 1)    { echo json_encode(array('success'=>false,'error'=>'Mensagem vazia.')); exit; }
    if (mb_strlen($content) > 2000) { echo json_encode(array('success'=>false,'error'=>'Mensagem demasiado longa.')); exit; }

    // Verificar se destinatário existe
    $rs = $db->prepare("SELECT id,full_name,username,avatar_url,role FROM users WHERE id=? AND is_active=1");
    $rs->execute(array($receiverId)); $receiver=$rs->fetch();
    if (!$receiver) { echo json_encode(array('success'=>false,'error'=>'Utilizador não encontrado.')); exit; }

    // Bloquear envio para Master se o remetente não for staff
    if ($receiver['role'] === 'master' && !canModerate($user)) {
        echo json_encode(array('success'=>false,'error'=>'Não tens permissão para enviar mensagens diretas ao Administrador Master.'));
        exit;
    }

    $ins = $db->prepare("INSERT INTO private_messages (sender_id,receiver_id,content) VALUES (?,?,?)");
    $ins->execute(array($uid,$receiverId,$content));
    $newId = (int)$db->lastInsertId();

    // Retornar mensagem completa para o DOM
    $ms = $db->prepare("SELECT pm.*,u.full_name as sender_name,u.username as sender_username,u.avatar_url as sender_avatar FROM private_messages pm JOIN users u ON u.id=pm.sender_id WHERE pm.id=?");
    $ms->execute(array($newId));
    $msg = $ms->fetch();

    echo json_encode(array('success'=>true,'message'=>$msg));
    exit;
}

// ── Polling de novas mensagens ────────────────────────────────
if ($action === 'poll_messages') {
    $otherId = (int)($input['other_id'] ?? 0);
    $lastId  = (int)($input['last_id']  ?? 0);
    if ($otherId < 1) { echo json_encode(array('success'=>false,'error'=>'Inválido.')); exit; }

    // Marcar como lidas ao mesmo tempo
    $db->prepare("UPDATE private_messages SET read_at=NOW() WHERE sender_id=? AND receiver_id=? AND read_at IS NULL")
       ->execute(array($otherId,$uid));

    $msgs = $db->query("
        SELECT pm.*, u.full_name as sender_name, u.username as sender_username, u.avatar_url as sender_avatar
        FROM private_messages pm
        JOIN users u ON u.id=pm.sender_id
        WHERE ((pm.sender_id=$uid AND pm.receiver_id=$otherId) OR (pm.sender_id=$otherId AND pm.receiver_id=$uid))
          AND pm.id > $lastId
        ORDER BY pm.created_at ASC
        LIMIT 50
    ")->fetchAll();

    echo json_encode(array('success'=>true,'messages'=>$msgs));
    exit;
}

// ── Fixar resposta ────────────────────────────────────────────
if ($action === 'toggle_pin_reply') {
    $replyId = (int)($input['reply_id'] ?? 0);
    $value   = (int)($input['value']    ?? 0);
    if ($replyId < 1) { echo json_encode(array('success'=>false,'error'=>'Resposta inválida.')); exit; }

    $rs = $db->prepare("SELECT fr.id, fr.post_id, fr.user_id, fp.user_id as post_author_id, fp.community_id
                        FROM forum_replies fr
                        JOIN forum_posts fp ON fp.id = fr.post_id
                        WHERE fr.id = ?");
    $rs->execute(array($replyId));
    $reply = $rs->fetch();
    if (!$reply) { echo json_encode(array('success'=>false,'error'=>'Resposta não encontrada.')); exit; }

    // Permissão: Autor do post ou Moderador/Admin
    $isPostAuthor = (int)$reply['post_author_id'] === $uid;
    $isGlobMod    = isGlobalMod($user);
    $isCommMod    = false;
    if (!$isGlobMod && !$isPostAuthor) {
        $cm = $db->prepare("SELECT role FROM forum_memberships WHERE user_id=? AND community_id=? AND role IN ('owner','admin','moderator')");
        $cm->execute(array($uid, (int)$reply['community_id']));
        $isCommMod = (bool)$cm->fetch();
    }

    if (!$isPostAuthor && !$isGlobMod && !$isCommMod) {
        echo json_encode(array('success'=>false,'error'=>'Sem permissão para fixar esta resposta.')); exit;
    }

    $db->prepare("UPDATE forum_replies SET is_pinned=? WHERE id=?")->execute(array($value?1:0, $replyId));
    echo json_encode(array('success'=>true, 'pinned'=>(bool)$value));
    exit;
}

echo json_encode(array('success'=>false,'error'=>'Ação desconhecida.'));