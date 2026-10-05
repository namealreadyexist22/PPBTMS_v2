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

    /** Stored in users.categories: 1 = Luzon/Mindanao, 2 = Visayas. */
    public static function fromCategory(mixed $category): self
    {
        return (int) $category === 2 ? self::Vis : self::Lm;
    }

    public function category(): int
    {
        return $this === self::Vis ? 2 : 1;
    }

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
