<?php
// config/auth.php - Configuração do LOGIN NORMAL (sistema principal)

// ============================================================
// CONFIGURAÇÃO DA SESSÃO
// ============================================================

// Tempo máximo da sessão no servidor: 2 horas
ini_set('session.gc_maxlifetime', 7200);

// Tempo de vida do cookie da sessão: 2 horas
session_set_cookie_params([
    'lifetime' => 7200,
    'path' => '/',
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    'httponly' => true,
    'samesite' => 'Lax'
]);

// Proteções da sessão
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', 1);

// Inicia a sessão somente se ainda não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// ============================================================
// TOKEN CSRF
// ============================================================

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


// ============================================================
// CREDENCIAIS DO LOGIN NORMAL
// ============================================================

define('NORMAL_USER', getenv('NORMAL_USER') ?: '');
define('USER_MASTER', getenv('USER_MASTER') ?: '');
define('NORMAL_HASH', getenv('NORMAL_HASH') ?: '');
define('MASTER_HASH', getenv('MASTER_HASH') ?: '');


// ============================================================
// VERIFICA SE ESTÁ LOGADO NO SISTEMA NORMAL
// ============================================================

function estaLogado(): bool
{
    return !empty($_SESSION['user_logado']);
}


// ============================================================
// BLOQUEIA E REDIRECIONA PARA LOGIN NORMAL
// ============================================================

function exigirLogin(): void
{
    if (!estaLogado()) {
        header('Location: login.php');
        exit;
    }
}


// ============================================================
// LOGOUT DO SISTEMA NORMAL
// ============================================================

function fazerLogout(): void
{
    unset($_SESSION['user_logado']);
    unset($_SESSION['id_usuario']);

    // Mantém $_SESSION['restricted_access'] intacto se existir
}


require_once __DIR__ . '/csrf.php';
