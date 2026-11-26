<?php

declare(strict_types=1);

namespace Cofrelo\Modelo;

/** Pessoa que usa o Cofrelo. A senha só existe como hash (password_hash) e nunca sai da classe. */
final class Usuario
{
    public function __construct(
        public readonly int $id,
        public readonly string $nome,
        public readonly string $email,
        public readonly string $senhaHash,
        public readonly float $orcamentoMensal,
        public readonly string $criadoEm,
    ) {
    }

    /** @param array<string, mixed> $linha */
    public static function daLinha(array $linha): self
    {
        return new self(
            (int) $linha['id'],
            (string) $linha['nome'],
            (string) $linha['email'],
            (string) $linha['senha_hash'],
            (float) $linha['orcamento_mensal'],
            (string) $linha['criado_em'],
        );
    }

    public function primeiroNome(): string
    {
        return explode(' ', trim($this->nome))[0];
    }

    public function senhaConfere(string $senha): bool
    {
        return password_verify($senha, $this->senhaHash);
    }

    /** @return array<string, mixed> */
    public function paraArray(): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'primeiro_nome' => $this->primeiroNome(),
            'email' => $this->email,
            'orcamento_mensal' => $this->orcamentoMensal,
        ];
    }
}
