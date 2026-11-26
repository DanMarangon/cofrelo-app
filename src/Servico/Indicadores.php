<?php

declare(strict_types=1);

namespace Cofrelo\Servico;

/** Cálculos dos indicadores mostrados na visão geral e nos relatórios. */
final class Indicadores
{
    /** Variação em % entre dois períodos. Sem valor anterior não há base de comparação, então devolve null. */
    public static function variacao(float $atual, float $anterior): ?float
    {
        if (abs($anterior) < 0.005) {
            return null;
        }
        return round(($atual - $anterior) / abs($anterior) * 100, 1);
    }

    /** Quanto da renda sobrou no período (RN10). */
    public static function taxaEconomia(float $receitas, float $despesas): ?float
    {
        if ($receitas <= 0) {
            return null;
        }
        return round(($receitas - $despesas) / $receitas * 100, 1);
    }

    public static function percentualOrcamento(float $usado, float $orcamento): ?float
    {
        if ($orcamento <= 0) {
            return null;
        }
        return round($usado / $orcamento * 100, 1);
    }

    /**
     * Distribui 100% entre os valores em números inteiros pelo método dos maiores restos,
     * para que a soma mostrada na tela seja sempre exatamente 100%.
     *
     * @param array<int|string, float> $valores
     * @return array<int|string, int>
     */
    public static function distribuicao(array $valores): array
    {
        $total = array_sum($valores);
        if ($total <= 0) {
            return array_map(static fn (): int => 0, $valores);
        }
        $inteiros = [];
        $restos = [];
        foreach ($valores as $chave => $valor) {
            $exato = $valor / $total * 100;
            $inteiros[$chave] = (int) floor($exato);
            $restos[$chave] = $exato - floor($exato);
        }
        $faltam = 100 - array_sum($inteiros);
        arsort($restos);
        foreach (array_keys($restos) as $chave) {
            if ($faltam <= 0) {
                break;
            }
            $inteiros[$chave]++;
            $faltam--;
        }
        return $inteiros;
    }
}
