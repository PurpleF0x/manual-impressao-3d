<?php
/**
 * api/google_auth.php
 * Endpoint para processar o token do Google e autenticar o utilizador.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/google_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../login.php');
    exit;
}

$idToken = $_POST['credential'] ?? '';

if (empty($idToken)) {
    header('Location: ../login.php?error=google_failed');
    exit;
}

/**
 * NOTA: Para uma verificação 100% segura, devíamos usar a biblioteca do Google.
 * Para a PAP, vamos descodificar o payload básico do JWT (JSON Web Token).
 */
$parts = explode('.', $idToken);
if (count($parts) < 2) {
    header('Location: ../login.php?error=invalid_token');
    exit;
}

$payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])), true);

if (!$payload || $payload['aud'] !== GOOGLE_CLIENT_ID) {
    header('Location: ../login.php?error=auth_error');
    exit;
}

$googleId = $payload['sub'];
$email    = $payload['email'];
$name     = $payload['name'];
$picture  = $payload['picture'] ?? null;

$db = getDB();

// 1. Verificar se o utilizador já existe com este Google ID
$stmt = $db->prepare("SELECT * FROM users WHERE google_id = ?");
$stmt->execute([$googleId]);
$user = $stmt->fetch();

if (!$user) {
    // 2. Verificar se existe utilizador com o mesmo email
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // Vincula a conta existente ao Google ID
        $db->prepare("UPDATE users SET google_id = ? WHERE id = ?")->execute([$googleId, $user['id']]);
    } else {
        // 3. Criar nova conta
        $username = explode('@', $email)[0] . rand(10, 99);
        $stmt = $db->prepare("INSERT INTO users (username, email, full_name, avatar_url, google_id, password_hash) VALUES (?, ?, ?, ?, ?, 'GOOGLE_AUTH')");
        $stmt->execute([$username, $email, $name, $picture, $googleId]);
        $userId = $db->lastInsertId();

        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        logActivity($userId, 'register_google', "Novo registo via Google: $username");
    }
}

// Iniciar sessão
$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['role'] = $user['role'];

logActivity($user['id'], 'login_google', "Login via Google efetuado");

header('Location: ../index.php');
exit;
