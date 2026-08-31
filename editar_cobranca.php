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

function buscarParcela(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare("SELECT p.*, o.status AS status_orcamento, proc.titulo AS procedimento_titulo, COALESCE(pr_proc.paciente, pr_orc.paciente, 'Paciente não encontrado') AS paciente FROM parcelas p LEFT JOIN orcamentos o ON o.id = p.orcamento_id LEFT JOIN procedimentos proc ON proc.id = p.procedimento_id LEFT JOIN prontuarios pr_orc ON pr_orc.id = o.paciente_id LEFT JOIN prontuarios pr_proc ON pr_proc.id = proc.paciente_id WHERE p.id = ? LIMIT 1");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$cobranca = buscarParcela($pdo, $parcela_id);
if (!$cobranca) {
    http_response_code(404);
    exit('Cobrança não encontrada.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        validar_csrf();
        if (strtolower((string)$cobranca['status']) === 'paga') throw new Exception('Não é permitido alterar o vencimento de uma parcela já paga.');
        $vencimento = trim((string)($_POST['vencimento'] ?? ''));
        $dt = DateTime::createFromFormat('Y-m-d', $vencimento);
        if (!$dt || $dt->format('Y-m-d') !== $vencimento) throw new Exception('Data de vencimento inválida.');

        $status = $vencimento < date('Y-m-d') ? 'atrasada' : 'pendente';
        $stmt = $pdo->prepare("UPDATE parcelas SET vencimento = ?, status = ? WHERE id = ? AND status IN ('pendente','atrasada')");
        $stmt->execute([$vencimento, $status, $parcela_id]);
        if ($stmt->rowCount() !== 1) throw new Exception('Não foi possível atualizar a cobrança.');
        header('Location: visualizar_cobranca.php?parcela_id=' . $parcela_id . '&sucesso=1');
        exit;
    } catch (Throwable $e) {
        $erro = $e->getMessage();
        $cobranca = buscarParcela($pdo, $parcela_id);
    }
}

function dataBR($data): string
{
    return !empty($data) ? date('d/m/Y', strtotime($data)) : '—';
}
$sucesso = isset($_GET['sucesso']);
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar cobrança | Dentech</title>
    <link rel="stylesheet" href="css/global.css">
    <link rel="stylesheet" href="css/variables.css">
    <link rel="stylesheet" href="css/layout.css">
    <link rel="stylesheet" href="css/navbar.css">
    <style>
        .edit-page {
            max-width: 760px;
            margin: 0 auto
        }

        .card-edit {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 26px;
            box-shadow: 0 4px 16px rgba(15, 23, 42, .05)
        }

        .field {
            margin: 20px 0
        }

        .field label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px
        }

        .field input {
            width: 100%;
            box-sizing: border-box;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 15px
        }

        .info-box {
            background: #f8fafc;
            padding: 14px;
            border-radius: 8px;
            color: #475569
        }

        .acoes {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 24px
        }

        .btn {
            padding: 10px 15px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            text-decoration: none;
            background: #fff;
            color: #172033
        }

        .btn-primary {
            background: #2563eb;
            color: #fff;
            border-color: #2563eb
        }

        .alert {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 16px
        }

        .erro {
            background: #fee2e2;
            color: #991b1b
        }

        .sucesso {
            background: #dcfce7;
            color: #166534
        }
    </style>
</head>

<body><?php include 'navbar.php'; ?><main class="content">
        <div class="edit-page">
            <p><a href="visualizar_cobranca.php?parcela_id=<?= $parcela_id ?>">← Voltar para cobrança</a></p>
            <h1>Editar cobrança</h1>
            <p>Altere o vencimento da parcela. Parcelas pagas não podem ser alteradas.</p><?php if (!empty($erro)): ?><div class="alert erro"><?= htmlspecialchars($erro) ?></div><?php endif; ?><section class="card-edit"><?php if ($sucesso): ?><div class="alert sucesso">Vencimento atualizado com sucesso.</div><?php endif; ?><div class="info-box"><strong><?= htmlspecialchars($cobranca['paciente']) ?></strong><br>Parcela <?= (int)$cobranca['numero_parcela'] ?> · R$ <?= number_format((float)$cobranca['valor'], 2, ',', '.') ?><br>Vencimento atual: <?= dataBR($cobranca['vencimento']) ?></div>
                <form method="POST"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <div class="field"><label for="vencimento">Data de vencimento</label><input type="date" id="vencimento" name="vencimento" value="<?= htmlspecialchars($cobranca['vencimento']) ?>" required></div>
                    <div class="acoes"><a class="btn" href="visualizar_cobranca.php?parcela_id=<?= $parcela_id ?>">Cancelar</a><button class="btn btn-primary" type="submit">Salvar alteração</button></div>
                </form>
            </section>
        </div>
    </main>
</body>

</html>