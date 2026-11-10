<?php

namespace App\Models\Procurement;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Budget-only line of a PAP: MOOE that is not procured (travel, training, representation, subsidies, ...).
 * Counts toward the department's budget once its Budget Proposal is submitted; never goes to the APP, PRs or RFQs.
 */
class BudgetLine extends Model
{
    protected $fillable = ['budget_pap_id', 'expense_class_id', 'location_id', 'fund_source_id', 'description', 'amount', 'remarks', 'sort_order'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function pap(): BelongsTo
    {
        return $this->belongsTo(BudgetPap::class, 'budget_pap_id');
    }

    public function expenseClass(): BelongsTo
    {
        return $this->belongsTo(ExpenseClass::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function fundSource(): BelongsTo
    {
        return $this->belongsTo(FundSource::class);
    }
}
