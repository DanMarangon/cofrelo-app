<?php

declare(strict_types=1);

namespace Cofrelo\Testes\Unitario;

use Cofrelo\Http\ErroHttp;
use Cofrelo\Http\Resposta;
use Cofrelo\Http\Roteador;
use PHPUnit\Framework\TestCase;

final class RoteadorTest extends TestCase
{
    private Roteador $rotas;

    protected function setUp(): void
    {
        $this->rotas = new Roteador();
        $this->rotas->get('/api/transacoes/exportar', static fn (): Resposta => Resposta::texto('csv'));
        $this->rotas->get('/api/transacoes/{id}', static fn (): Resposta => Resposta::texto('uma'));
        $this->rotas->delete('/api/transacoes/{id}', static fn (): Resposta => Resposta::semConteudo(), ['csrf' => true]);
    }

    public function testEncontraARotaEOParametro(): void
    {
        $rota = $this->rotas->encontrar('GET', '/api/transacoes/42');

        self::assertSame(['id' => '42'], $rota['parametros']);
        self::assertSame('/api/transacoes/{id}', $rota['padrao']);
    }

    public function testRotaFixaTemPrioridadeSobreAComParametro(): void
    {
        self::assertSame('/api/transacoes/exportar', $this->rotas->encontrar('GET', '/api/transacoes/exportar')['padrao']);
    }

    public function testCaminhoInexistenteDa404EMetodoErradoDa405(): void
    {
        try {
            $this->rotas->encontrar('GET', '/api/nada');
            self::fail('Deveria lançar 404');
        } catch (ErroHttp $erro) {
            self::assertSame(404, $erro->status);
        }

        $this->expectExceptionObject(new ErroHttp(405, 'Método não permitido. Use GET ou DELETE.'));
        $this->rotas->encontrar('POST', '/api/transacoes/42');
    }
}
