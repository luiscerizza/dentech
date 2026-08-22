<?php

require_once 'config/auth.php';
require_once 'config/csrf.php';

exigirLogin();

require_once 'conexao/conexao.php';

header('Content-Type: application/json; charset=utf-8');

/*
|--------------------------------------------------------------------------
| Somente POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'error' => 'Método não permitido.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

try {
    validar_csrf();
} catch (Throwable $e) {
    http_response_code(403);

    echo json_encode([
        'success' => false,
        'error' => 'Token de segurança inválido.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Receber dados
|--------------------------------------------------------------------------
*/

try {

    $dadosJson = $_POST['dados'] ?? '';

    if ($dadosJson === '') {
        throw new Exception('Dados do procedimento não foram enviados.');
    }

    $dados = json_decode($dadosJson, true);

    if (!is_array($dados)) {
        throw new Exception('Dados do procedimento inválidos.');
    }

    /*
    |--------------------------------------------------------------------------
    | Dados básicos
    |--------------------------------------------------------------------------
    */

    $prontuario_id = (int)($dados['prontuario_id'] ?? 0);

    $agendamento_id = null;

    if (
        isset($dados['agendamento_id']) &&
        $dados['agendamento_id'] !== null &&
        $dados['agendamento_id'] !== ''
    ) {
        $agendamento_id = (int)$dados['agendamento_id'];
    }

    $procedimento_id = null;

    if (
        isset($dados['procedimento_id']) &&
        $dados['procedimento_id'] !== null &&
        $dados['procedimento_id'] !== ''
    ) {
        $procedimento_id = (int)$dados['procedimento_id'];
    }

    $titulo = trim($dados['titulo'] ?? '');

    $data_procedimento = trim(
        $dados['data_procedimento'] ?? ''
    );

    $descricao = trim(
        $dados['descricao'] ?? ''
    );

    $medicamentos = trim(
        $dados['medicamentos'] ?? ''
    );

    $valor_mao_obra = (float)(
        $dados['valor_mao_obra'] ?? 0
    );

    $valor_final_informado = (float)(
        $dados['valor_final'] ?? 0
    );

    $materiaisRecebidos = $dados['materiais'] ?? [];

    /*
    |--------------------------------------------------------------------------
    | Validações
    |--------------------------------------------------------------------------
    */

    if ($prontuario_id <= 0) {
        throw new Exception(
            'Prontuário inválido.'
        );
    }

    if ($titulo === '') {
        throw new Exception(
            'Informe o nome do procedimento.'
        );
    }

    if ($data_procedimento === '') {
        throw new Exception(
            'Informe a data do procedimento.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Validar data
    |--------------------------------------------------------------------------
    */

    $dataValida = DateTime::createFromFormat(
        'Y-m-d',
        $data_procedimento
    );

    if (
        !$dataValida ||
        $dataValida->format('Y-m-d') !== $data_procedimento
    ) {
        throw new Exception(
            'Data do procedimento inválida.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Validar mão de obra
    |--------------------------------------------------------------------------
    */

    if ($valor_mao_obra < 0) {
        throw new Exception(
            'O valor da mão de obra não pode ser negativo.'
        );
    }

    if ($valor_final_informado < 0) {
        throw new Exception(
            'O valor final do procedimento não pode ser negativo.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Validar materiais recebidos
    |--------------------------------------------------------------------------
    */

    if (!is_array($materiaisRecebidos)) {
        throw new Exception(
            'Lista de materiais inválida.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Normalizar materiais
    |--------------------------------------------------------------------------
    |
    | Se por algum motivo o mesmo material aparecer duas vezes,
    | somamos as quantidades no backend.
    |
    */

    $materiais = [];

    foreach ($materiaisRecebidos as $material) {

        if (!is_array($material)) {
            continue;
        }

        $estoque_id = (int)(
            $material['estoque_id'] ?? 0
        );

        $quantidade = (float)(
            $material['quantidade'] ?? 0
        );

        $subtotal_informado = array_key_exists('subtotal', $material)
            ? (float)$material['subtotal']
            : null;

        if ($estoque_id <= 0) {
            throw new Exception(
                'Material inválido.'
            );
        }

        if ($quantidade <= 0) {
            throw new Exception(
                'A quantidade utilizada deve ser maior que zero.'
            );
        }

        if ($subtotal_informado !== null && $subtotal_informado < 0) {
            throw new Exception(
                'O subtotal do material não pode ser negativo.'
            );
        }

        if (isset($materiais[$estoque_id])) {
            $materiais[$estoque_id]['quantidade'] += $quantidade;

            if ($subtotal_informado !== null) {
                $materiais[$estoque_id]['subtotal_informado'] =
                    ($materiais[$estoque_id]['subtotal_informado'] ?? 0)
                    + $subtotal_informado;
            }
        } else {
            $materiais[$estoque_id] = [
                'estoque_id' => $estoque_id,
                'quantidade' => $quantidade,
                'subtotal_informado' => $subtotal_informado
            ];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Iniciar transação
    |--------------------------------------------------------------------------
    */

    $pdo->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | 1. Verificar prontuário
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT id, paciente
        FROM prontuarios
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $prontuario_id
    ]);

    $prontuario = $stmt->fetch(
        PDO::FETCH_ASSOC
    );

    if (!$prontuario) {
        throw new Exception(
            'Prontuário não encontrado.'
        );
    }

    $orcamento_id = null;
    $procedimento_antigo = null;

    if ($procedimento_id !== null) {
        if ($procedimento_id <= 0) {
            throw new Exception('Procedimento inválido.');
        }

        $stmt = $pdo->prepare("
            SELECT
                id,
                paciente_id,
                orcamento_id,
                titulo,
                descricao,
                medicamentos,
                valor_mao_obra,
                valor_final,
                data_procedimento
            FROM procedimentos
            WHERE id = ?
              AND paciente_id = ?
            LIMIT 1
            FOR UPDATE
        ");

        $stmt->execute([
            $procedimento_id,
            $prontuario_id
        ]);

        $procedimento_antigo = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$procedimento_antigo) {
            throw new Exception('Procedimento não encontrado.');
        }

        $orcamento_id = !empty($procedimento_antigo['orcamento_id'])
            ? (int)$procedimento_antigo['orcamento_id']
            : null;

        if (!$orcamento_id) {
            throw new Exception(
                'O procedimento não possui um orçamento vinculado.'
            );
        }

        $stmtOrcamento = $pdo->prepare("
            SELECT id
            FROM orcamentos
            WHERE id = ?
              AND paciente_id = ?
              AND status = 'aceito'
            LIMIT 1
            FOR UPDATE
        ");

        $stmtOrcamento->execute([
            $orcamento_id,
            $prontuario_id
        ]);

        if (!$stmtOrcamento->fetchColumn()) {
            throw new Exception(
                'O orçamento vinculado ao procedimento não está aceito.'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 2. Verificar agendamento
    |--------------------------------------------------------------------------
    |
    | Se o procedimento veio de um agendamento,
    | ele obrigatoriamente precisa estar confirmado.
    |
    */

    if ($agendamento_id !== null) {

        $stmt = $pdo->prepare("
            SELECT
                id,
                paciente_id,
                status
            FROM agendamentos
            WHERE id = ?
            LIMIT 1
            FOR UPDATE
        ");

        $stmt->execute([
            $agendamento_id
        ]);

        $agendamento = $stmt->fetch(
            PDO::FETCH_ASSOC
        );

        if (!$agendamento) {
            throw new Exception(
                'Agendamento não encontrado.'
            );
        }

        if (
            (int)$agendamento['paciente_id']
            !== $prontuario_id
        ) {
            throw new Exception(
                'O agendamento não pertence a este paciente.'
            );
        }

        if (
            $agendamento['status']
            !== 'confirmado'
        ) {
            throw new Exception(
                'O agendamento precisa estar confirmado antes de registrar o procedimento.'
            );
        }

        if ($procedimento_id === null) {
            $stmtOrcamento = $pdo->prepare("
                SELECT id
                FROM orcamentos
                WHERE agendamento_id = ?
                  AND paciente_id = ?
                  AND status = 'aceito'
                ORDER BY id DESC
                LIMIT 1
                FOR UPDATE
            ");

            $stmtOrcamento->execute([
                $agendamento_id,
                $prontuario_id
            ]);

            $orcamento_id = $stmtOrcamento->fetchColumn();

            if (!$orcamento_id) {
                throw new Exception(
                    'Este agendamento não possui um orçamento aceito.'
                );
            }
        }
    }

    if ($procedimento_id !== null && $orcamento_id === null) {
        throw new Exception(
            'O procedimento não possui um orçamento vinculado.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 3. Verificar estoque e calcular valores
    |--------------------------------------------------------------------------
    */

    $materiaisProcessados = [];

    $valor_materiais = 0;

    $valor_sugerido = 0;

    if ($procedimento_id !== null) {
        $stmt = $pdo->prepare("
            SELECT estoque_id, quantidade
            FROM procedimento_materiais
            WHERE procedimento_id = ?
            FOR UPDATE
        ");

        $stmt->execute([$procedimento_id]);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $materialAntigo) {
            $pdo->prepare("
                UPDATE estoque
                SET quantidade = quantidade + ?
                WHERE id = ?
            ")->execute([
                (float)$materialAntigo['quantidade'],
                (int)$materialAntigo['estoque_id']
            ]);
        }

        $pdo->prepare("
            DELETE FROM procedimento_materiais
            WHERE procedimento_id = ?
        ")->execute([$procedimento_id]);
    }

    foreach ($materiais as $material) {

        $estoque_id = $material['estoque_id'];

        $quantidadeUsada =
            (float)$material['quantidade'];

        /*
        | Buscar o estoque com bloqueio da linha
        */

        $stmt = $pdo->prepare("
            SELECT
                id,
                nome,
                quantidade,
                unidade,
                valor_item,
                valor_sugerido
            FROM estoque
            WHERE id = ?
            LIMIT 1
            FOR UPDATE
        ");

        $stmt->execute([
            $estoque_id
        ]);

        $estoque = $stmt->fetch(
            PDO::FETCH_ASSOC
        );

        if (!$estoque) {
            throw new Exception(
                'Material não encontrado no estoque.'
            );
        }

        $quantidadeDisponivel =
            (float)$estoque['quantidade'];

        /*
        | Impedir estoque negativo
        */

        if (
            $quantidadeUsada >
            $quantidadeDisponivel
        ) {
            throw new Exception(
                'Estoque insuficiente para "' .
                    $estoque['nome'] .
                    '". Disponível: ' .
                    number_format(
                        $quantidadeDisponivel,
                        2,
                        ',',
                        '.'
                    ) .
                    ' ' .
                    $estoque['unidade'] .
                    '. Solicitado: ' .
                    number_format(
                        $quantidadeUsada,
                        2,
                        ',',
                        '.'
                    ) .
                    '.'
            );
        }

        $valorUnitario =
            (float)$estoque['valor_item'];

        $valorSugeridoUnitario =
            (float)$estoque['valor_sugerido'];

        $subtotalInformado = $material['subtotal_informado'] ?? null;

        $valorTotal = $subtotalInformado === null
            ? ($valorUnitario * $quantidadeUsada)
            : max(0, (float)$subtotalInformado);

        $valorSugeridoTotal =
            $valorSugeridoUnitario *
            $quantidadeUsada;

        $valor_materiais +=
            $valorTotal;

        $valor_sugerido +=
            $valorSugeridoTotal;

        $materiaisProcessados[] = [
            'estoque_id' => $estoque_id,
            'nome' => $estoque['nome'],
            'quantidade' => $quantidadeUsada,
            'valor_unitario' => $valorUnitario,
            'valor_total' => $valorTotal
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | 4. Calcular valor final
    |--------------------------------------------------------------------------
    */

    $valor_materiais =
        round($valor_materiais, 2);

    $valor_sugerido =
        round($valor_sugerido, 2);

    $valor_mao_obra =
        round($valor_mao_obra, 2);

    $valor_final =
        round($valor_final_informado, 2);

    /*
    |--------------------------------------------------------------------------
    | 5. Criar ou atualizar procedimento
    |--------------------------------------------------------------------------
    */

    if ($procedimento_id !== null) {

        $stmt = $pdo->prepare("
            UPDATE procedimentos
            SET titulo = ?,
                descricao = ?,
                medicamentos = ?,
                valor_materiais = ?,
                valor_mao_obra = ?,
                valor_final = ?,
                data_procedimento = ?,
                orcamento_id = ?
            WHERE id = ?
              AND paciente_id = ?
        ");

        $stmt->execute([
            $titulo,
            $descricao !== '' ? $descricao : null,
            $medicamentos !== '' ? $medicamentos : null,
            $valor_materiais,
            $valor_mao_obra,
            $valor_final,
            $data_procedimento,
            $orcamento_id,
            $procedimento_id,
            $prontuario_id
        ]);
    } else {

        $stmt = $pdo->prepare("
            INSERT INTO procedimentos (
                paciente_id,
                orcamento_id,
                titulo,
                descricao,
                medicamentos,
                valor_materiais,
                valor_mao_obra,
                valor_final,
                data_procedimento
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $prontuario_id,
            $orcamento_id,
            $titulo,
            $descricao !== '' ? $descricao : null,
            $medicamentos !== '' ? $medicamentos : null,
            $valor_materiais,
            $valor_mao_obra,
            $valor_final,
            $data_procedimento
        ]);

        $procedimento_id =
            (int)$pdo->lastInsertId();
    }

    /*
    |--------------------------------------------------------------------------
    | 6. Registrar materiais utilizados
    |--------------------------------------------------------------------------
    */

    $stmtMaterial = $pdo->prepare("
        INSERT INTO procedimento_materiais (
            procedimento_id,
            estoque_id,
            quantidade,
            valor_unitario,
            valor_total
        )
        VALUES (?, ?, ?, ?, ?)
    ");

    foreach ($materiaisProcessados as $material) {

        $stmtMaterial->execute([
            $procedimento_id,
            $material['estoque_id'],
            $material['quantidade'],
            $material['valor_unitario'],
            $material['valor_total']
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | 7. Baixar estoque
    |--------------------------------------------------------------------------
    */

    $stmtEstoque = $pdo->prepare("
        UPDATE estoque
        SET quantidade = quantidade - ?
        WHERE id = ?
          AND quantidade >= ?
    ");

    foreach ($materiaisProcessados as $material) {

        $quantidade =
            $material['quantidade'];

        $estoque_id =
            $material['estoque_id'];

        $stmtEstoque->execute([
            $quantidade,
            $estoque_id,
            $quantidade
        ]);

        if ($stmtEstoque->rowCount() !== 1) {

            throw new Exception(
                'Não foi possível atualizar o estoque do material "' .
                    $material['nome'] .
                    '".'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 8. Finalizar transação
    |--------------------------------------------------------------------------
    */

    $pdo->commit();

    /*
    |--------------------------------------------------------------------------
    | Resposta
    |--------------------------------------------------------------------------
    */

    echo json_encode([
        'success' => true,
        'procedimento_id' => $procedimento_id,
        'modo' => $procedimento_antigo ? 'edicao' : 'criacao',
        'orcamento_id' => $orcamento_id,
        'valor_materiais' => $valor_materiais,
        'valor_sugerido' => $valor_sugerido,
        'valor_mao_obra' => $valor_mao_obra,
        'valor_final' => $valor_final
    ]);

    exit;
} catch (Throwable $e) {

    /*
    |--------------------------------------------------------------------------
    | Rollback
    |--------------------------------------------------------------------------
    */

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);

    exit;
}
