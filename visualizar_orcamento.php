<?php
require_once 'conexao/conexao.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("
    SELECT o.*, p.paciente
    FROM orcamentos o
    JOIN prontuarios p ON o.paciente_id = p.id
    WHERE o.id = ?
");
$stmt->execute([$id]);
$orcamento = $stmt->fetch();

if (!$orcamento) die("Orçamento não encontrado.");

$stmt = $pdo->prepare("SELECT * FROM orcamentos_itens WHERE orcamento_id = ?");
$stmt->execute([$id]);
$itens = $stmt->fetchAll();

$total = 0;
foreach ($itens as $i) {
    $total += $i['quantidade'] * $i['valor_unitario'];
}

$isPrint = isset($_GET['print']) && $_GET['print'] == '1';
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orçamento #<?= $orcamento['id'] ?> - Dentech</title>
    <link rel="stylesheet" href="css/navbar.css">
    <link rel="stylesheet" href="css/vis_orcamento.css">
</head>

<body>
    <?php if (!$isPrint): ?>
        <?php include 'navbar.php'; ?>
    <?php endif; ?>

    <style>
        /* ... seu CSS existente ... */

        /* Estilos para impressão (apenas se for print) */
        <?php if ($isPrint): ?>body {
            padding-top: 0 !important;
            background: white !important;
        }

        .container {
            max-width: 210mm;
            margin: 0 auto;
            padding: 15mm;
        }

        nav,
        .btn-imprimir {
            display: none !important;
        }

        @media print {
            body {
                margin: 0;
            }
        }

        <?php endif; ?>
    </style>
    <div class="container">
        <div class="card">
            <h1>Orçamento #<?= $orcamento['id'] ?></h1>

            <div class="info">
                <p><strong>Paciente:</strong> <?= htmlspecialchars($orcamento['paciente']) ?></p>
                <p><strong>Data de criação:</strong> <?= date('d/m/Y', strtotime($orcamento['data_criacao'])) ?></p>
                <p><strong>Validade:</strong> <?= date('d/m/Y', strtotime($orcamento['validade'])) ?></p>
                <p><strong>Status:</strong>
                    <?php if ($orcamento['status'] == 'pendente'): ?>
                        <span class="status status-pendente">Pendente</span>
                    <?php elseif ($orcamento['status'] == 'aceito'): ?>
                        <span class="status status-aceito">Aceito</span>
                    <?php else: ?>
                        <span class="status status-recusado">Recusado</span>
                    <?php endif; ?>
                </p>
                <?php if (!empty($orcamento['observacoes'])): ?>
                    <p><strong>Observações:</strong> <?= nl2br(htmlspecialchars($orcamento['observacoes'])) ?></p>
                <?php endif; ?>

                <?php if ($isPrint): ?>
                    <div style="margin-top: 40px; padding-top: 20px; border-top: 1pt solid #ccc; font-size: 12px; color: #666;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 25px;">
                            <div style="width: 45%;">
                                <p style="margin: 0 0 8px; font-weight: bold; color: #555;">Assinatura do Paciente</p>
                                <div style="height: 50px; border-bottom: 1pt solid #999;"></div>
                            </div>
                            <div style="width: 45%;">
                                <p style="margin: 0 0 8px; font-weight: bold; color: #555;">Assinatura do Dentista</p>
                                <div style="height: 50px; border-bottom: 1pt solid #999;"></div>
                            </div>
                        </div>

                        <div style="text-align: center; margin-top: 10px; padding-top: 10px; border-top: 1pt solid #eee;">
                            <p style="margin: 4px 0; line-height: 1.4;">
                                <strong>Dra Katia Gonçalves de Jesus CRO-SP 135972</strong><br>
                                Siqueira Campos 1100 sala 03 • São João • Araçatuba/SP<br>
                                (18) 981904484 • katiagjesus@gmail.com<br>
                            </p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Descrição</th>
                        <th>Qtd</th>
                        <th>Valor Unit.</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($itens as $i): ?>
                        <tr>
                            <td><?= htmlspecialchars($i['descricao']) ?></td>
                            <td><?= $i['quantidade'] ?></td>
                            <td>R$ <?= number_format($i['valor_unitario'], 2, ',', '.') ?></td>
                            <td>R$ <?= number_format($i['quantidade'] * $i['valor_unitario'], 2, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="total">Total: R$ <?= number_format($total, 2, ',', '.') ?></div>

            <div class="acoes">
                <button class="btn btn-imprimir"
                    onclick="window.open('visualizar_orcamento.php?id=<?= $orcamento['id'] ?>&print=1', '_blank')">
                    🖨️ Imprimir Orçamento
                </button>

                <?php if ($orcamento['status'] == 'pendente'): ?>
                    <button class="btn btn-aceitar" onclick="window.location='aceitar_orcamento.php?id=<?= $orcamento['id'] ?>'">Aceitar Orçamento</button>
                    <button class="btn btn-recusar" onclick="window.location='recusar_orcamento.php?id=<?= $orcamento['id'] ?>'">Recusar</button>
                <?php endif; ?>
                <button class="btn btn-voltar" onclick="window.location='orcamento.php'">Voltar</button>
            </div>
        </div>
    </div>
</body>

</html>