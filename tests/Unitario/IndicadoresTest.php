<?php

declare(strict_types=1);

namespace Cofrelo\Testes\Unitario;

use Cofrelo\Servico\Indicadores;
use PHPUnit\Framework\TestCase;

final class IndicadoresTest extends TestCase
{
    public function testVariacaoEmRelacaoAoMesAnterior(): void
    {
        self::assertSame(-4.2, Indicadores::variacao(3920, 4090));
        self::assertSame(8.1, Indicadores::variacao(8500, 7860));
        self::assertNull(Indicadores::variacao(100, 0));
    }

    public function testTaxaDeEconomia(): void
    {
        self::assertSame(53.9, Indicadores::taxaEconomia(8500, 3920));
        self::assertNull(Indicadores::taxaEconomia(0, 300));
    }

    public function testPercentualDoOrcamentoUsado(): void
    {
        self::assertSame(65.3, Indicadores::percentualOrcamento(3920, 6000));
        self::assertNull(Indicadores::percentualOrcamento(3920, 0));
    }

    public function testDistribuicaoPorCategoriaSomaSempreCem(): void
    {
        $gastos = ['Moradia' => 1764.90, 'Alimentação' => 872.70, 'Saúde' => 660.80, 'Transporte' => 420.00, 'Assinaturas' => 201.60];

        $percentuais = Indicadores::distribuicao($gastos);

        self::assertSame(['Moradia' => 45, 'Alimentação' => 22, 'Saúde' => 17, 'Transporte' => 11, 'Assinaturas' => 5], $percentuais);
        self::assertSame(100, array_sum($percentuais));
    }

    public function testDistribuicaoSemGastosFicaZerada(): void
    {
        self::assertSame(['a' => 0, 'b' => 0], Indicadores::distribuicao(['a' => 0.0, 'b' => 0.0]));
    }
}
