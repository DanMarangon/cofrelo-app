<?php

declare(strict_types=1);

namespace Cofrelo\Http;

/** Erro com status HTTP e mensagem pronta para mostrar ao usuário. */
final class ErroHttp extends \RuntimeException
{
    /** @param array<string, string> $campos erros por campo do formulário */
    public function __construct(
        public readonly int $status,
        string $mensagem,
        public readonly array $campos = [],
    ) {
        parent::__construct($mensagem, $status);
    }

    public static function naoEncontrado(string $mensagem = 'Não encontramos o que você procurou.'): self
    {
        return new self(404, $mensagem);
    }

    /** @param array<string, string> $campos */
    public static function invalido(array $campos, string $mensagem = 'Confira os campos destacados.'): self
    {
        return new self(422, $mensagem, $campos);
    }

    public static function naoAutenticado(): self
    {
        return new self(401, 'Entre na sua conta para continuar.');
    }

    public static function proibido(string $mensagem): self
    {
        return new self(403, $mensagem);
    }

    public static function conflito(string $mensagem): self
    {
        return new self(409, $mensagem);
    }
}
