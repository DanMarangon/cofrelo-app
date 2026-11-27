<?php

declare(strict_types=1);

namespace Cofrelo\Http;

/**
 * Resposta HTTP que o controlador devolve: status (200, 404...), cabeçalhos e corpo (JSON, HTML, CSV ou texto).
 * Ela é imutável: comCabecalho() devolve uma cópia nova. Só enviar() manda para o navegador.
 */
final class Resposta
{
    /** @param array<string, string> $cabecalhos */
    public function __construct(
        public readonly int $status = 200,
        public readonly string $corpo = '',
        public readonly array $cabecalhos = [],
    ) {
    }

    public static function json(mixed $dados, int $status = 200): self
    {
        $corpo = json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
        return new self($status, $corpo, ['Content-Type' => 'application/json; charset=utf-8']);
    }

    public static function html(string $html, int $status = 200): self
    {
        return new self($status, $html, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public static function texto(string $texto, int $status = 200, string $tipo = 'text/plain; charset=utf-8'): self
    {
        return new self($status, $texto, ['Content-Type' => $tipo]);
    }

    public static function semConteudo(): self
    {
        return new self(204);
    }

    public static function redirecionar(string $url, int $status = 303): self
    {
        return new self($status, '', ['Location' => $url]);
    }

    /** CSV com BOM para o Excel reconhecer os acentos. */
    public static function csv(string $nomeDoArquivo, string $conteudo): self
    {
        return new self(200, "\u{FEFF}" . $conteudo, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $nomeDoArquivo . '"',
        ]);
    }

    public function comCabecalho(string $nome, string $valor): self
    {
        return new self($this->status, $this->corpo, [$nome => $valor] + $this->cabecalhos);
    }

    public function cabecalho(string $nome): ?string
    {
        foreach ($this->cabecalhos as $chave => $valor) {
            if (strcasecmp($chave, $nome) === 0) {
                return $valor;
            }
        }
        return null;
    }

    /** @return mixed corpo JSON decodificado (usado nos testes) */
    public function dados(): mixed
    {
        return json_decode($this->corpo, true);
    }

    public function enviar(): void
    {
        http_response_code($this->status);
        foreach ($this->cabecalhos as $nome => $valor) {
            header("{$nome}: {$valor}");
        }
        echo $this->corpo;
    }
}
