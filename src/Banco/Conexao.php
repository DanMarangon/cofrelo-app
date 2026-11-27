<?php

declare(strict_types=1);

namespace Cofrelo\Banco;

use Cofrelo\Config;
use PDO;

/**
 * Conexão única com o MySQL via PDO. Os dados de acesso vêm do ambiente (DB_HOST, DB_PORT,
 * DB_NAME, DB_USER, DB_PASS), nunca do código.
 */
final class Conexao
{
    private static ?PDO $pdo = null;

    public static function obter(): PDO
    {
        return self::$pdo ??= self::criar();
    }

    /** Permite que os testes troquem ou descartem a conexão. */
    public static function definir(?PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    public static function criar(bool $comBanco = true): PDO
    {
        $host = Config::texto('DB_HOST', '127.0.0.1');
        $porta = Config::inteiro('DB_PORT', 3306);
        $dsn = "mysql:host={$host};port={$porta};charset=utf8mb4";
        if ($comBanco) {
            $dsn .= ';dbname=' . self::nomeDoBanco();
        }

        return new PDO($dsn, Config::texto('DB_USER', 'root'), Config::texto('DB_PASS', ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '-03:00'",
        ]);
    }

    public static function nomeDoBanco(): string
    {
        $nome = Config::texto('DB_NAME', 'cofrelo');
        if (preg_match('/^[A-Za-z0-9_]+$/', $nome) !== 1) {
            throw new \RuntimeException('DB_NAME só pode ter letras, números e sublinhado.');
        }
        return $nome;
    }
}
