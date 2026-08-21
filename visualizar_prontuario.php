<?php

require_once 'config/auth.php';
exigirLogin();

require_once 'conexao/conexao.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Prontuário não encontrado.");
}

$id = (int) $_GET['id'];

$isPrint = isset($_GET['print']) && $_GET['print'] == '1';

/*
|--------------------------------------------------------------------------
| BUSCAR PRONTUÁRIO
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM prontuarios
    WHERE id = ?
");

$stmt->execute([$id]);

$prontuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$prontuario) {
    die("Prontuário não encontrado.");
}

// Buscar consentimento do prontuário
$stmtConsentimento = $pdo->prepare("
    SELECT aceito, data_aceite
    FROM consentimentos
    WHERE prontuario_id = ?
    ORDER BY id DESC
    LIMIT 1
");

$stmtConsentimento->execute([$id]);
$consentimento = $stmtConsentimento->fetch(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| CALCULAR IDADE
|--------------------------------------------------------------------------
*/

$dataNasc = new DateTime($prontuario['nascimento']);
$hoje = new DateTime();

$idade = $hoje->diff($dataNasc)->y;

/*
|--------------------------------------------------------------------------
| INICIAIS DO PACIENTE
|--------------------------------------------------------------------------
*/

$nomeCompleto = trim($prontuario['paciente']);

$partesNome = preg_split('/\s+/', $nomeCompleto);

if (count($partesNome) >= 2) {
    $iniciais =
        mb_substr($partesNome[0], 0, 1) .
        mb_substr($partesNome[count($partesNome) - 1], 0, 1);
} else {
    $iniciais = mb_substr($nomeCompleto, 0, 2);
}

$iniciais = mb_strtoupper($iniciais);

/*
|--------------------------------------------------------------------------
| BUSCAR PROCEDIMENTOS
|--------------------------------------------------------------------------
*/

$stmtProc = $pdo->prepare("
    SELECT *
    FROM procedimentos
    WHERE paciente_id = ?
    ORDER BY data_procedimento DESC
");

$stmtProc->execute([$id]);

$procedimentos = $stmtProc->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| STATUS DO TERMO
|--------------------------------------------------------------------------
*/

// O aceite pode estar registrado em prontuarios ou em consentimentos.
$consentimentoAceito = !empty($consentimento)
    && (int)($consentimento['aceito'] ?? 0) === 1;

$termoAceito = (isset($prontuario['termo_consentimento_aceito'])
    && (int)$prontuario['termo_consentimento_aceito'] === 1)
    || $consentimentoAceito;

$dataAceite = $prontuario['termo_consentimento_aceito_em']
    ?? ($consentimento['data_aceite'] ?? null);

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>
        Prontuário de <?= htmlspecialchars($prontuario['paciente']) ?>
        | Dentech
    </title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="css/global.css">
    <link rel="stylesheet" href="css/variables.css">
    <link rel="stylesheet" href="css/layout.css">
    <link rel="stylesheet" href="css/vis_prontuario.css">
    <link rel="stylesheet" href="css/navbar.css">
    <link rel="icon" type="image/png" href="img/icon.PNG">

</head>

<body class="<?= $isPrint ? 'print-mode' : '' ?>">

    <?php if (!$isPrint): ?>

        <?php include 'navbar.php'; ?>

    <?php endif; ?>


    <main class="prontuario-page">

        <!-- =====================================================
         CABEÇALHO
    ====================================================== -->

        <div class="page-header">

            <div class="page-header-info">

                <div class="breadcrumb">

                    <span>Prontuários</span>

                    <span class="breadcrumb-separator">/</span>

                    <span>Visualização</span>

                </div>

                <h1>
                    Prontuário do paciente
                </h1>

                <p>
                    Consulte os dados, histórico e informações do paciente.
                </p>

            </div>

        </div>


        <!-- =====================================================
         CABEÇALHO DO PACIENTE
    ====================================================== -->

        <section class="patient-header">

            <div class="patient-main">

                <div class="patient-avatar">
                    <?= htmlspecialchars($iniciais) ?>
                </div>

                <div class="patient-info">

                    <h2>
                        <?= htmlspecialchars($prontuario['paciente']) ?>
                    </h2>

                    <div class="patient-details">

                        <span>
                            <i class="fa-regular fa-calendar"></i>

                            <?= date(
                                'd/m/Y',
                                strtotime($prontuario['nascimento'])
                            ) ?>

                            (<?= $idade ?> anos)
                        </span>


                        <span>
                            <i class="fa-regular fa-id-card"></i>

                            CPF:
                            <?= htmlspecialchars(
                                $prontuario['cpf'] ?: 'Não informado'
                            ) ?>
                        </span>


                        <span>
                            <i class="fa-solid fa-phone"></i>

                            <?= htmlspecialchars(
                                $prontuario['telefone'] ?: 'Não informado'
                            ) ?>
                        </span>
                        <span>
                            <i class="fa-regular fa-envelope"></i>
                            E-mail: <?= htmlspecialchars($prontuario['email'] ?: 'Não informado') ?>
                        </span>

                    </div>

                </div>

            </div>


            <?php if (!$isPrint): ?>

                <div class="patient-actions">

                    <a
                        href="editar_prontuario.php?id=<?= $id ?>"
                        class="action-btn">

                        <i class="fa-solid fa-pen"></i>

                        Editar

                    </a>


                    <button
                        type="button"
                        class="action-btn"
                        onclick="window.open('visualizar_prontuario.php?id=<?= $id ?>&print=1', '_blank')">

                        <i class="fa-solid fa-print"></i>

                        Imprimir

                    </button>


                    <div class="more-wrapper">

                        <button
                            type="button"
                            class="action-btn"
                            id="moreButton">

                            Mais

                            <i class="fa-solid fa-chevron-down"></i>

                        </button>

                        <div
                            class="more-menu"
                            id="moreMenu">

                            <a
                                href="termo_conscentimento.php?id=<?= $id ?>"
                                target="_blank">

                                <i class="fa-regular fa-file-lines"></i>

                                Termo de Consentimento

                            </a>

                            <a
                                href="adicionar_procedimento.php?prontuario_id=<?= $id ?>">

                                <i class="fa-solid fa-plus"></i>

                                Adicionar Procedimento

                            </a>

                        </div>

                    </div>

                </div>

            <?php endif; ?>

        </section>


        <!-- =====================================================
         ABAS
    ====================================================== -->

        <?php if (!$isPrint): ?>

            <nav class="patient-tabs">

                <button
                    type="button"
                    class="patient-tab active"
                    data-tab="resumo">

                    Resumo

                </button>

                <button
                    type="button"
                    class="patient-tab"
                    data-tab="historico">

                    Histórico

                </button>

                <button
                    type="button"
                    class="patient-tab"
                    data-tab="documentos">

                    Documentos

                </button>

                <button
                    type="button"
                    class="patient-tab"
                    data-tab="anexos">

                    Anexos

                </button>

                <button
                    type="button"
                    class="patient-tab"
                    data-tab="financeiro">

                    Financeiro

                </button>

            </nav>

        <?php endif; ?>


        <!-- =====================================================
         RESUMO
    ====================================================== -->

        <div
            class="tab-content active"
            id="tab-resumo">


            <!-- INFORMAÇÕES PESSOAIS -->

            <div class="content-grid">

                <section class="info-card">

                    <div class="card-header">

                        <h2>
                            Informações pessoais
                        </h2>

                    </div>


                    <div class="info-list">

                        <div class="info-item">

                            <strong>Sexo</strong>

                            <span>
                                <?= htmlspecialchars(
                                    $prontuario['sexo'] ?: 'Não informado'
                                ) ?>
                            </span>

                        </div>


                        <div class="info-item">

                            <strong>Estado civil</strong>

                            <span>
                                <?= htmlspecialchars(
                                    $prontuario['estado_civil'] ?: 'Não informado'
                                ) ?>
                            </span>

                        </div>


                        <div class="info-item">

                            <strong>Profissão</strong>

                            <span>
                                <?= htmlspecialchars(
                                    $prontuario['profissao'] ?: 'Não informado'
                                ) ?>
                            </span>

                        </div>


                        <div class="info-item">

                            <strong>RG</strong>

                            <span>
                                <?= htmlspecialchars(
                                    $prontuario['rg'] ?: 'Não informado'
                                ) ?>
                            </span>

                        </div>


                        <div class="info-item">

                            <strong>CEP</strong>

                            <span>
                                <?= htmlspecialchars(
                                    $prontuario['cep'] ?: 'Não informado'
                                ) ?>
                            </span>

                        </div>


                        <div class="info-item info-item-full">

                            <strong>Endereço</strong>

                            <span>
                                <?= nl2br(
                                    htmlspecialchars(
                                        $prontuario['endereco'] ?: 'Não informado'
                                    )
                                ) ?>
                            </span>

                        </div>

                    </div>

                </section>


                <!-- OBSERVAÇÕES -->

                <section class="info-card">

                    <div class="card-header">

                        <h2>
                            Observações
                        </h2>

                    </div>


                    <div class="observacoes">

                        <?php if (!empty($prontuario['observacoes'])): ?>

                            <?= nl2br(
                                htmlspecialchars(
                                    $prontuario['observacoes']
                                )
                            ) ?>

                        <?php else: ?>

                            <span class="empty-text">
                                Nenhuma observação registrada.
                            </span>

                        <?php endif; ?>

                    </div>

                </section>

            </div>


            <!-- =================================================
             TERMO DE CONSENTIMENTO
        ================================================== -->

            <section class="info-card consent-card">

                <div class="card-header">

                    <div>

                        <span class="card-kicker">
                            DOCUMENTAÇÃO
                        </span>

                        <h2>
                            Termo de Consentimento
                        </h2>

                    </div>

                    <?php if ($termoAceito): ?>

                        <span class="status-badge status-accepted">
                            <i class="fa-solid fa-check"></i>
                            Aceito
                        </span>

                    <?php else: ?>

                        <span class="status-badge status-pending">
                            <i class="fa-solid fa-clock"></i>
                            Pendente
                        </span>

                    <?php endif; ?>

                </div>


                <div class="consent-content">

                    <?php if ($termoAceito): ?>

                        <div class="consent-status accepted">

                            <div class="consent-icon">
                                <i class="fa-solid fa-check"></i>
                            </div>

                            <div>

                                <strong>
                                    Termo aceito pelo paciente
                                </strong>

                                <?php if ($dataAceite): ?>

                                    <span>
                                        Aceito em
                                        <?= date(
                                            'd/m/Y \à\s H:i',
                                            strtotime($dataAceite)
                                        ) ?>
                                    </span>

                                <?php endif; ?>

                            </div>

                        </div>

                    <?php else: ?>

                        <div class="consent-status pending">

                            <div class="consent-icon">
                                <i class="fa-solid fa-clock"></i>
                            </div>

                            <div>

                                <strong>
                                    Consentimento ainda não registrado
                                </strong>

                                <span>
                                    O paciente ainda não confirmou o Termo de
                                    Consentimento.
                                </span>

                            </div>

                        </div>

                    <?php endif; ?>
                    <?php if (!$isPrint && !$termoAceito): ?>
                        <a href="termo_conscentimento.php?id=<?= $id ?>"
                            target="_blank"
                            class="consent-button">
                            <i class="fa-regular fa-file-lines"></i>
                            Aceitar Termo de Consentimento
                        </a>
                    <?php elseif (!$isPrint && $termoAceito): ?>
                        <span class="consent-accepted-label">
                            <i class="fa-solid fa-circle-check"></i>
                            Termo já aceito
                        </span>
                    <?php endif; ?>

                </div>

            </section>


            <!-- =================================================
             PROCEDIMENTOS
        ================================================== -->

            <section class="info-card procedures-card">

                <div class="card-header">

                    <div>

                        <span class="card-kicker">
                            HISTÓRICO CLÍNICO
                        </span>

                        <h2>
                            Últimos procedimentos
                        </h2>

                    </div>

                    <?php if (!$isPrint): ?>

                        <a
                            href="adicionar_procedimento.php?prontuario_id=<?= $id ?>"
                            class="card-link">

                            + Adicionar procedimento

                        </a>

                    <?php endif; ?>

                </div>


                <?php if (empty($procedimentos)): ?>

                    <div class="empty-state">

                        <div class="empty-icon">
                            <i class="fa-regular fa-folder-open"></i>
                        </div>

                        <strong>
                            Nenhum procedimento registrado
                        </strong>

                        <span>
                            Os procedimentos realizados neste paciente
                            aparecerão aqui.
                        </span>

                    </div>

                <?php else: ?>

                    <div class="table-wrapper">

                        <table class="procedures-table">

                            <thead>

                                <tr>

                                    <th>Data</th>

                                    <th>Procedimento</th>

                                    <th>Descrição</th>

                                    <?php if (!$isPrint): ?>

                                        <th>Ação</th>

                                    <?php endif; ?>

                                </tr>

                            </thead>


                            <tbody>

                                <?php foreach ($procedimentos as $proc): ?>

                                    <tr>

                                        <td>
                                            <?= date(
                                                'd/m/Y',
                                                strtotime(
                                                    $proc['data_procedimento']
                                                )
                                            ) ?>
                                        </td>


                                        <td>

                                            <strong>
                                                <?= htmlspecialchars(
                                                    $proc['titulo']
                                                ) ?>
                                            </strong>

                                        </td>


                                        <td>

                                            <?php

                                            $descricao =
                                                trim(
                                                    $proc['descricao'] ?? ''
                                                );

                                            if ($descricao === '') {
                                                echo '<span class="muted">—</span>';
                                            } else {

                                                echo htmlspecialchars(
                                                    mb_strimwidth(
                                                        $descricao,
                                                        0,
                                                        100,
                                                        '...'
                                                    )
                                                );
                                            }

                                            ?>

                                        </td>


                                        <?php if (!$isPrint): ?>

                                            <td>

                                                <button
                                                    type="button"
                                                    class="table-action btn-visualizar"
                                                    data-titulo="<?= htmlspecialchars(
                                                                        $proc['titulo'],
                                                                        ENT_QUOTES
                                                                    ) ?>"
                                                    data-descricao="<?= htmlspecialchars(
                                                                        $proc['descricao'] ?? '',
                                                                        ENT_QUOTES
                                                                    ) ?>"
                                                    data-medicamentos="<?= htmlspecialchars(
                                                                            $proc['medicamentos'] ?? '',
                                                                            ENT_QUOTES
                                                                        ) ?>"
                                                    data-data="<?= date(
                                                                    'd/m/Y',
                                                                    strtotime(
                                                                        $proc['data_procedimento']
                                                                    )
                                                                ) ?>">

                                                    Visualizar

                                                </button>

                                            </td>

                                        <?php endif; ?>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php endif; ?>

            </section>

        </div>


        <!-- =====================================================
         ABAS FUTURAS
    ====================================================== -->

        <?php if (!$isPrint): ?>

            <div
                class="tab-content"
                id="tab-historico">

                <section class="info-card future-card">

                    <div class="future-icon">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                    </div>

                    <h2>Histórico</h2>

                    <p>
                        O histórico completo do paciente será disponibilizado
                        nesta seção.
                    </p>

                </section>

            </div>


            <div
                class="tab-content"
                id="tab-documentos">

                <section class="info-card future-card">

                    <div class="future-icon">
                        <i class="fa-regular fa-file-lines"></i>
                    </div>

                    <h2>Documentos</h2>

                    <p>
                        Os documentos do paciente serão disponibilizados
                        nesta seção.
                    </p>

                </section>

            </div>


            <div
                class="tab-content"
                id="tab-anexos">

                <section class="info-card future-card">

                    <div class="future-icon">
                        <i class="fa-solid fa-paperclip"></i>
                    </div>

                    <h2>Anexos</h2>

                    <p>
                        Os anexos do paciente serão disponibilizados
                        nesta seção.
                    </p>

                </section>

            </div>


            <div
                class="tab-content"
                id="tab-financeiro">

                <section class="info-card future-card">

                    <div class="future-icon">
                        <i class="fa-solid fa-dollar-sign"></i>
                    </div>

                    <h2>Financeiro</h2>

                    <p>
                        As informações financeiras do paciente serão
                        disponibilizadas nesta seção.
                    </p>

                </section>

            </div>

        <?php endif; ?>

    </main>


    <!-- =========================================================
     MODAL PROCEDIMENTO
========================================================== -->

    <?php if (!$isPrint): ?>

        <div
            class="modal"
            id="procedureModal">

            <div class="modal-content">

                <div class="modal-header">

                    <h2 id="modalTitle">
                        Procedimento
                    </h2>

                    <button
                        type="button"
                        class="modal-close"
                        id="modalClose">

                        &times;

                    </button>

                </div>


                <div
                    class="modal-body"
                    id="modalBody">
                </div>


                <div class="modal-footer">

                    <button
                        type="button"
                        class="modal-button"
                        id="modalCloseFooter">

                        Fechar

                    </button>

                </div>

            </div>

        </div>

    <?php endif; ?>


    <?php if (!$isPrint): ?>

        <script>
            document.addEventListener('DOMContentLoaded', function() {

                /*
                |--------------------------------------------------------------------------
                | MENU "MAIS"
                |--------------------------------------------------------------------------
                */

                const moreButton = document.getElementById('moreButton');
                const moreMenu = document.getElementById('moreMenu');

                if (moreButton && moreMenu) {

                    moreButton.addEventListener('click', function(event) {

                        event.stopPropagation();

                        moreMenu.classList.toggle('show');

                    });

                    document.addEventListener('click', function() {

                        moreMenu.classList.remove('show');

                    });

                }


                /*
                |--------------------------------------------------------------------------
                | ABAS
                |--------------------------------------------------------------------------
                */

                const tabs = document.querySelectorAll('.patient-tab');
                const contents = document.querySelectorAll('.tab-content');

                tabs.forEach(function(tab) {

                    tab.addEventListener('click', function(event) {
                        event.preventDefault();

                        const target = this.dataset.tab;

                        tabs.forEach(function(item) {
                            item.classList.remove('active');
                        });

                        contents.forEach(function(content) {
                            content.classList.remove('active');
                        });

                        this.classList.add('active');

                        const targetContent =
                            document.getElementById('tab-' + target);

                        if (targetContent) {
                            targetContent.classList.add('active');
                        }

                    });

                });


                /*
                |--------------------------------------------------------------------------
                | MODAL DE PROCEDIMENTO
                |--------------------------------------------------------------------------
                */

                const modal =
                    document.getElementById('procedureModal');

                const modalTitle =
                    document.getElementById('modalTitle');

                const modalBody =
                    document.getElementById('modalBody');

                const modalClose =
                    document.getElementById('modalClose');

                const modalCloseFooter =
                    document.getElementById('modalCloseFooter');


                function fecharModal() {

                    if (modal) {
                        modal.classList.remove('show');
                    }

                }


                document
                    .querySelectorAll('.btn-visualizar')
                    .forEach(function(button) {

                        button.addEventListener('click', function() {

                            const titulo =
                                this.dataset.titulo || 'Procedimento';

                            const descricao =
                                this.dataset.descricao || '';

                            const medicamentos =
                                this.dataset.medicamentos || '';

                            const data =
                                this.dataset.data || '';


                            modalTitle.textContent = titulo;


                            let html = '';

                            html += `
                    <div class="modal-info">
                        <strong>Data</strong>
                        <span>${data}</span>
                    </div>
                `;


                            if (descricao.trim() !== '') {

                                html += `
                        <div class="modal-section">
                            <strong>Descrição</strong>
                            <p>
                                ${descricao.replace(/\n/g, '<br>')}
                            </p>
                        </div>
                    `;

                            }


                            if (medicamentos.trim() !== '') {

                                html += `
                        <div class="modal-section">
                            <strong>Medicamentos receitados</strong>
                            <p>
                                ${medicamentos.replace(/\n/g, '<br>')}
                            </p>
                        </div>
                    `;

                            }


                            if (
                                descricao.trim() === '' &&
                                medicamentos.trim() === ''
                            ) {

                                html += `
                        <p class="modal-empty">
                            Nenhuma informação adicional registrada.
                        </p>
                    `;

                            }


                            modalBody.innerHTML = html;

                            modal.classList.add('show');

                        });

                    });


                if (modalClose) {
                    modalClose.addEventListener(
                        'click',
                        fecharModal
                    );
                }


                if (modalCloseFooter) {
                    modalCloseFooter.addEventListener(
                        'click',
                        fecharModal
                    );
                }


                if (modal) {

                    modal.addEventListener('click', function(event) {

                        if (event.target === modal) {
                            fecharModal();
                        }

                    });

                }


                document.addEventListener('keydown', function(event) {

                    if (event.key === 'Escape') {
                        fecharModal();
                    }

                });

            });
        </script>

    <?php endif; ?>


    <?php if ($isPrint): ?>
        <script>
            window.addEventListener('load', function() {
                setTimeout(function() {
                    window.print();
                }, 250);
            });
        </script>
    <?php endif; ?>

</body>

</html>