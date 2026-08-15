<?php
require_once 'config/auth.php';
require_once 'config/csrf.php';
exigirLogin();
require_once 'conexao/conexao.php';

header('Content-Type: application/json');

try {

    validar_csrf();

    $paciente_id = !empty($_POST['paciente_id']) ? (int)$_POST['paciente_id'] : null;
    $paciente_nome = trim($_POST['paciente_nome'] ?? '');
    $procedimento = trim($_POST['procedimento'] ?? '');
    $data = $_POST['data'] ?? '';
    $horario = $_POST['horario'] ?? '';

    if (!$paciente_id && !$paciente_nome) {
        throw new Exception("Informe um paciente cadastrado ou um nome avulso.");
    }

    if (empty($procedimento) || empty($data) || empty($horario)) {
        throw new Exception("Preencha todos os campos obrigatórios.");
    }

    // Validar data e horário
    $data_obj = DateTime::createFromFormat('Y-m-d', $data);
    if (!$data_obj || $data_obj->format('Y-m-d') !== $data) {
        throw new Exception("Data inválida.");
    }

    $stmt = $pdo->prepare("
        INSERT INTO agendamentos (paciente_id, paciente_nome, procedimento, data, horario)
        VALUES (?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $paciente_id,
        $paciente_nome ?: null,
        $procedimento,
        $data,
        $horario
    ]);

    registrarLog(
    $pdo,
    'Criou agendamento',
    'agendamentos',
    $pdo->lastInsertId(),
    'Novo agendamento criado'
);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
