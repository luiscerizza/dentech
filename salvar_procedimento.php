<?php
require_once 'config/auth.php';
require_once 'config/csrf.php';
exigirLogin();

require_once 'conexao/conexao.php';

header('Content-Type: application/json');

try {
    validar_csrf();

    $prontuario_id = (int)($_POST['prontuario_id'] ?? 0);
    $titulo = trim($_POST['titulo'] ?? '');
    $data_procedimento = $_POST['data_procedimento'] ?? '';
    $descricao = trim($_POST['descricao'] ?? '');
    $medicamentos = trim($_POST['medicamentos'] ?? '');

    if (!$prontuario_id) {
        throw new Exception("Prontuário inválido.");
    }

    if (empty($titulo)) {
        throw new Exception("O nome do procedimento é obrigatório.");
    }

    // Validar data
    $data_obj = DateTime::createFromFormat('Y-m-d', $data_procedimento);
    if (!$data_obj || $data_obj->format('Y-m-d') !== $data_procedimento) {
        throw new Exception("Data do procedimento inválida.");
    }

    // Verificar se o prontuário existe
    $stmt = $pdo->prepare("SELECT id FROM prontuarios WHERE id = ?");
    $stmt->execute([$prontuario_id]);
    if (!$stmt->fetch()) {
        throw new Exception("Paciente não encontrado.");
    }

    $stmt = $pdo->prepare("
    INSERT INTO procedimentos (paciente_id, titulo, descricao, medicamentos, data_procedimento)
    VALUES (?, ?, ?, ?, ?)
");
    $stmt->execute([
        $prontuario_id,
        $titulo,
        $descricao ?: null,
        $medicamentos ?: null,
        $data_procedimento
    ]);

    $id_criado = $pdo->lastInsertId();

    registrarLog(
    $pdo,
    'Criou procedimento',
    'procedimentos',
    $id_criado,
    'Novo procedimento registrado no prontuário'
);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
