<?php

declare(strict_types=1);

namespace Cofrelo\Repositorio;

use Cofrelo\Modelo\Assinatura;

final class AssinaturaRepositorio extends Repositorio
{
    private const SELECAO = 'SELECT a.*, c.nome AS conta_nome, cat.nome AS categoria_nome
                               FROM assinatura a
                               JOIN conta c ON c.id = a.conta_id
                               JOIN categoria cat ON cat.id = a.categoria_id';

    /** @return Assinatura[] */
    public function listar(int $usuarioId, bool $somenteAtivas = false): array
    {
        $sql = self::SELECAO . ' WHERE a.usuario_id = :usuario' . ($somenteAtivas ? ' AND a.ativa = 1' : '')
            . ' ORDER BY a.ativa DESC, a.dia_cobranca, a.id';
        return array_map([Assinatura::class, 'daLinha'], $this->linhas($sql, ['usuario' => $usuarioId]));
    }

    public function buscar(int $usuarioId, int $id): ?Assinatura
    {
        $linha = $this->linha(self::SELECAO . ' WHERE a.id = :id AND a.usuario_id = :usuario', [
            'id' => $id,
            'usuario' => $usuarioId,
        ]);
        return $linha ? Assinatura::daLinha($linha) : null;
    }

    /** @param array<string, mixed> $dados */
    public function criar(int $usuarioId, array $dados): int
    {
        $this->executar(
            'INSERT INTO assinatura (usuario_id, conta_id, categoria_id, descricao, valor, forma_pagamento,
                                     dia_cobranca, aviso_dias, inicio)
             VALUES (:usuario, :conta, :categoria, :descricao, :valor, :forma, :dia, :aviso, :inicio)',
            [
                'usuario' => $usuarioId,
                'conta' => $dados['conta_id'],
                'categoria' => $dados['categoria_id'],
                'descricao' => $dados['descricao'],
                'valor' => $dados['valor'],
                'forma' => $dados['forma_pagamento'] ?? null,
                'dia' => $dados['dia_cobranca'],
                'aviso' => $dados['aviso_dias'] ?? 3,
                'inicio' => $dados['inicio'],
            ]
        );
        return $this->ultimoId();
    }

    /** @param array{descricao: string, valor: float, dia_cobranca: int, aviso_dias: int, ativa: bool} $dados */
    public function atualizar(int $usuarioId, int $id, array $dados): bool
    {
        $this->executar(
            'UPDATE assinatura SET descricao = :descricao, valor = :valor, dia_cobranca = :dia, aviso_dias = :aviso,
                                   ativa = :ativa
              WHERE id = :id AND usuario_id = :usuario',
            [
                'descricao' => $dados['descricao'],
                'valor' => $dados['valor'],
                'dia' => $dados['dia_cobranca'],
                'aviso' => $dados['aviso_dias'],
                'ativa' => $dados['ativa'] ? 1 : 0,
                'id' => $id,
                'usuario' => $usuarioId,
            ]
        );
        return $this->buscar($usuarioId, $id) !== null;
    }

    /** Excluir a assinatura não apaga as cobranças já lançadas; elas só perdem o vínculo. */
    public function excluir(int $usuarioId, int $id): bool
    {
        return $this->executar('DELETE FROM assinatura WHERE id = :id AND usuario_id = :usuario', [
            'id' => $id,
            'usuario' => $usuarioId,
        ]) > 0;
    }
}
