<?php

declare(strict_types=1);

namespace Cofrelo\Modelo;

/** Conta onde o dinheiro está: conta corrente, carteira digital, reserva... (a "carteira" do schema original). */
final class Conta
{
    public const TIPOS = [
        'corrente' => 'Conta corrente',
        'pagamento' => 'Conta de pagamento',
        'investimento' => 'Conta de investimento',
        'dinheiro' => 'Dinheiro em espécie',
    ];

    public function __construct(
        public readonly int $id,
        public readonly int $usuarioId,
        public readonly string $nome,
        public readonly string $tipo,
        public readonly float $saldoInicial,
        public readonly float $saldoAtual,
        public readonly ?string $descricao,
        public readonly int $quantidadeTransacoes = 0,
    ) {
    }

    /** @param array<string, mixed> $linha */
    public static function daLinha(array $linha): self
    {
        return new self(
            (int) $linha['id'],
            (int) $linha['usuario_id'],
            (string) $linha['nome'],
            (string) $linha['tipo'],
            (float) $linha['saldo_inicial'],
            (float) ($linha['saldo_atual'] ?? $linha['saldo_inicial']),
            $linha['descricao'] !== null ? (string) $linha['descricao'] : null,
            (int) ($linha['quantidade_transacoes'] ?? 0),
        );
    }

    /** @return array<string, mixed> */
    public function paraArray(float $patrimonio = 0.0): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'tipo' => $this->tipo,
            'tipo_rotulo' => self::TIPOS[$this->tipo] ?? $this->tipo,
            'descricao' => $this->descricao,
            'saldo_inicial' => $this->saldoInicial,
            'saldo_atual' => round($this->saldoAtual, 2),
            'percentual_do_total' => $patrimonio > 0 ? (int) round($this->saldoAtual / $patrimonio * 100) : 0,
            'quantidade_transacoes' => $this->quantidadeTransacoes,
        ];
    }
}
