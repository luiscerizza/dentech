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

if ($id <= 0) {
    exit('Orçamento inválido.');
}
$stmt = $pdo->prepare("UPDATE orcamentos SET status = 'recusado' WHERE id = ?");
$stmt->execute([$id]);

registrarLog(
    $pdo,
    'Recusou orçamento',
    'orcamentos',
    $id,
    'Orçamento recusado'
);

header("Location: visualizar_orcamento.php?id=" . $id);
exit;
