<?php
require_once 'config/auth.php';
require_once 'config/csrf.php';
exigirLogin();

require_once 'conexao/conexao.php';

// 🔍 Captura filtros da URL
$busca    = trim($_GET['busca'] ?? '');
$status   = $_GET['status'] ?? '';
$data_ini = $_GET['data_ini'] ?? '';
$data_fim = $_GET['data_fim'] ?? '';

// 🛡️ Monta WHERE dinâmico seguro
$where = [];
$params = [];

// Filtro de busca (nome do paciente)
if ($busca !== '') {
    $where[] = "(p.paciente LIKE ?)";
    $params[] = "%{$busca}%";
}

// Filtro de status
if ($status !== '' && in_array($status, ['pendente', 'aceito', 'recusado'])) {
    $where[] = "o.status = ?";
    $params[] = $status;
}

// Filtro de período (data de criação)
if ($data_ini !== '') {
    $where[] = "o.data_criacao >= ?";
    $params[] = $data_ini;
}
if ($data_fim !== '') {
    $where[] = "o.data_criacao <= ?";
    $params[] = $data_fim;
}

// 🔽 Query principal COM filtros aplicados
$sql = "
    SELECT 
        o.id,
        o.status,
        o.data_criacao,
        p.paciente
    FROM orcamentos o
    JOIN prontuarios p ON o.paciente_id = p.id
";

if (isset($_GET['exportar']) && $_GET['exportar'] === '1') {
    require_once 'funcoes_export.php';
    $sql_exp = "SELECT o.id, o.data_criacao, o.status, p.paciente, o.valor_total 
                FROM orcamentos o JOIN prontuarios p ON o.paciente_id = p.id";
    if (!empty($where)) $sql_exp .= " WHERE " . implode(" AND ", $where);
    $sql_exp .= " ORDER BY o.data_criacao DESC";
    exportarParaCSV($pdo, 'Relatório de Orçamentos - Dentech', $sql_exp, $params, 'orcamentos');
}

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY o.data_criacao DESC";

// Executa com prepared statement
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orcamentos = $stmt->fetchAll();
// 🔼 Fim da query filtrada
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orçamentos - Dentech</title>
    <link rel="stylesheet" href="css/navbar.css">
    <link rel="stylesheet" href="css/orcamento.css">
    <link rel="icon" type="image/png" href="img/icon.PNG">
    <style>
        .filter-form {
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        }
    </style>
</head>

<body>
    <?php include 'navbar.php'; ?>
    <div class="container">
        <h1>Orçamentos</h1>

        <!-- 🔽 BARRA DE FILTROS -->
        <div class="filter-bar">
            <form method="GET" class="filter-form">
                <input type="text" name="busca" placeholder="Nome do paciente..." value="<?= htmlspecialchars($busca) ?>">
                <select name="status">
                    <option value="">Todos os status</option>
                    <option value="pendente" <?= $status === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                    <option value="aceito" <?= $status === 'aceito' ? 'selected' : '' ?>>Aceito</option>
                    <option value="recusado" <?= $status === 'recusado' ? 'selected' : '' ?>>Recusado</option>
                </select>
                <input type="date" name="data_ini" value="<?= htmlspecialchars($data_ini) ?>" title="Data inicial">
                <button type="submit" class="btn-filter">🔍 Filtrar</button>
                <a href="orcamento" class="btn-reset">✖ Limpar</a>
            </form>
            <?php if ($busca !== '' || $status !== '' || $data_ini !== '' || $data_fim !== ''): ?>
                <div class="filter-info">
                    ✅ Filtros ativos:
                    <?php
                    $ativos = [];
                    if ($busca !== '') $ativos[] = "Paciente: '{$busca}'";
                    if ($status !== '') $ativos[] = "Status: " . ucfirst($status);
                    if ($data_ini !== '') $ativos[] = "De: " . date('d/m/Y', strtotime($data_ini));
                    if ($data_fim !== '') $ativos[] = "Até: " . date('d/m/Y', strtotime($data_fim));
                    echo implode(' • ', $ativos);
                    ?>
                </div>
            <?php endif; ?>
        </div>
        <!-- 🔼 FIM DA BARRA DE FILTROS -->

        <a href="novo_orcamento" class="btn-add">+ Novo Orçamento</a>

        <a href="<?= '?' . http_build_query(array_merge($_GET, ['exportar' => '1'])) ?>" class="btn-exportar">📥 Exportar CSV</a>

        <?php if (empty($orcamentos)): ?>
            <p style="text-align:center; padding:40px; color:#888;">
                <?= ($busca !== '' || $status !== '' || $data_ini !== '' || $data_fim !== '')
                    ? 'Nenhum orçamento encontrado com os filtros aplicados.'
                    : 'Nenhum orçamento cadastrado.'
                ?>
            </p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Paciente</th>
                        <th>Data</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orcamentos as $o): ?>
                        <tr>
                            <td><?= htmlspecialchars($o['paciente']) ?></td>
                            <td><?= date('d/m/Y', strtotime($o['data_criacao'])) ?></td>
                            <td>
                                <span class="status-badge status-<?= htmlspecialchars($o['status']) ?>">
                                    <?= ucfirst(htmlspecialchars($o['status'])) ?>
                                </span>
                            </td>
                            <td class="acoes">
                                <a href="visualizar_orcamento.php?id=<?= $o['id'] ?>">Visualizar</a>
                                <?php if ($o['status'] == 'pendente'): ?>
                                    <a href="editar_orcamento.php?id=<?= $o['id'] ?>" style="color:var(--roxo-medio);">Editar</a>
                                    <form method="POST" action="aceitar_orcamento.php" style="display:inline;"
                                        onsubmit="return confirm('Aceitar este orçamento?')">

                                        <?= csrf_field() ?>

                                        <input type="hidden" name="id" value="<?= $o['id'] ?>">

                                        <button type="submit"
                                            style="color:var(--verde); background:none; border:0; cursor:pointer;">
                                            Aceitar
                                        </button>

                                    </form>


                                    <form method="POST" action="recusar_orcamento.php" style="display:inline;"
                                        onsubmit="return confirm('Recusar este orçamento?')">

                                        <?= csrf_field() ?>

                                        <input type="hidden" name="id" value="<?= $o['id'] ?>">

                                        <button type="submit"
                                            style="color:var(--vermelho); background:none; border:0; cursor:pointer;">
                                            Recusar
                                        </button>

                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>

</html>