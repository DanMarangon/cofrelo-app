<?php

declare(strict_types=1);

namespace Cofrelo\Servico;

use DateTimeImmutable;

/**
 * Regras de negócio de uma transação (RN03 a RN07 da documentação).
 *
 * Recebe o que veio do formulário ou da API e devolve duas coisas:
 *  - os erros, um por campo (vazio quando está tudo certo);
 *  - os dados já "limpos" (números como número, datas no formato do banco, textos sem espaços sobrando).
 *
 * Cada campo tem o seu próprio método de validação, na mesma ordem em que aparecem no formulário.
 */
final class ValidadorTransacao
{
    public const TIPOS = ['receita', 'despesa', 'transferencia'];
    public const STATUS = ['pago', 'pendente'];
    public const FORMAS_PAGAMENTO = [
        'debito' => 'Cartão de débito',
        'credito' => 'Cartão de crédito',
        'pix' => 'Pix',
        'boleto' => 'Boleto',
        'debito_automatico' => 'Débito automático',
        'transferencia' => 'Transferência',
        'dinheiro' => 'Dinheiro',
    ];
    /** Maior valor que cabe na coluna DECIMAL(12,2). */
    public const VALOR_MAXIMO = 9999999999.99;

    /** @var array<string, string> erros encontrados na validação atual, por campo */
    private array $erros = [];

    /**
     * @param array<string, mixed> $dados o que veio do formulário ou da API
     * @param int[] $contas ids das contas ativas do usuário
     * @param array<int, string> $categorias id da categoria => tipo ('receita' ou 'despesa')
     * @return array{0: array<string, string>, 1: array<string, mixed>} erros por campo e dados normalizados
     */
    public function validar(array $dados, array $contas, array $categorias, ?DateTimeImmutable $hoje = null): array
    {
        $this->erros = [];
        $hoje ??= new DateTimeImmutable('today');

        $tipo = $this->validarTipo($dados);
        $valor = $this->validarValor($dados);
        $descricao = $this->validarDescricao($dados);
        $data = $this->validarData($dados, $hoje);
        $contaId = $this->validarConta($dados, $contas);

        // Transferência leva conta de destino; receita e despesa levam categoria (RN07).
        $destinoId = $tipo === 'transferencia' ? $this->validarContaDestino($dados, $contas, $contaId) : null;
        $categoriaId = in_array($tipo, ['receita', 'despesa'], true) ? $this->validarCategoria($dados, $categorias, $tipo) : null;

        $forma = $this->validarFormaDePagamento($dados);
        $status = $this->validarStatus($dados);
        [$assinatura, $dia, $aviso] = $this->validarAssinatura($dados, $tipo);
        $observacao = $this->validarObservacao($dados);

        $normalizados = [
            'tipo' => $tipo,
            'valor' => $valor !== null ? round($valor, 2) : null,
            'descricao' => $descricao,
            'data' => $data?->format('Y-m-d'),
            'conta_id' => $contaId,
            'conta_destino_id' => $destinoId,
            'categoria_id' => $categoriaId,
            'forma_pagamento' => $tipo === 'transferencia' ? 'transferencia' : $forma,
            'status' => $status,
            'assinatura' => $assinatura,
            'dia_cobranca' => $dia,
            'aviso_dias' => $aviso,
            'observacao' => $observacao === '' ? null : $observacao,
        ];
        return [$this->erros, $normalizados];
    }

    // ------------------------------------------------------------------ um método por campo

    /** @param array<string, mixed> $dados */
    private function validarTipo(array $dados): string
    {
        $tipo = (string) ($dados['tipo'] ?? '');
        if (!in_array($tipo, self::TIPOS, true)) {
            $this->erros['tipo'] = 'Escolha entre despesa, receita ou transferência.';
        }
        return $tipo;
    }

    /**
     * RN03: maior que zero, até o limite do banco e com no máximo duas casas decimais.
     *
     * @param array<string, mixed> $dados
     */
    private function validarValor(array $dados): ?float
    {
        $valor = self::numero($dados['valor'] ?? null);
        if ($valor === null || $valor <= 0) {
            $this->erros['valor'] = 'Informe um valor maior que zero.';
        } elseif ($valor > self::VALOR_MAXIMO) {
            $this->erros['valor'] = 'O valor é maior que o permitido.';
        } elseif (abs(round($valor, 2) - $valor) > 1e-9) {
            $this->erros['valor'] = 'Use no máximo duas casas decimais.';
        }
        return $valor;
    }

    /** @param array<string, mixed> $dados */
    private function validarDescricao(array $dados): string
    {
        $descricao = trim((string) ($dados['descricao'] ?? ''));
        $tamanho = mb_strlen($descricao);
        if ($tamanho < 2 || $tamanho > 120) {
            $this->erros['descricao'] = 'A descrição precisa ter entre 2 e 120 caracteres.';
        }
        return $descricao;
    }

    /**
     * A data precisa existir de verdade e ficar entre 2000 e daqui a dois anos.
     *
     * @param array<string, mixed> $dados
     */
    private function validarData(array $dados, DateTimeImmutable $hoje): ?DateTimeImmutable
    {
        $data = self::data($dados['data'] ?? null);
        if ($data === null) {
            $this->erros['data'] = 'Informe uma data válida.';
        } elseif ($data < new DateTimeImmutable('2000-01-01') || $data > $hoje->modify('+2 years')) {
            $this->erros['data'] = 'A data precisa estar entre o ano 2000 e os próximos dois anos.';
        }
        return $data;
    }

    /**
     * A conta precisa ser uma das contas do próprio usuário.
     *
     * @param array<string, mixed> $dados
     * @param int[] $contas
     */
    private function validarConta(array $dados, array $contas): ?int
    {
        $contaId = self::inteiro($dados['conta_id'] ?? null);
        if ($contaId === null || !in_array($contaId, $contas, true)) {
            $this->erros['conta_id'] = 'Escolha uma conta válida.';
        }
        return $contaId;
    }

    /**
     * @param array<string, mixed> $dados
     * @param int[] $contas
     */
    private function validarContaDestino(array $dados, array $contas, ?int $contaOrigem): ?int
    {
        $destinoId = self::inteiro($dados['conta_destino_id'] ?? null);
        if ($destinoId === null || !in_array($destinoId, $contas, true)) {
            $this->erros['conta_destino_id'] = 'Escolha a conta de destino.';
        } elseif ($destinoId === $contaOrigem) {
            $this->erros['conta_destino_id'] = 'A conta de destino precisa ser diferente da conta de origem.';
        }
        return $destinoId;
    }

    /**
     * Categoria de receita só em receita, de despesa só em despesa.
     *
     * @param array<string, mixed> $dados
     * @param array<int, string> $categorias
     */
    private function validarCategoria(array $dados, array $categorias, string $tipo): ?int
    {
        $categoriaId = self::inteiro($dados['categoria_id'] ?? null);
        if ($categoriaId === null || !isset($categorias[$categoriaId])) {
            $this->erros['categoria_id'] = 'Escolha uma categoria.';
        } elseif ($categorias[$categoriaId] !== $tipo) {
            $this->erros['categoria_id'] = $tipo === 'receita'
                ? 'Essa categoria é de despesa. Escolha uma categoria de receita.'
                : 'Essa categoria é de receita. Escolha uma categoria de despesa.';
        }
        return $categoriaId;
    }

    /**
     * A forma de pagamento é opcional.
     *
     * @param array<string, mixed> $dados
     */
    private function validarFormaDePagamento(array $dados): ?string
    {
        $forma = $dados['forma_pagamento'] ?? null;
        if ($forma === '' || $forma === null) {
            return null;
        }
        $forma = (string) $forma;
        if (!array_key_exists($forma, self::FORMAS_PAGAMENTO)) {
            $this->erros['forma_pagamento'] = 'Forma de pagamento inválida.';
        }
        return $forma;
    }

    /** @param array<string, mixed> $dados */
    private function validarStatus(array $dados): string
    {
        $status = (string) ($dados['status'] ?? 'pago');
        if (!in_array($status, self::STATUS, true)) {
            $this->erros['status'] = 'O status precisa ser pago ou pendente.';
        }
        return $status;
    }

    /**
     * RN11: só despesa vira assinatura, cobrada num dia de 1 a 28, com aviso de 0 a 15 dias antes.
     *
     * @param array<string, mixed> $dados
     * @return array{0: bool, 1: int|null, 2: int|null} é assinatura?, dia da cobrança e dias de aviso
     */
    private function validarAssinatura(array $dados, string $tipo): array
    {
        $assinatura = filter_var($dados['assinatura'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if (!$assinatura) {
            return [false, null, null];
        }
        if ($tipo !== 'despesa') {
            $this->erros['assinatura'] = 'Só despesas podem virar assinatura.';
        }
        $dia = self::inteiro($dados['dia_cobranca'] ?? null);
        if ($dia === null || $dia < 1 || $dia > 28) {
            $this->erros['dia_cobranca'] = 'Escolha um dia de cobrança entre 1 e 28.';
        }
        $aviso = self::inteiro($dados['aviso_dias'] ?? 3);
        if ($aviso === null || $aviso < 0 || $aviso > 15) {
            $this->erros['aviso_dias'] = 'O aviso precisa ser de 0 a 15 dias antes da cobrança.';
        }
        return [true, $dia, $aviso];
    }

    /** @param array<string, mixed> $dados */
    private function validarObservacao(array $dados): string
    {
        $observacao = trim((string) ($dados['observacao'] ?? ''));
        if (mb_strlen($observacao) > 255) {
            $this->erros['observacao'] = 'A observação pode ter no máximo 255 caracteres.';
        }
        return $observacao;
    }

    // ------------------------------------------------------------------ conversões de texto para número e data

    /** Aceita número, "1234.56" ou o formato brasileiro "1.234,56". */
    private static function numero(mixed $valor): ?float
    {
        if (is_int($valor) || is_float($valor)) {
            return (float) $valor;
        }
        if (!is_string($valor)) {
            return null;
        }
        $limpo = trim($valor);
        if (preg_match('/^\d{1,3}(\.\d{3})*,\d{1,2}$|^\d+,\d{1,2}$/', $limpo) === 1) {
            $limpo = str_replace(['.', ','], ['', '.'], $limpo); // "1.234,56" vira "1234.56"
        }
        return is_numeric($limpo) ? (float) $limpo : null;
    }

    /** Aceita 7, "7" ou 7.0; qualquer outra coisa vira null. */
    private static function inteiro(mixed $valor): ?int
    {
        if (is_int($valor)) {
            return $valor;
        }
        if (is_string($valor) && preg_match('/^\d+$/', trim($valor)) === 1) {
            return (int) trim($valor);
        }
        if (is_float($valor) && floor($valor) === $valor) {
            return (int) $valor;
        }
        return null;
    }

    /** Só aceita AAAA-MM-DD de uma data que existe (31/02, por exemplo, é recusada). */
    private static function data(mixed $valor): ?DateTimeImmutable
    {
        if (!is_string($valor) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) !== 1) {
            return null;
        }
        $data = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
        // createFromFormat aceita 30/02 e "pula" para março; comparar de volta pega esse caso.
        return ($data !== false && $data->format('Y-m-d') === $valor) ? $data : null;
    }
}
