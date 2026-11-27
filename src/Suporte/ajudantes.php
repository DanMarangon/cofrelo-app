<?php

declare(strict_types=1);

/*
 * Funções curtas usadas nas páginas (views). Ficam no escopo global para o HTML continuar legível.
 */

use Cofrelo\Suporte\Url;

if (!function_exists('e')) {
    /** Escapa o texto antes de colocar no HTML (evita XSS). */
    function e(mixed $valor): string
    {
        return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('url')) {
    function url(string $caminho = '/'): string
    {
        return Url::para($caminho);
    }
}

if (!function_exists('recurso')) {
    function recurso(string $caminho): string
    {
        return Url::recurso($caminho);
    }
}
