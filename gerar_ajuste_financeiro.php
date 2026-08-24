<?php

require_once 'config/auth.php';
require_once 'config/csrf.php';
require_once 'conexao/conexao.php';

exigirLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: visualizar_prontuario.php');
    exit;
}

validar_csrf();

$procedimento_id = (int)($_POST['procedimento_id'] ?? 0);

if ($procedimento_id <= 0) {
    die('Procedimento inválido.');
}

try {
    $pdo->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | 1. Buscar procedimento e orçamento
    |--------------------------------------------------------------------------
    */
    $stmt = $pdo->prepare("
        SELECT
            p.id,
            p.paciente_id,
            p.orcamento_id,
            p.titulo,
            p.valor_final
        FROM procedimentos p
        WHERE p.id = ?
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->execute([$procedimento_id]);
    $procedimento = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$procedimento) {
        throw new Exception('Procedimento não encontrado.');
    }

    $orcamento_id = (int)($procedimento['orcamento_id'] ?? 0);

    if ($orcamento_id <= 0) {
        throw new Exception('O procedimento não possui um orçamento vinculado.');
    }

    /*
    |--------------------------------------------------------------------------
    | 2. Confirmar orçamento aceito
    |--------------------------------------------------------------------------
    */
    $stmt = $pdo->prepare("
        SELECT id, status
        FROM orcamentos
        WHERE id = ?
          AND paciente_id = ?
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->execute([
        $orcamento_id,
        (int)$procedimento['paciente_id']
    ]);

    $orcamento = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$orcamento) {
        throw new Exception('Orçamento vinculado não encontrado.');
    }

    if ($orcamento['status'] !== 'aceito') {
        throw new Exception('O orçamento vinculado precisa estar aceito.');
    }

    /*
    |--------------------------------------------------------------------------
    | 3. Calcular valor do orçamento
    |--------------------------------------------------------------------------
    */
    $stmt = $pdo->prepare("
        SELECT
            COALESCE(
                SUM(quantidade * valor_unitario),
                0
            )
        FROM orcamentos_itens
        WHERE orcamento_id = ?
    ");

    $stmt->execute([$orcamento_id]);
    $valor_orcado = (float)$stmt->fetchColumn();

    $valor_realizado = (float)$procedimento['valor_final'];

    $diferenca = round(
        $valor_realizado - $valor_orcado,
        2
    );

    if (round($diferenca, 2) === 0.0) {
        throw new Exception(
            'Não existe diferença entre o orçamento e o valor realizado.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 4. Evitar ajuste duplicado
    |--------------------------------------------------------------------------
    */
    $stmt = $pdo->prepare("
        SELECT id
        FROM lancamentos_financeiros
        WHERE procedimento_id = ?
          AND categoria = 'Ajuste de procedimento'
          AND tipo = 'receita'
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->execute([$procedimento_id]);

    if ($stmt->fetchColumn()) {
        throw new Exception(
            'Já existe um ajuste financeiro para este procedimento.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 5. Criar ajuste
    |--------------------------------------------------------------------------
    */
    $ehCobranca = $diferenca > 0;

    $descricao = sprintf(
        '%s do procedimento #%d - %s',
        $ehCobranca ? 'Cobrança adicional' : 'Desconto',
        $procedimento_id,
        $procedimento['titulo']
    );

    $observacoes = sprintf(
        '%s gerado pela diferença entre o orçamento #%d (R$ %s) e o valor realizado do procedimento (R$ %s).',
        $ehCobranca ? 'Cobrança adicional' : 'Desconto',
        $orcamento_id,
        number_format($valor_orcado, 2, ',', '.'),
        number_format($valor_realizado, 2, ',', '.')
    );

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
            orcamento_id,
            parcela_id,
            procedimento_id
        )
        VALUES (
            'receita',
            'Ajuste de procedimento',
            ?,
            CURDATE(),
            'A definir',
            ?,
            1,
            'pendente',
            ?,
            ?,
            NULL,
            ?
        )
    ");

    $stmt->execute([
        $descricao,
        $diferenca,
        $observacoes,
        $orcamento_id,
        $procedimento_id
    ]);

    $pdo->commit();

    header(
        'Location: visualizar_prontuario.php?id=' .
            (int)$procedimento['paciente_id'] .
            '&ajuste=1'
    );

    exit;
} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(400);

    die('Não foi possível gerar o ajuste financeiro: ' .
        htmlspecialchars($e->getMessage()));
}
