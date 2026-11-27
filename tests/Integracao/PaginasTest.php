<?php

declare(strict_types=1);

namespace Cofrelo\Testes\Integracao;

use Cofrelo\Aplicacao;
use Cofrelo\Http\Requisicao;
use Cofrelo\Http\Resposta;
use Cofrelo\Suporte\Sessao;

/** Páginas HTML e formulários tradicionais (login, cadastro e configurações). */
final class PaginasTest extends TesteComBanco
{
    private Aplicacao $app;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = new Aplicacao();
    }

    /** @param array<string, mixed> $corpo */
    private function chamar(string $metodo, string $caminho, array $corpo = []): Resposta
    {
        if ($metodo === 'POST') {
            $corpo['_csrf'] = Sessao::tokenCsrf();
        }
        return $this->app->tratar(new Requisicao($metodo, $caminho, [], $corpo, ['accept' => 'text/html']));
    }

    public function testTelaDeLoginAbreParaVisitantes(): void
    {
        $resposta = $this->chamar('GET', '/login');

        self::assertSame(200, $resposta->status);
        self::assertStringContainsString('Entrar no Cofrelo', $resposta->corpo);
    }

    public function testPaginaInternaSemLoginVaiParaOLogin(): void
    {
        $resposta = $this->chamar('GET', '/visao-geral');

        self::assertSame(303, $resposta->status);
        self::assertSame('/login', $resposta->cabecalho('Location'));
    }

    public function testCadastroPeloFormularioEntraEMostraAVisaoGeral(): void
    {
        $resposta = $this->chamar('POST', '/cadastro', [
            'nome' => 'Bruno Lima', 'email' => 'bruno@exemplo.com', 'senha' => 'senha1234', 'confirmacao' => 'senha1234',
        ]);
        self::assertSame('/visao-geral', $resposta->cabecalho('Location'));

        $pagina = $this->chamar('GET', '/visao-geral');
        self::assertSame(200, $pagina->status);
        self::assertStringContainsString('Olá, Bruno', $pagina->corpo);
        self::assertStringContainsString('Conta criada!', $pagina->corpo);
    }

    public function testLoginComSenhaErradaVoltaComAviso(): void
    {
        $this->criarUsuario('ana@exemplo.com');

        $resposta = $this->chamar('POST', '/login', ['email' => 'ana@exemplo.com', 'senha' => 'errada123']);

        self::assertSame('/login', $resposta->cabecalho('Location'));
        self::assertNull(Sessao::usuarioId());
        self::assertStringContainsString('E-mail ou senha incorretos', $this->chamar('GET', '/login')->corpo);
    }

    public function testFormularioSemTokenCsrfERecusado(): void
    {
        $resposta = $this->app->tratar(new Requisicao('POST', '/login', [], ['email' => 'x@y.com', 'senha' => 'abc'], ['accept' => 'text/html']));

        self::assertSame(403, $resposta->status);
        self::assertStringContainsString('Acesso negado', $resposta->corpo);
    }

    public function testAtualizarOrcamentoNasConfiguracoes(): void
    {
        Sessao::entrar($this->criarUsuario('ana@exemplo.com', 3000));

        $this->chamar('POST', '/configuracoes/perfil', ['nome' => 'Ana Souza', 'email' => 'ana@exemplo.com', 'orcamento_mensal' => '4.500,00']);

        $orcamento = self::$pdo->query("SELECT orcamento_mensal FROM usuario WHERE email = 'ana@exemplo.com'")->fetchColumn();
        self::assertSame(4500.0, (float) $orcamento);
    }

    public function testRotaInexistenteMostraPaginaDeErro(): void
    {
        $resposta = $this->chamar('GET', '/pagina-que-nao-existe');

        self::assertSame(404, $resposta->status);
        self::assertStringContainsString('Página não encontrada', $resposta->corpo);
    }
}
