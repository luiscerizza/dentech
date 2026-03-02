<?php
require_once 'conexao/conexao.php'; // Corrigido caminho

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Prontuário não encontrado.");
}

$id = (int)$_GET['id'];

// Verificar modo impressão
$isPrint = isset($_GET['print']) && $_GET['print'] == '1';

// Buscar prontuário
$stmt = $pdo->prepare("SELECT * FROM prontuarios WHERE id = ?");
$stmt->execute([$id]);
$prontuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$prontuario) {
    die("Prontuário não encontrado.");
}

// Calcular idade
$dataNasc = new DateTime($prontuario['nascimento']);
$hoje = new DateTime();
$idade = $hoje->diff($dataNasc)->y;

// Buscar procedimentos
$stmtProc = $pdo->prepare("SELECT * FROM procedimentos WHERE paciente_id = ? ORDER BY data_procedimento DESC");
$stmtProc->execute([$id]);
$procedimentos = $stmtProc->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prontuário - <?= htmlspecialchars($prontuario['paciente']) ?> | Dentech</title>
    <link rel="stylesheet" href="css/navbar.css">
    <link rel="stylesheet" href="css/vis_prontuario.css">
</head>

<body>
    <?php if (!$isPrint): ?>
        <?php include 'navbar.php'; ?>
    <?php endif; ?>

    <div class="container">
        <h1>Prontuário de <?= htmlspecialchars($prontuario['paciente']) ?></h1>

        <!-- Dados do paciente -->
        <div class="card">
            <p><strong>Nome:</strong> <?= htmlspecialchars($prontuario['paciente']) ?></p>
            <p><strong>Data de nascimento:</strong> <?= date('d/m/Y', strtotime($prontuario['nascimento'])) ?> (<?= $idade ?> anos)</p>
            <p><strong>Telefone:</strong> <?= htmlspecialchars($prontuario['telefone'] ?? '—') ?></p>
            <p><strong>Observações:</strong> <?= nl2br(htmlspecialchars($prontuario['observacoes'] ?? '')) ?></p>
        </div>

        <?php if (!$isPrint): ?>
            <!-- Botões de ação -->
            <div class="acoes" style="text-align: center; margin: 24px 0; display: flex; justify-content: center; gap: 12px; flex-wrap: wrap;">
                <button class="btn btn-imprimir"
                    onclick="window.open('termo_conscentimento.php?id=<?= $id ?>', '_blank')">
                    📄 Termo de Consentimento
                </button>
                <a href="adicionar_procedimento.php?prontuario_id=<?= $id ?>" class="btn btn-add">
                    + Adicionar Procedimento
                </a>
                <a href="editar_prontuario.php?id=<?= $id ?>" class="btn btn-editar">Editar Prontuário</a>
            </div>
        <?php endif; ?>

        <!-- Lista de procedimentos -->
        <h2>Procedimentos</h2>
        <?php if (empty($procedimentos)): ?>
            <p class="empty">Nenhum procedimento registrado.</p>
        <?php else: ?>
            <table class="tabela-procedimentos">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Procedimento</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($procedimentos as $proc): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($proc['data_procedimento'])) ?></td>
                            <td><?= htmlspecialchars($proc['titulo']) ?></td>
                            <td>
                                <button class="btn btn-visualizar"
                                    data-titulo="<?= htmlspecialchars($proc['titulo'], ENT_QUOTES) ?>"
                                    data-descricao="<?= htmlspecialchars($proc['descricao'] ?? '', ENT_QUOTES) ?>"
                                    data-medicamentos="<?= htmlspecialchars($proc['medicamentos'] ?? '', ENT_QUOTES) ?>"
                                    data-data="<?= date('d/m/Y', strtotime($proc['data_procedimento'])) ?>">
                                    Visualizar
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <?php if (!$isPrint): ?>
        <!-- Modal -->
        <div id="modal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title" id="modalTitle">Procedimento</h2>
                    <button class="btn-close" onclick="fecharModal()">&times;</button>
                </div>
                <div id="modalBody" class="modal-body"></div>
                <div class="modal-footer">
                    <button class="btn btn-fechar" onclick="fecharModal()">Fechar</button>
                </div>
            </div>
        </div>

        <script>
            // Adicionar evento a todos os botões "Visualizar"
            document.querySelectorAll('.btn-visualizar').forEach(button => {
                button.addEventListener('click', function() {
                    const titulo = this.dataset.titulo;
                    const descricao = this.dataset.descricao || '';
                    const medicamentos = this.dataset.medicamentos || '';
                    const data = this.dataset.data;

                    let html = `<p><strong>Data:</:</strong> ${data}</p>`;

                    if (descricao.trim()) {
                        html += `<p><strong>Descrição:</strong><br>${descricao.replace(/\n/g, '<br>')}</p>`;
                    }

                    if (medicamentos.trim()) {
                        html += `<p><strong>Medicamentos receitados:</strong><br>${medicamentos.replace(/\n/g, '<br>')}</p>`;
                    }

                    if (!descricao.trim() && !medicamentos.trim()) {
                        html += `<p>Nenhuma informação adicional.</p>`;
                    }

                    document.getElementById('modalTitle').textContent = titulo;
                    document.getElementById('modalBody').innerHTML = html;
                    document.getElementById('modal').style.display = 'flex';
                });
            });

            function fecharModal() {
                document.getElementById('modal').style.display = 'none';
            }

            window.onclick = function(event) {
                const modal = document.getElementById('modal');
                if (event.target === modal) {
                    fecharModal();
                }
            }
        </script>
    <?php endif; ?>
</body>

</html>