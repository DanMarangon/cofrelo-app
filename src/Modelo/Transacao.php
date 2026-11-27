<?php

declare(strict_types=1);

namespace Cofrelo\Modelo;

use Cofrelo\Servico\ValidadorTransacao;

/** Entrada (receita), saída (despesa) ou transferência entre contas. */
final class Transacao
{
    public function __construct(
        public readonly int $id,
        public readonly string $tipo,
        public readonly string $descricao,
        public readonly float $valor,
        public readonly string $data,
        public readonly string $status,
        public readonly ?string $formaPagamento,
        public readonly ?string $observacao,
        public readonly int $contaId,
        public readonly string $contaNome,
        public readonly ?int $contaDestinoId,
        public readonly ?string $contaDestinoNome,
        public readonly ?int $categoriaId,
        public readonly ?string $categoriaNome,
        public readonly ?string $categoriaCor,
        public readonly ?int $assinaturaId,
    ) {
    }

    /** @param array<string, mixed> $linha */
    public static function daLinha(array $linha): self
    {
        $inteiroOuNulo = static fn (mixed $valor): ?int => $valor === null ? null : (int) $valor;
        return new self(
            (int) $linha['id'],
            (string) $linha['tipo'],
            (string) $linha['descricao'],
            (float) $linha['valor'],
            (string) $linha['data'],
            (string) $linha['status'],
            $linha['forma_pagamento'] ?? null,
            $linha['observacao'] ?? null,
            (int) $linha['conta_id'],
            (string) ($linha['conta_nome'] ?? ''),
            $inteiroOuNulo($linha['conta_destino_id'] ?? null),
            $linha['conta_destino_nome'] ?? null,
            $inteiroOuNulo($linha['categoria_id'] ?? null),
            $linha['categoria_nome'] ?? null,
            $linha['categoria_cor'] ?? null,
            $inteiroOuNulo($linha['assinatura_id'] ?? null),
        );
    }

    /** Texto do status como aparece na tela: receita paga aparece como "Recebido". */
    public function statusRotulo(): string
    {
        if ($this->status === 'pendente') {
            return 'Pendente';
        }
        return match ($this->tipo) {
            'receita' => 'Recebido',
            'transferencia' => 'Concluída',
            default => 'Pago',
        };
    }

    /** Linha de apoio abaixo da descrição: "Cartão de débito", "Transferência recebida"... */
    public function detalhe(): string
    {
        if ($this->tipo === 'transferencia') {
            return 'Transferência entre contas';
        }
        $forma = ValidadorTransacao::FORMAS_PAGAMENTO[$this->formaPagamento ?? ''] ?? null;
        if ($this->tipo === 'receita') {
            return match ($this->formaPagamento) {
                'transferencia' => 'Transferência recebida',
                'pix' => 'Pix recebido',
                default => $forma ?? 'Entrada',
            };
        }
        if ($this->formaPagamento === 'pix') {
            return 'Pix enviado';
        }
        return $forma ?? 'Saída';
    }

    /** @return array<string, mixed> */
    public function paraArray(): array
    {
        return [
            'id' => $this->id,
            'tipo' => $this->tipo,
            'descricao' => $this->descricao,
            'detalhe' => $this->detalhe(),
            'valor' => $this->valor,
            'data' => $this->data,
            'status' => $this->status,
            'status_rotulo' => $this->statusRotulo(),
            'forma_pagamento' => $this->formaPagamento,
            'observacao' => $this->observacao,
            'conta' => ['id' => $this->contaId, 'nome' => $this->contaNome],
            'conta_destino' => $this->contaDestinoId !== null
                ? ['id' => $this->contaDestinoId, 'nome' => $this->contaDestinoNome]
                : null,
            'categoria' => $this->categoriaId !== null
                ? ['id' => $this->categoriaId, 'nome' => $this->categoriaNome, 'cor' => $this->categoriaCor]
                : null,
            'assinatura_id' => $this->assinaturaId,
        ];
    }
}
