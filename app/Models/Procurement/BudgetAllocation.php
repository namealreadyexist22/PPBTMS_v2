<?php

namespace App\Models\Procurement;

use App\Enums\FundGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Approved budget of a department for a fiscal year and fund (COB / SIDA), CO and MOOE together.
 * Every office in the department shares it.
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
        return $this->belongsTo(Department::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(BudgetAllocationChange::class)->latest('id');
    }
}
