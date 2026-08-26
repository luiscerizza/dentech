<?php
require_once 'config/auth.php';
require_once 'config/csrf.php';
require_once 'conexao/conexao.php';

exigirLogin();
header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido.');
    }

    validar_csrf();

    $planoItemId = (int)($_POST['plano_item_id'] ?? 0);
    $pacienteId = (int)($_POST['paciente_id'] ?? 0);
    $pacienteNome = trim($_POST['paciente_nome'] ?? '');
    $procedimento = trim($_POST['procedimento'] ?? '');
    $data = trim($_POST['data'] ?? '');
    $horario = trim($_POST['horario'] ?? '');

    if ($data === '' || $horario === '') {
        throw new Exception('Informe data e horário.');
    }

    $d = DateTime::createFromFormat('Y-m-d', $data);
    $t = DateTime::createFromFormat('H:i', $horario);

    if (!$d || $d->format('Y-m-d') !== $data) {
        throw new Exception('Data do agendamento inválida.');
    }

    if (!$t || $t->format('H:i') !== $horario) {
        throw new Exception('Horário do agendamento inválido.');
    }

    $item = null;

    if ($planoItemId > 0) {
        $stmt = $pdo->prepare("
            SELECT
                pti.id,
                pti.descricao,
                pti.status AS status_item,
                pt.id AS plano_id,
                pt.status AS status_plano,
                pt.paciente_id,
                p.paciente AS nome_paciente
            FROM planos_tratamento_itens pti
            INNER JOIN planos_tratamento pt ON pt.id = pti.plano_id
            INNER JOIN prontuarios p ON p.id = pt.paciente_id
            WHERE pti.id = ?
            LIMIT 1
        ");
        $stmt->execute([$planoItemId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            throw new Exception('Etapa do plano de tratamento não encontrada.');
        }

        if (in_array($item['status_item'], ['concluido', 'cancelado'], true)) {
            throw new Exception('Não é possível agendar uma etapa concluída ou cancelada.');
        }

        if ($pacienteId <= 0) {
            $pacienteId = (int)$item['paciente_id'];
        }

        if ($pacienteId !== (int)$item['paciente_id']) {
            throw new Exception('O paciente informado não pertence à etapa selecionada.');
        }

        if ($procedimento === '') {
            $procedimento = $item['descricao'];
        }

        if ($pacienteNome === '') {
            $pacienteNome = $item['nome_paciente'];
        }
    }

    if ($pacienteId <= 0 && $pacienteNome === '') {
        throw new Exception('Selecione um paciente ou informe um nome avulso.');
    }

    if ($procedimento === '') {
        throw new Exception('Informe o procedimento.');
    }

    if ($pacienteId > 0) {
        $stmt = $pdo->prepare("
            SELECT id, paciente
            FROM prontuarios
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$pacienteId]);
        $paciente = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$paciente) {
            throw new Exception('Paciente não encontrado.');
        }

        if ($pacienteNome === '') {
            $pacienteNome = $paciente['paciente'];
        }
    }

    if ($pacienteId > 0) {
        $stmt = $pdo->prepare("
            SELECT id
            FROM agendamentos
            WHERE paciente_id = ?
              AND data = ?
              AND horario = ?
              AND status <> 'cancelado'
            LIMIT 1
        ");
        $stmt->execute([$pacienteId, $data, $horario]);

        if ($stmt->fetchColumn()) {
            throw new Exception('Este paciente já possui um agendamento neste horário.');
        }
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO agendamentos (
            paciente_id,
            paciente_nome,
            procedimento,
            data,
            horario,
            status,
            plano_item_id
        )
        VALUES (?, ?, ?, ?, ?, 'agendado', ?)
    ");

    $stmt->execute([
        $pacienteId > 0 ? $pacienteId : null,
        $pacienteNome !== '' ? $pacienteNome : null,
        $procedimento,
        $data,
        $horario,
        $planoItemId > 0 ? $planoItemId : null
    ]);

    $agendamentoId = (int)$pdo->lastInsertId();

    if ($planoItemId > 0) {
        $stmt = $pdo->prepare("
            UPDATE planos_tratamento_itens
            SET status = CASE
                WHEN status = 'planejado' THEN 'em_andamento'
                ELSE status
            END
            WHERE id = ?
        ");
        $stmt->execute([$planoItemId]);

        $stmt = $pdo->prepare("
            UPDATE planos_tratamento
            SET status = CASE
                WHEN status = 'planejamento' THEN 'em_andamento'
                ELSE status
            END
            WHERE id = ?
        ");
        $stmt->execute([(int)$item['plano_id']]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'agendamento_id' => $agendamentoId,
        'plano_item_id' => $planoItemId > 0 ? $planoItemId : null
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(400);

    error_log('ERRO AO SALVAR AGENDAMENTO: ' . $e->getMessage());

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
