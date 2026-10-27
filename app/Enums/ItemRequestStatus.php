<?php

namespace App\Enums;

enum ItemRequestStatus: string
{
    case Pending = 'pending';       // with the procuring office
    case Approved = 'approved';     // the whole quantity
    case Partial = 'partial';       // part of it
    case Declined = 'declined';     // none
    case Cancelled = 'cancelled';   // withdrawn by the requesting office before the decision

    public function label(): string
    {
        return match ($this) {
            self::Partial => 'Partly approved',
            default       => ucfirst($this->value),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending   => 'warning',
            self::Approved  => 'success',
            self::Partial   => 'info',
            self::Declined  => 'danger',
            self::Cancelled => 'secondary',
        };
    }
}
