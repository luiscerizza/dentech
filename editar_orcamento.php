<?php
require_once 'config/auth.php';
require_once 'config/csrf.php';

exigirLogin();

require_once 'conexao/conexao.php';

// ============================================================
// VERIFICA ID DO ORÇAMENTO
// ============================================================

$id_orc = (int)($_GET['id'] ?? 0);

if ($id_orc <= 0) {
    die("ID do orçamento não informado.");
}

// ============================================================
// BUSCA ORÇAMENTO + PACIENTE
// ============================================================

$stmt = $pdo->prepare("
    SELECT 
        o.*,
        p.paciente,
        p.cpf,
        p.telefone,
        p.email
    FROM orcamentos o
    JOIN prontuarios p ON o.paciente_id = p.id
    WHERE o.id = ?
");

$stmt->execute([$id_orc]);

$orc = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$orc) {
    die("Orçamento não encontrado.");
}

// ============================================================
// BUSCA ITENS
// ============================================================

$stmt_itens = $pdo->prepare("
    SELECT *
    FROM orcamentos_itens
    WHERE orcamento_id = ?
    ORDER BY id ASC
");

$stmt_itens->execute([$id_orc]);

$itens_existentes = $stmt_itens->fetchAll(PDO::FETCH_ASSOC);

// ============================================================
// BUSCA PARCELAS
// ============================================================

$stmt_par = $pdo->prepare("
    SELECT *
    FROM parcelas
    WHERE orcamento_id = ?
    ORDER BY numero_parcela ASC
");

$stmt_par->execute([$id_orc]);

$parcelas_existentes = $stmt_par->fetchAll(PDO::FETCH_ASSOC);

// ============================================================
// CALCULA TOTAL ATUAL
// ============================================================

$total_atual = 0;

foreach ($itens_existentes as $item) {
    $quantidade = (int)($item['quantidade'] ?? 0);
    $valor = (float)($item['valor_unitario'] ?? 0);

    $total_atual += $quantidade * $valor;
}

$total_atual = round($total_atual, 2);

// ============================================================
// VERIFICA SE EXISTE PARCELA PAGA
// ============================================================

$tem_parcela_paga = false;

foreach ($parcelas_existentes as $parcela) {
    if (($parcela['status'] ?? '') === 'paga') {
        $tem_parcela_paga = true;
        break;
    }
}

$erro = null;
$sucesso = null;

// ============================================================
// FUNÇÃO PARA NORMALIZAR VALORES MONETÁRIOS
// ============================================================

function normalizarValorMonetario($valor)
{
    $valor = trim((string)$valor);

    if ($valor === '') {
        return 0;
    }

    // Remove R$
    $valor = str_replace('R$', '', $valor);
    $valor = trim($valor);

    /*
     * Aceita:
     * 1500.50
     * 1500,50
     * 1.500,50
     */

    if (strpos($valor, ',') !== false) {
        // Formato brasileiro: 1.500,50
        $valor = str_replace('.', '', $valor);
        $valor = str_replace(',', '.', $valor);
    }

    return (float)$valor;
}

// ============================================================
// PROCESSAMENTO
// ============================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    validar_csrf();

    try {

        $pdo->beginTransaction();

        // ====================================================
        // DADOS GERAIS
        // ====================================================

        $validade = trim($_POST['validade'] ?? '');
        $observacoes = trim($_POST['observacoes'] ?? '');

        if ($validade === '') {
            throw new Exception("A data de validade é obrigatória.");
        }

        // Validação da data
        $dataValidade = DateTime::createFromFormat('Y-m-d', $validade);

        if (
            !$dataValidade ||
            $dataValidade->format('Y-m-d') !== $validade
        ) {
            throw new Exception("A data de validade é inválida.");
        }

        // ====================================================
        // ATUALIZA DADOS DO ORÇAMENTO
        // ====================================================

        $stmt = $pdo->prepare("
            UPDATE orcamentos
            SET validade = ?,
                observacoes = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $validade,
            $observacoes,
            $id_orc
        ]);

        // ====================================================
        // SE EXISTE PARCELA PAGA:
        // NÃO ALTERA ITENS NEM PARCELAMENTO
        // ====================================================

        if ($tem_parcela_paga) {

            /*
             * O orçamento já possui histórico financeiro.
             *
             * Portanto:
             * - não altera itens;
             * - não altera valores;
             * - não altera quantidade;
             * - não altera parcelas.
             *
             * Apenas validade e observações são editáveis.
             */

            $pdo->commit();

            $sucesso = "Orçamento atualizado com sucesso!";

            header("Refresh: 2; URL=visualizar_orcamento.php?id={$id_orc}");
            exit;
        }

        // ====================================================
        // SEM PARCELA PAGA:
        // PODE ALTERAR ITENS
        // ====================================================

        $descricoes = $_POST['descricao'] ?? [];
        $quantidades = $_POST['quantidade'] ?? [];
        $valores = $_POST['valor'] ?? [];

        if (!is_array($descricoes)) {
            $descricoes = [];
        }

        if (!is_array($quantidades)) {
            $quantidades = [];
        }

        if (!is_array($valores)) {
            $valores = [];
        }

        $total_novo = 0;
        $itens_validos = [];

        foreach ($descricoes as $i => $desc) {

            $desc = trim((string)$desc);

            $qtd = (int)($quantidades[$i] ?? 1);

            $valor = normalizarValorMonetario(
                $valores[$i] ?? ''
            );

            // Ignora linha completamente vazia
            if ($desc === '' && $valor <= 0) {
                continue;
            }

            // Descrição obrigatória para item preenchido
            if ($desc === '') {
                throw new Exception(
                    "O item #" . ($i + 1) . " precisa ter uma descrição."
                );
            }

            // Quantidade obrigatoriamente positiva
            if ($qtd <= 0) {
                throw new Exception(
                    "A quantidade do item #" . ($i + 1) . " deve ser maior que zero."
                );
            }

            // Valor obrigatoriamente positivo
            if ($valor <= 0) {
                throw new Exception(
                    "O valor do item #" . ($i + 1) . " deve ser maior que zero."
                );
            }

            $subtotal = round($qtd * $valor, 2);

            $total_novo += $subtotal;

            $itens_validos[] = [
                'descricao' => $desc,
                'quantidade' => $qtd,
                'valor' => $valor
            ];
        }

        $total_novo = round($total_novo, 2);

        if (empty($itens_validos)) {
            throw new Exception(
                "Adicione pelo menos 1 item válido ao orçamento."
            );
        }

        // ====================================================
        // SUBSTITUI OS ITENS
        // ====================================================

        $stmtDeleteItens = $pdo->prepare("
            DELETE FROM orcamentos_itens
            WHERE orcamento_id = ?
        ");

        $stmtDeleteItens->execute([$id_orc]);

        $stmtInsertItem = $pdo->prepare("
            INSERT INTO orcamentos_itens (
                orcamento_id,
                descricao,
                quantidade,
                valor_unitario
            )
            VALUES (?, ?, ?, ?)
        ");

        foreach ($itens_validos as $item) {

            $stmtInsertItem->execute([
                $id_orc,
                $item['descricao'],
                $item['quantidade'],
                $item['valor']
            ]);
        }

        // ====================================================
        // ATUALIZA PARCELAMENTO
        // ====================================================

        $num_parcelas = (int)($_POST['num_parcelas'] ?? 1);

        if ($num_parcelas < 1) {
            throw new Exception(
                "A quantidade de parcelas deve ser pelo menos 1."
            );
        }

        if ($num_parcelas > 24) {
            throw new Exception(
                "A quantidade máxima de parcelas é 24."
            );
        }

        if ($total_novo <= 0) {
            throw new Exception(
                "O valor total do orçamento deve ser maior que zero."
            );
        }

        // Remove somente parcelas pendentes
        $stmtDeleteParcelas = $pdo->prepare("
            DELETE FROM parcelas
            WHERE orcamento_id = ?
              AND status = 'pendente'
        ");

        $stmtDeleteParcelas->execute([$id_orc]);

        // ====================================================
        // CRIA NOVAS PARCELAS
        // ====================================================

        $valor_parcela_base = round(
            $total_novo / $num_parcelas,
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

        for ($i = 1; $i <= $num_parcelas; $i++) {

            /*
             * Ajusta a última parcela para garantir
             * que a soma seja exatamente igual ao total.
             */

            if ($i === $num_parcelas) {

                $valor_final = round(
                    $total_novo -
                    ($valor_parcela_base * ($num_parcelas - 1)),
                    2
                );

            } else {

                $valor_final = $valor_parcela_base;
            }

            $vencimento = date(
                'Y-m-d',
                strtotime("+{$i} month")
            );

            $stmtParcela->execute([
                $id_orc,
                $i,
                $valor_final,
                $vencimento
            ]);
        }

        // ====================================================
        // FINALIZA TRANSAÇÃO
        // ====================================================

        $pdo->commit();

        $sucesso = "Orçamento atualizado com sucesso!";

        header(
            "Refresh: 2; URL=visualizar_orcamento.php?id={$id_orc}"
        );

        exit;

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $erro = "Erro ao atualizar: " .
            htmlspecialchars(
                $e->getMessage(),
                ENT_QUOTES,
                'UTF-8'
            );

        error_log(
            "ERRO EDIÇÃO ORÇAMENTO #{$id_orc}: " .
            $e->getMessage()
        );
    }
}

// ============================================================
// BUSCA PACIENTES
// ============================================================

$pacientes = $pdo->query("
    SELECT id, paciente
    FROM prontuarios
    ORDER BY paciente
")->fetchAll(PDO::FETCH_ASSOC);

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
        Editar Orçamento #<?= $id_orc ?> - Dentech
    </title>

    <link
        rel="stylesheet"
        href="css/navbar.css"
    >

    <link
        rel="stylesheet"
        href="css/new_orcamento.css"
    >

    <link
        rel="icon"
        type="image/png"
        href="img/icon.PNG"
    >


</head>

<body>

<?php include 'navbar.php'; ?>

<div class="container">

    <h1>
        ✏️ Editar Orçamento #<?= $id_orc ?>
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
            "
        >
            <?= $erro ?>
        </div>

    <?php endif; ?>


    <?php if (!empty($sucesso)): ?>

        <div
            class="sucesso"
            style="
                background:#e8f5e9;
                color:#2e7d32;
                padding:12px;
                border-radius:6px;
                margin-bottom:20px;
            "
        >
            <?= $sucesso ?>
            <small>(Redirecionando...)</small>
        </div>

    <?php endif; ?>


    <form
        method="POST"
        id="formEditarOrcamento"
    >

        <?= csrf_field() ?>


        <!-- PACIENTE -->

        <div class="form-group">

            <label>Paciente</label>

            <input
                type="text"
                value="<?= htmlspecialchars($orc['paciente']) ?>"
                readonly
                style="
                    background:#f4f7f6;
                    cursor:not-allowed;
                "
            >

            <input
                type="hidden"
                name="paciente_id"
                value="<?= (int)$orc['paciente_id'] ?>"
            >

        </div>


        <!-- VALIDADE -->

        <div class="form-group">

            <label>Data de Validade</label>

            <input
                type="date"
                name="validade"
                value="<?= htmlspecialchars($orc['validade']) ?>"
                required
            >

        </div>


        <!-- ITENS -->

        <div class="form-group">

            <label>Itens do orçamento</label>

            <?php if ($tem_parcela_paga): ?>

                <div class="aviso-itens-bloqueados">

                    ⚠️ Este orçamento possui parcelas já pagas.
                    Os itens e valores estão bloqueados para preservar
                    o histórico financeiro.

                </div>

            <?php endif; ?>


            <div
                id="itens-container"
                class="<?= $tem_parcela_paga ? 'itens-bloqueados' : '' ?>"
            >

                <?php foreach ($itens_existentes as $idx => $item): ?>

                    <div class="item-row">

                        <div>

                            <input
                                type="text"
                                name="descricao[]"
                                value="<?= htmlspecialchars($item['descricao']) ?>"
                                required
                                <?= $tem_parcela_paga ? 'disabled' : '' ?>
                            >

                        </div>

                        <div>

                            <input
                                type="number"
                                name="quantidade[]"
                                value="<?= (int)$item['quantidade'] ?>"
                                min="1"
                                style="width:80px;"
                                <?= $tem_parcela_paga ? 'disabled' : '' ?>
                            >

                        </div>

                        <div>

                            <input
                                type="number"
                                name="valor[]"
                                step="0.01"
                                min="0"
                                value="<?= number_format((float)$item['valor_unitario'], 2, '.', '') ?>"
                                placeholder="0.00"
                                required
                                class="item-valor"
                                <?= $tem_parcela_paga ? 'disabled' : '' ?>
                            >

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>


            <?php if (!$tem_parcela_paga): ?>

                <button
                    type="button"
                    class="btn-add-item"
                    onclick="adicionarItem()"
                >
                    + Adicionar Item
                </button>

            <?php endif; ?>

        </div>


        <!-- PARCELAMENTO -->

        <div
            class="form-group"
            style="
                border-top:1px solid #eee;
                padding-top:20px;
                margin-top:20px;
            "
        >

            <label>Parcelamento</label>


            <?php if ($tem_parcela_paga): ?>

                <div class="aviso-parcelas">

                    ⚠️ Este orçamento possui parcelas já pagas.
                    O parcelamento não pode ser alterado para preservar
                    o histórico financeiro.

                </div>

                <select
                    name="num_parcelas"
                    id="num_parcelas"
                    disabled
                >

                    <option value="<?= count($parcelas_existentes) ?>">

                        <?= count($parcelas_existentes) ?>x
                        (não editável)

                    </option>

                </select>

            <?php else: ?>

                <select
                    name="num_parcelas"
                    id="num_parcelas"
                >

                    <?php for ($i = 1; $i <= 24; $i++): ?>

                        <option
                            value="<?= $i ?>"
                            <?= $i === count($parcelas_existentes) ? 'selected' : '' ?>
                        >

                            <?= $i ?>x sem juros

                        </option>

                    <?php endfor; ?>

                </select>

            <?php endif; ?>


            <div
                id="preview_parcelas"
                class="preview-parcelas"
            >

                <?php

                $qtdParcelasAtual =
                    count($parcelas_existentes);

                if ($qtdParcelasAtual > 1) {

                    $valorBase =
                        round(
                            $total_atual /
                            $qtdParcelasAtual,
                            2
                        );

                    $valorUltima =
                        round(
                            $total_atual -
                            (
                                $valorBase *
                                ($qtdParcelasAtual - 1)
                            ),
                            2
                        );

                    echo $qtdParcelasAtual .
                        "x de R$ " .
                        number_format(
                            $valorBase,
                            2,
                            ',',
                            '.'
                        );

                } else {

                    echo "À vista: R$ " .
                        number_format(
                            $total_atual,
                            2,
                            ',',
                            '.'
                        );
                }

                ?>

            </div>

        </div>


        <!-- PARCELAS EXISTENTES -->

        <?php if (!empty($parcelas_existentes)): ?>

            <div class="parcelas-existentes">

                <h4>
                    💰 Parcelas Atuais
                </h4>

                <?php foreach ($parcelas_existentes as $p): ?>

                    <div class="parcela-item">

                        <span>

                            <?= (int)$p['numero_parcela'] ?>x

                            • Venc:
                            <?= date(
                                'd/m/Y',
                                strtotime($p['vencimento'])
                            ) ?>

                        </span>

                        <span>

                            R$
                            <?= number_format(
                                (float)$p['valor'],
                                2,
                                ',',
                                '.'
                            ) ?>

                            <span
                                class="<?=
                                    $p['status'] === 'paga'
                                        ? 'badge-paga'
                                        : 'badge-pendente'
                                ?>"
                            >

                                <?= ucfirst(
                                    htmlspecialchars($p['status'])
                                ) ?>

                            </span>

                        </span>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>


        <!-- OBSERVAÇÕES -->

        <div class="form-group">

            <label>
                Observações (opcional)
            </label>

            <textarea
                name="observacoes"
                rows="3"
                placeholder="Condições, descontos, etc..."
            ><?= htmlspecialchars($orc['observacoes'] ?? '') ?></textarea>

        </div>


        <!-- AÇÕES -->

        <button
            type="submit"
            class="btn"
        >
            💾 Salvar Alterações
        </button>


        <a
            href="visualizar_orcamento.php?id=<?= $id_orc ?>"
            style="
                display:inline-block;
                margin-left:12px;
                color:var(--roxo-medio);
            "
        >
            Cancelar
        </a>

    </form>

</div>


<script>

    function adicionarItem() {

        const container =
            document.getElementById('itens-container');

        const div =
            document.createElement('div');

        div.className = 'item-row';

        div.innerHTML = `
            <div>
                <input
                    type="text"
                    name="descricao[]"
                    placeholder="Ex: Restauração"
                    required
                >
            </div>

            <div>
                <input
                    type="number"
                    name="quantidade[]"
                    value="1"
                    min="1"
                    style="width:80px;"
                >
            </div>

            <div>
                <input
                    type="number"
                    name="valor[]"
                    step="0.01"
                    min="0"
                    placeholder="0.00"
                    required
                    class="item-valor"
                >
            </div>
        `;

        container.appendChild(div);

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

        calcularPreviewParcelas();
    }


    function calcularPreviewParcelas() {

        const selectParcelas =
            document.getElementById('num_parcelas');

        if (!selectParcelas || selectParcelas.disabled) {
            return;
        }

        const qtdParcelas =
            parseInt(selectParcelas.value) || 1;

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
                    parseInt(qtdInput?.value) || 0;

                const val =
                    parseFloat(valInput?.value) || 0;

                total += qtd * val;
            });


        total = Math.round(
            (total + Number.EPSILON) * 100
        ) / 100;


        const preview =
            document.getElementById(
                'preview_parcelas'
            );


        if (total <= 0) {

            preview.textContent =
                'Adicione itens para calcular parcelas';

            preview.style.color = '#999';

            return;
        }


        if (qtdParcelas === 1) {

            preview.textContent =
                `À vista: R$ ${formatarMoeda(total)}`;

        } else {

            const valorBase =
                Math.round(
                    (total / qtdParcelas + Number.EPSILON) * 100
                ) / 100;

            const valorUltima =
                Math.round(
                    (
                        total -
                        (
                            valorBase *
                            (qtdParcelas - 1)
                        )
                    ) * 100
                ) / 100;

            preview.textContent =
                `${qtdParcelas}x de R$ ${formatarMoeda(valorBase)} ` +
                `(última: R$ ${formatarMoeda(valorUltima)} | ` +
                `Total: R$ ${formatarMoeda(total)})`;
        }

        preview.style.color = '#7b3ff2';
    }


    function formatarMoeda(valor) {

        return valor
            .toFixed(2)
            .replace('.', ',');
    }


    document
        .getElementById('num_parcelas')
        ?.addEventListener(
            'change',
            calcularPreviewParcelas
        );


    document.addEventListener(
        'DOMContentLoaded',
        () => {

            document
                .querySelectorAll('.item-valor')
                .forEach(input => {

                    input.addEventListener(
                        'input',
                        calcularPreviewParcelas
                    );

                });


            document
                .querySelectorAll('[name="quantidade[]"]')
                .forEach(input => {

                    input.addEventListener(
                        'input',
                        calcularPreviewParcelas
                    );

                });


            calcularPreviewParcelas();

        }
    );

</script>

</body>

</html>