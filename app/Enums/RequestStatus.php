<?php

namespace App\Enums;

enum RequestStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';     // final: PPMP budget charged, printed for signature
    case Superseded = 'superseded';   // replaced by a submitted revision
    case Cancelled = 'cancelled';     // PPMP budget given back

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft      => 'secondary',
            self::Submitted  => 'success',
            self::Superseded => 'dark',
            self::Cancelled  => 'danger',
        };
    }
}
