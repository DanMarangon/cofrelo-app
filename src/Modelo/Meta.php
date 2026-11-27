<?php

declare(strict_types=1);

namespace Cofrelo\Modelo;

use Cofrelo\Servico\CalculadoraMeta;
use Cofrelo\Suporte\Formatador;
use DateTimeImmutable;

/** Objetivo financeiro: reserva de emergência, viagem, carro... */
final class Meta
{
    public function __construct(
        public readonly int $id,
        public readonly string $nome,
        public readonly ?string $descricao,
        public readonly float $valorObjetivo,
        public readonly float $valorAtual,
        public readonly string $dataInicio,
        public readonly ?string $dataLimite,
        public readonly string $icone,
    ) {
    }

    /** @param array<string, mixed> $linha */
    public static function daLinha(array $linha): self
    {
        return new self(
            (int) $linha['id'],
            (string) $linha['nome'],
            $linha['descricao'] ?? null,
            (float) $linha['valor_objetivo'],
            (float) $linha['valor_atual'],
            (string) $linha['data_inicio'],
            $linha['data_limite'] ?? null,
            (string) $linha['icone'],
        );
    }

    public function status(): string
    {
        return CalculadoraMeta::status($this->valorAtual, $this->valorObjetivo);
    }

    /**
     * @param float $ritmoMensal média guardada por mês nos últimos 3 meses (usada na previsão de conclusão)
     * @return array<string, mixed>
     */
    public function paraArray(?DateTimeImmutable $hoje = null, float $ritmoMensal = 0.0): array
    {
        $hoje ??= new DateTimeImmutable('today');
        $limite = $this->dataLimite !== null ? new DateTimeImmutable($this->dataLimite) : null;
        $meses = CalculadoraMeta::mesesRestantes($hoje, $limite);
        $status = $this->status();
        $previsao = CalculadoraMeta::mesesParaConcluir($this->valorAtual, $this->valorObjetivo, $ritmoMensal);

        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'descricao' => $this->descricao,
            'icone' => $this->icone,
            'valor_objetivo' => $this->valorObjetivo,
            'valor_atual' => $this->valorAtual,
            'falta' => CalculadoraMeta::falta($this->valorAtual, $this->valorObjetivo),
            'percentual' => CalculadoraMeta::percentual($this->valorAtual, $this->valorObjetivo),
            'status' => $status,
            'status_rotulo' => CalculadoraMeta::ROTULOS[$status],
            'data_inicio' => $this->dataInicio,
            'data_limite' => $this->dataLimite,
            'prazo_rotulo' => $limite !== null
                ? 'Até ' . Formatador::nomeMes((int) $limite->format('n')) . ' de ' . $limite->format('Y')
                : 'Sem prazo definido',
            'meses_restantes' => $meses,
            'aporte_mensal_sugerido' => $meses !== null && $status !== CalculadoraMeta::CONCLUIDA
                ? CalculadoraMeta::aporteMensal($this->valorAtual, $this->valorObjetivo, $meses)
                : null,
            'ritmo_mensal' => round($ritmoMensal, 2),
            'previsao_meses' => $previsao,
            'previsao_rotulo' => $this->rotuloDaPrevisao($previsao, $ritmoMensal, $hoje, $limite),
        ];
    }

    /** Frase da previsão mostrada no cartão da meta (RN14). */
    private function rotuloDaPrevisao(?int $meses, float $ritmo, DateTimeImmutable $hoje, ?DateTimeImmutable $limite): ?string
    {
        if ($this->status() === CalculadoraMeta::CONCLUIDA) {
            return null;
        }
        if ($meses === null) {
            return 'Sem depósitos nos últimos 3 meses: guarde um valor para ver a previsão.';
        }
        $quando = $hoje->modify('first day of this month')->modify("+{$meses} months");
        $texto = sprintf(
            'No ritmo atual (%s por mês), fica pronta em %d %s, em %s de %s',
            Formatador::moeda($ritmo),
            $meses,
            $meses === 1 ? 'mês' : 'meses',
            Formatador::nomeMes((int) $quando->format('n')),
            $quando->format('Y'),
        );
        return $texto . ($limite !== null && $quando > $limite ? ', depois do prazo.' : '.');
    }
}
