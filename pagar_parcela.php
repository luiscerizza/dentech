<?php

require_once 'config/auth.php';
require_once 'config/csrf.php';

exigirLogin();

require_once 'conexao/conexao.php';


// ============================================================
// VALIDAR POST
// ============================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: financeiro.php');
    exit;
}


// ============================================================
// CSRF
// ============================================================

validar_csrf();


// ============================================================
// ID DA PARCELA
// ============================================================

$parcela_id = (int)($_POST['parcela_id'] ?? 0);

if ($parcela_id <= 0) {
    die("Parcela inválida.");
}


try {

    // ========================================================
    // BUSCAR PARCELA
    // ========================================================

    $stmt = $pdo->prepare("
        SELECT
            p.id,
            p.orcamento_id,
            p.status,
            p.valor,
            p.vencimento,
            o.status AS status_orcamento
        FROM parcelas p
        INNER JOIN orcamentos o
            ON o.id = p.orcamento_id
        WHERE p.id = ?
        LIMIT 1
    ");

    $stmt->execute([$parcela_id]);

    $parcela = $stmt->fetch(PDO::FETCH_ASSOC);


    if (!$parcela) {
        die("Parcela não encontrada.");
    }


    // ========================================================
    // SOMENTE ORÇAMENTO ACEITO
    // ========================================================

    if ($parcela['status_orcamento'] !== 'aceito') {
        die("O orçamento ainda não foi aceito.");
    }


    // ========================================================
    // NÃO PERMITIR PAGAMENTO DUPLICADO
    // ========================================================

    if ($parcela['status'] === 'paga') {

        header(
            "Location: financeiro.php"
        );

        exit;
    }


    // ========================================================
    // TRANSAÇÃO
    // ========================================================

    $pdo->beginTransaction();


    // ========================================================
    // MARCAR PARCELA COMO PAGA
    // ========================================================

    $stmt = $pdo->prepare("
        UPDATE parcelas
        SET
            status = 'paga',
            data_pagamento = CURDATE()
        WHERE id = ?
          AND status IN ('pendente', 'atrasada')
    ");

    $stmt->execute([
        $parcela_id
    ]);


    // ========================================================
    // CONFIRMAR
    // ========================================================

    $pdo->commit();


    // ========================================================
    // VOLTAR AO FINANCEIRO
    // ========================================================

    header(
        "Location: financeiro.php?sucesso=1"
    );

    exit;
} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        "ERRO AO PAGAR PARCELA #{$parcela_id}: " .
            $e->getMessage()
    );

    die("Não foi possível registrar o pagamento da parcela.");
}
