<?php

namespace App\Enums;

enum PpmpType: string
{
    case Indicative = 'indicative';
    case Final = 'final';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
