<?php

require_once 'config/auth.php';
require_once 'config/csrf.php';
require_once 'conexao/conexao.php';

exigirLogin();

/*
|--------------------------------------------------------------------------
| Formas de pagamento permitidas
|--------------------------------------------------------------------------
*/
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

/*
|--------------------------------------------------------------------------
| Identificação da parcela
|--------------------------------------------------------------------------
*/
$parcela_id = filter_input(
    INPUT_GET,
    'parcela_id',
    FILTER_VALIDATE_INT
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $parcela_id = filter_input(
        INPUT_POST,
        'parcela_id',
        FILTER_VALIDATE_INT
    );
}

if (!$parcela_id || $parcela_id <= 0) {
    header('Location: financeiro.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Buscar dados da parcela
|--------------------------------------------------------------------------
*/
function buscarParcela(PDO $pdo, int $parcela_id): ?array
{
    $stmt = $pdo->prepare("
        SELECT
            p.id,
            p.numero_parcela,
            p.valor,
            p.vencimento,
            p.status,
            p.data_pagamento,
            p.orcamento_id,
            p.procedimento_id,

            o.status AS status_orcamento,

            proc.titulo AS procedimento_titulo,

            COALESCE(
                pr_proc.paciente,
                pr_orc.paciente,
                'Paciente não encontrado'
            ) AS paciente

        FROM parcelas p

        LEFT JOIN orcamentos o
            ON o.id = p.orcamento_id

        LEFT JOIN procedimentos proc
            ON proc.id = p.procedimento_id

        LEFT JOIN prontuarios pr_orc
            ON pr_orc.id = o.paciente_id

        LEFT JOIN prontuarios pr_proc
            ON pr_proc.id = proc.paciente_id

        WHERE p.id = ?

        LIMIT 1
    ");

    $stmt->execute([$parcela_id]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$parcela = buscarParcela($pdo, $parcela_id);

if (!$parcela) {
    http_response_code(404);
    exit('Parcela não encontrada.');
}

/*
|--------------------------------------------------------------------------
| Processar pagamento
|--------------------------------------------------------------------------
*/
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        validar_csrf();

        $forma_pagamento = trim(
            (string)($_POST['forma_pagamento'] ?? '')
        );

        if (!in_array(
            $forma_pagamento,
            $formas_pagamento,
            true
        )) {
            throw new Exception(
                'Selecione uma forma de pagamento válida.'
            );
        }

        $pdo->beginTransaction();

        /*
        |--------------------------------------------------------------------------
        | Bloquear a parcela durante o pagamento
        |--------------------------------------------------------------------------
        */
        $stmt = $pdo->prepare("
            SELECT
                p.id,
                p.numero_parcela,
                p.valor,
                p.vencimento,
                p.status,
                p.data_pagamento,
                p.orcamento_id,
                p.procedimento_id,
                o.status AS status_orcamento
            FROM parcelas p
            LEFT JOIN orcamentos o
                ON o.id = p.orcamento_id
            WHERE p.id = ?
            LIMIT 1
            FOR UPDATE
        ");

        $stmt->execute([$parcela_id]);

        $parcela_locked = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$parcela_locked) {
            throw new Exception(
                'Parcela não encontrada.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Validar origem financeira
        |--------------------------------------------------------------------------
        |
        | O orçamento é apenas uma proposta comercial.
        | O pagamento financeiro somente pode ocorrer para uma parcela
        | gerada a partir de uma cobrança real de procedimento.
        |
        | Fluxo:
        | ORÇAMENTO → ACEITO → PROCEDIMENTO → COBRANÇA → PARCELAS
        | → A RECEBER → PAGAMENTO → RECEITA
        |--------------------------------------------------------------------------
        */
        if (empty($parcela_locked['procedimento_id'])) {
            throw new Exception(
                'Somente parcelas de cobranças de procedimentos podem ser pagas.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Impedir pagamento duplicado
        |--------------------------------------------------------------------------
        */
        if ($parcela_locked['status'] === 'paga') {
            throw new Exception(
                'Esta parcela já está paga.'
            );
        }

        if (!in_array(
            $parcela_locked['status'],
            ['pendente', 'atrasada'],
            true
        )) {
            throw new Exception(
                'Status da parcela não permite pagamento.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Atualizar parcela
        |--------------------------------------------------------------------------
        */
        $stmt = $pdo->prepare("
            UPDATE parcelas

            SET
                status = 'paga',
                data_pagamento = CURDATE()

            WHERE
                id = ?
                AND status IN ('pendente', 'atrasada')
        ");

        $stmt->execute([$parcela_id]);

        if ($stmt->rowCount() !== 1) {
            throw new Exception(
                'Não foi possível registrar o pagamento.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Verificar se já existe lançamento para esta parcela
        |--------------------------------------------------------------------------
        |
        | parcela_id é UNIQUE em lancamentos_financeiros.
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

        /*
        |--------------------------------------------------------------------------
        | Montar categoria e descrição
        |--------------------------------------------------------------------------
        |
        | Como o pagamento financeiro somente é permitido para cobranças
        | de procedimentos, o lançamento sempre será classificado como
        | receita de procedimento.
        |--------------------------------------------------------------------------
        */
        $categoria = 'Procedimento';

        $descricao = sprintf(
            'Procedimento #%d - Parcela %d',
            (int)$parcela_locked['procedimento_id'],
            (int)$parcela_locked['numero_parcela']
        );

        /*
        |--------------------------------------------------------------------------
        | Criar ou atualizar lançamento financeiro
        |--------------------------------------------------------------------------
        */
        if ($lancamento_id) {

            /*
            | Já existe um lançamento para a parcela.
            | Apenas transformamos em receita paga e atualizamos
            | a forma de pagamento/data.
            */
            $stmt = $pdo->prepare("
                UPDATE lancamentos_financeiros

                SET
                    tipo = 'receita',
                    categoria = ?,
                    descricao = ?,
                    data = CURDATE(),
                    forma_pagamento = ?,
                    valor = ?,
                    parcelas = 1,
                    status = 'pago',
                    orcamento_id = ?,
                    procedimento_id = ?

                WHERE id = ?
            ");

            $stmt->execute([
                $categoria,
                $descricao,
                $forma_pagamento,
                $parcela_locked['valor'],
                !empty($parcela_locked['orcamento_id'])
                    ? (int)$parcela_locked['orcamento_id']
                    : null,
                !empty($parcela_locked['procedimento_id'])
                    ? (int)$parcela_locked['procedimento_id']
                    : null,
                $lancamento_id
            ]);
        } else {

            /*
            | Ainda não existe lançamento financeiro.
            */
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
                    orcamento_id,
                    parcela_id,
                    procedimento_id
                )

                VALUES (
                    'receita',
                    ?,
                    ?,
                    CURDATE(),
                    ?,
                    ?,
                    1,
                    'pago',
                    ?,
                    ?,
                    ?
                )
            ");

            $stmt->execute([
                $categoria,
                $descricao,
                $forma_pagamento,
                $parcela_locked['valor'],
                !empty($parcela_locked['orcamento_id'])
                    ? (int)$parcela_locked['orcamento_id']
                    : null,
                $parcela_id,
                !empty($parcela_locked['procedimento_id'])
                    ? (int)$parcela_locked['procedimento_id']
                    : null
            ]);
        }

        $pdo->commit();

        header(
            'Location: financeiro.php?sucesso=pagamento'
        );

        exit;
    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log(
            'Erro ao pagar parcela: ' . $e->getMessage()
        );

        $erro = $e->getMessage();

        /*
        | Recarregar os dados depois de um possível rollback.
        */
        $parcela = buscarParcela(
            $pdo,
            $parcela_id
        );
    }
}

/*
|--------------------------------------------------------------------------
| Dados para apresentação
|--------------------------------------------------------------------------
*/
$status = strtolower(
    trim((string)$parcela['status'])
);

$status_texto = match ($status) {
    'paga' => 'Paga',
    'atrasada' => 'Atrasada',
    default => 'Pendente'
};

function moedaBR($valor): string
{
    return 'R$ ' . number_format(
        (float)$valor,
        2,
        ',',
        '.'
    );
}

function dataBR($data): string
{
    return !empty($data)
        ? date('d/m/Y', strtotime($data))
        : '—';
}

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Confirmar pagamento | Dentech</title>

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
        href="css/navbar.css">

    <link rel="stylesheet"
        href="css/pagar_parcela.css">

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">


</head>

<body>

    <?php include 'navbar.php'; ?>

    <main class="content">

        <div class="pagamento-page">

            <div class="breadcrumb">
                Financeiro / Pagamento
            </div>

            <h1>Confirmar pagamento</h1>

            <p>
                Confira os dados da cobrança e informe como o pagamento foi realizado.
            </p>

            <?php if ($erro !== ''): ?>

                <div class="alert">

                    <i class="fa-solid fa-circle-exclamation"></i>

                    <?= htmlspecialchars(
                        $erro,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </div>

            <?php endif; ?>

            <section class="card-pagamento">

                <div class="resumo">

                    <div class="info">

                        <label>Paciente</label>

                        <strong>
                            <?= htmlspecialchars(
                                $parcela['paciente'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </strong>

                    </div>

                    <div class="info">

                        <label>Parcela</label>

                        <strong>
                            #<?= (int)$parcela['numero_parcela'] ?>
                        </strong>

                    </div>

                    <div class="info">

                        <label>Valor</label>

                        <strong class="valor">
                            <?= moedaBR($parcela['valor']) ?>
                        </strong>

                    </div>

                    <div class="info">

                        <label>Vencimento</label>

                        <strong>
                            <?= dataBR($parcela['vencimento']) ?>
                        </strong>

                    </div>

                    <div class="info">

                        <label>Status</label>

                        <strong>

                            <span class="status status-<?= htmlspecialchars($status) ?>">

                                <?= htmlspecialchars($status_texto) ?>

                            </span>

                        </strong>

                    </div>

                </div>

                <form method="POST">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars(
                                    $_SESSION['csrf_token'] ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>">

                    <input
                        type="hidden"
                        name="parcela_id"
                        value="<?= (int)$parcela_id ?>">

                    <div class="campo">

                        <label for="forma_pagamento">
                            Como o pagamento foi realizado?
                        </label>

                        <select
                            id="forma_pagamento"
                            name="forma_pagamento"
                            required>

                            <option value="">
                                Selecione uma forma de pagamento
                            </option>

                            <?php foreach ($formas_pagamento as $forma): ?>

                                <option
                                    value="<?= htmlspecialchars(
                                                $forma,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>">

                                    <?= htmlspecialchars(
                                        $forma,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="acoes">

                        <a
                            class="btn"
                            href="visualizar_cobranca.php?parcela_id=<?= (int)$parcela_id ?>">

                            <i class="fa-solid fa-arrow-left"></i>

                            Cancelar

                        </a>

                        <button
                            type="submit"
                            class="btn btn-success">

                            <i class="fa-solid fa-check"></i>

                            Confirmar pagamento

                        </button>

                    </div>

                </form>

            </section>

        </div>

    </main>

</body>

</html>