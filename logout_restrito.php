<?php
// logout.php - Faz logout APENAS da área restrita
require_once 'config/auth.config_area_restrita.php';

logoutRestrito(); // Remove apenas a flag de acesso restrito

// Redireciona para o index (sistema principal continua disponível)
header('Location: index.php');
exit;
