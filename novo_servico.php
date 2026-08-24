<?php
require_once 'config/auth.php';
require_once 'config/csrf.php';
require_once 'conexao/conexao.php';

exigirLogin();

$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validar_csrf();

    try {
        $nome = trim($_POST['nome'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');

        $valor_bruto = trim(str_replace('R$', '', $_POST['valor_sugerido'] ?? ''));

        if (strpos($valor_bruto, ',') !== false) {
            $valor_bruto = str_replace('.', '', $valor_bruto);
            $valor_bruto = str_replace(',', '.', $valor_bruto);
        }

        $valor_sugerido = (float)$valor_bruto;

        if ($nome === '') {
            throw new Exception('Informe o nome do serviço.');
        }

        if (mb_strlen($nome) > 255) {
            throw new Exception('O nome do serviço pode ter no máximo 255 caracteres.');
        }

        if ($valor_sugerido < 0) {
            throw new Exception('O valor sugerido não pode ser negativo.');
        }

        $stmt = $pdo->prepare("
            SELECT id
            FROM servicos
            WHERE LOWER(TRIM(nome)) = LOWER(TRIM(?))
            LIMIT 1
        ");
        $stmt->execute([$nome]);

        if ($stmt->fetchColumn()) {
            throw new Exception('Já existe um serviço com esse nome.');
        }

        $stmt = $pdo->prepare("
            INSERT INTO servicos (
                nome,
                descricao,
                valor_sugerido,
                ativo,
                data_criacao
            )
            VALUES (?, ?, ?, 1, CURDATE())
        ");

        $stmt->execute([
            $nome,
            $descricao !== '' ? $descricao : null,
            $valor_sugerido
        ]);

        header('Location: servicos.php?sucesso=criado');
        exit;
    } catch (Throwable $e) {
        $erro = $e->getMessage();
        error_log('ERRO NOVO SERVICO: ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novo Serviço - Dentech</title>

    <link rel="stylesheet" href="css/global.css">
    <link rel="stylesheet" href="css/variables.css">
    <link rel="stylesheet" href="css/layout.css">
    <link rel="stylesheet" href="css/navbar.css">
    <link rel="stylesheet" href="css/novo_servico.css">

    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <link rel="icon" type="image/png" href="img/icon.PNG">
</head>

<body>
    <?php include 'navbar.php'; ?>

    <main class="novo-servico-page">
        <div class="novo-servico-container">

            <div class="page-header">
                <div>
                    <span class="page-kicker">CATÁLOGO</span>
                    <h1>Novo serviço</h1>
                    <p>
                        Cadastre um serviço que poderá ser utilizado posteriormente nos orçamentos.
                    </p>
                </div>

                <a href="servicos.php" class="btn btn-outline">
                    <i class="fa-solid fa-arrow-left"></i>
                    Voltar
                </a>
            </div>

            <?php if (!empty($erro)): ?>
                <div class="alert alert-error">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span><?= htmlspecialchars($erro) ?></span>
                </div>
            <?php endif; ?>

            <section class="form-card">
                <form method="POST">
                    <?= csrf_field() ?>

                    <div class="form-grid">
                        <div class="form-group form-group-full">
                            <label for="nome">
                                Nome do serviço <span>*</span>
                            </label>

                            <input
                                type="text"
                                id="nome"
                                name="nome"
                                maxlength="255"
                                required
                                value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>"
                                placeholder="Ex.: Restauração">
                        </div>

                        <div class="form-group form-group-full">
                            <label for="descricao">Descrição</label>

                            <textarea
                                id="descricao"
                                name="descricao"
                                rows="4"
                                placeholder="Descreva o serviço, quando necessário."><?= htmlspecialchars($_POST['descricao'] ?? '') ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="valor_sugerido">
                                Valor sugerido <span>*</span>
                            </label>

                            <div class="money-input">
                                <span>R$</span>
                                <input
                                    type="text"
                                    id="valor_sugerido"
                                    name="valor_sugerido"
                                    inputmode="decimal"
                                    required
                                    value="<?= htmlspecialchars($_POST['valor_sugerido'] ?? '') ?>"
                                    placeholder="0,00">
                            </div>

                            <small>
                                Este valor será apenas uma sugestão.
                                No orçamento, o usuário poderá ajustá-lo.
                            </small>
                        </div>
                    </div>

                    <div class="form-actions">
                        <a href="servicos.php" class="btn btn-cancel">
                            Cancelar
                        </a>

                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-check"></i>
                            Salvar serviço
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const input = document.getElementById('valor_sugerido');

            if (!input) return;

            input.addEventListener('input', () => {
                let valor = input.value.replace(/[^\d,.-]/g, '');
                const partes = valor.split(',');

                if (partes.length > 2) {
                    valor = partes[0] + ',' + partes.slice(1).join('');
                }

                input.value = valor;
            });
        });
    </script>
</body>

</html>