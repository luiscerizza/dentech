<?php
require_once 'conexao/conexao.php';

// Buscar todos os materiais, ordenados por estoque (baixos primeiro)
$stmt = $pdo->query("
    SELECT *, 
           (quantidade <= estoque_minimo) AS estoque_baixo
    FROM estoque 
    ORDER BY estoque_baixo DESC, nome ASC
");
$materiais = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventário - Dentech</title>
    <link rel="stylesheet" href="css/navbar.css">
    <link rel="stylesheet" href="css/inventario.css">
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container">
        <h1>Inventário</h1>
        <a href="adicionar_material.php" class="btn-add">+ Adicionar Material</a>

        <?php if (empty($materiais)): ?>
            <div class="empty">Nenhum material cadastrado.</div>
        <?php else: ?>
            <div class="tabela-estoque">
                <table>
                    <thead>
                        <tr>
                            <th>Material</th>
                            <th>Quantidade</th>
                            <th>Estoque Mínimo</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($materiais as $m): ?>
                            <tr class="<?= $m['estoque_baixo'] ? 'estoque-baixo' : '' ?>">
                                <td data-label="Material"><?= htmlspecialchars($m['nome']) ?></td>
                                <td data-label="Quantidade" class="quantidade">
                                    <?= number_format($m['quantidade'], 2, ',', '.') ?> <?= htmlspecialchars($m['unidade']) ?>
                                </td>
                                <td data-label="Estoque Mínimo"><?= number_format($m['estoque_minimo'], 2, ',', '.') ?></td>
                                <td data-label="Ações" class="acoes">
                                    <button class="btn-acao btn-entrada" 
                                            onclick="atualizarEstoque(<?= $m['id'] ?>, 'entrada')">
                                        Entrada
                                    </button>
                                    <button class="btn-acao btn-saida" 
                                            onclick="atualizarEstoque(<?= $m['id'] ?>, 'saida')">
                                        Saída
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <script>
        async function atualizarEstoque(materialId, tipo) {
            const quantidade = prompt(`Digite a quantidade de ${tipo === 'entrada' ? 'entrada' : 'saída'}:`, "1");
            if (quantidade === null || quantidade === "" || isNaN(quantidade) || parseFloat(quantidade) <= 0) {
                return;
            }

            const formData = new FormData();
            formData.append('id', materialId);
            formData.append('tipo', tipo);
            formData.append('quantidade', quantidade);

            try {
                const response = await fetch('atualizar_estoque.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                if (result.success) {
                    alert("Estoque atualizado com sucesso!");
                    location.reload();
                } else {
                    alert("Erro: " + result.error);
                }
            } catch (err) {
                alert("Erro de conexão.");
            }
        }
    </script>
</body>
</html>