<?php

declare(strict_types=1);

namespace Cofrelo\Suporte;

/** Gera endereços respeitando a pasta onde o site está (raiz no Docker, subpasta no XAMPP). */
final class Url
{
    private static string $base = '';

    public static function definirBase(string $base): void
    {
        self::$base = rtrim($base, '/');
    }

    public static function base(): string
    {
        return self::$base;
    }

    public static function para(string $caminho = '/'): string
    {
        return self::$base . '/' . ltrim($caminho, '/');
    }

    /** Arquivo estático com a data de modificação na URL, para o navegador baixar de novo quando mudar. */
    public static function recurso(string $caminho): string
    {
        $arquivo = COFRELO_RAIZ . '/public/' . ltrim($caminho, '/');
        $versao = is_file($arquivo) ? (string) filemtime($arquivo) : '1';
        return self::para($caminho) . '?v=' . $versao;
    }
}
