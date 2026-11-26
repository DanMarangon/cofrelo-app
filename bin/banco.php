<?php

declare(strict_types=1);

/*
 * Utilitário de linha de comando do banco de dados.
 *
 *   php bin/banco.php status     mostra quantos registros há em cada tabela
 *   php bin/banco.php preparar   cria as tabelas (e os dados de exemplo, se SEED_DEMO=true)
 *   php bin/banco.php recriar    apaga tudo e cria de novo, com a usuária de demonstração
 *
 * No Docker: docker compose exec app php bin/banco.php recriar
 */

require dirname(__DIR__) . '/src/bootstrap.php';

use Cofrelo\Banco\Conexao;
use Cofrelo\Banco\DadosDeExemplo;
use Cofrelo\Banco\Migrador;
use Cofrelo\Config;

$comando = $argv[1] ?? 'status';

try {
    Migrador::criarBancoSeNaoExistir();
    $pdo = Conexao::obter();
    $migrador = new Migrador($pdo);

    switch ($comando) {
        case 'preparar':
            $migrador->preparar(Config::booleano('SEED_DEMO', true));
            echo "Banco pronto.\n";
            break;

        case 'recriar':
            $migrador->apagarEstrutura();
            $migrador->criarEstrutura();
            (new DadosDeExemplo($pdo))->criar();
            echo 'Banco recriado. Acesso de demonstração: ' . DadosDeExemplo::EMAIL . ' / ' . DadosDeExemplo::SENHA . "\n";
            break;

        case 'status':
            if (!$migrador->estruturaCriada()) {
                echo "As tabelas ainda não existem. Rode: php bin/banco.php preparar\n";
                break;
            }
            foreach (['usuario', 'conta', 'categoria', 'transacao', 'assinatura', 'meta', 'meta_deposito'] as $tabela) {
                printf("%-15s %6d registros\n", $tabela, (int) $pdo->query("SELECT COUNT(*) FROM {$tabela}")->fetchColumn());
            }
            break;

        default:
            fwrite(STDERR, "Uso: php bin/banco.php [status|preparar|recriar]\n");
            exit(1);
    }
} catch (Throwable $erro) {
    fwrite(STDERR, 'Erro: ' . $erro->getMessage() . "\n");
    exit(1);
}
