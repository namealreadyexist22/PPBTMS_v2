<?php

namespace App\Enums;

enum PpmpStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Returned = 'returned';
    case Approved = 'approved';
    case Superseded = 'superseded';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** Office can still add/edit/remove lines. */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Returned], true);
    }
}
