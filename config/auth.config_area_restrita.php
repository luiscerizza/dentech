<?php

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', 1);
    session_start();
}

$timeout = 1800;
if (isset($_SESSION['restricted_login_time']) && (time() - $_SESSION['restricted_login_time'] > $timeout)) {
    logoutRestrito();
    header('Location: login_restrito.php?timeout=1');
    exit;
}
$_SESSION['restricted_login_time'] = time();

define('RESTRICTED_USER', 'admin');
define('RESTRICTED_HASH', '$2y$10$aq.KVo76eUfvYV7K796aDOnPAbUT0LfHVjmlf78k9cvIFvwjjEXca'); 

function temAcessoRestrito(): bool {
    return !empty($_SESSION['restricted_access']);
}

function exigeAcessoRestrito(): void {
    if (!temAcessoRestrito()) {
        header('Location: login_restrito.php');
        exit;
    }
}

function logoutRestrito(): void {
    unset($_SESSION['restricted_access']);
}