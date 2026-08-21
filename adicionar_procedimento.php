<?php

require_once 'config/auth.php';
exigirLogin();

require_once 'conexao/conexao.php';

/*
|--------------------------------------------------------------------------
| Verificação do prontuário
|--------------------------------------------------------------------------
*/

if (!isset($_GET['prontuario_id']) || !is_numeric($_GET['prontuario_id'])) {
    die("Prontuário não especificado.");
}

$prontuario_id = (int) $_GET['prontuario_id'];

/*
|--------------------------------------------------------------------------
| Agendamento
|--------------------------------------------------------------------------
| O procedimento deve ser iniciado a partir de um agendamento confirmado.
| Porém, mantemos o prontuario_id como obrigatório para compatibilidade.
|--------------------------------------------------------------------------
*/

$agendamento_id = null;

if (isset($_GET['agendamento_id']) && is_numeric($_GET['agendamento_id'])) {
    $agendamento_id = (int) $_GET['agendamento_id'];
}

/*
|--------------------------------------------------------------------------
| Buscar paciente
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT id, paciente
    FROM prontuarios
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$prontuario_id]);

$prontuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$prontuario) {
    die("Prontuário não encontrado.");
}

$paciente = $prontuario['paciente'];

/*
|--------------------------------------------------------------------------
| Se veio de um agendamento, verificar se está confirmado
|--------------------------------------------------------------------------
*/

$agendamento = null;

if ($agendamento_id !== null) {

    $stmt = $pdo->prepare("
        SELECT
            id,
            paciente_id,
            paciente_nome,
            procedimento,
            data,
            horario,
            status
        FROM agendamentos
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$agendamento_id]);

    $agendamento = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$agendamento) {
        die("Agendamento não encontrado.");
    }

    if ((int)$agendamento['paciente_id'] !== $prontuario_id) {
        die("O agendamento não pertence a este paciente.");
    }

    if ($agendamento['status'] !== 'confirmado') {
        die("Este agendamento ainda não está confirmado.");
    }
}

/*
|--------------------------------------------------------------------------
| Buscar materiais disponíveis no estoque
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        id,
        nome,
        quantidade,
        unidade,
        estoque_minimo,
        valor_item,
        valor_sugerido
    FROM estoque
    WHERE quantidade > 0
    ORDER BY nome ASC
");

$materiais = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        Novo Procedimento -
        <?= htmlspecialchars($paciente) ?>
        | Dentech
    </title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="css/global.css">
    <link rel="stylesheet" href="css/variables.css">
    <link rel="stylesheet" href="css/layout.css">
    <link rel="stylesheet" href="css/add_procedimento.css">
    <link rel="stylesheet" href="css/navbar.css">
    <link rel="icon" type="image/png" href="img/icon.PNG">

</head>

<body>

    <?php include 'navbar.php'; ?>

    <div class="container">

        <main>

            <h1>Novo Procedimento</h1>

            <p class="subtitle">
                Paciente:
                <strong><?= htmlspecialchars($paciente) ?></strong>
            </p>

            <?php if ($agendamento): ?>

                <div class="agendamento-info">

                    <strong>Agendamento confirmado</strong>

                    <span>
                        <?= htmlspecialchars($agendamento['procedimento']) ?>
                    </span>

                    <span>
                        <?= date('d/m/Y', strtotime($agendamento['data'])) ?>
                        às
                        <?= date('H:i', strtotime($agendamento['horario'])) ?>
                    </span>

                </div>

            <?php endif; ?>


            <form id="procedimentoForm">

                <input
                    type="hidden"
                    id="prontuarioId"
                    value="<?= $prontuario_id ?>">

                <input
                    type="hidden"
                    id="agendamentoId"
                    value="<?= $agendamento_id ?? '' ?>">

                <!-- =====================================================
                 DADOS DO PROCEDIMENTO
            ====================================================== -->

                <section class="form-section">

                    <h2>Dados do procedimento</h2>

                    <div class="form-group">

                        <label for="titulo">
                            Nome do procedimento
                        </label>

                        <input
                            type="text"
                            id="titulo"
                            placeholder="Ex: Clareamento dental, Restauração..."
                            required>

                    </div>


                    <div class="form-group">

                        <label for="data_procedimento">
                            Data do procedimento
                        </label>

                        <input
                            type="date"
                            id="data_procedimento"
                            value="<?= date('Y-m-d') ?>"
                            required>

                    </div>


                    <div class="form-group">

                        <label for="descricao">
                            Descrição do procedimento
                        </label>

                        <textarea
                            id="descricao"
                            placeholder="Detalhes clínicos, observações, etc..."></textarea>

                    </div>


                    <div class="form-group">

                        <label for="medicamentos">
                            Medicamentos receitados
                        </label>

                        <textarea
                            id="medicamentos"
                            placeholder="Ex: Amoxicilina 500mg – 1 comprimido de 8/8h por 7 dias"></textarea>

                    </div>

                </section>


                <!-- =====================================================
                 MATERIAIS
            ====================================================== -->

                <section class="form-section">

                    <div class="section-header">

                        <div>

                            <h2>Materiais utilizados</h2>

                            <p>
                                Selecione os materiais utilizados no procedimento.
                            </p>

                        </div>

                        <button
                            type="button"
                            class="btn btn-add-material"
                            id="btnAdicionarMaterial">
                            + Adicionar material
                        </button>

                    </div>


                    <div id="materiaisContainer">

                        <div class="material-row">

                            <div class="material-field material-select">

                                <label>Material</label>

                                <select
                                    class="material"
                                    required>

                                    <option value="">
                                        Selecione um material
                                    </option>

                                    <?php foreach ($materiais as $material): ?>

                                        <option
                                            value="<?= (int)$material['id'] ?>"
                                            data-estoque="<?= htmlspecialchars($material['quantidade']) ?>"
                                            data-unidade="<?= htmlspecialchars($material['unidade']) ?>"
                                            data-valor="<?= htmlspecialchars($material['valor_item']) ?>"
                                            data-sugerido="<?= htmlspecialchars($material['valor_sugerido']) ?>">

                                            <?= htmlspecialchars($material['nome']) ?>

                                            —
                                            Estoque:
                                            <?= htmlspecialchars($material['quantidade']) ?>
                                            <?= htmlspecialchars($material['unidade']) ?>

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>


                            <div class="material-field">

                                <label>Quantidade</label>

                                <input
                                    type="number"
                                    class="quantidade-material"
                                    min="0.01"
                                    step="0.01"
                                    value="1"
                                    required>

                                <small class="estoque-disponivel">
                                    Selecione um material
                                </small>

                            </div>


                            <div class="material-field">

                                <label>Valor do item</label>

                                <input
                                    type="text"
                                    class="valor-material"
                                    value="R$ 0,00"
                                    readonly>

                            </div>


                            <div class="material-field">

                                <label>Valor sugerido</label>

                                <input
                                    type="text"
                                    class="valor-sugerido-material"
                                    value="R$ 0,00"
                                    readonly>

                            </div>


                            <div class="material-field">

                                <label>Subtotal</label>

                                <input
                                    type="text"
                                    class="subtotal-material"
                                    value="R$ 0,00"
                                    readonly>

                            </div>


                            <button
                                type="button"
                                class="btn-remove-material"
                                title="Remover material">
                                ×
                            </button>

                        </div>

                    </div>


                    <!-- =================================================
                     RESUMO DOS MATERIAIS
                ================================================== -->

                    <div class="materiais-resumo">

                        <div>

                            <span>
                                Custo dos materiais
                            </span>

                            <strong id="totalMateriais">
                                R$ 0,00
                            </strong>

                        </div>


                        <div>

                            <span>
                                Valor sugerido dos materiais
                            </span>

                            <strong id="totalSugerido">
                                R$ 0,00
                            </strong>

                        </div>

                    </div>

                </section>


                <!-- =====================================================
                 VALORES
            ====================================================== -->

                <section class="form-section">

                    <h2>Valores do procedimento</h2>


                    <div class="valor-grid">

                        <div class="form-group">

                            <label>
                                Custo dos materiais
                            </label>

                            <input
                                type="text"
                                id="valorMateriais"
                                value="R$ 0,00"
                                readonly>

                        </div>


                        <div class="form-group">

                            <label>
                                Valor sugerido dos materiais
                            </label>

                            <input
                                type="text"
                                id="valorSugerido"
                                value="R$ 0,00"
                                readonly>

                        </div>


                        <div class="form-group">

                            <label for="maoObra">
                                Mão de obra / procedimento
                            </label>

                            <input
                                type="number"
                                id="maoObra"
                                min="0"
                                step="0.01"
                                value="0.00">

                        </div>

                    </div>


                    <div class="valor-final-box">

                        <span>
                            Valor final do procedimento
                        </span>

                        <strong id="valorFinal">
                            R$ 0,00
                        </strong>

                    </div>

                </section>


                <!-- =====================================================
                 AÇÕES
            ====================================================== -->

                <div class="actions">

                    <button
                        type="button"
                        class="btn btn-cancel"
                        onclick="voltar()">
                        Cancelar
                    </button>

                    <button
                        type="submit"
                        class="btn btn-save"
                        id="btnSalvar">
                        Adicionar Procedimento
                    </button>

                </div>

            </form>

        </main>

    </div>


    <script>
        const materiaisDisponiveis = <?= json_encode(
                                            $materiais,
                                            JSON_UNESCAPED_UNICODE |
                                                JSON_UNESCAPED_SLASHES |
                                                JSON_HEX_TAG |
                                                JSON_HEX_APOS |
                                                JSON_HEX_AMP |
                                                JSON_HEX_QUOT
                                        ) ?>;


        /*
        |--------------------------------------------------------------------------
        | Formatação monetária
        |--------------------------------------------------------------------------
        */

        function formatarMoeda(valor) {

            valor = Number(valor) || 0;

            return valor.toLocaleString('pt-BR', {
                style: 'currency',
                currency: 'BRL'
            });

        }


        /*
        |--------------------------------------------------------------------------
        | Criar opções do estoque
        |--------------------------------------------------------------------------
        */

        function gerarOpcoesMateriais() {

            let html = `
        <option value="">
            Selecione um material
        </option>
    `;

            materiaisDisponiveis.forEach(material => {

                html += `
            <option
                value="${material.id}"
                data-estoque="${material.quantidade}"
                data-unidade="${material.unidade}"
                data-valor="${material.valor_item}"
                data-sugerido="${material.valor_sugerido}"
            >
                ${escapeHtml(material.nome)}
                —
                Estoque: ${material.quantidade} ${escapeHtml(material.unidade)}
            </option>
        `;

            });

            return html;

        }


        /*
        |--------------------------------------------------------------------------
        | Escape HTML
        |--------------------------------------------------------------------------
        */

        function escapeHtml(text) {

            const div = document.createElement('div');

            div.textContent = text ?? '';

            return div.innerHTML;

        }


        /*
        |--------------------------------------------------------------------------
        | Atualizar linha do material
        |--------------------------------------------------------------------------
        */

        function atualizarLinha(linha) {

            const select = linha.querySelector('.material');

            const quantidadeInput =
                linha.querySelector('.quantidade-material');

            const valorInput =
                linha.querySelector('.valor-material');

            const sugeridoInput =
                linha.querySelector('.valor-sugerido-material');

            const subtotalInput =
                linha.querySelector('.subtotal-material');

            const estoqueTexto =
                linha.querySelector('.estoque-disponivel');

            const option =
                select.options[select.selectedIndex];


            if (!option || !option.value) {

                valorInput.value = 'R$ 0,00';

                sugeridoInput.value = 'R$ 0,00';

                subtotalInput.value = 'R$ 0,00';

                estoqueTexto.textContent =
                    'Selecione um material';

                return;

            }


            const estoque =
                Number(option.dataset.estoque) || 0;

            const valor =
                Number(option.dataset.valor) || 0;

            const sugerido =
                Number(option.dataset.sugerido) || 0;

            const quantidade =
                Number(quantidadeInput.value) || 0;


            valorInput.value =
                formatarMoeda(valor);

            sugeridoInput.value =
                formatarMoeda(sugerido);


            const subtotal =
                valor * quantidade;

            subtotalInput.value =
                formatarMoeda(subtotal);


            estoqueTexto.textContent =
                `Disponível: ${estoque} ${option.dataset.unidade}`;


            if (quantidade > estoque) {

                estoqueTexto.classList.add('estoque-insuficiente');

                estoqueTexto.textContent =
                    `Estoque insuficiente. Disponível: ${estoque} ${option.dataset.unidade}`;

            } else {

                estoqueTexto.classList.remove('estoque-insuficiente');

            }


            atualizarTotais();

        }


        /*
        |--------------------------------------------------------------------------
        | Atualizar totais
        |--------------------------------------------------------------------------
        */

        function atualizarTotais() {

            let totalMateriais = 0;

            let totalSugerido = 0;


            document
                .querySelectorAll('.material-row')
                .forEach(linha => {

                    const select =
                        linha.querySelector('.material');

                    const quantidadeInput =
                        linha.querySelector('.quantidade-material');

                    if (!select.value) {
                        return;
                    }


                    const option =
                        select.options[select.selectedIndex];

                    const quantidade =
                        Number(quantidadeInput.value) || 0;

                    const valor =
                        Number(option.dataset.valor) || 0;

                    const sugerido =
                        Number(option.dataset.sugerido) || 0;


                    totalMateriais +=
                        valor * quantidade;

                    totalSugerido +=
                        sugerido * quantidade;

                });


            document.getElementById('totalMateriais').textContent =
                formatarMoeda(totalMateriais);

            document.getElementById('totalSugerido').textContent =
                formatarMoeda(totalSugerido);


            document.getElementById('valorMateriais').value =
                formatarMoeda(totalMateriais);

            document.getElementById('valorSugerido').value =
                formatarMoeda(totalSugerido);


            atualizarValorFinal();

        }


        /*
        |--------------------------------------------------------------------------
        | Atualizar valor final
        |--------------------------------------------------------------------------
        */

        function atualizarValorFinal() {

            let totalMateriais = 0;

            let maoObra =
                Number(document.getElementById('maoObra').value) || 0;


            document
                .querySelectorAll('.material-row')
                .forEach(linha => {

                    const select =
                        linha.querySelector('.material');

                    const quantidadeInput =
                        linha.querySelector('.quantidade-material');

                    if (!select.value) {
                        return;
                    }


                    const option =
                        select.options[select.selectedIndex];

                    const quantidade =
                        Number(quantidadeInput.value) || 0;

                    const valor =
                        Number(option.dataset.valor) || 0;


                    totalMateriais +=
                        valor * quantidade;

                });


            const valorFinal =
                totalMateriais + maoObra;


            document.getElementById('valorFinal').textContent =
                formatarMoeda(valorFinal);

        }


        /*
        |--------------------------------------------------------------------------
        | Adicionar nova linha
        |--------------------------------------------------------------------------
        */

        function adicionarLinhaMaterial() {

            const container =
                document.getElementById('materiaisContainer');


            const linha =
                document.createElement('div');

            linha.className =
                'material-row';


            linha.innerHTML = `

        <div class="material-field material-select">

            <label>Material</label>

            <select class="material" required>

                ${gerarOpcoesMateriais()}

            </select>

        </div>


        <div class="material-field">

            <label>Quantidade</label>

            <input
                type="number"
                class="quantidade-material"
                min="0.01"
                step="0.01"
                value="1"
                required
            >

            <small class="estoque-disponivel">
                Selecione um material
            </small>

        </div>


        <div class="material-field">

            <label>Valor do item</label>

            <input
                type="text"
                class="valor-material"
                value="R$ 0,00"
                readonly
            >

        </div>


        <div class="material-field">

            <label>Valor sugerido</label>

            <input
                type="text"
                class="valor-sugerido-material"
                value="R$ 0,00"
                readonly
            >

        </div>


        <div class="material-field">

            <label>Subtotal</label>

            <input
                type="text"
                class="subtotal-material"
                value="R$ 0,00"
                readonly
            >

        </div>


        <button
            type="button"
            class="btn-remove-material"
            title="Remover material"
        >
            ×
        </button>

    `;


            container.appendChild(linha);

            configurarLinha(linha);

        }


        /*
        |--------------------------------------------------------------------------
        | Configurar eventos de uma linha
        |--------------------------------------------------------------------------
        */

        function configurarLinha(linha) {

            const select =
                linha.querySelector('.material');

            const quantidade =
                linha.querySelector('.quantidade-material');

            const remover =
                linha.querySelector('.btn-remove-material');


            select.addEventListener(
                'change',
                () => atualizarLinha(linha)
            );


            quantidade.addEventListener(
                'input',
                () => atualizarLinha(linha)
            );


            remover.addEventListener(
                'click',
                () => {

                    const linhas =
                        document.querySelectorAll('.material-row');


                    if (linhas.length <= 1) {

                        select.value = '';

                        quantidade.value = 1;

                        atualizarLinha(linha);

                        return;

                    }


                    linha.remove();

                    atualizarTotais();

                }
            );

        }


        /*
        |--------------------------------------------------------------------------
        | Inicializar primeira linha
        |--------------------------------------------------------------------------
        */

        document
            .querySelectorAll('.material-row')
            .forEach(configurarLinha);


        /*
        |--------------------------------------------------------------------------
        | Botão adicionar material
        |--------------------------------------------------------------------------
        */

        document
            .getElementById('btnAdicionarMaterial')
            .addEventListener(
                'click',
                adicionarLinhaMaterial
            );


        /*
        |--------------------------------------------------------------------------
        | Mão de obra
        |--------------------------------------------------------------------------
        */

        document
            .getElementById('maoObra')
            .addEventListener(
                'input',
                atualizarValorFinal
            );


        /*
        |--------------------------------------------------------------------------
        | Voltar
        |--------------------------------------------------------------------------
        */

        function voltar() {

            const prontuarioId =
                document.getElementById('prontuarioId').value;

            window.location =
                'visualizar_prontuario.php?id=' + prontuarioId;

        }


        /*
        |--------------------------------------------------------------------------
        | Enviar formulário
        |--------------------------------------------------------------------------
        */

        document
            .getElementById('procedimentoForm')
            .addEventListener('submit', async function(e) {

                e.preventDefault();


                const btnSalvar =
                    document.getElementById('btnSalvar');


                /*
                |--------------------------------------------------------------
                | Montar materiais
                |--------------------------------------------------------------
                */

                const materiais = [];


                let erroEstoque = false;


                document
                    .querySelectorAll('.material-row')
                    .forEach(linha => {

                        const select =
                            linha.querySelector('.material');

                        const quantidadeInput =
                            linha.querySelector('.quantidade-material');


                        if (!select.value) {
                            return;
                        }


                        const option =
                            select.options[select.selectedIndex];


                        const estoque =
                            Number(option.dataset.estoque) || 0;

                        const quantidade =
                            Number(quantidadeInput.value) || 0;


                        if (quantidade <= 0) {

                            erroEstoque = true;

                            alert(
                                'A quantidade utilizada deve ser maior que zero.'
                            );

                            return;

                        }


                        if (quantidade > estoque) {

                            erroEstoque = true;

                            alert(
                                `Estoque insuficiente para "${option.text.split('—')[0].trim()}".\n\n` +
                                `Disponível: ${estoque} ${option.dataset.unidade}\n` +
                                `Solicitado: ${quantidade}`
                            );

                            return;

                        }


                        materiais.push({

                            estoque_id: Number(option.value),

                            quantidade: quantidade

                        });

                    });


                if (erroEstoque) {
                    return;
                }


                /*
                |--------------------------------------------------------------
                | Verificar materiais duplicados
                |--------------------------------------------------------------
                */

                const ids =
                    materiais.map(material => material.estoque_id);


                const idsDuplicados =
                    ids.filter(
                        (id, index) =>
                        ids.indexOf(id) !== index
                    );


                if (idsDuplicados.length > 0) {

                    alert(
                        'O mesmo material foi adicionado mais de uma vez. ' +
                        'Ajuste a quantidade em uma única linha.'
                    );

                    return;

                }


                /*
                |--------------------------------------------------------------
                | Dados
                |--------------------------------------------------------------
                */

                const dados = {

                    prontuario_id: Number(
                        document.getElementById('prontuarioId').value
                    ),

                    agendamento_id: document.getElementById('agendamentoId').value ?
                        Number(
                            document.getElementById('agendamentoId').value
                        ) :
                        null,

                    titulo: document.getElementById('titulo').value.trim(),

                    data_procedimento: document.getElementById('data_procedimento').value,

                    descricao: document.getElementById('descricao').value.trim(),

                    medicamentos: document.getElementById('medicamentos').value.trim(),

                    valor_mao_obra: Number(
                        document.getElementById('maoObra').value
                    ) || 0,

                    materiais: materiais

                };


                if (!dados.titulo) {

                    alert(
                        'Informe o nome do procedimento.'
                    );

                    return;

                }


                /*
                |--------------------------------------------------------------
                | Evitar duplo envio
                |--------------------------------------------------------------
                */

                btnSalvar.disabled = true;

                btnSalvar.textContent =
                    'Salvando...';


                try {

                    const formData =
                        new FormData();


                    formData.append(
                        'csrf_token',
                        '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>'
                    );


                    formData.append(
                        'dados',
                        JSON.stringify(dados)
                    );


                    const response =
                        await fetch(
                            'salvar_procedimento.php', {
                                method: 'POST',
                                body: formData
                            }
                        );


                    const result =
                        await response.json();


                    if (result.success) {

                        alert(
                            'Procedimento adicionado com sucesso!'
                        );


                        window.location =
                            'visualizar_prontuario.php?id=' +
                            dados.prontuario_id;


                        return;

                    }


                    alert(
                        'Erro: ' +
                        (
                            result.error ||
                            'Não foi possível salvar o procedimento.'
                        )
                    );


                } catch (error) {

                    console.error(error);

                    alert(
                        'Erro de conexão. Tente novamente.'
                    );

                } finally {

                    btnSalvar.disabled = false;

                    btnSalvar.textContent =
                        'Adicionar Procedimento';

                }

            });
    </script>

</body>

</html>