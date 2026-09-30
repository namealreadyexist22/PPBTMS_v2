<?php

namespace App\Support;

/**
 * Peso amounts as integer centavos, so budget checks never suffer float drift.
 */
final class Money
{
    public static function toCents(string|int|float|null $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    public static function fromCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    public static function format(string|int|float|null $amount): string
    {
        return number_format((float) $amount, 2);
    }
}
