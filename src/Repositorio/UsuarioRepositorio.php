<?php

declare(strict_types=1);

namespace Cofrelo\Repositorio;

use Cofrelo\Modelo\Usuario;

final class UsuarioRepositorio extends Repositorio
{
    public function buscar(int $id): ?Usuario
    {
        $linha = $this->linha('SELECT * FROM usuario WHERE id = :id', ['id' => $id]);
        return $linha ? Usuario::daLinha($linha) : null;
    }

    public function buscarPorEmail(string $email): ?Usuario
    {
        $linha = $this->linha('SELECT * FROM usuario WHERE email = :email', ['email' => self::normalizarEmail($email)]);
        return $linha ? Usuario::daLinha($linha) : null;
    }

    public function emailEmUso(string $email, ?int $exceto = null): bool
    {
        $id = $this->valor('SELECT id FROM usuario WHERE email = :email', ['email' => self::normalizarEmail($email)]);
        return $id !== false && (int) $id !== $exceto;
    }

    /** A senha chega em texto e é guardada só como hash (bcrypt, via password_hash). */
    public function criar(string $nome, string $email, string $senha, float $orcamentoMensal = 0.0): int
    {
        $this->executar(
            'INSERT INTO usuario (nome, email, senha_hash, orcamento_mensal) VALUES (:nome, :email, :hash, :orcamento)',
            [
                'nome' => trim($nome),
                'email' => self::normalizarEmail($email),
                'hash' => password_hash($senha, PASSWORD_DEFAULT),
                'orcamento' => $orcamentoMensal,
            ]
        );
        return $this->ultimoId();
    }

    public function atualizarPerfil(int $id, string $nome, string $email, float $orcamentoMensal): void
    {
        $this->executar(
            'UPDATE usuario SET nome = :nome, email = :email, orcamento_mensal = :orcamento WHERE id = :id',
            ['nome' => trim($nome), 'email' => self::normalizarEmail($email), 'orcamento' => $orcamentoMensal, 'id' => $id]
        );
    }

    public function atualizarSenha(int $id, string $senha): void
    {
        $this->executar('UPDATE usuario SET senha_hash = :hash WHERE id = :id', [
            'hash' => password_hash($senha, PASSWORD_DEFAULT),
            'id' => $id,
        ]);
    }

    /** Apaga o usuário e, em cascata, as contas, transações, assinaturas e metas dele. */
    public function excluir(int $id): void
    {
        $this->executar('DELETE FROM usuario WHERE id = :id', ['id' => $id]);
    }

    public function total(): int
    {
        return (int) $this->valor('SELECT COUNT(*) FROM usuario');
    }

    public static function normalizarEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
