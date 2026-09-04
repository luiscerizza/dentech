<?php

require_once 'config/auth.php';
require_once 'config/csrf.php';
require_once 'conexao/conexao.php';

exigirLogin();

/*
|--------------------------------------------------------------------------
| Funções auxiliares
|--------------------------------------------------------------------------
*/

function escapar($valor): string
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

function moedaBR($valor): string
{
    return 'R$ ' . number_format((float)$valor, 2, ',', '.');
}

function dataBR(?string $data): string
{
    if (empty($data)) {
        return '-';
    }

    $timestamp = strtotime($data);

    return $timestamp ? date('d/m/Y', $timestamp) : '-';
}

function textoTipo(string $tipo): string
{
    return $tipo === 'receita' ? 'Receita' : 'Despesa';
}

function classeTipo(string $tipo): string
{
    return $tipo === 'receita' ? 'tipo-receita' : 'tipo-despesa';
}

function textoStatus(string $status): string
{
    return $status === 'pago' ? 'Pago' : 'Pendente';
}

function classeStatus(string $status): string
{
    return $status === 'pago' ? 'status-pago' : 'status-pendente';
}

/*
|--------------------------------------------------------------------------
| ID
|--------------------------------------------------------------------------
*/

$lancamento_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$lancamento_id || $lancamento_id <= 0) {
    header('Location: financeiro.php?erro=' . urlencode('Lançamento inválido.'));
    exit;
}

/*
|--------------------------------------------------------------------------
| Buscar lançamento
|--------------------------------------------------------------------------
|
| Lançamentos manuais são aqueles que não possuem vínculo com
| procedimento, parcela ou orçamento.
|
*/

$stmt = $pdo->prepare("
    SELECT
        l.id,
        l.tipo,
        l.categoria,
        l.descricao,
        l.data,
        l.forma_pagamento,
        l.valor,
        l.parcelas,
        l.status,
        l.observacoes,
        l.orcamento_id,
        l.parcela_id,
        l.procedimento_id,
        l.created_at
    FROM lancamentos_financeiros l
    WHERE l.id = ?
    LIMIT 1
");

$stmt->execute([$lancamento_id]);
$lancamento = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lancamento) {
    header('Location: financeiro.php?erro=' . urlencode('Lançamento não encontrado.'));
    exit;
}

/*
|--------------------------------------------------------------------------
| Identificar origem
|--------------------------------------------------------------------------
*/

if (!empty($lancamento['procedimento_id'])) {
    $origem = 'procedimento';
} elseif (!empty($lancamento['parcela_id'])) {
    $origem = 'parcela';
} elseif (!empty($lancamento['orcamento_id'])) {
    $origem = 'orcamento';
} else {
    $origem = 'manual';
}

/*
|--------------------------------------------------------------------------
| Bloquear edição/pagamento por esta tela para registros vinculados
|--------------------------------------------------------------------------
|
| Esta página foi criada para lançamentos financeiros manuais.
| Registros originados de procedimento/parcela/orçamento possuem
| seus próprios fluxos.
|
*/

if ($origem !== 'manual') {
    header(
        'Location: financeiro.php?erro=' .
            urlencode('Este registro possui origem vinculada e deve ser acessado pelo fluxo correspondente.')
    );
    exit;
}

$csrf_token = $_SESSION['csrf_token'] ?? '';

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        Visualizar lançamento #<?= (int)$lancamento['id'] ?> | Dentech
    </title>

    <link rel="stylesheet" href="css/global.css">
    <link rel="stylesheet" href="css/variables.css">
    <link rel="stylesheet" href="css/layout.css">
    <link rel="stylesheet" href="css/navbar.css">
    <link rel="stylesheet" href="css/visualizar_lancamento.css">

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <link rel="icon" type="image/png" href="img/icon.PNG">

</head>

<body>

    <?php include 'navbar.php'; ?>

    <main class="container">

        <div class="page-header">

            <div>
                <div class="breadcrumb">
                    <a href="financeiro.php">Financeiro</a>
                    <span class="breadcrumb-separator">/</span>
                    <span>Visualização</span>
                </div>

                <h1>Visualizar lançamento</h1>

                <p>
                    Consulte os detalhes desta movimentação financeira.
                </p>
            </div>

            <div class="header-actions">

                <a href="financeiro.php" class="btn btn-voltar">
                    <i class="fa-solid fa-arrow-left"></i>
                    Voltar
                </a>

                <a
                    href="editar_lancamento.php?id=<?= (int)$lancamento['id'] ?>"
                    class="btn btn-editar">
                    <i class="fa-solid fa-pen"></i>
                    Editar
                </a>

                <?php if ($lancamento['status'] === 'pendente'): ?>

                    <form
                        method="POST"
                        action="pagar_lancamento.php"
                        style="margin:0;"
                        onsubmit="return confirm('Confirmar pagamento deste lançamento?');">

                        <input
                            type="hidden"
                            name="lancamento_id"
                            value="<?= (int)$lancamento['id'] ?>">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= escapar($csrf_token) ?>">

                        <button type="submit" class="btn btn-pagar">
                            <i class="fa-solid fa-check"></i>
                            Pagar
                        </button>

                    </form>

                <?php endif; ?>

            </div>

        </div>

        <section class="card">

            <div class="card-header">

                <div>
                    <h2>Resumo do lançamento</h2>
                    <p>
                        Lançamento #<?= (int)$lancamento['id'] ?>
                    </p>
                </div>

                <span class="status-topo <?= classeStatus($lancamento['status']) ?>">

                    <i class="fa-solid <?= $lancamento['status'] === 'pago' ? 'fa-circle-check' : 'fa-clock' ?>"></i>

                    <?= escapar(textoStatus($lancamento['status'])) ?>

                </span>

            </div>

            <div class="card-body">

                <div class="dados-grid">

                    <div class="dado">
                        <span class="dado-label">Tipo</span>

                        <div class="dado-valor tipo <?= classeTipo($lancamento['tipo']) ?>">

                            <i class="fa-solid <?= $lancamento['tipo'] === 'receita'
                                                    ? 'fa-arrow-trend-up'
                                                    : 'fa-arrow-trend-down' ?>"></i>

                            <?= escapar(textoTipo($lancamento['tipo'])) ?>

                        </div>
                    </div>

                    <div class="dado">
                        <span class="dado-label">Valor</span>

                        <div class="dado-valor valor-destaque <?= $lancamento['tipo'] === 'receita'
                                                                    ? 'valor-receita'
                                                                    : 'valor-despesa' ?>">

                            <?= $lancamento['tipo'] === 'receita' ? '+' : '-' ?>
                            <?= moedaBR($lancamento['valor']) ?>

                        </div>
                    </div>

                    <div class="dado">
                        <span class="dado-label">Descrição</span>

                        <div class="dado-valor">
                            <strong><?= escapar($lancamento['descricao']) ?></strong>
                        </div>
                    </div>

                    <div class="dado">
                        <span class="dado-label">Categoria</span>

                        <div class="dado-valor">
                            <?= escapar($lancamento['categoria']) ?>
                        </div>
                    </div>

                    <div class="dado">
                        <span class="dado-label">Data do lançamento</span>

                        <div class="dado-valor">
                            <?= dataBR($lancamento['data']) ?>
                        </div>
                    </div>

                    <div class="dado">
                        <span class="dado-label">Forma de pagamento</span>

                        <div class="dado-valor">
                            <?= escapar($lancamento['forma_pagamento']) ?>
                        </div>
                    </div>

                    <div class="dado">
                        <span class="dado-label">Parcelas</span>

                        <div class="dado-valor">
                            <?= (int)$lancamento['parcelas'] === 1
                                ? 'À vista'
                                : (int)$lancamento['parcelas'] . 'x' ?>
                        </div>
                    </div>

                    <div class="dado">
                        <span class="dado-label">Cadastrado em</span>

                        <div class="dado-valor">
                            <?= dataBR($lancamento['created_at']) ?>

                            <?php if (!empty($lancamento['created_at'])): ?>
                                às <?= date('H:i', strtotime($lancamento['created_at'])) ?>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>

            </div>

        </section>

        <section class="card">

            <div class="card-header">

                <div>
                    <h2>Observações</h2>
                    <p>Informações adicionais registradas no lançamento.</p>
                </div>

            </div>

            <div class="card-body">

                <?php if (trim((string)$lancamento['observacoes']) !== ''): ?>

                    <p class="observacoes">
                        <?= escapar($lancamento['observacoes']) ?>
                    </p>

                <?php else: ?>

                    <div class="sem-observacoes">
                        Nenhuma observação foi registrada para este lançamento.
                    </div>

                <?php endif; ?>

            </div>

        </section>

        <?php if ($lancamento['status'] === 'pendente'): ?>

            <section class="card">

                <div class="card-body">

                    <div class="aviso-pagamento">
                        <i class="fa-solid fa-circle-info"></i>

                        <div>
                            Este lançamento está <strong>pendente</strong>.
                            Ao confirmar o pagamento, ele será marcado como
                            <strong>pago</strong> no Financeiro.
                        </div>
                    </div>

                </div>

            </section>

        <?php endif; ?>

    </main>

</body>

</html>