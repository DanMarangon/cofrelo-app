<?php

declare(strict_types=1);

namespace Cofrelo\Suporte;

use Cofrelo\Config;

/**
 * Registro de execução em JSON, uma linha por evento.
 *
 * No Docker as linhas vão para a saída de erro do container (aparecem em `docker compose logs app`).
 * No XAMPP, defina LOG_DESTINO com o caminho de um arquivo para gravar lá.
 */
final class Log
{
    private static ?string $requisicao = null;

    public static function definirRequisicao(?string $id): void
    {
        self::$requisicao = $id;
    }

    public static function requisicao(): ?string
    {
        return self::$requisicao;
    }

    /** @param array<string, mixed> $dados */
    public static function info(string $evento, array $dados = []): void
    {
        self::escrever('info', $evento, $dados);
    }

    /** @param array<string, mixed> $dados */
    public static function aviso(string $evento, array $dados = []): void
    {
        self::escrever('aviso', $evento, $dados);
    }

    /** @param array<string, mixed> $dados */
    public static function erro(string $evento, array $dados = []): void
    {
        self::escrever('erro', $evento, $dados);
    }

    /** @param array<string, mixed> $dados */
    private static function escrever(string $nivel, string $evento, array $dados): void
    {
        if (Config::emTeste() && !Config::booleano('LOG_EM_TESTE', false)) {
            return;
        }
        $registro = ['ts' => date('c'), 'nivel' => $nivel, 'evento' => $evento];
        if (self::$requisicao !== null) {
            $registro['requisicao'] = self::$requisicao;
        }
        $linha = json_encode($registro + $dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) . PHP_EOL;

        $destino = Config::texto('LOG_DESTINO', 'stderr');
        if ($destino === 'stderr') {
            file_put_contents('php://stderr', $linha);
            return;
        }
        @file_put_contents($destino, $linha, FILE_APPEND | LOCK_EX);
    }
}
