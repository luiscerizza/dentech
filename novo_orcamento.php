<?php
require_once 'conexao/conexao.php';

// Buscar pacientes
$pacientes = $pdo->query("SELECT id, paciente FROM prontuarios ORDER BY paciente")->fetchAll();

if ($_POST) {
    try {
        $pdo->beginTransaction();
        $paciente_id = (int)$_POST['paciente_id'];
        $validade = $_POST['validade'];
        $observacoes = trim($_POST['observacoes'] ?? '');

        // Inserir orçamento
        $stmt = $pdo->prepare("
            INSERT INTO orcamentos (paciente_id, validade, observacoes)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$paciente_id, $validade, $observacoes]);
        $orcamento_id = $pdo->lastInsertId();

        // Inserir itens
        $descricoes = $_POST['descricao'] ?? [];
        $quantidades = $_POST['quantidade'] ?? [];
        $valores = $_POST['valor'] ?? [];

        foreach ($descricoes as $i => $desc) {
            if (!empty($desc) && !empty($valores[$i])) {
                $stmt = $pdo->prepare("
                    INSERT INTO orcamentos_itens (orcamento_id, descricao, quantidade, valor_unitario)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([
                    $orcamento_id,
                    $desc,
                    (int)($quantidades[$i] ?? 1),
                    (float)$valores[$i]
                ]);
            }
        }

        $pdo->commit();
        header("Location: visualizar_orcamento.php?id=" . $orcamento_id);
        exit;
    } catch (Exception $e) {
        $erro = $e->getMessage();
        $pdo->rollBack();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novo Orçamento - Dentech</title>
    <link rel="stylesheet" href="css/navbar.css">
    <link rel="stylesheet" href="css/new_orcamento.css">
</head>

<body>
    <?php include 'navbar.php'; ?>
    <div class="container">
        <h1>Novo Orçamento</h1>
        <?php if (!empty($erro)): ?>
            <div class="erro"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>
        <form method="POST" id="formOrcamento">
            <div class="form-group">
                <label>Paciente</label>
                <select name="paciente_id" required>
                    <option value="">Selecione</option>
                    <?php foreach ($pacientes as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['paciente']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Validade (dias)</label>
                <input type="date" name="validade" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required>
            </div>

            <div class="form-group">
                <label>Itens do orçamento</label>
                <div id="itens-container">
                    <div class="item-row">
                        <div><input type="text" name="descricao[]" placeholder="Ex: Clareamento dental" required></div>
                        <div><input type="number" name="quantidade[]" value="1" min="1" style="width:80px;"></div>
                        <div><input type="number" name="valor[]" step="0.01" min="0" placeholder="0.00" required></div>
                    </div>
                </div>
                <button type="button" class="btn-add-item" onclick="adicionarItem()">+ Adicionar Item</button>
            </div>

            <div class="form-group">
                <label>Observações (opcional)</label>
                <textarea name="observacoes" rows="3" placeholder="Condições, descontos, etc..."></textarea>
            </div>

            <button type="submit" class="btn">Criar Orçamento</button>
            <a href="orcamento.php" style="display:inline-block; margin-left:12px; color:var(--roxo-medio);">Cancelar</a>
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
            `;
            container.appendChild(div);
        }
    </script>
</body>

</html>