<?php

require_once 'config/auth.php';
require_once 'config/csrf.php';

exigirLogin();

require_once 'conexao/conexao.php';

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

/*
|--------------------------------------------------------------------------
| Identificar agendamento
|--------------------------------------------------------------------------
|
| GET:
|   A página é aberta pela agenda.
|
| POST:
|   O formulário desta página é enviado para confirmar o atendimento.
|
*/

$agendamento_id = filter_input(
    $metodo === 'POST' ? INPUT_POST : INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$agendamento_id || $agendamento_id < 1) {
    die("Agendamento não especificado.");
}

if ($metodo === 'POST') {
    validar_csrf();
}

/*
|--------------------------------------------------------------------------
| Buscar agendamento
|--------------------------------------------------------------------------
*/

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
    LEFT JOIN prontuarios p
        ON a.paciente_id = p.id
    WHERE a.id = ?
");

$stmt->execute([$agendamento_id]);

$agendamento = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$agendamento) {
    die("Agendamento não encontrado.");
}

if (!$agendamento['paciente_id']) {
    die("Não é possível registrar atendimento sem paciente vinculado.");
}

/*
|--------------------------------------------------------------------------
| Evitar duplicação
|--------------------------------------------------------------------------
|
| Um agendamento concluído já possui atendimento registrado.
|
*/

if (($agendamento['status'] ?? '') === 'concluido') {
    header(
        "Location: agendamentos.php?data=" .
            urlencode($agendamento['data']) .
            "&msg=atendimento_ja_confirmado"
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Buscar materiais disponíveis
|--------------------------------------------------------------------------
|
| O valor_item será utilizado para registrar o custo do material no
| procedimento_materiais e calcular procedimentos.valor_materiais.
|
*/

$stmt = $pdo->query("
    SELECT
        id,
        nome,
        unidade,
        valor_item
    FROM estoque
    ORDER BY nome
");

$materiais = $stmt->fetchAll(PDO::FETCH_ASSOC);

$message = '';

/*
|--------------------------------------------------------------------------
| Confirmar atendimento
|--------------------------------------------------------------------------
*/

if ($metodo === 'POST') {

    try {

        $pdo->beginTransaction();

        /*
        |--------------------------------------------------------------------------
        | 1. Dados enviados pelo formulário
        |--------------------------------------------------------------------------
        */

        $descricao = trim($_POST['descricao'] ?? '');
        $medicamentos = trim($_POST['medicamentos'] ?? '');

        /*
        |--------------------------------------------------------------------------
        | 2. Criar procedimento
        |--------------------------------------------------------------------------
        |
        | O procedimento fica vinculado:
        | - ao paciente;
        | - ao agendamento;
        | - à etapa do plano, quando existir.
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

        if ($procedimento_id <= 0) {
            throw new Exception(
                "Não foi possível criar o procedimento."
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 3. Registrar materiais utilizados
        |--------------------------------------------------------------------------
        |
        | Para cada material:
        |
        | 1. trava o registro do estoque;
        | 2. verifica a quantidade disponível;
        | 3. obtém o valor unitário;
        | 4. desconta do estoque;
        | 5. cria o vínculo em procedimento_materiais;
        | 6. soma o custo dos materiais.
        |
        */

        $valor_materiais = 0.0;

        if (!empty($_POST['materiais']) && is_array($_POST['materiais'])) {

            $stmtEstoque = $pdo->prepare("
                SELECT
                    id,
                    nome,
                    quantidade,
                    unidade,
                    valor_item
                FROM estoque
                WHERE id = ?
                LIMIT 1
                FOR UPDATE
            ");

            $stmtAtualizarEstoque = $pdo->prepare("
                UPDATE estoque
                SET quantidade = quantidade - ?
                WHERE id = ?
            ");

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

            foreach ($_POST['materiais'] as $material_id => $qtd) {

                /*
                |--------------------------------------------------------------------------
                | Validar ID do material
                |--------------------------------------------------------------------------
                */

                $material_id = filter_var(
                    $material_id,
                    FILTER_VALIDATE_INT
                );

                if (!$material_id || $material_id < 1) {
                    throw new Exception(
                        "Material inválido."
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Validar quantidade
                |--------------------------------------------------------------------------
                */

                $qtd = (float)$qtd;

                if ($qtd <= 0) {
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | Buscar estoque com bloqueio
                |--------------------------------------------------------------------------
                */

                $stmtEstoque->execute([$material_id]);

                $estoque = $stmtEstoque->fetch(PDO::FETCH_ASSOC);

                if (!$estoque) {
                    throw new Exception(
                        "Material não encontrado no estoque."
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Verificar estoque disponível
                |--------------------------------------------------------------------------
                */

                $quantidade_disponivel = (float)$estoque['quantidade'];

                if ($quantidade_disponivel < $qtd) {
                    throw new Exception(
                        "Estoque insuficiente para o material: " .
                            $estoque['nome'] .
                            ". Disponível: " .
                            $quantidade_disponivel .
                            " " .
                            $estoque['unidade'] .
                            "."
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Calcular custo
                |--------------------------------------------------------------------------
                */

                $valor_unitario = (float)($estoque['valor_item'] ?? 0);

                $valor_total = $valor_unitario * $qtd;

                $valor_materiais += $valor_total;

                /*
                |--------------------------------------------------------------------------
                | Descontar estoque
                |--------------------------------------------------------------------------
                */

                $stmtAtualizarEstoque->execute([
                    $qtd,
                    $material_id
                ]);

                /*
                |--------------------------------------------------------------------------
                | Registrar material utilizado no procedimento
                |--------------------------------------------------------------------------
                */

                $stmtMaterial->execute([
                    $procedimento_id,
                    $material_id,
                    $qtd,
                    $valor_unitario,
                    $valor_total
                ]);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 4. Atualizar custo dos materiais no procedimento
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            UPDATE procedimentos
            SET valor_materiais = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $valor_materiais,
            $procedimento_id
        ]);

        /*
        |--------------------------------------------------------------------------
        | 5. Atualizar etapa do plano, quando houver
        |--------------------------------------------------------------------------
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
            |--------------------------------------------------------------------------
            | Verificar situação geral do plano
            |--------------------------------------------------------------------------
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
        | 6. Preservar o agendamento como histórico
        |--------------------------------------------------------------------------
        |
        | Não apagamos o agendamento.
        |
        | O procedimento possui agendamento_id e, portanto, precisamos
        | manter o registro para preservar o histórico do atendimento.
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

        /*
        |--------------------------------------------------------------------------
        | 7. Confirmar transação
        |--------------------------------------------------------------------------
        */

        $pdo->commit();

        /*
        |--------------------------------------------------------------------------
        | 8. Redirecionar
        |--------------------------------------------------------------------------
        */

        header(
            "Location: agendamentos.php?data=" .
                urlencode($agendamento['data']) .
                "&msg=atendimento_confirmado"
        );

        exit;
    } catch (Throwable $e) {

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

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Registrar Atendimento - Dentech</title>

    <link
        rel="stylesheet"
        href="css/global.css">

    <link
        rel="stylesheet"
        href="css/variables.css">

    <link
        rel="stylesheet"
        href="css/layout.css">

    <link
        rel="stylesheet"
        href="css/registrar_atendimento.css">

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <link
        rel="icon"
        type="image/png"
        href="img/icon.PNG">

</head>

<body>

    <?php include 'navbar.php'; ?>

    <div class="container">

        <a
            href="agendamentos.php?data=<?= urlencode($agendamento['data']) ?>"
            class="btn-back">
            ← Voltar
        </a>

        <h1>Registrar Atendimento</h1>

        <?php if ($message): ?>

            <div class="erro">
                <?= htmlspecialchars($message) ?>
            </div>

        <?php endif; ?>

        <div class="card">

            <div class="info-paciente">

                <p>
                    <strong>Paciente:</strong>
                    <?= htmlspecialchars($agendamento['nome_paciente']) ?>
                </p>

                <p>
                    <strong>Procedimento:</strong>
                    <?= htmlspecialchars($agendamento['procedimento']) ?>
                </p>

                <p>
                    <strong>Data:</strong>
                    <?= date(
                        'd/m/Y',
                        strtotime($agendamento['data'])
                    ) ?>
                </p>

            </div>

            <form
                method="POST"
                action="registrar_atendimento.php?id=<?= (int)$agendamento['id'] ?>"
                id="formAtendimento">

                <?= csrf_field() ?>

                <input
                    type="hidden"
                    name="id"
                    value="<?= (int)$agendamento['id'] ?>">

                <!-- =====================================================
                     DESCRIÇÃO
                ====================================================== -->

                <div class="form-group">

                    <label for="descricao">
                        Descrição do procedimento (opcional)
                    </label>

                    <textarea
                        name="descricao"
                        id="descricao"
                        placeholder="Ex: Procedimento realizado com sucesso. Paciente orientado quanto aos cuidados pós-operatórios."></textarea>

                </div>

                <!-- =====================================================
                     MEDICAMENTOS
                ====================================================== -->

                <div class="form-group">

                    <label for="medicamentos">
                        Medicamentos receitados (opcional)
                    </label>

                    <textarea
                        name="medicamentos"
                        id="medicamentos"
                        placeholder="Ex: Amoxicilina 500mg – 1 comprimido de 8/8h por 7 dias&#10;Paracetamol 750mg – 1 comprimido se dor"></textarea>

                </div>

                <!-- =====================================================
                     MATERIAIS
                ====================================================== -->

                <div class="secao-estoque">

                    <div class="aviso">
                        Selecione os materiais utilizados durante o atendimento.
                        Deixe em branco se nenhum foi usado.
                    </div>

                    <div class="form-group">

                        <label>
                            Materiais utilizados
                        </label>

                        <?php foreach ($materiais as $mat): ?>

                            <div class="material-item">

                                <select disabled>

                                    <option>
                                        <?= htmlspecialchars($mat['nome']) ?>
                                        (<?= htmlspecialchars($mat['unidade']) ?>)
                                    </option>

                                </select>

                                <input
                                    type="number"
                                    name="materiais[<?= (int)$mat['id'] ?>]"
                                    step="0.01"
                                    min="0"
                                    placeholder="0"
                                    style="width: 100px;">

                            </div>

                        <?php endforeach; ?>

                    </div>

                </div>

                <!-- =====================================================
                     CONFIRMAR
                ====================================================== -->

                <button
                    type="submit"
                    class="btn btn-save">
                    Confirmar Atendimento
                </button>

            </form>

        </div>

    </div>

</body>

</html>