<?php

namespace App\Support;

/**
 * Quantities as integer hundredths of a unit (two decimals, as stored), so
 * quantity checks never suffer float drift. Same idea as Money.
 */
final class Quantity
{
    public static function toHundredths(string|int|float|null $quantity): int
    {
        return (int) round(((float) $quantity) * 100);
    }

    public static function fromHundredths(int $hundredths): string
    {
        return number_format($hundredths / 100, 2, '.', '');
    }

    /** E.g. "2.5 piece", "8 unit". */
    public static function text(int $hundredths, ?string $unit = null): string
    {
        return trim(rtrim(rtrim(number_format($hundredths / 100, 2), '0'), '.') . ' ' . $unit);
    }
}
