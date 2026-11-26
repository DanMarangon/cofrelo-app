<?php

declare(strict_types=1);

namespace Cofrelo\Controlador\Api;

use Cofrelo\Controlador\Controlador;
use Cofrelo\Http\ErroHttp;
use Cofrelo\Http\Requisicao;
use Cofrelo\Http\Resposta;
use Cofrelo\Modelo\Assinatura;
use Cofrelo\Repositorio\AssinaturaRepositorio;
use Cofrelo\Servico\ValidadorCadastros;

/**
 * Assinaturas (cobranças mensais). Elas nascem no modal de nova transação ("Repetir todo mês")
 * e são listadas e editadas na aba Assinaturas da página de transações.
 */
final class AssinaturaControlador extends Controlador
{
    public function listar(Requisicao $requisicao): Resposta
    {
        $hoje = $this->hoje();
        $assinaturas = $this->assinaturas()->listar($this->usuario());
        $ativas = array_filter($assinaturas, static fn (Assinatura $a): bool => $a->ativa);
        return Resposta::json([
            'assinaturas' => array_map(static fn (Assinatura $a): array => $a->paraArray($hoje), $assinaturas),
            'total_mensal' => round(array_sum(array_map(static fn (Assinatura $a): float => $a->valor, $ativas)), 2),
        ]);
    }

    public function atualizar(Requisicao $requisicao): Resposta
    {
        $usuario = $this->usuario();
        $id = $requisicao->idDaRota();
        if ($this->assinaturas()->buscar($usuario, $id) === null) {
            throw ErroHttp::naoEncontrado('Assinatura não encontrada.');
        }
        [$erros, $dados] = (new ValidadorCadastros())->assinatura($requisicao->corpo);
        $this->falharSeInvalido($erros);
        $this->assinaturas()->atualizar($usuario, $id, $dados);
        return Resposta::json($this->assinaturas()->buscar($usuario, $id)?->paraArray($this->hoje()));
    }

    public function excluir(Requisicao $requisicao): Resposta
    {
        if (!$this->assinaturas()->excluir($this->usuario(), $requisicao->idDaRota())) {
            throw ErroHttp::naoEncontrado('Assinatura não encontrada.');
        }
        return Resposta::semConteudo();
    }

    private function assinaturas(): AssinaturaRepositorio
    {
        return new AssinaturaRepositorio($this->pdo);
    }
}
