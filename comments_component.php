<?php
/**
 * comments_component.php
 */

if (!function_exists('isLoggedIn')) {
    require_once __DIR__ . '/includes/functions.php';
}
if (!function_exists('getApprovedComments')) {
    require_once __DIR__ . '/includes/comments.php';
}

if (!isset($commentsSection)) $commentsSection = 'geral';
$commentsCsrf  = generateCSRFToken();
$currentUser   = isLoggedIn() ? getCurrentUser() : null;
$unreadNotifs  = $currentUser ? getUnreadNotifications((int)$currentUser['id']) : [];
$isModUser     = $currentUser && canModerate($currentUser);
?>

<style>
/* ── Comentários ── */
.cmt-thread {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    overflow: hidden;
    transition: border-color 0.2s;
}
.cmt-thread:hover { border-color: rgba(0,229,255,0.2); }
.cmt-main { padding: 20px 22px 14px; }

/* Author row */
.cmt-author-row { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
.cmt-avatar {
    width: 42px; height: 42px; border-radius: 50%;
    background: linear-gradient(135deg, var(--accent3), var(--accent));
    display: flex; align-items: center; justify-content: center;
    font-family: 'Space Mono', monospace; font-weight: 700; color: #000;
    flex-shrink: 0; overflow: hidden; text-decoration: none; font-size: 15px;
}
.cmt-avatar img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
.cmt-avatar-sm { width: 32px; height: 32px; font-size: 11px; }
.cmt-author-meta a.name-link { color: #fff; text-decoration: none; font-weight: 600; font-size: 14px; transition: color 0.2s; }
.cmt-author-meta a.name-link:hover { color: var(--accent); }
.cmt-author-sub { font-size: 11px; color: var(--muted); margin-top: 2px; display: flex; align-items: center; gap: 6px; }
.cmt-author-sub a { color: var(--accent); text-decoration: none; font-size: 10px; }

.cmt-streak-fire {
    font-weight: 800;
    font-family: 'Syne', sans-serif;
    font-size: 11px;
    margin-left: 4px;
    filter: drop-shadow(0 0 3px currentColor);
}

/* Category badge */
.cmt-cat { font-family: 'Space Mono', monospace; font-size: 9px; font-weight: 700; letter-spacing: 1px; padding: 3px 8px; border-radius: 5px; text-transform: uppercase; margin-left: auto; flex-shrink: 0; }
.cmt-cat.duvida   { background: rgba(0,229,255,0.12);  color: var(--accent); }
.cmt-cat.problema { background: rgba(255,107,53,0.12); color: var(--accent2); }
.cmt-cat.dica     { background: rgba(0,255,136,0.12);  color: #00ff88; }
.cmt-cat.geral    { background: rgba(124,58,237,0.12); color: #a78bfa; }

/* Content */
.cmt-content { font-size: 14px; line-height: 1.7; color: var(--text); margin-bottom: 12px; word-break: break-word; }
.cmt-content-sm { font-size: 13px; }

/* Actions row */
.cmt-actions { display: flex; align-items: center; gap: 4px; flex-wrap: wrap; }
.cmt-btn {
    background: none; border: none; cursor: pointer;
    color: var(--muted); font-family: 'Space Mono', monospace;
    font-size: 11px; padding: 6px 10px; border-radius: 7px;
    transition: all 0.15s; display: inline-flex; align-items: center; gap: 5px;
}
.cmt-btn:hover { background: var(--surface2); color: var(--text); }
.cmt-btn.liked { color: #ff6b81; }
.cmt-btn.reply-open { background: rgba(0,229,255,0.08); color: var(--accent); }
.cmt-btn.report-btn:hover { color: #ff6b35; }

/* Replies section */
.cmt-replies-section {
    display: none;
    border-top: 1px solid var(--border);
    background: rgba(0,0,0,0.12);
}
.cmt-replies-section.open { display: block; }
.cmt-replies-inner {
    padding: 14px 22px 14px 42px;
    display: flex; flex-direction: column; gap: 12px;
}
.cmt-reply-item {
    border-left: 2px solid rgba(0,229,255,0.18);
    padding-left: 16px;
}

/* Reply form */
.cmt-reply-form {
    display: none;
    padding: 12px 22px 14px 42px;
    border-top: 1px solid var(--border);
    background: rgba(0,0,0,0.12);
}
.cmt-reply-form.open { display: block; }
.cmt-reply-form textarea {
    width: 100%; background: var(--surface);
    border: 1px solid var(--border); border-radius: 8px;
    padding: 10px 14px; color: var(--text);
    font-family: 'Inter', sans-serif; font-size: 13px;
    min-height: 70px; resize: vertical; margin-bottom: 8px;
    transition: border-color 0.2s;
}
.cmt-reply-form textarea:focus { outline: none; border-color: var(--accent); }
.cmt-reply-btns { display: flex; gap: 8px; }
.cmt-reply-submit {
    background: linear-gradient(135deg, var(--accent), var(--accent3));
    border: none; border-radius: 7px; padding: 8px 16px;
    color: #000; font-family: 'Space Mono', monospace;
    font-size: 10px; font-weight: 700; cursor: pointer; letter-spacing: 1px;
}
.cmt-reply-cancel {
    background: var(--surface3); border: 1px solid var(--border);
    border-radius: 7px; padding: 8px 12px; color: var(--muted);
    font-family: 'Space Mono', monospace; font-size: 10px; cursor: pointer;
}

/* Sort */
.cmt-sort-bar {
    display: flex; align-items: center; gap: 8px; margin-bottom: 14px;
    font-family: 'Space Mono', monospace; font-size: 10px; color: var(--muted); flex-wrap: wrap;
}
.sort-btn {
    background: none; border: 1px solid var(--border); border-radius: 6px;
    padding: 4px 10px; color: var(--muted);
    font-family: 'Space Mono', monospace; font-size: 10px; cursor: pointer; transition: all 0.2s;
}
.sort-btn.active, .sort-btn:hover { border-color: var(--accent); color: var(--accent); background: rgba(0,229,255,0.06); }
</style>

<section class="section" id="comentarios">
  <div class="section-header">
    <div class="section-number">12</div>
    <div class="section-title">
      <h2>Dúvidas &amp; Comentários</h2>
      <p>Partilha as tuas questões e ajuda outros utilizadores</p>
    </div>
  </div>

  <div class="comments-section">

    <div class="comments-header">
      <h3>💬 Comunidade de Apoio
        <?php if ($isModUser): ?>
          <a href="moderacao.php" style="font-size:12px;background:rgba(255,107,53,0.15);color:var(--accent2);border:1px solid rgba(255,107,53,0.3);border-radius:6px;padding:4px 10px;text-decoration:none;margin-left:10px;font-family:'Space Mono',monospace">🛡️ Moderação</a>
        <?php endif; ?>
      </h3>
      <div class="comments-filter">
        <button class="filter-btn active" data-filter="all">Todos</button>
        <button class="filter-btn" data-filter="duvida">Dúvidas</button>
        <button class="filter-btn" data-filter="problema">Problemas</button>
        <button class="filter-btn" data-filter="dica">Dicas</button>
      </div>
    </div>

    <div class="stats-bar">
      <div class="stat">💬 <span id="cmtTotal">0</span> comentários</div>
      <div class="stat">❓ <span id="cmtQuestions">0</span> dúvidas</div>
      <div class="stat">✅ <span id="cmtReplied">0</span> com resposta</div>
      <div class="stat">👥 <span id="cmtUsers">0</span> participantes</div>
    </div>

    <?php if (!empty($unreadNotifs)): ?>
    <div id="notifBar" style="background:rgba(0,229,255,0.07);border:1px solid rgba(0,229,255,0.2);border-radius:10px;padding:14px 18px;margin-bottom:16px">
      <div style="font-family:'Space Mono',monospace;font-size:11px;color:var(--accent);margin-bottom:8px">🔔 NOTIFICAÇÕES (<?php echo count($unreadNotifs); ?>)</div>
      <?php foreach ($unreadNotifs as $n): ?>
        <div style="font-size:13px;color:var(--text);padding:4px 0;border-bottom:1px solid var(--border)"><?php echo sanitize($n['message']); ?></div>
      <?php endforeach; ?>
      <button onclick="markNotificationsRead()" style="margin-top:10px;background:transparent;border:1px solid var(--border);border-radius:6px;padding:6px 14px;color:var(--muted);font-family:'Space Mono',monospace;font-size:10px;cursor:pointer">✓ Marcar como lidas</button>
    </div>
    <?php endif; ?>

    <?php if ($currentUser): ?>
    <div class="comment-form" id="newCommentForm">
      <input type="hidden" id="cmtCsrf"    value="<?php echo $commentsCsrf; ?>">
      <input type="hidden" id="cmtSection" value="<?php echo htmlspecialchars($commentsSection, ENT_QUOTES, 'UTF-8'); ?>">
      <div class="form-row">
        <div class="form-group">
          <label>A comentar como</label>
          <input type="text" value="<?php echo sanitize($currentUser['full_name']); ?>" readonly style="opacity:0.6;cursor:not-allowed">
        </div>
        <div class="form-group">
          <label>Categoria</label>
          <select id="cmtCategory">
            <option value="duvida">❓ Dúvida</option>
            <option value="problema">🛠️ Problema</option>
            <option value="dica">💡 Dica</option>
            <option value="geral" selected>💬 Geral</option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label>Mensagem</label>
        <textarea id="cmtText" placeholder="Escreve a tua dúvida, problema ou dica..." maxlength="2000"></textarea>
        <div style="text-align:right;font-family:'Space Mono',monospace;font-size:10px;color:var(--muted);margin-top:4px"><span id="cmtCharCount">0</span>/2000</div>
      </div>
      <button class="submit-btn" onclick="submitComment()"><span>📤</span> Publicar Comentário</button>
      <span id="cmtStatus" style="font-size:13px;margin-left:14px;color:var(--muted)"></span>
    </div>
    <?php else: ?>
    <div style="background:var(--surface2);border:1px solid var(--border);border-radius:12px;padding:22px;text-align:center;margin-bottom:20px">
      <p style="color:var(--muted);margin-bottom:12px">Para comentar precisas de ter sessão iniciada.</p>
      <a href="login.php" style="background:var(--accent);color:#000;padding:10px 22px;border-radius:8px;text-decoration:none;font-family:'Space Mono',monospace;font-size:11px;font-weight:700">🔑 ENTRAR</a>
    </div>
    <?php endif; ?>

    <!-- Sort bar -->
    <div class="cmt-sort-bar">
      <span>Ordenar:</span>
      <button class="sort-btn active" data-sort="likes">❤️ Mais likes</button>
      <button class="sort-btn" data-sort="recent">⏱ Mais recentes</button>
      <button class="sort-btn" data-sort="replies">💬 Mais respostas</button>
    </div>

    <div class="comments-list" id="commentsList">
      <div style="text-align:center;padding:40px;color:var(--muted)">A carregar comentários…</div>
    </div>

    <div id="loadMoreWrap" style="text-align:center;margin-top:16px;display:none">
      <button onclick="loadMoreComments()" style="background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:10px 24px;color:var(--muted);font-family:'Space Mono',monospace;font-size:11px;cursor:pointer">Carregar mais</button>
    </div>

  </div>
</section>

<!-- Modal Reporte -->
<div id="reportCommentModal" style="display:none;position:fixed;inset:0;z-index:99990;background:rgba(0,0,0,0.75);backdrop-filter:blur(4px);align-items:center;justify-content:center;padding:20px">
  <div style="background:#111118;border:1px solid rgba(255,107,53,0.3);border-radius:20px;padding:36px;max-width:460px;width:100%;position:relative">
    <button onclick="closeReportModal()" style="position:absolute;top:14px;right:16px;background:none;border:none;color:var(--muted);font-size:20px;cursor:pointer">×</button>
    <div style="font-size:32px;margin-bottom:10px">🚩</div>
    <div style="font-family:'Space Mono',monospace;font-size:10px;color:#ff6b35;letter-spacing:3px;text-transform:uppercase;margin-bottom:8px">Reportar Comentário</div>
    <h3 style="font-family:'Syne',sans-serif;font-size:20px;font-weight:800;color:#fff;margin-bottom:20px">Porque estás a reportar?</h3>
    <input type="hidden" id="reportCommentId" value="">
    <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:18px">
      <?php
      $reasons = ['spam'=>['🔁','Spam ou publicidade'],'ofensivo'=>['😤','Conteúdo ofensivo ou insultos'],'inapropriado'=>['🔞','Conteúdo inapropriado'],'desinformacao'=>['❌','Desinformação ou informação errada'],'outro'=>['💬','Outro motivo']];
      foreach ($reasons as $val => $info): ?>
      <label style="display:flex;align-items:center;gap:10px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:12px 14px;cursor:pointer"
             onmouseover="this.style.borderColor='rgba(255,107,53,0.4)'" onmouseout="this.style.borderColor='var(--border)'">
        <input type="radio" name="reportReason" value="<?php echo $val; ?>" style="accent-color:#ff6b35;width:16px;height:16px">
        <span style="font-size:16px"><?php echo $info[0]; ?></span>
        <span style="color:var(--text);font-size:14px"><?php echo $info[1]; ?></span>
      </label>
      <?php endforeach; ?>
    </div>
    <div style="margin-bottom:20px">
      <label style="display:block;font-family:'Space Mono',monospace;font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px">Descrição adicional <span style="opacity:0.5">(opcional)</span></label>
      <textarea id="reportDescription" placeholder="Descreve o problema…" maxlength="500" style="width:100%;background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:12px 14px;color:var(--text);font-family:'Inter',sans-serif;font-size:13px;min-height:80px;resize:vertical"></textarea>
      <div style="text-align:right;font-size:10px;color:var(--muted);margin-top:4px"><span id="reportDescCount">0</span>/500</div>
    </div>
    <div style="display:flex;gap:10px">
      <button onclick="submitReportComment()" style="flex:1;background:linear-gradient(135deg,#ff6b35,#ff4444);border:none;color:#fff;border-radius:10px;padding:13px;font-family:'Space Mono',monospace;font-size:11px;font-weight:700;cursor:pointer;letter-spacing:1px">ENVIAR REPORTE</button>
      <button onclick="closeReportModal()" style="background:var(--surface2);border:1px solid var(--border);color:var(--muted);border-radius:10px;padding:13px 20px;font-family:'Space Mono',monospace;font-size:11px;cursor:pointer">CANCELAR</button>
    </div>
    <p id="reportStatus" style="text-align:center;font-size:13px;margin-top:12px;color:var(--muted);min-height:18px"></p>
  </div>
</div>

<script>
(function(){
  var API     = 'api/comments.php';
  var CSRF    = document.getElementById('cmtCsrf') ? document.getElementById('cmtCsrf').value : '';
  var SECTION = document.getElementById('cmtSection') ? document.getElementById('cmtSection').value : 'geral';
  var CUR_UID = <?php echo $currentUser ? (int)$currentUser['id'] : 'null'; ?>;
  var IS_MOD  = <?php echo $isModUser ? 'true' : 'false'; ?>;
  var CAT_LBL = {duvida:'DÚVIDA',problema:'PROBLEMA',dica:'DICA',geral:'GERAL'};
  var STREAK_COLORS = { 0: '#eab308', 31: '#22c55e', 91: '#f97316', 151: '#ef4444', 221: '#ffffff', 291: '#000000', 366: '#a855f7' };
  var allComments=[], currentFilter='all', currentSort='likes', offset=0, LIMIT=20;

  function getStreakColor(days) {
      if (days >= 366) return STREAK_COLORS[366];
      if (days >= 291) return STREAK_COLORS[291];
      if (days >= 221) return STREAK_COLORS[221];
      if (days >= 151) return STREAK_COLORS[151];
      if (days >= 91)  return STREAK_COLORS[91];
      if (days >= 31)  return STREAK_COLORS[31];
      return STREAK_COLORS[0];
  }

  // ── Carregar ─────────────────────────────────────────────────
  async function loadComments(reset) {
    if (reset===undefined) reset=true;
    if (reset) { offset=0; allComments=[]; }
    try {
      var res=await fetch(API,{method:'POST',headers:{'Content-Type':'application/json'},
        body:JSON.stringify({action:'list',csrf_token:CSRF,section:SECTION,limit:LIMIT,offset:offset})});
      var data=await res.json();
      if (!data.success) return;
      allComments=reset?data.comments:allComments.concat(data.comments);
      offset+=data.comments.length;
      document.getElementById('loadMoreWrap').style.display=data.comments.length===LIMIT?'block':'none';
      updateStats(); renderComments();
    } catch(e){console.error(e);}
  }
  window.loadMoreComments=function(){loadComments(false);};

  // ── Stats ────────────────────────────────────────────────────
  function updateStats() {
    document.getElementById('cmtTotal').textContent=allComments.length;
    document.getElementById('cmtQuestions').textContent=allComments.filter(function(c){return c.category==='duvida';}).length;
    document.getElementById('cmtReplied').textContent=allComments.filter(function(c){return c.replies&&c.replies.length>0;}).length;
    var uids=[]; allComments.forEach(function(c){if(uids.indexOf(c.user_id)<0)uids.push(c.user_id);});
    document.getElementById('cmtUsers').textContent=uids.length;
  }

  // ── Sort ─────────────────────────────────────────────────────
  function sortComments(list) {
    var copy=list.slice();
    if (currentSort==='likes')   copy.sort(function(a,b){return (b.like_count||0)-(a.like_count||0);});
    if (currentSort==='recent')  copy.sort(function(a,b){return new Date(b.created_at)-new Date(a.created_at);});
    if (currentSort==='replies') copy.sort(function(a,b){return (b.replies||[]).length-(a.replies||[]).length;});
    return copy;
  }

  // ── Render lista ─────────────────────────────────────────────
  function renderComments() {
    var list=document.getElementById('commentsList');
    var filtered=currentFilter==='all'?allComments:allComments.filter(function(c){return c.category===currentFilter;});
    var sorted=sortComments(filtered);
    if (!sorted.length) {
      list.innerHTML='<div class="empty-state"><div class="empty-icon">💬</div><p>Ainda não há comentários nesta categoria.</p></div>';
      return;
    }
    list.innerHTML='<div style="display:flex;flex-direction:column;gap:12px">'+sorted.map(renderThread).join('')+'</div>';
  }

  // ── Render thread ────────────────────────────────────────────
  function renderThread(c) {
    var catCls=c.category||'geral';
    var replies=c.replies||[];
    var repCount=replies.length;
    var likeIcon=c.user_liked?'❤️':'🤍';
    var likeCnt=c.like_count||0;
    var id=c.id;

    // Botão like
    var likeBtn='<button class="cmt-btn'+(c.user_liked?' liked':'')+'" onclick="toggleLike('+id+',this)" data-liked="'+(c.user_liked?'1':'0')+'">'
      +likeIcon+' <span class="like-count">'+likeCnt+'</span></button>';

    // Botão responder com contador — sempre visível se logado
    var replyLabel = repCount > 0
      ? '💬 Responder (' + repCount + ')'
      : '💬 Responder';
    var replyBtn = CUR_UID
      ? '<button class="cmt-btn" id="replybtn-'+id+'" onclick="toggleReplyArea('+id+')">' + replyLabel + '</button>'
      : (repCount > 0
        ? '<button class="cmt-btn" onclick="toggleRepliesOnly('+id+')" style="cursor:pointer">💬 '+repCount+' resposta'+(repCount>1?'s':'')+'</button>'
        : '');

    // Botão report (só se não for o autor)
    var reportBtn=(CUR_UID&&CUR_UID!==c.user_id)
      ?'<button class="cmt-btn report-btn" onclick="openReportModal('+id+')" style="margin-left:auto" title="Reportar">🚩</button>':'';

    // Botão eliminar (para autor ou mod)
    var deleteBtn=(CUR_UID && (CUR_UID===c.user_id || IS_MOD))
      ?'<button class="cmt-btn" onclick="modAction(\'delete\','+id+')" style="color:#ff7777; margin-left:'+(reportBtn?'0':'auto')+'" title="Eliminar">🗑️</button>':'';

    // Respostas
    var repliesHtml='';
    if (repCount>0) {
      repliesHtml='<div class="cmt-replies-section" id="replies-'+id+'">'
        +'<div class="cmt-replies-inner">'+replies.map(renderReplyItem).join('')+'</div>'
        +'</div>';
    }

    // Formulário de resposta (só se logado)
    var formHtml=CUR_UID
      ?'<div class="cmt-reply-form" id="rform-'+id+'">'
        +'<textarea id="rtext-'+id+'" placeholder="Escreve a tua resposta&#8230;"></textarea>'
        +'<div class="cmt-reply-btns">'
        +'<button class="cmt-reply-submit" onclick="submitReply('+id+')">Publicar resposta</button>'
        +'<button class="cmt-reply-cancel" onclick="toggleReplyArea('+id+')">Cancelar</button>'
        +'</div></div>'
      :'';

    return '<div class="cmt-thread" id="thread-'+id+'">'
      +'<div class="cmt-main">'
        +'<div class="cmt-author-row">'
          +avatarHtml(c,42,15)
          +'<div class="cmt-author-meta" style="flex:1;min-width:0">'
            +'<a class="name-link" href="perfil_publico.php?id='+c.user_id+'">'+esc(c.full_name)+'</a>'
            +(c.streak_count > 0 ? '<span class="cmt-streak-fire" style="color:'+getStreakColor(c.streak_count)+'" title="Streak de '+c.streak_count+' dias">🔥'+c.streak_count+'</span>' : '')
            +'<div class="cmt-author-sub">'
              +'<a href="perfil_publico.php?id='+c.user_id+'">@'+esc(c.username)+'</a>'
              +'<span>·</span><span>'+formatDate(c.created_at)+'</span>'
            +'</div>'
          +'</div>'
          +'<span class="cmt-cat '+catCls+'">'+(CAT_LBL[catCls]||catCls.toUpperCase())+'</span>'
        +'</div>'
        +'<div class="cmt-content">'+esc(c.content).replace(/\n/g,'<br>')+'</div>'
        +'<div class="cmt-actions">'+likeBtn+replyBtn+deleteBtn+reportBtn+'</div>'
      +'</div>'
      +repliesHtml
      +formHtml
      +'</div>';
  }

  // ── Render resposta ──────────────────────────────────────────
  function renderReplyItem(r) {
    var likeIcon=r.user_liked?'❤️':'🤍', likeCnt=r.like_count||0;
    var reportBtn=(CUR_UID&&CUR_UID!==r.user_id)
      ?'<button class="cmt-btn report-btn" onclick="openReportModal('+r.id+')" style="margin-left:auto" title="Reportar">🚩</button>':'';
    var deleteBtn=(CUR_UID && (CUR_UID===r.user_id || IS_MOD))
      ?'<button class="cmt-btn" onclick="modAction(\'delete\','+r.id+')" style="color:#ff7777; margin-left:'+(reportBtn?'0':'auto')+'" title="Eliminar">🗑️</button>':'';

    return '<div class="cmt-reply-item" id="cmt-'+r.id+'">'
      +'<div class="cmt-author-row" style="margin-bottom:8px">'
        +avatarHtml(r,32,11)
        +'<div class="cmt-author-meta" style="flex:1">'
          +'<a class="name-link" href="perfil_publico.php?id='+r.user_id+'" style="font-size:13px">'+esc(r.full_name)+'</a>'
          +(r.streak_count > 0 ? '<span class="cmt-streak-fire" style="color:'+getStreakColor(r.streak_count)+'" title="Streak de '+r.streak_count+' dias">🔥'+r.streak_count+'</span>' : '')
          +'<div class="cmt-author-sub">'
            +'<a href="perfil_publico.php?id='+r.user_id+'">@'+esc(r.username)+'</a>'
            +'<span>·</span><span>'+formatDate(r.created_at)+'</span>'
          +'</div>'
        +'</div>'
      +'</div>'
      +'<div class="cmt-content cmt-content-sm">'+esc(r.content).replace(/\n/g,'<br>')+'</div>'
      +'<div class="cmt-actions">'
        +'<button class="cmt-btn'+(r.user_liked?' liked':'')+'" onclick="toggleLike('+r.id+',this)" data-liked="'+(r.user_liked?'1':'0')+'">'
          +likeIcon+' <span class="like-count">'+likeCnt+'</span>'
        +'</button>'
        +deleteBtn+reportBtn
      +'</div>'
      +'</div>';
  }

  function avatarHtml(c, size, fs) {
    var initials=((c.full_name||'??').split(' ').map(function(n){return n[0]||'';}).join('').toUpperCase()).slice(0,2);
    var avUrl = c.avatar_url ? (c.avatar_url.indexOf('http') === 0 ? c.avatar_url : '/' + c.avatar_url.replace(/^\/+/, '')) : '';
    var inner = avUrl ? '<img src="'+avUrl+'" alt="">' : initials;
    return '<a href="perfil_publico.php?id='+c.user_id+'" class="cmt-avatar'+(size<=34?' cmt-avatar-sm':'')+'" style="font-size:'+fs+'px">'+inner+'</a>';
  }

  // ── Toggle área responder (form + respostas existentes) ──────
  window.toggleReplyArea = function(id) {
    var form    = document.getElementById('rform-'+id);
    var replies = document.getElementById('replies-'+id);
    var btn     = document.getElementById('replybtn-'+id);
    if (!form) return;

    var isOpen = form.classList.contains('open');

    if (!isOpen) {
      form.classList.add('open');
      if (replies) replies.classList.add('open');
      if (btn) btn.classList.add('reply-open');
      var ta = form.querySelector('textarea');
      if (ta) setTimeout(function(){ta.focus();},50);
    } else {
      form.classList.remove('open');
      // Mantém respostas visíveis se existirem
      if (btn) btn.classList.remove('reply-open');
    }
  };

  // Para utilizadores não logados — só ver respostas
  window.toggleRepliesOnly = function(id) {
    var replies = document.getElementById('replies-'+id);
    if (replies) replies.classList.toggle('open');
  };

  // ── Submit comentário ────────────────────────────────────────
  async function submitComment() {
    var content=document.getElementById('cmtText')?document.getElementById('cmtText').value.trim():'';
    var category=document.getElementById('cmtCategory')?document.getElementById('cmtCategory').value:'geral';
    var statusEl=document.getElementById('cmtStatus');
    if (!content){if(statusEl)statusEl.textContent='⚠️ Escreve um comentário antes de publicar.';return;}
    if(statusEl)statusEl.textContent='A enviar…';
    try {
      var res=await fetch(API,{method:'POST',headers:{'Content-Type':'application/json'},
        body:JSON.stringify({action:'create',csrf_token:CSRF,content:content,category:category,section:SECTION})});
      var data=await res.json();
      if(statusEl)statusEl.textContent=data.success?'✓ '+data.message:'⚠️ '+(data.error||'Erro.');
      if(data.success){document.getElementById('cmtText').value='';document.getElementById('cmtCharCount').textContent='0';loadComments();}
    }catch(e){if(statusEl)statusEl.textContent='⚠️ Erro de rede.';}
  }
  window.submitComment=submitComment;

  // ── Submit resposta ──────────────────────────────────────────
  async function submitReply(parentId) {
    var ta=document.getElementById('rtext-'+parentId);
    var content=ta?ta.value.trim():''; if(!content)return;
    try {
      var res=await fetch(API,{method:'POST',headers:{'Content-Type':'application/json'},
        body:JSON.stringify({action:'create',csrf_token:CSRF,content:content,category:'geral',section:SECTION,parent_id:parentId})});
      var data=await res.json();
      if(data.success){if(ta)ta.value='';loadComments();}
      else alert('⚠️ '+(data.error||'Erro.'));
    }catch(e){alert('⚠️ Erro de rede.');}
  }
  window.submitReply=submitReply;

  // ── Like ─────────────────────────────────────────────────────
  async function toggleLike(commentId,btn) {
    if(!CUR_UID){alert('Precisas de estar logado para dar like.');return;}
    try {
      var res=await fetch(API,{method:'POST',headers:{'Content-Type':'application/json'},
        body:JSON.stringify({action:'like',csrf_token:CSRF,comment_id:commentId})});
      var data=await res.json();
      if(data.success){
        btn.dataset.liked=data.liked?'1':'0';
        btn.classList.toggle('liked',data.liked);
        var spans=btn.querySelectorAll('span');
        if(spans[0])spans[0].textContent=data.liked?'❤️':'🤍';
        var lc=btn.querySelector('.like-count'); if(lc)lc.textContent=data.count;
      }
    }catch(e){}
  }
  window.toggleLike=toggleLike;

  // ── Mod ──────────────────────────────────────────────────────
  async function modAction(action,commentId) {
    if(!confirm('Tens a certeza?'))return;
    try {
      var res=await fetch(API,{method:'POST',headers:{'Content-Type':'application/json'},
        body:JSON.stringify({action:action,csrf_token:CSRF,comment_id:commentId})});
      var data=await res.json();
      alert(data.success?'✓ '+data.message:'⚠️ '+(data.error||'Erro.'));
      if(data.success)loadComments();
    }catch(e){alert('⚠️ Erro de rede.');}
  }
  window.modAction=modAction;

  // ── Notificações ─────────────────────────────────────────────
  async function markNotificationsRead() {
    await fetch(API,{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({action:'mark_notifications_read',csrf_token:CSRF})});
    var bar=document.getElementById('notifBar'); if(bar)bar.style.display='none';
  }
  window.markNotificationsRead=markNotificationsRead;

  // ── Modal reporte ────────────────────────────────────────────
  window.openReportModal=function(commentId){
    if(!CUR_UID){alert('Precisas de estar logado para reportar.');return;}
    document.getElementById('reportCommentId').value=commentId;
    document.querySelectorAll('input[name="reportReason"]').forEach(function(r){r.checked=false;});
    document.getElementById('reportDescription').value='';
    document.getElementById('reportDescCount').textContent='0';
    document.getElementById('reportStatus').textContent='';
    var m=document.getElementById('reportCommentModal'); m.style.display='flex'; document.body.style.overflow='hidden';
  };
  window.closeReportModal=function(){
    document.getElementById('reportCommentModal').style.display='none'; document.body.style.overflow='';
  };
  document.getElementById('reportCommentModal').addEventListener('click',function(e){if(e.target===this)window.closeReportModal();});
  document.getElementById('reportDescription').addEventListener('input',function(){document.getElementById('reportDescCount').textContent=this.value.length;});
  window.submitReportComment=async function(){
    var commentId=document.getElementById('reportCommentId').value;
    var reasonEl=document.querySelector('input[name="reportReason"]:checked');
    var description=document.getElementById('reportDescription').value.trim();
    var statusEl=document.getElementById('reportStatus');
    if(!reasonEl){statusEl.style.color='#ff6b35';statusEl.textContent='⚠️ Seleciona um motivo.';return;}
    statusEl.style.color='var(--muted)';statusEl.textContent='A enviar…';
    try {
      var res=await fetch('api/reports.php',{method:'POST',headers:{'Content-Type':'application/json'},
        body:JSON.stringify({action:'report_comment',csrf_token:CSRF,comment_id:parseInt(commentId),reason:reasonEl.value,description:description})});
      var data=await res.json();
      if(data.success){statusEl.style.color='var(--success)';statusEl.textContent='✓ '+data.message;setTimeout(window.closeReportModal,1800);}
      else{statusEl.style.color='#ff6b35';statusEl.textContent='⚠️ '+(data.error||'Erro.');}
    }catch(e){statusEl.style.color='#ff6b35';statusEl.textContent='⚠️ Erro de rede.';}
  };

  // ── Filtros & Sort ────────────────────────────────────────────
  document.querySelectorAll('.filter-btn').forEach(function(btn){
    btn.addEventListener('click',function(e){
      document.querySelectorAll('.filter-btn').forEach(function(b){b.classList.remove('active');});
      e.currentTarget.classList.add('active');
      currentFilter=e.currentTarget.dataset.filter||'all'; renderComments();
    });
  });
  document.querySelectorAll('.sort-btn').forEach(function(btn){
    btn.addEventListener('click',function(e){
      document.querySelectorAll('.sort-btn').forEach(function(b){b.classList.remove('active');});
      e.currentTarget.classList.add('active');
      currentSort=e.currentTarget.dataset.sort||'likes'; renderComments();
    });
  });

  var cmtTA=document.getElementById('cmtText');
  if(cmtTA)cmtTA.addEventListener('input',function(){document.getElementById('cmtCharCount').textContent=this.value.length;});

  function esc(s){if(!s)return'';return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
  function formatDate(d){if(!d)return'';var dt=new Date(d.replace(' ','T'));var diff=Math.floor((Date.now()-dt)/1000);if(diff<60)return'agora mesmo';if(diff<3600)return Math.floor(diff/60)+'min atrás';if(diff<86400)return Math.floor(diff/3600)+'h atrás';return dt.toLocaleDateString('pt-PT');}

  loadComments();
})();
</script>