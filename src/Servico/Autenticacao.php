<?php

declare(strict_types=1);

namespace Cofrelo\Servico;

use Cofrelo\Modelo\Usuario;
use Cofrelo\Repositorio\ContaRepositorio;
use Cofrelo\Repositorio\UsuarioRepositorio;
use Cofrelo\Suporte\Log;

/** Cadastro e login, usados tanto pela API quanto pelos formulários das páginas. */
final class Autenticacao
{
    public function __construct(
        private readonly UsuarioRepositorio $usuarios,
        private readonly ContaRepositorio $contas,
    ) {
    }

    /** Devolve o usuário se e-mail e senha conferem. A mensagem de erro é sempre a mesma, para não revelar e-mails cadastrados. */
    public function entrar(string $email, string $senha): ?Usuario
    {
        $usuario = $this->usuarios->buscarPorEmail($email);
        if ($usuario === null || !$usuario->senhaConfere($senha)) {
            // Pequena espera em toda tentativa errada deixa o ataque de força bruta mais lento.
            usleep(300_000);
            Log::aviso('autenticacao.falha', ['email_hash' => substr(hash('sha256', mb_strtolower(trim($email))), 0, 12)]);
            return null;
        }
        Log::info('autenticacao.entrada', ['usuario_id' => $usuario->id]);
        return $usuario;
    }

    /**
     * Cria o usuário e a primeira conta dele (RN02: todo usuário começa com uma conta principal).
     *
     * @param array{nome: string, email: string, senha: string, orcamento_mensal?: float} $dados dados já validados
     * @return array{0: array<string, string>, 1: int|null} erros e id do usuário criado
     */
    public function cadastrar(array $dados): array
    {
        if ($this->usuarios->emailEmUso($dados['email'])) {
            return [['email' => 'Já existe uma conta com esse e-mail.'], null];
        }
        $id = $this->usuarios->criar($dados['nome'], $dados['email'], $dados['senha'], (float) ($dados['orcamento_mensal'] ?? 0));
        $this->contas->criar($id, ['nome' => 'Conta principal', 'tipo' => 'corrente', 'saldo_inicial' => 0.0, 'descricao' => null]);
        Log::info('usuario.cadastrado', ['usuario_id' => $id]);
        return [[], $id];
    }
}
