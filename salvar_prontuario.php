<?php
require_once 'config/auth.php';
exigirLogin();

// Limpa qualquer saída anterior
if (ob_get_level()) ob_end_clean();

// Corrige o caminho da conexão
require_once 'conexao/conexao.php';

// Garante que NADA seja enviado antes do JSON
header('Content-Type: application/json');

try {
    // Validação básica
    $paciente = trim($_POST['paciente'] ?? '');
    $nascimento = $_POST['nascimento'] ?? '';

    if (empty($paciente) || empty($nascimento)) {
        throw new Exception("Nome e data de nascimento são obrigatórios.");
    }

    $data_obj = DateTime::createFromFormat('Y-m-d', $nascimento);
    if (!$data_obj || $data_obj->format('Y-m-d') !== $nascimento) {
        throw new Exception("Data de nascimento inválida.");
    }

    // Processar problemas de saúde (com fallback seguro)
    $problemas_saude = [];
    if (!empty($_POST['problemas_saude']) && is_array($_POST['problemas_saude'])) {
        $problemas_saude = array_filter($_POST['problemas_saude']);
    }
    if (!empty($_POST['problemas_saude_outros'])) {
        $problemas_saude[] = 'Outros: ' . trim($_POST['problemas_saude_outros']);
    }
    $problemas_saude_str = implode(', ', $problemas_saude);

    // Processar doenças transmissíveis
    $doencas = [];
    if (!empty($_POST['doencas_lista']) && is_array($_POST['doencas_lista'])) {
        $doencas = array_filter($_POST['doencas_lista']);
    }
    if (!empty($_POST['doencas_outros'])) {
        $doencas[] = 'Outras: ' . trim($_POST['doencas_outros']);
    }
    $doencas_str = implode(', ', $doencas);

    // Processar campos SIM/NÃO
    $tratamento_odonto = (!empty($_POST['tratamento_odonto_sim']) && $_POST['tratamento_odonto_sim'] == '1') ? ($_POST['tratamento_odonto'] ?? '') : '';
    $tratamento_medico = (!empty($_POST['tratamento_medico_sim']) && $_POST['tratamento_medico_sim'] == '1') ? ($_POST['tratamento_medico'] ?? '') : '';
    $medicamento_continuo = (!empty($_POST['medicamento_continuo_sim']) && $_POST['medicamento_continuo_sim'] == '1') ? ($_POST['medicamento_continuo'] ?? '') : '';
    $alergia_medicamento = (!empty($_POST['alergia_medicamento_sim']) && $_POST['alergia_medicamento_sim'] == '1') ? ($_POST['alergia_medicamento'] ?? '') : '';
    $alergia_outras = (!empty($_POST['alergia_outras_sim']) && $_POST['alergia_outras_sim'] == '1') ? ($_POST['alergia_outras'] ?? '') : '';
    $gravida_meses = (!empty($_POST['gravida']) && $_POST['gravida'] == '1') ? ($_POST['gravida_meses'] ?? '') : '';
    $fuma_tempo = (!empty($_POST['fuma']) && $_POST['fuma'] == '1') ? ($_POST['fuma_tempo'] ?? '') : '';
    $fuma_cigarros_dia = (!empty($_POST['fuma']) && $_POST['fuma'] == '1') ? ($_POST['fuma_cigarros_dia'] ?? '') : '';
    $bebida_frequencia = (!empty($_POST['bebida']) && $_POST['bebida'] == '1') ? ($_POST['bebida_frequencia'] ?? '') : '';
    $drogas_uso = (!empty($_POST['drogas_sim']) && $_POST['drogas_sim'] == '1') ? ($_POST['drogas_uso'] ?? '') : '';
    $cancer_familiar = (!empty($_POST['cancer_familiar_sim']) && $_POST['cancer_familiar_sim'] == '1') ? ($_POST['cancer_familiar'] ?? '') : '';
    $tratamento_cancer = (!empty($_POST['tratamento_cancer_sim']) && $_POST['tratamento_cancer_sim'] == '1') ? ($_POST['tratamento_cancer'] ?? '') : '';

    // Normalizar CPF
    $cpf = trim($_POST['cpf'] ?? '');
    $cpf = preg_replace('/\D/', '', $cpf);

    // CPF não informado
    if ($cpf === '') {
        $cpf = null;
    }

    // CPF informado deve ter 11 dígitos
    if ($cpf !== null && strlen($cpf) !== 11) {
        echo json_encode([
            'success' => false,
            'error' => 'CPF inválido. Informe os 11 dígitos do CPF ou deixe o campo vazio.'
        ]);
        exit;
    }

    if (isset($_POST['id']) && !empty($_POST['id'])) {
        // EDITAR
        $stmt = $pdo->prepare("
            UPDATE prontuarios SET
                paciente = ?, nascimento = ?, sexo = ?, estado_civil = ?, profissao = ?,
                rg = ?, cpf = ?, endereco = ?, cep = ?, telefone = ?, email = ?,
                tratamento_odonto = ?, tratamento_medico = ?, medicamento_continuo = ?,
                alergia_medicamento = ?, alergia_outras = ?, problemas_saude = ?,
                gravida_meses = ?, fuma_tempo = ?, fuma_cigarros_dia = ?,
                bebida_frequencia = ?, drogas_uso = ?, doencas_transmissiveis = ?,
                cancer_familiar = ?, tratamento_cancer = ?, observacoes = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $paciente,
            $nascimento,
            $_POST['sexo'] ?? null,
            $_POST['estado_civil'] ?? null,
            $_POST['profissao'] ?? null,
            $_POST['rg'] ?? null,
            $cpf,
            $_POST['endereco'] ?? null,
            $_POST['cep'] ?? null,
            $_POST['telefone'] ?? null,
            $_POST['email'] ?? null,
            $tratamento_odonto,
            $tratamento_medico,
            $medicamento_continuo,
            $alergia_medicamento,
            $alergia_outras,
            $problemas_saude_str,
            $gravida_meses,
            $fuma_tempo,
            $fuma_cigarros_dia,
            $bebida_frequencia,
            $drogas_uso,
            $doencas_str,
            $cancer_familiar,
            $tratamento_cancer,
            $_POST['observacoes'] ?? '',
            (int)$_POST['id']
        ]);

        echo json_encode(['success' => true, 'redirect' => 'prontuarios.php']);
    } else {
        // CRIAR NOVO
        $stmt = $pdo->prepare("
            INSERT INTO prontuarios (
                paciente, nascimento, sexo, estado_civil, profissao,
                rg, cpf, endereco, cep, telefone, email,
                tratamento_odonto, tratamento_medico, medicamento_continuo,
                alergia_medicamento, alergia_outras, problemas_saude,
                gravida_meses, fuma_tempo, fuma_cigarros_dia,
                bebida_frequencia, drogas_uso, doencas_transmissiveis,
                cancer_familiar, tratamento_cancer, observacoes
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?
            )
        ");
        $stmt->execute([
            $paciente,
            $nascimento,
            $_POST['sexo'] ?? null,
            $_POST['estado_civil'] ?? null,
            $_POST['profissao'] ?? null,
            $_POST['rg'] ?? null,
            $cpf,
            $_POST['endereco'] ?? null,
            $_POST['cep'] ?? null,
            $_POST['telefone'] ?? null,
            $_POST['email'] ?? null,
            $tratamento_odonto,
            $tratamento_medico,
            $medicamento_continuo,
            $alergia_medicamento,
            $alergia_outras,
            $problemas_saude_str,
            $gravida_meses,
            $fuma_tempo,
            $fuma_cigarros_dia,
            $bebida_frequencia,
            $drogas_uso,
            $doencas_str,
            $cancer_familiar,
            $tratamento_cancer,
            $_POST['observacoes'] ?? ''
        ]);

        $novo_id = $pdo->lastInsertId();
        echo json_encode(['success' => true, 'redirect' => 'termo_conscentimento.php?id=' . $novo_id]);
    }

    exit; // ← Importante: evita saída adicional

} catch (PDOException $e) {
    if ($e->errorInfo[1] == 1062) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'error' => 'Este CPF já está cadastrado para outro paciente.'
        ]);
    } else {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }

    exit;
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    exit;
}
