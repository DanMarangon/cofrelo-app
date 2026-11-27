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
 * Avisos do sino no topo da tela (RN12). São quatro tipos, cada um montado por um método:
 *  1. contas pendentes que vencem nos próximos 7 dias ou que já venceram;
 *  2. cobranças de assinatura que estão chegando;
 *  3. orçamento do mês com 80% ou mais usado;
 *  4. metas com prazo em até 30 dias.
 * No fim, os avisos são ordenados por data.
 *
 * @phpstan-type Aviso array{id: string, tipo: string, titulo: string, mensagem: string, data: string}
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

    /** @return list<Aviso> */
    public function listar(int $usuarioId, DateTimeImmutable $hoje): array
    {
        $avisos = array_merge(
            $this->contasPendentes($usuarioId, $hoje),
            $this->assinaturasChegando($usuarioId, $hoje),
            $this->orcamentoQuaseNoFim($usuarioId, $hoje),
            $this->metasComPrazoProximo($usuarioId, $hoje),
        );
        usort($avisos, static fn (array $a, array $b): int => strcmp($a['data'], $b['data']));
        return $avisos;
    }

    /** @return list<Aviso> */
    private function contasPendentes(int $usuarioId, DateTimeImmutable $hoje): array
    {
        $desde = $hoje->modify('-30 days')->format('Y-m-d');
        $ate = $hoje->modify('+7 days')->format('Y-m-d');
        $avisos = [];
        foreach ($this->transacoes->pendentesEntre($usuarioId, $desde, $ate) as $pendente) {
            $dias = self::diasAte($hoje, new DateTimeImmutable($pendente->data));
            $avisos[] = [
                'id' => 'pendente-' . $pendente->id,
                'tipo' => $dias < 0 ? 'atraso' : 'cobranca',
                'titulo' => $pendente->descricao,
                'mensagem' => Formatador::moeda($pendente->valor) . ' ' . self::quandoVence($dias) . '.',
                'data' => $pendente->data,
            ];
        }
        return $avisos;
    }

    /**
     * Assinaturas cuja próxima cobrança cai no mês seguinte e já está dentro do prazo de aviso.
     *
     * @return list<Aviso>
     */
    private function assinaturasChegando(int $usuarioId, DateTimeImmutable $hoje): array
    {
        $avisos = [];
        foreach ($this->assinaturas->listar($usuarioId, true) as $assinatura) {
            $proxima = $assinatura->proximaCobranca($hoje);
            $dias = (int) $hoje->diff($proxima)->days;
            $ehNoMesSeguinte = $proxima->format('Y-m') !== $hoje->format('Y-m');
            if (!$ehNoMesSeguinte || $dias > $assinatura->avisoDias) {
                continue;
            }
            $avisos[] = [
                'id' => 'assinatura-' . $assinatura->id . '-' . $proxima->format('Y-m'),
                'tipo' => 'cobranca',
                'titulo' => $assinatura->descricao,
                'mensagem' => 'A assinatura de ' . Formatador::moeda($assinatura->valor) . ' será cobrada em '
                    . $dias . ($dias === 1 ? ' dia.' : ' dias.'),
                'data' => $proxima->format('Y-m-d'),
            ];
        }
        return $avisos;
    }

    /** @return list<Aviso> */
    private function orcamentoQuaseNoFim(int $usuarioId, DateTimeImmutable $hoje): array
    {
        $usuario = $this->usuarios->buscar($usuarioId);
        if ($usuario === null || $usuario->orcamentoMensal <= 0) {
            return []; // sem orçamento definido, não há o que avisar
        }
        $totais = $this->transacoes->totais(
            $usuarioId,
            $hoje->modify('first day of this month')->format('Y-m-d'),
            $hoje->modify('last day of this month')->format('Y-m-d')
        );
        $percentual = Indicadores::percentualOrcamento($totais['despesas'], $usuario->orcamentoMensal) ?? 0;
        if ($percentual < 80) {
            return [];
        }
        $estourou = $percentual >= 100;
        return [[
            'id' => 'orcamento-' . $hoje->format('Y-m') . '-' . ($estourou ? 'estourado' : 'alerta'),
            'tipo' => 'orcamento',
            'titulo' => $estourou ? 'Orçamento ultrapassado' : 'Orçamento quase no fim',
            'mensagem' => 'Você já usou ' . Formatador::numero($percentual, 0) . '% do orçamento de '
                . Formatador::moeda($usuario->orcamentoMensal) . ' deste mês.',
            'data' => $hoje->format('Y-m-d'),
        ]];
    }

    /** @return list<Aviso> */
    private function metasComPrazoProximo(int $usuarioId, DateTimeImmutable $hoje): array
    {
        $avisos = [];
        foreach ($this->metas->listar($usuarioId) as $meta) {
            if ($meta->dataLimite === null || $meta->status() === CalculadoraMeta::CONCLUIDA) {
                continue; // meta sem prazo ou já concluída não gera aviso
            }
            $dias = self::diasAte($hoje, new DateTimeImmutable($meta->dataLimite));
            if ($dias < 0 || $dias > 30) {
                continue;
            }
            $falta = CalculadoraMeta::falta($meta->valorAtual, $meta->valorObjetivo);
            $avisos[] = [
                'id' => 'meta-' . $meta->id,
                'tipo' => 'meta',
                'titulo' => $meta->nome,
                'mensagem' => 'Faltam ' . Formatador::moeda($falta) . " e o prazo termina em {$dias} dias.",
                'data' => $meta->dataLimite,
            ];
        }
        return $avisos;
    }

    /** Dias entre hoje e a data: negativo quando a data já passou. */
    private static function diasAte(DateTimeImmutable $hoje, DateTimeImmutable $data): int
    {
        return (int) $hoje->diff($data)->format('%r%a');
    }

    private static function quandoVence(int $dias): string
    {
        return match (true) {
            $dias < 0 => 'venceu há ' . abs($dias) . (abs($dias) === 1 ? ' dia' : ' dias'),
            $dias === 0 => 'vence hoje',
            $dias === 1 => 'vence amanhã',
            default => "vence em {$dias} dias",
        };
    }
}
