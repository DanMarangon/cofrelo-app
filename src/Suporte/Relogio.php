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
        return self::$fixo?->setTime(0, 0) ?? new DateTimeImmutable('today');
    }

    /** Data e hora de agora (os testes podem fixar também a hora, como em '2026-11-26 10:00:00'). */
    public static function agora(): DateTimeImmutable
    {
        return self::$fixo ?? new DateTimeImmutable('now');
    }

    public static function fixar(?string $data): void
    {
        self::$fixo = $data === null ? null : new DateTimeImmutable($data);
    }
}
