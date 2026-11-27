<?php

declare(strict_types=1);

namespace Cofrelo\Suporte;

/**
 * Limite de tentativas de login erradas, contra ataques de força bruta.
 *
 * Conta as falhas por e-mail + IP (até 5) e por IP (até 20) numa janela de 15 minutos. Passou do limite,
 * o login fica bloqueado até a janela acabar, mesmo com a senha certa. Os contadores ficam no APCu, que o
 * Apache compartilha entre as requisições; sem APCu (testes na linha de comando ou XAMPP sem a extensão),
 * ficam na memória do processo. O e-mail e o IP entram nas chaves só como hash.
 */
final class LimiteDeTentativas
{
    public const JANELA_SEGUNDOS = 900;
    public const MAXIMO_POR_CONTA = 5;
    public const MAXIMO_POR_IP = 20;
    private const PREFIXO = 'cofrelo:login:';

    /** @var array<string, array{falhas: int, inicio: int}> */
    private static array $memoria = [];

    /** Segundos que ainda faltam de bloqueio; 0 quando o login está liberado. */
    public static function segundosBloqueado(string $email, string $ip): int
    {
        $agora = Relogio::agora()->getTimestamp();
        $espera = 0;
        foreach (self::limites($email, $ip) as $chave => $maximo) {
            $registro = self::ler($chave, $agora);
            if ($registro !== null && $registro['falhas'] >= $maximo) {
                $espera = max($espera, $registro['inicio'] + self::JANELA_SEGUNDOS - $agora);
            }
        }
        return $espera;
    }

    public static function registrarFalha(string $email, string $ip): void
    {
        $agora = Relogio::agora()->getTimestamp();
        foreach (array_keys(self::limites($email, $ip)) as $chave) {
            $registro = self::ler($chave, $agora) ?? ['falhas' => 0, 'inicio' => $agora];
            $registro['falhas']++;
            self::gravar($chave, $registro);
        }
    }

    /** Login certo zera as falhas daquela conta; as do IP continuam contando até a janela acabar. */
    public static function limpar(string $email, string $ip): void
    {
        $chave = array_key_first(self::limites($email, $ip));
        unset(self::$memoria[$chave]);
        if (self::usaApcu()) {
            apcu_delete($chave);
        }
    }

    /** Apaga todos os contadores (usado pelos testes). */
    public static function zerar(): void
    {
        self::$memoria = [];
        if (self::usaApcu()) {
            apcu_delete(new \APCUIterator('/^' . preg_quote(self::PREFIXO, '/') . '/'));
        }
    }

    /** @return array<string, int> chave do contador => máximo de falhas permitido */
    private static function limites(string $email, string $ip): array
    {
        $email = mb_strtolower(trim($email));
        return [
            self::PREFIXO . 'conta:' . hash('sha256', $email . '|' . $ip) => self::MAXIMO_POR_CONTA,
            self::PREFIXO . 'ip:' . hash('sha256', $ip) => self::MAXIMO_POR_IP,
        ];
    }

    /** @return array{falhas: int, inicio: int}|null */
    private static function ler(string $chave, int $agora): ?array
    {
        $registro = self::$memoria[$chave] ?? null;
        if (self::usaApcu()) {
            $valor = apcu_fetch($chave, $achou);
            $registro = $achou && is_array($valor) ? $valor : null;
        }
        if ($registro === null || $agora - $registro['inicio'] >= self::JANELA_SEGUNDOS) {
            return null; // nunca errou ou a janela de 15 minutos já acabou
        }
        return $registro;
    }

    /** @param array{falhas: int, inicio: int} $registro */
    private static function gravar(string $chave, array $registro): void
    {
        self::$memoria[$chave] = $registro;
        if (self::usaApcu()) {
            apcu_store($chave, $registro, self::JANELA_SEGUNDOS);
        }
    }

    private static function usaApcu(): bool
    {
        return function_exists('apcu_enabled') && apcu_enabled();
    }
}
