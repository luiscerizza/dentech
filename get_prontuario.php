<?php
require_once 'config/auth.php';
exigirLogin();
include "conexao/conexao.php";

if (!isset($_GET['id'])) exit;

$id = intval($_GET['id']);
$stmt = $pdo->prepare("SELECT id, paciente, nascimento, telefone, email
FROM prontuarios
WHERE id = ?");
$stmt->execute([$id]);
$prontuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$prontuario) exit;

echo json_encode($prontuario);
