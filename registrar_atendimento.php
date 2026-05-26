<?php
require_once 'conexao/conexao.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Agendamento não especificado.");
}

$agendamento_id = (int)$_GET['id'];

$stmt = $pdo->prepare("
    SELECT 
        a.id,
        a.paciente_id,
        a.procedimento,
        a.data,
        COALESCE(p.paciente, a.paciente_nome) AS nome_paciente
    FROM agendamentos a
    LEFT JOIN prontuarios p ON a.paciente_id = p.id
    WHERE a.id = ?
");
$stmt->execute([$agendamento_id]);
$agendamento = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$agendamento) {
    die("Agendamento não encontrado.");
}

if (!$agendamento['paciente_id']) {
    die("Não é possível registrar atendimento para agendamento avulso.");
}

$stmt = $pdo->query("SELECT id, nome, unidade FROM estoque ORDER BY nome");
$materiais = $stmt->fetchAll();

$message = '';

if ($_POST) {
    try {
        $pdo->beginTransaction();

        $descricao = trim($_POST['descricao'] ?? '');
        $medicamentos = trim($_POST['medicamentos'] ?? '');

        $stmt = $pdo->prepare("
            INSERT INTO procedimentos (paciente_id, titulo, descricao, medicamentos, data_procedimento)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $agendamento['paciente_id'],
            $agendamento['procedimento'],
            $descricao ?: null,
            $medicamentos ?: null, 
            $agendamento['data']
        ]);

        if (!empty($_POST['materiais'])) {
            foreach ($_POST['materiais'] as $material_id => $qtd) {
                $qtd = floatval($qtd);
                if ($qtd > 0) {
                    $stmt = $pdo->prepare("SELECT quantidade FROM estoque WHERE id = ?");
                    $stmt->execute([$material_id]);
                    $estoque_atual = $stmt->fetchColumn();

                    if ($estoque_atual === false) {
                        throw new Exception("Material não encontrado.");
                    }

                    if ($estoque_atual < $qtd) {
                        throw new Exception("Estoque insuficiente para o material: " . ($materiais[$material_id]['nome'] ?? 'ID ' . $material_id));
                    }

                    $stmt = $pdo->prepare("UPDATE estoque SET quantidade = quantidade - ? WHERE id = ?");
                    $stmt->execute([$qtd, $material_id]);
                }
            }
        }

        $stmt = $pdo->prepare("DELETE FROM agendamentos WHERE id = ?");
        $stmt->execute([$agendamento_id]);

        $pdo->commit();

        header("Location: agendamentos.php?data=" . $agendamento['data'] . "&msg=atendimento_confirmado");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Erro: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Atendimento - Dentech</title>
    <link rel="stylesheet" href="css/navbar.css">
    <link rel="stylesheet" href="css/re_atendimento.css">
    <link rel="icon" type="image/png" href="img/icon.PNG">
</head>

<body>
    <?php include 'navbar.php'; ?>

    <div class="container">
        <a href="agendamentos.php?data=<?= urlencode($agendamento['data']) ?>" class="btn-back">← Voltar</a>

        <h1>Registrar Atendimento</h1>

        <?php if ($message): ?>
            <div class="erro"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="info-paciente">
                <p><strong>Paciente:</strong> <?= htmlspecialchars($agendamento['nome_paciente']) ?></p>
                <p><strong>Procedimento:</strong> <?= htmlspecialchars($agendamento['procedimento']) ?></p>
                <p><strong>Data:</strong> <?= date('d/m/Y', strtotime($agendamento['data'])) ?></p>
            </div>

            <form method="POST" id="formAtendimento">

                <div class="form-group">
                    <label for="descricao">Descrição do procedimento (opcional)</label>
                    <textarea name="descricao" id="descricao"
                        placeholder="Ex: Procedimento realizado com sucesso. Paciente orientado quanto aos cuidados pós-operatórios."></textarea>
                </div>

                <!-- Medicamentos receitados -->
                <div class="form-group">
                    <label for="medicamentos">Medicamentos receitados (opcional)</label>
                    <textarea name="medicamentos" id="medicamentos"
                        placeholder="Ex: Amoxicilina 500mg – 1 comprimido de 8/8h por 7 dias&#10;Paracetamol 750mg – 1 comprimido se dor"></textarea>
                </div>

                <div class="secao-estoque">
                    <div class="aviso">
                        Selecione os materiais utilizados durante o atendimento. Deixe em branco se nenhum foi usado.
                    </div>

                    <div class="form-group">
                        <label>Materiais utilizados</label>
                        <?php foreach ($materiais as $mat): ?>
                            <div class="material-item">
                                <select disabled>
                                    <option><?= htmlspecialchars($mat['nome']) ?> (<?= htmlspecialchars($mat['unidade']) ?>)</option>
                                </select>
                                <input type="number"
                                    name="materiais[<?= $mat['id'] ?>]"
                                    step="0.01"
                                    min="0"
                                    placeholder="0"
                                    style="width: 100px;">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <button type="submit" class="btn btn-save">Confirmar Atendimento</button>
            </form>
        </div>
    </div>
</body>

</html>