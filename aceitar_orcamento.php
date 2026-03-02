<?php
require_once 'conexao/conexao.php';

$id = (int)($_GET['id'] ?? 0);

// Atualizar status
$stmt = $pdo->prepare("UPDATE orcamentos SET status = 'aceito' WHERE id = ?");
$stmt->execute([$id]);

// Criar agendamento para o primeiro procedimento (ou todos)
$stmt = $pdo->prepare("
    SELECT o.paciente_id, i.descricao
    FROM orcamentos o
    JOIN orcamentos_itens i ON o.id = i.orcamento_id
    WHERE o.id = ? LIMIT 1
");
$stmt->execute([$id]);
$dados = $stmt->fetch();

if ($dados) {
    // Agendar para amanhã (ou escolha a data)
    $data_agendamento = date('Y-m-d', strtotime('+1 day'));
    $stmt = $pdo->prepare("
        INSERT INTO agendamentos (paciente_id, procedimento, data, horario)
        VALUES (?, ?, ?, '09:00')
    ");
    $stmt->execute([$dados['paciente_id'], $dados['descricao'], $data_agendamento]);
}

header("Location: visualizar_orcamento.php?id=" . $id);
exit;
