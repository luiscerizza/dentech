<?php
require_once 'conexao/conexao.php';

// 🔍 Captura filtros da URL
$busca    = trim($_GET['busca'] ?? '');
$estoque  = $_GET['estoque'] ?? ''; // 'baixo' ou 'normal'

// 🛡️ Monta WHERE dinâmico seguro
$where = [];
$params = [];

// Filtro de busca (nome ou código do material)
if ($busca !== '') {
    $where[] = "(nome LIKE ? OR codigo LIKE ?)";
    $busca_like = "%{$busca}%";
    $params[] = $busca_like;
    $params[] = $busca_like;
}

// Filtro de estoque (baixo ou normal)
if ($estoque === 'baixo') {
    $where[] = "quantidade <= estoque_minimo";
} elseif ($estoque === 'normal') {
    $where[] = "quantidade > estoque_minimo";
}

// 🔽 Query principal COM filtros aplicados
$sql = "
    SELECT *, 
           (quantidade <= estoque_minimo) AS estoque_baixo
    FROM estoque
";

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

// Mantém a ordenação original: estoque baixo primeiro, depois por nome
$sql .= " ORDER BY estoque_baixo DESC, nome ASC";

// Executa com prepared statement
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$materiais = $stmt->fetchAll();
// 🔼 Fim da query filtrada
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventário - Dentech</title>
    <link rel="stylesheet" href="css/navbar.css">
    <link rel="stylesheet" href="css/inventario.css">
    <link rel="icon" type="image/png" href="img/icon.PNG">
</head>

<body>
    <?php include 'navbar.php'; ?>

    <div class="container">
        <h1>Inventário</h1>

        <!-- 🔽 BARRA DE FILTROS -->
        <div class="filter-bar">
            <form method="GET" class="filter-form">
                <input type="text" name="busca" placeholder="Material ou código..." value="<?= htmlspecialchars($busca) ?>">
                <select name="estoque">
                    <option value="">Todos</option>
                    <option value="baixo" <?= $estoque === 'baixo' ? 'selected' : '' ?>>⚠️ Estoque Baixo</option>
                    <option value="normal" <?= $estoque === 'normal' ? 'selected' : '' ?>>✅ Estoque Normal</option>
                </select>
                <button type="submit" class="btn-filter">🔍 Filtrar</button>
                <a href="inventario" class="btn-reset">✖ Limpar</a>
            </form>
            <?php if ($busca !== '' || $estoque !== ''): ?>
                <div class="filter-info">
                    ✅ Filtros ativos:
                    <?php
                    $ativos = [];
                    if ($busca !== '') $ativos[] = "Busca: '{$busca}'";
                    if ($estoque === 'baixo') $ativos[] = "Estoque: Baixo";
                    if ($estoque === 'normal') $ativos[] = "Estoque: Normal";
                    echo implode(' • ', $ativos);
                    ?>
                </div>
            <?php endif; ?>
        </div>
        <!-- 🔼 FIM DA BARRA DE FILTROS -->

        <a href="adicionar_material" class="btn-add">+ Adicionar Material</a>

        <?php if (empty($materiais)): ?>
            <div class="empty">
                <?= ($busca !== '' || $estoque !== '')
                    ? 'Nenhum material encontrado com os filtros aplicados.'
                    : 'Nenhum material cadastrado.'
                ?>
            </div>
        <?php else: ?>
            <div class="tabela-estoque">
                <table>
                    <thead>
                        <tr>
                            <th class="col-excluir"></th>
                            <th>Material</th>
                            <th>Quantidade</th>
                            <th>Estoque Mínimo</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($materiais as $m): ?>
                            <tr class="<?= $m['estoque_baixo'] ? 'estoque-baixo' : '' ?>">
                                <td class="col-excluir">
                                    <button type="button" class="btn-excluir"  onclick="excluirMaterial(<?= $m['id'] ?>, '<?= htmlspecialchars($m['nome'], ENT_QUOTES)?>')"
                                    title="Excluir material">
                                X
                                </button>
                                </td>
                                <td data-label="Material">
                                    <strong><?= htmlspecialchars($m['nome']) ?></strong><br>
                                </td>
                                <td data-label="Quantidade" class="quantidade">
                                    <?= number_format($m['quantidade'], 2, ',', '.') ?> <?= htmlspecialchars($m['unidade']) ?>
                                </td>
                                <td data-label="Estoque Mínimo"><?= number_format($m['estoque_minimo'], 2, ',', '.') ?></td>
                                <td data-label="Status">
                                    <span class="badge-estoque <?= $m['estoque_baixo'] ? 'badge-baixo' : 'badge-normal' ?>">
                                        <?= $m['estoque_baixo'] ? '⚠️ Baixo' : '✅ Normal' ?>
                                    </span>
                                </td>
                                <td data-label="Ações" class="acoes">
                                    <button class="btn-acao btn-entrada" onclick="atualizarEstoque(<?= $m['id'] ?>, 'entrada')">
                                        Entrada
                                    </button>
                                    <button class="btn-acao btn-saida" onclick="atualizarEstoque(<?= $m['id'] ?>, 'saida')">
                                        Saída
                                    </button>
                                    <button class="btn-acao btn-editar" onclick="atualizarEstoqueMinimo(<?= $m['id'] ?>)">
                                        Editar
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

    async function atualizarEstoqueMinimo(materialId) {
        const novoMinimo = prompt("Digite o novo valor de estoque mínimo:", "0");
        if (novoMinimo === null || novoMinimo === "" || isNaN(novoMinimo) || parseFloat(novoMinimo) < 0) {
            return;
        }

        const formData = new FormData();
        formData.append('id', materialId);
        formData.append('estoque_minimo', novoMinimo);

        try {
            const response = await fetch('atualizar_estoque_minimo.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            if (result.success) {
                alert("Estoque mínimo atualizado com sucesso!");
                location.reload();
            } else {
                alert("Erro: " + result.error);
            }
        } catch (err) {
            alert("Erro de conexão.");
        }
    }

    async function excluirMaterial(materialId, nomeMaterial) {

    const confirmar = confirm(
        `Tem certeza que deseja excluir o material "${nomeMaterial}"?\n\n` +
        `Essa ação não poderá ser desfeita.`
    );

    if (!confirmar) {
        return;
    }

    const formData = new FormData();
    formData.append('id', materialId);

    try {

        const response = await fetch('excluir_material.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            alert('Material excluído com sucesso!');
            location.reload();
        } else {
            alert('Erro: ' + result.error);
        }

    } catch (err) {
        console.error(err);
        alert('Erro de conexão ao excluir o material.');
    }
}
    </script>
</body>

</html>