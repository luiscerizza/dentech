<?php
// gerar_orcamento_pdf.php - Versão com Parcelas Corrigida
require_once 'conexao/conexao.php';

// 1. Carregar Dompdf
require_once 'dompdf/autoload.inc.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$options = new Options();
$options->set('defaultFont', 'Helvetica');
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);

// 2. Receber ID
$id_orc = $_GET['id'] ?? ($_POST['id'] ?? 0);
if (!$id_orc) die("ID do orçamento não informado.");

// 3. Buscar dados do orçamento + paciente
$stmt = $pdo->prepare("
    SELECT o.*, p.paciente, p.cpf, p.telefone, p.email 
    FROM orcamentos o 
    JOIN prontuarios p ON o.paciente_id = p.id 
    WHERE o.id = ?
");
$stmt->execute([$id_orc]);
$orc = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$orc) die("Orçamento não encontrado.");

// 4. Buscar itens
$stmt_itens = $pdo->prepare("SELECT * FROM orcamentos_itens WHERE orcamento_id = ? ORDER BY id ASC");
$stmt_itens->execute([$id_orc]);
$itens = $stmt_itens->fetchAll();

// 5. 🔽 BUSCAR PARCELAS (ANTES de montar o HTML)
$stmt_par = $pdo->prepare("SELECT * FROM parcelas WHERE orcamento_id = ? ORDER BY numero_parcela ASC");
$stmt_par->execute([$id_orc]);
$parcelas = $stmt_par->fetchAll();

// Montar HTML das parcelas (compatível com PHP 7+)
$html_parcelas = '';
if (!empty($parcelas)) {
    $html_parcelas = "
    <div class='section-title'>CRONOGRAMA DE PARCELAS</div>
    <table class='items-table'>
        <thead>
            <tr>
                <th style='width:15%; text-align:center;'>PARCELA</th>
                <th style='width:30%; text-align:center;'>VENCIMENTO</th>
                <th style='width:25%; text-align:right;'>VALOR</th>
                <th style='width:30%; text-align:center;'>STATUS</th>
            </tr>
        </thead>
        <tbody>
    ";

    foreach ($parcelas as $p) {
        // Compatível com PHP 7.x (sem match())
        if ($p['status'] === 'paga') {
            $cor_status = '#43a047';
        } elseif ($p['status'] === 'atrasada') {
            $cor_status = '#e53935';
        } else {
            $cor_status = '#ef6c00';
        }

        $html_parcelas .= "
            <tr>
                <td style='text-align:center; font-weight:bold;'>{$p['numero_parcela']}x</td>
                <td style='text-align:center;'>" . date('d/m/Y', strtotime($p['vencimento'])) . "</td>
                <td style='text-align:right;'>R$ " . number_format($p['valor'], 2, ',', '.') . "</td>
                <td style='text-align:center; color:{$cor_status}; font-weight:bold; text-transform:uppercase; font-size:9pt;'>" . ucfirst($p['status']) . "</td>
            </tr>
        ";
    }
    $html_parcelas .= "
        </tbody>
    </table>
    <div style='margin-top:8px; font-size:8pt; color:#718096;'>
        * Valores sem juros. Vencimentos a cada 30 dias.
    </div>
    <br>
    ";
}
// 🔼 FIM DA MONTAGEM DAS PARCELAS

// 6. Logo em Base64
$logo_path = 'img/logo.jpg';
$logo_base64 = '';
if (file_exists($logo_path)) {
    $imageData = base64_encode(file_get_contents($logo_path));
    $logo_base64 = 'data:image/jpeg;base64,' . $imageData;
}

// 7. Calcular totais e montar linhas da tabela de itens
$total = 0;
$linhas_itens = '';
foreach ($itens as $item) {
    $subtotal = $item['quantidade'] * $item['valor_unitario'];
    $total += $subtotal;
    $linhas_itens .= "
        <tr>
            <td style='padding:10px 12px; border-bottom:1px solid #edf2f7;'>{$item['descricao']}</td>
            <td style='padding:10px 12px; text-align:center; border-bottom:1px solid #edf2f7;'>{$item['quantidade']}</td>
            <td style='padding:10px 12px; text-align:right; border-bottom:1px solid #edf2f7;'>R$ " . number_format($item['valor_unitario'], 2, ',', '.') . "</td>
            <td style='padding:10px 12px; text-align:right; font-weight:bold; border-bottom:1px solid #edf2f7;'>R$ " . number_format($subtotal, 2, ',', '.') . "</td>
        </tr>
    ";
}

// 8. HTML Profissional (agora com $html_parcelas já pronto)
$html = "
<!DOCTYPE html>
<html lang='pt-BR'>
<head>
<meta charset='UTF-8'>
<style>
    @page { margin: 14mm 16mm 14mm 16mm; }
    body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 10pt; color: #2d3748; line-height: 1.5; margin: 0; padding: 0; }
    
    /* CABEÇALHO */
    .header { display: table; width: 100%; border-bottom: 3px solid #7b3ff2; padding-bottom: 12px; margin-bottom: 20px; }
    .header-left { display: table-cell; vertical-align: middle; width: 25%; }
    .header-right { display: table-cell; vertical-align: middle; text-align: right; }
    .logo { max-width: 110px; max-height: 65px; }
    .title { font-size: 18pt; font-weight: bold; color: #7b3ff2; margin: 0 0 4px 0; letter-spacing: 0.5px; }
    .subtitle { font-size: 8.5pt; color: #718096; margin: 0; }
    .badge { display: inline-block; background: #ACC89F; color: #1a2e1a; padding: 4px 10px; border-radius: 12px; font-size: 8pt; font-weight: bold; margin-top: 6px; }

    /* SEÇÕES */
    .section-title { background: #7b3ff2; color: #fff; padding: 6px 10px; font-size: 9pt; font-weight: bold; border-radius: 4px 4px 0 0; margin-top: 18px; }
    .info-table { width: 100%; border-collapse: collapse; border: 1px solid #e2e8f0; border-top: none; background: #fff; }
    .info-table td { padding: 9px 12px; border-bottom: 1px solid #edf2f7; font-size: 10pt; }
    .label { color: #718096; font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.8px; display: block; margin-bottom: 2px; }

    /* TABELA DE ITENS */
    .items-table { width: 100%; border-collapse: collapse; }
    .items-table th { background: #7b3ff2; color: #fff; padding: 9px 10px; text-align: left; font-size: 8.5pt; font-weight: bold; border-bottom: 2px solid #5e2fc4; }
    .items-table th:nth-child(2) { text-align: center; width: 12%; }
    .items-table th:nth-child(3), .items-table th:nth-child(4) { text-align: right; width: 22%; }
    .items-table tbody tr:nth-child(even) { background-color: #f8fafc; }

    /* TOTAL & OBS */
    .total-box { text-align: right; margin-top: 14px; padding: 14px; background: #f0f4f8; border-radius: 6px; border-left: 5px solid #ACC89F; }
    .total-label { font-size: 9pt; color: #4a5568; text-transform: uppercase; letter-spacing: 0.5px; }
    .total-value { font-size: 16pt; font-weight: bold; color: #7b3ff2; margin-top: 2px; }

    .obs-box { margin-top: 16px; padding: 12px; background: #fffbeb; border: 1px solid #fef3c7; border-radius: 6px; font-size: 9pt; color: #744210; }

    /* ASSINATURAS */
    .signatures { margin-top: 45px; display: table; width: 100%; }
    .sign-box { display: table-cell; width: 46%; text-align: center; vertical-align: bottom; }
    .sign-line { border-top: 1px solid #cbd5e0; margin-top: 55px; padding-top: 6px; font-size: 8pt; color: #718096; }

    /* RODAPÉ */
    .footer { margin-top: 25px; text-align: center; font-size: 7.5pt; color: #a0aec0; border-top: 1px solid #e2e8f0; padding-top: 10px; }
</style>
</head>
<body>
    <div class='header'>
        <div class='header-left'>
            " . ($logo_base64 ? "<img src='{$logo_base64}' class='logo'>" : "<span style='font-size:24px;'>🦷</span>") . "
        </div>
        <div class='header-right'>
            <h1 class='title'>ORÇAMENTO ODONTOLÓGICO</h1>
            <p class='subtitle'>Documento gerado automaticamente pelo sistema Dentech</p>
            <span class='badge'>📅 Válido até: " . date('d/m/Y', strtotime($orc['validade'])) . "</span>
        </div>
    </div>

    <div class='section-title'>DADOS DO PACIENTE</div>
    <table class='info-table'>
        <tr>
            <td width='50%'><span class='label'>Nome Completo</span>{$orc['paciente']}</td>
            <td width='25%'><span class='label'>CPF</span>" . (!empty($orc['cpf']) ? htmlspecialchars($orc['cpf']) : "—") . "</td>
            <td width='25%'><span class='label'>Contato</span>" . (!empty($orc['telefone']) ? htmlspecialchars($orc['telefone']) : "—") . "</td>
        </tr>
    </table>

    <div class='section-title'>PROCEDIMENTOS & VALORES</div>
    <table class='items-table'>
        <thead>
            <tr>
                <th>DESCRIÇÃO</th>
                <th>QTD</th>
                <th>VALOR UNIT.</th>
                <th>SUBTOTAL</th>
            </tr>
        </thead>
        <tbody>
            {$linhas_itens}
        </tbody>
    </table>

    <div class='total-box'>
        <div class='total-label'>Valor Total do Orçamento</div>
        <div class='total-value'>R$ " . number_format($total, 2, ',', '.') . "</div>
    </div>

    " . (!empty($orc['observacoes']) ? "
    <div class='obs-box'>
        <strong>📝 Observações Clínicas:</strong><br>
        " . nl2br(htmlspecialchars($orc['observacoes'])) . "
    </div>
    " : "") . "

    <!-- 🔽 PARCELAS (inserido aqui, já pré-montado) -->
    {$html_parcelas}
    <!-- 🔼 FIM PARCELAS -->

    <div class='signatures'>
        <div class='sign-box'>
            <div class='sign-line'>Assinatura do Paciente</div>
        </div>
        <div class='sign-box'>
            <div class='sign-line'>Assinatura do Cirurgião-Dentista</div>
        </div>
    </div>

    <div class='footer'>
        Dentech - Sistema de Gestão Odontológica | Gerado em " . date('d/m/Y \à\s H:i') . "<br>
        Este documento não substitui a avaliação clínica presencial. Valores podem ser reajustados conforme complexidade do tratamento.
    </div>
</body>
</html>
";

// 9. Renderizar e Baixar
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("Orcamento_{$orc['id']}_{$orc['paciente']}.pdf", ["Attachment" => true]);
exit;
