<?php

namespace App\Enums;

/** How an RFQ's quotations are evaluated and awarded (set on the RFQ, told to the suppliers). */
enum AwardBasis: string
{
    case Item = 'item';   // each item to its lowest offer that meets the specs; suppliers may quote some items
    case Lot = 'lot';     // all items as one lot to the lowest lot total; suppliers must quote every item

    public function label(): string
    {
        return match ($this) {
            self::Item => 'Per item',
            self::Lot  => 'Per lot (all items)',
        };
    }
}
