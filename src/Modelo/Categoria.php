<?php

declare(strict_types=1);

namespace Cofrelo\Modelo;

/**
 * Categoria de transação (Moradia, Alimentação, Salário...), sempre de receita ou de despesa, com cor e ícone.
 */
final class Categoria
{
    public function __construct(
        public readonly int $id,
        public readonly string $nome,
        public readonly string $tipo,
        public readonly string $cor,
    ) {
    }

    /** @param array<string, mixed> $linha */
    public static function daLinha(array $linha): self
    {
        return new self((int) $linha['id'], (string) $linha['nome'], (string) $linha['tipo'], (string) $linha['cor']);
    }

    /** @return array<string, mixed> */
    public function paraArray(): array
    {
        return ['id' => $this->id, 'nome' => $this->nome, 'tipo' => $this->tipo, 'cor' => $this->cor];
    }
}
