<?php

declare(strict_types=1);

namespace Cofrelo\Suporte;

use Cofrelo\Http\Resposta;

/**
 * Cabeçalhos de segurança em todas as respostas.
 * A Content-Security-Policy só permite scripts, estilos e fontes do próprio site, o que bloqueia
 * scripts injetados (XSS) e impede que o Cofrelo seja aberto dentro de um iframe de outro site.
 */
final class CabecalhosSeguranca
{
    private const CSP = "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; "
        . "font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'";

    public static function aplicar(Resposta $resposta): Resposta
    {
        $resposta = $resposta
            ->comCabecalho('Content-Security-Policy', self::CSP)
            ->comCabecalho('X-Content-Type-Options', 'nosniff')
            ->comCabecalho('X-Frame-Options', 'DENY')
            ->comCabecalho('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->comCabecalho('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // Dados financeiros não devem ficar guardados no cache do navegador nem de proxies.
        if ($resposta->cabecalho('Cache-Control') === null) {
            $resposta = $resposta->comCabecalho('Cache-Control', 'no-store');
        }
        return $resposta;
    }
}
