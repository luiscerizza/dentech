<?php
require_once 'conexao/conexao.php';

$id = (int)($_GET['id'] ?? 0);

// Buscar orçamento
$stmt = $pdo->prepare("
    SELECT o.*, p.paciente
    FROM orcamentos o
    JOIN prontuarios p ON o.paciente_id = p.id
    WHERE o.id = ? AND o.status = 'pendente'
");
$stmt->execute([$id]);
$orcamento = $stmt->fetch();

if (!$orcamento) {
    die("Orçamento não encontrado ou já finalizado.");
}

// Buscar itens
$stmt = $pdo->prepare("SELECT * FROM orcamentos_itens WHERE orcamento_id = ?");
$stmt->execute([$id]);
$itens = $stmt->fetchAll();

// Buscar lista de pacientes
$pacientes = $pdo->query("SELECT id, paciente FROM prontuarios ORDER BY paciente")->fetchAll();

$message = '';

if ($_POST) {
    try {
        $pdo->beginTransaction();

        $paciente_id = (int)$_POST['paciente_id'];
        $validade = $_POST['validade'];
        $observacoes = trim($_POST['observacoes'] ?? '');

        // Atualizar orçamento
        $stmt = $pdo->prepare("
            UPDATE orcamentos 
            SET paciente_id = ?, validade = ?, observacoes = ?
            WHERE id = ?
        ");
        $stmt->execute([$paciente_id, $validade, $observacoes, $id]);

        // Excluir itens antigos
        $stmt = $pdo->prepare("DELETE FROM orcamentos_itens WHERE orcamento_id = ?");
        $stmt->execute([$id]);

        // Inserir novos itens
        $descricoes = $_POST['descricao'] ?? [];
        $quantidades = $_POST['quantidade'] ?? [];
        $valores = $_POST['valor'] ?? [];

        foreach ($descricoes as $i => $desc) {
            if (!empty($desc) && isset($valores[$i]) && is_numeric($valores[$i])) {
                $stmt = $pdo->prepare("
                    INSERT INTO orcamentos_itens (orcamento_id, descricao, quantidade, valor_unitario)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([
                    $id,
                    $desc,
                    (int)($quantidades[$i] ?? 1),
                    (float)$valores[$i]
                ]);
            }
        }

        $pdo->commit();
        header("Location: visualizar_orcamento.php?id=" . $id);
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Erro ao salvar: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Orçamento #<?= $orcamento['id'] ?> - Dentech</title>
    <link rel="stylesheet" href="css/navbar.css">
    <link rel="stylesheet" href="css/edt_orcamento.css">
</head>

<body>
    <?php include 'navbar.php'; ?>
    <div class="container">
        <h1>Editar Orçamento #<?= $orcamento['id'] ?></h1>

        <?php if ($message): ?>
            <div class="erro"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <form method="POST" id="formOrcamento">
            <div class="form-group">
                <label>Paciente</label>
                <select name="paciente_id" required>
                    <?php foreach ($pacientes as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $p['id'] == $orcamento['paciente_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['paciente']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Validade</label>
                <input type="date" name="validade" value="<?= $orcamento['validade'] ?>" required>
            </div>

            <div class="form-group">
                <label>Itens do orçamento</label>
                <div id="itens-container">
                    <?php foreach ($itens as $i): ?>
                        <div class="item-row">
                            <div><input type="text" name="descricao[]" value="<?= htmlspecialchars($i['descricao']) ?>" required></div>
                            <div><input type="number" name="quantidade[]" value="<?= $i['quantidade'] ?>" min="1" style="width:80px;"></div>
                            <div><input type="number" name="valor[]" step="0.01" min="0" value="<?= $i['valor_unitario'] ?>" required></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn-add-item" onclick="adicionarItem()">+ Adicionar Item</button>
            </div>

            <div class="form-group">
                <label>Observações</label>
                <textarea name="observacoes" rows="3"><?= htmlspecialchars($orcamento['observacoes'] ?? '') ?></textarea>
            </div>

            <button type="submit" class="btn">Salvar Alterações</button>
            <a href="visualizar_orcamento.php?id=<?= $id ?>" style="display:inline-block; margin-left:12px; color:var(--roxo-medio);">Cancelar</a>
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
            <div><input type="number" name="valor[]" step="0.01" min="0" placeholder="0.00" required></div>
            <div><button type="button" class="btn-remover" onclick="removerItem(this)">×</button></div>
        `;
            container.appendChild(div);
        }

        function removerItem(btn) {
            if (document.querySelectorAll('.item-row').length > 1) {
                btn.closest('.item-row').remove();
            } else {
                alert("O orçamento deve conter pelo menos um item.");
            }
        }

        // Adicionar botão de remover nos itens existentes
        document.querySelectorAll('.item-row').forEach(row => {
            if (!row.querySelector('.btn-remover')) {
                const btn = document.createElement('div');
                btn.innerHTML = `<button type="button" class="btn-remover" onclick="removerItem(this)">×</button>`;
                row.appendChild(btn);
            }
        });
    </script>
</body>

</html>