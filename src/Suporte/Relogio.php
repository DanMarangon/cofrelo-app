<?php

declare(strict_types=1);

namespace Cofrelo\Suporte;

use DateTimeImmutable;

/** Data de hoje do sistema. Os testes podem fixar um dia para os resultados não mudarem com o calendário. */
final class Relogio
{
    private static ?DateTimeImmutable $fixo = null;

    public static function hoje(): DateTimeImmutable
    {
        return self::$fixo ?? new DateTimeImmutable('today');
    }

    public static function fixar(?string $data): void
    {
        self::$fixo = $data === null ? null : new DateTimeImmutable($data);
    }
}
