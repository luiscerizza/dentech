<?php
require_once 'config/auth.php';
exigirLogin();
require_once 'conexao/conexao.php';

$pacientes = $pdo->query("SELECT id, paciente FROM prontuarios ORDER BY paciente")->fetchAll();

$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (
        empty($_POST['csrf_token']) ||
        empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        throw new Exception("Token de segurança inválido.");
    }

    try {
        $pdo->beginTransaction();

        $paciente_id = (int)($_POST['paciente_id'] ?? 0);
        $validade = $_POST['validade'] ?? '';
        $observacoes = trim($_POST['observacoes'] ?? '');

        // Validações básicas
        if ($paciente_id <= 0) {
            throw new Exception("Selecione um paciente válido.");
        }
        if (empty($validade)) {
            throw new Exception("A data de validade é obrigatória.");
        }

        // Inserir orçamento
        $stmt = $pdo->prepare("
            INSERT INTO orcamentos (paciente_id, data_criacao, validade, observacoes)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$paciente_id, date('Y-m-d'), $validade, $observacoes]);
        $orcamento_id = $pdo->lastInsertId();

        // Inserir itens e calcular total
        $descricoes = $_POST['descricao'] ?? [];
        $quantidades = $_POST['quantidade'] ?? [];
        $valores = $_POST['valor'] ?? [];

        $total_itens = 0; // ← Variável que faltava!
        $itens_salvos = 0;

        foreach ($descricoes as $i => $desc) {
            $desc = trim($desc ?? '');
            $valor = !empty($valores[$i]) ? (float)str_replace(',', '.', $valores[$i]) : 0;

            if (!empty($desc) && $valor > 0) {
                $qtd = (int)($quantidades[$i] ?? 1);
                $subtotal = $qtd * $valor;
                $total_itens += $subtotal; // ← Acumula o total

                $stmt = $pdo->prepare("
                    INSERT INTO orcamentos_itens (orcamento_id, descricao, quantidade, valor_unitario)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$orcamento_id, $desc, $qtd, $valor]);
                $itens_salvos++;
            }
        }

        if ($itens_salvos === 0) {
            throw new Exception("Adicione pelo menos 1 item válido ao orçamento.");
        }

        //  GERAR PARCELAS (dentro da mesma transação)
        $num_parcelas = (int)($_POST['num_parcelas'] ?? 1);

        if ($num_parcelas > 0 && $total_itens > 0) {
            $valor_parcela_base = round($total_itens / $num_parcelas, 2);
            $stmt_par = $pdo->prepare("
                INSERT INTO parcelas (orcamento_id, numero_parcela, valor, vencimento, status)
                VALUES (?, ?, ?, ?, 'pendente')
            ");

            for ($i = 1; $i <= $num_parcelas; $i++) {
                // Ajusta centavos na última parcela para fechar o total exato
                $valor_final = ($i === $num_parcelas)
                    ? round($total_itens - ($valor_parcela_base * ($num_parcelas - 1)), 2)
                    : $valor_parcela_base;

                // Vencimento: mês atual + número da parcela
                $vencimento = date('Y-m-d', strtotime("+{$i} month"));

                $stmt_par->execute([$orcamento_id, $i, $valor_final, $vencimento]);
            }
        }
        //  FIM DA GERAÇÃO DE PARCELAS

        $pdo->commit(); // ← Único commit, após TUDO estar salvo
        header("Location: visualizar_orcamento.php?id=" . $orcamento_id);
        exit;
    } catch (Exception $e) { // ← Captura QUALQUER Exception, não só PDO
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $msg = $e->getMessage();

        if (strpos($msg, '1146') !== false) {
            preg_match("/Table '[^.]+\\.([^']+)' doesn't exist/", $msg, $matches);
            $tabela = $matches[1] ?? 'desconhecida';
            $erro = "Tabela não encontrada: <strong>{$tabela}</strong>. Verifique se ela foi criada no banco.";
        } else {
            $erro = "Erro ao salvar: " . htmlspecialchars($msg);
        }
        error_log("ERRO ORÇAMENTO: " . $msg);
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novo Orçamento - Dentech</title>
    <link rel="stylesheet" href="css/navbar.css">
    <link rel="stylesheet" href="css/new_orcamento.css">
    <link rel="icon" type="image/png" href="img/icon.PNG">
</head>

<body>
    <?php include 'navbar.php'; ?>
    <div class="container">
        <h1>Novo Orçamento</h1>

        <?php if (!empty($erro)): ?>
            <div class="erro" style="background:#ffebee; color:#c62828; padding:12px; border-radius:6px; margin-bottom:20px;">
                <?= htmlspecialchars($erro) ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="formOrcamento">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <div class="form-group">
                <label>Paciente</label>
                <select name="paciente_id" required>
                    <option value="">Selecione</option>
                    <?php foreach ($pacientes as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['paciente']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Data de Validade</label>
                <input type="date" name="validade" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required>
            </div>

            <!-- ITENS DO ORÇAMENTO -->
            <div class="form-group">
                <label>Itens do orçamento</label>
                <div id="itens-container">
                    <div class="item-row">
                        <div><input type="text" name="descricao[]" placeholder="Ex: Clareamento dental" required></div>
                        <div><input type="number" name="quantidade[]" value="1" min="1" style="width:80px;"></div>
                        <div><input type="number" name="valor[]" step="0.01" min="0" placeholder="0.00" required class="item-valor"></div>
                    </div>
                </div>
                <button type="button" class="btn-add-item" onclick="adicionarItem()">+ Adicionar Item</button>
            </div>

            <!-- PARCELAMENTO (FORA do container de itens) -->
            <div class="form-group" style="border-top:1px solid #eee; padding-top:20px; margin-top:20px;">
                <label>Parcelamento</label>
                <select name="num_parcelas" id="num_parcelas">
                    <option value="1">À vista (1x)</option>
                    <option value="2">2x sem juros</option>
                    <option value="3">3x sem juros</option>
                    <option value="4">4x sem juros</option>
                    <option value="5">5x sem juros</option>
                    <option value="6">6x sem juros</option>
                    <option value="7">7x sem juros</option>
                    <option value="8">8x sem juros</option>
                    <option value="9">9x sem juros</option>
                    <option value="10">10x sem juros</option>
                    <option value="11">11x sem juros</option>
                    <option value="12">12x sem juros</option>
                    <option value="13">13x sem juros</option>
                    <option value="14">14x sem juros</option>
                    <option value="15">15x sem juros</option>
                    <option value="16">16x sem juros</option>
                    <option value="17">17x sem juros</option>
                    <option value="18">18x sem juros</option>
                    <option value="19">19x sem juros</option>
                    <option value="20">20x sem juros</option>
                    <option value="21">21x sem juros</option>
                    <option value="22">22x sem juros</option>
                    <option value="23">23x sem juros</option>
                    <option value="24">24x sem juros</option>
                </select>
                <div id="preview_parcelas" style="margin-top:8px; font-size:13px; font-weight:600; color:#7b3ff2;"></div>
            </div>

            <div class="form-group">
                <label>Observações (opcional)</label>
                <textarea name="observacoes" rows="3" placeholder="Condições, descontos, etc..."></textarea>
            </div>

            <button type="submit" class="btn">Criar Orçamento</button>
            <a href="orcamento" style="display:inline-block; margin-left:12px; color:var(--roxo-medio);">Cancelar</a>
        </form>
    </div>

    <script>
        function adicionarItem() {
            const container = document.getElementById('itens-container');
            const div = document.createElement('div');
            div.className = 'item-row';
            div.innerHTML = `
                <div><input type="text" name="descricao[]" placeholder="Ex: Restauração" required></div>
                <div><input type="number" name="quantidade[]" value="1" min="1" style="width:80px;"></div>
                <div><input type="number" name="valor[]" step="0.01" min="0" placeholder="0.00" required class="item-valor"></div>
            `;
            container.appendChild(div);

            // Adiciona listener no novo campo de valor
            div.querySelector('.item-valor').addEventListener('input', calcularPreviewParcelas);
        }

        function calcularPreviewParcelas() {
            const qtdParcelas = parseInt(document.getElementById('num_parcelas').value) || 1;
            let total = 0;

            // Soma todos os itens: quantidade × valor
            document.querySelectorAll('.item-row').forEach(row => {
                const qtdInput = row.querySelector('[name="quantidade[]"]');
                const valInput = row.querySelector('[name="valor[]"]');
                const qtd = parseInt(qtdInput?.value) || 1;
                const val = parseFloat(valInput?.value) || 0;
                total += qtd * val;
            });

            const valorParcela = total / qtdParcelas;
            const preview = document.getElementById('preview_parcelas');

            if (total === 0) {
                preview.textContent = 'Adicione itens para calcular parcelas';
                preview.style.color = '#999';
            } else if (qtdParcelas === 1) {
                preview.textContent = `À vista: R$ ${total.toFixed(2).replace('.', ',')}`;
                preview.style.color = '#7b3ff2';
            } else {
                preview.textContent = `${qtdParcelas}x de R$ ${valorParcela.toFixed(2).replace('.', ',')} (Total: R$ ${total.toFixed(2).replace('.', ',')})`;
                preview.style.color = '#7b3ff2';
            }
        }

        // Listener no select de parcelas
        document.getElementById('num_parcelas').addEventListener('change', calcularPreviewParcelas);

        // Listeners nos campos existentes ao carregar a página
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.item-valor').forEach(input => {
                input.addEventListener('input', calcularPreviewParcelas);
            });
            document.querySelectorAll('[name="quantidade[]"]').forEach(input => {
                input.addEventListener('input', calcularPreviewParcelas);
            });
            // Calcula preview inicial
            calcularPreviewParcelas();
        });

        // Força a recriação das opções depois que a página carregar totalmente
        window.addEventListener('load', function() {
            const select = document.getElementById('num_parcelas');

            // Se por algum motivo o select tiver menos de 24 opções, recria tudo
            if (select.options.length < 24) {
                select.innerHTML = ''; // Limpa o que estiver errado

                for (let i = 1; i <= 24; i++) {
                    const opt = document.createElement('option');
                    opt.value = i;
                    opt.textContent = (i === 1) ? 'À vista (1x)' : `${i}x sem juros`;
                    select.appendChild(opt);
                }
                console.log('Parcelas recriadas com sucesso via JS!');
            }
        });
    </script>
</body>

</html>