<?php

declare(strict_types=1);

namespace Cofrelo\Suporte;

/** Renderiza os arquivos da pasta views. O conteúdo da página é colocado dentro de um layout. */
final class Visao
{
    /** @param array<string, mixed> $dados */
    public static function renderizar(string $visao, array $dados = [], ?string $layout = 'layout'): string
    {
        $conteudo = self::incluir($visao, $dados);
        if ($layout === null) {
            return $conteudo;
        }
        return self::incluir($layout, ['conteudo' => $conteudo] + $dados);
    }

    /** @param array<string, mixed> $dados */
    private static function incluir(string $visao, array $dados): string
    {
        if (preg_match('#^[a-z0-9/-]+$#', $visao) !== 1) {
            throw new \InvalidArgumentException("Nome de view inválido: {$visao}");
        }
        $arquivo = COFRELO_RAIZ . '/views/' . $visao . '.php';
        extract($dados, EXTR_SKIP);
        ob_start();
        try {
            require $arquivo;
        } catch (\Throwable $erro) {
            ob_end_clean();
            throw $erro;
        }
        return (string) ob_get_clean();
    }
}
