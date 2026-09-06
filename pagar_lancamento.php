<?php

declare(strict_types=1);

require_once 'config/auth.php';
require_once 'config/csrf.php';
require_once 'conexao/conexao.php';

exigirLogin();

function escapar(?string $valor): string
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

function moedaBR($valor): string
{
    return 'R$ ' . number_format((float)$valor, 2, ',', '.');
}

function dataBR(?string $data): string
{
    if (!$data) {
        return '-';
    }

    $objeto = DateTime::createFromFormat('Y-m-d', $data);

    return $objeto ? $objeto->format('d/m/Y') : $data;
}

function textoTipo(string $tipo): string
{
    return $tipo === 'despesa' ? 'Despesa' : 'Receita';
}

function classeTipo(string $tipo): string
{
    return $tipo === 'despesa' ? 'type-expense' : 'type-income';
}

function textoStatus(string $status): string
{
    return $status === 'pago' ? 'Pago' : 'Pendente';
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    header('Location: financeiro.php?erro=lancamento_invalido');
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT
            id,
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
        FROM lancamentos_financeiros
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $lancamento = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$lancamento) {
        header('Location: financeiro.php?erro=lancamento_nao_encontrado');
        exit;
    }

    // Esta ação é exclusiva para lançamentos manuais.
    if (
        !empty($lancamento['orcamento_id']) ||
        !empty($lancamento['parcela_id']) ||
        !empty($lancamento['procedimento_id'])
    ) {
        header('Location: financeiro.php?erro=lancamento_nao_editavel');
        exit;
    }
} catch (Throwable $e) {
    error_log('pagar_lancamento.php [GET]: ' . $e->getMessage());
    header('Location: financeiro.php?erro=erro_ao_carregar_lancamento');
    exit;
}

$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        validar_csrf();

        $idPost = filter_input(INPUT_POST, 'lancamento_id', FILTER_VALIDATE_INT);

        if (!$idPost || $idPost !== $id) {
            throw new RuntimeException('Lançamento inválido.');
        }

        $pdo->beginTransaction();

        $stmtLock = $pdo->prepare("
            SELECT
                id,
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
            FROM lancamentos_financeiros
            WHERE id = ?
            FOR UPDATE
        ");
        $stmtLock->execute([$id]);

        $lancamentoLocked = $stmtLock->fetch(PDO::FETCH_ASSOC);

        if (!$lancamentoLocked) {
            throw new RuntimeException('Lançamento não encontrado.');
        }

        if (
            !empty($lancamentoLocked['orcamento_id']) ||
            !empty($lancamentoLocked['parcela_id']) ||
            !empty($lancamentoLocked['procedimento_id'])
        ) {
            throw new RuntimeException(
                'Este lançamento está vinculado a outro registro e não pode ser pago por aqui.'
            );
        }

        if ($lancamentoLocked['status'] === 'pago') {
            throw new RuntimeException('Este lançamento já está marcado como pago.');
        }

        $stmtUpdate = $pdo->prepare("
            UPDATE lancamentos_financeiros
            SET status = 'pago'
            WHERE id = ?
              AND status = 'pendente'
              AND orcamento_id IS NULL
              AND parcela_id IS NULL
              AND procedimento_id IS NULL
        ");
        $stmtUpdate->execute([$id]);

        if ($stmtUpdate->rowCount() !== 1) {
            throw new RuntimeException('Não foi possível concluir o pagamento do lançamento.');
        }

        $pdo->commit();

        $mensagem = $lancamentoLocked['tipo'] === 'despesa'
            ? 'Despesa marcada como paga.'
            : 'Receita marcada como recebida.';

        header(
            'Location: visualizar_lancamento.php?id=' .
                $id .
                '&sucesso=' . urlencode($mensagem)
        );
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('pagar_lancamento.php [POST]: ' . $e->getMessage());
        $erros[] = $e->getMessage();
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

$isDespesa = $lancamento['tipo'] === 'despesa';
$acaoTexto = $isDespesa ? 'Pagar despesa' : 'Marcar receita como recebida';
$confirmacaoTexto = $isDespesa
    ? 'Confirme que esta despesa foi efetivamente paga.'
    : 'Confirme que esta receita foi efetivamente recebida.';
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escapar($acaoTexto) ?> - Dentech</title>

    <link rel="stylesheet" href="css/global.css">
    <link rel="stylesheet" href="css/variables.css">
    <link rel="stylesheet" href="css/layout.css">
    <link rel="stylesheet" href="css/navbar.css">
    <link rel="stylesheet" href="css/pagar_lancamento.css">
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>

<body>

    <?php include 'navbar.php'; ?>

    <main class="page-container">
        <div class="page-header">
            <div>
                <span class="eyebrow">FINANCEIRO</span>
                <h1>
                    <i class="fa-solid <?= $isDespesa ? 'fa-money-bill-transfer' : 'fa-circle-check' ?>"></i>
                    <?= escapar($acaoTexto) ?>
                </h1>
                <p><?= escapar($confirmacaoTexto) ?></p>
            </div>

            <a href="visualizar_lancamento.php?id=<?= (int)$lancamento['id'] ?>" class="btn btn-secondary">
                <i class="fa-solid fa-arrow-left"></i>
                Voltar
            </a>
        </div>

        <?php if ($erros): ?>
            <div class="alert alert-error">
                <i class="fa-solid fa-circle-exclamation"></i>
                <div>
                    <?php foreach ($erros as $erro): ?>
                        <div><?= escapar($erro) ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <section class="payment-card">
            <div class="card-title">
                <div class="title-icon <?= $isDespesa ? 'icon-expense' : 'icon-income' ?>">
                    <i class="fa-solid <?= $isDespesa ? 'fa-receipt' : 'fa-hand-holding-dollar' ?>"></i>
                </div>
                <div>
                    <h2>Confirmar operação</h2>
                    <p>Confira os dados antes de alterar o status financeiro.</p>
                </div>
            </div>

            <div class="summary-grid">
                <div class="summary-item">
                    <span>Tipo</span>
                    <strong class="<?= escapar(classeTipo($lancamento['tipo'])) ?>">
                        <?= escapar(textoTipo($lancamento['tipo'])) ?>
                    </strong>
                </div>

                <div class="summary-item">
                    <span>Categoria</span>
                    <strong><?= escapar($lancamento['categoria']) ?></strong>
                </div>

                <div class="summary-item summary-wide">
                    <span>Descrição</span>
                    <strong><?= escapar($lancamento['descricao']) ?></strong>
                </div>

                <div class="summary-item">
                    <span>Valor</span>
                    <strong class="value"><?= moedaBR($lancamento['valor']) ?></strong>
                </div>

                <div class="summary-item">
                    <span>Data</span>
                    <strong><?= escapar(dataBR($lancamento['data'])) ?></strong>
                </div>

                <div class="summary-item">
                    <span>Forma de pagamento</span>
                    <strong><?= escapar($lancamento['forma_pagamento']) ?></strong>
                </div>

                <div class="summary-item">
                    <span>Status atual</span>
                    <strong class="status-pending">
                        <i class="fa-solid fa-clock"></i>
                        <?= escapar(textoStatus($lancamento['status'])) ?>
                    </strong>
                </div>
            </div>

            <div class="warning-box">
                <i class="fa-solid fa-circle-info"></i>
                <div>
                    <strong>O que acontecerá?</strong>
                    <p>
                        O lançamento será alterado de <strong>Pendente</strong> para
                        <strong>Pago</strong>. Esta operação não cria parcelas,
                        não altera procedimentos e não modifica orçamentos.
                    </p>
                </div>
            </div>

            <form method="POST" action="pagar_lancamento.php?id=<?= (int)$lancamento['id'] ?>">
                <input type="hidden" name="csrf_token" value="<?= escapar($csrfToken) ?>">
                <input type="hidden" name="lancamento_id" value="<?= (int)$lancamento['id'] ?>">

                <div class="form-footer">
                    <a href="visualizar_lancamento.php?id=<?= (int)$lancamento['id'] ?>" class="btn btn-secondary">
                        Cancelar
                    </a>

                    <button type="submit" class="btn btn-success">
                        <i class="fa-solid fa-circle-check"></i>
                        <?= escapar($acaoTexto) ?>
                    </button>
                </div>
            </form>
        </section>
    </main>

</body>

</html>