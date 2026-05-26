<?php
require_once 'conexao/conexao.php';

$mes = $_GET['mes'] ?? date('Y-m');
$data_inicio = $mes . '-01';
$data_fim = date('Y-m-t', strtotime($data_inicio)); 

$stmt = $pdo->prepare("
    SELECT 
        a.*,
        COALESCE(p.paciente, a.paciente_nome) AS nome_paciente
    FROM agendamentos a
    LEFT JOIN prontuarios p ON a.paciente_id = p.id
    WHERE a.data BETWEEN ? AND ?
    ORDER BY a.data, a.horario
");
$stmt->execute([$data_inicio, $data_fim]);
$agendamentos = $stmt->fetchAll();

$mes_formatado = date('F Y', strtotime($data_inicio));
$mes_formatado = ucfirst(strftime('%B de %Y', strtotime($data_inicio))); 
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agendamentos do Mês - Dentech</title>
    <link rel="stylesheet" href="css/navbar.css">
    <link rel="stylesheet" href="css/listar_mes.css">
    <link rel="icon" type="image/png" href="img/icon.PNG">
</head>

<body>
    <?php include 'navbar.php'; ?>

    <div class="container">
        <a href="agendamentos" class="voltar">&larr; Voltar para o dia</a>

        <h1>Agendamentos de <?= $mes_formatado ?></h1>

        <div class="filtro-mes">
            <form method="GET">
                <label for="mes">Ver outro mês:</label>
                <input type="month" name="mes" value="<?= htmlspecialchars($mes) ?>" required>
                <button type="submit">Filtrar</button>
            </form>
        </div>

        <?php if (empty($agendamentos)): ?>
            <div class="sem-agendamentos">
                Nenhum agendamento encontrado neste mês.
            </div>
        <?php else: ?>
            <?php
            // Agrupar agendamentos por data
            $agendamentos_por_data = [];
            foreach ($agendamentos as $ag) {
                $agendamentos_por_data[$ag['data']][] = $ag;
            }

            foreach ($agendamentos_por_data as $data => $lista):
                $data_formatada = date('d/m/Y', strtotime($data));
                $dia_semana = ucfirst(strftime('%A', strtotime($data)));
                $dias = [
                    'Monday' => 'Segunda-feira',
                    'Tuesday' => 'Terça-feira',
                    'Wednesday' => 'Quarta-feira',
                    'Thursday' => 'Quinta-feira',
                    'Friday' => 'Sexta-feira',
                    'Saturday' => 'Sábado',
                    'Sunday' => 'Domingo'
                ];
                $dia_semana_pt = $dias[date('l', strtotime($data))] ?? $dia_semana;
            ?>
                <div class="dia-secao">
                    <h2><?= $dia_semana_pt ?>, <?= $data_formatada ?></h2>
                    <div class="tabela-agendamentos">
                        <table>
                            <thead>
                                <tr>
                                    <th>Horário</th>
                                    <th>Paciente</th>
                                    <th>Procedimento</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lista as $ag): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($ag['horario']) ?></td>
                                        <td><?= htmlspecialchars($ag['nome_paciente']) ?></td>
                                        <td><?= htmlspecialchars($ag['procedimento']) ?></td>
                                        <td class="acoes">
                                            <a href="excluir_agendamentos.php?id=<?= $ag['id'] ?>"
                                                onclick="return confirm('Excluir este agendamento?');">
                                                Excluir
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>

</html>