<?php

require_once 'config/auth.php';
require_once 'config/csrf.php';

exigirLogin();

require_once 'conexao/conexao.php';


// ============================================================
// ID DO ORÇAMENTO
// ============================================================

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    die("ID do orçamento não informado.");
}


// ============================================================
// PROCESSAMENTO DOS POSTS
// ============================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    validar_csrf();

    $acao = $_POST['acao'] ?? '';

    try {

        // ====================================================
        // CONFIRMAR ORÇAMENTO
        // ====================================================

        if ($acao === 'confirmar_orcamento') {

            $stmt = $pdo->prepare("
                UPDATE orcamentos
                SET status = 'aceito'
                WHERE id = ?
                  AND status = 'pendente'
            ");

            $stmt->execute([$id]);

            header(
                "Location: visualizar_orcamento.php?id=" . $id
            );

            exit;
        }


        // ====================================================
        // RECUSAR ORÇAMENTO
        // ====================================================

        if ($acao === 'recusar_orcamento') {

            $stmt = $pdo->prepare("
                UPDATE orcamentos
                SET status = 'recusado'
                WHERE id = ?
                  AND status = 'pendente'
            ");

            $stmt->execute([$id]);

            header(
                "Location: visualizar_orcamento.php?id=" . $id
            );

            exit;
        }


        // ====================================================
        // PAGAMENTO DE PARCELA
        // ====================================================

        if ($acao === 'marcar_paga') {

            $parcela_id = (int)($_POST['parcela_id'] ?? 0);

            if ($parcela_id <= 0) {
                die("Parcela inválida.");
            }


            // =================================================
            // INICIAR TRANSAÇÃO
            // =================================================

            $pdo->beginTransaction();


            // =================================================
            // VERIFICAR PARCELA
            // =================================================

            $stmt = $pdo->prepare("
                SELECT
                    id,
                    valor,
                    status
                FROM parcelas
                WHERE id = ?
                  AND orcamento_id = ?
                LIMIT 1
            ");

            $stmt->execute([
                $parcela_id,
                $id
            ]);

            $parcela = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$parcela) {

                $pdo->rollBack();

                die("Parcela não encontrada.");
            }


            // =================================================
            // SÓ PERMITE PAGAR PENDENTE OU ATRASADA
            // =================================================

            if (
                $parcela['status'] !== 'pendente' &&
                $parcela['status'] !== 'atrasada'
            ) {

                $pdo->rollBack();

                header(
                    "Location: visualizar_orcamento.php?id=" . $id
                );

                exit;
            }


            // =================================================
            // MARCAR PARCELA COMO PAGA
            // =================================================

            $stmt = $pdo->prepare("
                UPDATE parcelas
                SET
                    status = 'paga',
                    data_pagamento = CURDATE()
                WHERE id = ?
                  AND orcamento_id = ?
            ");

            $stmt->execute([
                $parcela_id,
                $id
            ]);


            // =================================================
            // ATUALIZAR LANÇAMENTO FINANCEIRO
            // =================================================
            //
            // Cada parcela possui um lançamento financeiro
            // vinculado através de parcela_id.
            //

            $stmt = $pdo->prepare("
                UPDATE lancamentos_financeiros
                SET
                    status = 'pago',
                    data = CURDATE()
                WHERE parcela_id = ?
                  AND orcamento_id = ?
            ");

            $stmt->execute([
                $parcela_id,
                $id
            ]);


            // =================================================
            // CONFIRMAR TRANSAÇÃO
            // =================================================

            $pdo->commit();


            // =================================================
            // VOLTAR PARA O ORÇAMENTO
            // =================================================

            header(
                "Location: visualizar_orcamento.php?id=" . $id
            );

            exit;
        }
    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log(
            "ERRO VISUALIZAR ORCAMENTO #{$id}: " .
                $e->getMessage()
        );

        die("Ocorreu um erro ao processar a solicitação.");
    }
}


// ============================================================
// BUSCAR ORÇAMENTO + PACIENTE
// ============================================================

$stmt = $pdo->prepare("
    SELECT
        o.*,
        p.paciente,
        p.cpf,
        p.telefone,
        p.email
    FROM orcamentos o
    INNER JOIN prontuarios p
        ON o.paciente_id = p.id
    WHERE o.id = ?
    LIMIT 1
");

$stmt->execute([$id]);

$orc = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$orc) {
    die("Orçamento não encontrado.");
}


// ============================================================
// ATUALIZAR PARCELAS ATRASADAS
// ============================================================

$pdo->prepare("
    UPDATE parcelas
    SET status = 'atrasada'
    WHERE orcamento_id = ?
      AND status = 'pendente'
      AND vencimento < CURDATE()
")
    ->execute([$id]);


// ============================================================
// BUSCAR ITENS
// ============================================================

$stmt_itens = $pdo->prepare("
    SELECT *
    FROM orcamentos_itens
    WHERE orcamento_id = ?
    ORDER BY id ASC
");

$stmt_itens->execute([$id]);

$itens = $stmt_itens->fetchAll(PDO::FETCH_ASSOC);


// ============================================================
// BUSCAR PARCELAS
// ============================================================

$stmt_par = $pdo->prepare("
    SELECT *
    FROM parcelas
    WHERE orcamento_id = ?
    ORDER BY numero_parcela ASC
");

$stmt_par->execute([$id]);

$parcelas = $stmt_par->fetchAll(PDO::FETCH_ASSOC);


// ============================================================
// CÁLCULO DO TOTAL
// ============================================================

$total_itens = 0;

foreach ($itens as $item) {

    $quantidade = (float)$item['quantidade'];
    $valor = (float)$item['valor_unitario'];

    $total_itens += $quantidade * $valor;
}


// ============================================================
// CONTROLE DAS PARCELAS
// ============================================================

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


// ============================================================
// STATUS DO ORÇAMENTO
// ============================================================

$status_orcamento = $orc['status'] ?? 'pendente';


// Alguns projetos utilizam "aceito"
// e outros "confirmado".

$orcamento_confirmado = in_array(
    $status_orcamento,
    ['aceito', 'confirmado'],
    true
);

$orcamento_recusado =
    $status_orcamento === 'recusado';

$orcamento_pendente =
    $status_orcamento === 'pendente';


// ============================================================
// TEXTO DO STATUS
// ============================================================

$status_texto = match ($status_orcamento) {

    'aceito',
    'confirmado'
    => 'Confirmado',

    'recusado'
    => 'Recusado',

    default
    => 'Pendente'
};


// ============================================================
// COR DO STATUS
// ============================================================

$status_classe = match ($status_orcamento) {

    'aceito',
    'confirmado'
    => 'status-confirmado',

    'recusado'
    => 'status-recusado',

    default
    => 'status-pendente'
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


    <!-- CSS GLOBAL -->

    <link rel="stylesheet" href="css/global.css">

    <link rel="stylesheet" href="css/variables.css">

    <link rel="stylesheet" href="css/layout.css">

    <!-- NAVBAR -->

    <link rel="stylesheet" href="css/navbar.css">

    <!-- CSS DA PÁGINA -->

    <link rel="stylesheet" href="css/vis_orcamento.css">


    <!-- FONT AWESOME -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">


    <!-- FAVICON -->

    <link
        rel="icon"
        type="image/png"
        href="img/icon.PNG">

</head>


<body>

    <?php include 'navbar.php'; ?>


    <main class="content">

        <div class="orc-container">


            <!-- ==================================================
                 CABEÇALHO
            =================================================== -->

            <div class="header-actions">

                <div>

                    <h1>

                        Orçamento #<?= $id ?>

                        <span
                            class="status-badge <?= $status_classe ?>">

                            <?= htmlspecialchars($status_texto) ?>

                        </span>

                    </h1>

                </div>


                <!-- ==================================================
                     BOTÕES
                =================================================== -->

                <div class="btn-group">


                    <!-- VOLTAR -->

                    <a
                        href="orcamento.php"
                        class="btn btn-outline">

                        ← Voltar

                    </a>


                    <!-- PDF -->

                    <a
                        href="gerar_orcamento_pdf.php?id=<?= $id ?>"
                        target="_blank"
                        class="btn btn-success">

                        📥 Baixar PDF

                    </a>


                    <?php if ($orcamento_pendente): ?>


                        <!-- EDITAR -->

                        <a
                            href="editar_orcamento.php?id=<?= $id ?>"
                            class="btn btn-primary">

                            ✏️ Editar

                        </a>


                        <!-- CONFIRMAR -->

                        <form
                            method="POST"
                            class="form-acao"
                            onsubmit="return confirm('Confirmar este orçamento?');">

                            <?= csrf_field() ?>

                            <input
                                type="hidden"
                                name="acao"
                                value="confirmar_orcamento">

                            <button
                                type="submit"
                                class="btn btn-confirmar">

                                ✓ Confirmar

                            </button>

                        </form>


                        <!-- RECUSAR -->

                        <form
                            method="POST"
                            class="form-acao"
                            onsubmit="return confirm('Recusar este orçamento?');">

                            <?= csrf_field() ?>

                            <input
                                type="hidden"
                                name="acao"
                                value="recusar_orcamento">

                            <button
                                type="submit"
                                class="btn btn-recusar">

                                ✕ Recusar

                            </button>

                        </form>


                    <?php endif; ?>


                </div>

            </div>


            <!-- ==================================================
                 INFORMAÇÕES DO PACIENTE
            =================================================== -->

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

                        <?= date(
                            'd/m/Y',
                            strtotime($orc['validade'])
                        ) ?>

                    </span>

                </div>


            </div>


            <!-- ==================================================
                 PROCEDIMENTOS
            =================================================== -->

            <h2>
                🦷 Procedimentos
            </h2>


            <?php if (empty($itens)): ?>

                <p class="sem-itens">
                    Nenhum item registrado.
                </p>

            <?php else: ?>


                <div class="table-wrapper">

                    <table>

                        <thead>

                            <tr>

                                <th>
                                    Descrição
                                </th>

                                <th class="col-qtd">
                                    Qtd
                                </th>

                                <th class="col-valor">
                                    Unitário
                                </th>

                                <th class="col-valor">
                                    Subtotal
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                            <?php foreach ($itens as $item): ?>

                                <?php

                                $subtotal =
                                    (float)$item['quantidade']
                                    *
                                    (float)$item['valor_unitario'];

                                ?>


                                <tr>

                                    <td>

                                        <?= htmlspecialchars(
                                            $item['descricao']
                                        ) ?>

                                    </td>


                                    <td class="text-center">

                                        <?= (int)$item['quantidade'] ?>

                                    </td>


                                    <td class="text-right">

                                        R$

                                        <?= number_format(
                                            $item['valor_unitario'],
                                            2,
                                            ',',
                                            '.'
                                        ) ?>

                                    </td>


                                    <td class="text-right">

                                        <strong>

                                            R$

                                            <?= number_format(
                                                $subtotal,
                                                2,
                                                ',',
                                                '.'
                                            ) ?>

                                        </strong>

                                    </td>

                                </tr>


                            <?php endforeach; ?>


                        </tbody>

                    </table>

                </div>


            <?php endif; ?>


            <!-- ==================================================
                 TOTAL
            =================================================== -->

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


            <!-- ==================================================
                 OBSERVAÇÕES
            =================================================== -->

            <?php if (!empty($orc['observacoes'])): ?>

                <h2>
                    📝 Observações
                </h2>


                <div class="observacoes-box">

                    <?= nl2br(
                        htmlspecialchars(
                            $orc['observacoes']
                        )
                    ) ?>

                </div>

            <?php endif; ?>


            <!-- ==================================================
                 PARCELAS
            =================================================== -->

            <?php if (!empty($parcelas)): ?>

                <div class="parcelas-section">


                    <div class="parcelas-header">

                        <h3>
                            💰 Controle de Parcelas
                        </h3>

                        <span>

                            <?= $qtd_pagas ?>

                            /

                            <?= $qtd_total ?>

                            pagas

                        </span>

                    </div>


                    <div class="table-wrapper">

                        <table>

                            <thead>

                                <tr>

                                    <th>
                                        Parcela
                                    </th>

                                    <th>
                                        Vencimento
                                    </th>

                                    <th class="text-right">
                                        Valor
                                    </th>

                                    <th class="text-center">
                                        Status
                                    </th>

                                    <th class="text-center">
                                        Ação
                                    </th>

                                </tr>

                            </thead>


                            <tbody>


                                <?php foreach ($parcelas as $p): ?>


                                    <?php

                                    switch ($p['status']) {

                                        case 'paga':

                                            $badge_class =
                                                'parcela-paga';

                                            $status_parcela =
                                                'Paga';

                                            break;


                                        case 'atrasada':

                                            $badge_class =
                                                'parcela-atrasada';

                                            $status_parcela =
                                                'Atrasada';

                                            break;


                                        default:

                                            $badge_class =
                                                'parcela-pendente';

                                            $status_parcela =
                                                'Pendente';

                                            break;
                                    }

                                    ?>


                                    <tr>


                                        <td>

                                            <strong>

                                                <?= (int)$p['numero_parcela'] ?>ª

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


                                        <td class="text-right">

                                            R$

                                            <?= number_format(
                                                $p['valor'],
                                                2,
                                                ',',
                                                '.'
                                            ) ?>

                                        </td>


                                        <td class="text-center">

                                            <span
                                                class="parcela-status <?= $badge_class ?>">

                                                <?= $status_parcela ?>

                                            </span>

                                        </td>


                                        <td class="text-center">


                                            <?php if (
                                                $p['status'] === 'pendente' ||
                                                $p['status'] === 'atrasada'
                                            ): ?>


                                                <form
                                                    method="POST"
                                                    class="form-pagamento"
                                                    onsubmit="return confirm('Confirmar pagamento desta parcela?');">


                                                    <?= csrf_field() ?>


                                                    <input
                                                        type="hidden"
                                                        name="acao"
                                                        value="marcar_paga">


                                                    <input
                                                        type="hidden"
                                                        name="parcela_id"
                                                        value="<?= (int)$p['id'] ?>">


                                                    <button
                                                        type="submit"
                                                        class="btn-pagar">

                                                        💳 Pagar

                                                    </button>


                                                </form>


                                            <?php elseif (
                                                $p['status'] === 'paga'
                                            ): ?>


                                                <span
                                                    class="pagamento-confirmado">

                                                    ✓ Pago


                                                    <?php if (
                                                        !empty($p['data_pagamento'])
                                                    ): ?>

                                                        <small>

                                                            em

                                                            <?= date(
                                                                'd/m/Y',
                                                                strtotime(
                                                                    $p['data_pagamento']
                                                                )
                                                            ) ?>

                                                        </small>

                                                    <?php endif; ?>


                                                </span>


                                            <?php endif; ?>


                                        </td>


                                    </tr>


                                <?php endforeach; ?>


                            </tbody>

                        </table>

                    </div>


                    <!-- ==================================================
                         PROGRESSO
                    =================================================== -->

                    <div class="progress-bar">

                        <div
                            class="progress-fill"
                            style="width: <?= $progresso ?>%;">

                        </div>

                    </div>


                    <div class="progress-text">

                        Progresso:

                        <strong>
                            <?= $progresso ?>%
                        </strong>

                        concluído

                    </div>


                </div>

            <?php endif; ?>


            <!-- ==================================================
                 INFORMAÇÃO FINANCEIRA
            =================================================== -->

            <?php if ($orcamento_confirmado): ?>


                <div class="financeiro-integracao">
                    <div class="financeiro-integracao-icon">💰</div>

                    <div class="financeiro-integracao-conteudo">
                        <strong>Orçamento integrado ao financeiro</strong>

                        <p>
                            As parcelas deste orçamento são consideradas automaticamente
                            no controle financeiro.
                        </p>

                        <a href="financeiro.php">
                            Ver financeiro →
                        </a>
                    </div>
                </div>


            <?php endif; ?>


            <!-- ==================================================
                 RODAPÉ
            =================================================== -->

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