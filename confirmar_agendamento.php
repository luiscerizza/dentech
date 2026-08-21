<?php

require_once 'config/auth.php';
require_once 'config/csrf.php';

exigirLogin();

require_once 'conexao/conexao.php';

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST' ||
    !isset($_POST['id']) ||
    !is_numeric($_POST['id'])
) {
    die("Agendamento não especificado.");
}

validar_csrf();

$agendamento_id = (int) $_POST['id'];

try {
    $stmt = $pdo->prepare("
        UPDATE agendamentos
        SET status = 'confirmado'
        WHERE id = ?
          AND status = 'agendado'
    ");

    $stmt->execute([$agendamento_id]);

    if ($stmt->rowCount() === 0) {
        throw new Exception(
            "Agendamento não encontrado ou já foi confirmado."
        );
    }

    header("Location: agendamentos.php?msg=agendamento_confirmado");
    exit;
} catch (Exception $e) {
    die("Erro ao confirmar agendamento: " . $e->getMessage());
}
