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

    /** @return array<string, mixed> */
    public function paraArray(?DateTimeImmutable $hoje = null): array
    {
        $hoje ??= new DateTimeImmutable('today');
        $limite = $this->dataLimite !== null ? new DateTimeImmutable($this->dataLimite) : null;
        $meses = CalculadoraMeta::mesesRestantes($hoje, $limite);
        $status = $this->status();

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
        ];
    }
}
