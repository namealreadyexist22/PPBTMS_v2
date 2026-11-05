<?php

namespace App\Enums;

enum BiddingStatus: string
{
    case Ongoing = 'ongoing';       // numbered when the BAC creates it; going through its steps
    case Cancelled = 'cancelled';   // its PR / JR lines are free for another bidding; the number is not reused

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Ongoing   => 'primary',
            self::Cancelled => 'danger',
        };
    }
}
