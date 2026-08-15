<?php
require_once 'config/auth.php';
exigirLogin();
require_once 'conexao/conexao.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("UPDATE orcamentos SET status = 'recusado' WHERE id = ?");
$stmt->execute([$id]);

header("Location: visualizar_orcamento.php?id=" . $id);
exit;
?>