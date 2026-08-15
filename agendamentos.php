<?php
// agendamentos.php
require_once 'config/auth.php';
exigirLogin();
require_once 'conexao/conexao.php'; // ajustado para o caminho correto

// Buscar prontuários
$stmt_pacientes = $pdo->query("SELECT id, paciente FROM prontuarios ORDER BY paciente");
$prontuarios = $stmt_pacientes->fetchAll();

// Data do filtro
$data_filtro = $_GET['data'] ?? date('Y-m-d');

// Buscar agendamentos do dia
$stmt_agendamentos = $pdo->prepare("
    SELECT 
        a.id,
        a.paciente_id,
        COALESCE(p.paciente, a.paciente_nome) AS nome_paciente,
        a.procedimento,
        a.data,
        a.horario
    FROM agendamentos a
    LEFT JOIN prontuarios p ON a.paciente_id = p.id
    WHERE a.data = ?
    ORDER BY a.horario
");
$stmt_agendamentos->execute([$data_filtro]);
$agendamentos = $stmt_agendamentos->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="timezone" content="America/Sao_Paulo">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agendamentos - Dentech</title>
    <link rel="stylesheet" href="css/navbar.css">
    <link rel="stylesheet" href="css/agendamento.css">
    <link rel="icon" type="image/png" href="img/icon.PNG">
</head>

<body>
    <?php include 'navbar.php'; ?>

    <div class="container" id="container">
        <h1>Agendamentos</h1>

        <!-- Mensagem de feedback -->
        <div id="mensagem" style="display: none;"></div>

        <!-- Formulário de novo agendamento -->
        <div class="form-agendamento">
            <form id="formAgendamento">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label for="paciente_id">Paciente cadastrado</label>
                        <select name="paciente_id" id="paciente_id">
                            <option value="">Selecione</option>
                            <?php foreach ($prontuarios as $p): ?>
                                <option value="<?= htmlspecialchars($p['id']) ?>">
                                    <?= htmlspecialchars($p['paciente']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="paciente_nome">Nome avulso (opcional)</label>
                        <input type="text" name="paciente_nome" id="paciente_nome" placeholder="Ex: Visitante">
                    </div>
                </div>

                <div class="form-group">
                    <label for="procedimento">Procedimento</label>
                    <input type="text" name="procedimento" required placeholder="Ex: Clareamento...">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="data">Data</label>
                        <input type="date" name="data" value="<?= htmlspecialchars($data_filtro) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="horario">Horário</label>
                        <input type="time" name="horario" required>
                    </div>
                </div>

                <button type="submit">Agendar</button>
            </form>
        </div>

        <!-- Filtro por data -->
        <div class="filtro-data">
            <form id="formFiltro" method="GET">
                <label for="data">Filtrar por </label>
                <input type="date" name="data" value="<?= htmlspecialchars($data_filtro) ?>" required>
                <button type="submit">Aplicar</button>
            </form>
            <a href="listar_mes.php?mes=<?= date('Y-m') ?>">Ver mês</a>
        </div>

        <!-- Lista de agendamentos -->
        <h2>Agendamentos em <?= date('d/m/Y', strtotime($data_filtro)) ?></h2>
        <div id="lista-agendamentos">
            <?php if (empty($agendamentos)): ?>
                <div class="empty">Nenhum agendamento neste dia.</div>
            <?php else: ?>
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
                            <?php foreach ($agendamentos as $ag): ?>
                                <tr>
                                    <td><?= htmlspecialchars($ag['horario']) ?></td>
                                    <td><?= htmlspecialchars($ag['nome_paciente']) ?></td>
                                    <td><?= htmlspecialchars($ag['procedimento']) ?></td>
                                    <td class="acoes">
                                        <?php if (!empty($ag['paciente_id'])): ?>
                                            <form method="POST" action="registrar_atendimento.php" style="display:inline;"
                                                onsubmit="return confirm('Confirmar atendimento e registrar procedimento?')">

                                                <?= csrf_field() ?>

                                                <input type="hidden" name="id" value="<?= $ag['id'] ?>">
                                                <button type="submit" class="btn-confirmar">
                                                    ✅
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="avulso">(avulso)</span>
                                        <?php endif; ?>
                                        <form method="POST" action="excluir_agendamento.php" style="display:inline;"
                                            onsubmit="return confirm('Excluir este agendamento?')">

                                            <?= csrf_field() ?>

                                            <input type="hidden" name="id" value="<?= $ag['id'] ?>">
                                            <button type="submit" class="btn-excluir">
                                                ❌
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Salvar novo agendamento via AJAX
        document.getElementById('formAgendamento').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');
            try {
                const response = await fetch('salvar_agendamento.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                const msgDiv = document.getElementById('mensagem');

                if (result.success) {
                    msgDiv.className = 'mensagem sucesso';
                    msgDiv.textContent = 'Agendamento salvo com sucesso!';
                    msgDiv.style.display = 'block';
                    this.reset();

                    // Atualiza a lista após 1s
                    setTimeout(() => {
                        const data = formData.get('data') || '<?= date('Y-m-d') ?>';
                        window.location.search = 'data=' + encodeURIComponent(data);
                    }, 1000);
                } else {
                    msgDiv.className = 'mensagem erro';
                    msgDiv.textContent = 'Erro: ' + (result.error || 'Não foi possível salvar.');
                    msgDiv.style.display = 'block';
                    setTimeout(() => msgDiv.style.display = 'none', 4000);
                }
            } catch (err) {
                console.error(err);
                const msgDiv = document.getElementById('mensagem');
                msgDiv.className = 'mensagem erro';
                msgDiv.textContent = 'Erro de conexão. Tente novamente.';
                msgDiv.style.display = 'block';
                setTimeout(() => msgDiv.style.display = 'none', 4000);
            }
        });

        // Filtro de data
        document.getElementById('formFiltro').addEventListener('submit', function(e) {
            e.preventDefault();
            const data = this.querySelector('input[name="data"]').value;
            window.location.search = 'data=' + encodeURIComponent(data);
        });
    </script>
</body>

</html>