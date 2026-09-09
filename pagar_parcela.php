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
        | Orçamento é apenas uma proposta comercial. Suas parcelas são
        | condições comerciais e não podem ser recebidas pelo Financeiro.
        |
        | Somente parcelas vinculadas a um procedimento possuem cobrança
        | financeira efetiva e podem ser pagas por este fluxo.
        |--------------------------------------------------------------------------
        */
        if (empty($parcela_locked['procedimento_id'])) {
            throw new Exception(
                'Somente parcelas de cobranças de procedimentos podem ser pagas.'
            );
        }

        if (!empty($parcela_locked['orcamento_id'])) {
            throw new Exception(
                'Esta parcela pertence a um orçamento e não possui cobrança financeira.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Validar status
        |--------------------------------------------------------------------------
        */
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
            |--------------------------------------------------------------------------
            | Já existe um lançamento para a parcela.
            |
            | IMPORTANTE:
            | - data permanece como a data original da cobrança;
            | - data_pagamento registra a data efetiva do pagamento.
            |--------------------------------------------------------------------------
            */
            $stmt = $pdo->prepare("
                UPDATE lancamentos_financeiros

                SET
                    tipo = 'receita',
                    categoria = ?,
                    descricao = ?,
                    forma_pagamento = ?,
                    valor = ?,
                    parcelas = 1,
                    status = 'pago',
                    data_pagamento = CURDATE(),
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
            |--------------------------------------------------------------------------
            | Ainda não existe lançamento financeiro.
            |
            | data = vencimento da cobrança
            | data_pagamento = data efetiva do pagamento
            |--------------------------------------------------------------------------
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
                    data_pagamento,
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
                    'pago',
                    CURDATE(),
                    ?,
                    ?,
                    ?
                )
            ");

            $stmt->execute([
                $categoria,
                $descricao,

                /*
                 * A data do lançamento representa a data da cobrança.
                 */
                $parcela_locked['vencimento'],

                $forma_pagamento,
                $parcela_locked['valor'],

                null,

                $parcela_id,

                (int)$parcela_locked['procedimento_id']
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
        |--------------------------------------------------------------------------
        | Recarregar os dados depois de um possível rollback.
        |--------------------------------------------------------------------------
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

    <link
        rel="stylesheet"
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
                            <?= htmlspecialchars(
                                $status_texto,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </strong>

                    </div>

                </div>

                <?php if (
                    in_array(
                        $status,
                        ['pendente', 'atrasada'],
                        true
                    )
                ): ?>

                    <form
                        method="POST"
                        class="form-pagamento">

                        <input
                            type="hidden"
                            name="parcela_id"
                            value="<?= (int)$parcela_id ?>">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= htmlspecialchars(
                                        $_SESSION['csrf_token'] ?? '',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>">

                        <div class="campo">

                            <label for="forma_pagamento">
                                Forma de pagamento
                            </label>

                            <select
                                name="forma_pagamento"
                                id="forma_pagamento"
                                required>

                                <option value="">
                                    Selecione...
                                </option>

                                <?php foreach (
                                    $formas_pagamento
                                    as $forma
                                ): ?>

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
                                href="financeiro.php"
                                class="btn btn-secundario">

                                <i class="fa-solid fa-arrow-left"></i>

                                Voltar

                            </a>

                            <button
                                type="submit"
                                class="btn btn-confirmar">

                                <i class="fa-solid fa-check"></i>

                                Confirmar pagamento

                            </button>

                        </div>

                    </form>

                <?php else: ?>

                    <div class="acoes">

                        <a
                            href="financeiro.php"
                            class="btn btn-secundario">

                            <i class="fa-solid fa-arrow-left"></i>

                            Voltar

                        </a>

                    </div>

                <?php endif; ?>

            </section>

        </div>

    </main>

</body>

</html>