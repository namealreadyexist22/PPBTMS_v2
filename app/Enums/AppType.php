<?php

namespace App\Enums;

/** The APP form's header boxes: INDICATIVE / FINAL / UPDATED [Version No. n]. */
enum AppType: string
{
    case Indicative = 'indicative';
    case Final = 'final';
    case Updated = 'updated';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
