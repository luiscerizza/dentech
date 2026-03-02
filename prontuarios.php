<?php
require_once 'conexao/conexao.php';

// Buscar todos os prontuários
// No início do arquivo, substitua a query por:
$stmt = $pdo->query("
    SELECT 
        p.*,
        COALESCE(proc_count.total, 0) as total_procedimentos
    FROM prontuarios p
    LEFT JOIN (
        SELECT paciente_id, COUNT(*) as total
        FROM procedimentos
        GROUP BY paciente_id
    ) proc_count ON p.id = proc_count.paciente_id
    ORDER BY p.paciente ASC
");
$prontuarios = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prontuários - Dentech</title>
    <link rel="stylesheet" href="css/navbar.css">
    <link rel="stylesheet" href="css/prontuarios.css">
</head>

<body>
    <?php include 'navbar.php'; ?>

    <div class="container">
        <main>
            <h1>Prontuários</h1>

            <a href="editar_prontuario.php" class="add-btn">+ Adicionar Prontuário</a>

            <div class="cards">
                <?php if (empty($prontuarios)): ?>
                    <p class="empty">Nenhum prontuário cadastrado ainda.</p>
                <?php else: ?>
                    <?php foreach ($prontuarios as $p): ?>
                        <div class="card">
                            <h3><?= htmlspecialchars($p['paciente']) ?></h3>
                            <p><strong>Data de nascimento:</strong> <?= date('d/m/Y', strtotime($p['nascimento'])) ?></p>
                            <p><strong>Telefone:</strong> <?= htmlspecialchars($p['telefone'] ?? '—') ?></p>
                            <p><strong>Procedimentos:</strong> <?= nl2br(htmlspecialchars($p['procedimentos'] ?? '')) ?></p>
                            <p><strong>Observações:</strong> <?= nl2br(htmlspecialchars($p['observacoes'] ?? '')) ?></p>
                            <div class="actions">
                                <a href="visualizar_prontuario.php?id=<?= $p['id'] ?>" class="edit">Visualizar</a>
                                <a href="editar_prontuario.php?id=<?= $p['id'] ?>" class="edit">Editar</a>
                                <button class="delete" onclick="excluirProntuario(<?= $p['id'] ?>)">
                                    Excluir
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        function excluirProntuario(id) {
            if (!confirm('⚠️ ATENÇÃO:\nExcluir este prontuário também removerá todos os agendamentos, procedimentos e orçamentos vinculados a ele.\n\nDeseja continuar?')) {
                return;
            }

            fetch('excluir_prontuario.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'id=' + encodeURIComponent(id)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Prontuário excluído com sucesso!');
                        location.reload(); // Atualiza a página
                    } else {
                        alert('Erro: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    alert('Erro de conexão. Tente novamente.');
                });
        }
    </script>
</body>

</html>