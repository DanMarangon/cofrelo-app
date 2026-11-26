<?php

declare(strict_types=1);

namespace Cofrelo\Controlador\Api;

use Cofrelo\Controlador\Controlador;
use Cofrelo\Http\ErroHttp;
use Cofrelo\Http\Requisicao;
use Cofrelo\Http\Resposta;
use Cofrelo\Modelo\Transacao;
use Cofrelo\Repositorio\AssinaturaRepositorio;
use Cofrelo\Repositorio\CategoriaRepositorio;
use Cofrelo\Repositorio\ContaRepositorio;
use Cofrelo\Repositorio\TransacaoRepositorio;
use Cofrelo\Servico\Assinaturas;
use Cofrelo\Servico\Relatorio;
use Cofrelo\Servico\ValidadorTransacao;
use Cofrelo\Suporte\Formatador;
use Cofrelo\Suporte\Log;
use Cofrelo\Suporte\Url;

/** CRUD de transações: /api/transacoes e /api/transacoes/{id}, mais a exportação em CSV. */
final class TransacaoControlador extends Controlador
{
    public function listar(Requisicao $requisicao): Resposta
    {
        $usuario = $this->usuario();
        $this->assinaturas()->lancarCobrancasDoMes($usuario, $this->hoje());

        $filtro = $this->filtro($requisicao);
        $resultado = $this->transacoes()->listar($usuario, $filtro);
        $porPagina = (int) $filtro['por_pagina'];

        $resposta = [
            'itens' => array_map(static fn (Transacao $t): array => $t->paraArray(), $resultado['itens']),
            'total' => $resultado['total'],
            'pagina' => (int) $filtro['pagina'],
            'por_pagina' => $porPagina,
            'paginas' => max(1, (int) ceil($resultado['total'] / $porPagina)),
        ];
        if ($filtro['inicio'] !== null && $filtro['fim'] !== null) {
            $totais = $this->transacoes()->totais($usuario, $filtro['inicio'], $filtro['fim'], $filtro['conta_id']);
            $resposta['totais'] = $totais + ['balanco' => round($totais['receitas'] - $totais['despesas'], 2)];
        }
        return Resposta::json($resposta);
    }

    public function mostrar(Requisicao $requisicao): Resposta
    {
        $transacao = $this->transacoes()->buscar($this->usuario(), $requisicao->idDaRota())
            ?? throw ErroHttp::naoEncontrado('Transação não encontrada.');
        return Resposta::json($transacao->paraArray());
    }

    public function criar(Requisicao $requisicao): Resposta
    {
        $usuario = $this->usuario();
        $dados = $this->validar($usuario, $requisicao->corpo);

        $assinaturaId = null;
        if ($dados['assinatura']) {
            $assinaturaId = $this->repositorioAssinaturas()->criar($usuario, $dados + ['inicio' => $dados['data']]);
        }
        $id = $this->transacoes()->criar($usuario, $dados, $assinaturaId);
        Log::info('transacao.criada', [
            'usuario_id' => $usuario,
            'transacao_id' => $id,
            'tipo' => $dados['tipo'],
            'assinatura' => $assinaturaId !== null,
        ]);

        $transacao = $this->transacoes()->buscar($usuario, $id);
        return Resposta::json($transacao?->paraArray(), 201)->comCabecalho('Location', Url::para("/api/transacoes/{$id}"));
    }

    public function atualizar(Requisicao $requisicao): Resposta
    {
        $usuario = $this->usuario();
        $id = $requisicao->idDaRota();
        $existente = $this->transacoes()->buscar($usuario, $id) ?? throw ErroHttp::naoEncontrado('Transação não encontrada.');
        $dados = $this->validar($usuario, $requisicao->corpo);

        $this->transacoes()->atualizar($usuario, $id, $dados);
        // Marcar "Repetir todo mês" numa despesa que ainda não era assinatura cria a assinatura.
        if ($dados['assinatura'] && $existente->assinaturaId === null) {
            $assinaturaId = $this->repositorioAssinaturas()->criar($usuario, $dados + ['inicio' => $dados['data']]);
            $this->transacoes()->vincularAssinatura($id, $assinaturaId);
        }
        Log::info('transacao.atualizada', ['usuario_id' => $usuario, 'transacao_id' => $id]);
        return Resposta::json($this->transacoes()->buscar($usuario, $id)?->paraArray());
    }

    public function excluir(Requisicao $requisicao): Resposta
    {
        $usuario = $this->usuario();
        $id = $requisicao->idDaRota();
        if (!$this->transacoes()->excluir($usuario, $id)) {
            throw ErroHttp::naoEncontrado('Transação não encontrada.');
        }
        Log::info('transacao.excluida', ['usuario_id' => $usuario, 'transacao_id' => $id]);
        return Resposta::semConteudo();
    }

    /** CSV com as transações do filtro atual (sem paginação). */
    public function exportar(Requisicao $requisicao): Resposta
    {
        $filtro = ['pagina' => 1, 'por_pagina' => 100] + $this->filtro($requisicao);
        $linhas = [['Data', 'Descrição', 'Tipo', 'Categoria', 'Conta', 'Status', 'Valor (R$)']];
        $usuario = $this->usuario();
        do {
            $pagina = $this->transacoes()->listar($usuario, $filtro);
            foreach ($pagina['itens'] as $transacao) {
                $sinal = $transacao->tipo === 'despesa' ? -1 : 1;
                $linhas[] = [
                    Formatador::data($transacao->data),
                    $transacao->descricao,
                    ['receita' => 'Receita', 'despesa' => 'Despesa', 'transferencia' => 'Transferência'][$transacao->tipo],
                    $transacao->categoriaNome ?? '',
                    $transacao->contaDestinoNome !== null ? "{$transacao->contaNome} → {$transacao->contaDestinoNome}" : $transacao->contaNome,
                    $transacao->statusRotulo(),
                    Formatador::numero($sinal * $transacao->valor),
                ];
            }
            $filtro['pagina']++;
        } while (count($pagina['itens']) === 100);

        return Resposta::csv('transacoes-cofrelo-' . $this->hoje()->format('Y-m-d') . '.csv', Relatorio::montarCsv($linhas));
    }

    /** @return array<string, mixed> */
    private function validar(int $usuario, array $corpo): array
    {
        [$erros, $dados] = (new ValidadorTransacao())->validar(
            $corpo,
            (new ContaRepositorio($this->pdo))->idsAtivos($usuario),
            (new CategoriaRepositorio($this->pdo))->tipos(),
            $this->hoje()
        );
        $this->falharSeInvalido($erros);
        return $dados;
    }

    /** @return array<string, mixed> */
    private function filtro(Requisicao $requisicao): array
    {
        $inicio = $this->dataDaConsulta($requisicao, 'inicio');
        $fim = $this->dataDaConsulta($requisicao, 'fim');
        if ($inicio !== null && $fim !== null && $fim < $inicio) {
            throw ErroHttp::invalido(['fim' => 'A data final precisa ser depois da inicial.'], 'Período inválido.');
        }
        $tipo = $requisicao->consulta('tipo', '');
        return [
            'inicio' => $inicio?->format('Y-m-d'),
            'fim' => $fim?->format('Y-m-d'),
            'tipo' => in_array($tipo, ['receita', 'despesa', 'transferencia', 'assinatura'], true) ? $tipo : '',
            'categoria_id' => ctype_digit((string) $requisicao->consulta('categoria_id', '')) ? (int) $requisicao->consulta('categoria_id') : null,
            'conta_id' => ctype_digit((string) $requisicao->consulta('conta_id', '')) ? (int) $requisicao->consulta('conta_id') : null,
            'status' => $requisicao->consulta('status', ''),
            'busca' => mb_substr((string) $requisicao->consulta('busca', ''), 0, 80),
            'ordenar' => $requisicao->consulta('ordenar', 'data'),
            'direcao' => $requisicao->consulta('direcao', 'desc'),
            'pagina' => max(1, (int) $requisicao->consulta('pagina', '1')),
            'por_pagina' => max(1, min(100, (int) $requisicao->consulta('por_pagina', '8'))),
        ];
    }

    private function transacoes(): TransacaoRepositorio
    {
        return new TransacaoRepositorio($this->pdo);
    }

    private function repositorioAssinaturas(): AssinaturaRepositorio
    {
        return new AssinaturaRepositorio($this->pdo);
    }

    private function assinaturas(): Assinaturas
    {
        return new Assinaturas($this->repositorioAssinaturas(), $this->transacoes());
    }
}
