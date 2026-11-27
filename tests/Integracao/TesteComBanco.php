<?php

declare(strict_types=1);

namespace Cofrelo\Testes\Integracao;

use Cofrelo\Banco\Conexao;
use Cofrelo\Banco\Migrador;
use Cofrelo\Repositorio\CategoriaRepositorio;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Base dos testes que usam um MySQL de verdade (no GitHub Actions é um container de serviço).
 * Cada classe recria as tabelas e cada teste começa com o banco limpo, só com as categorias padrão.
 */
abstract class TesteComBanco extends TestCase
{
    protected static PDO $pdo;

    public static function setUpBeforeClass(): void
    {
        try {
            Migrador::criarBancoSeNaoExistir();
            Conexao::definir(null);
            self::$pdo = Conexao::obter();
        } catch (\PDOException $erro) {
            if (getenv('EXIGIR_BANCO') === '1') {
                throw $erro;
            }
            self::markTestSkipped('MySQL indisponível para os testes de integração: ' . $erro->getMessage());
        }
        $migrador = new Migrador(self::$pdo);
        $migrador->apagarEstrutura();
        $migrador->criarEstrutura();
    }

    protected function setUp(): void
    {
        self::$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['meta_deposito', 'meta', 'transacao', 'assinatura', 'conta', 'usuario'] as $tabela) {
            self::$pdo->exec("TRUNCATE TABLE {$tabela}");
        }
        self::$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        $_SESSION = [];
    }

    protected function criarUsuario(string $email = 'ana@exemplo.com', float $orcamento = 3000): int
    {
        self::$pdo->prepare('INSERT INTO usuario (nome, email, senha_hash, orcamento_mensal) VALUES (?, ?, ?, ?)')
            ->execute(['Ana Teste', $email, password_hash('senha-segura', PASSWORD_DEFAULT), $orcamento]);
        return (int) self::$pdo->lastInsertId();
    }

    protected function criarConta(int $usuarioId, string $nome = 'Conta principal', float $saldoInicial = 0.0): int
    {
        self::$pdo->prepare('INSERT INTO conta (usuario_id, nome, tipo, saldo_inicial) VALUES (?, ?, ?, ?)')
            ->execute([$usuarioId, $nome, 'corrente', $saldoInicial]);
        return (int) self::$pdo->lastInsertId();
    }

    protected function categoria(string $nome, string $tipo = 'despesa'): int
    {
        return (int) (new CategoriaRepositorio(self::$pdo))->idPorNome($nome, $tipo);
    }
}
