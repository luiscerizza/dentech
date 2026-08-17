<?php
require_once 'conexao/conexao.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Prontuário não encontrado.");
}

$id = (int)$_GET['id'];

// Verificar modo impressão
$isPrint = isset($_GET['print']) && $_GET['print'] == '1';

// Buscar prontuário
$stmt = $pdo->prepare("SELECT * FROM prontuarios WHERE id = ?");
$stmt->execute([$id]);
$prontuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$prontuario) {
    die("Prontuário não encontrado.");
}

// Buscar consentimento
$stmtConsentimento = $pdo->prepare("
    SELECT aceito, data_aceite
    FROM consentimentos
    WHERE prontuario_id = ?
    ORDER BY id DESC
    LIMIT 1
");
$stmtConsentimento->execute([$id]);

$consentimento = $stmtConsentimento->fetch(PDO::FETCH_ASSOC);

$consentimentoAceito = $consentimento && (int)$consentimento['aceito'] === 1;

// Calcular idade
$dataNasc = new DateTime($prontuario['nascimento']);
$hoje = new DateTime();
$idade = $hoje->diff($dataNasc)->y;

// Buscar procedimentos
$stmtProc = $pdo->prepare("
    SELECT *
    FROM procedimentos
    WHERE paciente_id = ?
    ORDER BY data_procedimento DESC
");
$stmtProc->execute([$id]);
$procedimentos = $stmtProc->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        Prontuário - <?= htmlspecialchars($prontuario['paciente']) ?> | Dentech
    </title>

    <?php if (!$isPrint): ?>
        <link rel="stylesheet" href="css/navbar.css">
    <?php endif; ?>

    <link rel="stylesheet" href="css/vis_prontuario.css">
    <link rel="icon" type="image/png" href="img/icon.PNG">
</head>

<body>

    <?php if (!$isPrint): ?>
        <?php include 'navbar.php'; ?>
    <?php endif; ?>

    <div class="container">

        <!-- CABEÇALHO -->
        <div class="page-header">

            <div class="page-header-info">

                <div class="breadcrumb">
                    <span>Prontuários</span>
                    <span class="breadcrumb-separator">/</span>
                    <span>Visualização</span>
                </div>

                <h1>Prontuário do paciente</h1>

            </div>

        </div>

        <!-- IDENTIFICAÇÃO DO PACIENTE -->
        <div class="paciente-header">

            <div class="paciente-avatar">
                <?= strtoupper(substr($prontuario['paciente'], 0, 1)) ?>
            </div>

            <div class="paciente-info">

                <h2>
                    <?= htmlspecialchars($prontuario['paciente']) ?>
                </h2>

                <div class="paciente-detalhes">

                    <span>
                        📅
                        <?= date('d/m/Y', strtotime($prontuario['nascimento'])) ?>
                        (<?= $idade ?> anos)
                    </span>

                    <?php if (!empty($prontuario['cpf'])): ?>
                        <span>
                            👤 CPF: <?= htmlspecialchars($prontuario['cpf']) ?>
                        </span>
                    <?php endif; ?>

                    <?php if (!empty($prontuario['telefone'])): ?>
                        <span>
                            ☎ <?= htmlspecialchars($prontuario['telefone']) ?>
                        </span>
                    <?php endif; ?>

                </div>

                <?php if (!empty($prontuario['email'])): ?>
                    <div class="paciente-email">
                        <?= htmlspecialchars($prontuario['email']) ?>
                    </div>
                <?php endif; ?>

            </div>

            <?php if (!$isPrint): ?>

                <div class="paciente-acoes">

                    <a
                        href="editar_prontuario.php?id=<?= $id ?>"
                        class="btn btn-editar">
                        ✎ Editar
                    </a>

                    <button
                        type="button"
                        class="btn btn-imprimir"
                        onclick="window.open('visualizar_prontuario.php?id=<?= $id ?>&print=1', '_blank')">
                        🖨 Imprimir
                    </button>

                    <button
                        type="button"
                        class="btn btn-termo"
                        onclick="window.open('termo_conscentimento.php?id=<?= $id ?>', '_blank')">
                        📄 Termo de Consentimento
                    </button>

                </div>

            <?php endif; ?>

        </div>

        <!-- STATUS DO CONSENTIMENTO -->
        <div class="consentimento-status-card">

            <div class="consentimento-status-info">

                <strong>Consentimento LGPD</strong>

                <?php if ($consentimentoAceito): ?>

                    <span class="status-consentimento aceito">
                        ✓ Aceito
                    </span>

                    <?php if (!empty($consentimento['data_aceite'])): ?>

                        <span class="data-consentimento">
                            em
                            <?= date(
                                'd/m/Y H:i',
                                strtotime($consentimento['data_aceite'])
                            ) ?>
                        </span>

                    <?php endif; ?>

                <?php else: ?>

                    <span class="status-consentimento pendente">
                        ⚠ Pendente
                    </span>

                <?php endif; ?>

            </div>

            <?php if (!$isPrint && !$consentimentoAceito): ?>

                <a
                    href="termo_conscentimento.php?id=<?= $id ?>"
                    target="_blank"
                    class="btn-consentimento">
                    Solicitar consentimento
                </a>

            <?php endif; ?>

        </div>

        <!-- RESUMO -->
        <div class="tabs-card">

            <div class="tabs">

                <div class="tab active">
                    Resumo
                </div>

                <div class="tab">
                    Histórico
                </div>

                <div class="tab">
                    Documentos
                </div>

                <div class="tab">
                    Anexos
                </div>

                <div class="tab">
                    Financeiro
                </div>

            </div>

            <div class="resumo-grid">

                <!-- INFORMAÇÕES PESSOAIS -->
                <div class="card">

                    <h2>Informações pessoais</h2>

                    <div class="informacoes-lista">

                        <div class="informacao-item">
                            <strong>Nome</strong>
                            <span>
                                <?= htmlspecialchars($prontuario['paciente']) ?>
                            </span>
                        </div>

                        <div class="informacao-item">
                            <strong>Data de nascimento</strong>
                            <span>
                                <?= date(
                                    'd/m/Y',
                                    strtotime($prontuario['nascimento'])
                                ) ?>
                            </span>
                        </div>

                        <div class="informacao-item">
                            <strong>Sexo</strong>
                            <span>
                                <?= htmlspecialchars(
                                    $prontuario['sexo'] ?? '—'
                                ) ?>
                            </span>
                        </div>

                        <div class="informacao-item">
                            <strong>Estado civil</strong>
                            <span>
                                <?= htmlspecialchars(
                                    $prontuario['estado_civil'] ?? '—'
                                ) ?>
                            </span>
                        </div>

                        <div class="informacao-item">
                            <strong>Profissão</strong>
                            <span>
                                <?= htmlspecialchars(
                                    $prontuario['profissao'] ?? '—'
                                ) ?>
                            </span>
                        </div>

                        <div class="informacao-item">
                            <strong>CPF</strong>
                            <span>
                                <?= htmlspecialchars(
                                    $prontuario['cpf'] ?? '—'
                                ) ?>
                            </span>
                        </div>

                        <div class="informacao-item">
                            <strong>RG</strong>
                            <span>
                                <?= htmlspecialchars(
                                    $prontuario['rg'] ?? '—'
                                ) ?>
                            </span>
                        </div>

                        <div class="informacao-item">
                            <strong>Telefone</strong>
                            <span>
                                <?= htmlspecialchars(
                                    $prontuario['telefone'] ?? '—'
                                ) ?>
                            </span>
                        </div>

                        <div class="informacao-item">
                            <strong>E-mail</strong>
                            <span>
                                <?= htmlspecialchars(
                                    $prontuario['email'] ?? '—'
                                ) ?>
                            </span>
                        </div>

                        <div class="informacao-item">
                            <strong>CEP</strong>
                            <span>
                                <?= htmlspecialchars(
                                    $prontuario['cep'] ?? '—'
                                ) ?>
                            </span>
                        </div>

                        <div class="informacao-item informacao-endereco">
                            <strong>Endereço</strong>
                            <span>
                                <?= nl2br(
                                    htmlspecialchars(
                                        $prontuario['endereco'] ?? '—'
                                    )
                                ) ?>
                            </span>
                        </div>

                    </div>

                </div>

                <!-- OBSERVAÇÕES -->
                <div class="card">

                    <h2>Observações</h2>

                    <?php if (!empty($prontuario['observacoes'])): ?>

                        <div class="observacoes">
                            <?= nl2br(
                                htmlspecialchars(
                                    $prontuario['observacoes']
                                )
                            ) ?>
                        </div>

                    <?php else: ?>

                        <div class="observacoes vazio">
                            Nenhuma observação registrada.
                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>

        <!-- INFORMAÇÕES DE SAÚDE -->
        <div class="card saude-card">

            <h2>Informações de saúde</h2>

            <div class="saude-grid">

                <div class="saude-item">
                    <strong>Tratamento odontológico</strong>
                    <span>
                        <?= nl2br(
                            htmlspecialchars(
                                $prontuario['tratamento_odonto'] ?? '—'
                            )
                        ) ?>
                    </span>
                </div>

                <div class="saude-item">
                    <strong>Tratamento médico</strong>
                    <span>
                        <?= nl2br(
                            htmlspecialchars(
                                $prontuario['tratamento_medico'] ?? '—'
                            )
                        ) ?>
                    </span>
                </div>

                <div class="saude-item">
                    <strong>Medicamento contínuo</strong>
                    <span>
                        <?= nl2br(
                            htmlspecialchars(
                                $prontuario['medicamento_continuo'] ?? '—'
                            )
                        ) ?>
                    </span>
                </div>

                <div class="saude-item">
                    <strong>Alergia a medicamentos</strong>
                    <span>
                        <?= nl2br(
                            htmlspecialchars(
                                $prontuario['alergia_medicamento'] ?? '—'
                            )
                        ) ?>
                    </span>
                </div>

                <div class="saude-item">
                    <strong>Outras alergias</strong>
                    <span>
                        <?= nl2br(
                            htmlspecialchars(
                                $prontuario['alergia_outras'] ?? '—'
                            )
                        ) ?>
                    </span>
                </div>

                <div class="saude-item">
                    <strong>Problemas de saúde</strong>
                    <span>
                        <?= nl2br(
                            htmlspecialchars(
                                $prontuario['problemas_saude'] ?? '—'
                            )
                        ) ?>
                    </span>
                </div>

                <div class="saude-item">
                    <strong>Gravidez</strong>
                    <span>
                        <?= htmlspecialchars(
                            $prontuario['gravida_meses'] ?? '—'
                        ) ?>
                    </span>
                </div>

                <div class="saude-item">
                    <strong>Tabagismo</strong>
                    <span>
                        <?= htmlspecialchars(
                            $prontuario['fuma_tempo'] ?? '—'
                        ) ?>
                    </span>
                </div>

                <div class="saude-item">
                    <strong>Cigarros por dia</strong>
                    <span>
                        <?= htmlspecialchars(
                            $prontuario['fuma_cigarros_dia'] ?? '—'
                        ) ?>
                    </span>
                </div>

                <div class="saude-item">
                    <strong>Consumo de bebida alcoólica</strong>
                    <span>
                        <?= htmlspecialchars(
                            $prontuario['bebida_frequencia'] ?? '—'
                        ) ?>
                    </span>
                </div>

                <div class="saude-item">
                    <strong>Uso de drogas</strong>
                    <span>
                        <?= nl2br(
                            htmlspecialchars(
                                $prontuario['drogas_uso'] ?? '—'
                            )
                        ) ?>
                    </span>
                </div>

                <div class="saude-item">
                    <strong>Doenças transmissíveis</strong>
                    <span>
                        <?= nl2br(
                            htmlspecialchars(
                                $prontuario['doencas_transmissiveis'] ?? '—'
                            )
                        ) ?>
                    </span>
                </div>

                <div class="saude-item">
                    <strong>Câncer familiar</strong>
                    <span>
                        <?= nl2br(
                            htmlspecialchars(
                                $prontuario['cancer_familiar'] ?? '—'
                            )
                        ) ?>
                    </span>
                </div>

                <div class="saude-item">
                    <strong>Tratamento de câncer</strong>
                    <span>
                        <?= nl2br(
                            htmlspecialchars(
                                $prontuario['tratamento_cancer'] ?? '—'
                            )
                        ) ?>
                    </span>
                </div>

            </div>

        </div>

        <!-- PROCEDIMENTOS -->
        <div class="card procedimentos-card">

            <div class="card-header">

                <h2>Últimos atendimentos</h2>

                <?php if (!$isPrint): ?>

                    <a
                        href="adicionar_procedimento.php?prontuario_id=<?= $id ?>"
                        class="btn btn-add">
                        + Adicionar Procedimento
                    </a>

                <?php endif; ?>

            </div>

            <?php if (empty($procedimentos)): ?>

                <p class="empty">
                    Nenhum procedimento registrado.
                </p>

            <?php else: ?>

                <div class="table-wrapper">

                    <table class="tabela-procedimentos">

                        <thead>

                            <tr>
                                <th>Data</th>
                                <th>Procedimento</th>
                                <th>Descrição</th>
                                <th>Medicamentos</th>

                                <?php if (!$isPrint): ?>
                                    <th>Ações</th>
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
                                        <?= htmlspecialchars(
                                            $proc['titulo']
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= !empty($proc['descricao'])
                                            ? nl2br(
                                                htmlspecialchars(
                                                    $proc['descricao']
                                                )
                                            )
                                            : '—'
                                        ?>
                                    </td>

                                    <td>
                                        <?= !empty($proc['medicamentos'])
                                            ? nl2br(
                                                htmlspecialchars(
                                                    $proc['medicamentos']
                                                )
                                            )
                                            : '—'
                                        ?>
                                    </td>

                                    <?php if (!$isPrint): ?>

                                        <td>

                                            <button
                                                type="button"
                                                class="btn btn-visualizar"
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

        </div>

    </div>

    <?php if (!$isPrint): ?>

        <!-- MODAL -->
        <div id="modal" class="modal">

            <div class="modal-content">

                <div class="modal-header">

                    <h2
                        class="modal-title"
                        id="modalTitle">
                        Procedimento
                    </h2>

                    <button
                        type="button"
                        class="btn-close"
                        onclick="fecharModal()">
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
                        class="btn btn-fechar"
                        onclick="fecharModal()">
                        Fechar
                    </button>

                </div>

            </div>

        </div>

        <script>
            document
                .querySelectorAll('.btn-visualizar')
                .forEach(button => {

                    button.addEventListener('click', function() {

                        const titulo =
                            this.dataset.titulo;

                        const descricao =
                            this.dataset.descricao || '';

                        const medicamentos =
                            this.dataset.medicamentos || '';

                        const data =
                            this.dataset.data;

                        let html =
                            `<p><strong>Data:</strong> ${data}</p>`;

                        if (descricao.trim()) {

                            html +=
                                `<p>
                                    <strong>Descrição:</strong><br>
                                    ${descricao.replace(/\n/g, '<br>')}
                                </p>`;

                        }

                        if (medicamentos.trim()) {

                            html +=
                                `<p>
                                    <strong>Medicamentos receitados:</strong><br>
                                    ${medicamentos.replace(/\n/g, '<br>')}
                                </p>`;

                        }

                        if (
                            !descricao.trim() &&
                            !medicamentos.trim()
                        ) {

                            html +=
                                `<p>Nenhuma informação adicional.</p>`;

                        }

                        document
                            .getElementById('modalTitle')
                            .textContent = titulo;

                        document
                            .getElementById('modalBody')
                            .innerHTML = html;

                        document
                            .getElementById('modal')
                            .style.display = 'flex';

                    });

                });

            function fecharModal() {

                document
                    .getElementById('modal')
                    .style.display = 'none';

            }

            window.onclick = function(event) {

                const modal =
                    document.getElementById('modal');

                if (event.target === modal) {
                    fecharModal();
                }

            };
        </script>

    <?php endif; ?>

</body>

</html>