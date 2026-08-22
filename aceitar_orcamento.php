<?php

require_once 'config/auth.php';
require_once 'config/csrf.php';
require_once 'conexao/conexao.php';

exigirLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: orcamento.php');
    exit;
}

validar_csrf();

$orcamento_id = (int)($_POST['id'] ?? 0);

if ($orcamento_id <= 0) {
    die('Orçamento inválido.');
}

try {

    $pdo->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | 1. Buscar orçamento
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT id, status
        FROM orcamentos
        WHERE id = ?
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->execute([$orcamento_id]);

    $orcamento = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$orcamento) {
        throw new Exception('Orçamento não encontrado.');
    }

    if ($orcamento['status'] === 'recusado') {
        throw new Exception('Um orçamento recusado não pode ser aceito.');
    }

    if (!in_array($orcamento['status'], ['pendente', 'aceito'], true)) {
        throw new Exception('Status do orçamento inválido.');
    }


    /*
    |--------------------------------------------------------------------------
    | 2. Buscar parcelas
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            id,
            numero_parcela,
            valor,
            vencimento,
            status
        FROM parcelas
        WHERE orcamento_id = ?
        ORDER BY numero_parcela ASC
        FOR UPDATE
    ");

    $stmt->execute([$orcamento_id]);

    $parcelas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($parcelas)) {
        throw new Exception(
            'Este orçamento não possui parcelas cadastradas.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | 3. Alterar orçamento para ACEITO
    |--------------------------------------------------------------------------
    */

    if ($orcamento['status'] === 'pendente') {
        $stmt = $pdo->prepare("
            UPDATE orcamentos
            SET status = 'aceito'
            WHERE id = ?
              AND status = 'pendente'
        ");

        $stmt->execute([$orcamento_id]);

        if ($stmt->rowCount() !== 1) {
            throw new Exception(
                'Não foi possível aceitar o orçamento.'
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | 4. Criar lançamentos financeiros
    |--------------------------------------------------------------------------
    */

    $stmtLancamento = $pdo->prepare("
        INSERT INTO lancamentos_financeiros (
            tipo,
            categoria,
            descricao,
            data,
            forma_pagamento,
            valor,
            parcelas,
            status,
            observacoes,
            orcamento_id,
            parcela_id
        )
        VALUES (
            'receita',
            'Orçamento',
            ?,
            ?,
            'A definir',
            ?,
            ?,
            ?,
            ?,
            ?,
            ?
        )
    ");


    $totalParcelas = count($parcelas);

    foreach ($parcelas as $parcela) {

        /*
        |--------------------------------------------------------------------------
        | Evita duplicação
        |--------------------------------------------------------------------------
        */

        $stmtExiste = $pdo->prepare("
            SELECT id
            FROM lancamentos_financeiros
            WHERE parcela_id = ?
            LIMIT 1
        ");

        $stmtExiste->execute([
            $parcela['id']
        ]);

        if ($stmtExiste->fetch()) {
            continue;
        }


        /*
        |--------------------------------------------------------------------------
        | Status financeiro
        |--------------------------------------------------------------------------
        */

        $statusFinanceiro = $parcela['status'] === 'paga'
            ? 'pago'
            : 'pendente';


        /*
        |--------------------------------------------------------------------------
        | Descrição
        |--------------------------------------------------------------------------
        */

        $descricao = sprintf(
            'Orçamento #%d - Parcela %d/%d',
            $orcamento_id,
            $parcela['numero_parcela'],
            $totalParcelas
        );


        /*
        |--------------------------------------------------------------------------
        | Observação
        |--------------------------------------------------------------------------
        */

        $observacao = sprintf(
            'Receita gerada pelo orçamento #%d. Parcela %d/%d.',
            $orcamento_id,
            $parcela['numero_parcela'],
            $totalParcelas
        );


        /*
        |--------------------------------------------------------------------------
        | Inserção
        |--------------------------------------------------------------------------
        */

        $stmtLancamento->execute([
            $descricao,
            $parcela['vencimento'],
            $parcela['valor'],
            $totalParcelas,
            $statusFinanceiro,
            $observacao,
            $orcamento_id,
            $parcela['id']
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | 5. Confirmar tudo
    |--------------------------------------------------------------------------
    */

    $pdo->commit();

    header(
        'Location: visualizar_orcamento.php?id=' .
            $orcamento_id
    );

    exit;
} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'ERRO AO ACEITAR ORÇAMENTO #' .
            $orcamento_id .
            ': ' .
            $e->getMessage()
    );

    die('Não foi possível aceitar o orçamento. ' .
        htmlspecialchars($e->getMessage()));
}
