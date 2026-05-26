<?php
// logout.php - Faz logout APENAS do sistema normal
require_once 'config/auth.php';

fazerLogout(); // Remove apenas as variáveis do login normal

// Redireciona para a tela de login normal
header('Location: login.php');
exit;