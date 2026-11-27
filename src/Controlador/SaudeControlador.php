<?php

declare(strict_types=1);

namespace Cofrelo\Controlador;

use Cofrelo\Banco\Conexao;
use Cofrelo\Config;
use Cofrelo\Http\Requisicao;
use Cofrelo\Http\Resposta;
use Cofrelo\Suporte\Log;
use Cofrelo\Suporte\Metricas;

/**
 * Observabilidade: /health diz se a aplicação está saudável (usado pelo Docker, pelo pipeline e pelo
 * script de implantação) e /metrics expõe números no formato do Prometheus.
 */
final class SaudeControlador
{
    public function saude(Requisicao $requisicao): Resposta
    {
        $inicio = hrtime(true);
        $banco = 'ok';
        try {
            Conexao::obter()->query('SELECT 1')->fetchColumn();
        } catch (\Throwable $erro) {
            $banco = 'indisponivel';
            Conexao::definir(null);
        }
        $simularFalha = Config::booleano('SIMULAR_FALHA', false);
        $saudavel = $banco === 'ok' && !$simularFalha;

        $corpo = [
            'status' => $saudavel ? 'ok' : 'falha',
            'versao' => Config::versao(),
            'commit' => substr(Config::commit(), 0, 12),
            'ambiente' => Config::ambiente(),
            'banco' => $banco,
            'tempo_resposta_ms' => round((hrtime(true) - $inicio) / 1e6, 1),
            'verificado_em' => date('c'),
        ];
        if ($simularFalha) {
            $corpo['motivo'] = 'Falha simulada pela variável SIMULAR_FALHA (teste de rollback).';
        }
        if (!$saudavel) {
            Log::erro('saude.falha', $corpo);
        }
        return Resposta::json($corpo, $saudavel ? 200 : 503);
    }

    /**
     * /metrics: números da aplicação no formato texto que o Prometheus lê a cada 15 segundos.
     * Cada métrica tem uma linha "# HELP" (o que é), uma "# TYPE" (gauge = valor atual, counter = só cresce)
     * e as linhas com os valores.
     */
    public function metricas(Requisicao $requisicao): Resposta
    {
        $linhas = array_merge(self::metricasDaVersao(), self::metricasDoBanco(), self::metricasDasRequisicoes());
        return Resposta::texto(implode("\n", $linhas) . "\n", 200, 'text/plain; version=0.0.4; charset=utf-8');
    }

    /** @return list<string> */
    private static function metricasDaVersao(): array
    {
        $rotulos = sprintf('{versao="%s",commit="%s"}', self::rotulo(Config::versao()), self::rotulo(substr(Config::commit(), 0, 12)));
        return self::metrica('cofrelo_info', 'gauge', 'Versão em execução.', ["cofrelo_info{$rotulos} 1"]);
    }

    /**
     * Se o banco responde e quantos registros ele tem. O alerta BancoIndisponivel olha a primeira.
     *
     * @return list<string>
     */
    private static function metricasDoBanco(): array
    {
        $bancoOk = 1;
        $totais = ['usuarios' => 0, 'transacoes' => 0, 'metas' => 0];
        try {
            $pdo = Conexao::obter();
            foreach (['usuarios' => 'usuario', 'transacoes' => 'transacao', 'metas' => 'meta'] as $nome => $tabela) {
                $totais[$nome] = (int) $pdo->query("SELECT COUNT(*) FROM {$tabela}")->fetchColumn();
            }
        } catch (\Throwable) {
            $bancoOk = 0;
        }
        $linhas = self::metrica('cofrelo_banco_disponivel', 'gauge', '1 se o banco respondeu na última verificação.',
            ["cofrelo_banco_disponivel {$bancoOk}"]);
        foreach ($totais as $nome => $valor) {
            $linhas = array_merge($linhas, self::metrica("cofrelo_{$nome}_total", 'gauge',
                "Quantidade de registros de {$nome} no banco.", ["cofrelo_{$nome}_total {$valor}"]));
        }
        return $linhas;
    }

    /**
     * Contadores guardados no APCu a cada requisição (src/Suporte/Metricas.php).
     *
     * @return list<string>
     */
    private static function metricasDasRequisicoes(): array
    {
        if (!Metricas::disponivel()) {
            return [];
        }
        $dados = Metricas::ler();
        $porRota = [];
        foreach ($dados['requisicoes'] as $chave => $valor) {
            [$metodo, $rota, $classe] = array_pad(explode('|', $chave), 3, '');
            $porRota[] = sprintf('cofrelo_requisicoes_total{metodo="%s",rota="%s",status="%s"} %d',
                self::rotulo($metodo), self::rotulo($rota), self::rotulo($classe), $valor);
        }
        return array_merge(
            self::metrica('cofrelo_requisicoes_total', 'counter', 'Requisições atendidas por método, rota e classe de status.', $porRota),
            self::metrica('cofrelo_requisicao_duracao_ms', 'summary', 'Tempo das requisições em milissegundos.', [
                'cofrelo_requisicao_duracao_ms_sum ' . round($dados['duracao_soma'], 1),
                'cofrelo_requisicao_duracao_ms_count ' . (int) $dados['duracao_contagem'],
            ]),
            self::metrica('cofrelo_erros_total', 'counter', 'Respostas com erro interno (status 5xx).', ['cofrelo_erros_total ' . (int) $dados['erros']]),
        );
    }

    /**
     * @param list<string> $valores
     * @return list<string>
     */
    private static function metrica(string $nome, string $tipo, string $ajuda, array $valores): array
    {
        return ["# HELP {$nome} {$ajuda}", "# TYPE {$nome} {$tipo}", ...$valores];
    }

    private static function rotulo(string $valor): string
    {
        return str_replace(['\\', '"', "\n"], ['\\\\', '\\"', ''], $valor);
    }
}
