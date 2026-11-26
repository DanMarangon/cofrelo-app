<?php

declare(strict_types=1);

namespace Cofrelo\Banco;

use Cofrelo\Config;
use Cofrelo\Suporte\Log;
use PDO;

/**
 * Prepara o banco: cria as tabelas do database/schema.sql e, se a configuração pedir,
 * os dados de exemplo. Assim o sistema funciona sem nenhum passo manual no Docker ou no XAMPP.
 */
final class Migrador
{
    private static bool $verificado = false;

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** Chamado no início de cada requisição. Só trabalha de verdade na primeira vez. */
    public static function prepararSeNecessario(): void
    {
        if (self::$verificado || !Config::booleano('AUTO_MIGRATE', true)) {
            return;
        }
        $chave = 'cofrelo_migrado_' . Conexao::nomeDoBanco();
        if (function_exists('apcu_fetch') && apcu_fetch($chave) === true) {
            self::$verificado = true;
            return;
        }

        try {
            $pdo = Conexao::obter();
        } catch (\PDOException $erro) {
            if ((int) $erro->getCode() !== 1049) { // 1049 = banco desconhecido
                throw $erro;
            }
            self::criarBancoSeNaoExistir();
            Conexao::definir(null);
            $pdo = Conexao::obter();
        }

        $migrador = new self($pdo);
        $comDados = Config::booleano('SEED_DEMO', true);
        if (!$migrador->estruturaCriada() || ($comDados && $migrador->semUsuarios())) {
            $migrador->preparar($comDados);
        }
        self::$verificado = true;
        if (function_exists('apcu_store')) {
            apcu_store($chave, true, 60);
        }
    }

    /** No XAMPP o banco pode ainda não existir: cria se o usuário do MySQL tiver permissão. */
    public static function criarBancoSeNaoExistir(): void
    {
        $nome = Conexao::nomeDoBanco();
        Conexao::criar(false)->exec("CREATE DATABASE IF NOT EXISTS `{$nome}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }

    public function preparar(bool $comDadosDeExemplo): void
    {
        // A trava evita que duas requisições simultâneas criem as tabelas ou os dados ao mesmo tempo.
        $this->pdo->query("SELECT GET_LOCK('cofrelo_migracao', 20)")->fetchColumn();
        try {
            if (!$this->estruturaCriada()) {
                $this->criarEstrutura();
                Log::info('banco.estrutura_criada', ['banco' => Conexao::nomeDoBanco()]);
            }
            if ($comDadosDeExemplo && $this->semUsuarios()) {
                $id = (new DadosDeExemplo($this->pdo))->criar();
                Log::info('banco.dados_de_exemplo_criados', ['usuario_id' => $id]);
            }
        } finally {
            $this->pdo->query("SELECT RELEASE_LOCK('cofrelo_migracao')")->fetchColumn();
        }
    }

    public function estruturaCriada(): bool
    {
        $consulta = $this->pdo->query(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'meta_deposito'"
        );
        return (int) $consulta->fetchColumn() > 0;
    }

    public function semUsuarios(): bool
    {
        if (!$this->estruturaCriada()) {
            return true;
        }
        return $this->pdo->query('SELECT 1 FROM usuario LIMIT 1')->fetchColumn() === false;
    }

    public function criarEstrutura(): void
    {
        $sql = (string) file_get_contents(COFRELO_RAIZ . '/database/schema.sql');
        foreach (self::comandos($sql) as $comando) {
            $this->pdo->exec($comando);
        }
    }

    /** Apaga todas as tabelas. Usado só pelos testes e pelo script bin/banco.php --recriar. */
    public function apagarEstrutura(): void
    {
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['meta_deposito', 'meta', 'transacao', 'assinatura', 'categoria', 'conta', 'usuario'] as $tabela) {
            $this->pdo->exec("DROP TABLE IF EXISTS {$tabela}");
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        self::$verificado = false;
    }

    /**
     * Separa o arquivo SQL em comandos, ignorando as linhas de comentário.
     *
     * @return string[]
     */
    public static function comandos(string $sql): array
    {
        $semComentarios = (string) preg_replace('/^\s*--.*$/m', '', $sql);
        $comandos = array_map('trim', explode(';', $semComentarios));
        return array_values(array_filter($comandos, static fn (string $comando): bool => $comando !== ''));
    }
}
