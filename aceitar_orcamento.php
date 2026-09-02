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
        SELECT
            id,
            status
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

    /*
    |--------------------------------------------------------------------------
    | 2. Validar status
    |--------------------------------------------------------------------------
    */

    if ($orcamento['status'] === 'recusado') {
        throw new Exception(
            'Um orçamento recusado não pode ser aceito.'
        );
    }

    if (!in_array(
        $orcamento['status'],
        ['pendente', 'aceito'],
        true
    )) {
        throw new Exception(
            'Status do orçamento inválido.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 3. Confirmar orçamento
    |--------------------------------------------------------------------------
    |
    | IMPORTANTE:
    |
    | Confirmar um orçamento NÃO cria receita.
    |
    | O orçamento continua sendo uma proposta comercial.
    |
    | A geração de cobrança financeira acontecerá separadamente,
    | quando realmente houver uma cobrança.
    |
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
                'Não foi possível confirmar o orçamento.'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 4. Registrar log
    |--------------------------------------------------------------------------
    */

    if (function_exists('registrarLog')) {

        registrarLog(
            $pdo,
            'Confirmou orçamento',
            'orcamentos',
            $orcamento_id,
            'Orçamento confirmado. Nenhum lançamento financeiro foi criado.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 5. Confirmar transação
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
        'ERRO AO CONFIRMAR ORÇAMENTO #' .
            $orcamento_id .
            ': ' .
            $e->getMessage()
    );

    die('Não foi possível confirmar o orçamento. ' .
        htmlspecialchars(
            $e->getMessage(),
            ENT_QUOTES,
            'UTF-8'
        ));
}
