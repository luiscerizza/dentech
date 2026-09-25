<?php
// config/auth.config_area_restrita.php
// 🔐 Configuração EXCLUSIVA da Área Restrita (não interfere no login normal)

// Inicia sessão de forma segura (adaptada para localhost)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', 1);
    session_start();
}

// ⏱️ Timeout de 30 minutos para área restrita
$timeout = 1800;
if (isset($_SESSION['restricted_login_time']) && (time() - $_SESSION['restricted_login_time'] > $timeout)) {
    logoutRestrito();
    header('Location: login_restrito.php?timeout=1');
    exit;
}
// Atualiza tempo de atividade
$_SESSION['restricted_login_time'] = time();

// 🔑 CREDENCIAIS DA ÁREA RESTRITA (ALTERE O HASH!)
define('RESTRICTED_USER', 'admin');
define('RESTRICTED_HASH', '$2y$10$aq.KVo76eUfvYV7K796aDOnPAbUT0LfHVjmlf78k9cvIFvwjjEXca'); // ← Substitua pelo hash gerado

// ✅ Verifica se já tem acesso à área restrita
function temAcessoRestrito(): bool {
    return !empty($_SESSION['restricted_access']);
}

// 🔒 Bloqueia e redireciona para login restrito se não tiver acesso
function exigeAcessoRestrito(): void {
    if (!temAcessoRestrito()) {
        header('Location: login_restrito.php');
        exit;
    }
}

// 🚪 Faz logout apenas da área restrita (não afeta login normal)
function logoutRestrito(): void {
    unset($_SESSION['restricted_access']);
    // Não destrói a sessão inteira, caso haja login normal ativo
}