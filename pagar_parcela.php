<?php

require_once 'config/auth.php';
require_once 'config/csrf.php';
require_once 'conexao/conexao.php';

exigirLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: financeiro.php');
    exit;
}

try {
    validar_csrf();

    $parcela_id = filter_input(INPUT_POST, 'parcela_id', FILTER_VALIDATE_INT);

    if (!$parcela_id || $parcela_id <= 0) {
        throw new Exception('Parcela inválida.');
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT
            p.id,
            p.status,
            p.orcamento_id,
            p.procedimento_id,
            o.status AS status_orcamento
        FROM parcelas p
        LEFT JOIN orcamentos o ON o.id = p.orcamento_id
        WHERE p.id = ?
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute([$parcela_id]);
    $parcela = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$parcela) {
        throw new Exception('Parcela não encontrada.');
    }

    // Parcelas de orçamento só podem ser pagas quando o orçamento estiver aceito.
    // Parcelas de procedimento são cobranças próprias e não dependem de orçamento.
    if (!empty($parcela['orcamento_id']) && $parcela['status_orcamento'] !== 'aceito') {
        throw new Exception('Somente parcelas de orçamentos aceitos podem ser pagas.');
    }

    if (empty($parcela['orcamento_id']) && empty($parcela['procedimento_id'])) {
        throw new Exception('Parcela sem origem financeira válida.');
    }

    if ($parcela['status'] === 'paga') {
        throw new Exception('Esta parcela já está paga.');
    }

    if (!in_array($parcela['status'], ['pendente', 'atrasada'], true)) {
        throw new Exception('Status da parcela não permite pagamento.');
    }

    $stmt = $pdo->prepare("
        UPDATE parcelas
        SET status = 'paga', data_pagamento = CURDATE()
        WHERE id = ?
          AND status IN ('pendente', 'atrasada')
    ");
    $stmt->execute([$parcela_id]);

    if ($stmt->rowCount() !== 1) {
        throw new Exception('Não foi possível registrar o pagamento.');
    }

    $pdo->commit();

    header('Location: financeiro.php?sucesso=pagamento');
    exit;
} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Erro ao pagar parcela: ' . $e->getMessage());

    header('Location: financeiro.php?erro=' . urlencode($e->getMessage()));
    exit;
}
