<?php

declare(strict_types=1);

namespace Cofrelo\Testes\Unitario;

use Cofrelo\Suporte\LimiteDeTentativas;
use Cofrelo\Suporte\Relogio;
use PHPUnit\Framework\TestCase;

/** Proteção do login contra força bruta: 5 erros do mesmo e-mail e IP bloqueiam por 15 minutos. */
final class LimiteDeTentativasTest extends TestCase
{
    private const EMAIL = 'mariana@cofrelo.app';
    private const IP = '203.0.113.7';

    protected function setUp(): void
    {
        LimiteDeTentativas::zerar();
        Relogio::fixar('2026-11-26 10:00:00');
    }

    protected function tearDown(): void
    {
        Relogio::fixar(null);
        LimiteDeTentativas::zerar();
    }

    private function errar(int $vezes, string $email = self::EMAIL, string $ip = self::IP): void
    {
        for ($i = 0; $i < $vezes; $i++) {
            LimiteDeTentativas::registrarFalha($email, $ip);
        }
    }

    public function testQuatroErrosAindaDeixamTentarEOQuintoBloqueia(): void
    {
        $this->errar(4);
        self::assertSame(0, LimiteDeTentativas::segundosBloqueado(self::EMAIL, self::IP));

        $this->errar(1);
        self::assertSame(LimiteDeTentativas::JANELA_SEGUNDOS, LimiteDeTentativas::segundosBloqueado(self::EMAIL, self::IP));
        self::assertSame(LimiteDeTentativas::JANELA_SEGUNDOS, LimiteDeTentativas::segundosBloqueado('  MARIANA@cofrelo.app ', self::IP));
    }

    public function testBloqueioAcabaQuandoAJanelaDeQuinzeMinutosPassa(): void
    {
        $this->errar(5);
        Relogio::fixar('2026-11-26 10:10:00');
        self::assertSame(300, LimiteDeTentativas::segundosBloqueado(self::EMAIL, self::IP));

        Relogio::fixar('2026-11-26 10:15:00');
        self::assertSame(0, LimiteDeTentativas::segundosBloqueado(self::EMAIL, self::IP));
    }

    public function testLoginCertoZeraOsErrosDaConta(): void
    {
        $this->errar(4);
        LimiteDeTentativas::limpar(self::EMAIL, self::IP);
        $this->errar(4);
        self::assertSame(0, LimiteDeTentativas::segundosBloqueado(self::EMAIL, self::IP));
    }

    public function testBloqueioDaContaNaoAtingeOutroEmailNemOutroIp(): void
    {
        $this->errar(5);
        self::assertSame(0, LimiteDeTentativas::segundosBloqueado('outra@cofrelo.app', self::IP));
        self::assertSame(0, LimiteDeTentativas::segundosBloqueado(self::EMAIL, '198.51.100.2'));
    }

    public function testMuitosErrosDoMesmoIpBloqueiamQualquerEmail(): void
    {
        for ($i = 1; $i <= LimiteDeTentativas::MAXIMO_POR_IP; $i++) {
            LimiteDeTentativas::registrarFalha("pessoa{$i}@exemplo.com", self::IP);
        }
        self::assertGreaterThan(0, LimiteDeTentativas::segundosBloqueado('nova@exemplo.com', self::IP));
    }
}
