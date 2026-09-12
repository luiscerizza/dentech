<?php

/**
 * Carrega as variáveis de ambiente locais do Evoluqi.
 *
 * Em produção (Vercel), o arquivo .env.local não existe no deploy,
 * então as variáveis configuradas no próprio ambiente da Vercel
 * continuam sendo utilizadas normalmente.
 */

$envFile = dirname(__DIR__) . '/.env.local';

if (is_file($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        // Ignora comentários
        if ($line === '' || substr($line, 0, 1) === '#') {
            continue;
        }

        // Procura pelo primeiro "="
        $separator = strpos($line, '=');

        if ($separator === false) {
            continue;
        }

        $name = trim(substr($line, 0, $separator));
        $value = trim(substr($line, $separator + 1));

        if ($name === '') {
            continue;
        }

        // Remove aspas externas
        if (
            strlen($value) >= 2 &&
            (
                ($value[0] === '"' && $value[strlen($value) - 1] === '"') ||
                ($value[0] === "'" && $value[strlen($value) - 1] === "'")
            )
        ) {
            $value = substr($value, 1, -1);
        }

        // Não sobrescreve variáveis que já existam no ambiente.
        if (getenv($name) === false) {
            putenv($name . '=' . $value);
        }
    }
}
