<?php

require_once 'config/auth.php';
exigirLogin();

require_once 'conexao/conexao.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];

/*
|--------------------------------------------------------------------------
| Variáveis
|--------------------------------------------------------------------------
*/

$erros = [];

$tipo = $_POST['tipo'] ?? 'receita';
$categoria = trim($_POST['categoria'] ?? '');
$descricao = trim($_POST['descricao'] ?? '');
$data = $_POST['data'] ?? date('Y-m-d');
$forma_pagamento = $_POST['forma_pagamento'] ?? '';
$status = $_POST['status'] ?? 'pendente';
$valor = $_POST['valor'] ?? '';
$observacoes = trim($_POST['observacoes'] ?? '');

/*
|--------------------------------------------------------------------------
| Métodos de pagamento
|--------------------------------------------------------------------------
*/

$formas_validas = [
    'Dinheiro',
    'PIX',
    'Cartão de débito',
    'Cartão de crédito',
    'Boleto',
    'Transferência bancária',
    'Cheque',
    'Outro'
];

/*
|--------------------------------------------------------------------------
| Processamento
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
    |--------------------------------------------------------------------------
    | CSRF
    |--------------------------------------------------------------------------
    */

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $erros[] = 'Token de segurança inválido. Atualize a página e tente novamente.';
    }

    /*
    |--------------------------------------------------------------------------
    | Tipo
    |--------------------------------------------------------------------------
    */

    if (!in_array($tipo, ['receita', 'despesa'], true)) {
        $erros[] = 'Selecione um tipo de lançamento válido.';
    }

    /*
    |--------------------------------------------------------------------------
    | Categoria
    |--------------------------------------------------------------------------
    */

    if ($categoria === '') {
        $erros[] = 'Informe a categoria.';
    }

    /*
    |--------------------------------------------------------------------------
    | Descrição
    |--------------------------------------------------------------------------
    */

    if ($descricao === '') {
        $erros[] = 'Informe a descrição do lançamento.';
    }

    /*
    |--------------------------------------------------------------------------
    | Data
    |--------------------------------------------------------------------------
    */

    if ($data === '') {

        $erros[] = 'Informe a data do lançamento.';
    } else {

        $dataObj = DateTime::createFromFormat('Y-m-d', $data);

        if (
            !$dataObj ||
            $dataObj->format('Y-m-d') !== $data
        ) {
            $erros[] = 'Informe uma data válida.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Forma de pagamento
    |--------------------------------------------------------------------------
    */

    if (!in_array($forma_pagamento, $formas_validas, true)) {
        $erros[] = 'Selecione uma forma de pagamento válida.';
    }

    if (!in_array($status, ['pago', 'pendente'], true)) {
        $erros[] = 'Selecione um status válido.';
    }

    /*
    |--------------------------------------------------------------------------
    | Valor
    |--------------------------------------------------------------------------
    */

    if ($valor === '') {

        $erros[] = 'Informe o valor.';
    } else {

        /*
        | Aceita:
        | 1500.50
        | 1.500,50
        | 1500,50
        */

        if (str_contains((string) $valor, ',') && str_contains((string) $valor, '.')) {
            $valor_limpo = str_replace('.', '', (string) $valor);
            $valor_limpo = str_replace(',', '.', $valor_limpo);
        } elseif (str_contains((string) $valor, ',')) {
            $valor_limpo = str_replace(',', '.', (string) $valor);
        } else {
            $valor_limpo = (string) $valor;
        }

        if (!is_numeric($valor_limpo)) {

            $erros[] = 'Informe um valor válido.';
        } else {

            $valor_numero = (float) $valor_limpo;

            if ($valor_numero <= 0) {
                $erros[] = 'O valor deve ser maior que zero.';
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Salvar
    |--------------------------------------------------------------------------
    */

    if (empty($erros)) {

        try {

            $sql = "
                INSERT INTO lancamentos_financeiros (
                    tipo,
                    categoria,
                    descricao,
                    data,
                    forma_pagamento,
                    valor,
                    status,
                    observacoes
                ) VALUES (
                    :tipo,
                    :categoria,
                    :descricao,
                    :data,
                    :forma_pagamento,
                    :valor,
                    :status,
                    :observacoes
                )
            ";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                ':tipo' => $tipo,
                ':categoria' => $categoria,
                ':descricao' => $descricao,
                ':data' => $data,
                ':forma_pagamento' => $forma_pagamento,
                ':valor' => $valor_numero,
                ':status' => $status,
                ':observacoes' => $observacoes !== ''
                    ? $observacoes
                    : null
            ]);

            /*
            |--------------------------------------------------------------------------
            | Novo CSRF
            |--------------------------------------------------------------------------
            */

            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            /*
            |--------------------------------------------------------------------------
            | Redirecionamento
            |--------------------------------------------------------------------------
            */

            header('Location: financeiro.php?sucesso=1');
            exit;
        } catch (PDOException $e) {

            $erros[] = 'Não foi possível salvar o lançamento. Tente novamente.';
        }
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

    <title>Novo Lançamento | Dentech</title>

    <link rel="stylesheet" href="css/global.css">
    <link rel="stylesheet" href="css/variables.css">
    <link rel="stylesheet" href="css/layout.css">
    <link rel="stylesheet" href="css/navbar.css">
    <link rel="stylesheet" href="css/novo_lancamento.css">

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <link rel="icon" type="image/png" href="img/icon.PNG">

</head>

<body>

    <?php include 'navbar.php'; ?>

    <main class="container">

        <!-- =====================================================
             CABEÇALHO
        ====================================================== -->

        <div class="page-header">

            <div class="page-header-info">

                <div class="breadcrumb">

                    <span>Financeiro</span>

                    <span class="breadcrumb-separator">
                        /
                    </span>

                    <span>Novo lançamento</span>

                </div>

                <h1>
                    Novo Lançamento
                </h1>

                <p>
                    Registre uma nova receita ou despesa no financeiro.
                </p>

            </div>

        </div>


        <!-- =====================================================
             ERROS
        ====================================================== -->

        <?php if (!empty($erros)): ?>

            <div class="alert alert-error">

                <div class="alert-icon">

                    <i class="fa-solid fa-circle-exclamation"></i>

                </div>

                <div>

                    <?php foreach ($erros as $erro): ?>

                        <p>
                            <?= htmlspecialchars($erro) ?>
                        </p>

                    <?php endforeach; ?>

                </div>

            </div>

        <?php endif; ?>


        <!-- =====================================================
             FORMULÁRIO
        ====================================================== -->

        <form
            method="POST"
            class="form-card"
            autocomplete="off">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($csrf_token) ?>">


            <!-- =================================================
                 TIPO
            ================================================== -->

            <div class="form-section">

                <div class="section-header">

                    <div class="section-icon">

                        <i class="fa-solid fa-arrow-right-arrow-left"></i>

                    </div>

                    <div>

                        <h2>
                            Tipo de lançamento
                        </h2>

                        <p>
                            Informe se o lançamento representa uma entrada ou saída.
                        </p>

                    </div>

                </div>


                <div class="tipo-grid">

                    <!-- RECEITA -->

                    <label class="tipo-option receita">

                        <input
                            type="radio"
                            name="tipo"
                            value="receita"
                            <?= $tipo === 'receita' ? 'checked' : '' ?>>

                        <span class="tipo-content">

                            <span class="tipo-icon">

                                <i class="fa-solid fa-arrow-trend-up"></i>

                            </span>

                            <span>

                                <strong>
                                    Receita
                                </strong>

                                <small>
                                    Entrada de dinheiro
                                </small>

                            </span>

                        </span>

                    </label>


                    <!-- DESPESA -->

                    <label class="tipo-option despesa">

                        <input
                            type="radio"
                            name="tipo"
                            value="despesa"
                            <?= $tipo === 'despesa' ? 'checked' : '' ?>>

                        <span class="tipo-content">

                            <span class="tipo-icon">

                                <i class="fa-solid fa-arrow-trend-down"></i>

                            </span>

                            <span>

                                <strong>
                                    Despesa
                                </strong>

                                <small>
                                    Saída de dinheiro
                                </small>

                            </span>

                        </span>

                    </label>

                </div>

            </div>


            <!-- =================================================
                 DADOS DO LANÇAMENTO
            ================================================== -->

            <div class="form-section">

                <div class="section-header">

                    <div class="section-icon">

                        <i class="fa-solid fa-file-invoice"></i>

                    </div>

                    <div>

                        <h2>
                            Dados do lançamento
                        </h2>

                        <p>
                            Preencha as informações financeiras.
                        </p>

                    </div>

                </div>


                <div class="form-grid">


                    <!-- CATEGORIA -->

                    <div class="form-group">

                        <label for="categoria">
                            Categoria
                        </label>

                        <input
                            type="text"
                            id="categoria"
                            name="categoria"
                            value="<?= htmlspecialchars($categoria) ?>"
                            placeholder="Ex.: Consulta, Material, Salário..."
                            maxlength="100"
                            required>

                    </div>


                    <!-- DATA -->

                    <div class="form-group">

                        <label for="data">
                            Data
                        </label>

                        <input
                            type="date"
                            id="data"
                            name="data"
                            value="<?= htmlspecialchars($data) ?>"
                            required>

                    </div>


                    <!-- DESCRIÇÃO -->

                    <div class="form-group form-group-full">

                        <label for="descricao">
                            Descrição
                        </label>

                        <input
                            type="text"
                            id="descricao"
                            name="descricao"
                            value="<?= htmlspecialchars($descricao) ?>"
                            placeholder="Ex.: Limpeza - Nome do paciente"
                            maxlength="255"
                            required>

                    </div>


                    <!-- FORMA DE PAGAMENTO -->

                    <div class="form-group">

                        <label for="forma_pagamento">
                            Forma de pagamento
                        </label>

                        <select
                            id="forma_pagamento"
                            name="forma_pagamento"
                            required>

                            <option value="">
                                Selecione a forma de pagamento
                            </option>

                            <?php foreach ($formas_validas as $forma): ?>

                                <option
                                    value="<?= htmlspecialchars($forma) ?>"
                                    <?= $forma_pagamento === $forma ? 'selected' : '' ?>>

                                    <?= htmlspecialchars($forma) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- VALOR -->

                    <div class="form-group">

                        <label for="valor">
                            Valor
                        </label>

                        <div class="input-money">

                            <span>
                                R$
                            </span>

                            <input
                                type="text"
                                id="valor"
                                name="valor"
                                value="<?= htmlspecialchars($valor) ?>"
                                placeholder="0,00"
                                inputmode="decimal"
                                required>

                        </div>

                    </div>


                    <!-- STATUS -->

                    <div class="form-group">

                        <label for="status">
                            Status
                        </label>

                        <select
                            id="status"
                            name="status"
                            required>

                            <option value="pendente" <?= $status === 'pendente' ? 'selected' : '' ?>>
                                Pendente
                            </option>

                            <option value="pago" <?= $status === 'pago' ? 'selected' : '' ?>>
                                Pago
                            </option>

                        </select>

                    </div>

                </div>

            </div>


            <!-- =================================================
                 OBSERVAÇÕES
            ================================================== -->

            <div class="form-section">

                <div class="section-header">

                    <div class="section-icon">

                        <i class="fa-solid fa-note-sticky"></i>

                    </div>

                    <div>

                        <h2>
                            Observações
                        </h2>

                        <p>
                            Campo opcional.
                        </p>

                    </div>

                </div>


                <div class="form-group">

                    <textarea
                        id="observacoes"
                        name="observacoes"
                        rows="4"
                        maxlength="1000"
                        placeholder="Adicione alguma observação sobre este lançamento..."><?= htmlspecialchars($observacoes) ?></textarea>

                </div>

            </div>


            <!-- =================================================
                 AÇÕES
            ================================================== -->

            <div class="form-actions">

                <a
                    href="financeiro.php"
                    class="btn btn-cancelar">

                    <i class="fa-solid fa-xmark"></i>

                    Cancelar

                </a>


                <button
                    type="submit"
                    class="btn btn-salvar">

                    <i class="fa-solid fa-check"></i>

                    Salvar Lançamento

                </button>

            </div>

        </form>

    </main>


    <!-- =========================================================
         MÁSCARA DE VALOR
    ========================================================== -->

    <script>
        const campoValor = document.getElementById('valor');

        campoValor.addEventListener('input', function() {

            let valor = this.value;

            valor = valor.replace(/\D/g, '');

            if (!valor) {

                this.value = '';

                return;
            }

            valor = (parseInt(valor, 10) / 100).toFixed(2);

            valor = valor.replace('.', ',');

            valor = valor.replace(
                /\B(?=(\d{3})+(?!\d))/g,
                '.'
            );

            this.value = valor;

        });
    </script>

</body>

</html>