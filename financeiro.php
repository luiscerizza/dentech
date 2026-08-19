<?php

require_once 'config/auth.php';
exigirLogin();

require_once 'conexao/conexao.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| FUNÇÕES
|--------------------------------------------------------------------------
*/

function moedaBR($valor): string
{
    return 'R$ ' . number_format((float)$valor, 2, ',', '.');
}

function dataBR($data): string
{
    if (empty($data)) {
        return '—';
    }

    $timestamp = strtotime($data);

    return $timestamp
        ? date('d/m/Y', $timestamp)
        : '—';
}

function classeStatus($status): string
{
    switch ($status) {
        case 'pago':
        case 'paga':
            return 'status-pago';

        case 'pendente':
            return 'status-pendente';

        case 'atrasada':
            return 'status-atrasada';

        default:
            return 'status-outro';
    }
}

function textoStatus($status): string
{
    switch ($status) {
        case 'pago':
        case 'paga':
            return 'Pago';

        case 'pendente':
            return 'Pendente';

        case 'atrasada':
            return 'Atrasada';

        default:
            return ucfirst((string)$status);
    }
}

/*
|--------------------------------------------------------------------------
| FILTROS
|--------------------------------------------------------------------------
*/

$periodo = $_GET['periodo'] ?? 'mes';
$tipo_filtro = $_GET['tipo'] ?? 'todos';

$periodos_validos = [
    'mes',
    'hoje',
    '7dias',
    '30dias',
    'todos'
];

$tipos_validos = [
    'todos',
    'receita',
    'despesa'
];

if (!in_array($periodo, $periodos_validos, true)) {
    $periodo = 'mes';
}

if (!in_array($tipo_filtro, $tipos_validos, true)) {
    $tipo_filtro = 'todos';
}

/*
|--------------------------------------------------------------------------
| DEFINIR PERÍODO
|--------------------------------------------------------------------------
|
| Usamos PHP para montar as datas.
| Isso facilita usar exatamente o mesmo período
| nos lançamentos manuais e nas parcelas dos orçamentos.
|
|--------------------------------------------------------------------------
*/

$data_inicio = null;
$data_fim = null;

switch ($periodo) {

    case 'hoje':

        $data_inicio = date('Y-m-d');
        $data_fim = date('Y-m-d');

        break;

    case '7dias':

        $data_inicio = date('Y-m-d', strtotime('-6 days'));
        $data_fim = date('Y-m-d');

        break;

    case '30dias':

        $data_inicio = date('Y-m-d', strtotime('-29 days'));
        $data_fim = date('Y-m-d');

        break;

    case 'todos':

        $data_inicio = null;
        $data_fim = null;

        break;

    case 'mes':

    default:

        $data_inicio = date('Y-m-01');
        $data_fim = date('Y-m-t');

        break;
}

/*
|--------------------------------------------------------------------------
| CONDIÇÃO DOS LANÇAMENTOS MANUAIS
|--------------------------------------------------------------------------
*/

$where_manual = [];
$params_manual = [];

if ($data_inicio !== null && $data_fim !== null) {

    $where_manual[] = "data BETWEEN :data_inicio AND :data_fim";

    $params_manual[':data_inicio'] = $data_inicio;
    $params_manual[':data_fim'] = $data_fim;
}

if ($tipo_filtro !== 'todos') {

    $where_manual[] = "tipo = :tipo";

    $params_manual[':tipo'] = $tipo_filtro;
}

$where_manual_sql = '';

if (!empty($where_manual)) {
    $where_manual_sql = 'WHERE ' . implode(' AND ', $where_manual);
}

/*
|--------------------------------------------------------------------------
| BUSCAR LANÇAMENTOS MANUAIS
|--------------------------------------------------------------------------
*/

$stmt_manual = $pdo->prepare("
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
    $where_manual_sql
    ORDER BY data DESC, id DESC
");

$stmt_manual->execute($params_manual);

$lancamentos_manuais = $stmt_manual->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| BUSCAR PARCELAS DE ORÇAMENTOS ACEITOS
|--------------------------------------------------------------------------
|
| Aqui acontece a integração entre:
|
| orcamentos
|       ↓
| parcelas
|       ↓
| financeiro
|
| Somente orçamentos com status "aceito" entram.
|
|--------------------------------------------------------------------------
*/

$where_orcamento = [
    "o.status = 'aceito'"
];

$params_orcamento = [];

/*
|--------------------------------------------------------------------------
| DATA DA MOVIMENTAÇÃO DO ORÇAMENTO
|--------------------------------------------------------------------------
|
| Se a parcela já foi paga:
|   usa data_pagamento
|
| Se ainda não foi paga:
|   usa vencimento
|
|--------------------------------------------------------------------------
*/

$data_movimentacao_sql = "
    CASE
        WHEN p.status = 'paga' AND p.data_pagamento IS NOT NULL
            THEN p.data_pagamento
        ELSE p.vencimento
    END
";

/*
|--------------------------------------------------------------------------
| FILTRO DE DATA DO ORÇAMENTO
|--------------------------------------------------------------------------
*/

if ($data_inicio !== null && $data_fim !== null) {

    $where_orcamento[] = "
        $data_movimentacao_sql BETWEEN :orc_data_inicio AND :orc_data_fim
    ";

    $params_orcamento[':orc_data_inicio'] = $data_inicio;
    $params_orcamento[':orc_data_fim'] = $data_fim;
}

/*
|--------------------------------------------------------------------------
| TIPO
|--------------------------------------------------------------------------
|
| Parcelas de orçamento sempre são RECEITAS.
|
|--------------------------------------------------------------------------
*/

if ($tipo_filtro === 'despesa') {

    /*
     * Se o usuário filtrou somente despesas,
     * não precisamos buscar orçamento.
     */
    $where_orcamento[] = "1 = 0";
}

/*
|--------------------------------------------------------------------------
| SQL DOS ORÇAMENTOS
|--------------------------------------------------------------------------
*/

$where_orcamento_sql = 'WHERE ' . implode(' AND ', $where_orcamento);

$stmt_orcamentos = $pdo->prepare("
    SELECT
        p.id AS parcela_id,
        p.orcamento_id,
        p.numero_parcela,
        p.valor,
        p.vencimento,
        p.status AS status_parcela,
        p.data_pagamento,

        o.id AS numero_orcamento,
        o.status AS status_orcamento,

        pr.paciente

    FROM parcelas p

    INNER JOIN orcamentos o
        ON o.id = p.orcamento_id

    INNER JOIN prontuarios pr
        ON pr.id = o.paciente_id

    $where_orcamento_sql

    ORDER BY
        $data_movimentacao_sql DESC,
        p.id DESC
");

$stmt_orcamentos->execute($params_orcamento);

$parcelas_orcamentos = $stmt_orcamentos->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| TRANSFORMAR PARCELAS EM MOVIMENTAÇÕES FINANCEIRAS
|--------------------------------------------------------------------------
*/

$lancamentos_orcamentos = [];

foreach ($parcelas_orcamentos as $parcela) {

    if ($parcela['status_parcela'] === 'paga') {

        $data_movimentacao = $parcela['data_pagamento'];

        $status_financeiro = 'pago';
    } else {

        $data_movimentacao = $parcela['vencimento'];

        if ($parcela['status_parcela'] === 'atrasada') {
            $status_financeiro = 'atrasada';
        } else {
            $status_financeiro = 'pendente';
        }
    }

    $numero_parcela = (int)$parcela['numero_parcela'];

    $descricao = sprintf(
        'Orçamento #%d - %s - Parcela %dx',
        (int)$parcela['numero_orcamento'],
        $parcela['paciente'],
        $numero_parcela
    );

    $lancamentos_orcamentos[] = [
        'id' => null,

        'tipo' => 'receita',

        'categoria' => 'Orçamento odontológico',

        'descricao' => $descricao,

        'data' => $data_movimentacao,

        'forma_pagamento' => 'Orçamento',

        'valor' => (float)$parcela['valor'],

        'parcelas' => $numero_parcela,

        'status' => $status_financeiro,

        'observacoes' => null,

        'orcamento_id' => (int)$parcela['orcamento_id'],

        'origem' => 'orcamento',

        'parcela_id' => (int)$parcela['parcela_id'],

        'numero_orcamento' => (int)$parcela['numero_orcamento'],

        'paciente' => $parcela['paciente']
    ];
}

/*
|--------------------------------------------------------------------------
| TRANSFORMAR LANÇAMENTOS MANUAIS
|--------------------------------------------------------------------------
*/

$lancamentos = [];

foreach ($lancamentos_manuais as $lancamento) {

    $lancamento['origem'] = 'lancamento';

    $lancamento['parcela_id'] = null;

    $lancamento['numero_orcamento'] = null;

    $lancamento['paciente'] = null;

    $lancamentos[] = $lancamento;
}

/*
|--------------------------------------------------------------------------
| ADICIONAR ORÇAMENTOS AO FINANCEIRO
|--------------------------------------------------------------------------
*/

foreach ($lancamentos_orcamentos as $lancamento_orcamento) {

    $lancamentos[] = $lancamento_orcamento;
}

/*
|--------------------------------------------------------------------------
| ORDENAR MOVIMENTAÇÕES
|--------------------------------------------------------------------------
*/

usort(
    $lancamentos,
    function ($a, $b) {

        $dataA = strtotime($a['data'] ?? '1970-01-01');
        $dataB = strtotime($b['data'] ?? '1970-01-01');

        if ($dataA === $dataB) {

            $idA = (int)($a['id'] ?? 0);
            $idB = (int)($b['id'] ?? 0);

            return $idB <=> $idA;
        }

        return $dataB <=> $dataA;
    }
);

/*
|--------------------------------------------------------------------------
| LIMITAR LANÇAMENTOS RECENTES
|--------------------------------------------------------------------------
*/

$lancamentos_recentes = array_slice($lancamentos, 0, 20);

/*
|--------------------------------------------------------------------------
| RESUMO FINANCEIRO
|--------------------------------------------------------------------------
*/

$receitas = 0;
$despesas = 0;
$a_receber = 0;

foreach ($lancamentos as $lancamento) {

    $valor = (float)$lancamento['valor'];

    $tipo = $lancamento['tipo'];

    $status = $lancamento['status'];

    /*
    |--------------------------------------------------------------------------
    | RECEITAS PAGAS
    |--------------------------------------------------------------------------
    */

    if (
        $tipo === 'receita' &&
        $status === 'pago'
    ) {

        $receitas += $valor;
    }

    /*
    |--------------------------------------------------------------------------
    | DESPESAS PAGAS
    |--------------------------------------------------------------------------
    */

    if (
        $tipo === 'despesa' &&
        $status === 'pago'
    ) {

        $despesas += $valor;
    }

    /*
    |--------------------------------------------------------------------------
    | A RECEBER
    |--------------------------------------------------------------------------
    */

    if (
        $tipo === 'receita' &&
        (
            $status === 'pendente' ||
            $status === 'atrasada'
        )
    ) {

        $a_receber += $valor;
    }
}

$lucro = $receitas - $despesas;

/*
|--------------------------------------------------------------------------
| FLUXO DE CAIXA
|--------------------------------------------------------------------------
|
| Somente valores efetivamente pagos entram no fluxo de caixa.
|
|--------------------------------------------------------------------------
*/

$dados_grafico_array = [];

foreach ($lancamentos as $lancamento) {

    if ($lancamento['status'] !== 'pago') {
        continue;
    }

    if (
        $lancamento['tipo'] !== 'receita' &&
        $lancamento['tipo'] !== 'despesa'
    ) {
        continue;
    }

    $dia = date(
        'Y-m-d',
        strtotime($lancamento['data'])
    );

    if (!isset($dados_grafico_array[$dia])) {

        $dados_grafico_array[$dia] = [
            'dia' => $dia,
            'receitas' => 0,
            'despesas' => 0
        ];
    }

    if ($lancamento['tipo'] === 'receita') {

        $dados_grafico_array[$dia]['receitas'] +=
            (float)$lancamento['valor'];
    } else {

        $dados_grafico_array[$dia]['despesas'] +=
            (float)$lancamento['valor'];
    }
}

ksort($dados_grafico_array);

$dados_grafico = array_values($dados_grafico_array);

/*
|--------------------------------------------------------------------------
| MENSAGENS
|--------------------------------------------------------------------------
*/

$sucesso = isset($_GET['sucesso']) &&
    $_GET['sucesso'] === '1';

/*
|--------------------------------------------------------------------------
| NOMES DOS FILTROS
|--------------------------------------------------------------------------
*/

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

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Financeiro | Dentech</title>

    <link rel="stylesheet" href="css/global.css">
    <link rel="stylesheet" href="css/variables.css">
    <link rel="stylesheet" href="css/layout.css">
    <link rel="stylesheet" href="css/navbar.css">
    <link rel="stylesheet" href="css/financeiro.css">

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <link rel="icon" type="image/png" href="img/icon.PNG">

</head>

<body>

    <?php include 'navbar.php'; ?>

    <div class="content">

        <main class="container">

            <!-- =========================================================
         CABEÇALHO
    ========================================================== -->

            <div class="page-header">

                <div>

                    <div class="breadcrumb">

                        <span>Financeiro</span>

                    </div>

                    <h1>Financeiro</h1>

                    <p>
                        Controle as receitas, despesas e movimentações
                        financeiras da clínica.
                    </p>

                </div>

                <a
                    href="novo_lancamento.php"
                    class="btn-novo">

                    <i class="fa-solid fa-plus"></i>

                    Novo lançamento

                </a>

            </div>


            <!-- =========================================================
         ALERTA
    ========================================================== -->

            <?php if ($sucesso): ?>

                <div class="alert-sucesso">

                    <i class="fa-solid fa-circle-check"></i>

                    Lançamento salvo com sucesso.

                </div>

            <?php endif; ?>


            <!-- =========================================================
         FILTROS
    ========================================================== -->

            <section class="filtros-card">

                <form
                    method="GET"
                    class="filtros-form">

                    <div class="filtro-grupo">

                        <label for="periodo">
                            Período
                        </label>

                        <div class="select-wrapper">

                            <i class="fa-regular fa-calendar"></i>

                            <select
                                id="periodo"
                                name="periodo">

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

                        <label for="tipo">
                            Tipo
                        </label>

                        <div class="select-wrapper">

                            <i class="fa-solid fa-filter"></i>

                            <select
                                id="tipo"
                                name="tipo">

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


                    <button
                        type="submit"
                        class="btn-filtrar">

                        <i class="fa-solid fa-filter"></i>

                        Filtrar

                    </button>


                    <?php if (
                        $periodo !== 'mes' ||
                        $tipo_filtro !== 'todos'
                    ): ?>

                        <a
                            href="financeiro.php"
                            class="btn-limpar">

                            Limpar

                        </a>

                    <?php endif; ?>

                </form>

            </section>


            <!-- =========================================================
         CARDS RESUMO
    ========================================================== -->

            <section class="resumo-grid">


                <!-- RECEITAS -->

                <article class="resumo-card receita">

                    <div class="resumo-icon">

                        <i class="fa-solid fa-arrow-trend-up"></i>

                    </div>

                    <div>

                        <span class="resumo-label">
                            Receitas
                        </span>

                        <strong>
                            <?= moedaBR($receitas) ?>
                        </strong>

                    </div>

                </article>


                <!-- DESPESAS -->

                <article class="resumo-card despesa">

                    <div class="resumo-icon">

                        <i class="fa-solid fa-arrow-trend-down"></i>

                    </div>

                    <div>

                        <span class="resumo-label">
                            Despesas
                        </span>

                        <strong>
                            <?= moedaBR($despesas) ?>
                        </strong>

                    </div>

                </article>


                <!-- LUCRO -->

                <article class="resumo-card lucro">

                    <div class="resumo-icon">

                        <i class="fa-solid fa-dollar-sign"></i>

                    </div>

                    <div>

                        <span class="resumo-label">
                            Lucro
                        </span>

                        <strong>
                            <?= moedaBR($lucro) ?>
                        </strong>

                    </div>

                </article>


                <!-- A RECEBER -->

                <article class="resumo-card receber">

                    <div class="resumo-icon">

                        <i class="fa-regular fa-credit-card"></i>

                    </div>

                    <div>

                        <span class="resumo-label">
                            A receber
                        </span>

                        <strong>
                            <?= moedaBR($a_receber) ?>
                        </strong>

                    </div>

                </article>

            </section>


            <!-- =========================================================
         CONTEÚDO
    ========================================================== -->

            <section class="conteudo-grid">


                <!-- =====================================================
             FLUXO DE CAIXA
        ====================================================== -->

                <div class="card fluxo-card">

                    <div class="card-header">

                        <div>

                            <h2>
                                Fluxo de caixa
                            </h2>

                            <p>
                                Movimentações financeiras do período selecionado.
                            </p>

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

                                foreach ($dados_grafico as $dia) {

                                    $maior_valor = max(
                                        $maior_valor,
                                        (float)$dia['receitas'],
                                        (float)$dia['despesas']
                                    );
                                }

                                $maior_valor =
                                    $maior_valor > 0
                                    ? $maior_valor
                                    : 1;

                                ?>


                                <?php foreach ($dados_grafico as $dia): ?>

                                    <?php

                                    $altura_receita =
                                        ((float)$dia['receitas'] /
                                            $maior_valor) * 100;

                                    $altura_despesa =
                                        ((float)$dia['despesas'] /
                                            $maior_valor) * 100;

                                    ?>

                                    <div
                                        class="barra-coluna"
                                        title="<?= dataBR($dia['dia']) ?>">

                                        <div class="barras">

                                            <div
                                                class="barra barra-receita"
                                                style="
                                            height:
                                            <?= max(2, $altura_receita) ?>%;">
                                            </div>

                                            <div
                                                class="barra barra-despesa"
                                                style="
                                            height:
                                            <?= max(2, $altura_despesa) ?>%;">
                                            </div>

                                        </div>

                                        <span class="barra-label">

                                            <?= date(
                                                'd/m',
                                                strtotime($dia['dia'])
                                            ) ?>

                                        </span>

                                    </div>

                                <?php endforeach; ?>

                            </div>

                        <?php else: ?>

                            <div class="sem-dados">

                                <i class="fa-solid fa-chart-line"></i>

                                <span>
                                    Não existem movimentações
                                    pagas no período selecionado.
                                </span>

                            </div>

                        <?php endif; ?>

                    </div>

                </div>


                <!-- =====================================================
             LANÇAMENTOS RECENTES
        ====================================================== -->

                <div class="card recentes-card">

                    <div class="card-header">

                        <div>

                            <h2>
                                Lançamentos recentes
                            </h2>

                            <p>
                                Últimas movimentações financeiras.
                            </p>

                        </div>

                    </div>


                    <?php if (!empty($lancamentos_recentes)): ?>

                        <div class="lista-lancamentos">

                            <?php foreach ($lancamentos_recentes as $lancamento): ?>

                                <div class="lancamento-item">

                                    <div class="lancamento-data">

                                        <?= date(
                                            'd/m',
                                            strtotime($lancamento['data'])
                                        ) ?>

                                    </div>


                                    <div class="lancamento-info">

                                        <strong>

                                            <?= htmlspecialchars(
                                                $lancamento['descricao']
                                            ) ?>

                                        </strong>

                                        <span>

                                            <?= htmlspecialchars(
                                                $lancamento['categoria']
                                            ) ?>

                                            ·

                                            <?= htmlspecialchars(
                                                $lancamento['forma_pagamento']
                                            ) ?>

                                        </span>

                                    </div>


                                    <div
                                        class="
                                    lancamento-valor
                                    <?= $lancamento['tipo'] === 'receita'
                                        ? 'valor-receita'
                                        : 'valor-despesa' ?>
                                ">

                                        <?= $lancamento['tipo'] === 'receita'
                                            ? '+'
                                            : '-' ?>

                                        <?= moedaBR(
                                            $lancamento['valor']
                                        ) ?>

                                    </div>

                                </div>

                            <?php endforeach; ?>

                        </div>

                    <?php else: ?>

                        <div class="sem-lancamentos">

                            <i class="fa-regular fa-file-lines"></i>

                            <strong>
                                Nenhum lançamento encontrado
                            </strong>

                            <span>
                                Cadastre uma receita ou despesa
                                para começar.
                            </span>

                            <a
                                href="novo_lancamento.php"
                                class="btn-novo-vazio">

                                <i class="fa-solid fa-plus"></i>

                                Novo lançamento

                            </a>

                        </div>

                    <?php endif; ?>

                </div>

            </section>


            <!-- =========================================================
         TABELA DE MOVIMENTAÇÕES
    ========================================================== -->

            <section class="card tabela-card">

                <div class="card-header tabela-header">

                    <div>

                        <h2>
                            Movimentações
                        </h2>

                        <p>
                            Lançamentos e parcelas de orçamentos aceitos.
                        </p>

                    </div>


                    <span class="contador">

                        <?= count($lancamentos) ?>

                        movimentação<?= count($lancamentos) === 1
                                        ? ''
                                        : 's' ?>

                    </span>

                </div>


                <?php if (!empty($lancamentos)): ?>

                    <div class="tabela-scroll">

                        <table>

                            <thead>

                                <tr>

                                    <th>
                                        Data
                                    </th>

                                    <th>
                                        Descrição
                                    </th>

                                    <th>
                                        Categoria
                                    </th>

                                    <th>
                                        Origem
                                    </th>

                                    <th>
                                        Pagamento
                                    </th>

                                    <th>
                                        Status
                                    </th>

                                    <th class="coluna-valor">
                                        Valor
                                    </th>

                                    <th class="coluna-acoes">
                                        Ações
                                    </th>

                                </tr>

                            </thead>


                            <tbody>

                                <?php foreach ($lancamentos as $lancamento): ?>

                                    <tr>

                                        <!-- DATA -->

                                        <td>

                                            <?= dataBR(
                                                $lancamento['data']
                                            ) ?>

                                        </td>


                                        <!-- DESCRIÇÃO -->

                                        <td>

                                            <strong>

                                                <?= htmlspecialchars(
                                                    $lancamento['descricao']
                                                ) ?>

                                            </strong>

                                        </td>


                                        <!-- CATEGORIA -->

                                        <td>

                                            <?= htmlspecialchars(
                                                $lancamento['categoria']
                                            ) ?>

                                        </td>


                                        <!-- ORIGEM -->

                                        <td>

                                            <?php if (
                                                $lancamento['origem'] ===
                                                'orcamento'
                                            ): ?>

                                                <span
                                                    class="origem-orcamento">

                                                    <i
                                                        class="
                                                fa-solid
                                                fa-file-invoice-dollar
                                            ">
                                                    </i>

                                                    Orçamento

                                                </span>

                                            <?php else: ?>

                                                <span
                                                    class="origem-lancamento">

                                                    <i
                                                        class="
                                                fa-solid
                                                fa-money-bill-transfer
                                            ">
                                                    </i>

                                                    Lançamento

                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <!-- PAGAMENTO -->

                                        <td>

                                            <?= htmlspecialchars(
                                                $lancamento['forma_pagamento']
                                            ) ?>

                                        </td>


                                        <!-- STATUS -->

                                        <td>

                                            <span
                                                class="
                                        status-badge
                                        <?= classeStatus(
                                            $lancamento['status']
                                        ) ?>
                                    ">

                                                <?= htmlspecialchars(
                                                    textoStatus(
                                                        $lancamento['status']
                                                    )
                                                ) ?>

                                            </span>

                                        </td>


                                        <!-- VALOR -->

                                        <td
                                            class="
                                    coluna-valor
                                    <?= $lancamento['tipo'] === 'receita'
                                        ? 'valor-receita'
                                        : 'valor-despesa' ?>
                                ">

                                            <?= $lancamento['tipo'] === 'receita'
                                                ? '+'
                                                : '-' ?>

                                            <?= moedaBR(
                                                $lancamento['valor']
                                            ) ?>

                                        </td>


                                        <!-- AÇÕES -->

                                        <td class="coluna-acoes">

                                            <?php if ($lancamento['origem'] === 'orcamento'): ?>

                                                <!-- VISUALIZAR ORÇAMENTO -->

                                                <a
                                                    href="visualizar_orcamento.php?id=<?= (int)$lancamento['orcamento_id'] ?>"
                                                    class="btn-acao"
                                                    title="Visualizar orçamento">

                                                    <i class="fa-regular fa-eye"></i>

                                                </a>


                                                <!-- PAGAR PARCELA -->

                                                <?php if (
                                                    $lancamento['status'] === 'pendente' ||
                                                    $lancamento['status'] === 'atrasada'
                                                ): ?>

                                                    <form
                                                        method="POST"
                                                        action="pagar_parcela.php"
                                                        class="form-pagar-parcela"
                                                        onsubmit="return confirm('Confirmar o pagamento desta parcela?');">

                                                        <?= csrf_field() ?>

                                                        <input
                                                            type="hidden"
                                                            name="parcela_id"
                                                            value="<?= (int)$lancamento['parcela_id'] ?>">

                                                        <button
                                                            type="submit"
                                                            class="btn-acao btn-pagar-financeiro"
                                                            title="Pagar parcela">

                                                            <i class="fa-solid fa-check"></i>

                                                        </button>

                                                    </form>

                                                <?php endif; ?>


                                            <?php else: ?>

                                                <!-- LANÇAMENTO MANUAL -->

                                                <?php if (!empty($lancamento['id'])): ?>

                                                    <a
                                                        href="visualizar_lancamento.php?id=<?= (int)$lancamento['id'] ?>"
                                                        class="btn-acao"
                                                        title="Visualizar">

                                                        <i class="fa-regular fa-eye"></i>

                                                    </a>


                                                    <a
                                                        href="editar_lancamento.php?id=<?= (int)$lancamento['id'] ?>"
                                                        class="btn-acao"
                                                        title="Editar">

                                                        <i class="fa-solid fa-pen"></i>

                                                    </a>

                                                <?php endif; ?>

                                            <?php endif; ?>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php else: ?>

                    <div class="tabela-vazia">

                        <i class="fa-solid fa-wallet"></i>

                        <h3>
                            Nenhuma movimentação cadastrada
                        </h3>

                        <p>
                            Comece cadastrando sua primeira
                            receita ou despesa.
                        </p>

                        <a
                            href="novo_lancamento.php"
                            class="btn-novo">

                            <i class="fa-solid fa-plus"></i>

                            Novo lançamento

                        </a>

                    </div>

                <?php endif; ?>

            </section>



        </main>

    </div>

</body>

</html>