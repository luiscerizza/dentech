<?php
require_once 'config/auth.php';
require_once 'config/csrf.php';
exigirLogin();
require_once 'conexao/conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método não permitido.');
}

validar_csrf();

$id = (int)($_POST['id'] ?? 0);

if ($id > 0) {
    $stmt = $pdo->prepare("DELETE FROM agendamentos WHERE id = ?");
    $stmt->execute([$id]);
}

registrarLog(
    $pdo,
    'Excluiu agendamento',
    'agendamentos',
    $id,
    'Agendamento removido'
);

// Voltar para a página anterior ou para agendamentos
$referer = $_SERVER['HTTP_REFERER'] ?? 'agendamentos.php';
header("Location: " . $referer);
exit;
