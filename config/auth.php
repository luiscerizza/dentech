<?php

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', 1);
    session_start();
}

define('NORMAL_USER', 'user');
define('USER_MASTER', 'user_master');
define('NORMAL_HASH', '$2y$10$YOXNdOPiUVqcY6WshENXi.B3GnNB2GUKuJu5E8i8/4KxOoJheAV0e'); 
define('MASTER_HASH', '$2y$10$erBUtqhkGfHr5HfgMe1GQ.LLX1p31csuxRtUGG7Zi7FmZ4bQ0uZ0O');

function estaLogado(): bool {
    return !empty($_SESSION['user_logado']);
}

function exigirLogin(): void {
    if (!estaLogado()) {
        header('Location: login.php');
        exit;
    }
}

function fazerLogout(): void {
    unset($_SESSION['user_logado']);
    unset($_SESSION['id_usuario']);
}