<?php

declare(strict_types=1);

namespace Cofrelo\Suporte;

/**
 * Contadores de requisições guardados na memória compartilhada do PHP (APCu) e expostos em /metrics
 * no formato do Prometheus. Sem APCu (por exemplo, no XAMPP), os contadores ficam desligados.
 */
final class Metricas
{
    private const PREFIXO = 'cofrelo_metrica_';

    public static function disponivel(): bool
    {
        return function_exists('apcu_enabled') && apcu_enabled();
    }

    public static function registrarRequisicao(string $metodo, string $rota, int $status, float $duracaoMs): void
    {
        if (!self::disponivel()) {
            return;
        }
        $classe = intdiv($status, 100) . 'xx';
        self::somar("requisicoes|{$metodo}|{$rota}|{$classe}", 1);
        self::somar('duracao_ms_soma', $duracaoMs);
        self::somar('duracao_ms_contagem', 1);
        if ($status >= 500) {
            self::somar('erros', 1);
        }
    }

    /** @return array{requisicoes: array<string, float>, duracao_soma: float, duracao_contagem: float, erros: float} */
    public static function ler(): array
    {
        $dados = ['requisicoes' => [], 'duracao_soma' => 0.0, 'duracao_contagem' => 0.0, 'erros' => 0.0];
        if (!self::disponivel()) {
            return $dados;
        }
        $iterador = new \APCUIterator('/^' . preg_quote(self::PREFIXO, '/') . '/');
        foreach ($iterador as $item) {
            $chave = substr((string) $item['key'], strlen(self::PREFIXO));
            $valor = (float) $item['value'];
            match (true) {
                str_starts_with($chave, 'requisicoes|') => $dados['requisicoes'][substr($chave, 12)] = $valor,
                $chave === 'duracao_ms_soma' => $dados['duracao_soma'] = $valor,
                $chave === 'duracao_ms_contagem' => $dados['duracao_contagem'] = $valor,
                $chave === 'erros' => $dados['erros'] = $valor,
                default => null,
            };
        }
        return $dados;
    }

    private static function somar(string $chave, float $valor): void
    {
        $completa = self::PREFIXO . $chave;
        // apcu_inc só soma inteiros; a duração usa uma leitura e escrita simples.
        if (floor($valor) === $valor) {
            apcu_add($completa, 0);
            apcu_inc($completa, (int) $valor);
            return;
        }
        apcu_store($completa, (float) apcu_fetch($completa) + $valor);
    }
}
