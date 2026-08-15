<?php
require_once 'config/auth.php';
exigirLogin();
require_once 'conexao/conexao.php';

// 🔍 Captura filtros da URL
$busca    = trim($_GET['busca'] ?? '');
$data_ini = $_GET['data_ini'] ?? '';
$data_fim = $_GET['data_fim'] ?? '';

// 🛡️ Monta WHERE dinâmico seguro
$where = [];
$params = [];

// Filtro de busca (nome, CPF ou telefone)
if ($busca !== '') {
    $where[] = "(p.paciente LIKE ? OR p.cpf LIKE ? OR p.telefone LIKE ?)";
    $busca_like = "%{$busca}%";
    $params[] = $busca_like;
    $params[] = $busca_like;
    $params[] = $busca_like;
}

// Filtro de período (nascimento)
if ($data_ini !== '') {
    $where[] = "p.nascimento >= ?";
    $params[] = $data_ini;
}
if ($data_fim !== '') {
    $where[] = "p.nascimento <= ?";
    $params[] = $data_fim;
}

// 🔽 Query principal COM filtros aplicados
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

// 🔽 Lógica de Exportação CSV
if (isset($_GET['exportar']) && $_GET['exportar'] === '1') {
    require_once 'funcoes_export.php';

    // Reutiliza exatamente a mesma query dos filtros
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

// Executa com prepared statement
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$prontuarios = $stmt->fetchAll();
// 🔼 Fim da query filtrada
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

            <!-- 🔽 BARRA DE FILTROS -->
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
            <!-- 🔼 FIM DA BARRA DE FILTROS -->

            <a href="editar_prontuario" class="add-btn">+ Adicionar Prontuário</a>
            <?php
            // Gera link preservando TODOS os filtros atuais automaticamente
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
                    body: 'id=' + encodeURIComponent(id) +
                        '&csrf_token=' + encodeURIComponent('<?= $_SESSION['csrf_token'] ?>')
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