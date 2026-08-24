<?php
require_once 'config/auth.php';
require_once 'config/csrf.php';
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
| Atualizar parcelas vencidas
|--------------------------------------------------------------------------
*/
$pdo->exec("
    UPDATE parcelas p
    INNER JOIN orcamentos o
        ON o.id = p.orcamento_id
    SET p.status = 'atrasada'
    WHERE LOWER(TRIM(COALESCE(o.status, ''))) IN ('aceito', 'confirmado')
      AND LOWER(TRIM(COALESCE(p.status, ''))) = 'pendente'
      AND p.vencimento < CURDATE()
");

/*
|--------------------------------------------------------------------------
| Funções auxiliares de período
|--------------------------------------------------------------------------
*/
function condicoesPeriodo(string $expressao, string $periodo): array
{
    return match ($periodo) {
        'hoje' => [
            "DATE($expressao) = CURDATE()"
        ],

        '7dias' => [
            "$expressao >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)",
            "$expressao <= CURDATE()"
        ],

        '30dias' => [
            "$expressao >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)",
            "$expressao <= CURDATE()"
        ],

        'todos' => [],

        default => [
            "YEAR($expressao) = YEAR(CURDATE())",
            "MONTH($expressao) = MONTH(CURDATE())"
        ]
    };
}

/*
|--------------------------------------------------------------------------
| Data das parcelas
|--------------------------------------------------------------------------
|
| Paga:
|   data_pagamento
|
| Pendente/atrasada:
|   vencimento
|--------------------------------------------------------------------------
*/
$data_parcela_sql = "
    CASE
        WHEN LOWER(TRIM(COALESCE(p.status, ''))) IN ('paga', 'pago')
             AND p.data_pagamento IS NOT NULL
        THEN p.data_pagamento
        ELSE p.vencimento
    END
";

/*
|--------------------------------------------------------------------------
| 1. Lançamentos manuais
|--------------------------------------------------------------------------
|
| Registros vinculados a orçamento não são usados aqui.
| A fonte oficial das parcelas de orçamento é a tabela parcelas.
| Isso impede a duplicação causada pelo aceitar_orcamento.php.
|--------------------------------------------------------------------------
*/
$where_manuais = [
    "(
        lf.orcamento_id IS NULL
        OR lf.orcamento_id = 0
        OR lf.categoria = 'Ajuste de procedimento'
    )"
];
$params_manuais = [];

foreach (condicoesPeriodo('lf.data', $periodo) as $condicao) {
    $where_manuais[] = $condicao;
}

if ($tipo_filtro !== 'todos') {
    $where_manuais[] = "LOWER(TRIM(COALESCE(lf.tipo, ''))) = :tipo_manual";
    $params_manuais[':tipo_manual'] = $tipo_filtro;
}

$stmt_manuais = $pdo->prepare("
    SELECT
        lf.id,
        LOWER(TRIM(COALESCE(lf.tipo, ''))) AS tipo,
        lf.categoria,
        lf.descricao,
        lf.data,
        lf.forma_pagamento,
        lf.valor,
        lf.parcelas,
        LOWER(TRIM(COALESCE(lf.status, ''))) AS status,
        lf.observacoes,
        lf.orcamento_id,
        lf.parcela_id,
        lf.procedimento_id
    FROM lancamentos_financeiros lf
    WHERE " . implode(' AND ', $where_manuais) . "
    ORDER BY lf.data DESC, lf.id DESC
    LIMIT 100
");

$stmt_manuais->execute($params_manuais);
$lancamentos_manuais = $stmt_manuais->fetchAll(PDO::FETCH_ASSOC);

foreach ($lancamentos_manuais as &$manual) {
    $manual['origem'] = 'lancamento';
    $manual['numero_orcamento'] = null;
    $manual['paciente'] = null;
    $manual['parcela_id'] = !empty($manual['parcela_id'])
        ? (int)$manual['parcela_id']
        : null;

    $manual['procedimento_id'] = !empty($manual['procedimento_id'])
        ? (int)$manual['procedimento_id']
        : null;
}
unset($manual);

/*
|--------------------------------------------------------------------------
| 2. Parcelas de orçamentos aceitos
|--------------------------------------------------------------------------
*/
$where_parcelas = [
    "LOWER(TRIM(COALESCE(o.status, ''))) IN ('aceito', 'confirmado')"
];
$params_parcelas = [];

foreach (condicoesPeriodo($data_parcela_sql, $periodo) as $condicao) {
    $where_parcelas[] = $condicao;
}

if ($tipo_filtro === 'despesa') {
    $where_parcelas[] = '1 = 0';
}

$stmt_parcelas = $pdo->prepare("
    SELECT
        p.id AS parcela_id,
        p.orcamento_id,
        p.numero_parcela,
        p.valor,
        p.vencimento,
        LOWER(TRIM(COALESCE(p.status, 'pendente'))) AS status_parcela,
        p.data_pagamento,
        o.id AS numero_orcamento,
        COALESCE(pr.paciente, 'Paciente não encontrado') AS paciente,

        (
            SELECT COUNT(*)
            FROM parcelas px
            WHERE px.orcamento_id = p.orcamento_id
        ) AS total_parcelas

    FROM parcelas p

    INNER JOIN orcamentos o
        ON o.id = p.orcamento_id

    LEFT JOIN prontuarios pr
        ON pr.id = o.paciente_id

    WHERE " . implode(' AND ', $where_parcelas) . "

    ORDER BY $data_parcela_sql DESC, p.id DESC
    LIMIT 100
");

$stmt_parcelas->execute($params_parcelas);
$parcelas_orcamentos = $stmt_parcelas->fetchAll(PDO::FETCH_ASSOC);

$lancamentos_orcamentos = [];

foreach ($parcelas_orcamentos as $parcela) {

    $status_parcela = strtolower(
        trim((string)$parcela['status_parcela'])
    );

    if (in_array($status_parcela, ['paga', 'pago'], true)) {
        $status_financeiro = 'pago';
        $data_movimentacao = $parcela['data_pagamento']
            ?: $parcela['vencimento'];
    } elseif ($status_parcela === 'atrasada') {
        $status_financeiro = 'atrasada';
        $data_movimentacao = $parcela['vencimento'];
    } else {
        $status_financeiro = 'pendente';
        $data_movimentacao = $parcela['vencimento'];
    }

    $numero_parcela = (int)$parcela['numero_parcela'];
    $total_parcelas = max(
        1,
        (int)$parcela['total_parcelas']
    );

    $lancamentos_orcamentos[] = [
        'id' => null,
        'tipo' => 'receita',
        'categoria' => 'Orçamento odontológico',
        'descricao' => sprintf(
            'Orçamento #%d - %s - Parcela %d/%d',
            (int)$parcela['numero_orcamento'],
            $parcela['paciente'],
            $numero_parcela,
            $total_parcelas
        ),
        'data' => $data_movimentacao,
        'forma_pagamento' => 'Orçamento',
        'valor' => (float)$parcela['valor'],

        /*
         * Aqui guardamos a quantidade total de parcelas.
         * A descrição já mostra 1/4, 2/4 etc.
         */
        'parcelas' => $total_parcelas,

        'status' => $status_financeiro,
        'observacoes' => null,
        'orcamento_id' => (int)$parcela['orcamento_id'],
        'parcela_id' => (int)$parcela['parcela_id'],
        'origem' => 'orcamento',
        'numero_orcamento' => (int)$parcela['numero_orcamento'],
        'paciente' => $parcela['paciente']
    ];
}

/*
|--------------------------------------------------------------------------
| 3. Unificar movimentações
|--------------------------------------------------------------------------
*/
$lancamentos = array_merge(
    $lancamentos_manuais,
    $lancamentos_orcamentos
);

usort($lancamentos, static function ($a, $b) {
    $dataA = strtotime((string)($a['data'] ?? '')) ?: 0;
    $dataB = strtotime((string)($b['data'] ?? '')) ?: 0;

    if ($dataA === $dataB) {
        return ((int)($b['id'] ?? 0))
            <=> ((int)($a['id'] ?? 0));
    }

    return $dataB <=> $dataA;
});

$lancamentos = array_slice($lancamentos, 0, 20);

/*
|--------------------------------------------------------------------------
| 4. Resumo financeiro
|--------------------------------------------------------------------------
|
| Receitas:
|   somente valores pagos no período.
|
| Despesas:
|   somente despesas pagas no período.
|
| A receber:
|   todas as receitas pendentes/atrasadas,
|   sem limitar pelo período.
|--------------------------------------------------------------------------
*/
$receitas = 0.0;
$despesas = 0.0;

/*
|--------------------------------------------------------------------------
| Receitas e despesas manuais pagas
|--------------------------------------------------------------------------
*/
$where_resumo_manuais = [
    "(
        lf.orcamento_id IS NULL
        OR lf.orcamento_id = 0
        OR lf.categoria = 'Ajuste de procedimento'
    )",
    "LOWER(TRIM(COALESCE(lf.status, ''))) IN ('pago', 'paga')"
];
$params_resumo_manuais = [];

foreach (condicoesPeriodo('lf.data', $periodo) as $condicao) {
    $where_resumo_manuais[] = $condicao;
}

if ($tipo_filtro !== 'todos') {
    $where_resumo_manuais[] =
        "LOWER(TRIM(COALESCE(lf.tipo, ''))) = :tipo_resumo";
    $params_resumo_manuais[':tipo_resumo'] = $tipo_filtro;
}

$stmt_resumo_manuais = $pdo->prepare(
    "
    SELECT
        COALESCE(
            SUM(
                CASE
                    WHEN LOWER(TRIM(COALESCE(lf.tipo, ''))) = 'receita'
                    THEN lf.valor
                    ELSE 0
                END
            ),
            0
        ) AS receitas,

        COALESCE(
            SUM(
                CASE
                    WHEN LOWER(TRIM(COALESCE(lf.tipo, ''))) = 'despesa'
                    THEN lf.valor
                    ELSE 0
                END
            ),
            0
        ) AS despesas

    FROM lancamentos_financeiros lf

    WHERE " . implode(' AND ', $where_resumo_manuais)
);

$stmt_resumo_manuais->execute($params_resumo_manuais);
$resumo_manuais = $stmt_resumo_manuais->fetch(PDO::FETCH_ASSOC) ?: [];

$receitas += (float)($resumo_manuais['receitas'] ?? 0);
$despesas += (float)($resumo_manuais['despesas'] ?? 0);

/*
|--------------------------------------------------------------------------
| Receitas pagas de parcelas de orçamento
|--------------------------------------------------------------------------
*/
$where_receitas_parcelas = [
    "LOWER(TRIM(COALESCE(o.status, ''))) IN ('aceito', 'confirmado')",
    "LOWER(TRIM(COALESCE(p.status, ''))) IN ('paga', 'pago')",
    "p.data_pagamento IS NOT NULL"
];

$params_receitas_parcelas = [];

foreach (condicoesPeriodo('p.data_pagamento', $periodo) as $condicao) {
    $where_receitas_parcelas[] = $condicao;
}

if ($tipo_filtro === 'despesa') {
    $where_receitas_parcelas[] = '1 = 0';
}

$stmt_receitas_parcelas = $pdo->prepare(
    "
    SELECT COALESCE(SUM(p.valor), 0)
    FROM parcelas p
    INNER JOIN orcamentos o
        ON o.id = p.orcamento_id
    WHERE " . implode(' AND ', $where_receitas_parcelas)
);

$stmt_receitas_parcelas->execute($params_receitas_parcelas);
$receitas += (float)(
    $stmt_receitas_parcelas->fetchColumn() ?: 0
);

/*
|--------------------------------------------------------------------------
| A RECEBER — SEM FILTRO DE PERÍODO
|--------------------------------------------------------------------------
|
| IMPORTANTE:
| A tabela parcelas é a fonte oficial das parcelas dos orçamentos.
|
| Exemplo:
| orçamento aceito:
|   parcela 1 = paga
|   parcela 2 = pendente
|   parcela 3 = pendente
|
| A receber = parcela 2 + parcela 3.
|
| Mesmo que a parcela 2 ou 3 vença em outro mês,
| ela continua no card A receber.
|--------------------------------------------------------------------------
*/
$a_receber = 0.0;

/*
 * Receitas manuais pendentes/atrasadas.
 */
$stmt_receber_manuais = $pdo->prepare("
    SELECT COALESCE(SUM(lf.valor), 0)
    FROM lancamentos_financeiros lf
    WHERE (
        lf.orcamento_id IS NULL
        OR lf.orcamento_id = 0
        OR lf.categoria = 'Ajuste de procedimento'
    )
      AND LOWER(TRIM(COALESCE(lf.tipo, ''))) = 'receita'
      AND LOWER(TRIM(COALESCE(lf.status, ''))) IN (
          'pendente',
          'atrasada'
      )
");

$stmt_receber_manuais->execute();

$a_receber += (float)(
    $stmt_receber_manuais->fetchColumn() ?: 0
);

/*
 * Parcelas de todos os orçamentos aceitos/confirmados.
 * Sem filtro de data.
 */
$stmt_receber_parcelas = $pdo->prepare("
    SELECT COALESCE(SUM(p.valor), 0)
    FROM parcelas p
    INNER JOIN orcamentos o
        ON o.id = p.orcamento_id
    WHERE LOWER(TRIM(COALESCE(o.status, ''))) IN (
        'aceito',
        'confirmado'
    )
      AND LOWER(TRIM(COALESCE(p.status, ''))) IN (
          'pendente',
          'atrasada'
      )
");

$stmt_receber_parcelas->execute();

$a_receber += (float)(
    $stmt_receber_parcelas->fetchColumn() ?: 0
);

$lucro = $receitas - $despesas;

/*
|--------------------------------------------------------------------------
| 5. Fluxo de caixa
|--------------------------------------------------------------------------
|
| Apenas dinheiro efetivamente recebido/pago entra no gráfico.
|--------------------------------------------------------------------------
*/
$where_grafico_manuais = [
    "(
        lf.orcamento_id IS NULL
        OR lf.orcamento_id = 0
        OR lf.categoria = 'Ajuste de procedimento'
    )",
    "LOWER(TRIM(COALESCE(lf.status, ''))) IN ('pago', 'paga')"
];
$params_grafico_manuais = [];

foreach (condicoesPeriodo('lf.data', $periodo) as $condicao) {
    $where_grafico_manuais[] = $condicao;
}

if ($tipo_filtro !== 'todos') {
    $where_grafico_manuais[] =
        "LOWER(TRIM(COALESCE(lf.tipo, ''))) = :tipo_grafico";
    $params_grafico_manuais[':tipo_grafico'] = $tipo_filtro;
}

$stmt_grafico_manuais = $pdo->prepare("
    SELECT
        DATE(lf.data) AS dia,

        COALESCE(
            SUM(
                CASE
                    WHEN LOWER(TRIM(COALESCE(lf.tipo, ''))) = 'receita'
                    THEN lf.valor
                    ELSE 0
                END
            ),
            0
        ) AS receitas,

        COALESCE(
            SUM(
                CASE
                    WHEN LOWER(TRIM(COALESCE(lf.tipo, ''))) = 'despesa'
                    THEN lf.valor
                    ELSE 0
                END
            ),
            0
        ) AS despesas

    FROM lancamentos_financeiros lf

    WHERE " . implode(' AND ', $where_grafico_manuais) . "

    GROUP BY DATE(lf.data)
");

$stmt_grafico_manuais->execute($params_grafico_manuais);
$grafico_manuais = $stmt_grafico_manuais->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Fluxo das parcelas pagas
|--------------------------------------------------------------------------
*/
$where_grafico_parcelas = [
    "LOWER(TRIM(COALESCE(o.status, ''))) IN ('aceito', 'confirmado')",
    "LOWER(TRIM(COALESCE(p.status, ''))) IN ('paga', 'pago')",
    "p.data_pagamento IS NOT NULL"
];
$params_grafico_parcelas = [];

foreach (condicoesPeriodo('p.data_pagamento', $periodo) as $condicao) {
    $where_grafico_parcelas[] = $condicao;
}

if ($tipo_filtro === 'despesa') {
    $where_grafico_parcelas[] = '1 = 0';
}

$stmt_grafico_parcelas = $pdo->prepare("
    SELECT
        DATE(p.data_pagamento) AS dia,
        COALESCE(SUM(p.valor), 0) AS receitas,
        0 AS despesas

    FROM parcelas p

    INNER JOIN orcamentos o
        ON o.id = p.orcamento_id

    WHERE " . implode(' AND ', $where_grafico_parcelas) . "

    GROUP BY DATE(p.data_pagamento)
");

$stmt_grafico_parcelas->execute($params_grafico_parcelas);
$grafico_parcelas = $stmt_grafico_parcelas->fetchAll(PDO::FETCH_ASSOC);

$grafico_por_dia = [];

foreach (
    array_merge($grafico_manuais, $grafico_parcelas)
    as $dia
) {
    $chave = $dia['dia'];

    if (!isset($grafico_por_dia[$chave])) {
        $grafico_por_dia[$chave] = [
            'dia' => $chave,
            'receitas' => 0.0,
            'despesas' => 0.0
        ];
    }

    $grafico_por_dia[$chave]['receitas'] +=
        (float)$dia['receitas'];

    $grafico_por_dia[$chave]['despesas'] +=
        (float)$dia['despesas'];
}

ksort($grafico_por_dia);

$dados_grafico = array_values($grafico_por_dia);

/*
|--------------------------------------------------------------------------
| Mensagens
|--------------------------------------------------------------------------
*/
$sucesso = isset($_GET['sucesso']);

$sucesso_tipo = $_GET['sucesso'] ?? '';

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
    return match (strtolower(trim((string)$status))) {
        'pago', 'paga' => 'status-pago',
        'pendente' => 'status-pendente',
        'atrasada' => 'status-outro',
        default => 'status-outro'
    };
}

function textoStatus($status): string
{
    return match (strtolower(trim((string)$status))) {
        'pago', 'paga' => 'Pago',
        'pendente' => 'Pendente',
        'atrasada' => 'Atrasada',
        default => ucfirst((string)$status)
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
                    <?php if ($sucesso_tipo === 'pagamento'): ?>
                        Pagamento da parcela registrado com sucesso.
                    <?php else: ?>
                        Lançamento salvo com sucesso.
                    <?php endif; ?>
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

                                        <?php if (($lancamento['categoria'] ?? '') === 'Ajuste de procedimento'): ?>
                                            <small class="lancamento-ajuste-badge">
                                                Ajuste de procedimento
                                            </small>
                                        <?php endif; ?>

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

                                            <?php if (($lancamento['origem'] ?? '') === 'orcamento'): ?>

                                                <a
                                                    href="visualizar_orcamento.php?id=<?= (int) $lancamento['orcamento_id'] ?>"
                                                    class="btn-acao"
                                                    title="Visualizar orçamento">
                                                    <i class="fa-regular fa-eye"></i>
                                                </a>

                                                <?php if (in_array(
                                                    strtolower(trim((string)$lancamento['status'])),
                                                    ['pendente', 'atrasada'],
                                                    true
                                                )): ?>

                                                    <form
                                                        method="POST"
                                                        action="pagar_parcela.php"
                                                        class="form-pagar-parcela"
                                                        onsubmit="return confirm('Confirmar pagamento desta parcela?');">

                                                        <input
                                                            type="hidden"
                                                            name="parcela_id"
                                                            value="<?= (int)$lancamento['parcela_id'] ?>">

                                                        <input
                                                            type="hidden"
                                                            name="csrf_token"
                                                            value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

                                                        <button
                                                            type="submit"
                                                            class="btn-acao btn-pagar-financeiro"
                                                            title="Marcar como paga">
                                                            <i class="fa-solid fa-check"></i>
                                                        </button>

                                                    </form>

                                                <?php endif; ?>

                                            <?php else: ?>

                                                <a
                                                    href="visualizar_lancamento.php?id=<?= (int) $lancamento['id'] ?>"
                                                    class="btn-acao"
                                                    title="Visualizar">
                                                    <i class="fa-regular fa-eye"></i>
                                                </a>

                                                <?php if (
                                                    ($lancamento['categoria'] ?? '') === 'Ajuste de procedimento'
                                                    && strtolower(trim((string)$lancamento['status'])) === 'pendente'
                                                ): ?>

                                                    <form
                                                        method="POST"
                                                        action="pagar_ajuste_financeiro.php"
                                                        class="form-pagar-parcela"
                                                        onsubmit="return confirm('Confirmar recebimento deste ajuste de procedimento?');">

                                                        <input
                                                            type="hidden"
                                                            name="lancamento_id"
                                                            value="<?= (int)$lancamento['id'] ?>">

                                                        <input
                                                            type="hidden"
                                                            name="csrf_token"
                                                            value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

                                                        <button
                                                            type="submit"
                                                            class="btn-acao btn-pagar-financeiro"
                                                            title="Marcar ajuste como pago">
                                                            <i class="fa-solid fa-check"></i>
                                                        </button>

                                                    </form>

                                                <?php else: ?>

                                                    <a
                                                        href="editar_lancamento.php?id=<?= (int) $lancamento['id'] ?>"
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
    </div>

    <style>
        .lancamento-ajuste-badge {
            display: inline-flex;
            align-items: center;
            width: fit-content;
            margin-top: 4px;
            padding: 3px 7px;
            border: 1px solid #fed7aa;
            border-radius: 999px;
            background: #fff7ed;
            color: #c2410c;
            font-size: 10px;
            font-weight: 700;
        }
    </style>
</body>

</html>