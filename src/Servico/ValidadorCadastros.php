<?php

declare(strict_types=1);

namespace Cofrelo\Servico;

use Cofrelo\Modelo\Conta;
use DateTimeImmutable;

/**
 * Validação dos cadastros de conta, meta, depósito, assinatura e usuário.
 * Cada método devolve [erros por campo, dados normalizados].
 */
final class ValidadorCadastros
{
    public const ICONES_META = ['alvo', 'grafico', 'carteira', 'casa', 'aviao', 'carro', 'presente', 'estudo'];

    /**
     * @return array{0: array<string, string>, 1: array<string, mixed>}
     * @param array<string, mixed> $dados
     */
    public function conta(array $dados): array
    {
        $erros = [];
        $nome = self::texto($dados['nome'] ?? '');
        if (mb_strlen($nome) < 2 || mb_strlen($nome) > 80) {
            $erros['nome'] = 'O nome da conta precisa ter entre 2 e 80 caracteres.';
        }
        $tipo = (string) ($dados['tipo'] ?? 'corrente');
        if (!array_key_exists($tipo, Conta::TIPOS)) {
            $erros['tipo'] = 'Escolha um tipo de conta válido.';
        }
        $saldo = self::numero($dados['saldo_inicial'] ?? 0);
        if ($saldo === null || abs($saldo) > 9999999999.99) {
            $erros['saldo_inicial'] = 'Informe o saldo inicial em reais.';
        }
        $descricao = self::texto($dados['descricao'] ?? '');
        if (mb_strlen($descricao) > 160) {
            $erros['descricao'] = 'A descrição pode ter no máximo 160 caracteres.';
        }
        return [$erros, [
            'nome' => $nome,
            'tipo' => $tipo,
            'saldo_inicial' => round($saldo ?? 0, 2),
            'descricao' => $descricao === '' ? null : $descricao,
        ]];
    }

    /**
     * @return array{0: array<string, string>, 1: array<string, mixed>}
     * @param array<string, mixed> $dados
     */
    public function meta(array $dados, DateTimeImmutable $hoje, bool $nova = true): array
    {
        $erros = [];
        $nome = self::texto($dados['nome'] ?? '');
        if (mb_strlen($nome) < 2 || mb_strlen($nome) > 80) {
            $erros['nome'] = 'Dê um nome de 2 a 80 caracteres para a meta.';
        }
        $descricao = self::texto($dados['descricao'] ?? '');
        if (mb_strlen($descricao) > 120) {
            $erros['descricao'] = 'A descrição pode ter no máximo 120 caracteres.';
        }
        $objetivo = self::numero($dados['valor_objetivo'] ?? null);
        if ($objetivo === null || $objetivo <= 0) {
            $erros['valor_objetivo'] = 'Informe quanto você quer juntar.';
        }
        $atual = self::numero($dados['valor_atual'] ?? 0);
        if ($atual === null || $atual < 0) {
            $erros['valor_atual'] = 'O valor já guardado não pode ser negativo.';
        }
        $limite = null;
        $textoLimite = trim((string) ($dados['data_limite'] ?? ''));
        if ($textoLimite !== '') {
            $limite = self::data($textoLimite);
            if ($limite === null) {
                $erros['data_limite'] = 'Informe uma data válida.';
            } elseif ($nova && $limite < $hoje) {
                $erros['data_limite'] = 'O prazo precisa ser uma data futura.';
            }
        }
        $icone = (string) ($dados['icone'] ?? 'alvo');
        if (!in_array($icone, self::ICONES_META, true)) {
            $icone = 'alvo';
        }
        return [$erros, [
            'nome' => $nome,
            'descricao' => $descricao === '' ? null : $descricao,
            'valor_objetivo' => round($objetivo ?? 0, 2),
            'valor_atual' => round($atual ?? 0, 2),
            'data_inicio' => $hoje->format('Y-m-d'),
            'data_limite' => $limite?->format('Y-m-d'),
            'icone' => $icone,
        ]];
    }

    /**
     * "Guardar dinheiro" numa meta.
     *
     * @return array{0: array<string, string>, 1: array{valor: float, data: string}}
     * @param array<string, mixed> $dados
     */
    public function deposito(array $dados, DateTimeImmutable $hoje): array
    {
        $erros = [];
        $valor = self::numero($dados['valor'] ?? null);
        if ($valor === null || $valor <= 0) {
            $erros['valor'] = 'Informe um valor maior que zero.';
        }
        $data = self::data((string) ($dados['data'] ?? $hoje->format('Y-m-d')));
        if ($data === null || $data > $hoje) {
            $erros['data'] = 'A data do depósito não pode ser no futuro.';
        }
        return [$erros, ['valor' => round($valor ?? 0, 2), 'data' => ($data ?? $hoje)->format('Y-m-d')]];
    }

    /**
     * @return array{0: array<string, string>, 1: array<string, mixed>}
     * @param array<string, mixed> $dados
     */
    public function assinatura(array $dados): array
    {
        $erros = [];
        $descricao = self::texto($dados['descricao'] ?? '');
        if (mb_strlen($descricao) < 2 || mb_strlen($descricao) > 120) {
            $erros['descricao'] = 'A descrição precisa ter entre 2 e 120 caracteres.';
        }
        $valor = self::numero($dados['valor'] ?? null);
        if ($valor === null || $valor <= 0) {
            $erros['valor'] = 'Informe um valor maior que zero.';
        }
        $dia = filter_var($dados['dia_cobranca'] ?? null, FILTER_VALIDATE_INT);
        if ($dia === false || $dia < 1 || $dia > 28) {
            $erros['dia_cobranca'] = 'Escolha um dia de cobrança entre 1 e 28.';
        }
        $aviso = filter_var($dados['aviso_dias'] ?? 3, FILTER_VALIDATE_INT);
        if ($aviso === false || $aviso < 0 || $aviso > 15) {
            $erros['aviso_dias'] = 'O aviso precisa ser de 0 a 15 dias antes.';
        }
        return [$erros, [
            'descricao' => $descricao,
            'valor' => round($valor ?? 0, 2),
            'dia_cobranca' => (int) $dia,
            'aviso_dias' => (int) $aviso,
            'ativa' => filter_var($dados['ativa'] ?? true, FILTER_VALIDATE_BOOLEAN),
        ]];
    }

    /**
     * Cadastro e perfil do usuário.
     *
     * @return array{0: array<string, string>, 1: array<string, mixed>}
     * @param array<string, mixed> $dados
     */
    public function usuario(array $dados, bool $exigirSenha): array
    {
        $erros = [];
        $nome = self::texto($dados['nome'] ?? '');
        if (mb_strlen($nome) < 2 || mb_strlen($nome) > 120) {
            $erros['nome'] = 'Informe seu nome (de 2 a 120 caracteres).';
        }
        $email = mb_strtolower(self::texto($dados['email'] ?? ''));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 160) {
            $erros['email'] = 'Informe um e-mail válido.';
        }
        $senha = (string) ($dados['senha'] ?? '');
        if ($exigirSenha) {
            $erros += self::senha($senha, (string) ($dados['confirmacao'] ?? ''));
        }
        $orcamento = self::numero($dados['orcamento_mensal'] ?? 0);
        if ($orcamento === null || $orcamento < 0) {
            $erros['orcamento_mensal'] = 'O orçamento não pode ser negativo.';
        }
        return [$erros, ['nome' => $nome, 'email' => $email, 'senha' => $senha, 'orcamento_mensal' => round($orcamento ?? 0, 2)]];
    }

    /**
     * RN01: senha com pelo menos 8 caracteres, misturando letras e números.
     *
     * @return array<string, string>
     */
    public static function senha(string $senha, string $confirmacao): array
    {
        if (mb_strlen($senha) < 8 || preg_match('/[A-Za-z]/', $senha) !== 1 || preg_match('/\d/', $senha) !== 1) {
            return ['senha' => 'A senha precisa ter pelo menos 8 caracteres, com letras e números.'];
        }
        if ($senha !== $confirmacao) {
            return ['confirmacao' => 'A confirmação não é igual à senha.'];
        }
        return [];
    }

    private static function texto(mixed $valor): string
    {
        return is_scalar($valor) ? trim((string) $valor) : '';
    }

    private static function numero(mixed $valor): ?float
    {
        if (is_int($valor) || is_float($valor)) {
            return (float) $valor;
        }
        if (!is_string($valor)) {
            return null;
        }
        $limpo = trim($valor);
        if ($limpo === '') {
            return null;
        }
        if (preg_match('/^-?\d{1,3}(\.\d{3})*,\d{1,2}$|^-?\d+,\d{1,2}$/', $limpo) === 1) {
            $limpo = str_replace(['.', ','], ['', '.'], $limpo);
        }
        return is_numeric($limpo) ? (float) $limpo : null;
    }

    private static function data(string $valor): ?DateTimeImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) !== 1) {
            return null;
        }
        $data = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
        return ($data !== false && $data->format('Y-m-d') === $valor) ? $data : null;
    }
}
