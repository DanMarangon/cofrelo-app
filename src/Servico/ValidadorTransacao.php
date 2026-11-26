<?php

declare(strict_types=1);

namespace Cofrelo\Servico;

use DateTimeImmutable;

/**
 * Regras de negócio de uma transação (RN03 a RN07 da documentação).
 *
 * Recebe o que veio do formulário ou da API e devolve os erros por campo e os dados já normalizados.
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

    /**
     * @param array<string, mixed> $dados
     * @param int[] $contas ids das contas ativas do usuário
     * @param array<int, string> $categorias id da categoria => tipo ('receita' ou 'despesa')
     * @return array{0: array<string, string>, 1: array<string, mixed>} erros por campo e dados normalizados
     */
    public function validar(array $dados, array $contas, array $categorias, ?DateTimeImmutable $hoje = null): array
    {
        $hoje ??= new DateTimeImmutable('today');
        $erros = [];

        $tipo = (string) ($dados['tipo'] ?? '');
        if (!in_array($tipo, self::TIPOS, true)) {
            $erros['tipo'] = 'Escolha entre despesa, receita ou transferência.';
        }

        $valor = self::numero($dados['valor'] ?? null);
        if ($valor === null || $valor <= 0) {
            $erros['valor'] = 'Informe um valor maior que zero.';
        } elseif ($valor > self::VALOR_MAXIMO) {
            $erros['valor'] = 'O valor é maior que o permitido.';
        } elseif (abs(round($valor, 2) - $valor) > 1e-9) {
            $erros['valor'] = 'Use no máximo duas casas decimais.';
        }

        $descricao = trim((string) ($dados['descricao'] ?? ''));
        $tamanho = mb_strlen($descricao);
        if ($tamanho < 2 || $tamanho > 120) {
            $erros['descricao'] = 'A descrição precisa ter entre 2 e 120 caracteres.';
        }

        $data = self::data($dados['data'] ?? null);
        if ($data === null) {
            $erros['data'] = 'Informe uma data válida.';
        } elseif ($data < new DateTimeImmutable('2000-01-01') || $data > $hoje->modify('+2 years')) {
            $erros['data'] = 'A data precisa estar entre o ano 2000 e os próximos dois anos.';
        }

        $contaId = self::inteiro($dados['conta_id'] ?? null);
        if ($contaId === null || !in_array($contaId, $contas, true)) {
            $erros['conta_id'] = 'Escolha uma conta válida.';
        }

        $destinoId = null;
        $categoriaId = null;
        if ($tipo === 'transferencia') {
            $destinoId = self::inteiro($dados['conta_destino_id'] ?? null);
            if ($destinoId === null || !in_array($destinoId, $contas, true)) {
                $erros['conta_destino_id'] = 'Escolha a conta de destino.';
            } elseif ($destinoId === $contaId) {
                $erros['conta_destino_id'] = 'A conta de destino precisa ser diferente da conta de origem.';
            }
        } elseif ($tipo !== '' && !isset($erros['tipo'])) {
            $categoriaId = self::inteiro($dados['categoria_id'] ?? null);
            if ($categoriaId === null || !isset($categorias[$categoriaId])) {
                $erros['categoria_id'] = 'Escolha uma categoria.';
            } elseif ($categorias[$categoriaId] !== $tipo) {
                $erros['categoria_id'] = $tipo === 'receita'
                    ? 'Essa categoria é de despesa. Escolha uma categoria de receita.'
                    : 'Essa categoria é de receita. Escolha uma categoria de despesa.';
            }
        }

        $forma = $dados['forma_pagamento'] ?? null;
        $forma = ($forma === '' || $forma === null) ? null : (string) $forma;
        if ($forma !== null && !array_key_exists($forma, self::FORMAS_PAGAMENTO)) {
            $erros['forma_pagamento'] = 'Forma de pagamento inválida.';
        }

        $status = (string) ($dados['status'] ?? 'pago');
        if (!in_array($status, self::STATUS, true)) {
            $erros['status'] = 'O status precisa ser pago ou pendente.';
        }

        $assinatura = filter_var($dados['assinatura'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $dia = null;
        $aviso = null;
        if ($assinatura) {
            if ($tipo !== 'despesa') {
                $erros['assinatura'] = 'Só despesas podem virar assinatura.';
            }
            $dia = self::inteiro($dados['dia_cobranca'] ?? null);
            if ($dia === null || $dia < 1 || $dia > 28) {
                $erros['dia_cobranca'] = 'Escolha um dia de cobrança entre 1 e 28.';
            }
            $aviso = self::inteiro($dados['aviso_dias'] ?? 3);
            if ($aviso === null || $aviso < 0 || $aviso > 15) {
                $erros['aviso_dias'] = 'O aviso precisa ser de 0 a 15 dias antes da cobrança.';
            }
        }

        $observacao = trim((string) ($dados['observacao'] ?? ''));
        if (mb_strlen($observacao) > 255) {
            $erros['observacao'] = 'A observação pode ter no máximo 255 caracteres.';
        }

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

        return [$erros, $normalizados];
    }

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
            $limpo = str_replace(['.', ','], ['', '.'], $limpo);
        }
        return is_numeric($limpo) ? (float) $limpo : null;
    }

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
