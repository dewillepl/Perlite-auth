<?php
ini_set('session.save_path', '/tmp');
ini_set('session.name', 'PERLITE_AUTH');
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', $isHttps ? '1' : '0');
ini_set('session.cookie_samesite', 'Lax');
session_start();

if (isset($_SESSION['auth']) && $_SESSION['auth'] === true) {
    http_response_code(200);
    exit;
} else {
    http_response_code(401);
    exit;
}
