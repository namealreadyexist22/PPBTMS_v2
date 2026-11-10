<?php

namespace App\Models\Procurement;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A PAP used by a section PPMP: the link to the PAP in its department's Budget Proposal (which has
 * the code and title), with this PPMP's projects under it. PRs are charged to it.
 *
 * @property-read ?string $code  the Budget Proposal PAP's code (null until the evaluator gives it)
 * @property-read string $title
 */
class PpmpPap extends Model
{
    protected $fillable = ['ppmp_id', 'budget_pap_id', 'sort_order'];

    /** Code and title come from it, everywhere a PPMP PAP is shown */
    protected $with = ['budgetPap'];

    public function ppmp(): BelongsTo
    {
        return $this->belongsTo(Ppmp::class);
    }

    public function budgetPap(): BelongsTo
    {
        return $this->belongsTo(BudgetPap::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PpmpItem::class)->orderBy('sort_order');
    }

    public function getCodeAttribute(): ?string
    {
        return $this->budgetPap?->code;
    }

    public function getTitleAttribute(): string
    {
        return (string) $this->budgetPap?->title;
    }

    /** "26-05012-01 - ICT Infrastructure Management" (just the title while it has no code) */
    public function label(): string
    {
        return $this->budgetPap?->label() ?? '';
    }
}
