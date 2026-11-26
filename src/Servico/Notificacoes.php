<?php

declare(strict_types=1);

namespace Cofrelo\Servico;

use Cofrelo\Repositorio\AssinaturaRepositorio;
use Cofrelo\Repositorio\MetaRepositorio;
use Cofrelo\Repositorio\TransacaoRepositorio;
use Cofrelo\Repositorio\UsuarioRepositorio;
use Cofrelo\Suporte\Formatador;
use DateTimeImmutable;

/**
 * Avisos do sino no topo da tela (RN12): contas pendentes que vencem nos próximos dias ou atrasadas,
 * cobranças de assinaturas que estão chegando, orçamento quase no fim e metas com prazo próximo.
 */
final class Notificacoes
{
    public function __construct(
        private readonly TransacaoRepositorio $transacoes,
        private readonly AssinaturaRepositorio $assinaturas,
        private readonly MetaRepositorio $metas,
        private readonly UsuarioRepositorio $usuarios,
    ) {
    }

    /** @return array<int, array{id: string, tipo: string, titulo: string, mensagem: string, data: string}> */
    public function listar(int $usuarioId, DateTimeImmutable $hoje): array
    {
        $avisos = [];

        $desde = $hoje->modify('-30 days')->format('Y-m-d');
        $ate = $hoje->modify('+7 days')->format('Y-m-d');
        foreach ($this->transacoes->pendentesEntre($usuarioId, $desde, $ate) as $pendente) {
            $dias = (int) $hoje->diff(new DateTimeImmutable($pendente->data))->format('%r%a');
            $quando = match (true) {
                $dias < 0 => 'venceu há ' . abs($dias) . (abs($dias) === 1 ? ' dia' : ' dias'),
                $dias === 0 => 'vence hoje',
                $dias === 1 => 'vence amanhã',
                default => "vence em {$dias} dias",
            };
            $avisos[] = [
                'id' => 'pendente-' . $pendente->id,
                'tipo' => $dias < 0 ? 'atraso' : 'cobranca',
                'titulo' => $pendente->descricao,
                'mensagem' => Formatador::moeda($pendente->valor) . ' ' . $quando . '.',
                'data' => $pendente->data,
            ];
        }

        // Assinaturas cuja próxima cobrança cai no mês seguinte e já está dentro do prazo de aviso.
        foreach ($this->assinaturas->listar($usuarioId, true) as $assinatura) {
            $proxima = $assinatura->proximaCobranca($hoje);
            $dias = (int) $hoje->diff($proxima)->days;
            if ($proxima->format('Y-m') !== $hoje->format('Y-m') && $dias <= $assinatura->avisoDias) {
                $avisos[] = [
                    'id' => 'assinatura-' . $assinatura->id . '-' . $proxima->format('Y-m'),
                    'tipo' => 'cobranca',
                    'titulo' => $assinatura->descricao,
                    'mensagem' => 'A assinatura de ' . Formatador::moeda($assinatura->valor) . ' será cobrada em '
                        . $dias . ($dias === 1 ? ' dia.' : ' dias.'),
                    'data' => $proxima->format('Y-m-d'),
                ];
            }
        }

        $usuario = $this->usuarios->buscar($usuarioId);
        if ($usuario !== null && $usuario->orcamentoMensal > 0) {
            $totais = $this->transacoes->totais(
                $usuarioId,
                $hoje->modify('first day of this month')->format('Y-m-d'),
                $hoje->modify('last day of this month')->format('Y-m-d')
            );
            $percentual = Indicadores::percentualOrcamento($totais['despesas'], $usuario->orcamentoMensal) ?? 0;
            if ($percentual >= 80) {
                $avisos[] = [
                    'id' => 'orcamento-' . $hoje->format('Y-m') . '-' . ($percentual >= 100 ? 'estourado' : 'alerta'),
                    'tipo' => 'orcamento',
                    'titulo' => $percentual >= 100 ? 'Orçamento ultrapassado' : 'Orçamento quase no fim',
                    'mensagem' => 'Você já usou ' . Formatador::numero($percentual, 0) . '% do orçamento de '
                        . Formatador::moeda($usuario->orcamentoMensal) . ' deste mês.',
                    'data' => $hoje->format('Y-m-d'),
                ];
            }
        }

        foreach ($this->metas->listar($usuarioId) as $meta) {
            if ($meta->dataLimite === null || $meta->status() === CalculadoraMeta::CONCLUIDA) {
                continue;
            }
            $limite = new DateTimeImmutable($meta->dataLimite);
            $dias = (int) $hoje->diff($limite)->format('%r%a');
            if ($dias >= 0 && $dias <= 30) {
                $avisos[] = [
                    'id' => 'meta-' . $meta->id,
                    'tipo' => 'meta',
                    'titulo' => $meta->nome,
                    'mensagem' => 'Faltam ' . Formatador::moeda(CalculadoraMeta::falta($meta->valorAtual, $meta->valorObjetivo))
                        . " e o prazo termina em {$dias} dias.",
                    'data' => $meta->dataLimite,
                ];
            }
        }

        usort($avisos, static fn (array $a, array $b): int => strcmp($a['data'], $b['data']));
        return $avisos;
    }
}
