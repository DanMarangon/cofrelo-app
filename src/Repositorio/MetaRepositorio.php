<?php

declare(strict_types=1);

namespace Cofrelo\Repositorio;

use Cofrelo\Modelo\Meta;

final class MetaRepositorio extends Repositorio
{
    /** @return Meta[] */
    public function listar(int $usuarioId): array
    {
        $linhas = $this->linhas(
            'SELECT * FROM meta WHERE usuario_id = :usuario ORDER BY (valor_atual >= valor_objetivo), valor_atual DESC, id',
            ['usuario' => $usuarioId]
        );
        return array_map([Meta::class, 'daLinha'], $linhas);
    }

    public function buscar(int $usuarioId, int $id): ?Meta
    {
        $linha = $this->linha('SELECT * FROM meta WHERE id = :id AND usuario_id = :usuario', [
            'id' => $id,
            'usuario' => $usuarioId,
        ]);
        return $linha ? Meta::daLinha($linha) : null;
    }

    /** @param array<string, mixed> $dados */
    public function criar(int $usuarioId, array $dados): int
    {
        $this->executar(
            'INSERT INTO meta (usuario_id, nome, descricao, valor_objetivo, valor_atual, data_inicio, data_limite, icone)
             VALUES (:usuario, :nome, :descricao, :objetivo, :atual, :inicio, :limite, :icone)',
            [
                'usuario' => $usuarioId,
                'nome' => $dados['nome'],
                'descricao' => $dados['descricao'],
                'objetivo' => $dados['valor_objetivo'],
                'atual' => $dados['valor_atual'],
                'inicio' => $dados['data_inicio'],
                'limite' => $dados['data_limite'],
                'icone' => $dados['icone'],
            ]
        );
        return $this->ultimoId();
    }

    /** @param array<string, mixed> $dados */
    public function atualizar(int $usuarioId, int $id, array $dados): bool
    {
        $this->executar(
            'UPDATE meta SET nome = :nome, descricao = :descricao, valor_objetivo = :objetivo, valor_atual = :atual,
                             data_limite = :limite, icone = :icone
              WHERE id = :id AND usuario_id = :usuario',
            [
                'nome' => $dados['nome'],
                'descricao' => $dados['descricao'],
                'objetivo' => $dados['valor_objetivo'],
                'atual' => $dados['valor_atual'],
                'limite' => $dados['data_limite'],
                'icone' => $dados['icone'],
                'id' => $id,
                'usuario' => $usuarioId,
            ]
        );
        return $this->buscar($usuarioId, $id) !== null;
    }

    public function excluir(int $usuarioId, int $id): bool
    {
        return $this->executar('DELETE FROM meta WHERE id = :id AND usuario_id = :usuario', [
            'id' => $id,
            'usuario' => $usuarioId,
        ]) > 0;
    }

    /** "Guardar dinheiro": registra o depósito e soma ao valor atual da meta numa única transação do banco. */
    public function depositar(int $usuarioId, int $id, float $valor, string $data): ?Meta
    {
        if ($this->buscar($usuarioId, $id) === null) {
            return null;
        }
        $abriuTransacao = !$this->pdo->inTransaction();
        if ($abriuTransacao) {
            $this->pdo->beginTransaction();
        }
        try {
            $this->executar('INSERT INTO meta_deposito (meta_id, valor, data) VALUES (:meta, :valor, :data)', [
                'meta' => $id,
                'valor' => $valor,
                'data' => $data,
            ]);
            $this->executar('UPDATE meta SET valor_atual = valor_atual + :valor WHERE id = :id', ['valor' => $valor, 'id' => $id]);
            if ($abriuTransacao) {
                $this->pdo->commit();
            }
        } catch (\Throwable $erro) {
            if ($abriuTransacao) {
                $this->pdo->rollBack();
            }
            throw $erro;
        }
        return $this->buscar($usuarioId, $id);
    }

    /** @return array<int, array{valor: float, data: string}> */
    public function depositos(int $metaId): array
    {
        return array_map(static fn (array $linha): array => [
            'valor' => (float) $linha['valor'],
            'data' => (string) $linha['data'],
        ], $this->linhas('SELECT valor, data FROM meta_deposito WHERE meta_id = :meta ORDER BY data, id', ['meta' => $metaId]));
    }
}
