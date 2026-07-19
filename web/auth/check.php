<?php
ini_set('session.save_path', '/tmp');
ini_set('session.name', 'PERLITE_AUTH');
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', $isHttps ? '1' : '0');
ini_set('session.cookie_samesite', 'Lax');
session_start();

$USER = getenv('PERLITE_USERNAME') ?: 'admin';
// Fallback hash is bcrypt('admin') so the default docker-compose credentials keep working out of the box.
$PASS_HASH = getenv('PERLITE_PASSWORD_HASH') ?: '$2y$10$IJuy5jLFSy6L3OChjmL3euBMLuCMCx17C3h7bNIeuk4hftuH.LUXK';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';

    if (hash_equals($USER, $user) && password_verify($pass, $PASS_HASH)) {
        $_SESSION["auth"] = true;
        // Regenerate session ID to prevent session fixation
        session_regenerate_id(true);
        header("Location: /");
        exit;
    }
}

header("Location: /auth/login.php?error=1");
exit;
