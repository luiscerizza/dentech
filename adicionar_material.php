<?php
require_once 'conexao/conexao.php';

if ($_POST) {
    try {
        $nome = trim($_POST['nome'] ?? '');
        $unidade = trim($_POST['unidade'] ?? 'unidade');
        $estoque_minimo = floatval($_POST['estoque_minimo'] ?? 5);
        $quantidade = floatval($_POST['quantidade'] ?? 0);

        if (empty($nome)) throw new Exception("Nome do material é obrigatório.");

        $stmt = $pdo->prepare("
            INSERT INTO estoque (nome, quantidade, unidade, estoque_minimo)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$nome, $quantidade, $unidade, $estoque_minimo]);

        header("Location: inventario.php");
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novo Material - Dentech</title>
    <link rel="stylesheet" href="css/navbar.css">
    <link rel="stylesheet" href="css/add_material.css">
    <link rel="icon" type="image/png" href="img/icon.PNG">
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container">
        <h1>Novo Material</h1>
        <?php if (!empty($erro)): ?>
            <div class="erro"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label>Nome do material</label>
                <input type="text" name="nome" required placeholder="Ex: Anestésico, Luvas...">
            </div>
            <div class="form-group">
                <label>Unidade</label>
                <select name="unidade">
                    <option value="unidade">Unidade(s)</option>
                    <option value="frasco">Frasco(s)</option>
                    <option value="pacote">Pacote(s)</option>
                    <option value="mL">mL</option>
                    <option value="g">g</option>
                </select>
            </div>
            <div class="form-group">
                <label>Quantidade inicial</label>
                <input type="number" step="0.01" name="quantidade" value="0" min="0">
            </div>
            <div class="form-group">
                <label>Estoque mínimo (alerta)</label>
                <input type="number" step="0.01" name="estoque_minimo" value="5" min="0">
            </div>
            <button type="submit">Cadastrar Material</button>
            <a href="inventario" style="display:inline-block; margin-left:12px; color:#8a5ebf;">Cancelar</a>
        </form>
    </div>
</body>
</html>