<?php
require_once 'conexao/conexao.php';
require_once 'config/auth.php';

exigirLogin();

// =====================================================
// DADOS DO DASHBOARD
// =====================================================

// Total pacientes
$total_pacientes = $pdo
    ->query("SELECT COUNT(*) FROM prontuarios")
    ->fetchColumn();

// Total agendamentos recentes
$total_agendamentos = $pdo
    ->query("
        SELECT COUNT(*) 
        FROM agendamentos 
        WHERE data <= CURDATE()
    ")
    ->fetchColumn();

// Últimos agendamentos
$stmt_proximos = $pdo->prepare("
    SELECT
        COALESCE(p.paciente, a.paciente_nome) AS paciente,
        a.procedimento,
        a.data,
        a.horario
    FROM agendamentos a
    LEFT JOIN prontuarios p 
        ON a.paciente_id = p.id
    WHERE a.data <= CURDATE()
    ORDER BY a.data DESC, a.horario DESC
    LIMIT 5
");

$stmt_proximos->execute();
$proximos_agendamentos = $stmt_proximos->fetchAll();

// =====================================================
// ORÇAMENTOS RECENTES
// =====================================================
$stmt_orc = $pdo->prepare("
    SELECT
        o.id,
        o.data_criacao,
        o.status,
        p.paciente
    FROM orcamentos o
    JOIN prontuarios p 
        ON o.paciente_id = p.id
    ORDER BY o.data_criacao DESC
    LIMIT 5
");

$stmt_orc->execute();
$ultimos_orcamentos = $stmt_orc->fetchAll();

// =====================================================
// ESTOQUE BAIXO
// =====================================================
$stmt_estoque = $pdo->query("
    SELECT 
        nome,
        quantidade,
        estoque_minimo,
        unidade
    FROM estoque
    WHERE quantidade <= estoque_minimo
    ORDER BY quantidade ASC
");

$materiais_baixo = $stmt_estoque->fetchAll();
$tem_estoque_baixo = !empty($materiais_baixo);
// =====================================================
// RELATÓRIO ORÇAMENTOS DO MÊS
// =====================================================
$mes_atual = date('Y-m');
$stmt_rel_orc = $pdo->prepare("
    SELECT

        COUNT(*) AS total,

        SUM(
            CASE 
                WHEN status='aceito' 
                THEN 1 
                ELSE 0 
            END
        ) AS aceitos,


        SUM(
            CASE 
                WHEN status='recusado' 
                THEN 1 
                ELSE 0 
            END
        ) AS recusados,


        COALESCE(
            SUM(
                CASE 
                    WHEN o.status='aceito' 
                    THEN itens.total_item 
                    ELSE 0 
                END
            ),0
        ) AS valor_total_aceitos


    FROM orcamentos o


    LEFT JOIN (

        SELECT 
            orcamento_id,
            SUM(
                quantidade * valor_unitario
            ) AS total_item

        FROM orcamentos_itens

        GROUP BY orcamento_id

    ) itens

    ON o.id = itens.orcamento_id 
    WHERE DATE_FORMAT(
        o.data_criacao,
        '%Y-%m'
    ) = ?
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
    <title>Dentech | Dashboard</title>
    <link rel="icon" href="img/logo.png">
    <link rel="stylesheet" href="css/global.css">
    <link rel="stylesheet" href="css/variables.css">
    <link rel="stylesheet" href="css/index.css">
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>

<body>
    <!-- MENU LATERAL -->
    <aside class="sidebar">
        <div class="sidebar-logo">
            <img src="img/logo.png" alt="Dentech">
        </div>
        <a href="index" class="nav-link">
            <i class="fa-solid fa-house"></i>
            <span>
                Dashboard
            </span>
        </a>
        <a href="prontuarios" class="nav-link">
            <i class="fa-solid fa-user-group"></i>
            <span>
                Prontuários
            </span>
        </a>
        <a href="agendamentos" class="nav-link">
            <i class="fa-solid fa-calendar-check"></i>
            <span>
                Agenda
            </span>
        </a>
        <a href="inventario" class="nav-link">
            <i class="fa-solid fa-box"></i>
            <span>
                Estoque
            </span>
        </a>
        <a href="orcamento" class="nav-link">
            <i class="fa-solid fa-file-invoice-dollar"></i>
            <span>
                Orçamentos
            </span>
        </a>
        <!-- ÁREA RESTRITA -->
        <a href="restrito" class="nav-link restricted">
            <i class="fa-solid fa-lock"></i>
            <span>
                Área Restrita
            </span>
        </a>
        <button
            class="logout"
            onclick="location.href='logout.php'">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span>
                Sair
            </span>
        </button>
    </aside>
    <!-- CONTEÚDO PRINCIPAL -->
    <main class="content">
        <div class="page-header">
            <div>
                <h1>
                    Dashboard
                </h1>
                <p>
                    Visão geral da clínica
                </p>
            </div>
            <div class="header-date">
                <i class="fa-solid fa-calendar"></i><?= date('d/m/Y') ?>
            </div>
        </div>
        <!-- ==========================
        CARDS PRINCIPAIS
=========================== -->
        <section class="card-grid">
            <!-- PACIENTES -->
            <div class="dashboard-card">
                <div class="card-icon blue">
                    <i class="fa-solid fa-users"></i>
                </div>
                <div class="card-info">
                    <span>
                        Pacientes cadastrados
                    </span>
                    <strong>
                        <?= (int)$total_pacientes ?>
                    </strong>
                </div>
            </div>
            <!-- AGENDAMENTOS -->
            <div class="dashboard-card">
                <div class="card-icon green">
                    <i class="fa-solid fa-calendar-check"></i>
                </div>
                <div class="card-info">
                    <span>
                        Agendamentos
                    </span>
                    <strong>
                        <?= (int)$total_agendamentos ?>
                    </strong>
                    <small>
                        Hoje e anteriores
                    </small>
                </div>
            </div>
            <!-- ESTOQUE -->
            <div class="dashboard-card <?= $tem_estoque_baixo ? 'danger-card' : '' ?>">
                <div class="card-icon orange">
                    <i class="fa-solid fa-box"></i>
                </div>
                <div class="card-info">
                    <span>
                        Estoque
                    </span>
                    <?php if ($tem_estoque_baixo): ?>
                        <strong class="danger-text">
                            <?= count($materiais_baixo) ?>
                        </strong>
                        <small>
                            Materiais críticos
                        </small>
                    <?php else: ?>
                        <strong>
                            OK
                        </strong>
                        <small>
                            Estoque normal
                        </small>
                    <?php endif; ?>
                </div>
            </div>
            <!-- ORÇAMENTOS -->
            <div class="dashboard-card">
                <div class="card-icon purple">
                    <i class="fa-solid fa-file-invoice-dollar"></i>
                </div>
                <div class="card-info">
                    <span>
                        Orçamentos aceitos
                    </span>
                    <strong>
                        R$ <?= number_format($valor_total_aceitos, 2, ',', '.') ?>
                    </strong>
                    <small>
                        <?= (int)$aceitos ?> aceitos /<?= (int)$total_orc ?> total
                    </small>
                </div>
            </div>
        </section>
        <!-- ==========================
        BLOCO DE ESTOQUE ALERTA
=========================== -->
        <?php if ($tem_estoque_baixo): ?>
            <section class="table-card estoque-alerta">
                <div class="section-title">
                    <h3><i class="fa-solid fa-triangle-exclamation"></i>Estoque baixo</h3>
                    <a href="inventario">Ver estoque</a>
                </div>
                <?php foreach (array_slice($materiais_baixo, 0, 5) as $mat): ?>
                    <div class="list-item">
                        <div>
                            <strong>
                                <?= htmlspecialchars($mat['nome']) ?>
                            </strong>
                            <br>
                            <small>
                                <?= htmlspecialchars($mat['unidade']) ?>
                            </small>
                        </div>
                        <div class="stock-value">
                            <?= number_format($mat['quantidade'], 2, ',', '.') ?> /<?= number_format($mat['estoque_minimo'], 2, ',', '.') ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
        <!-- ==========================
        ÚLTIMOS ORÇAMENTOS
=========================== -->
        <section class="table-card">
            <div class="section-title">
                <h3><i class="fa-solid fa-file-circle-check"></i>Últimos orçamentos</h3>
                <a href="orcamento"> Ver todos</a>
            </div>
            <!-- LISTA: Agendamentos recentes -->
            <div class="table-card">
                <h3>
                    <i class="fa-solid fa-calendar-check"></i>
                    Agendamentos recentes
                </h3>
                <?php if (empty($proximos_agendamentos)): ?>
                    <div class="list-item">
                        <span>
                            Nenhum agendamento encontrado.
                        </span>
                        <span>
                            —
                        </span>
                    </div>
                <?php else: ?>
                    <?php foreach ($proximos_agendamentos as $ag): ?>
                        <?php
                        $data = date("d/m/Y", strtotime($ag['data']));
                        $hora = substr(
                            $ag['horario'] ?? '00:00',
                            0,
                            5
                        );
                        $nome = htmlspecialchars($ag['paciente'] ?? 'Paciente não informado');
                        $procedimento = htmlspecialchars($ag['procedimento'] ?? '');
                        ?>
                        <div class="list-item">
                            <div>
                                <strong>
                                    <?= $nome ?>
                                </strong>
                                <br>
                                <small>
                                    <?= $procedimento ?>
                                </small>
                            </div>
                            <div>
                                <span>
                                    <i class="fa-solid fa-calendar"></i>
                                    <?= $data ?>
                                </span>
                                <br>
                                <small>
                                    <i class="fa-solid fa-clock"></i>
                                    <?= $hora ?>
                                </small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
    </main>
</body>

</html>