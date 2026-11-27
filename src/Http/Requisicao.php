<?php

declare(strict_types=1);

namespace Cofrelo\Http;

/** Dados da requisição HTTP, já normalizados (caminho sem a pasta base, corpo JSON decodificado). */
final class Requisicao
{
    /** @var array<string, string> parâmetros da rota, como o {id} de /api/transacoes/{id} */
    private array $parametros = [];

    /**
     * @param array<string, mixed> $consulta
     * @param array<string, mixed> $corpo
     * @param array<string, string> $cabecalhos nomes em minúsculas
     */
    public function __construct(
        public readonly string $metodo,
        public readonly string $caminho,
        public readonly array $consulta = [],
        public readonly array $corpo = [],
        public readonly array $cabecalhos = [],
        public readonly string $base = '',
        public readonly string $ip = '',
    ) {
    }

    /** Monta a requisição a partir das variáveis do PHP ($_SERVER, $_GET, $_POST e o corpo bruto). */
    public static function capturar(): self
    {
        $metodo = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $uri = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));

        // No Docker o site fica na raiz. No XAMPP pode estar em /cofrelo-app/public ou em /cofrelo-app.
        $base = rtrim(dirname($script), '/');
        $candidatas = [$base];
        if (str_ends_with($base, '/public')) {
            $candidatas[] = substr($base, 0, -strlen('/public'));
        }
        $baseUsada = '';
        foreach ($candidatas as $candidata) {
            if ($candidata !== '' && ($uri === $candidata || str_starts_with($uri, $candidata . '/'))) {
                $baseUsada = $candidata;
                $uri = substr($uri, strlen($candidata));
                break;
            }
        }
        $caminho = '/' . trim(rawurldecode($uri), '/');

        $cabecalhos = [];
        foreach ($_SERVER as $chave => $valor) {
            if (is_string($valor) && str_starts_with($chave, 'HTTP_')) {
                $cabecalhos[strtolower(str_replace('_', '-', substr($chave, 5)))] = $valor;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $cabecalhos['content-type'] = (string) $_SERVER['CONTENT_TYPE'];
        }

        $corpo = $_POST;
        if (str_contains($cabecalhos['content-type'] ?? '', 'application/json')) {
            $json = json_decode((string) file_get_contents('php://input'), true);
            $corpo = is_array($json) ? $json : [];
        }

        return new self($metodo, $caminho, $_GET, $corpo, $cabecalhos, $baseUsada, (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    }

    public function ehApi(): bool
    {
        return str_starts_with($this->caminho, '/api/');
    }

    public function cabecalho(string $nome): ?string
    {
        return $this->cabecalhos[strtolower($nome)] ?? null;
    }

    public function consulta(string $chave, ?string $padrao = null): ?string
    {
        $valor = $this->consulta[$chave] ?? null;
        return is_scalar($valor) && $valor !== '' ? trim((string) $valor) : $padrao;
    }

    public function parametro(string $nome): string
    {
        return $this->parametros[$nome] ?? '';
    }

    public function idDaRota(): int
    {
        $id = $this->parametro('id');
        if (preg_match('/^\d{1,10}$/', $id) !== 1) {
            throw ErroHttp::naoEncontrado();
        }
        return (int) $id;
    }

    /** @param array<string, string> $parametros */
    public function comParametros(array $parametros): self
    {
        $copia = clone $this;
        $copia->parametros = $parametros;
        return $copia;
    }
}
