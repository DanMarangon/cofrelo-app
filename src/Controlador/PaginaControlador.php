<?php

declare(strict_types=1);

namespace Cofrelo\Controlador;

use Cofrelo\Banco\DadosDeExemplo;
use Cofrelo\Config;
use Cofrelo\Http\ErroHttp;
use Cofrelo\Http\Requisicao;
use Cofrelo\Http\Resposta;
use Cofrelo\Repositorio\ContaRepositorio;
use Cofrelo\Repositorio\UsuarioRepositorio;
use Cofrelo\Servico\Autenticacao;
use Cofrelo\Servico\ValidadorCadastros;
use Cofrelo\Suporte\Log;
use Cofrelo\Suporte\Sessao;
use Cofrelo\Suporte\Url;
use Cofrelo\Suporte\Visao;

/**
 * Páginas HTML do sistema. As telas internas são preenchidas pelo JavaScript com os dados da API;
 * login, cadastro e configurações são formulários tradicionais tratados aqui no PHP.
 */
final class PaginaControlador extends Controlador
{
    public const PAGINAS = [
        'visao-geral' => 'Visão geral',
        'transacoes' => 'Transações',
        'contas' => 'Minhas contas',
        'metas' => 'Metas e objetivos',
        'relatorios' => 'Relatórios',
        'configuracoes' => 'Configurações',
        'ajuda' => 'Central de ajuda',
    ];

    public function inicio(Requisicao $requisicao): Resposta
    {
        return Resposta::redirecionar(Url::para(Sessao::usuarioId() !== null ? '/visao-geral' : '/login'));
    }

    public function mostrar(string $pagina): Resposta
    {
        $usuario = $this->usuarios()->buscar($this->usuario());
        if ($usuario === null) {
            Sessao::sair();
            return Resposta::redirecionar(Url::para('/login'));
        }
        $html = Visao::renderizar('paginas/' . $pagina, [
            'titulo' => self::PAGINAS[$pagina],
            'pagina' => $pagina,
            'script' => $pagina,
            'usuario' => $usuario->paraArray(),
            'csrf' => Sessao::tokenCsrf(),
            'avisos' => Sessao::retirarAvisos(),
            'antigos' => Sessao::retirarLembrados(),
            'versao' => Config::versao(),
        ]);
        return Resposta::html($html);
    }

    public function formularioEntrar(Requisicao $requisicao): Resposta
    {
        if (Sessao::usuarioId() !== null) {
            return Resposta::redirecionar(Url::para('/visao-geral'));
        }
        $avisos = Sessao::retirarAvisos();
        if ($requisicao->consulta('saiu') === '1') {
            $avisos[] = ['tipo' => 'sucesso', 'texto' => 'Você saiu da sua conta. Até logo!'];
        }
        return Resposta::html(Visao::renderizar('autenticacao/entrar', [
            'titulo' => 'Entrar',
            'csrf' => Sessao::tokenCsrf(),
            'avisos' => $avisos,
            'antigos' => Sessao::retirarLembrados(),
            'mostrarDemo' => $this->usuarios()->buscarPorEmail(DadosDeExemplo::EMAIL) !== null,
        ], 'autenticacao/base'));
    }

    public function entrar(Requisicao $requisicao): Resposta
    {
        $email = trim((string) ($requisicao->corpo['email'] ?? ''));
        try {
            $usuario = $this->autenticacao()->entrar($email, (string) ($requisicao->corpo['senha'] ?? ''), $requisicao->ip);
        } catch (ErroHttp $erro) {
            Sessao::avisar('erro', $erro->getMessage());
            Sessao::lembrar(['email' => $email]);
            return Resposta::redirecionar(Url::para('/login'));
        }
        if ($usuario === null) {
            Sessao::avisar('erro', 'E-mail ou senha incorretos. Confira e tente de novo.');
            Sessao::lembrar(['email' => $email]);
            return Resposta::redirecionar(Url::para('/login'));
        }
        Sessao::entrar($usuario->id);
        return Resposta::redirecionar(Url::para('/visao-geral'));
    }

    public function formularioCadastro(Requisicao $requisicao): Resposta
    {
        if (Sessao::usuarioId() !== null) {
            return Resposta::redirecionar(Url::para('/visao-geral'));
        }
        $antigos = Sessao::retirarLembrados();
        $erros = $antigos['erros'] ?? [];
        unset($antigos['erros']);
        return Resposta::html(Visao::renderizar('autenticacao/cadastro', [
            'titulo' => 'Criar conta',
            'csrf' => Sessao::tokenCsrf(),
            'avisos' => Sessao::retirarAvisos(),
            'antigos' => $antigos,
            'erros' => is_array($erros) ? $erros : [],
        ], 'autenticacao/base'));
    }

    public function cadastrar(Requisicao $requisicao): Resposta
    {
        [$erros, $dados] = (new ValidadorCadastros())->usuario($requisicao->corpo, true);
        $id = null;
        if ($erros === []) {
            [$erros, $id] = $this->autenticacao()->cadastrar($dados);
        }
        if ($erros !== []) {
            Sessao::lembrar(['nome' => $dados['nome'], 'email' => $dados['email'], 'erros' => $erros]);
            return Resposta::redirecionar(Url::para('/cadastro'));
        }
        Sessao::entrar((int) $id);
        Sessao::avisar('sucesso', 'Conta criada! Comece registrando sua primeira transação.');
        return Resposta::redirecionar(Url::para('/visao-geral'));
    }

    public function sair(Requisicao $requisicao): Resposta
    {
        Log::info('autenticacao.saida', ['usuario_id' => Sessao::usuarioId()]);
        Sessao::sair();
        return Resposta::redirecionar(Url::para('/login?saiu=1'));
    }

    public function atualizarPerfil(Requisicao $requisicao): Resposta
    {
        $usuarioId = $this->usuario();
        [$erros, $dados] = (new ValidadorCadastros())->usuario($requisicao->corpo, false);
        if ($erros === [] && $this->usuarios()->emailEmUso($dados['email'], $usuarioId)) {
            $erros['email'] = 'Esse e-mail já está em uso por outra conta.';
        }
        if ($erros !== []) {
            Sessao::avisar('erro', implode(' ', $erros));
            Sessao::lembrar([
                'nome' => $dados['nome'],
                'email' => $dados['email'],
                'orcamento_mensal' => (string) ($requisicao->corpo['orcamento_mensal'] ?? ''),
            ]);
            return Resposta::redirecionar(Url::para('/configuracoes'));
        }
        $this->usuarios()->atualizarPerfil($usuarioId, $dados['nome'], $dados['email'], $dados['orcamento_mensal']);
        Log::info('usuario.perfil_atualizado', ['usuario_id' => $usuarioId]);
        Sessao::avisar('sucesso', 'Perfil e orçamento atualizados.');
        return Resposta::redirecionar(Url::para('/configuracoes'));
    }

    public function atualizarSenha(Requisicao $requisicao): Resposta
    {
        $usuario = $this->usuarios()->buscar($this->usuario());
        $atual = (string) ($requisicao->corpo['senha_atual'] ?? '');
        if ($usuario === null || !$usuario->senhaConfere($atual)) {
            Sessao::avisar('erro', 'A senha atual não confere.');
            return Resposta::redirecionar(Url::para('/configuracoes'));
        }
        $erros = ValidadorCadastros::senha((string) ($requisicao->corpo['senha'] ?? ''), (string) ($requisicao->corpo['confirmacao'] ?? ''));
        if ($erros !== []) {
            Sessao::avisar('erro', implode(' ', $erros));
            return Resposta::redirecionar(Url::para('/configuracoes'));
        }
        $this->usuarios()->atualizarSenha($usuario->id, (string) $requisicao->corpo['senha']);
        Log::info('usuario.senha_alterada', ['usuario_id' => $usuario->id]);
        Sessao::avisar('sucesso', 'Senha alterada com sucesso.');
        return Resposta::redirecionar(Url::para('/configuracoes'));
    }

    public function excluirConta(Requisicao $requisicao): Resposta
    {
        $usuario = $this->usuarios()->buscar($this->usuario());
        $confirmou = trim((string) ($requisicao->corpo['confirmacao'] ?? '')) === 'EXCLUIR';
        if ($usuario === null || !$confirmou || !$usuario->senhaConfere((string) ($requisicao->corpo['senha'] ?? ''))) {
            Sessao::avisar('erro', 'Para excluir, digite EXCLUIR e informe sua senha corretamente.');
            return Resposta::redirecionar(Url::para('/configuracoes'));
        }
        $this->usuarios()->excluir($usuario->id);
        Log::info('usuario.excluido', ['usuario_id' => $usuario->id]);
        Sessao::sair();
        return Resposta::redirecionar(Url::para('/login'));
    }

    private function usuarios(): UsuarioRepositorio
    {
        return new UsuarioRepositorio($this->pdo);
    }

    private function autenticacao(): Autenticacao
    {
        return new Autenticacao($this->usuarios(), new ContaRepositorio($this->pdo));
    }
}
