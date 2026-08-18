<?php

require_once 'config/auth.php';
require_once 'config/csrf.php';

exigirLogin();

require_once 'conexao/conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: orcamento.php');
    exit;
}

validar_csrf();

$id_orcamento = (int)($_POST['id'] ?? 0);

if ($id_orcamento <= 0) {
    die('Orçamento inválido.');
}

try {

    $pdo->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | Buscar orçamento
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            o.id,
            o.status,
            o.paciente_id,
            p.paciente
        FROM orcamentos o
        INNER JOIN prontuarios p
            ON p.id = o.paciente_id
        WHERE o.id = ?
        FOR UPDATE
    ");

    $stmt->execute([$id_orcamento]);

    $orcamento = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$orcamento) {
        throw new Exception('Orçamento não encontrado.');
    }

    /*
    |--------------------------------------------------------------------------
    | Só permite aceitar orçamento pendente
    |--------------------------------------------------------------------------
    */

    if ($orcamento['status'] !== 'pendente') {
        throw new Exception('Este orçamento não está pendente.');
    }

    /*
    |--------------------------------------------------------------------------
    | Calcular total do orçamento
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            COALESCE(
                SUM(quantidade * valor_unitario),
                0
            ) AS total
        FROM orcamentos_itens
        WHERE orcamento_id = ?
    ");

    $stmt->execute([$id_orcamento]);

    $total = (float)$stmt->fetchColumn();

    if ($total <= 0) {
        throw new Exception(
            'Não é possível aceitar um orçamento sem valor.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Buscar quantidade de parcelas
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) AS quantidade,
            COALESCE(SUM(valor), 0) AS total_parcelas
        FROM parcelas
        WHERE orcamento_id = ?
    ");

    $stmt->execute([$id_orcamento]);

    $dados_parcelas = $stmt->fetch(PDO::FETCH_ASSOC);

    $quantidade_parcelas = (int)$dados_parcelas['quantidade'];

    /*
    |--------------------------------------------------------------------------
    | Se não houver parcelas, considera à vista
    |--------------------------------------------------------------------------
    */

    if ($quantidade_parcelas <= 0) {
        $quantidade_parcelas = 1;
    }

    /*
    |--------------------------------------------------------------------------
    | Alterar status do orçamento
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        UPDATE orcamentos
        SET status = 'aceito'
        WHERE id = ?
    ");

    $stmt->execute([$id_orcamento]);

    /*
    |--------------------------------------------------------------------------
    | Verificar se já existe lançamento financeiro
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT id
        FROM lancamentos_financeiros
        WHERE orcamento_id = ?
          AND tipo = 'receita'
        LIMIT 1
    ");

    $stmt->execute([$id_orcamento]);

    $lancamento_existente = $stmt->fetchColumn();

    /*
    |--------------------------------------------------------------------------
    | Criar receita no financeiro
    |--------------------------------------------------------------------------
    */

    if (!$lancamento_existente) {

        $descricao = 'Orçamento #' .
            $id_orcamento .
            ' - ' .
            $orcamento['paciente'];

        $stmt = $pdo->prepare("
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
                orcamento_id
            )
            VALUES (
                'receita',
                'Orçamento',
                ?,
                CURDATE(),
                'A definir',
                ?,
                ?,
                'pendente',
                ?,
                ?
            )
        ");

        $observacoes =
            'Receita gerada automaticamente a partir do orçamento #' .
            $id_orcamento .
            '.';

        $stmt->execute([
            $descricao,
            $total,
            $quantidade_parcelas,
            $observacoes,
            $id_orcamento
        ]);
    }

    $pdo->commit();

    header(
        'Location: visualizar_orcamento.php?id=' .
            $id_orcamento
    );

    exit;
} catch (Exception $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    die('Erro ao aceitar orçamento: ' .
        htmlspecialchars($e->getMessage()));
}
