<?php

declare(strict_types=1);

namespace Cofrelo\Banco;

use DateTimeImmutable;
use PDO;

/**
 * Cria a usuária de demonstração (Mariana) com 12 meses de movimentação.
 *
 * As datas são relativas ao dia de hoje, então o sistema sempre abre com o mês atual preenchido.
 * O mês atual reproduz os números do protótipo do Figma: R$ 8.500 de receitas, R$ 3.920 de despesas
 * em 22 pagamentos e a mesma divisão por categoria (Moradia 45%, Alimentação 22%...).
 */
final class DadosDeExemplo
{
    public const NOME = 'Mariana Alves';
    public const EMAIL = 'mariana@cofrelo.app';
    public const SENHA = 'cofrelo123';
    public const ORCAMENTO = 6000.00;

    private const MESES = 12;

    /** Saldo que cada conta deve ter hoje (o saldo inicial é calculado a partir disto). */
    private const SALDOS_DE_HOJE = ['principal' => 10580.00, 'digital' => 2000.00, 'reserva' => 12000.00];

    /** Despesas do mês atual: descrição, categoria, valor, dia, forma de pagamento, conta, valor fixo? */
    private const DESPESAS = [
        ['Aluguel', 'Moradia', 1500.00, 15, 'pix', 'principal', true],
        ['Internet residencial', 'Moradia', 119.90, 15, 'boleto', 'principal', true],
        ['Energia elétrica', 'Moradia', 100.00, 10, 'debito_automatico', 'principal', false],
        ['Gás', 'Moradia', 45.00, 7, 'pix', 'principal', false],
        ['Supermercado', 'Alimentação', 286.40, 18, 'debito', 'principal', false],
        ['Café e companhia', 'Alimentação', 42.90, 14, 'debito', 'principal', false],
        ['Supermercado', 'Alimentação', 231.30, 8, 'debito', 'principal', false],
        ['Padaria', 'Alimentação', 38.60, 3, 'debito', 'principal', false],
        ['Restaurante', 'Alimentação', 104.50, 21, 'credito', 'principal', false],
        ['Feira', 'Alimentação', 65.00, 11, 'pix', 'principal', false],
        ['iFood', 'Alimentação', 80.00, 24, 'credito', 'digital', false],
        ['Lanche', 'Alimentação', 24.00, 2, 'debito', 'digital', false],
        ['Plano de saúde', 'Saúde', 480.00, 17, 'debito_automatico', 'principal', true],
        ['Farmácia', 'Saúde', 180.80, 13, 'debito', 'principal', false],
        ['Posto de combustível', 'Transporte', 210.00, 16, 'debito', 'principal', false],
        ['Uber', 'Transporte', 36.00, 9, 'credito', 'digital', false],
        ['Posto de combustível', 'Transporte', 156.00, 27, 'debito', 'principal', false],
        ['Estacionamento', 'Transporte', 18.00, 19, 'debito', 'principal', true],
    ];

    /** Total de cada categoria nos meses anteriores (índice = quantos meses atrás). */
    private const TOTAIS_ANTERIORES = [
        1 => ['Moradia' => 1749.90, 'Alimentação' => 1027.70, 'Saúde' => 640.80, 'Transporte' => 475.00],
        2 => ['Moradia' => 1790.40, 'Alimentação' => 948.30, 'Saúde' => 702.50, 'Transporte' => 398.00],
        3 => ['Moradia' => 1772.10, 'Alimentação' => 1104.90, 'Saúde' => 575.20, 'Transporte' => 452.00],
        4 => ['Moradia' => 1801.30, 'Alimentação' => 918.60, 'Saúde' => 619.90, 'Transporte' => 436.50],
        5 => ['Moradia' => 1745.80, 'Alimentação' => 1032.40, 'Saúde' => 588.70, 'Transporte' => 501.00],
        6 => ['Moradia' => 1688.00, 'Alimentação' => 965.80, 'Saúde' => 662.40, 'Transporte' => 410.00],
        7 => ['Moradia' => 1702.50, 'Alimentação' => 1088.20, 'Saúde' => 596.30, 'Transporte' => 467.80],
        8 => ['Moradia' => 1735.60, 'Alimentação' => 997.40, 'Saúde' => 640.00, 'Transporte' => 389.90],
        9 => ['Moradia' => 1810.20, 'Alimentação' => 1046.10, 'Saúde' => 712.60, 'Transporte' => 455.30],
        10 => ['Moradia' => 1764.00, 'Alimentação' => 931.50, 'Saúde' => 603.20, 'Transporte' => 428.70],
        11 => ['Moradia' => 1698.70, 'Alimentação' => 1012.80, 'Saúde' => 654.90, 'Transporte' => 476.40],
    ];

    /** Gastos que aconteceram uma vez só em meses anteriores: meses atrás, descrição, categoria, valor, dia. */
    private const GASTOS_EVENTUAIS = [
        [2, 'Show', 'Lazer', 180.00, 23],
        [2, 'Roupas', 'Compras', 349.90, 12],
        [3, 'Manutenção do carro', 'Outras despesas', 1250.00, 9],
        [3, 'Cinema', 'Lazer', 64.00, 20],
        [4, 'Notebook (parcela)', 'Compras', 1650.00, 14],
        [4, 'Curso online', 'Educação', 89.90, 4],
        [5, 'Viagem de fim de semana', 'Lazer', 1450.00, 18],
        [5, 'Presente', 'Compras', 120.00, 22],
        [6, 'Celular novo', 'Compras', 1899.00, 11],
        [7, 'Viagem de férias', 'Lazer', 2680.00, 8],
        [8, 'Curso de inglês', 'Educação', 420.00, 5],
        [8, 'Barzinho com amigos', 'Lazer', 95.00, 27],
        [9, 'Eletrodomésticos', 'Compras', 2290.00, 16],
        [10, 'Seguro do carro', 'Outras despesas', 1850.00, 3],
        [11, 'Festival de música', 'Lazer', 210.00, 25],
        [11, 'Móveis', 'Compras', 980.00, 13],
    ];

    /** Renda extra de cada mês (índice = meses atrás). */
    private const FREELAS = [1000.00, 360.00, 850.00, 0.00, 420.00, 680.00, 0.00, 950.00, 300.00, 0.00, 760.00, 0.00];

    /** Assinaturas: descrição, valor atual, dia, forma, valor antes do reajuste (meses anteriores). */
    private const ASSINATURAS = [
        ['Netflix', 55.90, 8, 'credito', 55.90],
        ['Spotify', 21.90, 12, 'credito', 21.90],
        ['Academia', 109.90, 5, 'debito_automatico', 104.90],
        ['iCloud', 13.90, 22, 'credito', 13.90],
    ];

    private DateTimeImmutable $hoje;
    /** @var array<string, int> nome da categoria => id */
    private array $categorias = [];
    private ?\PDOStatement $insercao = null;

    public function __construct(private readonly PDO $pdo, ?DateTimeImmutable $hoje = null)
    {
        $this->hoje = $hoje ?? new DateTimeImmutable('today');
    }

    /** Cria tudo dentro de uma transação do banco e devolve o id da usuária. */
    public function criar(): int
    {
        $this->pdo->beginTransaction();
        try {
            $this->categorias = $this->pdo->query('SELECT nome, id FROM categoria')->fetchAll(PDO::FETCH_KEY_PAIR);

            $usuario = $this->inserir('usuario', [
                'nome' => self::NOME,
                'email' => self::EMAIL,
                'senha_hash' => password_hash(self::SENHA, PASSWORD_DEFAULT),
                'orcamento_mensal' => self::ORCAMENTO,
            ]);
            $inicio = $this->inicioDoMes(self::MESES - 1)->format('Y-m-d');
            $contas = [
                'principal' => $this->inserir('conta', ['usuario_id' => $usuario, 'nome' => 'Conta principal', 'tipo' => 'corrente', 'descricao' => 'Onde cai o salário']),
                'digital' => $this->inserir('conta', ['usuario_id' => $usuario, 'nome' => 'Carteira digital', 'tipo' => 'pagamento', 'descricao' => 'Gastos do dia a dia']),
                'reserva' => $this->inserir('conta', ['usuario_id' => $usuario, 'nome' => 'Reserva de emergência', 'tipo' => 'investimento', 'descricao' => 'Dinheiro guardado para imprevistos']),
            ];

            $assinaturas = [];
            foreach (self::ASSINATURAS as [$descricao, $valor, $dia, $forma]) {
                $assinaturas[] = $this->inserir('assinatura', [
                    'usuario_id' => $usuario,
                    'conta_id' => $contas['principal'],
                    'categoria_id' => $this->categorias['Assinaturas'],
                    'descricao' => $descricao,
                    'valor' => $valor,
                    'forma_pagamento' => $forma,
                    'dia_cobranca' => $dia,
                    'aviso_dias' => 3,
                    'inicio' => $inicio,
                ]);
            }

            for ($mesesAtras = self::MESES - 1; $mesesAtras >= 0; $mesesAtras--) {
                $this->criarMes($usuario, $mesesAtras, $contas, $assinaturas);
            }
            $this->criarMetas($usuario);
            $this->ajustarSaldosIniciais($contas);

            $this->pdo->commit();
            return $usuario;
        } catch (\Throwable $erro) {
            $this->pdo->rollBack();
            throw $erro;
        }
    }

    /**
     * @param array<string, int> $contas
     * @param int[] $assinaturas
     */
    private function criarMes(int $usuario, int $mesesAtras, array $contas, array $assinaturas): void
    {
        // Receitas: promoção há 4 meses (o salário subiu de R$ 6.200 para R$ 7.500).
        $salario = $mesesAtras <= 3 ? 7500.00 : 6200.00;
        $this->lancar($usuario, $mesesAtras, 'receita', 'Salário mensal', 'Salário', $salario, 5, 'transferencia', $contas['principal']);
        if (self::FREELAS[$mesesAtras] > 0) {
            $descricao = $mesesAtras === 0 ? 'Projeto freelance' : ['Freelance de design', 'Consultoria', 'Projeto freelance'][$mesesAtras % 3];
            $this->lancar($usuario, $mesesAtras, 'receita', $descricao, 'Freelance', self::FREELAS[$mesesAtras], 20, 'pix', $contas['principal']);
        }

        // Despesas do dia a dia, ajustadas para o total de cada categoria no mês.
        foreach ($this->despesasDoMes($mesesAtras) as [$descricao, $categoria, $valor, $dia, $forma, $conta]) {
            $this->lancar($usuario, $mesesAtras, 'despesa', $descricao, $categoria, $valor, $dia, $forma, $contas[$conta]);
        }
        foreach (self::GASTOS_EVENTUAIS as [$quando, $descricao, $categoria, $valor, $dia]) {
            if ($quando === $mesesAtras) {
                $this->lancar($usuario, $mesesAtras, 'despesa', $descricao, $categoria, $valor, $dia, 'credito', $contas['principal']);
            }
        }

        // Cobranças das assinaturas (a Academia teve reajuste no mês atual).
        foreach (self::ASSINATURAS as $indice => [$descricao, $valor, $dia, $forma, $valorAnterior]) {
            $this->lancar($usuario, $mesesAtras, 'despesa', $descricao, 'Assinaturas', $mesesAtras === 0 ? $valor : $valorAnterior,
                $dia, $forma, $contas['principal'], null, $assinaturas[$indice]);
        }

        // Transferências entre contas.
        if ($mesesAtras === 0) {
            $this->lancar($usuario, 0, 'transferencia', 'Reserva do mês', null, 800.00, 2, 'transferencia', $contas['principal'], $contas['reserva']);
            $this->lancar($usuario, 0, 'transferencia', 'Saldo para a carteira', null, 500.00, 10, 'transferencia', $contas['principal'], $contas['digital']);
            $this->lancar($usuario, 0, 'transferencia', 'Reserva extra', null, 1000.00, 12, 'transferencia', $contas['principal'], $contas['reserva']);
        } else {
            if ($mesesAtras <= 10) {
                $this->lancar($usuario, $mesesAtras, 'transferencia', 'Reserva do mês', null, 1000.00, 6, 'transferencia', $contas['principal'], $contas['reserva']);
            }
            $this->lancar($usuario, $mesesAtras, 'transferencia', 'Saldo para a carteira', null, 200.00, 10, 'transferencia', $contas['principal'], $contas['digital']);
        }
    }

    /**
     * No mês atual usa os valores da lista. Nos anteriores mantém os gastos fixos (aluguel, plano de saúde...)
     * e distribui o restante do total da categoria entre os gastos variáveis, na mesma proporção.
     *
     * @return array<int, array{0: string, 1: string, 2: float, 3: int, 4: string, 5: string}>
     */
    private function despesasDoMes(int $mesesAtras): array
    {
        if ($mesesAtras === 0) {
            return array_map(static fn (array $item): array => array_slice($item, 0, 6), self::DESPESAS);
        }
        $resultado = [];
        foreach (self::TOTAIS_ANTERIORES[$mesesAtras] as $categoria => $total) {
            $itens = array_values(array_filter(self::DESPESAS, static fn (array $item): bool => $item[1] === $categoria));
            $fixos = array_sum(array_map(static fn (array $item): float => $item[6] ? $item[2] : 0.0, $itens));
            $variaveis = array_values(array_filter($itens, static fn (array $item): bool => !$item[6]));
            $baseVariavel = array_sum(array_column($variaveis, 2));
            $restante = round($total - $fixos, 2);
            $distribuido = 0.0;
            foreach ($itens as $item) {
                if ($item[6]) {
                    $resultado[] = array_slice($item, 0, 6);
                    continue;
                }
                $ultimo = $item === end($variaveis);
                $valor = $ultimo ? round($restante - $distribuido, 2) : round($item[2] / $baseVariavel * $restante, 2);
                $distribuido += $valor;
                $resultado[] = [$item[0], $item[1], $valor, $item[3], $item[4], $item[5]];
            }
        }
        return $resultado;
    }

    private function criarMetas(int $usuario): void
    {
        $reserva = $this->inserir('meta', [
            'usuario_id' => $usuario,
            'nome' => 'Reserva de emergência',
            'descricao' => 'Mais tranquilidade para o que vier.',
            'valor_objetivo' => 20000.00,
            'valor_atual' => 12000.00,
            'data_inicio' => $this->inicioDoMes(self::MESES - 1)->format('Y-m-d'),
            'data_limite' => $this->hoje->modify('last day of +13 months')->format('Y-m-d'),
            'icone' => 'alvo',
        ]);
        // Depósitos que formam os R$ 12.000: o valor inicial e um depósito por mês.
        $this->inserir('meta_deposito', ['meta_id' => $reserva, 'valor' => 200.00, 'data' => $this->inicioDoMes(self::MESES - 1)->format('Y-m-d')]);
        for ($mesesAtras = 10; $mesesAtras >= 1; $mesesAtras--) {
            $this->inserir('meta_deposito', ['meta_id' => $reserva, 'valor' => 1000.00, 'data' => $this->dia($mesesAtras, 6)->format('Y-m-d')]);
        }
        $this->inserir('meta_deposito', ['meta_id' => $reserva, 'valor' => 800.00, 'data' => $this->dia(0, 2)->format('Y-m-d')]);
        $this->inserir('meta_deposito', ['meta_id' => $reserva, 'valor' => 1000.00, 'data' => $this->dia(0, 12)->format('Y-m-d')]);

        $this->inserir('meta', [
            'usuario_id' => $usuario,
            'nome' => 'Viagem de férias',
            'descricao' => 'Novas memórias começam aqui.',
            'valor_objetivo' => 8000.00,
            'valor_atual' => 0.00,
            'data_inicio' => $this->hoje->format('Y-m-d'),
            'data_limite' => $this->hoje->modify('last day of +20 months')->format('Y-m-d'),
            'icone' => 'grafico',
        ]);
        $this->inserir('meta', [
            'usuario_id' => $usuario,
            'nome' => 'Meu próximo carro',
            'descricao' => 'Planeje hoje, vá mais longe amanhã.',
            'valor_objetivo' => 30000.00,
            'valor_atual' => 0.00,
            'data_inicio' => $this->hoje->format('Y-m-d'),
            'data_limite' => $this->hoje->modify('last day of +37 months')->format('Y-m-d'),
            'icone' => 'carteira',
        ]);
    }

    /** Calcula o saldo inicial de cada conta para que o saldo de hoje fique igual ao planejado. */
    private function ajustarSaldosIniciais(array $contas): void
    {
        $movimento = $this->pdo->prepare(
            "SELECT COALESCE((SELECT SUM(CASE tipo WHEN 'receita' THEN valor ELSE -valor END) FROM transacao
                               WHERE conta_id = :conta1 AND status = 'pago'), 0)
                  + COALESCE((SELECT SUM(valor) FROM transacao
                               WHERE conta_destino_id = :conta2 AND tipo = 'transferencia' AND status = 'pago'), 0)"
        );
        $atualizar = $this->pdo->prepare('UPDATE conta SET saldo_inicial = :saldo WHERE id = :id');
        foreach (self::SALDOS_DE_HOJE as $chave => $saldoDeHoje) {
            $movimento->execute(['conta1' => $contas[$chave], 'conta2' => $contas[$chave]]);
            $liquido = (float) $movimento->fetchColumn();
            $atualizar->execute(['saldo' => round($saldoDeHoje - $liquido, 2), 'id' => $contas[$chave]]);
        }
    }

    private function lancar(
        int $usuario,
        int $mesesAtras,
        string $tipo,
        string $descricao,
        ?string $categoria,
        float $valor,
        int $dia,
        string $forma,
        int $conta,
        ?int $destino = null,
        ?int $assinatura = null,
    ): void {
        $data = $this->dia($mesesAtras, $dia);
        $this->insercao ??= $this->pdo->prepare(
            'INSERT INTO transacao (usuario_id, conta_id, conta_destino_id, categoria_id, assinatura_id, tipo, descricao,
                                    valor, data, forma_pagamento, status)
             VALUES (:usuario, :conta, :destino, :categoria, :assinatura, :tipo, :descricao, :valor, :data, :forma, :status)'
        );
        $this->insercao->execute([
            'usuario' => $usuario,
            'conta' => $conta,
            'destino' => $destino,
            'categoria' => $categoria !== null ? $this->categorias[$categoria] : null,
            'assinatura' => $assinatura,
            'tipo' => $tipo,
            'descricao' => $descricao,
            'valor' => $valor,
            'data' => $data->format('Y-m-d'),
            'forma' => $forma,
            // O que tem data depois de hoje ainda não foi pago.
            'status' => $data > $this->hoje ? 'pendente' : 'pago',
        ]);
    }

    /** @param array<string, mixed> $valores */
    private function inserir(string $tabela, array $valores): int
    {
        $colunas = implode(', ', array_keys($valores));
        $marcadores = implode(', ', array_map(static fn (string $coluna): string => ':' . $coluna, array_keys($valores)));
        $this->pdo->prepare("INSERT INTO {$tabela} ({$colunas}) VALUES ({$marcadores})")->execute($valores);
        return (int) $this->pdo->lastInsertId();
    }

    private function inicioDoMes(int $mesesAtras): DateTimeImmutable
    {
        return $this->hoje->modify('first day of this month')->modify("-{$mesesAtras} months");
    }

    private function dia(int $mesesAtras, int $dia): DateTimeImmutable
    {
        $inicio = $this->inicioDoMes($mesesAtras);
        return $inicio->setDate((int) $inicio->format('Y'), (int) $inicio->format('n'), min($dia, (int) $inicio->format('t')));
    }
}
