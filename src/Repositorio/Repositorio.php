<?php

declare(strict_types=1);

namespace Cofrelo\Repositorio;

use PDO;

/**
 * Base dos repositórios. Toda consulta usa parâmetros preparados do PDO,
 * então o que o usuário digita nunca vira parte do SQL (proteção contra SQL injection).
 */
abstract class Repositorio
{
    public function __construct(protected readonly PDO $pdo)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     * @param array<string, mixed> $parametros
     */
    protected function linhas(string $sql, array $parametros = []): array
    {
        $consulta = $this->pdo->prepare($sql);
        $consulta->execute($parametros);
        return $consulta->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     * @param array<string, mixed> $parametros
     */
    protected function linha(string $sql, array $parametros = []): ?array
    {
        $consulta = $this->pdo->prepare($sql);
        $consulta->execute($parametros);
        $linha = $consulta->fetch();
        return $linha === false ? null : $linha;
    }

    /** @param array<string, mixed> $parametros */
    protected function valor(string $sql, array $parametros = []): mixed
    {
        $consulta = $this->pdo->prepare($sql);
        $consulta->execute($parametros);
        return $consulta->fetchColumn();
    }

    /** @param array<string, mixed> $parametros */
    protected function executar(string $sql, array $parametros = []): int
    {
        $consulta = $this->pdo->prepare($sql);
        $consulta->execute($parametros);
        return $consulta->rowCount();
    }

    protected function ultimoId(): int
    {
        return (int) $this->pdo->lastInsertId();
    }
}
