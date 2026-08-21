<?php

require_once 'config/auth.php';
require_once 'config/csrf.php';

exigirLogin();

require_once 'conexao/conexao.php';

/*
|--------------------------------------------------------------------------
| BUSCAR PACIENTES
|--------------------------------------------------------------------------
*/

$pacientes = $pdo->query("
    SELECT id, paciente
    FROM prontuarios
    ORDER BY paciente
")->fetchAll();

$erro = null;

/*
|--------------------------------------------------------------------------
| SALVAR ORÇAMENTO
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    validar_csrf();

    try {

        $pdo->beginTransaction();

        /*
        |--------------------------------------------------------------------------
        | DADOS PRINCIPAIS
        |--------------------------------------------------------------------------
        */

        $paciente_id = (int)($_POST['paciente_id'] ?? 0);

        $validade = $_POST['validade'] ?? '';

        $observacoes = trim(
            $_POST['observacoes'] ?? ''
        );


        /*
        |--------------------------------------------------------------------------
        | VALIDAÇÕES
        |--------------------------------------------------------------------------
        */

        if ($paciente_id <= 0) {
            throw new Exception(
                "Selecione um paciente válido."
            );
        }

        if (empty($validade)) {
            throw new Exception(
                "A data de validade é obrigatória."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | INSERIR ORÇAMENTO
        |--------------------------------------------------------------------------
        |
        | IMPORTANTE:
        | Sua tabela é "orcamento" no singular.
        |
        */

        $stmt = $pdo->prepare("
            INSERT INTO orcamentos (
                paciente_id,
                data_criacao,
                validade,
                observacoes
            )
            VALUES (?, ?, ?, ?)
        ");

        $stmt->execute([
            $paciente_id,
            date('Y-m-d'),
            $validade,
            $observacoes
        ]);

        $orcamento_id = $pdo->lastInsertId();


        /*
        |--------------------------------------------------------------------------
        | ITENS
        |--------------------------------------------------------------------------
        */

        $descricoes = $_POST['descricao'] ?? [];

        $quantidades = $_POST['quantidade'] ?? [];

        $valores = $_POST['valor'] ?? [];


        $total_itens = 0;

        $itens_salvos = 0;


        /*
        |--------------------------------------------------------------------------
        | INSERIR ITENS
        |--------------------------------------------------------------------------
        |
        | Sua tabela é "orcamento_itens" no singular.
        |
        */

        foreach ($descricoes as $i => $desc) {

            $desc = trim(
                $desc ?? ''
            );


            /*
            | Normalizar valor
            |
            | Aceita:
            | 1500.50
            | 1500,50
            */

            $valor_bruto = $valores[$i] ?? '';

            $valor_bruto = str_replace(
                'R$',
                '',
                $valor_bruto
            );

            $valor_bruto = trim(
                $valor_bruto
            );


            /*
            | Se vier no formato brasileiro:
            | 1.500,50
            |
            | transforma para:
            | 1500.50
            */

            if (
                strpos($valor_bruto, ',') !== false
            ) {

                $valor_bruto = str_replace(
                    '.',
                    '',
                    $valor_bruto
                );

                $valor_bruto = str_replace(
                    ',',
                    '.',
                    $valor_bruto
                );
            }


            $valor = (float)$valor_bruto;


            /*
            | Quantidade
            */

            $qtd = (int)(
                $quantidades[$i] ?? 1
            );


            if ($qtd <= 0) {
                $qtd = 1;
            }


            /*
            |--------------------------------------------------------------------------
            | VALIDAR ITEM
            |--------------------------------------------------------------------------
            */

            if (
                !empty($desc) &&
                $valor > 0
            ) {

                $subtotal = $qtd * $valor;

                $total_itens += $subtotal;


                /*
                |--------------------------------------------------------------------------
                | INSERT DO ITEM
                |--------------------------------------------------------------------------
                */

                $stmtItem = $pdo->prepare("
                    INSERT INTO orcamentos_itens (
                        orcamento_id,
                        descricao,
                        quantidade,
                        valor_unitario
                    )
                    VALUES (?, ?, ?, ?)
                ");

                $stmtItem->execute([
                    $orcamento_id,
                    $desc,
                    $qtd,
                    $valor
                ]);

                $itens_salvos++;
            }
        }


        /*
        |--------------------------------------------------------------------------
        | GARANTIR PELO MENOS UM ITEM
        |--------------------------------------------------------------------------
        */

        if ($itens_salvos === 0) {

            throw new Exception(
                "Adicione pelo menos 1 item válido ao orçamento."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | PARCELAMENTO
        |--------------------------------------------------------------------------
        */

        $num_parcelas = (int)(
            $_POST['num_parcelas'] ?? 1
        );


        if ($num_parcelas <= 0) {
            $num_parcelas = 1;
        }


        /*
        |--------------------------------------------------------------------------
        | GERAR PARCELAS
        |--------------------------------------------------------------------------
        */

        if (
            $num_parcelas > 0 &&
            $total_itens > 0
        ) {

            /*
            | Valor base
            */

            $valor_parcela_base = round(
                $total_itens / $num_parcelas,
                2
            );


            $stmtParcela = $pdo->prepare("
                INSERT INTO parcelas (
                    orcamento_id,
                    numero_parcela,
                    valor,
                    vencimento,
                    status
                )
                VALUES (?, ?, ?, ?, 'pendente')
            ");


            /*
            |--------------------------------------------------------------------------
            | CRIAR PARCELAS
            |--------------------------------------------------------------------------
            */

            for (
                $i = 1;
                $i <= $num_parcelas;
                $i++
            ) {

                /*
                | A última parcela recebe o ajuste
                | dos centavos para fechar exatamente
                | o valor total.
                */

                if (
                    $i === $num_parcelas
                ) {

                    $valor_final = round(
                        $total_itens -
                            (
                                $valor_parcela_base *
                                ($num_parcelas - 1)
                            ),
                        2
                    );
                } else {

                    $valor_final =
                        $valor_parcela_base;
                }


                /*
                | Vencimento:
                | 1ª parcela = próximo mês
                | 2ª parcela = mês seguinte
                | etc.
                */

                $vencimento = date(
                    'Y-m-d',
                    strtotime(
                        "+{$i} month"
                    )
                );


                $stmtParcela->execute([
                    $orcamento_id,
                    $i,
                    $valor_final,
                    $vencimento
                ]);
            }
        }


        /*
        |--------------------------------------------------------------------------
        | FINALIZAR TRANSAÇÃO
        |--------------------------------------------------------------------------
        */

        $pdo->commit();


        /*
        |--------------------------------------------------------------------------
        | IR PARA VISUALIZAÇÃO
        |--------------------------------------------------------------------------
        */

        header(
            "Location: visualizar_orcamento.php?id=" .
                $orcamento_id
        );

        exit;
    } catch (Exception $e) {

        /*
        |--------------------------------------------------------------------------
        | DESFAZER TRANSAÇÃO
        |--------------------------------------------------------------------------
        */

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }


        $msg = $e->getMessage();


        /*
        |--------------------------------------------------------------------------
        | MENSAGEM DE ERRO
        |--------------------------------------------------------------------------
        */

        if (
            strpos($msg, '1146') !== false
        ) {

            preg_match(
                "/Table '[^.]+\\.([^']+)' doesn't exist/",
                $msg,
                $matches
            );

            $tabela =
                $matches[1]
                ?? 'desconhecida';


            $erro =
                "Tabela não encontrada: " .
                "<strong>" .
                htmlspecialchars($tabela) .
                "</strong>. " .
                "Verifique se ela foi criada no banco.";
        } else {

            $erro =
                "Erro ao salvar: " .
                htmlspecialchars($msg);
        }


        error_log(
            "ERRO ORÇAMENTO: " .
                $msg
        );
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
        Novo Orçamento - Dentech
    </title>

    <link rel="stylesheet" href="css/global.css">
    <link rel="stylesheet" href="css/variables.css">
    <link rel="stylesheet" href="css/layout.css">
    <link rel="stylesheet" href="css/navbar.css">
    <link rel="stylesheet" href="css/new_orcamento.css">

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <link rel="icon" type="image/png" href="img/icon.PNG">


    <style>
        .item-row {
            position: relative;
        }

        .btn-remove-item {
            width: 38px;
            height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-top: 22px;
            border: 1px solid #fecaca;
            border-radius: 7px;
            background: #fff;
            color: #dc2626;
            cursor: pointer;
            transition: 0.18s ease;
        }

        .btn-remove-item:hover {
            background: #fef2f2;
            border-color: #fca5a5;
            color: #b91c1c;
        }

        @media (max-width: 700px) {
            .btn-remove-item {
                width: 100%;
                margin-top: 0;
            }
        }
    </style>

</head>


<body>

    <?php include 'navbar.php'; ?>


    <div class="container">

        <h1>
            Novo Orçamento
        </h1>


        <?php if (!empty($erro)): ?>

            <div
                class="erro"
                style="
                background:#ffebee;
                color:#c62828;
                padding:12px;
                border-radius:6px;
                margin-bottom:20px;
            ">

                <?= $erro ?>

            </div>

        <?php endif; ?>


        <form
            method="POST"
            id="formOrcamento">


            <?= csrf_field() ?>


            <!-- =====================================================
             PACIENTE
        ====================================================== -->

            <div class="form-group">

                <label>
                    Paciente
                </label>

                <select
                    name="paciente_id"
                    required>

                    <option value="">
                        Selecione
                    </option>

                    <?php foreach ($pacientes as $p): ?>

                        <option
                            value="<?= $p['id'] ?>">

                            <?= htmlspecialchars(
                                $p['paciente']
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- =====================================================
             VALIDADE
        ====================================================== -->

            <div class="form-group">

                <label>
                    Data de Validade
                </label>

                <input
                    type="date"
                    name="validade"
                    value="<?= date(
                                'Y-m-d',
                                strtotime('+30 days')
                            ) ?>"
                    required>

            </div>


            <!-- =====================================================
             ITENS DO ORÇAMENTO
        ====================================================== -->

            <div class="form-group">

                <label>
                    Itens do orçamento
                </label>


                <div id="itens-container">

                    <div class="item-row">

                        <div>

                            <input
                                type="text"
                                name="descricao[]"
                                placeholder="Ex: Clareamento dental"
                                required>

                        </div>


                        <div>

                            <input
                                type="number"
                                name="quantidade[]"
                                value="1"
                                min="1"
                                style="width:80px;">

                        </div>


                        <div>

                            <input
                                type="number"
                                name="valor[]"
                                step="0.01"
                                min="0"
                                placeholder="0.00"
                                required
                                class="item-valor">

                        </div>

                        <button
                            type="button"
                            class="btn-remove-item"
                            onclick="removerItem(this)"
                            title="Excluir item">
                            <i class="fa-solid fa-trash"></i>
                        </button>

                    </div>

                </div>


                <button
                    type="button"
                    class="btn-add-item"
                    onclick="adicionarItem()">

                    + Adicionar Item

                </button>

            </div>


            <!-- =====================================================
             PARCELAMENTO
        ====================================================== -->

            <div
                class="form-group"
                style="
                border-top:1px solid #eee;
                padding-top:20px;
                margin-top:20px;
            ">

                <label>
                    Parcelamento
                </label>


                <select
                    name="num_parcelas"
                    id="num_parcelas">

                    <option value="1">
                        À vista (1x)
                    </option>

                    <?php for ($i = 2; $i <= 24; $i++): ?>

                        <option value="<?= $i ?>">

                            <?= $i ?>x sem juros

                        </option>

                    <?php endfor; ?>

                </select>


                <div
                    id="preview_parcelas"
                    style="
                    margin-top:8px;
                    font-size:13px;
                    font-weight:600;
                    color:#7b3ff2;
                ">

                </div>

            </div>


            <!-- =====================================================
             OBSERVAÇÕES
        ====================================================== -->

            <div class="form-group">

                <label>
                    Observações (opcional)
                </label>

                <textarea
                    name="observacoes"
                    rows="3"
                    placeholder="Condições, descontos, etc..."></textarea>

            </div>


            <!-- =====================================================
             AÇÕES
        ====================================================== -->

            <button
                type="submit"
                class="btn">

                Criar Orçamento

            </button>


            <a
                href="orcamento"
                style="
                display:inline-block;
                margin-left:12px;
                color:var(--roxo-medio);
            ">

                Cancelar

            </a>

        </form>

    </div>


    <script>
        /*
|--------------------------------------------------------------------------
| ADICIONAR ITEM
|--------------------------------------------------------------------------
*/

        function adicionarItem() {

            const container =
                document.getElementById(
                    'itens-container'
                );


            const div =
                document.createElement('div');


            div.className =
                'item-row';


            div.innerHTML = `

        <div>

            <input
                type="text"
                name="descricao[]"
                placeholder="Ex: Restauração"
                required>

        </div>

        <div>

            <input
                type="number"
                name="quantidade[]"
                value="1"
                min="1"
                style="width:80px;">

        </div>

        <div>

            <input
                type="number"
                name="valor[]"
                step="0.01"
                min="0"
                placeholder="0.00"
                required
                class="item-valor">

        </div>

        <button
            type="button"
            class="btn-remove-item"
            onclick="removerItem(this)"
            title="Excluir item">
            <i class="fa-solid fa-trash"></i>
        </button>

    `;


            container.appendChild(div);


            /*
            | Atualizar preview quando o valor mudar
            */

            div
                .querySelector('.item-valor')
                .addEventListener(
                    'input',
                    calcularPreviewParcelas
                );


            div
                .querySelector('[name="quantidade[]"]')
                .addEventListener(
                    'input',
                    calcularPreviewParcelas
                );
        }


        /*
        |--------------------------------------------------------------------------
        | REMOVER ITEM
        |--------------------------------------------------------------------------
        */

        function removerItem(botao) {
            const container = document.getElementById('itens-container');
            const itens = container.querySelectorAll('.item-row');

            if (itens.length <= 1) {
                const primeiro = itens[0];

                if (primeiro) {
                    primeiro.querySelector('[name="descricao[]"]').value = '';
                    primeiro.querySelector('[name="quantidade[]"]').value = '1';
                    primeiro.querySelector('[name="valor[]"]').value = '';
                }

                calcularPreviewParcelas();
                return;
            }

            botao.closest('.item-row').remove();
            calcularPreviewParcelas();
        }

        /*
        |--------------------------------------------------------------------------
        | CALCULAR PREVIEW DAS PARCELAS
        |--------------------------------------------------------------------------
        */

        function calcularPreviewParcelas() {

            const qtdParcelas =
                parseInt(
                    document.getElementById(
                        'num_parcelas'
                    ).value
                ) || 1;


            let total = 0;


            document
                .querySelectorAll('.item-row')
                .forEach(row => {

                    const qtdInput =
                        row.querySelector(
                            '[name="quantidade[]"]'
                        );


                    const valInput =
                        row.querySelector(
                            '[name="valor[]"]'
                        );


                    const qtd =
                        parseInt(
                            qtdInput?.value
                        ) || 1;


                    const val =
                        parseFloat(
                            valInput?.value
                        ) || 0;


                    total +=
                        qtd * val;
                });


            const valorParcela =
                total / qtdParcelas;


            const preview =
                document.getElementById(
                    'preview_parcelas'
                );


            if (total === 0) {

                preview.textContent =
                    'Adicione itens para calcular parcelas';

                preview.style.color =
                    '#999';

                return;
            }


            if (qtdParcelas === 1) {

                preview.textContent =
                    `À vista: R$ ${
                total
                    .toFixed(2)
                    .replace('.', ',')
            }`;

            } else {

                preview.textContent =
                    `${qtdParcelas}x de R$ ${
                valorParcela
                    .toFixed(2)
                    .replace('.', ',')
            } (Total: R$ ${
                total
                    .toFixed(2)
                    .replace('.', ',')
            })`;
            }


            preview.style.color =
                '#7b3ff2';
        }


        /*
        |--------------------------------------------------------------------------
        | EVENTOS
        |--------------------------------------------------------------------------
        */

        document.addEventListener(
            'DOMContentLoaded',
            () => {

                /*
                | Parcelas
                */

                document
                    .getElementById('num_parcelas')
                    .addEventListener(
                        'change',
                        calcularPreviewParcelas
                    );


                /*
                | Valores
                */

                document
                    .querySelectorAll('.item-valor')
                    .forEach(input => {

                        input.addEventListener(
                            'input',
                            calcularPreviewParcelas
                        );

                    });


                /*
                | Quantidades
                */

                document
                    .querySelectorAll(
                        '[name="quantidade[]"]'
                    )
                    .forEach(input => {

                        input.addEventListener(
                            'input',
                            calcularPreviewParcelas
                        );

                    });


                /*
                | Preview inicial
                */

                calcularPreviewParcelas();

            }
        );
    </script>


</body>

</html>