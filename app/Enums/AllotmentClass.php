<?php

namespace App\Enums;

/** Budget class of a PPMP project; the APP splits estimated budgets into MOOE and CO. */
enum AllotmentClass: string
{
    case Mooe = 'mooe';
    case Co = 'co';

    public function label(): string
    {
        return match ($this) {
            self::Mooe => 'MOOE',
            self::Co   => 'Capital Outlay (CO)',
        };
    }

    public function short(): string
    {
        return match ($this) {
            self::Mooe => 'MOOE',
            self::Co   => 'CO',
        };
    }
}
