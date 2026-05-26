<?php
require_once 'conexao/conexao.php';

$id_orc = $_GET['id'] ?? 0;
if (!$id_orc) die("ID do orçamento não informado.");

$stmt = $pdo->prepare("
    SELECT o.*, p.paciente, p.cpf, p.telefone, p.email 
    FROM orcamentos o 
    JOIN prontuarios p ON o.paciente_id = p.id 
    WHERE o.id = ?
");
$stmt->execute([$id_orc]);
$orc = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$orc) die("Orçamento não encontrado.");

$stmt_itens = $pdo->prepare("SELECT * FROM orcamentos_itens WHERE orcamento_id = ? ORDER BY id ASC");
$stmt_itens->execute([$id_orc]);
$itens_existentes = $stmt_itens->fetchAll();

$stmt_par = $pdo->prepare("SELECT * FROM parcelas WHERE orcamento_id = ? ORDER BY numero_parcela ASC");
$stmt_par->execute([$id_orc]);
$parcelas_existentes = $stmt_par->fetchAll();

$total_atual = 0;
foreach ($itens_existentes as $item) {
    $total_atual += $item['quantidade'] * $item['valor_unitario'];
}

$tem_parcela_paga = false;
foreach ($parcelas_existentes as $p) {
    if ($p['status'] === 'paga') {
        $tem_parcela_paga = true;
        break;
    }
}

$erro = null;
$sucesso = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        $validade = $_POST['validade'] ?? '';
        $observacoes = trim($_POST['observacoes'] ?? '');

        if (empty($validade)) {
            throw new Exception("A data de validade é obrigatória.");
        }

        $stmt = $pdo->prepare("
            UPDATE orcamentos 
            SET validade = ?, observacoes = ?
            WHERE id = ?
        ");
        $stmt->execute([$validade, $observacoes, $id_orc]);

        $pdo->prepare("DELETE FROM orcamentos_itens WHERE orcamento_id = ?")->execute([$id_orc]);

        $descricoes = $_POST['descricao'] ?? [];
        $quantidades = $_POST['quantidade'] ?? [];
        $valores = $_POST['valor'] ?? [];

        $total_novo = 0;
        $itens_salvos = 0;

        foreach ($descricoes as $i => $desc) {
            $desc = trim($desc ?? '');
            $valor = !empty($valores[$i]) ? (float)str_replace(',', '.', $valores[$i]) : 0;

            if (!empty($desc) && $valor > 0) {
                $qtd = (int)($quantidades[$i] ?? 1);
                $subtotal = $qtd * $valor;
                $total_novo += $subtotal;

                $stmt = $pdo->prepare("
                    INSERT INTO orcamentos_itens (orcamento_id, descricao, quantidade, valor_unitario)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$id_orc, $desc, $qtd, $valor]);
                $itens_salvos++;
            }
        }

        if ($itens_salvos === 0) {
            throw new Exception("Adicione pelo menos 1 item válido ao orçamento.");
        }

        $num_parcelas = (int)($_POST['num_parcelas'] ?? 1);

        if (!$tem_parcela_paga && $num_parcelas > 0 && $total_novo > 0) {
            $pdo->prepare("DELETE FROM parcelas WHERE orcamento_id = ? AND status = 'pendente'")
                ->execute([$id_orc]);

            $valor_parcela_base = round($total_novo / $num_parcelas, 2);
            $stmt_par = $pdo->prepare("
                INSERT INTO parcelas (orcamento_id, numero_parcela, valor, vencimento, status)
                VALUES (?, ?, ?, ?, 'pendente')
            ");

            for ($i = 1; $i <= $num_parcelas; $i++) {
                $valor_final = ($i === $num_parcelas)
                    ? round($total_novo - ($valor_parcela_base * ($num_parcelas - 1)), 2)
                    : $valor_parcela_base;

                $vencimento = date('Y-m-d', strtotime("+{$i} month"));
                $stmt_par->execute([$id_orc, $i, $valor_final, $vencimento]);
            }
        }

        $pdo->commit();
        $sucesso = "Orçamento atualizado com sucesso!";

        header("Refresh: 2; URL=visualizar_orcamento.php?id={$id_orc}");
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $erro = "Erro ao atualizar: " . htmlspecialchars($e->getMessage());
        error_log("ERRO EDIÇÃO ORÇAMENTO #{$id_orc}: " . $e->getMessage());
    }
}

$pacientes = $pdo->query("SELECT id, paciente FROM prontuarios ORDER BY paciente")->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Orçamento #<?= $id_orc ?> - Dentech</title>
    <link rel="stylesheet" href="css/navbar.css">
    <link rel="stylesheet" href="css/new_orcamento.css">
    <link rel="icon" type="image/png" href="img/icon.PNG">
</head>

<body>
    <?php include 'navbar.php'; ?>
    <div class="container">
        <h1>✏️ Editar Orçamento #<?= $id_orc ?></h1>

        <?php if (!empty($erro)): ?>
            <div class="erro" style="background:#ffebee; color:#c62828; padding:12px; border-radius:6px; margin-bottom:20px;">
                <?= $erro ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($sucesso)): ?>
            <div class="sucesso" style="background:#e8f5e9; color:#2e7d32; padding:12px; border-radius:6px; margin-bottom:20px;">
                <?= $sucesso ?> <small>(Redirecionando...)</small>
            </div>
        <?php endif; ?>

        <form method="POST" id="formEditarOrcamento">
            <div class="form-group">
                <label>Paciente</label>
                <input type="text" value="<?= htmlspecialchars($orc['paciente']) ?>" readonly style="background:#f4f7f6; cursor:not-allowed;">
                <input type="hidden" name="paciente_id" value="<?= $orc['paciente_id'] ?>">
            </div>

            <div class="form-group">
                <label>Data de Validade</label>
                <input type="date" name="validade" value="<?= htmlspecialchars($orc['validade']) ?>" required>
            </div>

            <div class="form-group">
                <label>Itens do orçamento</label>
                <div id="itens-container">
                    <?php foreach ($itens_existentes as $idx => $item): ?>
                        <div class="item-row">
                            <div><input type="text" name="descricao[]" value="<?= htmlspecialchars($item['descricao']) ?>" required></div>
                            <div><input type="number" name="quantidade[]" value="<?= (int)$item['quantidade'] ?>" min="1" style="width:80px;"></div>
                            <div><input type="number" name="valor[]" step="0.01" min="0" value="<?= number_format($item['valor_unitario'], 2, '.', '') ?>" placeholder="0.00" required class="item-valor"></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn-add-item" onclick="adicionarItem()">+ Adicionar Item</button>
            </div>

            <div class="form-group" style="border-top:1px solid #eee; padding-top:20px; margin-top:20px;">
                <label>Parcelamento</label>

                <?php if ($tem_parcela_paga): ?>
                    <div class="aviso-parcelas">
                        ⚠️ Este orçamento possui parcelas já pagas. O parcelamento não pode ser alterado para preservar o histórico financeiro.
                    </div>
                    <select name="num_parcelas" id="num_parcelas" disabled>
                        <option value="<?= count($parcelas_existentes) ?>"><?= count($parcelas_existentes) ?>x (não editável)</option>
                    </select>
                <?php else: ?>
                    <select name="num_parcelas" id="num_parcelas">
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?= $i ?>" <?= $i === count($parcelas_existentes) ? 'selected' : '' ?>>
                                <?= $i ?>x sem juros
                            </option>
                        <?php endfor; ?>
                    </select>
                <?php endif; ?>

                <div id="preview_parcelas" class="preview-parcelas">
                    <?= count($parcelas_existentes) > 1
                        ? count($parcelas_existentes) . "x de R$ " . number_format($total_atual / count($parcelas_existentes), 2, ',', '.')
                        : "À vista: R$ " . number_format($total_atual, 2, ',', '.')
                    ?>
                </div>
            </div>

            <?php if (!empty($parcelas_existentes)): ?>
                <div class="parcelas-existentes">
                    <h4>💰 Parcelas Atuais</h4>
                    <?php foreach ($parcelas_existentes as $p): ?>
                        <div class="parcela-item">
                            <span><?= $p['numero_parcela'] ?>x • Venc: <?= date('d/m/Y', strtotime($p['vencimento'])) ?></span>
                            <span>
                                R$ <?= number_format($p['valor'], 2, ',', '.') ?>
                                <span class="<?= $p['status'] === 'paga' ? 'badge-paga' : 'badge-pendente' ?>">
                                    <?= ucfirst($p['status']) ?>
                                </span>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label>Observações (opcional)</label>
                <textarea name="observacoes" rows="3" placeholder="Condições, descontos, etc..."><?= htmlspecialchars($orc['observacoes']) ?></textarea>
            </div>

            <button type="submit" class="btn">💾 Salvar Alterações</button>
            <a href="visualizar_orcamento.php?id=<?= $id_orc ?>" style="display:inline-block; margin-left:12px; color:var(--roxo-medio);">Cancelar</a>
        </form>
    </div>

    <script>
        function adicionarItem() {
            const container = document.getElementById('itens-container');
            const div = document.createElement('div');
            div.className = 'item-row';
            div.innerHTML = `
                <div><input type="text" name="descricao[]" placeholder="Ex: Restauração" required></div>
                <div><input type="number" name="quantidade[]" value="1" min="1" style="width:80px;"></div>
                <div><input type="number" name="valor[]" step="0.01" min="0" placeholder="0.00" required class="item-valor"></div>
            `;
            container.appendChild(div);
            div.querySelector('.item-valor').addEventListener('input', calcularPreviewParcelas);
            calcularPreviewParcelas();
        }

        function calcularPreviewParcelas() {
            const selectParcelas = document.getElementById('num_parcelas');
            if (selectParcelas.disabled) return; // Não calcula se estiver bloqueado

            const qtdParcelas = parseInt(selectParcelas.value) || 1;
            let total = 0;

            document.querySelectorAll('.item-row').forEach(row => {
                const qtdInput = row.querySelector('[name="quantidade[]"]');
                const valInput = row.querySelector('[name="valor[]"]');
                const qtd = parseInt(qtdInput?.value) || 1;
                const val = parseFloat(valInput?.value) || 0;
                total += qtd * val;
            });

            const valorParcela = total / qtdParcelas;
            const preview = document.getElementById('preview_parcelas');

            if (total === 0) {
                preview.textContent = 'Adicione itens para calcular parcelas';
                preview.style.color = '#999';
            } else if (qtdParcelas === 1) {
                preview.textContent = `À vista: R$ ${total.toFixed(2).replace('.', ',')}`;
                preview.style.color = '#7b3ff2';
            } else {
                preview.textContent = `${qtdParcelas}x de R$ ${valorParcela.toFixed(2).replace('.', ',')} (Total: R$ ${total.toFixed(2).replace('.', ',')})`;
                preview.style.color = '#7b3ff2';
            }
        }

        document.getElementById('num_parcelas')?.addEventListener('change', calcularPreviewParcelas);

        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.item-valor').forEach(input => {
                input.addEventListener('input', calcularPreviewParcelas);
            });
            document.querySelectorAll('[name="quantidade[]"]').forEach(input => {
                input.addEventListener('input', calcularPreviewParcelas);
            });
            calcularPreviewParcelas(); // Calcula preview inicial
        });
    </script>
</body>

</html>