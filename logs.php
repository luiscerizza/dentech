<?php
require_once 'conexao/conexao.php';
require_once 'config/auth.config_area_restrita.php';

exigeAcessoRestrito();

$busca_usuario = trim($_GET['busca_usuario'] ?? '');
$filtro_acao   = $_GET['filtro_acao'] ?? '';
$data_ini      = $_GET['data_ini'] ?? '';
$data_fim      = $_GET['data_fim'] ?? '';

$where = [];
$params = [];

if ($busca_usuario !== '') {
    $where[] = "usuario LIKE ?";
    $params[] = "%{$busca_usuario}%";
}
if ($filtro_acao !== '') {
    $where[] = "acao = ?";
    $params[] = $filtro_acao;
}
if ($data_ini !== '') {
    $where[] = "created_at >= ?";
    $params[] = $data_ini . ' 00:00:00';
}
if ($data_fim !== '') {
    $where[] = "created_at <= ?";
    $params[] = $data_fim . ' 23:59:59';
}

$sql = "SELECT * FROM logs";
if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY id DESC LIMIT 100"; 

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs de Auditoria - Dentech</title>
    <link rel="stylesheet" href="css/navbar.css">
    <link rel="stylesheet" href="css/prontuarios.css">
    <link rel="icon" type="image/png" href="img/icon.PNG">

</head>

<body>
    <?php include 'navbar.php'; ?>

    <div class="container">
        <h1> Logs de Auditoria</h1>

        <div class="filter-bar">
            <form method="GET" class="filter-form">
                <input type="text" name="busca_usuario" placeholder="Usuário..." value="<?= htmlspecialchars($busca_usuario) ?>">
                <select name="filtro_acao">
                    <option value="">Todas as Ações</option>
                    <option value="Login" <?= $filtro_acao === 'Login' ? 'selected' : '' ?>>Login</option>
                    <option value="Criar" <?= $filtro_acao === 'Criar' ? 'selected' : '' ?>>Criação</option>
                    <option value="Editar" <?= $filtro_acao === 'Editar' ? 'selected' : '' ?>>Edição</option>
                    <option value="Excluir" <?= $filtro_acao === 'Excluir' ? 'selected' : '' ?>>Exclusão</option>
                    <option value="Restrito" <?= $filtro_acao === 'Restrito' ? 'selected' : '' ?>>Área Restrita</option>
                </select>
                <input type="date" name="data_ini" value="<?= htmlspecialchars($data_ini) ?>">
                <input type="date" name="data_fim" value="<?= htmlspecialchars($data_fim) ?>">
                <button type="submit" class="btn-filter">🔍 Filtrar</button>
                <a href="logs.php" class="btn-reset">✖ Limpar</a>
            </form>
        </div>

        <div class="tabela-estoque" style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px;">
                <thead>
                    <tr style="background: #7b3ff2; color: #fff;">
                        <th style="padding: 12px; text-align: left;">Data/Hora</th>
                        <th style="padding: 12px; text-align: left;">Usuário</th>
                        <th style="padding: 12px; text-align: left;">Ação</th>
                        <th style="padding: 12px; text-align: left;">Módulo</th>
                        <th style="padding: 12px; text-align: left;">Detalhes</th>
                        <th style="padding: 12px; text-align: left;">IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6" style="padding: 20px; text-align: center; color: #888;">Nenhum log encontrado.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log):
                            // Define cor do badge
                            $badge_class = 'bg-login';
                            if ($log['acao'] === 'Criar') $badge_class = 'bg-criar';
                            if ($log['acao'] === 'Editar') $badge_class = 'bg-editar';
                            if ($log['acao'] === 'Excluir') $badge_class = 'bg-excluir';
                            if ($log['acao'] === 'Restrito') $badge_class = 'bg-restrito';
                        ?>
                            <tr style="border-bottom: 1px solid #eee;">
                                <td style="padding: 10px; white-space: nowrap;">
                                    <?= date('d/m/Y H:i', strtotime($log['created_at'])) ?>
                                </td>
                                <td style="padding: 10px; font-weight: 500;"><?= htmlspecialchars($log['usuario']) ?></td>
                                <td style="padding: 10px;">
                                    <span class="badge-acao <?= $badge_class ?>"><?= htmlspecialchars($log['acao']) ?></span>
                                </td>
                                <td style="padding: 10px; font-size: 12px; color: #555;"><?= htmlspecialchars($log['tabela'] ?? '—') ?></td>
                                <td style="padding: 10px;">
                                    <div class="detalhes-text" title="<?= htmlspecialchars($log['detalhes']) ?>">
                                        <?= htmlspecialchars($log['detalhes']) ?>
                                    </div>
                                </td>
                                <td style="padding: 10px; font-family: monospace; font-size: 11px; color: #888;">
                                    <?= htmlspecialchars($log['ip']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>

</html>