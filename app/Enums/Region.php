<?php

namespace App\Enums;

/**
 * Procurement region. Users belong to one; their PPMPs follow it into that
 * region's Division PPMP and APP (LM -> BAC, Visayas -> Regional BAC).
 */
enum Region: string
{
    case Lm = 'lm';
    case Vis = 'vis';

    public function label(): string
    {
        return match ($this) {
            self::Lm  => 'Luzon/Mindanao',
            self::Vis => 'Visayas',
        };
    }

    public function short(): string
    {
        return match ($this) {
            self::Lm  => 'LM',
            self::Vis => 'VIS',
        };
    }

    /** The committee that consolidates this region's PPMPs into its APP. */
    public function bac(): string
    {
        return match ($this) {
            self::Lm  => 'Bids and Awards Committee',
            self::Vis => 'Regional Bids and Awards Committee',
        };
    }
}
