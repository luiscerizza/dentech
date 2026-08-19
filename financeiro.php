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
        'pago',
        'paga'
        => 'status-pago',

        'pendente'
        => 'status-pendente',

        'atrasada'
        => 'status-atrasada',

        default
        => 'status-outro'
    };
}

function textoStatus($status): string
{
    return match ($status) {
        'pago',
        'paga'
        => 'Pago',

        'pendente'
        => 'Pendente',

        'atrasada'
        => 'Atrasada',

        default
        => ucfirst((string)$status)
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
| PERÍODO
|--------------------------------------------------------------------------
*/

$where = [];
$params = [];

switch ($periodo) {

    case 'hoje':

        $where[] = "DATE(data) = CURDATE()";

        break;


    case '7dias':

        $where[] =
            "data >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)";

        $where[] =
            "data <= CURDATE()";

        break;


    case '30dias':

        $where[] =
            "data >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)";

        $where[] =
            "data <= CURDATE()";

        break;


    case 'todos':

        break;


    case 'mes':
    default:

        $where[] =
            "YEAR(data) = YEAR(CURDATE())";

        $where[] =
            "MONTH(data) = MONTH(CURDATE())";

        break;
}


/*
|--------------------------------------------------------------------------
| FILTRO DE TIPO PARA LANÇAMENTOS MANUAIS
|--------------------------------------------------------------------------
*/

if ($tipo_filtro !== 'todos') {

    $where[] =
        "tipo = :tipo";

    $params[':tipo'] =
        $tipo_filtro;
}


$where_sql =
    $where
    ? 'WHERE ' . implode(' AND ', $where)
    : '';


/*
|--------------------------------------------------------------------------
| ATUALIZAR PARCELAS ATRASADAS
|--------------------------------------------------------------------------
*/

$pdo->exec("
    UPDATE parcelas p

    INNER JOIN orcamentos o
        ON o.id = p.orcamento_id

    SET p.status = 'atrasada'

    WHERE o.status IN ('aceito', 'confirmado')
      AND p.status = 'pendente'
      AND p.vencimento < CURDATE()
");


/*
|--------------------------------------------------------------------------
| RESUMO DOS LANÇAMENTOS MANUAIS
|--------------------------------------------------------------------------
|
| Receitas:
|   somente lançamentos pagos.
|
| Despesas:
|   somente despesas pagas.
|
| A receber:
|   lançamentos manuais pendentes.
|
|--------------------------------------------------------------------------
*/

$stmt_resumo_manual = $pdo->prepare("
    SELECT

        COALESCE(
            SUM(
                CASE
                    WHEN tipo = 'receita'
                     AND status = 'pago'
                    THEN valor
                    ELSE 0
                END
            ),
            0
        ) AS receitas,

        COALESCE(
            SUM(
                CASE
                    WHEN tipo = 'despesa'
                     AND status = 'pago'
                    THEN valor
                    ELSE 0
                END
            ),
            0
        ) AS despesas,

        COALESCE(
            SUM(
                CASE
                    WHEN tipo = 'receita'
                     AND status = 'pendente'
                    THEN valor
                    ELSE 0
                END
            ),
            0
        ) AS a_receber

    FROM lancamentos_financeiros

    $where_sql
");

$stmt_resumo_manual->execute($params);

$resumo_manual =
    $stmt_resumo_manual->fetch(PDO::FETCH_ASSOC)
    ?: [
        'receitas' => 0,
        'despesas' => 0,
        'a_receber' => 0
    ];


/*
|--------------------------------------------------------------------------
| VALORES MANUAIS
|--------------------------------------------------------------------------
*/

$receitas =
    (float)$resumo_manual['receitas'];

$despesas =
    (float)$resumo_manual['despesas'];

$a_receber =
    (float)$resumo_manual['a_receber'];


/*
|--------------------------------------------------------------------------
| A RECEBER DOS ORÇAMENTOS
|--------------------------------------------------------------------------
|
| IMPORTANTE:
|
| Não aplicamos o filtro de período aqui.
|
| O card "A receber" representa o total que a clínica
| ainda tem para receber de todos os orçamentos aceitos.
|
| Portanto, uma parcela que vence mês que vem continua
| aparecendo no A receber.
|
|--------------------------------------------------------------------------
*/

$stmt_a_receber_orcamentos = $pdo->query("
    SELECT
        COALESCE(SUM(p.valor), 0)

    FROM parcelas p

    INNER JOIN orcamentos o
        ON o.id = p.orcamento_id

    WHERE o.status IN ('aceito', 'confirmado')

      AND p.status IN (
          'pendente',
          'atrasada'
      )
");

$a_receber_orcamentos =
    (float)$stmt_a_receber_orcamentos->fetchColumn();


/*
|--------------------------------------------------------------------------
| SOMAR ORÇAMENTOS AO A RECEBER
|--------------------------------------------------------------------------
*/

$a_receber +=
    $a_receber_orcamentos;


/*
|--------------------------------------------------------------------------
| RECEITAS JÁ PAGAS DOS ORÇAMENTOS
|--------------------------------------------------------------------------
*/

$stmt_receitas_orcamentos = $pdo->query("
    SELECT
        COALESCE(SUM(p.valor), 0)

    FROM parcelas p

    INNER JOIN orcamentos o
        ON o.id = p.orcamento_id

    WHERE o.status IN ('aceito', 'confirmado')

      AND p.status = 'paga'
");

$receitas_orcamentos =
    (float)$stmt_receitas_orcamentos->fetchColumn();


/*
|--------------------------------------------------------------------------
| SOMAR RECEITAS DOS ORÇAMENTOS
|--------------------------------------------------------------------------
*/

$receitas +=
    $receitas_orcamentos;


/*
|--------------------------------------------------------------------------
| LUCRO
|--------------------------------------------------------------------------
*/

$lucro =
    $receitas - $despesas;


/*
|--------------------------------------------------------------------------
| LANÇAMENTOS MANUAIS RECENTES
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

    ORDER BY
        data DESC,
        id DESC

    LIMIT 20
");

$stmt_lancamentos->execute($params);

$lancamentos_manuais =
    $stmt_lancamentos->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| PARCELAS DOS ORÇAMENTOS PARA A TABELA
|--------------------------------------------------------------------------
|
| Aqui SIM usamos o período selecionado.
|
| Assim o filtro controla quais movimentações aparecem
| na lista, mas não interfere no total de "A receber".
|
|--------------------------------------------------------------------------
*/

$where_parcelas = [
    "o.status IN ('aceito', 'confirmado')"
];

$params_parcelas = [];


/*
|--------------------------------------------------------------------------
| FILTRO DE PERÍODO DAS PARCELAS
|--------------------------------------------------------------------------
*/

if ($periodo === 'hoje') {

    $where_parcelas[] = "
        DATE(
            CASE
                WHEN p.status = 'paga'
                     AND p.data_pagamento IS NOT NULL
                THEN p.data_pagamento
                ELSE p.vencimento
            END
        ) = CURDATE()
    ";
} elseif ($periodo === '7dias') {

    $where_parcelas[] = "
        DATE(
            CASE
                WHEN p.status = 'paga'
                     AND p.data_pagamento IS NOT NULL
                THEN p.data_pagamento
                ELSE p.vencimento
            END
        )
        BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        AND CURDATE()
    ";
} elseif ($periodo === '30dias') {

    $where_parcelas[] = "
        DATE(
            CASE
                WHEN p.status = 'paga'
                     AND p.data_pagamento IS NOT NULL
                THEN p.data_pagamento
                ELSE p.vencimento
            END
        )
        BETWEEN DATE_SUB(CURDATE(), INTERVAL 29 DAY)
        AND CURDATE()
    ";
} elseif ($periodo === 'mes') {

    $where_parcelas[] = "
        YEAR(
            CASE
                WHEN p.status = 'paga'
                     AND p.data_pagamento IS NOT NULL
                THEN p.data_pagamento
                ELSE p.vencimento
            END
        ) = YEAR(CURDATE())
    ";

    $where_parcelas[] = "
        MONTH(
            CASE
                WHEN p.status = 'paga'
                     AND p.data_pagamento IS NOT NULL
                THEN p.data_pagamento
                ELSE p.vencimento
            END
        ) = MONTH(CURDATE())
    ";
}


/*
|--------------------------------------------------------------------------
| BUSCAR PARCELAS
|--------------------------------------------------------------------------
*/

$where_parcelas_sql =
    'WHERE ' . implode(
        ' AND ',
        $where_parcelas
    );


$stmt_parcelas = $pdo->prepare("

    SELECT

        p.id AS parcela_id,

        p.orcamento_id,

        p.numero_parcela,

        p.valor,

        p.vencimento,

        p.status AS status_parcela,

        p.data_pagamento,

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

    LIMIT 20

");

$stmt_parcelas->execute(
    $params_parcelas
);

$parcelas_orcamentos =
    $stmt_parcelas->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| CONVERTER LANÇAMENTOS MANUAIS
|--------------------------------------------------------------------------
*/

$lancamentos = [];

foreach (
    $lancamentos_manuais
    as $lancamento
) {

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
| ADICIONAR PARCELAS DOS ORÇAMENTOS
|--------------------------------------------------------------------------
*/

if (
    $tipo_filtro === 'todos'
    ||
    $tipo_filtro === 'receita'
) {

    foreach (
        $parcelas_orcamentos
        as $parcela
    ) {

        /*
        |--------------------------------------------------------------------------
        | DATA DA MOVIMENTAÇÃO
        |--------------------------------------------------------------------------
        */

        if (
            $parcela['status_parcela'] === 'paga'
            &&
            !empty($parcela['data_pagamento'])
        ) {

            $data =
                $parcela['data_pagamento'];

            $status =
                'paga';
        } elseif (
            $parcela['status_parcela'] === 'atrasada'
        ) {

            $data =
                $parcela['vencimento'];

            $status =
                'atrasada';
        } else {

            $data =
                $parcela['vencimento'];

            $status =
                'pendente';
        }


        /*
        |--------------------------------------------------------------------------
        | DESCRIÇÃO
        |--------------------------------------------------------------------------
        */

        $descricao =
            'Orçamento #' .
            (int)$parcela['orcamento_id'] .
            ' - ' .
            $parcela['paciente'] .
            ' - Parcela ' .
            (int)$parcela['numero_parcela'];


        /*
        |--------------------------------------------------------------------------
        | LANÇAMENTO VIRTUAL
        |--------------------------------------------------------------------------
        */

        $lancamentos[] = [

            'id' =>
            null,

            'tipo' =>
            'receita',

            'categoria' =>
            'Orçamento',

            'descricao' =>
            $descricao,

            'data' =>
            $data,

            'forma_pagamento' =>
            'Orçamento',

            'valor' =>
            (float)$parcela['valor'],

            'parcelas' =>
            (int)$parcela['numero_parcela'],

            'status' =>
            $status,

            'observacoes' =>
            null,

            'orcamento_id' =>
            (int)$parcela['orcamento_id'],

            'origem' =>
            'orcamento',

            'parcela_id' =>
            (int)$parcela['parcela_id'],

            'numero_orcamento' =>
            (int)$parcela['orcamento_id'],

            'paciente' =>
            $parcela['paciente']
        ];
    }
}


/*
|--------------------------------------------------------------------------
| ORDENAR
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

        return $dataB <=> $dataA;
    }
);


/*
|--------------------------------------------------------------------------
| GRÁFICO
|--------------------------------------------------------------------------
|
| O gráfico considera:
|
| - lançamentos manuais pagos;
| - parcelas de orçamento pagas.
|
|--------------------------------------------------------------------------
*/

$dados_grafico = [];


/*
|--------------------------------------------------------------------------
| GRÁFICO DOS LANÇAMENTOS MANUAIS
|--------------------------------------------------------------------------
*/

$where_grafico = [];
$params_grafico = [];


if ($periodo === 'mes') {

    $where_grafico[] =
        "YEAR(data) = YEAR(CURDATE())";

    $where_grafico[] =
        "MONTH(data) = MONTH(CURDATE())";
} elseif ($periodo === 'hoje') {

    $where_grafico[] =
        "DATE(data) = CURDATE()";
} elseif ($periodo === '7dias') {

    $where_grafico[] =
        "data >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)";

    $where_grafico[] =
        "data <= CURDATE()";
} elseif ($periodo === '30dias') {

    $where_grafico[] =
        "data >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)";

    $where_grafico[] =
        "data <= CURDATE()";
} else {

    $where_grafico[] =
        "data >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)";

    $where_grafico[] =
        "data <= CURDATE()";
}


if ($tipo_filtro !== 'todos') {

    $where_grafico[] =
        "tipo = :tipo_grafico";

    $params_grafico[':tipo_grafico'] =
        $tipo_filtro;
}


$where_grafico_sql =
    'WHERE ' .
    implode(
        ' AND ',
        $where_grafico
    );


$stmt_grafico =
    $pdo->prepare("

        SELECT

            DATE(data) AS dia,

            COALESCE(
                SUM(
                    CASE
                        WHEN tipo = 'receita'
                             AND status = 'pago'
                        THEN valor
                        ELSE 0
                    END
                ),
                0
            ) AS receitas,

            COALESCE(
                SUM(
                    CASE
                        WHEN tipo = 'despesa'
                             AND status = 'pago'
                        THEN valor
                        ELSE 0
                    END
                ),
                0
            ) AS despesas

        FROM lancamentos_financeiros

        $where_grafico_sql

        GROUP BY DATE(data)

        ORDER BY DATE(data) ASC

    ");


$stmt_grafico->execute(
    $params_grafico
);

$dados_grafico_manual =
    $stmt_grafico->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| INSERIR DADOS MANUAIS NO GRÁFICO
|--------------------------------------------------------------------------
*/

foreach (
    $dados_grafico_manual
    as $dia
) {

    $data =
        $dia['dia'];

    $dados_grafico[$data] = [

        'dia' =>
        $data,

        'receitas' =>
        (float)$dia['receitas'],

        'despesas' =>
        (float)$dia['despesas']
    ];
}


/*
|--------------------------------------------------------------------------
| GRÁFICO DAS PARCELAS PAGAS
|--------------------------------------------------------------------------
*/

$where_grafico_parcelas = [
    "o.status IN ('aceito', 'confirmado')",
    "p.status = 'paga'"
];

$params_grafico_parcelas = [];


if ($periodo === 'mes') {

    $where_grafico_parcelas[] = "
        YEAR(p.data_pagamento) = YEAR(CURDATE())
    ";

    $where_grafico_parcelas[] = "
        MONTH(p.data_pagamento) = MONTH(CURDATE())
    ";
} elseif ($periodo === 'hoje') {

    $where_grafico_parcelas[] = "
        DATE(p.data_pagamento) = CURDATE()
    ";
} elseif ($periodo === '7dias') {

    $where_grafico_parcelas[] = "
        p.data_pagamento >=
        DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    ";

    $where_grafico_parcelas[] = "
        p.data_pagamento <= CURDATE()
    ";
} elseif ($periodo === '30dias') {

    $where_grafico_parcelas[] = "
        p.data_pagamento >=
        DATE_SUB(CURDATE(), INTERVAL 29 DAY)
    ";

    $where_grafico_parcelas[] = "
        p.data_pagamento <= CURDATE()
    ";
}


$where_grafico_parcelas_sql =
    'WHERE ' .
    implode(
        ' AND ',
        $where_grafico_parcelas
    );


$stmt_grafico_parcelas =
    $pdo->prepare("

        SELECT

            DATE(p.data_pagamento) AS dia,

            COALESCE(
                SUM(p.valor),
                0
            ) AS receitas

        FROM parcelas p

        INNER JOIN orcamentos o
            ON o.id = p.orcamento_id

        $where_grafico_parcelas_sql

        GROUP BY
            DATE(p.data_pagamento)

        ORDER BY
            DATE(p.data_pagamento) ASC

    ");


$stmt_grafico_parcelas->execute(
    $params_grafico_parcelas
);

$dados_grafico_parcelas =
    $stmt_grafico_parcelas->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| ADICIONAR PARCELAS AO GRÁFICO
|--------------------------------------------------------------------------
*/

foreach (
    $dados_grafico_parcelas
    as $dia
) {

    $data =
        $dia['dia'];


    if (!isset($dados_grafico[$data])) {

        $dados_grafico[$data] = [

            'dia' =>
            $data,

            'receitas' =>
            0,

            'despesas' =>
            0
        ];
    }


    $dados_grafico[$data]['receitas']
        += (float)$dia['receitas'];
}


/*
|--------------------------------------------------------------------------
| ORDENAR GRÁFICO
|--------------------------------------------------------------------------
*/

ksort($dados_grafico);

$dados_grafico =
    array_values(
        $dados_grafico
    );


/*
|--------------------------------------------------------------------------
| MENSAGEM DE SUCESSO
|--------------------------------------------------------------------------
*/

$sucesso =
    isset($_GET['sucesso'])
    &&
    $_GET['sucesso'] === '1';


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


            <!-- ======================================================
         CABEÇALHO
    ======================================================= -->

            <div class="page-header">

                <div>

                    <div class="breadcrumb">
                        <span>Financeiro</span>
                    </div>

                    <h1>
                        Financeiro
                    </h1>

                    <p>
                        Controle as receitas, despesas e movimentações financeiras da clínica.
                    </p>

                </div>


                <a
                    href="novo_lancamento.php"
                    class="btn-novo">

                    <i class="fa-solid fa-plus"></i>

                    Novo lançamento

                </a>

            </div>


            <!-- ======================================================
         SUCESSO
    ======================================================= -->

            <?php if ($sucesso): ?>

                <div class="alert-sucesso">

                    <i class="fa-solid fa-circle-check"></i>

                    Operação realizada com sucesso.

                </div>

            <?php endif; ?>


            <!-- ======================================================
         FILTROS
    ======================================================= -->

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
                        $periodo !== 'mes'
                        ||
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


            <!-- ======================================================
         RESUMO
    ======================================================= -->

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


            <!-- ======================================================
         CONTEÚDO
    ======================================================= -->

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
                                array_slice(
                                    $lancamentos,
                                    0,
                                    10
                                )
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


            <!-- ======================================================
         TABELA
    ======================================================= -->

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
                                                        action="visualizar_orcamento.php?id=<?= (int)$lancamento['orcamento_id'] ?>"
                                                        style="display:inline;"
                                                        onsubmit="return confirm('Confirmar pagamento desta parcela?');">

                                                        <input
                                                            type="hidden"
                                                            name="csrf_token"
                                                            value="<?= htmlspecialchars(
                                                                        $_SESSION['csrf_token'] ?? ''
                                                                    ) ?>">

                                                        <input
                                                            type="hidden"
                                                            name="parcela_id"
                                                            value="<?= (int)$lancamento['parcela_id'] ?>">

                                                        <input
                                                            type="hidden"
                                                            name="marcar_paga"
                                                            value="1">

                                                        <button
                                                            type="submit"
                                                            class="btn-acao btn-pagar-financeiro"
                                                            title="Marcar parcela como paga">

                                                            <i class="fa-solid fa-check"></i>

                                                        </button>

                                                    </form>

                                                <?php endif; ?>


                                            <?php else: ?>


                                                <!-- VISUALIZAR LANÇAMENTO -->

                                                <a
                                                    href="visualizar_lancamento.php?id=<?= (int)$lancamento['id'] ?>"
                                                    class="btn-acao"
                                                    title="Visualizar">

                                                    <i class="fa-regular fa-eye"></i>

                                                </a>


                                                <!-- EDITAR -->

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


        <style>
       
        </style>


</body>

</html>