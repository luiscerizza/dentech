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

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    die("ID do orçamento não informado.");
}


/*
|--------------------------------------------------------------------------
| PROCESSAMENTO DOS POSTS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
    |--------------------------------------------------------------------------
    | VALIDAÇÃO CSRF
    |--------------------------------------------------------------------------
    */

    if (
        empty($_POST['csrf_token']) ||
        empty($_SESSION['csrf_token']) ||
        !hash_equals(
            $_SESSION['csrf_token'],
            $_POST['csrf_token']
        )
    ) {
        http_response_code(403);
        die("Token de segurança inválido.");
    }


    /*
    |--------------------------------------------------------------------------
    | AÇÃO SOLICITADA
    |--------------------------------------------------------------------------
    */

    $acao = $_POST['acao'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | CONFIRMAR ORÇAMENTO
    |--------------------------------------------------------------------------
    */

    if ($acao === 'confirmar') {

        $stmt = $pdo->prepare("
            UPDATE orcamentos
            SET status = 'aceito'
            WHERE id = ?
              AND status = 'pendente'
        ");

        $stmt->execute([$id]);

        header("Location: visualizar_orcamento.php?id=" . $id);
        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | RECUSAR ORÇAMENTO
    |--------------------------------------------------------------------------
    */

    if ($acao === 'recusar') {

        $stmt = $pdo->prepare("
            UPDATE orcamentos
            SET status = 'recusado'
            WHERE id = ?
              AND status = 'pendente'
        ");

        $stmt->execute([$id]);

        header("Location: visualizar_orcamento.php?id=" . $id);
        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | PAGAMENTO DE PARCELA
    |
    | Ao pagar uma parcela, o orçamento passa automaticamente
    | para aceito.
    |--------------------------------------------------------------------------
    */

    if ($acao === 'pagar_parcela') {

        $parcela_id = (int) ($_POST['parcela_id'] ?? 0);

        if ($parcela_id > 0) {

            /*
            |--------------------------------------------------------------------------
            | Confirma o pagamento da parcela
            |--------------------------------------------------------------------------
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


            /*
            |--------------------------------------------------------------------------
            | Ao pagar, confirma automaticamente o orçamento
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                UPDATE orcamentos
                SET status = 'aceito'
                WHERE id = ?
                  AND status = 'pendente'
            ");

            $stmt->execute([$id]);
        }

        header("Location: visualizar_orcamento.php?id=" . $id);
        exit;
    }
}


/*
|--------------------------------------------------------------------------
| BUSCAR ORÇAMENTO + DADOS DO PACIENTE
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

$stmt->execute([$id]);

$orc = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$orc) {
    die("Orçamento não encontrado.");
}


/*
|--------------------------------------------------------------------------
| BUSCAR ITENS DO ORÇAMENTO
|--------------------------------------------------------------------------
*/

$stmt_itens = $pdo->prepare("
    SELECT *
    FROM orcamentos_itens
    WHERE orcamento_id = ?
    ORDER BY id ASC
");

$stmt_itens->execute([$id]);

$itens = $stmt_itens->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| ATUALIZAR PARCELAS VENCIDAS
|--------------------------------------------------------------------------
|
| Somente parcelas ainda pendentes são alteradas para atrasada.
|
*/

$pdo->prepare("
    UPDATE parcelas
    SET status = 'atrasada'
    WHERE orcamento_id = ?
      AND status = 'pendente'
      AND vencimento < CURDATE()
")->execute([$id]);


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

$stmt_par->execute([$id]);

$parcelas = $stmt_par->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| CALCULAR TOTAL DO ORÇAMENTO
|--------------------------------------------------------------------------
*/

$total_itens = 0;

foreach ($itens as $item) {

    $quantidade = (int) $item['quantidade'];
    $valor = (float) $item['valor_unitario'];

    $total_itens += $quantidade * $valor;
}


/*
|--------------------------------------------------------------------------
| CALCULAR PROGRESSO DAS PARCELAS
|--------------------------------------------------------------------------
*/

$qtd_total = count($parcelas);

$qtd_pagas = 0;

foreach ($parcelas as $p) {

    if ($p['status'] === 'paga') {
        $qtd_pagas++;
    }
}

$progresso = $qtd_total > 0
    ? round(($qtd_pagas / $qtd_total) * 100)
    : 0;


/*
|--------------------------------------------------------------------------
| STATUS DO ORÇAMENTO
|--------------------------------------------------------------------------
*/

$status_orcamento = $orc['status'] ?? 'pendente';

?>

<!DOCTYPE html>

<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>
        Orçamento #<?= htmlspecialchars($id) ?> - Dentech
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

    <style>
        /*
        |--------------------------------------------------------------------------
        | BOTÕES DO CABEÇALHO
        |--------------------------------------------------------------------------
        */

        .btn-group {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;

            min-height: 42px;

            padding: 10px 16px;

            border-radius: 8px;

            text-decoration: none;

            font-size: 14px;
            font-weight: 600;

            border: 1px solid transparent;

            cursor: pointer;

            transition: all 0.2s ease;

            box-sizing: border-box;
        }


        /*
        |--------------------------------------------------------------------------
        | VOLTAR
        |--------------------------------------------------------------------------
        */

        .btn-voltar {
            background: #ffffff;
            color: #2d3748;
            border-color: #cbd5e0;
        }

        .btn-voltar:hover {
            background: #f7fafc;
        }


        /*
        |--------------------------------------------------------------------------
        | PDF
        |--------------------------------------------------------------------------
        */

        .btn-pdf {
            background: #ffffff;
            color: #2b6cb0;
            border-color: #2b6cb0;
        }

        .btn-pdf:hover {
            background: #ebf8ff;
        }


        /*
        |--------------------------------------------------------------------------
        | EDITAR
        |--------------------------------------------------------------------------
        */

        .btn-editar {
            background: #2563eb;
            color: #ffffff;
            border-color: #2563eb;
        }

        .btn-editar:hover {
            background: #1d4ed8;
        }


        /*
        |--------------------------------------------------------------------------
        | CONFIRMAR
        |--------------------------------------------------------------------------
        */

        .btn-confirmar {
            background: #198754;
            color: #ffffff;
            border-color: #198754;
        }

        .btn-confirmar:hover {
            background: #157347;
        }


        /*
        |--------------------------------------------------------------------------
        | RECUSAR
        |--------------------------------------------------------------------------
        */

        .btn-recusar {
            background: #dc3545;
            color: #ffffff;
            border-color: #dc3545;
        }

        .btn-recusar:hover {
            background: #bb2d3b;
        }


        /*
        |--------------------------------------------------------------------------
        | BOTÃO PAGAR PARCELA
        |--------------------------------------------------------------------------
        */

        .btn-pagar {
            display: inline-flex;

            align-items: center;
            justify-content: center;

            padding: 7px 12px;

            border: 0;
            border-radius: 6px;

            background: #198754;
            color: #ffffff;

            font-size: 12px;
            font-weight: 600;

            cursor: pointer;

            transition: 0.2s ease;
        }

        .btn-pagar:hover {
            background: #157347;
        }


        /*
        |--------------------------------------------------------------------------
        | STATUS DO ORÇAMENTO
        |--------------------------------------------------------------------------
        */

        .status-badge.status-pendente {
            background: #fff3cd;
            color: #856404;
        }

        .status-badge.status-aceito {
            background: #d1e7dd;
            color: #0f5132;
        }

        .status-badge.status-recusado {
            background: #f8d7da;
            color: #842029;
        }


        /*
        |--------------------------------------------------------------------------
        | RESPONSIVO
        |--------------------------------------------------------------------------
        */

        @media (max-width: 900px) {

            .btn-group {
                width: 100%;
            }

            .btn-group .btn,
            .btn-group form {
                flex: 1;
            }

            .btn-group form .btn {
                width: 100%;
            }
        }
    </style>

</head>


<body>


    <?php include 'navbar.php'; ?>


    <main class="content">

        <div class="orc-container">


            <!--
        |--------------------------------------------------------------------------
        | CABEÇALHO
        |--------------------------------------------------------------------------
        -->

            <div class="header-actions">


                <h1>

                    Orçamento #<?= htmlspecialchars($id) ?>

                    <span
                        class="status-badge status-<?= htmlspecialchars($status_orcamento) ?>">

                        <?php

                        if ($status_orcamento === 'aceito') {
                            echo 'Confirmado';
                        } elseif ($status_orcamento === 'recusado') {
                            echo 'Recusado';
                        } else {
                            echo 'Pendente';
                        }

                        ?>

                    </span>

                </h1>


                <div class="btn-group">


                    <!--
                |--------------------------------------------------------------------------
                | 1 - VOLTAR
                |--------------------------------------------------------------------------
                -->

                    <a
                        href="orcamento.php"
                        class="btn btn-voltar">
                        ← Voltar
                    </a>


                    <!--
                |--------------------------------------------------------------------------
                | 2 - BAIXAR PDF
                |--------------------------------------------------------------------------
                -->

                    <a
                        href="gerar_orcamento_pdf.php?id=<?= $id ?>"
                        target="_blank"
                        class="btn btn-pdf">
                        📥 Baixar PDF
                    </a>


                    <?php if ($status_orcamento === 'pendente'): ?>


                        <!--
                    |--------------------------------------------------------------------------
                    | 3 - EDITAR
                    |--------------------------------------------------------------------------
                    -->

                        <a
                            href="editar_orcamento.php?id=<?= $id ?>"
                            class="btn btn-editar">
                            ✏️ Editar
                        </a>


                        <!--
                    |--------------------------------------------------------------------------
                    | 4 - CONFIRMAR
                    |--------------------------------------------------------------------------
                    -->

                        <form
                            method="POST"
                            style="display:inline;"
                            onsubmit="return confirm('Confirmar este orçamento?');">

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                            <input
                                type="hidden"
                                name="acao"
                                value="confirmar">

                            <button
                                type="submit"
                                class="btn btn-confirmar">
                                ✓ Confirmar
                            </button>

                        </form>


                        <!--
                    |--------------------------------------------------------------------------
                    | 5 - RECUSAR
                    |--------------------------------------------------------------------------
                    -->

                        <form
                            method="POST"
                            style="display:inline;"
                            onsubmit="return confirm('Recusar este orçamento? Esta ação não poderá ser desfeita por esta tela.');">

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                            <input
                                type="hidden"
                                name="acao"
                                value="recusar">

                            <button
                                type="submit"
                                class="btn btn-recusar">
                                ✕ Recusar
                            </button>

                        </form>


                    <?php endif; ?>


                </div>

            </div>



            <!--
        |--------------------------------------------------------------------------
        | DADOS DO PACIENTE
        |--------------------------------------------------------------------------
        -->

            <h2>
                👤 Dados do Paciente
            </h2>


            <div class="info-grid">


                <div class="info-item">

                    <label>
                        Nome
                    </label>

                    <span>
                        <?= htmlspecialchars($orc['paciente']) ?>
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
                        Validade
                    </label>

                    <span>

                        <?= !empty($orc['validade'])
                            ? date(
                                'd/m/Y',
                                strtotime($orc['validade'])
                            )
                            : '—'
                        ?>

                    </span>

                </div>


            </div>



            <!--
        |--------------------------------------------------------------------------
        | PROCEDIMENTOS
        |--------------------------------------------------------------------------
        -->

            <h2>
                🦷 Procedimentos
            </h2>


            <?php if (empty($itens)): ?>


                <p style="color:#666; padding:10px 0;">
                    Nenhum item registrado.
                </p>


            <?php else: ?>


                <table>

                    <thead>

                        <tr>

                            <th>
                                Descrição
                            </th>

                            <th
                                style="width:80px; text-align:center;">
                                Qtd
                            </th>

                            <th
                                style="width:120px; text-align:right;">
                                Unitário
                            </th>

                            <th
                                style="width:120px; text-align:right;">
                                Subtotal
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                        <?php foreach ($itens as $item): ?>


                            <?php

                            $quantidade = (int) $item['quantidade'];

                            $valor_unitario =
                                (float) $item['valor_unitario'];

                            $subtotal =
                                $quantidade * $valor_unitario;

                            ?>


                            <tr>


                                <td>
                                    <?= htmlspecialchars($item['descricao']) ?>
                                </td>


                                <td style="text-align:center;">

                                    <?= $quantidade ?>

                                </td>


                                <td style="text-align:right;">

                                    R$
                                    <?= number_format(
                                        $valor_unitario,
                                        2,
                                        ',',
                                        '.'
                                    ) ?>

                                </td>


                                <td
                                    style="
                                    text-align:right;
                                    font-weight:500;
                                ">

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


            <?php endif; ?>



            <!--
        |--------------------------------------------------------------------------
        | TOTAL
        |--------------------------------------------------------------------------
        -->

            <div class="total-box">


                <div class="total-label">
                    Valor Total do Orçamento
                </div>


                <div class="total-value">

                    R$
                    <?= number_format(
                        $total_itens,
                        2,
                        ',',
                        '.'
                    ) ?>

                </div>


            </div>



            <!--
        |--------------------------------------------------------------------------
        | OBSERVAÇÕES
        |--------------------------------------------------------------------------
        -->

            <?php if (!empty($orc['observacoes'])): ?>


                <h2>
                    📝 Observações
                </h2>


                <div
                    style="
                    background:#fff;
                    padding:12px;
                    border-radius:8px;
                    border:1px solid #e2e8f0;
                    font-size:13px;
                    color:#4a5568;
                    white-space:pre-line;
                ">

                    <?= nl2br(
                        htmlspecialchars(
                            $orc['observacoes']
                        )
                    ) ?>

                </div>


            <?php endif; ?>



            <!--
        |--------------------------------------------------------------------------
        | PARCELAS
        |--------------------------------------------------------------------------
        -->

            <?php if (!empty($parcelas)): ?>


                <div class="parcelas-section">


                    <h3>

                        💰 Controle de Parcelas

                        <span
                            style="
                            font-size:12px;
                            font-weight:normal;
                            color:#718096;
                            margin-left:auto;
                        ">

                            (
                            <?= $qtd_pagas ?>
                            /
                            <?= $qtd_total ?>
                            pagas
                            )

                        </span>

                    </h3>


                    <table>


                        <thead>

                            <tr>

                                <th>
                                    #
                                </th>

                                <th>
                                    Vencimento
                                </th>

                                <th
                                    style="text-align:right;">
                                    Valor
                                </th>

                                <th
                                    style="text-align:center;">
                                    Status
                                </th>

                                <th
                                    style="text-align:center;">
                                    Ação
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                            <?php foreach ($parcelas as $p): ?>


                                <?php

                                $badge_color = match ($p['status']) {

                                    'paga' =>
                                    '#198754',

                                    'atrasada' =>
                                    '#dc3545',

                                    default =>
                                    '#ef6c00'
                                };

                                ?>


                                <tr>


                                    <td
                                        style="font-weight:600;">

                                        <?= (int) $p['numero_parcela'] ?>x

                                    </td>


                                    <td>

                                        <?= date(
                                            'd/m/Y',
                                            strtotime(
                                                $p['vencimento']
                                            )
                                        ) ?>

                                    </td>


                                    <td
                                        style="
                                        text-align:right;
                                        font-weight:500;
                                    ">

                                        R$
                                        <?= number_format(
                                            $p['valor'],
                                            2,
                                            ',',
                                            '.'
                                        ) ?>

                                    </td>


                                    <td
                                        style="text-align:center;">

                                        <span
                                            class="status-badge"
                                            style="
                                            background:<?= $badge_color ?>;
                                            color:#fff;
                                        ">

                                            <?= ucfirst(
                                                htmlspecialchars(
                                                    $p['status']
                                                )
                                            ) ?>

                                        </span>

                                    </td>


                                    <td
                                        style="text-align:center;">


                                        <?php if (
                                            $p['status'] === 'pendente'
                                            ||
                                            $p['status'] === 'atrasada'
                                        ): ?>


                                            <?php if (
                                                $status_orcamento !== 'recusado'
                                            ): ?>


                                                <form
                                                    method="POST"
                                                    style="display:inline;"
                                                    onsubmit="return confirm('Confirmar pagamento desta parcela? O orçamento também será confirmado automaticamente.');">

                                                    <input
                                                        type="hidden"
                                                        name="csrf_token"
                                                        value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                                                    <input
                                                        type="hidden"
                                                        name="acao"
                                                        value="pagar_parcela">

                                                    <input
                                                        type="hidden"
                                                        name="parcela_id"
                                                        value="<?= (int) $p['id'] ?>">

                                                    <button
                                                        type="submit"
                                                        class="btn-pagar">
                                                        💳 Pagar parcela
                                                    </button>

                                                </form>


                                            <?php else: ?>


                                                <small
                                                    style="color:#dc3545;">
                                                    Orçamento recusado
                                                </small>


                                            <?php endif; ?>


                                        <?php elseif (
                                            $p['status'] === 'paga'
                                        ): ?>


                                            <small
                                                style="color:#198754;">

                                                ✓ Pago

                                                <?php if (
                                                    !empty($p['data_pagamento'])
                                                ): ?>

                                                    em
                                                    <?= date(
                                                        'd/m/Y',
                                                        strtotime(
                                                            $p['data_pagamento']
                                                        )
                                                    ) ?>

                                                <?php endif; ?>

                                            </small>


                                        <?php endif; ?>


                                    </td>


                                </tr>


                            <?php endforeach; ?>


                        </tbody>


                    </table>



                    <!--
                |--------------------------------------------------------------------------
                | BARRA DE PROGRESSO
                |--------------------------------------------------------------------------
                -->

                    <div class="progress-bar">

                        <div
                            class="progress-fill"
                            style="
                            width:<?= $progresso ?>%;"></div>

                    </div>


                    <div class="progress-text">

                        Progresso:
                        <?= $progresso ?>%
                        concluído

                    </div>


                </div>


            <?php endif; ?>



            <!--
        |--------------------------------------------------------------------------
        | RODAPÉ
        |--------------------------------------------------------------------------
        -->

            <div class="footer-note">

                Dentech <?= date('Y') ?>

                |

                Documento gerado automaticamente.

                Valores sujeitos a alteração conforme avaliação clínica.

            </div>


        </div>

    </main>


</body>

</html>