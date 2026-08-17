<?php
require_once 'config/auth.php';
exigirLogin();

require_once 'conexao/conexao.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Prontuário não encontrado.");
}

$id = (int) $_GET['id'];

$isPrint = isset($_GET['print']) && $_GET['print'] === '1';

/*
|--------------------------------------------------------------------------
| BUSCAR PRONTUÁRIO
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("SELECT * FROM prontuarios WHERE id = ?");
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
    try {
        $dataNascimento = new DateTime($prontuario['nascimento']);
        $hoje = new DateTime();

        $idade = $hoje->diff($dataNascimento)->y;
    } catch (Exception $e) {
        $idade = null;
    }
}


/*
|--------------------------------------------------------------------------
| INICIAIS DO PACIENTE
|--------------------------------------------------------------------------
*/

$nomePaciente = trim($prontuario['paciente'] ?? '');

$partesNome = preg_split('/\s+/', $nomePaciente);

if (count($partesNome) >= 2) {
    $iniciais =
        mb_substr($partesNome[0], 0, 1) .
        mb_substr($partesNome[count($partesNome) - 1], 0, 1);
} else {
    $iniciais = mb_substr($nomePaciente, 0, 2);
}

$iniciais = mb_strtoupper($iniciais);


/*
|--------------------------------------------------------------------------
| FORMATAÇÕES
|--------------------------------------------------------------------------
*/

$dataNascimentoFormatada = '—';

if (!empty($prontuario['nascimento'])) {
    $dataNascimentoFormatada = date(
        'd/m/Y',
        strtotime($prontuario['nascimento'])
    );
}

$cpf = !empty($prontuario['cpf'])
    ? $prontuario['cpf']
    : '—';

$telefone = !empty($prontuario['telefone'])
    ? $prontuario['telefone']
    : '—';

$email = !empty($prontuario['email'])
    ? $prontuario['email']
    : '—';

$endereco = !empty($prontuario['endereco'])
    ? $prontuario['endereco']
    : '—';

$estadoCivil = !empty($prontuario['estado_civil'])
    ? $prontuario['estado_civil']
    : '—';

$profissao = !empty($prontuario['profissao'])
    ? $prontuario['profissao']
    : '—';

$rg = !empty($prontuario['rg'])
    ? $prontuario['rg']
    : '—';

$cep = !empty($prontuario['cep'])
    ? $prontuario['cep']
    : '—';

$sexo = !empty($prontuario['sexo'])
    ? $prontuario['sexo']
    : '—';


/*
|--------------------------------------------------------------------------
| OBSERVAÇÕES
|--------------------------------------------------------------------------
*/

$observacoes = trim($prontuario['observacoes'] ?? '');

if ($observacoes === '') {
    $observacoes = 'Nenhuma observação registrada.';
}


/*
|--------------------------------------------------------------------------
| PROCEDIMENTOS
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
| TERMO DE CONSENTIMENTO
|--------------------------------------------------------------------------
|
| A tabela possui:
| id
| prontuario_id
| aceito
| data_aceite
|
*/

$consentimentoAceito = false;
$dataAceite = null;

try {

    $stmtConsentimento = $pdo->prepare("
        SELECT aceito, data_aceite
        FROM consentimentos
        WHERE prontuario_id = ?
        ORDER BY data_aceite DESC
        LIMIT 1
    ");

    $stmtConsentimento->execute([$id]);

    $consentimento = $stmtConsentimento->fetch(PDO::FETCH_ASSOC);

    if ($consentimento && (int)$consentimento['aceito'] === 1) {
        $consentimentoAceito = true;
        $dataAceite = $consentimento['data_aceite'];
    }
} catch (PDOException $e) {

    /*
     * Caso a tabela ainda não esteja disponível,
     * a página continua funcionando normalmente.
     */

    $consentimentoAceito = false;
}


/*
|--------------------------------------------------------------------------
| DATA DE ACEITE FORMATADA
|--------------------------------------------------------------------------
*/

$dataAceiteFormatada = '';

if (!empty($dataAceite)) {
    $dataAceiteFormatada = date(
        'd/m/Y H:i',
        strtotime($dataAceite)
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
        Prontuário de <?= htmlspecialchars($nomePaciente) ?> | Dentech
    </title>

    <link
        rel="icon"
        type="image/png"
        href="img/icon.PNG">

    <link
        rel="stylesheet"
        href="css/navbar.css">

    <link
        rel="stylesheet"
        href="css/vis_prontuario.css">

</head>

<body>

    <?php if (!$isPrint): ?>

        <?php include 'navbar.php'; ?>

    <?php endif; ?>


    <main class="prontuario-container">


        <!-- =====================================================
         CABEÇALHO DA PÁGINA
         ===================================================== -->

        <div class="page-header">

            <div>

                <span class="page-kicker">
                    PRONTUÁRIOS
                </span>

                <h1>
                    Prontuário do paciente
                </h1>

                <div class="breadcrumb">

                    <span>Prontuários</span>

                    <span class="breadcrumb-separator">
                        /
                    </span>

                    <span>
                        Visualização
                    </span>

                </div>

            </div>

        </div>


        <!-- =====================================================
         CARD PRINCIPAL DO PACIENTE
         ===================================================== -->

        <section class="patient-card">


            <!-- AVATAR -->

            <div class="patient-avatar">

                <?= htmlspecialchars($iniciais) ?>

            </div>


            <!-- INFORMAÇÕES PRINCIPAIS -->

            <div class="patient-main">

                <h2>
                    <?= htmlspecialchars($nomePaciente) ?>
                </h2>


                <div class="patient-details">

                    <span class="patient-detail">

                        <span class="detail-icon">
                            📅
                        </span>

                        <?= htmlspecialchars($dataNascimentoFormatada) ?>

                        <?php if ($idade !== null): ?>

                            <span>
                                (<?= $idade ?> anos)
                            </span>

                        <?php endif; ?>

                    </span>


                    <span class="patient-detail">

                        <span class="detail-icon">
                            👤
                        </span>

                        CPF:
                        <?= htmlspecialchars($cpf) ?>

                    </span>


                    <span class="patient-detail">

                        <span class="detail-icon">
                            ☎
                        </span>

                        <?= htmlspecialchars($telefone) ?>

                    </span>


                    <span class="patient-detail">

                        <span class="detail-icon">
                            ✉
                        </span>

                        <?= htmlspecialchars($email) ?>

                    </span>

                </div>

            </div>


            <!-- AÇÕES -->

            <?php if (!$isPrint): ?>

                <div class="patient-actions">


                    <a
                        href="editar_prontuario.php?id=<?= $id ?>"
                        class="action-button">

                        <span>✎</span>

                        Editar

                    </a>


                    <a
                        href="visualizar_prontuario.php?id=<?= $id ?>&print=1"
                        target="_blank"
                        class="action-button">

                        <span>🖨</span>

                        Imprimir

                    </a>


                    <div class="more-wrapper">

                        <button
                            type="button"
                            class="action-button more-button"
                            onclick="toggleMoreMenu()">

                            Mais

                            <span class="arrow">
                                ▾
                            </span>

                        </button>


                        <div
                            id="moreMenu"
                            class="more-menu">

                            <a
                                href="termo_conscentimento.php?id=<?= $id ?>">
                                📄 Termo de Consentimento
                            </a>

                            <a
                                href="editar_prontuario.php?id=<?= $id ?>">
                                ✎ Editar prontuário
                            </a>

                        </div>

                    </div>

                </div>

            <?php endif; ?>


        </section>


        <!-- =====================================================
         ABAS
         ===================================================== -->

        <section class="tabs-card">

            <div class="tabs">

                <button
                    type="button"
                    class="tab active"
                    data-tab="resumo">
                    Resumo
                </button>

                <button
                    type="button"
                    class="tab"
                    data-tab="historico">
                    Histórico
                </button>

                <button
                    type="button"
                    class="tab"
                    data-tab="documentos">
                    Documentos
                </button>

                <button
                    type="button"
                    class="tab"
                    data-tab="anexos">
                    Anexos
                </button>

                <button
                    type="button"
                    class="tab"
                    data-tab="financeiro">
                    Financeiro
                </button>

            </div>


            <!-- CONTEÚDO DAS ABAS -->

            <div class="tab-content">


                <!-- =================================================
                 RESUMO
                 ================================================= -->

                <div
                    class="tab-panel active"
                    id="tab-resumo">


                    <div class="summary-grid">


                        <!-- INFORMAÇÕES PESSOAIS -->

                        <div class="info-card">

                            <h3>
                                Informações pessoais
                            </h3>


                            <div class="info-list">

                                <div class="info-row">

                                    <strong>
                                        Endereço
                                    </strong>

                                    <span>
                                        <?= nl2br(
                                            htmlspecialchars($endereco)
                                        ) ?>
                                    </span>

                                </div>


                                <div class="info-row">

                                    <strong>
                                        CEP
                                    </strong>

                                    <span>
                                        <?= htmlspecialchars($cep) ?>
                                    </span>

                                </div>


                                <div class="info-row">

                                    <strong>
                                        Sexo
                                    </strong>

                                    <span>
                                        <?= htmlspecialchars($sexo) ?>
                                    </span>

                                </div>


                                <div class="info-row">

                                    <strong>
                                        Estado civil
                                    </strong>

                                    <span>
                                        <?= htmlspecialchars($estadoCivil) ?>
                                    </span>

                                </div>


                                <div class="info-row">

                                    <strong>
                                        Profissão
                                    </strong>

                                    <span>
                                        <?= htmlspecialchars($profissao) ?>
                                    </span>

                                </div>


                                <div class="info-row">

                                    <strong>
                                        RG
                                    </strong>

                                    <span>
                                        <?= htmlspecialchars($rg) ?>
                                    </span>

                                </div>

                            </div>

                        </div>


                        <!-- OBSERVAÇÕES -->

                        <div class="info-card observations-card">

                            <h3>
                                Observações
                            </h3>

                            <div class="observations">

                                <?= nl2br(
                                    htmlspecialchars($observacoes)
                                ) ?>

                            </div>


                            <div class="consent-box">

                                <?php if ($consentimentoAceito): ?>

                                    <div class="consent-status accepted">

                                        <span class="consent-icon">
                                            ✓
                                        </span>

                                        <div>

                                            <strong>
                                                Termo de consentimento aceito
                                            </strong>

                                            <?php if ($dataAceiteFormatada): ?>

                                                <span>
                                                    Aceito em
                                                    <?= htmlspecialchars($dataAceiteFormatada) ?>
                                                </span>

                                            <?php endif; ?>

                                        </div>

                                    </div>

                                <?php else: ?>

                                    <div class="consent-status pending">

                                        <span class="consent-icon">
                                            !
                                        </span>

                                        <div>

                                            <strong>
                                                Termo de consentimento pendente
                                            </strong>

                                            <span>
                                                O paciente ainda não aceitou o termo.
                                            </span>

                                        </div>

                                    </div>

                                <?php endif; ?>

                            </div>

                        </div>

                    </div>


                    <!-- =================================================
                     ÚLTIMOS ATENDIMENTOS
                     ================================================= -->

                    <div class="attendance-card">

                        <div class="attendance-header">

                            <h3>
                                Últimos atendimentos
                            </h3>

                            <?php if (!$isPrint): ?>

                                <a
                                    href="adicionar_procedimento.php?prontuario_id=<?= $id ?>">
                                    + Adicionar procedimento
                                </a>

                            <?php endif; ?>

                        </div>


                        <?php if (empty($procedimentos)): ?>

                            <div class="empty-attendance">

                                <div class="empty-icon">
                                    📋
                                </div>

                                <strong>
                                    Nenhum procedimento registrado
                                </strong>

                                <span>
                                    Os procedimentos realizados aparecerão aqui.
                                </span>

                            </div>

                        <?php else: ?>


                            <div class="table-wrapper">

                                <table class="attendance-table">

                                    <thead>

                                        <tr>

                                            <th>
                                                Data
                                            </th>

                                            <th>
                                                Tipo de atendimento
                                            </th>

                                            <th>
                                                Profissional
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

                                            <?php

                                            $dataProcedimento = '—';

                                            if (!empty($proc['data_procedimento'])) {
                                                $dataProcedimento = date(
                                                    'd/m/Y',
                                                    strtotime(
                                                        $proc['data_procedimento']
                                                    )
                                                );
                                            }

                                            $titulo =
                                                $proc['titulo'] ??
                                                'Procedimento';

                                            $descricao =
                                                $proc['descricao'] ??
                                                '';

                                            $medicamentos =
                                                $proc['medicamentos'] ??
                                                '';

                                            ?>

                                            <tr>

                                                <td>

                                                    <?= htmlspecialchars(
                                                        $dataProcedimento
                                                    ) ?>

                                                </td>


                                                <td>

                                                    <?= htmlspecialchars(
                                                        $titulo
                                                    ) ?>

                                                </td>


                                                <td>

                                                    Dentista

                                                </td>


                                                <td>

                                                    <?php

                                                    if (
                                                        trim($descricao) !== ''
                                                    ) {

                                                        echo htmlspecialchars(
                                                            mb_strimwidth(
                                                                trim($descricao),
                                                                0,
                                                                80,
                                                                '...'
                                                            )
                                                        );
                                                    } elseif (
                                                        trim($medicamentos) !== ''
                                                    ) {

                                                        echo htmlspecialchars(
                                                            mb_strimwidth(
                                                                trim($medicamentos),
                                                                0,
                                                                80,
                                                                '...'
                                                            )
                                                        );
                                                    } else {

                                                        echo 'Sem resumo';
                                                    }

                                                    ?>

                                                </td>


                                                <?php if (!$isPrint): ?>

                                                    <td>

                                                        <button
                                                            type="button"
                                                            class="view-procedure"
                                                            data-titulo="<?= htmlspecialchars(
                                                                                $titulo,
                                                                                ENT_QUOTES
                                                                            ) ?>"
                                                            data-descricao="<?= htmlspecialchars(
                                                                                $descricao,
                                                                                ENT_QUOTES
                                                                            ) ?>"
                                                            data-medicamentos="<?= htmlspecialchars(
                                                                                    $medicamentos,
                                                                                    ENT_QUOTES
                                                                                ) ?>"
                                                            data-data="<?= htmlspecialchars(
                                                                            $dataProcedimento,
                                                                            ENT_QUOTES
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


                        <?php if (!empty($procedimentos) && !$isPrint): ?>

                            <div class="attendance-footer">

                                <span>
                                    <?= count($procedimentos) ?>
                                    procedimento(s) registrado(s)
                                </span>

                            </div>

                        <?php endif; ?>

                    </div>


                </div>


                <!-- =================================================
                 HISTÓRICO
                 ================================================= -->

                <div
                    class="tab-panel"
                    id="tab-historico">

                    <div class="coming-soon">

                        <div class="coming-icon">
                            🕘
                        </div>

                        <h3>
                            Histórico
                        </h3>

                        <p>
                            O histórico completo do paciente será
                            disponibilizado nesta área.
                        </p>

                    </div>

                </div>


                <!-- =================================================
                 DOCUMENTOS
                 ================================================= -->

                <div
                    class="tab-panel"
                    id="tab-documentos">

                    <div class="coming-soon">

                        <div class="coming-icon">
                            📄
                        </div>

                        <h3>
                            Documentos
                        </h3>

                        <p>
                            Os documentos do paciente serão
                            disponibilizados nesta área.
                        </p>

                    </div>

                </div>


                <!-- =================================================
                 ANEXOS
                 ================================================= -->

                <div
                    class="tab-panel"
                    id="tab-anexos">

                    <div class="coming-soon">

                        <div class="coming-icon">
                            📎
                        </div>

                        <h3>
                            Anexos
                        </h3>

                        <p>
                            Os anexos do prontuário serão
                            disponibilizados nesta área.
                        </p>

                    </div>

                </div>


                <!-- =================================================
                 FINANCEIRO
                 ================================================= -->

                <div
                    class="tab-panel"
                    id="tab-financeiro">

                    <div class="coming-soon">

                        <div class="coming-icon">
                            $
                        </div>

                        <h3>
                            Financeiro
                        </h3>

                        <p>
                            As informações financeiras do paciente
                            serão disponibilizadas nesta área.
                        </p>

                    </div>

                </div>


            </div>

        </section>


    </main>


    <!-- =========================================================
     MODAL DE PROCEDIMENTO
     ========================================================= -->

    <?php if (!$isPrint): ?>

        <div
            id="procedureModal"
            class="modal">

            <div class="modal-content">


                <div class="modal-header">

                    <div>

                        <span class="modal-kicker">
                            PROCEDIMENTO
                        </span>

                        <h2 id="modalTitle">
                            Procedimento
                        </h2>

                    </div>


                    <button
                        type="button"
                        class="modal-close"
                        onclick="fecharModal()">
                        ×
                    </button>

                </div>


                <div
                    id="modalBody"
                    class="modal-body"></div>


                <div class="modal-footer">

                    <button
                        type="button"
                        class="modal-footer-button"
                        onclick="fecharModal()">
                        Fechar
                    </button>

                </div>

            </div>

        </div>


        <script>
            /*
|--------------------------------------------------------------------------
| MENU MAIS
|--------------------------------------------------------------------------
*/

            function toggleMoreMenu() {

                const menu =
                    document.getElementById('moreMenu');

                menu.classList.toggle('show');

            }


            /*
            |--------------------------------------------------------------------------
            | FECHAR MENU AO CLICAR FORA
            |--------------------------------------------------------------------------
            */

            document.addEventListener('click', function(event) {

                const wrapper =
                    document.querySelector('.more-wrapper');

                const menu =
                    document.getElementById('moreMenu');

                if (
                    wrapper &&
                    menu &&
                    !wrapper.contains(event.target)
                ) {

                    menu.classList.remove('show');

                }

            });


            /*
            |--------------------------------------------------------------------------
            | ABAS
            |--------------------------------------------------------------------------
            */

            document.querySelectorAll('.tab').forEach(function(tab) {

                tab.addEventListener('click', function() {

                    const tabName =
                        this.dataset.tab;


                    document
                        .querySelectorAll('.tab')
                        .forEach(function(item) {

                            item.classList.remove('active');

                        });


                    document
                        .querySelectorAll('.tab-panel')
                        .forEach(function(panel) {

                            panel.classList.remove('active');

                        });


                    this.classList.add('active');


                    const panel =
                        document.getElementById(
                            'tab-' + tabName
                        );


                    if (panel) {

                        panel.classList.add('active');

                    }

                });

            });


            /*
            |--------------------------------------------------------------------------
            | MODAL
            |--------------------------------------------------------------------------
            */

            document
                .querySelectorAll('.view-procedure')
                .forEach(function(button) {

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
                <div class="modal-info-row">
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
                    <div class="modal-empty">
                        Nenhuma informação adicional registrada.
                    </div>
                `;

                        }


                        document
                            .getElementById('modalTitle')
                            .textContent = titulo;


                        document
                            .getElementById('modalBody')
                            .innerHTML = html;


                        document
                            .getElementById('procedureModal')
                            .classList.add('show');

                    });

                });


            /*
            |--------------------------------------------------------------------------
            | FECHAR MODAL
            |--------------------------------------------------------------------------
            */

            function fecharModal() {

                document
                    .getElementById('procedureModal')
                    .classList.remove('show');

            }


            /*
            |--------------------------------------------------------------------------
            | FECHAR MODAL CLICANDO FORA
            |--------------------------------------------------------------------------
            */

            document
                .getElementById('procedureModal')
                .addEventListener('click', function(event) {

                    if (event.target === this) {

                        fecharModal();

                    }

                });


            /*
            |--------------------------------------------------------------------------
            | ESC FECHA MODAL
            |--------------------------------------------------------------------------
            */

            document.addEventListener('keydown', function(event) {

                if (event.key === 'Escape') {

                    fecharModal();

                }

            });
        </script>

    <?php endif; ?>


</body>

</html>