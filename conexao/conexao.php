<?php
$host    = getenv('DB_HOST');
$port    = getenv('DB_PORT') ?: '3306';
$db      = getenv('DB_NAME');
$user    = getenv('DB_USER');
$pass    = getenv('DB_PASS');
$charset = getenv('DB_CHARSET') ?: 'utf8mb4';

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";

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
 * @param PDO $pdo Conexão com o banco
 * @param string $acao Tipo de ação (ex: 'Login', 'Excluir')
 * @param string $tabela Tabela afetada (ex: 'prontuarios')
 * @param int|null $id ID do registro afetado
 * @param string $detalhes Detalhes adicionais (opcional)
 */
function registrarLog($pdo, $acao, $tabela = null, $id = null, $detalhes = '')
{
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Desconhecido';
        // Tenta pegar usuário da sessão restrita, ou 'Visitante'
        $usuario = !empty($_SESSION['restricted_access'])
            ? 'Admin'
            : (!empty($_SESSION['user_logado']) ? 'Usuario' : 'Visitante');

        $stmt = $pdo->prepare("
            INSERT INTO logs (usuario, acao, tabela, registro_id, detalhes, ip)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$usuario, $acao, $tabela, $id, $detalhes, $ip]);
    } catch (Exception $e) {
        // Falha silenciosa para não quebrar o sistema se o log der erro
        error_log("Erro ao registrar Log: " . $e->getMessage());
    }
}
