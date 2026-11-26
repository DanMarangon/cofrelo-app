<?php

declare(strict_types=1);

namespace Cofrelo\Controlador\Api;

use Cofrelo\Controlador\Controlador;
use Cofrelo\Http\ErroHttp;
use Cofrelo\Http\Requisicao;
use Cofrelo\Http\Resposta;
use Cofrelo\Modelo\Conta;
use Cofrelo\Modelo\Transacao;
use Cofrelo\Repositorio\ContaRepositorio;
use Cofrelo\Repositorio\TransacaoRepositorio;
use Cofrelo\Servico\Indicadores;
use Cofrelo\Servico\ValidadorCadastros;
use Cofrelo\Suporte\Log;

/** CRUD de contas: /api/contas e /api/contas/{id}. */
final class ContaControlador extends Controlador
{
    public function listar(Requisicao $requisicao): Resposta
    {
        $usuario = $this->usuario();
        $contas = $this->contas()->listar($usuario);
        $patrimonio = round(array_sum(array_map(static fn (Conta $c): float => $c->saldoAtual, $contas)), 2);
        $fimDoMesPassado = $this->hoje()->modify('last day of previous month')->format('Y-m-d');

        $transferencias = (new TransacaoRepositorio($this->pdo))->listar($usuario, [
            'tipo' => 'transferencia',
            'fim' => $this->hoje()->format('Y-m-d'),
            'por_pagina' => 5,
        ]);

        return Resposta::json([
            'contas' => array_map(static fn (Conta $c): array => $c->paraArray($patrimonio), $contas),
            'patrimonio' => $patrimonio,
            'patrimonio_variacao' => Indicadores::variacao($patrimonio, $this->contas()->patrimonio($usuario, $fimDoMesPassado)),
            'quantidade' => count($contas),
            'transferencias' => array_map(static fn (Transacao $t): array => $t->paraArray(), $transferencias['itens']),
        ]);
    }

    public function mostrar(Requisicao $requisicao): Resposta
    {
        $conta = $this->contas()->buscar($this->usuario(), $requisicao->idDaRota()) ?? throw ErroHttp::naoEncontrado('Conta não encontrada.');
        return Resposta::json($conta->paraArray());
    }

    public function criar(Requisicao $requisicao): Resposta
    {
        $usuario = $this->usuario();
        [$erros, $dados] = (new ValidadorCadastros())->conta($requisicao->corpo);
        $this->falharSeInvalido($erros);
        $id = $this->contas()->criar($usuario, $dados);
        Log::info('conta.criada', ['usuario_id' => $usuario, 'conta_id' => $id]);
        return Resposta::json($this->contas()->buscar($usuario, $id)?->paraArray(), 201);
    }

    public function atualizar(Requisicao $requisicao): Resposta
    {
        $usuario = $this->usuario();
        $id = $requisicao->idDaRota();
        if ($this->contas()->buscar($usuario, $id) === null) {
            throw ErroHttp::naoEncontrado('Conta não encontrada.');
        }
        [$erros, $dados] = (new ValidadorCadastros())->conta($requisicao->corpo);
        $this->falharSeInvalido($erros);
        $this->contas()->atualizar($usuario, $id, $dados);
        return Resposta::json($this->contas()->buscar($usuario, $id)?->paraArray());
    }

    /** RN04: conta com transações não pode ser excluída, para não apagar o histórico junto. */
    public function excluir(Requisicao $requisicao): Resposta
    {
        $usuario = $this->usuario();
        $conta = $this->contas()->buscar($usuario, $requisicao->idDaRota()) ?? throw ErroHttp::naoEncontrado('Conta não encontrada.');
        if ($conta->quantidadeTransacoes > 0) {
            throw ErroHttp::conflito('Essa conta tem transações. Exclua ou mude as transações de conta antes de apagá-la.');
        }
        $this->contas()->excluir($usuario, $conta->id);
        Log::info('conta.excluida', ['usuario_id' => $usuario, 'conta_id' => $conta->id]);
        return Resposta::semConteudo();
    }

    private function contas(): ContaRepositorio
    {
        return new ContaRepositorio($this->pdo);
    }
}
