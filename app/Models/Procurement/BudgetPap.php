<?php

namespace App\Models\Procurement;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A PAP (Program / Activity / Project) of an office in its department's Budget Proposal, e.g.
 * "26-05012-02 ICT Infrastructure Management". The office writes the title; the evaluator gives the code.
 */
class BudgetPap extends Model
{
    protected $fillable = ['budget_proposal_id', 'office_id', 'code', 'title', 'sort_order'];

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(BudgetProposal::class, 'budget_proposal_id');
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    /** Performance indicators with quarterly targets (Form A) */
    public function indicators(): HasMany
    {
        return $this->hasMany(BudgetPapIndicator::class)->orderBy('sort_order')->orderBy('id');
    }

    /** MOOE it needs that is not procured (travel, training, ...) */
    public function budgetLines(): HasMany
    {
        return $this->hasMany(BudgetLine::class)->orderBy('sort_order')->orderBy('id');
    }

    /** Where PPMPs use it */
    public function ppmpPaps(): HasMany
    {
        return $this->hasMany(PpmpPap::class);
    }

    /** "26-05012-02 - ICT Infrastructure Management", or just the title before the evaluator gives the code */
    public function label(): string
    {
        return $this->code ? "{$this->code} - {$this->title}" : $this->title;
    }
}
