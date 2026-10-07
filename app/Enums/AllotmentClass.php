<?php

namespace App\Enums;

/** Allotment class of a PPMP project: MOOE, Capital Outlay or Semi-Expendable. */
enum AllotmentClass: string
{
    case Mooe = 'mooe';
    case Co = 'co';
    case Semi = 'semi';

    public function label(): string
    {
        return match ($this) {
            self::Mooe => 'MOOE',
            self::Co   => 'Capital Outlay (CO)',
            self::Semi => 'Semi-Expendable',
        };
    }

    public function short(): string
    {
        return match ($this) {
            self::Mooe => 'MOOE',
            self::Co   => 'CO',
            self::Semi => 'Semi-Exp.',
        };
    }
}
