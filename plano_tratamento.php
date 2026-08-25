<?php

require_once 'config/auth.php';
require_once 'conexao/conexao.php';

exigirLogin();

/*
|--------------------------------------------------------------------------
| FILTROS
|--------------------------------------------------------------------------
*/

$busca = trim($_GET['busca'] ?? '');
$status = $_GET['status'] ?? 'todos';

$where = [];
$params = [];

if ($busca !== '') {
    $where[] = 'p.paciente LIKE ?';
    $params[] = '%' . $busca . '%';
}

$statusPermitidos = [
    'planejamento',
    'em_andamento',
    'concluido',
    'cancelado'
];

if ($status !== 'todos' && in_array($status, $statusPermitidos, true)) {
    $where[] = 'pt.status = ?';
    $params[] = $status;
}

/*
|--------------------------------------------------------------------------
| QUERY PRINCIPAL
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        pt.id,
        pt.paciente_id,
        pt.titulo,
        pt.descricao,
        pt.status,
        pt.data_criacao,
        pt.data_atualizacao,
        p.paciente,

        COUNT(pti.id) AS quantidade_itens,

        COALESCE(
            SUM(pti.valor_estimado),
            0
        ) AS valor_estimado

    FROM planos_tratamento pt

    INNER JOIN prontuarios p
        ON p.id = pt.paciente_id

    LEFT JOIN planos_tratamento_itens pti
        ON pti.plano_id = pt.id
";

if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= "
    GROUP BY
        pt.id,
        pt.paciente_id,
        pt.titulo,
        pt.descricao,
        pt.status,
        pt.data_criacao,
        pt.data_atualizacao,
        p.paciente

    ORDER BY pt.data_atualizacao DESC, pt.id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$planos = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| CONTADORES
|--------------------------------------------------------------------------
*/

$stmtResumo = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'planejamento' THEN 1 ELSE 0 END) AS planejamento,
        SUM(CASE WHEN status = 'em_andamento' THEN 1 ELSE 0 END) AS em_andamento,
        SUM(CASE WHEN status = 'concluido' THEN 1 ELSE 0 END) AS concluido,
        SUM(CASE WHEN status = 'cancelado' THEN 1 ELSE 0 END) AS cancelado
    FROM planos_tratamento
");

$resumo = $stmtResumo->fetch(PDO::FETCH_ASSOC);

$totalPlanos = (int)($resumo['total'] ?? 0);
$totalPlanejamento = (int)($resumo['planejamento'] ?? 0);
$totalAndamento = (int)($resumo['em_andamento'] ?? 0);
$totalConcluido = (int)($resumo['concluido'] ?? 0);
$totalCancelado = (int)($resumo['cancelado'] ?? 0);

/*
|--------------------------------------------------------------------------
| MENSAGENS
|--------------------------------------------------------------------------
*/

$sucesso = $_GET['sucesso'] ?? '';
$erro = $_GET['erro'] ?? '';

$mensagensSucesso = [
    'criado' => 'Plano de tratamento criado com sucesso.',
    'editado' => 'Plano de tratamento atualizado com sucesso.',
];

$mensagensErro = [
    'invalido' => 'Plano de tratamento inválido.',
    'nao_encontrado' => 'Plano de tratamento não encontrado.',
];

$statusLabels = [
    'planejamento' => 'Planejamento',
    'em_andamento' => 'Em andamento',
    'concluido' => 'Concluído',
    'cancelado' => 'Cancelado',
];

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>
        Plano de Tratamento - Dentech
    </title>

    <link rel="stylesheet" href="css/global.css">
    <link rel="stylesheet" href="css/variables.css">
    <link rel="stylesheet" href="css/layout.css">
    <link rel="stylesheet" href="css/navbar.css">
    <link rel="stylesheet" href="css/plano_tratamento.css">

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <link
        rel="icon"
        type="image/png"
        href="img/icon.PNG">

</head>

<body>

    <?php include 'navbar.php'; ?>

    <main class="plano-page">

        <div class="plano-container">

            <header class="page-header">

                <div>

                    <span class="page-kicker">
                        CLÍNICO
                    </span>

                    <h1>
                        Plano de Tratamento
                    </h1>

                    <p>
                        Organize e acompanhe o planejamento clínico
                        de cada paciente.
                    </p>

                </div>

                <a
                    href="novo_plano_tratamento.php"
                    class="btn btn-primary">

                    <i class="fa-solid fa-plus"></i>

                    Novo plano

                </a>

            </header>

            <?php if (
                $sucesso !== '' &&
                isset($mensagensSucesso[$sucesso])
            ): ?>

                <div class="message message-success">

                    <i class="fa-solid fa-circle-check"></i>

                    <span>
                        <?= htmlspecialchars(
                            $mensagensSucesso[$sucesso]
                        ) ?>
                    </span>

                </div>

            <?php endif; ?>

            <?php if (
                $erro !== '' &&
                isset($mensagensErro[$erro])
            ): ?>

                <div class="message message-error">

                    <i class="fa-solid fa-circle-exclamation"></i>

                    <span>
                        <?= htmlspecialchars(
                            $mensagensErro[$erro]
                        ) ?>
                    </span>

                </div>

            <?php endif; ?>

            <section class="summary-grid">

                <article class="summary-card">

                    <div class="summary-icon neutral">
                        <i class="fa-solid fa-layer-group"></i>
                    </div>

                    <div>
                        <span>Total</span>
                        <strong>
                            <?= $totalPlanos ?>
                        </strong>
                    </div>

                </article>

                <article class="summary-card">

                    <div class="summary-icon planning">
                        <i class="fa-regular fa-pen-to-square"></i>
                    </div>

                    <div>
                        <span>Planejamento</span>
                        <strong>
                            <?= $totalPlanejamento ?>
                        </strong>
                    </div>

                </article>

                <article class="summary-card">

                    <div class="summary-icon progress">
                        <i class="fa-solid fa-spinner"></i>
                    </div>

                    <div>
                        <span>Em andamento</span>
                        <strong>
                            <?= $totalAndamento ?>
                        </strong>
                    </div>

                </article>

                <article class="summary-card">

                    <div class="summary-icon done">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>

                    <div>
                        <span>Concluídos</span>
                        <strong>
                            <?= $totalConcluido ?>
                        </strong>
                    </div>

                </article>

            </section>

            <section class="filter-card">

                <form method="GET" class="filter-form">

                    <div class="filter-field search-field">

                        <label for="busca">
                            Paciente
                        </label>

                        <div class="input-with-icon">

                            <i class="fa-solid fa-magnifying-glass"></i>

                            <input
                                type="text"
                                id="busca"
                                name="busca"
                                value="<?= htmlspecialchars($busca) ?>"
                                placeholder="Nome do paciente...">

                        </div>

                    </div>

                    <div class="filter-field">

                        <label for="status">
                            Status
                        </label>

                        <select
                            id="status"
                            name="status">

                            <option
                                value="todos"
                                <?= $status === 'todos'
                                    ? 'selected'
                                    : '' ?>>
                                Todos
                            </option>

                            <?php foreach (
                                $statusLabels as $valor => $label
                            ): ?>

                                <option
                                    value="<?= $valor ?>"
                                    <?= $status === $valor
                                        ? 'selected'
                                        : '' ?>>

                                    <?= htmlspecialchars($label) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="filter-actions">

                        <button
                            type="submit"
                            class="btn btn-filter">

                            <i class="fa-solid fa-filter"></i>

                            Filtrar

                        </button>

                        <a
                            href="plano_tratamento.php"
                            class="btn btn-outline">

                            Limpar

                        </a>

                    </div>

                </form>

            </section>

            <section class="table-card">

                <div class="table-header">

                    <div>

                        <span class="table-kicker">
                            PLANEJAMENTO CLÍNICO
                        </span>

                        <h2>
                            Planos de tratamento
                        </h2>

                    </div>

                    <span class="result-count">
                        <?= count($planos) ?>
                        resultado(s)
                    </span>

                </div>

                <?php if (empty($planos)): ?>

                    <div class="empty-state">

                        <div class="empty-icon">
                            <i class="fa-solid fa-notes-medical"></i>
                        </div>

                        <strong>
                            Nenhum plano de tratamento encontrado
                        </strong>

                        <span>
                            Crie um novo plano para começar a organizar
                            o tratamento do paciente.
                        </span>

                        <a
                            href="novo_plano_tratamento.php"
                            class="btn btn-primary">

                            <i class="fa-solid fa-plus"></i>

                            Criar plano

                        </a>

                    </div>

                <?php else: ?>

                    <div class="table-wrapper">

                        <table class="plans-table">

                            <thead>

                                <tr>

                                    <th>
                                        Paciente
                                    </th>

                                    <th>
                                        Plano
                                    </th>

                                    <th>
                                        Etapas
                                    </th>

                                    <th>
                                        Valor estimado
                                    </th>

                                    <th>
                                        Status
                                    </th>

                                    <th>
                                        Atualização
                                    </th>

                                    <th class="actions-column">
                                        Ações
                                    </th>

                                </tr>

                            </thead>

                            <tbody>

                                <?php foreach (
                                    $planos as $plano
                                ): ?>

                                    <tr>

                                        <td>

                                            <div class="patient-cell">

                                                <div class="patient-icon">
                                                    <i class="fa-regular fa-user"></i>
                                                </div>

                                                <strong>
                                                    <?= htmlspecialchars(
                                                        $plano['paciente']
                                                    ) ?>
                                                </strong>

                                            </div>

                                        </td>

                                        <td>

                                            <div class="plan-cell">

                                                <strong>
                                                    <?= htmlspecialchars(
                                                        $plano['titulo']
                                                    ) ?>
                                                </strong>

                                                <?php if (
                                                    !empty($plano['descricao'])
                                                ): ?>

                                                    <span>
                                                        <?= htmlspecialchars(
                                                            mb_strimwidth(
                                                                $plano['descricao'],
                                                                0,
                                                                100,
                                                                '...'
                                                            )
                                                        ) ?>
                                                    </span>

                                                <?php endif; ?>

                                            </div>

                                        </td>

                                        <td>

                                            <span class="steps-badge">

                                                <i class="fa-solid fa-list-check"></i>

                                                <?= (int)$plano['quantidade_itens'] ?>

                                            </span>

                                        </td>

                                        <td>

                                            <strong class="plan-value">

                                                R$
                                                <?= number_format(
                                                    (float)$plano['valor_estimado'],
                                                    2,
                                                    ',',
                                                    '.'
                                                ) ?>

                                            </strong>

                                        </td>

                                        <td>

                                            <span
                                                class="status-badge status-<?= htmlspecialchars(
                                                                                $plano['status']
                                                                            ) ?>">

                                                <?php
                                                $statusIcon = match ($plano['status']) {
                                                    'planejamento'
                                                    => 'fa-regular fa-pen-to-square',
                                                    'em_andamento'
                                                    => 'fa-solid fa-spinner',
                                                    'concluido'
                                                    => 'fa-solid fa-circle-check',
                                                    'cancelado'
                                                    => 'fa-solid fa-ban',
                                                    default
                                                    => 'fa-solid fa-circle-question',
                                                };
                                                ?>

                                                <i class="<?= $statusIcon ?>"></i>

                                                <?= htmlspecialchars(
                                                    $statusLabels[$plano['status']] ?? $plano['status']
                                                ) ?>

                                            </span>

                                        </td>

                                        <td>

                                            <span class="date-text">

                                                <?= date(
                                                    'd/m/Y H:i',
                                                    strtotime(
                                                        $plano['data_atualizacao']
                                                    )
                                                ) ?>

                                            </span>

                                        </td>

                                        <td class="actions-cell">

                                            <div class="action-buttons">

                                                <a
                                                    href="visualizar_plano_tratamento.php?id=<?= (int)$plano['id'] ?>"
                                                    class="action-button view"
                                                    title="Visualizar plano">

                                                    <i class="fa-regular fa-eye"></i>

                                                </a>

                                                <a
                                                    href="editar_plano_tratamento.php?id=<?= (int)$plano['id'] ?>"
                                                    class="action-button edit"
                                                    title="Editar plano">

                                                    <i class="fa-solid fa-pen"></i>

                                                </a>

                                            </div>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php endif; ?>

            </section>

            <div class="catalog-note">

                <i class="fa-solid fa-circle-info"></i>

                <p>
                    O plano de tratamento representa o planejamento
                    clínico. Ele não cria automaticamente um orçamento
                    nem registra um procedimento realizado.
                </p>

            </div>

        </div>

    </main>

</body>

</html>