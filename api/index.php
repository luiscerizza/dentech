<?php

// Ponto de entrada do Dentech no Vercel

$root = dirname(__DIR__);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = '/' . ltrim($path, '/');

// Segurança
if (
    str_contains($path, '..') ||
    str_contains($path, "\0")
) {
    http_response_code(400);
    exit('Requisição inválida.');
}

// ---------------------------------------------------------
// ARQUIVOS ESTÁTICOS
// ---------------------------------------------------------

$staticExtensions = [
    'css'   => 'text/css',
    'js'    => 'application/javascript',
    'png'   => 'image/png',
    'jpg'   => 'image/jpeg',
    'jpeg'  => 'image/jpeg',
    'gif'   => 'image/gif',
    'webp'  => 'image/webp',
    'svg'   => 'image/svg+xml',
    'ico'   => 'image/x-icon',
    'woff'  => 'font/woff',
    'woff2' => 'font/woff2',
    'ttf'   => 'font/ttf',
];

$extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

if (isset($staticExtensions[$extension])) {
    $arquivo = $root . $path;

    if (is_file($arquivo)) {
        header('Content-Type: ' . $staticExtensions[$extension]);
        readfile($arquivo);
        exit;
    }

    http_response_code(404);
    exit('Arquivo não encontrado.');
}

// ---------------------------------------------------------
// PÁGINA PHP
// ---------------------------------------------------------

if ($path === '/') {
    $arquivo = 'login.php';
} else {
    $arquivo = ltrim($path, '/');
}

$caminho = $root . '/' . $arquivo;

// Só permite PHP
if (strtolower(pathinfo($caminho, PATHINFO_EXTENSION)) !== 'php') {
    http_response_code(404);
    exit('Página não encontrada.');
}

if (!is_file($caminho)) {
    http_response_code(404);
    exit('Página não encontrada.');
}

// Faz os caminhos relativos funcionarem
chdir($root);

// Executa a página PHP
require $caminho;