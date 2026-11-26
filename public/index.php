<?php

declare(strict_types=1);

// Toda requisição passa por aqui (o Apache manda para este arquivo o que não for CSS, JS ou imagem).
require dirname(__DIR__) . '/src/bootstrap.php';

(new Cofrelo\Aplicacao())->executar();
