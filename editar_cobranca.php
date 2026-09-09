<?php

require_once 'config/auth.php';
require_once 'config/csrf.php';
require_once 'conexao/conexao.php';

exigirLogin();

$parcela_id = filter_input(INPUT_GET, 'parcela_id', FILTER_VALIDATE_INT);

if (!$parcela_id || $parcela_id <= 0) {
    header('Location: financeiro.php');
    exit;
}

function buscarParcela(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare("
        SELECT
            p.*,
            proc.titulo AS procedimento_titulo,
            pr_proc.paciente AS paciente,
            lf.forma_pagamento
        FROM parcelas p
        INNER JOIN procedimentos proc
            ON proc.id = p.procedimento_id
        LEFT JOIN prontuarios pr_proc
            ON pr_proc.id = proc.paciente_id
        LEFT JOIN lancamentos_financeiros lf
            ON lf.parcela_id = p.id
        WHERE p.id = ?
          AND p.procedimento_id IS NOT NULL
        LIMIT 1
    ");

    $stmt->execute([$id]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/*
|--------------------------------------------------------------------------
| Cobranças editáveis são exclusivamente de procedimentos.
| Parcelas antigas originadas de orçamento não fazem parte do fluxo
| financeiro atual e não podem ser editadas por esta página.
|--------------------------------------------------------------------------
*/
$cobranca = buscarParcela($pdo, $parcela_id);

if (!$cobranca) {
    $stmt = $pdo->prepare("
        SELECT id, procedimento_id, orcamento_id
        FROM parcelas
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$parcela_id]);
    $parcela_origem = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$parcela_origem) {
        http_response_code(404);
        exit('Cobrança não encontrada.');
    }

    if (empty($parcela_origem['procedimento_id'])) {
        http_response_code(403);
        exit('Esta parcela não pertence a uma cobrança de procedimento.');
    }

    http_response_code(404);
    exit('Cobrança não encontrada.');
}

$formas_pagamento = [
    'Dinheiro',
    'PIX',
    'Cartão de débito',
    'Cartão de crédito',
    'Boleto',
    'Transferência bancária',
    'Cheque',
    'Outro'
];

$erro = '';

$forma_pagamento_atual = trim((string)($cobranca['forma_pagamento'] ?? ''));

if ($forma_pagamento_atual === '') {
    $forma_pagamento_atual = 'Não informado';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        validar_csrf();

        if (empty($cobranca['procedimento_id'])) {
            throw new Exception(
                'Somente cobranças de procedimentos podem ser editadas.'
            );
        }

        $status_atual = strtolower(trim((string)$cobranca['status']));

        if ($status_atual === 'paga') {
            throw new Exception(
                'Não é permitido editar uma cobrança já paga.'
            );
        }

        $vencimento = trim((string)($_POST['vencimento'] ?? ''));
        $forma_pagamento = trim((string)($_POST['forma_pagamento'] ?? ''));

        $dt = DateTime::createFromFormat('Y-m-d', $vencimento);

        if (!$dt || $dt->format('Y-m-d') !== $vencimento) {
            throw new Exception('Data de vencimento inválida.');
        }

        if (!in_array($forma_pagamento, $formas_pagamento, true)) {
            throw new Exception('Selecione uma forma de pagamento válida.');
        }

        $status = $vencimento < date('Y-m-d')
            ? 'atrasada'
            : 'pendente';

        $pdo->beginTransaction();

        /* Bloqueia a parcela e confirma novamente a origem. */
        $stmt = $pdo->prepare("
            SELECT id, valor, vencimento, status, data_pagamento,
                   procedimento_id, orcamento_id, numero_parcela
            FROM parcelas
            WHERE id = ?
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute([$parcela_id]);
        $parcela_locked = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$parcela_locked) {
            throw new Exception('Cobrança não encontrada.');
        }

        if (empty($parcela_locked['procedimento_id'])) {
            throw new Exception(
                'Somente cobranças de procedimentos podem ser editadas.'
            );
        }

        if ($parcela_locked['status'] === 'paga') {
            throw new Exception(
                'Não é permitido editar uma cobrança já paga.'
            );
        }

        if (!empty($parcela_locked['data_pagamento'])) {
            throw new Exception(
                'Uma cobrança com data de pagamento registrada não pode ser editada.'
            );
        }

        $stmt = $pdo->prepare("
            UPDATE parcelas
            SET
                vencimento = ?,
                status = ?,
                data_pagamento = NULL
            WHERE id = ?
              AND status IN ('pendente', 'atrasada')
        ");
        $stmt->execute([$vencimento, $status, $parcela_id]);


        /* O lançamento vinculado representa a mesma cobrança. */
        $stmt = $pdo->prepare("
            SELECT id
            FROM lancamentos_financeiros
            WHERE parcela_id = ?
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute([$parcela_id]);
        $lancamento_id = $stmt->fetchColumn();

        $descricao = sprintf(
            'Procedimento #%d - %s - Parcela %d',
            (int)$parcela_locked['procedimento_id'],
            $cobranca['paciente'],
            (int)$parcela_locked['numero_parcela']
        );

        if ($lancamento_id) {
            $stmt = $pdo->prepare("
                UPDATE lancamentos_financeiros
                SET
                    tipo = 'receita',
                    categoria = 'Procedimento',
                    descricao = ?,
                    forma_pagamento = ?,
                    valor = ?,
                    parcelas = 1,
                    status = 'pendente',
                    data = ?,
                    data_pagamento = NULL,
                    orcamento_id = NULL,
                    procedimento_id = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $descricao,
                $forma_pagamento,
                $parcela_locked['valor'],
                $vencimento,
                (int)$parcela_locked['procedimento_id'],
                $lancamento_id
            ]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO lancamentos_financeiros (
                    tipo,
                    categoria,
                    descricao,
                    data,
                    forma_pagamento,
                    valor,
                    parcelas,
                    status,
                    data_pagamento,
                    orcamento_id,
                    parcela_id,
                    procedimento_id
                ) VALUES (
                    'receita',
                    'Procedimento',
                    ?,
                    ?,
                    ?,
                    ?,
                    1,
                    'pendente',
                    NULL,
                    NULL,
                    ?,
                    ?
                )
            ");
            $stmt->execute([
                $descricao,
                $vencimento,
                $forma_pagamento,
                $parcela_locked['valor'],
                $parcela_id,
                (int)$parcela_locked['procedimento_id']
            ]);
        }

        $pdo->commit();

        header(
            'Location: visualizar_cobranca.php?parcela_id=' .
                $parcela_id .
                '&sucesso=edicao'
        );
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $erro = $e->getMessage();

        $cobranca = buscarParcela($pdo, $parcela_id);

        if (!$cobranca) {
            http_response_code(404);
            exit('Cobrança não encontrada.');
        }

        $forma_pagamento_atual =
            trim((string)($cobranca['forma_pagamento'] ?? 'Não informado'));

        if ($forma_pagamento_atual === '') {
            $forma_pagamento_atual = 'Não informado';
        }
    }
}

function dataBR($data): string
{
    return !empty($data)
        ? date('d/m/Y', strtotime($data))
        : '—';
}

$status_atual = strtolower(trim((string)$cobranca['status']));

$status_texto = match ($status_atual) {
    'paga' => 'Paga',
    'atrasada' => 'Atrasada',
    default => 'Pendente'
};

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Editar cobrança | Dentech</title>

    <link rel="stylesheet" href="css/global.css">
    <link rel="stylesheet" href="css/variables.css">
    <link rel="stylesheet" href="css/layout.css">
    <link rel="stylesheet" href="css/navbar.css">
    <link rel="stylesheet" href="css/editar_cobranca.css">

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

</head>

<body>

    <?php include 'navbar.php'; ?>

    <main class="content">

        <div class="edit-page">

            <p>
                <a
                    href="visualizar_cobranca.php?parcela_id=<?= (int)$parcela_id ?>">
                    ← Voltar para cobrança
                </a>
            </p>

            <div class="breadcrumb">
                Financeiro / Cobrança
            </div>

            <h1>Editar cobrança</h1>

            <p>
                Atualize os dados financeiros da cobrança.
                O status é calculado automaticamente pelo vencimento.
            </p>

            <?php if ($erro !== ''): ?>

                <div class="alert erro">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <?= htmlspecialchars($erro) ?>
                </div>

            <?php endif; ?>

            <section class="card-edit">

                <h2>Dados da cobrança</h2>

                <div class="info-grid">

                    <div class="info-box">
                        <label>Paciente</label>
                        <strong>
                            <?= htmlspecialchars($cobranca['paciente']) ?>
                        </strong>
                    </div>

                    <div class="info-box">
                        <label>Origem</label>
                        <strong>
                            Procedimento
                        </strong>
                    </div>

                    <div class="info-box">
                        <label>Procedimento</label>
                        <strong>
                            Procedimento #<?= (int)$cobranca['procedimento_id'] ?>

                            <?php if (!empty($cobranca['procedimento_titulo'])): ?>
                                —
                                <?= htmlspecialchars($cobranca['procedimento_titulo']) ?>
                            <?php endif; ?>
                        </strong>
                    </div>

                    <div class="info-box">
                        <label>Parcela</label>
                        <strong>
                            <?= (int)$cobranca['numero_parcela'] ?>
                        </strong>
                    </div>

                    <div class="info-box">
                        <label>Valor</label>
                        <strong>
                            R$
                            <?= number_format(
                                (float)$cobranca['valor'],
                                2,
                                ',',
                                '.'
                            ) ?>
                        </strong>
                    </div>

                    <div class="info-box">
                        <label>Status atual</label>
                        <strong>
                            <span class="status-box status-<?= htmlspecialchars($status_atual) ?>">
                                <?= htmlspecialchars($status_texto) ?>
                            </span>
                        </strong>
                    </div>

                </div>

                <h2>Informações editáveis</h2>

                <form method="POST">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars(
                                    $_SESSION['csrf_token'] ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>">

                    <div class="field">

                        <label for="vencimento">
                            Data de vencimento
                        </label>

                        <input
                            type="date"
                            id="vencimento"
                            name="vencimento"
                            value="<?= htmlspecialchars(
                                        $cobranca['vencimento']
                                    ) ?>"
                            required>

                        <small>
                            Datas anteriores a hoje tornam a cobrança
                            automaticamente <strong>atrasada</strong>.
                        </small>

                    </div>

                    <div class="field">

                        <label for="forma_pagamento">
                            Forma de pagamento
                        </label>

                        <select
                            id="forma_pagamento"
                            name="forma_pagamento"
                            required>

                            <option
                                value=""
                                <?= $forma_pagamento_atual === 'Não informado'
                                    ? 'selected'
                                    : '' ?>>
                                Selecione
                            </option>

                            <?php foreach ($formas_pagamento as $forma): ?>

                                <option
                                    value="<?= htmlspecialchars($forma) ?>"
                                    <?= $forma_pagamento_atual === $forma
                                        ? 'selected'
                                        : '' ?>>

                                    <?= htmlspecialchars($forma) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="acoes">

                        <a
                            class="btn"
                            href="visualizar_cobranca.php?parcela_id=<?= (int)$parcela_id ?>">

                            Cancelar

                        </a>

                        <button
                            class="btn btn-primary"
                            type="submit">

                            <i class="fa-solid fa-floppy-disk"></i>
                            Salvar alterações

                        </button>

                    </div>

                </form>

            </section>

        </div>

    </main>

</body>

</html>