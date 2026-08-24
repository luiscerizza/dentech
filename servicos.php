<?php

require_once 'config/auth.php';
require_once 'config/csrf.php';
require_once 'conexao/conexao.php';

exigirLogin();

/*
|--------------------------------------------------------------------------
| FILTROS
|--------------------------------------------------------------------------
*/

$busca = trim($_GET['busca'] ?? '');
$status = $_GET['status'] ?? 'ativos';

$where = [];
$params = [];

if ($busca !== '') {
    $where[] = "
        (
            s.nome LIKE ?
            OR s.descricao LIKE ?
        )
    ";

    $buscaLike = '%' . $busca . '%';

    $params[] = $buscaLike;
    $params[] = $buscaLike;
}

if ($status === 'ativos') {
    $where[] = 's.ativo = 1';
} elseif ($status === 'inativos') {
    $where[] = 's.ativo = 0';
}

/*
|--------------------------------------------------------------------------
| BUSCAR SERVIÇOS
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        s.id,
        s.nome,
        s.descricao,
        s.valor_sugerido,
        s.ativo,
        s.data_criacao
    FROM servicos s
";

if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= "
    ORDER BY
        s.ativo DESC,
        s.nome ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$servicos = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| CONTADORES
|--------------------------------------------------------------------------
*/

$stmtResumo = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN ativo = 1 THEN 1 ELSE 0 END) AS ativos,
        SUM(CASE WHEN ativo = 0 THEN 1 ELSE 0 END) AS inativos
    FROM servicos
");

$resumo = $stmtResumo->fetch(PDO::FETCH_ASSOC);

$totalServicos = (int)($resumo['total'] ?? 0);
$totalAtivos = (int)($resumo['ativos'] ?? 0);
$totalInativos = (int)($resumo['inativos'] ?? 0);

/*
|--------------------------------------------------------------------------
| MENSAGENS
|--------------------------------------------------------------------------
*/

$sucesso = $_GET['sucesso'] ?? '';
$erro = $_GET['erro'] ?? '';

$mensagensSucesso = [
    'criado' => 'Serviço criado com sucesso.',
    'editado' => 'Serviço atualizado com sucesso.',
    'ativado' => 'Serviço ativado com sucesso.',
    'desativado' => 'Serviço desativado com sucesso.',
];

$mensagensErro = [
    'servico_invalido' => 'Serviço inválido.',
    'servico_nao_encontrado' => 'Serviço não encontrado.',
    'nao_foi_possivel_alterar' => 'Não foi possível alterar o status do serviço.',
];

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Serviços - Dentech</title>

    <link rel="stylesheet" href="css/global.css">
    <link rel="stylesheet" href="css/variables.css">
    <link rel="stylesheet" href="css/layout.css">
    <link rel="stylesheet" href="css/navbar.css">
    <link rel="stylesheet" href="css/servicos.css">

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

    <main class="servicos-page">

        <div class="servicos-container">

            <header class="page-header">

                <div>

                    <span class="page-kicker">
                        CATÁLOGO
                    </span>

                    <h1>
                        Serviços
                    </h1>

                    <p>
                        Cadastre e gerencie os serviços disponíveis
                        para futuros orçamentos.
                    </p>

                </div>

                <a
                    href="novo_servico.php"
                    class="btn btn-primary">
                    <i class="fa-solid fa-plus"></i>
                    Novo serviço
                </a>

            </header>

            <?php if (
                $sucesso !== '' &&
                isset($mensagensSucesso[$sucesso])
            ): ?>

                <div class="message message-success">

                    <i class="fa-solid fa-circle-check"></i>

                    <span>
                        <?= htmlspecialchars($mensagensSucesso[$sucesso]) ?>
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
                        <?= htmlspecialchars($mensagensErro[$erro]) ?>
                    </span>

                </div>

            <?php endif; ?>

            <section class="summary-grid">

                <article class="summary-card">

                    <div class="summary-icon neutral">
                        <i class="fa-solid fa-list"></i>
                    </div>

                    <div>
                        <span>
                            Total
                        </span>

                        <strong>
                            <?= $totalServicos ?>
                        </strong>
                    </div>

                </article>

                <article class="summary-card">

                    <div class="summary-icon active">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>

                    <div>
                        <span>
                            Ativos
                        </span>

                        <strong>
                            <?= $totalAtivos ?>
                        </strong>
                    </div>

                </article>

                <article class="summary-card">

                    <div class="summary-icon inactive">
                        <i class="fa-solid fa-circle-pause"></i>
                    </div>

                    <div>
                        <span>
                            Inativos
                        </span>

                        <strong>
                            <?= $totalInativos ?>
                        </strong>
                    </div>

                </article>

            </section>

            <section class="filter-card">

                <form
                    method="GET"
                    class="filter-form">

                    <div class="filter-field search-field">

                        <label for="busca">
                            Buscar
                        </label>

                        <div class="input-with-icon">

                            <i class="fa-solid fa-magnifying-glass"></i>

                            <input
                                type="text"
                                id="busca"
                                name="busca"
                                value="<?= htmlspecialchars($busca) ?>"
                                placeholder="Nome ou descrição...">

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
                                value="ativos"
                                <?= $status === 'ativos' ? 'selected' : '' ?>>
                                Ativos
                            </option>

                            <option
                                value="inativos"
                                <?= $status === 'inativos' ? 'selected' : '' ?>>
                                Inativos
                            </option>

                            <option
                                value="todos"
                                <?= $status === 'todos' ? 'selected' : '' ?>>
                                Todos
                            </option>

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
                            href="servicos.php"
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
                            CATÁLOGO DE SERVIÇOS
                        </span>

                        <h2>
                            Serviços cadastrados
                        </h2>

                    </div>

                    <span class="result-count">
                        <?= count($servicos) ?>
                        resultado(s)
                    </span>

                </div>

                <?php if (empty($servicos)): ?>

                    <div class="empty-state">

                        <div class="empty-icon">
                            <i class="fa-regular fa-folder-open"></i>
                        </div>

                        <strong>
                            Nenhum serviço encontrado
                        </strong>

                        <span>
                            Ajuste os filtros ou cadastre um novo serviço.
                        </span>

                        <a
                            href="novo_servico.php"
                            class="btn btn-primary">
                            <i class="fa-solid fa-plus"></i>
                            Cadastrar serviço
                        </a>

                    </div>

                <?php else: ?>

                    <div class="table-wrapper">

                        <table class="services-table">

                            <thead>

                                <tr>
                                    <th>Serviço</th>
                                    <th>Descrição</th>
                                    <th>Valor sugerido</th>
                                    <th>Status</th>
                                    <th>Cadastro</th>
                                    <th class="actions-column">Ações</th>
                                </tr>

                            </thead>

                            <tbody>

                                <?php foreach ($servicos as $servico): ?>

                                    <tr>

                                        <td>

                                            <div class="service-name">

                                                <div class="service-icon">
                                                    <i class="fa-solid fa-tooth"></i>
                                                </div>

                                                <div>

                                                    <strong>
                                                        <?= htmlspecialchars($servico['nome']) ?>
                                                    </strong>

                                                </div>

                                            </div>

                                        </td>

                                        <td>

                                            <div class="service-description">

                                                <?php if (!empty($servico['descricao'])): ?>

                                                    <?= htmlspecialchars($servico['descricao']) ?>

                                                <?php else: ?>

                                                    <span class="description-empty">
                                                        Sem descrição
                                                    </span>

                                                <?php endif; ?>

                                            </div>

                                        </td>

                                        <td>

                                            <strong class="service-value">
                                                R$
                                                <?= number_format(
                                                    (float)$servico['valor_sugerido'],
                                                    2,
                                                    ',',
                                                    '.'
                                                ) ?>
                                            </strong>

                                        </td>

                                        <td>

                                            <?php if ((int)$servico['ativo'] === 1): ?>

                                                <span class="status-badge active">
                                                    <i class="fa-solid fa-circle-check"></i>
                                                    Ativo
                                                </span>

                                            <?php else: ?>

                                                <span class="status-badge inactive">
                                                    <i class="fa-solid fa-circle-pause"></i>
                                                    Inativo
                                                </span>

                                            <?php endif; ?>

                                        </td>

                                        <td>

                                            <span class="date-text">
                                                <?= date(
                                                    'd/m/Y',
                                                    strtotime($servico['data_criacao'])
                                                ) ?>
                                            </span>

                                        </td>

                                        <td class="actions-cell">

                                            <div class="action-buttons">

                                                <a
                                                    href="editar_servico.php?id=<?= (int)$servico['id'] ?>"
                                                    class="action-button edit"
                                                    title="Editar serviço">
                                                    <i class="fa-solid fa-pen"></i>
                                                </a>

                                                <form
                                                    method="POST"
                                                    action="alternar_status_servico.php"
                                                    class="status-form"
                                                    onsubmit="return confirm(
                                                'Deseja realmente alterar o status deste serviço?'
                                            );">

                                                    <?= csrf_field() ?>

                                                    <input
                                                        type="hidden"
                                                        name="id"
                                                        value="<?= (int)$servico['id'] ?>">

                                                    <?php if ((int)$servico['ativo'] === 1): ?>

                                                        <button
                                                            type="submit"
                                                            class="action-button toggle deactivate"
                                                            title="Desativar serviço">
                                                            <i class="fa-solid fa-pause"></i>
                                                        </button>

                                                    <?php else: ?>

                                                        <button
                                                            type="submit"
                                                            class="action-button toggle activate"
                                                            title="Ativar serviço">
                                                            <i class="fa-solid fa-play"></i>
                                                        </button>

                                                    <?php endif; ?>

                                                </form>

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
                    O valor sugerido serve apenas como referência.
                    Ao criar um orçamento, o valor poderá ser ajustado
                    conforme a avaliação e negociação com o paciente.
                </p>

            </div>

        </div>

    </main>

</body>

</html>