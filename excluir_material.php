<?php
require_once 'config/auth.php';
require_once 'config/csrf.php';
exigirLogin();
require_once 'conexao/conexao.php';

header('Content-Type: application/json; charset=utf-8');

validar_csrf();

try {

    $id = (int)($_POST['id'] ?? 0);

    if ($id <= 0) {
        throw new Exception('Material inválido.');
    }

    // Verifica se o material existe
    $stmt = $pdo->prepare("SELECT id FROM estoque WHERE id = ?");
    $stmt->execute([$id]);

    registrarLog(
    $pdo,
    'Excluiu material',
    'estoque',
    $id,
    'Material removido do estoque'
);

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
