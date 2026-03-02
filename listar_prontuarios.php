<?php
header('Content-Type: application/json');
include 'conexao/conexao.php';

$stmt = $pdo->query("SELECT * FROM prontuarios ORDER BY paciente ASC");
$prontuarios = $stmt->fetchAll();

echo json_encode($prontuarios);
?>
