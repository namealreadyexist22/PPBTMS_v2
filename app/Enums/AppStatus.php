<?php

namespace App\Enums;

/** APP workflow: BAC Secretariat prepares -> BAC Chair recommends -> HOPE approves. */
enum AppStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';       // waiting for the BAC Chair's recommendation
    case Recommended = 'recommended';   // waiting for the HOPE's approval
    case Approved = 'approved';
    case Superseded = 'superseded';     // replaced by an updated version

    public function label(): string
    {
        return match ($this) {
            self::Submitted   => 'For Recommendation',
            self::Recommended => 'For Approval',
            default           => ucfirst($this->value),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft       => 'secondary',
            self::Submitted   => 'primary',
            self::Recommended => 'info',
            self::Approved    => 'success',
            self::Superseded  => 'dark',
        };
    }
}
