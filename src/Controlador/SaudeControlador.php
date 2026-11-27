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

    public function metricas(Requisicao $requisicao): Resposta
    {
        $linhas = [
            '# HELP cofrelo_info Versão em execução.',
            '# TYPE cofrelo_info gauge',
            sprintf('cofrelo_info{versao="%s",commit="%s"} 1', self::rotulo(Config::versao()), self::rotulo(substr(Config::commit(), 0, 12))),
        ];

        $bancoOk = 1;
        $totais = ['usuarios' => 0, 'transacoes' => 0, 'metas' => 0];
        try {
            $pdo = Conexao::obter();
            foreach (['usuarios' => 'usuario', 'transacoes' => 'transacao', 'metas' => 'meta'] as $nome => $tabela) {
                $totais[$nome] = (int) $pdo->query("SELECT COUNT(*) FROM {$tabela}")->fetchColumn();
            }
        } catch (\Throwable $erro) {
            $bancoOk = 0;
        }
        $linhas[] = '# HELP cofrelo_banco_disponivel 1 se o banco respondeu na última verificação.';
        $linhas[] = '# TYPE cofrelo_banco_disponivel gauge';
        $linhas[] = "cofrelo_banco_disponivel {$bancoOk}";
        foreach ($totais as $nome => $valor) {
            $linhas[] = "# HELP cofrelo_{$nome}_total Quantidade de registros de {$nome} no banco.";
            $linhas[] = "# TYPE cofrelo_{$nome}_total gauge";
            $linhas[] = "cofrelo_{$nome}_total {$valor}";
        }

        if (Metricas::disponivel()) {
            $dados = Metricas::ler();
            $linhas[] = '# HELP cofrelo_requisicoes_total Requisições atendidas por método, rota e classe de status.';
            $linhas[] = '# TYPE cofrelo_requisicoes_total counter';
            foreach ($dados['requisicoes'] as $chave => $valor) {
                [$metodo, $rota, $classe] = array_pad(explode('|', $chave), 3, '');
                $linhas[] = sprintf('cofrelo_requisicoes_total{metodo="%s",rota="%s",status="%s"} %d', self::rotulo($metodo), self::rotulo($rota), self::rotulo($classe), $valor);
            }
            $linhas[] = '# HELP cofrelo_requisicao_duracao_ms Tempo das requisições em milissegundos.';
            $linhas[] = '# TYPE cofrelo_requisicao_duracao_ms summary';
            $linhas[] = 'cofrelo_requisicao_duracao_ms_sum ' . round($dados['duracao_soma'], 1);
            $linhas[] = 'cofrelo_requisicao_duracao_ms_count ' . (int) $dados['duracao_contagem'];
            $linhas[] = '# HELP cofrelo_erros_total Respostas com erro interno (status 5xx).';
            $linhas[] = '# TYPE cofrelo_erros_total counter';
            $linhas[] = 'cofrelo_erros_total ' . (int) $dados['erros'];
        }

        return Resposta::texto(implode("\n", $linhas) . "\n", 200, 'text/plain; version=0.0.4; charset=utf-8');
    }

    private static function rotulo(string $valor): string
    {
        return str_replace(['\\', '"', "\n"], ['\\\\', '\\"', ''], $valor);
    }
}
