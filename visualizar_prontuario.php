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

/*
|--------------------------------------------------------------------------
| CALCULAR IDADE
|--------------------------------------------------------------------------
*/

$idade = null;

if (!empty($prontuario['nascimento'])) {

    $dataNasc = new DateTime($prontuario['nascimento']);
    $hoje = new DateTime();

    $idade = $hoje->diff($dataNasc)->y;
}

/*
|--------------------------------------------------------------------------
| BUSCAR CONSENTIMENTO
|--------------------------------------------------------------------------
*/

$stmtConsentimento = $pdo->prepare("
    SELECT aceito, data_aceite
    FROM consentimentos
    WHERE prontuario_id = ?
    ORDER BY id DESC
    LIMIT 1
");

$stmtConsentimento->execute([$id]);

$consentimento = $stmtConsentimento->fetch(PDO::FETCH_ASSOC);

$consentimentoAceito = (
    $consentimento &&
    isset($consentimento['aceito']) &&
    (int)$consentimento['aceito'] === 1
);

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
| FUNÇÃO PARA ESCAPAR HTML
|--------------------------------------------------------------------------
*/

function e($valor)
{
    return htmlspecialchars(
        (string)($valor ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

/*
|--------------------------------------------------------------------------
| INICIAIS DO PACIENTE
|--------------------------------------------------------------------------
*/

$nomePaciente = trim($prontuario['paciente']);

$partesNome = preg_split('/\s+/', $nomePaciente);

$iniciais = '';

if (!empty($partesNome[0])) {
    $iniciais .= strtoupper(substr($partesNome[0], 0, 1));
}

if (count($partesNome) > 1) {
    $ultimo = end($partesNome);
    $iniciais .= strtoupper(substr($ultimo, 0, 1));
}

if ($iniciais === '') {
    $iniciais = '?';
}

/*
|--------------------------------------------------------------------------
| DATA DO CONSENTIMENTO
|--------------------------------------------------------------------------
*/

$dataConsentimento = '';

if ($consentimentoAceito && !empty($consentimento['data_aceite'])) {

    $dataConsentimento = date(
        'd/m/Y H:i',
        strtotime($consentimento['data_aceite'])
    );
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
        Prontuário de <?= e($prontuario['paciente']) ?> | Dentech
    </title>

    <link
        rel="stylesheet"
        href="css/vis_prontuario.css">

    <link
        rel="icon"
        type="image/png"
        href="img/icon.PNG">

</head>

<body>

    <?php if (!$isPrint): ?>

        <?php include 'navbar.php'; ?>

    <?php endif; ?>


    <main class="prontuario-page">

        <!-- =========================================================
         CABEÇALHO
    ========================================================== -->

        <div class="page-header">

            <div>

                <div class="breadcrumb">

                    <span>Prontuários</span>

                    <span class="breadcrumb-separator">
                        /
                    </span>

                    <span>Visualização</span>

                </div>

                <h1>
                    Prontuário do paciente
                </h1>

            </div>

        </div>


        <!-- =========================================================
         CARD PRINCIPAL DO PACIENTE
    ========================================================== -->

        <section class="patient-header">

            <div class="patient-main">

                <div class="patient-avatar">

                    <?= e($iniciais) ?>

                </div>


                <div class="patient-name">

                    <h2>
                        <?= e($prontuario['paciente']) ?>
                    </h2>

                    <div class="patient-info">

                        <span>

                            <i class="fa-regular fa-calendar"></i>

                            <?php if (!empty($prontuario['nascimento'])): ?>

                                <?= date(
                                    'd/m/Y',
                                    strtotime($prontuario['nascimento'])
                                ) ?>

                                <?php if ($idade !== null): ?>

                                    (<?= $idade ?> anos)

                                <?php endif; ?>

                            <?php else: ?>

                                —

                            <?php endif; ?>

                        </span>


                        <span>

                            <i class="fa-regular fa-id-card"></i>

                            CPF:
                            <?= !empty($prontuario['cpf'])
                                ? e($prontuario['cpf'])
                                : '—'
                            ?>

                        </span>


                        <span>

                            <i class="fa-solid fa-phone"></i>

                            <?= !empty($prontuario['telefone'])
                                ? e($prontuario['telefone'])
                                : '—'
                            ?>

                        </span>

                    </div>


                    <div class="patient-email">

                        <i class="fa-regular fa-envelope"></i>

                        <?= !empty($prontuario['email'])
                            ? e($prontuario['email'])
                            : 'E-mail não informado'
                        ?>

                    </div>

                </div>

            </div>


            <!-- AÇÕES -->

            <?php if (!$isPrint): ?>

                <div class="patient-actions">

                    <a
                        href="editar_prontuario.php?id=<?= $id ?>"
                        class="action-button">

                        <i class="fa-solid fa-pen"></i>

                        Editar

                    </a>


                    <button
                        type="button"
                        class="action-button"
                        onclick="window.print()">

                        <i class="fa-solid fa-print"></i>

                        Imprimir

                    </button>


                    <div class="more-wrapper">

                        <button
                            type="button"
                            class="action-button"
                            onclick="toggleMoreMenu()">

                            Mais

                            <i class="fa-solid fa-chevron-down"></i>

                        </button>


                        <div
                            id="moreMenu"
                            class="more-menu">

                            <a
                                href="termo_conscentimento.php?id=<?= $id ?>"
                                target="_blank">

                                <i class="fa-regular fa-file-lines"></i>

                                Termo de Consentimento

                            </a>

                            <a
                                href="editar_prontuario.php?id=<?= $id ?>">

                                <i class="fa-solid fa-pen"></i>

                                Editar prontuário

                            </a>

                        </div>

                    </div>

                </div>

            <?php endif; ?>

        </section>


        <!-- =========================================================
         ABAS
    ========================================================== -->

        <nav class="tabs">

            <button
                class="tab active"
                type="button">

                Resumo

            </button>

            <button
                class="tab"
                type="button">

                Histórico

            </button>

            <button
                class="tab"
                type="button">

                Documentos

            </button>

            <button
                class="tab"
                type="button">

                Anexos

            </button>

            <button
                class="tab"
                type="button">

                Financeiro

            </button>

        </nav>


        <!-- =========================================================
         CONTEÚDO
    ========================================================== -->

        <section class="content-area">


            <!-- =====================================================
             INFORMAÇÕES PESSOAIS
        ====================================================== -->

            <div class="info-grid">

                <div class="card personal-card">

                    <h3>
                        Informações pessoais
                    </h3>


                    <div class="info-list">

                        <div class="info-row">

                            <strong>
                                Endereço
                            </strong>

                            <span>
                                <?= !empty($prontuario['endereco'])
                                    ? nl2br(e($prontuario['endereco']))
                                    : '—'
                                ?>
                            </span>

                        </div>


                        <div class="info-row">

                            <strong>
                                CEP
                            </strong>

                            <span>
                                <?= !empty($prontuario['cep'])
                                    ? e($prontuario['cep'])
                                    : '—'
                                ?>
                            </span>

                        </div>


                        <div class="info-row">

                            <strong>
                                RG
                            </strong>

                            <span>
                                <?= !empty($prontuario['rg'])
                                    ? e($prontuario['rg'])
                                    : '—'
                                ?>
                            </span>

                        </div>


                        <div class="info-row">

                            <strong>
                                Sexo
                            </strong>

                            <span>
                                <?= !empty($prontuario['sexo'])
                                    ? e($prontuario['sexo'])
                                    : '—'
                                ?>
                            </span>

                        </div>


                        <div class="info-row">

                            <strong>
                                Estado civil
                            </strong>

                            <span>
                                <?= !empty($prontuario['estado_civil'])
                                    ? e($prontuario['estado_civil'])
                                    : '—'
                                ?>
                            </span>

                        </div>


                        <div class="info-row">

                            <strong>
                                Profissão
                            </strong>

                            <span>
                                <?= !empty($prontuario['profissao'])
                                    ? e($prontuario['profissao'])
                                    : '—'
                                ?>
                            </span>

                        </div>

                    </div>

                </div>


                <!-- =================================================
                 OBSERVAÇÕES
            ================================================== -->

                <div class="card observations-card">

                    <h3>
                        Observações
                    </h3>


                    <?php if (!empty($prontuario['observacoes'])): ?>

                        <div class="observations-text">

                            <?= nl2br(e($prontuario['observacoes'])) ?>

                        </div>

                    <?php else: ?>

                        <div class="empty-content">

                            Nenhuma observação registrada.

                        </div>

                    <?php endif; ?>

                </div>

            </div>


            <!-- =====================================================
             CONSENTIMENTO
        ====================================================== -->

            <div class="card consent-card">

                <div class="card-title-row">

                    <h3>
                        Termo de Consentimento
                    </h3>

                    <?php if ($consentimentoAceito): ?>

                        <span class="status status-accepted">

                            <i class="fa-solid fa-circle-check"></i>

                            Aceito

                        </span>

                    <?php else: ?>

                        <span class="status status-pending">

                            <i class="fa-solid fa-clock"></i>

                            Pendente

                        </span>

                    <?php endif; ?>

                </div>


                <?php if ($consentimentoAceito): ?>

                    <p class="consent-success">

                        Termo de consentimento aceito
                        <?php if ($dataConsentimento): ?>

                            em <?= e($dataConsentimento) ?>

                        <?php endif; ?>

                    </p>

                <?php else: ?>

                    <p class="consent-pending-text">

                        O termo de consentimento ainda não foi aceito.

                    </p>

                <?php endif; ?>


                <?php if (!$isPrint): ?>

                    <a
                        href="termo_conscentimento.php?id=<?= $id ?>"
                        class="consent-button">

                        <i class="fa-regular fa-file-lines"></i>

                        <?= $consentimentoAceito
                            ? 'Visualizar termo'
                            : 'Abrir termo de consentimento'
                        ?>

                    </a>

                <?php endif; ?>

            </div>


            <!-- =====================================================
             PROCEDIMENTOS
        ====================================================== -->

            <div class="card procedures-card">

                <div class="card-title-row">

                    <h3>
                        Últimos atendimentos
                    </h3>

                    <?php if (!$isPrint): ?>

                        <a
                            href="adicionar_procedimento.php?prontuario_id=<?= $id ?>"
                            class="add-procedure">

                            <i class="fa-solid fa-plus"></i>

                            Adicionar procedimento

                        </a>

                    <?php endif; ?>

                </div>


                <?php if (empty($procedimentos)): ?>

                    <div class="empty-procedures">

                        <i class="fa-regular fa-calendar-xmark"></i>

                        <p>
                            Nenhum procedimento registrado.
                        </p>

                        <?php if (!$isPrint): ?>

                            <a
                                href="adicionar_procedimento.php?prontuario_id=<?= $id ?>">

                                Adicionar primeiro procedimento

                            </a>

                        <?php endif; ?>

                    </div>

                <?php else: ?>

                    <div class="table-wrapper">

                        <table class="procedures-table">

                            <thead>

                                <tr>

                                    <th>
                                        Data
                                    </th>

                                    <th>
                                        Tipo de atendimento
                                    </th>

                                    <th>
                                        Resumo
                                    </th>

                                    <?php if (!$isPrint): ?>

                                        <th>
                                            Ação
                                        </th>

                                    <?php endif; ?>

                                </tr>

                            </thead>


                            <tbody>

                                <?php foreach ($procedimentos as $proc): ?>

                                    <tr>

                                        <td>

                                            <?= !empty($proc['data_procedimento'])
                                                ? date(
                                                    'd/m/Y',
                                                    strtotime($proc['data_procedimento'])
                                                )
                                                : '—'
                                            ?>

                                        </td>


                                        <td>

                                            <?= !empty($proc['titulo'])
                                                ? e($proc['titulo'])
                                                : '—'
                                            ?>

                                        </td>


                                        <td>

                                            <?php

                                            $resumo = '';

                                            if (!empty($proc['descricao'])) {

                                                $resumo = $proc['descricao'];
                                            } elseif (!empty($proc['medicamentos'])) {

                                                $resumo = $proc['medicamentos'];
                                            }

                                            ?>

                                            <?= $resumo
                                                ? e($resumo)
                                                : '—'
                                            ?>

                                        </td>


                                        <?php if (!$isPrint): ?>

                                            <td>

                                                <button
                                                    type="button"
                                                    class="view-procedure"
                                                    data-titulo="<?= e($proc['titulo'] ?? 'Procedimento') ?>"
                                                    data-descricao="<?= e($proc['descricao'] ?? '') ?>"
                                                    data-medicamentos="<?= e($proc['medicamentos'] ?? '') ?>"
                                                    data-data="<?= !empty($proc['data_procedimento'])
                                                                    ? date(
                                                                        'd/m/Y',
                                                                        strtotime($proc['data_procedimento'])
                                                                    )
                                                                    : '—'
                                                                ?>">

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

            </div>

        </section>

    </main>


    <!-- =============================================================
     MODAL
============================================================== -->

    <?php if (!$isPrint): ?>

        <div
            id="procedureModal"
            class="modal">

            <div class="modal-content">

                <div class="modal-header">

                    <h2 id="modalTitle">
                        Procedimento
                    </h2>

                    <button
                        type="button"
                        class="modal-close"
                        onclick="closeProcedureModal()">

                        &times;

                    </button>

                </div>


                <div
                    id="modalBody"
                    class="modal-body">

                </div>


                <div class="modal-footer">

                    <button
                        type="button"
                        class="modal-button"
                        onclick="closeProcedureModal()">

                        Fechar

                    </button>

                </div>

            </div>

        </div>

    <?php endif; ?>


    <script>
        function toggleMoreMenu() {
            const menu = document.getElementById('moreMenu');

            if (!menu) {
                return;
            }

            menu.classList.toggle('show');
        }


        document.addEventListener('click', function(event) {
            const wrapper = document.querySelector('.more-wrapper');

            const menu = document.getElementById('moreMenu');

            if (
                wrapper &&
                menu &&
                !wrapper.contains(event.target)
            ) {

                menu.classList.remove('show');

            }
        });


        document.querySelectorAll('.view-procedure').forEach(function(button) {

            button.addEventListener('click', function() {

                const titulo =
                    this.dataset.titulo || 'Procedimento';

                const descricao =
                    this.dataset.descricao || '';

                const medicamentos =
                    this.dataset.medicamentos || '';

                const data =
                    this.dataset.data || '—';


                let html = '';

                html += `
            <div class="modal-info">
                <strong>Data:</strong>
                <span>${data}</span>
            </div>
        `;


                if (descricao.trim() !== '') {

                    html += `
                <div class="modal-section">

                    <h4>Descrição</h4>

                    <p>
                        ${descricao.replace(/\n/g, '<br>')}
                    </p>

                </div>
            `;

                }


                if (medicamentos.trim() !== '') {

                    html += `
                <div class="modal-section">

                    <h4>Medicamentos receitados</h4>

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
                <div class="modal-empty">

                    Nenhuma informação adicional
                    registrada para este procedimento.

                </div>
            `;

                }


                document.getElementById('modalTitle').textContent =
                    titulo;

                document.getElementById('modalBody').innerHTML =
                    html;

                document.getElementById('procedureModal').classList.add('show');

            });

        });


        function closeProcedureModal() {

            const modal =
                document.getElementById('procedureModal');

            if (modal) {

                modal.classList.remove('show');

            }

        }


        window.addEventListener('click', function(event) {

            const modal =
                document.getElementById('procedureModal');

            if (
                modal &&
                event.target === modal
            ) {

                closeProcedureModal();

            }

        });


        document.addEventListener('keydown', function(event) {

            if (event.key === 'Escape') {

                closeProcedureModal();

            }

        });
    </script>


</body>

</html>