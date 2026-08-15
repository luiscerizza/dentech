<?php

// Ponto de entrada PHP do Dentech para o Vercel

// Caminho absoluto da raiz do projeto
$root = dirname(__DIR__);

// Pega o caminho solicitado
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

// Remove barras extras
$path = '/' . trim($path, '/');

// Página inicial
if ($path === '/') {
    $arquivo = 'login.php';
} else {
    // Remove a barra inicial
    $arquivo = ltrim($path, '/');

    // Segurança: impede acesso a arquivos/diretórios fora do projeto
    if (
        str_contains($arquivo, '..') ||
        str_contains($arquivo, "\0")
    ) {
        http_response_code(400);
        exit('Requisição inválida.');
    }
}

// Só permite arquivos PHP existentes na raiz do projeto
$caminho = $root . '/' . $arquivo;

if (!is_file($caminho)) {
    http_response_code(404);
    exit('Página não encontrada.');
}

// Só executa PHP
if (strtolower(pathinfo($caminho, PATHINFO_EXTENSION)) !== 'php') {
    http_response_code(404);
    exit('Página não encontrada.');
}

// Executa o arquivo solicitado
chdir($root);
require $caminho;