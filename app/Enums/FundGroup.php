<?php

namespace App\Enums;

/**
 * Which APP a fund source belongs to: SIDA-funded projects (including GAA continuing
 * appropriations for SIDA) have their own APP, separate from the regular one.
 */
enum FundGroup: string
{
    case Regular = 'regular';
    case Sida = 'sida';

    public function label(): string
    {
        return match ($this) {
            self::Regular => 'COB',
            self::Sida    => 'SIDA',
        };
    }
}
