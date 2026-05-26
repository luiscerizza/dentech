<?php

require_once 'config/auth.config_area_restrita.php';
require_once 'conexao/conexao.php';

if (!function_exists('exigeAcessoRestrito')) {
    die("❌ Erro crítico: Função 'requireRestrictedAccess' não encontrada.<br>
         Verifique se o arquivo <code>config/auth.config_area_restrita.php</code> existe e contém a função correta.");
}

exigeAcessoRestrito();

if (function_exists('registrarLog')) {
    registrarLog($pdo, 'Acesso', 'Area_Restrita', null, 'Usuário acessou o painel administrativo');
}

$mensagem = '';
$tipo_msg = '';

if (isset($_POST['gerar_backup'])) {
    try {
        while (ob_get_level()) ob_end_clean();

        if (!isset($pdo) || !$pdo instanceof PDO) {
            throw new Exception("Conexão com banco de dados não estabelecida.");
        }

        $nome_arquivo = 'backup_dentech_' . date('Y-m-d_H-i-s') . '.sql';

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $nome_arquivo . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');

        echo "-- =========================================\n";
        echo "-- BACKUP AUTOMÁTICO - DENTECH\n";
        echo "-- Gerado em: " . date('Y-m-d H:i:s') . "\n";
        echo "-- =========================================\n\n";

        echo "SET FOREIGN_KEY_CHECKS=0;\n";
        echo "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n";
        echo "SET time_zone = '+00:00';\n";
        echo "START TRANSACTION;\n";

        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            echo "\n-- Tabela: `{$table}`\n";

            $create = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM);
            if ($create) {
                echo $create[1] . ";\n\n";
            }

            $stmt = $pdo->query("SELECT * FROM `{$table}`");
            $cols = [];
            for ($i = 0; $i < $stmt->columnCount(); $i++) {
                $meta = $stmt->getColumnMeta($i);
                if ($meta) $cols[] = "`{$meta['name']}`";
            }

            if (!empty($cols)) {
                $header = "INSERT INTO `{$table}` (" . implode(', ', $cols) . ") VALUES ";
                $batch = [];

                while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                    $vals = array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote($v), $row);
                    $batch[] = "(" . implode(', ', $vals) . ")";

                    if (count($batch) >= 500) {
                        echo $header . implode(",\n", $batch) . ";\n";
                        $batch = [];
                        if (ob_get_level()) ob_flush();
                        flush();
                    }
                }
                if (!empty($batch)) {
                    echo $header . implode(",\n", $batch) . ";\n";
                }
            }
        }

        echo "\nCOMMIT;\nSET FOREIGN_KEY_CHECKS=1;\n";

        if (function_exists('registrarLog')) {
            registrarLog($pdo, 'Backup', 'Sistema', null, 'Download do arquivo de backup realizado');
        }

        exit; 

    } catch (Exception $e) {
        if (!headers_sent()) {
            $mensagem = "Erro ao gerar backup: " . htmlspecialchars($e->getMessage());
            $tipo_msg = "erro";
        }
        error_log("ERRO BACKUP DENTECH: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Área Restrita - Dentech</title>
    <link rel="stylesheet" href="css/navbar.css">
    <link rel="stylesheet" href="css/restrito.css">
    <link rel="icon" type="image/png" href="img/icon.PNG">
    <style>
        
    </style>
</head>

<body>
    <?php include 'navbar.php'; ?>
    <main style="padding: 20px;">
        <div class="container-rest">
            <h1> Área Restrita (Administrador)</h1>

            <div class="log-status">
                <span class="dot-green"></span> Sistema de auditoria ativo: suas ações são registradas.
            </div>

            <?php if ($mensagem): ?>
                <div class="alert alert-<?= htmlspecialchars($tipo_msg) ?>"><?= $mensagem ?></div>
            <?php endif; ?>



            <!-- DEPOIS (cole exatamente assim) -->
            <div style="display: flex; gap: 15px; margin-top: 20px; flex-wrap: wrap;">
                <form method="POST" action="restrito.php">
                    <button type="submit" name="gerar_backup" class="btn-backup">
                        📥 Baixar Backup Completo (.sql)
                    </button>
                </form>

                <a href="logs.php" class="btn-logs" style="
        background: #7b3ff2; 
        color: #fff; 
        padding: 16px 28px; 
        border: none; 
        border-radius: 10px; 
        font-size: 15px; 
        font-weight: 600; 
        cursor: pointer; 
        transition: all 0.2s ease; 
        display: inline-flex; 
        align-items: center; 
        gap: 10px;
        text-decoration: none;
    " onmouseover="this.style.background='#6a35d9'; this.style.transform='translateY(-2px)'"
                    onmouseout="this.style.background='#7b3ff2'; this.style.transform='translateY(0)'">
                    📋 Ver Logs do Sistema
                </a>
            </div>

            <div class="info-box">
                <strong>ℹ️ Como usar:</strong>
                <ul style="margin: 8px 0 0 20px;">
                    <li>O arquivo é compatível com phpMyAdmin e MySQL Workbench.</li>
                    <li>Inclui estrutura + dados de todas as tabelas.</li>
                    <li>Recomendado fazer antes de atualizações ou limpeza.</li>
                </ul>
            </div>

            <a href="logout.php" style="display:inline-block; margin-top:24px; color:#d32f2f; text-decoration:none;">🚪 Sair da área restrita</a>
        </div>
    </main>
</body>

</html>