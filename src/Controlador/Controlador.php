<?php

declare(strict_types=1);

namespace Cofrelo\Controlador;

use Cofrelo\Http\ErroHttp;
use Cofrelo\Http\Requisicao;
use Cofrelo\Suporte\Relogio;
use Cofrelo\Suporte\Sessao;
use DateTimeImmutable;
use PDO;

/** Base dos controladores: acesso ao banco, ao usuário logado e à data de hoje. */
abstract class Controlador
{
    public function __construct(protected readonly PDO $pdo)
    {
    }

    protected function usuario(): int
    {
        return Sessao::usuarioObrigatorio();
    }

    protected function hoje(): DateTimeImmutable
    {
        return Relogio::hoje();
    }

    /** @param array<string, string> $erros */
    protected function falharSeInvalido(array $erros): void
    {
        if ($erros !== []) {
            throw ErroHttp::invalido($erros);
        }
    }

    /** Data no formato AAAA-MM-DD vinda da URL, ou o valor padrão. */
    protected function dataDaConsulta(Requisicao $requisicao, string $chave, ?DateTimeImmutable $padrao = null): ?DateTimeImmutable
    {
        $texto = $requisicao->consulta($chave);
        if ($texto === null) {
            return $padrao;
        }
        $data = DateTimeImmutable::createFromFormat('!Y-m-d', $texto);
        if ($data === false || $data->format('Y-m-d') !== $texto) {
            throw ErroHttp::invalido([$chave => 'Use uma data no formato AAAA-MM-DD.'], 'Período inválido.');
        }
        return $data;
    }
}
