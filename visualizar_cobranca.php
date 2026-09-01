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

$pdo->prepare("
    UPDATE parcelas
    SET status = 'atrasada'
    WHERE id = ?
      AND status = 'pendente'
      AND vencimento < CURDATE()
")->execute([$parcela_id]);

$stmt = $pdo->prepare("
    SELECT
        p.id AS parcela_id, p.numero_parcela, p.valor, p.vencimento,
        p.status, p.data_pagamento, p.orcamento_id, p.procedimento_id,
        o.status AS status_orcamento,
        proc.titulo AS procedimento_titulo,
        proc.data_procedimento,
        COALESCE(pr_proc.paciente, pr_orc.paciente, 'Paciente não encontrado') AS paciente,
        lf.forma_pagamento,
        (SELECT COUNT(*) FROM parcelas px WHERE
            (p.orcamento_id IS NOT NULL AND px.orcamento_id = p.orcamento_id)
            OR (p.procedimento_id IS NOT NULL AND px.procedimento_id = p.procedimento_id)
        ) AS total_parcelas
    FROM parcelas p
    LEFT JOIN orcamentos o ON o.id = p.orcamento_id
    LEFT JOIN procedimentos proc ON proc.id = p.procedimento_id
    LEFT JOIN prontuarios pr_orc ON pr_orc.id = o.paciente_id
    LEFT JOIN prontuarios pr_proc ON pr_proc.id = proc.paciente_id
    LEFT JOIN lancamentos_financeiros lf ON lf.parcela_id = p.id
    WHERE p.id = ?
    LIMIT 1
");
$stmt->execute([$parcela_id]);
$cobranca = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cobranca) {
    http_response_code(404);
    exit('Cobrança não encontrada.');
}

function moedaBR($valor): string
{
    return 'R$ ' . number_format((float)$valor, 2, ',', '.');
}
function dataBR($data): string
{
    return !empty($data) ? date('d/m/Y', strtotime($data)) : '—';
}
$status = strtolower(trim((string)$cobranca['status']));
$statusTexto = ['paga' => 'Paga', 'pendente' => 'Pendente', 'atrasada' => 'Atrasada'][$status] ?? ucfirst($status);
$origem = !empty($cobranca['procedimento_id']) ? 'Procedimento' : 'Orçamento';
$referencia = !empty($cobranca['procedimento_id']) ? '#' . (int)$cobranca['procedimento_id'] . ' — ' . ($cobranca['procedimento_titulo'] ?? '') : 'Orçamento #' . (int)$cobranca['orcamento_id'];
$totalParcelas = max(1, (int)$cobranca['total_parcelas']);
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cobrança #<?= (int)$cobranca['parcela_id'] ?> | Dentech</title>
    <link rel="stylesheet" href="css/global.css">
    <link rel="stylesheet" href="css/variables.css">
    <link rel="stylesheet" href="css/layout.css">
    <link rel="stylesheet" href="css/navbar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        .cobranca-page {
            max-width: 980px;
            margin: 0 auto;
            padding-bottom: 40px
        }

        .cobranca-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 24px
        }

        .cobranca-header h1 {
            margin: 4px 0
        }

        .breadcrumb {
            color: #2563eb;
            font-weight: 600
        }

        .acoes {
            display: flex;
            gap: 10px;
            flex-wrap: wrap
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 15px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            text-decoration: none;
            color: #172033;
            background: #fff
        }

        .btn-primary {
            background: #2563eb;
            color: #fff;
            border-color: #2563eb
        }

        .btn-success {
            background: #16a34a;
            color: #fff;
            border-color: #16a34a
        }

        .card-cobranca {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 24px;
            box-shadow: 0 4px 16px rgba(15, 23, 42, .05);
            margin-bottom: 18px
        }

        .grid-info {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px
        }

        .info label {
            display: block;
            color: #64748b;
            font-size: 12px;
            margin-bottom: 5px
        }

        .info strong {
            font-size: 16px
        }

        .valor {
            font-size: 30px !important
        }

        .badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 999px;
            font-weight: 700;
            font-size: 12px
        }

        .paga {
            background: #dcfce7;
            color: #166534
        }

        .pendente {
            background: #fef3c7;
            color: #92400e
        }

        .atrasada {
            background: #fee2e2;
            color: #991b1b
        }

        @media(max-width:700px) {
            .cobranca-header {
                align-items: flex-start;
                flex-direction: column
            }

            .grid-info {
                grid-template-columns: 1fr
            }
        }
    </style>
</head>

<body>
    <?php include 'navbar.php'; ?>
    <main class="content">
        <div class="cobranca-page">
            <div class="cobranca-header">
                <div>
                    <div class="breadcrumb">Financeiro / Cobrança</div>
                    <h1>Cobrança #<?= (int)$cobranca['parcela_id'] ?></h1>
                    <p>Detalhes da parcela e situação financeira.</p>
                </div>
                <div class="acoes"><a class="btn" href="financeiro.php"><i class="fa-solid fa-arrow-left"></i> Voltar</a><?php if ($status !== 'paga'): ?><a class="btn btn-primary" href="editar_cobranca.php?parcela_id=<?= (int)$cobranca['parcela_id'] ?>"><i class="fa-solid fa-pen"></i> Editar</a><?php endif; ?></div>
            </div>
            <section class="card-cobranca">
                <div class="grid-info">
                    <div class="info"><label>Paciente</label><strong><?= htmlspecialchars($cobranca['paciente']) ?></strong></div>
                    <div class="info"><label>Origem</label><strong><?= htmlspecialchars($origem) ?></strong></div>
                    <div class="info"><label>Referência</label><strong><?= htmlspecialchars($referencia) ?></strong></div>
                    <div class="info"><label>Parcela</label><strong><?= (int)$cobranca['numero_parcela'] ?> / <?= $totalParcelas ?></strong></div>
                    <div class="info"><label>Valor</label><strong class="valor"><?= moedaBR($cobranca['valor']) ?></strong></div>
                    <div class="info"><label>Vencimento</label><strong><?= dataBR($cobranca['vencimento']) ?></strong></div>
                    <div class="info"><label>Status</label><strong><span class="badge <?= htmlspecialchars($status) ?>"><?= htmlspecialchars($statusTexto) ?></span></strong></div>
                    <div class="info"><label>Forma de pagamento</label><strong><?= htmlspecialchars($cobranca['forma_pagamento'] ?: 'Não informado') ?></strong></div>
                    <?php if (!empty($cobranca['data_pagamento'])): ?><div class="info"><label>Data do pagamento</label><strong><?= dataBR($cobranca['data_pagamento']) ?></strong></div><?php endif; ?>
                </div>
            </section>
            
        </div>
    </main>
</body>

</html>