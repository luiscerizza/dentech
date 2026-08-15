<?php
require_once 'config/auth.php';
exigirLogin();

require_once 'conexao/conexao.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido');
    }

    if (
    empty($_POST['csrf_token']) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    throw new Exception('Token de segurança inválido.');
}

    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $estoque_minimo = filter_input(INPUT_POST, 'estoque_minimo', FILTER_VALIDATE_FLOAT);

    if (!$id || $estoque_minimo === false || $estoque_minimo < 0) {
        throw new Exception('Dados inválidos');
    }

    $sql = "UPDATE estoque SET estoque_minimo = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$estoque_minimo, $id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Estoque mínimo atualizado']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Nenhuma alteração foi feita']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}