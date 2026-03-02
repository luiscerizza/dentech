<?php
require_once 'conexao/conexao.php';

$stmt = $pdo->query("
    SELECT 
        o.id,
        o.status,
        o.data_criacao,
        p.paciente
    FROM orcamentos o
    JOIN prontuarios p ON o.paciente_id = p.id
    ORDER BY o.data_criacao DESC
");
$orcamentos = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orçamentos - Dentech</title>
    <link rel="stylesheet" href="css/navbar.css">
    <link rel="stylesheet" href="css/orcamento.css">
</head>

<body>
    <?php include 'navbar.php'; ?>
    <div class="container">
        <h1>Orçamentos</h1>
        <a href="novo_orcamento.php" class="btn-add">+ Novo Orçamento</a>
        <?php if (empty($orcamentos)): ?>
            <p style="text-align:center; padding:40px; color:#888;">Nenhum orçamento cadastrado.</p>
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
                                <?php if ($o['status'] == 'pendente'): ?>
                                    <span class="status-pendente">Pendente</span>
                                <?php elseif ($o['status'] == 'aceito'): ?>
                                    <span class="status-aceito">Aceito</span>
                                <?php else: ?>
                                    <span class="status-recusado">Recusado</span>
                                <?php endif; ?>
                            </td>
                            <td class="acoes">
                                <a href="visualizar_orcamento.php?id=<?= $o['id'] ?>">Visualizar</a>
                                <?php if ($o['status'] == 'pendente'): ?>
                                    <a href="editar_orcamento.php?id=<?= $o['id'] ?>" style="color:var(--roxo-medio);">Editar</a>
                                    <a href="aceitar_orcamento.php?id=<?= $o['id'] ?>" style="color:var(--verde);" onclick="return confirm('Aceitar este orçamento?')">Aceitar</a>
                                    <a href="recusar_orcamento.php?id=<?= $o['id'] ?>" style="color:var(--vermelho);" onclick="return confirm('Recusar este orçamento?')">Recusar</a>
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