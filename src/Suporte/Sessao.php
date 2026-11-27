<?php

declare(strict_types=1);

namespace Cofrelo\Suporte;

use Cofrelo\Http\ErroHttp;
use Cofrelo\Http\Requisicao;

/**
 * Login por sessão e proteção contra CSRF.
 *
 * O cookie da sessão é HttpOnly (o JavaScript não lê) e SameSite=Lax. Toda requisição que altera dados
 * precisa mandar o token CSRF, no cabeçalho X-CSRF-Token (JavaScript) ou no campo _csrf (formulários).
 */
final class Sessao
{
    public static function iniciar(): void
    {
        if (PHP_SAPI === 'cli' || session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION ??= [];
            return;
        }
        session_name('cofrelo_sessao');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        ]);
        session_start();
    }

    public static function usuarioId(): ?int
    {
        $id = $_SESSION['usuario_id'] ?? null;
        return is_int($id) ? $id : null;
    }

    public static function usuarioObrigatorio(): int
    {
        return self::usuarioId() ?? throw ErroHttp::naoAutenticado();
    }

    public static function entrar(int $usuarioId): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true); // novo id de sessão evita fixação de sessão
        }
        $_SESSION['usuario_id'] = $usuarioId;
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    public static function sair(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            $parametros = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 3600,
                'path' => $parametros['path'],
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_destroy();
        }
    }

    public static function tokenCsrf(): string
    {
        if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function exigirCsrf(Requisicao $requisicao): void
    {
        $enviado = $requisicao->cabecalho('x-csrf-token') ?? (string) ($requisicao->corpo['_csrf'] ?? '');
        if (!is_string($enviado) || !hash_equals(self::tokenCsrf(), $enviado)) {
            throw ErroHttp::proibido('Sua sessão expirou ou a página está desatualizada. Recarregue e tente de novo.');
        }
    }

    /** Mensagem exibida uma única vez na próxima página (ex.: "Perfil atualizado"). */
    public static function avisar(string $tipo, string $texto): void
    {
        $_SESSION['avisos'][] = ['tipo' => $tipo, 'texto' => $texto];
    }

    /** @return array<int, array{tipo: string, texto: string}> */
    public static function retirarAvisos(): array
    {
        $avisos = $_SESSION['avisos'] ?? [];
        unset($_SESSION['avisos']);
        return is_array($avisos) ? $avisos : [];
    }

    /** Guarda o que foi digitado para preencher de novo o formulário depois de um erro. */
    public static function lembrar(array $campos): void
    {
        $_SESSION['antigos'] = $campos;
    }

    /** @return array<string, string> */
    public static function retirarLembrados(): array
    {
        $antigos = $_SESSION['antigos'] ?? [];
        unset($_SESSION['antigos']);
        return is_array($antigos) ? $antigos : [];
    }
}
