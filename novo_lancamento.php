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

$parcelado = isset($_POST['parcelado']) && $_POST['parcelado'] === '1';
$quantidade_parcelas = (int) ($_POST['quantidade_parcelas'] ?? 2);
$primeiro_vencimento = $_POST['primeiro_vencimento'] ?? date('Y-m-d');

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
| Funções auxiliares
|--------------------------------------------------------------------------
*/

function normalizarValorParaCentavos(string $valor): ?int
{
    $valor = trim($valor);

    if ($valor === '') {
        return null;
    }

    if (str_contains($valor, ',') && str_contains($valor, '.')) {
        $valor = str_replace('.', '', $valor);
        $valor = str_replace(',', '.', $valor);
    } elseif (str_contains($valor, ',')) {
        $valor = str_replace(',', '.', $valor);
    }

    if (!is_numeric($valor)) {
        return null;
    }

    $valor = (float) $valor;

    if ($valor <= 0) {
        return null;
    }

    return (int) round($valor * 100);
}

function adicionarMesPreservandoDia(string $data, int $meses): string
{
    $dataObj = new DateTime($data);
    $diaOriginal = (int) $dataObj->format('d');

    $dataObj->modify('first day of this month');
    $dataObj->modify('+' . $meses . ' month');

    $ultimoDia = (int) $dataObj->format('t');
    $diaFinal = min($diaOriginal, $ultimoDia);

    $dataObj->setDate(
        (int) $dataObj->format('Y'),
        (int) $dataObj->format('m'),
        $diaFinal
    );

    return $dataObj->format('Y-m-d');
}

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

    /*
    |--------------------------------------------------------------------------
    | Status
    |--------------------------------------------------------------------------
    */

    if (!in_array($status, ['pago', 'pendente'], true)) {
        $erros[] = 'Selecione um status válido.';
    }

    /*
    |--------------------------------------------------------------------------
    | Valor
    |--------------------------------------------------------------------------
    */

    $valor_centavos = normalizarValorParaCentavos((string) $valor);

    if ($valor === '') {
        $erros[] = 'Informe o valor.';
    } elseif ($valor_centavos === null) {
        $erros[] = 'Informe um valor válido maior que zero.';
    }

    /*
    |--------------------------------------------------------------------------
    | Parcelamento
    |--------------------------------------------------------------------------
    */

    if ($parcelado) {

        if ($quantidade_parcelas < 2 || $quantidade_parcelas > 60) {
            $erros[] = 'A quantidade de parcelas deve estar entre 2 e 60.';
        }

        if ($status === 'pago') {
            $erros[] = 'Lançamentos parcelados devem ser criados como pendentes. Cada parcela poderá ser paga individualmente.';
        }

        if ($primeiro_vencimento === '') {

            $erros[] = 'Informe o primeiro vencimento.';
        } else {

            $vencimentoObj = DateTime::createFromFormat('Y-m-d', $primeiro_vencimento);

            if (
                !$vencimentoObj ||
                $vencimentoObj->format('Y-m-d') !== $primeiro_vencimento
            ) {
                $erros[] = 'Informe um primeiro vencimento válido.';
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

            $pdo->beginTransaction();

            /*
            |--------------------------------------------------------------------------
            | Lançamento à vista
            |--------------------------------------------------------------------------
            */

            if (!$parcelado) {

                $sql = "
                    INSERT INTO lancamentos_financeiros (
                        tipo,
                        categoria,
                        descricao,
                        data,
                        data_pagamento,
                        forma_pagamento,
                        valor,
                        parcelas,
                        status,
                        observacoes
                    ) VALUES (
                        :tipo,
                        :categoria,
                        :descricao,
                        :data,
                        :data_pagamento,
                        :forma_pagamento,
                        :valor,
                        1,
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
                    ':data_pagamento' => $status === 'pago' ? $data : null,
                    ':forma_pagamento' => $forma_pagamento,
                    ':valor' => $valor_centavos / 100,
                    ':status' => $status,
                    ':observacoes' => $observacoes !== ''
                        ? $observacoes
                        : null
                ]);

                /*
            |--------------------------------------------------------------------------
            | Lançamento parcelado
            |--------------------------------------------------------------------------
            */
            } else {

                $sql = "
                    INSERT INTO lancamentos_financeiros (
                        tipo,
                        categoria,
                        descricao,
                        data,
                        data_pagamento,
                        forma_pagamento,
                        valor,
                        parcelas,
                        status,
                        observacoes
                    ) VALUES (
                        :tipo,
                        :categoria,
                        :descricao,
                        :data,
                        NULL,
                        :forma_pagamento,
                        :valor,
                        :parcelas,
                        'pendente',
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
                    ':valor' => $valor_centavos / 100,
                    ':parcelas' => $quantidade_parcelas,
                    ':observacoes' => $observacoes !== ''
                        ? $observacoes
                        : null
                ]);

                $lancamento_id = (int) $pdo->lastInsertId();

                /*
                |--------------------------------------------------------------------------
                | Distribuição das parcelas em centavos
                |--------------------------------------------------------------------------
                */

                $valorBase = intdiv($valor_centavos, $quantidade_parcelas);
                $resto = $valor_centavos % $quantidade_parcelas;

                $sqlParcela = "
                    INSERT INTO lancamentos_parcelas (
                        lancamento_id,
                        numero_parcela,
                        valor,
                        vencimento,
                        status,
                        data_pagamento
                    ) VALUES (
                        :lancamento_id,
                        :numero_parcela,
                        :valor,
                        :vencimento,
                        'pendente',
                        NULL
                    )
                ";

                $stmtParcela = $pdo->prepare($sqlParcela);

                for ($i = 1; $i <= $quantidade_parcelas; $i++) {

                    $valorParcelaCentavos = $valorBase;

                    if ($i <= $resto) {
                        $valorParcelaCentavos++;
                    }

                    $vencimento = adicionarMesPreservandoDia(
                        $primeiro_vencimento,
                        $i - 1
                    );

                    $stmtParcela->execute([
                        ':lancamento_id' => $lancamento_id,
                        ':numero_parcela' => $i,
                        ':valor' => $valorParcelaCentavos / 100,
                        ':vencimento' => $vencimento
                    ]);
                }
            }

            $pdo->commit();

            /*
            |--------------------------------------------------------------------------
            | Novo CSRF
            |--------------------------------------------------------------------------
            */

            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            header('Location: financeiro.php?sucesso=1');
            exit;
        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

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

                    <div class="form-group">

                        <label for="status">
                            Status
                        </label>

                        <select
                            id="status"
                            name="status"
                            required>

                            <option
                                value="pendente"
                                <?= $status === 'pendente' ? 'selected' : '' ?>>

                                Pendente

                            </option>

                            <option
                                value="pago"
                                <?= $status === 'pago' ? 'selected' : '' ?>>

                                Pago

                            </option>

                        </select>

                    </div>

                </div>

            </div>

            <!-- =================================================
                 PARCELAMENTO
            ================================================== -->

            <div class="form-section">

                <div class="section-header">

                    <div class="section-icon">
                        <i class="fa-solid fa-layer-group"></i>
                    </div>

                    <div>

                        <h2>
                            Parcelamento
                        </h2>

                        <p>
                            Opcional. Cada parcela terá vencimento e pagamento independentes.
                        </p>

                    </div>

                </div>

                <label class="parcelamento-toggle">

                    <input
                        type="checkbox"
                        id="parcelado"
                        name="parcelado"
                        value="1"
                        <?= $parcelado ? 'checked' : '' ?>>

                    <span class="parcelamento-toggle-content">

                        <span class="parcelamento-toggle-icon">
                            <i class="fa-solid fa-check"></i>
                        </span>

                        <span>

                            <strong>
                                Parcelar lançamento
                            </strong>

                            <small>
                                Crie parcelas individuais para controlar os pagamentos.
                            </small>

                        </span>

                    </span>

                </label>

                <div
                    id="parcelamento-config"
                    class="parcelamento-config <?= $parcelado ? 'is-visible' : '' ?>">

                    <div class="form-grid">

                        <div class="form-group">

                            <label for="quantidade_parcelas">
                                Quantidade de parcelas
                            </label>

                            <input
                                type="number"
                                id="quantidade_parcelas"
                                name="quantidade_parcelas"
                                min="2"
                                max="60"
                                step="1"
                                value="<?= htmlspecialchars((string) $quantidade_parcelas) ?>">

                        </div>

                        <div class="form-group">

                            <label for="primeiro_vencimento">
                                Primeiro vencimento
                            </label>

                            <input
                                type="date"
                                id="primeiro_vencimento"
                                name="primeiro_vencimento"
                                value="<?= htmlspecialchars($primeiro_vencimento) ?>">

                        </div>

                    </div>

                    <div class="parcelamento-aviso">

                        <i class="fa-solid fa-circle-info"></i>

                        <span>
                            Lançamentos parcelados serão criados como pendentes.
                            Cada parcela poderá ser paga individualmente.
                        </span>

                    </div>

                    <div
                        id="parcelas-preview"
                        class="parcelas-preview"
                        aria-live="polite">
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

    <script>
        /*
        |--------------------------------------------------------------------------
        | Elementos
        |--------------------------------------------------------------------------
        */

        const campoValor = document.getElementById('valor');
        const campoParcelado = document.getElementById('parcelado');
        const configParcelamento = document.getElementById('parcelamento-config');
        const campoQuantidade = document.getElementById('quantidade_parcelas');
        const campoPrimeiroVencimento = document.getElementById('primeiro_vencimento');
        const campoStatus = document.getElementById('status');
        const previewParcelas = document.getElementById('parcelas-preview');

        /*
        |--------------------------------------------------------------------------
        | Máscara de valor
        |--------------------------------------------------------------------------
        */

        campoValor.addEventListener('input', function() {

            let valor = this.value;

            valor = valor.replace(/\D/g, '');

            if (!valor) {

                this.value = '';

                atualizarPreview();

                return;
            }

            valor = (parseInt(valor, 10) / 100).toFixed(2);

            valor = valor.replace('.', ',');

            valor = valor.replace(
                /\B(?=(\d{3})+(?!\d))/g,
                '.'
            );

            this.value = valor;

            atualizarPreview();

        });

        /*
        |--------------------------------------------------------------------------
        | Utilitários
        |--------------------------------------------------------------------------
        */

        function obterValorEmCentavos(valor) {

            let limpo = String(valor || '')
                .replace(/\./g, '')
                .replace(',', '.');

            const numero = Number(limpo);

            if (!Number.isFinite(numero) || numero <= 0) {
                return 0;
            }

            return Math.round(numero * 100);
        }

        function formatarMoeda(centavos) {

            return (centavos / 100).toLocaleString('pt-BR', {
                style: 'currency',
                currency: 'BRL'
            });

        }

        function adicionarMesPreservandoDia(data, meses) {

            const partes = data.split('-');

            if (partes.length !== 3) {
                return '';
            }

            const ano = Number(partes[0]);
            const mes = Number(partes[1]);
            const dia = Number(partes[2]);

            if (!ano || !mes || !dia) {
                return '';
            }

            const novaData = new Date(ano, mes - 1 + meses, 1);

            const ultimoDia = new Date(
                novaData.getFullYear(),
                novaData.getMonth() + 1,
                0
            ).getDate();

            const diaFinal = Math.min(dia, ultimoDia);

            const anoFinal = novaData.getFullYear();
            const mesFinal = String(novaData.getMonth() + 1).padStart(2, '0');
            const diaFormatado = String(diaFinal).padStart(2, '0');

            return `${anoFinal}-${mesFinal}-${diaFormatado}`;

        }

        function formatarData(data) {

            if (!data) {
                return '-';
            }

            const [ano, mes, dia] = data.split('-');

            if (!ano || !mes || !dia) {
                return '-';
            }

            return `${dia}/${mes}/${ano}`;

        }

        /*
        |--------------------------------------------------------------------------
        | Exibir / ocultar parcelamento
        |--------------------------------------------------------------------------
        */

        function atualizarEstadoParcelamento() {

            if (campoParcelado.checked) {

                configParcelamento.classList.add('is-visible');

                campoQuantidade.required = true;
                campoPrimeiroVencimento.required = true;

                /*
                | Parcelado não pode ser criado diretamente como pago.
                */

                campoStatus.value = 'pendente';
                campoStatus.disabled = true;

            } else {

                configParcelamento.classList.remove('is-visible');

                campoQuantidade.required = false;
                campoPrimeiroVencimento.required = false;

                campoStatus.disabled = false;

            }

            atualizarPreview();

        }

        /*
        |--------------------------------------------------------------------------
        | Prévia das parcelas
        |--------------------------------------------------------------------------
        */

        function atualizarPreview() {

            if (!campoParcelado.checked) {

                previewParcelas.innerHTML = '';

                return;

            }

            const valorCentavos = obterValorEmCentavos(campoValor.value);
            const quantidade = Number(campoQuantidade.value);
            const primeiroVencimento = campoPrimeiroVencimento.value;

            if (
                valorCentavos <= 0 ||
                !Number.isInteger(quantidade) ||
                quantidade < 2 ||
                quantidade > 60 ||
                !primeiroVencimento
            ) {

                previewParcelas.innerHTML = `
                    <div class="parcelas-preview-empty">
                        <i class="fa-solid fa-circle-info"></i>
                        <span>Informe o valor, a quantidade de parcelas e o primeiro vencimento para visualizar a prévia.</span>
                    </div>
                `;

                return;

            }

            const valorBase = Math.floor(valorCentavos / quantidade);
            const resto = valorCentavos % quantidade;

            let linhas = '';

            for (let i = 1; i <= quantidade; i++) {

                let valorParcela = valorBase;

                if (i <= resto) {
                    valorParcela++;
                }

                const vencimento = adicionarMesPreservandoDia(
                    primeiroVencimento,
                    i - 1
                );

                linhas += `
                    <tr>
                        <td>${i}/${quantidade}</td>
                        <td>${formatarData(vencimento)}</td>
                        <td>${formatarMoeda(valorParcela)}</td>
                    </tr>
                `;

            }

            previewParcelas.innerHTML = `
                <div class="parcelas-preview-header">
                    <div>
                        <strong>Prévia das parcelas</strong>
                        <span>${quantidade} parcelas · Total ${formatarMoeda(valorCentavos)}</span>
                    </div>
                </div>

                <div class="parcelas-table-wrapper">

                    <table class="parcelas-preview-table">

                        <thead>
                            <tr>
                                <th>Parcela</th>
                                <th>Vencimento</th>
                                <th>Valor</th>
                            </tr>
                        </thead>

                        <tbody>
                            ${linhas}
                        </tbody>

                    </table>

                </div>
            `;

        }

        /*
        |--------------------------------------------------------------------------
        | Eventos
        |--------------------------------------------------------------------------
        */

        campoParcelado.addEventListener(
            'change',
            atualizarEstadoParcelamento
        );

        campoQuantidade.addEventListener(
            'input',
            atualizarPreview
        );

        campoPrimeiroVencimento.addEventListener(
            'change',
            atualizarPreview
        );

        /*
        |--------------------------------------------------------------------------
        | Estado inicial
        |--------------------------------------------------------------------------
        */

        atualizarEstadoParcelamento();
    </script>

</body>

</html>