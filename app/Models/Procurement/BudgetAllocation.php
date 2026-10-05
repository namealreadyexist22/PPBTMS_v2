<?php

namespace App\Models\Procurement;

use App\Enums\FundGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Approved budget of an office for a fiscal year and fund (COB / SIDA), in MOOE and CO.
 * It caps the PPMPs of the office and everything under it.
 */
class BudgetAllocation extends Model
{
    protected $fillable = ['fiscal_year', 'office_id', 'fund_group', 'mooe_amount', 'co_amount', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return [
            'fiscal_year' => 'integer',
            'fund_group'  => FundGroup::class,
            'mooe_amount' => 'decimal:2',
            'co_amount'   => 'decimal:2',
        ];
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(BudgetAllocationChange::class)->latest('id');
    }
}
