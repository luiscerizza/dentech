<?php
// funcoes_export.php - Geração de CSV compatível com Excel PT-BR
if (!function_exists('exportarParaCSV')) {
    function exportarParaCSV($pdo, $titulo_relatorio, $sql, $params = [], $nome_arquivo = 'exportacao')
    {
        // Limpa buffers para evitar conflito com headers
        while (ob_get_level()) ob_end_clean();

        $nome_arquivo = $nome_arquivo . '_' . date('Ymd_His') . '.csv';

        // Headers de download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nome_arquivo . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        // 🔑 BOM UTF-8 (Obrigatório para o Excel ler acentos corretamente)
        echo "\xEF\xBB\xBF";

        $saida = fopen('php://output', 'w');

        // Cabeçalho do relatório
        fputcsv($saida, [$titulo_relatorio], ';');
        fputcsv($saida, ['Gerado em: ' . date('d/m/Y H:i:s')], ';');
        fputcsv($saida, [], ';'); // Linha em branco

        // Executa query segura
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($dados)) {
            fputcsv($saida, ['Nenhum dado encontrado com os filtros aplicados.'], ';');
            fclose($saida);
            exit;
        }

        // Cabeçalhos da tabela (formata nomes de colunas)
        $colunas = array_keys($dados[0]);
        $cabecalhos = array_map(fn($c) => ucwords(str_replace('_', ' ', $c)), $colunas);
        fputcsv($saida, $cabecalhos, ';');

        // Dados formatados
        foreach ($dados as $linha) {
            $formatada = [];
            foreach ($linha as $valor) {
                if ($valor === null) {
                    $formatada[] = '';
                } elseif (preg_match('/^\d{4}-\d{2}-\d{2}/', $valor)) {
                    // Formata datas para dd/mm/yyyy
                    $formatada[] = date('d/m/Y', strtotime($valor));
                } elseif (is_numeric($valor)) {
                    // Formata valores para R$ com vírgula decimal
                    $formatada[] = number_format($valor, 2, ',', '.');
                } else {
                    $formatada[] = $valor;
                }
            }
            fputcsv($saida, $formatada, ';');
        }

        fclose($saida);
        exit;
    }
}
