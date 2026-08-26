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
    die("Agendamento não especificado.");
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
        a.status,
        a.plano_item_id,
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

// Buscar todos os materiais (para o select)
$stmt = $pdo->query("SELECT id, nome, unidade FROM estoque ORDER BY nome");
$materiais = $stmt->fetchAll();

$message = '';

if ($_POST) {
    try {
        $pdo->beginTransaction();

        // 1. Criar procedimento
        $descricao = trim($_POST['descricao'] ?? '');
        $medicamentos = trim($_POST['medicamentos'] ?? '');

        /*
        |--------------------------------------------------------------------------
        | 1. Criar procedimento
        |--------------------------------------------------------------------------
        |
        | Mantém o paciente e a data do atendimento e registra a origem:
        | - agendamento_id para o atendimento que gerou o procedimento;
        | - plano_item_id quando o agendamento veio de uma etapa do plano.
        |
        | Os dois campos são opcionais no banco, então atendimentos antigos
        | continuam compatíveis.
        |
        */

        $stmt = $pdo->prepare("
            INSERT INTO procedimentos (
                paciente_id,
                titulo,
                descricao,
                medicamentos,
                data_procedimento,
                plano_item_id,
                agendamento_id
            )
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            (int)$agendamento['paciente_id'],
            $agendamento['procedimento'],
            $descricao ?: null,
            $medicamentos ?: null,
            $agendamento['data'],
            !empty($agendamento['plano_item_id'])
                ? (int)$agendamento['plano_item_id']
                : null,
            $agendamento_id
        ]);

        $procedimento_id = (int)$pdo->lastInsertId();

        // 2. Atualizar estoque (se houver itens selecionados)
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

        /*
        |--------------------------------------------------------------------------
        | 3. Atualizar a etapa do plano, quando houver
        |--------------------------------------------------------------------------
        |
        | O atendimento concluído encerra a etapa clínica. Não criamos um
        | procedimento "do nada": ele já foi criado acima e está ligado
        | à etapa e ao agendamento.
        |
        */

        if (!empty($agendamento['plano_item_id'])) {

            $stmt = $pdo->prepare("
                UPDATE planos_tratamento_itens
                SET status = 'concluido'
                WHERE id = ?
            ");

            $stmt->execute([
                (int)$agendamento['plano_item_id']
            ]);

            /*
            | Atualizar o plano somente com base nas etapas existentes:
            | - concluído se todas as etapas estão concluídas/canceladas;
            | - em andamento se ainda houver etapas não finalizadas.
            */
            $stmt = $pdo->prepare("
                SELECT
                    pti.plano_id,
                    COUNT(*) AS total_etapas,
                    SUM(
                        CASE
                            WHEN pti.status IN ('concluido', 'cancelado')
                            THEN 1
                            ELSE 0
                        END
                    ) AS etapas_finalizadas
                FROM planos_tratamento_itens pti
                WHERE pti.plano_id = (
                    SELECT plano_id
                    FROM planos_tratamento_itens
                    WHERE id = ?
                    LIMIT 1
                )
                GROUP BY pti.plano_id
            ");

            $stmt->execute([
                (int)$agendamento['plano_item_id']
            ]);

            $resumoPlano = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($resumoPlano) {

                if (
                    (int)$resumoPlano['total_etapas'] > 0
                    &&
                    (int)$resumoPlano['etapas_finalizadas']
                    ===
                    (int)$resumoPlano['total_etapas']
                ) {
                    $novoStatusPlano = 'concluido';
                } else {
                    $novoStatusPlano = 'em_andamento';
                }

                $stmt = $pdo->prepare("
                    UPDATE planos_tratamento
                    SET status = ?
                    WHERE id = ?
                ");

                $stmt->execute([
                    $novoStatusPlano,
                    (int)$resumoPlano['plano_id']
                ]);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 4. Preservar o agendamento como histórico
        |--------------------------------------------------------------------------
        |
        | Não apagamos mais o registro. Como procedimentos.agendamento_id
        | aponta para este registro, excluí-lo perderia a referência histórica.
        | O agendamento passa a representar um atendimento realizado.
        |
        */

        $stmt = $pdo->prepare("
            UPDATE agendamentos
            SET status = 'concluido'
            WHERE id = ?
        ");

        $stmt->execute([
            $agendamento_id
        ]);

        $pdo->commit();

        header("Location: agendamentos.php?data=" . $agendamento['data'] . "&msg=atendimento_confirmado");
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

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

                <!-- Descrição do procedimento -->
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

                <!-- Materiais do estoque -->
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