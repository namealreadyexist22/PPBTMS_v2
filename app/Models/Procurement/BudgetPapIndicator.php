<?php

namespace App\Models\Procurement;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Performance indicator of a PAP with its quarterly physical targets (COB Form A, Performance Contract).
 * description = the brief description of the activity it measures; blank = the same as the indicator above.
 */
class BudgetPapIndicator extends Model
{
    protected $fillable = ['budget_pap_id', 'description', 'indicator', 'q1', 'q2', 'q3', 'q4', 'total', 'sort_order'];

    public function pap(): BelongsTo
    {
        return $this->belongsTo(BudgetPap::class, 'budget_pap_id');
    }
}
