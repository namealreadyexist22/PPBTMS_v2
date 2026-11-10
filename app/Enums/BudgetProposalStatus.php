<?php

namespace App\Enums;

/** Budget Proposal: the offices prepare it, submit it; the evaluator gives the PAP codes and evaluates it, or returns it. */
enum BudgetProposalStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Returned = 'returned';
    case Evaluated = 'evaluated';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** The offices may still change their PAPs, budget lines and targets */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Returned], true);
    }

    /** Its budget lines hold budget (like a submitted or approved PPMP) */
    public function holdsBudget(): bool
    {
        return in_array($this, [self::Submitted, self::Evaluated], true);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Draft     => 'bg-secondary',
            self::Submitted => 'bg-primary',
            self::Returned  => 'bg-warning text-dark',
            self::Evaluated => 'bg-success',
        };
    }
}
