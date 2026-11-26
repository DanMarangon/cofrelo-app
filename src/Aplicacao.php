<?php

declare(strict_types=1);

namespace Cofrelo;

use Cofrelo\Banco\Conexao;
use Cofrelo\Banco\Migrador;
use Cofrelo\Controlador\Api\AssinaturaControlador;
use Cofrelo\Controlador\Api\AutenticacaoControlador;
use Cofrelo\Controlador\Api\ContaControlador;
use Cofrelo\Controlador\Api\MetaControlador;
use Cofrelo\Controlador\Api\PainelControlador;
use Cofrelo\Controlador\Api\TransacaoControlador;
use Cofrelo\Controlador\SaudeControlador;
use Cofrelo\Http\ErroHttp;
use Cofrelo\Http\Requisicao;
use Cofrelo\Http\Resposta;
use Cofrelo\Http\Roteador;
use Cofrelo\Suporte\CabecalhosSeguranca;
use Cofrelo\Suporte\Log;
use Cofrelo\Suporte\Metricas;
use Cofrelo\Suporte\Sessao;
use Cofrelo\Suporte\Url;

/**
 * Coração da aplicação: registra as rotas, confere login e token CSRF, chama o controlador,
 * transforma erros em respostas amigáveis e registra cada requisição no log.
 */
final class Aplicacao
{
    private Roteador $rotas;

    public function __construct()
    {
        $this->rotas = new Roteador();
        $this->registrarRotasDaApi();
    }

    /** Usado pelo public/index.php: lê a requisição real, responde e grava o log. */
    public function executar(): void
    {
        $inicio = hrtime(true);
        $requisicao = Requisicao::capturar();
        Url::definirBase($requisicao->base);

        $id = $requisicao->cabecalho('x-request-id');
        if ($id === null || preg_match('/^[A-Za-z0-9-]{6,64}$/', $id) !== 1) {
            $id = bin2hex(random_bytes(8));
        }
        Log::definirRequisicao($id);

        [$resposta, $rota] = $this->processar($requisicao);
        $resposta = $resposta->comCabecalho('X-Request-Id', $id);

        $duracao = round((hrtime(true) - $inicio) / 1e6, 1);
        Metricas::registrarRequisicao($requisicao->metodo, $rota, $resposta->status, $duracao);
        if (!in_array($rota, ['/health', '/metrics'], true) || $resposta->status >= 400) {
            Log::info('http.requisicao', [
                'metodo' => $requisicao->metodo,
                'caminho' => $requisicao->caminho,
                'rota' => $rota,
                'status' => $resposta->status,
                'duracao_ms' => $duracao,
                'usuario_id' => Sessao::usuarioId(),
                'ip' => $requisicao->ip,
            ]);
        }
        $resposta->enviar();
    }

    /** Processa uma requisição sem enviar nada. Os testes de integração usam este método. */
    public function tratar(Requisicao $requisicao): Resposta
    {
        return $this->processar($requisicao)[0];
    }

    /** @return array{0: Resposta, 1: string} resposta e padrão da rota (para as métricas) */
    private function processar(Requisicao $requisicao): array
    {
        $rota = 'nao-encontrada';
        try {
            $encontrada = $this->rotas->encontrar($requisicao->metodo, $requisicao->caminho);
            $rota = $encontrada['padrao'];
            $opcoes = $encontrada['opcoes'];
            $requisicao = $requisicao->comParametros($encontrada['parametros']);

            if (!($opcoes['sem_banco'] ?? false)) {
                Migrador::prepararSeNecessario();
            }
            if (!($opcoes['sem_sessao'] ?? false)) {
                Sessao::iniciar();
            }
            if (!($opcoes['publica'] ?? false) && Sessao::usuarioId() === null) {
                if ($requisicao->ehApi()) {
                    throw ErroHttp::naoAutenticado();
                }
                return [CabecalhosSeguranca::aplicar(Resposta::redirecionar(Url::para('/login'))), $rota];
            }
            if (in_array($requisicao->metodo, ['POST', 'PUT', 'PATCH', 'DELETE'], true) && ($opcoes['csrf'] ?? true)) {
                Sessao::exigirCsrf($requisicao);
            }
            $resposta = ($encontrada['acao'])($requisicao);
        } catch (ErroHttp $erro) {
            $resposta = $this->respostaDeErro($requisicao, $erro->status, $erro->getMessage(), $erro->campos);
        } catch (\PDOException $erro) {
            Log::erro('banco.erro', ['mensagem' => $erro->getMessage(), 'codigo' => $erro->getCode()]);
            $resposta = $this->respostaDeErro($requisicao, 503, 'O banco de dados não respondeu. Tente de novo em instantes.');
        } catch (\Throwable $erro) {
            Log::erro('aplicacao.erro', [
                'tipo' => $erro::class,
                'mensagem' => $erro->getMessage(),
                'arquivo' => $erro->getFile() . ':' . $erro->getLine(),
            ]);
            $resposta = $this->respostaDeErro($requisicao, 500, 'Algo deu errado do nosso lado. O erro foi registrado no log.');
        }
        return [CabecalhosSeguranca::aplicar($resposta), $rota];
    }

    /** @param array<string, string> $campos */
    private function respostaDeErro(Requisicao $requisicao, int $status, string $mensagem, array $campos = []): Resposta
    {
        $corpo = ['erro' => $mensagem, 'status' => $status];
        if ($campos !== []) {
            $corpo['campos'] = $campos;
        }
        return Resposta::json($corpo, $status);
    }

    private function registrarRotasDaApi(): void
    {
        $r = $this->rotas;
        $api = static fn (string $classe, string $metodo): \Closure =>
            static fn (Requisicao $requisicao): Resposta => (new $classe(Conexao::obter()))->$metodo($requisicao);
        $saude = ['publica' => true, 'sem_banco' => true, 'sem_sessao' => true];

        // Observabilidade
        $r->get('/health', static fn (Requisicao $q): Resposta => (new SaudeControlador())->saude($q), $saude);
        $r->get('/api/health', static fn (Requisicao $q): Resposta => (new SaudeControlador())->saude($q), $saude);
        $r->get('/metrics', static fn (Requisicao $q): Resposta => (new SaudeControlador())->metricas($q), $saude);
        $r->get('/api', static fn (): Resposta => Resposta::json(self::indiceDaApi()), $saude);

        // Autenticação
        $r->post('/api/auth/entrar', $api(AutenticacaoControlador::class, 'entrar'), ['publica' => true, 'csrf' => false]);
        $r->post('/api/auth/cadastro', $api(AutenticacaoControlador::class, 'cadastrar'), ['publica' => true, 'csrf' => false]);
        $r->post('/api/auth/sair', $api(AutenticacaoControlador::class, 'sair'), ['publica' => true]);
        $r->get('/api/auth/eu', $api(AutenticacaoControlador::class, 'eu'));

        // Painel, relatórios, notificações e categorias
        $r->get('/api/resumo', $api(PainelControlador::class, 'resumo'));
        $r->get('/api/relatorios', $api(PainelControlador::class, 'relatorio'));
        $r->get('/api/relatorios/exportar', $api(PainelControlador::class, 'exportarRelatorio'));
        $r->get('/api/notificacoes', $api(PainelControlador::class, 'notificacoes'));
        $r->get('/api/categorias', $api(PainelControlador::class, 'categorias'));

        // Contas
        $r->get('/api/contas', $api(ContaControlador::class, 'listar'));
        $r->post('/api/contas', $api(ContaControlador::class, 'criar'));
        $r->get('/api/contas/{id}', $api(ContaControlador::class, 'mostrar'));
        $r->put('/api/contas/{id}', $api(ContaControlador::class, 'atualizar'));
        $r->delete('/api/contas/{id}', $api(ContaControlador::class, 'excluir'));

        // Transações (a rota de exportar vem antes da rota com {id})
        $r->get('/api/transacoes/exportar', $api(TransacaoControlador::class, 'exportar'));
        $r->get('/api/transacoes', $api(TransacaoControlador::class, 'listar'));
        $r->post('/api/transacoes', $api(TransacaoControlador::class, 'criar'));
        $r->get('/api/transacoes/{id}', $api(TransacaoControlador::class, 'mostrar'));
        $r->put('/api/transacoes/{id}', $api(TransacaoControlador::class, 'atualizar'));
        $r->delete('/api/transacoes/{id}', $api(TransacaoControlador::class, 'excluir'));

        // Assinaturas
        $r->get('/api/assinaturas', $api(AssinaturaControlador::class, 'listar'));
        $r->put('/api/assinaturas/{id}', $api(AssinaturaControlador::class, 'atualizar'));
        $r->delete('/api/assinaturas/{id}', $api(AssinaturaControlador::class, 'excluir'));

        // Metas
        $r->get('/api/metas', $api(MetaControlador::class, 'listar'));
        $r->post('/api/metas', $api(MetaControlador::class, 'criar'));
        $r->get('/api/metas/{id}', $api(MetaControlador::class, 'mostrar'));
        $r->put('/api/metas/{id}', $api(MetaControlador::class, 'atualizar'));
        $r->delete('/api/metas/{id}', $api(MetaControlador::class, 'excluir'));
        $r->post('/api/metas/{id}/depositos', $api(MetaControlador::class, 'depositar'));
    }

    /** @return array<string, mixed> */
    private static function indiceDaApi(): array
    {
        return [
            'aplicacao' => 'Cofrelo',
            'versao' => Config::versao(),
            'autenticacao' => 'POST /api/auth/entrar com {"email","senha"}; depois envie o cookie da sessão e o cabeçalho X-CSRF-Token.',
            'endpoints' => [
                'GET /health', 'GET /metrics',
                'POST /api/auth/entrar', 'POST /api/auth/cadastro', 'POST /api/auth/sair', 'GET /api/auth/eu',
                'GET /api/resumo?mes=AAAA-MM', 'GET /api/relatorios?inicio=AAAA-MM-DD&fim=AAAA-MM-DD',
                'GET /api/relatorios/exportar', 'GET /api/notificacoes', 'GET /api/categorias',
                'GET|POST /api/contas', 'GET|PUT|DELETE /api/contas/{id}',
                'GET|POST /api/transacoes', 'GET|PUT|DELETE /api/transacoes/{id}', 'GET /api/transacoes/exportar',
                'GET /api/assinaturas', 'PUT|DELETE /api/assinaturas/{id}',
                'GET|POST /api/metas', 'GET|PUT|DELETE /api/metas/{id}', 'POST /api/metas/{id}/depositos',
            ],
        ];
    }
}
