<?php

require_once 'config/auth.php';
require_once 'config/csrf.php';

exigirLogin();

require_once 'conexao/conexao.php';

/*
|--------------------------------------------------------------------------
| ID DO ORÇAMENTO
|--------------------------------------------------------------------------
*/

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    die("Orçamento não encontrado.");
}

/*
|--------------------------------------------------------------------------
| CONFIRMAR PAGAMENTO DE PARCELA
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        empty($_POST['csrf_token']) ||
        empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        http_response_code(403);
        die("Token de segurança inválido.");
    }

    $parcela_id = (int)($_POST['parcela_id'] ?? 0);

    if ($parcela_id > 0) {

        $stmtPagamento = $pdo->prepare("
            UPDATE parcelas
            SET
                status = 'paga',
                data_pagamento = CURDATE()
            WHERE id = ?
              AND orcamento_id = ?
        ");

        $stmtPagamento->execute([
            $parcela_id,
            $id
        ]);

        header("Location: visualizar_orcamento.php?id=" . $id);
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| BUSCAR ORÇAMENTO + PACIENTE
|--------------------------------------------------------------------------
|
| IMPORTANTE:
| A tabela informada por você é "orcamento" (singular).
|
*/

$stmt = $pdo->prepare("
    SELECT
        o.*,
        p.paciente,
        p.cpf,
        p.telefone,
        p.email
    FROM orcamento o
    JOIN prontuarios p
        ON o.paciente_id = p.id
    WHERE o.id = ?
");

$stmt->execute([$id]);

$orc = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$orc) {
    die("Orçamento não encontrado.");
}

/*
|--------------------------------------------------------------------------
| BUSCAR ITENS
|--------------------------------------------------------------------------
|
| A tabela informada por você é "orcamento_itens".
|
*/

$stmtItens = $pdo->prepare("
    SELECT *
    FROM orcamento_itens
    WHERE orcamento_id = ?
    ORDER BY id ASC
");

$stmtItens->execute([$id]);

$itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| ATUALIZAR PARCELAS VENCIDAS
|--------------------------------------------------------------------------
*/

$stmtAtrasadas = $pdo->prepare("
    UPDATE parcelas
    SET status = 'atrasada'
    WHERE orcamento_id = ?
      AND status = 'pendente'
      AND vencimento < CURDATE()
");

$stmtAtrasadas->execute([$id]);

/*
|--------------------------------------------------------------------------
| BUSCAR PARCELAS
|--------------------------------------------------------------------------
*/

$stmtParcelas = $pdo->prepare("
    SELECT *
    FROM parcelas
    WHERE orcamento_id = ?
    ORDER BY numero_parcela ASC
");

$stmtParcelas->execute([$id]);

$parcelas = $stmtParcelas->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| CALCULAR TOTAL DOS ITENS
|--------------------------------------------------------------------------
*/

$total_itens = 0;

foreach ($itens as $item) {

    $quantidade = (float)($item['quantidade'] ?? 0);
    $valor_unitario = (float)($item['valor_unitario'] ?? 0);

    $total_itens += $quantidade * $valor_unitario;
}

/*
|--------------------------------------------------------------------------
| CALCULAR PROGRESSO DAS PARCELAS
|--------------------------------------------------------------------------
*/

$qtd_total = count($parcelas);
$qtd_pagas = 0;

foreach ($parcelas as $parcela) {

    if (($parcela['status'] ?? '') === 'paga') {
        $qtd_pagas++;
    }
}

$progresso = $qtd_total > 0
    ? round(($qtd_pagas / $qtd_total) * 100)
    : 0;

/*
|--------------------------------------------------------------------------
| STATUS
|--------------------------------------------------------------------------
*/

$status = $orc['status'] ?? 'pendente';

$statusLabels = [
    'pendente' => 'Pendente',
    'aprovado' => 'Aprovado',
    'aprovada' => 'Aprovado',
    'cancelado' => 'Cancelado',
    'cancelada' => 'Cancelado',
    'concluido' => 'Concluído',
    'concluida' => 'Concluído'
];

$statusTexto = $statusLabels[$status] ?? ucfirst($status);

/*
|--------------------------------------------------------------------------
| VALIDADE
|--------------------------------------------------------------------------
*/

$validade = '—';

if (!empty($orc['validade'])) {

    $dataValidade = strtotime($orc['validade']);

    if ($dataValidade !== false) {
        $validade = date('d/m/Y', $dataValidade);
    }
}

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>
        Orçamento #<?= $id ?> | Dentech
    </title>

    <link
        rel="icon"
        type="image/png"
        href="img/icon.PNG">

    <link
        rel="stylesheet"
        href="css/global.css">

    <link
        rel="stylesheet"
        href="css/variables.css">

    <link
        rel="stylesheet"
        href="css/layout.css">

    <link
        rel="stylesheet"
        href="css/navbar.css">

    <link
        rel="stylesheet"
        href="css/vis_orcamento.css">

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

</head>

<body>

    <?php include 'navbar.php'; ?>


    <main class="content">

        <div class="orc-container">

            <!-- =====================================================
             CABEÇALHO
        ====================================================== -->

            <div class="header-actions">

                <div class="header-info">

                    <div class="breadcrumb">

                        <span>Orçamentos</span>

                        <span class="breadcrumb-separator">
                            /
                        </span>

                        <span>
                            Orçamento #<?= $id ?>
                        </span>

                    </div>

                    <div class="title-row">

                        <h1>
                            Orçamento #<?= $id ?>
                        </h1>

                        <span class="status-badge status-<?= htmlspecialchars($status) ?>">
                            <?= htmlspecialchars($statusTexto) ?>
                        </span>

                    </div>

                    <p>
                        Visualização completa do orçamento
                    </p>

                </div>


                <div class="btn-group">

                    <a
                        href="editar_orcamento.php?id=<?= $id ?>"
                        class="btn btn-primary">

                        <i class="fa-solid fa-pen"></i>

                        Editar

                    </a>


                    <a
                        href="gerar_orcamento_pdf.php?id=<?= $id ?>"
                        target="_blank"
                        class="btn btn-success">

                        <i class="fa-solid fa-file-pdf"></i>

                        Baixar PDF

                    </a>


                    <a
                        href="orcamento.php"
                        class="btn btn-outline">

                        <i class="fa-solid fa-arrow-left"></i>

                        Voltar

                    </a>

                </div>

            </div>


            <!-- =====================================================
             DADOS DO PACIENTE
        ====================================================== -->

            <section class="card">

                <div class="section-title">

                    <div class="section-icon">

                        <i class="fa-solid fa-user"></i>

                    </div>

                    <div>

                        <h2>
                            Dados do Paciente
                        </h2>

                        <p>
                            Informações do paciente relacionado ao orçamento.
                        </p>

                    </div>

                </div>


                <div class="info-grid">

                    <div class="info-item">

                        <label>
                            Nome
                        </label>

                        <span>
                            <?= htmlspecialchars($orc['paciente'] ?? '—') ?>
                        </span>

                    </div>


                    <div class="info-item">

                        <label>
                            CPF
                        </label>

                        <span>
                            <?= !empty($orc['cpf'])
                                ? htmlspecialchars($orc['cpf'])
                                : '—'
                            ?>
                        </span>

                    </div>


                    <div class="info-item">

                        <label>
                            Telefone
                        </label>

                        <span>
                            <?= !empty($orc['telefone'])
                                ? htmlspecialchars($orc['telefone'])
                                : '—'
                            ?>
                        </span>

                    </div>


                    <div class="info-item">

                        <label>
                            E-mail
                        </label>

                        <span>
                            <?= !empty($orc['email'])
                                ? htmlspecialchars($orc['email'])
                                : '—'
                            ?>
                        </span>

                    </div>


                    <div class="info-item">

                        <label>
                            Validade
                        </label>

                        <span>
                            <?= $validade ?>
                        </span>

                    </div>

                </div>

            </section>


            <!-- =====================================================
             PROCEDIMENTOS
        ====================================================== -->

            <section class="card">

                <div class="section-title">

                    <div class="section-icon">

                        <i class="fa-solid fa-tooth"></i>

                    </div>

                    <div>

                        <h2>
                            Procedimentos
                        </h2>

                        <p>
                            Procedimentos e valores incluídos neste orçamento.
                        </p>

                    </div>

                </div>


                <?php if (empty($itens)): ?>

                    <div class="empty-state">

                        <i class="fa-solid fa-file-circle-xmark"></i>

                        <p>
                            Nenhum item registrado neste orçamento.
                        </p>

                    </div>

                <?php else: ?>

                    <div class="table-wrapper">

                        <table class="orcamento-table">

                            <thead>

                                <tr>

                                    <th>
                                        Descrição
                                    </th>

                                    <th class="center">
                                        Qtd.
                                    </th>

                                    <th class="right">
                                        Valor unitário
                                    </th>

                                    <th class="right">
                                        Subtotal
                                    </th>

                                </tr>

                            </thead>


                            <tbody>

                                <?php foreach ($itens as $item): ?>

                                    <?php

                                    $quantidade = (float)($item['quantidade'] ?? 0);

                                    $valorUnitario = (float)($item['valor_unitario'] ?? 0);

                                    $subtotal = $quantidade * $valorUnitario;

                                    ?>

                                    <tr>

                                        <td>

                                            <?= htmlspecialchars(
                                                $item['descricao'] ?? '—'
                                            ) ?>

                                        </td>


                                        <td class="center">

                                            <?= rtrim(
                                                rtrim(
                                                    number_format(
                                                        $quantidade,
                                                        2,
                                                        ',',
                                                        '.'
                                                    ),
                                                    '0'
                                                ),
                                                ','
                                            ) ?>

                                        </td>


                                        <td class="right">

                                            R$
                                            <?= number_format(
                                                $valorUnitario,
                                                2,
                                                ',',
                                                '.'
                                            ) ?>

                                        </td>


                                        <td class="right subtotal">

                                            R$
                                            <?= number_format(
                                                $subtotal,
                                                2,
                                                ',',
                                                '.'
                                            ) ?>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>


                    <!-- TOTAL -->

                    <div class="total-box">

                        <div>

                            <span class="total-label">
                                Valor Total do Orçamento
                            </span>

                        </div>

                        <strong class="total-value">

                            R$
                            <?= number_format(
                                $total_itens,
                                2,
                                ',',
                                '.'
                            ) ?>

                        </strong>

                    </div>

                <?php endif; ?>

            </section>


            <!-- =====================================================
             OBSERVAÇÕES
        ====================================================== -->

            <?php if (!empty($orc['observacoes'])): ?>

                <section class="card">

                    <div class="section-title">

                        <div class="section-icon">

                            <i class="fa-solid fa-note-sticky"></i>

                        </div>

                        <div>

                            <h2>
                                Observações
                            </h2>

                            <p>
                                Informações adicionais do orçamento.
                            </p>

                        </div>

                    </div>


                    <div class="observacoes">

                        <?= nl2br(
                            htmlspecialchars(
                                $orc['observacoes']
                            )
                        ) ?>

                    </div>

                </section>

            <?php endif; ?>


            <!-- =====================================================
             PARCELAS
        ====================================================== -->

            <?php if (!empty($parcelas)): ?>

                <section class="card parcelas-section">

                    <div class="section-title">

                        <div class="section-icon">

                            <i class="fa-solid fa-credit-card"></i>

                        </div>

                        <div>

                            <h2>
                                Controle de Parcelas
                            </h2>

                            <p>
                                Acompanhe os pagamentos deste orçamento.
                            </p>

                        </div>

                        <span class="parcelas-resumo">

                            <?= $qtd_pagas ?>
                            /
                            <?= $qtd_total ?>
                            pagas

                        </span>

                    </div>


                    <div class="table-wrapper">

                        <table class="parcelas-table">

                            <thead>

                                <tr>

                                    <th>
                                        Parcela
                                    </th>

                                    <th>
                                        Vencimento
                                    </th>

                                    <th class="right">
                                        Valor
                                    </th>

                                    <th class="center">
                                        Status
                                    </th>

                                    <th class="center">
                                        Ação
                                    </th>

                                </tr>

                            </thead>


                            <tbody>

                                <?php foreach ($parcelas as $parcela): ?>

                                    <?php

                                    $statusParcela =
                                        $parcela['status'] ?? 'pendente';

                                    $statusParcelaLabels = [

                                        'pendente' => 'Pendente',

                                        'paga' => 'Paga',

                                        'atrasada' => 'Atrasada'

                                    ];

                                    $statusParcelaTexto =
                                        $statusParcelaLabels[$statusParcela]
                                        ?? ucfirst($statusParcela);

                                    ?>

                                    <tr>

                                        <td>

                                            <strong>
                                                <?= (int)$parcela['numero_parcela'] ?>ª
                                            </strong>

                                        </td>


                                        <td>

                                            <?php

                                            if (!empty($parcela['vencimento'])) {

                                                echo date(
                                                    'd/m/Y',
                                                    strtotime(
                                                        $parcela['vencimento']
                                                    )
                                                );
                                            } else {

                                                echo '—';
                                            }

                                            ?>

                                        </td>


                                        <td class="right">

                                            R$
                                            <?= number_format(
                                                (float)$parcela['valor'],
                                                2,
                                                ',',
                                                '.'
                                            ) ?>

                                        </td>


                                        <td class="center">

                                            <span class="status-badge parcela-status status-<?= htmlspecialchars($statusParcela) ?>">

                                                <?= htmlspecialchars(
                                                    $statusParcelaTexto
                                                ) ?>

                                            </span>

                                        </td>


                                        <td class="center">

                                            <?php if ($statusParcela === 'pendente'): ?>

                                                <form
                                                    method="POST"
                                                    class="form-pagamento"
                                                    onsubmit="return confirm('Confirmar pagamento desta parcela?');">

                                                    <input
                                                        type="hidden"
                                                        name="csrf_token"
                                                        value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                                                    <input
                                                        type="hidden"
                                                        name="parcela_id"
                                                        value="<?= (int)$parcela['id'] ?>">

                                                    <button
                                                        type="submit"
                                                        name="marcar_paga"
                                                        class="btn-pagar">

                                                        <i class="fa-solid fa-check"></i>

                                                        Confirmar

                                                    </button>

                                                </form>

                                            <?php elseif ($statusParcela === 'paga'): ?>

                                                <span class="pagamento-confirmado">

                                                    <i class="fa-solid fa-circle-check"></i>

                                                    <?php if (!empty($parcela['data_pagamento'])): ?>

                                                        Pago em
                                                        <?= date(
                                                            'd/m/Y',
                                                            strtotime(
                                                                $parcela['data_pagamento']
                                                            )
                                                        ) ?>

                                                    <?php else: ?>

                                                        Pago

                                                    <?php endif; ?>

                                                </span>

                                            <?php else: ?>

                                                <span class="pagamento-atrasado">

                                                    <i class="fa-solid fa-triangle-exclamation"></i>

                                                    Em atraso

                                                </span>

                                            <?php endif; ?>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>


                    <!-- =================================================
                     PROGRESSO
                ================================================== -->

                    <div class="progress-section">

                        <div class="progress-header">

                            <span>
                                Progresso dos pagamentos
                            </span>

                            <strong>
                                <?= $progresso ?>%
                            </strong>

                        </div>


                        <div class="progress-bar">

                            <div
                                class="progress-fill"
                                style="width: <?= $progresso ?>%;">
                            </div>

                        </div>


                        <div class="progress-text">

                            <?= $qtd_pagas ?>
                            de
                            <?= $qtd_total ?>
                            parcelas pagas

                        </div>

                    </div>

                </section>

            <?php endif; ?>


            <!-- =====================================================
             RODAPÉ
        ====================================================== -->

            <div class="footer-note">

                <i class="fa-solid fa-circle-info"></i>

                Dentech <?= date('Y') ?>
                |
                Documento gerado automaticamente.
                Valores sujeitos a alteração conforme avaliação clínica.

            </div>

        </div>

    </main>

</body>

</html>