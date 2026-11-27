<?php

declare(strict_types=1);

namespace Cofrelo\Testes\Unitario;

use Cofrelo\Servico\CalculadoraMeta;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class CalculadoraMetaTest extends TestCase
{
    public function testPercentualDaReservaDeEmergencia(): void
    {
        self::assertSame(60.0, CalculadoraMeta::percentual(12000, 20000));
        self::assertSame(8000.0, CalculadoraMeta::falta(12000, 20000));
    }

    public function testPercentualNuncaPassaDeCemENadaFaltaQuandoAtinge(): void
    {
        self::assertSame(100.0, CalculadoraMeta::percentual(25000, 20000));
        self::assertSame(0.0, CalculadoraMeta::falta(25000, 20000));
        self::assertSame(0.0, CalculadoraMeta::percentual(500, 0));
    }

    public function testSituacaoDaMetaConformeOValorGuardado(): void
    {
        self::assertSame(CalculadoraMeta::PRONTA, CalculadoraMeta::status(0, 8000));
        self::assertSame(CalculadoraMeta::EM_PROGRESSO, CalculadoraMeta::status(12000, 20000));
        self::assertSame(CalculadoraMeta::CONCLUIDA, CalculadoraMeta::status(20000, 20000));
    }

    public function testAporteMensalSemRendimento(): void
    {
        self::assertSame(615.38, CalculadoraMeta::aporteMensal(12000, 20000, 13));
    }

    public function testAporteMensalComRendimentoMensal(): void
    {
        self::assertSame(381.33, CalculadoraMeta::aporteMensal(0, 8000, 20, 0.5));
    }

    public function testMesesRestantesAteOPrazo(): void
    {
        $hoje = new DateTimeImmutable('2026-11-26');
        self::assertSame(14, CalculadoraMeta::mesesRestantes($hoje, new DateTimeImmutable('2027-12-31')));
        self::assertSame(0, CalculadoraMeta::mesesRestantes($hoje, new DateTimeImmutable('2026-01-31')));
        self::assertNull(CalculadoraMeta::mesesRestantes($hoje, null));
    }
}
