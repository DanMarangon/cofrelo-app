<?php

declare(strict_types=1);

namespace Cofrelo\Controlador\Api;

use Cofrelo\Controlador\Controlador;
use Cofrelo\Http\ErroHttp;
use Cofrelo\Http\Requisicao;
use Cofrelo\Http\Resposta;
use Cofrelo\Modelo\Categoria;
use Cofrelo\Repositorio\AssinaturaRepositorio;
use Cofrelo\Repositorio\CategoriaRepositorio;
use Cofrelo\Repositorio\ContaRepositorio;
use Cofrelo\Repositorio\MetaRepositorio;
use Cofrelo\Repositorio\TransacaoRepositorio;
use Cofrelo\Repositorio\UsuarioRepositorio;
use Cofrelo\Servico\Assinaturas;
use Cofrelo\Servico\Notificacoes;
use Cofrelo\Servico\Relatorio;
use Cofrelo\Servico\ResumoFinanceiro;
use DateTimeImmutable;

/** Visão geral (/api/resumo), relatórios (/api/relatorios), notificações e categorias. */
final class PainelControlador extends Controlador
{
    public function resumo(Requisicao $requisicao): Resposta
    {
        $usuario = $this->usuario();
        $lancadas = (new Assinaturas(new AssinaturaRepositorio($this->pdo), $this->transacoes()))
            ->lancarCobrancasDoMes($usuario, $this->hoje());

        $textoMes = $requisicao->consulta('mes', $this->hoje()->format('Y-m'));
        $mes = DateTimeImmutable::createFromFormat('!Y-m', (string) $textoMes);
        if ($mes === false || $mes->format('Y-m') !== $textoMes) {
            throw ErroHttp::invalido(['mes' => 'Use o formato AAAA-MM.'], 'Mês inválido.');
        }

        $resumo = (new ResumoFinanceiro(
            $this->transacoes(),
            new ContaRepositorio($this->pdo),
            new MetaRepositorio($this->pdo),
            new UsuarioRepositorio($this->pdo),
        ))->montar($usuario, $mes, $this->hoje());

        return Resposta::json($resumo + ['assinaturas_lancadas' => $lancadas]);
    }

    public function relatorio(Requisicao $requisicao): Resposta
    {
        return Resposta::json($this->montarRelatorio($requisicao));
    }

    public function exportarRelatorio(Requisicao $requisicao): Resposta
    {
        $relatorio = $this->montarRelatorio($requisicao);
        $nome = 'relatorio-cofrelo-' . $relatorio['periodo']['inicio'] . '-a-' . $relatorio['periodo']['fim'] . '.csv';
        return Resposta::csv($nome, Relatorio::paraCsv($relatorio));
    }

    public function notificacoes(Requisicao $requisicao): Resposta
    {
        $avisos = (new Notificacoes(
            $this->transacoes(),
            new AssinaturaRepositorio($this->pdo),
            new MetaRepositorio($this->pdo),
            new UsuarioRepositorio($this->pdo),
        ))->listar($this->usuario(), $this->hoje());
        return Resposta::json(['notificacoes' => $avisos, 'quantidade' => count($avisos)]);
    }

    public function categorias(Requisicao $requisicao): Resposta
    {
        $tipo = $requisicao->consulta('tipo');
        $tipo = in_array($tipo, ['receita', 'despesa'], true) ? $tipo : null;
        $categorias = (new CategoriaRepositorio($this->pdo))->listar($tipo);
        return Resposta::json(['categorias' => array_map(static fn (Categoria $c): array => $c->paraArray(), $categorias)]);
    }

    /** @return array<string, mixed> */
    private function montarRelatorio(Requisicao $requisicao): array
    {
        $hoje = $this->hoje();
        $fim = $this->dataDaConsulta($requisicao, 'fim', $hoje->modify('last day of this month'));
        $inicio = $this->dataDaConsulta($requisicao, 'inicio', $fim->modify('first day of this month')->modify('-5 months'));
        if ($fim < $inicio) {
            throw ErroHttp::invalido(['fim' => 'A data final precisa ser depois da inicial.'], 'Período inválido.');
        }
        return (new Relatorio($this->transacoes()))->montar($this->usuario(), $inicio, $fim);
    }

    private function transacoes(): TransacaoRepositorio
    {
        return new TransacaoRepositorio($this->pdo);
    }
}
