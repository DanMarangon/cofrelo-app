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
use Cofrelo\Controlador\PaginaControlador;
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
use Cofrelo\Suporte\Visao;

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
        $this->registrarRotasDasPaginas();
    }

    /**
     * Ponto de partida de toda requisição real (chamado pelo public/index.php):
     * lê o que o navegador mandou, processa, mede o tempo, registra no log e envia a resposta.
     */
    public function executar(): void
    {
        $inicio = hrtime(true);
        $requisicao = Requisicao::capturar();
        Url::definirBase($requisicao->base);
        $id = self::idDaRequisicao($requisicao);
        Log::definirRequisicao($id);

        [$resposta, $rota] = $this->processar($requisicao);
        $resposta = $resposta->comCabecalho('X-Request-Id', $id);

        $duracaoMs = round((hrtime(true) - $inicio) / 1e6, 1);
        Metricas::registrarRequisicao($requisicao->metodo, $rota, $resposta->status, $duracaoMs);
        $this->registrarNoLog($requisicao, $rota, $resposta, $duracaoMs);
        $resposta->enviar();
    }

    /** Processa uma requisição sem enviar nada. Os testes de integração usam este método. */
    public function tratar(Requisicao $requisicao): Resposta
    {
        return $this->processar($requisicao)[0];
    }

    /**
     * As etapas de uma requisição, em ordem:
     *  1. achar a rota (qual controlador atende este endereço);
     *  2. preparar o banco e a sessão;
     *  3. conferir o login e o token CSRF;
     *  4. chamar o controlador, que devolve a resposta.
     * Qualquer erro no caminho vira uma resposta amigável (respostaDoErro).
     *
     * @return array{0: Resposta, 1: string} resposta e padrão da rota (para as métricas)
     */
    private function processar(Requisicao $requisicao): array
    {
        $rota = 'nao-encontrada';
        try {
            $encontrada = $this->rotas->encontrar($requisicao->metodo, $requisicao->caminho);
            $rota = $encontrada['padrao'];
            $opcoes = $encontrada['opcoes'];
            $requisicao = $requisicao->comParametros($encontrada['parametros']);

            $this->prepararBancoESessao($opcoes);
            $redirecionamento = $this->conferirAcesso($requisicao, $opcoes);
            $resposta = $redirecionamento ?? ($encontrada['acao'])($requisicao);
        } catch (\Throwable $erro) {
            $resposta = $this->respostaDoErro($requisicao, $erro);
        }
        return [CabecalhosSeguranca::aplicar($resposta), $rota];
    }

    /** @param array<string, bool> $opcoes opções da rota (sem_banco, sem_sessao, publica, csrf) */
    private function prepararBancoESessao(array $opcoes): void
    {
        if (!($opcoes['sem_banco'] ?? false)) {
            Migrador::prepararSeNecessario(); // cria as tabelas na primeira execução
        }
        if (!($opcoes['sem_sessao'] ?? false)) {
            Sessao::iniciar();
        }
    }

    /**
     * Páginas e API exigem login (menos as rotas públicas). Sem login, a API responde 401 e as páginas mandam
     * para a tela de entrada. Toda alteração (POST, PUT, PATCH, DELETE) também precisa do token CSRF.
     *
     * @param array<string, bool> $opcoes
     * @return Resposta|null o redirecionamento para o login, ou null quando o acesso está liberado
     */
    private function conferirAcesso(Requisicao $requisicao, array $opcoes): ?Resposta
    {
        $precisaDeLogin = !($opcoes['publica'] ?? false);
        if ($precisaDeLogin && Sessao::usuarioId() === null) {
            if ($requisicao->ehApi()) {
                throw ErroHttp::naoAutenticado();
            }
            return Resposta::redirecionar(Url::para('/login'));
        }
        $alteraDados = in_array($requisicao->metodo, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
        if ($alteraDados && ($opcoes['csrf'] ?? true)) {
            Sessao::exigirCsrf($requisicao);
        }
        return null;
    }

    /**
     * Três tipos de erro:
     *  - ErroHttp: erro esperado, com a mensagem pronta para o usuário (404, 422, 429...);
     *  - PDOException: o banco não respondeu (503);
     *  - qualquer outro: falha inesperada (500), registrada no log com os detalhes.
     */
    private function respostaDoErro(Requisicao $requisicao, \Throwable $erro): Resposta
    {
        if ($erro instanceof ErroHttp) {
            $resposta = $this->respostaDeErro($requisicao, $erro->status, $erro->getMessage(), $erro->campos);
            foreach ($erro->cabecalhos as $nome => $valor) {
                $resposta = $resposta->comCabecalho($nome, $valor);
            }
            return $resposta;
        }
        if ($erro instanceof \PDOException) {
            Log::erro('banco.erro', ['mensagem' => $erro->getMessage(), 'codigo' => $erro->getCode()]);
            return $this->respostaDeErro($requisicao, 503, 'O banco de dados não respondeu. Tente de novo em instantes.');
        }
        Log::erro('aplicacao.erro', [
            'tipo' => $erro::class,
            'mensagem' => $erro->getMessage(),
            'arquivo' => $erro->getFile() . ':' . $erro->getLine(),
        ]);
        return $this->respostaDeErro($requisicao, 500, 'Algo deu errado do nosso lado. O erro foi registrado no log.');
    }

    /** Usa o id que veio do cliente (se for válido) ou cria um novo; ele aparece em todas as linhas de log. */
    private static function idDaRequisicao(Requisicao $requisicao): string
    {
        $id = $requisicao->cabecalho('x-request-id');
        return ($id !== null && preg_match('/^[A-Za-z0-9-]{6,64}$/', $id) === 1) ? $id : bin2hex(random_bytes(8));
    }

    /** O /health e o /metrics são chamados a toda hora; só entram no log quando dão erro. */
    private function registrarNoLog(Requisicao $requisicao, string $rota, Resposta $resposta, float $duracaoMs): void
    {
        if (in_array($rota, ['/health', '/metrics'], true) && $resposta->status < 400) {
            return;
        }
        Log::info('http.requisicao', [
            'metodo' => $requisicao->metodo,
            'caminho' => $requisicao->caminho,
            'rota' => $rota,
            'status' => $resposta->status,
            'duracao_ms' => $duracaoMs,
            'usuario_id' => Sessao::usuarioId(),
            'ip' => $requisicao->ip,
        ]);
    }

    /**
     * Erro em JSON para a API e em página HTML para o navegador.
     *
     * @param array<string, string> $campos
     */
    private function respostaDeErro(Requisicao $requisicao, int $status, string $mensagem, array $campos = []): Resposta
    {
        $aceitaHtml = str_contains((string) $requisicao->cabecalho('accept'), 'text/html');
        if (!$requisicao->ehApi() && $aceitaHtml) {
            return Resposta::html(Visao::renderizar('erro', ['status' => $status, 'mensagem' => $mensagem], null), $status);
        }
        $corpo = ['erro' => $mensagem, 'status' => $status];
        if ($campos !== []) {
            $corpo['campos'] = $campos;
        }
        return Resposta::json($corpo, $status);
    }

    private function registrarRotasDasPaginas(): void
    {
        $r = $this->rotas;
        $pagina = static fn (string $nome): \Closure =>
            static fn (): Resposta => (new PaginaControlador(Conexao::obter()))->mostrar($nome);
        $acao = static fn (string $metodo): \Closure =>
            static fn (Requisicao $requisicao): Resposta => (new PaginaControlador(Conexao::obter()))->$metodo($requisicao);
        $publica = ['publica' => true];

        $r->get('/', $acao('inicio'), $publica);
        $r->get('/login', $acao('formularioEntrar'), $publica);
        $r->post('/login', $acao('entrar'), $publica);
        $r->get('/cadastro', $acao('formularioCadastro'), $publica);
        $r->post('/cadastro', $acao('cadastrar'), $publica);
        $r->post('/sair', $acao('sair'), $publica);

        foreach (array_keys(PaginaControlador::PAGINAS) as $nome) {
            $r->get('/' . $nome, $pagina($nome));
        }
        $r->post('/configuracoes/perfil', $acao('atualizarPerfil'));
        $r->post('/configuracoes/senha', $acao('atualizarSenha'));
        $r->post('/configuracoes/excluir', $acao('excluirConta'));
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
