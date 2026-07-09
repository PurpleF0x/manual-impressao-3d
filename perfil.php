<?php
require_once 'includes/functions.php';
require_once 'includes/comments.php';
require_once 'includes/content_filter.php';
require_once 'includes/missions.php';
require_once 'includes/user_notices.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user = getCurrentUser();
$db   = getDB();
$errors  = [];
$success = [];

// --- Garantir colunas extras ---
try { $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS bio TEXT DEFAULT NULL"); } catch(Exception $e){}
try { $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS location VARCHAR(100) DEFAULT NULL"); } catch(Exception $e){}
try { $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS website VARCHAR(255) DEFAULT NULL"); } catch(Exception $e){}
try { $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS avatar_url VARCHAR(500) DEFAULT NULL"); } catch(Exception $e){}
try { $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS experience_level ENUM('iniciante','intermedio','avancado','profissional') DEFAULT 'iniciante'"); } catch(Exception $e){}
try { $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS karma_total INT DEFAULT 0"); } catch(Exception $e){}
try { $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS top_badges TEXT DEFAULT NULL"); } catch(Exception $e){}

// --- Tabelas extra ---
try { $db->exec("CREATE TABLE IF NOT EXISTS user_printers (
    id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL,
    brand VARCHAR(100) NOT NULL, model VARCHAR(100) NOT NULL,
    type ENUM('FDM','SLA','SLS','MSLA','Outro') DEFAULT 'FDM',
    bed_size VARCHAR(50) DEFAULT NULL, notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE)"); } catch(Exception $e){}
try { $db->exec("CREATE TABLE IF NOT EXISTS user_slicers (
    id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL, version VARCHAR(50) DEFAULT NULL,
    notes TEXT DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE)"); } catch(Exception $e){}
try { $db->exec("CREATE TABLE IF NOT EXISTS user_materials (
    id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL,
    material VARCHAR(100) NOT NULL, brand VARCHAR(100) DEFAULT NULL,
    notes TEXT DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE)"); } catch(Exception $e){}

// ==================== PROCESSAR FORMULÁRIOS ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = "Token de segurança inválido.";
    } else {
        $action = $_POST['action'];

        if ($action === 'upload_avatar') {
            if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['avatar'];
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime  = $finfo->file($file['tmp_name']);
                if (!in_array($mime, ['image/jpeg','image/png','image/gif','image/webp'])) {
                    $errors[] = "Formato não suportado. Use JPG, PNG, GIF ou WebP.";
                } elseif ($file['size'] > 2*1024*1024) {
                    $errors[] = "Imagem não pode ultrapassar 2MB.";
                } else {
                    $dir = __DIR__.'/uploads/avatars/';
                    if (!is_dir($dir)) mkdir($dir,0755,true);
                    $ext  = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $dest = $dir.'avatar_'.$user['id'].'_'.time().'.'.$ext;
                    if (move_uploaded_file($file['tmp_name'], $dest)) {
                        $db->prepare("UPDATE users SET avatar_url=? WHERE id=?")->execute(['uploads/avatars/'.basename($dest), $user['id']]);
                        $success[] = "Foto de perfil atualizada!";
                    } else { $errors[] = "Erro ao guardar a imagem."; }
                }
            }
        }
        elseif ($action === 'update_profile') {
            $fn  = trim($_POST['full_name'] ?? '');
            $bio = trim($_POST['bio'] ?? '');
            $loc = trim($_POST['location'] ?? '');
            $web = trim($_POST['website'] ?? '');
            $exp = $_POST['experience_level'] ?? 'iniciante';
            $birth = $_POST['birth_date'] ?? null;

            if (strlen($fn)<2) $errors[] = "O nome deve ter pelo menos 2 caracteres.";
            if (!in_array($exp,['iniciante','intermedio','avancado','profissional'])) $exp='iniciante';
            if (empty($errors)) {
                $filterResult = checkFields(['Nome' => $fn, 'Bio' => $bio, 'Localização' => $loc]);
                if ($filterResult !== true) { $errors[] = $filterResult; }
                else {
                    $db->prepare("UPDATE users SET full_name=?,bio=?,location=?,website=?,experience_level=? WHERE id=?")->execute([$fn,$bio,$loc,$web,$exp,$user['id']]);

                    if ($birth) {
                        $db->prepare("UPDATE user_profile_config SET birth_date=? WHERE user_id=?")->execute([$birth, $user['id']]);
                    }

                    $success[] = "Perfil atualizado com sucesso!";
                }
            }
        }
        elseif ($action === 'change_password') {
            $cur = $_POST['current_password'] ?? '';
            $new = $_POST['new_password'] ?? '';
            $con = $_POST['confirm_password'] ?? '';
            if (!password_verify($cur, $user['password_hash'])) $errors[] = "Palavra-passe atual incorreta.";
            elseif (strlen($new)<8) $errors[] = "Nova palavra-passe deve ter pelo menos 8 caracteres.";
            elseif ($new!==$con) $errors[] = "As palavras-passe não coincidem.";
            else {
                $db->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([password_hash($new,PASSWORD_DEFAULT),$user['id']]);

                // Enviar email de confirmação
                require_once 'includes/mail_config.php';
                sendPasswordChangedEmail($user['email'], $user['full_name']);

                $success[]="Palavra-passe alterada!";
            }
        }
        elseif ($action === 'add_printer') {
            $br=trim($_POST['printer_brand']??''); $mo=trim($_POST['printer_model']??'');
            $ty=$_POST['printer_type']??'FDM'; $be=trim($_POST['printer_bed']??''); $no=trim($_POST['printer_notes']??'');
            if (empty($br)||empty($mo)) $errors[]=" Marca e modelo são obrigatórios.";
            else {
                $filterResult = checkFields(['Marca' => $br, 'Modelo' => $mo, 'Notas' => $no, 'Área' => $be]);
                if ($filterResult !== true) { $errors[] = $filterResult; }
                else { if(!in_array($ty,['FDM','SLA','SLS','MSLA','Outro']))$ty='FDM'; $db->prepare("INSERT INTO user_printers(user_id,brand,model,type,bed_size,notes)VALUES(?,?,?,?,?,?)")->execute([$user['id'],$br,$mo,$ty,$be,$no]); $success[]="Impressora adicionada!"; }
            }
        }
        elseif ($action === 'edit_printer') {
            $pid=(int)($_POST['printer_id']??0); $br=trim($_POST['printer_brand']??''); $mo=trim($_POST['printer_model']??'');
            $ty=$_POST['printer_type']??'FDM'; $be=trim($_POST['printer_bed']??''); $no=trim($_POST['printer_notes']??'');
            if (empty($br)||empty($mo)) $errors[]="Marca e modelo são obrigatórios.";
            else {
                $filterResult = checkFields(['Marca' => $br, 'Modelo' => $mo, 'Notas' => $no, 'Área' => $be]);
                if ($filterResult !== true) { $errors[] = $filterResult; }
                else { if(!in_array($ty,['FDM','SLA','SLS','MSLA','Outro']))$ty='FDM'; $db->prepare("UPDATE user_printers SET brand=?,model=?,type=?,bed_size=?,notes=? WHERE id=? AND user_id=?")->execute([$br,$mo,$ty,$be,$no,$pid,$user['id']]); $success[]=" Impressora atualizada!"; }
            }
        }
        elseif ($action === 'remove_printer') { $db->prepare("DELETE FROM user_printers WHERE id=? AND user_id=?")->execute([(int)$_POST['printer_id'],$user['id']]); $success[]="Impressora removida."; }
        elseif ($action === 'add_slicer') {
            $na=trim($_POST['slicer_name']??''); $ve=trim($_POST['slicer_version']??''); $no=trim($_POST['slicer_notes']??'');
            if (empty($na)) $errors[]="Nome do slicer é obrigatório.";
            else {
                $filterResult = checkFields(['Nome' => $na, 'Notas' => $no]);
                if ($filterResult !== true) { $errors[] = $filterResult; }
                else { $db->prepare("INSERT INTO user_slicers(user_id,name,version,notes)VALUES(?,?,?,?)")->execute([$user['id'],$na,$ve,$no]); $success[]=" Slicer adicionado!"; }
            }
        }
        elseif ($action === 'edit_slicer') {
            $sid=(int)($_POST['slicer_id']??0); $na=trim($_POST['slicer_name']??''); $ve=trim($_POST['slicer_version']??''); $no=trim($_POST['slicer_notes']??'');
            if (empty($na)) $errors[]="Nome do slicer é obrigatório.";
            else {
                $filterResult = checkFields(['Nome' => $na, 'Notas' => $no]);
                if ($filterResult !== true) { $errors[] = $filterResult; }
                else { $db->prepare("UPDATE user_slicers SET name=?,version=?,notes=? WHERE id=? AND user_id=?")->execute([$na,$ve,$no,$sid,$user['id']]); $success[]=" Slicer atualizado!"; }
            }
        }
        elseif ($action === 'remove_slicer') { $db->prepare("DELETE FROM user_slicers WHERE id=? AND user_id=?")->execute([(int)$_POST['slicer_id'],$user['id']]); $success[]="Slicer removido."; }
        elseif ($action === 'add_material') {
            $ma=trim($_POST['material_name']??''); $br=trim($_POST['material_brand']??''); $no=trim($_POST['material_notes']??'');
            if (empty($ma)) $errors[]="Nome do material é obrigatório.";
            else {
                $filterResult = checkFields(['Material' => $ma, 'Marca' => $br, 'Notas' => $no]);
                if ($filterResult !== true) { $errors[] = $filterResult; }
                else { $db->prepare("INSERT INTO user_materials(user_id,material,brand,notes)VALUES(?,?,?,?)")->execute([$user['id'],$ma,$br,$no]); $success[]=" Material adicionado!"; }
            }
        }
        elseif ($action === 'edit_material') {
            $mid=(int)($_POST['material_id']??0); $ma=trim($_POST['material_name']??''); $br=trim($_POST['material_brand']??''); $no=trim($_POST['material_notes']??'');
            if (empty($ma)) $errors[]="Nome do material é obrigatório.";
            else {
                $filterResult = checkFields(['Material' => $ma, 'Marca' => $br, 'Notas' => $no]);
                if ($filterResult !== true) { $errors[] = $filterResult; }
                else { $db->prepare("UPDATE user_materials SET material=?,brand=?,notes=? WHERE id=? AND user_id=?")->execute([$ma,$br,$no,$mid,$user['id']]); $success[]=" Material atualizado!"; }
            }
        }
        elseif ($action === 'remove_material') { $db->prepare("DELETE FROM user_materials WHERE id=? AND user_id=?")->execute([(int)$_POST['material_id'],$user['id']]); $success[]="Material removido."; }
        elseif ($action === 'update_top_badges') {
            $selected = $_POST['badges'] ?? [];
            if (count($selected) > 3) {
                $errors[] = "Só podes selecionar até 3 emblemas.";
            } else {
                // Sincronizar com o novo sistema de personalização
                $val = json_encode(array_values(array_map('intval', $selected)));

                // Garantir que a config existe
                $stmt = $db->prepare("SELECT 1 FROM user_profile_config WHERE user_id = ?");
                $stmt->execute([$user['id']]);
                if ($stmt->fetch()) {
                    $db->prepare("UPDATE user_profile_config SET top_badges=? WHERE user_id=?")->execute([$val, $user['id']]);
                } else {
                    $db->prepare("INSERT INTO user_profile_config (user_id, top_badges) VALUES (?, ?)")->execute([$user['id'], $val]);
                }

                // Manter legado por segurança (opcional, mas bom para transição)
                $db->prepare("UPDATE users SET top_badges=? WHERE id=?")->execute([$val, $user['id']]);

                $success[] = "Emblemas em destaque atualizados!";
            }
        }
    }
}

$user      = getCurrentUser();
ensureUserProfileConfig((int)$user['id']);
$userConfig = ['streak_count' => 0, 'growth_points' => 0, 'birth_date' => null];
try {
    $stmtConfig = $db->prepare("SELECT streak_count, growth_points, birth_date FROM user_profile_config WHERE user_id = ?");
    $stmtConfig->execute([$user['id']]);
    $userConfig = $stmtConfig->fetch() ?: $userConfig;
} catch (Exception $e) {
    error_log("Erro ao carregar config do perfil: " . $e->getMessage());
}
$streakCount = (int)$userConfig['streak_count'];
$growthPoints = (int)$userConfig['growth_points'];
$birthDate = $userConfig['birth_date'];
$streakColor = getStreakColor($streakCount);

$printers  = $db->prepare("SELECT * FROM user_printers WHERE user_id=? ORDER BY created_at DESC");  $printers->execute([$user['id']]);  $printers=$printers->fetchAll();
$slicers   = $db->prepare("SELECT * FROM user_slicers WHERE user_id=? ORDER BY created_at DESC");   $slicers->execute([$user['id']]);   $slicers=$slicers->fetchAll();
$materials = $db->prepare("SELECT * FROM user_materials WHERE user_id=? ORDER BY created_at DESC"); $materials->execute([$user['id']]); $materials=$materials->fetchAll();

// Missões
$userMissions = getUserMissions((int)$user['id']);
$missionDefs  = getMissionsDefinitions();

// comentários do utilizador (aprovados)
$myComments = getUserComments((int)$user['id']);
 
// Posts do fórum (se a tabela existir)
$forumPosts   = array();
$forumReplies = array();
$forumStats   = array('posts'=>0,'replies'=>0,'vote_score'=>0);
try {
    $fp = $db->prepare("
        SELECT fp.id, fp.title, fp.content, fp.vote_score, fp.reply_count,
               fp.created_at, fp.is_pinned, fp.is_locked,
               fc.name as community_name, fc.slug as community_slug, fc.icon as community_icon
        FROM forum_posts fp
        JOIN forum_communities fc ON fc.id=fp.community_id
        WHERE fp.user_id=?
        ORDER BY fp.created_at DESC LIMIT 50
    ");
    $fp->execute(array($user['id']));
    $forumPosts = $fp->fetchAll();
 
    $fr = $db->prepare("
        SELECT fr.id, fr.content, fr.vote_score, fr.created_at,
               fp.id as post_id, fp.title as post_title,
               fc.name as community_name, fc.slug as community_slug
        FROM forum_replies fr
        JOIN forum_posts fp ON fp.id=fr.post_id
        JOIN forum_communities fc ON fc.id=fp.community_id
        WHERE fr.user_id=?
        ORDER BY fr.created_at DESC LIMIT 30
    ");
    $fr->execute(array($user['id']));
    $forumReplies = $fr->fetchAll();
 
    $forumStats['posts']      = count($forumPosts);
    $forumStats['replies']    = count($forumReplies);
    $forumStats['vote_score'] = array_sum(array_column($forumPosts, 'vote_score'))
                              + array_sum(array_column($forumReplies, 'vote_score'));
} catch(Exception $e) {}
$csrf = generateCSRFToken();

// XP e Emblemas
$karmaTotal    = (int)($user['karma_total'] ?? 0);
$currentLevel  = getUserLevel($karmaTotal);
$nextLevelXP   = $currentLevel['next'];
$xpProgress    = 100;
if ($nextLevelXP) {
    $range = $nextLevelXP - $currentLevel['min'];
    $currentRange = $karmaTotal - $currentLevel['min'];
    $xpProgress = min(100, max(0, round(($currentRange / $range) * 100)));
}

$availableBadges = getAvailableBadges((int)$user['id']);
$topBadges       = getTopBadges((int)$user['id']);
$topBadgeIds     = array_column($topBadges, 'id');
$expLvl    = $user['experience_level'] ?? 'iniciante';
$bio       = $user['bio'] ?? '';
$loc       = $user['location'] ?? '';
$web       = $user['website'] ?? '';
$avUrl     = $user['avatar_url'] ?? '';
$expLabels = ['iniciante'=>'Iniciante','intermedio'=>'Intermédio','avancado'=>'Avançado','profissional'=>'Profissional'];

$activeTab = 'info';
if (!empty($_POST['action'])) {
    $map = ['add_printer'=>'printers','edit_printer'=>'printers','remove_printer'=>'printers','add_slicer'=>'slicers','edit_slicer'=>'slicers','remove_slicer'=>'slicers','add_material'=>'materials','edit_material'=>'materials','remove_material'=>'materials','change_password'=>'security'];
    $activeTab = $map[$_POST['action']] ?? 'info';
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Perfil — <?php echo sanitize($user['full_name']); ?></title>
<link rel="icon" type="image/x-icon"  href="/favicon.ico">
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png">
<link rel="apple-touch-icon" sizes="192x192" href="/favicon-192.png">
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.3/dist/confetti.browser.min.js"></script>
<style>
  :root {
    --bg:#0a0a0f; --surface:#111118; --surface2:#1a1a26; --surface3:#222235;
    --accent:#00e5ff; --accent2:#ff6b35; --accent3:#7c3aed; --accent4:#00ff88;
    --text:#e8e8f0; --muted:#888899; --border:rgba(0,229,255,0.15);
    --glow:0 0 40px rgba(0,229,255,0.15); --glow2:0 0 40px rgba(255,107,53,0.15);
  }
  *{margin:0;padding:0;box-sizing:border-box}
  html{scroll-behavior:smooth}
  body{background:var(--bg);color:var(--text);font-family:'Inter',sans-serif;overflow-x:hidden;line-height:1.6}
  body::before{content:'';position:fixed;inset:0;background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.04'/%3E%3C/svg%3E");pointer-events:none;z-index:9999;opacity:0.4}

  /* USER BAR */
  .user-bar{position:fixed;top:0;right:0;left:0;height:50px;background:var(--surface);border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:flex-end;padding:0 24px;z-index:90;gap:16px}
  .user-info{display:flex;align-items:center;gap:10px;color:var(--muted);font-size:13px}
  .user-avatar{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--accent),var(--accent3));display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#fff;overflow:hidden}
  .user-avatar img{width:100%;height:100%;object-fit:cover;border-radius:50%}
  .user-name{color:var(--text);font-weight:600}
  .user-role{font-family:'Space Mono',monospace;font-size:9px;padding:2px 8px;border-radius:4px;text-transform:uppercase}
  .user-role.master{background:transparent;color:var(--text);border:1px solid var(--border);padding:1px 6px}
  .user-role.master{background:transparent;color:var(--text);border:1px solid var(--border);padding:1px 6px}
  .user-role.admin{background:rgba(255,107,53,0.2);color:var(--accent2)}
  .user-role.moderator{background:rgba(124,58,237,0.2);color:#a78bfa}
  .user-role.user{background:rgba(0,229,255,0.1);color:var(--accent)}
  .btn-auth{padding:8px 16px;border-radius:8px;font-family:'Space Mono',monospace;font-size:11px;text-decoration:none;transition:all 0.2s}
  .btn-profile{background:transparent;border:1px solid var(--accent);color:var(--accent)}
  .btn-profile:hover{box-shadow:0 0 16px rgba(0,229,255,0.2)}
  .btn-logout{background:transparent;border:1px solid var(--border);color:var(--muted);padding:6px 12px;font-size:10px}
  .btn-logout:hover{border-color:var(--accent2);color:var(--accent2)}

  /* SIDEBAR */
  nav{position:fixed;left:0;top:0;bottom:0;width:280px;background:var(--surface);border-right:1px solid var(--border);display:flex;flex-direction:column;padding:32px 0;z-index:100;overflow-y:auto;transition:transform 0.3s ease}
  .nav-logo{padding:0 24px 24px;border-bottom:1px solid var(--border);margin-bottom:16px}
  .nav-logo .label{font-family:'Space Mono',monospace;font-size:10px;color:var(--accent);letter-spacing:3px;text-transform:uppercase;margin-bottom:8px}
  .nav-logo h1{font-family:'Syne',sans-serif;font-size:22px;font-weight:800;line-height:1.2;color:#fff}
  .nav-logo h1 span{color:var(--accent)}
  nav a{display:flex;align-items:center;gap:12px;padding:12px 24px;color:var(--muted);text-decoration:none;font-size:13px;font-weight:500;transition:all 0.2s;border-left:3px solid transparent}
  nav a:hover,nav a.active{color:var(--accent);border-left-color:var(--accent);background:rgba(0,229,255,0.05)}
  nav a .icon{width:20px;font-size:16px;text-align:center}
  .nav-section{font-family:'Space Mono',monospace;font-size:9px;letter-spacing:3px;color:var(--muted);text-transform:uppercase;padding:20px 24px 8px;opacity:0.6}

  /* MENU TOGGLE + PROGRESS + BACK TO TOP */
  .menu-toggle{display:none;position:fixed;top:8px;left:14px;z-index:1300;width:42px;height:42px;background:rgba(17,17,24,0.96);border:1px solid var(--border);border-radius:12px;padding:0;cursor:pointer;font-size:20px;line-height:1;color:var(--text);align-items:center;justify-content:center;box-shadow:0 10px 30px rgba(0,0,0,0.35);backdrop-filter:blur(12px)}
  .menu-toggle:hover{border-color:var(--accent);color:var(--accent)}
  .sidebar-backdrop{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.52);z-index:1100;backdrop-filter:blur(2px)}
  .sidebar-backdrop.open{display:block}
  .progress-bar{position:fixed;top:50px;left:0;right:0;height:3px;background:var(--surface);z-index:1000}
  .progress-fill{height:100%;background:linear-gradient(90deg,var(--accent),var(--accent3));width:0%;transition:width 0.1s}
  .back-to-top{position:fixed;bottom:30px;right:30px;width:50px;height:50px;background:var(--surface);border:1px solid var(--border);border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;opacity:0;visibility:hidden;transition:all 0.3s;z-index:100;font-size:20px}
  .back-to-top.visible{opacity:1;visibility:visible}
  .back-to-top:hover{background:var(--accent);border-color:var(--accent);transform:translateY(-4px)}

  /* MAIN */
  main{min-height:100vh;padding-top:50px}

  /* HERO */
  .profile-hero{position:relative;padding:50px 60px;overflow:hidden;border-bottom:1px solid var(--border);background:linear-gradient(135deg,#0a0a0f 0%,#0d0d1a 50%,#0a0a0f 100%);display:flex;gap:32px;align-items:center;flex-wrap:wrap}
  .hero-grid{position:absolute;inset:0;background-image:linear-gradient(rgba(0,229,255,0.04) 1px,transparent 1px),linear-gradient(90deg,rgba(0,229,255,0.04) 1px,transparent 1px);background-size:40px 40px;animation:gridMove 20s linear infinite}
  @keyframes gridMove{0%{transform:translate(0,0)}100%{transform:translate(40px,40px)}}
  .hero-glow{position:absolute;top:-100px;right:-100px;width:500px;height:500px;background:radial-gradient(circle,rgba(124,58,237,0.2) 0%,transparent 70%);pointer-events:none;animation:pulseGlow 4s ease-in-out infinite}
  @keyframes pulseGlow{0%,100%{opacity:0.5;transform:scale(1)}50%{opacity:0.8;transform:scale(1.1)}}

  .hero-avatar-wrap{position:relative;z-index:1;flex-shrink:0}
  .hero-avatar{width:110px;height:110px;border-radius:50%;border:3px solid var(--accent);background:linear-gradient(135deg,var(--accent3),var(--accent));display:flex;align-items:center;justify-content:center;font-family:'Syne',sans-serif;font-size:36px;font-weight:800;color:#fff;box-shadow:0 0 32px rgba(0,229,255,0.25);overflow:hidden}
  .hero-avatar img{width:100%;height:100%;object-fit:cover;border-radius:50%}
  .hero-avatar-btn{position:absolute;bottom:2px;right:2px;width:32px;height:32px;border-radius:50%;background:var(--accent);border:2px solid var(--bg);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:14px;transition:all 0.2s}
  .hero-avatar-btn:hover{transform:scale(1.15);box-shadow:0 0 12px rgba(0,229,255,0.4)}

  .hero-info{flex:1;min-width:200px;position:relative;z-index:1}
  .hero-info h1{font-family:'Syne',sans-serif;font-size:32px;font-weight:800;color:#fff;margin-bottom:4px;line-height:1.1}
  .hero-info .username{font-family:'Space Mono',monospace;font-size:12px;color:var(--accent);margin-bottom:14px;letter-spacing:1px}
  .hero-meta{display:flex;gap:14px;flex-wrap:wrap;align-items:center}
  .meta-item{display:flex;align-items:center;gap:6px;font-size:13px;color:var(--muted)}
  .meta-item a{color:var(--accent);text-decoration:none}
  .badge-exp{padding:4px 14px;border-radius:100px;font-family:'Space Mono',monospace;font-size:10px;letter-spacing:1px;text-transform:uppercase;font-weight:700}
  .badge-exp.iniciante{background:rgba(0,229,255,0.1);color:var(--accent);border:1px solid rgba(0,229,255,0.3)}
  .badge-exp.intermedio{background:rgba(255,107,53,0.1);color:var(--accent2);border:1px solid rgba(255,107,53,0.3)}
  .badge-exp.avancado{background:rgba(124,58,237,0.1);color:#a78bfa;border:1px solid rgba(124,58,237,0.3)}
  .badge-exp.profissional{background:rgba(0,255,136,0.1);color:var(--accent4);border:1px solid rgba(0,255,136,0.3)}
  .hero-bio{color:var(--muted);font-size:14px;line-height:1.7;margin-top:12px;max-width:560px}

  .hero-stats{display:flex;flex-direction:column;gap:16px;position:relative;z-index:1;flex-shrink:0}
  .stat-box{text-align:right}
  .stat-num{font-family:'Syne',sans-serif;font-size:28px;font-weight:800;color:var(--accent);line-height:1}
  .stat-lbl{font-family:'Space Mono',monospace;font-size:9px;color:var(--muted);letter-spacing:2px;text-transform:uppercase;margin-top:2px}

  /* TABS BAR */
  .tabs-bar{position:sticky;top:50px;z-index:50;display:flex;gap:0;border-bottom:1px solid var(--border);backdrop-filter:blur(12px);background:rgba(10,10,15,0.92);padding:0 60px;overflow-x:auto}
  .tab-btn{padding:16px 22px;border:none;background:transparent;color:var(--muted);font-family:'Space Mono',monospace;font-size:11px;letter-spacing:1px;text-transform:uppercase;cursor:pointer;border-bottom:3px solid transparent;transition:all 0.2s;white-space:nowrap}
  .tab-btn:hover{color:var(--text)}
  .tab-btn.active{color:var(--accent);border-bottom-color:var(--accent)}

  /* TAB PANELS */
  .tab-panel{display:none}
  .tab-panel.active{display:block}

  /* SECTION */
  .section{padding:50px 60px;border-bottom:1px solid var(--border)}
  .section-header{display:flex;align-items:flex-start;gap:20px;margin-bottom:36px}
  .section-number{font-family:'Space Mono',monospace;font-size:48px;font-weight:700;color:var(--border);line-height:1;flex-shrink:0;margin-top:-4px;transition:color 0.3s}
  .section:hover .section-number{color:var(--accent)}
  .section-title h2{font-family:'Syne',sans-serif;font-size:28px;font-weight:800;color:#fff;margin-bottom:6px}
  .section-title p{color:var(--muted);font-size:14px}

  /* CARD */
  .card{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:28px;margin-bottom:20px;transition:all 0.3s;position:relative;overflow:hidden}
  .card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,var(--accent),var(--accent3));transform:scaleX(0);transform-origin:left;transition:transform 0.3s}
  .card:hover{border-color:rgba(0,229,255,0.25);box-shadow:var(--glow)}
  .card:hover::before{transform:scaleX(1)}
  .card-title{font-family:'Syne',sans-serif;font-size:16px;font-weight:700;color:#fff;margin-bottom:22px;display:flex;align-items:center;gap:10px}
  .card-title .icon{width:34px;height:34px;border-radius:9px;background:rgba(0,229,255,0.1);display:flex;align-items:center;justify-content:center;font-size:17px}

  /* FORM */
  .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
  .form-group{margin-bottom:0}
  .form-group.full{grid-column:1/-1}
  .form-group label{display:block;font-family:'Space Mono',monospace;font-size:10px;color:var(--muted);margin-bottom:8px;text-transform:uppercase;letter-spacing:1px}
  .form-group input,.form-group select,.form-group textarea{width:100%;background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:13px 16px;color:var(--text);font-family:'Inter',sans-serif;font-size:14px;transition:all 0.2s}
  .form-group textarea{resize:vertical;min-height:80px}
  .form-group input:focus,.form-group select:focus,.form-group textarea:focus{outline:none;border-color:var(--accent);box-shadow:0 0 20px rgba(0,229,255,0.1)}
  .form-group input::placeholder,.form-group textarea::placeholder{color:var(--muted);opacity:0.6}
  .form-group select{appearance:none;cursor:pointer;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%23888899' stroke-width='1.5' fill='none'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 14px center;padding-right:36px}
  .form-group select option{background:var(--surface2)}

  /* BOTÕES */
  .btn{padding:11px 24px;border:none;border-radius:10px;font-family:'Space Mono',monospace;font-size:11px;font-weight:700;letter-spacing:1px;cursor:pointer;transition:all 0.2s;display:inline-flex;align-items:center;gap:7px}
  .btn-primary{background:linear-gradient(135deg,var(--accent),var(--accent3));color:#000}
  .btn-primary:hover{transform:translateY(-2px);box-shadow:0 6px 24px rgba(0,229,255,0.3)}
  .btn-secondary{background:var(--surface3);color:var(--muted);border:1px solid var(--border)}
  .btn-secondary:hover{color:var(--text);background:var(--surface2)}
  .btn-danger{background:rgba(255,68,68,0.12);color:#ff7777;border:1px solid rgba(255,68,68,0.25);padding:7px 14px;font-size:10px}
  .btn-danger:hover{background:rgba(255,68,68,0.22)}
  .btn-edit{background:rgba(0,229,255,0.08);color:var(--accent);border:1px solid rgba(0,229,255,0.2);padding:7px 14px;font-size:10px}
  .btn-edit:hover{background:rgba(0,229,255,0.15)}
  .btn-save{background:rgba(0,255,136,0.1);color:var(--accent4);border:1px solid rgba(0,255,136,0.25);padding:7px 14px;font-size:10px}
  .btn-save:hover{background:rgba(0,255,136,0.18)}

  /* ALERTS */
  .alert{padding:14px 20px;border-radius:10px;margin-bottom:0;font-size:14px}
  .alert-error{background:rgba(255,68,68,0.08);border:1px solid rgba(255,68,68,0.25);color:#ff8888}
  .alert-success{background:rgba(0,255,136,0.08);border:1px solid rgba(0,255,136,0.25);color:#00ff88}

  /* ITEM LIST */
  .item-list{display:flex;flex-direction:column;gap:10px;margin-bottom:16px}
  .item-card{background:var(--surface2);border:1px solid var(--border);border-radius:12px;overflow:hidden;transition:all 0.3s}
  .item-card:hover{border-color:rgba(0,229,255,0.3);transform:translateX(4px)}
  .item-view{padding:16px 20px;display:flex;align-items:flex-start;justify-content:space-between;gap:12px}
  .item-main{flex:1;min-width:0}
  .item-title{font-family:'Syne',sans-serif;font-weight:700;font-size:16px;color:#fff;margin-bottom:4px}
  .item-sub{font-size:12px;color:var(--muted);margin-top:3px}
  .item-badges{display:flex;gap:6px;margin-top:8px;flex-wrap:wrap}
  .item-actions{display:flex;gap:8px;flex-shrink:0;align-items:flex-start}

  .tag{padding:3px 10px;border-radius:20px;font-family:'Space Mono',monospace;font-size:10px;background:var(--surface3);color:var(--muted);border:1px solid var(--border)}
  .tag.fdm{background:rgba(0,229,255,0.07);color:var(--accent)}
  .tag.sla{background:rgba(124,58,237,0.1);color:#a78bfa}
  .tag.sls{background:rgba(255,107,53,0.1);color:var(--accent2)}
  .tag.msla{background:rgba(0,255,136,0.07);color:var(--accent4)}
  .tag.outro{background:rgba(136,136,153,0.1);color:var(--muted)}

  /* EDIT PANEL */
  .item-edit-panel{display:none;padding:16px 20px 20px;background:var(--surface3);border-top:1px solid var(--border);animation:slideDown 0.2s ease}
  .item-edit-panel.open{display:block}
  @keyframes slideDown{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}
  .edit-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px}
  .edit-grid .full{grid-column:1/-1}

  /* ADD FORM */
  .add-toggle{width:100%;background:transparent;border:1px dashed rgba(0,229,255,0.2);border-radius:10px;padding:13px;color:var(--muted);font-family:'Space Mono',monospace;font-size:11px;letter-spacing:1px;cursor:pointer;transition:all 0.2s;display:flex;align-items:center;gap:8px;justify-content:center}
  .add-toggle:hover{border-color:var(--accent);color:var(--accent);background:rgba(0,229,255,0.03)}
  .add-panel{display:none;background:var(--surface2);border:1px solid var(--border);border-radius:12px;padding:22px;margin-top:10px}
  .add-panel.open{display:block;animation:slideDown 0.2s ease}

  /* PASSWORD STRENGTH */
  .pw-strength{height:3px;background:var(--surface3);border-radius:2px;margin-top:6px}
  .pw-bar{height:100%;width:0;border-radius:2px;transition:all 0.3s}
  .pw-bar.weak{width:33%;background:#ff4444}
  .pw-bar.medium{width:66%;background:var(--accent2)}
  .pw-bar.strong{width:100%;background:var(--accent4)}

  /* DIVIDER */
  .divider{border:none;border-top:1px solid var(--border);margin:28px 0}

  /* EMPTY */
  .empty-state{text-align:center;padding:36px 20px;color:var(--muted);font-size:14px}
  .empty-icon{font-size:42px;margin-bottom:12px}

  /* FOOTER */
  footer{padding:40px 60px;text-align:center;color:var(--muted);font-size:13px;font-family:'Space Mono',monospace;border-top:1px solid var(--border);background:var(--surface)}
  footer strong{color:var(--accent)}

  ::-webkit-scrollbar{width:8px}
  ::-webkit-scrollbar-track{background:var(--bg)}
  ::-webkit-scrollbar-thumb{background:var(--border);border-radius:4px}
  ::-webkit-scrollbar-thumb:hover{background:rgba(0,229,255,0.3)}

  /* WIDGET XP */
  .xp-widget{background:rgba(0,0,0,0.2);border:1px solid var(--border);border-radius:12px;padding:12px 16px;min-width:200px}
  .xp-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px}
  .xp-level-name{font-family:'Space Mono',monospace;font-size:10px;text-transform:uppercase;letter-spacing:1px;font-weight:700}
  .xp-value{font-family:'Space Mono',monospace;font-size:11px;color:var(--muted)}
  .xp-bar-bg{height:6px;background:rgba(255,255,255,0.05);border-radius:10px;overflow:hidden}
  .xp-bar-fill{height:100%;transition:width 0.5s ease-out;box-shadow:0 0 10px rgba(0,229,255,0.3)}
  .xp-next{font-family:'Space Mono',monospace;font-size:9px;color:var(--muted);margin-top:6px;text-align:right}

  /* STREAK & GROWTH */
  .streak-fire {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-weight: 800;
    font-family: 'Syne', sans-serif;
    filter: drop-shadow(0 0 5px currentColor);
    vertical-align: middle;
  }
  .growth-widget {
    background: rgba(0, 255, 136, 0.05);
    border: 1px solid rgba(0, 255, 136, 0.1);
    border-radius: 12px;
    padding: 12px 16px;
    margin-top: 12px;
  }
  .growth-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
    color: var(--accent4);
    font-family: 'Space Mono', monospace;
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 1px;
  }
  .growth-bar-bg { height: 6px; background: rgba(0, 255, 136, 0.1); border-radius: 10px; overflow: hidden; }
  .growth-bar-fill { height: 100%; background: var(--accent4); transition: width 0.5s ease-out; box-shadow: 0 0 10px rgba(0, 255, 136, 0.3); }
  .growth-footer { font-family: 'Space Mono', monospace; font-size: 9px; color: var(--muted); margin-top: 6px; text-align: right; }

  .badges-row{display:flex;gap:8px;margin-top:12px}
  .badge-slot{width:32px;height:32px;border-radius:8px;background:var(--surface3);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:18px;transition:all 0.2s}
  .badge-slot:hover{transform:scale(1.1);border-color:var(--accent)}

  .badge-picker{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:12px}
  .badge-item{background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:12px;cursor:pointer;transition:all 0.2s;position:relative}
  .badge-item:hover{border-color:var(--accent);background:var(--surface3)}
  .badge-item.selected{border-color:var(--accent);background:rgba(0,229,255,0.05);box-shadow:inset 0 0 10px rgba(0,229,255,0.1)}
  .badge-item.selected::after{content:'✓';position:absolute;top:5px;right:8px;color:var(--accent);font-weight:700;font-size:12px}
  .badge-item input{display:none}
  .badge-icon{font-size:24px;margin-bottom:8px;display:block}
  .badge-name{display:block;font-weight:700;font-size:12px;color:#fff;margin-bottom:2px}
  .badge-desc{display:block;font-size:10px;color:var(--muted);line-height:1.2}

  #avatarFileInput{display:none}

  @media(max-width:1024px){
    .user-bar{left:0;padding:0 16px;z-index:900}
    main{margin-left:0}
    .profile-hero{padding:70px 24px 40px}
    .hero-stats{flex-direction:row;gap:24px}
    .stat-box{text-align:left}
    .tabs-bar{padding:0 24px}
    .section{padding:40px 24px}
    .form-grid,.edit-grid{grid-template-columns:1fr}
    .progress-bar{left:0}
  }
  @media(max-width:600px){
    .user-bar{gap:8px;padding-left:66px}
    .user-info{min-width:0;gap:8px}
    .user-name{max-width:34vw;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .user-role{display:none}
    .btn-auth{padding:7px 10px;font-size:10px}
    .hero-stats{display:none}
    .profile-hero{gap:20px;padding-top:92px}
  }
</style>
</head>
<body>
<?php renderUserNotice(); ?>

<div class="sidebar-backdrop" id="sidebarBackdrop" onclick="closeProfileMenu()"></div>

<!-- USER BAR -->
<div class="user-bar">
  <a href="index.php" class="btn-auth btn-profile" style="margin-right: auto; border-color: var(--muted); color: var(--muted);">← Manual</a>
  <div class="user-info">
    <div class="user-avatar">
      <?php if (!empty($avUrl) && file_exists(__DIR__.'/'.$avUrl)): ?>
        <img src="<?php echo sanitize($avUrl); ?>?v=<?php echo time(); ?>" alt="">
      <?php else: ?>
        <?php echo sanitize($user['avatar'] ?? '??'); ?>
      <?php endif; ?>
    </div>
    <span class="user-name"><?php echo sanitize($user['full_name']); ?></span>
    <span class="user-role <?php echo $user['role']; ?>"><?php echo $user['role']; ?></span>
  </div>
  <a href="perfil.php" class="btn-auth btn-profile">Perfil</a>
  <a href="logout.php" class="btn-auth btn-logout">Sair</a>
</div>

<!-- PROGRESS BAR -->
<div class="progress-bar"><div class="progress-fill" id="progressFill"></div></div>

<!-- MAIN -->
<main>

<!-- HERO -->
<div class="profile-hero">
  <div class="hero-grid"></div>
  <div class="hero-glow"></div>

  <form method="POST" enctype="multipart/form-data" id="avatarForm" style="display:none">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
    <input type="hidden" name="action" value="upload_avatar">
    <input type="file" id="avatarFileInput" name="avatar" accept="image/*">
  </form>

  <div class="hero-avatar-wrap">
    <div class="hero-avatar">
      <?php if (!empty($avUrl) && file_exists(__DIR__.'/'.$avUrl)): ?>
        <img src="<?php echo sanitize($avUrl); ?>?v=<?php echo time(); ?>" alt="Foto de perfil">
      <?php else: ?>
        <?php echo sanitize($user['avatar'] ?? '??'); ?>
      <?php endif; ?>
    </div>
    <label for="avatarFileInput" class="hero-avatar-btn" title="Alterar foto">📷</label>
  </div>

  <div class="hero-info">
    <h1>
        <?php echo sanitize($user['full_name']); ?>
        <?php if ($streakCount > 0): ?>
            <span class="streak-fire" style="color: <?php echo $streakColor; ?>" title="Streak de <?php echo $streakCount; ?> dias!">
                🔥 <?php echo $streakCount; ?>
            </span>
        <?php endif; ?>
    </h1>
    <div class="username">@<?php echo sanitize($user['username']); ?></div>
    <div class="hero-meta">
      <span class="badge-exp" style="background:<?php echo $currentLevel['color']; ?>22; color:<?php echo $currentLevel['color']; ?>; border:1px solid <?php echo $currentLevel['color']; ?>55">
        <?php echo $currentLevel['name']; ?>
      </span>
      <?php if (!empty($loc)): ?><span class="meta-item">📍 <?php echo sanitize($loc); ?></span><?php endif; ?>
      <?php if (!empty($web)): ?><span class="meta-item">🌐 <a href="<?php echo sanitize($web); ?>" target="_blank" rel="noopener"><?php echo sanitize(parse_url($web,PHP_URL_HOST)?:$web); ?></a></span><?php endif; ?>
      <span class="meta-item">📅 Membro desde <?php echo date('M Y', strtotime($user['created_at'])); ?></span>
    </div>
    <div class="badges-row">
        <?php foreach($topBadges as $tb): ?>
            <div class="badge-slot" title="<?php echo sanitize($tb['name'] . ': ' . $tb['desc']); ?>">
                <?php if($tb['category'] === 'badge' || $tb['category'] === 'medal'): ?>
                    <?php echo $tb['icon']; ?>
                <?php elseif($tb['category'] === 'frame'): ?>
                    <div style="width:18px;height:18px;border-radius:50%;<?php echo $tb['icon']; ?>"></div>
                <?php elseif($tb['category'] === 'accent'): ?>
                    <div style="width:16px;height:16px;border-radius:50%;background:<?php echo $tb['icon']; ?>"></div>
                <?php else: ?>
                    🏅
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <?php for($i=count($topBadges); $i<3; $i++): ?>
            <div class="badge-slot" style="opacity:0.2; border-style:dashed">?</div>
        <?php endfor; ?>
    </div>
    <?php if (!empty($bio)): ?><div class="hero-bio"><?php echo nl2br(sanitize($bio)); ?></div><?php endif; ?>
  </div>

    <div class="hero-stats">
    <div class="xp-widget">
        <div class="xp-header">
            <span class="xp-level-name" style="color:<?php echo $currentLevel['color']; ?>"><?php echo $currentLevel['name']; ?></span>
            <span class="xp-value"><?php echo number_format($karmaTotal); ?> XP</span>
        </div>
        <div class="xp-bar-bg">
            <div class="xp-bar-fill" style="width: <?php echo $xpProgress; ?>%; background: <?php echo $currentLevel['color']; ?>"></div>
        </div>
        <?php if ($nextLevelXP): ?>
            <div class="xp-next">Faltam <?php echo ($nextLevelXP - $karmaTotal); ?> XP para o próximo nível</div>
        <?php else: ?>
            <div class="xp-next">Nível máximo atingido!</div>
        <?php endif; ?>
    </div>

    <div class="growth-widget" style="margin-top: 15px; background: rgba(0, 255, 136, 0.05); border: 1px solid rgba(0, 255, 136, 0.1); border-radius: 12px; padding: 12px 16px;">
        <div class="growth-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
            <span style="color: var(--accent4); font-family: 'Space Mono', monospace; font-size: 10px; text-transform: uppercase; letter-spacing: 1px;">🌱 Crescimento</span>
            <span style="font-family: 'Space Mono', monospace; font-size: 11px; color: #fff;"><?php echo $growthPoints; ?> GP</span>
        </div>
        <div class="growth-bar-bg" style="height: 6px; background: rgba(255,255,255,0.05); border-radius: 10px; overflow: hidden;">
            <?php
                // Planta evolui a cada 100 GP
                $plantLevel = floor($growthPoints / 100);
                $plantProgress = $growthPoints % 100;
                $stages = ['Semente', 'Broto', 'Plântula', 'Pequena Árvore', 'Árvore Maker', 'Grande Carvalho Tech'];
                $currentStage = $stages[min($plantLevel, count($stages)-1)];
            ?>
            <div class="growth-bar-fill" style="width: <?php echo $plantProgress; ?>%; height: 100%; background: var(--accent4); box-shadow: 0 0 10px rgba(0,255,136,0.3);"></div>
        </div>
        <div class="growth-footer" style="font-family: 'Space Mono', monospace; font-size: 9px; color: var(--muted); margin-top: 6px; text-align: right;">
            Estágio: <strong style="color: var(--accent4);"><?php echo $currentStage; ?></strong> (Nível <?php echo $plantLevel; ?>)
        </div>
    </div>

    <div style="display:flex; gap:16px; margin-top:8px">
        <div class="stat-box"><div class="stat-num"><?php echo count($printers); ?></div><div class="stat-lbl">Impressoras</div></div>
        <div class="stat-box"><div class="stat-num"><?php echo count($slicers); ?></div><div class="stat-lbl">Slicers</div></div>
        <div class="stat-box"><div class="stat-num"><?php echo count($materials); ?></div><div class="stat-lbl">Materiais</div></div>
    </div>
  </div>
</div>

<!-- ALERTS -->
<?php if (!empty($errors) || !empty($success)): ?>
<div style="padding:20px 60px 0;">
  <?php if (!empty($errors)): ?><div class="alert alert-error">⚠️ <?php echo sanitize($errors[0]); ?></div><?php endif; ?>
  <?php if (!empty($success)): ?><div class="alert alert-success">✓ <?php echo sanitize($success[0]); ?></div><?php endif; ?>
</div>
<?php endif; ?>

<!-- TABS BAR -->
<div class="tabs-bar" id="tabsBar">
  <button class="tab-btn active" id="tb-info"      onclick="switchTab('info',this)">👤 Informações</button>
  <button class="tab-btn"        id="tb-missions"  onclick="switchTab('missions',this)">🎯 Missões</button>
  <button class="tab-btn"        id="tb-printers"  onclick="switchTab('printers',this)">🖨️ Impressoras</button>
  <button class="tab-btn"        id="tb-slicers"   onclick="switchTab('slicers',this)">⚙️ Slicers</button>
  <button class="tab-btn"        id="tb-materials" onclick="switchTab('materials',this)">🧵 Materiais</button>
  <button class="tab-btn"        id="tb-forum"    onclick="switchTab('forum',this)">🌐 Fórum (<?php echo $forumStats['posts']; ?>)</button>
  <button class="tab-btn"        id="tb-comments" onclick="switchTab('comments',this)">💬 Comentários (<?php echo count($myComments); ?>)</button>
  <button class="tab-btn"        id="tb-security"  onclick="switchTab('security',this)">🔒 Segurança</button>
</div>

<!-- ════ TAB: INFORMAÇÕES ════ -->
<div class="tab-panel active" id="tab-info">
  <section class="section">
    <div class="section-header">
      <div class="section-number">01</div>
      <div class="section-title"><h2>Informações Pessoais</h2><p>Atualiza o teu perfil público na comunidade</p></div>
    </div>
    <div class="card">
      <div class="card-title"><span class="icon">✏️</span> Editar Perfil</div>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
        <input type="hidden" name="action" value="update_profile">
        <div class="form-grid" style="margin-bottom:16px;">
          <div class="form-group"><label>Nome Completo</label><input type="text" name="full_name" value="<?php echo sanitize($user['full_name']); ?>" required></div>
          <div class="form-group">
            <label>Nível de Experiência</label>
            <select name="experience_level">
              <?php foreach($expLabels as $v=>$l): ?><option value="<?php echo $v; ?>" <?php echo $expLvl===$v?'selected':''; ?>><?php echo $l; ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label>Localização</label><input type="text" name="location" value="<?php echo sanitize($loc); ?>" placeholder="ex: Lisboa, Portugal"></div>
          <div class="form-group"><label>Data de Nascimento</label><input type="date" name="birth_date" value="<?php echo $birthDate; ?>"></div>
          <div class="form-group"><label>Website / Portfolio</label><input type="url" name="website" value="<?php echo sanitize($web); ?>" placeholder="https://..."></div>
          <div class="form-group full"><label>Sobre mim</label><textarea name="bio" placeholder="Conta um pouco sobre ti e a tua experiência com impressão 3D..."><?php echo sanitize($bio); ?></textarea></div>
        </div>
        <button type="submit" class="btn btn-primary">💾 GUARDAR ALTERAÇÕES</button>
      </form>
    </div>

    <div class="card">
      <div class="card-title"><span class="icon">🎖️</span> Emblemas em Destaque</div>
      <p style="color:var(--muted);font-size:13px;margin-bottom:20px;">Seleciona até 3 emblemas conquistados para exibir no teu perfil.</p>

      <?php if (empty($availableBadges)): ?>
        <div class="empty-state">Ainda não conquistaste emblemas. Participa no fórum para ganhar XP!</div>
      <?php else: ?>
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
          <input type="hidden" name="action" value="update_top_badges">
          <div class="badge-picker">
            <?php foreach ($availableBadges as $badge): $sel = in_array($badge['id'], $topBadgeIds); ?>
              <label class="badge-item <?php echo $sel?'selected':''; ?>" id="lbl-<?php echo $badge['id']; ?>">
                <input type="checkbox" name="badges[]" value="<?php echo $badge['id']; ?>" <?php echo $sel?'checked':''; ?>
                       onchange="this.parentElement.classList.toggle('selected', this.checked); updateBadgeCount(this)">
                <span class="badge-icon"><?php echo $badge['icon']; ?></span>
                <span class="badge-name"><?php echo sanitize($badge['name']); ?></span>
                <span class="badge-desc"><?php echo sanitize($badge['desc']); ?></span>
              </label>
            <?php endforeach; ?>
          </div>
          <div style="margin-top:20px; display:flex; align-items:center; gap:16px">
            <button type="submit" class="btn btn-primary" id="btnUpdateBadges">💾 ATUALIZAR DESTAQUES</button>
            <span id="badgeCountMsg" style="font-size:11px; color:var(--muted); font-family:'Space Mono',monospace;">
                <?php echo count($topBadgeIds); ?> / 3 selecionados
            </span>
          </div>
        </form>
      <?php endif; ?>
    </div>
    <div class="card">
      <div class="card-title"><span class="icon">📷</span> Foto de Perfil</div>
      <p style="color:var(--muted);font-size:14px;line-height:1.7;margin-bottom:18px;">Clica no ícone 📷 na tua foto ou usa o botão abaixo. Formatos: JPG, PNG, GIF, WebP (máx. 2MB).</p>
      <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
        <label for="avatarFileInput" class="btn btn-secondary" style="cursor:pointer;">📷 ESCOLHER IMAGEM</label>
        <?php if (!empty($avUrl) && file_exists(__DIR__.'/'.$avUrl)): ?>
          <span style="font-size:13px;color:var(--accent4);">✓ Foto personalizada ativa</span>
        <?php else: ?>
          <span style="font-size:13px;color:var(--muted);">A usar avatar gerado automaticamente</span>
        <?php endif; ?>
      </div>
    </div>
  </section>
</div>

<!-- ════ TAB: MISSÕES ════ -->
<div class="tab-panel" id="tab-missions">
  <section class="section">
    <div class="section-header">
      <div class="section-number">02</div>
      <div class="section-title"><h2>Missões Diárias e Semanais</h2><p>Completa objetivos para ganhar XP e pontos de crescimento para a tua planta</p></div>
    </div>

    <div class="card">
        <div class="card-title"><span class="icon">🌱</span> Estado da Planta</div>
        <div class="growth-widget" style="margin-top: 0; background: rgba(0, 255, 136, 0.05); border: 1px solid rgba(0, 255, 136, 0.1); border-radius: 12px; padding: 18px 22px;">
            <div class="growth-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <span style="color: var(--accent4); font-family: 'Space Mono', monospace; font-size: 11px; text-transform: uppercase; letter-spacing: 2px; font-weight: 700;">Progresso da Planta</span>
                <span style="font-family: 'Space Mono', monospace; font-size: 14px; font-weight: 700; color: #fff;"><?php echo $growthPoints; ?> GP</span>
            </div>
            <div class="growth-bar-bg" style="height: 10px; background: rgba(255,255,255,0.05); border-radius: 10px; overflow: hidden; margin-bottom: 12px;">
                <div class="growth-bar-fill" style="width: <?php echo $plantProgress; ?>%; height: 100%; background: linear-gradient(90deg, var(--accent4), #00ffaa); box-shadow: 0 0 15px rgba(0,255,136,0.4);"></div>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: flex-end;">
                <div style="font-size: 12px; color: var(--muted); line-height: 1.4;">
                    Estágio atual: <strong style="color: #fff;"><?php echo $currentStage; ?></strong><br>
                    Próximo nível: <strong><?php echo (100 - $plantProgress); ?> GP</strong>
                </div>
                <div style="font-family: 'Space Mono', monospace; font-size: 10px; color: var(--accent4); text-transform: uppercase;">
                    Nível <?php echo $plantLevel; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-title"><span class="icon">🎯</span> Missões Ativas</div>

        <div style="display: grid; gap: 16px;">
            <?php
            $dailyKeys = array_filter(array_keys($missionDefs), fn($k) => !isset($missionDefs[$k]['type']) || $missionDefs[$k]['type'] === 'daily');
            $weeklyKeys = array_filter(array_keys($missionDefs), fn($k) => ($missionDefs[$k]['type'] ?? '') === 'weekly');
            ?>

            <div style="font-family: 'Syne'; font-size: 12px; color: var(--accent); margin-bottom: 4px; text-transform: uppercase; letter-spacing: 1px;">Objetivos Diários</div>
            <?php foreach($dailyKeys as $key):
                $def = $missionDefs[$key];
                $prog = $userMissions['list'][$key] ?? ['current'=>0, 'completed'=>false, 'claimed'=>false];
                $pct = min(100, ($prog['current'] / $def['goal']) * 100);
            ?>
                <div class="item-card <?php echo $prog['completed'] ? 'completed' : ''; ?>" style="padding: 16px; <?php echo $prog['completed'] ? 'border-color: rgba(0,255,136,0.3); background: rgba(0,255,136,0.02);' : ''; ?>">
                    <div style="display: flex; gap: 14px; align-items: center;">
                        <div style="font-size: 24px; width: 48px; height: 48px; background: var(--surface2); border-radius: 12px; display: flex; align-items: center; justify-content: center;"><?php echo $def['icon']; ?></div>
                        <div style="flex: 1;">
                            <div style="font-weight: 700; color: #fff; font-size: 14px; margin-bottom: 2px;"><?php echo $def['title']; ?></div>
                            <div style="font-size: 12px; color: var(--muted);"><?php echo $def['desc']; ?></div>
                            <div style="margin-top: 8px; height: 4px; background: rgba(255,255,255,0.05); border-radius: 2px; overflow: hidden;">
                                <div style="width: <?php echo $pct; ?>%; height: 100%; background: var(--accent2); transition: width 0.4s;"></div>
                            </div>
                        </div>
                        <div style="text-align: right; min-width: 60px;">
                            <?php if($prog['claimed']): ?>
                                <span style="color: var(--accent4); font-size: 12px; font-weight: 700;">RECLAMADO ✅</span>
                            <?php elseif($prog['completed']): ?>
                                <button class="btn btn-primary" style="padding: 6px 12px; font-size: 10px;" onclick="claimProfileMission('<?php echo $key; ?>')">RECLAMAR</button>
                            <?php else: ?>
                                <span style="font-family: 'Space Mono', monospace; font-size: 12px; color: var(--muted);"><?php echo $prog['current']; ?>/<?php echo $def['goal']; ?></span>
                            <?php endif; ?>
                            <div style="font-family: 'Space Mono', monospace; font-size: 9px; color: var(--accent2); margin-top: 4px;">+<?php echo $def['xp']; ?> XP</div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if(!empty($weeklyKeys)): ?>
                <div style="font-family: 'Syne'; font-size: 12px; color: var(--accent3); margin: 16px 0 4px; text-transform: uppercase; letter-spacing: 1px;">Objetivos Semanais</div>
                <?php foreach($weeklyKeys as $key):
                    $def = $missionDefs[$key];
                    $prog = $userMissions['weekly_list'][$key] ?? ['current'=>0, 'completed'=>false, 'claimed'=>false];
                    $pct = min(100, ($prog['current'] / $def['goal']) * 100);
                ?>
                    <div class="item-card <?php echo $prog['completed'] ? 'completed' : ''; ?>" style="padding: 16px; <?php echo $prog['completed'] ? 'border-color: rgba(124,58,237,0.3); background: rgba(124,58,237,0.02);' : ''; ?>">
                        <div style="display: flex; gap: 14px; align-items: center;">
                            <div style="font-size: 24px; width: 48px; height: 48px; background: var(--surface2); border-radius: 12px; display: flex; align-items: center; justify-content: center;"><?php echo $def['icon']; ?></div>
                            <div style="flex: 1;">
                                <div style="font-weight: 700; color: #fff; font-size: 14px; margin-bottom: 2px;"><?php echo $def['title']; ?></div>
                                <div style="font-size: 12px; color: var(--muted);"><?php echo $def['desc']; ?></div>
                                <div style="margin-top: 8px; height: 4px; background: rgba(255,255,255,0.05); border-radius: 2px; overflow: hidden;">
                                    <div style="width: <?php echo $pct; ?>%; height: 100%; background: var(--accent3); transition: width 0.4s;"></div>
                                </div>
                            </div>
                            <div style="text-align: right; min-width: 60px;">
                                <?php if($prog['claimed']): ?>
                                    <span style="color: var(--accent4); font-size: 12px; font-weight: 700;">RECLAMADO ✅</span>
                                <?php elseif($prog['completed']): ?>
                                    <button class="btn btn-primary" style="padding: 6px 12px; font-size: 10px; background: var(--accent3);" onclick="claimProfileMission('<?php echo $key; ?>')">RECLAMAR</button>
                                <?php else: ?>
                                    <span style="font-family: 'Space Mono', monospace; font-size: 12px; color: var(--muted);"><?php echo $prog['current']; ?>/<?php echo $def['goal']; ?></span>
                                <?php endif; ?>
                                <div style="font-family: 'Space Mono', monospace; font-size: 9px; color: var(--accent3); margin-top: 4px;">+<?php echo $def['xp']; ?> XP</div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
  </section>
</div>

<!-- ════ TAB: IMPRESSORAS ════ -->
<div class="tab-panel" id="tab-printers">
  <section class="section">
    <div class="section-header">
      <div class="section-number">02</div>
      <div class="section-title"><h2>As Minhas Impressoras 3D</h2><p>Adiciona e edita as impressoras que utilizas</p></div>
    </div>
    <div class="card">
      <div class="card-title"><span class="icon">🖨️</span> Impressoras Registadas</div>
      <?php if (empty($printers)): ?>
        <div class="empty-state"><div class="empty-icon">🖨️</div><p>Ainda não adicionaste nenhuma impressora.</p></div>
      <?php else: ?>
        <div class="item-list">
          <?php foreach ($printers as $p): $pid = (int)$p['id']; ?>
          <div class="item-card" id="printer-<?php echo $pid; ?>">
            <div class="item-view">
              <div class="item-main">
                <div class="item-title"><?php echo sanitize($p['brand']); ?> <?php echo sanitize($p['model']); ?></div>
                <?php if (!empty($p['bed_size'])): ?><div class="item-sub">📐 <?php echo sanitize($p['bed_size']); ?></div><?php endif; ?>
                <?php if (!empty($p['notes'])): ?><div class="item-sub">💬 <?php echo sanitize($p['notes']); ?></div><?php endif; ?>
                <div class="item-badges"><span class="tag <?php echo strtolower($p['type']); ?>"><?php echo $p['type']; ?></span></div>
              </div>
              <div class="item-actions">
                <button class="btn btn-edit" onclick="toggleEdit('ep<?php echo $pid; ?>')">✏️ Editar</button>
                <form method="POST" onsubmit="return confirm('Remover esta impressora?')" style="display:inline">
                  <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                  <input type="hidden" name="action" value="remove_printer">
                  <input type="hidden" name="printer_id" value="<?php echo $pid; ?>">
                  <button type="submit" class="btn btn-danger">✕</button>
                </form>
              </div>
            </div>
            <div class="item-edit-panel" id="ep<?php echo $pid; ?>">
              <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                <input type="hidden" name="action" value="edit_printer">
                <input type="hidden" name="printer_id" value="<?php echo $pid; ?>">
                <div class="edit-grid">
                  <div class="form-group"><label>Marca *</label><input type="text" name="printer_brand" value="<?php echo sanitize($p['brand']); ?>" required></div>
                  <div class="form-group"><label>Modelo *</label><input type="text" name="printer_model" value="<?php echo sanitize($p['model']); ?>" required></div>
                  <div class="form-group"><label>Tipo</label><select name="printer_type"><?php foreach(['FDM'=>'FDM (Filamento)','SLA'=>'SLA (Resina)','MSLA'=>'MSLA / LCD','SLS'=>'SLS (Pó)','Outro'=>'Outro'] as $v=>$l): ?><option value="<?php echo $v; ?>" <?php echo $p['type']===$v?'selected':''; ?>><?php echo $l; ?></option><?php endforeach; ?></select></div>
                  <div class="form-group"><label>Área de Impressão</label><input type="text" name="printer_bed" value="<?php echo sanitize($p['bed_size']??''); ?>" placeholder="ex: 256×256×256mm"></div>
                  <div class="form-group full"><label>Notas / Modificações</label><textarea name="printer_notes"><?php echo sanitize($p['notes']??''); ?></textarea></div>
                </div>
                <div style="display:flex;gap:10px;">
                  <button type="submit" class="btn btn-save">💾 GUARDAR</button>
                  <button type="button" class="btn btn-secondary" onclick="toggleEdit('ep<?php echo $pid; ?>')">Cancelar</button>
                </div>
              </form>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <button class="add-toggle" onclick="toggleAdd('addPrinter')">＋ ADICIONAR IMPRESSORA</button>
      <div class="add-panel" id="addPrinter">
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
          <input type="hidden" name="action" value="add_printer">
          <div class="edit-grid">
            <div class="form-group"><label>Marca *</label><input type="text" name="printer_brand" placeholder="ex: Bambu Lab, Prusa, Creality..." required></div>
            <div class="form-group"><label>Modelo *</label><input type="text" name="printer_model" placeholder="ex: X1C, i3 MK4, Ender 3..."></div>
            <div class="form-group"><label>Tipo</label><select name="printer_type"><option value="FDM">FDM (Filamento)</option><option value="SLA">SLA (Resina)</option><option value="MSLA">MSLA / LCD</option><option value="SLS">SLS (Pó)</option><option value="Outro">Outro</option></select></div>
            <div class="form-group"><label>Área de Impressão</label><input type="text" name="printer_bed" placeholder="ex: 256×256×256mm"></div>
            <div class="form-group full"><label>Notas / Modificações</label><textarea name="printer_notes" placeholder="Upgrades, modificações, observações..."></textarea></div>
          </div>
          <div style="display:flex;gap:10px;">
            <button type="submit" class="btn btn-primary">＋ ADICIONAR</button>
            <button type="button" class="btn btn-secondary" onclick="toggleAdd('addPrinter')">Cancelar</button>
          </div>
        </form>
      </div>
    </div>
  </section>
</div>

<!-- ════ TAB: SLICERS ════ -->
<div class="tab-panel" id="tab-slicers">
  <section class="section">
    <div class="section-header">
      <div class="section-number">03</div>
      <div class="section-title"><h2>Slicers que Utilizo</h2><p>O software que usas para preparar os teus ficheiros de impressão</p></div>
    </div>
    <div class="card">
      <div class="card-title"><span class="icon">⚙️</span> Slicers Registados</div>
      <?php if (empty($slicers)): ?>
        <div class="empty-state"><div class="empty-icon">⚙️</div><p>Ainda não adicionaste nenhum slicer.</p></div>
      <?php else: ?>
        <div class="item-list">
          <?php foreach ($slicers as $s): $sid = (int)$s['id']; ?>
          <div class="item-card" id="slicer-<?php echo $sid; ?>">
            <div class="item-view">
              <div class="item-main">
                <div class="item-title"><?php echo sanitize($s['name']); ?></div>
                <?php if (!empty($s['version'])): ?><div class="item-sub">v<?php echo sanitize($s['version']); ?></div><?php endif; ?>
                <?php if (!empty($s['notes'])): ?><div class="item-sub">💬 <?php echo sanitize($s['notes']); ?></div><?php endif; ?>
              </div>
              <div class="item-actions">
                <button class="btn btn-edit" onclick="toggleEdit('es<?php echo $sid; ?>')">✏️ Editar</button>
                <form method="POST" onsubmit="return confirm('Remover este slicer?')" style="display:inline">
                  <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                  <input type="hidden" name="action" value="remove_slicer">
                  <input type="hidden" name="slicer_id" value="<?php echo $sid; ?>">
                  <button type="submit" class="btn btn-danger">✕</button>
                </form>
              </div>
            </div>
            <div class="item-edit-panel" id="es<?php echo $sid; ?>">
              <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                <input type="hidden" name="action" value="edit_slicer">
                <input type="hidden" name="slicer_id" value="<?php echo $sid; ?>">
                <div class="edit-grid">
                  <div class="form-group"><label>Nome do Slicer *</label><input type="text" name="slicer_name" value="<?php echo sanitize($s['name']); ?>" list="sl-e-<?php echo $sid; ?>" required><datalist id="sl-e-<?php echo $sid; ?>"><option value="Bambu Studio"><option value="PrusaSlicer"><option value="OrcaSlicer"><option value="Ultimaker Cura"><option value="Simplify3D"><option value="Chitubox"><option value="Lychee Slicer"></datalist></div>
                  <div class="form-group"><label>Versão</label><input type="text" name="slicer_version" value="<?php echo sanitize($s['version']??''); ?>" placeholder="ex: 2.1.0"></div>
                  <div class="form-group full"><label>Notas</label><textarea name="slicer_notes"><?php echo sanitize($s['notes']??''); ?></textarea></div>
                </div>
                <div style="display:flex;gap:10px;">
                  <button type="submit" class="btn btn-save">💾 GUARDAR</button>
                  <button type="button" class="btn btn-secondary" onclick="toggleEdit('es<?php echo $sid; ?>')">Cancelar</button>
                </div>
              </form>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <button class="add-toggle" onclick="toggleAdd('addSlicer')">＋ ADICIONAR SLICER</button>
      <div class="add-panel" id="addSlicer">
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
          <input type="hidden" name="action" value="add_slicer">
          <div class="edit-grid">
            <div class="form-group"><label>Nome do Slicer *</label><input type="text" name="slicer_name" list="sl-add" placeholder="ex: Bambu Studio, PrusaSlicer..." required><datalist id="sl-add"><option value="Bambu Studio"><option value="PrusaSlicer"><option value="OrcaSlicer"><option value="Ultimaker Cura"><option value="Simplify3D"><option value="IdeaMaker"><option value="Chitubox"><option value="Lychee Slicer"><option value="FlashPrint"></datalist></div>
            <div class="form-group"><label>Versão</label><input type="text" name="slicer_version" placeholder="ex: 2.1.0"></div>
            <div class="form-group full"><label>Notas</label><textarea name="slicer_notes" placeholder="Para que tipo de impressões usas, o que gostas..."></textarea></div>
          </div>
          <div style="display:flex;gap:10px;">
            <button type="submit" class="btn btn-primary">＋ ADICIONAR</button>
            <button type="button" class="btn btn-secondary" onclick="toggleAdd('addSlicer')">Cancelar</button>
          </div>
        </form>
      </div>
    </div>
  </section>
</div>

<!-- ════ TAB: MATERIAIS ════ -->
<div class="tab-panel" id="tab-materials">
  <section class="section">
    <div class="section-header">
      <div class="section-number">04</div>
      <div class="section-title"><h2>Materiais Favoritos</h2><p>Os filamentos e resinas que preferes usar</p></div>
    </div>
    <div class="card">
      <div class="card-title"><span class="icon">🧵</span> Materiais Registados</div>
      <?php if (empty($materials)): ?>
        <div class="empty-state"><div class="empty-icon">🧵</div><p>Ainda não adicionaste nenhum material.</p></div>
      <?php else: ?>
        <div class="item-list">
          <?php foreach ($materials as $m): $mid = (int)$m['id']; ?>
          <div class="item-card" id="material-<?php echo $mid; ?>">
            <div class="item-view">
              <div class="item-main">
                <div class="item-title"><?php echo sanitize($m['material']); ?></div>
                <?php if (!empty($m['brand'])): ?><div class="item-sub">🏷️ <?php echo sanitize($m['brand']); ?></div><?php endif; ?>
                <?php if (!empty($m['notes'])): ?><div class="item-sub">💬 <?php echo sanitize($m['notes']); ?></div><?php endif; ?>
              </div>
              <div class="item-actions">
                <button class="btn btn-edit" onclick="toggleEdit('em<?php echo $mid; ?>')">✏️ Editar</button>
                <form method="POST" onsubmit="return confirm('Remover este material?')" style="display:inline">
                  <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                  <input type="hidden" name="action" value="remove_material">
                  <input type="hidden" name="material_id" value="<?php echo $mid; ?>">
                  <button type="submit" class="btn btn-danger">✕</button>
                </form>
              </div>
            </div>
            <div class="item-edit-panel" id="em<?php echo $mid; ?>">
              <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                <input type="hidden" name="action" value="edit_material">
                <input type="hidden" name="material_id" value="<?php echo $mid; ?>">
                <div class="edit-grid">
                  <div class="form-group"><label>Material *</label><input type="text" name="material_name" value="<?php echo sanitize($m['material']); ?>" list="mat-e-<?php echo $mid; ?>" required><datalist id="mat-e-<?php echo $mid; ?>"><option value="PLA"><option value="PLA+"><option value="PETG"><option value="ABS"><option value="ASA"><option value="TPU"><option value="Nylon"><option value="PA-CF"><option value="PEEK"><option value="Resina Standard"><option value="Resina ABS-like"></datalist></div>
                  <div class="form-group"><label>Marca</label><input type="text" name="material_brand" value="<?php echo sanitize($m['brand']??''); ?>" placeholder="ex: eSUN, Prusament..."></div>
                  <div class="form-group full"><label>Notas</label><textarea name="material_notes"><?php echo sanitize($m['notes']??''); ?></textarea></div>
                </div>
                <div style="display:flex;gap:10px;">
                  <button type="submit" class="btn btn-save">💾 GUARDAR</button>
                  <button type="button" class="btn btn-secondary" onclick="toggleEdit('em<?php echo $mid; ?>')">Cancelar</button>
                </div>
              </form>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <button class="add-toggle" onclick="toggleAdd('addMaterial')">＋ ADICIONAR MATERIAL</button>
      <div class="add-panel" id="addMaterial">
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
          <input type="hidden" name="action" value="add_material">
          <div class="edit-grid">
            <div class="form-group"><label>Tipo de Material *</label><input type="text" name="material_name" list="mat-add" placeholder="ex: PLA, PETG, Resina..." required><datalist id="mat-add"><option value="PLA"><option value="PLA+"><option value="PLA-CF"><option value="PETG"><option value="PETG-CF"><option value="ABS"><option value="ASA"><option value="TPU"><option value="Nylon"><option value="PA-CF"><option value="PEEK"><option value="PC (Policarbonato)"><option value="Resina Standard"><option value="Resina ABS-like"><option value="Resina Flexível"></datalist></div>
            <div class="form-group"><label>Marca</label><input type="text" name="material_brand" placeholder="ex: eSUN, Prusament, Bambu..."></div>
            <div class="form-group full"><label>Notas</label><textarea name="material_notes" placeholder="Temperatura, velocidade, observações..."></textarea></div>
          </div>
          <div style="display:flex;gap:10px;">
            <button type="submit" class="btn btn-primary">＋ ADICIONAR</button>
            <button type="button" class="btn btn-secondary" onclick="toggleAdd('addMaterial')">Cancelar</button>
          </div>
        </form>
      </div>
    </div>
  </section>
</div>

<!-- ════ TAB: SEGURANÇA ════ -->
<div class="tab-panel" id="tab-security">
  <section class="section">
    <div class="section-header">
      <div class="section-number">05</div>
      <div class="section-title"><h2>Segurança & Conta</h2><p>Altera a tua palavra-passe e consulta informações da conta</p></div>
    </div>
    <div class="card">
      <div class="card-title"><span class="icon">🔒</span> Alterar Palavra-passe</div>
      <form method="POST" style="max-width:500px;">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
        <input type="hidden" name="action" value="change_password">
        <div class="form-group" style="margin-bottom:16px;"><label>Palavra-passe Atual</label><input type="password" name="current_password" placeholder="A tua palavra-passe atual" required></div>
        <div class="form-group" style="margin-bottom:4px;"><label>Nova Palavra-passe</label><input type="password" name="new_password" id="pwNew" placeholder="Mínimo 8 caracteres" required><div class="pw-strength"><div class="pw-bar" id="pwBar"></div></div></div>
        <div class="form-group" style="margin-bottom:20px;"><label>Confirmar Nova Palavra-passe</label><input type="password" name="confirm_password" placeholder="Repete a nova palavra-passe" required></div>
        <button type="submit" class="btn btn-primary">🔒 ALTERAR PALAVRA-PASSE</button>
      </form>
      <hr class="divider">
      <div class="card-title" style="margin-bottom:18px;"><span class="icon">ℹ️</span> Informações da Conta</div>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:14px;max-width:640px;">
        <?php
        $infos = ['Email'=>sanitize($user['email']),'Username'=>'@'.sanitize($user['username']),'Função'=>ucfirst(sanitize($user['role'])),'Registado em'=>date('d/m/Y',strtotime($user['created_at'])),'Último Login'=>($user['last_login']?date('d/m/Y H:i',strtotime($user['last_login'])):'N/A'),'Estado'=>($user['is_active']?'✓ Ativo':'✗ Inativo')];
        foreach($infos as $label=>$val): ?>
        <div style="background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:14px 16px;transition:border-color 0.2s;" onmouseover="this.style.borderColor='rgba(0,229,255,0.3)'" onmouseout="this.style.borderColor='var(--border)'">
          <div style="font-family:'Space Mono',monospace;font-size:9px;color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;"><?php echo $label; ?></div>
          <div style="font-size:14px;color:var(--text);"><?php echo $val; ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
</div>

<!-- TAB: FÓRUM -->
<div class="tab-panel" id="tab-forum">
  <section class="section">
    <div class="section-header">
      <div class="section-number">06</div>
      <div class="section-title">
        <h2>Atividade no Fórum</h2>
        <p>Posts e respostas publicados no fórum global</p>
      </div>
    </div>
 
    <!-- Stats -->
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:12px;margin-bottom:24px">
      <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:18px 20px">
        <div style="font-family:'Space Mono',monospace;font-size:9px;color:var(--muted);letter-spacing:2px;text-transform:uppercase;margin-bottom:8px">POSTS</div>
        <div style="font-family:'Syne',sans-serif;font-size:28px;font-weight:800;color:var(--accent)"><?php echo $forumStats['posts']; ?></div>
      </div>
      <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:18px 20px">
        <div style="font-family:'Space Mono',monospace;font-size:9px;color:var(--muted);letter-spacing:2px;text-transform:uppercase;margin-bottom:8px">RESPOSTAS</div>
        <div style="font-family:'Syne',sans-serif;font-size:28px;font-weight:800;color:var(--accent2)"><?php echo $forumStats['replies']; ?></div>
      </div>
      <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:18px 20px">
        <div style="font-family:'Space Mono',monospace;font-size:9px;color:var(--muted);letter-spacing:2px;text-transform:uppercase;margin-bottom:8px">KARMA</div>
        <div style="font-family:'Syne',sans-serif;font-size:28px;font-weight:800;color:<?php echo $forumStats['vote_score']>=0?'var(--accent4)':'#ff7777'; ?>"><?php echo ($forumStats['vote_score']>=0?'+':'').$forumStats['vote_score']; ?></div>
      </div>
      <div style="background:var(--surface);border:1px solid rgba(124,58,237,0.2);border-radius:12px;padding:18px 20px;display:flex;align-items:center;justify-content:center">
        <a href="forum/" style="font-family:'Space Mono',monospace;font-size:10px;color:#a78bfa;text-decoration:none;display:flex;flex-direction:column;align-items:center;gap:6px;transition:opacity 0.2s" onmouseover="this.style.opacity='0.7'" onmouseout="this.style.opacity='1'">
          <span style="font-size:24px">🌐</span>
          IR AO FÓRUM →
        </a>
      </div>
    </div>
 
    <!-- Posts -->
    <?php if (!empty($forumPosts)): ?>
    <div class="card" style="margin-bottom:20px">
      <div class="card-title"><span class="icon">📝</span> Os Meus Posts</div>
      <div class="item-list">
        <?php foreach ($forumPosts as $fp):
          $score = (int)$fp['vote_score'];
          $scoreCls = $score > 0 ? 'color:var(--accent4)' : ($score < 0 ? 'color:#ff7777' : 'color:var(--muted)');
          $diff = time() - strtotime($fp['created_at']);
          $timeStr = $diff<3600 ? floor($diff/60).'min atrás' : ($diff<86400 ? floor($diff/3600).'h atrás' : date('d/m/Y', strtotime($fp['created_at'])));
        ?>
        <div class="item-card">
          <div class="item-view">
            <div class="item-main" style="flex:1;min-width:0">
              <!-- Comunidade -->
              <a href="forum/comunidade.php?slug=<?php echo urlencode($fp['community_slug']); ?>" style="display:inline-flex;align-items:center;gap:5px;font-family:'Space Mono',monospace;font-size:9px;color:var(--muted);text-decoration:none;letter-spacing:1px;text-transform:uppercase;margin-bottom:6px;transition:color 0.2s" onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='var(--muted)'">
                <?php echo $fp['community_icon']; ?> <?php echo sanitize($fp['community_name']); ?>
              </a>
              <!-- Título -->
              <a href="forum/topico.php?id=<?php echo $fp['id']; ?>" style="display:block;font-family:'Syne',sans-serif;font-size:15px;font-weight:700;color:#fff;text-decoration:none;margin-bottom:6px;line-height:1.3;transition:color 0.2s" onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='#fff'">
                <?php if ($fp['is_pinned']): ?><span style="font-size:11px">📌</span> <?php endif; ?>
                <?php if ($fp['is_locked']): ?><span style="font-size:11px">🔒</span> <?php endif; ?>
                <?php echo sanitize($fp['title']); ?>
              </a>
              <?php if (!empty($fp['content'])): ?>
              <div style="font-size:12px;color:var(--muted);line-height:1.5;margin-bottom:8px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden"><?php echo sanitize(mb_substr($fp['content'],0,150)); ?></div>
              <?php endif; ?>
              <!-- Meta -->
              <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">
                <span style="font-family:'Space Mono',monospace;font-size:10px;<?php echo $scoreCls; ?>">▲ <?php echo $score >= 0 ? '+'.$score : $score; ?></span>
                <span style="font-family:'Space Mono',monospace;font-size:10px;color:var(--muted)">💬 <?php echo (int)$fp['reply_count']; ?></span>
                <span style="font-family:'Space Mono',monospace;font-size:10px;color:var(--muted);margin-left:auto"><?php echo $timeStr; ?></span>
              </div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
 
    <!-- Respostas -->
    <?php if (!empty($forumReplies)): ?>
    <div class="card">
      <div class="card-title"><span class="icon">💬</span> As Minhas Respostas</div>
      <div class="item-list">
        <?php foreach ($forumReplies as $fr):
          $rScore = (int)$fr['vote_score'];
          $rScoreCls = $rScore > 0 ? 'color:var(--accent4)' : ($rScore < 0 ? 'color:#ff7777' : 'color:var(--muted)');
          $diff = time() - strtotime($fr['created_at']);
          $timeStr = $diff<3600 ? floor($diff/60).'min atrás' : ($diff<86400 ? floor($diff/3600).'h atrás' : date('d/m/Y', strtotime($fr['created_at'])));
        ?>
        <div class="item-card">
          <div class="item-view">
            <div class="item-main" style="flex:1;min-width:0">
              <!-- Post pai -->
              <a href="forum/topico.php?id=<?php echo $fr['post_id']; ?>" style="display:flex;align-items:center;gap:6px;font-size:11px;color:var(--muted);text-decoration:none;margin-bottom:6px;transition:color 0.2s" onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='var(--muted)'">
                <span style="font-family:'Space Mono',monospace;font-size:9px;letter-spacing:1px;text-transform:uppercase">↩ RESPOSTA EM</span>
                <span style="font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:300px"><?php echo sanitize($fr['post_title']); ?></span>
              </a>
              <!-- Conteúdo -->
              <div style="font-size:13px;color:var(--text);line-height:1.6;margin-bottom:8px"><?php echo sanitize(mb_substr($fr['content'],0,200)); ?><?php echo mb_strlen($fr['content'])>200?'…':''; ?></div>
              <!-- Meta -->
              <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">
                <span style="font-family:'Space Mono',monospace;font-size:10px;<?php echo $rScoreCls; ?>">▲ <?php echo $rScore >= 0 ? '+'.$rScore : $rScore; ?></span>
                <span style="font-family:'Space Mono',monospace;font-size:9px;color:var(--muted);text-transform:uppercase"><?php echo sanitize($fr['community_name']); ?></span>
                <span style="font-family:'Space Mono',monospace;font-size:10px;color:var(--muted);margin-left:auto"><?php echo $timeStr; ?></span>
              </div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
 
    <?php if (empty($forumPosts) && empty($forumReplies)): ?>
    <div class="card">
      <div class="empty-state">
        <div class="empty-icon">🌐</div>
        <p>Ainda não participaste no fórum.</p>
        <p style="margin-top:8px;font-size:12px"><a href="forum/" style="color:#a78bfa">→ Explorar o fórum global</a></p>
      </div>
    </div>
    <?php endif; ?>
 
  </section>
</div>

<!-- TAB: COMENTÁRIOS -->
<div class="tab-panel" id="tab-comments">
  <section class="section">
    <div class="section-header">
      <div class="section-number">06</div>
      <div class="section-title">
        <h2>Os Meus Comentários</h2>
        <p>Todos os teus comentários aprovados na comunidade</p>
      </div>
    </div>
    <div class="card">
      <div class="card-title">
        <span class="icon">💬</span>
        Comentários Publicados
        <span style="margin-left:auto;font-family:'Space Mono',monospace;font-size:11px;color:var(--muted)"><?php echo count($myComments); ?> total</span>
      </div>

      <?php if (empty($myComments)): ?>
        <div class="empty-state">
          <div class="empty-icon">💬</div>
          <p>Ainda não tens comentários aprovados.</p>
          <p style="margin-top:8px;font-size:12px"><a href="index.php#comentarios" style="color:var(--accent)">→ Vai à comunidade e participa!</a></p>
        </div>
      <?php else: ?>
        <!-- Resumo -->
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:12px;margin-bottom:24px">
          <?php
          $byCategory = array_count_values(array_column($myComments, 'category'));
          $totalLikes = array_sum(array_column($myComments, 'like_count'));
          $withReplies = count(array_filter($myComments, fn($c) => $c['parent_id'] === null));
          ?>
          <div style="background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:14px 16px">
            <div style="font-family:'Space Mono',monospace;font-size:9px;color:var(--muted);margin-bottom:6px">TOTAL</div>
            <div style="font-family:'Syne',sans-serif;font-size:24px;font-weight:800;color:var(--accent)"><?php echo count($myComments); ?></div>
          </div>
          <div style="background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:14px 16px">
            <div style="font-family:'Space Mono',monospace;font-size:9px;color:var(--muted);margin-bottom:6px">LIKES RECEBIDOS</div>
            <div style="font-family:'Syne',sans-serif;font-size:24px;font-weight:800;color:var(--accent2)"><?php echo $totalLikes; ?></div>
          </div>
          <div style="background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:14px 16px">
            <div style="font-family:'Space Mono',monospace;font-size:9px;color:var(--muted);margin-bottom:6px">PRINCIPAIS</div>
            <div style="font-family:'Syne',sans-serif;font-size:24px;font-weight:800;color:var(--accent4)"><?php echo $withReplies; ?></div>
          </div>
        </div>

        <!-- Lista -->
        <div class="item-list">
          <?php
          $catLabels = ['duvida'=>'DÚVIDA','problema'=>'PROBLEMA','dica'=>'DICA','geral'=>'GERAL'];
          $catColors = ['duvida'=>'rgba(124,58,237,0.1)','problema'=>'rgba(255,107,53,0.1)','dica'=>'rgba(0,255,136,0.07)','geral'=>'rgba(0,229,255,0.07)'];
          $catText   = ['duvida'=>'#a78bfa','problema'=>'var(--accent2)','dica'=>'var(--accent4)','geral'=>'var(--accent)'];
          foreach ($myComments as $c):
            $cat = $c['category'] ?? 'geral';
          ?>
          <div class="item-card">
            <div class="item-view">
              <div class="item-main" style="flex:1;min-width:0">
                <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px;flex-wrap:wrap">
                  <span style="background:<?php echo $catColors[$cat]??'rgba(0,229,255,0.07)'; ?>;color:<?php echo $catText[$cat]??'var(--accent)'; ?>;padding:3px 10px;border-radius:4px;font-family:'Space Mono',monospace;font-size:10px"><?php echo $catLabels[$cat]??strtoupper($cat); ?></span>
                  <?php if ($c['parent_id']): ?>
                    <span style="background:var(--surface3);color:var(--muted);padding:3px 10px;border-radius:4px;font-family:'Space Mono',monospace;font-size:10px">↩ RESPOSTA</span>
                  <?php endif; ?>
                  <span style="font-family:'Space Mono',monospace;font-size:10px;color:var(--muted);margin-left:auto"><?php echo date('d/m/Y H:i', strtotime($c['created_at'])); ?></span>
                </div>
                <div style="font-size:14px;color:var(--text);line-height:1.7;margin-bottom:10px"><?php echo nl2br(sanitize(mb_substr($c['content'], 0, 300))); ?><?php echo mb_strlen($c['content']) > 300 ? '…' : ''; ?></div>
                <div style="display:flex;align-items:center;gap:14px">
                  <span style="font-size:12px;color:var(--muted)">❤️ <?php echo (int)($c['like_count'] ?? 0); ?> likes</span>
                  <a href="index.php#comentarios" style="font-family:'Space Mono',monospace;font-size:10px;color:var(--accent);text-decoration:none">→ Ver na comunidade</a>
                </div>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>
</div>

<!-- FOOTER -->
<footer style="display: flex; justify-content: space-between; align-items: center; padding: 40px 60px 120px;">
  <div style="text-align: left;">
    <p style="font-size: 11px; color: var(--muted); margin: 0; font-family: 'Space Mono', monospace;">© 2026 <strong>Manual de Impressão 3D</strong></p>
  </div>
  <div style="text-align: right;">
    <p style="margin: 0; font-size: 12px; color: var(--muted);">
      <a href="terms.php" style="color: var(--muted); text-decoration: none;">Termos</a> |
      <a href="privacy.php" style="color: var(--muted); text-decoration: none;">Privacidade</a>
    </p>
    <p style="margin-top:4px; opacity:0.5; font-size: 10px;">Este documento pode ser livremente partilhado.</p>
  </div>
</footer>

</main>
<button class="back-to-top" id="backToTop" onclick="window.scrollTo({top:0,behavior:'smooth'})">↑</button>

<script>
function setProfileMenuOpen(open) {
  const sidebar = document.getElementById('sidebar');
  const backdrop = document.getElementById('sidebarBackdrop');
  const toggle = document.getElementById('menuToggle');
  if (sidebar) sidebar.classList.toggle('open', open);
  if (backdrop) backdrop.classList.toggle('open', open);
  if (toggle) {
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    toggle.setAttribute('aria-label', open ? 'Fechar menu' : 'Abrir menu');
    toggle.textContent = open ? '×' : '☰';
  }
  document.body.classList.toggle('sidebar-open', open);
}

function toggleProfileMenu() {
  const sidebar = document.getElementById('sidebar');
  setProfileMenuOpen(!(sidebar && sidebar.classList.contains('open')));
}

function closeProfileMenu() {
  setProfileMenuOpen(false);
}

window.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') closeProfileMenu();
});

window.addEventListener('resize', () => {
  if (window.innerWidth > 1024) closeProfileMenu();
});

// TABS
function switchTab(tab, btn) {
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('tab-' + tab).classList.add('active');
  const b = btn || document.getElementById('tb-' + tab);
  if (b) b.classList.add('active');
}

// EDIT INLINE
function toggleEdit(panelId) {
  const panel = document.getElementById(panelId);
  const isOpen = panel.classList.contains('open');
  // Fechar todos os edit panels abertos
  document.querySelectorAll('.item-edit-panel.open').forEach(p => p.classList.remove('open'));
  if (!isOpen) panel.classList.add('open');
}

// ADD FORM
function toggleAdd(panelId) {
  const panel = document.getElementById(panelId);
  panel.classList.toggle('open');
}

// AVATAR
document.getElementById('avatarFileInput').addEventListener('change', function() {
  if (this.files.length > 0) document.getElementById('avatarForm').submit();
});

// PASSWORD STRENGTH
document.getElementById('pwNew').addEventListener('input', function() {
  const pw = this.value, bar = document.getElementById('pwBar');
  let s = 0;
  if (pw.length >= 8) s++;
  if (/[a-z]/.test(pw) && /[A-Z]/.test(pw)) s++;
  if (/[0-9]/.test(pw)) s++;
  if (/[^a-zA-Z0-9]/.test(pw)) s++;
  bar.className = 'pw-bar' + (s<=1?' weak':s===2?' medium':' strong');
});

// PROGRESS + BACK TO TOP
window.addEventListener('scroll', () => {
  const p = (window.scrollY / (document.documentElement.scrollHeight - window.innerHeight)) * 100;
  document.getElementById('progressFill').style.width = p + '%';
  document.getElementById('backToTop').classList.toggle('visible', window.scrollY > 400);
});

function updateBadgeCount(input) {
    const checked = document.querySelectorAll('input[name="badges[]"]:checked');
    const msg = document.getElementById('badgeCountMsg');
    const btn = document.getElementById('btnUpdateBadges');

    msg.textContent = checked.length + ' / 3 selecionados';

    if (checked.length > 3) {
        msg.style.color = '#ff4444';
        btn.disabled = true;
        btn.style.opacity = '0.5';
    } else {
        msg.style.color = 'var(--muted)';
        btn.disabled = false;
        btn.style.opacity = '1';
    }
}

async function claimProfileMission(key) {
    const btn = event ? event.target : null;
    if (btn && btn.tagName === 'BUTTON') {
        btn.disabled = true;
        btn.textContent = '...';
    }
    try {
        const res = await fetch('api/missions.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ action: 'claim', mission_key: key })
        });
        const data = await res.json();
        if (data.success) {
            confetti({
                particleCount: 150,
                spread: 70,
                origin: { y: 0.6 },
                colors: ['#00ff88', '#ff6b35', '#7c3aed', '#00e5ff']
            });
            new Audio('assets/audio/success.mp3').play();
            setTimeout(() => location.reload(), 1200);
        } else {
            if (btn) btn.disabled = false;
            alert(data.error || 'Erro ao reclamar.');
        }
    } catch(e) {
        console.error(e);
        if (btn) btn.disabled = false;
    }
}

// MANTER TAB APÓS SUBMIT
document.addEventListener('DOMContentLoaded', () => {
  const tab = '<?php echo $activeTab; ?>';
  if (tab !== 'info') switchTab(tab, null);
});
</script>
</body>
</html>
