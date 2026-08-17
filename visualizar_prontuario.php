<?php

require_once 'config/auth.php';
exigirLogin();

require_once 'conexao/conexao.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    die('Prontuário não encontrado.');
}

/*
|--------------------------------------------------------------------------
| BUSCAR PRONTUÁRIO
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM prontuarios
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$id]);

$prontuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$prontuario) {
    die('Prontuário não encontrado.');
}

/*
|--------------------------------------------------------------------------
| MODO DE IMPRESSÃO
|--------------------------------------------------------------------------
*/

$isPrint = isset($_GET['print']) && $_GET['print'] === '1';

/*
|--------------------------------------------------------------------------
| FUNÇÕES AUXILIARES
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

function valorOuTraco($valor)
{
    if ($valor === null || trim((string)$valor) === '') {
        return '—';
    }

    return e($valor);
}

/*
|--------------------------------------------------------------------------
| IDADE
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
| CONSENTIMENTO
|
| Como não temos aqui o nome exato da coluna criada no banco,
| verificamos alguns nomes possíveis.
|--------------------------------------------------------------------------
*/

$consentimentoAceito = false;
$dataConsentimento = null;

$possiveisColunasAceito = [
    'consentimento_aceito',
    'termo_aceito',
    'aceitou_termo',
    'consentimento'
];

$possiveisColunasData = [
    'consentimento_data',
    'data_consentimento',
    'termo_data',
    'data_aceite'
];

foreach ($possiveisColunasAceito as $coluna) {

    if (array_key_exists($coluna, $prontuario)) {

        $valor = $prontuario[$coluna];

        if (
            $valor === 1 ||
            $valor === '1' ||
            $valor === true ||
            strtolower((string)$valor) === 'sim' ||
            strtolower((string)$valor) === 'aceito'
        ) {
            $consentimentoAceito = true;
        }

        break;
    }
}

foreach ($possiveisColunasData as $coluna) {

    if (
        array_key_exists($coluna, $prontuario) &&
        !empty($prontuario[$coluna])
    ) {

        $dataConsentimento = $prontuario[$coluna];

        break;
    }
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
| INFORMAÇÕES DO PACIENTE
|--------------------------------------------------------------------------
*/

$nomePaciente = $prontuario['paciente'] ?? 'Paciente';

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Prontuário de <?= e($nomePaciente) ?> | Dentech
    </title>

    <link
        rel="icon"
        type="image/png"
        href="img/icon.PNG"
    >

    <link
        rel="stylesheet"
        href="css/vis_prontuario.css"
    >

</head>

<body>

<?php if (!$isPrint): ?>

    <?php include 'navbar.php'; ?>

<?php endif; ?>


<?php if (!$isPrint): ?>

<main class="content">

<?php else: ?>

<main class="print-content">

<?php endif; ?>


    <!-- =====================================================
         CABEÇALHO
    ====================================================== -->

    <header class="page-header">

        <div class="page-header-info">

            <div class="breadcrumb">

                <span>Prontuários</span>

                <span class="breadcrumb-separator">
                    /
                </span>

                <span>Visualizar prontuário</span>

            </div>

            <h1>
                <?= e($nomePaciente) ?>
            </h1>

            <p>
                Visualização completa dos dados e informações
                de saúde do paciente.
            </p>

        </div>


        <?php if (!$isPrint): ?>

        <div class="page-header-actions">

            <a
                href="editar_prontuario.php?id=<?= $id ?>"
                class="btn btn-primary"
            >
                <i class="fa-solid fa-pen"></i>
                Editar prontuário
            </a>

            <button
                type="button"
                class="btn btn-secondary"
                onclick="window.print()"
            >
                <i class="fa-solid fa-print"></i>
                Imprimir
            </button>

        </div>

        <?php endif; ?>

    </header>


    <!-- =====================================================
         RESUMO DO PACIENTE
    ====================================================== -->

    <section class="patient-summary">

        <div class="patient-avatar">

            <i class="fa-solid fa-user"></i>

        </div>

        <div class="patient-main">

            <span class="patient-label">
                PACIENTE
            </span>

            <h2>
                <?= e($nomePaciente) ?>
            </h2>

            <div class="patient-meta">

                <?php if ($idade !== null): ?>

                    <span>
                        <i class="fa-solid fa-cake-candles"></i>
                        <?= $idade ?> anos
                    </span>

                <?php endif; ?>


                <?php if (!empty($prontuario['sexo'])): ?>

                    <span>
                        <i class="fa-solid fa-venus-mars"></i>
                        <?= e($prontuario['sexo']) ?>
                    </span>

                <?php endif; ?>


                <?php if (!empty($prontuario['cpf'])): ?>

                    <span>
                        <i class="fa-solid fa-id-card"></i>
                        <?= e($prontuario['cpf']) ?>
                    </span>

                <?php endif; ?>

            </div>

        </div>


        <div class="consent-status">

            <?php if ($consentimentoAceito): ?>

                <span class="status-icon accepted">
                    <i class="fa-solid fa-check"></i>
                </span>

                <div>

                    <strong>
                        Consentimento aceito
                    </strong>

                    <?php if ($dataConsentimento): ?>

                        <span>
                            <?= date(
                                'd/m/Y H:i',
                                strtotime($dataConsentimento)
                            ) ?>
                        </span>

                    <?php else: ?>

                        <span>
                            Termo de consentimento confirmado
                        </span>

                    <?php endif; ?>

                </div>

            <?php else: ?>

                <span class="status-icon pending">
                    <i class="fa-solid fa-clock"></i>
                </span>

                <div>

                    <strong>
                        Consentimento pendente
                    </strong>

                    <span>
                        Termo ainda não confirmado
                    </span>

                </div>

            <?php endif; ?>

        </div>

    </section>


    <!-- =====================================================
         DADOS DO PACIENTE
    ====================================================== -->

    <section class="card">

        <div class="card-header">

            <div>

                <span class="card-kicker">
                    CADASTRO
                </span>

                <h2>
                    Dados do paciente
                </h2>

            </div>

            <span class="card-icon">
                <i class="fa-solid fa-user"></i>
            </span>

        </div>


        <div class="info-grid">

            <div class="info-item">

                <span>
                    Nome completo
                </span>

                <strong>
                    <?= valorOuTraco($prontuario['paciente'] ?? null) ?>
                </strong>

            </div>


            <div class="info-item">

                <span>
                    Data de nascimento
                </span>

                <strong>

                    <?php if (!empty($prontuario['nascimento'])): ?>

                        <?= date(
                            'd/m/Y',
                            strtotime($prontuario['nascimento'])
                        ) ?>

                    <?php else: ?>

                        —

                    <?php endif; ?>

                </strong>

            </div>


            <div class="info-item">

                <span>
                    Sexo
                </span>

                <strong>
                    <?= valorOuTraco($prontuario['sexo'] ?? null) ?>
                </strong>

            </div>


            <div class="info-item">

                <span>
                    Estado civil
                </span>

                <strong>
                    <?= valorOuTraco($prontuario['estado_civil'] ?? null) ?>
                </strong>

            </div>


            <div class="info-item">

                <span>
                    Profissão
                </span>

                <strong>
                    <?= valorOuTraco($prontuario['profissao'] ?? null) ?>
                </strong>

            </div>


            <div class="info-item">

                <span>
                    RG
                </span>

                <strong>
                    <?= valorOuTraco($prontuario['rg'] ?? null) ?>
                </strong>

            </div>


            <div class="info-item">

                <span>
                    CPF
                </span>

                <strong>
                    <?= valorOuTraco($prontuario['cpf'] ?? null) ?>
                </strong>

            </div>


            <div class="info-item">

                <span>
                    CEP
                </span>

                <strong>
                    <?= valorOuTraco($prontuario['cep'] ?? null) ?>
                </strong>

            </div>


            <div class="info-item info-item-wide">

                <span>
                    Endereço
                </span>

                <strong>
                    <?= nl2br(
                        valorOuTraco($prontuario['endereco'] ?? null)
                    ) ?>
                </strong>

            </div>


            <div class="info-item">

                <span>
                    Telefone
                </span>

                <strong>
                    <?= valorOuTraco($prontuario['telefone'] ?? null) ?>
                </strong>

            </div>


            <div class="info-item">

                <span>
                    E-mail
                </span>

                <strong>
                    <?= valorOuTraco($prontuario['email'] ?? null) ?>
                </strong>

            </div>

        </div>

    </section>


    <!-- =====================================================
         INFORMAÇÕES DE SAÚDE
    ====================================================== -->

    <section class="card">

        <div class="card-header">

            <div>

                <span class="card-kicker">
                    SAÚDE
                </span>

                <h2>
                    Histórico e informações de saúde
                </h2>

            </div>

            <span class="card-icon health">
                <i class="fa-solid fa-heart-pulse"></i>
            </span>

        </div>


        <div class="health-grid">

            <div class="health-item">

                <span>
                    Tratamento odontológico atual
                </span>

                <div>
                    <?= nl2br(
                        valorOuTraco(
                            $prontuario['tratamento_odonto'] ?? null
                        )
                    ) ?>
                </div>

            </div>


            <div class="health-item">

                <span>
                    Tratamento médico
                </span>

                <div>
                    <?= nl2br(
                        valorOuTraco(
                            $prontuario['tratamento_medico'] ?? null
                        )
                    ) ?>
                </div>

            </div>


            <div class="health-item">

                <span>
                    Medicamento contínuo
                </span>

                <div>
                    <?= nl2br(
                        valorOuTraco(
                            $prontuario['medicamento_continuo'] ?? null
                        )
                    ) ?>
                </div>

            </div>


            <div class="health-item">

                <span>
                    Alergia a medicamentos
                </span>

                <div>
                    <?= nl2br(
                        valorOuTraco(
                            $prontuario['alergia_medicamento'] ?? null
                        )
                    ) ?>
                </div>

            </div>


            <div class="health-item">

                <span>
                    Outras alergias
                </span>

                <div>
                    <?= nl2br(
                        valorOuTraco(
                            $prontuario['alergia_outras'] ?? null
                        )
                    ) ?>
                </div>

            </div>


            <div class="health-item">

                <span>
                    Problemas de saúde
                </span>

                <div>
                    <?= nl2br(
                        valorOuTraco(
                            $prontuario['problemas_saude'] ?? null
                        )
                    ) ?>
                </div>

            </div>


            <div class="health-item">

                <span>
                    Doenças transmissíveis
                </span>

                <div>
                    <?= nl2br(
                        valorOuTraco(
                            $prontuario['doencas_transmissiveis'] ?? null
                        )
                    ) ?>
                </div>

            </div>


            <div class="health-item">

                <span>
                    Histórico familiar de câncer
                </span>

                <div>
                    <?= nl2br(
                        valorOuTraco(
                            $prontuario['cancer_familiar'] ?? null
                        )
                    ) ?>
                </div>

            </div>


            <div class="health-item">

                <span>
                    Tratamento de câncer
                </span>

                <div>
                    <?= nl2br(
                        valorOuTraco(
                            $prontuario['tratamento_cancer'] ?? null
                        )
                    ) ?>
                </div>

            </div>

        </div>

    </section>


    <!-- =====================================================
         HÁBITOS
    ====================================================== -->

    <section class="card">

        <div class="card-header">

            <div>

                <span class="card-kicker">
                    HÁBITOS
                </span>

                <h2>
                    Hábitos e informações adicionais
                </h2>

            </div>

            <span class="card-icon habits">
                <i class="fa-solid fa-notes-medical"></i>
            </span>

        </div>


        <div class="info-grid">

            <div class="info-item">

                <span>
                    Gravidez
                </span>

                <strong>
                    <?= valorOuTraco(
                        $prontuario['gravida_meses'] ?? null
                    ) ?>
                </strong>

            </div>


            <div class="info-item">

                <span>
                    Tempo fumando
                </span>

                <strong>
                    <?= valorOuTraco(
                        $prontuario['fuma_tempo'] ?? null
                    ) ?>
                </strong>

            </div>


            <div class="info-item">

                <span>
                    Cigarros por dia
                </span>

                <strong>
                    <?= valorOuTraco(
                        $prontuario['fuma_cigarros_dia'] ?? null
                    ) ?>
                </strong>

            </div>


            <div class="info-item">

                <span>
                    Frequência de bebida alcoólica
                </span>

                <strong>
                    <?= valorOuTraco(
                        $prontuario['bebida_frequencia'] ?? null
                    ) ?>
                </strong>

            </div>


            <div class="info-item info-item-wide">

                <span>
                    Uso de drogas
                </span>

                <strong>
                    <?= nl2br(
                        valorOuTraco(
                            $prontuario['drogas_uso'] ?? null
                        )
                    ) ?>
                </strong>

            </div>

        </div>

    </section>


    <!-- =====================================================
         OBSERVAÇÕES
    ====================================================== -->

    <section class="card">

        <div class="card-header">

            <div>

                <span class="card-kicker">
                    OBSERVAÇÕES
                </span>

                <h2>
                    Observações do prontuário
                </h2>

            </div>

            <span class="card-icon notes">
                <i class="fa-solid fa-file-lines"></i>
            </span>

        </div>


        <div class="notes-content">

            <?php if (!empty($prontuario['observacoes'])): ?>

                <?= nl2br(
                    e($prontuario['observacoes'])
                ) ?>

            <?php else: ?>

                <span class="empty-text">
                    Nenhuma observação registrada.
                </span>

            <?php endif; ?>

        </div>

    </section>


    <!-- =====================================================
         PROCEDIMENTOS
    ====================================================== -->

    <section class="card">

        <div class="card-header">

            <div>

                <span class="card-kicker">
                    HISTÓRICO CLÍNICO
                </span>

                <h2>
                    Procedimentos realizados
                </h2>

            </div>

            <?php if (!$isPrint): ?>

                <a
                    href="adicionar_procedimento.php?prontuario_id=<?= $id ?>"
                    class="small-action"
                >
                    <i class="fa-solid fa-plus"></i>
                    Adicionar
                </a>

            <?php endif; ?>

        </div>


        <?php if (empty($procedimentos)): ?>

            <div class="empty-state">

                <div class="empty-icon">

                    <i class="fa-solid fa-tooth"></i>

                </div>

                <strong>
                    Nenhum procedimento registrado
                </strong>

                <span>
                    Os procedimentos realizados aparecerão nesta área.
                </span>

            </div>

        <?php else: ?>

            <div class="procedures-list">

                <?php foreach ($procedimentos as $proc): ?>

                    <article class="procedure-item">

                        <div class="procedure-date">

                            <strong>

                                <?php if (!empty($proc['data_procedimento'])): ?>

                                    <?= date(
                                        'd',
                                        strtotime(
                                            $proc['data_procedimento']
                                        )
                                    ) ?>

                                <?php else: ?>

                                    —

                                <?php endif; ?>

                            </strong>

                            <span>

                                <?php if (!empty($proc['data_procedimento'])): ?>

                                    <?= date(
                                        'M',
                                        strtotime(
                                            $proc['data_procedimento']
                                        )
                                    ) ?>

                                <?php endif; ?>

                            </span>

                        </div>


                        <div class="procedure-info">

                            <h3>
                                <?= e(
                                    $proc['titulo'] ?? 'Procedimento'
                                ) ?>
                            </h3>

                            <span>

                                <?php if (!empty($proc['data_procedimento'])): ?>

                                    <?= date(
                                        'd/m/Y',
                                        strtotime(
                                            $proc['data_procedimento']
                                        )
                                    ) ?>

                                <?php else: ?>

                                    Data não informada

                                <?php endif; ?>

                            </span>

                        </div>


                        <?php if (!$isPrint): ?>

                            <button
                                type="button"
                                class="procedure-view"
                                data-titulo="<?= e($proc['titulo'] ?? 'Procedimento') ?>"
                                data-descricao="<?= e($proc['descricao'] ?? '') ?>"
                                data-medicamentos="<?= e($proc['medicamentos'] ?? '') ?>"
                                data-data="<?= !empty($proc['data_procedimento'])
                                    ? date(
                                        'd/m/Y',
                                        strtotime(
                                            $proc['data_procedimento']
                                        )
                                    )
                                    : '—'
                                ?>"
                            >
                                <i class="fa-solid fa-eye"></i>
                                Visualizar
                            </button>

                        <?php endif; ?>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>


    <!-- =====================================================
         RODAPÉ DE AÇÕES
    ====================================================== -->

    <?php if (!$isPrint): ?>

        <div class="bottom-actions">

            <a
                href="prontuarios"
                class="btn btn-secondary"
            >
                <i class="fa-solid fa-arrow-left"></i>
                Voltar para prontuários
            </a>

            <a
                href="editar_prontuario.php?id=<?= $id ?>"
                class="btn btn-primary"
            >
                <i class="fa-solid fa-pen"></i>
                Editar prontuário
            </a>

        </div>

    <?php endif; ?>


</main>


<!-- =====================================================
     MODAL DE PROCEDIMENTO
====================================================== -->

<?php if (!$isPrint): ?>

<div
    id="procedureModal"
    class="modal"
>

    <div class="modal-overlay"></div>

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
                id="modalClose"
            >
                &times;
            </button>

        </div>


        <div
            id="modalBody"
            class="modal-body"
        ></div>


        <div class="modal-footer">

            <button
                type="button"
                class="btn btn-secondary"
                id="modalCloseBottom"
            >
                Fechar
            </button>

        </div>

    </div>

</div>


<script>

document.addEventListener('DOMContentLoaded', function () {

    const modal = document.getElementById('procedureModal');

    const modalTitle = document.getElementById('modalTitle');

    const modalBody = document.getElementById('modalBody');

    const modalClose = document.getElementById('modalClose');

    const modalCloseBottom =
        document.getElementById('modalCloseBottom');

    const overlay =
        modal.querySelector('.modal-overlay');


    function abrirModal(button) {

        const titulo =
            button.dataset.titulo || 'Procedimento';

        const descricao =
            button.dataset.descricao || '';

        const medicamentos =
            button.dataset.medicamentos || '';

        const data =
            button.dataset.data || '—';


        modalTitle.textContent = titulo;


        let html = '';

        html += `
            <div class="modal-info">
                <span>Data do procedimento</span>
                <strong>${data}</strong>
            </div>
        `;


        if (descricao.trim() !== '') {

            html += `
                <div class="modal-section">
                    <span>Descrição</span>
                    <p>
                        ${descricao.replace(/\n/g, '<br>')}
                    </p>
                </div>
            `;

        }


        if (medicamentos.trim() !== '') {

            html += `
                <div class="modal-section">
                    <span>Medicamentos receitados</span>
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


        modalBody.innerHTML = html;

        modal.classList.add('active');

        document.body.classList.add('modal-open');

    }


    function fecharModal() {

        modal.classList.remove('active');

        document.body.classList.remove('modal-open');

    }


    document
        .querySelectorAll('.procedure-view')
        .forEach(function (button) {

            button.addEventListener(
                'click',
                function () {

                    abrirModal(this);

                }
            );

        });


    modalClose.addEventListener(
        'click',
        fecharModal
    );


    modalCloseBottom.addEventListener(
        'click',
        fecharModal
    );


    overlay.addEventListener(
        'click',
        fecharModal
    );


    document.addEventListener(
        'keydown',
        function (event) {

            if (event.key === 'Escape') {

                fecharModal();

            }

        }
    );

});


<?php if ($isPrint): ?>

window.addEventListener(
    'load',
    function () {

        window.print();

    }
);

<?php endif; ?>

</script>

<?php endif; ?>


</body>

</html>