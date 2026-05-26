<?php
require_once 'config/auth.php';

if (estaLogado()) {
    header('Location: index.php');
    exit;
}

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $senha   = $_POST['senha'] ?? '';

    if ($usuario === NORMAL_USER && password_verify($senha, NORMAL_HASH)) {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['user_logado'] = true;
        $_SESSION['id_usuario'] = 1;
        $_SESSION['login_time'] = time();

        header('Location: index.php');
        exit;
    } if ($usuario === USER_MASTER && password_verify($senha, MASTER_HASH)) {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['user_logado'] = true;
        $_SESSION['id_usuario'] = 1; 
        $_SESSION['login_time'] = time();

        header('Location: index.php');
        exit;
    }
}

if (isset($_GET['timeout'])) {
    $erro = 'Sessão expirada. Faça login novamente.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="img/icon.PNG">
    <link rel="stylesheet" href="css/login.css">
    <title>Login - Dentech</title>
    <style>
        
    </style>
</head>

<body>
    <div class="login-card">
        <h2>🦷 Dentech</h2>
        <p class="subtitle">Acesso ao sistema de gestão</p>
        <?php if ($erro): ?>
            <div class="error"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label>Usuário</label>
                <input type="text" name="usuario" required autocomplete="username">
            </div>
            <div class="form-group">
                <label>Senha</label>
                <input type="password" name="senha" required autocomplete="current-password">
            </div>
            <button type="submit">Entrar</button>
        </form>
    </div>
</body>

</html>