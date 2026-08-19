<?php
require_once 'config/auth.php';
exigirLogin();
require_once 'conexao/conexao.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Filtros
|--------------------------------------------------------------------------
*/

$periodo = $_GET['periodo'] ?? 'mes';
$tipo_filtro = $_GET['tipo'] ?? 'todos';

$periodos_validos = ['mes', 'hoje', '7dias', '30dias', 'todos'];
$tipos_validos = ['todos', 'receita', 'despesa'];

if (!in_array($periodo, $periodos_validos, true)) {
    $periodo = 'mes';
}

if (!in_array($tipo_filtro, $tipos_validos, true)) {
    $tipo_filtro = 'todos';
}

/*
|--------------------------------------------------------------------------
| Condições dos filtros
|--------------------------------------------------------------------------
*/

$where = [];
$params = [];

switch ($periodo) {
    case 'hoje':
        $where[] = "DATE(data) = CURDATE()";
        break;

    case '7dias':
        $where[] = "data >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)";
        $where[] = "data <= CURDATE()";
        break;

    case '30dias':
        $where[] = "data >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)";
        $where[] = "data <= CURDATE()";
        break;

    case 'todos':
        break;

    case 'mes':
    default:
        $where[] = "YEAR(data) = YEAR(CURDATE())";
        $where[] = "MONTH(data) = MONTH(CURDATE())";
        break;
}

if ($tipo_filtro !== 'todos') {
    $where[] = "tipo = :tipo";
    $params[':tipo'] = $tipo_filtro;
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

/*
|--------------------------------------------------------------------------
| Resumo financeiro
|--------------------------------------------------------------------------
|
| Os cards são calculados respeitando o período selecionado.
| "A receber" considera somente receitas pendentes.
|--------------------------------------------------------------------------
*/

$stmt_resumo = $pdo->prepare("
    SELECT
        COALESCE(SUM(CASE WHEN tipo = 'receita' THEN valor ELSE 0 END), 0) AS receitas,
        COALESCE(SUM(CASE WHEN tipo = 'despesa' THEN valor ELSE 0 END), 0) AS despesas,
        COALESCE(SUM(
            CASE
                WHEN tipo = 'receita' AND status = 'pendente' THEN valor
                ELSE 0
            END
        ), 0) AS a_receber
    FROM lancamentos_financeiros
    $where_sql
");
$stmt_resumo->execute($params);
$resumo = $stmt_resumo->fetch(PDO::FETCH_ASSOC) ?: [
    'receitas' => 0,
    'despesas' => 0,
    'a_receber' => 0
];

$receitas = (float) $resumo['receitas'];
$despesas = (float) $resumo['despesas'];
$a_receber = (float) $resumo['a_receber'];
$lucro = $receitas - $despesas;

/*
|--------------------------------------------------------------------------
| Lançamentos recentes
|--------------------------------------------------------------------------
*/

$stmt_lancamentos = $pdo->prepare("
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
        orcamento_id
    FROM lancamentos_financeiros
    $where_sql
    ORDER BY data DESC, id DESC
    LIMIT 20
");
$stmt_lancamentos->execute($params);
$lancamentos = $stmt_lancamentos->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Fluxo de caixa
|--------------------------------------------------------------------------
|
| Agrupa os últimos 30 dias com lançamentos.
|--------------------------------------------------------------------------
*/

$where_grafico = [];
$params_grafico = [];

if ($periodo === 'mes') {
    $where_grafico[] = "YEAR(data) = YEAR(CURDATE())";
    $where_grafico[] = "MONTH(data) = MONTH(CURDATE())";
} elseif ($periodo === 'hoje') {
    $where_grafico[] = "DATE(data) = CURDATE()";
} elseif ($periodo === '7dias') {
    $where_grafico[] = "data >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)";
    $where_grafico[] = "data <= CURDATE()";
} elseif ($periodo === '30dias') {
    $where_grafico[] = "data >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)";
    $where_grafico[] = "data <= CURDATE()";
} else {
    $where_grafico[] = "data >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)";
    $where_grafico[] = "data <= CURDATE()";
}

if ($tipo_filtro !== 'todos') {
    $where_grafico[] = "tipo = :tipo_grafico";
    $params_grafico[':tipo_grafico'] = $tipo_filtro;
}

$where_grafico_sql = 'WHERE ' . implode(' AND ', $where_grafico);

$stmt_grafico = $pdo->prepare("
    SELECT
        DATE(data) AS dia,
        COALESCE(SUM(CASE WHEN tipo = 'receita' THEN valor ELSE 0 END), 0) AS receitas,
        COALESCE(SUM(CASE WHEN tipo = 'despesa' THEN valor ELSE 0 END), 0) AS despesas
    FROM lancamentos_financeiros
    $where_grafico_sql
    GROUP BY DATE(data)
    ORDER BY DATE(data) ASC
");
$stmt_grafico->execute($params_grafico);
$dados_grafico = $stmt_grafico->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Mensagem de sucesso
|--------------------------------------------------------------------------
*/

$sucesso = isset($_GET['sucesso']) && $_GET['sucesso'] === '1';

function moedaBR($valor): string
{
    return 'R$ ' . number_format((float) $valor, 2, ',', '.');
}

function dataBR($data): string
{
    if (empty($data)) {
        return '—';
    }

    $timestamp = strtotime($data);
    return $timestamp ? date('d/m/Y', $timestamp) : '—';
}

function classeStatus($status): string
{
    return match ($status) {
        'pago' => 'status-pago',
        'pendente' => 'status-pendente',
        default => 'status-outro'
    };
}

function textoStatus($status): string
{
    return match ($status) {
        'pago' => 'Pago',
        'pendente' => 'Pendente',
        default => ucfirst((string) $status)
    };
}

$periodos_nomes = [
    'mes' => 'Este mês',
    'hoje' => 'Hoje',
    '7dias' => 'Últimos 7 dias',
    '30dias' => 'Últimos 30 dias',
    'todos' => 'Todo o período'
];

$tipos_nomes = [
    'todos' => 'Todos os tipos',
    'receita' => 'Receitas',
    'despesa' => 'Despesas'
];
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Financeiro | Dentech</title>

    <link rel="icon" type="image/png" href="img/icon.PNG">

    <link rel="stylesheet" href="css/navbar.css">
    <link rel="stylesheet" href="css/financeiro.css">

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>

<body>

    <?php include 'navbar.php'; ?>

    <main class="container">

        <div class="page-header">
            <div>
                <div class="breadcrumb">
                    <span>Financeiro</span>
                </div>

                <h1>Financeiro</h1>
                <p>Controle as receitas, despesas e movimentações financeiras da clínica.</p>
            </div>

            <a href="novo_lancamento.php" class="btn-novo">
                <i class="fa-solid fa-plus"></i>
                Novo lançamento
            </a>
        </div>

        <?php if ($sucesso): ?>
            <div class="alert-sucesso">
                <i class="fa-solid fa-circle-check"></i>
                Lançamento salvo com sucesso.
            </div>
        <?php endif; ?>

        <section class="filtros-card">

            <form method="GET" class="filtros-form">

                <div class="filtro-grupo">
                    <label for="periodo">Período</label>

                    <div class="select-wrapper">
                        <i class="fa-regular fa-calendar"></i>

                        <select id="periodo" name="periodo">
                            <?php foreach ($periodos_nomes as $valor => $nome): ?>
                                <option
                                    value="<?= htmlspecialchars($valor) ?>"
                                    <?= $periodo === $valor ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($nome) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="filtro-grupo">
                    <label for="tipo">Tipo</label>

                    <div class="select-wrapper">
                        <i class="fa-solid fa-filter"></i>

                        <select id="tipo" name="tipo">
                            <?php foreach ($tipos_nomes as $valor => $nome): ?>
                                <option
                                    value="<?= htmlspecialchars($valor) ?>"
                                    <?= $tipo_filtro === $valor ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($nome) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <button type="submit" class="btn-filtrar">
                    <i class="fa-solid fa-filter"></i>
                    Filtrar
                </button>

                <?php if ($periodo !== 'mes' || $tipo_filtro !== 'todos'): ?>
                    <a href="financeiro.php" class="btn-limpar">
                        Limpar
                    </a>
                <?php endif; ?>

            </form>

        </section>

        <section class="resumo-grid">

            <article class="resumo-card receita">
                <div class="resumo-icon">
                    <i class="fa-solid fa-arrow-trend-up"></i>
                </div>

                <div>
                    <span class="resumo-label">Receitas</span>
                    <strong><?= moedaBR($receitas) ?></strong>
                </div>
            </article>

            <article class="resumo-card despesa">
                <div class="resumo-icon">
                    <i class="fa-solid fa-arrow-trend-down"></i>
                </div>

                <div>
                    <span class="resumo-label">Despesas</span>
                    <strong><?= moedaBR($despesas) ?></strong>
                </div>
            </article>

            <article class="resumo-card lucro">
                <div class="resumo-icon">
                    <i class="fa-solid fa-dollar-sign"></i>
                </div>

                <div>
                    <span class="resumo-label">Lucro</span>
                    <strong><?= moedaBR($lucro) ?></strong>
                </div>
            </article>

            <article class="resumo-card receber">
                <div class="resumo-icon">
                    <i class="fa-regular fa-credit-card"></i>
                </div>

                <div>
                    <span class="resumo-label">A receber</span>
                    <strong><?= moedaBR($a_receber) ?></strong>
                </div>
            </article>

        </section>

        <section class="conteudo-grid">

            <div class="card fluxo-card">

                <div class="card-header">
                    <div>
                        <h2>Fluxo de caixa</h2>
                        <p>Movimentações financeiras do período selecionado.</p>
                    </div>
                </div>

                <div class="legenda">
                    <span>
                        <i class="ponto receita"></i>
                        Receitas
                    </span>

                    <span>
                        <i class="ponto despesa"></i>
                        Despesas
                    </span>
                </div>

                <div class="grafico-wrapper">
                    <?php if (!empty($dados_grafico)): ?>

                        <div class="grafico-barras">

                            <?php
                            $maior_valor = 0;

                            foreach ($dados_grafico as $dia):
                                $maior_valor = max(
                                    $maior_valor,
                                    (float) $dia['receitas'],
                                    (float) $dia['despesas']
                                );
                            endforeach;

                            $maior_valor = $maior_valor > 0 ? $maior_valor : 1;
                            ?>

                            <?php foreach ($dados_grafico as $dia): ?>
                                <?php
                                $altura_receita = ((float) $dia['receitas'] / $maior_valor) * 100;
                                $altura_despesa = ((float) $dia['despesas'] / $maior_valor) * 100;
                                ?>

                                <div class="barra-coluna" title="<?= dataBR($dia['dia']) ?>">
                                    <div class="barras">
                                        <div
                                            class="barra barra-receita"
                                            style="height: <?= max(2, $altura_receita) ?>%;">
                                        </div>

                                        <div
                                            class="barra barra-despesa"
                                            style="height: <?= max(2, $altura_despesa) ?>%;">
                                        </div>
                                    </div>

                                    <span class="barra-label">
                                        <?= date('d/m', strtotime($dia['dia'])) ?>
                                    </span>
                                </div>

                            <?php endforeach; ?>

                        </div>

                    <?php else: ?>

                        <div class="sem-dados">
                            <i class="fa-solid fa-chart-line"></i>
                            <span>Não existem movimentações no período selecionado.</span>
                        </div>

                    <?php endif; ?>
                </div>

            </div>

            <div class="card recentes-card">

                <div class="card-header">
                    <div>
                        <h2>Lançamentos recentes</h2>
                        <p>Últimas movimentações financeiras.</p>
                    </div>
                </div>

                <?php if (!empty($lancamentos)): ?>

                    <div class="lista-lancamentos">

                        <?php foreach ($lancamentos as $lancamento): ?>

                            <div class="lancamento-item">

                                <div class="lancamento-data">
                                    <?= date('d/m', strtotime($lancamento['data'])) ?>
                                </div>

                                <div class="lancamento-info">

                                    <strong>
                                        <?= htmlspecialchars($lancamento['descricao']) ?>
                                    </strong>

                                    <span>
                                        <?= htmlspecialchars($lancamento['categoria']) ?>
                                        ·
                                        <?= htmlspecialchars($lancamento['forma_pagamento']) ?>
                                    </span>

                                </div>

                                <div class="lancamento-valor <?= $lancamento['tipo'] === 'receita' ? 'valor-receita' : 'valor-despesa' ?>">
                                    <?= $lancamento['tipo'] === 'receita' ? '+' : '-' ?>
                                    <?= moedaBR($lancamento['valor']) ?>
                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php else: ?>

                    <div class="sem-lancamentos">
                        <i class="fa-regular fa-file-lines"></i>
                        <strong>Nenhum lançamento encontrado</strong>
                        <span>Cadastre uma receita ou despesa para começar.</span>

                        <a href="novo_lancamento.php" class="btn-novo-vazio">
                            <i class="fa-solid fa-plus"></i>
                            Novo lançamento
                        </a>
                    </div>

                <?php endif; ?>

                <a href="financeiro_lancamentos.php" class="ver-todos">
                    Ver todos
                    <i class="fa-solid fa-arrow-right"></i>
                </a>

            </div>

        </section>

        <section class="card tabela-card">

            <div class="card-header tabela-header">
                <div>
                    <h2>Movimentações</h2>
                    <p>Últimos lançamentos encontrados.</p>
                </div>

                <span class="contador">
                    <?= count($lancamentos) ?> lançamento<?= count($lancamentos) === 1 ? '' : 's' ?>
                </span>
            </div>

            <?php if (!empty($lancamentos)): ?>

                <div class="tabela-scroll">

                    <table>

                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Descrição</th>
                                <th>Categoria</th>
                                <th>Pagamento</th>
                                <th>Parcelas</th>
                                <th>Status</th>
                                <th class="coluna-valor">Valor</th>
                                <th class="coluna-acoes">Ações</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($lancamentos as $lancamento): ?>

                                <tr>

                                    <td>
                                        <?= dataBR($lancamento['data']) ?>
                                    </td>

                                    <td>
                                        <strong>
                                            <?= htmlspecialchars($lancamento['descricao']) ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($lancamento['categoria']) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($lancamento['forma_pagamento']) ?>
                                    </td>

                                    <td>
                                        <?= (int) $lancamento['parcelas'] === 1
                                            ? 'À vista'
                                            : (int) $lancamento['parcelas'] . 'x' ?>
                                    </td>

                                    <td>
                                        <span class="status-badge <?= classeStatus($lancamento['status']) ?>">
                                            <?= htmlspecialchars(textoStatus($lancamento['status'])) ?>
                                        </span>
                                    </td>

                                    <td class="coluna-valor <?= $lancamento['tipo'] === 'receita' ? 'valor-receita' : 'valor-despesa' ?>">
                                        <?= $lancamento['tipo'] === 'receita' ? '+' : '-' ?>
                                        <?= moedaBR($lancamento['valor']) ?>
                                    </td>

                                    <td class="coluna-acoes">

                                        <a
                                            href="visualizar_lancamento.php?id=<?= (int) $lancamento['id'] ?>"
                                            class="btn-acao"
                                            title="Visualizar">
                                            <i class="fa-regular fa-eye"></i>
                                        </a>

                                        <a
                                            href="editar_lancamento.php?id=<?= (int) $lancamento['id'] ?>"
                                            class="btn-acao"
                                            title="Editar">
                                            <i class="fa-solid fa-pen"></i>
                                        </a>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="tabela-vazia">
                    <i class="fa-solid fa-wallet"></i>
                    <h3>Nenhum lançamento cadastrado</h3>
                    <p>Comece cadastrando sua primeira receita ou despesa.</p>

                    <a href="novo_lancamento.php" class="btn-novo">
                        <i class="fa-solid fa-plus"></i>
                        Novo lançamento
                    </a>
                </div>

            <?php endif; ?>

        </section>

    </main>

</body>

</html>