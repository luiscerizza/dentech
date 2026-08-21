<?php

require_once 'config/auth.php';
exigirLogin();

require_once 'conexao/conexao.php';

/*
|--------------------------------------------------------------------------
| DATA SELECIONADA
|--------------------------------------------------------------------------
*/

$data_filtro = $_GET['data'] ?? date('Y-m-d');

$dataAtual = DateTime::createFromFormat('Y-m-d', $data_filtro);

if (!$dataAtual || $dataAtual->format('Y-m-d') !== $data_filtro) {
    $dataAtual = new DateTime();
    $data_filtro = $dataAtual->format('Y-m-d');
}

/*
|--------------------------------------------------------------------------
| DATAS DE NAVEGAÇÃO
|--------------------------------------------------------------------------
*/

$dataAnterior = (clone $dataAtual)
    ->modify('-1 day')
    ->format('Y-m-d');

$dataProxima = (clone $dataAtual)
    ->modify('+1 day')
    ->format('Y-m-d');

$dataHoje = date('Y-m-d');

/*
|--------------------------------------------------------------------------
| PACIENTES
|--------------------------------------------------------------------------
*/

$stmt_pacientes = $pdo->query("
    SELECT
        id,
        paciente
    FROM prontuarios
    ORDER BY paciente ASC
");

$prontuarios = $stmt_pacientes->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| AGENDAMENTOS DO DIA
|--------------------------------------------------------------------------
*/

$stmt_agendamentos = $pdo->prepare("
    SELECT
        a.id,
        a.paciente_id,
        COALESCE(p.paciente, a.paciente_nome) AS nome_paciente,
        a.procedimento,
        a.data,
        a.horario,
        COALESCE(a.status, 'agendado') AS status
    FROM agendamentos a
    LEFT JOIN prontuarios p
        ON a.paciente_id = p.id
    WHERE a.data = ?
    ORDER BY a.horario ASC
");

$stmt_agendamentos->execute([$data_filtro]);

$agendamentos = $stmt_agendamentos->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| PRÓXIMOS AGENDAMENTOS
|--------------------------------------------------------------------------
*/

$stmt_proximos = $pdo->prepare("
    SELECT
        a.id,
        a.paciente_id,
        COALESCE(p.paciente, a.paciente_nome) AS nome_paciente,
        a.procedimento,
        a.data,
        a.horario,
        COALESCE(a.status, 'agendado') AS status
    FROM agendamentos a
    LEFT JOIN prontuarios p
        ON a.paciente_id = p.id
    WHERE a.data >= ?
    ORDER BY
        a.data ASC,
        a.horario ASC
    LIMIT 5
");

$stmt_proximos->execute([$dataHoje]);

$proximos = $stmt_proximos->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| CALENDÁRIO DO MÊS
|--------------------------------------------------------------------------
*/

$mesCalendario = (clone $dataAtual)
    ->modify('first day of this month');

$anoMes = (int)$mesCalendario->format('Y');
$mesMes = (int)$mesCalendario->format('m');

$quantidadeDias = (int)$mesCalendario->format('t');

/*
|--------------------------------------------------------------------------
| CORREÇÃO DO CALENDÁRIO
|--------------------------------------------------------------------------
|
| PHP:
| N = segunda 1 ... domingo 7
|
| Nossa grade:
| domingo = 0
| segunda = 1
| terça   = 2
| ...
|
| Portanto usamos:
| N % 7
|
*/

$primeiroDiaSemana = (int)$mesCalendario->format('N') % 7;

$mesAnterior = (clone $mesCalendario)
    ->modify('-1 month')
    ->format('Y-m-d');

$mesProximo = (clone $mesCalendario)
    ->modify('+1 month')
    ->format('Y-m-d');

$nomesMeses = [
    1 => 'Janeiro',
    2 => 'Fevereiro',
    3 => 'Março',
    4 => 'Abril',
    5 => 'Maio',
    6 => 'Junho',
    7 => 'Julho',
    8 => 'Agosto',
    9 => 'Setembro',
    10 => 'Outubro',
    11 => 'Novembro',
    12 => 'Dezembro'
];

$nomeMes = $nomesMeses[$mesMes];

/*
|--------------------------------------------------------------------------
| ORGANIZAR AGENDAMENTOS POR HORA
|--------------------------------------------------------------------------
|
| Exemplo:
|
| 08:30 -> 08:00
| 10:15 -> 10:00
| 19:45 -> 19:00
|
| O evento continua mostrando o horário real.
|
*/

$agendamentosPorHorario = [];

foreach ($agendamentos as $agendamento) {

    $horarioOriginal = substr(
        (string)$agendamento['horario'],
        0,
        5
    );

    if (preg_match('/^(\d{2}):(\d{2})$/', $horarioOriginal, $match)) {

        $hora = (int)$match[1];

        $horarioGrade = sprintf(
            '%02d:00',
            $hora
        );
    } else {

        $horarioGrade = '00:00';
    }

    $agendamentosPorHorario[$horarioGrade][] = $agendamento;
}

/*
|--------------------------------------------------------------------------
| HORÁRIOS DA AGENDA
|--------------------------------------------------------------------------
|
| Grade padrão:
| 08:00 até 18:00
|
| Porém, se existir um agendamento fora desse intervalo,
| o horário dele será automaticamente acrescentado.
|
*/

$horarios = [];

/*
| Horário padrão
*/

for ($hora = 8; $hora <= 18; $hora++) {

    $horarios[] = sprintf(
        '%02d:00',
        $hora
    );
}

/*
| Adicionar horários de agendamentos fora da grade
*/

foreach (array_keys($agendamentosPorHorario) as $horarioAgendamento) {

    if (!in_array($horarioAgendamento, $horarios, true)) {

        $horarios[] = $horarioAgendamento;
    }
}

/*
| Ordenar horários
*/

usort(
    $horarios,
    function ($a, $b) {

        return strcmp($a, $b);
    }
);

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Agendamentos - Dentech</title>

    <link
        rel="stylesheet"
        href="css/global.css">

    <link
        rel="stylesheet"
        href="css/variables.css">

    <link
        rel="stylesheet"
        href="css/layout.css">

    <link
        rel="stylesheet"
        href="css/agendamento.css">

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <link
        rel="icon"
        type="image/png"
        href="img/icon.PNG">

</head>

<body>

    <?php include 'navbar.php'; ?>

    <div class="agenda-page">

        <!-- =========================================================
             CABEÇALHO
        ========================================================== -->

        <header class="agenda-header">

            <div>

                <h1>Agendamentos</h1>

            </div>

            <button
                type="button"
                class="btn-novo-agendamento"
                id="abrirModal">

                <i class="fa-solid fa-plus"></i>

                Novo agendamento

            </button>

        </header>


        <!-- =========================================================
             BARRA SUPERIOR
        ========================================================== -->

        <section class="agenda-toolbar">

            <div class="agenda-navigation">

                <a
                    href="?data=<?= htmlspecialchars($dataHoje) ?>"
                    class="btn-hoje">

                    Hoje

                </a>


                <div class="date-navigation">

                    <a
                        href="?data=<?= htmlspecialchars($dataAnterior) ?>"
                        class="date-arrow"
                        title="Dia anterior">

                        <i class="fa-solid fa-chevron-left"></i>

                    </a>


                    <div class="selected-date">

                        <i class="fa-regular fa-calendar"></i>

                        <span>

                            <?= $dataAtual->format('d') ?>

                            de

                            <?= htmlspecialchars($nomeMes) ?>

                            de

                            <?= $dataAtual->format('Y') ?>

                        </span>

                    </div>


                    <a
                        href="?data=<?= htmlspecialchars($dataProxima) ?>"
                        class="date-arrow"
                        title="Próximo dia">

                        <i class="fa-solid fa-chevron-right"></i>

                    </a>

                </div>

            </div>


            <div class="view-buttons">

                <button
                    type="button"
                    class="view-button active">

                    Dia

                </button>


                <button
                    type="button"
                    class="view-button"
                    onclick="window.location.href='listar_mes.php?mes=<?= htmlspecialchars($dataAtual->format('Y-m')) ?>'">

                    Mês

                </button>

            </div>

        </section>


        <!-- =========================================================
             CONTEÚDO PRINCIPAL
        ========================================================== -->

        <div class="agenda-layout">


            <!-- =====================================================
                 AGENDA DO DIA
            ====================================================== -->

            <section class="agenda-card">

                <div class="agenda-grid">


                    <!-- =================================================
                         COLUNA DE HORÁRIOS
                    ================================================== -->

                    <div class="time-column">

                        <?php foreach ($horarios as $horario): ?>

                            <div class="time-slot">

                                <?= htmlspecialchars($horario) ?>

                            </div>

                        <?php endforeach; ?>

                    </div>


                    <!-- =================================================
                         COLUNA DE AGENDAMENTOS
                    ================================================== -->

                    <div class="appointments-column">

                        <?php foreach ($horarios as $index => $horario): ?>

                            <div class="hour-row">

                                <?php

                                $agendamentosHorario =
                                    $agendamentosPorHorario[$horario] ?? [];

                                ?>


                                <?php if (!empty($agendamentosHorario)): ?>

                                    <?php foreach (
                                        $agendamentosHorario
                                        as $posicao => $ag
                                    ): ?>

                                        <?php

                                        $classesEvento = [
                                            'event-blue',
                                            'event-green',
                                            'event-purple'
                                        ];

                                        $classeEvento =
                                            $classesEvento[($index + $posicao)
                                                %
                                                count($classesEvento)];

                                        ?>


                                        <div
                                            class="appointment-event <?= htmlspecialchars($classeEvento) ?> <?= $ag['status'] === 'confirmado' ? 'appointment-confirmed' : '' ?>">

                                            <div class="event-content">
                                                <strong class="<?= $ag['status'] === 'confirmado' ? 'paciente-confirmado' : '' ?>">
                                                    <?= htmlspecialchars($ag['nome_paciente']) ?>
                                                </strong>

                                                <span>
                                                    <?= htmlspecialchars($ag['procedimento']) ?>
                                                </span>

                                                <small>
                                                    <?= htmlspecialchars(substr($ag['horario'], 0, 5)) ?>
                                                </small>
                                            </div>

                                            <div class="event-actions">

                                                <?php if (!empty($ag['paciente_id'])): ?>
                                                    <a
                                                        href="visualizar_prontuario.php?id=<?= (int)$ag['paciente_id'] ?>"
                                                        title="Visualizar prontuário">
                                                        <i class="fa-regular fa-folder-open"></i>
                                                    </a>
                                                <?php endif; ?>

                                                <?php if ($ag['status'] === 'confirmado'): ?>
                                                    <?php if (!empty($ag['paciente_id'])): ?>
                                                        <a href="adicionar_procedimento.php?prontuario_id=<?= (int)$ag['paciente_id'] ?>&agendamento_id=<?= (int)$ag['id'] ?>">
                                                            title="Adicionar procedimento">
                                                            <i class="fa-solid fa-tooth"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <form
                                                        method="POST"
                                                        action="confirmar_agendamento.php"
                                                        onsubmit="return confirm('Confirmar este agendamento?')">

                                                        <?= csrf_field() ?>

                                                        <input
                                                            type="hidden"
                                                            name="id"
                                                            value="<?= (int)$ag['id'] ?>">

                                                        <button
                                                            type="submit"
                                                            title="Confirmar agendamento">
                                                            <i class="fa-solid fa-check"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>

                                                <form
                                                    method="POST"
                                                    action="excluir_agendamento.php"
                                                    onsubmit="return confirm('Excluir este agendamento?')">

                                                    <?= csrf_field() ?>

                                                    <input
                                                        type="hidden"
                                                        name="id"
                                                        value="<?= (int)$ag['id'] ?>">

                                                    <button
                                                        type="submit"
                                                        title="Excluir agendamento">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                </form>

                                            </div>
                                        </div>

                                    <?php endforeach; ?>

                                <?php endif; ?>

                            </div>

                        <?php endforeach; ?>

                    </div>

                </div>

            </section>


            <!-- =====================================================
                 COLUNA DIREITA
            ====================================================== -->

            <aside class="agenda-sidebar">


                <!-- =================================================
                     CALENDÁRIO
                ================================================== -->

                <section class="calendar-card">

                    <div class="calendar-header">

                        <a
                            href="?data=<?= htmlspecialchars($mesAnterior) ?>"
                            class="calendar-arrow">

                            <i class="fa-solid fa-chevron-left"></i>

                        </a>


                        <h2>

                            <?= htmlspecialchars($nomeMes) ?>

                            <?= $anoMes ?>

                        </h2>


                        <a
                            href="?data=<?= htmlspecialchars($mesProximo) ?>"
                            class="calendar-arrow">

                            <i class="fa-solid fa-chevron-right"></i>

                        </a>

                    </div>


                    <!-- =================================================
                         DIAS DA SEMANA
                    ================================================== -->

                    <div class="calendar-weekdays">

                        <span>D</span>
                        <span>S</span>
                        <span>T</span>
                        <span>Q</span>
                        <span>Q</span>
                        <span>S</span>
                        <span>S</span>

                    </div>


                    <!-- =================================================
                         DIAS
                    ================================================== -->

                    <div class="calendar-days">

                        <?php

                        /*
                        | Dias do mês anterior
                        |
                        | Como o calendário começa no domingo,
                        | o deslocamento agora está correto.
                        */

                        $mesAnteriorCalendario = (clone $mesCalendario)
                            ->modify('-1 month');

                        $diasMesAnterior =
                            (int)$mesAnteriorCalendario->format('t');


                        /*
                        | Preencher dias anteriores ao primeiro dia
                        */

                        for (
                            $i = $primeiroDiaSemana - 1;
                            $i >= 0;
                            $i--
                        ):

                            $diaAnterior =
                                $diasMesAnterior - $i;

                        ?>

                            <a
                                href="?data=<?= htmlspecialchars(
                                                $mesAnteriorCalendario->format('Y-m-')
                                            ) . sprintf('%02d', $diaAnterior) ?>"
                                class="other-month">

                                <?= $diaAnterior ?>

                            </a>

                        <?php endfor; ?>


                        <!-- =============================================
                             DIAS DO MÊS ATUAL
                        ============================================== -->

                        <?php for (
                            $dia = 1;
                            $dia <= $quantidadeDias;
                            $dia++
                        ): ?>

                            <?php

                            $dataDia = sprintf(
                                '%04d-%02d-%02d',
                                $anoMes,
                                $mesMes,
                                $dia
                            );

                            $classes = [];


                            if ($dataDia === $data_filtro) {

                                $classes[] = 'selected';
                            }


                            if ($dataDia === $dataHoje) {

                                $classes[] = 'today';
                            }

                            ?>


                            <a
                                href="?data=<?= htmlspecialchars($dataDia) ?>"
                                class="<?= htmlspecialchars(
                                            implode(' ', $classes)
                                        ) ?>">

                                <?= $dia ?>

                            </a>

                        <?php endfor; ?>


                        <?php

                        /*
                        | Preencher os dias restantes da última semana
                        |
                        | Isso mantém a grade visualmente organizada.
                        */

                        $totalCelulas =
                            $primeiroDiaSemana + $quantidadeDias;

                        $celulasRestantes =
                            (7 - ($totalCelulas % 7)) % 7;

                        $mesProximoCalendario =
                            (clone $mesCalendario)
                            ->modify('+1 month');

                        for (
                            $dia = 1;
                            $dia <= $celulasRestantes;
                            $dia++
                        ):

                            $dataProximoMes =
                                $mesProximoCalendario->format('Y-m-')
                                . sprintf('%02d', $dia);

                        ?>

                            <a
                                href="?data=<?= htmlspecialchars($dataProximoMes) ?>"
                                class="other-month">

                                <?= $dia ?>

                            </a>

                        <?php endfor; ?>

                    </div>

                </section>


                <!-- =================================================
                     PRÓXIMOS AGENDAMENTOS
                ================================================== -->

                <section class="next-appointments-card">

                    <div class="next-header">

                        <h2>

                            Próximos agendamentos

                        </h2>

                    </div>


                    <?php if (empty($proximos)): ?>

                        <div class="next-empty">

                            <i class="fa-regular fa-calendar-xmark"></i>

                            <span>

                                Nenhum agendamento próximo.

                            </span>

                        </div>

                    <?php else: ?>

                        <div class="next-list">

                            <?php foreach ($proximos as $proximo): ?>

                                <a
                                    href="?data=<?= htmlspecialchars($proximo['data']) ?>"
                                    class="next-item">


                                    <div class="next-date">

                                        <strong>

                                            <?= date(
                                                'd/m',
                                                strtotime(
                                                    $proximo['data']
                                                )
                                            ) ?>

                                        </strong>


                                        <span>

                                            <?= htmlspecialchars(
                                                substr(
                                                    $proximo['horario'],
                                                    0,
                                                    5
                                                )
                                            ) ?>

                                        </span>

                                    </div>


                                    <div class="next-patient">

                                        <strong>

                                            <?= htmlspecialchars(
                                                $proximo['nome_paciente']
                                            ) ?>

                                        </strong>


                                        <span>

                                            <?= htmlspecialchars(
                                                $proximo['procedimento']
                                            ) ?>

                                        </span>

                                    </div>

                                </a>

                            <?php endforeach; ?>

                        </div>

                    <?php endif; ?>

                </section>

            </aside>

        </div>

    </div>


    <!-- =============================================================
         MODAL NOVO AGENDAMENTO
    ============================================================== -->

    <div
        class="modal-overlay"
        id="modalAgendamento">


        <div class="modal-agendamento">


            <!-- =====================================================
                 CABEÇALHO DO MODAL
            ====================================================== -->

            <div class="modal-header">

                <div>

                    <span class="modal-kicker">

                        AGENDAMENTOS

                    </span>


                    <h2>

                        Novo agendamento

                    </h2>

                </div>


                <button
                    type="button"
                    class="modal-close"
                    id="fecharModal">

                    <i class="fa-solid fa-xmark"></i>

                </button>

            </div>


            <!-- =====================================================
                 MENSAGEM
            ====================================================== -->

            <div
                id="mensagem"
                class="mensagem"
                style="display:none;">

            </div>


            <!-- =====================================================
                 FORMULÁRIO
            ====================================================== -->

            <form
                id="formAgendamento">


                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(
                                $_SESSION['csrf_token']
                            ) ?>">


                <!-- PACIENTE -->

                <div class="form-group">

                    <label for="paciente_id">

                        Paciente cadastrado

                    </label>


                    <select
                        name="paciente_id"
                        id="paciente_id">

                        <option value="">

                            Selecione o paciente

                        </option>


                        <?php foreach ($prontuarios as $p): ?>

                            <option
                                value="<?= htmlspecialchars(
                                            $p['id']
                                        ) ?>">

                                <?= htmlspecialchars(
                                    $p['paciente']
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- NOME AVULSO -->

                <div class="form-group">

                    <label for="paciente_nome">

                        Nome avulso

                    </label>


                    <input
                        type="text"
                        name="paciente_nome"
                        id="paciente_nome"
                        placeholder="Opcional">

                </div>


                <!-- PROCEDIMENTO -->

                <div class="form-group">

                    <label for="procedimento">

                        Procedimento

                    </label>


                    <input
                        type="text"
                        name="procedimento"
                        id="procedimento"
                        required
                        placeholder="Ex.: Limpeza, Consulta, Clareamento">

                </div>


                <!-- DATA E HORÁRIO -->

                <div class="form-row">


                    <div class="form-group">

                        <label for="data">

                            Data

                        </label>


                        <input
                            type="date"
                            name="data"
                            id="data"
                            value="<?= htmlspecialchars(
                                        $data_filtro
                                    ) ?>"
                            required>

                    </div>


                    <div class="form-group">

                        <label for="horario">

                            Horário

                        </label>


                        <input
                            type="time"
                            name="horario"
                            id="horario"
                            required>

                    </div>


                </div>


                <!-- BOTÕES -->

                <div class="modal-actions">


                    <button
                        type="button"
                        class="btn-cancelar"
                        id="cancelarModal">

                        Cancelar

                    </button>


                    <button
                        type="submit"
                        class="btn-salvar">

                        <i class="fa-solid fa-check"></i>

                        Agendar

                    </button>


                </div>


            </form>

        </div>

    </div>


    <!-- =============================================================
         JAVASCRIPT
    ============================================================== -->

    <script>
        /*
        |--------------------------------------------------------------------------
        | MODAL
        |--------------------------------------------------------------------------
        */

        const modal =
            document.getElementById('modalAgendamento');

        const abrirModal =
            document.getElementById('abrirModal');

        const fecharModal =
            document.getElementById('fecharModal');

        const cancelarModal =
            document.getElementById('cancelarModal');


        function fecharModalAgendamento() {

            modal.classList.remove('show');

        }


        abrirModal.addEventListener(
            'click',
            function() {

                modal.classList.add('show');

            }
        );


        fecharModal.addEventListener(
            'click',
            fecharModalAgendamento
        );


        cancelarModal.addEventListener(
            'click',
            fecharModalAgendamento
        );


        modal.addEventListener(
            'click',
            function(event) {

                if (event.target === modal) {

                    fecharModalAgendamento();

                }

            }
        );


        document.addEventListener(
            'keydown',
            function(event) {

                if (event.key === 'Escape') {

                    fecharModalAgendamento();

                }

            }
        );


        /*
        |--------------------------------------------------------------------------
        | SALVAR AGENDAMENTO
        |--------------------------------------------------------------------------
        */

        document
            .getElementById('formAgendamento')
            .addEventListener(
                'submit',
                async function(e) {

                    e.preventDefault();


                    const form = this;

                    const formData =
                        new FormData(form);

                    const mensagem =
                        document.getElementById(
                            'mensagem'
                        );


                    mensagem.style.display =
                        'none';


                    try {

                        const response =
                            await fetch(
                                'salvar_agendamento.php', {
                                    method: 'POST',
                                    body: formData
                                }
                            );


                        const result =
                            await response.json();


                        if (result.success) {

                            mensagem.className =
                                'mensagem sucesso';

                            mensagem.textContent =
                                'Agendamento salvo com sucesso!';

                            mensagem.style.display =
                                'block';


                            const data =
                                formData.get('data');


                            setTimeout(
                                function() {

                                    window.location.href =
                                        '?data=' +
                                        encodeURIComponent(
                                            data
                                        );

                                },
                                700
                            );


                        } else {

                            mensagem.className =
                                'mensagem erro';

                            mensagem.textContent =
                                'Erro: ' +
                                (
                                    result.error ||
                                    'Não foi possível salvar.'
                                );

                            mensagem.style.display =
                                'block';

                        }


                    } catch (error) {

                        console.error(error);


                        mensagem.className =
                            'mensagem erro';

                        mensagem.textContent =
                            'Erro de conexão. Tente novamente.';

                        mensagem.style.display =
                            'block';

                    }

                }
            );
    </script>

</body>

</html>