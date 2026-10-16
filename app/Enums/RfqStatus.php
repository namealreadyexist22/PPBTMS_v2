<?php

namespace App\Enums;

enum RfqStatus: string
{
    case Draft = 'draft';           // items and terms still being put together
    case Issued = 'issued';         // numbered and sent out for quotations
    case Cancelled = 'cancelled';   // its PR / JR lines are free for another RFQ

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft     => 'secondary',
            self::Issued    => 'success',
            self::Cancelled => 'danger',
        };
    }
}
