<?php
// config/auth.php - Configuração do LOGIN NORMAL (sistema principal)

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', 1);
    session_start();
}

// 🔑 CREDENCIAIS DO LOGIN NORMAL (ALTERE PARA SUAS)
define('NORMAL_USER', getenv('NORMAL_USER') ?: '');
define('USER_MASTER', getenv('USER_MASTER') ?: '');
define('NORMAL_HASH', getenv('NORMAL_HASH') ?: '');
define('MASTER_HASH', getenv('MASTER_HASH') ?: '');

// ✅ Verifica se está logado no sistema normal
function estaLogado(): bool {
    return !empty($_SESSION['user_logado']);
}

// 🔒 Bloqueia e redireciona para login normal se não estiver autenticado
function exigirLogin(): void {
    if (!estaLogado()) {
        header('Location: login.php');
        exit;
    }
}

// 🚪 Faz logout do sistema normal
function fazerLogout(): void {
    unset($_SESSION['user_logado']);
    unset($_SESSION['id_usuario']);
    // Mantém $_SESSION['restricted_access'] intacto se existir
}