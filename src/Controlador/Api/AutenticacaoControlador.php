<?php

declare(strict_types=1);

namespace Cofrelo\Controlador\Api;

use Cofrelo\Controlador\Controlador;
use Cofrelo\Http\ErroHttp;
use Cofrelo\Http\Requisicao;
use Cofrelo\Http\Resposta;
use Cofrelo\Repositorio\ContaRepositorio;
use Cofrelo\Repositorio\UsuarioRepositorio;
use Cofrelo\Servico\Autenticacao;
use Cofrelo\Servico\ValidadorCadastros;
use Cofrelo\Suporte\Sessao;

/**
 * POST /api/auth/entrar, POST /api/auth/cadastro, POST /api/auth/sair e GET /api/auth/eu.
 * Depois de entrar, guarde o cookie da sessão e envie o token CSRF devolvido no cabeçalho X-CSRF-Token.
 */
final class AutenticacaoControlador extends Controlador
{
    public function entrar(Requisicao $requisicao): Resposta
    {
        $email = (string) ($requisicao->corpo['email'] ?? '');
        $senha = (string) ($requisicao->corpo['senha'] ?? '');
        $usuario = $this->autenticacao()->entrar($email, $senha, $requisicao->ip);
        if ($usuario === null) {
            throw new ErroHttp(401, 'E-mail ou senha incorretos.');
        }
        Sessao::entrar($usuario->id);
        return Resposta::json(['usuario' => $usuario->paraArray(), 'csrf' => Sessao::tokenCsrf()]);
    }

    public function cadastrar(Requisicao $requisicao): Resposta
    {
        [$erros, $dados] = (new ValidadorCadastros())->usuario($requisicao->corpo, true);
        $this->falharSeInvalido($erros);
        [$erros, $id] = $this->autenticacao()->cadastrar($dados);
        $this->falharSeInvalido($erros);
        Sessao::entrar((int) $id);
        $usuario = (new UsuarioRepositorio($this->pdo))->buscar((int) $id);
        return Resposta::json(['usuario' => $usuario?->paraArray(), 'csrf' => Sessao::tokenCsrf()], 201);
    }

    public function sair(Requisicao $requisicao): Resposta
    {
        Sessao::sair();
        return Resposta::semConteudo();
    }

    public function eu(Requisicao $requisicao): Resposta
    {
        $usuario = (new UsuarioRepositorio($this->pdo))->buscar($this->usuario());
        if ($usuario === null) {
            Sessao::sair();
            throw ErroHttp::naoAutenticado();
        }
        return Resposta::json(['usuario' => $usuario->paraArray(), 'csrf' => Sessao::tokenCsrf()]);
    }

    private function autenticacao(): Autenticacao
    {
        return new Autenticacao(new UsuarioRepositorio($this->pdo), new ContaRepositorio($this->pdo));
    }
}
