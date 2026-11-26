<?php

declare(strict_types=1);

namespace Cofrelo\Http;

/**
 * Liga cada método + caminho a uma ação. Caminhos aceitam parâmetros, como /api/metas/{id}.
 * Opções da rota: publica (não exige login), csrf (padrão true), sem_banco, sem_sessao.
 */
final class Roteador
{
    /** @var array<int, array{metodo: string, padrao: string, regex: string, acao: callable, opcoes: array<string, bool>}> */
    private array $rotas = [];

    /** @param array<string, bool> $opcoes */
    public function get(string $padrao, callable $acao, array $opcoes = []): void
    {
        $this->adicionar('GET', $padrao, $acao, $opcoes);
    }

    /** @param array<string, bool> $opcoes */
    public function post(string $padrao, callable $acao, array $opcoes = []): void
    {
        $this->adicionar('POST', $padrao, $acao, $opcoes);
    }

    /** @param array<string, bool> $opcoes */
    public function put(string $padrao, callable $acao, array $opcoes = []): void
    {
        $this->adicionar('PUT', $padrao, $acao, $opcoes);
    }

    /** @param array<string, bool> $opcoes */
    public function delete(string $padrao, callable $acao, array $opcoes = []): void
    {
        $this->adicionar('DELETE', $padrao, $acao, $opcoes);
    }

    /** @param array<string, bool> $opcoes */
    public function adicionar(string $metodo, string $padrao, callable $acao, array $opcoes = []): void
    {
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $padrao) . '$#';
        $this->rotas[] = ['metodo' => $metodo, 'padrao' => $padrao, 'regex' => $regex, 'acao' => $acao, 'opcoes' => $opcoes];
    }

    /**
     * @return array{acao: callable, parametros: array<string, string>, opcoes: array<string, bool>, padrao: string}
     * @throws ErroHttp 404 se o caminho não existe, 405 se existe com outro método
     */
    public function encontrar(string $metodo, string $caminho): array
    {
        $metodosPermitidos = [];
        foreach ($this->rotas as $rota) {
            if (preg_match($rota['regex'], $caminho, $encontrado) !== 1) {
                continue;
            }
            if ($rota['metodo'] !== $metodo && !($metodo === 'HEAD' && $rota['metodo'] === 'GET')) {
                $metodosPermitidos[] = $rota['metodo'];
                continue;
            }
            $parametros = array_filter($encontrado, static fn ($chave): bool => is_string($chave), ARRAY_FILTER_USE_KEY);
            return ['acao' => $rota['acao'], 'parametros' => $parametros, 'opcoes' => $rota['opcoes'], 'padrao' => $rota['padrao']];
        }
        if ($metodosPermitidos !== []) {
            throw new ErroHttp(405, 'Método não permitido. Use ' . implode(' ou ', array_unique($metodosPermitidos)) . '.');
        }
        throw ErroHttp::naoEncontrado('Página não encontrada.');
    }
}
