<?php

declare(strict_types=1);

namespace Cofrelo\Servico;

use DateTimeImmutable;

/** Progresso, situação e quanto guardar por mês em cada meta (RN08 e RN09). */
final class CalculadoraMeta
{
    public const PRONTA = 'pronta';
    public const EM_PROGRESSO = 'em_progresso';
    public const CONCLUIDA = 'concluida';

    public const ROTULOS = [
        self::PRONTA => 'Pronta para começar',
        self::EM_PROGRESSO => 'Em progresso',
        self::CONCLUIDA => 'Concluída',
    ];

    public static function percentual(float $atual, float $objetivo): float
    {
        if ($objetivo <= 0) {
            return 0.0;
        }
        return round(min(100, max(0, $atual / $objetivo * 100)), 1);
    }

    public static function falta(float $atual, float $objetivo): float
    {
        return round(max(0, $objetivo - $atual), 2);
    }

    public static function status(float $atual, float $objetivo): string
    {
        if ($objetivo > 0 && $atual >= $objetivo) {
            return self::CONCLUIDA;
        }
        return $atual > 0 ? self::EM_PROGRESSO : self::PRONTA;
    }

    /** Meses que ainda faltam até o prazo, contando o mês em andamento. Prazo vencido devolve 0. */
    public static function mesesRestantes(DateTimeImmutable $hoje, ?DateTimeImmutable $limite): ?int
    {
        if ($limite === null) {
            return null;
        }
        if ($limite < $hoje) {
            return 0;
        }
        $diferenca = $hoje->diff($limite);
        $meses = $diferenca->y * 12 + $diferenca->m + ($diferenca->d > 0 ? 1 : 0);
        return max(1, $meses);
    }

    /**
     * Quanto guardar por mês para chegar ao objetivo no prazo.
     * Com rendimento mensal, usa o valor futuro de uma série de depósitos:
     * objetivo = atual * (1 + i)^n + aporte * ((1 + i)^n - 1) / i
     */
    public static function aporteMensal(float $atual, float $objetivo, int $meses, float $rendimentoMensalPercentual = 0.0): float
    {
        if ($meses <= 0) {
            return self::falta($atual, $objetivo);
        }
        $taxa = $rendimentoMensalPercentual / 100;
        if ($taxa <= 0) {
            return round(max(0, ($objetivo - $atual) / $meses), 2);
        }
        $fator = (1 + $taxa) ** $meses;
        $faltaDepoisDoRendimento = $objetivo - $atual * $fator;
        if ($faltaDepoisDoRendimento <= 0) {
            return 0.0;
        }
        return round($faltaDepoisDoRendimento * $taxa / ($fator - 1), 2);
    }

    /**
     * Previsão (RN14): em quantos meses a meta fica pronta se a pessoa continuar guardando no ritmo atual.
     * A conta é feita em centavos para não errar por arredondamento. Meta concluída devolve 0; sem ritmo, null.
     */
    public static function mesesParaConcluir(float $atual, float $objetivo, float $ritmoMensal): ?int
    {
        $faltaCentavos = (int) round(self::falta($atual, $objetivo) * 100);
        if ($faltaCentavos === 0) {
            return 0;
        }
        $ritmoCentavos = (int) round($ritmoMensal * 100);
        if ($ritmoCentavos <= 0) {
            return null;
        }
        // Arredonda para cima: se sobrar qualquer centavo, ele só entra no mês seguinte.
        return intdiv($faltaCentavos + $ritmoCentavos - 1, $ritmoCentavos);
    }
}
