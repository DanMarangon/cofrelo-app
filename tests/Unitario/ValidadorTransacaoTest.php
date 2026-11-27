<?php

declare(strict_types=1);

namespace Cofrelo\Testes\Unitario;

use Cofrelo\Servico\ValidadorTransacao;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ValidadorTransacaoTest extends TestCase
{
    private const CONTAS = [1, 2, 3];
    private const CATEGORIAS = [10 => 'despesa', 20 => 'receita'];

    /**
     * @param array<string, mixed> $alteracoes
     * @return array<string, mixed>
     */
    private function dados(array $alteracoes = []): array
    {
        return array_merge([
            'tipo' => 'despesa',
            'valor' => 286.40,
            'descricao' => 'Supermercado',
            'data' => '2026-11-18',
            'conta_id' => 1,
            'categoria_id' => 10,
            'forma_pagamento' => 'debito',
            'status' => 'pago',
        ], $alteracoes);
    }

    /**
     * @return array{0: array<string, string>, 1: array<string, mixed>}
     * @param array<string, mixed> $dados
     */
    private function validar(array $dados): array
    {
        return (new ValidadorTransacao())->validar($dados, self::CONTAS, self::CATEGORIAS, new DateTimeImmutable('2026-11-26'));
    }

    public function testAceitaDespesaValidaENormalizaOsDados(): void
    {
        [$erros, $dados] = $this->validar($this->dados(['descricao' => '  Supermercado  ']));

        self::assertSame([], $erros);
        self::assertSame(286.4, $dados['valor']);
        self::assertSame('Supermercado', $dados['descricao']);
        self::assertNull($dados['observacao']);
        self::assertFalse($dados['assinatura']);
    }

    #[DataProvider('valoresInvalidos')]
    public function testRecusaValorInvalido(mixed $valor): void
    {
        [$erros] = $this->validar($this->dados(['valor' => $valor]));

        self::assertArrayHasKey('valor', $erros);
    }

    /** @return array<string, array{0: mixed}> */
    public static function valoresInvalidos(): array
    {
        return [
            'zero' => [0],
            'negativo' => [-10],
            'texto' => ['abc'],
            'tres casas decimais' => [10.555],
        ];
    }

    public function testAceitaValorDigitadoNoFormatoBrasileiro(): void
    {
        [$erros, $dados] = $this->validar($this->dados(['valor' => '1.234,56']));

        self::assertSame([], $erros);
        self::assertSame(1234.56, $dados['valor']);
    }

    public function testRecusaDescricaoCurtaDemais(): void
    {
        [$erros] = $this->validar($this->dados(['descricao' => 'a']));

        self::assertArrayHasKey('descricao', $erros);
    }

    public function testRecusaContaQueNaoEDoUsuario(): void
    {
        [$erros] = $this->validar($this->dados(['conta_id' => 99]));

        self::assertArrayHasKey('conta_id', $erros);
    }

    public function testTransferenciaPrecisaDeContaDeDestinoDiferente(): void
    {
        [$erros] = $this->validar($this->dados(['tipo' => 'transferencia', 'conta_destino_id' => 1]));
        self::assertArrayHasKey('conta_destino_id', $erros);

        [$erros, $dados] = $this->validar($this->dados(['tipo' => 'transferencia', 'conta_destino_id' => 3]));
        self::assertSame([], $erros);
        self::assertNull($dados['categoria_id']);
        self::assertSame('transferencia', $dados['forma_pagamento']);
    }

    public function testCategoriaPrecisaCombinarComOTipo(): void
    {
        [$erros] = $this->validar($this->dados(['tipo' => 'receita', 'categoria_id' => 10]));

        self::assertSame('Essa categoria é de despesa. Escolha uma categoria de receita.', $erros['categoria_id'] ?? null);
    }

    public function testAssinaturaPrecisaDeDiaDeCobrancaEntre1E28(): void
    {
        [$erros] = $this->validar($this->dados(['assinatura' => true, 'dia_cobranca' => 31]));
        self::assertArrayHasKey('dia_cobranca', $erros);

        [$erros, $dados] = $this->validar($this->dados(['assinatura' => true, 'dia_cobranca' => 12, 'aviso_dias' => 2]));
        self::assertSame([], $erros);
        self::assertSame(12, $dados['dia_cobranca']);
    }

    public function testRecusaDataQueNaoExiste(): void
    {
        [$erros] = $this->validar($this->dados(['data' => '2026-02-30']));

        self::assertArrayHasKey('data', $erros);
    }
}
