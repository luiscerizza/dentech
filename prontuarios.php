<?php
require_once 'conexao/conexao.php';

$busca    = trim($_GET['busca'] ?? '');
$data_ini = $_GET['data_ini'] ?? '';
$data_fim = $_GET['data_fim'] ?? '';

$where = [];
$params = [];

if ($busca !== '') {
    $where[] = "(p.paciente LIKE ? OR p.cpf LIKE ? OR p.telefone LIKE ?)";
    $busca_like = "%{$busca}%";
    $params[] = $busca_like;
    $params[] = $busca_like;
    $params[] = $busca_like;
}

if ($data_ini !== '') {
    $where[] = "p.nascimento >= ?";
    $params[] = $data_ini;
}
if ($data_fim !== '') {
    $where[] = "p.nascimento <= ?";
    $params[] = $data_fim;
}

$sql = "
    SELECT 
        p.*,
        COALESCE(proc_count.total, 0) as total_procedimentos
    FROM prontuarios p
    LEFT JOIN (
        SELECT paciente_id, COUNT(*) as total
        FROM procedimentos
        GROUP BY paciente_id
    ) proc_count ON p.id = proc_count.paciente_id
";

if (isset($_GET['exportar']) && $_GET['exportar'] === '1') {
    require_once 'funcoes_export.php';

    $sql_exp = "
        SELECT p.id, p.paciente, p.cpf, p.telefone, p.nascimento, p.observacoes, 
               COALESCE(proc_count.total, 0) as total_procedimentos
        FROM prontuarios p
        LEFT JOIN (SELECT paciente_id, COUNT(*) as total FROM procedimentos GROUP BY paciente_id) 
        proc_count ON p.id = proc_count.paciente_id
    ";

    if (!empty($where)) {
        $sql_exp .= " WHERE " . implode(" AND ", $where);
    }
    $sql_exp .= " ORDER BY p.paciente ASC";

    exportarParaCSV($pdo, 'Relatório de Prontuários - Dentech', $sql_exp, $params, 'prontuarios');
}

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY p.paciente ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$prontuarios = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prontuários - Dentech</title>
    <link rel="stylesheet" href="css/navbar.css">
    <link rel="stylesheet" href="css/prontuarios.css">
    <link rel="icon" type="image/png" href="img/icon.PNG">
</head>

<body>
    <?php include 'navbar.php'; ?>

    <div class="container">
        <main>
            <h1>Prontuários</h1>

            <div class="filter-bar">
                <form method="GET" class="filter-form">
                    <input type="text" name="busca" placeholder="Nome, CPF ou telefone..." value="<?= htmlspecialchars($busca) ?>">
                    <input type="date" name="data_ini" value="<?= htmlspecialchars($data_ini) ?>" title="Data de nascimento inicial">
                    <input type="date" name="data_fim" value="<?= htmlspecialchars($data_fim) ?>" title="Data de nascimento final">
                    <button type="submit" class="btn-filter">🔍 Buscar</button>
                    <a href="prontuarios" class="btn-reset">✖ Limpar</a>
                </form>
                <?php if ($busca !== '' || $data_ini !== '' || $data_fim !== ''): ?>
                    <div class="filter-info">
                        ✅ Filtros ativos:
                        <?php
                        $ativos = [];
                        if ($busca !== '') $ativos[] = "Busca: '{$busca}'";
                        if ($data_ini !== '') $ativos[] = "De: " . date('d/m/Y', strtotime($data_ini));
                        if ($data_fim !== '') $ativos[] = "Até: " . date('d/m/Y', strtotime($data_fim));
                        echo implode(' • ', $ativos);
                        ?>
                    </div>
                <?php endif; ?>
            </div>

            <a href="editar_prontuario" class="add-btn">+ Adicionar Prontuário</a>
            <?php
            $export_url = '?' . http_build_query(array_merge($_GET, ['exportar' => '1']));
            ?>
            <a href="<?= $export_url ?>" class="btn-exportar">📥 Exportar CSV</a>

            <div class="cards">
                <?php if (empty($prontuarios)): ?>
                    <p class="empty">
                        <?= ($busca !== '' || $data_ini !== '' || $data_fim !== '')
                            ? 'Nenhum prontuário encontrado com os filtros aplicados.'
                            : 'Nenhum prontuário cadastrado ainda.'
                        ?>
                    </p>
                <?php else: ?>
                    <?php foreach ($prontuarios as $p): ?>
                        <div class="card">
                            <h3><?= htmlspecialchars($p['paciente']) ?></h3>
                            <p><strong>Data de nascimento:</strong> <?= date('d/m/Y', strtotime($p['nascimento'])) ?></p>
                            <p><strong>Telefone:</strong> <?= htmlspecialchars($p['telefone'] ?? '—') ?></p>
                            <div class="actions">
                                <a href="visualizar_prontuario.php?id=<?= $p['id'] ?>" class="edit">Visualizar</a>
                                <a href="editar_prontuario.php?id=<?= $p['id'] ?>" class="edit">Editar</a>
                                <button class="delete" onclick="excluirProntuario(<?= $p['id'] ?>)">
                                    Excluir
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        function excluirProntuario(id) {
            if (!confirm('⚠️ ATENÇÃO:\nExcluir este prontuário também removerá todos os agendamentos, procedimentos e orçamentos vinculados a ele.\n\nDeseja continuar?')) {
                return;
            }

            fetch('excluir_prontuario.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'id=' + encodeURIComponent(id)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Prontuário excluído com sucesso!');
                        location.reload();
                    } else {
                        alert('Erro: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    alert('Erro de conexão. Tente novamente.');
                });
        }
    </script>
</body>

</html>