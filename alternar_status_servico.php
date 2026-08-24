<?php

require_once 'config/auth.php';
require_once 'config/csrf.php';
require_once 'conexao/conexao.php';

exigirLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: servicos.php');
    exit;
}

validar_csrf();

$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    header('Location: servicos.php?erro=servico_invalido');
    exit;
}

try {

    /*
    |--------------------------------------------------------------------------
    | BUSCAR STATUS ATUAL
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT id, nome, ativo
        FROM servicos
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$id]);

    $servico = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$servico) {
        header('Location: servicos.php?erro=servico_nao_encontrado');
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | ALTERNAR STATUS
    |--------------------------------------------------------------------------
    */

    $novo_status = ((int)$servico['ativo'] === 1) ? 0 : 1;

    $stmt = $pdo->prepare("
        UPDATE servicos
        SET ativo = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $novo_status,
        $id
    ]);

    $mensagem =
        $novo_status === 1
            ? 'ativado'
            : 'desativado';

    header(
        'Location: servicos.php?sucesso=' .
        $mensagem
    );

    exit;

} catch (Throwable $e) {

    error_log(
        'ERRO ALTERAR STATUS SERVICO: ' .
        $e->getMessage()
    );

    header(
        'Location: servicos.php?erro=nao_foi_possivel_alterar'
    );

    exit;
}