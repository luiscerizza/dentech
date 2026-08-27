<?php

require_once 'conexao/conexao.php';

$mes = $_GET['mes'] ?? date('Y-m');

if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
    $mes = date('Y-m');
}

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
$agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
 * Nome dos meses em português
 */
$meses = [
    1  => 'Janeiro',
    2  => 'Fevereiro',
    3  => 'Março',
    4  => 'Abril',
    5  => 'Maio',
    6  => 'Junho',
    7  => 'Julho',
    8  => 'Agosto',
    9  => 'Setembro',
    10 => 'Outubro',
    11 => 'Novembro',
    12 => 'Dezembro'
];

$numero_mes = (int) date('n', strtotime($data_inicio));
$ano = date('Y', strtotime($data_inicio));

$mes_formatado = $meses[$numero_mes] . ' de ' . $ano;

/*
 * Dias da semana
 */
$dias_semana = [
    'Monday'    => 'Segunda-feira',
    'Tuesday'   => 'Terça-feira',
    'Wednesday' => 'Quarta-feira',
    'Thursday'  => 'Quinta-feira',
    'Friday'    => 'Sexta-feira',
    'Saturday'  => 'Sábado',
    'Sunday'    => 'Domingo'
];

/*
 * Agrupar agendamentos por data
 */
$agendamentos_por_data = [];

foreach ($agendamentos as $agendamento) {
    $agendamentos_por_data[$agendamento['data']][] = $agendamento;
}

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Agendamentos do Mês - Dentech</title>

    <link rel="stylesheet" href="css/global.css">

    <linkrel="stylesheet" href="css/variables.css">

        <link rel="stylesheet" href="css/layout.css">
        <link rel="stylesheet" href="css/navbar.css">
        <link rel="stylesheet" href="css/listar_mes.css">
        <link rel="icon" type="image/png" href="img/icon.PNG">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>

<body>

    <?php include 'navbar.php'; ?>

    <main class="mes-page">

        <div class="mes-container">

            <div class="cabecalho-pagina">

                <div>
                    <a href="agendamentos.php" class="voltar">
                        ← Voltar para o dia
                    </a>

                    <h1>
                        Agendamentos de <?= htmlspecialchars($mes_formatado) ?>
                    </h1>

                    <p class="subtitulo">
                        Visualização mensal dos agendamentos
                    </p>
                </div>

            </div>

            <section class="filtro-card">

                <form method="GET" class="filtro-mes">

                    <div class="campo-filtro">

                        <label for="mes">
                            Selecionar mês
                        </label>

                        <input
                            type="month"
                            id="mes"
                            name="mes"
                            value="<?= htmlspecialchars($mes) ?>"
                            required>

                    </div>

                    <button type="submit" class="btn-filtrar">
                        Filtrar mês
                    </button>

                </form>

            </section>

            <?php if (empty($agendamentos_por_data)): ?>

                <section class="sem-agendamentos">

                    <div class="sem-agendamentos-icon">
                        📅
                    </div>

                    <h2>
                        Nenhum agendamento encontrado
                    </h2>

                    <p>
                        Não existem agendamentos cadastrados para
                        <?= htmlspecialchars($mes_formatado) ?>.
                    </p>

                </section>

            <?php else: ?>

                <div class="resumo-mes">

                    <strong>
                        <?= count($agendamentos) ?>
                    </strong>

                    <span>
                        <?= count($agendamentos) === 1
                            ? 'agendamento encontrado'
                            : 'agendamentos encontrados'
                        ?>
                    </span>

                </div>

                <div class="dias-container">

                    <?php foreach ($agendamentos_por_data as $data => $lista): ?>

                        <?php
                        $timestamp = strtotime($data);

                        $data_formatada = date('d/m/Y', $timestamp);

                        $dia_semana_en = date('l', $timestamp);

                        $dia_semana_pt =
                            $dias_semana[$dia_semana_en]
                            ?? $dia_semana_en;
                        ?>

                        <section class="dia-secao">

                            <div class="dia-cabecalho">

                                <div class="dia-data">

                                    <span class="dia-semana">
                                        <?= htmlspecialchars($dia_semana_pt) ?>
                                    </span>

                                    <span class="data-completa">
                                        <?= htmlspecialchars($data_formatada) ?>
                                    </span>

                                </div>

                                <span class="contador-dia">
                                    <?= count($lista) ?>
                                    <?= count($lista) === 1
                                        ? 'agendamento'
                                        : 'agendamentos'
                                    ?>
                                </span>

                            </div>

                            <div class="tabela-wrapper">

                                <table class="tabela-agendamentos">

                                    <thead>

                                        <tr>
                                            <th>Horário</th>
                                            <th>Paciente</th>
                                            <th>Procedimento</th>
                                            <th>Status</th>
                                            <th>Ações</th>
                                        </tr>

                                    </thead>

                                    <tbody>

                                        <?php foreach ($lista as $ag): ?>

                                            <?php
                                            $status = strtolower(
                                                trim($ag['status'] ?? 'agendado')
                                            );

                                            $status_class = match ($status) {
                                                'concluido', 'concluído' => 'concluido',
                                                'cancelado', 'cancelada' => 'cancelado',
                                                default => 'agendado'
                                            };

                                            $status_text = match ($status) {
                                                'concluido', 'concluído' => 'Concluído',
                                                'cancelado', 'cancelada' => 'Cancelado',
                                                default => 'Agendado'
                                            };
                                            ?>

                                            <tr>

                                                <td class="horario">
                                                    <?= htmlspecialchars(
                                                        date(
                                                            'H:i',
                                                            strtotime($ag['horario'])
                                                        )
                                                    ) ?>
                                                </td>

                                                <td class="paciente">
                                                    <?= htmlspecialchars(
                                                        $ag['nome_paciente'] ?? 'Não informado'
                                                    ) ?>
                                                </td>

                                                <td class="procedimento">
                                                    <?= htmlspecialchars(
                                                        $ag['procedimento'] ?? '-'
                                                    ) ?>
                                                </td>

                                                <td>

                                                    <span class="status <?= $status_class ?>">
                                                        <?= htmlspecialchars($status_text) ?>
                                                    </span>

                                                </td>

                                                <td class="acoes">

                                                    <a
                                                        href="excluir_agendamentos.php?id=<?= (int) $ag['id'] ?>"
                                                        class="btn-excluir"
                                                        onclick="return confirm('Excluir este agendamento?');">
                                                        Excluir
                                                    </a>

                                                </td>

                                            </tr>

                                        <?php endforeach; ?>

                                    </tbody>

                                </table>

                            </div>

                        </section>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </div>

    </main>

</body>

</html>