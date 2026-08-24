<?php

require_once 'config/auth.php';
require_once 'config/csrf.php';
require_once 'conexao/conexao.php';

exigirLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: financeiro.php');
    exit;
}

validar_csrf();

$lancamento_id = (int)($_POST['lancamento_id'] ?? 0);

if ($lancamento_id <= 0) {
    die('Lançamento inválido.');
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT id, status, categoria
        FROM lancamentos_financeiros
        WHERE id = ?
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute([$lancamento_id]);

    $lancamento = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$lancamento) {
        throw new Exception('Lançamento financeiro não encontrado.');
    }

    if ($lancamento['categoria'] !== 'Ajuste de procedimento') {
        throw new Exception('Este lançamento não é um ajuste de procedimento.');
    }

    if (strtolower(trim((string)$lancamento['status'])) !== 'pendente') {
        throw new Exception('Este ajuste já foi processado.');
    }

    $stmt = $pdo->prepare("
        UPDATE lancamentos_financeiros
        SET
            status = 'pago',
            data = CURDATE()
        WHERE id = ?
          AND categoria = 'Ajuste de procedimento'
          AND LOWER(TRIM(status)) = 'pendente'
    ");
    $stmt->execute([$lancamento_id]);

    if ($stmt->rowCount() !== 1) {
        throw new Exception('Não foi possível registrar o pagamento do ajuste.');
    }

    $pdo->commit();

    header('Location: financeiro.php?sucesso=pagamento');
    exit;
} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(400);

    die('Não foi possível registrar o pagamento: ' .
        htmlspecialchars($e->getMessage()));
}
