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
    die("ID do orçamento não informado.");
}


/*
|--------------------------------------------------------------------------
| PROCESSAR POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    validar_csrf();

    $acao = $_POST['acao'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | CONFIRMAR ORÇAMENTO
    |--------------------------------------------------------------------------
    */

    if ($acao === 'aceitar_orcamento') {

        $stmt = $pdo->prepare("
            UPDATE orcamentos
            SET status = 'aceito'
            WHERE id = ?
              AND status = 'pendente'
        ");

        $stmt->execute([
            $id
        ]);


        header(
            "Location: visualizar_orcamento.php?id=" .
                $id
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | RECUSAR ORÇAMENTO
    |--------------------------------------------------------------------------
    */

    if ($acao === 'recusar_orcamento') {

        $stmt = $pdo->prepare("
            UPDATE orcamentos
            SET status = 'recusado'
            WHERE id = ?
              AND status = 'pendente'
        ");

        $stmt->execute([
            $id
        ]);


        header(
            "Location: visualizar_orcamento.php?id=" .
                $id
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | PAGAR PARCELA
    |--------------------------------------------------------------------------
    */

    if ($acao === 'pagar_parcela') {

        $parcela_id = (int)(
            $_POST['parcela_id'] ?? 0
        );


        if ($parcela_id > 0) {

            /*
            | Atualiza somente uma parcela
            | pertencente a este orçamento.
            */

            $stmt = $pdo->prepare("
                UPDATE parcelas

                SET
                    status = 'paga',
                    data_pagamento = CURDATE()

                WHERE id = ?
                  AND orcamento_id = ?
                  AND status IN ('pendente', 'atrasada')
            ");

            $stmt->execute([
                $parcela_id,
                $id
            ]);
        }


        /*
        | Volta para a própria página
        */

        header(
            "Location: visualizar_orcamento.php?id=" .
                $id
        );

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| BUSCAR ORÇAMENTO + PACIENTE
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        o.*,
        p.paciente,
        p.cpf,
        p.telefone,
        p.email

    FROM orcamentos o

    JOIN prontuarios p
        ON o.paciente_id = p.id

    WHERE o.id = ?
");

$stmt->execute([
    $id
]);

$orc = $stmt->fetch(
    PDO::FETCH_ASSOC
);


if (!$orc) {
    die("Orçamento não encontrado.");
}


/*
|--------------------------------------------------------------------------
| STATUS
|--------------------------------------------------------------------------
*/

$status_orcamento =
    strtolower(
        trim(
            $orc['status'] ?? 'pendente'
        )
    );


/*
|--------------------------------------------------------------------------
| BUSCAR ITENS
|--------------------------------------------------------------------------
*/

$stmt_itens = $pdo->prepare("
    SELECT *
    FROM orcamentos_itens
    WHERE orcamento_id = ?
    ORDER BY id ASC
");

$stmt_itens->execute([
    $id
]);

$itens =
    $stmt_itens->fetchAll(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| ATUALIZAR PARCELAS VENCIDAS
|--------------------------------------------------------------------------
*/

$pdo->prepare("
    UPDATE parcelas

    SET status = 'atrasada'

    WHERE orcamento_id = ?

      AND status = 'pendente'

      AND vencimento < CURDATE()
")
    ->execute([
        $id
    ]);


/*
|--------------------------------------------------------------------------
| BUSCAR PARCELAS
|--------------------------------------------------------------------------
*/

$stmt_par = $pdo->prepare("
    SELECT *
    FROM parcelas
    WHERE orcamento_id = ?
    ORDER BY numero_parcela ASC
");

$stmt_par->execute([
    $id
]);

$parcelas =
    $stmt_par->fetchAll(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| CÁLCULO DO TOTAL
|--------------------------------------------------------------------------
*/

$total_itens = 0;

foreach ($itens as $item) {

    $quantidade =
        (int)($item['quantidade'] ?? 1);

    $valor =
        (float)($item['valor_unitario'] ?? 0);

    $total_itens +=
        $quantidade * $valor;
}


/*
|--------------------------------------------------------------------------
| CONTROLE DAS PARCELAS
|--------------------------------------------------------------------------
*/

$qtd_total =
    count($parcelas);

$qtd_pagas = 0;

$total_pago = 0;

$total_pendente = 0;


foreach ($parcelas as $p) {

    $valor_parcela =
        (float)($p['valor'] ?? 0);


    if ($p['status'] === 'paga') {

        $qtd_pagas++;

        $total_pago +=
            $valor_parcela;
    } else {

        $total_pendente +=
            $valor_parcela;
    }
}


$progresso =
    $qtd_total > 0
    ? round(
        ($qtd_pagas / $qtd_total) * 100
    )
    : 0;


/*
|--------------------------------------------------------------------------
| STATUS VISUAL
|--------------------------------------------------------------------------
*/

$status_texto = match ($status_orcamento) {

    'aceito' =>
    'Aceito',

    'recusado' =>
    'Recusado',

    default =>
    'Pendente'
};


?>

<!DOCTYPE html>

<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>
        Orçamento #<?= $id ?> - Dentech
    </title>


    <link
        rel="stylesheet"
        href="css/navbar.css">

    <link
        rel="stylesheet"
        href="css/vis_orcamento.css">

    <link
        rel="icon"
        type="image/png"
        href="img/icon.PNG">

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

                        <span>
                            Orçamentos
                        </span>

                        <span class="breadcrumb-separator">
                            /
                        </span>

                        <span>
                            Visualização
                        </span>

                    </div>


                    <div class="title-row">

                        <h1>
                            Orçamento #<?= $id ?>
                        </h1>


                        <span
                            class="status-badge
                        status-<?= htmlspecialchars($status_orcamento) ?>">

                            <?= htmlspecialchars(
                                $status_texto
                            ) ?>

                        </span>

                    </div>


                    <p>
                        Detalhes completos do orçamento.
                    </p>

                </div>


                <div class="btn-group">


                    <!-- =================================================
                     CONFIRMAR / RECUSAR
                ================================================== -->

                    <?php if ($status_orcamento === 'pendente'): ?>


                        <form
                            method="POST"
                            style="display:inline;">

                            <?= csrf_field() ?>


                            <input
                                type="hidden"
                                name="acao"
                                value="aceitar_orcamento">


                            <button
                                type="submit"
                                class="btn btn-success">

                                ✓ Confirmar

                            </button>

                        </form>


                        <form
                            method="POST"
                            style="display:inline;">

                            <?= csrf_field() ?>


                            <input
                                type="hidden"
                                name="acao"
                                value="recusar_orcamento">


                            <button
                                type="submit"
                                class="btn btn-danger">

                                ✕ Recusar

                            </button>

                        </form>


                    <?php endif; ?>


                    <!-- =================================================
                     EDITAR
                     Só aparece enquanto estiver pendente
                ================================================== -->

                    <?php if ($status_orcamento === 'pendente'): ?>

                        <a
                            href="editar_orcamento.php?id=<?= $id ?>"
                            class="btn btn-primary">

                            ✏️ Editar

                        </a>

                    <?php endif; ?>


                    <!-- =================================================
                     PDF
                ================================================== -->

                    <a
                        href="gerar_orcamento_pdf.php?id=<?= $id ?>"
                        target="_blank"
                        class="btn btn-success">

                        📥 Baixar PDF

                    </a>


                    <!-- =================================================
                     VOLTAR
                ================================================== -->

                    <a
                        href="orcamento.php"
                        class="btn btn-outline">

                        ← Voltar

                    </a>

                </div>

            </div>


            <!-- =====================================================
             AVISO DE STATUS
        ====================================================== -->

            <?php if ($status_orcamento === 'aceito'): ?>

                <div class="status-message status-message-success">

                    <i class="fa-solid fa-circle-check"></i>

                    <div>

                        <strong>
                            Orçamento confirmado
                        </strong>

                        <span>
                            Este orçamento foi aceito e não pode mais ser editado.
                        </span>

                    </div>

                </div>


            <?php elseif ($status_orcamento === 'recusado'): ?>

                <div class="status-message status-message-danger">

                    <i class="fa-solid fa-circle-xmark"></i>

                    <div>

                        <strong>
                            Orçamento recusado
                        </strong>

                        <span>
                            Este orçamento foi recusado e não pode mais ser editado.
                        </span>

                    </div>

                </div>


            <?php else: ?>

                <div class="status-message status-message-pending">

                    <i class="fa-solid fa-clock"></i>

                    <div>

                        <strong>
                            Orçamento pendente
                        </strong>

                        <span>
                            Confirme ou recuse o orçamento para finalizar sua situação.
                        </span>

                    </div>

                </div>

            <?php endif; ?>


            <!-- =====================================================
             DADOS DO PACIENTE
        ====================================================== -->

            <div class="card">

                <div class="section-title">

                    <div class="section-icon">

                        <i class="fa-solid fa-user"></i>

                    </div>

                    <div>

                        <h2>
                            Dados do Paciente
                        </h2>

                        <p>
                            Informações vinculadas ao orçamento
                        </p>

                    </div>

                </div>


                <div class="info-grid">


                    <div class="info-item">

                        <label>
                            Nome
                        </label>

                        <span>
                            <?= htmlspecialchars(
                                $orc['paciente']
                            ) ?>
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
                            Data de criação
                        </label>

                        <span>

                            <?= !empty($orc['data_criacao'])
                                ? date(
                                    'd/m/Y',
                                    strtotime(
                                        $orc['data_criacao']
                                    )
                                )
                                : '—'
                            ?>

                        </span>

                    </div>


                    <div class="info-item">

                        <label>
                            Validade
                        </label>

                        <span>

                            <?= !empty($orc['validade'])
                                ? date(
                                    'd/m/Y',
                                    strtotime(
                                        $orc['validade']
                                    )
                                )
                                : '—'
                            ?>

                        </span>

                    </div>


                </div>

            </div>


            <!-- =====================================================
             PROCEDIMENTOS
        ====================================================== -->

            <div class="card">

                <div class="section-title">

                    <div class="section-icon">

                        <i class="fa-solid fa-tooth"></i>

                    </div>

                    <div>

                        <h2>
                            Procedimentos
                        </h2>

                        <p>
                            Itens incluídos neste orçamento
                        </p>

                    </div>

                </div>


                <?php if (empty($itens)): ?>


                    <div class="empty-state">

                        <i class="fa-solid fa-box-open"></i>

                        <p>
                            Nenhum item registrado.
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
                                        Unitário
                                    </th>

                                    <th class="right">
                                        Subtotal
                                    </th>

                                </tr>

                            </thead>


                            <tbody>

                                <?php foreach ($itens as $item): ?>

                                    <?php

                                    $sub =
                                        (int)$item['quantidade']
                                        *
                                        (float)$item['valor_unitario'];

                                    ?>

                                    <tr>

                                        <td>

                                            <?= htmlspecialchars(
                                                $item['descricao']
                                            ) ?>

                                        </td>


                                        <td class="center">

                                            <?= (int)
                                            $item['quantidade']
                                            ?>

                                        </td>


                                        <td class="right">

                                            R$
                                            <?= number_format(
                                                $item['valor_unitario'],
                                                2,
                                                ',',
                                                '.'
                                            ) ?>

                                        </td>


                                        <td class="right subtotal">

                                            R$
                                            <?= number_format(
                                                $sub,
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


                <?php endif; ?>


                <div class="total-box">

                    <span class="total-label">

                        Valor Total do Orçamento

                    </span>


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

            </div>


            <!-- =====================================================
             OBSERVAÇÕES
        ====================================================== -->

            <?php if (!empty($orc['observacoes'])): ?>


                <div class="card">

                    <div class="section-title">

                        <div class="section-icon">

                            <i class="fa-solid fa-note-sticky"></i>

                        </div>

                        <div>

                            <h2>
                                Observações
                            </h2>

                            <p>
                                Informações adicionais
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

                </div>


            <?php endif; ?>


            <!-- =====================================================
             PARCELAS
        ====================================================== -->

            <?php if (!empty($parcelas)): ?>


                <div class="card parcelas-section">


                    <div class="section-title">

                        <div class="section-icon">

                            <i class="fa-solid fa-credit-card"></i>

                        </div>

                        <div>

                            <h2>
                                Controle de Parcelas
                            </h2>

                            <p>
                                <?= $qtd_pagas ?>
                                de
                                <?= $qtd_total ?>
                                parcelas pagas
                            </p>

                        </div>


                        <div class="parcelas-resumo">

                            R$
                            <?= number_format(
                                $total_pendente,
                                2,
                                ',',
                                '.'
                            ) ?>

                            pendente

                        </div>

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

                                <?php foreach ($parcelas as $p): ?>


                                    <?php

                                    $status_parcela =
                                        $p['status'];

                                    ?>


                                    <tr>

                                        <td>

                                            <strong>
                                                <?= (int)
                                                $p['numero_parcela']
                                                ?>ª
                                            </strong>

                                        </td>


                                        <td>

                                            <?= date(
                                                'd/m/Y',
                                                strtotime(
                                                    $p['vencimento']
                                                )
                                            ) ?>

                                        </td>


                                        <td class="right">

                                            R$
                                            <?= number_format(
                                                $p['valor'],
                                                2,
                                                ',',
                                                '.'
                                            ) ?>

                                        </td>


                                        <td class="center">


                                            <?php if (
                                                $status_parcela === 'paga'
                                            ): ?>

                                                <span
                                                    class="status-badge parcela-status status-paga">

                                                    ✓ Paga

                                                </span>


                                            <?php elseif (
                                                $status_parcela === 'atrasada'
                                            ): ?>

                                                <span
                                                    class="status-badge parcela-status status-atrasada">

                                                    Atrasada

                                                </span>


                                            <?php else: ?>

                                                <span
                                                    class="status-badge parcela-status status-pendente">

                                                    Pendente

                                                </span>

                                            <?php endif; ?>


                                        </td>


                                        <td class="center">


                                            <?php if (
                                                $status_parcela === 'paga'
                                            ): ?>


                                                <span
                                                    class="pagamento-confirmado">

                                                    <i class="fa-solid fa-circle-check"></i>

                                                    <?php if (
                                                        !empty($p['data_pagamento'])
                                                    ): ?>

                                                        Pago em
                                                        <?= date(
                                                            'd/m/Y',
                                                            strtotime(
                                                                $p['data_pagamento']
                                                            )
                                                        ) ?>

                                                    <?php else: ?>

                                                        Pago

                                                    <?php endif; ?>

                                                </span>


                                            <?php elseif (
                                                in_array(
                                                    $status_parcela,
                                                    [
                                                        'pendente',
                                                        'atrasada'
                                                    ],
                                                    true
                                                )
                                            ): ?>


                                                <form
                                                    method="POST"
                                                    class="form-pagamento">

                                                    <?= csrf_field() ?>


                                                    <input
                                                        type="hidden"
                                                        name="acao"
                                                        value="pagar_parcela">


                                                    <input
                                                        type="hidden"
                                                        name="parcela_id"
                                                        value="<?= (int)$p['id'] ?>">


                                                    <button
                                                        type="submit"
                                                        class="btn-pagar">

                                                        <i class="fa-solid fa-check"></i>

                                                        Pagar

                                                    </button>

                                                </form>


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
                                Pagamentos
                            </span>

                            <strong>
                                <?= $progresso ?>%
                            </strong>

                        </div>


                        <div class="progress-bar">

                            <div
                                class="progress-fill"
                                style="width: <?= $progresso ?>%;"></div>

                        </div>


                        <div class="progress-text">

                            R$
                            <?= number_format(
                                $total_pago,
                                2,
                                ',',
                                '.'
                            ) ?>

                            pagos de

                            R$
                            <?= number_format(
                                $total_itens,
                                2,
                                ',',
                                '.'
                            ) ?>

                        </div>

                    </div>


                </div>


            <?php endif; ?>


            <!-- =====================================================
             RODAPÉ
        ====================================================== -->

            <div class="footer-note">

                <i class="fa-solid fa-shield-halved"></i>

                Dentech <?= date('Y') ?>

                |

                Documento gerado automaticamente.

            </div>


        </div>

    </main>


</body>

</html>