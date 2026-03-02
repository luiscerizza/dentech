<?php
require_once 'conexao/conexao.php';

$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    $stmt = $pdo->prepare("DELETE FROM agendamentos WHERE id = ?");
    $stmt->execute([$id]);
}

// Voltar para a página anterior ou para agendamentos
$referer = $_SERVER['HTTP_REFERER'] ?? 'agendamentos.php';
header("Location: " . $referer);
exit;