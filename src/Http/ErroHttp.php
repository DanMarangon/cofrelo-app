<?php

declare(strict_types=1);

namespace Cofrelo\Http;

/** Erro com status HTTP e mensagem pronta para mostrar ao usuário. */
final class ErroHttp extends \RuntimeException
{
    /**
     * @param array<string, string> $campos erros por campo do formulário
     * @param array<string, string> $cabecalhos cabeçalhos extras da resposta (por exemplo, Retry-After)
     */
    public function __construct(
        public readonly int $status,
        string $mensagem,
        public readonly array $campos = [],
        public readonly array $cabecalhos = [],
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

    /** 429: login bloqueado por excesso de tentativas erradas. */
    public static function muitasTentativas(int $segundos): self
    {
        $minutos = max(1, (int) ceil($segundos / 60));
        $texto = $minutos === 1 ? '1 minuto' : "{$minutos} minutos";
        return new self(429, "Muitas tentativas de login erradas. Espere {$texto} e tente de novo.", [], [
            'Retry-After' => (string) $segundos,
        ]);
    }
}
