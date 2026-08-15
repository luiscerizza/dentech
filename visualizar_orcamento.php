<?php
require_once 'config/auth.php';
require_once 'config/csrf.php';
exigirLogin();

require_once 'conexao/conexao.php';

// 🔍 ID do orçamento
$id = $_GET['id'] ?? 0;
if (!$id) die("ID do orçamento não informado.");

// 💰 Processar confirmação de pagamento (POST)

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        empty($_POST['csrf_token']) ||
        empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        http_response_code(403);
        die("Token de segurança inválido.");
    }

    $parcela_id = (int)($_POST['parcela_id'] ?? 0);

    if ($parcela_id > 0) {

        $pdo->prepare("
            UPDATE parcelas 
            SET status = 'paga', data_pagamento = CURDATE()
            WHERE id = ? AND orcamento_id = ?
        ")->execute([$parcela_id, $id]);

        header("Location: " . $_SERVER['PHP_SELF'] . "?id=" . $id);
        exit;
    }
}
        $pdo->prepare("UPDATE parcelas SET status = 'paga', data_pagamento = CURDATE() WHERE id = ? AND orcamento_id = ?")
            ->execute([$parcela_id, $id]);
        // Redireciona para evitar reenvio do formulário
        header("Location: " . $_SERVER['PHP_SELF'] . "?id=" . $id);
        exit;
    
// 📄 Buscar orçamento + dados do paciente
$stmt = $pdo->prepare("
    SELECT o.*, p.paciente, p.cpf, p.telefone, p.email 
    FROM orcamentos o 
    JOIN prontuarios p ON o.paciente_id = p.id 
    WHERE o.id = ?
");
$stmt->execute([$id]);
$orc = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$orc) die("Orçamento não encontrado.");

// 📦 Buscar itens
$stmt_itens = $pdo->prepare("SELECT * FROM orcamentos_itens WHERE orcamento_id = ? ORDER BY id ASC");
$stmt_itens->execute([$id]);
$itens = $stmt_itens->fetchAll();

// ⏰ Auto-atualizar parcelas vencidas como "atrasada"
$pdo->prepare("UPDATE parcelas SET status = 'atrasada' WHERE orcamento_id = ? AND status = 'pendente' AND vencimento < CURDATE()")
    ->execute([$id]);

// 💳 Buscar parcelas
$stmt_par = $pdo->prepare("SELECT * FROM parcelas WHERE orcamento_id = ? ORDER BY numero_parcela ASC");
$stmt_par->execute([$id]);
$parcelas = $stmt_par->fetchAll();

// 📊 Cálculos
$total_itens = 0;
foreach ($itens as $item) {
    $total_itens += $item['quantidade'] * $item['valor_unitario'];
}
$qtd_total = count($parcelas);
$qtd_pagas = 0;
foreach ($parcelas as $p) {
    if ($p['status'] === 'paga') $qtd_pagas++;
}
$progresso = $qtd_total > 0 ? round(($qtd_pagas / $qtd_total) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orçamento #<?= $id ?> - Dentech</title>
    <link rel="stylesheet" href="css/navbar.css">
    <link rel="stylesheet" href="css/vis_orcamento.css">
    <link rel="icon" type="image/png" href="img/icon.PNG">
    <style>

    </style>
</head>

<body>
    <?php include 'navbar.php'; ?>

    <main class="content" style="padding: 20px;">
        <div class="orc-container">
            <!-- Header -->
            <div class="header-actions">
                <h1 style="margin:0; font-size:20px; color:#2d3748;">Orçamento #<?= $id ?> <span class="status-badge status-<?= htmlspecialchars($orc['status']) ?>"><?= ucfirst(htmlspecialchars($orc['status'])) ?></span></h1>
                <div class="btn-group">
                    <a href="editar_orcamento.php?id=<?= $id ?>" class="btn btn-primary">✏️ Editar</a>
                    <a href="gerar_orcamento_pdf.php?id=<?= $id ?>" target="_blank" class="btn btn-success">📥 Baixar PDF</a>
                    <a href="orcamento.php" class="btn btn-outline">← Voltar</a>
                </div>
            </div>

            <!-- Dados do Paciente -->
            <h2 style="font-size:16px; color:#2d3748; margin-top:20px;">👤 Dados do Paciente</h2>
            <div class="info-grid">
                <div class="info-item"><label>Nome</label><span><?= htmlspecialchars($orc['paciente']) ?></span></div>
                <div class="info-item"><label>CPF</label><span><?= !empty($orc['cpf']) ? htmlspecialchars($orc['cpf']) : '—' ?></span></div>
                <div class="info-item"><label>Telefone</label><span><?= !empty($orc['telefone']) ? htmlspecialchars($orc['telefone']) : '—' ?></span></div>
                <div class="info-item"><label>Validade</label><span><?= date('d/m/Y', strtotime($orc['validade'])) ?></span></div>
            </div>

            <!-- Itens -->
            <h2 style="font-size:16px; color:#2d3748;">🦷 Procedimentos</h2>
            <?php if (empty($itens)): ?>
                <p style="color:#666; padding:10px 0;">Nenhum item registrado.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Descrição</th>
                            <th style="width:80px; text-align:center;">Qtd</th>
                            <th style="width:120px; text-align:right;">Unitário</th>
                            <th style="width:120px; text-align:right;">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($itens as $item):
                            $sub = $item['quantidade'] * $item['valor_unitario']; ?>
                            <tr>
                                <td><?= htmlspecialchars($item['descricao']) ?></td>
                                <td style="text-align:center;"><?= (int)$item['quantidade'] ?></td>
                                <td style="text-align:right;">R$ <?= number_format($item['valor_unitario'], 2, ',', '.') ?></td>
                                <td style="text-align:right; font-weight:500;">R$ <?= number_format($sub, 2, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <!-- Total -->
            <div class="total-box">
                <div class="total-label">Valor Total do Orçamento</div>
                <div class="total-value">R$ <?= number_format($total_itens, 2, ',', '.') ?></div>
            </div>

            <!-- Observações -->
            <?php if (!empty($orc['observacoes'])): ?>
                <h2 style="font-size:16px; color:#2d3748; margin-top:20px;">📝 Observações</h2>
                <div style="background:#fff; padding:12px; border-radius:8px; border:1px solid #e2e8f0; font-size:13px; color:#4a5568; white-space:pre-line;"><?= nl2br(htmlspecialchars($orc['observacoes'])) ?></div>
            <?php endif; ?>

            <!-- Parcelas -->
            <?php if (!empty($parcelas)): ?>
                <div class="parcelas-section">
                    <h3>💰 Controle de Parcelas <span style="font-size:12px; font-weight:normal; color:#718096; margin-left:auto;">(<?= $qtd_pagas ?>/<?= $qtd_total ?> pagas)</span></h3>

                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Vencimento</th>
                                <th style="text-align:right;">Valor</th>
                                <th style="text-align:center;">Status</th>
                                <th style="text-align:center;">Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($parcelas as $p):
                                $badge_color = match ($p['status']) {
                                    'paga' => '#43a047',
                                    'atrasada' => '#e53935',
                                    default => '#ef6c00'
                                }; ?>
                                <tr>
                                    <td style="font-weight:600;"><?= $p['numero_parcela'] ?>x</td>
                                    <td><?= date('d/m/Y', strtotime($p['vencimento'])) ?></td>
                                    <td style="text-align:right; font-weight:500;">R$ <?= number_format($p['valor'], 2, ',', '.') ?></td>
                                    <td style="text-align:center;">
                                        <span class="status-badge" style="background:<?= $badge_color ?>; color:#fff;"><?= ucfirst($p['status']) ?></span>
                                    </td>
                                    <td style="text-align:center;">
                                        <?php if ($p['status'] === 'pendente'): ?>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Confirmar pagamento desta parcela?');">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                <input type="hidden" name="parcela_id" value="<?= $p['id'] ?>">
                                                <button type="submit" name="marcar_paga" class="btn-pagar">✅ Confirmar</button>
                                            </form>
                                        <?php elseif ($p['status'] === 'paga'): ?>
                                            <small style="color:#43a047;">Pago em <?= date('d/m', strtotime($p['data_pagamento'])) ?></small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Barra de Progresso -->
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?= $progresso ?>%;"></div>
                    </div>
                    <div class="progress-text">Progresso: <?= $progresso ?>% concluído</div>
                </div>
            <?php endif; ?>

            <div class="footer-note">
                Dentech <?= date('Y') ?> | Documento gerado automaticamente. Valores sujeitos a alteração conforme avaliação clínica.
            </div>
        </div>
    </main>
</body>

</html>