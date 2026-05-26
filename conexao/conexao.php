<?php
$host = "localhost";
$db   = "dentech";
$user = "root";
$pass = "usbw";
$charset = "utf8mb4";

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die("Erro ao conectar: " . $e->getMessage());
}

/**
 * Registra uma ação no sistema de Logs
 * 
 * @param PDO 
 * @param string 
 * @param string 
 * @param int|null 
 * @param string 
 */
function registrarLog($pdo, $acao, $tabela = null, $id = null, $detalhes = '')
{
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Desconhecido';
        $usuario = $_SESSION['restricted_access'] ? 'Admin' : 'Visitante';

        $stmt = $pdo->prepare("
            INSERT INTO logs (usuario, acao, tabela, registro_id, detalhes, ip)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$usuario, $acao, $tabela, $id, $detalhes, $ip]);
    } catch (Exception $e) {
        error_log("Erro ao registrar Log: " . $e->getMessage());
    }
}
