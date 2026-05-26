<?php
require_once 'conexao/conexao.php';

if (!isset($_GET['prontuario_id']) || !is_numeric($_GET['prontuario_id'])) {
    die("Prontuário não especificado.");
}

$prontuario_id = (int)$_GET['prontuario_id'];

// Buscar nome do paciente para exibir
$stmt = $pdo->prepare("SELECT paciente FROM prontuarios WHERE id = ?");
$stmt->execute([$prontuario_id]);
$paciente = $stmt->fetchColumn();

if (!$paciente) {
    die("Prontuário não encontrado.");
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novo Procedimento - <?= htmlspecialchars($paciente) ?> | Dentech</title>
    <link rel="stylesheet" href="css/navbar.css">
    <link rel="stylesheet" href="css/add_procedimento.css">
    <link rel="icon" type="image/png" href="img/icon.PNG">
</head>

<body>
    <?php include 'navbar.php'; ?>

    <div class="container">
        <main>
            <h1>Novo Procedimento</h1>
            <p class="subtitle">Paciente: <?= htmlspecialchars($paciente) ?></p>

            <form id="procedimentoForm">
                <input type="hidden" id="prontuarioId" value="<?= $prontuario_id ?>">

                <div class="form-group">
                    <label for="titulo">Nome do procedimento</label>
                    <input type="text" id="titulo" placeholder="Ex: Clareamento dental, Restauração..." required>
                </div>

                <div class="form-group">
                    <label for="data_procedimento">Data do procedimento</label>
                    <input type="date" id="data_procedimento" value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="form-group">
                    <label for="descricao">Descrição do procedimento (opcional)</label>
                    <textarea id="descricao" placeholder="Detalhes clínicos, observações, etc..."></textarea>
                </div>

                <div class="form-group">
                    <label for="medicamentos">Medicamentos receitados (opcional)</label>
                    <textarea id="medicamentos" placeholder="Ex: Amoxicilina 500mg – 1 comprimido de 8/8h por 7 dias&#10;Paracetamol 750mg – 1 comprimido se dor"></textarea>
                </div>

                <div class="actions">
                    <button type="button" class="btn btn-cancel" onclick="window.location='visualizar_prontuario.php?id=<?= $prontuario_id ?>'">
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-save">Adicionar Procedimento</button>
                </div>
            </form>
        </main>
    </div>

    <script>
        document.getElementById('procedimentoForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData();
            formData.append('prontuario_id', document.getElementById('prontuarioId').value);
            formData.append('titulo', document.getElementById('titulo').value);
            formData.append('data_procedimento', document.getElementById('data_procedimento').value);
            formData.append('descricao', document.getElementById('descricao').value);
            formData.append('descricao', document.getElementById('descricao').value);
            formData.append('medicamentos', document.getElementById('medicamentos').value);

            try {
                const response = await fetch('salvar_procedimento.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    alert("Procedimento adicionado com sucesso!");
                    window.location = 'visualizar_prontuario.php?id=' + document.getElementById('prontuarioId').value;
                } else {
                    alert("Erro: " + result.error);
                }
            } catch (err) {
                alert("Erro de conexão. Tente novamente.");
                console.error(err);
            }
        });
    </script>
</body>

</html>