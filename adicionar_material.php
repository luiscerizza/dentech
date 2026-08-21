<?php

require_once 'config/auth.php';

exigirLogin();

require_once 'conexao/conexao.php';

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ==============================
    // CSRF
    // ==============================
    if (
        empty($_POST['csrf_token']) ||
        empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        http_response_code(403);
        die('Token de segurança inválido.');
    }

    try {

        // ==============================
        // DADOS
        // ==============================
        $nome = trim($_POST['nome'] ?? '');
        $unidade = trim($_POST['unidade'] ?? 'unidade');

        $quantidade = str_replace(
            ',',
            '.',
            trim($_POST['quantidade'] ?? '0')
        );

        $estoque_minimo = str_replace(
            ',',
            '.',
            trim($_POST['estoque_minimo'] ?? '5')
        );

        $valor_item = str_replace(
            ',',
            '.',
            trim($_POST['valor_item'] ?? '0')
        );

        $valor_sugerido = str_replace(
            ',',
            '.',
            trim($_POST['valor_sugerido'] ?? '0')
        );

        // ==============================
        // VALIDAÇÕES
        // ==============================

        if ($nome === '') {
            throw new Exception(
                'Nome do material é obrigatório.'
            );
        }

        if (!is_numeric($quantidade) || $quantidade < 0) {
            throw new Exception(
                'A quantidade inicial deve ser maior ou igual a zero.'
            );
        }

        if (!is_numeric($estoque_minimo) || $estoque_minimo < 0) {
            throw new Exception(
                'O estoque mínimo deve ser maior ou igual a zero.'
            );
        }

        if (!is_numeric($valor_item) || $valor_item < 0) {
            throw new Exception(
                'O valor do item deve ser maior ou igual a zero.'
            );
        }

        if (!is_numeric($valor_sugerido) || $valor_sugerido < 0) {
            throw new Exception(
                'O valor sugerido deve ser maior ou igual a zero.'
            );
        }

        /*
         * O valor sugerido não pode ser inferior
         * ao valor do item.
         */
        if ((float)$valor_sugerido < (float)$valor_item) {
            throw new Exception(
                'O valor sugerido não pode ser menor que o valor do item.'
            );
        }

        // ==============================
        // INSERT
        // ==============================

        $stmt = $pdo->prepare("
            INSERT INTO estoque (
                nome,
                quantidade,
                unidade,
                estoque_minimo,
                valor_item,
                valor_sugerido
            )
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $nome,
            $quantidade,
            $unidade,
            $estoque_minimo,
            $valor_item,
            $valor_sugerido
        ]);

        header('Location: inventario.php');
        exit;
    } catch (Exception $e) {

        $erro = $e->getMessage();
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

    <title>Novo Material - Dentech</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="css/global.css">
    <link rel="stylesheet" href="css/variables.css">
    <link rel="stylesheet" href="css/layout.css">
    <link rel="stylesheet" href="css/add_material.css">
    <link rel="stylesheet" href="css/navbar.css">
    <link rel="icon" type="image/png" href="img/icon.PNG">

</head>

<body>

    <?php include 'navbar.php'; ?>

    <div class="content">

        <div class="container">

            <!-- CABEÇALHO -->

            <div class="page-header">

                <div>
                    <h1>Novo material</h1>

                    <div class="breadcrumb">
                        <span>Estoque</span>
                        <span class="breadcrumb-separator">›</span>
                        <strong>Novo material</strong>
                    </div>
                </div>

                <div class="page-actions">

                    <a
                        href="inventario.php"
                        class="btn-cancelar">
                        Cancelar
                    </a>

                    <button
                        type="submit"
                        form="form-material"
                        class="btn-salvar">
                        Cadastrar material
                    </button>

                </div>

            </div>

            <?php if (!empty($erro)): ?>

                <div class="erro">
                    <strong>Não foi possível cadastrar o material.</strong>
                    <span>
                        <?= htmlspecialchars($erro) ?>
                    </span>
                </div>

            <?php endif; ?>


            <!-- FORMULÁRIO -->

            <form
                method="POST"
                id="form-material">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">


                <!-- CARD PRINCIPAL -->

                <section class="form-card">

                    <div class="card-header">

                        <div>

                            <h2>Informações do material</h2>

                            <p>
                                Cadastre os dados do material e os valores
                                utilizados nos procedimentos.
                            </p>

                        </div>

                    </div>


                    <div class="card-body">


                        <!-- NOME + UNIDADE -->

                        <div class="form-row">

                            <div class="form-group form-col">

                                <label for="nome">
                                    Nome do material
                                    <span class="required">*</span>
                                </label>

                                <input
                                    type="text"
                                    id="nome"
                                    name="nome"
                                    maxlength="255"
                                    required
                                    value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>"
                                    placeholder="Ex: Anestésico, Luvas, Resina...">

                            </div>


                            <div class="form-group form-col">

                                <label for="unidade">
                                    Unidade
                                </label>

                                <select
                                    id="unidade"
                                    name="unidade">

                                    <option
                                        value="unidade"
                                        <?= (($_POST['unidade'] ?? 'unidade') === 'unidade')
                                            ? 'selected'
                                            : '' ?>>
                                        Unidade(s)
                                    </option>

                                    <option
                                        value="frasco"
                                        <?= (($_POST['unidade'] ?? '') === 'frasco')
                                            ? 'selected'
                                            : '' ?>>
                                        Frasco(s)
                                    </option>

                                    <option
                                        value="pacote"
                                        <?= (($_POST['unidade'] ?? '') === 'pacote')
                                            ? 'selected'
                                            : '' ?>>
                                        Pacote(s)
                                    </option>

                                    <option
                                        value="mL"
                                        <?= (($_POST['unidade'] ?? '') === 'mL')
                                            ? 'selected'
                                            : '' ?>>
                                        mL
                                    </option>

                                    <option
                                        value="g"
                                        <?= (($_POST['unidade'] ?? '') === 'g')
                                            ? 'selected'
                                            : '' ?>>
                                        g
                                    </option>

                                </select>

                            </div>

                        </div>


                        <!-- QUANTIDADE + MÍNIMO -->

                        <div class="form-row">

                            <div class="form-group form-col">

                                <label for="quantidade">
                                    Quantidade inicial
                                </label>

                                <input
                                    type="number"
                                    id="quantidade"
                                    name="quantidade"
                                    step="0.01"
                                    min="0"
                                    value="<?= htmlspecialchars($_POST['quantidade'] ?? '0') ?>"
                                    placeholder="0">

                                <small>
                                    Quantidade disponível inicialmente no estoque.
                                </small>

                            </div>


                            <div class="form-group form-col">

                                <label for="estoque_minimo">
                                    Estoque mínimo
                                </label>

                                <input
                                    type="number"
                                    id="estoque_minimo"
                                    name="estoque_minimo"
                                    step="0.01"
                                    min="0"
                                    value="<?= htmlspecialchars($_POST['estoque_minimo'] ?? '5') ?>"
                                    placeholder="5">

                                <small>
                                    O sistema poderá utilizar esse valor para alertar
                                    quando o estoque estiver baixo.
                                </small>

                            </div>

                        </div>


                        <!-- VALORES -->

                        <div class="section-divider">

                            <h3>Valores do material</h3>

                            <p>
                                Esses valores serão utilizados posteriormente
                                no cálculo dos procedimentos.
                            </p>

                        </div>


                        <div class="form-row">

                            <div class="form-group form-col">

                                <label for="valor_item">
                                    Valor do item
                                    <span class="required">*</span>
                                </label>

                                <div class="money-input">

                                    <span>R$</span>

                                    <input
                                        type="number"
                                        id="valor_item"
                                        name="valor_item"
                                        step="0.01"
                                        min="0"
                                        required
                                        value="<?= htmlspecialchars($_POST['valor_item'] ?? '0.00') ?>"
                                        placeholder="0,00">

                                </div>

                                <small>
                                    Valor/custo de uma unidade do material.
                                </small>

                            </div>


                            <div class="form-group form-col">

                                <label for="valor_sugerido">
                                    Valor sugerido
                                    <span class="required">*</span>
                                </label>

                                <div class="money-input">

                                    <span>R$</span>

                                    <input
                                        type="number"
                                        id="valor_sugerido"
                                        name="valor_sugerido"
                                        step="0.01"
                                        min="0"
                                        required
                                        value="<?= htmlspecialchars($_POST['valor_sugerido'] ?? '0.00') ?>"
                                        placeholder="0,00">

                                </div>

                                <small>
                                    Valor de referência utilizado no cálculo
                                    dos procedimentos.
                                </small>

                            </div>

                        </div>


                        <!-- AVISO -->

                        <div class="info-box">

                            <div class="info-icon">
                                $
                            </div>

                            <div>

                                <strong>
                                    Como esses valores serão utilizados?
                                </strong>

                                <p>
                                    O valor do item será utilizado para calcular
                                    o custo dos materiais consumidos. O valor
                                    sugerido servirá como referência no momento
                                    de registrar um procedimento.
                                </p>

                            </div>

                        </div>

                    </div>

                </section>

            </form>

        </div>

    </div>

</body>

</html>