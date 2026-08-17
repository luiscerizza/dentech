<?php

require_once 'config/auth.php';
require_once 'config/csrf.php';
exigirLogin();

require_once 'conexao/conexao.php';

try {

    validar_csrf();

    $prontuario_id = (int)($_POST['prontuario_id'] ?? 0);
    $aceito = isset($_POST['aceito']) && $_POST['aceito'] == '1';

    if ($prontuario_id <= 0) {
        throw new Exception('Prontuário inválido.');
    }

    if (!$aceito) {
        throw new Exception('É necessário aceitar o termo de consentimento.');
    }

    // Verifica se o prontuário realmente existe
    $stmt = $pdo->prepare("
        SELECT id
        FROM prontuarios
        WHERE id = ?
    ");

    $stmt->execute([$prontuario_id]);

    $prontuario = $stmt->fetch();

    if (!$prontuario) {
        throw new Exception('Prontuário não encontrado.');
    }

    // Verifica se já existe um consentimento para esse prontuário
    $stmt = $pdo->prepare("
        SELECT id
        FROM consentimentos
        WHERE prontuario_id = ?
        LIMIT 1
    ");

    $stmt->execute([$prontuario_id]);

    $consentimento = $stmt->fetch();

    if ($consentimento) {

        // Atualiza o consentimento existente
        $stmt = $pdo->prepare("
            UPDATE consentimentos
            SET aceito = 1,
                data_aceite = NOW()
            WHERE prontuario_id = ?
        ");

        $stmt->execute([$prontuario_id]);
    } else {

        // Cria um novo consentimento
        $stmt = $pdo->prepare("
            INSERT INTO consentimentos (
                prontuario_id,
                aceito,
                data_aceite
            )
            VALUES (?, 1, NOW())
        ");

        $stmt->execute([$prontuario_id]);
    }

    /*
     * Aqui vamos decidir posteriormente para qual página
     * o sistema deve ir depois do consentimento.
     *
     * Por enquanto, voltamos para o prontuário.
     */

    header(
        'Location: visualizar_prontuario.php?id=' .
            $prontuario_id
    );

    exit;
} catch (PDOException $e) {

    die('Erro ao salvar o consentimento: ' .
        htmlspecialchars($e->getMessage()));
} catch (Exception $e) {

    die(htmlspecialchars($e->getMessage()));
}
