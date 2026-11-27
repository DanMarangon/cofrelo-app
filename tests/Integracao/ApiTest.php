<?php

declare(strict_types=1);

namespace Cofrelo\Testes\Integracao;

use Cofrelo\Aplicacao;
use Cofrelo\Banco\DadosDeExemplo;
use Cofrelo\Http\Requisicao;
use Cofrelo\Http\Resposta;
use Cofrelo\Suporte\LimiteDeTentativas;
use Cofrelo\Suporte\Relogio;
use Cofrelo\Suporte\Sessao;

/** Testa os endpoints da API do jeito que o navegador e o curl usam: método, caminho, corpo JSON e sessão. */
final class ApiTest extends TesteComBanco
{
    private Aplicacao $app;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = new Aplicacao();
        Relogio::fixar('2026-11-26');
        LimiteDeTentativas::zerar();
    }

    protected function tearDown(): void
    {
        Relogio::fixar(null);
        LimiteDeTentativas::zerar();
    }

    /** @param array<string, mixed> $corpo */
    private function chamar(string $metodo, string $caminho, array $corpo = [], bool $comCsrf = true): Resposta
    {
        [$rota, $consulta] = array_pad(explode('?', $caminho, 2), 2, '');
        parse_str($consulta, $parametros);
        $cabecalhos = ['content-type' => 'application/json'];
        if ($comCsrf && isset($_SESSION['csrf'])) {
            $cabecalhos['x-csrf-token'] = $_SESSION['csrf'];
        }
        return $this->app->tratar(new Requisicao($metodo, $rota, $parametros, $corpo, $cabecalhos));
    }

    private function entrarComoNovoUsuario(): void
    {
        $resposta = $this->chamar('POST', '/api/auth/cadastro', [
            'nome' => 'Ana Teste', 'email' => 'ana@exemplo.com', 'senha' => 'senha1234', 'confirmacao' => 'senha1234',
        ]);
        self::assertSame(201, $resposta->status);
    }

    /**
     * @return array<string, mixed>
     * @param array<string, mixed> $alteracoes
     */
    private function novaDespesa(array $alteracoes = []): array
    {
        $contas = $this->chamar('GET', '/api/contas')->dados();
        return array_merge([
            'tipo' => 'despesa', 'valor' => 286.40, 'descricao' => 'Supermercado', 'data' => '2026-11-18',
            'conta_id' => $contas['contas'][0]['id'], 'categoria_id' => $this->categoria('Alimentação'),
            'forma_pagamento' => 'debito', 'status' => 'pago',
        ], $alteracoes);
    }

    public function testSaudeRespondeComVersaoEStatusDoBanco(): void
    {
        $resposta = $this->chamar('GET', '/health');

        self::assertSame(200, $resposta->status);
        self::assertSame('ok', $resposta->dados()['status']);
        self::assertSame('ok', $resposta->dados()['banco']);
        self::assertSame('DENY', $resposta->cabecalho('X-Frame-Options'));
    }

    public function testApiExigeLogin(): void
    {
        $resposta = $this->chamar('GET', '/api/transacoes');

        self::assertSame(401, $resposta->status);
        self::assertSame('Entre na sua conta para continuar.', $resposta->dados()['erro']);
    }

    public function testLoginFicaBloqueadoDepoisDeCincoSenhasErradas(): void
    {
        $this->entrarComoNovoUsuario();
        $this->chamar('POST', '/api/auth/sair');
        for ($i = 1; $i <= LimiteDeTentativas::MAXIMO_POR_CONTA; $i++) {
            $errada = $this->chamar('POST', '/api/auth/entrar', ['email' => 'ana@exemplo.com', 'senha' => "errada{$i}"], false);
            self::assertSame(401, $errada->status);
        }

        // Bloqueado: nem a senha certa entra até a janela de 15 minutos acabar.
        $bloqueada = $this->chamar('POST', '/api/auth/entrar', ['email' => 'ana@exemplo.com', 'senha' => 'senha1234'], false);
        self::assertSame(429, $bloqueada->status);
        self::assertSame((string) LimiteDeTentativas::JANELA_SEGUNDOS, $bloqueada->cabecalho('Retry-After'));
        self::assertStringContainsString('Espere 15 minutos', $bloqueada->dados()['erro']);
    }

    public function testCadastroCriaAContaPrincipalEExigeTokenCsrf(): void
    {
        $this->entrarComoNovoUsuario();

        $contas = $this->chamar('GET', '/api/contas')->dados();
        self::assertSame('Conta principal', $contas['contas'][0]['nome']);

        $semToken = $this->chamar('POST', '/api/transacoes', $this->novaDespesa(), false);
        self::assertSame(403, $semToken->status);

        $comToken = $this->chamar('POST', '/api/transacoes', $this->novaDespesa());
        self::assertSame(201, $comToken->status);
        self::assertSame(286.4, $comToken->dados()['valor']);
        self::assertSame('Cartão de débito', $comToken->dados()['detalhe']);
    }

    public function testTransacaoInvalidaDevolve422ComOsCampos(): void
    {
        $this->entrarComoNovoUsuario();

        $resposta = $this->chamar('POST', '/api/transacoes', $this->novaDespesa(['valor' => 0, 'descricao' => '']));

        self::assertSame(422, $resposta->status);
        self::assertArrayHasKey('valor', $resposta->dados()['campos']);
        self::assertArrayHasKey('descricao', $resposta->dados()['campos']);
    }

    public function testCrudDeTransacaoPelaApi(): void
    {
        $this->entrarComoNovoUsuario();
        $criada = $this->chamar('POST', '/api/transacoes', $this->novaDespesa())->dados();

        $lista = $this->chamar('GET', '/api/transacoes?inicio=2026-11-01&fim=2026-11-30')->dados();
        self::assertSame(1, $lista['total']);
        self::assertSame(286.4, $lista['totais']['despesas']);

        $alterada = $this->chamar('PUT', '/api/transacoes/' . $criada['id'], $this->novaDespesa(['descricao' => 'Feira', 'valor' => 65]));
        self::assertSame('Feira', $alterada->dados()['descricao']);

        self::assertSame(204, $this->chamar('DELETE', '/api/transacoes/' . $criada['id'])->status);
        self::assertSame(404, $this->chamar('GET', '/api/transacoes/' . $criada['id'])->status);
    }

    public function testDespesaMarcadaComoAssinaturaCriaACobrancaMensal(): void
    {
        $this->entrarComoNovoUsuario();

        $this->chamar('POST', '/api/transacoes', $this->novaDespesa([
            'descricao' => 'Netflix', 'valor' => 55.90, 'categoria_id' => $this->categoria('Assinaturas'),
            'assinatura' => true, 'dia_cobranca' => 8, 'aviso_dias' => 3,
        ]));

        $assinaturas = $this->chamar('GET', '/api/assinaturas')->dados();
        self::assertSame('Netflix', $assinaturas['assinaturas'][0]['descricao']);
        self::assertSame('2026-12-08', $assinaturas['assinaturas'][0]['proxima_cobranca']);
        self::assertSame(55.9, $assinaturas['total_mensal']);
    }

    public function testResumoDoMesComOsDadosDeExemplo(): void
    {
        $usuario = (new DadosDeExemplo(self::$pdo, Relogio::hoje()))->criar();
        Sessao::entrar($usuario);

        $resumo = $this->chamar('GET', '/api/resumo?mes=2026-11')->dados();

        self::assertSame(8500.0, $resumo['receitas']);
        self::assertSame(3920.0, $resumo['despesas']);
        self::assertSame(24580.0, $resumo['saldo_total']);
        self::assertSame(-4.2, $resumo['despesas_variacao']);
        self::assertSame(65.3, $resumo['orcamento']['percentual']);
        self::assertSame(['Moradia', 45], [$resumo['categorias'][0]['nome'], $resumo['categorias'][0]['percentual']]);
        self::assertCount(6, $resumo['fluxo']);
        self::assertSame('Reserva de emergência', $resumo['meta_destaque']['nome']);
        self::assertSame(0, $resumo['assinaturas_lancadas']);
    }

    public function testRelatorioTrazComparacaoEMapaDeGastos(): void
    {
        $usuario = (new DadosDeExemplo(self::$pdo, Relogio::hoje()))->criar();
        Sessao::entrar($usuario);

        $relatorio = $this->chamar('GET', '/api/relatorios?inicio=2026-06-01&fim=2026-11-30')->dados();

        self::assertCount(6, $relatorio['evolucao']);
        self::assertSame('Novembro', $relatorio['comparacao']['mes_atual']);
        self::assertSame(4090.0, $relatorio['comparacao']['categorias']['total']['anterior']);
        self::assertGreaterThan(100, $relatorio['mapa_de_gastos']['dias_com_gasto']);
    }

    public function testGuardarDinheiroAtualizaAMeta(): void
    {
        $this->entrarComoNovoUsuario();
        $meta = $this->chamar('POST', '/api/metas', ['nome' => 'Viagem', 'valor_objetivo' => 1000, 'data_limite' => '2027-06-30'])->dados();
        self::assertSame('pronta', $meta['status']);

        $depois = $this->chamar('POST', "/api/metas/{$meta['id']}/depositos", ['valor' => 250])->dados();

        self::assertSame(25.0, $depois['percentual']);
        self::assertSame('em_progresso', $depois['status']);
    }

    public function testContaComTransacoesNaoPodeSerExcluida(): void
    {
        $this->entrarComoNovoUsuario();
        $despesa = $this->novaDespesa();
        $this->chamar('POST', '/api/transacoes', $despesa);

        $resposta = $this->chamar('DELETE', '/api/contas/' . $despesa['conta_id']);

        self::assertSame(409, $resposta->status);
    }

    public function testCaminhoInexistenteEMetodoErrado(): void
    {
        self::assertSame(404, $this->chamar('GET', '/api/nada')->status);
        self::assertSame(405, $this->chamar('PATCH', '/api/transacoes')->status);
    }
}
