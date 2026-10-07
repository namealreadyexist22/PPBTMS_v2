<?php

namespace App\Enums;

/** Purchase Request (goods) or Job Request (services, repairs, job orders). */
enum RequestKind: string
{
    case Pr = 'pr';
    case Jr = 'jr';

    public function label(): string
    {
        return match ($this) {
            self::Pr => 'Purchase Request',
            self::Jr => 'Job Request',
        };
    }

    public function short(): string
    {
        return strtoupper($this->value);
    }
}
