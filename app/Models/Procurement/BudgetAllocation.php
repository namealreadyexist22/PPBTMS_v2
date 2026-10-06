<?php

namespace App\Models\Procurement;

use App\Enums\FundGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Approved budget of a department (a unit of type department in the organization tree) for a
 * fiscal year and fund (COB / SIDA), CO and MOOE together. Every unit under it shares it.
 */
class BudgetAllocation extends Model
{
    protected $fillable = ['fiscal_year', 'department_id', 'fund_group', 'amount', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return [
            'fiscal_year' => 'integer',
            'fund_group'  => FundGroup::class,
            'amount'      => 'decimal:2',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'department_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(BudgetAllocationChange::class)->latest('id');
    }
}
