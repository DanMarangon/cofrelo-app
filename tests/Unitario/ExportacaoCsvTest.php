<?php

declare(strict_types=1);

namespace Cofrelo\Testes\Unitario;

use Cofrelo\Servico\Relatorio;
use PHPUnit\Framework\TestCase;

/** A exportação em CSV não pode deixar o Excel executar uma descrição como fórmula. */
final class ExportacaoCsvTest extends TestCase
{
    public function testCelulasQueViraramFormulaGanhamApostrofo(): void
    {
        self::assertSame("'=HYPERLINK(\"http://exemplo.com\")", Relatorio::celulaSegura('=HYPERLINK("http://exemplo.com")'));
        self::assertSame("'+5561999990000", Relatorio::celulaSegura('+5561999990000'));
        self::assertSame("'@SOMA(A1:A9)", Relatorio::celulaSegura('@SOMA(A1:A9)'));
        self::assertSame("'-2+3", Relatorio::celulaSegura('-2+3'));
        self::assertSame("'\tTab", Relatorio::celulaSegura("\tTab"));
    }

    public function testNumerosETextosComunsFicamComoEstao(): void
    {
        self::assertSame('-1.234,56', Relatorio::celulaSegura('-1.234,56'));
        self::assertSame('-42,50', Relatorio::celulaSegura('-42,50'));
        self::assertSame('8.500,00', Relatorio::celulaSegura('8.500,00'));
        self::assertSame('Supermercado', Relatorio::celulaSegura('Supermercado'));
        self::assertSame('', Relatorio::celulaSegura(''));
    }

    public function testArquivoGeradoTrazAsCelulasProtegidas(): void
    {
        $csv = Relatorio::montarCsv([['Descrição', 'Valor'], ['=1+1', '-12,50']]);
        self::assertSame("Descrição;Valor\n'=1+1;-12,50\n", $csv);
    }
}
