<?php

declare(strict_types=1);

namespace Cofrelo\Servico;

use Cofrelo\Repositorio\TransacaoRepositorio;
use Cofrelo\Suporte\Formatador;
use DateTimeImmutable;

/** Números da página Relatórios para o período escolhido no calendário. */
final class Relatorio
{
    /** Períodos maiores que isso deixariam o gráfico ilegível. */
    public const MESES_NO_MAXIMO = 24;

    public function __construct(private readonly TransacaoRepositorio $transacoes)
    {
    }

    /** @return array<string, mixed> */
    public function montar(int $usuarioId, DateTimeImmutable $inicio, DateTimeImmutable $fim): array
    {
        $totais = $this->transacoes->totais($usuarioId, $inicio->format('Y-m-d'), $fim->format('Y-m-d'));

        // A tabela de categorias compara o último mês do período com o mês anterior a ele.
        $mesAtual = $fim->modify('first day of this month');
        $mesAnterior = $mesAtual->modify('-1 month');

        return [
            'periodo' => ['inicio' => $inicio->format('Y-m-d'), 'fim' => $fim->format('Y-m-d')],
            'receitas' => $totais['receitas'],
            'despesas' => $totais['despesas'],
            'resultado' => round($totais['receitas'] - $totais['despesas'], 2),
            'taxa_economia' => Indicadores::taxaEconomia($totais['receitas'], $totais['despesas']),
            'qtd_receitas' => $totais['qtd_receitas'],
            'qtd_despesas' => $totais['qtd_despesas'],
            'evolucao' => $this->evolucao($usuarioId, $inicio, $fim),
            'comparacao' => [
                'mes_atual' => Formatador::capitalizar(Formatador::nomeMes((int) $mesAtual->format('n'))),
                'mes_anterior' => Formatador::capitalizar(Formatador::nomeMes((int) $mesAnterior->format('n'))),
                'categorias' => $this->categoriasComparadas($usuarioId, $mesAtual, $mesAnterior),
            ],
            'mapa_de_gastos' => $this->mapaDeGastos($usuarioId, $fim),
        ];
    }

    /** @return array<int, array{mes: string, rotulo: string, receitas: float, despesas: float}> */
    private function evolucao(int $usuarioId, DateTimeImmutable $inicio, DateTimeImmutable $fim): array
    {
        $porMes = $this->transacoes->porMes($usuarioId, $inicio->format('Y-m-d'), $fim->format('Y-m-d'));
        $resultado = [];
        $mes = $inicio->modify('first day of this month');
        $ultimo = $fim->modify('first day of this month');
        while ($mes <= $ultimo && count($resultado) < self::MESES_NO_MAXIMO) {
            $chave = $mes->format('Y-m');
            $resultado[] = [
                'mes' => $chave,
                'rotulo' => Formatador::mesCurto((int) $mes->format('n')),
                'receitas' => $porMes[$chave]['receitas'] ?? 0.0,
                'despesas' => $porMes[$chave]['despesas'] ?? 0.0,
            ];
            $mes = $mes->modify('+1 month');
        }
        return $resultado;
    }

    /** @return array<string, mixed> */
    private function categoriasComparadas(int $usuarioId, DateTimeImmutable $mesAtual, DateTimeImmutable $mesAnterior): array
    {
        $atual = $this->porNome($this->transacoes->despesasPorCategoria(
            $usuarioId,
            $mesAtual->format('Y-m-d'),
            $mesAtual->modify('last day of this month')->format('Y-m-d')
        ));
        $anterior = $this->porNome($this->transacoes->despesasPorCategoria(
            $usuarioId,
            $mesAnterior->format('Y-m-d'),
            $mesAnterior->modify('last day of this month')->format('Y-m-d')
        ));
        $participacao = Indicadores::distribuicao(array_map(static fn (array $c): float => $c['total'], $atual));

        $linhas = [];
        foreach (array_unique(array_merge(array_keys($atual), array_keys($anterior))) as $nome) {
            $valorAtual = $atual[$nome]['total'] ?? 0.0;
            $valorAnterior = $anterior[$nome]['total'] ?? 0.0;
            $linhas[] = [
                'nome' => $nome,
                'cor' => $atual[$nome]['cor'] ?? $anterior[$nome]['cor'],
                'anterior' => $valorAnterior,
                'atual' => $valorAtual,
                'participacao' => $participacao[$nome] ?? 0,
                'variacao' => Indicadores::variacao($valorAtual, $valorAnterior),
            ];
        }
        usort($linhas, static fn (array $a, array $b): int => $b['atual'] <=> $a['atual']);

        $totalAtual = round(array_sum(array_column($linhas, 'atual')), 2);
        $totalAnterior = round(array_sum(array_column($linhas, 'anterior')), 2);
        return [
            'linhas' => $linhas,
            'total' => [
                'anterior' => $totalAnterior,
                'atual' => $totalAtual,
                'variacao' => Indicadores::variacao($totalAtual, $totalAnterior),
            ],
        ];
    }

    /**
     * Gasto de cada dia nas últimas 53 semanas até a data final, para o mapa no estilo do calendário do GitHub.
     *
     * @return array<string, mixed>
     */
    private function mapaDeGastos(int $usuarioId, DateTimeImmutable $fim): array
    {
        $inicio = $fim->modify('-370 days');
        $dias = $this->transacoes->despesasPorDia($usuarioId, $inicio->format('Y-m-d'), $fim->format('Y-m-d'));
        $maior = null;
        foreach ($dias as $data => $valor) {
            if ($maior === null || $valor > $maior['valor']) {
                $maior = ['data' => $data, 'valor' => $valor];
            }
        }
        return [
            'inicio' => $inicio->format('Y-m-d'),
            'fim' => $fim->format('Y-m-d'),
            'dias' => $dias,
            'dias_com_gasto' => count($dias),
            'total' => round(array_sum($dias), 2),
            'maior_dia' => $maior,
        ];
    }

    /**
     * @param array<int, array{id: int, nome: string, cor: string, total: float}> $categorias
     * @return array<string, array{id: int, nome: string, cor: string, total: float}>
     */
    private function porNome(array $categorias): array
    {
        $resultado = [];
        foreach ($categorias as $categoria) {
            $resultado[$categoria['nome']] = $categoria;
        }
        return $resultado;
    }

    /** Relatório em CSV (abre direto no Excel em português, com ponto e vírgula). */
    public static function paraCsv(array $relatorio): string
    {
        $linhas = [['Relatório do Cofrelo'], ['Período', Formatador::data($relatorio['periodo']['inicio']) . ' a ' . Formatador::data($relatorio['periodo']['fim'])], []];
        $linhas[] = ['Receitas', Formatador::numero($relatorio['receitas'])];
        $linhas[] = ['Despesas', Formatador::numero($relatorio['despesas'])];
        $linhas[] = ['Resultado', Formatador::numero($relatorio['resultado'])];
        $linhas[] = ['Taxa de economia (%)', $relatorio['taxa_economia'] === null ? '' : Formatador::numero($relatorio['taxa_economia'], 1)];
        $linhas[] = [];
        $linhas[] = ['Mês', 'Receitas', 'Despesas'];
        foreach ($relatorio['evolucao'] as $mes) {
            $linhas[] = [Formatador::mesAno($mes['mes']), Formatador::numero($mes['receitas']), Formatador::numero($mes['despesas'])];
        }
        $comparacao = $relatorio['comparacao'];
        $linhas[] = [];
        $linhas[] = ['Categoria', $comparacao['mes_anterior'], $comparacao['mes_atual'], 'Participação (%)', 'Variação (%)'];
        foreach ($comparacao['categorias']['linhas'] as $linha) {
            $linhas[] = [
                $linha['nome'],
                Formatador::numero($linha['anterior']),
                Formatador::numero($linha['atual']),
                (string) $linha['participacao'],
                $linha['variacao'] === null ? '' : Formatador::numero($linha['variacao'], 1),
            ];
        }
        return self::montarCsv($linhas);
    }

    /** @param array<int, array<int, string>> $linhas */
    public static function montarCsv(array $linhas): string
    {
        $saida = fopen('php://temp', 'r+');
        foreach ($linhas as $linha) {
            fputcsv($saida, $linha, ';', '"', '\\');
        }
        rewind($saida);
        $conteudo = (string) stream_get_contents($saida);
        fclose($saida);
        return $conteudo;
    }
}
