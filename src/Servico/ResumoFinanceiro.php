<?php

declare(strict_types=1);

namespace Cofrelo\Servico;

use Cofrelo\Modelo\Transacao;
use Cofrelo\Repositorio\ContaRepositorio;
use Cofrelo\Repositorio\MetaRepositorio;
use Cofrelo\Repositorio\TransacaoRepositorio;
use Cofrelo\Repositorio\UsuarioRepositorio;
use Cofrelo\Suporte\Formatador;
use DateTimeImmutable;

/** Monta os números da Visão geral de um mês. */
final class ResumoFinanceiro
{
    public function __construct(
        private readonly TransacaoRepositorio $transacoes,
        private readonly ContaRepositorio $contas,
        private readonly MetaRepositorio $metas,
        private readonly UsuarioRepositorio $usuarios,
    ) {
    }

    /** @return array<string, mixed> */
    public function montar(int $usuarioId, DateTimeImmutable $mes, DateTimeImmutable $hoje): array
    {
        $inicio = $mes->modify('first day of this month');
        $fim = $mes->modify('last day of this month');
        $inicioAnterior = $inicio->modify('-1 month');
        $fimAnterior = $inicioAnterior->modify('last day of this month');
        $ehMesAtual = $inicio->format('Y-m') === $hoje->format('Y-m');

        $atual = $this->transacoes->totais($usuarioId, $inicio->format('Y-m-d'), $fim->format('Y-m-d'));
        $anterior = $this->transacoes->totais($usuarioId, $inicioAnterior->format('Y-m-d'), $fimAnterior->format('Y-m-d'));

        // Saldo de hoje (mês atual) ou do fim do mês consultado, comparado ao fim do mês anterior.
        $saldo = $ehMesAtual ? $this->contas->patrimonio($usuarioId) : $this->contas->patrimonio($usuarioId, $fim->format('Y-m-d'));
        $saldoAnterior = $this->contas->patrimonio($usuarioId, $fimAnterior->format('Y-m-d'));

        $usuario = $this->usuarios->buscar($usuarioId);
        $orcamento = $usuario->orcamentoMensal ?? 0.0;

        $ate = min($hoje, $fim)->format('Y-m-d');

        return [
            'mes' => $inicio->format('Y-m'),
            'mes_rotulo' => Formatador::mesAno($inicio->format('Y-m')),
            'mes_atual' => $ehMesAtual,
            'saldo_total' => $saldo,
            'saldo_variacao' => Indicadores::variacao($saldo, $saldoAnterior),
            'receitas' => $atual['receitas'],
            'receitas_variacao' => Indicadores::variacao($atual['receitas'], $anterior['receitas']),
            'receitas_quantidade' => $atual['qtd_receitas'],
            'despesas' => $atual['despesas'],
            'despesas_variacao' => Indicadores::variacao($atual['despesas'], $anterior['despesas']),
            'despesas_quantidade' => $atual['qtd_despesas'],
            'balanco' => round($atual['receitas'] - $atual['despesas'], 2),
            'fluxo' => $this->fluxo($usuarioId, $inicio, 6),
            'categorias' => self::categoriasComPercentual($this->transacoes->despesasPorCategoria(
                $usuarioId,
                $inicio->format('Y-m-d'),
                $fim->format('Y-m-d')
            )),
            'orcamento' => [
                'valor' => $orcamento,
                'usado' => $atual['despesas'],
                'percentual' => Indicadores::percentualOrcamento($atual['despesas'], $orcamento),
            ],
            'ultimas' => array_map(
                static fn (Transacao $transacao): array => $transacao->paraArray(),
                $this->transacoes->ultimas($usuarioId, $ate, 4)
            ),
            'meta_destaque' => $this->metaDestaque($usuarioId, $hoje),
        ];
    }

    /**
     * Receitas e despesas dos últimos meses, terminando no mês consultado. Mês sem movimento aparece zerado.
     *
     * @return array<int, array{mes: string, rotulo: string, receitas: float, despesas: float}>
     */
    public function fluxo(int $usuarioId, DateTimeImmutable $ultimoMes, int $quantidade): array
    {
        $primeiro = $ultimoMes->modify('first day of this month')->modify('-' . ($quantidade - 1) . ' months');
        $porMes = $this->transacoes->porMes(
            $usuarioId,
            $primeiro->format('Y-m-d'),
            $ultimoMes->modify('last day of this month')->format('Y-m-d')
        );
        $resultado = [];
        for ($i = 0; $i < $quantidade; $i++) {
            $mes = $primeiro->modify("+{$i} months");
            $chave = $mes->format('Y-m');
            $resultado[] = [
                'mes' => $chave,
                'rotulo' => Formatador::mesCurto((int) $mes->format('n')),
                'receitas' => $porMes[$chave]['receitas'] ?? 0.0,
                'despesas' => $porMes[$chave]['despesas'] ?? 0.0,
            ];
        }
        return $resultado;
    }

    /**
     * Mantém as 5 maiores categorias e junta o resto em "Outras", com percentuais que somam 100.
     *
     * @param array<int, array{id: int, nome: string, cor: string, total: float}> $categorias
     * @return array<int, array{id: int|null, nome: string, cor: string, valor: float, percentual: int}>
     */
    public static function categoriasComPercentual(array $categorias, int $limite = 5): array
    {
        if (count($categorias) > $limite) {
            $principais = array_slice($categorias, 0, $limite - 1);
            $resto = array_slice($categorias, $limite - 1);
            $principais[] = ['id' => null, 'nome' => 'Outras', 'cor' => '#A7B3AC', 'total' => round(array_sum(array_column($resto, 'total')), 2)];
            $categorias = $principais;
        }
        $percentuais = Indicadores::distribuicao(array_column($categorias, 'total'));
        $resultado = [];
        foreach ($categorias as $indice => $categoria) {
            $resultado[] = [
                'id' => $categoria['id'],
                'nome' => $categoria['nome'],
                'cor' => $categoria['cor'],
                'valor' => $categoria['total'],
                'percentual' => $percentuais[$indice],
            ];
        }
        return $resultado;
    }

    /**
     * A meta em andamento mais adiantada; se nenhuma começou, a primeira ativa.
     *
     * @return array<string, mixed>|null
     */
    private function metaDestaque(int $usuarioId, DateTimeImmutable $hoje): ?array
    {
        $ativas = array_filter(
            $this->metas->listar($usuarioId),
            static fn ($meta): bool => $meta->status() !== CalculadoraMeta::CONCLUIDA
        );
        if ($ativas === []) {
            return null;
        }
        usort($ativas, static fn ($a, $b): int => CalculadoraMeta::percentual($b->valorAtual, $b->valorObjetivo)
            <=> CalculadoraMeta::percentual($a->valorAtual, $a->valorObjetivo));
        return $ativas[0]->paraArray($hoje);
    }
}
