<?php

declare(strict_types=1);

namespace Cofrelo\Testes\Integracao;

use Cofrelo\Banco\DadosDeExemplo;
use Cofrelo\Repositorio\ContaRepositorio;
use Cofrelo\Repositorio\MetaRepositorio;
use Cofrelo\Repositorio\TransacaoRepositorio;
use Cofrelo\Servico\Indicadores;
use DateTimeImmutable;

final class DadosDeExemploTest extends TesteComBanco
{
    public function testDadosDeExemploReproduzemOsNumerosDoPrototipo(): void
    {
        $usuario = (new DadosDeExemplo(self::$pdo, new DateTimeImmutable('2026-11-26')))->criar();
        $transacoes = new TransacaoRepositorio(self::$pdo);

        $totais = $transacoes->totais($usuario, '2026-11-01', '2026-11-30');
        self::assertSame(8500.0, $totais['receitas']);
        self::assertSame(3920.0, $totais['despesas']);
        self::assertSame(2, $totais['qtd_receitas']);
        self::assertSame(22, $totais['qtd_despesas']);

        $categorias = [];
        foreach ($transacoes->despesasPorCategoria($usuario, '2026-11-01', '2026-11-30') as $categoria) {
            $categorias[$categoria['nome']] = $categoria['total'];
        }
        self::assertSame(['Moradia' => 45, 'Alimentação' => 22, 'Saúde' => 17, 'Transporte' => 11, 'Assinaturas' => 5], Indicadores::distribuicao($categorias));

        $anterior = $transacoes->totais($usuario, '2026-10-01', '2026-10-31');
        self::assertSame(4090.0, $anterior['despesas']);
    }

    public function testSaldosDasContasEMetasFicamCoerentes(): void
    {
        $usuario = (new DadosDeExemplo(self::$pdo, new DateTimeImmutable('2026-11-26')))->criar();
        $contas = (new ContaRepositorio(self::$pdo))->listar($usuario);

        self::assertSame([10580.0, 2000.0, 12000.0], array_map(static fn ($c) => round($c->saldoAtual, 2), $contas));
        foreach ($contas as $conta) {
            self::assertGreaterThanOrEqual(0, $conta->saldoInicial, "Saldo inicial negativo na conta {$conta->nome}");
        }

        $metas = (new MetaRepositorio(self::$pdo))->listar($usuario);
        self::assertCount(3, $metas);
        self::assertSame(60.0, $metas[0]->paraArray(new DateTimeImmutable('2026-11-26'))['percentual']);
        $depositos = (new MetaRepositorio(self::$pdo))->depositos($metas[0]->id);
        self::assertSame(12000.0, array_sum(array_column($depositos, 'valor')));
    }
}
