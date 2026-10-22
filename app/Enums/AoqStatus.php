<?php

namespace App\Enums;

enum AoqStatus: string
{
    case Draft = 'draft';                     // the BAC records the quotations
    case ForEvaluation = 'for_evaluation';    // with the assigned TWG: offers checked against the specs
    case Evaluated = 'evaluated';             // TWG done: the BAC awards
    case Awarded = 'awarded';                 // finalized: each item awarded to its lowest responsive quote

    public function label(): string
    {
        return match ($this) {
            self::Draft         => 'Draft',
            self::ForEvaluation => 'For TWG Evaluation',
            self::Evaluated     => 'Evaluated',
            self::Awarded       => 'Awarded',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft         => 'secondary',
            self::ForEvaluation => 'warning',
            self::Evaluated     => 'info',
            self::Awarded       => 'success',
        };
    }
}
