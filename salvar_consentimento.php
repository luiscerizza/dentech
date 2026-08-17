<?php

require_once 'config/auth.php';
require_once 'config/csrf.php';
require_once 'conexao/conexao.php';

exigirLogin();

try {

    // Verificar CSRF
    validar_csrf();

    // ID do prontuário
    $prontuario_id = (int)($_POST['prontuario_id'] ?? 0);

    // Verificar se o usuário realmente marcou o aceite
    $aceito = isset($_POST['aceito']) && $_POST['aceito'] === '1';

    if ($prontuario_id <= 0) {
        throw new Exception('Prontuário inválido.');
    }

    if (!$aceito) {
        throw new Exception('É necessário aceitar o Termo de Consentimento.');
    }

    // Verificar se o prontuário existe
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

    // Registrar o consentimento
    $stmt = $pdo->prepare("
        UPDATE prontuarios
        SET
            termo_consentimento_aceito = 1,
            termo_consentimento_aceito_em = NOW()
        WHERE id = ?
    ");

    $stmt->execute([$prontuario_id]);

    // Redirecionar para o prontuário
    header(
        'Location: visualizar_prontuario.php?id=' .
            $prontuario_id .
            '&consentimento=aceito'
    );

    exit;
} catch (Exception $e) {

    http_response_code(400);

    echo '<!DOCTYPE html>';
    echo '<html lang="pt-BR">';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<title>Erro</title>';
    echo '</head>';
    echo '<body>';

    echo '<h2>Não foi possível registrar o consentimento.</h2>';
    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';

    echo '<p>';
    echo '<a href="javascript:history.back()">Voltar</a>';
    echo '</p>';

    echo '</body>';
    echo '</html>';

    exit;
}
