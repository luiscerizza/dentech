<?php
require_once 'conexao/conexao.php';

header('Content-Type: application/json');

try {
    if (!isset($_POST['id']) || empty($_POST['id'])) {
        throw new Exception("ID não informado.");
    }

    $id = (int)$_POST['id'];
    if ($id <= 0) throw new Exception("ID inválido.");

    $stmt = $pdo->prepare("DELETE FROM prontuarios WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}