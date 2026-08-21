<?php

require_once 'config/auth.php';
exigirLogin();

require_once 'conexao/conexao.php';

header('Content-Type: application/json; charset=utf-8');


/*
|--------------------------------------------------------------------------
| Função de resposta
|--------------------------------------------------------------------------
*/

function responder($success, $data = [])
{
    echo json_encode(
        array_merge(
            ['success' => $success],
            $data
        ),
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Somente POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    responder(false, [
        'error' => 'Método não permitido.'
    ]);
}


/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

if (
    empty($_POST['csrf_token']) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals(
        $_SESSION['csrf_token'],
        $_POST['csrf_token']
    )
) {

    http_response_code(403);

    responder(false, [
        'error' => 'Token de segurança inválido.'
    ]);
}


/*
|--------------------------------------------------------------------------
| JSON recebido
|--------------------------------------------------------------------------
*/

if (empty($_POST['dados'])) {

    responder(false, [
        'error' => 'Dados do procedimento não foram enviados.'
    ]);
}


$dados = json_decode(
    $_POST['dados'],
    true
);


if (!is_array($dados)) {

    responder(false, [
        'error' => 'Dados do procedimento inválidos.'
    ]);
}


/*
|--------------------------------------------------------------------------
| Dados principais
|--------------------------------------------------------------------------
*/

$prontuario_id =
    isset($dados['prontuario_id'])
    ? (int)$dados['prontuario_id']
    : 0;


$agendamento_id =
    !empty($dados['agendamento_id'])
    ? (int)$dados['agendamento_id']
    : null;


$titulo =
    trim($dados['titulo'] ?? '');


$data_procedimento =
    trim($dados['data_procedimento'] ?? '');


$descricao =
    trim($dados['descricao'] ?? '');


$medicamentos =
    trim($dados['medicamentos'] ?? '');


$valor_mao_obra =
    isset($dados['valor_mao_obra'])
    ? (float)$dados['valor_mao_obra']
    : 0;


$materiais =
    $dados['materiais'] ?? [];


/*
|--------------------------------------------------------------------------
| Validações básicas
|--------------------------------------------------------------------------
*/

if ($prontuario_id <= 0) {

    responder(false, [
        'error' => 'Prontuário inválido.'
    ]);
}


if ($titulo === '') {

    responder(false, [
        'error' => 'O nome do procedimento é obrigatório.'
    ]);
}


if ($data_procedimento === '') {

    responder(false, [
        'error' => 'A data do procedimento é obrigatória.'
    ]);
}


if (!is_array($materiais)) {

    responder(false, [
        'error' => 'Lista de materiais inválida.'
    ]);
}


if ($valor_mao_obra < 0) {

    responder(false, [
        'error' => 'O valor da mão de obra não pode ser negativo.'
    ]);
}


/*
|--------------------------------------------------------------------------
| Verificar data
|--------------------------------------------------------------------------
*/

$dataValida =
    DateTime::createFromFormat(
        'Y-m-d',
        $data_procedimento
    );


if (
    !$dataValida ||
    $dataValida->format('Y-m-d') !== $data_procedimento
) {

    responder(false, [
        'error' => 'Data do procedimento inválida.'
    ]);
}


/*
|--------------------------------------------------------------------------
| Evitar materiais duplicados
|--------------------------------------------------------------------------
*/

$idsMateriais = [];

foreach ($materiais as $material) {

    $estoque_id =
        isset($material['estoque_id'])
        ? (int)$material['estoque_id']
        : 0;


    if ($estoque_id <= 0) {

        responder(false, [
            'error' => 'Material inválido.'
        ]);
    }


    if (in_array($estoque_id, $idsMateriais, true)) {

        responder(false, [
            'error' => 'O mesmo material foi informado mais de uma vez.'
        ]);
    }


    $idsMateriais[] = $estoque_id;
}


/*
|--------------------------------------------------------------------------
| Verificar prontuário
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT id
    FROM prontuarios
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([
    $prontuario_id
]);


if (!$stmt->fetchColumn()) {

    responder(false, [
        'error' => 'Prontuário não encontrado.'
    ]);
}


/*
|--------------------------------------------------------------------------
| Transação
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | Verificar agendamento
    |--------------------------------------------------------------------------
    */

    if ($agendamento_id !== null) {

        $stmt = $pdo->prepare("
            SELECT
                id,
                paciente_id,
                status
            FROM agendamentos
            WHERE id = ?
            FOR UPDATE
        ");

        $stmt->execute([
            $agendamento_id
        ]);

        $agendamento =
            $stmt->fetch(PDO::FETCH_ASSOC);


        if (!$agendamento) {

            throw new Exception(
                'Agendamento não encontrado.'
            );
        }


        if (
            (int)$agendamento['paciente_id']
            !== $prontuario_id
        ) {

            throw new Exception(
                'O agendamento não pertence ao paciente informado.'
            );
        }


        if ($agendamento['status'] !== 'confirmado') {

            throw new Exception(
                'O procedimento só pode ser registrado para um agendamento confirmado.'
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Calcular materiais diretamente do banco
    |--------------------------------------------------------------------------
    */

    $valor_materiais = 0;
    $valor_sugerido_materiais = 0;

    $materiaisCalculados = [];


    foreach ($materiais as $material) {

        $estoque_id =
            (int)$material['estoque_id'];


        $quantidade =
            (float)($material['quantidade'] ?? 0);


        if ($quantidade <= 0) {

            throw new Exception(
                'A quantidade de material deve ser maior que zero.'
            );
        }


        /*
        |--------------------------------------------------------------
        | Bloqueia a linha do estoque durante a transação
        |--------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT
                id,
                nome,
                quantidade,
                unidade,
                valor_item,
                valor_sugerido
            FROM estoque
            WHERE id = ?
            FOR UPDATE
        ");

        $stmt->execute([
            $estoque_id
        ]);


        $estoque =
            $stmt->fetch(PDO::FETCH_ASSOC);


        if (!$estoque) {

            throw new Exception(
                'Material não encontrado no estoque.'
            );
        }


        $estoqueAtual =
            (float)$estoque['quantidade'];


        if ($quantidade > $estoqueAtual) {

            throw new Exception(
                'Estoque insuficiente para "' .
                    $estoque['nome'] .
                    '". Disponível: ' .
                    $estoqueAtual .
                    ' ' .
                    $estoque['unidade'] .
                    '. Solicitado: ' .
                    $quantidade .
                    '.'
            );
        }


        $valorItem =
            (float)$estoque['valor_item'];


        $valorSugerido =
            (float)$estoque['valor_sugerido'];


        $subtotal =
            $valorItem * $quantidade;


        $subtotalSugerido =
            $valorSugerido * $quantidade;


        $valor_materiais +=
            $subtotal;


        $valor_sugerido_materiais +=
            $subtotalSugerido;


        $materiaisCalculados[] = [

            'estoque_id' =>
            $estoque_id,

            'quantidade' =>
            $quantidade,

            'valor_unitario' =>
            $valorItem,

            'valor_sugerido_unitario' =>
            $valorSugerido,

            'subtotal' =>
            $subtotal

        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Valor final
    |--------------------------------------------------------------------------
    */

    $valor_final =
        $valor_materiais +
        $valor_mao_obra;


    /*
    |--------------------------------------------------------------------------
    | Criar procedimento
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        INSERT INTO procedimentos (
            paciente_id,
            agendamento_id,
            titulo,
            descricao,
            medicamentos,
            data_procedimento,
            valor_materiais,
            valor_mao_obra,
            valor_final
        )
        VALUES (
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?
        )
    ");


    $stmt->execute([

        $prontuario_id,

        $agendamento_id,

        $titulo,

        $descricao !== ''
            ? $descricao
            : null,

        $medicamentos !== ''
            ? $medicamentos
            : null,

        $data_procedimento,

        $valor_materiais,

        $valor_mao_obra,

        $valor_final

    ]);


    $procedimento_id =
        (int)$pdo->lastInsertId();


    /*
    |--------------------------------------------------------------------------
    | Registrar materiais e baixar estoque
    |--------------------------------------------------------------------------
    */

    $stmtMaterial = $pdo->prepare("
        INSERT INTO procedimento_materiais (
            procedimento_id,
            estoque_id,
            quantidade,
            valor_unitario,
            valor_total
        )
        VALUES (
            ?,
            ?,
            ?,
            ?,
            ?
        )
    ");


    $stmtEstoque = $pdo->prepare("
        UPDATE estoque
        SET quantidade = quantidade - ?
        WHERE id = ?
        AND quantidade >= ?
    ");


    foreach ($materiaisCalculados as $material) {

        /*
        |--------------------------------------------------------------
        | Baixar estoque
        |--------------------------------------------------------------
        */

        $stmtEstoque->execute([

            $material['quantidade'],

            $material['estoque_id'],

            $material['quantidade']

        ]);


        if ($stmtEstoque->rowCount() !== 1) {

            throw new Exception(
                'Não foi possível atualizar o estoque. ' .
                    'A quantidade disponível pode ter sido alterada.'
            );
        }


        /*
        |--------------------------------------------------------------
        | Registrar material utilizado
        |--------------------------------------------------------------
        */

        $stmtMaterial->execute([

            $procedimento_id,

            $material['estoque_id'],

            $material['quantidade'],

            $material['valor_unitario'],

            $material['subtotal']

        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Confirmar tudo
    |--------------------------------------------------------------------------
    */

    $pdo->commit();


    responder(true, [

        'message' =>
        'Procedimento adicionado com sucesso.',

        'procedimento_id' =>
        $procedimento_id,

        'valor_materiais' =>
        $valor_materiais,

        'valor_sugerido_materiais' =>
        $valor_sugerido_materiais,

        'valor_mao_obra' =>
        $valor_mao_obra,

        'valor_final' =>
        $valor_final

    ]);
} catch (Throwable $e) {

    /*
    |--------------------------------------------------------------------------
    | Desfazer tudo
    |--------------------------------------------------------------------------
    */

    if ($pdo->inTransaction()) {

        $pdo->rollBack();
    }


    error_log(
        'Erro ao salvar procedimento: ' .
            $e->getMessage()
    );


    responder(false, [

        'error' =>
        $e->getMessage()

    ]);
}
