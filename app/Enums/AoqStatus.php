<?php

namespace App\Enums;

enum AoqStatus: string
{
    case Draft = 'draft';       // quotations being recorded
    case Awarded = 'awarded';   // finalized: each item awarded to its lowest responsive quote

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft   => 'secondary',
            self::Awarded => 'success',
        };
    }
}
