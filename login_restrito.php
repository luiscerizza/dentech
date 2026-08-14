<?php
// login_restrito.php - Tela de login EXCLUSIVA da Área Restrita
require_once 'config/auth.config_area_restrita.php';

// Se já tem acesso, volta para o dashboard
if (temAcessoRestrito()) {
    header('Location: index.php');
    exit;
}

$erro = '';

// ✅ Verifica timeout PRIMEIRO (antes do POST)
if (isset($_GET['timeout'])) {
    $erro = 'Sessão expirada por inatividade. Faça login novamente.';
}

// Processa login apenas se não houver erro de timeout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($erro)) {
    $usuario = trim($_POST['usuario'] ?? '');
    $senha   = $_POST['senha'] ?? '';

    if ($usuario === RESTRICTED_USER && password_verify($senha, RESTRICTED_HASH)) {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['restricted_access'] = true;
        $_SESSION['restricted_login_time'] = time();

        header('Location: index.php');
        exit;
    }
    // Só define erro de credencial se não houver erro de timeout
    if (empty($erro)) {
        $erro = 'Usuário ou senha restrita incorretos.';
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="img/icon.PNG">
    <link rel="stylesheet" href="css/login_restrito.css">
    <title>🔒 Área Restrita - Dentech</title>
    <style>
        
    </style>
</head>

<body>
    <div class="login-card">
        <span class="lock-icon">🔐</span>
        <h2>Área Restrita</h2>
        <p class="subtitle">Acesso exclusivo ao administrador</p>

        <?php if ($erro): ?>
            <div class="error"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="usuario">Usuário</label>
                <input type="text" id="usuario" name="usuario" required autocomplete="username" placeholder="Digite seu usuário">
            </div>
            <div class="form-group">
                <label for="senha">Senha Restrita</label>
                <input type="password" id="senha" name="senha" required autocomplete="current-password" placeholder="Digite sua senha">
            </div>
            <button type="submit">🔓 Entrar na Área Restrita</button>
        </form>

        <a href="index.php" class="back-link">← Voltar ao Dashboard Principal</a>
    </div>
</body>

</html>