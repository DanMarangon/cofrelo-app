<?php

declare(strict_types=1);

/*
 * Ponto de partida comum do Cofrelo: carrega as classes, lê a configuração e acerta o fuso horário.
 * É usado pelo site (public/index.php), pelos scripts de linha de comando (bin/) e pelos testes.
 */

if (defined('COFRELO_RAIZ')) {
    return;
}

define('COFRELO_RAIZ', dirname(__DIR__));

spl_autoload_register(static function (string $classe): void {
    $prefixo = 'Cofrelo\\';
    if (strncmp($classe, $prefixo, strlen($prefixo)) !== 0) {
        return;
    }
    $relativo = str_replace('\\', '/', substr($classe, strlen($prefixo)));
    $arquivo = COFRELO_RAIZ . '/src/' . $relativo . '.php';
    if (is_file($arquivo)) {
        require $arquivo;
    }
});

require __DIR__ . '/Suporte/ajudantes.php';

// Nos testes a configuração vem só das variáveis de ambiente, para nunca usar o banco do .env por engano.
if (getenv('APP_ENV') !== 'testing') {
    Cofrelo\Config::carregar(COFRELO_RAIZ . '/.env');
}

date_default_timezone_set(Cofrelo\Config::texto('APP_TIMEZONE', 'America/Sao_Paulo'));
mb_internal_encoding('UTF-8');
