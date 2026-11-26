<?php

declare(strict_types=1);

namespace Cofrelo\Testes\Unitario;

use Cofrelo\Suporte\Formatador;
use PHPUnit\Framework\TestCase;

final class FormatadorTest extends TestCase
{
    public function testMoedaNoPadraoBrasileiro(): void
    {
        self::assertSame('R$ 24.580,00', Formatador::moeda(24580));
        self::assertSame('-R$ 210,00', Formatador::moeda(-210));
    }

    public function testDatasEMeses(): void
    {
        self::assertSame('18/11/2026', Formatador::data('2026-11-18'));
        self::assertSame('Novembro de 2026', Formatador::mesAno('2026-11'));
        self::assertSame('Mar', Formatador::mesCurto(3));
    }
}
