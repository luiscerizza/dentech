<?php

// Roteador central do Dentech para o Vercel

$root = dirname(__DIR__);

if (!is_dir($root)) {
    http_response_code(500);
    exit('Erro interno: diretório do sistema não encontrado.');
}

// Mantém os caminhos relativos das páginas funcionando
chdir($root);

// Caminho solicitado
$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$requestUri = urldecode($requestUri);

// Remove barras extras
$requestUri = trim($requestUri, '/');

// Página inicial
if ($requestUri === '') {
    $requestUri = 'login';
}

// Remove .php se o usuário colocar
$requestUri = preg_replace('/\.php$/i', '', $requestUri);

// Impede tentativa de acessar diretórios/arquivos fora do projeto
if (
    strpos($requestUri, '..') !== false ||
    strpos($requestUri, '\\') !== false
) {
    http_response_code(400);
    exit('Requisição inválida.');
}

// Converte:
// prontuarios
// novo-orcamento
// novo_orcamento
// etc.
$pagina = $requestUri . '.php';

// Verifica se a página existe
$arquivo = $root . DIRECTORY_SEPARATOR . $pagina;

if (!is_file($arquivo)) {
    http_response_code(404);
    echo '404 - Página não encontrada';
    exit;
}

// Executa a página PHP
require $arquivo;
