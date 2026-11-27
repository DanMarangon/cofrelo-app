<?php

declare(strict_types=1);

namespace Cofrelo\Repositorio;

use Cofrelo\Modelo\Categoria;

/**
 * Consultas SQL das categorias. As categorias são as mesmas para todos os usuários (criadas pelo schema.sql).
 */
final class CategoriaRepositorio extends Repositorio
{
    /** @return Categoria[] */
    public function listar(?string $tipo = null): array
    {
        if ($tipo !== null) {
            $linhas = $this->linhas('SELECT * FROM categoria WHERE tipo = :tipo ORDER BY id', ['tipo' => $tipo]);
        } else {
            $linhas = $this->linhas('SELECT * FROM categoria ORDER BY tipo DESC, id');
        }
        return array_map([Categoria::class, 'daLinha'], $linhas);
    }

    /** @return array<int, string> id => tipo, usado pela validação das transações */
    public function tipos(): array
    {
        $mapa = [];
        foreach ($this->linhas('SELECT id, tipo FROM categoria') as $linha) {
            $mapa[(int) $linha['id']] = (string) $linha['tipo'];
        }
        return $mapa;
    }

    public function idPorNome(string $nome, string $tipo): ?int
    {
        $id = $this->valor('SELECT id FROM categoria WHERE nome = :nome AND tipo = :tipo', ['nome' => $nome, 'tipo' => $tipo]);
        return $id === false ? null : (int) $id;
    }
}
