<?php
require_once 'config/auth.php';
exigirLogin();
require_once 'conexao/conexao.php';

header('Content-Type: application/json; charset=utf-8');

if (
    empty($_POST['csrf_token']) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Token de segurança inválido.'
    ]);
    exit;
}

try {

    $id = (int)($_POST['id'] ?? 0);

    if ($id <= 0) {
        throw new Exception('Material inválido.');
    }

    // Verifica se o material existe
    $stmt = $pdo->prepare("SELECT id FROM estoque WHERE id = ?");
    $stmt->execute([$id]);

    if (!$stmt->fetch()) {
        throw new Exception('Material não encontrado.');
    }

    // Exclui o material
    $stmt = $pdo->prepare("DELETE FROM estoque WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode([
        'success' => true
    ]);
} catch (Exception $e) {

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
