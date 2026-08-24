<?php

require_once 'config/auth.php';
require_once 'config/csrf.php';
require_once 'conexao/conexao.php';

exigirLogin();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if ($id <= 0) {
    die('Serviço inválido.');
}

$erro = null;

/*
|--------------------------------------------------------------------------
| BUSCAR SERVIÇO
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        nome,
        descricao,
        valor_sugerido,
        ativo
    FROM servicos
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$id]);

$servico = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$servico) {
    die('Serviço não encontrado.');
}

/*
|--------------------------------------------------------------------------
| SALVAR ALTERAÇÃO
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    validar_csrf();

    try {

        $nome = trim($_POST['nome'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');

        $valor_bruto = trim(
            str_replace(
                'R$',
                '',
                $_POST['valor_sugerido'] ?? ''
            )
        );

        /*
        |--------------------------------------------------------------------------
        | NORMALIZAR VALOR
        |--------------------------------------------------------------------------
        */

        if (strpos($valor_bruto, ',') !== false) {
            $valor_bruto = str_replace('.', '', $valor_bruto);
            $valor_bruto = str_replace(',', '.', $valor_bruto);
        }

        $valor_sugerido = (float)$valor_bruto;

        if ($nome === '') {
            throw new Exception(
                'Informe o nome do serviço.'
            );
        }

        if (mb_strlen($nome) > 255) {
            throw new Exception(
                'O nome do serviço pode ter no máximo 255 caracteres.'
            );
        }

        if ($valor_sugerido < 0) {
            throw new Exception(
                'O valor sugerido não pode ser negativo.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | VERIFICAR DUPLICIDADE
        |--------------------------------------------------------------------------
        | O próprio serviço é ignorado na comparação.
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT id
            FROM servicos
            WHERE LOWER(TRIM(nome)) = LOWER(TRIM(?))
              AND id <> ?
            LIMIT 1
        ");

        $stmt->execute([
            $nome,
            $id
        ]);

        if ($stmt->fetchColumn()) {
            throw new Exception(
                'Já existe outro serviço com esse nome.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | ATUALIZAR
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            UPDATE servicos
            SET
                nome = ?,
                descricao = ?,
                valor_sugerido = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $nome,
            $descricao !== '' ? $descricao : null,
            $valor_sugerido,
            $id
        ]);

        header(
            'Location: servicos.php?sucesso=editado'
        );

        exit;
    } catch (Throwable $e) {

        $erro = $e->getMessage();

        error_log(
            'ERRO EDITAR SERVICO: ' .
                $e->getMessage()
        );

        /*
        |--------------------------------------------------------------------------
        | Manter o que foi digitado no formulário em caso de erro
        |--------------------------------------------------------------------------
        */

        $servico['nome'] = $_POST['nome'] ?? $servico['nome'];
        $servico['descricao'] = $_POST['descricao'] ?? $servico['descricao'];
        $servico['valor_sugerido'] = $_POST['valor_sugerido']
            ?? $servico['valor_sugerido'];
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

    <title>
        Editar Serviço - Dentech
    </title>

    <link rel="stylesheet" href="css/global.css">
    <link rel="stylesheet" href="css/variables.css">
    <link rel="stylesheet" href="css/layout.css">
    <link rel="stylesheet" href="css/navbar.css">
    <link rel="stylesheet" href="css/editar_servico.css">

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

    <main class="editar-servico-page">

        <div class="editar-servico-container">

            <div class="page-header">

                <div>

                    <span class="page-kicker">
                        CATÁLOGO
                    </span>

                    <h1>
                        Editar serviço
                    </h1>

                    <p>
                        Altere os dados do serviço.
                        Essas mudanças afetam apenas o catálogo e
                        não alteram orçamentos ou procedimentos antigos.
                    </p>

                </div>

                <a
                    href="servicos.php"
                    class="btn btn-outline">
                    <i class="fa-solid fa-arrow-left"></i>
                    Voltar
                </a>

            </div>

            <?php if (!empty($erro)): ?>

                <div class="alert alert-error">

                    <i class="fa-solid fa-circle-exclamation"></i>

                    <span>
                        <?= htmlspecialchars($erro) ?>
                    </span>

                </div>

            <?php endif; ?>

            <section class="form-card">

                <form
                    method="POST"
                    action="editar_servico.php?id=<?= $id ?>">

                    <?= csrf_field() ?>

                    <input
                        type="hidden"
                        name="id"
                        value="<?= $id ?>">

                    <div class="form-grid">

                        <div class="form-group form-group-full">

                            <label for="nome">
                                Nome do serviço
                                <span>*</span>
                            </label>

                            <input
                                type="text"
                                id="nome"
                                name="nome"
                                maxlength="255"
                                required
                                value="<?= htmlspecialchars($servico['nome']) ?>">

                        </div>

                        <div class="form-group form-group-full">

                            <label for="descricao">
                                Descrição
                            </label>

                            <textarea
                                id="descricao"
                                name="descricao"
                                rows="4"
                                placeholder="Descreva o serviço, quando necessário."><?= htmlspecialchars($servico['descricao'] ?? '') ?></textarea>

                        </div>

                        <div class="form-group">

                            <label for="valor_sugerido">
                                Valor sugerido
                                <span>*</span>
                            </label>

                            <div class="money-input">

                                <span>
                                    R$
                                </span>

                                <input
                                    type="text"
                                    id="valor_sugerido"
                                    name="valor_sugerido"
                                    inputmode="decimal"
                                    required
                                    value="<?= htmlspecialchars(
                                                number_format(
                                                    (float)$servico['valor_sugerido'],
                                                    2,
                                                    ',',
                                                    '.'
                                                )
                                            ) ?>">

                            </div>

                            <small>
                                Alterar este valor muda apenas a sugestão
                                usada futuramente nos orçamentos.
                            </small>

                        </div>

                        <div class="form-group status-group">

                            <label>
                                Status
                            </label>

                            <div class="status-display">

                                <?php if ((int)$servico['ativo'] === 1): ?>

                                    <span class="status-badge ativo">
                                        <i class="fa-solid fa-circle-check"></i>
                                        Ativo
                                    </span>

                                <?php else: ?>

                                    <span class="status-badge inativo">
                                        <i class="fa-solid fa-circle-pause"></i>
                                        Inativo
                                    </span>

                                <?php endif; ?>

                            </div>

                            <small>
                                O status é controlado pela ação
                                Ativar/Desativar no catálogo.
                            </small>

                        </div>

                    </div>

                    <div class="form-actions">

                        <a
                            href="servicos.php"
                            class="btn btn-cancel">
                            Cancelar
                        </a>

                        <button
                            type="submit"
                            class="btn btn-primary">
                            <i class="fa-solid fa-check"></i>
                            Salvar alterações
                        </button>

                    </div>

                </form>

            </section>

        </div>

    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {

            const input =
                document.getElementById('valor_sugerido');

            if (!input) {
                return;
            }

            input.addEventListener('input', () => {

                let valor =
                    input.value.replace(/[^\d,.-]/g, '');

                const partes =
                    valor.split(',');

                if (partes.length > 2) {
                    valor =
                        partes[0] + ',' +
                        partes.slice(1).join('');
                }

                input.value = valor;
            });

        });
    </script>

</body>

</html>