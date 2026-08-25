<?php

require_once 'config/auth.php';
require_once 'config/csrf.php';
require_once 'conexao/conexao.php';

exigirLogin();

$erro = null;

try {
    $pacientes = $pdo->query("
        SELECT id, paciente
        FROM prontuarios
        ORDER BY paciente ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $servicos = $pdo->query("
        SELECT
            id,
            nome,
            descricao,
            valor_sugerido
        FROM servicos
        WHERE ativo = 1
        ORDER BY nome ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $pacientes = [];
    $servicos = [];
    $erro = 'Não foi possível carregar os dados necessários para criar o plano.';
    error_log('ERRO CARREGAR NOVO PLANO: ' . $e->getMessage());
}

/*
|--------------------------------------------------------------------------
| SALVAR PLANO
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    validar_csrf();

    try {

        $pacienteId = (int)($_POST['paciente_id'] ?? 0);
        $titulo = trim($_POST['titulo'] ?? '');
        $descricaoPlano = trim($_POST['descricao'] ?? '');

        $servicoIds = $_POST['servico_id'] ?? [];
        $descricoes = $_POST['item_descricao'] ?? [];
        $dentes = $_POST['dente_regiao'] ?? [];
        $prioridades = $_POST['prioridade'] ?? [];
        $valores = $_POST['valor_estimado'] ?? [];
        $observacoesItens = $_POST['observacoes_item'] ?? [];

        if ($pacienteId <= 0) {
            throw new Exception('Selecione um paciente.');
        }

        if ($titulo === '') {
            throw new Exception('Informe o título do plano de tratamento.');
        }

        if (mb_strlen($titulo) > 255) {
            throw new Exception('O título do plano pode ter no máximo 255 caracteres.');
        }

        /*
        |--------------------------------------------------------------------------
        | Validar paciente
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT id
            FROM prontuarios
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([$pacienteId]);

        if (!$stmt->fetchColumn()) {
            throw new Exception('O paciente selecionado não foi encontrado.');
        }

        /*
        |--------------------------------------------------------------------------
        | Montar itens
        |--------------------------------------------------------------------------
        */

        $itens = [];

        $totalItens = max(
            count($servicoIds),
            count($descricoes),
            count($dentes),
            count($prioridades),
            count($valores),
            count($observacoesItens)
        );

        for ($i = 0; $i < $totalItens; $i++) {

            $servicoId = (int)($servicoIds[$i] ?? 0);
            $descricaoItem = trim($descricoes[$i] ?? '');
            $denteRegiao = trim($dentes[$i] ?? '');
            $prioridade = $prioridades[$i] ?? 'media';
            $valorBruto = trim($valores[$i] ?? '');
            $observacaoItem = trim($observacoesItens[$i] ?? '');

            if ($servicoId > 0) {

                $stmt = $pdo->prepare("
                    SELECT
                        id,
                        nome
                    FROM servicos
                    WHERE id = ?
                      AND ativo = 1
                    LIMIT 1
                ");

                $stmt->execute([$servicoId]);

                $servico = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$servico) {
                    throw new Exception(
                        'Um dos serviços selecionados não está disponível.'
                    );
                }

                /*
                 * O nome do catálogo é a descrição padrão.
                 * O usuário ainda pode editar a descrição manualmente.
                 */
                if ($descricaoItem === '') {
                    $descricaoItem = $servico['nome'];
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Normalizar valor brasileiro
            |--------------------------------------------------------------------------
            */

            if (strpos($valorBruto, ',') !== false) {
                $valorBruto = str_replace('.', '', $valorBruto);
                $valorBruto = str_replace(',', '.', $valorBruto);
            }

            $valorEstimado = (float)$valorBruto;

            $prioridadesPermitidas = [
                'baixa',
                'media',
                'alta'
            ];

            if (!in_array($prioridade, $prioridadesPermitidas, true)) {
                $prioridade = 'media';
            }

            /*
             * Ignora linhas completamente vazias.
             */
            if (
                $servicoId <= 0 &&
                $descricaoItem === '' &&
                $denteRegiao === '' &&
                $valorBruto === '' &&
                $observacaoItem === ''
            ) {
                continue;
            }

            if ($descricaoItem === '') {
                throw new Exception(
                    'Informe a descrição de cada etapa preenchida.'
                );
            }

            if ($valorEstimado < 0) {
                throw new Exception(
                    'O valor estimado não pode ser negativo.'
                );
            }

            $itens[] = [
                'servico_id' => $servicoId > 0 ? $servicoId : null,
                'descricao' => $descricaoItem,
                'dente_regiao' => $denteRegiao !== ''
                    ? $denteRegiao
                    : null,
                'prioridade' => $prioridade,
                'valor_estimado' => $valorEstimado,
                'observacoes' => $observacaoItem !== ''
                    ? $observacaoItem
                    : null,
                'ordem' => count($itens) + 1
            ];
        }

        /*
         |--------------------------------------------------------------------------
         | Pelo menos uma etapa
         |--------------------------------------------------------------------------
         */

        if (empty($itens)) {
            throw new Exception(
                'Adicione pelo menos uma etapa ao plano de tratamento.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | TRANSAÇÃO
        |--------------------------------------------------------------------------
        */

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO planos_tratamento (
                paciente_id,
                titulo,
                descricao,
                status
            )
            VALUES (?, ?, ?, 'planejamento')
        ");

        $stmt->execute([
            $pacienteId,
            $titulo,
            $descricaoPlano !== ''
                ? $descricaoPlano
                : null
        ]);

        $planoId = (int)$pdo->lastInsertId();

        $stmtItem = $pdo->prepare("
            INSERT INTO planos_tratamento_itens (
                plano_id,
                servico_id,
                descricao,
                dente_regiao,
                prioridade,
                valor_estimado,
                status,
                ordem,
                observacoes
            )
            VALUES (
                ?, ?, ?, ?, ?, ?, 'planejado', ?, ?
            )
        ");

        foreach ($itens as $item) {

            $stmtItem->execute([
                $planoId,
                $item['servico_id'],
                $item['descricao'],
                $item['dente_regiao'],
                $item['prioridade'],
                $item['valor_estimado'],
                $item['ordem'],
                $item['observacoes']
            ]);
        }

        $pdo->commit();

        header(
            'Location: visualizar_plano_tratamento.php?id=' .
                $planoId .
                '&sucesso=criado'
        );

        exit;
    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $erro = $e->getMessage();

        error_log(
            'ERRO CRIAR PLANO TRATAMENTO: ' .
                $e->getMessage()
        );
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
        Novo Plano de Tratamento - Dentech
    </title>

    <link rel="stylesheet" href="css/global.css">
    <link rel="stylesheet" href="css/variables.css">
    <link rel="stylesheet" href="css/layout.css">
    <link rel="stylesheet" href="css/navbar.css">
    <link rel="stylesheet" href="css/novo_plano_tratamento.css">

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

    <main class="novo-plano-page">

        <div class="novo-plano-container">

            <header class="page-header">

                <div>

                    <span class="page-kicker">
                        PLANEJAMENTO CLÍNICO
                    </span>

                    <h1>
                        Novo plano de tratamento
                    </h1>

                    <p>
                        Monte o planejamento do paciente sem criar
                        automaticamente um orçamento ou procedimento.
                    </p>

                </div>

                <a
                    href="plano_tratamento.php"
                    class="btn btn-outline">
                    <i class="fa-solid fa-arrow-left"></i>
                    Voltar
                </a>

            </header>

            <?php if (!empty($erro)): ?>

                <div class="message message-error">

                    <i class="fa-solid fa-circle-exclamation"></i>

                    <span>
                        <?= htmlspecialchars($erro) ?>
                    </span>

                </div>

            <?php endif; ?>

            <form
                method="POST"
                id="formPlano"
                class="form-card">

                <?= csrf_field() ?>

                <section class="section-card">

                    <div class="section-header">

                        <div>
                            <span class="section-kicker">
                                PLANO
                            </span>

                            <h2>
                                Dados do planejamento
                            </h2>
                        </div>

                    </div>

                    <div class="form-grid">

                        <div class="form-group form-group-full">

                            <label for="paciente_id">
                                Paciente <span>*</span>
                            </label>

                            <select
                                id="paciente_id"
                                name="paciente_id"
                                required>

                                <option value="">
                                    Selecione o paciente
                                </option>

                                <?php foreach ($pacientes as $paciente): ?>

                                    <option
                                        value="<?= (int)$paciente['id'] ?>"
                                        <?= (
                                            (string)($_POST['paciente_id'] ?? '')
                                            === (string)$paciente['id']
                                        )
                                            ? 'selected'
                                            : '' ?>>
                                        <?= htmlspecialchars(
                                            $paciente['paciente']
                                        ) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                        <div class="form-group form-group-full">

                            <label for="titulo">
                                Título do plano <span>*</span>
                            </label>

                            <input
                                type="text"
                                id="titulo"
                                name="titulo"
                                maxlength="255"
                                value="<?= htmlspecialchars(
                                            $_POST['titulo'] ?? ''
                                        ) ?>"
                                placeholder="Ex.: Reabilitação oral"
                                required>

                        </div>

                        <div class="form-group form-group-full">

                            <label for="descricao">
                                Descrição geral
                            </label>

                            <textarea
                                id="descricao"
                                name="descricao"
                                rows="4"
                                placeholder="Descreva o objetivo geral do tratamento."><?= htmlspecialchars(
                                                                                            $_POST['descricao'] ?? ''
                                                                                        ) ?></textarea>

                        </div>

                    </div>

                </section>

                <section class="section-card">

                    <div class="section-header section-header-items">

                        <div>

                            <span class="section-kicker">
                                ETAPAS
                            </span>

                            <h2>
                                Etapas do tratamento
                            </h2>

                            <p>
                                O valor informado aqui é uma estimativa
                                do planejamento e pode ser alterado
                                posteriormente.
                            </p>

                        </div>

                    </div>

                    <div
                        id="itens-container"
                        class="itens-container">

                        <div class="tratamento-item">

                            <div class="item-field item-service">

                                <label>
                                    Serviço
                                </label>

                                <select
                                    name="servico_id[]"
                                    class="servico-select"
                                    onchange="preencherEtapa(this)">

                                    <option value="">
                                        Serviço personalizado
                                    </option>

                                    <?php foreach ($servicos as $servico): ?>

                                        <option
                                            value="<?= (int)$servico['id'] ?>"
                                            data-nome="<?= htmlspecialchars(
                                                            $servico['nome'],
                                                            ENT_QUOTES
                                                        ) ?>"
                                            data-descricao="<?= htmlspecialchars(
                                                                $servico['descricao'] ?? '',
                                                                ENT_QUOTES
                                                            ) ?>"
                                            data-valor="<?= htmlspecialchars(
                                                            (string)$servico['valor_sugerido'],
                                                            ENT_QUOTES
                                                        ) ?>">
                                            <?= htmlspecialchars(
                                                $servico['nome']
                                            ) ?>
                                            — R$
                                            <?= number_format(
                                                (float)$servico['valor_sugerido'],
                                                2,
                                                ',',
                                                '.'
                                            ) ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>

                            <div class="item-field item-description">

                                <label>
                                    Descrição <span>*</span>
                                </label>

                                <input
                                    type="text"
                                    name="item_descricao[]"
                                    placeholder="Ex.: Restauração no dente 16"
                                    required>

                            </div>

                            <div class="item-field item-region">

                                <label>
                                    Dente / região
                                </label>

                                <input
                                    type="text"
                                    name="dente_regiao[]"
                                    placeholder="Ex.: 16">

                            </div>

                            <div class="item-field item-priority">

                                <label>
                                    Prioridade
                                </label>

                                <select name="prioridade[]">

                                    <option value="baixa">
                                        Baixa
                                    </option>

                                    <option
                                        value="media"
                                        selected>
                                        Média
                                    </option>

                                    <option value="alta">
                                        Alta
                                    </option>

                                </select>

                            </div>

                            <div class="item-field item-value">

                                <label>
                                    Valor estimado
                                </label>

                                <div class="money-input">

                                    <span>R$</span>

                                    <input
                                        type="number"
                                        name="valor_estimado[]"
                                        step="0.01"
                                        min="0"
                                        placeholder="0,00"
                                        required>

                                </div>

                            </div>

                            <div class="item-field item-observation">

                                <label>
                                    Observações
                                </label>

                                <input
                                    type="text"
                                    name="observacoes_item[]"
                                    placeholder="Observação da etapa">

                            </div>

                            <button
                                type="button"
                                class="btn-remove-item"
                                onclick="removerEtapa(this)"
                                title="Excluir etapa">
                                <i class="fa-solid fa-trash"></i>
                            </button>

                        </div>

                    </div>

                    <button
                        type="button"
                        class="btn-add-item"
                        onclick="adicionarEtapa()">
                        <i class="fa-solid fa-plus"></i>
                        Adicionar etapa
                    </button>

                </section>

                <div class="form-actions">

                    <a
                        href="plano_tratamento.php"
                        class="btn btn-cancel">
                        Cancelar
                    </a>

                    <button
                        type="submit"
                        class="btn btn-primary">
                        <i class="fa-solid fa-check"></i>
                        Salvar plano
                    </button>

                </div>

            </form>

        </div>

    </main>

    <script>
        const servicosCatalogo = <?= json_encode(
                                        array_map(
                                            static function ($servico) {
                                                return [
                                                    'id' => (int)$servico['id'],
                                                    'nome' => $servico['nome'],
                                                    'descricao' => $servico['descricao'] ?? '',
                                                    'valor' => (float)$servico['valor_sugerido'],
                                                ];
                                            },
                                            $servicos
                                        ),
                                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                                    ) ?>;

        function escaparHtml(valor) {
            return String(valor ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function obterOpcoesServicos() {

            return servicosCatalogo
                .map(servico => `
            <option
                value="${Number(servico.id)}"
                data-nome="${escaparHtml(servico.nome)}"
                data-descricao="${escaparHtml(servico.descricao)}"
                data-valor="${Number(servico.valor)}"
            >
                ${escaparHtml(servico.nome)}
                — R$ ${Number(servico.valor)
                    .toFixed(2)
                    .replace('.', ',')}
            </option>
        `)
                .join('');
        }

        function preencherEtapa(select) {

            const item = select.closest('.tratamento-item');

            if (!item) {
                return;
            }

            const descricao =
                item.querySelector('[name="item_descricao[]"]');

            const valor =
                item.querySelector('[name="valor_estimado[]"]');

            if (!descricao || !valor) {
                return;
            }

            if (select.value === '') {
                return;
            }

            const option =
                select.options[select.selectedIndex];

            const nome =
                option.dataset.nome || '';

            const valorCatalogo =
                Number(option.dataset.valor || 0);

            if (descricao.value.trim() === '') {
                descricao.value =
                    nome;
            }

            valor.value =
                valorCatalogo.toFixed(2);
        }

        function adicionarEtapa() {

            const container =
                document.getElementById('itens-container');

            const div =
                document.createElement('div');

            div.className =
                'tratamento-item';

            div.innerHTML = `

        <div class="item-field item-service">

            <label>
                Serviço
            </label>

            <select
                name="servico_id[]"
                class="servico-select"
                onchange="preencherEtapa(this)"
            >

                <option value="">
                    Serviço personalizado
                </option>

                ${obterOpcoesServicos()}

            </select>

        </div>

        <div class="item-field item-description">

            <label>
                Descrição <span>*</span>
            </label>

            <input
                type="text"
                name="item_descricao[]"
                placeholder="Ex.: Restauração no dente 16"
                required
            >

        </div>

        <div class="item-field item-region">

            <label>
                Dente / região
            </label>

            <input
                type="text"
                name="dente_regiao[]"
                placeholder="Ex.: 16"
            >

        </div>

        <div class="item-field item-priority">

            <label>
                Prioridade
            </label>

            <select name="prioridade[]">

                <option value="baixa">
                    Baixa
                </option>

                <option value="media" selected>
                    Média
                </option>

                <option value="alta">
                    Alta
                </option>

            </select>

        </div>

        <div class="item-field item-value">

            <label>
                Valor estimado
            </label>

            <div class="money-input">

                <span>R$</span>

                <input
                    type="number"
                    name="valor_estimado[]"
                    step="0.01"
                    min="0"
                    placeholder="0,00"
                    required
                >

            </div>

        </div>

        <div class="item-field item-observation">

            <label>
                Observações
            </label>

            <input
                type="text"
                name="observacoes_item[]"
                placeholder="Observação da etapa"
            >

        </div>

        <button
            type="button"
            class="btn-remove-item"
            onclick="removerEtapa(this)"
            title="Excluir etapa"
        >
            <i class="fa-solid fa-trash"></i>
        </button>

    `;

            container.appendChild(div);

            div.scrollIntoView({
                behavior: 'smooth',
                block: 'nearest'
            });
        }

        function removerEtapa(botao) {

            const container =
                document.getElementById('itens-container');

            const itens =
                container.querySelectorAll('.tratamento-item');

            if (itens.length <= 1) {

                const primeiro =
                    itens[0];

                if (!primeiro) {
                    return;
                }

                primeiro.querySelector(
                    '[name="servico_id[]"]'
                ).value = '';

                primeiro.querySelector(
                    '[name="item_descricao[]"]'
                ).value = '';

                primeiro.querySelector(
                    '[name="dente_regiao[]"]'
                ).value = '';

                primeiro.querySelector(
                    '[name="prioridade[]"]'
                ).value = 'media';

                primeiro.querySelector(
                    '[name="valor_estimado[]"]'
                ).value = '';

                primeiro.querySelector(
                    '[name="observacoes_item[]"]'
                ).value = '';

                return;
            }

            botao
                .closest('.tratamento-item')
                .remove();
        }

        document.addEventListener('DOMContentLoaded', () => {

            document
                .getElementById('formPlano')
                ?.addEventListener('submit', (event) => {

                    const itens =
                        document.querySelectorAll(
                            '.tratamento-item'
                        );

                    if (itens.length === 0) {
                        event.preventDefault();

                        alert(
                            'Adicione pelo menos uma etapa ao plano.'
                        );

                        return;
                    }
                });

        });
    </script>

</body>

</html>