<?php
require_once 'conexao/conexao.php';
require_once 'config/auth.php';

exigirLogin();

$total_pacientes = $pdo->query("SELECT COUNT(*) FROM prontuarios")->fetchColumn();

$total_agendamentos = $pdo->query("
    SELECT COUNT(*) FROM agendamentos WHERE data <= CURDATE()
")->fetchColumn();

$stmt_proximos = $pdo->prepare("
    SELECT 
        COALESCE(p.paciente, a.paciente_nome) AS paciente,
        a.procedimento,
        a.data,
        a.horario
    FROM agendamentos a
    LEFT JOIN prontuarios p ON a.paciente_id = p.id
    WHERE a.data <= CURDATE()           -- ← ✅ Apenas este filtro
    ORDER BY a.data DESC, a.horario DESC  
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

$stmt_estoque_baixo = $pdo->query("
    SELECT nome, quantidade, estoque_minimo, unidade
    FROM estoque
    WHERE quantidade <= estoque_minimo AND quantidade >= 0
    ORDER BY quantidade ASC
");
$materiais_baixo = $stmt_estoque_baixo->fetchAll();
$tem_estoque_baixo = !empty($materiais_baixo);

$mes_atual = date('Y-m');
$stmt_rel_orc = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'aceito' THEN 1 ELSE 0 END) as aceitos,
        SUM(CASE WHEN status = 'recusado' THEN 1 ELSE 0 END) as recusados,
        -- ← VALOR TOTAL: SOMA APENAS DOS ACEITOS (conforme solicitado)
        COALESCE(SUM(CASE WHEN o.status = 'aceito' THEN itens.total_item ELSE 0 END), 0) as valor_total_aceitos
    FROM orcamentos o
    LEFT JOIN (
        SELECT orcamento_id, SUM(quantidade * valor_unitario) as total_item
        FROM orcamentos_itens
        GROUP BY orcamento_id
    ) itens ON o.id = itens.orcamento_id
    WHERE DATE_FORMAT(o.data_criacao, '%Y-%m') = ?
");
$stmt_rel_orc->execute([$mes_atual]);
$rel_orc = $stmt_rel_orc->fetch();

$total_orc = $rel_orc['total'] ?? 0;
$aceitos = $rel_orc['aceitos'] ?? 0;
$recusados = $rel_orc['recusados'] ?? 0;
$valor_total_aceitos = $rel_orc['valor_total_aceitos'] ?? 0; 
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/index.css">
    <link rel="icon" type="image/png" href="img/icon.PNG">
    <title>Dentech Dashboard</title>
</head>

<body>
    <aside class="sidebar">
        <div class="logo">
            <img src="img/logo.jpg" alt="Dentech">
        </div>
        <a href="prontuarios" class="nav-link">
            <div class="nav-dot"></div>
            Prontuários
        </a>
        <a href="inventario" class="nav-link">
            <div class="nav-dot"></div>
            Inventário
        </a>
        <a href="orcamento" class="nav-link">
            <div class="nav-dot"></div>
            Orçamentos
        </a>
        <a href="agendamentos" class="nav-link">
            <div class="nav-dot"></div>
            Agendamentos
        </a>

        <a href="restrito" class="nav-link" style="font-weight: 600; color: #1a2e1a; margin-top: 12px; border-top: 1px solid rgba(0,0,0,0.1); padding-top: 12px;">
            <div class="nav-dot" style="background: #2c3e2c;"></div>
            🔒 Área Restrita
        </a>
        <button class="logout" onclick="location.href='logout.php'">Sair</button>
    </aside>

    <main class="content">
        <h1>Dashboard</h1>

        <div class="card-grid">
            <div class="card">
                <p class="card-title">Pacientes cadastrados</p>
                <p class="card-value"><?= (int)$total_pacientes ?></p>
            </div>

            <div class="card">
                <p class="card-title">Agendamentos recentes</p>
                <p class="card-value"><?= (int)$total_agendamentos ?></p>
                <small style="color:#666; font-size:12px;">Hoje e anteriores</small>
            </div>

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
                                <span><?= (int)$mat['quantidade'] ?> / <?= (int)$mat['estoque_minimo'] ?> <?= htmlspecialchars($mat['unidade']) ?></span>
                            </div>
                        <?php endforeach; ?>
                        <?php if (count($materiais_baixo) > 3): ?>
                            <div style="font-size:12px; color:#666; margin-top:6px;">
                                +<?= count($materiais_baixo) - 3 ?> itens críticos
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <p class="card-value" style="color: var(--roxo-escuro, #5e35b1);">Estável</p>
                <?php endif; ?>
            </div>

            <div class="card">
                <p class="card-title">Orçamentos (<?= date('M/Y') ?>)</p>
                <p class="card-value" style="font-size: 20px; color: #43a047;">
                    R$ <?= number_format($valor_total_aceitos, 2, ',', '.') ?>
                </p>
                <small style="color:#666; display:block; margin-bottom:8px;">
                    *Valor total dos orçamentos <strong>aceitos</strong>
                </small>
                <div style="margin-top: 8px; font-size: 13px;">
                    <div style="display: flex; justify-content: space-between; margin: 4px 0;">
                        <span>Aceitos: <strong><?= (int)$aceitos ?></strong></span>
                        <span style="color: #43a047;"><?= $total_orc > 0 ? round(($aceitos / $total_orc) * 100) : 0 ?>%</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin: 4px 0;">
                        <span>Recusados: <strong><?= (int)$recusados ?></strong></span>
                        <span style="color: #e53935;"><?= $total_orc > 0 ? round(($recusados / $total_orc) * 100) : 0 ?>%</span>
                    </div>
                    <div style="margin-top: 8px; font-weight: 600; color: var(--roxo-medio, #7b3ff2); border-top:1px solid #eee; padding-top:8px;">
                        Total geral: <?= (int)$total_orc ?> orçamentos
                    </div>
                </div>
            </div>
        </div>

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
                            <a href="visualizar_orcamento.php?id=<?= (int)$orc['id'] ?>" class="link-orcamento">
                                <?= htmlspecialchars($orc['paciente']) ?>
                            </a>
                        </span>
                        <span>
                            <?= date('d/m/Y', strtotime($orc['data_criacao'])) ?>
                            <span class="status-orcamento status-<?= htmlspecialchars($orc['status']) ?>">
                                <?= ucfirst(htmlspecialchars($orc['status'])) ?>
                            </span>
                        </span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="table-card">
            <h3>Agendamentos recentes</h3>
            <small style="color:#666; display:block; margin-bottom:12px;">
                * Apenas agendamentos criados manualmente (não via orçamento)
            </small>
            <?php if (empty($proximos_agendamentos)): ?>
                <div class="list-item">
                    <span>Nenhum agendamento recente</span>
                    <span>—</span>
                </div>
            <?php else: ?>
                <?php foreach ($proximos_agendamentos as $ag): ?>
                    <?php
                    $dataFormatada = date("d/m", strtotime($ag["data"]));
                    $horaFormatada = substr($ag["horario"] ?? '00:00', 0, 5);
                    $nome = htmlspecialchars($ag["paciente"] ?? '—');
                    $proc = htmlspecialchars($ag["procedimento"] ?? '');
                    ?>
                    <div class="list-item">
                        <span><?= $nome ?> <small style="color:#666">(<?= $proc ?>)</small></span>
                        <span><?= $dataFormatada ?> às <?= $horaFormatada ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
</body>

</html>