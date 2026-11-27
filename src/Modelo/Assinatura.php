<?php

declare(strict_types=1);

namespace Cofrelo\Modelo;

use DateTimeImmutable;

/**
 * Cobrança que se repete todo mês (streaming, academia...). Criada pelo "Repetir todo mês"
 * do modal de nova transação; o sistema lança sozinho a cobrança de cada mês.
 */
final class Assinatura
{
    public function __construct(
        public readonly int $id,
        public readonly int $contaId,
        public readonly string $contaNome,
        public readonly int $categoriaId,
        public readonly string $categoriaNome,
        public readonly string $descricao,
        public readonly float $valor,
        public readonly ?string $formaPagamento,
        public readonly int $diaCobranca,
        public readonly int $avisoDias,
        public readonly bool $ativa,
        public readonly string $inicio,
    ) {
    }

    /** @param array<string, mixed> $linha */
    public static function daLinha(array $linha): self
    {
        return new self(
            (int) $linha['id'],
            (int) $linha['conta_id'],
            (string) ($linha['conta_nome'] ?? ''),
            (int) $linha['categoria_id'],
            (string) ($linha['categoria_nome'] ?? ''),
            (string) $linha['descricao'],
            (float) $linha['valor'],
            $linha['forma_pagamento'] ?? null,
            (int) $linha['dia_cobranca'],
            (int) $linha['aviso_dias'],
            (bool) $linha['ativa'],
            (string) $linha['inicio'],
        );
    }

    /** Próxima data de cobrança a partir de hoje (inclusive). */
    public function proximaCobranca(DateTimeImmutable $hoje): DateTimeImmutable
    {
        // O dia vai de 1 a 28 (regra do validador), então existe em qualquer mês.
        $nesteMes = $hoje->setDate((int) $hoje->format('Y'), (int) $hoje->format('n'), $this->diaCobranca);
        if ($nesteMes >= $hoje) {
            return $nesteMes;
        }
        $proximoMes = $hoje->modify('first day of next month');
        return $proximoMes->setDate((int) $proximoMes->format('Y'), (int) $proximoMes->format('n'), $this->diaCobranca);
    }

    /** @return array<string, mixed> */
    public function paraArray(?DateTimeImmutable $hoje = null): array
    {
        $hoje ??= new DateTimeImmutable('today');
        $proxima = $this->proximaCobranca($hoje);
        return [
            'id' => $this->id,
            'descricao' => $this->descricao,
            'valor' => $this->valor,
            'forma_pagamento' => $this->formaPagamento,
            'dia_cobranca' => $this->diaCobranca,
            'aviso_dias' => $this->avisoDias,
            'ativa' => $this->ativa,
            'inicio' => $this->inicio,
            'conta' => ['id' => $this->contaId, 'nome' => $this->contaNome],
            'categoria' => ['id' => $this->categoriaId, 'nome' => $this->categoriaNome],
            'proxima_cobranca' => $proxima->format('Y-m-d'),
            'dias_para_cobranca' => (int) $hoje->diff($proxima)->days,
        ];
    }
}
