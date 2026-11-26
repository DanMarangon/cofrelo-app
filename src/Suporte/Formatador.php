<?php

declare(strict_types=1);

namespace Cofrelo\Suporte;

use DateTimeImmutable;

/** Formatação no padrão brasileiro, usada nas páginas geradas pelo PHP e na exportação em CSV. */
final class Formatador
{
    private const MESES = [
        'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
        'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro',
    ];

    public static function moeda(float $valor): string
    {
        $sinal = $valor < 0 ? '-' : '';
        return $sinal . 'R$ ' . number_format(abs($valor), 2, ',', '.');
    }

    public static function numero(float $valor, int $casas = 2): string
    {
        return number_format($valor, $casas, ',', '.');
    }

    /** 2026-11-18 vira 18/11/2026. */
    public static function data(string $iso): string
    {
        $data = DateTimeImmutable::createFromFormat('!Y-m-d', substr($iso, 0, 10));
        return $data ? $data->format('d/m/Y') : $iso;
    }

    public static function nomeMes(int $mes): string
    {
        return self::MESES[$mes - 1] ?? '';
    }

    /** 2026-11 vira "Novembro de 2026". */
    public static function mesAno(string $anoMes): string
    {
        $partes = explode('-', $anoMes);
        $ano = (int) ($partes[0] ?? 0);
        $mes = (int) ($partes[1] ?? 1);
        return self::capitalizar(self::nomeMes($mes)) . ' de ' . $ano;
    }

    /** 6 vira "Jun". */
    public static function mesCurto(int $mes): string
    {
        return self::capitalizar(mb_substr(self::nomeMes($mes), 0, 3));
    }

    public static function capitalizar(string $texto): string
    {
        return mb_strtoupper(mb_substr($texto, 0, 1)) . mb_substr($texto, 1);
    }
}
