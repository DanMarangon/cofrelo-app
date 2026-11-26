<?php

declare(strict_types=1);

namespace Cofrelo\Repositorio;

use Cofrelo\Modelo\Conta;

final class ContaRepositorio extends Repositorio
{
    /**
     * Saldo = saldo inicial + receitas − despesas − transferências enviadas + transferências recebidas.
     * Só entram transações pagas (RN05): o que está pendente ainda não saiu da conta.
     */
    private const SALDO = "c.saldo_inicial
        + COALESCE((SELECT SUM(CASE t.tipo WHEN 'receita' THEN t.valor ELSE -t.valor END)
                      FROM transacao t WHERE t.conta_id = c.id AND t.status = 'pago' %1\$s), 0)
        + COALESCE((SELECT SUM(t.valor)
                      FROM transacao t WHERE t.conta_destino_id = c.id AND t.tipo = 'transferencia'
                       AND t.status = 'pago' %2\$s), 0)";

    /** @return Conta[] */
    public function listar(int $usuarioId): array
    {
        $sql = 'SELECT c.*, ' . sprintf(self::SALDO, '', '') . ' AS saldo_atual,
                       (SELECT COUNT(*) FROM transacao t WHERE t.conta_id = c.id OR t.conta_destino_id = c.id)
                       AS quantidade_transacoes
                  FROM conta c
                 WHERE c.usuario_id = :usuario AND c.ativa = 1
                 ORDER BY c.id';
        return array_map([Conta::class, 'daLinha'], $this->linhas($sql, ['usuario' => $usuarioId]));
    }

    public function buscar(int $usuarioId, int $id): ?Conta
    {
        $sql = 'SELECT c.*, ' . sprintf(self::SALDO, '', '') . ' AS saldo_atual,
                       (SELECT COUNT(*) FROM transacao t WHERE t.conta_id = c.id OR t.conta_destino_id = c.id)
                       AS quantidade_transacoes
                  FROM conta c
                 WHERE c.id = :id AND c.usuario_id = :usuario AND c.ativa = 1';
        $linha = $this->linha($sql, ['id' => $id, 'usuario' => $usuarioId]);
        return $linha ? Conta::daLinha($linha) : null;
    }

    /** @return int[] */
    public function idsAtivos(int $usuarioId): array
    {
        $ids = $this->linhas('SELECT id FROM conta WHERE usuario_id = :usuario AND ativa = 1', ['usuario' => $usuarioId]);
        return array_map(static fn (array $linha): int => (int) $linha['id'], $ids);
    }

    /** Soma dos saldos de todas as contas, hoje ou ao fim de uma data. */
    public function patrimonio(int $usuarioId, ?string $ate = null): float
    {
        if ($ate === null) {
            $sql = 'SELECT COALESCE(SUM(' . sprintf(self::SALDO, '', '') . '), 0) FROM conta c
                     WHERE c.usuario_id = :usuario AND c.ativa = 1';
            return round((float) $this->valor($sql, ['usuario' => $usuarioId]), 2);
        }
        $sql = 'SELECT COALESCE(SUM(' . sprintf(self::SALDO, 'AND t.data <= :ate1', 'AND t.data <= :ate2') . '), 0)
                  FROM conta c WHERE c.usuario_id = :usuario AND c.ativa = 1';
        return round((float) $this->valor($sql, ['usuario' => $usuarioId, 'ate1' => $ate, 'ate2' => $ate]), 2);
    }

    /** @param array{nome: string, tipo: string, saldo_inicial: float, descricao: ?string} $dados */
    public function criar(int $usuarioId, array $dados): int
    {
        $this->executar(
            'INSERT INTO conta (usuario_id, nome, tipo, saldo_inicial, descricao)
             VALUES (:usuario, :nome, :tipo, :saldo, :descricao)',
            [
                'usuario' => $usuarioId,
                'nome' => $dados['nome'],
                'tipo' => $dados['tipo'],
                'saldo' => $dados['saldo_inicial'],
                'descricao' => $dados['descricao'],
            ]
        );
        return $this->ultimoId();
    }

    /** @param array{nome: string, tipo: string, saldo_inicial: float, descricao: ?string} $dados */
    public function atualizar(int $usuarioId, int $id, array $dados): bool
    {
        $this->executar(
            'UPDATE conta SET nome = :nome, tipo = :tipo, saldo_inicial = :saldo, descricao = :descricao
              WHERE id = :id AND usuario_id = :usuario',
            [
                'nome' => $dados['nome'],
                'tipo' => $dados['tipo'],
                'saldo' => $dados['saldo_inicial'],
                'descricao' => $dados['descricao'],
                'id' => $id,
                'usuario' => $usuarioId,
            ]
        );
        return $this->buscar($usuarioId, $id) !== null;
    }

    public function excluir(int $usuarioId, int $id): bool
    {
        return $this->executar('DELETE FROM conta WHERE id = :id AND usuario_id = :usuario', [
            'id' => $id,
            'usuario' => $usuarioId,
        ]) > 0;
    }
}
