<?php

declare(strict_types=1);

namespace Cofrelo;

/**
 * Lê a configuração do ambiente.
 *
 * Primeiro valem as variáveis de ambiente reais (Docker, GitHub Actions); depois o arquivo .env, útil no XAMPP.
 * Senhas nunca ficam no código: os valores padrão abaixo servem só para rodar localmente.
 */
final class Config
{
    /** @var array<string, string> */
    private static array $arquivo = [];

    public static function carregar(string $caminho): void
    {
        self::$arquivo = [];
        if (!is_file($caminho)) {
            return;
        }
        foreach (file($caminho, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $linha) {
            $linha = trim($linha);
            if ($linha === '' || $linha[0] === '#' || !str_contains($linha, '=')) {
                continue;
            }
            [$chave, $valor] = array_map('trim', explode('=', $linha, 2));
            self::$arquivo[$chave] = trim($valor, "\"'");
        }
    }

    public static function texto(string $chave, string $padrao = ''): string
    {
        $valor = getenv($chave);
        if ($valor !== false && $valor !== '') {
            return $valor;
        }
        if (isset($_ENV[$chave]) && $_ENV[$chave] !== '') {
            return (string) $_ENV[$chave];
        }
        return self::$arquivo[$chave] ?? $padrao;
    }

    public static function inteiro(string $chave, int $padrao): int
    {
        $valor = self::texto($chave);
        return is_numeric($valor) ? (int) $valor : $padrao;
    }

    public static function booleano(string $chave, bool $padrao): bool
    {
        $valor = strtolower(self::texto($chave));
        if ($valor === '') {
            return $padrao;
        }
        return in_array($valor, ['1', 'true', 'sim', 'yes', 'on'], true);
    }

    public static function ambiente(): string
    {
        return self::texto('APP_ENV', 'production');
    }

    public static function emTeste(): bool
    {
        return self::ambiente() === 'testing';
    }

    public static function emDesenvolvimento(): bool
    {
        return self::ambiente() === 'development';
    }

    public static function versao(): string
    {
        return self::texto('APP_VERSION', 'dev');
    }

    public static function commit(): string
    {
        return self::texto('APP_COMMIT', 'local');
    }
}
