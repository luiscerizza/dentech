<?php
require_once 'config/auth.php';
require_once 'config/csrf.php';
exigirLogin();
require_once 'conexao/conexao.php';

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST' ||
    !isset($_POST['id']) ||
    !is_numeric($_POST['id'])
) {
    die("Agendamento inválido.");
}

validar_csrf();

$agendamento_id = (int)$_POST['id'];

// Buscar agendamento com dados do paciente
$stmt = $pdo->prepare("
    SELECT 
        a.id,
        a.paciente_id,
        a.procedimento,
        a.data,
        p.paciente AS nome_paciente
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
    die("Não é possível confirmar atendimento para agendamento avulso.");
}

try {
    $pdo->beginTransaction();

    // 1. Criar procedimento no prontuário
    $stmt = $pdo->prepare("
        INSERT INTO procedimentos (paciente_id, titulo, descricao, data_procedimento)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([
        $agendamento['paciente_id'],
        $agendamento['procedimento'],
        'Procedimento realizado a partir do agendamento do dia ' . $agendamento['data'],
        $agendamento['data'] // ou date('Y-m-d') para data de hoje
    ]);

    // 2. OPCIONAL: Excluir o agendamento após confirmação
    // (ou você pode adicionar uma coluna 'status' depois)
    $stmt = $pdo->prepare("DELETE FROM agendamentos WHERE id = ?");
    $stmt->execute([$agendamento_id]);

    $pdo->commit();

    // Mensagem de sucesso
    echo "<script>
        alert('Atendimento confirmado!\\nProcedimento registrado no prontuário de " . addslashes($agendamento['nome_paciente']) . ".');
        window.location.href = 'agendamentos.php?data=" . $agendamento['data'] . "';
    </script>";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "<script>
        alert('Erro ao confirmar atendimento: " . addslashes($e->getMessage()) . "');
        window.history.back();
    </script>";
}