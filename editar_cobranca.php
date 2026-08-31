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
            o.status AS status_orcamento,
            proc.titulo AS procedimento_titulo,

            COALESCE(
                pr_proc.paciente,
                pr_orc.paciente,
                'Paciente não encontrado'
            ) AS paciente,

            lf.forma_pagamento,
            lf.observacoes AS observacoes_financeiras

        FROM parcelas p

        LEFT JOIN orcamentos o
            ON o.id = p.orcamento_id

        LEFT JOIN procedimentos proc
            ON proc.id = p.procedimento_id

        LEFT JOIN prontuarios pr_orc
            ON pr_orc.id = o.paciente_id

        LEFT JOIN prontuarios pr_proc
            ON pr_proc.id = proc.paciente_id

        LEFT JOIN lancamentos_financeiros lf
            ON lf.parcela_id = p.id

        WHERE p.id = ?

        LIMIT 1
    ");

    $stmt->execute([$id]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$cobranca = buscarParcela($pdo, $parcela_id);

if (!$cobranca) {
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

$forma_pagamento_atual =
    trim((string)($cobranca['forma_pagamento'] ?? ''));

$observacoes_atual =
    (string)($cobranca['observacoes_financeiras'] ?? '');

if ($forma_pagamento_atual === '') {
    $forma_pagamento_atual = 'Não informado';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        validar_csrf();

        $status_atual = strtolower(
            trim((string)$cobranca['status'])
        );

        if ($status_atual === 'paga') {
            throw new Exception(
                'Não é permitido editar uma cobrança já paga.'
            );
        }

        $vencimento = trim(
            (string)($_POST['vencimento'] ?? '')
        );

        $forma_pagamento = trim(
            (string)($_POST['forma_pagamento'] ?? '')
        );

        $observacoes = trim(
            (string)($_POST['observacoes'] ?? '')
        );

        $dt = DateTime::createFromFormat(
            'Y-m-d',
            $vencimento
        );

        if (
            !$dt ||
            $dt->format('Y-m-d') !== $vencimento
        ) {
            throw new Exception(
                'Data de vencimento inválida.'
            );
        }

        if (!in_array(
            $forma_pagamento,
            $formas_pagamento,
            true
        )) {
            throw new Exception(
                'Selecione uma forma de pagamento válida.'
            );
        }

        if (mb_strlen($observacoes) > 2000) {
            throw new Exception(
                'As observações podem ter no máximo 2000 caracteres.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Status é automático.
        |--------------------------------------------------------------------------
        */
        $status = $vencimento < date('Y-m-d')
            ? 'atrasada'
            : 'pendente';

        $pdo->beginTransaction();

        /*
        |--------------------------------------------------------------------------
        | Atualizar parcela
        |--------------------------------------------------------------------------
        */
        $stmt = $pdo->prepare("
            UPDATE parcelas

            SET
                vencimento = ?,
                status = ?

            WHERE
                id = ?
                AND status IN ('pendente', 'atrasada')
        ");

        $stmt->execute([
            $vencimento,
            $status,
            $parcela_id
        ]);

        if ($stmt->rowCount() !== 1) {
            throw new Exception(
                'Não foi possível atualizar a cobrança.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Forma de pagamento e observações ficam no lançamento financeiro
        | vinculado à parcela.
        |
        | Para evitar duplicidade, usamos parcela_id, que é UNIQUE.
        |--------------------------------------------------------------------------
        */
        $stmt = $pdo->prepare("
            SELECT id
            FROM lancamentos_financeiros
            WHERE parcela_id = ?
            LIMIT 1
            FOR UPDATE
        ");

        $stmt->execute([$parcela_id]);

        $lancamento_id = $stmt->fetchColumn();

        $eh_procedimento = !empty($cobranca['procedimento_id']);

        if ($eh_procedimento) {

            $categoria = 'Procedimento';

            $descricao = sprintf(
                'Procedimento #%d - %s - Parcela %d',
                (int)$cobranca['procedimento_id'],
                $cobranca['paciente'],
                (int)$cobranca['numero_parcela']
            );
        } else {

            $categoria = 'Orçamento odontológico';

            $descricao = sprintf(
                'Orçamento #%d - %s - Parcela %d',
                (int)$cobranca['orcamento_id'],
                $cobranca['paciente'],
                (int)$cobranca['numero_parcela']
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Uma cobrança ainda não paga é mantida como receita pendente.
        | O pagamento real continua sendo feito por pagar_parcela.php.
        |--------------------------------------------------------------------------
        */
        if ($lancamento_id) {

            $stmt = $pdo->prepare("
                UPDATE lancamentos_financeiros

                SET
                    tipo = 'receita',
                    categoria = ?,
                    descricao = ?,
                    forma_pagamento = ?,
                    valor = ?,
                    parcelas = 1,
                    status = 'pendente',
                    observacoes = ?,
                    orcamento_id = ?,
                    procedimento_id = ?,
                    data = ?

                WHERE id = ?
            ");

            $stmt->execute([
                $categoria,
                $descricao,
                $forma_pagamento,
                $cobranca['valor'],
                $observacoes !== '' ? $observacoes : null,
                !empty($cobranca['orcamento_id'])
                    ? (int)$cobranca['orcamento_id']
                    : null,
                !empty($cobranca['procedimento_id'])
                    ? (int)$cobranca['procedimento_id']
                    : null,
                $vencimento,
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
                    observacoes,
                    orcamento_id,
                    parcela_id,
                    procedimento_id
                )

                VALUES (
                    'receita',
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    1,
                    'pendente',
                    ?,
                    ?,
                    ?,
                    ?
                )
            ");

            $stmt->execute([
                $categoria,
                $descricao,
                $vencimento,
                $forma_pagamento,
                $cobranca['valor'],
                $observacoes !== '' ? $observacoes : null,
                !empty($cobranca['orcamento_id'])
                    ? (int)$cobranca['orcamento_id']
                    : null,
                $parcela_id,
                !empty($cobranca['procedimento_id'])
                    ? (int)$cobranca['procedimento_id']
                    : null
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

        $cobranca = buscarParcela(
            $pdo,
            $parcela_id
        );

        $forma_pagamento_atual =
            trim((string)($cobranca['forma_pagamento'] ?? 'Não informado'));

        $observacoes_atual =
            (string)($cobranca['observacoes_financeiras'] ?? '');
    }
}

function dataBR($data): string
{
    return !empty($data)
        ? date('d/m/Y', strtotime($data))
        : '—';
}

$status_atual = strtolower(
    trim((string)$cobranca['status'])
);

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

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        .edit-page {
            max-width: 860px;
            margin: 0 auto;
            padding-bottom: 40px;
        }

        .breadcrumb {
            color: #2563eb;
            font-weight: 600;
            margin-bottom: 6px;
        }

        .edit-page h1 {
            margin-bottom: 6px;
        }

        .edit-page>p {
            color: #64748b;
        }

        .card-edit {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 26px;
            box-shadow: 0 4px 16px rgba(15, 23, 42, .05);
            margin-top: 20px;
        }

        .card-edit h2 {
            margin: 0 0 18px;
            font-size: 18px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 26px;
        }

        .info-box {
            background: #f8fafc;
            padding: 14px;
            border-radius: 8px;
            color: #475569;
        }

        .info-box label {
            display: block;
            color: #64748b;
            font-size: 12px;
            margin-bottom: 4px;
        }

        .info-box strong {
            color: #172033;
        }

        .field {
            margin: 20px 0;
        }

        .field label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #172033;
        }

        .field input,
        .field select,
        .field textarea {
            width: 100%;
            box-sizing: border-box;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font: inherit;
            background: #fff;
        }

        .field textarea {
            min-height: 110px;
            resize: vertical;
        }

        .field input:focus,
        .field select:focus,
        .field textarea:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .10);
        }

        .field small {
            display: block;
            margin-top: 6px;
            color: #64748b;
        }

        .status-box {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 11px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 700;
        }

        .status-pendente {
            background: #fef3c7;
            color: #92400e;
        }

        .status-atrasada {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-paga {
            background: #dcfce7;
            color: #166534;
        }

        .alert {
            padding: 12px 14px;
            border-radius: 8px;
            margin-bottom: 16px;
        }

        .erro {
            background: #fee2e2;
            color: #991b1b;
        }

        .acoes {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 26px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 15px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            text-decoration: none;
            background: #fff;
            color: #172033;
            cursor: pointer;
            font: inherit;
        }

        .btn-primary {
            background: #2563eb;
            color: #fff;
            border-color: #2563eb;
        }

        @media (max-width: 700px) {

            .info-grid {
                grid-template-columns: 1fr;
            }

            .acoes {
                flex-direction: column;
            }

            .acoes .btn {
                width: 100%;
            }

        }
    </style>

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
                            <?= !empty($cobranca['procedimento_id'])
                                ? 'Procedimento'
                                : 'Orçamento' ?>
                        </strong>
                    </div>

                    <div class="info-box">
                        <label>Procedimento / Orçamento</label>
                        <strong>

                            <?php if (!empty($cobranca['procedimento_id'])): ?>

                                Procedimento #<?= (int)$cobranca['procedimento_id'] ?>

                                <?php if (!empty($cobranca['procedimento_titulo'])): ?>
                                    —
                                    <?= htmlspecialchars($cobranca['procedimento_titulo']) ?>
                                <?php endif; ?>

                            <?php else: ?>

                                Orçamento #<?= (int)$cobranca['orcamento_id'] ?>

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

                    <div class="field">

                        <label for="observacoes">
                            Observações
                        </label>

                        <textarea
                            id="observacoes"
                            name="observacoes"
                            maxlength="2000"
                            placeholder="Adicione uma observação sobre esta cobrança..."><?= htmlspecialchars(
                                                                                                $observacoes_atual
                                                                                            ) ?></textarea>

                        <small>
                            Máximo de 2000 caracteres.
                        </small>

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