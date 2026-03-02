<?php
// index.php
require_once 'conexao/conexao.php'; // ajuste se estiver em outra pasta

// Total de prontuários (pacientes)
$total_pacientes = $pdo->query("SELECT COUNT(*) FROM prontuarios")->fetchColumn();

// Total de agendamentos futuros
$total_agendamentos = $pdo->query("
    SELECT COUNT(*) FROM agendamentos WHERE data >= CURDATE()
")->fetchColumn();

// Últimos agendamentos (com nome do paciente do prontuário ou avulso)
$stmt_proximos = $pdo->prepare("
    SELECT 
        COALESCE(p.paciente, a.paciente_nome) AS paciente,
        a.procedimento,
        a.data,
        a.horario
    FROM agendamentos a
    LEFT JOIN prontuarios p ON a.paciente_id = p.id
    WHERE a.data >= CURDATE()
    ORDER BY a.data ASC, a.horario ASC
    LIMIT 5
");
$stmt_proximos->execute();
$proximos_agendamentos = $stmt_proximos->fetchAll();

$stmt_orc = $pdo->prepare("
    SELECT 
        o.id,
        o.data_criacao,
        o.status,
        p.paciente
    FROM orcamentos o
    JOIN prontuarios p ON o.paciente_id = p.id
    ORDER BY o.data_criacao DESC
    LIMIT 5
");
$stmt_orc->execute();
$ultimos_orcamentos = $stmt_orc->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/index.css">
    <title>Dentech Dashboard</title>
</head>

<body>

    <!-- MENU LATERAL -->
    <aside class="sidebar">
        <div class="logo">
            <img src="img/logo.jpg" alt="Dentech">
        </div>

        <a href="prontuarios.php" class="nav-link">
            <div class="nav-dot"></div>
            Prontuários
        </a>
        <a href="inventario.php" class="nav-link">
            <div class="nav-dot"></div>
            Inventário
        </a>
        <a href="orcamento.php" class="nav-link">
            <div class="nav-dot"></div>
            Orçamentos
        </a>
        <a href="agendamentos.php" class="nav-link">
            <div class="nav-dot"></div>
            Agendamentos
        </a>

        <button class="logout" onclick="location.href='logout.php'">Sair</button>
    </aside>

    <!-- CONTEÚDO PRINCIPAL -->
    <main class="content">
        <h1>Dashboard</h1>

        <?php
        // Contar pacientes
        $total_pacientes = $pdo->query("SELECT COUNT(*) FROM prontuarios")->fetchColumn();

        // Contar agendamentos futuros
        $total_agendamentos = $pdo->query("
    SELECT COUNT(*) FROM agendamentos WHERE data >= CURDATE()
")->fetchColumn();

        // Verificar materiais com estoque baixo
        $stmt_estoque_baixo = $pdo->query("
    SELECT nome, quantidade, estoque_minimo, unidade
    FROM estoque
    WHERE quantidade <= estoque_minimo AND quantidade >= 0
    ORDER BY quantidade ASC
");
        $materiais_baixo = $stmt_estoque_baixo->fetchAll();
        $tem_estoque_baixo = !empty($materiais_baixo);
        ?>

        <?php
        // Relatório de orçamentos do mês atual
        $mes_atual = date('Y-m');
        $stmt_orc = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'aceito' THEN 1 ELSE 0 END) as aceitos,
        SUM(CASE WHEN status = 'recusado' THEN 1 ELSE 0 END) as recusados,
        COALESCE(SUM(itens.total_item), 0) as valor_total
    FROM orcamentos o
    LEFT JOIN (
        SELECT orcamento_id, SUM(quantidade * valor_unitario) as total_item
        FROM orcamentos_itens
        GROUP BY orcamento_id
    ) itens ON o.id = itens.orcamento_id
    WHERE DATE_FORMAT(o.data_criacao, '%Y-%m') = ?
");
        $stmt_orc->execute([$mes_atual]);
        $rel_orc = $stmt_orc->fetch();
        $total_orc = $rel_orc['total'] ?? 0;
        $aceitos = $rel_orc['aceitos'] ?? 0;
        $recusados = $rel_orc['recusados'] ?? 0;
        $valor_total = $rel_orc['valor_total'] ?? 0;
        ?>

        <div class="card-grid">
            <div class="card">
                <p class="card-title">Pacientes cadastrados</p>
                <p class="card-value"><?= $total_pacientes ?></p>
            </div>

            <div class="card">
                <p class="card-title">Agendamentos futuros</p>
                <p class="card-value"><?= $total_agendamentos ?></p>
            </div>

            <!-- CARD DE ESTOQUE -->
            <div class="card <?= $tem_estoque_baixo ? 'card-alerta' : '' ?>">
                <p class="card-title">Estoque de materiais</p>
                <?php if ($tem_estoque_baixo): ?>
                    <p class="card-value" style="color: #d32f2f; font-size: 20px;">
                        ⚠️ <?= count($materiais_baixo) ?> críticos
                    </p>
                    <div class="card-alerta-detalhe">
                        <?php foreach (array_slice($materiais_baixo, 0, 3) as $mat): ?>
                            <div class="alerta-item">
                                <span><?= htmlspecialchars($mat['nome']) ?></span>
                                <span><?= $mat['quantidade'] ?> / <?= $mat['estoque_minimo'] ?> <?= htmlspecialchars($mat['unidade']) ?></span>
                            </div>
                        <?php endforeach; ?>
                        <?php if (count($materiais_baixo) > 3): ?>
                            <div style="font-size:12px; color:#666; margin-top:6px;">
                                +<?= count($materiais_baixo) - 3 ?> itens críticos
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <p class="card-value" style="color: var(--roxo-escuro);">Estável</p>
                <?php endif; ?>
            </div>

            <div class="card">
                <p class="card-title">Orçamentos (<?= date('M/Y') ?>)</p>
                <p class="card-value" style="font-size: 20px;">R$ <?= number_format($valor_total, 2, ',', '.') ?></p>
                <div style="margin-top: 12px; font-size: 13px;">
                    <div style="display: flex; justify-content: space-between; margin: 4px 0;">
                        <span>Aceitos: <?= $aceitos ?></span>
                        <span style="color: #4caf50;"><?= $total_orc ? round(($aceitos / $total_orc) * 100) : 0 ?>%</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin: 4px 0;">
                        <span>Recusados: <?= $recusados ?></span>
                        <span style="color: #f44336;"><?= $total_orc ? round(($recusados / $total_orc) * 100) : 0 ?>%</span>
                    </div>
                    <div style="margin-top: 8px; font-weight: 600; color: var(--roxo-medio);">
                        Total: <?= $total_orc ?> orçamentos
                    </div>
                </div>
            </div>
        </div>

        <!-- LISTAS -->
        <div class="table-card">
            <h3>Últimos orçamentos</h3>
            <?php if (empty($ultimos_orcamentos)): ?>
                <div class="list-item">
                    <span><em>Nenhum orçamento registrado.</em></span>
                    <span>—</span>
                </div>
            <?php else: ?>
                <?php foreach ($ultimos_orcamentos as $orc): ?>
                    <div class="list-item">
                        <span>
                            <a href="visualizar_orcamento.php?id=<?= $orc['id'] ?>" class="link-orcamento">
                                <?= htmlspecialchars($orc['paciente']) ?>
                            </a>
                        </span>
                        <span>
                            <?= date('d/m/Y', strtotime($orc['data_criacao'])) ?>
                            <span class="status-orcamento status-<?= $orc['status'] ?>">
                                <?php
                                if ($orc['status'] == 'pendente') echo 'Pendente';
                                elseif ($orc['status'] == 'aceito') echo 'Aceito';
                                else echo 'Recusado';
                                ?>
                            </span>
                        </span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="table-card">
            <h3>Próximos agendamentos</h3>
            <?php if (empty($proximos_agendamentos)): ?>
                <div class="list-item">
                    <span>Nenhum agendamento futuro</span>
                    <span>—</span>
                </div>
            <?php else: ?>
                <?php foreach ($proximos_agendamentos as $ag): ?>
                    <?php
                    $dataFormatada = date("d/m", strtotime($ag["data"]));
                    $horaFormatada = substr($ag["horario"], 0, 5);
                    $nome = htmlspecialchars($ag["paciente"] ?? '—');
                    $proc = htmlspecialchars($ag["procedimento"]);
                    ?>
                    <div class="list-item">
                        <span><?= $nome ?> <small>(<?= $proc ?>)</small></span>
                        <span><?= $dataFormatada ?> às <?= $horaFormatada ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        </div>
    </main>

</body>

</html>