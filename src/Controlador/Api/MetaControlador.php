<?php

declare(strict_types=1);

namespace Cofrelo\Controlador\Api;

use Cofrelo\Controlador\Controlador;
use Cofrelo\Http\ErroHttp;
use Cofrelo\Http\Requisicao;
use Cofrelo\Http\Resposta;
use Cofrelo\Modelo\Meta;
use Cofrelo\Repositorio\MetaRepositorio;
use Cofrelo\Servico\CalculadoraMeta;
use Cofrelo\Servico\ValidadorCadastros;
use Cofrelo\Suporte\Log;

/** CRUD de metas: /api/metas, /api/metas/{id} e o depósito em /api/metas/{id}/depositos. */
final class MetaControlador extends Controlador
{
    public function listar(Requisicao $requisicao): Resposta
    {
        $hoje = $this->hoje();
        $ritmos = $this->metas()->ritmosMensais($this->usuario(), $hoje);
        $metas = array_map(
            static fn (Meta $meta): array => $meta->paraArray($hoje, $ritmos[$meta->id] ?? 0.0),
            $this->metas()->listar($this->usuario())
        );
        $porStatus = array_count_values(array_column($metas, 'status'));
        $ativas = array_values(array_filter($metas, static fn (array $m): bool => $m['status'] !== CalculadoraMeta::CONCLUIDA));
        usort($ativas, static fn (array $a, array $b): int => $b['percentual'] <=> $a['percentual']);

        return Resposta::json([
            'metas' => $metas,
            'resumo' => [
                'total_reservado' => round(array_sum(array_column($metas, 'valor_atual')), 2),
                'ativas' => count($ativas),
                'em_progresso' => $porStatus[CalculadoraMeta::EM_PROGRESSO] ?? 0,
                'prontas' => $porStatus[CalculadoraMeta::PRONTA] ?? 0,
                'concluidas' => $porStatus[CalculadoraMeta::CONCLUIDA] ?? 0,
                'proxima_conquista' => $ativas[0] ?? null,
            ],
        ]);
    }

    public function mostrar(Requisicao $requisicao): Resposta
    {
        $meta = $this->metas()->buscar($this->usuario(), $requisicao->idDaRota()) ?? throw ErroHttp::naoEncontrado('Meta não encontrada.');
        return Resposta::json(($this->emArray($meta) ?? []) + ['depositos' => $this->metas()->depositos($meta->id)]);
    }

    public function criar(Requisicao $requisicao): Resposta
    {
        $usuario = $this->usuario();
        [$erros, $dados] = (new ValidadorCadastros())->meta($requisicao->corpo, $this->hoje());
        $this->falharSeInvalido($erros);
        $id = $this->metas()->criar($usuario, $dados);
        Log::info('meta.criada', ['usuario_id' => $usuario, 'meta_id' => $id]);
        return Resposta::json($this->emArray($this->metas()->buscar($usuario, $id)), 201);
    }

    public function atualizar(Requisicao $requisicao): Resposta
    {
        $usuario = $this->usuario();
        $id = $requisicao->idDaRota();
        if ($this->metas()->buscar($usuario, $id) === null) {
            throw ErroHttp::naoEncontrado('Meta não encontrada.');
        }
        [$erros, $dados] = (new ValidadorCadastros())->meta($requisicao->corpo, $this->hoje(), false);
        $this->falharSeInvalido($erros);
        $this->metas()->atualizar($usuario, $id, $dados);
        return Resposta::json($this->emArray($this->metas()->buscar($usuario, $id)));
    }

    public function excluir(Requisicao $requisicao): Resposta
    {
        $usuario = $this->usuario();
        if (!$this->metas()->excluir($usuario, $requisicao->idDaRota())) {
            throw ErroHttp::naoEncontrado('Meta não encontrada.');
        }
        return Resposta::semConteudo();
    }

    /** "Guardar dinheiro" na meta. */
    public function depositar(Requisicao $requisicao): Resposta
    {
        $usuario = $this->usuario();
        [$erros, $dados] = (new ValidadorCadastros())->deposito($requisicao->corpo, $this->hoje());
        $this->falharSeInvalido($erros);
        $meta = $this->metas()->depositar($usuario, $requisicao->idDaRota(), $dados['valor'], $dados['data'])
            ?? throw ErroHttp::naoEncontrado('Meta não encontrada.');
        Log::info('meta.deposito', ['usuario_id' => $usuario, 'meta_id' => $meta->id, 'valor' => $dados['valor']]);
        return Resposta::json($this->emArray($meta), 201);
    }

    /**
     * Meta pronta para a resposta, com a previsão calculada no ritmo dos últimos 3 meses.
     *
     * @return array<string, mixed>|null
     */
    private function emArray(?Meta $meta): ?array
    {
        if ($meta === null) {
            return null;
        }
        $hoje = $this->hoje();
        return $meta->paraArray($hoje, $this->metas()->ritmosMensais($this->usuario(), $hoje)[$meta->id] ?? 0.0);
    }

    private function metas(): MetaRepositorio
    {
        return new MetaRepositorio($this->pdo);
    }
}
