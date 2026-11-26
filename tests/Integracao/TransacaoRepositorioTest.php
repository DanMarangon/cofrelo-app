<?php

declare(strict_types=1);

namespace Cofrelo\Testes\Integracao;

use Cofrelo\Repositorio\ContaRepositorio;
use Cofrelo\Repositorio\TransacaoRepositorio;

final class TransacaoRepositorioTest extends TesteComBanco
{
    private TransacaoRepositorio $transacoes;
    private ContaRepositorio $contas;
    private int $usuario;
    private int $conta;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transacoes = new TransacaoRepositorio(self::$pdo);
        $this->contas = new ContaRepositorio(self::$pdo);
        $this->usuario = $this->criarUsuario();
        $this->conta = $this->criarConta($this->usuario, 'Conta principal', 1000.00);
    }

    /** @param array<string, mixed> $alteracoes */
    private function despesa(array $alteracoes = []): array
    {
        return array_merge([
            'tipo' => 'despesa',
            'conta_id' => $this->conta,
            'categoria_id' => $this->categoria('Alimentação'),
            'descricao' => 'Supermercado',
            'valor' => 286.40,
            'data' => '2026-11-18',
            'forma_pagamento' => 'debito',
            'status' => 'pago',
        ], $alteracoes);
    }

    public function testCriaConsultaAtualizaEExcluiUmaTransacao(): void
    {
        $id = $this->transacoes->criar($this->usuario, $this->despesa());

        $transacao = $this->transacoes->buscar($this->usuario, $id);
        self::assertNotNull($transacao);
        self::assertSame('Supermercado', $transacao->descricao);
        self::assertSame('Alimentação', $transacao->categoriaNome);
        self::assertSame('Pago', $transacao->statusRotulo());

        $this->transacoes->atualizar($this->usuario, $id, $this->despesa(['descricao' => 'Feira', 'valor' => 65.00]));
        self::assertSame(65.0, $this->transacoes->buscar($this->usuario, $id)?->valor);

        self::assertTrue($this->transacoes->excluir($this->usuario, $id));
        self::assertNull($this->transacoes->buscar($this->usuario, $id));
    }

    public function testOutroUsuarioNaoEnxergaNemApagaATransacao(): void
    {
        $id = $this->transacoes->criar($this->usuario, $this->despesa());
        $intruso = $this->criarUsuario('intruso@exemplo.com');

        self::assertNull($this->transacoes->buscar($intruso, $id));
        self::assertFalse($this->transacoes->excluir($intruso, $id));
    }

    public function testSaldoConsideraSoTransacoesPagasETransferencias(): void
    {
        $reserva = $this->criarConta($this->usuario, 'Reserva', 0.0);
        $this->transacoes->criar($this->usuario, [
            'tipo' => 'receita', 'conta_id' => $this->conta, 'categoria_id' => $this->categoria('Salário', 'receita'),
            'descricao' => 'Salário', 'valor' => 5000.00, 'data' => '2026-11-05', 'status' => 'pago',
        ]);
        $this->transacoes->criar($this->usuario, $this->despesa(['valor' => 300.00]));
        $this->transacoes->criar($this->usuario, $this->despesa(['valor' => 999.00, 'status' => 'pendente']));
        $this->transacoes->criar($this->usuario, [
            'tipo' => 'transferencia', 'conta_id' => $this->conta, 'conta_destino_id' => $reserva,
            'descricao' => 'Guardar', 'valor' => 700.00, 'data' => '2026-11-10', 'status' => 'pago',
        ]);

        // 1000 + 5000 - 300 - 700 (a despesa pendente de 999 ainda não saiu da conta)
        self::assertSame(5000.0, $this->contas->buscar($this->usuario, $this->conta)?->saldoAtual);
        self::assertSame(700.0, $this->contas->buscar($this->usuario, $reserva)?->saldoAtual);
        self::assertSame(5700.0, $this->contas->patrimonio($this->usuario));
    }

    public function testFiltraPorTipoPeriodoEBuscaPeloValor(): void
    {
        $this->transacoes->criar($this->usuario, $this->despesa());
        $this->transacoes->criar($this->usuario, $this->despesa(['descricao' => 'Padaria', 'valor' => 38.60, 'data' => '2026-10-03']));
        $this->transacoes->criar($this->usuario, [
            'tipo' => 'receita', 'conta_id' => $this->conta, 'categoria_id' => $this->categoria('Freelance', 'receita'),
            'descricao' => 'Projeto', 'valor' => 1000.00, 'data' => '2026-11-20', 'status' => 'pago',
        ]);

        $novembro = $this->transacoes->listar($this->usuario, ['inicio' => '2026-11-01', 'fim' => '2026-11-30']);
        self::assertSame(2, $novembro['total']);

        $despesas = $this->transacoes->listar($this->usuario, ['tipo' => 'despesa']);
        self::assertSame(2, $despesas['total']);

        $porValor = $this->transacoes->listar($this->usuario, ['busca' => '38,60']);
        self::assertSame(['Padaria'], array_map(static fn ($t) => $t->descricao, $porValor['itens']));

        $totais = $this->transacoes->totais($this->usuario, '2026-11-01', '2026-11-30');
        self::assertSame(['receitas' => 1000.0, 'despesas' => 286.4, 'qtd_receitas' => 1, 'qtd_despesas' => 1], $totais);
    }
}
