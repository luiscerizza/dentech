<?php
require_once 'conexao/conexao.php';

header('Content-Type: application/json');

try {
    $id = (int)($_POST['id'] ?? 0);
    $tipo = $_POST['tipo'] ?? '';
    $quantidade = floatval($_POST['quantidade'] ?? 0);

    if (!$id || ($tipo !== 'entrada' && $tipo !== 'saida') || $quantidade <= 0) {
        throw new Exception("Dados inválidos.");
    }

    // Buscar material atual
    $stmt = $pdo->prepare("SELECT quantidade FROM estoque WHERE id = ?");
    $stmt->execute([$id]);
    $atual = $stmt->fetchColumn();

    if ($atual === false) {
        throw new Exception("Material não encontrado.");
    }

    $nova_quantidade = $atual + ($tipo === 'entrada' ? $quantidade : -$quantidade);

    if ($nova_quantidade < 0) {
        throw new Exception("Não há estoque suficiente para essa saída.");
    }

    $stmt = $pdo->prepare("UPDATE estoque SET quantidade = ? WHERE id = ?");
    $stmt->execute([$nova_quantidade, $id]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}