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

    /*
    |--------------------------------------------------------------------------
    | CONFIRMAR AGENDAMENTO
    |--------------------------------------------------------------------------
    |
    | Aceitamos tanto:
    |   - status = 'agendado'
    |   - status = NULL
    |
    | O NULL pode existir em agendamentos antigos
    | ou criados antes da coluna status ser adicionada.
    |
    */

    $stmt = $pdo->prepare("
        UPDATE agendamentos
        SET status = 'confirmado'
        WHERE id = ?
          AND (status = 'agendado' OR status IS NULL)
    ");

    $stmt->execute([$agendamento_id]);

    if ($stmt->rowCount() === 0) {

        // Verificar se o agendamento realmente existe
        $stmtVerifica = $pdo->prepare("
            SELECT status
            FROM agendamentos
            WHERE id = ?
        ");

        $stmtVerifica->execute([$agendamento_id]);

        $statusAtual = $stmtVerifica->fetchColumn();

        if ($statusAtual === false) {
            throw new Exception("Agendamento não encontrado.");
        }

        if ($statusAtual === 'confirmado') {
            throw new Exception("Este agendamento já está confirmado.");
        }

        throw new Exception(
            "Não foi possível confirmar o agendamento."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | VOLTAR PARA A AGENDA
    |--------------------------------------------------------------------------
    */

    $stmtData = $pdo->prepare("
        SELECT data
        FROM agendamentos
        WHERE id = ?
    ");

    $stmtData->execute([$agendamento_id]);

    $data = $stmtData->fetchColumn();

    if ($data) {
        header(
            "Location: agendamentos.php?data=" .
                urlencode($data) .
                "&msg=agendamento_confirmado"
        );
    } else {
        header("Location: agendamentos.php?msg=agendamento_confirmado");
    }

    exit;
} catch (Exception $e) {

    http_response_code(400);

    die("Erro ao confirmar agendamento: " .
        htmlspecialchars($e->getMessage()));
}
