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
    return 'R$ ' . number_format(
        (float)$valor,
        2,
        ',',
        '.'
    );
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
    return match ($status) {
        'pago' => 'status-pago',
        'pendente' => 'status-pendente',
        'atrasada' => 'status-atrasada',
        default => 'status-outro'
    };
}

function textoStatus($status): string
{
    return match ($status) {
        'pago' => 'Pago',
        'pendente' => 'Pendente',
        'atrasada' => 'Atrasada',
        default => ucfirst((string)$status)
    };
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
*/

$data_inicio = null;
$data_fim = null;

switch ($periodo) {

    case 'hoje':

        $data_inicio = date('Y-m-d');
        $data_fim = date('Y-m-d');

        break;


    case '7dias':

        $data_inicio = date(
            'Y-m-d',
            strtotime('-6 days')
        );

        $data_fim = date('Y-m-d');

        break;


    case '30dias':

        $data_inicio = date(
            'Y-m-d',
            strtotime('-29 days')
        );

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
| ATUALIZAR PARCELAS ATRASADAS
|--------------------------------------------------------------------------
|
| Somente parcelas de orçamentos aceitos entram no financeiro.
|
*/

$pdo->exec("
    UPDATE parcelas p

    INNER JOIN orcamentos o
        ON o.id = p.orcamento_id

    SET p.status = 'atrasada'

    WHERE o.status = 'aceito'
      AND p.status = 'pendente'
      AND p.vencimento < CURDATE()
");


/*
|--------------------------------------------------------------------------
| LANÇAMENTOS MANUAIS
|--------------------------------------------------------------------------
*/

$where_manual = [];
$params_manual = [];


/*
|--------------------------------------------------------------------------
| FILTRO DE DATA DOS LANÇAMENTOS MANUAIS
|--------------------------------------------------------------------------
*/

if ($data_inicio !== null) {

    $where_manual[] =
        "DATE(data) BETWEEN :data_inicio AND :data_fim";

    $params_manual[':data_inicio'] =
        $data_inicio;

    $params_manual[':data_fim'] =
        $data_fim;
}


/*
|--------------------------------------------------------------------------
| FILTRO DE TIPO
|--------------------------------------------------------------------------
*/

if ($tipo_filtro !== 'todos') {

    $where_manual[] =
        "tipo = :tipo";

    $params_manual[':tipo'] =
        $tipo_filtro;
}


$where_manual_sql = '';

if (!empty($where_manual)) {

    $where_manual_sql =
        'WHERE ' .
        implode(' AND ', $where_manual);
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

    ORDER BY
        data DESC,
        id DESC
");

$stmt_manual->execute(
    $params_manual
);

$lancamentos_manuais =
    $stmt_manual->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| BUSCAR PARCELAS DOS ORÇAMENTOS
|--------------------------------------------------------------------------
*/

$where_parcelas = [
    "o.status = 'aceito'"
];

$params_parcelas = [];


/*
|--------------------------------------------------------------------------
| FILTRO DE DATA DAS PARCELAS
|--------------------------------------------------------------------------
|
| Se estiver paga:
|   usa data_pagamento
|
| Se estiver pendente/atrasada:
|   usa vencimento
|
*/

if ($data_inicio !== null) {

    $where_parcelas[] = "
        (
            CASE
                WHEN p.status = 'paga'
                     AND p.data_pagamento IS NOT NULL
                THEN DATE(p.data_pagamento)

                ELSE DATE(p.vencimento)
            END
        ) BETWEEN :data_inicio_parcela
          AND :data_fim_parcela
    ";

    $params_parcelas[':data_inicio_parcela'] =
        $data_inicio;

    $params_parcelas[':data_fim_parcela'] =
        $data_fim;
}


/*
|--------------------------------------------------------------------------
| MONTAR WHERE
|--------------------------------------------------------------------------
*/

$where_parcelas_sql =
    'WHERE ' .
    implode(' AND ', $where_parcelas);


/*
|--------------------------------------------------------------------------
| BUSCAR PARCELAS
|--------------------------------------------------------------------------
*/

$sql_parcelas = "

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

    $where_parcelas_sql

    ORDER BY

        CASE
            WHEN p.status = 'atrasada' THEN 1
            WHEN p.status = 'pendente' THEN 2
            WHEN p.status = 'paga' THEN 3
            ELSE 4
        END,

        p.vencimento ASC
";


$stmt_parcelas =
    $pdo->prepare($sql_parcelas);

$stmt_parcelas->execute(
    $params_parcelas
);

$parcelas_orcamentos =
    $stmt_parcelas->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| CONVERTER PARCELAS PARA O FORMATO DO FINANCEIRO
|--------------------------------------------------------------------------
*/

$lancamentos_orcamentos = [];

foreach ($parcelas_orcamentos as $parcela) {

    /*
    |--------------------------------------------------------------------------
    | STATUS
    |--------------------------------------------------------------------------
    */

    if ($parcela['status_parcela'] === 'paga') {

        $data_movimentacao =
            $parcela['data_pagamento'];

        $status_financeiro =
            'pago';
    } elseif (
        $parcela['status_parcela'] === 'atrasada'
    ) {

        $data_movimentacao =
            $parcela['vencimento'];

        $status_financeiro =
            'atrasada';
    } else {

        $data_movimentacao =
            $parcela['vencimento'];

        $status_financeiro =
            'pendente';
    }


    /*
    |--------------------------------------------------------------------------
    | DESCRIÇÃO
    |--------------------------------------------------------------------------
    */

    $descricao = sprintf(
        'Orçamento #%d - %s - Parcela %d',
        (int)$parcela['numero_orcamento'],
        $parcela['paciente'],
        (int)$parcela['numero_parcela']
    );


    /*
    |--------------------------------------------------------------------------
    | CRIAR LANÇAMENTO VIRTUAL
    |--------------------------------------------------------------------------
    |
    | Não gravamos isso em lancamentos_financeiros.
    |
    */

    $lancamentos_orcamentos[] = [

        'id' => null,

        'tipo' => 'receita',

        'categoria' =>
        'Orçamento odontológico',

        'descricao' =>
        $descricao,

        'data' =>
        $data_movimentacao,

        'forma_pagamento' =>
        'Orçamento',

        'valor' =>
        (float)$parcela['valor'],

        'parcelas' =>
        (int)$parcela['numero_parcela'],

        'status' =>
        $status_financeiro,

        'observacoes' =>
        null,

        'orcamento_id' =>
        (int)$parcela['orcamento_id'],

        'origem' =>
        'orcamento',

        'parcela_id' =>
        (int)$parcela['parcela_id'],

        'numero_orcamento' =>
        (int)$parcela['numero_orcamento'],

        'paciente' =>
        $parcela['paciente']
    ];
}


/*
|--------------------------------------------------------------------------
| UNIFICAR LANÇAMENTOS MANUAIS
|--------------------------------------------------------------------------
*/

$lancamentos = [];

foreach ($lancamentos_manuais as $lancamento) {

    $lancamento['origem'] =
        'lancamento';

    $lancamento['parcela_id'] =
        null;

    $lancamento['numero_orcamento'] =
        null;

    $lancamento['paciente'] =
        null;

    $lancamentos[] =
        $lancamento;
}


/*
|--------------------------------------------------------------------------
| ADICIONAR PARCELAS
|--------------------------------------------------------------------------
*/

if (
    $tipo_filtro === 'todos' ||
    $tipo_filtro === 'receita'
) {

    foreach (
        $lancamentos_orcamentos
        as $lancamento_orcamento
    ) {

        $lancamentos[] =
            $lancamento_orcamento;
    }
}


/*
|--------------------------------------------------------------------------
| ORDENAR POR DATA
|--------------------------------------------------------------------------
*/

usort(
    $lancamentos,
    function ($a, $b) {

        $dataA =
            strtotime(
                $a['data'] ?? '1970-01-01'
            );

        $dataB =
            strtotime(
                $b['data'] ?? '1970-01-01'
            );


        if ($dataA === $dataB) {

            return ((int)($b['id'] ?? 0))
                <=>
                ((int)($a['id'] ?? 0));
        }


        return $dataB <=> $dataA;
    }
);


/*
|--------------------------------------------------------------------------
| LIMITAR LANÇAMENTOS EXIBIDOS
|--------------------------------------------------------------------------
*/

$lancamentos =
    array_slice(
        $lancamentos,
        0,
        20
    );


/*
|--------------------------------------------------------------------------
| RESUMO FINANCEIRO
|--------------------------------------------------------------------------
*/

$receitas = 0;
$despesas = 0;
$a_receber = 0;


foreach ($lancamentos as $lancamento) {

    $valor =
        (float)$lancamento['valor'];


    /*
    |--------------------------------------------------------------------------
    | RECEITAS
    |--------------------------------------------------------------------------
    */

    if ($lancamento['tipo'] === 'receita') {

        if (
            $lancamento['status'] === 'pago'
        ) {

            $receitas += $valor;
        } else {

            $a_receber += $valor;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | DESPESAS
    |--------------------------------------------------------------------------
    */

    if ($lancamento['tipo'] === 'despesa') {

        if (
            $lancamento['status'] === 'pago'
        ) {

            $despesas += $valor;
        }
    }
}


/*
|--------------------------------------------------------------------------
| LUCRO
|--------------------------------------------------------------------------
*/

$lucro =
    $receitas - $despesas;


/*
|--------------------------------------------------------------------------
| DADOS PARA O GRÁFICO
|--------------------------------------------------------------------------
|
| O gráfico usa os lançamentos já unificados.
|
*/

$dados_grafico = [];

foreach ($lancamentos as $lancamento) {

    /*
    |--------------------------------------------------------------------------
    | Somente movimentações efetivamente recebidas/pagas
    |--------------------------------------------------------------------------
    */

    if (
        $lancamento['status'] !== 'pago'
    ) {
        continue;
    }


    $dia =
        date(
            'Y-m-d',
            strtotime($lancamento['data'])
        );


    if (!isset($dados_grafico[$dia])) {

        $dados_grafico[$dia] = [

            'dia' =>
            $dia,

            'receitas' =>
            0,

            'despesas' =>
            0
        ];
    }


    if (
        $lancamento['tipo'] === 'receita'
    ) {

        $dados_grafico[$dia]['receitas']
            += (float)$lancamento['valor'];
    } elseif (
        $lancamento['tipo'] === 'despesa'
    ) {

        $dados_grafico[$dia]['despesas']
            += (float)$lancamento['valor'];
    }
}


/*
|--------------------------------------------------------------------------
| ORDENAR GRÁFICO
|--------------------------------------------------------------------------
*/

ksort($dados_grafico);

$dados_grafico =
    array_values($dados_grafico);


/*
|--------------------------------------------------------------------------
| PEGAR ÚLTIMOS 30 DIAS
|--------------------------------------------------------------------------
*/

if (count($dados_grafico) > 30) {

    $dados_grafico =
        array_slice(
            $dados_grafico,
            -30
        );
}


/*
|--------------------------------------------------------------------------
| MENSAGEM DE SUCESSO
|--------------------------------------------------------------------------
*/

$sucesso =
    $_GET['sucesso'] ?? null;


/*
|--------------------------------------------------------------------------
| NOMES DOS FILTROS
|--------------------------------------------------------------------------
*/

$periodos_nomes = [

    'mes' =>
    'Este mês',

    'hoje' =>
    'Hoje',

    '7dias' =>
    'Últimos 7 dias',

    '30dias' =>
    'Últimos 30 dias',

    'todos' =>
    'Todo o período'
];


$tipos_nomes = [

    'todos' =>
    'Todos os tipos',

    'receita' =>
    'Receitas',

    'despesa' =>
    'Despesas'
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

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="css/global.css">
    <link rel="stylesheet" href="css/variables.css">
    <link rel="stylesheet" href="css/layout.css">
    <link rel="stylesheet" href="css/financeiro.css">
    <link rel="stylesheet" href="css/navbar.css">
    <link rel="icon" type="image/png" href="img/icon.PNG">

</head>

<body>

    <?php include 'navbar.php'; ?>

    <div class="content">

        <main class="container">

            <!-- ==================================================
             CABEÇALHO
        =================================================== -->

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


            <!-- ==================================================
             MENSAGEM DE SUCESSO
        =================================================== -->

            <?php if ($sucesso): ?>

                <div class="alert-sucesso">

                    <i class="fa-solid fa-circle-check"></i>

                    <?php if ($sucesso === 'pagamento'): ?>

                        Pagamento da parcela registrado com sucesso.

                    <?php else: ?>

                        Lançamento salvo com sucesso.

                    <?php endif; ?>

                </div>

            <?php endif; ?>


            <!-- ==================================================
             FILTROS
        =================================================== -->

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

                                <?php foreach (
                                    $periodos_nomes
                                    as $valor => $nome
                                ): ?>

                                    <option
                                        value="<?= htmlspecialchars($valor) ?>"
                                        <?= $periodo === $valor
                                            ? 'selected'
                                            : '' ?>>

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

                                <?php foreach (
                                    $tipos_nomes
                                    as $valor => $nome
                                ): ?>

                                    <option
                                        value="<?= htmlspecialchars($valor) ?>"
                                        <?= $tipo_filtro === $valor
                                            ? 'selected'
                                            : '' ?>>

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


            <!-- ==================================================
             RESUMO
        =================================================== -->

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


            <!-- ==================================================
             CONTEÚDO PRINCIPAL
        =================================================== -->

            <section class="conteudo-grid">


                <!-- ==================================================
                 FLUXO DE CAIXA
            =================================================== -->

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

                                foreach (
                                    $dados_grafico
                                    as $dia
                                ) {

                                    $maior_valor =
                                        max(
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


                                <?php foreach (
                                    $dados_grafico
                                    as $dia
                                ): ?>

                                    <?php

                                    $altura_receita =
                                        (
                                            (float)$dia['receitas']
                                            /
                                            $maior_valor
                                        ) * 100;


                                    $altura_despesa =
                                        (
                                            (float)$dia['despesas']
                                            /
                                            $maior_valor
                                        ) * 100;

                                    ?>


                                    <div
                                        class="barra-coluna"
                                        title="<?= dataBR($dia['dia']) ?>">

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
                                    Não existem movimentações no período selecionado.
                                </span>

                            </div>

                        <?php endif; ?>

                    </div>

                </div>


                <!-- ==================================================
                 LANÇAMENTOS RECENTES
            =================================================== -->

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


                    <?php if (!empty($lancamentos)): ?>

                        <div class="lista-lancamentos">

                            <?php foreach (
                                $lancamentos
                                as $lancamento
                            ): ?>

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
                                        class="lancamento-valor <?= $lancamento['tipo'] === 'receita'
                                                                    ? 'valor-receita'
                                                                    : 'valor-despesa' ?>">

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
                                Cadastre uma receita ou despesa para começar.
                            </span>


                            <a
                                href="novo_lancamento.php"
                                class="btn-novo-vazio">

                                <i class="fa-solid fa-plus"></i>

                                Novo lançamento

                            </a>

                        </div>

                    <?php endif; ?>


                    <a
                        href="financeiro_lancamentos.php"
                        class="ver-todos">

                        Ver todos

                        <i class="fa-solid fa-arrow-right"></i>

                    </a>

                </div>

            </section>


            <!-- ==================================================
             TABELA DE MOVIMENTAÇÕES
        =================================================== -->

            <section class="card tabela-card">

                <div class="card-header tabela-header">

                    <div>

                        <h2>
                            Movimentações
                        </h2>

                        <p>
                            Últimos lançamentos encontrados.
                        </p>

                    </div>


                    <span class="contador">

                        <?= count($lancamentos) ?>

                        lançamento<?= count($lancamentos) === 1
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
                                        Pagamento
                                    </th>

                                    <th>
                                        Parcelas
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

                                <?php foreach (
                                    $lancamentos
                                    as $lancamento
                                ): ?>

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


                                        <!-- PAGAMENTO -->

                                        <td>

                                            <?= htmlspecialchars(
                                                $lancamento['forma_pagamento']
                                            ) ?>

                                        </td>


                                        <!-- PARCELAS -->

                                        <td>

                                            <?php if (
                                                $lancamento['origem']
                                                === 'orcamento'
                                            ): ?>

                                                Parcela
                                                <?= (int)$lancamento['parcelas'] ?>

                                            <?php else: ?>

                                                <?= (int)$lancamento['parcelas'] === 1
                                                    ? 'À vista'
                                                    : (int)$lancamento['parcelas'] . 'x' ?>

                                            <?php endif; ?>

                                        </td>


                                        <!-- STATUS -->

                                        <td>

                                            <span
                                                class="status-badge <?= classeStatus(
                                                                        $lancamento['status']
                                                                    ) ?>">

                                                <?= htmlspecialchars(
                                                    textoStatus(
                                                        $lancamento['status']
                                                    )
                                                ) ?>

                                            </span>

                                        </td>


                                        <!-- VALOR -->

                                        <td
                                            class="coluna-valor <?= $lancamento['tipo'] === 'receita'
                                                                    ? 'valor-receita'
                                                                    : 'valor-despesa' ?>">

                                            <?= $lancamento['tipo'] === 'receita'
                                                ? '+'
                                                : '-' ?>

                                            <?= moedaBR(
                                                $lancamento['valor']
                                            ) ?>

                                        </td>


                                        <!-- AÇÕES -->

                                        <td class="coluna-acoes">


                                            <?php if (
                                                $lancamento['origem']
                                                === 'orcamento'
                                            ): ?>


                                                <!-- VISUALIZAR ORÇAMENTO -->

                                                <a
                                                    href="visualizar_orcamento.php?id=<?= (int)$lancamento['orcamento_id'] ?>"
                                                    class="btn-acao"
                                                    title="Visualizar orçamento">

                                                    <i class="fa-regular fa-eye"></i>

                                                </a>


                                                <!-- PAGAR PARCELA -->

                                                <?php if (
                                                    $lancamento['status']
                                                    === 'pendente'
                                                    ||
                                                    $lancamento['status']
                                                    === 'atrasada'
                                                ): ?>

                                                    <form
                                                        method="POST"
                                                        action="pagar_parcela.php"
                                                        class="form-pagar-parcela"
                                                        onsubmit="return confirm('Confirmar o pagamento desta parcela?');">


                                                        <?php if (
                                                            function_exists('csrf_field')
                                                        ): ?>

                                                            <?= csrf_field() ?>

                                                        <?php else: ?>

                                                            <input
                                                                type="hidden"
                                                                name="csrf_token"
                                                                value="<?= htmlspecialchars(
                                                                            $_SESSION['csrf_token'] ?? ''
                                                                        ) ?>">

                                                        <?php endif; ?>


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


                                                <!-- VISUALIZAR LANÇAMENTO MANUAL -->

                                                <a
                                                    href="visualizar_lancamento.php?id=<?= (int)$lancamento['id'] ?>"
                                                    class="btn-acao"
                                                    title="Visualizar">

                                                    <i class="fa-regular fa-eye"></i>

                                                </a>


                                                <!-- EDITAR LANÇAMENTO MANUAL -->

                                                <a
                                                    href="editar_lancamento.php?id=<?= (int)$lancamento['id'] ?>"
                                                    class="btn-acao"
                                                    title="Editar">

                                                    <i class="fa-solid fa-pen"></i>

                                                </a>


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
                            Nenhum lançamento cadastrado
                        </h3>

                        <p>
                            Comece cadastrando sua primeira receita ou despesa.
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