<?php

declare(strict_types=1);

namespace Cofrelo\Repositorio;

use Cofrelo\Modelo\Transacao;

/**
 * Consultas SQL das transações: listagem com filtros e paginação, totais do mês e os números dos gráficos.
 * Toda consulta filtra pelo usuário logado, para ninguém ver os dados de outra pessoa (RN13).
 */
final class TransacaoRepositorio extends Repositorio
{
    private const SELECAO = 'SELECT t.*, c.nome AS conta_nome, cd.nome AS conta_destino_nome,
                                    cat.nome AS categoria_nome, cat.cor AS categoria_cor
                               FROM transacao t
                               JOIN conta c ON c.id = t.conta_id
                               LEFT JOIN conta cd ON cd.id = t.conta_destino_id
                               LEFT JOIN categoria cat ON cat.id = t.categoria_id';

    /** Colunas que o usuário pode usar para ordenar a tabela (lista fechada: nada vem direto da URL). */
    private const ORDENACAO = [
        'data' => 't.data',
        'descricao' => 't.descricao',
        'valor' => 't.valor',
        'categoria' => 'cat.nome',
        'conta' => 'c.nome',
        'status' => 't.status',
    ];

    /**
     * Lista com filtros, ordenação e paginação.
     *
     * @param array<string, mixed> $filtro inicio, fim, tipo, categoria_id, conta_id, busca, status,
     *                                     ordenar, direcao, pagina, por_pagina
     * @return array{itens: Transacao[], total: int}
     */
    public function listar(int $usuarioId, array $filtro = []): array
    {
        [$onde, $parametros] = $this->condicoes($usuarioId, $filtro);

        $coluna = self::ORDENACAO[$filtro['ordenar'] ?? 'data'] ?? 't.data';
        $direcao = strtolower((string) ($filtro['direcao'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $porPagina = max(1, min(100, (int) ($filtro['por_pagina'] ?? 8)));
        $pagina = max(1, (int) ($filtro['pagina'] ?? 1));
        $deslocamento = ($pagina - 1) * $porPagina;

        $total = (int) $this->valor(
            'SELECT COUNT(*) FROM transacao t JOIN conta c ON c.id = t.conta_id
               LEFT JOIN categoria cat ON cat.id = t.categoria_id WHERE ' . $onde,
            $parametros
        );
        $linhas = $this->linhas(
            self::SELECAO . " WHERE {$onde} ORDER BY {$coluna} {$direcao}, t.id DESC LIMIT {$porPagina} OFFSET {$deslocamento}",
            $parametros
        );

        return ['itens' => array_map([Transacao::class, 'daLinha'], $linhas), 'total' => $total];
    }

    /**
     * Monta o WHERE da listagem a partir dos filtros da tela.
     *
     * Cada filtro preenchido acrescenta uma condição (como "t.data >= :inicio") e o valor dela vai separado em
     * $parametros. O PDO junta os dois com segurança (consulta preparada), então o texto digitado pela pessoa
     * nunca vira parte do SQL. No fim, as condições são ligadas com AND.
     *
     * @param array<string, mixed> $filtro
     * @return array{0: string, 1: array<string, mixed>} o WHERE e os valores dos parâmetros
     */
    private function condicoes(int $usuarioId, array $filtro): array
    {
        $onde = ['t.usuario_id = :usuario'];
        $parametros = ['usuario' => $usuarioId];

        if (!empty($filtro['inicio'])) {
            $onde[] = 't.data >= :inicio';
            $parametros['inicio'] = $filtro['inicio'];
        }
        if (!empty($filtro['fim'])) {
            $onde[] = 't.data <= :fim';
            $parametros['fim'] = $filtro['fim'];
        }
        $tipo = $filtro['tipo'] ?? '';
        if (in_array($tipo, ['receita', 'despesa', 'transferencia'], true)) {
            $onde[] = 't.tipo = :tipo';
            $parametros['tipo'] = $tipo;
        } elseif ($tipo === 'assinatura') {
            $onde[] = 't.assinatura_id IS NOT NULL';
        }
        if (!empty($filtro['categoria_id'])) {
            $onde[] = 't.categoria_id = :categoria';
            $parametros['categoria'] = (int) $filtro['categoria_id'];
        }
        if (!empty($filtro['conta_id'])) {
            $onde[] = '(t.conta_id = :conta1 OR t.conta_destino_id = :conta2)';
            $parametros['conta1'] = (int) $filtro['conta_id'];
            $parametros['conta2'] = (int) $filtro['conta_id'];
        }
        if (in_array($filtro['status'] ?? '', ['pago', 'pendente'], true)) {
            $onde[] = 't.status = :status';
            $parametros['status'] = $filtro['status'];
        }
        $busca = trim((string) ($filtro['busca'] ?? ''));
        if ($busca !== '') {
            [$condicao, $valores] = self::condicaoDeBusca($busca);
            $onde[] = $condicao;
            $parametros += $valores;
        }

        return [implode(' AND ', $onde), $parametros];
    }

    /**
     * A busca procura o texto na descrição, na categoria e na conta. Se o texto for um valor, como "286,40",
     * também procura a transação com esse valor exato.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private static function condicaoDeBusca(string $busca): array
    {
        $termo = '%' . addcslashes($busca, '%_\\') . '%'; // % e _ digitados viram texto comum no LIKE
        $condicao = '(t.descricao LIKE :busca1 OR cat.nome LIKE :busca2 OR c.nome LIKE :busca3';
        $valores = ['busca1' => $termo, 'busca2' => $termo, 'busca3' => $termo];
        if (preg_match('/^\d+([.,]\d{1,2})?$/', $busca) === 1) {
            $condicao .= ' OR t.valor = :valor_busca';
            $valores['valor_busca'] = (float) str_replace(',', '.', $busca);
        }
        return [$condicao . ')', $valores];
    }

    public function buscar(int $usuarioId, int $id): ?Transacao
    {
        $linha = $this->linha(self::SELECAO . ' WHERE t.id = :id AND t.usuario_id = :usuario', [
            'id' => $id,
            'usuario' => $usuarioId,
        ]);
        return $linha ? Transacao::daLinha($linha) : null;
    }

    /** @param array<string, mixed> $dados dados já validados pelo ValidadorTransacao */
    public function criar(int $usuarioId, array $dados, ?int $assinaturaId = null): int
    {
        $this->executar(
            'INSERT INTO transacao (usuario_id, conta_id, conta_destino_id, categoria_id, assinatura_id, tipo, descricao,
                                    valor, data, forma_pagamento, status, observacao)
             VALUES (:usuario, :conta, :destino, :categoria, :assinatura, :tipo, :descricao,
                     :valor, :data, :forma, :status, :observacao)',
            [
                'usuario' => $usuarioId,
                'conta' => $dados['conta_id'],
                'destino' => $dados['conta_destino_id'] ?? null,
                'categoria' => $dados['categoria_id'] ?? null,
                'assinatura' => $assinaturaId,
                'tipo' => $dados['tipo'],
                'descricao' => $dados['descricao'],
                'valor' => $dados['valor'],
                'data' => $dados['data'],
                'forma' => $dados['forma_pagamento'] ?? null,
                'status' => $dados['status'] ?? 'pago',
                'observacao' => $dados['observacao'] ?? null,
            ]
        );
        return $this->ultimoId();
    }

    /** @param array<string, mixed> $dados dados já validados pelo ValidadorTransacao */
    public function atualizar(int $usuarioId, int $id, array $dados): bool
    {
        $this->executar(
            'UPDATE transacao
                SET conta_id = :conta, conta_destino_id = :destino, categoria_id = :categoria, tipo = :tipo,
                    descricao = :descricao, valor = :valor, data = :data, forma_pagamento = :forma,
                    status = :status, observacao = :observacao
              WHERE id = :id AND usuario_id = :usuario',
            [
                'conta' => $dados['conta_id'],
                'destino' => $dados['conta_destino_id'] ?? null,
                'categoria' => $dados['categoria_id'] ?? null,
                'tipo' => $dados['tipo'],
                'descricao' => $dados['descricao'],
                'valor' => $dados['valor'],
                'data' => $dados['data'],
                'forma' => $dados['forma_pagamento'] ?? null,
                'status' => $dados['status'] ?? 'pago',
                'observacao' => $dados['observacao'] ?? null,
                'id' => $id,
                'usuario' => $usuarioId,
            ]
        );
        return $this->buscar($usuarioId, $id) !== null;
    }

    public function excluir(int $usuarioId, int $id): bool
    {
        return $this->executar('DELETE FROM transacao WHERE id = :id AND usuario_id = :usuario', [
            'id' => $id,
            'usuario' => $usuarioId,
        ]) > 0;
    }

    public function vincularAssinatura(int $id, int $assinaturaId): void
    {
        $this->executar('UPDATE transacao SET assinatura_id = :assinatura WHERE id = :id', [
            'assinatura' => $assinaturaId,
            'id' => $id,
        ]);
    }

    /**
     * Totais de um período. Entram as pendentes do período também: o mês mostra o que já foi
     * pago e o que ainda vai ser pago nele (RN06).
     *
     * @return array{receitas: float, despesas: float, qtd_receitas: int, qtd_despesas: int}
     */
    public function totais(int $usuarioId, string $inicio, string $fim, ?int $contaId = null): array
    {
        $sql = "SELECT COALESCE(SUM(CASE WHEN tipo = 'receita' THEN valor END), 0) AS receitas,
                       COALESCE(SUM(CASE WHEN tipo = 'despesa' THEN valor END), 0) AS despesas,
                       COUNT(CASE WHEN tipo = 'receita' THEN 1 END) AS qtd_receitas,
                       COUNT(CASE WHEN tipo = 'despesa' THEN 1 END) AS qtd_despesas
                  FROM transacao
                 WHERE usuario_id = :usuario AND data BETWEEN :inicio AND :fim";
        $parametros = ['usuario' => $usuarioId, 'inicio' => $inicio, 'fim' => $fim];
        if ($contaId !== null) {
            $sql .= ' AND conta_id = :conta';
            $parametros['conta'] = $contaId;
        }
        $linha = $this->linha($sql, $parametros) ?? [];
        return [
            'receitas' => round((float) ($linha['receitas'] ?? 0), 2),
            'despesas' => round((float) ($linha['despesas'] ?? 0), 2),
            'qtd_receitas' => (int) ($linha['qtd_receitas'] ?? 0),
            'qtd_despesas' => (int) ($linha['qtd_despesas'] ?? 0),
        ];
    }

    /** @return array<string, array{receitas: float, despesas: float}> chave "AAAA-MM" */
    public function porMes(int $usuarioId, string $inicio, string $fim): array
    {
        $linhas = $this->linhas(
            "SELECT DATE_FORMAT(data, '%Y-%m') AS mes,
                    COALESCE(SUM(CASE WHEN tipo = 'receita' THEN valor END), 0) AS receitas,
                    COALESCE(SUM(CASE WHEN tipo = 'despesa' THEN valor END), 0) AS despesas
               FROM transacao
              WHERE usuario_id = :usuario AND data BETWEEN :inicio AND :fim
              GROUP BY mes ORDER BY mes",
            ['usuario' => $usuarioId, 'inicio' => $inicio, 'fim' => $fim]
        );
        $resultado = [];
        foreach ($linhas as $linha) {
            $resultado[$linha['mes']] = ['receitas' => round((float) $linha['receitas'], 2), 'despesas' => round((float) $linha['despesas'], 2)];
        }
        return $resultado;
    }

    /** @return array<int, array{id: int, nome: string, cor: string, total: float}> do maior gasto para o menor */
    public function despesasPorCategoria(int $usuarioId, string $inicio, string $fim): array
    {
        $linhas = $this->linhas(
            "SELECT cat.id, cat.nome, cat.cor, SUM(t.valor) AS total
               FROM transacao t JOIN categoria cat ON cat.id = t.categoria_id
              WHERE t.usuario_id = :usuario AND t.tipo = 'despesa' AND t.data BETWEEN :inicio AND :fim
              GROUP BY cat.id, cat.nome, cat.cor
              ORDER BY total DESC",
            ['usuario' => $usuarioId, 'inicio' => $inicio, 'fim' => $fim]
        );
        return array_map(static fn (array $linha): array => [
            'id' => (int) $linha['id'],
            'nome' => (string) $linha['nome'],
            'cor' => (string) $linha['cor'],
            'total' => round((float) $linha['total'], 2),
        ], $linhas);
    }

    /** @return array<string, float> data "AAAA-MM-DD" => total gasto no dia */
    public function despesasPorDia(int $usuarioId, string $inicio, string $fim): array
    {
        $linhas = $this->linhas(
            "SELECT data, SUM(valor) AS total FROM transacao
              WHERE usuario_id = :usuario AND tipo = 'despesa' AND data BETWEEN :inicio AND :fim
              GROUP BY data ORDER BY data",
            ['usuario' => $usuarioId, 'inicio' => $inicio, 'fim' => $fim]
        );
        $resultado = [];
        foreach ($linhas as $linha) {
            $resultado[(string) $linha['data']] = round((float) $linha['total'], 2);
        }
        return $resultado;
    }

    /** @return Transacao[] as mais recentes até hoje */
    public function ultimas(int $usuarioId, string $ate, int $limite = 5): array
    {
        $limite = max(1, min(20, $limite));
        $linhas = $this->linhas(
            self::SELECAO . " WHERE t.usuario_id = :usuario AND t.data <= :ate ORDER BY t.data DESC, t.id DESC LIMIT {$limite}",
            ['usuario' => $usuarioId, 'ate' => $ate]
        );
        return array_map([Transacao::class, 'daLinha'], $linhas);
    }

    /** @return Transacao[] pendentes com vencimento entre duas datas (para as notificações) */
    public function pendentesEntre(int $usuarioId, string $de, string $ate): array
    {
        $linhas = $this->linhas(
            self::SELECAO . " WHERE t.usuario_id = :usuario AND t.status = 'pendente' AND t.data BETWEEN :de AND :ate
                              ORDER BY t.data, t.id",
            ['usuario' => $usuarioId, 'de' => $de, 'ate' => $ate]
        );
        return array_map([Transacao::class, 'daLinha'], $linhas);
    }

    public function existeDaAssinaturaNoMes(int $assinaturaId, string $inicio, string $fim): bool
    {
        return $this->valor(
            'SELECT 1 FROM transacao WHERE assinatura_id = :assinatura AND data BETWEEN :inicio AND :fim LIMIT 1',
            ['assinatura' => $assinaturaId, 'inicio' => $inicio, 'fim' => $fim]
        ) !== false;
    }

    public function total(): int
    {
        return (int) $this->valor('SELECT COUNT(*) FROM transacao');
    }
}
