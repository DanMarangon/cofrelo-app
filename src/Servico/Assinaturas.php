<?php

declare(strict_types=1);

namespace Cofrelo\Servico;

use Cofrelo\Repositorio\AssinaturaRepositorio;
use Cofrelo\Repositorio\TransacaoRepositorio;
use Cofrelo\Suporte\Log;
use DateTimeImmutable;

/**
 * Automação das assinaturas (RN11): no primeiro acesso de cada mês, o sistema lança sozinho a
 * cobrança de cada assinatura ativa. Se o dia da cobrança ainda não chegou, ela entra como pendente.
 */
final class Assinaturas
{
    public function __construct(
        private readonly AssinaturaRepositorio $assinaturas,
        private readonly TransacaoRepositorio $transacoes,
    ) {
    }

    /** @return int quantas cobranças foram lançadas agora */
    public function lancarCobrancasDoMes(int $usuarioId, DateTimeImmutable $hoje): int
    {
        $inicio = $hoje->modify('first day of this month');
        $fim = $hoje->modify('last day of this month');
        $lancadas = 0;

        foreach ($this->assinaturas->listar($usuarioId, true) as $assinatura) {
            if ($assinatura->inicio > $fim->format('Y-m-d')) {
                continue;
            }
            if ($this->transacoes->existeDaAssinaturaNoMes($assinatura->id, $inicio->format('Y-m-d'), $fim->format('Y-m-d'))) {
                continue;
            }
            $data = $inicio->setDate((int) $inicio->format('Y'), (int) $inicio->format('n'), $assinatura->diaCobranca);
            $this->transacoes->criar($usuarioId, [
                'tipo' => 'despesa',
                'conta_id' => $assinatura->contaId,
                'categoria_id' => $assinatura->categoriaId,
                'descricao' => $assinatura->descricao,
                'valor' => $assinatura->valor,
                'data' => $data->format('Y-m-d'),
                'forma_pagamento' => $assinatura->formaPagamento,
                'status' => $data > $hoje ? 'pendente' : 'pago',
                'observacao' => 'Lançada automaticamente pela assinatura',
            ], $assinatura->id);
            $lancadas++;
        }

        if ($lancadas > 0) {
            Log::info('assinatura.cobrancas_lancadas', ['usuario_id' => $usuarioId, 'quantidade' => $lancadas]);
        }
        return $lancadas;
    }
}
