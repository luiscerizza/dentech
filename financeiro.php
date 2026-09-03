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
function csvSeguro($valor): string
{
    $valor = (string)$valor;

    if ($valor !== '' && in_array($valor[0], ['=', '+', '-', '@'], true)) {
        return "'" . $valor;
    }

    return $valor;
}


/*
|--------------------------------------------------------------------------
| FILTROS
|--------------------------------------------------------------------------
*/

$periodo = $_GET['periodo'] ?? 'todos';
$tipo_filtro = $_GET['tipo'] ?? 'todos';
$mes_filtro = $_GET['mes'] ?? date('Y-m');
$mes_ativo = isset($_GET['mes_ativo']) && $_GET['mes_ativo'] === '1';
$paciente_filtro = isset($_GET['paciente_id']) ? (int)$_GET['paciente_id'] : 0;
$status_filtro = $_GET['status'] ?? 'todos';

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

$status_validos = ['todos', 'pago', 'pendente', 'atrasada'];

if (!in_array($periodo, $periodos_validos, true)) {
    $periodo = 'mes';
}

if (!in_array($tipo_filtro, $tipos_validos, true)) {
    $tipo_filtro = 'todos';
}

if (!in_array($status_filtro, $status_validos, true)) {
    $status_filtro = 'todos';
}

if ($mes_filtro !== '' && !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes_filtro)) {
    $mes_filtro = '';
}

/*
|--------------------------------------------------------------------------
| DEFINIR PERÍODO
|--------------------------------------------------------------------------
|
| Usamos PHP para montar as datas.
| Isso facilita usar exatamente o mesmo período
| nos lançamentos manuais e nas parcelas de procedimentos.
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

if ($mes_ativo && $mes_filtro !== '') {
    $data_inicio = $mes_filtro . '-01';
    $data_fim = date('Y-m-t', strtotime($data_inicio));
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

if ($paciente_filtro > 0 || $status_filtro === 'atrasada') {
    $where_manual[] = '1 = 0';
} elseif ($status_filtro !== 'todos') {
    $where_manual[] = 'status = :status_manual';
    $params_manual[':status_manual'] = $status_filtro;
}

/*
 * Orçamentos aceitos são exibidos a partir da tabela `parcelas`, que é a
 * fonte oficial de verdade do financeiro do orçamento.
 *
 * Algumas versões anteriores do fluxo também criavam um registro em
 * `lancamentos_financeiros` com `orcamento_id` preenchido. Se esse registro
 * for carregado junto com `parcelas`, a mesma parcela aparece duas vezes.
 * Portanto, lançamentos vinculados a orçamento não entram nesta consulta;
 * eles serão montados abaixo exclusivamente a partir de `parcelas`.
 */
$where_manual[] = '(orcamento_id IS NULL OR orcamento_id = 0)';

/*
 * Parcelas de procedimentos também possuem um lançamento financeiro
 * vinculado por `procedimento_id`. Esses lançamentos não devem entrar
 * como lançamentos manuais, pois serão montados abaixo a partir de
 * `parcelas`, evitando duplicidade no financeiro.
 */
$where_manual[] = '(procedimento_id IS NULL OR procedimento_id = 0)';

$where_manual_sql = 'WHERE ' . implode(' AND ', $where_manual);

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
| ORÇAMENTOS NÃO SÃO MOVIMENTAÇÕES FINANCEIRAS
|--------------------------------------------------------------------------
|
| Um orçamento é uma proposta comercial. Mesmo depois de confirmado,
| seu valor não entra em Receitas, A receber ou Fluxo de caixa.
|
| A cobrança financeira somente é criada quando existe um procedimento
| realizado e sua cobrança/parcelamento é gerado.
|
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| BUSCAR COBRANÇAS DE PROCEDIMENTOS
|--------------------------------------------------------------------------
|
| Procedimentos são cobrados diretamente por `parcelas`, sem depender
| de `orcamento_id`. O lançamento financeiro vinculado à parcela serve
| para guardar informações complementares, como a forma de pagamento.
|
|--------------------------------------------------------------------------
*/

$where_procedimento = [
    'p.procedimento_id IS NOT NULL'
];

$params_procedimento = [];

if ($paciente_filtro > 0) {
    $where_procedimento[] = 'proc.paciente_id = :proc_paciente_id';
    $params_procedimento[':proc_paciente_id'] = $paciente_filtro;
}

if ($status_filtro === 'pago') {
    $where_procedimento[] = "LOWER(TRIM(p.status)) = 'paga'";
} elseif ($status_filtro === 'pendente') {
    $where_procedimento[] = "LOWER(TRIM(p.status)) = 'pendente'";
} elseif ($status_filtro === 'atrasada') {
    $where_procedimento[] = "(LOWER(TRIM(p.status)) = 'atrasada' OR (LOWER(TRIM(p.status)) = 'pendente' AND p.vencimento < CURDATE()))";
}

/*
|--------------------------------------------------------------------------
| FILTRO DE DATA
|--------------------------------------------------------------------------
*/

if ($data_inicio !== null && $data_fim !== null) {

    $where_procedimento[] = "
        DATE(
            CASE
                WHEN LOWER(TRIM(p.status)) = 'paga'
                     AND p.data_pagamento IS NOT NULL
                    THEN p.data_pagamento
                ELSE p.vencimento
            END
        ) BETWEEN :proc_data_inicio AND :proc_data_fim
    ";

    $params_procedimento[':proc_data_inicio'] = $data_inicio;
    $params_procedimento[':proc_data_fim'] = $data_fim;
}

/*
|--------------------------------------------------------------------------
| TIPO
|--------------------------------------------------------------------------
|
| Parcelas de procedimentos são sempre receitas.
|
|--------------------------------------------------------------------------
*/

if ($tipo_filtro === 'despesa') {
    $where_procedimento[] = '1 = 0';
}

$where_procedimento_sql =
    'WHERE ' . implode(' AND ', $where_procedimento);

/*
|--------------------------------------------------------------------------
| SQL DOS PROCEDIMENTOS
|--------------------------------------------------------------------------
*/

$stmt_procedimentos = $pdo->prepare("
    SELECT
        p.id AS parcela_id,
        p.procedimento_id,
        p.numero_parcela,
        p.valor,
        p.vencimento,
        LOWER(TRIM(p.status)) AS status_parcela,
        p.data_pagamento,

        proc.titulo AS procedimento_titulo,
        proc.paciente_id,

        COALESCE(pr.paciente, 'Paciente não encontrado') AS paciente,

        COALESCE(lf.forma_pagamento, 'Não informado') AS forma_pagamento,

        COALESCE(pt.total_parcelas, 1) AS total_parcelas

    FROM parcelas p

    INNER JOIN procedimentos proc
        ON proc.id = p.procedimento_id

    LEFT JOIN prontuarios pr
        ON pr.id = proc.paciente_id

    LEFT JOIN lancamentos_financeiros lf
        ON lf.parcela_id = p.id

    LEFT JOIN (
        SELECT procedimento_id, COUNT(*) AS total_parcelas
        FROM parcelas
        WHERE procedimento_id IS NOT NULL
        GROUP BY procedimento_id
    ) pt
        ON pt.procedimento_id = p.procedimento_id

    $where_procedimento_sql

    ORDER BY
        CASE
            WHEN LOWER(TRIM(p.status)) = 'paga'
                 AND p.data_pagamento IS NOT NULL
                THEN p.data_pagamento
            ELSE p.vencimento
        END DESC,
        p.id DESC
");

$stmt_procedimentos->execute($params_procedimento);

$parcelas_procedimentos =
    $stmt_procedimentos->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| TRANSFORMAR COBRANÇAS DE PROCEDIMENTOS
|--------------------------------------------------------------------------
*/

$lancamentos_procedimentos = [];

foreach ($parcelas_procedimentos as $parcela) {

    $status_parcela = strtolower(
        trim((string)($parcela['status_parcela'] ?? ''))
    );

    if ($status_parcela === 'paga') {
        $data_movimentacao = $parcela['data_pagamento'];
        $status_financeiro = 'pago';
    } elseif ($status_parcela === 'atrasada') {
        $data_movimentacao = $parcela['vencimento'];
        $status_financeiro = 'atrasada';
    } else {
        $data_movimentacao = $parcela['vencimento'];
        $status_financeiro = 'pendente';
    }

    $numero_parcela = (int)$parcela['numero_parcela'];

    $total_parcelas = max(
        1,
        (int)($parcela['total_parcelas'] ?? 1)
    );

    $descricao = sprintf(
        'Procedimento #%d - %s - Parcela %d/%d',
        (int)$parcela['procedimento_id'],
        $parcela['paciente'],
        $numero_parcela,
        $total_parcelas
    );

    $lancamentos_procedimentos[] = [
        'id' => null,
        'tipo' => 'receita',
        'categoria' => 'Procedimento',
        'descricao' => $descricao,
        'data' => $data_movimentacao,
        'forma_pagamento' => $parcela['forma_pagamento'],
        'valor' => (float)$parcela['valor'],
        'parcelas' => $total_parcelas,
        'status' => $status_financeiro,
        'observacoes' => null,
        'orcamento_id' => null,
        'procedimento_id' => (int)$parcela['procedimento_id'],
        'origem' => 'procedimento',
        'parcela_id' => (int)$parcela['parcela_id'],
        'numero_orcamento' => null,
        'paciente' => $parcela['paciente'],
        'paciente_id' => (int)$parcela['paciente_id']
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
    $lancamento['paciente_id'] = null;
    $lancamento['procedimento_id'] = null;

    $lancamentos[] = $lancamento;
}

/*
|--------------------------------------------------------------------------
| ADICIONAR PROCEDIMENTOS AO FINANCEIRO
|--------------------------------------------------------------------------
*/

foreach ($lancamentos_procedimentos as $lancamento_procedimento) {
    $lancamentos[] = $lancamento_procedimento;
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
| EXPORTAÇÃO CSV
|--------------------------------------------------------------------------
*/

if (($_GET['export'] ?? '') === 'csv') {
    $nomeArquivo = 'financeiro_' . date('Y-m-d_H-i-s') . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    echo "\xEF\xBB\xBF";

    $saida = fopen('php://output', 'w');

    fputcsv($saida, [
        'Data',
        'Tipo',
        'Categoria',
        'Descrição',
        'Origem',
        'Paciente',
        'Forma de pagamento',
        'Status',
        'Valor',
        'Parcelas'
    ], ';');

    foreach ($lancamentos as $lancamento) {
        $origem = $lancamento['origem'] === 'procedimento'
            ? 'Procedimento'
            : 'Lançamento';

        $tipo = $lancamento['tipo'] === 'receita'
            ? 'Receita'
            : 'Despesa';

        fputcsv($saida, [
            csvSeguro(dataBR($lancamento['data'] ?? null)),
            csvSeguro($tipo),
            csvSeguro($lancamento['categoria'] ?? ''),
            csvSeguro($lancamento['descricao'] ?? ''),
            csvSeguro($origem),
            csvSeguro($lancamento['paciente'] ?? ''),
            csvSeguro($lancamento['forma_pagamento'] ?? 'Não informado'),
            csvSeguro(textoStatus($lancamento['status'] ?? '')),
            number_format((float)($lancamento['valor'] ?? 0), 2, ',', '.'),
            csvSeguro((string)($lancamento['parcelas'] ?? ''))
        ], ';');
    }

    fclose($saida);
    exit;
}

/*
|--------------------------------------------------------------------------
| LIMITAR LANÇAMENTOS RECENTES
|--------------------------------------------------------------------------
*/

$lancamentos_realizados = array_values(array_filter(
    $lancamentos,
    function ($lancamento) {
        return ($lancamento['status'] ?? '') === 'pago'
            && in_array(($lancamento['tipo'] ?? ''), ['receita', 'despesa'], true);
    }
));

$lancamentos_recentes = array_slice($lancamentos_realizados, 0, 20);
$lancamentos_recentes_total = count($lancamentos_realizados);

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

$sucesso = $_GET['sucesso'] ?? null;

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

$status_nomes = [
    'todos' => 'Todos os status',
    'pago' => 'Pagos',
    'pendente' => 'Pendentes',
    'atrasada' => 'Atrasados'
];

$stmt_pacientes = $pdo->query("SELECT id, paciente FROM prontuarios ORDER BY paciente ASC");
$pacientes = $stmt_pacientes->fetchAll(PDO::FETCH_ASSOC);

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

                <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                    <a
                        href="<?= htmlspecialchars('financeiro.php?' . http_build_query(array_merge($_GET, ['export' => 'csv'])), ENT_QUOTES, 'UTF-8') ?>"
                        class="btn-limpar"
                        title="Exportar as movimentações filtradas para CSV">
                        <i class="fa-solid fa-file-csv"></i>
                        Exportar CSV
                    </a>

                    <a
                        href="novo_lancamento.php"
                        class="btn-novo">

                        <i class="fa-solid fa-plus"></i>

                        Novo lançamento

                    </a>
                </div>

            </div>


            <!-- =========================================================
         ALERTA
    ========================================================== -->

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


            <!-- =========================================================
         FILTROS
    ========================================================== -->

            <section class="filtros-card">

                <form method="GET" class="filtros-form">

                    <div class="filtro-grupo">
                        <label for="periodo">Período</label>
                        <div class="select-wrapper">
                            <i class="fa-regular fa-calendar"></i>
                            <select id="periodo" name="periodo">
                                <?php foreach ($periodos_nomes as $valor => $nome): ?>
                                    <option value="<?= htmlspecialchars($valor) ?>" <?= $periodo === $valor ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($nome) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="filtro-grupo">
                        <label for="mes">Mês específico</label>
                        <div class="select-wrapper">
                            <i class="fa-regular fa-calendar-days"></i>
                            <input type="month" id="mes" name="mes" value="<?= htmlspecialchars($mes_filtro) ?>" data-default-month="<?= htmlspecialchars(date('Y-m')) ?>">
                            <input type="hidden" id="mes_ativo" name="mes_ativo" value="<?= $mes_ativo ? '1' : '0' ?>">
                        </div>
                    </div>

                    <div class="filtro-grupo">
                        <label for="paciente_id">Paciente</label>
                        <div class="select-wrapper">
                            <i class="fa-solid fa-user"></i>
                            <select id="paciente_id" name="paciente_id">
                                <option value="0">Todos os pacientes</option>
                                <?php foreach ($pacientes as $paciente): ?>
                                    <option value="<?= (int)$paciente['id'] ?>" <?= $paciente_filtro === (int)$paciente['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($paciente['paciente']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="filtro-grupo">
                        <label for="status">Status</label>
                        <div class="select-wrapper">
                            <i class="fa-solid fa-circle-half-stroke"></i>
                            <select id="status" name="status">
                                <?php foreach ($status_nomes as $valor => $nome): ?>
                                    <option value="<?= htmlspecialchars($valor) ?>" <?= $status_filtro === $valor ? 'selected' : '' ?>>
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
                                    <option value="<?= htmlspecialchars($valor) ?>" <?= $tipo_filtro === $valor ? 'selected' : '' ?>>
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

                    <a href="financeiro.php?periodo=todos&status=atrasada" class="btn-limpar">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        Ver atrasados
                    </a>

                    <?php if (
                        $periodo !== 'mes' ||
                        $mes_ativo ||
                        $paciente_filtro > 0 ||
                        $status_filtro !== 'todos' ||
                        $tipo_filtro !== 'todos'
                    ): ?>
                        <a href="financeiro.php" class="btn-limpar">Limpar</a>
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

                            <?php foreach ($lancamentos_recentes as $indice_recente => $lancamento): ?>

                                <div class="lancamento-item recente-item" data-recente-index="<?= (int)$indice_recente ?>">

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

                        <?php if ($lancamentos_recentes_total > 20): ?>
                            <div class="recentes-acoes">
                                <button type="button" class="btn-limpar" id="btnMostrarMaisRecentes">Mostrar mais</button>
                                <button type="button" class="btn-limpar" id="btnMostrarTudoRecentes">Mostrar tudo</button>
                            </div>
                        <?php endif; ?>

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
                            Lançamentos financeiros e cobranças de procedimentos.
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
                                                'procedimento'
                                            ): ?>

                                                <span
                                                    class="origem-procedimento">

                                                    <i
                                                        class="
                                                fa-solid
                                                fa-tooth
                                            ">
                                                    </i>

                                                    Procedimento

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

                                            <?php if (
                                                $lancamento['origem'] ===
                                                'orcamento'
                                            ): ?>

                                                <a
                                                    href="visualizar_cobranca.php?parcela_id=<?= (int)$lancamento['parcela_id'] ?>"
                                                    class="btn-acao"
                                                    title="Visualizar cobrança">

                                                    <i class="fa-regular fa-eye"></i>

                                                </a>

                                                <?php if (!empty($lancamento['parcela_id'])): ?>
                                                    <a href="editar_cobranca.php?parcela_id=<?= (int)$lancamento['parcela_id'] ?>" class="btn-acao" title="Editar cobrança">
                                                        <i class="fa-solid fa-pen"></i>
                                                    </a>
                                                <?php endif; ?>

                                                <?php if (
                                                    in_array(
                                                        strtolower(trim((string)$lancamento['status'])),
                                                        ['pendente', 'atrasada'],
                                                        true
                                                    ) && !empty($lancamento['parcela_id'])
                                                ): ?>

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
                                                            value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

                                                        <button
                                                            type="submit"
                                                            class="btn-acao btn-pagar-financeiro"
                                                            title="Marcar como paga">

                                                            <i class="fa-solid fa-check"></i>

                                                        </button>

                                                    </form>

                                                <?php endif; ?>

                                            <?php elseif (
                                                $lancamento['origem'] ===
                                                'procedimento'
                                            ): ?>

                                                <a
                                                    href="visualizar_cobranca.php?parcela_id=<?= (int)$lancamento['parcela_id'] ?>"
                                                    class="btn-acao"
                                                    title="Visualizar cobrança">

                                                    <i class="fa-regular fa-eye"></i>

                                                </a>

                                                <?php if (!empty($lancamento['parcela_id'])): ?>
                                                    <a href="editar_cobranca.php?parcela_id=<?= (int)$lancamento['parcela_id'] ?>" class="btn-acao" title="Editar cobrança">
                                                        <i class="fa-solid fa-pen"></i>
                                                    </a>
                                                <?php endif; ?>

                                                <?php if (
                                                    in_array(
                                                        strtolower(trim((string)$lancamento['status'])),
                                                        ['pendente', 'atrasada'],
                                                        true
                                                    ) && !empty($lancamento['parcela_id'])
                                                ): ?>

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
                                                            value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

                                                        <button
                                                            type="submit"
                                                            class="btn-acao btn-pagar-financeiro"
                                                            title="Marcar como paga">

                                                            <i class="fa-solid fa-check"></i>

                                                        </button>

                                                    </form>

                                                <?php endif; ?>

                                            <?php else: ?>

                                                <?php if (
                                                    !empty($lancamento['id'])
                                                ): ?>

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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const items = Array.from(document.querySelectorAll('.recente-item'));
            const btnMais = document.getElementById('btnMostrarMaisRecentes');
            const btnTudo = document.getElementById('btnMostrarTudoRecentes');

            if (!items.length) return;

            let visible = Math.min(20, items.length);

            function atualizar() {
                items.forEach((item, index) => {
                    item.style.display = index < visible ? '' : 'none';
                });

                const terminou = visible >= items.length;
                if (btnMais) btnMais.style.display = terminou ? 'none' : '';
                if (btnTudo) btnTudo.style.display = terminou ? 'none' : '';
            }

            if (btnMais) {
                btnMais.addEventListener('click', function() {
                    visible = Math.min(visible + 20, items.length);
                    atualizar();
                });
            }

            if (btnTudo) {
                btnTudo.addEventListener('click', function() {
                    visible = items.length;
                    atualizar();
                });
            }

            atualizar();
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('.filtros-form');
            const periodo = document.getElementById('periodo');
            const mes = document.getElementById('mes');
            const mesAtivo = document.getElementById('mes_ativo');
            if (!form || !periodo || !mes || !mesAtivo) return;
            if (mesAtivo.value !== '1') mes.value = mes.dataset.defaultMonth || '';
            mes.addEventListener('change', function() {
                mesAtivo.value = mes.value ? '1' : '0';
            });
            periodo.addEventListener('change', function() {
                mesAtivo.value = '0';
                if (periodo.value !== 'todos') mes.value = mes.dataset.defaultMonth || '';
            });
            form.addEventListener('submit', function() {
                if (!mes.value) mesAtivo.value = '0';
            });
        });
    </script>
</body>

</html>